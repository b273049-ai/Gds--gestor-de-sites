<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = (int)empresa_id_atual();

if (isset($_GET['excluir'])) {
    $pdo->prepare("DELETE FROM leads WHERE id=? AND empresa_id=?")->execute([(int)$_GET['excluir'], $empresaId]);
    flash('sucesso', 'Contato excluído.');
    header('Location: leads.php'); exit;
}
// Transforma o contato do site em cliente cadastrado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['virar_cliente'])) {
    $l = $pdo->prepare("SELECT * FROM leads WHERE id=? AND empresa_id=?");
    $l->execute([(int)$_POST['lead_id'], $empresaId]);
    if ($l = $l->fetch()) {
        $pdo->prepare("INSERT INTO clientes (empresa_id, nome, email, telefone, observacoes) VALUES (?,?,?,?,?)")
            ->execute([$empresaId, $l['nome'] ?: 'Contato do site', $l['email'], $l['telefone'], 'Veio pelo site: ' . mb_substr((string)$l['mensagem'], 0, 300)]);
        flash('sucesso', 'Contato adicionado aos clientes.');
    }
    header('Location: leads.php'); exit;
}

$leads = $pdo->prepare("SELECT * FROM leads WHERE empresa_id=? ORDER BY criado_em DESC LIMIT 300");
$leads->execute([$empresaId]); $leads = $leads->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Contatos do site - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Contatos do site</h1><p class="subtitulo">Mensagens enviadas pelo formulário de contato do seu site.</p></div></div>
    <?= flash_render() ?>
    <div class="tabela-wrap"><table>
        <thead><tr><th>Recebido</th><th>Nome</th><th>Contato</th><th>Mensagem</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($leads as $l): ?>
        <tr>
            <td><?= date('d/m/Y H:i', strtotime($l['criado_em'])) ?></td>
            <td><strong><?= e($l['nome']) ?></strong></td>
            <td><?php if ($l['email']): ?><a class="link" href="mailto:<?= e($l['email']) ?>"><?= e($l['email']) ?></a><?php endif; ?><?php if ($l['telefone']): ?><span class="mini"><a class="link" target="_blank" href="<?= e(link_whatsapp($l['telefone'], 'Olá, ' . $l['nome'] . '! Recebemos sua mensagem pelo site.')) ?>"><?= e($l['telefone']) ?></a></span><?php endif; ?></td>
            <td style="max-width:380px;white-space:pre-wrap"><?= e($l['mensagem']) ?></td>
            <td class="acoes">
                <form method="post"><?= csrf_campo() ?><input type="hidden" name="lead_id" value="<?= $l['id'] ?>"><button class="btn btn-ghost btn-sm" name="virar_cliente" value="1" title="Adicionar aos clientes"><?= icone('usuarios', 14) ?> Virar cliente</button></form>
                <a class="btn btn-perigo btn-sm" href="?excluir=<?= $l['id'] ?><?= csrf_qs() ?>" onclick="return confirm('Excluir este contato?')"><?= icone('lixeira', 14) ?></a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$leads): ?><tr class="vazio"><td colspan="5">Nenhum contato recebido ainda.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</main>
</div>
</body>
</html>
