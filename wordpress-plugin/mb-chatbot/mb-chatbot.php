<?php
/**
 * Plugin Name: Chatbot IA (Ollama)
 * Plugin URI:  https://github.com/MarbustTechnologyCompany/ChatBotIntegrationSkill
 * Description: Chatbot de IA de ventas para tu sitio. Se conecta a Ollama Cloud (gpt-oss:120b, gratis) con tu propia clave, o a tu propio backend. Contexto de venta configurable, memoria de conversacion y widget flotante.
 * Version:     1.0.0
 * Author:      MarAntBQ (Marbust Technology Company)
 * Author URI:  https://marbust.com
 * License:     MIT
 * Text Domain: mb-chatbot
 */

if (!defined('ABSPATH')) exit; // no acceso directo

define('MBCHAT_VER', '1.0.0');
define('MBCHAT_OPT', 'mbchat_settings');

function mbchat_defaults() {
  return [
    'enabled'      => 1,
    'mode'         => 'ollama',                 // 'ollama' | 'backend'
    'brand'        => 'Asistente Virtual',
    'welcome'      => 'Hola! En que puedo ayudarte?',
    'accent'       => '#2563eb',
    // modo "ollama directo"
    'ollama_base'  => 'https://ollama.com/v1',
    'ollama_model' => 'gpt-oss:120b',
    'ollama_key'   => '',
    'context'      => "Sos el asistente de ventas de MI NEGOCIO. Responde en el idioma del cliente, breve y amable. Habla SOLO del negocio. NO inventes precios ni datos: si no sabes, deriva por WhatsApp. NO des soporte tecnico gratis: ofrece el servicio y cierra por WhatsApp. Enlaces siempre en markdown clicable.",
    // modo "backend externo"
    'backend_url'  => '',
    'site_key'     => '',
  ];
}

function mbchat_get($k) {
  $o = get_option(MBCHAT_OPT, []);
  $d = mbchat_defaults();
  return array_key_exists($k, (array) $o) ? $o[$k] : $d[$k];
}

/* ============ FRONTEND: cargar el widget ============ */
add_action('wp_enqueue_scripts', function () {
  if (!mbchat_get('enabled')) return;
  wp_enqueue_script('mbchat-widget', plugins_url('assets/widget.js', __FILE__), [], MBCHAT_VER, true);
  // Solo config PUBLICA al navegador (NUNCA la API key ni el contexto):
  wp_localize_script('mbchat-widget', 'MBCHAT', [
    'endpoint' => esc_url_raw(rest_url('mbchat/v1/message')),
    'brand'    => mbchat_get('brand'),
    'welcome'  => mbchat_get('welcome'),
    'accent'   => mbchat_get('accent'),
  ]);
});

/* ============ REST: el navegador postea aca (la key queda en el server) ============ */
add_action('rest_api_init', function () {
  register_rest_route('mbchat/v1', '/message', [
    'methods'             => 'POST',
    'callback'            => 'mbchat_handle',
    'permission_callback' => '__return_true', // chat publico
  ]);
});

function mbchat_rate_limited() {
  $ip  = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'x';
  $key = 'mbchat_rl_' . md5($ip);
  $n   = (int) get_transient($key);
  if ($n >= 20) return true;              // 20 mensajes/minuto por IP
  set_transient($key, $n + 1, 60);
  return false;
}

function mbchat_handle(WP_REST_Request $req) {
  if (mbchat_rate_limited()) {
    return new WP_REST_Response(['reply' => 'Demasiadas solicitudes, proba en un momento.'], 429);
  }
  $body    = $req->get_json_params();
  $message = isset($body['message']) ? mb_substr(trim((string) $body['message']), 0, 1500) : '';
  $history = (isset($body['history']) && is_array($body['history'])) ? array_slice($body['history'], -12) : [];
  if ($message === '') return new WP_REST_Response(['error' => 'Falta message'], 400);

  $reply = (mbchat_get('mode') === 'backend')
    ? mbchat_call_backend($message, $history)
    : mbchat_call_ollama($message, $history);

  if ($reply === null || $reply === '') {
    $reply = 'Disculpa, el asistente no esta disponible ahora. Escribinos por WhatsApp y te ayudamos.';
  }
  return new WP_REST_Response(['reply' => $reply], 200);
}

