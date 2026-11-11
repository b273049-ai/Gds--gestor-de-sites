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
<?= painel_head('Minha Assinatura - Meu Negócio') ?>
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Minha assinatura</h1><p class="subtitulo">Plano, situação e histórico de pagamentos.</p></div></div>
    <div class="cards">
        <div class="card"><div class="label">Plano</div><div class="value"><?= e($empresa['plano_nome'] ?? '-') ?></div></div>
        <div class="card"><div class="label">Valor</div><div class="value">R$ <?= number_format($empresa['plano_valor'] ?? 0,2,',','.') ?><span style="font-size:12px;color:#9aa0c0;">/<?= e($empresa['ciclo'] ?? 'mês') ?></span></div></div>
        <div class="card"><div class="label">Status</div><div class="value"><span class="badge badge-<?= e($empresa['status']) ?>" style="font-size:14px;"><?= e($empresa['status']) ?></span></div></div>
        <div class="card"><div class="label">Próximo Vencimento</div><div class="value" style="font-size:16px;"><?= $empresa['vencimento'] ? date('d/m/Y', strtotime($empresa['vencimento'])) : '-' ?></div></div>
    </div>

    <h2>Histórico de pagamentos</h2>
    <div class="tabela-wrap"><table>
        <tr><th>Vencimento</th><th>Valor</th><th>Status</th><th>Pago em</th></tr>
        <?php foreach ($pagamentos as $p): ?>
        <tr>
            <td><?= date('d/m/Y', strtotime($p['vencimento'])) ?></td>
            <td>R$ <?= number_format($p['valor'],2,',','.') ?></td>
            <td><span class="badge badge-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
            <td><?= $p['pago_em'] ? date('d/m/Y', strtotime($p['pago_em'])) : '-' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($pagamentos)): ?><tr class="vazio"><td colspan="4">Nenhum pagamento registrado ainda.</td></tr><?php endif; ?>
    </table></div>
    <p class="dica" style="margin-top:12px">Dúvidas sobre cobrança ou upgrade de plano? Fale com o administrador da plataforma.</p>
</main>
</div>
</body>
</html>
