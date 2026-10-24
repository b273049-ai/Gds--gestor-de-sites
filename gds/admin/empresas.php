<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$erro = ''; $sucesso = '';

// Criar nova empresa (cria a empresa + o usuário de login dela)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_empresa']) && !csrf_valido()) {
    $erro = 'Sessão expirada, tente novamente.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_empresa'])) {
    $nome = trim($_POST['nome']);
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $nome));
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    $segmento_id = (int)$_POST['segmento_id'];
    $plano_id = (int)$_POST['plano_id'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO empresas (nome, slug, segmento_id, plano_id, email, status, vencimento) VALUES (?, ?, ?, ?, ?, 'pendente', CURRENT_DATE + INTERVAL '30 days')");
        $stmt->execute([$nome, $slug, $segmento_id, $plano_id, $email]);
        $empresaId = $pdo->lastInsertId();

        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt2 = $pdo->prepare("INSERT INTO usuarios (empresa_id, nome, email, senha_hash, role) VALUES (?, ?, ?, ?, 'empresa')");
        $stmt2->execute([$empresaId, $nome, $email, $hash]);

        $stmt3 = $pdo->prepare("INSERT INTO sites (empresa_id, titulo, publicado) VALUES (?, ?, false)");
        $stmt3->execute([$empresaId, $nome]);

        $plano = $pdo->prepare("SELECT valor FROM planos WHERE id=?");
        $plano->execute([$plano_id]); $plano = $plano->fetch();
        if ($plano) {
            $stmt4 = $pdo->prepare("INSERT INTO pagamentos_assinaturas (empresa_id, plano_id, valor, vencimento, status) VALUES (?,?,?, CURRENT_DATE + INTERVAL '30 days', 'pendente')");
            $stmt4->execute([$empresaId, $plano_id, $plano['valor']]);
        }

        $pdo->commit();
        $sucesso = "Empresa criada! Login da empresa: $email";
    } catch (Exception $ex) {
        $pdo->rollBack();
        $erro = 'Erro ao criar empresa: ' . $ex->getMessage();
    }
}

$segmentos = $pdo->query("SELECT * FROM segmentos ORDER BY nome")->fetchAll();
$planos = $pdo->query("SELECT * FROM planos ORDER BY valor")->fetchAll();
$empresas = $pdo->query("
    SELECT e.*, s.nome as segmento_nome, p.nome as plano_nome
    FROM empresas e
    LEFT JOIN segmentos s ON s.id = e.segmento_id
    LEFT JOIN planos p ON p.id = e.plano_id
    ORDER BY e.criado_em DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Empresas - GDS Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Empresas</h1>
    <?php if ($erro): ?><div class="alert-erro"><?= e($erro) ?></div><?php endif; ?>
    <?php if ($sucesso): ?><div class="alert-erro" style="background:#1f4d2e;color:#5ee06c;"><?= e($sucesso) ?></div><?php endif; ?>
    <?php if (isset($_GET['site_criado'])): ?><div class="alert-erro" style="background:#1f4d2e;color:#5ee06c;">Site criado/atualizado com sucesso!</div><?php endif; ?>
    <?php if (isset($_GET['atualizado'])): ?><div class="alert-erro" style="background:#1f4d2e;color:#5ee06c;">Empresa atualizada com sucesso!</div><?php endif; ?>

    <div class="form-box" style="margin-bottom:24px;">
        <h3>Cadastrar nova empresa</h3>
        <form method="post">
            <?= csrf_campo() ?>
            <label>Nome da empresa</label>
            <input type="text" name="nome" required>
            <label>Segmento</label>
            <select name="segmento_id" required>
                <?php foreach ($segmentos as $s): ?>
                <option value="<?= $s['id'] ?>"><?= e($s['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <label>Plano</label>
            <select name="plano_id" required>
                <?php foreach ($planos as $p): ?>
                <option value="<?= $p['id'] ?>"><?= e($p['nome']) ?> - R$ <?= number_format($p['valor'],2,',','.') ?></option>
                <?php endforeach; ?>
            </select>
            <label>E-mail de login da empresa</label>
            <input type="email" name="email" required>
            <label>Senha de acesso</label>
            <input type="password" name="senha" required minlength="6">
            <button type="submit" name="criar_empresa">Criar Empresa</button>
        </form>
    </div>

    <table>
        <tr><th>Empresa</th><th>Segmento</th><th>Plano</th><th>Status</th><th>Vencimento</th><th>Site público</th><th>Site</th><th>Preview</th><th>Empresa</th></tr>
        <?php foreach ($empresas as $emp): ?>
        <tr>
            <td><?= e($emp['nome']) ?></td>
            <td><?= e($emp['segmento_nome'] ?? '-') ?></td>
            <td><?= e($emp['plano_nome'] ?? '-') ?></td>
            <td><span class="badge badge-<?= e($emp['status']) ?>"><?= e($emp['status']) ?></span></td>
            <td><?= $emp['vencimento'] ? date('d/m/Y', strtotime($emp['vencimento'])) : '-' ?></td>
            <td><a href="/<?= e($emp['slug']) ?>" target="_blank">/site/<?= e($emp['slug']) ?></a></td>
            <td><a href="site_hub.php?empresa_id=<?= $emp['id'] ?>" class="btn btn-sm">Gerenciar Site</a></td>
            <td><a href="/site/preview.php?empresa_id=<?= $emp['id'] ?>" target="_blank" class="btn btn-sm">Pré-visualizar</a></td>
            <td><a href="empresa_editar.php?id=<?= $emp['id'] ?>" class="btn btn-sm">Editar Empresa</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</div>
</body>
</html>
