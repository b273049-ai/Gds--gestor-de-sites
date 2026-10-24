<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = (int)empresa_id_atual();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_cliente'])) {
    $id = (int)($_POST['cliente_id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if ($nome === '') flash('erro', 'Informe o nome.');
    elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) flash('erro', 'E-mail inválido.');
    else {
        $dados = [$nome, $email ?: null, trim($_POST['telefone'] ?? '') ?: null, trim($_POST['observacoes'] ?? '') ?: null];
        if ($id) {
            $pdo->prepare("UPDATE clientes SET nome=?, email=?, telefone=?, observacoes=? WHERE id=? AND empresa_id=?")->execute([...$dados, $id, $empresaId]);
            flash('sucesso', 'Cliente atualizado.');
        } else {
            $pdo->prepare("INSERT INTO clientes (nome, email, telefone, observacoes, empresa_id) VALUES (?,?,?,?,?)")->execute([...$dados, $empresaId]);
            flash('sucesso', 'Cliente cadastrado.');
        }
    }
    header('Location: clientes.php'); exit;
}
if (isset($_GET['excluir'])) {
    $pdo->prepare("DELETE FROM clientes WHERE id=? AND empresa_id=?")->execute([(int)$_GET['excluir'], $empresaId]);
    flash('sucesso', 'Cliente excluído.');
    header('Location: clientes.php'); exit;
}

// Exportar CSV (abre no Excel)
if (isset($_GET['exportar'])) {
    $st = $pdo->prepare("SELECT nome, email, telefone, observacoes, criado_em FROM clientes WHERE empresa_id=? ORDER BY nome");
    $st->execute([$empresaId]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clientes.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Nome', 'E-mail', 'Telefone', 'Observações', 'Cadastrado em'], ';');
    while ($r = $st->fetch()) fputcsv($out, [$r['nome'], $r['email'], $r['telefone'], $r['observacoes'], date('d/m/Y', strtotime($r['criado_em']))], ';');
    exit;
}

$busca = trim($_GET['q'] ?? '');
$sql = "SELECT c.*, COUNT(a.id) AS visitas, MAX(a.data_hora) AS ultima
        FROM clientes c LEFT JOIN agendamentos a ON a.cliente_id = c.id AND a.status <> 'cancelado'
        WHERE c.empresa_id=?" . ($busca !== '' ? " AND (c.nome ILIKE ? OR c.email ILIKE ? OR c.telefone ILIKE ?)" : '') . "
        GROUP BY c.id ORDER BY c.nome LIMIT 500";
$st = $pdo->prepare($sql);
$st->execute($busca !== '' ? [$empresaId, "%$busca%", "%$busca%", "%$busca%"] : [$empresaId]);
$clientes = $st->fetchAll();
$editando = null;
if (isset($_GET['editar'])) { $e = $pdo->prepare("SELECT * FROM clientes WHERE id=? AND empresa_id=?"); $e->execute([(int)$_GET['editar'], $empresaId]); $editando = $e->fetch(); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Clientes - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header">
        <div><h1>Clientes</h1><p class="subtitulo">Quem agenda pelo site entra aqui automaticamente.</p></div>
        <div class="acoes"><a class="btn btn-ghost" href="?exportar=1">Exportar planilha</a></div>
    </div>
    <?= flash_render() ?>
    <details class="form-box" id="form" <?= $editando ? 'open' : '' ?>>
        <summary><?= icone($editando ? 'lapis' : 'mais') ?> <?= $editando ? 'Editar cliente' : 'Novo cliente' ?></summary>
        <form method="post">
            <?= csrf_campo() ?>
            <input type="hidden" name="cliente_id" value="<?= (int)($editando['id'] ?? 0) ?>">
            <label>Nome</label><input type="text" name="nome" required value="<?= e($editando['nome'] ?? '') ?>">
            <div class="form-row">
                <div><label>E-mail</label><input type="email" name="email" value="<?= e($editando['email'] ?? '') ?>"></div>
                <div><label>Telefone</label><input type="tel" name="telefone" value="<?= e($editando['telefone'] ?? '') ?>"></div>
            </div>
            <label>Observações</label><textarea name="observacoes" rows="2"><?= e($editando['observacoes'] ?? '') ?></textarea>
            <button type="submit" name="salvar_cliente" value="1">Salvar</button>
            <?php if ($editando): ?><a href="clientes.php" class="btn btn-ghost" style="margin-top:18px">Cancelar</a><?php endif; ?>
        </form>
    </details>
    <form class="toolbar" method="get"><input type="search" name="q" placeholder="Buscar por nome, e-mail ou telefone…" value="<?= e($busca) ?>"><button class="btn btn-ghost">Buscar</button><?php if ($busca): ?><a class="link" href="clientes.php">limpar</a><?php endif; ?></form>
    <div class="tabela-wrap"><table>
        <thead><tr><th>Nome</th><th>Contato</th><th>Visitas</th><th>Última</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($clientes as $c): ?>
        <tr>
            <td><strong><?= e($c['nome']) ?></strong><?php if ($c['observacoes']): ?><span class="mini"><?= e(mb_strimwidth($c['observacoes'], 0, 60, '…')) ?></span><?php endif; ?></td>
            <td><?= e($c['email']) ?><?php if ($c['telefone']): ?><span class="mini"><a class="link" target="_blank" href="<?= e(link_whatsapp($c['telefone'])) ?>"><?= e($c['telefone']) ?></a></span><?php endif; ?></td>
            <td><?= (int)$c['visitas'] ?></td>
            <td><?= $c['ultima'] ? date('d/m/Y', strtotime($c['ultima'])) : '—' ?></td>
            <td class="acoes">
                <a class="btn btn-ghost btn-sm" href="?editar=<?= $c['id'] ?>#form"><?= icone('lapis', 14) ?></a>
                <a class="btn btn-perigo btn-sm" href="?excluir=<?= $c['id'] ?><?= csrf_qs() ?>" onclick="return confirm('Excluir este cliente? Os agendamentos dele continuam na agenda, sem o nome.')"><?= icone('lixeira', 14) ?></a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$clientes): ?><tr class="vazio"><td colspan="5"><?= $busca ? 'Nenhum cliente encontrado.' : 'Nenhum cliente ainda.' ?></td></tr><?php endif; ?>
        </tbody>
    </table></div>
</main>
</div>
</body>
</html>
