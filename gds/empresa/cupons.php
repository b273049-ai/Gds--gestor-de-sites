<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = empresa_id_atual();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_cupom'])) {
    $stmt = $pdo->prepare("INSERT INTO cupons (empresa_id, codigo, descricao, desconto_percentual, validade) VALUES (?,?,?,?,?)");
    $stmt->execute([$empresaId, strtoupper($_POST['codigo']), $_POST['descricao'], $_POST['desconto_percentual'], $_POST['validade'] ?: null]);
    header('Location: cupons.php'); exit;
}

if (isset($_GET['desativar'])) {
    $pdo->prepare("UPDATE cupons SET ativo=false WHERE id=? AND empresa_id=?")->execute([(int)$_GET['desativar'], $empresaId]);
    header('Location: cupons.php'); exit;
}
if (isset($_GET['ativar'])) {
    $pdo->prepare("UPDATE cupons SET ativo=true WHERE id=? AND empresa_id=?")->execute([(int)$_GET['ativar'], $empresaId]);
    header('Location: cupons.php'); exit;
}

$cupons = $pdo->prepare("SELECT * FROM cupons WHERE empresa_id=? ORDER BY criado_em DESC");
$cupons->execute([$empresaId]); $cupons = $cupons->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cupons - Meu Negócio</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Cupons de Desconto</h1>
    <div class="form-box" style="margin-bottom:24px;">
        <h3>Novo cupom</h3>
        <form method="post">
            <label>Código</label><input type="text" name="codigo" required placeholder="Ex: BEMVINDO10">
            <label>Descrição</label><input type="text" name="descricao" placeholder="Ex: 10% de desconto no primeiro corte">
            <label>Desconto (%)</label><input type="number" step="0.01" name="desconto_percentual" value="10">
            <label>Validade (opcional)</label><input type="date" name="validade">
            <button type="submit" name="criar_cupom">Salvar</button>
        </form>
    </div>
    <table>
        <tr><th>Código</th><th>Desconto</th><th>Validade</th><th>Status</th><th>Ação</th></tr>
        <?php foreach ($cupons as $c): ?>
        <tr>
            <td><?= e($c['codigo']) ?></td>
            <td><?= number_format($c['desconto_percentual'],0) ?>%</td>
            <td><?= $c['validade'] ? date('d/m/Y', strtotime($c['validade'])) : 'Sem validade' ?></td>
            <td><span class="badge badge-<?= pg_bool($c['ativo']) ? 'ativo' : 'suspenso' ?>"><?= pg_bool($c['ativo']) ? 'ativo' : 'inativo' ?></span></td>
            <td>
                <?php if (pg_bool($c['ativo'])): ?>
                <a class="btn btn-sm" href="?desativar=<?= $c['id'] ?>">Desativar</a>
                <?php else: ?>
                <a class="btn btn-sm" href="?ativar=<?= $c['id'] ?>">Ativar</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($cupons)): ?><tr><td colspan="5">Nenhum cupom cadastrado.</td></tr><?php endif; ?>
    </table>
</div>
</div>
</body>
</html>
