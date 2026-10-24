<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$totalEmpresas = $pdo->query("SELECT COUNT(*) c FROM empresas")->fetch()['c'];
$empresasAtivas = $pdo->query("SELECT COUNT(*) c FROM empresas WHERE status='ativo'")->fetch()['c'];
$totalSites = $pdo->query("SELECT COUNT(*) c FROM sites")->fetch()['c'];
$sitesPublicados = $pdo->query("SELECT COUNT(*) c FROM sites WHERE publicado=true")->fetch()['c'];
$receitaMes = $pdo->query("SELECT COALESCE(SUM(valor),0) v FROM pagamentos_assinaturas WHERE status='pago' AND EXTRACT(MONTH FROM pago_em)=EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM pago_em)=EXTRACT(YEAR FROM CURRENT_DATE)")->fetch()['v'];
$pagamentosPendentes = $pdo->query("SELECT COUNT(*) c FROM pagamentos_assinaturas WHERE status='pendente'")->fetch()['c'];

$empresasRecentes = $pdo->query("
    SELECT e.nome, e.status, e.vencimento, p.nome as plano_nome, p.valor
    FROM empresas e
    LEFT JOIN planos p ON p.id = e.plano_id
    ORDER BY e.criado_em DESC LIMIT 8
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard Admin - GDS</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Dashboard</h1>
    <div class="cards">
        <div class="card"><div class="label">Empresas</div><div class="value"><?= $totalEmpresas ?></div><div class="sub">Ativas: <?= $empresasAtivas ?></div></div>
        <div class="card"><div class="label">Sites</div><div class="value"><?= $totalSites ?></div><div class="sub">Publicados: <?= $sitesPublicados ?></div></div>
        <div class="card"><div class="label">Receita do Mês</div><div class="value">R$ <?= number_format($receitaMes,2,',','.') ?></div></div>
        <div class="card"><div class="label">Pagamentos Pendentes</div><div class="value"><?= $pagamentosPendentes ?></div></div>
    </div>

    <h1 style="font-size:18px;">Empresas Recentes</h1>
    <table>
        <tr><th>Empresa</th><th>Plano</th><th>Valor</th><th>Status</th><th>Vencimento</th></tr>
        <?php foreach ($empresasRecentes as $emp): ?>
        <tr>
            <td><?= e($emp['nome']) ?></td>
            <td><?= e($emp['plano_nome'] ?? '-') ?></td>
            <td>R$ <?= number_format($emp['valor'] ?? 0,2,',','.') ?></td>
            <td><span class="badge badge-<?= e($emp['status']) ?>"><?= e($emp['status']) ?></span></td>
            <td><?= $emp['vencimento'] ? date('d/m/Y', strtotime($emp['vencimento'])) : '-' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</div>
</body>
</html>
