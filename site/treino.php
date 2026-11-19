<?php
// Treino da semana do aluno (link pessoal): treino de hoje, marcar exercícios e check-in
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/treino_vale.php';
if (!function_exists('e')) { function e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); } }
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
$tok = preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? ''));
$t = $tok !== '' ? treino_por_token($tok) : null;
if (!$t) { http_response_code(404); echo 'Treino não encontrado. Peça o link novo ao seu professor.'; exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin'])) { treino_checkin($t['id']); header('Location: treino.php?t=' . $tok . '&ok=1', true, 303); exit; }
$plano = treino_normalizar($t['plano']);
$hoje = (int)date('w');
$feitos = treino_checkins($t['id']);
$jaHoje = in_array(date('Y-m-d'), $feitos, true);
$semana = count(array_filter($feitos, fn($d) => $d >= date('Y-m-d', strtotime('monday this week'))));
$mes = count(array_filter($feitos, fn($d) => substr($d, 0, 7) === date('Y-m')));
$cor = preg_match('/^#[0-9a-f]{6}$/i', (string)$t['cor_primaria']) ? $t['cor_primaria'] : '#1f6feb';
$diasComTreino = array_map('intval', array_keys($plano));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><meta name="theme-color" content="<?= e($cor) ?>">
<title>Meu treino · <?= e($t['empresa_nome']) ?></title>
<style>
:root{--c:<?= e($cor) ?>}*{box-sizing:border-box}body{margin:0;background:#f4f5f7;color:#16181d;font:16px/1.45 system-ui,-apple-system,"Segoe UI",sans-serif}
header{background:var(--c);color:#fff;padding:22px 18px 46px}header small{opacity:.9;font-weight:600}header h1{font-size:22px;margin:4px 0 0}
main{max-width:560px;margin:-30px auto 40px;padding:0 14px}.card{background:#fff;border-radius:18px;padding:18px;box-shadow:0 12px 34px -24px rgba(0,0,0,.4);margin-bottom:14px}
.kpis{display:grid;grid-template-columns:1fr 1fr;gap:10px}.kpis div{background:#f4f5f7;border-radius:12px;padding:10px 12px}.kpis span{display:block;font-size:13px;color:#555}.kpis strong{font-size:22px}
.dias{display:flex;gap:6px;overflow-x:auto;padding-bottom:4px}.dias button{flex:none;min-width:58px;min-height:48px;border-radius:12px;border:1px solid #d9dce2;background:#fff;font:700 14px system-ui;cursor:pointer;color:#16181d}
.dias button[aria-selected=true]{background:var(--c);border-color:var(--c);color:#fff}.dias button.vazio{border-style:dashed;color:#4a4f5a}.dias button .hoje{display:block;font-size:11px;font-weight:600}
ol{list-style:none;padding:0;margin:12px 0 0;display:grid;gap:8px}li label{display:flex;gap:12px;align-items:flex-start;border:1px solid #e3e5ea;border-radius:14px;padding:12px;cursor:pointer}
li input{width:24px;height:24px;margin-top:2px;accent-color:var(--c);flex:none}li input:checked+div{text-decoration:line-through;color:#3f4450}li strong{display:block}li small{color:#555}
li a{display:inline-block;margin-top:4px;color:#16181d;font-weight:600;text-decoration:underline}.descanso{color:#555;margin:12px 0 0}
.check{width:100%;min-height:54px;border:0;border-radius:14px;background:var(--c);color:#fff;font:800 17px system-ui;cursor:pointer}.check[disabled]{opacity:.7}
.ok{background:#e7f7ee;color:#0b6b3a;border-radius:12px;padding:10px 12px;font-weight:600;margin:0 0 12px}
</style></head>
<body>
<header><small><?= e($t['empresa_nome']) ?></small><h1><?= e($t['titulo'] ?: 'Treino de ' . explode(' ', $t['aluno_nome'])[0]) ?></h1></header>
<main>
    <div class="card"><div class="kpis"><div><span>Treinos nesta semana</span><strong><?= $semana ?></strong></div><div><span>Treinos no mês</span><strong><?= $mes ?></strong></div></div></div>
    <div class="card">
        <div class="dias" role="tablist" aria-label="Dias da semana">
        <?php foreach (TREINO_DIAS as $d => $nome): ?>
            <button type="button" role="tab" id="tb<?= $d ?>" aria-controls="tp<?= $d ?>" aria-selected="<?= $d === $hoje ? 'true' : 'false' ?>" class="<?= in_array($d, $diasComTreino, true) ? '' : 'vazio' ?>"<?= $d === $hoje ? '' : ' tabindex="-1"' ?>><?= mb_substr($nome, 0, 3) ?><?php if ($d === $hoje): ?><span class="hoje">hoje</span><?php endif; ?></button>
        <?php endforeach; ?>
        </div>
        <?php foreach (TREINO_DIAS as $d => $nome): ?>
        <div role="tabpanel" id="tp<?= $d ?>" aria-labelledby="tb<?= $d ?>"<?= $d === $hoje ? '' : ' hidden' ?>>
            <?php if (empty($plano[(string)$d])): ?><p class="descanso">😌 <?= e($nome) ?>: dia de descanso.</p>
            <?php else: ?><ol><?php foreach ($plano[(string)$d] as $i => $x): ?>
                <li><label><input type="checkbox" data-ex="<?= $d . '-' . $i ?>"><div><strong><?= e($x['nome']) ?></strong><small><?= e(implode(' · ', array_filter([$x['series'], $x['obs']]))) ?></small>
                <?php if ($x['video']): ?><br><a href="<?= e($x['video']) ?>" target="_blank" rel="noopener">▶ Ver como faz</a><?php endif; ?></div></label></li>
            <?php endforeach; ?></ol><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <?php if (isset($_GET['ok'])): ?><p class="ok" role="status">💪 Check-in feito! Bom treino.</p><?php endif; ?>
        <form method="post"><input type="hidden" name="checkin" value="1"><button type="submit" class="check"<?= $jaHoje ? ' disabled' : '' ?>><?= $jaHoje ? '✅ Treino de hoje feito' : 'Fiz o treino de hoje' ?></button></form>
    </div>
</main>
<script>
(function () {
  var abas = [].slice.call(document.querySelectorAll('[role=tab]'));
  var abrir = function (a, f) { abas.forEach(function (x) { var on = x === a; x.setAttribute('aria-selected', on); x.tabIndex = on ? 0 : -1; document.getElementById(x.getAttribute('aria-controls')).hidden = !on; }); if (f) a.focus(); };
  abas.forEach(function (a, i) { a.onclick = function () { abrir(a); }; a.onkeydown = function (e) { var d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0; if (d) { e.preventDefault(); abrir(abas[(i + d + abas.length) % abas.length], true); } }; });
  // exercícios marcados ficam guardados no celular só por hoje
  var chave = 'treino_<?= e($tok) ?>_' + new Date().toISOString().slice(0, 10), marc = {};
  try { marc = JSON.parse(localStorage.getItem(chave) || '{}'); } catch (e) {}
  document.querySelectorAll('[data-ex]').forEach(function (c) { c.checked = !!marc[c.dataset.ex]; c.onchange = function () { marc[c.dataset.ex] = c.checked; try { localStorage.setItem(chave, JSON.stringify(marc)); } catch (e) {} }; });
})();
</script>
</body></html>
