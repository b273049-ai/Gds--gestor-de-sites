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
    if (strlen($senha) < 6) {
        $erro = 'A senha precisa ter no mínimo 6 caracteres.';
    } elseif ($senha !== $confirmar) {
        $erro = 'As senhas não coincidem.';
    } else {
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE usuarios SET senha_hash=? WHERE id=?")->execute([$hash, $registro['usuario_id']]);
        $pdo->prepare("UPDATE password_reset_tokens SET usado=true WHERE id=?")->execute([$registro['id']]);
        $sucesso = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Redefinir senha - GDS</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="login-page">
<div class="login-box">
    <h1>GDS <span>Nova senha</span></h1>
    <?php if ($erro): ?><div class="alert-erro"><?= e($erro) ?></div><?php endif; ?>
    <?php if ($sucesso): ?>
        <div class="alert-erro" style="background:#1f4d2e;color:#5ee06c;">Senha alterada! Já pode entrar com a nova senha.</div>
        <p style="text-align:center;margin-top:14px;"><a href="/login.php" style="color:#4f7cff;font-size:13px;">Ir para o login</a></p>
    <?php elseif ($registro): ?>
    <form method="post">
        <?= csrf_campo() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label>Nova senha</label>
        <input type="password" name="senha" required minlength="6">
        <label>Confirmar nova senha</label>
        <input type="password" name="confirmar" required minlength="6">
        <button type="submit">Salvar nova senha</button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
