<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mercadopago_helper.php';
exigir_login('admin');

$pagamentoId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT pa.*, e.nome as empresa_nome, e.email as empresa_email FROM pagamentos_assinaturas pa JOIN empresas e ON e.id = pa.empresa_id WHERE pa.id=?");
$stmt->execute([$pagamentoId]);
$pagamento = $stmt->fetch();

if (!$pagamento) { header('Location: financeiro.php'); exit; }

$token = trim(get_config('mercadopago_access_token', ''));
$resultado = gerar_cobranca_pix($token, $pagamento['valor'], 'Assinatura GDS - ' . $pagamento['empresa_nome'], $pagamento['empresa_email']);

if ($resultado['ok'] && $resultado['mp_id']) {
    $pdo->prepare("UPDATE pagamentos_assinaturas SET referencia_gateway=?, metodo='pix' WHERE id=?")->execute([$resultado['mp_id'], $pagamento['id']]);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cobrança PIX - GDS Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Cobrança PIX — <?= e($pagamento['empresa_nome']) ?></h1>
    <p style="color:#9aa0c0;margin-bottom:14px;">Valor: R$ <?= number_format($pagamento['valor'],2,',','.') ?></p>

    <?php if (!$resultado['ok']): ?>
    <div class="alert-erro"><?= e($resultado['erro']) ?></div>
    <?php else: ?>
    <div class="form-box" style="max-width:360px;text-align:center;">
        <?php if ($resultado['qr_base64']): ?>
        <img src="data:image/png;base64,<?= $resultado['qr_base64'] ?>" style="width:100%;border-radius:8px;margin-bottom:14px;">
        <?php endif; ?>
        <label>Código PIX copia-e-cola</label>
        <textarea readonly rows="4" onclick="this.select()"><?= e($resultado['qr_code']) ?></textarea>
        <p style="color:#5c6199;font-size:12px;margin-top:10px;">Envie esse código ou a imagem pro cliente. Assim que o pagamento cair, marque como pago em Financeiro (confirmação automática exige webhook, pode ser configurado depois).</p>
    </div>
    <?php endif; ?>

    <p style="margin-top:16px;"><a href="financeiro.php" class="btn btn-sm">Voltar ao Financeiro</a></p>
</div>
</div>
</body>
</html>
