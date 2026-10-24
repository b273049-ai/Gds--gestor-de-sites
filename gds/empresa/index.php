<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = empresa_id_atual();

// TODA query aqui filtra por empresa_id = isolamento total entre empresas
$agendamentosHoje = $pdo->prepare("SELECT COUNT(*) c FROM agendamentos WHERE empresa_id=? AND data_hora::date = CURRENT_DATE");
$agendamentosHoje->execute([$empresaId]);
$agendamentosHoje = $agendamentosHoje->fetch()['c'];

$totalClientes = $pdo->prepare("SELECT COUNT(*) c FROM clientes WHERE empresa_id=?");
$totalClientes->execute([$empresaId]);
$totalClientes = $totalClientes->fetch()['c'];

$novosContatos = $pdo->prepare("SELECT COUNT(*) c FROM leads WHERE empresa_id=? AND EXTRACT(MONTH FROM criado_em)=EXTRACT(MONTH FROM CURRENT_DATE)");
$novosContatos->execute([$empresaId]);
$novosContatos = $novosContatos->fetch()['c'];

// Faturamento do mês: serviços concluídos + pedidos concluídos
$fatServicos = $pdo->prepare("
    SELECT COALESCE(SUM(s.preco),0) v FROM agendamentos a
    JOIN servicos s ON s.id = a.servico_id
    WHERE a.empresa_id=? AND a.status='concluido' AND EXTRACT(MONTH FROM a.data_hora)=EXTRACT(MONTH FROM CURRENT_DATE)
");
$fatServicos->execute([$empresaId]);
$fatServicos = $fatServicos->fetch()['v'];

$fatPedidos = $pdo->prepare("
    SELECT COALESCE(SUM(valor),0) v FROM pedidos
    WHERE empresa_id=? AND status='concluido' AND EXTRACT(MONTH FROM criado_em)=EXTRACT(MONTH FROM CURRENT_DATE)
");
$fatPedidos->execute([$empresaId]);
$fatPedidos = $fatPedidos->fetch()['v'];
$faturamentoMes = $fatServicos + $fatPedidos;

$cancelamentosHoje = $pdo->prepare("SELECT COUNT(*) c FROM agendamentos WHERE empresa_id=? AND status='cancelado' AND data_hora::date = CURRENT_DATE");
$cancelamentosHoje->execute([$empresaId]);
$cancelamentosHoje = $cancelamentosHoje->fetch()['c'];

$avaliacoesRecentes = $pdo->prepare("
    SELECT av.nota, av.comentario, c.nome as cliente_nome FROM avaliacoes av
    LEFT JOIN clientes c ON c.id = av.cliente_id
    WHERE av.empresa_id=? ORDER BY av.criado_em DESC LIMIT 3
");
$avaliacoesRecentes->execute([$empresaId]);
$avaliacoesRecentes = $avaliacoesRecentes->fetchAll();

$proximos = $pdo->prepare("
    SELECT a.data_hora, c.nome as cliente_nome, s.nome as servico_nome, pr.nome as profissional_nome
    FROM agendamentos a
    LEFT JOIN clientes c ON c.id = a.cliente_id
    LEFT JOIN servicos s ON s.id = a.servico_id
    LEFT JOIN profissionais pr ON pr.id = a.profissional_id
    WHERE a.empresa_id = ? AND a.data_hora >= NOW()
    ORDER BY a.data_hora ASC LIMIT 6
");
$proximos->execute([$empresaId]);
$proximos = $proximos->fetchAll();

// Completude operacional — o que a empresa de fato controla (o site fica com o admin)
$site = $pdo->prepare("SELECT * FROM sites WHERE empresa_id=?");
$site->execute([$empresaId]); $site = $site->fetch();
$totalServicos = $pdo->prepare("SELECT COUNT(*) c FROM servicos WHERE empresa_id=?");
$totalServicos->execute([$empresaId]); $totalServicos = $totalServicos->fetch()['c'];
$totalProfissionais = $pdo->prepare("SELECT COUNT(*) c FROM profissionais WHERE empresa_id=?");
$totalProfissionais->execute([$empresaId]); $totalProfissionais = $totalProfissionais->fetch()['c'];

$checklist = [
    ['label' => 'Pelo menos 1 serviço cadastrado', 'ok' => $totalServicos > 0, 'link' => 'servicos.php'],
    ['label' => 'Pelo menos 1 profissional cadastrado', 'ok' => $totalProfissionais > 0, 'link' => 'profissionais.php'],
    ['label' => 'Cor do site definida', 'ok' => !empty($site['cor_primaria']), 'link' => 'configuracoes.php'],
    ['label' => 'Site publicado', 'ok' => pg_bool($site['publicado'] ?? false), 'link' => null],
];
$feitos = 0;
foreach ($checklist as $item) { if ($item['ok']) $feitos++; }
$percentual = round(($feitos / count($checklist)) * 100);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard - Meu Negócio</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Olá, <?= e($_SESSION['nome']) ?> 👋</h1>

    <?php if ($percentual < 100): ?>
    <div class="form-box" style="max-width:480px;margin-bottom:20px;">
        <div style="display:flex;justify-content:space-between;font-size:13px;color:#9aa0c0;margin-bottom:6px;">
            <span>Site <?= $percentual ?>% completo</span><span><?= $feitos ?>/<?= count($checklist) ?></span>
        </div>
        <div style="background:#0f1226;border-radius:6px;height:8px;overflow:hidden;margin-bottom:12px;">
            <div style="background:#4f7cff;height:100%;width:<?= $percentual ?>%;"></div>
        </div>
        <?php foreach ($checklist as $item): if ($item['ok']) continue; ?>
        <div style="font-size:13px;color:#e8e8f0;margin-bottom:6px;">
            ○ <?= e($item['label']) ?><?php if ($item['link']): ?> — <a href="<?= $item['link'] ?>" style="color:#4f7cff;">resolver</a><?php else: ?> — <span style="color:#5c6199;">peça pro admin publicar</span><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="cards">
        <div class="card"><div class="label">Agendamentos Hoje</div><div class="value"><?= $agendamentosHoje ?></div></div>
        <div class="card"><div class="label">Clientes</div><div class="value"><?= $totalClientes ?></div></div>
        <div class="card"><div class="label">Faturamento do Mês</div><div class="value">R$ <?= number_format($faturamentoMes,2,',','.') ?></div></div>
        <div class="card"><div class="label">Novos Contatos (mês)</div><div class="value"><?= $novosContatos ?></div></div>
    </div>
    <?php if ($cancelamentosHoje > 0): ?>
    <div class="alert-erro" style="max-width:500px;">⚠️ <?= $cancelamentosHoje ?> cancelamento(s) hoje.</div>
    <?php endif; ?>

    <h1 style="font-size:18px;">Próximos Agendamentos</h1>
    <table>
        <tr><th>Data/Hora</th><th>Cliente</th><th>Serviço</th><th>Profissional</th></tr>
        <?php foreach ($proximos as $ag): ?>
        <tr>
            <td><?= date('d/m H:i', strtotime($ag['data_hora'])) ?></td>
            <td><?= e($ag['cliente_nome'] ?? '-') ?></td>
            <td><?= e($ag['servico_nome'] ?? '-') ?></td>
            <td><?= e($ag['profissional_nome'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($proximos)): ?><tr><td colspan="4">Nenhum agendamento futuro.</td></tr><?php endif; ?>
    </table>

    <h1 style="font-size:18px;margin-top:24px;">Avaliações Recentes</h1>
    <table>
        <tr><th>Cliente</th><th>Nota</th><th>Comentário</th></tr>
        <?php foreach ($avaliacoesRecentes as $av): ?>
        <tr>
            <td><?= e($av['cliente_nome'] ?? 'Cliente') ?></td>
            <td><?= str_repeat('★', (int)$av['nota']) ?></td>
            <td><?= e($av['comentario']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($avaliacoesRecentes)): ?><tr><td colspan="3">Nenhuma avaliação ainda.</td></tr><?php endif; ?>
    </table>
    <p style="color:#5c6199;font-size:12px;margin-top:14px;">Você só vê os dados do seu próprio negócio. Nenhuma outra empresa tem acesso a isso, e o Admin não gerencia sua agenda.</p>
</div>
</div>
</body>
</html>
