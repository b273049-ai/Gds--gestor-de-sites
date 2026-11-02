<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = (int)empresa_id_atual();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_cliente'])) {
    $id = (int)($_POST['cliente_id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if ($nome === '') flash('erro', 'Informe o nome.');
    elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) flash('erro', 'E-mail inválido.');
    else {
        $dados = [$nome, $email ?: null, trim($_POST['telefone'] ?? '') ?: null, trim($_POST['observacoes'] ?? '') ?: null];
        // Ficha: aniversário, anotações fixas (alergias, preferências) e etiquetas (VIP, indicou amigos…)
        $nasc = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['nascimento'] ?? '') ? $_POST['nascimento'] : null;
        $etq = implode(', ', array_slice(array_unique(array_filter(array_map(fn($t) => mb_substr(trim(strip_tags($t)), 0, 30), explode(',', (string)($_POST['etiquetas'] ?? ''))))), 0, 8));
        $extra = [$nasc, mb_substr(trim($_POST['ficha'] ?? ''), 0, 2000) ?: null, $etq ?: null];
        try {
            if ($id) $pdo->prepare("UPDATE clientes SET nome=?, email=?, telefone=?, observacoes=?, nascimento=?, ficha=?, etiquetas=? WHERE id=? AND empresa_id=?")->execute([...$dados, ...$extra, $id, $empresaId]);
            else { $pdo->prepare("INSERT INTO clientes (nome, email, telefone, observacoes, nascimento, ficha, etiquetas, empresa_id) VALUES (?,?,?,?,?,?,?,?)")->execute([...$dados, ...$extra, $empresaId]); $id = (int)$pdo->lastInsertId(); }
        } catch (PDOException $e) {
            if ($id) $pdo->prepare("UPDATE clientes SET nome=?, email=?, telefone=?, observacoes=? WHERE id=? AND empresa_id=?")->execute([...$dados, $id, $empresaId]);
            else $pdo->prepare("INSERT INTO clientes (nome, email, telefone, observacoes, empresa_id) VALUES (?,?,?,?,?)")->execute([...$dados, $empresaId]);
        }
        flash('sucesso', !empty($_POST['cliente_id']) ? 'Cliente atualizado.' : 'Cliente cadastrado.');
        if (!empty($_POST['cliente_id'])) { header('Location: clientes.php?ficha=' . (int)$_POST['cliente_id']); exit; }
    }
    header('Location: clientes.php'); exit;
}
// Cartão fidelidade: a cada N atendimentos/pedidos concluídos, o cliente ganha um prêmio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_fidelidade'])) {
    try {
        $pdo->prepare("UPDATE empresas SET fidelidade_meta=?, fidelidade_premio=? WHERE id=?")
            ->execute([max(0, min(50, (int)($_POST['fidelidade_meta'] ?? 0))), mb_substr(trim($_POST['fidelidade_premio'] ?? ''), 0, 120) ?: null, $empresaId]);
        flash('sucesso', 'Cartão fidelidade salvo.');
    } catch (PDOException $e) { flash('erro', 'Atualize o banco (Admin → Diagnóstico) para usar esta função.'); }
    header('Location: clientes.php'); exit;
}
if (isset($_GET['excluir'])) {
    $pdo->prepare("DELETE FROM clientes WHERE id=? AND empresa_id=?")->execute([(int)$_GET['excluir'], $empresaId]);
    flash('sucesso', 'Cliente excluído.');
    header('Location: clientes.php'); exit;
}