function mbchat_build_messages($message, $history) {
  $messages = [['role' => 'system', 'content' => mbchat_get('context')]];
  foreach ($history as $m) {
    if (isset($m['role'], $m['content']) && in_array($m['role'], ['user', 'assistant'], true)) {
      $messages[] = ['role' => $m['role'], 'content' => (string) $m['content']];
    }
  }
  $messages[] = ['role' => 'user', 'content' => $message];
  return $messages;
}

function mbchat_call_ollama($message, $history) {
  $headers = ['Content-Type' => 'application/json'];
  $key = mbchat_get('ollama_key');
  if ($key) $headers['Authorization'] = 'Bearer ' . $key;

  $res = wp_remote_post(rtrim(mbchat_get('ollama_base'), '/') . '/chat/completions', [
    'timeout' => 90,
    'headers' => $headers,
    'body'    => wp_json_encode([
      'model'       => mbchat_get('ollama_model'),
      'messages'    => mbchat_build_messages($message, $history),
      'stream'      => false,
      'temperature' => 0.4,
    ]),
  ]);
  if (is_wp_error($res) || wp_remote_retrieve_response_code($res) >= 400) return null;
  $data = json_decode(wp_remote_retrieve_body($res), true);
  return isset($data['choices'][0]['message']['content']) ? $data['choices'][0]['message']['content'] : null;
}

function mbchat_call_backend($message, $history) {
  $url = mbchat_get('backend_url');
  if (!$url) return null;
  $payload = ['message' => $message, 'history' => $history];
  if (mbchat_get('site_key')) $payload['site'] = mbchat_get('site_key');

  $res = wp_remote_post($url, [
    'timeout' => 90,
    'headers' => ['Content-Type' => 'application/json'],
    'body'    => wp_json_encode($payload),
  ]);
  if (is_wp_error($res) || wp_remote_retrieve_response_code($res) >= 400) return null;
  $data = json_decode(wp_remote_retrieve_body($res), true);
  // Acepta {reply:"..."} (nuestro backend) o formato OpenAI {choices:[{message:{content}}]}
  if (isset($data['reply'])) return $data['reply'];
  return isset($data['choices'][0]['message']['content']) ? $data['choices'][0]['message']['content'] : null;
}

/* ============ ADMIN: pagina de ajustes ============ */
add_action('admin_menu', function () {
  add_options_page('Chatbot IA', 'Chatbot IA', 'manage_options', 'mb-chatbot', 'mbchat_settings_page');
});
add_action('admin_init', function () {
  register_setting('mbchat_group', MBCHAT_OPT, 'mbchat_sanitize');
});

function mbchat_sanitize($in) {
  $d = mbchat_defaults(); $out = [];
  $out['enabled']      = empty($in['enabled']) ? 0 : 1;
  $out['mode']         = (isset($in['mode']) && in_array($in['mode'], ['ollama', 'backend'], true)) ? $in['mode'] : 'ollama';
  $out['brand']        = sanitize_text_field(isset($in['brand']) ? $in['brand'] : $d['brand']);
  $out['welcome']      = sanitize_text_field(isset($in['welcome']) ? $in['welcome'] : $d['welcome']);
  $out['accent']       = sanitize_hex_color(isset($in['accent']) ? $in['accent'] : $d['accent']);
  if (!$out['accent']) $out['accent'] = $d['accent'];
  $out['ollama_base']  = esc_url_raw(isset($in['ollama_base']) ? $in['ollama_base'] : $d['ollama_base']);
  $out['ollama_model'] = sanitize_text_field(isset($in['ollama_model']) ? $in['ollama_model'] : $d['ollama_model']);
  $out['ollama_key']   = trim(isset($in['ollama_key']) ? $in['ollama_key'] : '');
  $out['context']      = sanitize_textarea_field(isset($in['context']) ? $in['context'] : $d['context']);
  $out['backend_url']  = esc_url_raw(isset($in['backend_url']) ? $in['backend_url'] : '');
  $out['site_key']     = sanitize_text_field(isset($in['site_key']) ? $in['site_key'] : '');
  return $out;
}

function mbchat_field($k) { return MBCHAT_OPT . '[' . $k . ']'; }

