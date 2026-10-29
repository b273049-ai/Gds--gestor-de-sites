<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/email.php';

$mensagem = '';
$falhou = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $email = trim($_POST['email'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE LOWER(email)=? AND ativo=true LIMIT 1");
    $stmt->execute([mb_strtolower($email)]);
    $usuario = $stmt->fetch();

    // Sempre mostra a mesma mensagem, exista ou não o e-mail (evita revelar quem está cadastrado)
    $mensagem = 'Se esse e-mail estiver cadastrado, enviamos um link de redefinição para ele.';

    if ($usuario) {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare("INSERT INTO password_reset_tokens (usuario_id, token, expira_em) VALUES (?,?, NOW() + INTERVAL '1 hour')")
            ->execute([$usuario['id'], $token]);

        $hostSeguro = preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
        $https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $base = rtrim(get_config('plataforma_url', ''), '/') ?: (($https ? 'https://' : 'http://') . $hostSeguro);
        $link = $base . '/redefinir_senha.php?token=' . $token;
        $html = email_modelo('Redefinir sua senha', ['Olá, ' . htmlspecialchars(explode(' ', (string)$usuario['nome'])[0]) . '!', 'Recebemos um pedido para trocar a senha do seu acesso. O link vale por 1 hora.'],
            'Criar nova senha', $link) . '';
        $env = gds_enviar_email($usuario['email'], 'Redefinição de senha', $html);
        if (!$env['ok']) $falhou = true;
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
    <?php if ($mensagem): ?><div class="alert alert-sucesso"><?= e($mensagem) ?></div>
    <p class="dica" style="font-size:13px;color:var(--text-dim);margin-top:10px">Não chegou em 5 minutos? Olhe a caixa de spam. Se for uma empresa, peça ao administrador da plataforma para redefinir a sua senha. Se você é o administrador, use a <a href="/recuperar_acesso.php">recuperação de emergência</a>.</p><?php endif; ?>
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
