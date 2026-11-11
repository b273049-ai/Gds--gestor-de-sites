<?php
// ============================================================
// MAPA DA TV — /tv/TOKEN
// Tela cheia para a TV da loja ou o computador: todos os motoboys ao vivo,
// relógio e contadores. Não mostra nenhum dado de cliente.
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/rastreio.php';
if (!function_exists('e')) { function e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); } }

$token = (string)($_GET['t'] ?? '');
$empresa = null;
if (rastreio_pronto() && rastreio_token_valido($token)) {
    $st = $pdo->prepare("SELECT id, nome, logo, loja_lat, loja_lng FROM empresas WHERE rastreio_tv_token=? AND COALESCE(status, 'ativo') NOT IN ('suspenso','cancelado')");
    $st->execute([$token]);
    $empresa = $st->fetch() ?: null;
}
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
if (!$empresa) {
    http_response_code(404);
    ?><!DOCTYPE html><html lang="pt-BR"><head><?= rastreio_head('Link da TV inválido') ?></head><body class="rz"><main class="rz-expirado"><div class="rz-card">
    <h1>Link da TV inválido</h1><p class="rz-mute">Este link foi trocado ou desligado. Gere um novo no painel: Rastreamento → Loja e TV.</p></div></main></body></html><?php
    exit;
}
$loja = $empresa['loja_lat'] !== null ? ['lat' => (float)$empresa['loja_lat'], 'lng' => (float)$empresa['loja_lng']] : null;
?><!DOCTYPE html>
<html lang="pt-BR">
<head><?= rastreio_head('Mapa ao vivo · ' . $empresa['nome'], '', true) ?></head>
<body class="rz">
<main class="rz-tv" id="tv" data-t="<?= e($token) ?>" data-loja='<?= e(json_encode($loja)) ?>'>
  <header class="rz-tv-topo">
    <?php if (!empty($empresa['logo'])): ?><img src="<?= e($empresa['logo']) ?>" alt="" class="rz-tv-logo"><?php endif; ?>
    <div class="rz-tv-nome"><h1><?= e($empresa['nome']) ?></h1><span class="rz-vivo" id="tvVivo">Conectando…</span></div>
    <div class="rz-tv-contas" aria-live="polite">
      <div class="rz-tv-conta"><strong class="rz-num" id="tvRota" style="color:var(--rz-faixa)">0</strong><span>em rota</span></div>
      <div class="rz-tv-conta"><strong class="rz-num" id="tvVolta" style="color:var(--rz-volta)">0</strong><span>voltando</span></div>
      <div class="rz-tv-conta"><strong class="rz-num" id="tvLoja">0</strong><span>na loja</span></div>
      <div class="rz-tv-conta"><strong class="rz-num" id="tvHoje">0</strong><span>entregues hoje</span></div>
      <div class="rz-tv-conta"><strong class="rz-num" id="tvMedia">–</strong><span>tempo médio</span></div>
    </div>
    <div class="rz-tv-relogio rz-num" id="tvRelogio" aria-label="Hora atual">--:--</div>
    <button type="button" class="rz-btn rz-btn-linha rz-tv-cheia" id="tvCheia">Tela cheia</button>
  </header>
  <div class="rz-mapa" id="tvMapa" role="region" aria-label="Mapa com a posição dos motoboys"></div>
  <aside class="rz-tv-lado" id="tvLado" aria-label="Motoboys"></aside>
