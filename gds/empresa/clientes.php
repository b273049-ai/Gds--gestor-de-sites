<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = empresa_id_atual();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_cliente'])) {
    $stmt = $pdo->prepare("INSERT INTO clientes (empresa_id, nome, email, telefone, observacoes) VALUES (?,?,?,?,?)");
    $stmt->execute([$empresaId, $_POST['nome'], $_POST['email'], $_POST['telefone'], $_POST['observacoes']]);
    header('Location: clientes.php'); exit;
}

$clientes = $pdo->prepare("SELECT * FROM clientes WHERE empresa_id=? ORDER BY nome");
$clientes->execute([$empresaId]); $clientes = $clientes->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Clientes - Meu Negócio</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Clientes</h1>
    <div class="form-box" style="margin-bottom:24px;">
        <h3>Novo cliente</h3>
        <form method="post">
            <label>Nome</label><input type="text" name="nome" required>
            <label>E-mail</label><input type="email" name="email">
            <label>Telefone</label><input type="text" name="telefone">
            <label>Observações</label><textarea name="observacoes" rows="2"></textarea>
            <button type="submit" name="criar_cliente">Salvar</button>
        </form>
    </div>
    <table>
        <tr><th>Nome</th><th>E-mail</th><th>Telefone</th></tr>
        <?php foreach ($clientes as $c): ?>
        <tr><td><?= e($c['nome']) ?></td><td><?= e($c['email']) ?></td><td><?= e($c['telefone']) ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>
</div>
</body>
</html>
