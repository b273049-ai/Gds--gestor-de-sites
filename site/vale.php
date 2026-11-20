<?php
// Vale-presente: pagamento (Pix) e o cartão-presente de quem ganhou (includes/treino_vale.php)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/site_render.php';
require_once __DIR__ . '/../includes/pagamentos.php';
require_once __DIR__ . '/../includes/treino_vale.php';
if (!function_exists('e')) { function e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); } }
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
$v = vale_por_token(preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? '')));
if (!$v) { http_response_code(404); echo 'Vale não encontrado.'; exit; }
$pag = $v['pag_token'] ? pag_por_token($v['pag_token']) : null;
if ($v['status'] === 'pendente' && $pag && pag_verificar($pag) === 'pago') $v = vale_por_token($v['token']);   // pagou agora
$cor = preg_match('/^#[0-9a-f]{6}$/i', (string)$v['cor_primaria']) ? $v['cor_primaria'] : '#7a3b52';
$valor = 'R$ ' . number_format((float)$v['valor'], 2, ',', '.');
$site = '/' . $v['slug'];
$wa = preg_replace('/\D/', '', (string)$v['empresa_whatsapp']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Vale-presente · <?= e($v['empresa_nome']) ?></title>
<link rel="stylesheet" href="/assets/css/gds-componentes.css?v=20">
<style>
:root{--c:<?= e($cor) ?>}body{margin:0;min-height:100vh;background:#f5f3ef;color:#1d1b19;font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;display:grid;place-items:center;padding:20px;box-sizing:border-box}
main{width:100%;max-width:440px}.cartao{position:relative;overflow:hidden;border-radius:22px;padding:28px 24px;color:#fff;background:radial-gradient(120% 90% at 100% 0%,color-mix(in srgb,var(--c) 55%,#fff) 0,var(--c) 45%,color-mix(in srgb,var(--c) 70%,#000) 100%);box-shadow:0 30px 60px -28px color-mix(in srgb,var(--c) 70%,#000)}
.cartao small{opacity:.9;font-weight:600}.cartao h1{font-size:15px;margin:2px 0 18px;font-weight:600;opacity:.95}.cartao .v{font-size:46px;font-weight:800;line-height:1;margin:0 0 18px}
.cartao .para{font-size:18px;font-weight:700;margin:0}.cartao .de{opacity:.9;margin:2px 0 0}.cartao blockquote{margin:16px 0 0;font-style:italic;opacity:.95}
.cod{margin-top:20px;display:flex;justify-content:space-between;align-items:center;gap:10px;background:rgba(0,0,0,.22);border-radius:12px;padding:10px 14px;font:700 18px ui-monospace,monospace;letter-spacing:.08em}
.info{background:#fff;border-radius:18px;padding:20px;margin-top:16px;box-shadow:0 14px 40px -26px rgba(0,0,0,.35)}.info p{margin:6px 0}
a.b{display:inline-block;margin-top:12px;background:#1d1b19;color:#fff;text-decoration:none;font-weight:700;border-radius:999px;padding:12px 20px}
.gds-pix{background:#fff;border-radius:18px;padding:20px;margin-top:16px}.gds-pix img{max-width:220px;width:100%;display:block;margin:10px auto}
@media(prefers-reduced-motion:no-preference){.cartao{animation:surge .6s ease-out}@keyframes surge{from{opacity:0;transform:translateY(10px) scale(.98)}}}
</style></head>
<body><main>
<?php if ($v['status'] === 'pendente'): ?>
    <div class="info"><h1 style="font-size:20px;margin:0 0 6px">Falta só o pagamento</h1>
        <p>Vale-presente de <strong><?= $valor ?></strong> para <strong><?= e($v['para_nome']) ?></strong> na <?= e($v['empresa_nome']) ?>.</p>
        <?php if ($pag && !empty($pag['qr_code'])): ?><p>Pague com Pix abaixo. Assim que cair, o presente é enviado sozinho.</p>
        <?php else: ?><p>A <?= e($v['empresa_nome']) ?> combina o pagamento com você pelo WhatsApp. Assim que confirmar, o presente é enviado.</p>
            <?php if ($wa): ?><a class="b" href="https://wa.me/<?= strlen($wa) <= 11 ? '55' : '' ?><?= e($wa) ?>?text=<?= rawurlencode('Olá! Pedi um vale-presente de ' . $valor . ' pelo site (código ' . $v['codigo'] . '). Como faço o pagamento?') ?>" target="_blank" rel="noopener">Combinar pelo WhatsApp</a><?php endif; ?>
        <?php endif; ?></div>
    <?= $pag ? frag_pix_pagamento($pag, 'Pague o vale-presente') : '' ?>
    <script src="/assets/js/gds-site.js?v=15" defer></script>
    <?php if ($pag): ?><script>setInterval(function(){fetch('/site/pagamento.php?t=<?= e($pag['token']) ?>').then(function(r){return r.json()}).then(function(j){if(j.status==='pago')location.reload()})},5000)</script><?php endif; ?>
<?php else: ?>
    <div class="cartao" role="img" aria-label="Vale-presente de <?= e($valor) ?> para <?= e($v['para_nome']) ?>">
        <small>🎁 Vale-presente</small><h1><?= e($v['empresa_nome']) ?></h1>
        <p class="v"><?= $valor ?></p>
        <p class="para">Para <?= e($v['para_nome']) ?></p><p class="de">De <?= e($v['comprador_nome']) ?></p>
        <?php if ($v['mensagem']): ?><blockquote>“<?= e($v['mensagem']) ?>”</blockquote><?php endif; ?>
        <div class="cod"><span><?= e($v['codigo']) ?></span><span style="font:600 13px system-ui"><?= $v['status'] === 'ativo' ? 'válido até ' . date('d/m/Y', strtotime($v['validade'])) : ($v['status'] === 'usado' ? 'já usado' : e($v['status'])) ?></span></div>
    </div>
    <div class="info">
        <?php if ($v['status'] === 'ativo'): ?><p>Saldo: <strong>R$ <?= number_format((float)$v['saldo'], 2, ',', '.') ?></strong></p><p>Para usar, é só mostrar o código <strong><?= e($v['codigo']) ?></strong> na <?= e($v['empresa_nome']) ?> (ou dizer o código ao agendar).</p><?php endif; ?>
        <a class="b" href="<?= e($site) ?>">Conhecer a <?= e($v['empresa_nome']) ?></a>
    </div>
<?php endif; ?>
</main></body></html>
