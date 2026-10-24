<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

// Marcar como pago manualmente (enquanto o gateway de pagamento não está configurado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_pago']) && !csrf_valido()) {
    header('Location: financeiro.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_pago'])) {
    $id = (int)$_POST['pagamento_id'];
    $pdo->prepare("UPDATE pagamentos_assinaturas SET status='pago', pago_em=NOW() WHERE id=?")->execute([$id]);
    header('Location: financeiro.php'); exit;
}

$pagamentos = $pdo->query("
    SELECT pa.*, e.nome as empresa_nome
    FROM pagamentos_assinaturas pa
    JOIN empresas e ON e.id = pa.empresa_id
    ORDER BY pa.vencimento DESC
")->fetchAll();

$totalPago = $pdo->query("SELECT COALESCE(SUM(valor),0) v FROM pagamentos_assinaturas WHERE status='pago'")->fetch()['v'];
$totalPendente = $pdo->query("SELECT COALESCE(SUM(valor),0) v FROM pagamentos_assinaturas WHERE status='pendente'")->fetch()['v'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Financeiro - GDS Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Financeiro</h1>
    <div class="cards">
        <div class="card"><div class="label">Total Recebido</div><div class="value">R$ <?= number_format($totalPago,2,',','.') ?></div></div>
        <div class="card"><div class="label">Total Pendente</div><div class="value">R$ <?= number_format($totalPendente,2,',','.') ?></div></div>
    </div>

    <p style="color:#9aa0c0;font-size:13px;margin-bottom:14px;">
        Configure o Access Token do Mercado Pago em Configurações pra usar "Gerar PIX". Sem token, você pode marcar cobranças como pagas manualmente.
    </p>

    <table>
        <tr><th>Empresa</th><th>Valor</th><th>Vencimento</th><th>Status</th><th>Ação</th></tr>
        <?php foreach ($pagamentos as $p): ?>
        <tr>
            <td><?= e($p['empresa_nome']) ?></td>
            <td>R$ <?= number_format($p['valor'],2,',','.') ?></td>
            <td><?= date('d/m/Y', strtotime($p['vencimento'])) ?></td>
            <td><span class="badge badge-<?= $p['status']=='pago'?'ativo':($p['status']=='atrasado'?'suspenso':'pendente') ?>"><?= e($p['status']) ?></span></td>
            <td>
                <?php if ($p['status'] !== 'pago'): ?>
                <form method="post" style="display:inline;">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="pagamento_id" value="<?= $p['id'] ?>">
                    <button class="btn btn-sm" type="submit" name="marcar_pago">Marcar como pago</button>
                </form>
                <a class="btn btn-sm" href="mp_gerar_cobranca.php?id=<?= $p['id'] ?>">Gerar PIX</a>
                <?php else: ?>-<?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</div>
</body>
</html>
