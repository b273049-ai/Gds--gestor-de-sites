<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_template'])) {
    $segmentoId = $_POST['segmento_id'] ?: null;
    $stmt = $pdo->prepare("INSERT INTO templates (nome, segmento_id, cor_primaria, cor_secundaria, layout) VALUES (?,?,?,?,?)");
    $stmt->execute([$_POST['nome'], $segmentoId, $_POST['cor_primaria'], $_POST['cor_secundaria'], $_POST['layout']]);
    header('Location: templates.php'); exit;
}
if (isset($_GET['excluir'])) {
    $pdo->prepare("DELETE FROM templates WHERE id=?")->execute([(int)$_GET['excluir']]);
    header('Location: templates.php'); exit;
}

$segmentos = $pdo->query("SELECT * FROM segmentos ORDER BY nome")->fetchAll();
$templates = $pdo->query("
    SELECT t.*, s.nome as segmento_nome FROM templates t
    LEFT JOIN segmentos s ON s.id = t.segmento_id
    ORDER BY t.nome
")->fetchAll();
$estilos = ['classico'=>'Clássico','bold'=>'Bold','editorial'=>'Editorial','grid'=>'Grid Visual'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Templates - GDS Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Templates</h1>
    <div class="form-box" style="margin-bottom:24px;">
        <h3>Novo template</h3>
        <form method="post">
            <label>Nome</label><input type="text" name="nome" required>
            <label>Segmento (opcional — vazio = universal, serve pra qualquer segmento)</label>
            <select name="segmento_id">
                <option value="">-- universal --</option>
                <?php foreach ($segmentos as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['nome']) ?></option><?php endforeach; ?>
            </select>
            <label>Estilo visual</label>
            <select name="layout">
                <?php foreach ($estilos as $val => $label): ?><option value="<?= $val ?>"><?= $label ?></option><?php endforeach; ?>
            </select>
            <label>Cor primária</label><input type="color" name="cor_primaria" value="#1a1a2e">
            <label>Cor secundária</label><input type="color" name="cor_secundaria" value="#16213e">
            <button type="submit" name="criar_template">Salvar</button>
        </form>
    </div>
    <table>
        <tr><th>Nome</th><th>Segmento</th><th>Estilo</th><th>Cores</th><th>Ação</th></tr>
        <?php foreach ($templates as $t): ?>
        <tr>
            <td><?= e($t['nome']) ?></td>
            <td><?= e($t['segmento_nome'] ?? 'Universal') ?></td>
            <td><?= e($estilos[$t['layout']] ?? $t['layout']) ?></td>
            <td>
                <span style="display:inline-block;width:16px;height:16px;border-radius:4px;background:<?= e($t['cor_primaria']) ?>;vertical-align:middle;"></span>
                <span style="display:inline-block;width:16px;height:16px;border-radius:4px;background:<?= e($t['cor_secundaria']) ?>;vertical-align:middle;"></span>
            </td>
            <td><a class="btn btn-sm" href="?excluir=<?= $t['id'] ?>" onclick="return confirm('Excluir este template?')">Excluir</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</div>
</body>
</html>
