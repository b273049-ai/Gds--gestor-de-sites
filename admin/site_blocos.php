<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$empresaId = (int)($_GET['empresa_id'] ?? 0);
$empresa = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();
if (!$empresa) { header('Location: empresas.php'); exit; }
$voltar = "Location: site_blocos.php?empresa_id=$empresaId";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_bloco'])) {
    $titulo = trim($_POST['titulo'] ?? ''); $conteudo = trim($_POST['conteudo'] ?? '');
    $id = (int)($_POST['bloco_id'] ?? 0);
    if ($titulo === '' || $conteudo === '') { flash('erro', 'Preencha título e conteúdo.'); header($voltar); exit; }
    if ($id) {
        $pdo->prepare("UPDATE site_blocos SET titulo=?, conteudo=? WHERE id=? AND empresa_id=?")->execute([$titulo, $conteudo, $id, $empresaId]);
        flash('sucesso', 'Bloco atualizado.');
    } else {
        $max = $pdo->prepare("SELECT COALESCE(MAX(ordem),0) m FROM site_blocos WHERE empresa_id=?");
        $max->execute([$empresaId]);
        $pdo->prepare("INSERT INTO site_blocos (empresa_id, titulo, conteudo, ordem) VALUES (?,?,?,?)")->execute([$empresaId, $titulo, $conteudo, $max->fetch()['m'] + 1]);
        flash('sucesso', 'Bloco criado.');
    }
    header($voltar); exit;
}
if (isset($_GET['excluir'])) {
    $pdo->prepare("DELETE FROM site_blocos WHERE id=? AND empresa_id=?")->execute([(int)$_GET['excluir'], $empresaId]);
    flash('sucesso', 'Bloco excluído.');
    header($voltar); exit;
}
if (isset($_GET['ativar']) || isset($_GET['desativar'])) {
    $pdo->prepare("UPDATE site_blocos SET ativo=? WHERE id=? AND empresa_id=?")->execute([isset($_GET['ativar']) ? 'true' : 'false', (int)($_GET['ativar'] ?? $_GET['desativar']), $empresaId]);
    header($voltar); exit;
}
if (isset($_GET['subir']) || isset($_GET['descer'])) {
    $id = (int)($_GET['subir'] ?? $_GET['descer']);
    $sobe = isset($_GET['subir']);
    $atual = $pdo->prepare("SELECT * FROM site_blocos WHERE id=? AND empresa_id=?");
    $atual->execute([$id, $empresaId]); $atual = $atual->fetch();
    if ($atual) {
        $viz = $pdo->prepare("SELECT * FROM site_blocos WHERE empresa_id=? AND ordem " . ($sobe ? '<' : '>') . " ? ORDER BY ordem " . ($sobe ? 'DESC' : 'ASC') . " LIMIT 1");
        $viz->execute([$empresaId, $atual['ordem']]);
        if ($viz = $viz->fetch()) {
            $pdo->prepare("UPDATE site_blocos SET ordem=? WHERE id=?")->execute([$viz['ordem'], $atual['id']]);
            $pdo->prepare("UPDATE site_blocos SET ordem=? WHERE id=?")->execute([$atual['ordem'], $viz['id']]);
        }
    }
    header($voltar); exit;
}

$blocos = $pdo->prepare("SELECT * FROM site_blocos WHERE empresa_id=? ORDER BY ordem, id");
$blocos->execute([$empresaId]); $blocos = $blocos->fetchAll();
$editando = null;
foreach ($blocos as $b) if ((int)$b['id'] === (int)($_GET['editar'] ?? 0)) $editando = $b;
$q = '?empresa_id=' . $empresaId . csrf_qs();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Blocos do Site - GDS Admin') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <a href="site_hub.php?empresa_id=<?= $empresaId ?>" class="link-voltar">← Site de <?= e($empresa['nome']) ?></a>
    <div class="page-header"><div><h1>Blocos de conteúdo</h1><p class="subtitulo">Seções livres — diferenciais, promoção do mês, FAQ. Viram a seção "Destaques" do template.</p></div></div>
    <?= flash_render() ?>
    <form method="post" class="form-box" id="form">
        <?= csrf_campo() ?>
        <h3><?= $editando ? 'Editar bloco' : 'Novo bloco' ?></h3>
        <input type="hidden" name="bloco_id" value="<?= (int)($editando['id'] ?? 0) ?>">
        <label>Título</label><input type="text" name="titulo" required placeholder="Ex: Por que escolher a gente" value="<?= e($editando['titulo'] ?? '') ?>">
        <label>Conteúdo</label><textarea name="conteudo" rows="4" required><?= e($editando['conteudo'] ?? '') ?></textarea>
        <button type="submit" name="salvar_bloco" value="1"><?= $editando ? 'Salvar alterações' : 'Adicionar bloco' ?></button>
        <?php if ($editando): ?><a href="site_blocos.php?empresa_id=<?= $empresaId ?>" class="btn btn-ghost" style="margin-top:18px">Cancelar</a><?php endif; ?>
    </form>
    <div class="tabela-wrap"><table>
        <thead><tr><th>Título</th><th>Conteúdo</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($blocos as $b): ?>
        <tr>
            <td><strong><?= e($b['titulo']) ?></strong></td>
            <td><?= e(mb_strimwidth($b['conteudo'], 0, 70, '…')) ?></td>
            <td><span class="badge badge-<?= pg_bool($b['ativo']) ? 'ativo' : 'inativo' ?>"><?= pg_bool($b['ativo']) ? 'visível' : 'oculto' ?></span></td>
            <td class="acoes">
                <a class="btn btn-ghost btn-sm" href="<?= $q ?>&subir=<?= $b['id'] ?>" aria-label="Subir">↑</a>
                <a class="btn btn-ghost btn-sm" href="<?= $q ?>&descer=<?= $b['id'] ?>" aria-label="Descer">↓</a>
                <a class="btn btn-ghost btn-sm" href="?empresa_id=<?= $empresaId ?>&editar=<?= $b['id'] ?>#form"><?= icone('lapis', 14) ?></a>
                <a class="btn btn-ghost btn-sm" href="<?= $q ?>&<?= pg_bool($b['ativo']) ? 'desativar' : 'ativar' ?>=<?= $b['id'] ?>"><?= pg_bool($b['ativo']) ? 'Ocultar' : 'Mostrar' ?></a>
                <a class="btn btn-perigo btn-sm" href="<?= $q ?>&excluir=<?= $b['id'] ?>" onclick="return confirm('Excluir este bloco?')"><?= icone('lixeira', 14) ?></a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$blocos): ?><tr class="vazio"><td colspan="4">Nenhum bloco criado ainda.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</main>
</div>
</body>
</html>
