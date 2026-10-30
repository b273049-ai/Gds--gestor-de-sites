<?php
// QR Code por mesa: o cliente aponta a câmera, abre o cardápio já "na mesa N" e pede sem chamar o garçom.
// O pedido chega no painel de pedidos com o selo "Mesa N" (sem taxa nem endereço).
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
exigir_modulo('delivery');
$empresaId = (int)empresa_id_atual();
$st = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
$st->execute([$empresaId]);
$empresa = $st->fetch();
$qtd = max(1, min(100, (int)($_GET['qtd'] ?? 10)));
$base = wa_url_publica($empresa);
$link = fn($mesa) => $base . '?' . http_build_query(['mesa' => $mesa]) . '#gds-servicos';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('QR Code das mesas - Meu Negócio') ?>
<style>
.qr-grade{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:16px;margin-top:18px}
.qr-card{background:#fff;color:#111;border:1px solid #ddd;border-radius:14px;padding:16px;text-align:center;break-inside:avoid}
.qr-card h3{margin:0 0 2px;font-size:1rem}.qr-card .mesa{font-size:1.6rem;font-weight:800;margin:2px 0 8px}
.qr-card .qr{display:flex;justify-content:center;margin:6px 0}.qr-card .qr img,.qr-card .qr canvas{width:160px;height:160px}
.qr-card p{margin:6px 0 0;font-size:.82rem;color:#444}
@media print{.sidebar,.sidebar-toggle,.sidebar-overlay,.page-header,.form-box,.nao-imprimir{display:none!important}.content{margin:0!important;padding:0!important}body{background:#fff}.qr-grade{grid-template-columns:repeat(3,1fr)}}
</style></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>QR Code das mesas</h1>
        <p class="subtitulo">Cole um em cada mesa: o cliente aponta a câmera, vê o cardápio e pede sem esperar o garçom. O pedido chega em <a class="link" href="pedidos.php">Pedidos</a> com o número da mesa.</p></div>
        <div class="acoes"><button type="button" class="btn" onclick="window.print()">🖨 Imprimir</button></div></div>
    <form method="get" class="form-box nao-imprimir" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
        <div><label>Quantas mesas?</label><input type="number" name="qtd" min="1" max="100" value="<?= $qtd ?>" style="max-width:120px"></div>
        <button type="submit" class="btn btn-ghost">Gerar</button>
        <a class="link" target="_blank" href="<?= e($link('1')) ?>">Testar a mesa 1 →</a>
    </form>
    <?php if (isset($empresa['pedido_online']) && $empresa['pedido_online'] !== null && !pg_bool($empresa['pedido_online'])): ?><?= alerta('aviso', 'O pedido pelo site está desligado. Ligue em Configurações → Delivery para os pedidos da mesa chegarem aqui.') ?><?php endif; ?>
    <div class="qr-grade">
        <?php for ($i = 1; $i <= $qtd; $i++): ?>
        <div class="qr-card"><h3><?= e($empresa['nome']) ?></h3><div class="mesa">Mesa <?= $i ?></div>
            <div class="qr" data-qr="<?= e($link((string)$i)) ?>"></div>
            <p>📱 Aponte a câmera e faça seu pedido</p></div>
        <?php endfor; ?>
    </div>
</main>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.querySelectorAll('[data-qr]').forEach(function (el) {
    if (window.QRCode) new QRCode(el, { text: el.getAttribute('data-qr'), width: 320, height: 320, correctLevel: QRCode.CorrectLevel.M });
    else el.textContent = el.getAttribute('data-qr');
});
</script>
</body>
</html>
