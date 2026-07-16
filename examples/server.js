/*
 * Proxy de chatbot -> Ollama (endpoint OpenAI-compatible /v1/chat/completions).
 * Node.js 18+.  Mismo patron que usamos en produccion:
 *   - Proveedor PRIMARIO = Ollama Cloud con gpt-oss:120b (GRATIS, sin hardware).
 *   - FAILOVER opcional a un segundo proveedor (otra cuenta / gpt-oss:20b / Ollama local).
 *   - Contexto de VENTA del negocio (context.md).
 *   - Memoria de conversacion (el widget manda el historial).
 *   - Streaming, rate limit y CORS.
 *
 * El endpoint /v1/chat/completions funciona IGUAL con:
 *   - Ollama Cloud:  https://ollama.com/v1   (con API key)
 *   - Ollama local:  http://127.0.0.1:11434/v1
 * Cambias de uno a otro solo tocando el .env, sin tocar codigo.
 */
import express from 'express';
import fs from 'node:fs';
import 'dotenv/config';

const app = express();
app.use(express.json({ limit: '32kb' }));

// --- Proveedores: primario + failover (se prueban en orden) ---
const PROVIDERS = [
  {
    name: 'primario',
    baseUrl: process.env.OLLAMA_BASE_URL || 'https://ollama.com/v1',
    model: process.env.OLLAMA_MODEL || 'gpt-oss:120b',
    apiKey: process.env.OLLAMA_API_KEY || '',
  },
];
if (process.env.FAILOVER_BASE_URL) {
  PROVIDERS.push({
    name: 'failover',
    baseUrl: process.env.FAILOVER_BASE_URL,
    model: process.env.FAILOVER_MODEL || 'gpt-oss:20b',
    apiKey: process.env.FAILOVER_API_KEY || '',
  });
}

const ALLOWED_ORIGIN = process.env.ALLOWED_ORIGIN || '*';
const PORT = process.env.PORT || 8080;
const MAX_MSG = 1500;   // caracteres maximos por mensaje del usuario
const MAX_HISTORY = 12; // ultimos turnos que recordamos

// El "cerebro" del bot (persona + reglas de venta). Edita examples/context.md.
const SYSTEM_PROMPT = fs.existsSync('./context.md')
  ? fs.readFileSync('./context.md', 'utf8')
  : 'Sos un asistente de ventas amable y conciso. Responde en el idioma del cliente.';

// --- CORS restringido a tu sitio ---
app.use((req, res, next) => {
  res.setHeader('Access-Control-Allow-Origin', ALLOWED_ORIGIN);
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  if (req.method === 'OPTIONS') return res.sendStatus(204);
  next();
});

// --- Rate limit simple en memoria: 20 req/min por IP ---
const hits = new Map();
function rateLimited(ip) {
  const now = Date.now();
  const arr = (hits.get(ip) || []).filter((t) => now - t < 60_000);
  arr.push(now);
  hits.set(ip, arr);
  return arr.length > 20;
}

function buildMessages(message, history = []) {
  const hist = (Array.isArray(history) ? history : [])
    .filter((m) => m && (m.role === 'user' || m.role === 'assistant') && typeof m.content === 'string')
    .slice(-MAX_HISTORY);
  return [
    { role: 'system', content: SYSTEM_PROMPT },
    ...hist,
    { role: 'user', content: String(message).slice(0, MAX_MSG) },
  ];
}

// Abre un stream con un proveedor (OpenAI-compatible). Lanza si falla.
async function openStream(p, messages, signal) {
  const r = await fetch(`${p.baseUrl}/chat/completions`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      ...(p.apiKey ? { Authorization: `Bearer ${p.apiKey}` } : {}),
    },
    body: JSON.stringify({ model: p.model, messages, stream: true, temperature: 0.4 }),
    signal,
  });
  if (!r.ok || !r.body) throw new Error(`${p.name} HTTP ${r.status}`);
  return r;
}

app.post('/chat', async (req, res) => {
  const ip = (req.headers['x-forwarded-for'] || '').toString().split(',')[0].trim()
    || req.socket.remoteAddress || 'unknown';
  if (rateLimited(ip)) return res.status(429).json({ error: 'Demasiadas solicitudes, proba en un momento.' });

  const { message, history } = req.body || {};
  if (!message || typeof message !== 'string') return res.status(400).json({ error: 'Falta "message".' });
  const messages = buildMessages(message, history);

  res.setHeader('Content-Type', 'text/event-stream');
  res.setHeader('Cache-Control', 'no-cache');
  res.setHeader('Connection', 'keep-alive');

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 90_000);
  req.on('close', () => controller.abort());

  let sentAny = false;
  for (const p of PROVIDERS) {
    try {
      const r = await openStream(p, messages, controller.signal);
      const reader = r.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';
      while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop() || '';
        for (const line of lines) {
          const t = line.trim();
          if (t.indexOf('data:') !== 0) continue;
          const payload = t.slice(5).trim();
          if (payload === '[DONE]') { res.write('data: [DONE]\n\n'); res.end(); clearTimeout(timeout); return; }
          let obj;
          try { obj = JSON.parse(payload); } catch { continue; }
          const tok = obj?.choices?.[0]?.delta?.content || '';
          if (tok) { sentAny = true; res.write(`data: ${JSON.stringify(tok)}\n\n`); }
        }
      }
      res.write('data: [DONE]\n\n'); res.end(); clearTimeout(timeout); return;
    } catch (e) {
      if (sentAny) break;   // ya mandamos texto: no se puede reintentar limpio
      continue;             // probar el siguiente proveedor (failover)
    }
  }

  clearTimeout(timeout);
  if (!sentAny) {
    res.write(`data: ${JSON.stringify('Disculpa, el asistente no esta disponible ahora. Escribinos por WhatsApp y te ayudamos.')}\n\n`);
  }
  res.write('data: [DONE]\n\n');
  res.end();
});

app.get('/health', (_req, res) =>
  res.json({ ok: true, providers: PROVIDERS.map((p) => ({ name: p.name, model: p.model })) }));
app.listen(PORT, () => console.log(`Chatbot proxy en http://127.0.0.1:${PORT} — primario: ${PROVIDERS[0].model}`));
