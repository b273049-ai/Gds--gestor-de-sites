<?php
// ============================================================
// LINK DO CLIENTE — /r/TOKEN
// O cliente acompanha o motoboy no mapa enquanto a entrega dele está em rota.
// Quando o motoboy volta para a loja (ou a entrega é cancelada / passa da validade),
// o link responde "expirado" (410) e não mostra mais nada.
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/rastreio.php';
if (!function_exists('e')) { function e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); } }

$token = (string)($_GET['t'] ?? '');
$r = rastreio_pronto() ? rastreio_entrega_por_token($token) : null;
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

if (!rastreio_link_vivo($r)) {
    http_response_code($r ? 410 : 404);
    $nome = $r['empresa_nome'] ?? '';
    ?><!DOCTYPE html><html lang="pt-BR"><head><?= rastreio_head('Acompanhamento encerrado' . ($nome ? ' · ' . $nome : ''), '', false, '<link rel="icon" href="/assets/img/rastreio-icone.svg">') ?></head>
<body class="rz"><main class="rz-expirado"><div class="rz-card">
  <div class="rz-faixa-deco" aria-hidden="true"></div>
  <h1><?= $r ? 'Este link expirou' : 'Link inválido' ?></h1>
  <p class="rz-mute"><?= $r
      ? ($r['status'] === 'cancelada' ? 'Esta entrega foi cancelada pela loja.' : 'A entrega já foi concluída e o motoboy voltou para a loja. Por segurança, o acompanhamento ao vivo fica disponível só durante a entrega.')
      : 'Confira se o link foi copiado inteiro ou peça um novo à loja.' ?></p>
  <?php if ($nome): ?><p style="margin:0"><strong><?= e($nome) ?></strong> agradece a preferência!</p><?php endif; ?>
</div></main></body></html><?php
    exit;
}
$d = rastreio_dados_cliente($r);
$waLoja = preg_replace('/\D/', '', (string)($r['empresa_whatsapp'] ?: $r['empresa_telefone']));
if ($waLoja !== '' && strlen($waLoja) <= 11) $waLoja = '55' . $waLoja;
?><!DOCTYPE html>
<html lang="pt-BR">
<head><?= rastreio_head('Sua entrega · ' . $r['empresa_nome'], 'Acompanhe a sua entrega ao vivo.', true, '<link rel="icon" href="/assets/img/rastreio-icone.svg">') ?></head>
<body class="rz">
<main class="rz-cliente" id="cli" data-t="<?= e($token) ?>" data-inicial='<?= e(json_encode($d, JSON_UNESCAPED_UNICODE)) ?>'>
  <div class="rz-mapa" id="cliMapa" role="region" aria-label="Mapa da entrega"></div>
  <section class="rz-folha" aria-live="polite">
    <div class="rz-folha-marca">
      <?php if (!empty($r['empresa_logo'])): ?><img src="<?= e($r['empresa_logo']) ?>" alt=""><?php endif; ?>
      <span><?= e($r['empresa_nome']) ?></span>
      <span class="rz-vivo" id="cliVivo">Ao vivo</span>
    </div>
    <div class="rz-eta" id="cliEta" hidden><strong class="rz-num" id="cliMin">–</strong><span>min para chegar</span></div>
    <h1 id="cliTit">Acompanhe sua entrega</h1>
    <p class="rz-mute" id="cliTexto" style="margin:0"></p>
    <ol class="rz-passos" aria-label="Etapas da entrega">
      <li data-p="aguardando">Preparando</li><li data-p="rota">A caminho</li><li data-p="entregue">Entregue</li>
    </ol>
    <div class="rz-folha-linha rz-num" id="cliLinha"></div>
    <?php if ($waLoja !== ''): ?><a class="rz-btn rz-btn-linha" href="https://wa.me/<?= e($waLoja) ?>?text=<?= rawurlencode('Olá! Sobre a minha entrega' . ($r['codigo'] ? ' (pedido nº ' . $r['codigo'] . ')' : '') . ':') ?>" target="_blank" rel="noopener">Falar com a loja no WhatsApp</a><?php endif; ?>
  </section>
