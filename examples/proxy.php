<?php
/*
 * Proxy PHP -> Ollama (endpoint OpenAI-compatible /v1/chat/completions), con failover.
 * Version SIN streaming (mas simple; el widget la soporta igual).
 * Mismo patron que produccion: primario Ollama Cloud gpt-oss:120b + failover opcional.
 *
 * Podes configurar por variables de entorno O editando los valores por defecto abajo.
 * Subi este archivo + context.md a tu server (ej: /public_html/chat/proxy.php).
 */

header('Content-Type: application/json; charset=utf-8');
$ALLOWED = getenv('ALLOWED_ORIGIN') ?: 'https://tudominio.com'; // en pruebas: '*'
header("Access-Control-Allow-Origin: $ALLOWED");
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// --- Proveedores: primario (Ollama Cloud) + failover opcional ---
$PROVIDERS = [[
  'base'  => getenv('OLLAMA_BASE_URL') ?: 'https://ollama.com/v1',
  'model' => getenv('OLLAMA_MODEL')    ?: 'gpt-oss:120b',
  'key'   => getenv('OLLAMA_API_KEY')  ?: 'PON_AQUI_TU_API_KEY', // key de ollama.com/settings/keys
]];
if (getenv('FAILOVER_BASE_URL')) {
  $PROVIDERS[] = [
    'base'  => getenv('FAILOVER_BASE_URL'),
    'model' => getenv('FAILOVER_MODEL') ?: 'gpt-oss:20b',
    'key'   => getenv('FAILOVER_API_KEY') ?: '',
  ];
}

$SYSTEM = @file_get_contents(__DIR__ . '/context.md')
          ?: 'Sos un asistente de ventas amable y conciso. Responde en el idioma del cliente.';

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$message = isset($body['message']) ? mb_substr(trim($body['message']), 0, 1500) : '';
$history = (isset($body['history']) && is_array($body['history'])) ? array_slice($body['history'], -12) : [];
if ($message === '') { http_response_code(400); echo json_encode(['error' => 'Falta message']); exit; }

$messages = [['role' => 'system', 'content' => $SYSTEM]];
foreach ($history as $m) {
  if (isset($m['role'], $m['content']) && in_array($m['role'], ['user', 'assistant'], true)) {
    $messages[] = ['role' => $m['role'], 'content' => (string) $m['content']];
  }
}
$messages[] = ['role' => 'user', 'content' => $message];

function ask($p, $messages) {
  $payload = json_encode([
    'model' => $p['model'], 'messages' => $messages, 'stream' => false, 'temperature' => 0.4,
  ]);
  $headers = ['Content-Type: application/json'];
  if (!empty($p['key'])) $headers[] = 'Authorization: Bearer ' . $p['key'];
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
  if ($res === false || $code >= 400) return null;
  $data = json_decode($res, true);
  return $data['choices'][0]['message']['content'] ?? null;
}

foreach ($PROVIDERS as $p) {
  $reply = ask($p, $messages);
  if ($reply !== null && $reply !== '') { echo json_encode(['reply' => $reply]); exit; }
}
echo json_encode(['reply' => 'Disculpa, el asistente no esta disponible ahora. Escribinos por WhatsApp y te ayudamos.']);
