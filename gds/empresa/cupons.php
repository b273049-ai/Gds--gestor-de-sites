<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
exigir_modulo('cupons');
$empresaId = (int)empresa_id_atual();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_cupom'])) {
    $codigo = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', $_POST['codigo'] ?? ''));
    $desc = valor_decimal($_POST['desconto_percentual'] ?? '', -1);
    $existe = $pdo->prepare("SELECT 1 FROM cupons WHERE empresa_id=? AND codigo=?"); $existe->execute([$empresaId, $codigo]);
    if ($codigo === '' || $desc <= 0 || $desc > 100) flash('erro', 'Informe um código (letras e números) e um desconto entre 1% e 100%.');
    elseif ($existe->fetch()) flash('erro', 'Você já tem um cupom com esse código.');
    else {
        $pdo->prepare("INSERT INTO cupons (empresa_id, codigo, descricao, desconto_percentual, validade) VALUES (?,?,?,?,?)")
            ->execute([$empresaId, $codigo, trim($_POST['descricao'] ?? ''), $desc, ($_POST['validade'] ?? '') ?: null]);
        flash('sucesso', "Cupom $codigo criado — já aparece no site.");
    }
    header('Location: cupons.php'); exit;
}
if (isset($_GET['ativar']) || isset($_GET['desativar'])) {
    $pdo->prepare("UPDATE cupons SET ativo=? WHERE id=? AND empresa_id=?")->execute([isset($_GET['ativar']) ? 'true' : 'false', (int)($_GET['ativar'] ?? $_GET['desativar']), $empresaId]);
    header('Location: cupons.php'); exit;
}
if (isset($_GET['excluir'])) {
    $pdo->prepare("DELETE FROM cupons WHERE id=? AND empresa_id=?")->execute([(int)$_GET['excluir'], $empresaId]);
    flash('sucesso', 'Cupom excluído.');
    header('Location: cupons.php'); exit;
}
$cupons = $pdo->prepare("SELECT * FROM cupons WHERE empresa_id=? ORDER BY criado_em DESC");
$cupons->execute([$empresaId]); $cupons = $cupons->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Cupons - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Cupons de desconto</h1><p class="subtitulo">Cupons ativos e dentro da validade aparecem no site.</p></div></div>
    <?= flash_render() ?>
    <form method="post" class="form-box">
        <?= csrf_campo() ?>
        <h3>Novo cupom</h3>
        <div class="form-row">
            <div><label>Código</label><input type="text" name="codigo" required placeholder="BEMVINDO10" style="text-transform:uppercase" maxlength="30"></div>
            <div><label>Desconto (%)</label><input type="number" step="1" min="1" max="100" name="desconto_percentual" value="10"></div>
        </div>
        <div class="form-row">
            <div><label>Descrição</label><input type="text" name="descricao" placeholder="10% no primeiro corte"></div>
            <div><label>Validade (opcional)</label><input type="date" name="validade" min="<?= date('Y-m-d') ?>"></div>
        </div>
        <button type="submit" name="criar_cupom" value="1">Criar cupom</button>
    </form>
    <div class="tabela-wrap"><table>
        <thead><tr><th>Código</th><th>Desconto</th><th>Validade</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($cupons as $c): $ativo = pg_bool($c['ativo']); $vencido = $c['validade'] && strtotime($c['validade']) < strtotime('today'); ?>
        <tr>
            <td><strong><?= e($c['codigo']) ?></strong><span class="mini"><?= e($c['descricao']) ?></span></td>
            <td><?= number_format($c['desconto_percentual'], 0) ?>%</td>
            <td><?= $c['validade'] ? date('d/m/Y', strtotime($c['validade'])) : 'Sem validade' ?></td>
            <td><span class="badge badge-<?= $ativo && !$vencido ? 'ativo' : 'inativo' ?>"><?= $vencido ? 'vencido' : ($ativo ? 'ativo' : 'inativo') ?></span></td>
            <td class="acoes">
                <a class="btn btn-ghost btn-sm" href="?<?= $ativo ? 'desativar' : 'ativar' ?>=<?= $c['id'] ?><?= csrf_qs() ?>"><?= $ativo ? 'Desativar' : 'Ativar' ?></a>
                <a class="btn btn-perigo btn-sm" href="?excluir=<?= $c['id'] ?><?= csrf_qs() ?>" onclick="return confirm('Excluir este cupom?')"><?= icone('lixeira', 14) ?></a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$cupons): ?><tr class="vazio"><td colspan="5">Nenhum cupom cadastrado.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</main>
</div>
</body>
</html>
