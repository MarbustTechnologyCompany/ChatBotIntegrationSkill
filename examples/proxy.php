<?php
/*
 * Proxy PHP -> Ollama (endpoint OpenAI-compatible /v1/chat/completions), con failover.
 * Version SIN streaming (mas simple; el widget la soporta igual).
 * Mismo patron que produccion: primario Ollama Cloud gpt-oss:120b + failover opcional.
 *
 * QUE SUBIR AL SERVIDOR:
 *   /public_html/chat/proxy.php     <- este archivo
 *   /public_html/chat/context.md    <- el prompt del negocio
 *   /public_html/chat/.htaccess     <- IMPRESCINDIBLE: bloquea context.md por web
 *   ../chatbot-config.ini           <- la API key, FUERA del docroot (recomendado)
 *
 * La configuracion se busca en este orden:
 *   1. chatbot-config.ini un nivel ARRIBA del docroot  <- lo mas seguro
 *   2. variables de entorno
 *   3. los valores por defecto de abajo                <- solo para probar
 */

// ---------------------------------------------------------------------------
// Configuracion
// ---------------------------------------------------------------------------

/**
 * Lee la config de un .ini que vive FUERA de la carpeta publica.
 *
 * Por que importa: si dejas la API key dentro de proxy.php y algun dia el
 * servidor deja de ejecutar PHP (modulo caido, un backup que queda como
 * proxy.php.bak, una migracion mal hecha), el archivo se sirve como TEXTO y la
 * key queda publicada. Fuera del docroot eso no puede pasar.
 */
function cfg(string $key, string $default = ''): string
{
    static $ini = null;
    if ($ini === null) {
        $path = dirname(__DIR__) . '/chatbot-config.ini';
        $parsed = is_file($path) ? parse_ini_file($path) : false;
        $ini = is_array($parsed) ? $parsed : [];
    }

    if (isset($ini[$key]) && trim((string) $ini[$key]) !== '') {
        return trim((string) $ini[$key]);
    }

    $env = getenv($key);
    return $env !== false && trim($env) !== '' ? trim($env) : $default;
}

// Origenes permitidos, separados por coma. Poner el dominio real, no '*'.
$ALLOWED_ORIGINS = array_filter(array_map('trim', explode(',', cfg('ALLOWED_ORIGINS', 'https://tudominio.com'))));

// Cuantos mensajes puede mandar una misma IP por hora. Es lo unico que de
// verdad protege tu cuota gratis: el chequeo de Origin lo puede falsear
// cualquiera con curl, solo frena el abuso desde otro sitio web.
$RATE_LIMIT = (int) cfg('RATE_LIMIT_PER_HOUR', '30');

// ---------------------------------------------------------------------------
// CORS + metodo
// ---------------------------------------------------------------------------

header('Content-Type: application/json; charset=utf-8');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$originAllowed = in_array($origin, $ALLOWED_ORIGINS, true);

if ($originAllowed) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Vary: Origin');
}
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$originAllowed) {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

// ---------------------------------------------------------------------------
// Rate limit por IP (archivo temporal, sin base de datos)
// ---------------------------------------------------------------------------

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = sys_get_temp_dir() . '/chatbot-' . hash('sha256', $ip) . '.json';
$now = time();
$hits = [];

if (is_file($rateFile)) {
    $saved = json_decode((string) file_get_contents($rateFile), true);
    if (is_array($saved)) {
        $hits = array_filter($saved, static fn ($t) => is_int($t) && $t > $now - 3600);
    }
}

if (count($hits) >= $RATE_LIMIT) {
    http_response_code(429);
    echo json_encode(['reply' => 'Estas mandando muchos mensajes seguidos. Intenta de nuevo en un rato.']);
    exit;
}

$hits[] = $now;
file_put_contents($rateFile, json_encode(array_values($hits)), LOCK_EX);

// ---------------------------------------------------------------------------
// Proveedores: primario (Ollama Cloud) + failover opcional
// ---------------------------------------------------------------------------

$PROVIDERS = [[
    'base'  => cfg('OLLAMA_BASE_URL', 'https://ollama.com/v1'),
    'model' => cfg('OLLAMA_MODEL', 'gpt-oss:120b'),
    'key'   => cfg('OLLAMA_API_KEY'), // key de ollama.com/settings/keys
]];

if (cfg('FAILOVER_BASE_URL') !== '') {
    $PROVIDERS[] = [
        'base'  => cfg('FAILOVER_BASE_URL'),
        'model' => cfg('FAILOVER_MODEL', 'gpt-oss:20b'),
        'key'   => cfg('FAILOVER_API_KEY'),
    ];
}

if ($PROVIDERS[0]['key'] === '') {
    http_response_code(500);
    echo json_encode(['error' => 'missing_api_key']);
    exit;
}

// ---------------------------------------------------------------------------
// Prompt del negocio
// ---------------------------------------------------------------------------

$SYSTEM = @file_get_contents(__DIR__ . '/context.md')
    ?: 'Eres un asistente de ventas amable y conciso. Responde en el idioma del cliente.';

/*
 * Datos que cambian (precios, cifras del año, stock): en vez de reescribir el
 * prompt cada vez, deja un token y sustituyelo aca con el dato vivo. Asi el bot
 * nunca contradice a la web. En context.md pondrias, por ejemplo, la linea:
 *   - {{INDICADORES}}
 *
 * if (str_contains($SYSTEM, '{{INDICADORES}}')) {
 *     $SYSTEM = str_replace('{{INDICADORES}}', consultaTusDatos(), $SYSTEM);
 * }
 */

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$message = isset($body['message']) ? mb_substr(trim($body['message']), 0, 1500) : '';
$history = (isset($body['history']) && is_array($body['history'])) ? array_slice($body['history'], -12) : [];

if ($message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Falta message']);
    exit;
}

$messages = [['role' => 'system', 'content' => $SYSTEM]];
foreach ($history as $m) {
    if (isset($m['role'], $m['content']) && in_array($m['role'], ['user', 'assistant'], true)) {
        $messages[] = ['role' => $m['role'], 'content' => (string) $m['content']];
    }
}
$messages[] = ['role' => 'user', 'content' => $message];

// ---------------------------------------------------------------------------
// Llamada al modelo
// ---------------------------------------------------------------------------

function ask(array $p, array $messages): ?string
{
    $payload = json_encode([
        'model' => $p['model'], 'messages' => $messages, 'stream' => false, 'temperature' => 0.4,
    ]);
    $headers = ['Content-Type: application/json'];
    if (!empty($p['key'])) {
        $headers[] = 'Authorization: Bearer ' . $p['key'];
    }

    $ch = curl_init(rtrim($p['base'], '/') . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 90,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false || $code >= 400) {
        return null;
    }

    $data = json_decode($res, true);
    return $data['choices'][0]['message']['content'] ?? null;
}

foreach ($PROVIDERS as $p) {
    $reply = ask($p, $messages);
    if ($reply !== null && trim($reply) !== '') {
        echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

echo json_encode(['reply' => 'Disculpa, el asistente no esta disponible ahora. Escribenos por WhatsApp y te ayudamos.'], JSON_UNESCAPED_UNICODE);