</main>
<script>
(function () {
  'use strict';
  var raiz = document.getElementById('tv'), t = raiz.getAttribute('data-t'), loja = JSON.parse(raiz.getAttribute('data-loja') || 'null');
  var $ = function (id) { return document.getElementById(id); };
  var mapa = RZMapa.criar($('tvMapa'), { escuro: true, centro: loja ? [loja.lat, loja.lng] : null, zoom: 14, zoomControle: false });
  var frota = new RZMapa.Frota(mapa, { nomes: true, rastro: true });
  var marcaLoja = RZMapa.pontoFixo(mapa, 'loja');
  var SIT = { loja: 'Na loja', rota: 'Em rota', voltando: 'Voltando' };
  var esc = RZMapa.esc, timer = null, ultimoAjuste = 0, ultimo = null;

  function relogio() { var d = new Date(), h; try { h = d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', timeZone: 'America/Sao_Paulo' }); } catch (e) { h = ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); } $('tvRelogio').textContent = h; }
  relogio(); setInterval(relogio, 5000);

  function lado(lista) {
    if (!lista.length) { $('tvLado').innerHTML = '<p class="rz-mute">Nenhum motoboy cadastrado ainda.</p>'; return; }
    var ordem = { rota: 0, voltando: 1, loja: 2 };
    lista = lista.slice().sort(function (a, b) { return (ordem[a.situacao] - ordem[b.situacao]) || a.nome.localeCompare(b.nome); });
    $('tvLado').innerHTML = lista.map(function (m) {
      var s = m.situacao !== 'loja' && !m.sinal ? 'sem' : m.situacao;
      var info = m.situacao === 'loja' ? 'Na loja' : (m.sinal ? (m.kmh != null ? m.kmh + ' km/h' : 'ao vivo') : 'sem sinal ' + RZMapa.tempo(m.idade));
      if (m.fora_min != null) info += ' · fora há ' + m.fora_min + ' min';
      return '<div class="rz-tv-moto"><span class="rz-av" style="--cor:' + esc(m.cor) + '">' + esc(m.iniciais) + '</span>'
        + '<div><b>' + esc(m.nome) + '</b><small class="rz-num">' + esc(info) + '</small></div>'
        + '<span class="rz-chip" data-s="' + s + '">' + esc(SIT[m.situacao] || '') + (m.pendentes ? ' · ' + m.pendentes : '') + '</span></div>';
    }).join('');
  }

  function desenhar(d) {
    ultimo = d;
    frota.atualizar(d.motoboys || []);
    marcaLoja(d.loja, 'Loja');
    var r = d.resumo || {};
    $('tvRota').textContent = r.rota || 0; $('tvVolta').textContent = r.voltando || 0; $('tvLoja').textContent = r.loja || 0;
    $('tvHoje').textContent = r.entregues_hoje || 0; $('tvMedia').textContent = r.media_min != null ? r.media_min + ' min' : '–';
    lado(d.motoboys || []);
    // Enquadra quem está na rua (e a loja) a cada 20 s
    if (Date.now() - ultimoAjuste > 20000) {
      var naRua = (d.motoboys || []).filter(function (m) { return m.situacao !== 'loja' && m.lat != null; });
      var pts = naRua.map(function (m) { return [m.lat, m.lng]; });
      if (d.loja) pts.push([d.loja.lat, d.loja.lng]);
      if (pts.length > 1) mapa.fitBounds(L.latLngBounds(pts).pad(0.2), { maxZoom: 16 });
      else if (pts.length === 1) mapa.setView(pts[0], 15);
      ultimoAjuste = Date.now();
    }
  }

  function vivo(ok) { var v = $('tvVivo'); v.classList.toggle('parado', !ok); v.textContent = ok ? 'Ao vivo' : 'Sem conexão — tentando de novo'; }

  function carregar() {
    clearTimeout(timer);
    fetch('/api/rastreio.php?acao=tv&t=' + encodeURIComponent(t), { cache: 'no-store' })
      .then(function (r) { if (r.status === 404) { location.reload(); throw new Error('invalido'); } return r.json(); })
      .then(function (d) { if (!d.ok) throw new Error(d.erro); desenhar(d); vivo(true); })
      .catch(function () { vivo(false); })
      .then(function () { timer = setTimeout(carregar, 6000); });
  }
  carregar();

  // Tela cheia + cursor some sozinho + tela não apaga (quando o navegador deixa)
  var cheia = $('tvCheia');
  if (!document.documentElement.requestFullscreen) cheia.hidden = true;
  cheia.addEventListener('click', function () { document.documentElement.requestFullscreen().catch(function () {}); });
  document.addEventListener('fullscreenchange', function () { cheia.hidden = !!document.fullscreenElement; });
  var parado;
  document.addEventListener('mousemove', function () { raiz.classList.add('mexendo'); clearTimeout(parado); parado = setTimeout(function () { raiz.classList.remove('mexendo'); }, 3000); });
  var trava = null;
  function acordar() { if ('wakeLock' in navigator && document.visibilityState === 'visible') navigator.wakeLock.request('screen').then(function (w) { trava = w; }).catch(function () {}); }
  acordar(); document.addEventListener('visibilitychange', function () { if (!document.hidden) { acordar(); carregar(); } });
  // Recarrega a página uma vez por dia de madrugada (TV ligada direto não acumula memória)
  setInterval(function () { var h = new Date().getHours(); if (h === 4 && performance.now() > 3600000) location.reload(); }, 600000);
})();
</script>
</body>
</html>
