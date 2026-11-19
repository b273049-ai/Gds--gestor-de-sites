<?php
// Reservas, fila de espera e chamados das mesas — ao vivo (atualiza sozinho a cada 10 s, com som)
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
require_once __DIR__ . '/../includes/recursos_site.php';
require_once __DIR__ . '/../includes/restaurante.php';
$empresaId = (int)empresa_id_atual();
$st = $pdo->prepare("SELECT e.*, s.recursos FROM empresas e LEFT JOIN sites s ON s.empresa_id=e.id WHERE e.id=?"); $st->execute([$empresaId]); $empresa = $st->fetch();
$r = rec_ler($empresa);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        wa_adiar();
        if (isset($_POST['reserva'])) res_mudar($empresa, $id, (string)$_POST['reserva']);
        elseif (isset($_POST['fila'])) fila_mudar($empresa, $id, (string)$_POST['fila']);
        elseif (isset($_POST['atender'])) $pdo->prepare("UPDATE mesa_chamados SET status='atendido', atendido_em=NOW() WHERE id=? AND empresa_id=?")->execute([$id, $empresaId]);
        elseif (isset($_POST['fila_manual'])) {
            [$ok, $msg] = fila_entrar($empresa, ['nome' => $_POST['nome'] ?? '', 'tel' => $_POST['tel'] ?? '', 'pessoas' => $_POST['pessoas'] ?? 2]);
            flash($ok ? 'sucesso' : 'erro', $ok ? 'Adicionado à fila.' : $msg);
        }
    } catch (PDOException $e) { flash('erro', 'O banco ainda está sendo atualizado. Recarregue a página.'); }
    header('Location: restaurante.php'); exit;
}

function rest_painel_html($v) {
    $min = fn($t) => max(0, (int)floor((time() - strtotime($t)) / 60));
    $csrf = csrf_campo();
    $btn = fn($campo, $valor, $id, $rot, $classe = 'btn-ghost') => '<form method="post" style="display:inline">' . $csrf . '<input type="hidden" name="id" value="' . (int)$id . '"><input type="hidden" name="' . $campo . '" value="' . e($valor) . '"><button type="submit" class="btn btn-sm ' . $classe . '">' . $rot . '</button></form>';
    $h = '<div class="rt-grade">';
    // chamados
    $h .= '<section class="card rt-col" aria-labelledby="rtCh"><h2 id="rtCh">🙋 Mesas chamando <span class="rt-n">' . count($v['chamados']) . '</span></h2>';
    if (!$v['chamados']) $h .= '<p class="muted">Nenhuma mesa chamando agora.</p>';
    foreach ($v['chamados'] as $c) $h .= '<div class="rt-item rt-' . e($c['tipo']) . '"><div><strong>Mesa ' . e($c['mesa']) . '</strong> · ' . ($c['tipo'] === 'conta' ? '🧾 pediu a conta' : 'chamou o garçom') . '<br><small class="muted">há ' . $min($c['criado_em']) . ' min</small></div>' . $btn('atender', '1', $c['id'], '✓ Atendido', '') . '</div>';
    $h .= '</section>';
    // fila
    $h .= '<section class="card rt-col" aria-labelledby="rtFi"><h2 id="rtFi">⏳ Fila de espera <span class="rt-n">' . count($v['fila']) . '</span></h2>';
    if (!$v['fila']) $h .= '<p class="muted">Ninguém na fila.</p>';
    $pos = 0;
    foreach ($v['fila'] as $f) {
        $pos += $f['status'] === 'aguardando' ? 1 : 0;
        $h .= '<div class="rt-item"><div><strong>' . ($f['status'] === 'aguardando' ? $pos . 'º · ' : '🔔 ') . e($f['nome']) . '</strong> · ' . (int)$f['pessoas'] . ' pessoa(s)<br><small class="muted">' . ($f['status'] === 'chamado' ? 'chamado há ' . $min($f['chamado_em']) . ' min' : 'esperando há ' . $min($f['criado_em']) . ' min') . ($f['telefone'] ? ' · ' . e($f['telefone']) : ' · sem WhatsApp') . '</small></div><div class="rt-acoes">'
            . ($f['status'] === 'aguardando' ? $btn('fila', 'chamado', $f['id'], '🔔 Chamar', '') : '') . $btn('fila', 'sentou', $f['id'], 'Sentou') . $btn('fila', 'desistiu', $f['id'], 'Desistiu') . '</div></div>';
    }
    $h .= '<details class="rt-manual"><summary>+ Pôr alguém na fila (quem chegou na porta)</summary><form method="post" class="form-row">' . $csrf . '<input type="hidden" name="fila_manual" value="1">'
        . '<div><label for="fmN">Nome</label><input id="fmN" name="nome" required></div><div><label for="fmT">WhatsApp (opcional)</label><input id="fmT" name="tel" type="tel" inputmode="tel"></div>'
        . '<div><label for="fmP">Pessoas</label><input id="fmP" name="pessoas" type="number" min="1" max="30" value="2"></div><div style="align-self:end"><button type="submit">Adicionar</button></div></form></details></section>';
    // reservas
    $h .= '<section class="card rt-col" aria-labelledby="rtRe"><h2 id="rtRe">📅 Reservas <span class="rt-n">' . count($v['reservas']) . '</span></h2>';
    if (!$v['reservas']) $h .= '<p class="muted">Nenhuma reserva para hoje ou para os próximos dias.</p>';
    foreach ($v['reservas'] as $x) {
        $dia = $x['data'] === date('Y-m-d') ? 'Hoje' : date('d/m', strtotime($x['data']));
        $h .= '<div class="rt-item' . ($x['status'] === 'pendente' ? ' rt-pend' : '') . '"><div><strong>' . $dia . ' às ' . e($x['hora']) . '</strong> · ' . e($x['nome']) . ' · ' . (int)$x['pessoas'] . ' pessoa(s)<br><small class="muted">' . e(RES_STATUS[$x['status']]) . ' · ' . e($x['telefone']) . ($x['observacoes'] ? ' · ' . e($x['observacoes']) : '') . '</small></div><div class="rt-acoes">'
            . ($x['status'] === 'pendente' ? $btn('reserva', 'confirmada', $x['id'], '✓ Confirmar', '') . $btn('reserva', 'cancelada', $x['id'], 'Recusar') : $btn('reserva', 'nao_veio', $x['id'], 'Não veio') . $btn('reserva', 'cancelada', $x['id'], 'Cancelar')) . '</div></div>';
    }
    return $h . '</section></div>';
}

