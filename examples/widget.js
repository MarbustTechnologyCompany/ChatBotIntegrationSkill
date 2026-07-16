/*
 * Widget de chatbot embebible (vanilla JS, CERO dependencias).
 * Funciona con el proxy Node (streaming) Y con el proxy PHP (JSON simple).
 *
 * Uso: antes de </body> en tu sitio:
 *   <script>
 *     window.CHATBOT_API     = 'https://tudominio.com/chat';   // URL de tu proxy
 *     window.CHATBOT_NAME    = 'Asistente de Mi Negocio';
 *     window.CHATBOT_WELCOME = 'Hola! Soy el asistente virtual. En que te ayudo?';
 *     window.CHATBOT_ACCENT  = '#2563eb';                       // color de marca
 *   </script>
 *   <script src="widget.js" defer></script>
 */
(function () {
  var API = window.CHATBOT_API || 'http://localhost:8080/chat';
  var NAME = window.CHATBOT_NAME || 'Asistente';
  var WELCOME = window.CHATBOT_WELCOME || 'Hola! En que puedo ayudarte?';
  var ACCENT = window.CHATBOT_ACCENT || '#2563eb';
  var history = [];

  var css = ''
    + '.cbw-btn{position:fixed;bottom:20px;right:20px;width:56px;height:56px;border-radius:50%;background:' + ACCENT + ';color:#fff;border:none;cursor:pointer;box-shadow:0 6px 20px rgba(0,0,0,.25);font-size:24px;z-index:99999;display:flex;align-items:center;justify-content:center}'
    + '.cbw-panel{position:fixed;bottom:88px;right:20px;width:360px;max-width:calc(100vw - 40px);height:520px;max-height:calc(100vh - 120px);background:#fff;border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.28);display:none;flex-direction:column;overflow:hidden;z-index:99999;font-family:system-ui,-apple-system,sans-serif}'
    + '.cbw-panel.open{display:flex}'
    + '.cbw-head{background:' + ACCENT + ';color:#fff;padding:14px 16px;font-weight:600;font-size:15px;flex:0 0 auto}'
    + '.cbw-body{flex:1 1 auto;overflow-y:auto;padding:14px;background:#f7f8fa;display:flex;flex-direction:column;gap:10px}'
    + '.cbw-msg{max-width:80%;padding:9px 12px;border-radius:14px;font-size:14px;line-height:1.45;white-space:pre-wrap;word-wrap:break-word}'
    + '.cbw-user{align-self:flex-end;background:' + ACCENT + ';color:#fff;border-bottom-right-radius:4px}'
    + '.cbw-bot{align-self:flex-start;background:#fff;color:#111;border:1px solid #e5e7eb;border-bottom-left-radius:4px}'
    + '.cbw-foot{flex:0 0 auto;display:flex;gap:8px;padding:10px;border-top:1px solid #eee;background:#fff}'
    + '.cbw-foot input{flex:1;border:1px solid #d1d5db;border-radius:20px;padding:9px 14px;font-size:14px;outline:none}'
    + '.cbw-foot button{background:' + ACCENT + ';color:#fff;border:none;border-radius:20px;padding:0 16px;cursor:pointer;font-size:14px}'
    + '.cbw-foot button:disabled{opacity:.5;cursor:default}';
  var style = document.createElement('style'); style.textContent = css; document.head.appendChild(style);

  var btn = document.createElement('button'); btn.className = 'cbw-btn'; btn.innerHTML = '&#128172;';
  var panel = document.createElement('div'); panel.className = 'cbw-panel';
  panel.innerHTML =
    '<div class="cbw-head">' + NAME + '</div>' +
    '<div class="cbw-body" id="cbw-body"></div>' +
    '<div class="cbw-foot"><input id="cbw-in" placeholder="Escribi tu mensaje..." autocomplete="off"/>' +
    '<button id="cbw-send">Enviar</button></div>';
  document.body.appendChild(btn); document.body.appendChild(panel);

  var body = panel.querySelector('#cbw-body');
  var input = panel.querySelector('#cbw-in');
  var sendBtn = panel.querySelector('#cbw-send');
  var opened = false;

  function bubble(text, who) {
    var d = document.createElement('div');
    d.className = 'cbw-msg ' + (who === 'user' ? 'cbw-user' : 'cbw-bot');
    d.textContent = text; body.appendChild(d); body.scrollTop = body.scrollHeight;
    return d;
  }

  btn.onclick = function () {
    panel.classList.toggle('open');
    if (!opened) { opened = true; bubble(WELCOME, 'bot'); input.focus(); }
  };

  async function send() {
    var text = input.value.trim(); if (!text) return;
    input.value = ''; sendBtn.disabled = true;
    bubble(text, 'user'); history.push({ role: 'user', content: text });
    var out = bubble('...', 'bot'); var acc = '';
    try {
      var resp = await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text, history: history.slice(0, -1) }),
      });
      var ct = resp.headers.get('content-type') || '';
      if (ct.indexOf('text/event-stream') >= 0 && resp.body) {
        // Respuesta en streaming (proxy Node)
        var reader = resp.body.getReader(), dec = new TextDecoder(), buf = '';
        out.textContent = '';
        while (true) {
          var r = await reader.read(); if (r.done) break;
          buf += dec.decode(r.value, { stream: true });
          var lines = buf.split('\n'); buf = lines.pop();
          for (var i = 0; i < lines.length; i++) {
            var ln = lines[i].trim();
            if (ln.indexOf('data: ') !== 0) continue;
            var payload = ln.slice(6);
            if (payload === '[DONE]') continue;
            try { acc += JSON.parse(payload); out.textContent = acc; body.scrollTop = body.scrollHeight; } catch (e) {}
          }
        }
      } else {
        // Respuesta JSON simple (proxy PHP)
        var data = await resp.json(); acc = data.reply || '...'; out.textContent = acc;
      }
    } catch (e) {
      acc = 'No pude conectarme. Proba de nuevo en un momento.'; out.textContent = acc;
    }
    history.push({ role: 'assistant', content: acc });
    if (history.length > 24) history = history.slice(-24);
    sendBtn.disabled = false; input.focus();
  }

  sendBtn.onclick = send;
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter') send(); });
})();
