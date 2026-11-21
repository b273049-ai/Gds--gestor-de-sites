<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');
require_once __DIR__ . '/../includes/inteligencia.php';
$saude = intel_saude_empresas();
$prev = intel_previsao($saude);
$contaRisco = ['alto' => 0, 'medio' => 0, 'baixo' => 0]; foreach ($saude as $sx) $contaRisco[$sx['risco']]++;
$funil = ['vistas' => 0, 'sem_site' => 0, 'contatadas' => 0, 'abriram' => 0, 'responderam' => 0, 'negociando' => 0, 'fechadas' => 0];
try {
    $funil = $pdo->query("SELECT COUNT(*) vistas, COUNT(*) FILTER (WHERE NOT tem_site) sem_site, COUNT(*) FILTER (WHERE contatado_em IS NOT NULL) contatadas,
        COUNT(*) FILTER (WHERE previa_aberta_em IS NOT NULL) abriram, COUNT(*) FILTER (WHERE respondeu_em IS NOT NULL OR status IN ('respondeu','negociando','fechado')) responderam,
        COUNT(*) FILTER (WHERE status='negociando') negociando, COUNT(*) FILTER (WHERE status='fechado') fechadas FROM prospects")->fetch();
} catch (PDOException $e) {}

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
        <div class="form-box largo"><h3>🎯 Funil de vendas (prospecção → cliente)</h3><br>
            <?php $baseF = max(1, (int)$funil['vistas']); foreach (['Empresas vistas' => 'vistas', 'Sem site (oportunidades)' => 'sem_site', 'Chamadas' => 'contatadas', 'Abriram a prévia' => 'abriram', 'Responderam' => 'responderam', 'Negociando' => 'negociando', 'Viraram clientes' => 'fechadas'] as $rot => $k): ?>
            <?= barra($rot, (int)$funil[$k], $baseF, (int)$funil[$k] . ($k !== 'vistas' && $funil['contatadas'] && in_array($k, ['abriram', 'responderam', 'negociando', 'fechadas'], true) ? ' · ' . round(100 * $funil[$k] / $funil['contatadas']) . '% das chamadas' : '')) ?>
            <?php endforeach; ?>
        </div>
        <div class="form-box largo"><h3>📈 Previsão e saúde da base</h3><br>
            <?= barra('Receita recorrente hoje', $prev['mrr'], max(1, $prev['mes3'], $prev['mrr']), intel_dinheiro($prev['mrr'])) ?>
            <?= barra('Prevista em 30 dias', $prev['mes1'], max(1, $prev['mes3'], $prev['mrr']), intel_dinheiro($prev['mes1']), 'var(--ok)') ?>
            <?= barra('Prevista em 90 dias', $prev['mes3'], max(1, $prev['mes3'], $prev['mrr']), intel_dinheiro($prev['mes3']), 'var(--ok)') ?>
            <?= barra('Em risco (clientes desanimados)', $prev['em_risco'], max(1, $prev['mrr']), intel_dinheiro($prev['em_risco']) . '/mês', 'var(--danger)') ?>
            <p class="dica" style="margin-top:10px">Clientes: <strong style="color:#ff8a8a"><?= $contaRisco['alto'] ?> risco alto</strong> · <strong style="color:#f5c26b"><?= $contaRisco['medio'] ?> atenção</strong> · <strong style="color:#6be3a0"><?= $contaRisco['baixo'] ?> saudáveis</strong> — <a class="link" href="empresas.php?saude=alto">ver quem está em risco</a></p>
        </div>
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
