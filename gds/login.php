<?php
require_once __DIR__ . '/includes/auth.php';

$erro = '';

// Já está logado? Vai direto pro painel
if (!empty($_SESSION['usuario_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . (($_SESSION['role'] ?? '') === 'admin' ? '/admin/index.php' : '/empresa/index.php')); exit;
}

$MAX_TENTATIVAS = 5;
$BLOQUEIO_MINUTOS = 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $erro = 'Sessão expirada, tente novamente.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $idTentativa = mb_strtolower($email);

        $tent = $pdo->prepare("SELECT * FROM login_tentativas WHERE identificador=?");
        $tent->execute([$idTentativa]);
        $tent = $tent->fetch();

        if ($tent && $tent['bloqueado_ate'] && strtotime($tent['bloqueado_ate']) > time()) {
            $minutosRestantes = (int)ceil((strtotime($tent['bloqueado_ate']) - time()) / 60);
            $erro = "Muitas tentativas erradas. Tente novamente em $minutosRestantes minuto(s), ou use \"Esqueci minha senha\".";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE LOWER(email) = ? AND ativo = true LIMIT 1");
            $stmt->execute([$idTentativa]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
                if ($tent) { $pdo->prepare("DELETE FROM login_tentativas WHERE identificador=?")->execute([$idTentativa]); }

                session_regenerate_id(true);
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['nome']       = $usuario['nome'];
                $_SESSION['role']       = $usuario['role'];
                $_SESSION['empresa_id'] = $usuario['empresa_id'];

                $upd = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
                $upd->execute([$usuario['id']]);

                if ($usuario['role'] === 'admin') {
                    header('Location: /admin/index.php');
                } else {
                    header('Location: /empresa/index.php');
                }
                exit;
            } else {
                if ($tent) {
                    $novasTentativas = $tent['tentativas'] + 1;
                    $bloqueado = $novasTentativas >= $MAX_TENTATIVAS ? date('Y-m-d H:i:s', strtotime("+$BLOQUEIO_MINUTOS minutes")) : null;
                    $pdo->prepare("UPDATE login_tentativas SET tentativas=?, bloqueado_ate=?, atualizado_em=NOW() WHERE identificador=?")
                        ->execute([$novasTentativas, $bloqueado, $idTentativa]);
                } else {
                    $pdo->prepare("INSERT INTO login_tentativas (identificador, tentativas) VALUES (?, 1)")->execute([$idTentativa]);
                }
                $erro = 'E-mail ou senha inválidos.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<?= painel_head('Login - GDS') ?>
</head>
<body class="login-page">
<div class="login-box">
    <h1><span class="brand-logo">G</span><div>GDS <span>Gestor de Sites</span></div></h1>
    <?php if ($erro): ?><div class="alert alert-erro"><?= e($erro) ?></div><?php endif; ?>
    <?php if (isset($_GET['erro']) && $_GET['erro'] === 'acesso_negado'): ?>
        <div class="alert alert-erro">Você não tem acesso a essa área.</div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_campo() ?>
        <label>E-mail</label>
        <input type="email" name="email" required autofocus autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>">
        <label>Senha</label>
        <input type="password" name="senha" required autocomplete="current-password">
        <button type="submit">Entrar</button>
    </form>
    <p class="rodape-login"><a href="/esqueci_senha.php">Esqueci minha senha</a></p>
</div>
</body>
</html>
