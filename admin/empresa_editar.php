<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("UPDATE empresas SET nome=?, email=?, telefone=?, whatsapp=?, endereco=?, plano_id=?, status=?, vencimento=?, dominio_personalizado=? WHERE id=?");
    $dom = trim($_POST['dominio_personalizado'] ?? '');
    $dom = $dom !== '' ? preg_replace('/^https?:\/\/|^www\.|\/$/i', '', strtolower($dom)) : null;
    $stmt->execute([
        $_POST['nome'], $_POST['email'], $_POST['telefone'], $_POST['whatsapp'], $_POST['endereco'],
        $_POST['plano_id'], $_POST['status'], $_POST['vencimento'] ?: null, $dom, $id
    ]);
    header('Location: empresas.php?atualizado=1'); exit;
}

$empresa = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
$empresa->execute([$id]); $empresa = $empresa->fetch();
if (!$empresa) { header('Location: empresas.php'); exit; }

$planos = $pdo->query("SELECT * FROM planos ORDER BY valor")->fetchAll();
$statusOpcoes = ['ativo'=>'Ativo','pendente'=>'Pendente','suspenso'=>'Suspenso','cancelado'=>'Cancelado'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Editar Empresa - GDS Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Editar Empresa</h1>
    <div class="form-box">
        <form method="post">
            <label>Nome</label><input type="text" name="nome" value="<?= e($empresa['nome']) ?>" required>
            <label>E-mail</label><input type="email" name="email" value="<?= e($empresa['email']) ?>" required>
            <label>Telefone</label><input type="text" name="telefone" value="<?= e($empresa['telefone']) ?>">
            <label>WhatsApp</label><input type="text" name="whatsapp" value="<?= e($empresa['whatsapp']) ?>" placeholder="Ex: 5511999999999">
            <label>Endereço</label><input type="text" name="endereco" value="<?= e($empresa['endereco']) ?>">
            <label>Domínio próprio (opcional, ex: barbeariadoze.com.br)</label><input type="text" name="dominio_personalizado" value="<?= e($empresa['dominio_personalizado']) ?>">
            <label>Plano</label>
            <select name="plano_id">
                <?php foreach ($planos as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $empresa['plano_id']==$p['id']?'selected':'' ?>><?= e($p['nome']) ?> - R$ <?= number_format($p['valor'],2,',','.') ?></option>
                <?php endforeach; ?>
            </select>
            <label>Status</label>
            <select name="status">
                <?php foreach ($statusOpcoes as $val => $label): ?>
                <option value="<?= $val ?>" <?= $empresa['status']==$val?'selected':'' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <label>Vencimento</label>
            <input type="date" name="vencimento" value="<?= e($empresa['vencimento']) ?>">
            <button type="submit">Salvar Alterações</button>
        </form>
    </div>
</div>
</div>
</body>
</html>
