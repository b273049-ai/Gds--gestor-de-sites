<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$estilos = ['classico' => 'Clássico', 'bold' => 'Bold', 'editorial' => 'Editorial', 'grid' => 'Grid Visual'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_template'])) {
    $nome = trim($_POST['nome'] ?? '');
    if ($nome === '') { flash('erro', 'Informe o nome.'); }
    else {
        $pdo->prepare("INSERT INTO templates (nome, segmento_id, cor_primaria, cor_secundaria, layout) VALUES (?,?,?,?,?)")
            ->execute([$nome, (int)($_POST['segmento_id'] ?? 0) ?: null, cor_hex_valida($_POST['cor_primaria'] ?? '', '#1a1a2e'),
                       cor_hex_valida($_POST['cor_secundaria'] ?? '', '#16213e'), isset($estilos[$_POST['layout'] ?? '']) ? $_POST['layout'] : 'classico']);
        flash('sucesso', 'Template criado.');
    }
    header('Location: templates.php'); exit;
}
if (isset($_GET['excluir'])) {
    $pdo->prepare("DELETE FROM templates WHERE id=?")->execute([(int)$_GET['excluir']]);
    flash('sucesso', 'Template excluído.');
    header('Location: templates.php'); exit;
}

$segmentos = $pdo->query("SELECT * FROM segmentos WHERE ativo=true " . SEGMENTOS_ORDEM)->fetchAll();
$templates = $pdo->query("SELECT t.*, s.nome AS segmento_nome FROM templates t LEFT JOIN segmentos s ON s.id = t.segmento_id ORDER BY t.nome")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Templates - GDS Admin') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Templates</h1><p class="subtitulo">Combinações prontas de estilo e cores para o modo template.</p></div></div>
    <?= flash_render() ?>
    <details class="form-box" <?= $templates ? '' : 'open' ?>>
        <summary><?= icone('mais') ?> Novo template</summary>
        <form method="post">
            <?= csrf_campo() ?>
            <label>Nome</label><input type="text" name="nome" required>
            <div class="form-row">
                <div><label>Segmento (vazio = universal)</label>
                    <select name="segmento_id"><option value="">— universal —</option><?= segmentos_options($segmentos) ?></select></div>
                <div><label>Estilo visual</label>
                    <select name="layout"><?php foreach ($estilos as $val => $label): ?><option value="<?= $val ?>"><?= $label ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-row">
                <div><label>Cor primária</label><input type="color" name="cor_primaria" value="#4f7cff"></div>
                <div><label>Cor secundária</label><input type="color" name="cor_secundaria" value="#16213e"></div>
            </div>
            <button type="submit" name="criar_template" value="1">Salvar</button>
        </form>
    </details>
    <div class="tabela-wrap"><table>
        <thead><tr><th>Nome</th><th>Segmento</th><th>Estilo</th><th>Cores</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($templates as $t): ?>
        <tr>
            <td><strong><?= e($t['nome']) ?></strong></td>
            <td><?= e($t['segmento_nome'] ?? 'Universal') ?></td>
            <td><?= e($estilos[$t['layout']] ?? $t['layout']) ?></td>
            <td><span class="cor-amostra" style="background:<?= e(cor_hex_valida($t['cor_primaria'], '#000000')) ?>"></span> <span class="cor-amostra" style="background:<?= e(cor_hex_valida($t['cor_secundaria'], '#000000')) ?>"></span></td>
            <td class="acoes"><a class="btn btn-perigo btn-sm" href="?excluir=<?= $t['id'] ?><?= csrf_qs() ?>" onclick="return confirm('Excluir este template?')"><?= icone('lixeira', 14) ?></a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$templates): ?><tr class="vazio"><td colspan="5">Nenhum template.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</main>
</div>
</body>
</html>
