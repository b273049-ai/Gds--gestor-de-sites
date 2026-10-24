<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$receitaMensal = $pdo->query("
    SELECT TO_CHAR(pago_em, 'YYYY-MM') as mes, SUM(valor) as total
    FROM pagamentos_assinaturas
    WHERE status='pago' AND pago_em >= CURRENT_DATE - INTERVAL '6 months'
    GROUP BY mes ORDER BY mes
")->fetchAll();
$maxReceita = 0;
foreach ($receitaMensal as $r) { $maxReceita = max($maxReceita, $r['total']); }
if ($maxReceita == 0) $maxReceita = 1;

$empresasPorStatus = $pdo->query("SELECT status, COUNT(*) c FROM empresas GROUP BY status ORDER BY c DESC")->fetchAll();
$totalEmpresas = array_sum(array_column($empresasPorStatus, 'c'));
if ($totalEmpresas == 0) $totalEmpresas = 1;

$planosDistribuicao = $pdo->query("
    SELECT p.nome, COUNT(e.id) c FROM planos p LEFT JOIN empresas e ON e.plano_id = p.id GROUP BY p.nome ORDER BY c DESC
")->fetchAll();
$maxPlano = 0;
foreach ($planosDistribuicao as $p) { $maxPlano = max($maxPlano, $p['c']); }
if ($maxPlano == 0) $maxPlano = 1;

$sitesPublicados = $pdo->query("SELECT COUNT(*) c FROM sites WHERE publicado=true")->fetch()['c'];
$sitesTotal = $pdo->query("SELECT COUNT(*) c FROM sites")->fetch()['c'];
$sitesIA = $pdo->query("SELECT COUNT(*) c FROM sites WHERE ia_modo_total=true")->fetch()['c'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Relatórios - GDS Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Relatórios da Plataforma</h1>
    <p style="color:#5c6199;font-size:12px;margin-bottom:16px;">Só dados da plataforma em si (empresas, planos, sites, assinaturas) — a operação interna de cada negócio (agenda, clientes, faturamento) é privada de cada empresa.</p>

    <div class="cards">
        <div class="card"><div class="label">Empresas</div><div class="value"><?= $totalEmpresas ?></div></div>
        <div class="card"><div class="label">Sites Publicados</div><div class="value"><?= $sitesPublicados ?>/<?= $sitesTotal ?></div></div>
        <div class="card"><div class="label">Sites em modo IA total</div><div class="value"><?= $sitesIA ?></div></div>
    </div>

    <h1 style="font-size:18px;">Receita de Assinaturas (últimos 6 meses)</h1>
    <div class="form-box" style="margin-bottom:24px;">
        <?php if (empty($receitaMensal)): ?>
        <p style="color:#9aa0c0;">Sem pagamentos registrados ainda.</p>
        <?php endif; ?>
        <?php foreach ($receitaMensal as $r): ?>
        <div style="margin-bottom:12px;">
            <div style="display:flex;justify-content:space-between;font-size:13px;color:#9aa0c0;margin-bottom:4px;">
                <span><?= e($r['mes']) ?></span><span>R$ <?= number_format($r['total'],2,',','.') ?></span>
            </div>
            <div style="background:#0f1226;border-radius:6px;height:10px;overflow:hidden;">
                <div style="background:#4f7cff;height:100%;width:<?= round(($r['total']/$maxReceita)*100) ?>%;"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <h1 style="font-size:18px;">Empresas por Status</h1>
    <div class="form-box" style="margin-bottom:24px;">
        <?php foreach ($empresasPorStatus as $e): ?>
        <div style="margin-bottom:12px;">
            <div style="display:flex;justify-content:space-between;font-size:13px;color:#9aa0c0;margin-bottom:4px;">
                <span><?= e(ucfirst($e['status'])) ?></span><span><?= $e['c'] ?></span>
            </div>
            <div style="background:#0f1226;border-radius:6px;height:10px;overflow:hidden;">
                <div style="background:#4f7cff;height:100%;width:<?= round(($e['c']/$totalEmpresas)*100) ?>%;"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <h1 style="font-size:18px;">Distribuição de Planos</h1>
    <div class="form-box">
        <?php foreach ($planosDistribuicao as $p): ?>
        <div style="margin-bottom:12px;">
            <div style="display:flex;justify-content:space-between;font-size:13px;color:#9aa0c0;margin-bottom:4px;">
                <span><?= e($p['nome']) ?></span><span><?= $p['c'] ?></span>
            </div>
            <div style="background:#0f1226;border-radius:6px;height:10px;overflow:hidden;">
                <div style="background:#5ee06c;height:100%;width:<?= round(($p['c']/$maxPlano)*100) ?>%;"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</div>
</body>
</html>
