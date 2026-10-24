<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = (int)empresa_id_atual();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_servico'])) {
    $id = (int)($_POST['servico_id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $preco = valor_decimal($_POST['preco'] ?? '0', -1);
    $duracao = (int)($_POST['duracao_minutos'] ?? 30);
    if ($nome === '' || $preco < 0 || $duracao < 5 || $duracao > 720) {
        flash('erro', 'Confira os dados: nome obrigatório, preço válido e duração entre 5 e 720 minutos.');
    } elseif ($id) {
        $pdo->prepare("UPDATE servicos SET nome=?, descricao=?, preco=?, duracao_minutos=? WHERE id=? AND empresa_id=?")
            ->execute([$nome, trim($_POST['descricao'] ?? ''), $preco, $duracao, $id, $empresaId]);
        flash('sucesso', 'Serviço atualizado.');
    } else {
        $pdo->prepare("INSERT INTO servicos (empresa_id, nome, descricao, preco, duracao_minutos) VALUES (?,?,?,?,?)")
            ->execute([$empresaId, $nome, trim($_POST['descricao'] ?? ''), $preco, $duracao]);
        flash('sucesso', 'Serviço criado — já aparece no site.');
    }
    header('Location: servicos.php'); exit;
}
if (isset($_GET['ativar']) || isset($_GET['desativar'])) {
    $pdo->prepare("UPDATE servicos SET ativo=? WHERE id=? AND empresa_id=?")->execute([isset($_GET['ativar']) ? 'true' : 'false', (int)($_GET['ativar'] ?? $_GET['desativar']), $empresaId]);
    header('Location: servicos.php'); exit;
}
if (isset($_GET['excluir'])) {
    $pdo->prepare("DELETE FROM servicos WHERE id=? AND empresa_id=?")->execute([(int)$_GET['excluir'], $empresaId]);
    flash('sucesso', 'Serviço excluído.');
    header('Location: servicos.php'); exit;
}

$servicos = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM agendamentos a WHERE a.servico_id=s.id) AS usos FROM servicos s WHERE empresa_id=? ORDER BY ativo DESC, nome");
$servicos->execute([$empresaId]); $servicos = $servicos->fetchAll();
$editando = null;
foreach ($servicos as $s) if ((int)$s['id'] === (int)($_GET['editar'] ?? 0)) $editando = $s;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Serviços - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Serviços</h1><p class="subtitulo">O que você oferece — aparece no site com preço e no agendamento online.</p></div></div>
    <?= flash_render() ?>
    <form method="post" class="form-box" id="form">
        <?= csrf_campo() ?>
        <h3><?= $editando ? 'Editar serviço' : 'Novo serviço' ?></h3>
        <input type="hidden" name="servico_id" value="<?= (int)($editando['id'] ?? 0) ?>">
        <label>Nome</label><input type="text" name="nome" required maxlength="150" value="<?= e($editando['nome'] ?? '') ?>">
        <label>Descrição</label><textarea name="descricao" rows="2" placeholder="O que está incluso, diferenciais…"><?= e($editando['descricao'] ?? '') ?></textarea>
        <div class="form-row">
            <div><label>Preço (R$) — 0 = sob consulta</label><input type="text" inputmode="decimal" name="preco" value="<?= e(number_format((float)($editando['preco'] ?? 0), 2, ',', '')) ?>"></div>
            <div><label>Duração (minutos)</label><input type="number" min="5" max="720" step="5" name="duracao_minutos" value="<?= (int)($editando['duracao_minutos'] ?? 30) ?>"></div>
        </div>
        <button type="submit" name="salvar_servico" value="1"><?= $editando ? 'Salvar alterações' : 'Adicionar serviço' ?></button>
        <?php if ($editando): ?><a href="servicos.php" class="btn btn-ghost" style="margin-top:18px">Cancelar</a><?php endif; ?>
    </form>
    <div class="tabela-wrap"><table>
        <thead><tr><th>Serviço</th><th>Preço</th><th>Duração</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($servicos as $s): $ativo = pg_bool($s['ativo']); ?>
        <tr style="<?= $ativo ? '' : 'opacity:.6' ?>">
            <td><strong><?= e($s['nome']) ?></strong><?php if ($s['descricao']): ?><span class="mini"><?= e(mb_strimwidth($s['descricao'], 0, 80, '…')) ?></span><?php endif; ?></td>
            <td><?= (float)$s['preco'] > 0 ? 'R$ ' . number_format($s['preco'], 2, ',', '.') : 'Sob consulta' ?></td>
            <td><?= (int)$s['duracao_minutos'] ?> min</td>
            <td><span class="badge badge-<?= $ativo ? 'ativo' : 'inativo' ?>"><?= $ativo ? 'no site' : 'oculto' ?></span></td>
            <td class="acoes">
                <a class="btn btn-ghost btn-sm" href="?editar=<?= $s['id'] ?>#form"><?= icone('lapis', 14) ?></a>
                <a class="btn btn-ghost btn-sm" href="?<?= $ativo ? 'desativar' : 'ativar' ?>=<?= $s['id'] ?><?= csrf_qs() ?>"><?= $ativo ? 'Ocultar' : 'Mostrar' ?></a>
                <a class="btn btn-perigo btn-sm" href="?excluir=<?= $s['id'] ?><?= csrf_qs() ?>" onclick="return confirm('Excluir o serviço <?= e(addslashes($s['nome'])) ?>?<?= $s['usos'] ? ' Ele aparece em ' . (int)$s['usos'] . ' agendamento(s) — prefira Ocultar.' : '' ?>')"><?= icone('lixeira', 14) ?></a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$servicos): ?><tr class="vazio"><td colspan="5">Nenhum serviço ainda. Cadastre o primeiro acima.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</main>
</div>
</body>
</html>
