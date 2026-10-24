<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_plano'])) {
    $stmt = $pdo->prepare("INSERT INTO planos (nome, valor, ciclo, max_profissionais, permite_pix, permite_ia) VALUES (?,?,?,?,?,?)");
    $stmt->execute([
        $_POST['nome'], $_POST['valor'], $_POST['ciclo'], $_POST['max_profissionais'],
        isset($_POST['permite_pix']) ? 'true' : 'false',
        isset($_POST['permite_ia']) ? 'true' : 'false',
    ]);
    header('Location: planos.php'); exit;
}

if (isset($_GET['desativar'])) {
    $pdo->prepare("UPDATE planos SET ativo=false WHERE id=?")->execute([(int)$_GET['desativar']]);
    header('Location: planos.php'); exit;
}
if (isset($_GET['ativar'])) {
    $pdo->prepare("UPDATE planos SET ativo=true WHERE id=?")->execute([(int)$_GET['ativar']]);
    header('Location: planos.php'); exit;
}

$planos = $pdo->query("SELECT * FROM planos ORDER BY valor")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Planos - GDS Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Planos</h1>
    <div class="form-box" style="margin-bottom:24px;">
        <h3>Novo plano</h3>
        <form method="post">
            <label>Nome</label><input type="text" name="nome" required>
            <label>Valor (R$)</label><input type="number" step="0.01" name="valor" required>
            <label>Ciclo</label>
            <select name="ciclo"><option value="mensal">Mensal</option><option value="anual">Anual</option></select>
            <label>Máx. de profissionais</label><input type="number" name="max_profissionais" value="1">
            <label style="display:flex;align-items:center;gap:8px;margin-top:12px;">
                <input type="checkbox" name="permite_pix" style="width:auto;" checked> Permite PIX
            </label>
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="permite_ia" style="width:auto;"> Permite IA
            </label>
            <button type="submit" name="criar_plano">Salvar</button>
        </form>
    </div>
    <table>
        <tr><th>Nome</th><th>Valor</th><th>Ciclo</th><th>PIX</th><th>IA</th><th>Status</th><th>Ação</th></tr>
        <?php foreach ($planos as $p): ?>
        <tr>
            <td><?= e($p['nome']) ?></td>
            <td>R$ <?= number_format($p['valor'],2,',','.') ?></td>
            <td><?= e($p['ciclo']) ?></td>
            <td><?= pg_bool($p['permite_pix']) ? 'Sim' : 'Não' ?></td>
            <td><?= pg_bool($p['permite_ia']) ? 'Sim' : 'Não' ?></td>
            <td><span class="badge badge-<?= pg_bool($p['ativo']) ? 'ativo' : 'suspenso' ?>"><?= pg_bool($p['ativo']) ? 'ativo' : 'inativo' ?></span></td>
            <td>
                <?php if (pg_bool($p['ativo'])): ?>
                <a class="btn btn-sm" href="?desativar=<?= $p['id'] ?>">Desativar</a>
                <?php else: ?>
                <a class="btn btn-sm" href="?ativar=<?= $p['id'] ?>">Ativar</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</div>
</body>
</html>
