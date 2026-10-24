<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$um = fn($sql) => $pdo->query($sql)->fetch();
$totalEmpresas = $um("SELECT COUNT(*) c FROM empresas")['c'];
$empresasAtivas = $um("SELECT COUNT(*) c FROM empresas WHERE status='ativo'")['c'];
$totalSites = $um("SELECT COUNT(*) c FROM sites")['c'];
$sitesPublicados = $um("SELECT COUNT(*) c FROM sites WHERE publicado=true")['c'];
$sitesIA = $um("SELECT COUNT(*) c FROM sites WHERE ia_modo_total=true")['c'];
$receitaMes = $um("SELECT COALESCE(SUM(valor),0) v FROM pagamentos_assinaturas WHERE status='pago' AND date_trunc('month', pago_em) = date_trunc('month', CURRENT_DATE)")['v'];
$pend = $um("SELECT COUNT(*) c, COALESCE(SUM(valor),0) v FROM pagamentos_assinaturas WHERE status IN ('pendente','atrasado')");
$vencidas = $um("SELECT COUNT(*) c FROM pagamentos_assinaturas WHERE status IN ('pendente','atrasado') AND vencimento < CURRENT_DATE")['c'];
$mrr = $um("SELECT COALESCE(SUM(p.valor / CASE WHEN p.ciclo='anual' THEN 12 ELSE 1 END),0) v FROM empresas e JOIN planos p ON p.id=e.plano_id WHERE e.status='ativo'")['v'];

$empresasRecentes = $pdo->query("
    SELECT e.id, e.nome, e.slug, e.status, e.vencimento, p.nome AS plano_nome, s.publicado, s.ia_modo_total
    FROM empresas e
    LEFT JOIN planos p ON p.id = e.plano_id
    LEFT JOIN sites s ON s.empresa_id = e.id
    ORDER BY e.criado_em DESC LIMIT 8
")->fetchAll();
$semSite = $pdo->query("SELECT e.id, e.nome FROM empresas e JOIN sites s ON s.empresa_id=e.id WHERE COALESCE(s.html_gerado,'')='' AND s.publicado=false ORDER BY e.criado_em DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Dashboard Admin - GDS') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header">
        <div><h1>Olá, <?= e(explode(' ', $_SESSION['nome'] ?? '')[0]) ?></h1><p class="subtitulo">Visão geral da plataforma hoje, <?= date('d/m/Y') ?>.</p></div>
        <div class="acoes"><a href="empresas.php#nova" class="btn"><?= icone('mais') ?> Nova empresa</a></div>
    </div>
    <?= flash_render() ?>
    <?php if (!ia_configurada()): ?><?= alerta('aviso', 'A IA ainda não está configurada — o Estúdio IA e os botões "✨" não vão funcionar. Configure em Configurações (o Google Gemini é gratuito).') ?><?php endif; ?>

    <div class="cards">
        <div class="card"><div class="label"><?= icone('predio', 16) ?> Empresas</div><div class="value"><?= $totalEmpresas ?></div><div class="sub"><?= $empresasAtivas ?> ativas</div></div>
        <div class="card"><div class="label"><?= icone('globo', 16) ?> Sites publicados</div><div class="value"><?= $sitesPublicados ?><span class="muted" style="font-size:15px">/<?= $totalSites ?></span></div><div class="sub"><?= $sitesIA ?> com design IA</div></div>
        <div class="card"><div class="label"><?= icone('dinheiro', 16) ?> Recebido no mês</div><div class="value">R$ <?= number_format($receitaMes, 2, ',', '.') ?></div><div class="sub">Receita recorrente: R$ <?= number_format($mrr, 2, ',', '.') ?>/mês</div></div>
        <div class="card"><div class="label"><?= icone('cartao', 16) ?> A receber</div><div class="value">R$ <?= number_format($pend['v'], 2, ',', '.') ?></div><div class="sub" style="color:<?= $vencidas ? 'var(--danger)' : 'var(--ok)' ?>"><?= $pend['c'] ?> cobranças · <?= $vencidas ?> vencidas</div></div>
    </div>

    <div class="grid-2">
        <div>
            <h2 style="margin-top:0">Empresas recentes</h2>
            <div class="tabela-wrap"><table>
                <thead><tr><th>Empresa</th><th>Plano</th><th>Status</th><th>Site</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($empresasRecentes as $emp): ?>
                <tr>
                    <td><strong><?= e($emp['nome']) ?></strong><span class="mini">vence <?= $emp['vencimento'] ? date('d/m/Y', strtotime($emp['vencimento'])) : '—' ?></span></td>
                    <td><?= e($emp['plano_nome'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= e($emp['status']) ?>"><?= e($emp['status']) ?></span></td>
                    <td><?= pg_bool($emp['publicado']) ? '<span class="badge badge-ativo">no ar</span>' : '<span class="pill pill-neutro">rascunho</span>' ?></td>
                    <td class="acoes"><a class="btn btn-ghost btn-sm" href="site_hub.php?empresa_id=<?= $emp['id'] ?>">Site</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$empresasRecentes): ?><tr class="vazio"><td colspan="5">Nenhuma empresa ainda. <a class="link" href="empresas.php">Cadastre a primeira</a>.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
        <div>
            <div class="card card-ia" style="margin-bottom:16px">
                <div class="label"><?= icone('brilho', 16) ?> Estúdio IA</div>
                <p style="color:var(--text-dim);font-size:13px;margin:4px 0 12px">Descreva o negócio, a IA cria o site inteiro e você ajusta conversando — com pré-visualização por dispositivo e histórico de versões.</p>
                <?php if ($semSite): ?>
                <p style="font-size:13px;margin-bottom:8px">Empresas esperando um site:</p>
                <ul class="checklist">
                    <?php foreach ($semSite as $s): ?><li><?= icone('predio', 14) ?> <?= e($s['nome']) ?> — <a class="link" href="ia_estudio.php?empresa_id=<?= $s['id'] ?>">criar com IA</a></li><?php endforeach; ?>
                </ul>
                <?php else: ?><p class="dica">Nenhuma empresa sem site no momento.</p><?php endif; ?>
            </div>
        </div>
    </div>
</main>
</div>
</body>
</html>
