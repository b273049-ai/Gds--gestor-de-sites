<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = empresa_id_atual();

// TODA query aqui filtra por empresa_id = isolamento total entre empresas
$um = function ($sql) use ($pdo, $empresaId) { $s = $pdo->prepare($sql); $s->execute([$empresaId]); return $s->fetch(); };
$hoje = $um("SELECT COUNT(*) FILTER (WHERE status <> 'cancelado') c, COUNT(*) FILTER (WHERE status='cancelado') canc FROM agendamentos WHERE empresa_id=? AND data_hora::date = CURRENT_DATE");
$totalClientes = $um("SELECT COUNT(*) c FROM clientes WHERE empresa_id=?")['c'];
$novosClientes = $um("SELECT COUNT(*) c FROM clientes WHERE empresa_id=? AND criado_em >= date_trunc('month', CURRENT_DATE)")['c'];
$novosContatos = $um("SELECT COUNT(*) c FROM leads WHERE empresa_id=? AND criado_em >= date_trunc('month', CURRENT_DATE)")['c'];
// Faturamento do mês: serviços concluídos + pedidos concluídos (antes contava o mesmo mês de anos anteriores)
$fatServicos = $um("SELECT COALESCE(SUM(s.preco),0) v FROM agendamentos a JOIN servicos s ON s.id = a.servico_id
    WHERE a.empresa_id=? AND a.status='concluido' AND date_trunc('month', a.data_hora) = date_trunc('month', CURRENT_DATE)")['v'];
$fatPedidos = $um("SELECT COALESCE(SUM(valor),0) v FROM pedidos WHERE empresa_id=? AND status='concluido' AND date_trunc('month', criado_em) = date_trunc('month', CURRENT_DATE)")['v'];
$faturamentoMes = $fatServicos + $fatPedidos;
try { $avalPendentes = $um("SELECT COUNT(*) c FROM avaliacoes WHERE empresa_id=? AND aprovado = false")['c']; } catch (PDOException $e) { $avalPendentes = 0; }
$media = $um("SELECT COALESCE(AVG(nota),0) m, COUNT(*) c FROM avaliacoes WHERE empresa_id=?");

$proximos = $pdo->prepare("
    SELECT a.data_hora, a.status, c.nome AS cliente_nome, c.telefone, s.nome AS servico_nome, pr.nome AS profissional_nome
    FROM agendamentos a
    LEFT JOIN clientes c ON c.id = a.cliente_id
    LEFT JOIN servicos s ON s.id = a.servico_id
    LEFT JOIN profissionais pr ON pr.id = a.profissional_id
    WHERE a.empresa_id = ? AND a.data_hora >= NOW() - INTERVAL '1 hour' AND a.status <> 'cancelado'
    ORDER BY a.data_hora ASC LIMIT 8");
$proximos->execute([$empresaId]); $proximos = $proximos->fetchAll();

$empresa = $pdo->prepare("SELECT e.*, s.publicado, s.cor_primaria, s.html_gerado FROM empresas e LEFT JOIN sites s ON s.empresa_id=e.id WHERE e.id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();
$totalServicos = $um("SELECT COUNT(*) c FROM servicos WHERE empresa_id=? AND ativo=true")['c'];
$totalProfissionais = $um("SELECT COUNT(*) c FROM profissionais WHERE empresa_id=? AND ativo=true")['c'];
$checklist = [
    ['Pelo menos 1 serviço cadastrado', $totalServicos > 0, 'servicos.php'],
    ['Pelo menos 1 profissional cadastrado', $totalProfissionais > 0, 'profissionais.php'],
    ['WhatsApp, endereço e horários preenchidos', !empty($empresa['whatsapp']) && !empty($empresa['endereco']), 'configuracoes.php'],
    ['Logo cadastrada', !empty($empresa['logo']), 'configuracoes.php'],
    ['Site publicado', pg_bool($empresa['publicado'] ?? false), empresa_pode_usar_estudio($empresaId) ? 'ia_estudio.php' : null],
];
$feitos = count(array_filter($checklist, fn($c) => $c[1]));
$percentual = round($feitos / count($checklist) * 100);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Dashboard - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header">
        <div><h1>Olá, <?= e($_SESSION['nome']) ?> 👋</h1><p class="subtitulo">Resumo de hoje, <?= date('d/m/Y') ?>.</p></div>
        <div class="acoes">
            <a href="/<?= e($empresa['slug']) ?>" target="_blank" class="btn btn-ghost"><?= icone('olho') ?> Ver meu site</a>
            <a href="agenda.php#novo" class="btn"><?= icone('mais') ?> Novo agendamento</a>
        </div>
    </div>
    <?= flash_render() ?>
    <?php if (in_array($empresa['status'], ['pendente'], true)): ?><?= alerta('aviso', 'Sua assinatura está pendente de pagamento. Veja em Minha Assinatura.') ?><?php endif; ?>
    <?php if ($avalPendentes > 0): ?><?= alerta('info', "Você tem $avalPendentes avaliação(ões) de clientes esperando aprovação.") ?><?php endif; ?>

    <?php if ($percentual < 100): ?>
    <div class="form-box">
        <div class="barra-linha"><div class="rotulo"><span>Seu site está <?= $percentual ?>% pronto</span><span><?= $feitos ?>/<?= count($checklist) ?></span></div><div class="progresso"><div style="width:<?= $percentual ?>%"></div></div></div>
        <ul class="checklist">
        <?php foreach ($checklist as [$rotulo, $ok, $link]): ?>
            <li class="<?= $ok ? 'ok' : '' ?>"><?= $ok ? '✓' : '○' ?> <?= e($rotulo) ?><?php if (!$ok): ?> — <?= $link ? '<a class="link" href="' . $link . '">resolver</a>' : '<span class="muted">peça ao administrador</span>' ?><?php endif; ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="cards">
        <div class="card"><div class="label"><?= icone('calendario', 16) ?> Agendamentos hoje</div><div class="value"><?= $hoje['c'] ?></div><?php if ($hoje['canc']): ?><div class="sub" style="color:var(--danger)"><?= $hoje['canc'] ?> cancelado(s)</div><?php endif; ?></div>
        <div class="card"><div class="label"><?= icone('usuarios', 16) ?> Clientes</div><div class="value"><?= $totalClientes ?></div><div class="sub">+<?= $novosClientes ?> este mês</div></div>
        <div class="card"><div class="label"><?= icone('dinheiro', 16) ?> Faturamento do mês</div><div class="value">R$ <?= number_format($faturamentoMes, 2, ',', '.') ?></div><div class="sub">serviços e pedidos concluídos</div></div>
        <div class="card"><div class="label"><?= icone('caixa', 16) ?> Contatos do site (mês)</div><div class="value"><?= $novosContatos ?></div><div class="sub"><?= number_format($media['m'], 1, ',', '') ?> ★ média (<?= $media['c'] ?> avaliações)</div></div>
    </div>

    <h2>Próximos agendamentos</h2>
    <div class="tabela-wrap"><table>
        <thead><tr><th>Quando</th><th>Cliente</th><th>Serviço</th><th>Profissional</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($proximos as $ag): $ts = strtotime($ag['data_hora']); ?>
        <tr>
            <td><strong><?= date('d/m', $ts) ?></strong> <?= date('H:i', $ts) ?><?= date('Y-m-d', $ts) === date('Y-m-d') ? ' <span class="pill pill-ok">hoje</span>' : '' ?></td>
            <td><?= e($ag['cliente_nome'] ?? '—') ?><?php if ($ag['telefone']): ?><span class="mini"><a class="link" href="<?= e(link_whatsapp($ag['telefone'])) ?>" target="_blank"><?= e($ag['telefone']) ?></a></span><?php endif; ?></td>
            <td><?= e($ag['servico_nome'] ?? '—') ?></td>
            <td><?= e($ag['profissional_nome'] ?? '—') ?></td>
            <td><span class="badge badge-<?= e($ag['status']) ?>"><?= e($ag['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$proximos): ?><tr class="vazio"><td colspan="5">Nenhum agendamento futuro.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <p class="dica" style="margin-top:14px">Você só vê os dados do seu próprio negócio. Nenhuma outra empresa tem acesso a eles.</p>
</main>
</div>
</body>
</html>
