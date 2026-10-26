<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = (int)empresa_id_atual();
$statusOpcoes = ['novo' => 'Novo', 'em_andamento' => 'Em andamento', 'concluido' => 'Concluído', 'cancelado' => 'Cancelado'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_pedido'])) {
    $clienteId = (int)($_POST['cliente_id'] ?? 0) ?: null;
    if ($clienteId) { $c = $pdo->prepare("SELECT 1 FROM clientes WHERE id=? AND empresa_id=?"); $c->execute([$clienteId, $empresaId]); if (!$c->fetch()) $clienteId = null; }
    $descricao = trim($_POST['descricao'] ?? '');
    if ($descricao === '') flash('erro', 'Descreva o pedido/orçamento.');
    else {
        $pdo->prepare("INSERT INTO pedidos (empresa_id, cliente_id, descricao, valor, veiculo_info) VALUES (?,?,?,?,?)")
            ->execute([$empresaId, $clienteId, $descricao, max(0, valor_decimal($_POST['valor'] ?? '0')), trim($_POST['veiculo_info'] ?? '') ?: null]);
        flash('sucesso', 'Pedido registrado.');
    }
    header('Location: pedidos.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_status']) && isset($statusOpcoes[$_POST['status'] ?? ''])) {
    $pdo->prepare("UPDATE pedidos SET status=? WHERE id=? AND empresa_id=?")->execute([$_POST['status'], (int)$_POST['pedido_id'], $empresaId]);
    header('Location: pedidos.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : '')); exit;
}

$clientes = $pdo->prepare("SELECT id, nome FROM clientes WHERE empresa_id=? ORDER BY nome"); $clientes->execute([$empresaId]); $clientes = $clientes->fetchAll();
$filtro = $_GET['status'] ?? '';
$params = [$empresaId];
$sql = "SELECT p.*, c.nome AS cliente_nome, c.telefone, c.email AS cliente_email, sv.nome AS servico_nome FROM pedidos p LEFT JOIN clientes c ON c.id = p.cliente_id LEFT JOIN servicos sv ON sv.id = p.servico_id WHERE p.empresa_id=?";
if (isset($statusOpcoes[$filtro])) { $sql .= " AND p.status=?"; $params[] = $filtro; }
$pedidos = $pdo->prepare($sql . " ORDER BY p.criado_em DESC LIMIT 300"); $pedidos->execute($params); $pedidos = $pedidos->fetchAll();
$novosSite = count(array_filter($pedidos, fn($p) => ($p['origem'] ?? '') === 'site' && $p['status'] === 'novo'));
$totAberto = array_sum(array_map(fn($p) => in_array($p['status'], ['novo', 'em_andamento'], true) ? (float)$p['valor'] : 0, $pedidos));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Pedidos / Orçamentos - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Pedidos e orçamentos</h1><p class="subtitulo">Em aberto nesta lista: R$ <?= number_format($totAberto, 2, ',', '.') ?><?= $novosSite ? ' · <strong>' . $novosSite . ' pedido(s) novo(s) chegaram pelo site</strong>' : '' ?></p></div></div>
    <?= flash_render() ?>
    <details class="form-box" <?= $pedidos ? '' : 'open' ?>>
        <summary><?= icone('mais') ?> Novo pedido/orçamento</summary>
        <form method="post">
            <?= csrf_campo() ?>
            <div class="form-row">
                <div><label>Cliente</label><select name="cliente_id"><option value="">—</option><?php foreach ($clientes as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['nome']) ?></option><?php endforeach; ?></select></div>
                <div><label>Valor (R$)</label><input type="text" inputmode="decimal" name="valor" value="0,00"></div>
            </div>
            <label>Descrição / itens</label><textarea name="descricao" rows="3" required placeholder="Ex: troca de óleo + revisão de freios"></textarea>
            <label>Veículo (marca/modelo/ano) — se aplicável</label><input type="text" name="veiculo_info" placeholder="Ex: Honda Civic 2019">
            <button type="submit" name="criar_pedido" value="1">Salvar</button>
        </form>
    </details>
    <div class="toolbar">
        <a class="btn btn-sm <?= $filtro === '' ? '' : 'btn-ghost' ?>" href="pedidos.php">Todos</a>
        <?php foreach ($statusOpcoes as $v => $l): ?><a class="btn btn-sm <?= $filtro === $v ? '' : 'btn-ghost' ?>" href="?status=<?= $v ?>"><?= $l ?></a><?php endforeach; ?>
    </div>
    <div class="tabela-wrap"><table>
        <thead><tr><th>Data</th><th>Cliente</th><th>Descrição</th><th>Valor</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($pedidos as $p): ?>
        <tr>
            <td><?= date('d/m/Y', strtotime($p['criado_em'])) ?></td>
            <td><?= e($p['cliente_nome'] ?? '—') ?><?php if ($p['telefone']): ?><span class="mini"><a class="link" target="_blank" href="<?= e(link_whatsapp($p['telefone'])) ?>"><?= e($p['telefone']) ?></a></span><?php endif; ?></td>
            <td><?php if (($p['origem'] ?? '') === 'site'): ?><span class="badge badge-site">pelo site</span> <?php endif; ?><?php if (!empty($p['servico_nome'])): ?><strong><?= e($p['servico_nome']) ?></strong><br><?php endif; ?><?= nl2br(e($p['descricao'])) ?>
                <?php if (!empty($p['data_evento'])): ?><span class="mini">📅 <?= date('d/m/Y', strtotime($p['data_evento'])) ?></span><?php endif; ?>
                <?php if (!empty($p['local_evento'])): ?><span class="mini">📍 <?= e($p['local_evento']) ?></span><?php endif; ?>
                <?php if (!empty($p['cliente_email'])): ?><span class="mini">✉ <a class="link" href="mailto:<?= e($p['cliente_email']) ?>"><?= e($p['cliente_email']) ?></a></span><?php endif; ?><?php if ($p['veiculo_info']): ?><span class="mini">🚗 <?= e($p['veiculo_info']) ?></span><?php endif; ?></td>
            <td>R$ <?= number_format($p['valor'], 2, ',', '.') ?></td>
            <td><form method="post"><?= csrf_campo() ?><input type="hidden" name="pedido_id" value="<?= $p['id'] ?>"><input type="hidden" name="atualizar_status" value="1">
                <select name="status" onchange="this.form.submit()" aria-label="Status"><?php foreach ($statusOpcoes as $val => $label): ?><option value="<?= $val ?>" <?= $p['status'] == $val ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></form></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$pedidos): ?><tr class="vazio"><td colspan="5">Nenhum pedido registrado.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</main>
</div>
</body>
</html>
