<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$receitaMensal = $pdo->query("
    SELECT TO_CHAR(m, 'MM/YYYY') AS mes, COALESCE(SUM(pa.valor),0) AS total
    FROM generate_series(date_trunc('month', CURRENT_DATE) - INTERVAL '5 months', date_trunc('month', CURRENT_DATE), INTERVAL '1 month') m
    LEFT JOIN pagamentos_assinaturas pa ON pa.status='pago' AND date_trunc('month', pa.pago_em) = m
    GROUP BY m ORDER BY m")->fetchAll();
$maxReceita = max(1, ...array_map(fn($r) => (float)$r['total'], $receitaMensal));

$empresasPorStatus = $pdo->query("SELECT status, COUNT(*) c FROM empresas GROUP BY status ORDER BY c DESC")->fetchAll();
$totalEmpresas = max(1, array_sum(array_column($empresasPorStatus, 'c')));
$planosDistribuicao = $pdo->query("SELECT p.nome, COUNT(e.id) c FROM planos p LEFT JOIN empresas e ON e.plano_id = p.id GROUP BY p.id, p.nome ORDER BY c DESC")->fetchAll();
$maxPlano = max(1, ...array_map(fn($p) => (int)$p['c'], $planosDistribuicao ?: [['c' => 1]]));

$sites = $pdo->query("SELECT COUNT(*) total, COUNT(*) FILTER (WHERE publicado) publicados, COUNT(*) FILTER (WHERE ia_modo_total) ia FROM sites")->fetch();
$ia30 = $pdo->query("SELECT COUNT(*) c FROM ia_logs WHERE criado_em >= CURRENT_DATE - INTERVAL '30 days'")->fetch()['c'];
$topIa = $pdo->query("SELECT e.nome, COUNT(*) c FROM ia_logs l JOIN empresas e ON e.id = l.empresa_id WHERE l.criado_em >= CURRENT_DATE - INTERVAL '30 days' GROUP BY e.nome ORDER BY c DESC LIMIT 5")->fetchAll();

function barra($rotulo, $valor, $max, $texto = null, $cor = null) {
    $pct = $max > 0 ? round($valor / $max * 100) : 0;
    return '<div class="barra-linha"><div class="rotulo"><span>' . e($rotulo) . '</span><span>' . e($texto ?? $valor) . '</span></div>'
         . '<div class="progresso"><div style="width:' . $pct . '%' . ($cor ? ';background:' . $cor : '') . '"></div></div></div>';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Relatórios - GDS Admin') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Relatórios da plataforma</h1><p class="subtitulo">Só dados da plataforma (empresas, planos, sites, assinaturas) — a operação de cada negócio é privada.</p></div></div>
    <div class="cards">
        <div class="card"><div class="label">Empresas</div><div class="value"><?= array_sum(array_column($empresasPorStatus, 'c')) ?></div></div>
        <div class="card"><div class="label">Sites publicados</div><div class="value"><?= $sites['publicados'] ?>/<?= $sites['total'] ?></div></div>
        <div class="card"><div class="label">Sites com design IA</div><div class="value"><?= $sites['ia'] ?></div></div>
        <div class="card"><div class="label">Usos de IA (30 dias)</div><div class="value"><?= $ia30 ?></div></div>
    </div>
    <div class="grid-2">
        <div class="form-box largo"><h3>Receita de assinaturas (6 meses)</h3><br>
            <?php foreach ($receitaMensal as $r): ?><?= barra($r['mes'], (float)$r['total'], $maxReceita, 'R$ ' . number_format($r['total'], 2, ',', '.')) ?><?php endforeach; ?>
        </div>
        <div class="form-box largo"><h3>Empresas por status</h3><br>
            <?php foreach ($empresasPorStatus as $s): ?><?= barra(ucfirst($s['status']), (int)$s['c'], $totalEmpresas) ?><?php endforeach; ?>
            <?php if (!$empresasPorStatus): ?><p class="dica">Sem empresas ainda.</p><?php endif; ?>
        </div>
        <div class="form-box largo"><h3>Distribuição de planos</h3><br>
            <?php foreach ($planosDistribuicao as $p): ?><?= barra($p['nome'], (int)$p['c'], $maxPlano, null, 'var(--ok)') ?><?php endforeach; ?>
        </div>
        <div class="form-box largo"><h3>Quem mais usa IA (30 dias)</h3><br>
            <?php $maxIa = max(1, ...array_map(fn($t) => (int)$t['c'], $topIa ?: [['c' => 1]])); ?>
            <?php foreach ($topIa as $t): ?><?= barra($t['nome'], (int)$t['c'], $maxIa) ?><?php endforeach; ?>
            <?php if (!$topIa): ?><p class="dica">Nenhum uso de IA nos últimos 30 dias.</p><?php endif; ?>
        </div>
    </div>
</main>
</div>
</body>
</html>
