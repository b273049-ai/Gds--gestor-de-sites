<?php
require_once __DIR__ . '/includes/auth.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$erro = ''; $sucesso = false;

$stmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE token=? AND usado=false AND expira_em > NOW() LIMIT 1");
$stmt->execute([$token]);
$registro = $stmt->fetch();

if (!$registro) {
    $erro = 'Link inválido ou expirado. Solicite uma nova redefinição.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $senha = $_POST['senha'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';
    if (strlen($senha) < 8) {
        $erro = 'A senha precisa ter no mínimo 8 caracteres.';
    } elseif ($senha !== $confirmar) {
        $erro = 'As senhas não coincidem.';
    } else {
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE usuarios SET senha_hash=? WHERE id=?")->execute([$hash, $registro['usuario_id']]);
        $pdo->prepare("UPDATE password_reset_tokens SET usado=true WHERE usuario_id=?")->execute([$registro['usuario_id']]); // invalida todos os links pendentes
        $sucesso = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<?= painel_head('Redefinir senha - GDS') ?>
</head>
<body class="login-page">
<div class="login-box">
    <h1><span class="brand-logo">G</span><div>GDS <span>Nova senha</span></div></h1>
    <?php if ($erro): ?><div class="alert alert-erro"><?= e($erro) ?></div><?php endif; ?>
    <?php if ($sucesso): ?>
        <div class="alert alert-sucesso">Senha alterada! Já pode entrar com a nova senha.</div>
        <p class="rodape-login"><a href="/login.php">Ir para o login</a></p>
    <?php elseif ($registro): ?>
    <form method="post">
        <?= csrf_campo() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label>Nova senha</label>
        <input type="password" name="senha" required minlength="8">
        <label>Confirmar nova senha</label>
        <input type="password" name="confirmar" required minlength="8">
        <button type="submit">Salvar nova senha</button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
