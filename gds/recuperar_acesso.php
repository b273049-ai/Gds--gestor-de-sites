<?php
// ============================================================
// RECUPERAÇÃO DE EMERGÊNCIA DO ADMINISTRADOR
// Para quando o administrador esqueceu a senha e o e-mail não chega.
// Só funciona com a CHAVE DE RECUPERAÇÃO gravada no arquivo config/local.php
// (que só quem tem acesso à hospedagem consegue ler/editar):
//     'GDS_CHAVE_RECUPERACAO' => 'uma-frase-longa-que-so-voce-sabe',
// ============================================================
require_once __DIR__ . '/includes/auth.php';

$chaveArquivo = (string)gds_env('GDS_CHAVE_RECUPERACAO', '');
$ativa = mb_strlen($chaveArquivo) >= 16;
$erro = ''; $sucesso = '';
$admins = [];

// Limite: 5 tentativas erradas a cada 15 minutos (para ninguém ficar chutando a chave)
$tentativas = array_values(array_filter(json_decode(get_config('recuperacao_falhas', '[]'), true) ?: [], fn($t) => $t > time() - 900));
$bloqueado = count($tentativas) >= 5;

if ($ativa && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $chave = (string)($_POST['chave'] ?? '');
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $senha = (string)($_POST['senha'] ?? '');
    if ($bloqueado) {
        $erro = 'Muitas tentativas erradas. Espere 15 minutos.';
    } elseif (!hash_equals($chaveArquivo, $chave)) {
        $tentativas[] = time();
        set_config('recuperacao_falhas', json_encode($tentativas));
        $erro = 'Chave de recuperação incorreta.';
        error_log('GDS: tentativa de recuperação de acesso com chave errada (IP ' . ($_SERVER['REMOTE_ADDR'] ?? '?') . ')');
    } elseif (strlen($senha) < 8 || $senha !== ($_POST['confirmar'] ?? '')) {
        $erro = 'A nova senha precisa ter 8 caracteres ou mais e as duas precisam ser iguais.';
    } else {
        $st = $pdo->prepare("SELECT id, nome, email FROM usuarios WHERE role='admin' AND LOWER(email)=? LIMIT 1");
        $st->execute([$email]);
        $u = $st->fetch();
        if (!$u) {
            // Mostra quais e-mails de administrador existem (a chave já foi validada)
            $admins = $pdo->query("SELECT email FROM usuarios WHERE role='admin' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
            $erro = 'Não existe administrador com esse e-mail.';
        } else {
            $pdo->prepare("UPDATE usuarios SET senha_hash=?, ativo=true WHERE id=?")->execute([password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
            $pdo->prepare("UPDATE password_reset_tokens SET usado=true WHERE usuario_id=?")->execute([$u['id']]);
            set_config('recuperacao_falhas', '[]');
            error_log('GDS: senha do administrador ' . $u['email'] . ' redefinida pela recuperação de emergência');
            $sucesso = 'Pronto! A senha de ' . $u['email'] . ' foi trocada. Entre com a nova senha.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Recuperar acesso - GDS') ?><meta name="robots" content="noindex"></head>
<body class="login-page">
<div class="login-box">
    <h1><span class="brand-logo">G</span><div>GDS <span>Recuperação de emergência</span></div></h1>
    <?php if (!$ativa): ?>
        <div class="alert alert-aviso">A recuperação de emergência está desligada.</div>
        <p style="font-size:14px;line-height:1.6;color:var(--text-dim)">Para ligar: na Hostinger, abra o <strong>Gerenciador de Arquivos</strong>, edite o arquivo
            <code>config/local.php</code> e acrescente uma linha antes de <code>];</code>:</p>
        <pre style="white-space:pre-wrap;background:var(--bg);padding:10px;border-radius:8px;font-size:12px">'GDS_CHAVE_RECUPERACAO' => 'uma-frase-longa-que-so-voce-sabe',</pre>
        <p style="font-size:14px;color:var(--text-dim)">Salve e volte a esta página. A frase precisa ter pelo menos 16 caracteres.</p>
    <?php elseif ($sucesso): ?>
        <div class="alert alert-sucesso"><?= e($sucesso) ?></div>
        <p class="rodape-login"><a href="/login.php">Ir para o login</a></p>
    <?php else: ?>
        <?php if ($erro): ?><div class="alert alert-erro"><?= e($erro) ?></div><?php endif; ?>
        <?php if ($admins): ?><p style="font-size:13px;color:var(--text-dim)">Administradores cadastrados: <?= e(implode(', ', $admins)) ?></p><?php endif; ?>
        <form method="post" autocomplete="off">
            <?= csrf_campo() ?>
            <label>Chave de recuperação (está no config/local.php)</label>
            <input type="password" name="chave" required>
            <label>E-mail do administrador</label>
            <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
            <label>Nova senha (mín. 8)</label>
            <input type="password" name="senha" required minlength="8" autocomplete="new-password">
            <label>Confirmar nova senha</label>
            <input type="password" name="confirmar" required minlength="8" autocomplete="new-password">
            <button type="submit" <?= $bloqueado ? 'disabled' : '' ?>>Trocar a senha do administrador</button>
        </form>
    <?php endif; ?>
    <p class="rodape-login"><a href="/login.php">Voltar ao login</a></p>
</div>
</body>
</html>
