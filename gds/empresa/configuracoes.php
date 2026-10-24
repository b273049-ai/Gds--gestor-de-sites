<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = empresa_id_atual();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_dados']) && !csrf_valido()) {
    header('Location: configuracoes.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_dados'])) {
    $stmt = $pdo->prepare("UPDATE empresas SET telefone=?, whatsapp=?, endereco=?, logo=? WHERE id=?");
    $stmt->execute([$_POST['telefone'], $_POST['whatsapp'], $_POST['endereco'], $_POST['logo'], $empresaId]);
    header('Location: configuracoes.php?ok=1'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_cor']) && !csrf_valido()) {
    header('Location: configuracoes.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_cor'])) {
    $pdo->prepare("UPDATE sites SET cor_primaria=?, cor_secundaria=? WHERE empresa_id=?")
        ->execute([$_POST['cor_primaria'], $_POST['cor_secundaria'], $empresaId]);
    header('Location: configuracoes.php?ok=1'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_pagamento']) && !csrf_valido()) {
    header('Location: configuracoes.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_pagamento'])) {
    $stmt = $pdo->prepare("UPDATE empresas SET pix_ativo=?, mercadopago_access_token=? WHERE id=?");
    $stmt->execute([isset($_POST['pix_ativo']) ? 'true' : 'false', trim($_POST['mercadopago_access_token']), $empresaId]);
    header('Location: configuracoes.php?ok=1'); exit;
}

$erroSenha = ''; $sucessoSenha = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trocar_senha']) && !csrf_valido()) {
    $erroSenha = 'Sessão expirada, tente novamente.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trocar_senha'])) {
    $atual = $_POST['senha_atual'] ?? '';
    $nova = $_POST['senha_nova'] ?? '';
    $usuario = $pdo->prepare("SELECT * FROM usuarios WHERE id=?");
    $usuario->execute([usuario_logado()]); $usuario = $usuario->fetch();
    if (!password_verify($atual, $usuario['senha_hash'])) {
        $erroSenha = 'Senha atual incorreta.';
    } elseif (strlen($nova) < 6) {
        $erroSenha = 'A nova senha precisa ter no mínimo 6 caracteres.';
    } else {
        $pdo->prepare("UPDATE usuarios SET senha_hash=? WHERE id=?")->execute([password_hash($nova, PASSWORD_DEFAULT), usuario_logado()]);
        $sucessoSenha = 'Senha alterada com sucesso!';
    }
}

$empresa = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();
$site = $pdo->prepare("SELECT * FROM sites WHERE empresa_id=?");
$site->execute([$empresaId]); $site = $site->fetch();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Configurações - Meu Negócio</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Configurações do Negócio</h1>
    <?php if (isset($_GET['ok'])): ?><div class="alert-erro" style="background:#1f4d2e;color:#5ee06c;">Dados atualizados!</div><?php endif; ?>
    <div class="form-box">
        <form method="post">
            <?= csrf_campo() ?>
            <label>Nome do negócio</label>
            <input type="text" value="<?= e($empresa['nome']) ?>" disabled>
            <p style="color:#5c6199;font-size:12px;margin-top:-8px;">Para trocar o nome, fale com o administrador da plataforma.</p>

            <label>E-mail de login</label>
            <input type="text" value="<?= e($empresa['email']) ?>" disabled>

            <label>Telefone</label>
            <input type="text" name="telefone" value="<?= e($empresa['telefone']) ?>">

            <label>WhatsApp (com DDI e DDD, ex: 5511999999999)</label>
            <input type="text" name="whatsapp" value="<?= e($empresa['whatsapp']) ?>">

            <label>Endereço</label>
            <input type="text" name="endereco" value="<?= e($empresa['endereco']) ?>">

            <label>Logo (URL da imagem)</label>
            <input type="text" name="logo" value="<?= e($empresa['logo']) ?>" placeholder="https://...">

            <button type="submit" name="salvar_dados">Salvar</button>
        </form>
    </div>

    <div class="form-box" style="margin-top:20px;">
        <h3>Cor do meu site</h3>
        <p style="color:#9aa0c0;font-size:13px;margin-bottom:10px;">O restante do site (layout, textos, seções) é montado pelo administrador da plataforma — mas a cor de destaque é sua.</p>
        <form method="post">
            <?= csrf_campo() ?>
            <label>Cor primária</label>
            <input type="color" name="cor_primaria" value="<?= e($site['cor_primaria'] ?: '#1a1a2e') ?>">
            <label>Cor secundária</label>
            <input type="color" name="cor_secundaria" value="<?= e($site['cor_secundaria'] ?: '#16213e') ?>">
            <button type="submit" name="salvar_cor" style="margin-top:12px;">Salvar Cor</button>
        </form>
        <p style="margin-top:10px;"><a href="/<?= e($empresa['slug']) ?>" target="_blank" style="color:#4f7cff;font-size:13px;">Ver meu site</a></p>
    </div>

    <div class="form-box" style="margin-top:20px;">
        <h3>Pagamento online (PIX)</h3>
        <p style="color:#9aa0c0;font-size:13px;margin-bottom:10px;">
            Ative pra deixar o cliente pagar direto no site na hora de agendar. O dinheiro cai na SUA conta
            do Mercado Pago, não passa pela plataforma. Pra pegar seu Access Token: crie uma conta grátis em
            mercadopago.com.br → Seu negócio → Configurações → Credenciais → copie o "Access Token de produção".
        </p>
        <form method="post">
            <?= csrf_campo() ?>
            <label style="display:flex;align-items:center;gap:8px;font-weight:normal;color:#e8e8f0;">
                <input type="checkbox" name="pix_ativo" style="width:auto;" <?= pg_bool($empresa['pix_ativo'] ?? false) ? 'checked' : '' ?>>
                Aceitar pagamento por PIX direto no site
            </label>
            <label style="margin-top:12px;">Seu Access Token do Mercado Pago</label>
            <input type="text" name="mercadopago_access_token" value="<?= e($empresa['mercadopago_access_token']) ?>" placeholder="APP_USR-...">
            <button type="submit" name="salvar_pagamento" style="margin-top:12px;">Salvar</button>
        </form>
    </div>

    <div class="form-box" style="margin-top:20px;">
        <h3>Trocar minha senha</h3>
        <?php if ($erroSenha): ?><div class="alert-erro"><?= e($erroSenha) ?></div><?php endif; ?>
        <?php if ($sucessoSenha): ?><div class="alert-erro" style="background:#1f4d2e;color:#5ee06c;"><?= e($sucessoSenha) ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_campo() ?>
            <label>Senha atual</label>
            <input type="password" name="senha_atual" required>
            <label>Nova senha</label>
            <input type="password" name="senha_nova" required minlength="6">
            <button type="submit" name="trocar_senha" style="margin-top:12px;">Trocar Senha</button>
        </form>
    </div>
</div>
</div>
</body>
</html>
