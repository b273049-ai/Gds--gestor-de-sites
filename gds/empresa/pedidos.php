<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = empresa_id_atual();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_pedido'])) {
    $stmt = $pdo->prepare("INSERT INTO pedidos (empresa_id, cliente_id, descricao, valor, veiculo_info) VALUES (?,?,?,?,?)");
    $stmt->execute([$empresaId, $_POST['cliente_id'] ?: null, $_POST['descricao'], $_POST['valor'] ?: 0, $_POST['veiculo_info']]);
    header('Location: pedidos.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_status'])) {
    $pdo->prepare("UPDATE pedidos SET status=? WHERE id=? AND empresa_id=?")
        ->execute([$_POST['status'], (int)$_POST['pedido_id'], $empresaId]);
    header('Location: pedidos.php'); exit;
}

$clientes = $pdo->prepare("SELECT * FROM clientes WHERE empresa_id=? ORDER BY nome");
$clientes->execute([$empresaId]); $clientes = $clientes->fetchAll();

$pedidos = $pdo->prepare("
    SELECT p.*, c.nome as cliente_nome FROM pedidos p
    LEFT JOIN clientes c ON c.id = p.cliente_id
    WHERE p.empresa_id=? ORDER BY p.criado_em DESC
");
$pedidos->execute([$empresaId]); $pedidos = $pedidos->fetchAll();

$statusOpcoes = ['novo'=>'Novo','em_andamento'=>'Em andamento','concluido'=>'Concluído','cancelado'=>'Cancelado'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pedidos / Orçamentos - Meu Negócio</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Pedidos / Orçamentos</h1>
    <div class="form-box" style="margin-bottom:24px;">
        <h3>Novo pedido/orçamento</h3>
        <form method="post">
            <label>Cliente</label>
            <select name="cliente_id"><option value="">-- selecione --</option>
                <?php foreach ($clientes as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['nome']) ?></option><?php endforeach; ?>
            </select>
            <label>Descrição / Itens</label>
            <textarea name="descricao" rows="3" placeholder="Ex: troca de óleo + revisão de freios"></textarea>
            <label>Veículo (marca/modelo/ano) — se aplicável</label>
            <input type="text" name="veiculo_info" placeholder="Ex: Honda Civic 2019">
            <label>Valor (R$)</label>
            <input type="number" step="0.01" name="valor" value="0">
            <button type="submit" name="criar_pedido">Salvar</button>
        </form>
    </div>

    <table>
        <tr><th>Cliente</th><th>Descrição</th><th>Veículo</th><th>Valor</th><th>Status</th><th>Data</th></tr>
        <?php foreach ($pedidos as $p): ?>
        <tr>
            <td><?= e($p['cliente_nome'] ?? '-') ?></td>
            <td><?= e($p['descricao']) ?></td>
            <td><?= e($p['veiculo_info']) ?></td>
            <td>R$ <?= number_format($p['valor'],2,',','.') ?></td>
            <td>
                <form method="post" style="display:flex;gap:6px;align-items:center;">
                    <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                    <select name="status" onchange="this.form.submit()">
                        <?php foreach ($statusOpcoes as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $p['status']==$val?'selected':'' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="atualizar_status" value="1">
                </form>
            </td>
            <td><?= date('d/m/Y', strtotime($p['criado_em'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($pedidos)): ?><tr><td colspan="6">Nenhum pedido registrado.</td></tr><?php endif; ?>
    </table>
</div>
</div>
</body>
</html>
