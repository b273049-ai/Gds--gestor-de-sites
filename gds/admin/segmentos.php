<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_segmento'])) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $_POST['nome']));
    $pdo->prepare("INSERT INTO segmentos (nome, slug) VALUES (?,?)")->execute([$_POST['nome'], $slug]);
    header('Location: segmentos.php'); exit;
}
if (isset($_GET['desativar'])) {
    $pdo->prepare("UPDATE segmentos SET ativo=false WHERE id=?")->execute([(int)$_GET['desativar']]);
    header('Location: segmentos.php'); exit;
}
if (isset($_GET['ativar'])) {
    $pdo->prepare("UPDATE segmentos SET ativo=true WHERE id=?")->execute([(int)$_GET['ativar']]);
    header('Location: segmentos.php'); exit;
}

$segmentos = $pdo->query("SELECT * FROM segmentos ORDER BY nome")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Segmentos - GDS Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Segmentos de Negócio</h1>
    <div class="form-box" style="margin-bottom:24px;">
        <h3>Novo segmento</h3>
        <form method="post">
            <label>Nome</label><input type="text" name="nome" required placeholder="Ex: Estúdio de Tatuagem">
            <button type="submit" name="criar_segmento">Salvar</button>
        </form>
    </div>
    <table>
        <tr><th>Nome</th><th>Status</th><th>Ação</th></tr>
        <?php foreach ($segmentos as $s): ?>
        <tr>
            <td><?= e($s['nome']) ?></td>
            <td><span class="badge badge-<?= pg_bool($s['ativo']) ? 'ativo' : 'suspenso' ?>"><?= pg_bool($s['ativo']) ? 'ativo' : 'inativo' ?></span></td>
            <td>
                <?php if (pg_bool($s['ativo'])): ?>
                <a class="btn btn-sm" href="?desativar=<?= $s['id'] ?>">Desativar</a>
                <?php else: ?>
                <a class="btn btn-sm" href="?ativar=<?= $s['id'] ?>">Ativar</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p style="color:#5c6199;font-size:12px;margin-top:10px;">Novos segmentos não têm funcionalidades pré-cadastradas — adicione manualmente via SQL se quiser uma lista específica, ou deixe a empresa usar as funcionalidades universais.</p>
</div>
</div>
</body>
</html>
