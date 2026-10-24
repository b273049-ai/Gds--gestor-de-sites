<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$id = (int)($_GET['id'] ?? 0);
$empresa = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
$empresa->execute([$id]); $empresa = $empresa->fetch();
if (!$empresa) { header('Location: empresas.php'); exit; }
$usuario = $pdo->prepare("SELECT * FROM usuarios WHERE empresa_id=? ORDER BY id LIMIT 1");
$usuario->execute([$id]); $usuario = $usuario->fetch();

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar'])) {
    $nome = trim($_POST['nome'] ?? '');
    $email = mb_strtolower(trim($_POST['email'] ?? ''));
    $slug = slugify($_POST['slug'] ?? $empresa['slug']);
    $dom = trim($_POST['dominio_personalizado'] ?? '');
    $dom = $dom !== '' ? preg_replace('#^https?://|^www\.|/.*$#i', '', strtolower($dom)) : null;
    $statusOk = in_array($_POST['status'] ?? '', ['ativo', 'pendente', 'suspenso', 'cancelado'], true);

    $slugUsado = $pdo->prepare("SELECT 1 FROM empresas WHERE slug=? AND id<>?"); $slugUsado->execute([$slug, $id]);
    $domUsado = false;
    if ($dom) { $q = $pdo->prepare("SELECT 1 FROM empresas WHERE dominio_personalizado=? AND id<>?"); $q->execute([$dom, $id]); $domUsado = (bool)$q->fetch(); }

    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $erro = 'Nome e e-mail válidos são obrigatórios.';
    elseif (!$statusOk) $erro = 'Status inválido.';
    elseif ($slugUsado->fetch()) $erro = 'Esse endereço de site já está em uso por outra empresa.';
    elseif ($domUsado) $erro = 'Esse domínio já está ligado a outra empresa.';
    else {
        $pdo->prepare("UPDATE empresas SET nome=?, slug=?, email=?, telefone=?, whatsapp=?, endereco=?, plano_id=?, status=?, vencimento=?, dominio_personalizado=? WHERE id=?")
            ->execute([$nome, $slug, $email, trim($_POST['telefone'] ?? ''), so_digitos($_POST['whatsapp'] ?? ''), trim($_POST['endereco'] ?? ''),
                       (int)$_POST['plano_id'] ?: null, $_POST['status'], $_POST['vencimento'] ?: null, $dom, $id]);
        if ($usuario) {
            $pdo->prepare("UPDATE usuarios SET email=?, ativo=? WHERE id=?")->execute([$email, in_array($_POST['status'], ['ativo', 'pendente'], true) ? 'true' : 'false', $usuario['id']]);
            if (($nova = $_POST['nova_senha'] ?? '') !== '') {
                if (strlen($nova) < 8) { flash('aviso', 'Dados salvos, mas a senha não foi trocada (mínimo 8 caracteres).'); }
                else { $pdo->prepare("UPDATE usuarios SET senha_hash=? WHERE id=?")->execute([password_hash($nova, PASSWORD_DEFAULT), $usuario['id']]); }
            }
        }
        flash('sucesso', 'Empresa atualizada!');
        header("Location: empresa_editar.php?id=$id"); exit;
    }
    $empresa = array_merge($empresa, $_POST);
}

$planos = $pdo->query("SELECT * FROM planos ORDER BY valor")->fetchAll();
$statusOpcoes = ['ativo' => 'Ativo', 'pendente' => 'Pendente', 'suspenso' => 'Suspenso (site sai do ar e login bloqueado)', 'cancelado' => 'Cancelado (site sai do ar e login bloqueado)'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Editar Empresa - GDS Admin') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <a href="empresas.php" class="link-voltar">← Empresas</a>
    <div class="page-header">
        <div><h1><?= e($empresa['nome']) ?></h1><p class="subtitulo">Dados cadastrais, plano e acesso</p></div>
        <div class="acoes">
            <a href="ia_estudio.php?empresa_id=<?= $id ?>" class="btn btn-ia"><?= icone('brilho') ?> Estúdio IA</a>
            <a href="site_hub.php?empresa_id=<?= $id ?>" class="btn btn-ghost"><?= icone('globo') ?> Site</a>
        </div>
    </div>
    <?= flash_render() ?>
    <?php if ($erro): ?><?= alerta('erro', $erro) ?><?php endif; ?>
    <form method="post" class="form-box">
        <?= csrf_campo() ?>
        <h3>Dados da empresa</h3>
        <div class="form-row">
            <div><label>Nome</label><input type="text" name="nome" value="<?= e($empresa['nome']) ?>" required></div>
            <div><label>E-mail (também é o login)</label><input type="email" name="email" value="<?= e($empresa['email']) ?>" required></div>
        </div>
        <div class="form-row">
            <div><label>Telefone</label><input type="text" name="telefone" value="<?= e($empresa['telefone']) ?>"></div>
            <div><label>WhatsApp (com DDI e DDD)</label><input type="text" name="whatsapp" value="<?= e($empresa['whatsapp']) ?>" placeholder="5511999999999"></div>
        </div>
        <label>Endereço</label><input type="text" name="endereco" value="<?= e($empresa['endereco']) ?>">

        <h3 style="margin-top:22px">Endereço do site</h3>
        <div class="form-row">
            <div><label>Endereço na plataforma</label><input type="text" name="slug" value="<?= e($empresa['slug']) ?>"><p class="dica">Mudar quebra links antigos já divulgados.</p></div>
            <div><label>Domínio próprio (opcional)</label><input type="text" name="dominio_personalizado" value="<?= e($empresa['dominio_personalizado']) ?>" placeholder="barbeariadoze.com.br"><p class="dica">Aponte o DNS do domínio para este servidor.</p></div>
        </div>

        <h3 style="margin-top:22px">Plano e situação</h3>
        <div class="form-row">
            <div><label>Plano</label>
                <select name="plano_id"><?php foreach ($planos as $p): ?><option value="<?= $p['id'] ?>" <?= $empresa['plano_id'] == $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?> — R$ <?= number_format($p['valor'], 2, ',', '.') ?><?= pg_bool($p['permite_ia']) ? ' · IA' : '' ?></option><?php endforeach; ?></select></div>
            <div><label>Vencimento</label><input type="date" name="vencimento" value="<?= e($empresa['vencimento']) ?>"></div>
        </div>
        <label>Status</label>
        <select name="status"><?php foreach ($statusOpcoes as $val => $label): ?><option value="<?= $val ?>" <?= $empresa['status'] == $val ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select>

        <h3 style="margin-top:22px">Acesso</h3>
        <label>Nova senha da empresa (deixe vazio para não trocar)</label>
        <input type="password" name="nova_senha" minlength="8" autocomplete="new-password">
        <?php if ($usuario && $usuario['ultimo_login']): ?><p class="dica">Último acesso: <?= date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) ?></p><?php endif; ?>

        <button type="submit" name="salvar" value="1">Salvar alterações</button>
    </form>
</main>
</div>
</body>
</html>
