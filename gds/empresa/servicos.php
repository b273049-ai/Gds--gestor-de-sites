<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = empresa_id_atual();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_servico'])) {
    $stmt = $pdo->prepare("INSERT INTO servicos (empresa_id, nome, descricao, preco, duracao_minutos) VALUES (?,?,?,?,?)");
    $stmt->execute([$empresaId, $_POST['nome'], $_POST['descricao'], $_POST['preco'], $_POST['duracao_minutos']]);
    header('Location: servicos.php'); exit;
}

$servicos = $pdo->prepare("SELECT * FROM servicos WHERE empresa_id=? ORDER BY nome");
$servicos->execute([$empresaId]); $servicos = $servicos->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Serviços - Meu Negócio</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Serviços</h1>
    <div class="form-box" style="margin-bottom:24px;">
        <h3>Novo serviço</h3>
        <form method="post">
            <label>Nome</label><input type="text" name="nome" required>
            <label>Descrição</label><textarea name="descricao" rows="2"></textarea>
            <label>Preço (R$)</label><input type="number" step="0.01" name="preco" value="0">
            <label>Duração (minutos)</label><input type="number" name="duracao_minutos" value="30">
            <button type="submit" name="criar_servico">Salvar</button>
        </form>
    </div>
    <table>
        <tr><th>Nome</th><th>Preço</th><th>Duração</th></tr>
        <?php foreach ($servicos as $s): ?>
        <tr><td><?= e($s['nome']) ?></td><td>R$ <?= number_format($s['preco'],2,',','.') ?></td><td><?= $s['duracao_minutos'] ?> min</td></tr>
        <?php endforeach; ?>
    </table>
</div>
</div>
</body>
</html>
