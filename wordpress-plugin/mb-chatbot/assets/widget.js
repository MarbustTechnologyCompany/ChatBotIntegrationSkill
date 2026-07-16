/*
 * Widget del chatbot para el plugin de WordPress "Chatbot IA (Ollama)".
 * Lee la config de window.MBCHAT (inyectada por wp_localize_script) y postea
 * al REST del plugin (/wp-json/mbchat/v1/message), que devuelve {reply}.
 * La API key y el contexto viven en el server (nunca aca). Sin dependencias.
 */
(function () {
  var C = window.MBCHAT || {};
  var API = C.endpoint;
  if (!API) return;
  var NAME = C.brand || 'Asistente';
  var WELCOME = C.welcome || 'Hola! En que puedo ayudarte?';
  var ACCENT = C.accent || '#2563eb';
  var history = [];

  var css = ''
    + '.mbc-btn{position:fixed;bottom:20px;right:20px;width:56px;height:56px;border-radius:50%;background:' + ACCENT + ';color:#fff;border:none;cursor:pointer;box-shadow:0 6px 20px rgba(0,0,0,.25);font-size:24px;z-index:99999;display:flex;align-items:center;justify-content:center}'
    + '.mbc-panel{position:fixed;bottom:88px;right:20px;width:360px;max-width:calc(100vw - 40px);height:520px;max-height:calc(100vh - 120px);background:#fff;border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.28);display:none;flex-direction:column;overflow:hidden;z-index:99999;font-family:system-ui,-apple-system,sans-serif}'
    + '.mbc-panel.open{display:flex}'
    + '.mbc-head{background:' + ACCENT + ';color:#fff;padding:14px 16px;font-weight:600;font-size:15px;flex:0 0 auto}'
    + '.mbc-body{flex:1 1 auto;overflow-y:auto;padding:14px;background:#f7f8fa;display:flex;flex-direction:column;gap:10px}'
    + '.mbc-msg{max-width:80%;padding:9px 12px;border-radius:14px;font-size:14px;line-height:1.45;white-space:pre-wrap;word-wrap:break-word}'
    + '.mbc-user{align-self:flex-end;background:' + ACCENT + ';color:#fff;border-bottom-right-radius:4px}'
    + '.mbc-bot{align-self:flex-start;background:#fff;color:#111;border:1px solid #e5e7eb;border-bottom-left-radius:4px}'
    + '.mbc-foot{flex:0 0 auto;display:flex;gap:8px;padding:10px;border-top:1px solid #eee;background:#fff}'
    + '.mbc-foot input{flex:1;border:1px solid #d1d5db;border-radius:20px;padding:9px 14px;font-size:14px;outline:none}'
    + '.mbc-foot button{background:' + ACCENT + ';color:#fff;border:none;border-radius:20px;padding:0 16px;cursor:pointer;font-size:14px}'
    + '.mbc-foot button:disabled{opacity:.5;cursor:default}';
  var style = document.createElement('style'); style.textContent = css; document.head.appendChild(style);

  function build() {
    var btn = document.createElement('button'); btn.className = 'mbc-btn'; btn.innerHTML = '&#128172;';
    var panel = document.createElement('div'); panel.className = 'mbc-panel';
    panel.innerHTML =
      '<div class="mbc-head">' + NAME + '</div>' +
      '<div class="mbc-body" id="mbc-body"></div>' +
      '<div class="mbc-foot"><input id="mbc-in" placeholder="Escribi tu mensaje..." autocomplete="off"/>' +
      '<button id="mbc-send">Enviar</button></div>';
    document.body.appendChild(btn); document.body.appendChild(panel);

    var body = panel.querySelector('#mbc-body');
    var input = panel.querySelector('#mbc-in');
    var sendBtn = panel.querySelector('#mbc-send');
    var opened = false;

    function bubble(text, who) {
      var d = document.createElement('div');
      d.className = 'mbc-msg ' + (who === 'user' ? 'mbc-user' : 'mbc-bot');
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
      var out = bubble('...', 'bot'); var reply = '';
      try {
        var resp = await fetch(API, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ message: text, history: history.slice(0, -1) }),
        });
        var data = await resp.json();
        reply = (data && data.reply) ? data.reply : '...';
      } catch (e) {
        reply = 'No pude conectarme. Proba de nuevo en un momento.';
      }
      out.textContent = reply;
      history.push({ role: 'assistant', content: reply });
      if (history.length > 24) history = history.slice(-24);
      sendBtn.disabled = false; input.focus();
    }
    sendBtn.onclick = send;
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') send(); });
  }

  if (document.body) build();
  else document.addEventListener('DOMContentLoaded', build);
})();
