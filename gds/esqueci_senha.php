<?php
require_once __DIR__ . '/includes/auth.php';

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $email = trim($_POST['email'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email=? AND ativo=true LIMIT 1");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    // Sempre mostra a mesma mensagem, exista ou não o e-mail (evita revelar quem está cadastrado)
    $mensagem = 'Se esse e-mail estiver cadastrado, enviamos um link de redefinição para ele.';

    if ($usuario) {
        $token = bin2hex(random_bytes(32));
        $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $pdo->prepare("INSERT INTO password_reset_tokens (usuario_id, token, expira_em) VALUES (?,?,?)")
            ->execute([$usuario['id'], $token, $expira]);

        $link = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/redefinir_senha.php?token=' . $token;
        $assunto = 'Redefinição de senha - GDS';
        $corpo = "Olá {$usuario['nome']},\n\nClique no link para redefinir sua senha (válido por 1 hora):\n$link\n\nSe não foi você, ignore este e-mail.";
        $headers = "From: nao-responda@" . $_SERVER['HTTP_HOST'];

        // Tenta enviar por e-mail. Em muitas hospedagens compartilhadas mail() funciona nativamente;
        // se não funcionar aqui, é preciso configurar SMTP no lado da hospedagem.
        @mail($usuario['email'], $assunto, $corpo, $headers);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Esqueci minha senha - GDS</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="login-page">
<div class="login-box">
    <h1>GDS <span>Recuperar senha</span></h1>
    <?php if ($mensagem): ?><div class="alert-erro" style="background:#1f4d2e;color:#5ee06c;"><?= e($mensagem) ?></div><?php endif; ?>
    <?php if (!$mensagem): ?>
    <form method="post">
        <?= csrf_campo() ?>
        <label>Seu e-mail de login</label>
        <input type="email" name="email" required autofocus>
        <button type="submit">Enviar link de redefinição</button>
    </form>
    <?php endif; ?>
    <p style="text-align:center;margin-top:14px;"><a href="/login.php" style="color:#4f7cff;font-size:13px;">Voltar ao login</a></p>
</div>
</body>
</html>
