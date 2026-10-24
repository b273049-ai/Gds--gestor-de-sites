<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_segmento'])) {
    $nome = trim($_POST['nome'] ?? '');
    if ($nome === '') { flash('erro', 'Informe o nome.'); }
    else {
        $pdo->prepare("INSERT INTO segmentos (nome, slug) VALUES (?,?)")->execute([$nome, slug_unico('segmentos', slugify($nome))]);
        flash('sucesso', 'Segmento criado.');
    }
    header('Location: segmentos.php'); exit;
}
if (isset($_GET['desativar']) || isset($_GET['ativar'])) {
    $pdo->prepare("UPDATE segmentos SET ativo=? WHERE id=?")->execute([isset($_GET['ativar']) ? 'true' : 'false', (int)($_GET['ativar'] ?? $_GET['desativar'])]);
    header('Location: segmentos.php'); exit;
}
$segmentos = $pdo->query("SELECT s.*, (SELECT COUNT(*) FROM empresas e WHERE e.segmento_id=s.id) AS qtd FROM segmentos s ORDER BY nome")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Segmentos - GDS Admin') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Segmentos de negócio</h1><p class="subtitulo">O segmento ajuda a IA a entender o tipo de negócio.</p></div></div>
    <?= flash_render() ?>
    <form method="post" class="form-box">
        <?= csrf_campo() ?>
        <h3>Novo segmento</h3>
        <div style="display:flex;gap:10px;align-items:flex-end">
            <div style="flex:1"><label>Nome</label><input type="text" name="nome" required placeholder="Ex: Estúdio de Tatuagem"></div>
            <button type="submit" name="criar_segmento" value="1" style="margin-top:0">Adicionar</button>
        </div>
    </form>
    <div class="tabela-wrap"><table>
        <thead><tr><th>Nome</th><th>Empresas</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($segmentos as $s): $ativo = pg_bool($s['ativo']); ?>
        <tr>
            <td><strong><?= e($s['nome']) ?></strong></td>
            <td><?= (int)$s['qtd'] ?></td>
            <td><span class="badge badge-<?= $ativo ? 'ativo' : 'inativo' ?>"><?= $ativo ? 'ativo' : 'inativo' ?></span></td>
            <td class="acoes"><a class="btn btn-ghost btn-sm" href="?<?= $ativo ? 'desativar' : 'ativar' ?>=<?= $s['id'] ?><?= csrf_qs() ?>"><?= $ativo ? 'Desativar' : 'Ativar' ?></a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</main>
</div>
</body>
</html>
