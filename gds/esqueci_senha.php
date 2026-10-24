<?php
require_once __DIR__ . '/includes/auth.php';

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $email = trim($_POST['email'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE LOWER(email)=? AND ativo=true LIMIT 1");
    $stmt->execute([mb_strtolower($email)]);
    $usuario = $stmt->fetch();

    // Sempre mostra a mesma mensagem, exista ou não o e-mail (evita revelar quem está cadastrado)
    $mensagem = 'Se esse e-mail estiver cadastrado, enviamos um link de redefinição para ele.';

    if ($usuario) {
        $token = bin2hex(random_bytes(32));
        $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $pdo->prepare("INSERT INTO password_reset_tokens (usuario_id, token, expira_em) VALUES (?,?,?)")
            ->execute([$usuario['id'], $token, $expira]);

        $hostSeguro = preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost');
        $link = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $hostSeguro . '/redefinir_senha.php?token=' . $token;
        $assunto = 'Redefinição de senha - GDS';
        $corpo = "Olá {$usuario['nome']},\n\nClique no link para redefinir sua senha (válido por 1 hora):\n$link\n\nSe não foi você, ignore este e-mail.";
        $headers = "From: nao-responda@" . preg_replace('/:\d+$/', '', $hostSeguro) . "\r\nContent-Type: text/plain; charset=UTF-8";

        // Tenta enviar por e-mail. Em muitas hospedagens compartilhadas mail() funciona nativamente;
        // se não funcionar aqui, é preciso configurar SMTP no lado da hospedagem.
        @mail($usuario['email'], $assunto, $corpo, $headers);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<?= painel_head('Esqueci minha senha - GDS') ?>
</head>
<body class="login-page">
<div class="login-box">
    <h1><span class="brand-logo">G</span><div>GDS <span>Recuperar senha</span></div></h1>
    <?php if ($mensagem): ?><div class="alert alert-sucesso"><?= e($mensagem) ?></div><?php endif; ?>
    <?php if (!$mensagem): ?>
    <form method="post">
        <?= csrf_campo() ?>
        <label>Seu e-mail de login</label>
        <input type="email" name="email" required autofocus>
        <button type="submit">Enviar link de redefinição</button>
    </form>
    <?php endif; ?>
    <p class="rodape-login"><a href="/login.php">Voltar ao login</a></p>
</div>
</body>
</html>
