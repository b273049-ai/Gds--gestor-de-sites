<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = empresa_id_atual();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_profissional'])) {
    $stmt = $pdo->prepare("INSERT INTO profissionais (empresa_id, nome, especialidade, foto) VALUES (?,?,?,?)");
    $stmt->execute([$empresaId, $_POST['nome'], $_POST['especialidade'], $_POST['foto']]);
    header('Location: profissionais.php'); exit;
}

if (isset($_GET['desativar'])) {
    $pdo->prepare("UPDATE profissionais SET ativo=false WHERE id=? AND empresa_id=?")->execute([(int)$_GET['desativar'], $empresaId]);
    header('Location: profissionais.php'); exit;
}
if (isset($_GET['ativar'])) {
    $pdo->prepare("UPDATE profissionais SET ativo=true WHERE id=? AND empresa_id=?")->execute([(int)$_GET['ativar'], $empresaId]);
    header('Location: profissionais.php'); exit;
}

$profissionais = $pdo->prepare("SELECT * FROM profissionais WHERE empresa_id=? ORDER BY nome");
$profissionais->execute([$empresaId]); $profissionais = $profissionais->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Profissionais - Meu Negócio</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Profissionais</h1>
    <div class="form-box" style="margin-bottom:24px;">
        <h3>Novo profissional</h3>
        <form method="post">
            <label>Nome</label><input type="text" name="nome" required>
            <label>Especialidade</label><input type="text" name="especialidade" placeholder="Ex: Barbeiro, Cabeleireira, Mecânico...">
            <label>Foto (URL, opcional)</label><input type="text" name="foto" placeholder="https://...">
            <button type="submit" name="criar_profissional">Salvar</button>
        </form>
    </div>
    <table>
        <tr><th>Nome</th><th>Especialidade</th><th>Status</th><th>Ação</th></tr>
        <?php foreach ($profissionais as $p): ?>
        <tr>
            <td><?= e($p['nome']) ?></td>
            <td><?= e($p['especialidade']) ?></td>
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
        <?php if (empty($profissionais)): ?><tr><td colspan="4">Nenhum profissional cadastrado.</td></tr><?php endif; ?>
    </table>
</div>
</div>
</body>
</html>
