<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$empresaId = (int)($_GET['empresa_id'] ?? 0);
$empresa = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();
if (!$empresa) { header('Location: empresas.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_bloco'])) {
    $maxOrdem = $pdo->prepare("SELECT COALESCE(MAX(ordem),0) m FROM site_blocos WHERE empresa_id=?");
    $maxOrdem->execute([$empresaId]);
    $ordem = $maxOrdem->fetch()['m'] + 1;
    $stmt = $pdo->prepare("INSERT INTO site_blocos (empresa_id, titulo, conteudo, ordem) VALUES (?,?,?,?)");
    $stmt->execute([$empresaId, $_POST['titulo'], $_POST['conteudo'], $ordem]);
    header("Location: site_blocos.php?empresa_id=$empresaId"); exit;
}

if (isset($_GET['excluir'])) {
    $pdo->prepare("DELETE FROM site_blocos WHERE id=? AND empresa_id=?")->execute([(int)$_GET['excluir'], $empresaId]);
    header("Location: site_blocos.php?empresa_id=$empresaId"); exit;
}
if (isset($_GET['subir']) || isset($_GET['descer'])) {
    $id = (int)($_GET['subir'] ?? $_GET['descer']);
    $direcao = isset($_GET['subir']) ? -1 : 1;
    $atual = $pdo->prepare("SELECT * FROM site_blocos WHERE id=? AND empresa_id=?");
    $atual->execute([$id, $empresaId]); $atual = $atual->fetch();
    if ($atual) {
        $vizinho = $pdo->prepare("SELECT * FROM site_blocos WHERE empresa_id=? AND ordem " . ($direcao < 0 ? '<' : '>') . " ? ORDER BY ordem " . ($direcao < 0 ? 'DESC' : 'ASC') . " LIMIT 1");
        $vizinho->execute([$empresaId, $atual['ordem']]);
        $vizinho = $vizinho->fetch();
        if ($vizinho) {
            $pdo->prepare("UPDATE site_blocos SET ordem=? WHERE id=?")->execute([$vizinho['ordem'], $atual['id']]);
            $pdo->prepare("UPDATE site_blocos SET ordem=? WHERE id=?")->execute([$atual['ordem'], $vizinho['id']]);
        }
    }
    header("Location: site_blocos.php?empresa_id=$empresaId"); exit;
}

$blocos = $pdo->prepare("SELECT * FROM site_blocos WHERE empresa_id=? ORDER BY ordem, id");
$blocos->execute([$empresaId]); $blocos = $blocos->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Blocos do Site - GDS Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Blocos de Conteúdo — <?= e($empresa['nome']) ?></h1>
    <p style="margin-bottom:14px;"><a href="site_hub.php?empresa_id=<?= $empresaId ?>" style="color:#4f7cff;font-size:13px;">← voltar pro painel de site desta empresa</a></p>
    <p style="color:#9aa0c0;margin-bottom:14px;max-width:560px;">
        Seções livres pro site — diferenciais, promoção do mês, FAQ, o que fizer sentido. Viram uma seção
        chamada "Destaques" no site público, na ordem definida aqui.
    </p>
    <div class="form-box" style="margin-bottom:24px;">
        <h3>Novo bloco</h3>
        <form method="post">
            <label>Título</label><input type="text" name="titulo" required placeholder="Ex: Por que escolher a gente">
            <label>Conteúdo</label><textarea name="conteudo" rows="4" required></textarea>
            <button type="submit" name="criar_bloco">Salvar</button>
        </form>
    </div>
    <table>
        <tr><th>Título</th><th>Conteúdo</th><th>Ordem</th></tr>
        <?php foreach ($blocos as $b): ?>
        <tr>
            <td><?= e($b['titulo']) ?></td>
            <td><?= e(mb_substr($b['conteudo'],0,60)) ?><?= mb_strlen($b['conteudo'])>60?'...':'' ?></td>
            <td>
                <a class="btn btn-sm" href="?empresa_id=<?= $empresaId ?>&subir=<?= $b['id'] ?>">↑</a>
                <a class="btn btn-sm" href="?empresa_id=<?= $empresaId ?>&descer=<?= $b['id'] ?>">↓</a>
                <a class="btn btn-sm" href="?empresa_id=<?= $empresaId ?>&excluir=<?= $b['id'] ?>" onclick="return confirm('Excluir este bloco?')">Excluir</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($blocos)): ?><tr><td colspan="3">Nenhum bloco criado ainda.</td></tr><?php endif; ?>
    </table>
</div>
</div>
</body>
</html>
