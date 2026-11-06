<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
require_once __DIR__ . '/../includes/painel_ramo.php';
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
$temAgenda = modulo_ativo('agenda');
$temPedidos = modulo_ativo('delivery') || modulo_ativo('orcamentos');
$pedAbertos = $temPedidos ? $um("SELECT COUNT(*) FILTER (WHERE status='novo') novos, COUNT(*) FILTER (WHERE status NOT IN ('concluido','cancelado')) abertos,
    COALESCE(SUM(valor) FILTER (WHERE status NOT IN ('concluido','cancelado')),0) valor FROM pedidos WHERE empresa_id=?") : null;
$pedHoje = $temPedidos ? $um("SELECT COUNT(*) c FROM pedidos WHERE empresa_id=? AND status <> 'cancelado' AND criado_em::date = CURRENT_DATE")['c'] : 0;
$ultimosPedidos = [];
if ($temPedidos) {
    $st = $pdo->prepare("SELECT p.id, p.status, p.valor, p.tipo, p.criado_em, p.descricao, c.nome AS cliente_nome FROM pedidos p LEFT JOIN clientes c ON c.id=p.cliente_id
        WHERE p.empresa_id=? AND p.status NOT IN ('concluido','cancelado') ORDER BY (p.status='novo') DESC, p.criado_em DESC LIMIT 8");
    $st->execute([$empresaId]); $ultimosPedidos = $st->fetchAll();
}
$checklist = [
    ['Pelo menos 1 item em ' . modulos_rotulo_catalogo(), $totalServicos > 0, 'servicos.php'],
    ...(modulo_ativo('equipe') ? [['Pelo menos 1 profissional cadastrado', $totalProfissionais > 0, 'profissionais.php']] : []),
    ['WhatsApp, endereço e horários preenchidos', !empty($empresa['whatsapp']) && !empty($empresa['endereco']), 'configuracoes.php'],
    ['Logo cadastrada', !empty($empresa['logo']), 'configuracoes.php'],
    ['Site publicado', pg_bool($empresa['publicado'] ?? false), empresa_pode_usar_estudio($empresaId) ? 'ia_estudio.php' : null],
];
$feitos = count(array_filter($checklist, fn($c) => $c[1]));
// Números do ramo (delivery / agenda / orçamento)
$rDel = modulo_ativo('delivery') ? ramo_delivery($empresaId) : null;
$rAg = $temAgenda ? ramo_agenda($empresa) : null;
$rOrc = modulo_ativo('orcamentos') && !modulo_ativo('delivery') ? ramo_orcamento($empresaId) : null;
$rCrm = (modulos_atuais()['modo'] === 'contato' || modulo_ativo('imoveis')) ? ramo_contato($empresaId, modulo_ativo('imoveis')) : null;
$moeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
$variacao = function ($a, $b) { if (!$b) return ''; $p = round(100 * ($a - $b) / $b); return ' <span style="color:' . ($p >= 0 ? 'var(--ok)' : 'var(--danger)') . '">' . ($p >= 0 ? '▲' : '▼') . abs($p) . '% vs ontem</span>'; };
$percentual = round($feitos / count($checklist) * 100);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Dashboard - Meu Negócio') ?>
<style>
.ramo-grade { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 16px; margin-bottom: 16px; }
.ramo-grade .form-box, .ramo-grade + .form-box { margin: 0; }
.ramo-topo { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
.ramo-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-top: 12px; }
.ramo-kpis > div { background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; padding: 10px 12px; }
.ramo-kpis small { display: block; color: var(--text-dim); font-size: 12px; }
.ramo-kpis strong { font-size: 20px; }
.ramo-kpis .mini { display: block; }
.ramo-horas { display: flex; align-items: flex-end; gap: 4px; height: 120px; padding-top: 6px; }
.ramo-horas div { flex: 1; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; gap: 4px; min-width: 14px; }
.ramo-horas span { width: 100%; min-height: 2px; border-radius: 6px 6px 2px 2px; background: linear-gradient(180deg, var(--primary), rgba(91,124,255,.35)); }
.ramo-horas small { font-size: 10px; color: var(--text-dim); }
.ramo-aniv { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 8px 0; border-top: 1px solid var(--border); }
</style></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header">
        <div><h1>Olá, <?= e($_SESSION['nome']) ?> 👋</h1><p class="subtitulo">Resumo de hoje, <?= date('d/m/Y') ?>.</p></div>
        <div class="acoes">
            <a href="/<?= e($empresa['slug']) ?>" target="_blank" class="btn btn-ghost"><?= icone('olho') ?> Ver meu site</a>
            <?php if ($temAgenda): ?><a href="agenda.php#novo" class="btn"><?= icone('mais') ?> Novo agendamento</a>
            <?php elseif ($temPedidos): ?><a href="<?= modulo_ativo('delivery') ? 'gestor.php' : 'pedidos.php?status=abertos' ?>" class="btn"><?= icone(modulo_ativo('delivery') ? 'pacote' : 'prancheta') ?> <?= e(modulos_rotulo_pedidos()) ?><?= $pedAbertos['novos'] ? ' (' . (int)$pedAbertos['novos'] . ' novos)' : '' ?></a><?php endif; ?>
        </div>
    </div>
    <?= flash_render() ?>
    <?php if (in_array($empresa['status'], ['pendente'], true)): ?><?= alerta('aviso', 'Sua assinatura está pendente de pagamento. Veja em Minha Assinatura.') ?><?php endif; ?>
    <?php if ($avalPendentes > 0): ?><?= alerta('info', "Você tem $avalPendentes avaliação(ões) de clientes esperando aprovação.") ?><?php endif; ?>
    <?php // Indique e ganhe: 1 mês grátis por indicação que fechar
    $indicaLink = ''; $indicaRes = ['total' => 0, 'fechados' => 0];
    try { require_once __DIR__ . '/../includes/prospeccao_vendedor.php'; $indicaLink = prosp_indica_link($empresaId); $indicaRes = prosp_indica_resumo($empresaId); } catch (Throwable $e) {}
    if ($indicaLink): $txtIndica = "Oi! Uso um site com agendamento e WhatsApp automático e tá me ajudando muito. Eles montam uma prévia grátis com o nome do seu negócio, olha: $indicaLink"; ?>
    <section class="indica-card" aria-labelledby="tIndica">
        <div><h3 id="tIndica">🎁 Indique e ganhe 1 mês grátis</h3>
        <p>Mande seu link para outro dono de negócio. Se ele fechar, seu próximo mês sai de graça. <?= (int)$indicaRes['total'] ? '<b>' . (int)$indicaRes['total'] . ' indicação(ões), ' . (int)$indicaRes['fechados'] . ' fechada(s).</b>' : '' ?></p></div>
        <div class="indica-acoes">
            <a class="btn btn-wa btn-sm" href="https://wa.me/?text=<?= rawurlencode($txtIndica) ?>" target="_blank" rel="noopener">Mandar no WhatsApp</a>
            <button type="button" class="btn btn-ghost btn-sm" onclick="navigator.clipboard && navigator.clipboard.writeText(<?= e(json_encode($indicaLink)) ?>).then(() => { this.textContent = 'Link copiado ✔'; })">Copiar meu link</button>
        </div>
    </section>
    <style>.indica-card{display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between;align-items:center;margin:0 0 16px;padding:14px 16px;border-radius:14px;border:1px solid rgba(34,197,94,.4);background:linear-gradient(135deg,rgba(34,197,94,.10),transparent 70%)}.indica-card h3{margin:0 0 4px;font-size:16px}.indica-card p{margin:0;color:var(--text-dim);font-size:14px}.indica-acoes{display:flex;gap:8px;flex-wrap:wrap}.indica-acoes .btn{margin:0}</style>
    <?php endif; ?>

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
        <?php if ($temAgenda): ?>
        <div class="card"><div class="label"><?= icone('calendario', 16) ?> Agendamentos hoje</div><div class="value"><?= $hoje['c'] ?></div><?php if ($hoje['canc']): ?><div class="sub" style="color:var(--danger)"><?= $hoje['canc'] ?> cancelado(s)</div><?php endif; ?></div>
        <?php endif; ?>
        <?php if ($temPedidos): ?>
        <div class="card"><div class="label"><?= icone(modulo_ativo('delivery') ? 'pacote' : 'prancheta', 16) ?> <?= modulo_ativo('delivery') ? 'Pedidos hoje' : 'Orçamentos em aberto' ?></div>
            <div class="value"><?= modulo_ativo('delivery') ? (int)$pedHoje : (int)$pedAbertos['abertos'] ?></div>
            <div class="sub"><?= (int)$pedAbertos['novos'] ?> novo(s) · R$ <?= number_format((float)$pedAbertos['valor'], 2, ',', '.') ?> em aberto</div></div>
        <?php endif; ?>
        <div class="card"><div class="label"><?= icone('usuarios', 16) ?> Clientes</div><div class="value"><?= $totalClientes ?></div><div class="sub">+<?= $novosClientes ?> este mês</div></div>
        <div class="card"><div class="label"><?= icone('dinheiro', 16) ?> Faturamento do mês</div><div class="value">R$ <?= number_format($faturamentoMes, 2, ',', '.') ?></div><div class="sub">serviços e pedidos concluídos</div></div>
        <div class="card"><div class="label"><?= icone('caixa', 16) ?> Contatos do site (mês)</div><div class="value"><?= $novosContatos ?></div><div class="sub"><?= number_format($media['m'], 1, ',', '') ?> ★ média (<?= $media['c'] ?> avaliações)</div></div>
    </div>

    <?php if ($rDel): $maxH = max(1, ...$rDel['por_hora']); $horasAbertas = array_keys(array_filter($rDel['por_hora'])); ?>
    <div class="ramo-grade">
        <section class="form-box largo">
            <div class="ramo-topo"><h3>🛵 Delivery hoje</h3><a class="btn btn-sm" href="gestor.php">Abrir gestor de pedidos</a></div>
            <div class="ramo-kpis">
                <div><small>Pedidos</small><strong><?= (int)$rDel['hoje']['n'] ?></strong><span class="mini"><?= $variacao((int)$rDel['hoje']['n'], (int)$rDel['ontem']['n']) ?></span></div>
                <div><small>Faturamento</small><strong><?= $moeda($rDel['hoje']['v']) ?></strong><span class="mini"><?= $variacao((float)$rDel['hoje']['v'], (float)$rDel['ontem']['v']) ?></span></div>
                <div><small>Ticket médio</small><strong><?= $moeda($rDel['hoje']['n'] ? $rDel['hoje']['v'] / $rDel['hoje']['n'] : 0) ?></strong></div>
                <div><small>Tempo até entregar (7 dias)</small><strong><?= $rDel['tempo'] !== null ? $rDel['tempo'] . ' min' : '—' ?></strong></div>
                <div><small>Clientes que voltaram (30 dias)</small><strong><?= $rDel['recorrencia'] !== null ? $rDel['recorrencia'] . '%' : '—' ?></strong><span class="mini"><?= $rDel['clientes_mes'] ?> clientes no mês</span></div>
            </div>
            <h4 style="margin:14px 0 6px">Pedidos por horário (últimos 7 dias)</h4>
            <?php if ($horasAbertas): ?>
            <div class="ramo-horas"><?php for ($h = min($horasAbertas); $h <= max($horasAbertas); $h++): ?><div title="<?= $h ?>h: <?= $rDel['por_hora'][$h] ?> pedido(s)"><span style="height:<?= round(100 * $rDel['por_hora'][$h] / $maxH) ?>%"></span><small><?= $h ?>h</small></div><?php endfor; ?></div>
            <p class="dica">O pico é às <?= array_search(max($rDel['por_hora']), $rDel['por_hora']) ?>h: deixe a cozinha preparada nesse horário.</p>
            <?php else: ?><p class="dica">Os horários aparecem aqui depois dos primeiros pedidos.</p><?php endif; ?>
        </section>
        <section class="form-box largo">
            <h3>🏆 Mais vendidos da semana</h3>
            <?php if ($rDel['top']): $maxT = max(array_column($rDel['top'], 'qtd')); foreach ($rDel['top'] as $nome => $t): ?>
            <div class="barra-linha"><div class="rotulo"><span><?= e($nome) ?></span><span><?= (int)$t['qtd'] ?> un · <?= $moeda($t['valor']) ?></span></div><div class="progresso"><div style="width:<?= round(100 * $t['qtd'] / $maxT) ?>%"></div></div></div>
            <?php endforeach; else: ?><p class="dica">Ainda sem vendas nesta semana.</p><?php endif; ?>
            <p class="dica" style="margin-top:10px">Dica: marque os campeões como "Peça também" no cardápio para aparecerem no carrinho.</p>
        </section>
    </div>
    <?php endif; ?>

    <?php if ($rAg): ?>
    <div class="ramo-grade">
        <section class="form-box largo">
            <div class="ramo-topo"><h3>✂️ Agenda de hoje</h3><a class="btn btn-sm" href="agenda.php?ver=grade">Ver a grade do dia</a></div>
            <div class="ramo-kpis">
                <div><small>Ocupação</small><strong><?= $rAg['ocupacao'] ?>%</strong><div class="progresso" style="margin-top:6px"><div style="width:<?= $rAg['ocupacao'] ?>%"></div></div></div>
                <div><small>Atendimentos</small><strong><?= (int)$rAg['hoje']['marcados'] ?></strong><span class="mini"><?= (int)$rAg['hoje']['feitos'] ?> concluído(s)</span></div>
                <div><small>Previsto hoje</small><strong><?= $moeda($rAg['hoje']['previsto']) ?></strong></div>
                <div><small>No caixa hoje</small><strong><?= $rAg['caixa'] ? $moeda($rAg['caixa']['v']) : '—' ?></strong><span class="mini"><a class="link" href="caixa.php">abrir caixa</a></span></div>
                <div><small>Faltas (30 dias)</small><strong style="color:<?= ($rAg['faltas_pct'] ?? 0) >= 10 ? 'var(--danger)' : 'inherit' ?>"><?= $rAg['faltas_pct'] !== null ? $rAg['faltas_pct'] . '%' : '—' ?></strong><?php if (($rAg['faltas_pct'] ?? 0) >= 10): ?><span class="mini"><a class="link" href="configuracoes.php#pix">cobrar sinal no Pix</a></span><?php endif; ?></div>
            </div>
            <?php if ($rAg['ocupacao'] < 50): ?><p class="dica">Agenda com espaço hoje: chame os <a class="link" href="clientes.php?sumidos=1">clientes sumidos</a> com uma mensagem pronta.</p><?php endif; ?>
        </section>
        <?php if ($rAg['aniversariantes']): ?>
        <section class="form-box largo">
            <h3>🎂 Aniversariantes de hoje</h3>
            <?php foreach ($rAg['aniversariantes'] as $an): $tel = preg_replace('/\D/', '', (string)$an['telefone']); ?>
            <div class="ramo-aniv"><strong><?= e($an['nome']) ?></strong><?php if ($tel): ?><a class="btn btn-sm btn-wa" target="_blank" rel="noopener" href="https://wa.me/<?= (strlen($tel) <= 11 ? '55' : '') . $tel ?>?text=<?= rawurlencode('Feliz aniversário, ' . explode(' ', $an['nome'])[0] . '! 🎉 Que tal comemorar com a gente? Seu presente está esperando aqui na ' . $empresa['nome'] . '.') ?>">Mandar parabéns</a><?php endif; ?></div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($rCrm): ?>
    <section class="form-box largo ramo">
        <div class="ramo-topo"><h3><?= $rCrm['imoveis'] ? '🏠 Imóveis e interessados' : '🎯 Contatos e negociações' ?></h3><a class="btn btn-sm" href="leads.php">Abrir o funil</a></div>
        <div class="ramo-kpis">
            <div><small>Novos contatos (30 dias)</small><strong><?= (int)$rCrm['novos'] ?></strong></div>
            <div><small>Em negociação</small><strong><?= (int)$rCrm['abertos'] ?></strong></div>
            <div><small>Retornos para hoje</small><strong style="<?= (int)$rCrm['retornos'] ? 'color:var(--perigo,#f87171)' : '' ?>"><?= (int)$rCrm['retornos'] ?></strong></div>
            <div><small>Fechados (30 dias)</small><strong><?= (int)$rCrm['ganhos'] ?></strong></div>
            <?php if ((float)$rCrm['valor'] > 0): ?><div><small>Valor em negociação</small><strong><?= $moeda($rCrm['valor']) ?></strong></div><?php endif; ?>
            <?php if ($rCrm['imoveis']): ?><div><small>Imóveis disponíveis</small><strong><?= (int)$rCrm['imoveis']['disponiveis'] ?></strong></div><div><small>Visualizações</small><strong><?= (int)$rCrm['imoveis']['vis'] ?></strong></div><?php endif; ?>
        </div>
        <?php if ((int)($rCrm['sem_resposta'] ?? 0) > 0): ?><p class="dica">💬 <?= (int)$rCrm['sem_resposta'] ?> contato(s) na etapa "Novo": responder em até 5 minutos multiplica a chance de fechar.</p><?php endif; ?>
        <?php if ($rCrm['imoveis'] && (int)$rCrm['imoveis']['sem_foto'] > 0): ?><p class="dica">📷 <?= (int)$rCrm['imoveis']['sem_foto'] ?> imóvel(is) sem foto — anúncio sem foto quase não recebe contato. <a class="link" href="imoveis.php">Adicionar fotos</a></p><?php endif; ?>
        <?php if (!empty($rCrm['top'])): ?><h4 style="margin:12px 0 6px">Mais vistos no site</h4><ul class="dica" style="margin:0"><?php foreach ($rCrm['top'] as $t): ?><li>Cód. <?= e($t['codigo']) ?> — <?= e(mb_strimwidth($t['titulo'], 0, 50, '…')) ?>: 👁 <?= (int)$t['visualizacoes'] ?> · 🙋 <?= (int)$t['interessados'] ?></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
    <?php endif; ?>
    <?php if ($rOrc): ?>
    <section class="form-box largo">
        <div class="ramo-topo"><h3>📝 Orçamentos dos últimos 30 dias</h3><a class="btn btn-sm" href="pedidos.php?status=novo">Responder os novos (<?= (int)$rOrc['sem_resposta'] ?>)</a></div>
        <div class="ramo-kpis">
            <div><small>Recebidos</small><strong><?= (int)$rOrc['recebidos'] ?></strong></div>
            <div><small>Respondidos</small><strong><?= (int)$rOrc['respondidos'] ?></strong></div>
            <div><small>Aprovados</small><strong><?= (int)$rOrc['aprovados'] ?></strong></div>
            <div><small>Taxa de aprovação</small><strong><?= $rOrc['taxa'] !== null ? $rOrc['taxa'] . '%' : '—' ?></strong></div>
            <div><small>Valor aprovado</small><strong><?= $moeda($rOrc['valor_aprovado']) ?></strong></div>
            <div><small>Em negociação</small><strong><?= $moeda($rOrc['em_negociacao']) ?></strong></div>
        </div>
        <?php if ((int)$rOrc['sem_resposta'] > 0): ?><p class="dica">Quem responde o orçamento primeiro costuma fechar: <?= (int)$rOrc['sem_resposta'] ?> pedido(s) esperando o valor.</p><?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($temPedidos): $nomesSt = ['novo' => 'Novo', 'orcado' => 'Orçamento enviado', 'aprovado' => 'Aprovado', 'em_andamento' => 'Em andamento', 'pronto' => 'Pronto', 'saiu_entrega' => 'Saiu p/ entrega']; ?>
    <h2><?= modulo_ativo('delivery') ? 'Pedidos em andamento' : 'Orçamentos em aberto' ?></h2>
    <div class="tabela-wrap" style="margin-bottom:24px"><table>
        <thead><tr><th>nº</th><th>Cliente</th><th>Pedido</th><th>Valor</th><th>Situação</th></tr></thead>
        <tbody>
        <?php foreach ($ultimosPedidos as $pd): ?>
        <tr><td><a class="link" href="pedidos.php#pedido-<?= (int)$pd['id'] ?>"><?= (int)$pd['id'] ?></a></td><td><?= e($pd['cliente_nome'] ?? '—') ?><span class="mini"><?= date('d/m H:i', strtotime($pd['criado_em'])) ?></span></td>
            <td><?= e(mb_strimwidth((string)$pd['descricao'], 0, 70, '…')) ?></td><td>R$ <?= number_format((float)$pd['valor'], 2, ',', '.') ?></td>
            <td><span class="pill <?= $pd['status'] === 'novo' ? 'pill-aviso' : 'pill-neutro' ?>"><?= e($nomesSt[$pd['status']] ?? $pd['status']) ?></span></td></tr>
        <?php endforeach; ?>
        <?php if (!$ultimosPedidos): ?><tr class="vazio"><td colspan="5">Nada em aberto agora.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php endif; ?>

    <?php if ($temAgenda): ?>
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
    <?php endif; ?>
    <p class="dica" style="margin-top:14px">Você só vê os dados do seu próprio negócio. Nenhuma outra empresa tem acesso a eles.</p>
</main>
</div>
</body>
</html>
