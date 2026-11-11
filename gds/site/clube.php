<?php
// Assinar um plano/pacote pelo site e acompanhar (?t=TOKEN): Pix, situação, usos e vencimento
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/site_render.php';
require_once __DIR__ . '/../includes/pagamentos.php';
require_once __DIR__ . '/../includes/clube.php';
if (!function_exists('e')) { function e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); } }
header('X-Robots-Tag: noindex');

$erro = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $empresa = site_buscar_empresa('e.id = ?', (int)($_POST['e'] ?? 0));
    if (!$empresa || !pg_bool($empresa['publicado'])) { http_response_code(404); exit('Empresa não encontrada.'); }
    if (site_eh_spam()) $erro = 'Muitas tentativas. Tente de novo em alguns minutos.';
    else {
        $r = clube_assinar($empresa, (int)($_POST['plano'] ?? 0), (string)($_POST['nome'] ?? ''), (string)($_POST['telefone'] ?? ''));
        if ($r['ok']) { header('Location: /site/clube.php?t=' . $r['token']); exit; }
        $erro = $r['erro'];
    }
}

$token = preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? ''));
$a = null;
if (strlen($token) === 32) {
    $st = $pdo->prepare("SELECT a.*, p.nome AS plano_nome, p.tipo, p.preco, p.quantidade, p.validade_dias, e.nome AS empresa_nome, e.slug, e.whatsapp, e.id AS emp_id, si.cor_primaria, si.ia_tema, si.html_gerado, si.ia_modo_total
        FROM assinaturas a JOIN planos_cliente p ON p.id = a.plano_id JOIN empresas e ON e.id = a.empresa_id JOIN sites si ON si.empresa_id = e.id WHERE a.token=?");
    $st->execute([$token]);
    $a = $st->fetch() ?: null;
}
$pix = null;
if ($a) {
    $st = $pdo->prepare("SELECT * FROM pagamentos_clientes WHERE assinatura_id=? AND status='pendente' AND token IS NOT NULL ORDER BY id DESC LIMIT 1");
    $st->execute([$a['id']]);
    $pix = $st->fetch() ?: null;
}
$tema = $a ? site_tema($a) : ['accent' => '#2563eb'];
$rot = ['pendente' => 'Aguardando pagamento', 'ativa' => 'Ativo ✅', 'vencida' => 'Vencido', 'cancelada' => 'Cancelado', 'usada' => 'Sessões usadas'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= $a ? e($a['plano_nome']) . ' — ' . e($a['empresa_nome']) : 'Clube' ?></title>
<?= frag_css_apoio($tema) ?>
<style>body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;background:#f4f5f8;color:#1c1f2b;display:flex;justify-content:center;padding:32px 16px}main{width:100%;max-width:520px}</style>
</head>
<body><main class="gds-c">
<?php if ($erro): ?><div class="gds-card" style="background:#fff"><div class="gds-alerta gds-alerta-erro"><?= e($erro) ?></div><p style="margin-top:12px"><a href="javascript:history.back()">← Voltar</a></p></div>
<?php elseif (!$a): ?><div class="gds-card" style="background:#fff"><h3>Plano não encontrado</h3></div>
<?php else: $usos = (int)$a['usos']; $qtd = (int)$a['quantidade']; ?>
    <div class="gds-card" style="background:#fff">
        <p class="gds-mute" style="font-size:.85rem"><?= e($a['empresa_nome']) ?></p>
        <h3 style="font-size:1.4rem;margin:4px 0 10px"><?= e($a['plano_nome']) ?></h3>
        <div class="gds-resumo">
            <div><span>Situação</span><strong><?= e($rot[$a['status']] ?? $a['status']) ?></strong></div>
            <div><span>Valor</span><span><?= e(frag_preco($a['preco'])) ?><?= $a['tipo'] === 'pacote' ? '' : ' por período' ?></span></div>
            <?php if ($a['vence_em']): ?><div><span><?= $a['tipo'] === 'pacote' ? 'Válido até' : 'Próximo vencimento' ?></span><span><?= date('d/m/Y', strtotime($a['vence_em'])) ?></span></div><?php endif; ?>
            <div><span><?= $a['tipo'] === 'pacote' ? 'Sessões usadas' : 'Usos neste período' ?></span><span><?= $usos ?><?= $qtd > 0 ? ' de ' . $qtd : ' (ilimitado)' ?></span></div>
        </div>
        <?php if ($pix): ?><?= frag_pix_pagamento($pix, $a['status'] === 'ativa' ? 'Renove com Pix' : 'Pague com Pix para ativar') ?>
        <?php elseif ($a['status'] === 'pendente'): ?><p class="gds-mute" style="margin-top:12px">A empresa vai confirmar o seu pagamento e ativar o plano. Qualquer dúvida, fale com ela.</p><?php endif; ?>
        <?php if ($a['status'] === 'ativa'): ?><p style="margin-top:12px">É só agendar normalmente pelo site com o mesmo WhatsApp: os serviços do plano saem sem custo.</p><?php endif; ?>
        <div class="gds-acoes"><a class="gds-btn" href="/<?= e($a['slug']) ?>#agendar">Agendar</a>
            <?php if ($a['whatsapp']): ?><a class="gds-btn gds-btn-wa" target="_blank" rel="noopener" href="<?= e(frag_link_whatsapp($a['whatsapp'], 'Olá! Sobre o meu plano ' . $a['plano_nome'] . ':')) ?>"><?= frag_icone('whats') ?> Falar com a empresa</a><?php endif; ?></div>
    </div>
<?php endif; ?>
</main></body></html>
