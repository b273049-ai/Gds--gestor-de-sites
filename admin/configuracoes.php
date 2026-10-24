<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_config']) && !csrf_valido()) {
    header('Location: configuracoes.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_config'])) {
    set_config('ia_provider', $_POST['ia_provider']);
    set_config('ia_api_key', trim($_POST['ia_api_key']));
    set_config('mercadopago_access_token', trim($_POST['mercadopago_access_token']));
    set_config('whatsapp_api_token', trim($_POST['whatsapp_api_token']));
    set_config('google_maps_key', trim($_POST['google_maps_key']));
    set_config('google_analytics_id', trim($_POST['google_analytics_id']));
    header('Location: configuracoes.php?ok=1'); exit;
}

$iaProvider = get_config('ia_provider', 'gemini');
$iaApiKey = get_config('ia_api_key', '');
$mpToken = get_config('mercadopago_access_token', '');
$waToken = get_config('whatsapp_api_token', '');
$gmapsKey = get_config('google_maps_key', '');
$gaId = get_config('google_analytics_id', '');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Configurações - GDS Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Configurações</h1>
    <?php if (isset($_GET['ok'])): ?><div class="alert-erro" style="background:#1f4d2e;color:#5ee06c;">Configurações salvas!</div><?php endif; ?>

    <div class="form-box" style="margin-bottom:20px;background:#14183a;border:1px solid #2a2f5c;">
        <h3 style="margin-bottom:8px;">✨ Como conseguir uma chave de IA gratuita</h3>
        <p style="color:#9aa0c0;font-size:13px;line-height:1.6;">
            1. Acesse <strong>aistudio.google.com</strong> e entre com uma conta Google.<br>
            2. Clique em <strong>"Get API key"</strong> → <strong>"Create API key"</strong>.<br>
            3. Não pede cartão de crédito. Copie a chave gerada.<br>
            4. Cole no campo "Chave de API" abaixo e deixe o provedor como <strong>Google Gemini (gratuito)</strong>.
        </p>
    </div>

    <div class="form-box">
        <form method="post">
            <?= csrf_campo() ?>
            <h3>Inteligência Artificial</h3>
            <label>Provedor de IA</label>
            <select name="ia_provider">
                <option value="gemini" <?= $iaProvider=='gemini'?'selected':'' ?>>Google Gemini (gratuito)</option>
                <option value="anthropic" <?= $iaProvider=='anthropic'?'selected':'' ?>>Anthropic Claude (pago)</option>
            </select>
            <label>Chave de API</label>
            <input type="text" name="ia_api_key" value="<?= e($iaApiKey) ?>" placeholder="Cole sua chave aqui">

            <h3 style="margin-top:24px;">Pagamentos</h3>
            <label>Mercado Pago — Access Token</label>
            <input type="text" name="mercadopago_access_token" value="<?= e($mpToken) ?>" placeholder="Opcional por enquanto">

            <h3 style="margin-top:24px;">Comunicação</h3>
            <label>WhatsApp Cloud API — Token</label>
            <input type="text" name="whatsapp_api_token" value="<?= e($waToken) ?>" placeholder="Opcional por enquanto">

            <h3 style="margin-top:24px;">Mapas e Analytics</h3>
            <label>Google Maps — API Key</label>
            <input type="text" name="google_maps_key" value="<?= e($gmapsKey) ?>" placeholder="Opcional por enquanto">
            <label>Google Analytics — ID de acompanhamento</label>
            <input type="text" name="google_analytics_id" value="<?= e($gaId) ?>" placeholder="Ex: G-XXXXXXXXXX">

            <button type="submit" name="salvar_config" style="margin-top:20px;">Salvar Configurações</button>
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
