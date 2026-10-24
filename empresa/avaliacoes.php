<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = (int)empresa_id_atual();

if (isset($_GET['aprovar']) || isset($_GET['reprovar'])) {
    $pdo->prepare("UPDATE avaliacoes SET aprovado=? WHERE id=? AND empresa_id=?")->execute([isset($_GET['aprovar']) ? 'true' : 'false', (int)($_GET['aprovar'] ?? $_GET['reprovar']), $empresaId]);
    flash('sucesso', isset($_GET['aprovar']) ? 'Avaliação publicada no site.' : 'Avaliação ocultada do site.');
    header('Location: avaliacoes.php'); exit;
}
if (isset($_GET['excluir'])) {
    $pdo->prepare("DELETE FROM avaliacoes WHERE id=? AND empresa_id=?")->execute([(int)$_GET['excluir'], $empresaId]);
    flash('sucesso', 'Avaliação excluída.');
    header('Location: avaliacoes.php'); exit;
}
// Registrar uma avaliação recebida por fora (WhatsApp, Google, pessoalmente)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_avaliacao'])) {
    $nota = (int)($_POST['nota'] ?? 0);
    $autor = trim($_POST['nome_autor'] ?? '');
    if ($nota < 1 || $nota > 5 || $autor === '') flash('erro', 'Informe o nome do cliente e a nota.');
    else {
        $pdo->prepare("INSERT INTO avaliacoes (empresa_id, nota, comentario, nome_autor, aprovado, origem) VALUES (?,?,?,?,true,'painel')")
            ->execute([$empresaId, $nota, trim($_POST['comentario'] ?? ''), $autor]);
        flash('sucesso', 'Avaliação adicionada.');
    }
    header('Location: avaliacoes.php'); exit;
}

$avaliacoes = $pdo->prepare("
    SELECT av.*, COALESCE(NULLIF(av.nome_autor,''), c.nome, 'Cliente') AS autor FROM avaliacoes av
    LEFT JOIN clientes c ON c.id = av.cliente_id
    WHERE av.empresa_id=? ORDER BY av.aprovado ASC, av.criado_em DESC");
$avaliacoes->execute([$empresaId]); $avaliacoes = $avaliacoes->fetchAll();
$aprovadas = array_filter($avaliacoes, fn($a) => pg_bool($a['aprovado']));
$pendentes = count($avaliacoes) - count($aprovadas);
$media = $aprovadas ? array_sum(array_column($aprovadas, 'nota')) / count($aprovadas) : 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Avaliações - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Avaliações</h1><p class="subtitulo">Clientes avaliam pelo site; você decide o que aparece.</p></div></div>
    <?= flash_render() ?>
    <div class="cards">
        <div class="card"><div class="label"><?= icone('estrela', 16) ?> Nota média</div><div class="value"><?= number_format($media, 1, ',', '') ?> ★</div></div>
        <div class="card"><div class="label">Publicadas</div><div class="value"><?= count($aprovadas) ?></div></div>
        <div class="card"><div class="label">Aguardando aprovação</div><div class="value" style="color:<?= $pendentes ? 'var(--warn)' : 'inherit' ?>"><?= $pendentes ?></div></div>
    </div>
    <details class="form-box">
        <summary><?= icone('mais') ?> Adicionar avaliação recebida por fora</summary>
        <form method="post">
            <?= csrf_campo() ?>
            <div class="form-row">
                <div><label>Nome do cliente</label><input type="text" name="nome_autor" required></div>
                <div><label>Nota</label><select name="nota"><?php for ($n = 5; $n >= 1; $n--): ?><option value="<?= $n ?>"><?= str_repeat('★', $n) ?></option><?php endfor; ?></select></div>
            </div>
            <label>Comentário</label><textarea name="comentario" rows="2"></textarea>
            <button type="submit" name="nova_avaliacao" value="1">Adicionar</button>
        </form>
    </details>
    <div class="tabela-wrap"><table>
        <thead><tr><th>Cliente</th><th>Nota</th><th>Comentário</th><th>Data</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($avaliacoes as $av): $ok = pg_bool($av['aprovado']); ?>
        <tr>
            <td><strong><?= e($av['autor']) ?></strong><span class="mini"><?= ($av['origem'] ?? '') === 'site' ? 'pelo site' : 'adicionada no painel' ?></span></td>
            <td style="color:var(--warn)"><?= str_repeat('★', (int)$av['nota']) ?></td>
            <td><?= e($av['comentario']) ?></td>
            <td><?= date('d/m/Y', strtotime($av['criado_em'])) ?></td>
            <td><span class="badge badge-<?= $ok ? 'ativo' : 'pendente' ?>"><?= $ok ? 'no site' : 'pendente' ?></span></td>
            <td class="acoes">
                <?php if ($ok): ?><a class="btn btn-ghost btn-sm" href="?reprovar=<?= $av['id'] ?><?= csrf_qs() ?>">Ocultar</a>
                <?php else: ?><a class="btn btn-sm" href="?aprovar=<?= $av['id'] ?><?= csrf_qs() ?>">Aprovar</a><?php endif; ?>
                <a class="btn btn-perigo btn-sm" href="?excluir=<?= $av['id'] ?><?= csrf_qs() ?>" onclick="return confirm('Excluir esta avaliação?')"><?= icone('lixeira', 14) ?></a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$avaliacoes): ?><tr class="vazio"><td colspan="6">Nenhuma avaliação recebida ainda. Seus clientes podem avaliar direto no site.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</main>
</div>
</body>
</html>
