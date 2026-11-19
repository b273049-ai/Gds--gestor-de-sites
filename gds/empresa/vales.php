<?php
// Vales-presente vendidos pelo site: confirmar pagamento (quando não é Pix automático) e usar no balcão
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
require_once __DIR__ . '/../includes/recursos_site.php';
require_once __DIR__ . '/../includes/treino_vale.php';
$empresaId = (int)empresa_id_atual();
$st = $pdo->prepare("SELECT e.*, s.recursos FROM empresas e LEFT JOIN sites s ON s.empresa_id=e.id WHERE e.id=?"); $st->execute([$empresaId]); $empresa = $st->fetch();
$r = rec_ler($empresa);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        wa_adiar();
        if (isset($_POST['usar'])) { [$ok, $msg] = vale_usar($empresaId, $_POST['codigo'] ?? '', $_POST['valor'] ?? 0); flash($ok ? 'sucesso' : 'erro', $msg); }
        elseif (isset($_POST['confirmar'])) {
            $c = $pdo->prepare("SELECT id FROM vales_presente WHERE id=? AND empresa_id=? AND status='pendente'"); $c->execute([(int)$_POST['confirmar'], $empresaId]);
            if ($id = $c->fetchColumn()) { vale_ativar($id, $r['vale']['validade']); flash('sucesso', 'Pagamento confirmado: o vale foi enviado para quem ganhou.'); }
        } elseif (isset($_POST['cancelar'])) {
            $pdo->prepare("UPDATE vales_presente SET status='cancelado' WHERE id=? AND empresa_id=? AND status='pendente'")->execute([(int)$_POST['cancelar'], $empresaId]);
            flash('sucesso', 'Pedido de vale cancelado.');
        }
    } catch (PDOException $e) { flash('erro', 'O banco ainda está sendo atualizado. Recarregue a página.'); }
    header('Location: vales.php'); exit;
}
$vales = [];
try { $q = $pdo->prepare("SELECT * FROM vales_presente WHERE empresa_id=? ORDER BY criado_em DESC LIMIT 200"); $q->execute([$empresaId]); $vales = $q->fetchAll(); } catch (PDOException $e) {}
$rot = ['pendente' => 'Aguardando pagamento', 'ativo' => 'Ativo', 'usado' => 'Usado', 'cancelado' => 'Cancelado'];
$moeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Vales-presente - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Vales-presente</h1><p class="subtitulo">Vendidos pelo site. Com o Pix ligado, o pagamento confirma sozinho e o presente é enviado no WhatsApp de quem ganhou.</p></div></div>
    <?= flash_render() ?>
    <?php if (empty($r['vale']['ativo'])): ?><?= alerta('aviso', 'O vale-presente está desligado no site. Ligue em <a class="link" href="recursos.php">Recursos do site</a>.') ?><?php endif; ?>
    <form method="post" class="form-box largo"><?= csrf_campo() ?><input type="hidden" name="usar" value="1"><h3>Usar um vale no balcão</h3>
        <div class="form-row"><div><label for="vCod">Código do vale</label><input id="vCod" name="codigo" required placeholder="ABCD-1234" style="text-transform:uppercase"></div>
        <div><label for="vVal">Valor usado agora (R$)</label><input id="vVal" name="valor" required inputmode="decimal" placeholder="80,00"></div>
        <div style="align-self:end"><button type="submit">Abater do vale</button></div></div></form>
    <div class="tabela-wrap"><table>
        <thead><tr><th>Código</th><th>Para</th><th>De</th><th>Valor / saldo</th><th>Situação</th><th></th></tr></thead>
        <tbody>
        <?php if (!$vales): ?><tr><td colspan="6" class="muted">Nenhum vale ainda.</td></tr><?php endif; ?>
        <?php foreach ($vales as $v): ?>
        <tr><td><strong><?= e($v['codigo']) ?></strong><span class="mini"><?= date('d/m/Y', strtotime($v['criado_em'])) ?></span></td>
            <td><?= e($v['para_nome']) ?><?= $v['para_tel'] ? '<span class="mini">' . e($v['para_tel']) . '</span>' : '' ?></td>
            <td><?= e($v['comprador_nome']) ?><span class="mini"><?= e($v['comprador_tel']) ?></span></td>
            <td><?= $moeda($v['valor']) ?><?= $v['status'] === 'ativo' ? '<span class="mini">saldo ' . $moeda($v['saldo']) . '</span>' : '' ?></td>
            <td><?= e($rot[$v['status']] ?? $v['status']) ?><?= $v['status'] === 'ativo' && $v['validade'] ? '<span class="mini">até ' . date('d/m/Y', strtotime($v['validade'])) . '</span>' : '' ?></td>
            <td class="acoes"><?php if ($v['status'] === 'pendente'): ?>
                <form method="post" style="display:inline"><?= csrf_campo() ?><button class="btn btn-sm" name="confirmar" value="<?= (int)$v['id'] ?>">✓ Recebi o pagamento</button></form>
                <form method="post" style="display:inline"><?= csrf_campo() ?><button class="btn btn-ghost btn-sm" name="cancelar" value="<?= (int)$v['id'] ?>">Cancelar</button></form>
            <?php else: ?><a class="btn btn-ghost btn-sm" target="_blank" rel="noopener" href="/site/vale.php?t=<?= e($v['token']) ?>">Ver cartão</a><?php endif; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
</main>
</div>
</body>
</html>