</main>
<script>
(function () {
  'use strict';
  var raiz = document.getElementById('cli'), t = raiz.getAttribute('data-t');
  var $ = function (id) { return document.getElementById(id); };
  var d0 = JSON.parse(raiz.getAttribute('data-inicial'));
  var centro = d0.posicao || d0.destino || d0.loja;
  var mapa = RZMapa.criar($('cliMapa'), { escuro: true, centro: centro ? [centro.lat, centro.lng] : null, zoom: 15 });
  var frota = new RZMapa.Frota(mapa, { nomes: false, rastro: false });
  var destino = RZMapa.pontoFixo(mapa, 'destino'), loja = RZMapa.pontoFixo(mapa, 'loja');
  var timer = null, ultimoAjuste = 0, ultimoStatus = null;

  function km(m) { return m < 1000 ? m + ' m' : (m / 1000).toFixed(1).replace('.', ',') + ' km'; }

  function desenhar(d) {
    var emRota = d.status === 'rota';
    frota.atualizar(d.posicao ? [{ id: 1, lat: d.posicao.lat, lng: d.posicao.lng, rumo: d.posicao.rumo, kmh: 1, sinal: true, situacao: 'rota', cor: '#f5c518', iniciais: '🛵' }] : []);
    destino(d.destino, 'Seu endereço');
    loja(d.status === 'aguardando' ? d.loja : null, 'Loja');
    $('cliTit').textContent = { aguardando: 'Pedido em preparo', rota: d.distancia_m != null && d.distancia_m < 150 ? 'Chegando!' : 'Saiu para entrega', entregue: 'Pedido entregue' }[d.status] || 'Sua entrega';
    $('cliTexto').textContent = d.sem_sinal ? 'O motoboy está a caminho. O GPS dele perdeu o sinal por um instante; a posição volta sozinha.' : d.texto;
    $('cliEta').hidden = !(emRota && d.eta_min != null);
    if (d.eta_min != null) $('cliMin').textContent = d.eta_min;
    var linha = [];
    if (d.motoboy) linha.push((d.veiculo ? d.veiculo + ' · ' : '') + d.motoboy);
    if (emRota && d.distancia_m != null) linha.push('a ' + km(d.distancia_m));
    if (d.saiu_em) linha.push('saiu às ' + d.saiu_em);
    if (d.entregue_em) linha.push('entregue às ' + d.entregue_em);
    $('cliLinha').textContent = linha.join(' · ');
    var ordem = ['aguardando', 'rota', 'entregue'], i = ordem.indexOf(d.status);
    document.querySelectorAll('.rz-passos li').forEach(function (li, k) { li.classList.toggle('feito', k < i); li.classList.toggle('agora', k === i); });
    $('cliVivo').hidden = !emRota;
    // Enquadra motoboy + destino (respeita quem mexeu no mapa)
    if (ultimoStatus !== d.status || Date.now() - ultimoAjuste > 15000) {
      var pts = [];
      if (d.posicao) pts.push(d.posicao);
      if (d.destino) pts.push(d.destino);
      if (!pts.length && d.loja) pts.push(d.loja);
      if (pts.length) frota.ajustar(pts, ultimoStatus !== d.status);
      ultimoAjuste = Date.now(); ultimoStatus = d.status;
    }
    return d.status;
  }

  function carregar() {
    clearTimeout(timer);
    var proximo = 8000;
    fetch('/api/rastreio.php?acao=cliente&t=' + encodeURIComponent(t), { cache: 'no-store' })
      .then(function (r) { if (r.status === 410 || r.status === 404) { location.reload(); throw new Error('fim'); } return r.json(); })
      .then(function (d) { if (!d.ok) throw new Error(d.erro); var s = desenhar(d); proximo = s === 'rota' ? 6000 : s === 'entregue' ? 30000 : 10000; $('cliVivo').classList.remove('parado'); })
      .catch(function (e) { if (e.message !== 'fim') $('cliVivo').classList.add('parado'); })
      .then(function () { timer = setTimeout(carregar, document.hidden ? 30000 : proximo); });
  }
  desenhar(d0);
  timer = setTimeout(carregar, 4000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) carregar(); });
})();
</script>
</body>
</html>
