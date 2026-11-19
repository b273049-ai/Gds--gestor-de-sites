// Service worker dos sites do GDS (recurso "App no celular"). Só deixa o site instalável e mostra
// um aviso amigável quando o celular está sem internet. Não guarda páginas (o site sempre vem atualizado).
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', function (e) {
  if (e.request.mode !== 'navigate') return;
  e.respondWith(fetch(e.request).catch(function () {
    return new Response('<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sem internet</title></head>' +
      '<body style="font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;padding:24px;text-align:center;background:#f6f6f4;color:#222">' +
      '<div><h1 style="font-size:22px">Sem internet agora</h1><p>Assim que a conexão voltar, é só tocar em tentar de novo.</p>' +
      '<button onclick="location.reload()" style="font:600 16px system-ui;padding:12px 20px;border-radius:999px;border:0;background:#222;color:#fff">Tentar de novo</button></div></body></html>',
      { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
  }));
});