function mbchat_settings_page() {
  if (!current_user_can('manage_options')) return;
  ?>
  <div class="wrap">
    <h1>Chatbot IA (Ollama)</h1>
    <p>Chatbot de ventas para tu sitio. Elegí <strong>Ollama directo</strong> (pones tu API key de <a href="https://ollama.com/settings/keys" target="_blank">ollama.com</a> y listo) o <strong>Backend externo</strong> (lo apuntas a tu propia API).</p>
    <form method="post" action="options.php">
      <?php settings_fields('mbchat_group'); ?>
      <table class="form-table" role="presentation">
        <tr><th scope="row">Activar</th><td><label><input type="checkbox" name="<?php echo esc_attr(mbchat_field('enabled')); ?>" value="1" <?php checked(mbchat_get('enabled'), 1); ?>> Mostrar el chatbot en el sitio</label></td></tr>
        <tr><th scope="row">Nombre / marca</th><td><input type="text" class="regular-text" name="<?php echo esc_attr(mbchat_field('brand')); ?>" value="<?php echo esc_attr(mbchat_get('brand')); ?>"></td></tr>
        <tr><th scope="row">Mensaje de bienvenida</th><td><input type="text" class="regular-text" name="<?php echo esc_attr(mbchat_field('welcome')); ?>" value="<?php echo esc_attr(mbchat_get('welcome')); ?>"></td></tr>
        <tr><th scope="row">Color (hex)</th><td><input type="text" name="<?php echo esc_attr(mbchat_field('accent')); ?>" value="<?php echo esc_attr(mbchat_get('accent')); ?>" placeholder="#2563eb"></td></tr>
        <tr><th scope="row">Modo</th><td>
          <select name="<?php echo esc_attr(mbchat_field('mode')); ?>">
            <option value="ollama" <?php selected(mbchat_get('mode'), 'ollama'); ?>>Ollama directo (self-contained)</option>
            <option value="backend" <?php selected(mbchat_get('mode'), 'backend'); ?>>Backend externo (mi API)</option>
          </select>
        </td></tr>
      </table>

      <h2>Modo "Ollama directo"</h2>
      <table class="form-table" role="presentation">
        <tr><th scope="row">Base URL</th><td><input type="text" class="regular-text" name="<?php echo esc_attr(mbchat_field('ollama_base')); ?>" value="<?php echo esc_attr(mbchat_get('ollama_base')); ?>"><p class="description">Ollama Cloud: <code>https://ollama.com/v1</code> · Local: <code>http://127.0.0.1:11434/v1</code></p></td></tr>
        <tr><th scope="row">Modelo</th><td><input type="text" class="regular-text" name="<?php echo esc_attr(mbchat_field('ollama_model')); ?>" value="<?php echo esc_attr(mbchat_get('ollama_model')); ?>"><p class="description">Recomendado: <code>gpt-oss:120b</code> (o <code>gpt-oss:20b</code> si aprieta el free tier)</p></td></tr>
        <tr><th scope="row">API Key</th><td><input type="password" class="regular-text" autocomplete="off" name="<?php echo esc_attr(mbchat_field('ollama_key')); ?>" value="<?php echo esc_attr(mbchat_get('ollama_key')); ?>"><p class="description">De <a href="https://ollama.com/settings/keys" target="_blank">ollama.com/settings/keys</a>. Se guarda en el servidor; NUNCA se envia al navegador.</p></td></tr>
        <tr><th scope="row">Contexto de venta</th><td><textarea class="large-text" rows="10" name="<?php echo esc_attr(mbchat_field('context')); ?>"><?php echo esc_textarea(mbchat_get('context')); ?></textarea><p class="description">Quien es el bot, que ofrece, precios, reglas (vende / no inventa / idioma del cliente).</p></td></tr>
      </table>

      <h2>Modo "Backend externo"</h2>
      <table class="form-table" role="presentation">
        <tr><th scope="row">Backend URL</th><td><input type="text" class="regular-text" name="<?php echo esc_attr(mbchat_field('backend_url')); ?>" value="<?php echo esc_attr(mbchat_get('backend_url')); ?>" placeholder="https://tu-api.com/chatbot/message"><p class="description">Recibe <code>{message, history, site}</code> y devuelve <code>{reply}</code>.</p></td></tr>
        <tr><th scope="row">Site key</th><td><input type="text" class="regular-text" name="<?php echo esc_attr(mbchat_field('site_key')); ?>" value="<?php echo esc_attr(mbchat_get('site_key')); ?>"><p class="description">Opcional. Se envia como <code>site</code> (para backends con contexto por sitio). En este modo el contexto vive en TU backend.</p></td></tr>
      </table>

      <?php submit_button(); ?>
    </form>
  </div>
  <?php
}
