<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = empresa_id_atual();

if (isset($_GET['excluir'])) {
    $pdo->prepare("DELETE FROM leads WHERE id=? AND empresa_id=?")->execute([(int)$_GET['excluir'], $empresaId]);
    header('Location: leads.php'); exit;
}

$leads = $pdo->prepare("SELECT * FROM leads WHERE empresa_id=? ORDER BY criado_em DESC");
$leads->execute([$empresaId]); $leads = $leads->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Leads - Meu Negócio</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Leads / Contatos do Site</h1>
    <p style="color:#9aa0c0;margin-bottom:14px;">Mensagens enviadas pelo formulário de contato do seu site público.</p>
    <table>
        <tr><th>Nome</th><th>Contato</th><th>Mensagem</th><th>Recebido em</th><th>Ação</th></tr>
        <?php foreach ($leads as $l): ?>
        <tr>
            <td><?= e($l['nome']) ?></td>
            <td><?= e($l['email']) ?><?= $l['telefone'] ? ' / ' . e($l['telefone']) : '' ?></td>
            <td><?= e($l['mensagem']) ?></td>
            <td><?= date('d/m/Y H:i', strtotime($l['criado_em'])) ?></td>
            <td><a class="btn btn-sm" href="?excluir=<?= $l['id'] ?>" onclick="return confirm('Excluir este contato?')">Excluir</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($leads)): ?><tr><td colspan="5">Nenhum contato recebido ainda.</td></tr><?php endif; ?>
    </table>
</div>
</div>
</body>
</html>
