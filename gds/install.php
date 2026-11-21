<?php
// ============================================================
// EXECUTE ESTE ARQUIVO UMA ÚNICA VEZ NO NAVEGADOR PARA CRIAR
// O USUÁRIO ADMIN. DEPOIS DISSO, APAGUE ESTE ARQUIVO DO SERVIDOR.
// ============================================================
require_once __DIR__ . '/config/db.php';

// Já existe admin: o instalador não aparece mais (ninguém de fora descobre nem tenta usar)
try {
    if ($pdo->query("SELECT 1 FROM usuarios WHERE role = 'admin' LIMIT 1")->fetchColumn()) {
        http_response_code(404);
        exit('Página não encontrada.');
    }
} catch (PDOException $e) { /* banco ainda sem tabelas: segue para a instalação */ }

$mensagem = '';
$feito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (strlen($senha) < 8) {
        $mensagem = 'A senha precisa ter no mínimo 8 caracteres.';
    } else {
        $check = $pdo->prepare("SELECT id FROM usuarios WHERE role = 'admin' LIMIT 1");
        $check->execute();
        if ($check->fetch()) {
            $mensagem = 'Já existe um admin cadastrado. Por segurança, apague este arquivo (install.php).';
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (empresa_id, nome, email, senha_hash, role) VALUES (NULL, ?, ?, ?, 'admin')");
            $stmt->execute([$nome, mb_strtolower($email), $hash]);
            $feito = true;
            $mensagem = 'Admin criado com sucesso! Agora APAGUE o arquivo install.php do servidor e faça login em /login.php';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Instalação GDS</title>
<style>
body{font-family:Arial,sans-serif;background:#0f1226;color:#fff;display:flex;height:100vh;align-items:center;justify-content:center}
.box{background:#161a3a;padding:32px;border-radius:12px;width:360px}
input{width:100%;padding:10px;margin:8px 0;border-radius:6px;border:none}
button{width:100%;padding:10px;background:#4f7cff;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:bold}
.msg{background:#2a2f5c;padding:10px;border-radius:6px;margin-bottom:12px}
</style>
</head>
<body>
<div class="box">
<h2>Criar usuário Admin</h2>
<?php if ($mensagem): ?><div class="msg"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
<?php if (!$feito): ?>
<form method="post">
<input type="text" name="nome" placeholder="Seu nome" required>
<input type="email" name="email" placeholder="Seu e-mail" required>
<input type="password" name="senha" placeholder="Senha (mín. 8 caracteres)" required>
<button type="submit">Criar Admin</button>
</form>
<?php endif; ?>
</div>
</body>
</html>
