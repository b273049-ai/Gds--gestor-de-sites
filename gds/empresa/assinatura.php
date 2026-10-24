<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = empresa_id_atual();

$empresa = $pdo->prepare("SELECT e.*, p.nome as plano_nome, p.valor as plano_valor, p.ciclo FROM empresas e LEFT JOIN planos p ON p.id=e.plano_id WHERE e.id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();

$pagamentos = $pdo->prepare("SELECT * FROM pagamentos_assinaturas WHERE empresa_id=? ORDER BY vencimento DESC");
$pagamentos->execute([$empresaId]); $pagamentos = $pagamentos->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Minha Assinatura - Meu Negócio</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Minha Assinatura</h1>
    <div class="cards">
        <div class="card"><div class="label">Plano</div><div class="value"><?= e($empresa['plano_nome'] ?? '-') ?></div></div>
        <div class="card"><div class="label">Valor</div><div class="value">R$ <?= number_format($empresa['plano_valor'] ?? 0,2,',','.') ?><span style="font-size:12px;color:#9aa0c0;">/<?= e($empresa['ciclo'] ?? 'mês') ?></span></div></div>
        <div class="card"><div class="label">Status</div><div class="value"><span class="badge badge-<?= e($empresa['status']) ?>" style="font-size:14px;"><?= e($empresa['status']) ?></span></div></div>
        <div class="card"><div class="label">Próximo Vencimento</div><div class="value" style="font-size:16px;"><?= $empresa['vencimento'] ? date('d/m/Y', strtotime($empresa['vencimento'])) : '-' ?></div></div>
    </div>

    <h1 style="font-size:18px;">Histórico de Pagamentos</h1>
    <table>
        <tr><th>Vencimento</th><th>Valor</th><th>Status</th><th>Pago em</th></tr>
        <?php foreach ($pagamentos as $p): ?>
        <tr>
            <td><?= date('d/m/Y', strtotime($p['vencimento'])) ?></td>
            <td>R$ <?= number_format($p['valor'],2,',','.') ?></td>
            <td><span class="badge badge-<?= $p['status']=='pago'?'ativo':($p['status']=='atrasado'?'suspenso':'pendente') ?>"><?= e($p['status']) ?></span></td>
            <td><?= $p['pago_em'] ? date('d/m/Y', strtotime($p['pago_em'])) : '-' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($pagamentos)): ?><tr><td colspan="4">Nenhum pagamento registrado ainda.</td></tr><?php endif; ?>
    </table>
    <p style="color:#5c6199;font-size:12px;margin-top:10px;">Dúvidas sobre cobrança ou upgrade de plano? Fale com o administrador da plataforma.</p>
</div>
</div>
</body>
</html>