// Exportar CSV (abre no Excel)
if (isset($_GET['exportar'])) {
    $st = $pdo->prepare("SELECT nome, email, telefone, observacoes, criado_em FROM clientes WHERE empresa_id=? ORDER BY nome");
    $st->execute([$empresaId]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clientes.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Nome', 'E-mail', 'Telefone', 'Observações', 'Cadastrado em'], ';');
    while ($r = $st->fetch()) fputcsv($out, [$r['nome'], $r['email'], $r['telefone'], $r['observacoes'], date('d/m/Y', strtotime($r['criado_em']))], ';');
    exit;
}

// FICHA DO CLIENTE: tudo o que ele já fez com a empresa numa tela só
$ficha = null;
if (isset($_GET['ficha'])) {
    $f = $pdo->prepare("SELECT * FROM clientes WHERE id=? AND empresa_id=?"); $f->execute([(int)$_GET['ficha'], $empresaId]); $ficha = $f->fetch();
}
if ($ficha) {
    $cid = (int)$ficha['id'];
    $temComandas = (bool)$pdo->query("SELECT to_regclass('comandas') IS NOT NULL")->fetchColumn();
    $linha = [];
    $s = $pdo->prepare("SELECT a.id, a.data_hora quando, a.status, a.assinatura_id, COALESCE(a.valor, s.preco) valor, s.nome servico, pr.nome profissional" . ($temComandas ? ", (SELECT total FROM comandas cm WHERE cm.agendamento_id=a.id LIMIT 1) comanda" : ", NULL comanda") . "
        FROM agendamentos a LEFT JOIN servicos s ON s.id=a.servico_id LEFT JOIN profissionais pr ON pr.id=a.profissional_id WHERE a.cliente_id=? AND a.empresa_id=? ORDER BY a.data_hora DESC LIMIT 100");
    $s->execute([$cid, $empresaId]);
    foreach ($s->fetchAll() as $a) $linha[] = ['tipo' => 'agenda', 'quando' => $a['quando'], 'titulo' => ($a['servico'] ?: 'Atendimento') . ($a['profissional'] ? ' com ' . $a['profissional'] : ''), 'status' => $a['status'],
        'valor' => $a['comanda'] !== null ? (float)$a['comanda'] : ($a['status'] === 'concluido' && !$a['assinatura_id'] ? (float)$a['valor'] : null), 'clube' => (bool)$a['assinatura_id']];
    $s = $pdo->prepare("SELECT id, criado_em quando, status, valor, tipo, itens, descricao FROM pedidos WHERE cliente_id=? AND empresa_id=? ORDER BY id DESC LIMIT 100");
    $s->execute([$cid, $empresaId]);
    foreach ($s->fetchAll() as $p) {
        $its = array_map(fn($i) => (int)($i['qtd'] ?? 1) . '× ' . ($i['nome'] ?? ''), (array)json_decode((string)$p['itens'], true));
        $linha[] = ['tipo' => 'pedido', 'id' => $p['id'], 'quando' => $p['quando'], 'titulo' => ($p['tipo'] === 'delivery' ? 'Pedido #' . $p['id'] : 'Orçamento #' . $p['id']) . ($its ? ': ' . implode(', ', array_slice($its, 0, 4)) : ($p['descricao'] ? ': ' . mb_strimwidth($p['descricao'], 0, 70, '…') : '')),
            'status' => $p['status'], 'valor' => in_array($p['status'], ['aprovado', 'em_andamento', 'pronto', 'saiu_entrega', 'concluido'], true) && $p['valor'] !== null ? (float)$p['valor'] : null];
    }
    if ($temComandas) {
        $s = $pdo->prepare("SELECT criado_em quando, total, forma_pagamento, itens FROM comandas WHERE cliente_id=? AND empresa_id=? AND agendamento_id IS NULL ORDER BY id DESC LIMIT 100");
        $s->execute([$cid, $empresaId]);
        foreach ($s->fetchAll() as $k) $linha[] = ['tipo' => 'comanda', 'quando' => $k['quando'], 'titulo' => 'Comanda no balcão: ' . implode(', ', array_map(fn($i) => $i['nome'], array_slice((array)json_decode((string)$k['itens'], true), 0, 4))), 'status' => 'pago · ' . $k['forma_pagamento'], 'valor' => (float)$k['total']];
    }
    usort($linha, fn($a, $b) => strcmp($b['quando'], $a['quando']));
    $feitas = array_values(array_filter($linha, fn($l) => $l['valor'] !== null || ($l['clube'] ?? false) || in_array($l['status'], ['concluido'], true)));
    $gasto = array_sum(array_map(fn($l) => (float)$l['valor'], $feitas));
    $datas = array_map(fn($l) => strtotime($l['quando']), array_filter($feitas, fn($l) => strtotime($l['quando']) <= time()));
    sort($datas);
    $freq = count($datas) > 1 ? (int)round((end($datas) - $datas[0]) / 86400 / (count($datas) - 1)) : null;
    $ultima = $datas ? max($datas) : null;
    $proximo = null; foreach ($linha as $l) if ($l['tipo'] === 'agenda' && strtotime($l['quando']) > time() && !in_array($l['status'], ['cancelado', 'faltou'], true)) $proximo = $l;
    $faltas = count(array_filter($linha, fn($l) => $l['tipo'] === 'agenda' && $l['status'] === 'faltou'));
    $idade = !empty($ficha['nascimento']) ? (int)date_diff(date_create($ficha['nascimento']), date_create('today'))->y : null;
    $niver = !empty($ficha['nascimento']) && date('m-d', strtotime($ficha['nascimento'])) === date('m-d');
    // Sugestão automática de próxima ação
    $sugestao = null;
    if ($niver) $sugestao = ['🎂 Hoje é aniversário! Mande os parabéns com um mimo.', 'Feliz aniversário, ' . explode(' ', $ficha['nome'])[0] . '! 🎉 A ' . $pdo->query("SELECT nome FROM empresas WHERE id=$empresaId")->fetchColumn() . ' preparou um presente para você na sua próxima visita.'];
    elseif ($ultima && $freq && !$proximo && (time() - $ultima) / 86400 > $freq * 1.3) $sugestao = ['⏰ Costuma voltar a cada ' . $freq . ' dias e já faz ' . (int)((time() - $ultima) / 86400) . '. Hora de chamar.', 'Oi, ' . explode(' ', $ficha['nome'])[0] . '! Já está na hora de voltar aqui 😉 Quer que eu reserve um horário pra você essa semana?'];
}

$busca = trim($_GET['q'] ?? '');
$etiqueta = trim($_GET['etiqueta'] ?? '');
$sumidos = isset($_GET['sumidos']);
$emp = $pdo->prepare("SELECT * FROM empresas WHERE id=?"); $emp->execute([$empresaId]); $emp = $emp->fetch();
$meta = (int)($emp['fidelidade_meta'] ?? 0);
$temEtiquetas = (bool)$pdo->query("SELECT 1 FROM information_schema.columns WHERE table_name='clientes' AND column_name='etiquetas'")->fetchColumn();
$todasEtiquetas = [];
if ($temEtiquetas) {
    $s = $pdo->prepare("SELECT etiquetas FROM clientes WHERE empresa_id=? AND etiquetas IS NOT NULL"); $s->execute([$empresaId]);
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $t) foreach (explode(',', $t) as $x) if (($x = trim($x)) !== '') $todasEtiquetas[$x] = ($todasEtiquetas[$x] ?? 0) + 1;
    arsort($todasEtiquetas);
}
// Compras = atendimentos concluídos + pedidos concluídos (para o cartão fidelidade)
$sql = "SELECT c.*, COUNT(a.id) AS visitas, GREATEST(MAX(a.data_hora), (SELECT MAX(p.criado_em) FROM pedidos p WHERE p.cliente_id=c.id AND p.status<>'cancelado')) AS ultima,
            COUNT(a.id) FILTER (WHERE a.status='concluido') + (SELECT COUNT(*) FROM pedidos p WHERE p.cliente_id=c.id AND p.status='concluido') AS compras
        FROM clientes c LEFT JOIN agendamentos a ON a.cliente_id = c.id AND a.status <> 'cancelado'
        WHERE c.empresa_id=?" . ($busca !== '' ? " AND (c.nome ILIKE ? OR c.email ILIKE ? OR c.telefone ILIKE ?)" : '') . ($etiqueta !== '' && $temEtiquetas ? " AND c.etiquetas ILIKE ?" : '') . "
        GROUP BY c.id" . ($sumidos ? " HAVING GREATEST(MAX(a.data_hora), (SELECT MAX(p.criado_em) FROM pedidos p WHERE p.cliente_id=c.id AND p.status<>'cancelado')) < NOW() - INTERVAL '45 days'" : '') . "
        ORDER BY " . ($sumidos ? 'ultima' : 'c.nome') . " LIMIT 500";
$st = $pdo->prepare($sql);
$args = $busca !== '' ? [$empresaId, "%$busca%", "%$busca%", "%$busca%"] : [$empresaId];
if ($etiqueta !== '' && $temEtiquetas) $args[] = "%$etiqueta%";
$st->execute($args);
$clientes = $st->fetchAll();
$editando = null;
if (isset($_GET['editar'])) { $e = $pdo->prepare("SELECT * FROM clientes WHERE id=? AND empresa_id=?"); $e->execute([(int)$_GET['editar'], $empresaId]); $editando = $e->fetch(); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Clientes - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header">
        <div><h1>Clientes</h1><p class="subtitulo">Quem agenda pelo site entra aqui automaticamente.</p></div>
        <div class="acoes"><a class="btn btn-ghost" href="?exportar=1">Exportar planilha</a></div>
    </div>
    <?= flash_render() ?>
    <?php if ($ficha): $prim = explode(' ', $ficha['nome'])[0]; ?>
    <section class="form-box largo ficha">
        <div class="ficha-topo">
            <div class="ficha-avatar"><?= e(mb_strtoupper(mb_substr($ficha['nome'], 0, 1))) ?></div>
            <div style="flex:1;min-width:200px">
                <h2 style="margin:0"><?= e($ficha['nome']) ?> <?= $niver ? '🎂' : '' ?></h2>
                <div class="muted"><?= e($ficha['telefone'] ?: 'sem telefone') ?><?= $ficha['email'] ? ' · ' . e($ficha['email']) : '' ?><?= $idade !== null ? ' · ' . $idade . ' anos (' . date('d/m', strtotime($ficha['nascimento'])) . ')' : '' ?> · cliente desde <?= date('m/Y', strtotime($ficha['criado_em'])) ?></div>
                <?php if (!empty($ficha['etiquetas'])): ?><div style="margin-top:6px"><?php foreach (explode(',', $ficha['etiquetas']) as $t): ?><a class="pill pill-neutro" href="?etiqueta=<?= urlencode(trim($t)) ?>"><?= e(trim($t)) ?></a> <?php endforeach; ?></div><?php endif; ?>
            </div>
            <div class="acoes" style="display:flex;gap:6px;flex-wrap:wrap">
                <?php if ($ficha['telefone']): ?><a class="btn btn-sm" target="_blank" href="<?= e(link_whatsapp($ficha['telefone'], 'Oi, ' . $prim . '!')) ?>"><?= icone('chat', 14) ?> WhatsApp</a><?php endif; ?>
                <a class="btn btn-ghost btn-sm" href="?editar=<?= $cid ?>#form"><?= icone('lapis', 14) ?> Editar ficha</a>
                <a class="btn btn-ghost btn-sm" href="clientes.php">← Todos</a>
            </div>
        </div>
        <?php if ($sugestao): ?><div class="ficha-sugestao"><span><?= e($sugestao[0]) ?></span><?php if ($ficha['telefone']): ?><a class="btn btn-sm" target="_blank" href="<?= e(link_whatsapp($ficha['telefone'], $sugestao[1])) ?>">Enviar mensagem</a><?php endif; ?></div><?php endif; ?>
        <div class="ficha-kpis">
            <div><small>Total gasto</small><strong>R$ <?= number_format($gasto, 2, ',', '.') ?></strong></div>
            <div><small>Visitas/compras</small><strong><?= count($feitas) ?></strong></div>
            <div><small>Ticket médio</small><strong>R$ <?= number_format(count($feitas) ? $gasto / max(1, count(array_filter($feitas, fn($l) => $l['valor'] !== null))) : 0, 2, ',', '.') ?></strong></div>
            <div><small>Volta a cada</small><strong><?= $freq ? $freq . ' dias' : '—' ?></strong></div>
            <div><small>Última vez</small><strong><?= $ultima ? date('d/m/Y', $ultima) : '—' ?></strong></div>
            <div><small>Faltas</small><strong><?= $faltas ?></strong></div>
        </div>
        <?php if ($proximo): ?><p class="dica">📅 Próximo horário: <strong><?= date('d/m \à\s H:i', strtotime($proximo['quando'])) ?></strong> — <?= e($proximo['titulo']) ?></p><?php endif; ?>
        <?php if (!empty($ficha['ficha']) || $ficha['observacoes']): ?><div class="ficha-nota">📝 <?= nl2br(e(trim(($ficha['ficha'] ?? '') . "\n" . ($ficha['observacoes'] ?? '')))) ?></div><?php endif; ?>
        <h4 style="margin-top:16px">Histórico</h4>
        <ul class="ficha-linha">
            <?php foreach (array_slice($linha, 0, 60) as $l): ?>
            <li class="t-<?= $l['tipo'] ?>"><span class="quando"><?= date('d/m/Y H:i', strtotime($l['quando'])) ?></span>
                <span class="o-que"><?= ['agenda' => '✂️', 'pedido' => '🛍️', 'comanda' => '💵'][$l['tipo']] ?> <?= e($l['titulo']) ?><span class="mini"><?= e(str_replace('_', ' ', $l['status'])) ?><?= !empty($l['clube']) ? ' · ⭐ clube' : '' ?></span></span>
                <span class="valor"><?= $l['valor'] !== null ? 'R$ ' . number_format($l['valor'], 2, ',', '.') : '' ?></span></li>
            <?php endforeach; ?>
            <?php if (!$linha): ?><li class="dica">Ainda não tem atendimentos nem pedidos.</li><?php endif; ?>
        </ul>
    </section>
    <style>
    .ficha-topo { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; }
    .ficha-avatar { width: 56px; height: 56px; border-radius: 50%; display: grid; place-items: center; font-size: 24px; font-weight: 700; color: #fff; background: linear-gradient(135deg, var(--primary), #c026d3); }
    .ficha-sugestao { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 14px; padding: 10px 12px; border-radius: 10px; background: rgba(250, 204, 21, .1); border: 1px solid rgba(250, 204, 21, .35); }
    .ficha-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-top: 14px; }
    .ficha-kpis div { background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; padding: 10px 12px; }
    .ficha-kpis small { display: block; color: var(--text-dim); font-size: 12px; } .ficha-kpis strong { font-size: 18px; }
    .ficha-nota { margin-top: 12px; padding: 10px 12px; border-left: 3px solid var(--primary); background: var(--surface-2); border-radius: 0 10px 10px 0; }
    .ficha-linha { list-style: none; padding: 0; margin: 6px 0 0; }
    .ficha-linha li { display: grid; grid-template-columns: 130px 1fr auto; gap: 10px; padding: 9px 0; border-bottom: 1px solid var(--border); align-items: start; }
    .ficha-linha .quando { color: var(--text-dim); font-size: 13px; } .ficha-linha .mini { display: block; color: var(--text-dim); font-size: 12px; } .ficha-linha .valor { font-weight: 600; white-space: nowrap; }
    @media (max-width: 600px) { .ficha-linha li { grid-template-columns: 1fr auto; } .ficha-linha .quando { grid-column: 1 / -1; } }
    </style>
    <?php endif; ?>
    <details class="form-box" id="form" <?= $editando ? 'open' : '' ?>>
        <summary><?= icone($editando ? 'lapis' : 'mais') ?> <?= $editando ? 'Editar cliente' : 'Novo cliente' ?></summary>
        <form method="post">
            <?= csrf_campo() ?>
            <input type="hidden" name="cliente_id" value="<?= (int)($editando['id'] ?? 0) ?>">
            <label>Nome</label><input type="text" name="nome" required value="<?= e($editando['nome'] ?? '') ?>">
            <div class="form-row">
                <div><label>E-mail</label><input type="email" name="email" value="<?= e($editando['email'] ?? '') ?>"></div>
                <div><label>Telefone</label><input type="tel" name="telefone" value="<?= e($editando['telefone'] ?? '') ?>"></div>
            </div>
            <label>Observações</label><textarea name="observacoes" rows="2"><?= e($editando['observacoes'] ?? '') ?></textarea>
            <?php if ($temEtiquetas): ?>
            <div class="form-row">
                <div><label>Aniversário</label><input type="date" name="nascimento" value="<?= e($editando['nascimento'] ?? '') ?>"></div>
                <div><label>Etiquetas (separe com vírgula)</label><input type="text" name="etiquetas" maxlength="200" list="etqs" placeholder="Ex.: VIP, indica amigos, prefere manhã" value="<?= e($editando['etiquetas'] ?? '') ?>">
                    <datalist id="etqs"><?php foreach (array_keys($todasEtiquetas) as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist></div>
            </div>
            <label>Ficha (alergias, preferências, histórico importante)</label><textarea name="ficha" rows="3" maxlength="2000" placeholder="Ex.: alergia a amônia · gosta do degradê baixo · sempre pede refrigerante zero"><?= e($editando['ficha'] ?? '') ?></textarea>
            <?php endif; ?>
            <button type="submit" name="salvar_cliente" value="1">Salvar</button>
            <?php if ($editando): ?><a href="clientes.php" class="btn btn-ghost" style="margin-top:18px">Cancelar</a><?php endif; ?>
        </form>
    </details>
    <details class="form-box" <?= $meta ? '' : '' ?>>
        <summary>🎁 Cartão fidelidade <?= $meta ? '<span class="pill pill-ok">a cada ' . $meta . ' = ' . e($emp['fidelidade_premio'] ?: 'prêmio') . '</span>' : '<span class="muted">(desligado)</span>' ?></summary>
        <form method="post">
            <?= csrf_campo() ?>
            <div class="form-row">
                <div><label>A cada quantos atendimentos/pedidos?</label><input type="number" name="fidelidade_meta" min="0" max="50" value="<?= $meta ?: '' ?>" placeholder="Ex: 10 (0 = desligado)"></div>
                <div><label>O cliente ganha</label><input type="text" name="fidelidade_premio" maxlength="120" value="<?= e($emp['fidelidade_premio'] ?? '') ?>" placeholder="Ex: 1 corte grátis"></div>
            </div>
            <p class="dica">Conta sozinho os atendimentos e pedidos marcados como concluídos. Quando o cliente completa, aparece 🎁 aqui com o botão para avisar no WhatsApp.</p>
            <button type="submit" name="salvar_fidelidade" value="1">Salvar</button>
        </form>
    </details>
    <form class="toolbar" method="get"><input type="search" name="q" placeholder="Buscar por nome, e-mail ou telefone…" value="<?= e($busca) ?>"><button class="btn btn-ghost">Buscar</button>
        <a class="btn btn-sm <?= $sumidos ? '' : 'btn-ghost' ?>" href="?<?= $sumidos ? '' : 'sumidos=1' ?>" title="Não voltam há mais de 45 dias">😴 Sumidos</a>
        <?php foreach (array_slice($todasEtiquetas, 0, 6, true) as $t => $n): ?><a class="btn btn-sm <?= $etiqueta === $t ? '' : 'btn-ghost' ?>" href="?etiqueta=<?= urlencode($t) ?>">🏷️ <?= e($t) ?> (<?= $n ?>)</a><?php endforeach; ?>
        <?php if ($busca || $sumidos || $etiqueta): ?><a class="link" href="clientes.php">limpar</a><?php endif; ?></form>
    <?php if ($sumidos): ?><p class="dica">Clientes que não voltam há mais de 45 dias. Um "sentimos sua falta" no WhatsApp traz muita gente de volta.</p><?php endif; ?>
    <div class="tabela-wrap"><table>
        <thead><tr><th>Nome</th><th>Contato</th><th>Visitas</th><th>Última</th><?php if ($meta): ?><th>Fidelidade</th><?php endif; ?><th></th></tr></thead>
        <tbody>
        <?php foreach ($clientes as $c): ?>
        <tr>
            <td><a class="link" href="?ficha=<?= (int)$c['id'] ?>"><strong><?= e($c['nome']) ?></strong></a><?php if (!empty($c['etiquetas'])): ?> <?php foreach (array_slice(explode(',', $c['etiquetas']), 0, 3) as $t): ?><span class="pill pill-neutro" style="font-size:11px"><?= e(trim($t)) ?></span> <?php endforeach; endif; ?><?php if ($c['observacoes']): ?><span class="mini"><?= e(mb_strimwidth($c['observacoes'], 0, 60, '…')) ?></span><?php endif; ?></td>
            <td><?= e($c['email']) ?><?php if ($c['telefone']): ?><span class="mini"><a class="link" target="_blank" href="<?= e(link_whatsapp($c['telefone'])) ?>"><?= e($c['telefone']) ?></a></span><?php endif; ?></td>
            <td><?= (int)$c['visitas'] ?></td>
            <td><?= $c['ultima'] ? date('d/m/Y', strtotime($c['ultima'])) : '—' ?></td>
            <?php if ($meta): $n = (int)$c['compras']; $ganhou = $n > 0 && $n % $meta === 0; $prog = $ganhou ? $meta : $n % $meta; ?>
            <td style="min-width:130px"><?php if ($ganhou): ?><span class="pill pill-ok">🎁 ganhou!</span><?php if ($c['telefone']): ?> <a class="link" target="_blank" href="<?= e(link_whatsapp($c['telefone'], 'Oi, ' . explode(' ', $c['nome'])[0] . '! 🎉 Você completou o cartão fidelidade da ' . $emp['nome'] . ' e ganhou: ' . ($emp['fidelidade_premio'] ?: 'um presente') . '. É só usar na próxima vez!')) ?>">avisar</a><?php endif; ?>
                <?php else: ?><div class="progresso" title="<?= $prog ?> de <?= $meta ?>"><div style="width:<?= round($prog / $meta * 100) ?>%"></div></div><span class="mini"><?= $prog ?>/<?= $meta ?></span><?php endif; ?></td>
            <?php endif; ?>
            <td class="acoes">
                <?php if ($sumidos && $c['telefone']): ?><a class="btn btn-ghost btn-sm" target="_blank" href="<?= e(link_whatsapp($c['telefone'], 'Oi, ' . explode(' ', $c['nome'])[0] . '! Sentimos sua falta aqui na ' . $emp['nome'] . ' 💛 Que tal voltar essa semana? ' . 'É só escolher seu horário: ' . wa_url_publica($emp))) ?>"><?= icone('chat', 14) ?> Chamar de volta</a><?php endif; ?>
                <a class="btn btn-ghost btn-sm" href="?editar=<?= $c['id'] ?>#form"><?= icone('lapis', 14) ?></a>
                <a class="btn btn-perigo btn-sm" href="?excluir=<?= $c['id'] ?><?= csrf_qs() ?>" onclick="return confirm('Excluir este cliente? Os agendamentos dele continuam na agenda, sem o nome.')"><?= icone('lixeira', 14) ?></a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$clientes): ?><tr class="vazio"><td colspan="6"><?= $busca || $sumidos ? 'Nenhum cliente encontrado.' : 'Nenhum cliente ainda.' ?></td></tr><?php endif; ?>
        </tbody>
    </table></div>
</main>
</div>
</body>
</html>