$vivo = rest_ao_vivo($empresaId);
if (isset($_GET['parcial'])) {
    header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store');
    header('X-Novos: ' . (count($vivo['chamados']) . '-' . count(array_filter($vivo['fila'], fn($f) => $f['status'] === 'aguardando')) . '-' . count(array_filter($vivo['reservas'], fn($x) => $x['status'] === 'pendente'))));
    echo rest_painel_html($vivo); exit;
}
$ligados = array_filter(['reserva', 'fila', 'garcom'], fn($k) => !empty($r[$k]['ativo']));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Reservas, fila e mesas - Meu Negócio') ?>
<style>
.rt-grade{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:16px;align-items:start}
.rt-col h2{font-size:1.05rem;margin:0 0 12px;display:flex;align-items:center;gap:8px}.rt-n{background:var(--primaria,#4f7cff);color:#fff;border-radius:999px;font-size:.8rem;padding:2px 9px}
.rt-item{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;border:1px solid var(--borda,#e3e6ea);border-radius:12px;padding:10px 12px;margin-bottom:8px}
.rt-item>div:first-child{min-width:0;overflow-wrap:anywhere}.rt-acoes{display:flex;gap:6px;flex-wrap:wrap}
.rt-conta,.rt-garcom{border-color:#f0b429;background:color-mix(in srgb,#f0b429 10%,transparent)}.rt-pend{border-style:dashed}
.rt-manual summary{cursor:pointer;font-weight:600;margin-top:6px}
</style></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Reservas, fila e mesas</h1><p class="subtitulo">Atualiza sozinho a cada 10 segundos e toca um aviso quando chega algo novo.</p></div>
        <div class="acoes"><button type="button" class="btn btn-ghost" id="rtSom" aria-pressed="false">🔔 Ligar som</button></div></div>
    <?= flash_render() ?>
    <?php if (!$ligados): ?><?= alerta('aviso', 'Nada ligado ainda. Ligue reserva, fila ou chamar garçom em <a class="link" href="recursos.php">Recursos do site</a>.') ?><?php endif; ?>
    <div id="rtVivo" aria-live="polite"><?= rest_painel_html($vivo) ?></div>
</main>
</div>
<script>
(function () {
  var som = false, ultimo = null, b = document.getElementById('rtSom');
  try { som = localStorage.getItem('rt_som') === '1'; } catch (e) {}
  var marca = function () { b.setAttribute('aria-pressed', som ? 'true' : 'false'); b.textContent = som ? '🔔 Som ligado' : '🔕 Ligar som'; };
  marca();
  b.addEventListener('click', function () { som = !som; try { localStorage.setItem('rt_som', som ? '1' : '0'); } catch (e) {} marca(); if (som) bip(); });
  function bip() { try { var c = new (window.AudioContext || window.webkitAudioContext)(), o = c.createOscillator(), g = c.createGain(); o.frequency.value = 880; o.connect(g); g.connect(c.destination); g.gain.setValueAtTime(.2, c.currentTime); o.start(); g.gain.exponentialRampToValueAtTime(.001, c.currentTime + .6); o.stop(c.currentTime + .6); } catch (e) {} }
  function atualizar() {
    if (document.querySelector('#rtVivo :focus, #rtVivo details[open]')) return;   // não atrapalha quem está digitando
    fetch('restaurante.php?parcial=1', { credentials: 'same-origin' }).then(function (r) {
      var n = r.headers.get('X-Novos'); return r.text().then(function (t) { return [n, t]; });
    }).then(function (x) {
      document.getElementById('rtVivo').innerHTML = x[1];
      if (ultimo !== null && x[0] !== ultimo) { var a = ultimo.split('-').map(Number), d = x[0].split('-').map(Number); if (d.some(function (v, i) { return v > a[i]; }) && som) bip(); }
      ultimo = x[0];
    }).catch(function () {});
  }
  atualizar(); setInterval(atualizar, 10000);
})();
</script>
</body>
</html>
