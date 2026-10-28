<?php
// Comanda do pedido para impressora térmica (80mm) ou papel comum. Abre e já chama a impressão.
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = (int)empresa_id_atual();
$st = $pdo->prepare("SELECT p.*, c.nome AS cliente_nome, c.telefone, sv.nome AS servico_nome, e.nome AS empresa_nome
    FROM pedidos p JOIN empresas e ON e.id = p.empresa_id LEFT JOIN clientes c ON c.id = p.cliente_id LEFT JOIN servicos sv ON sv.id = p.servico_id
    WHERE p.id=? AND p.empresa_id=?");
$st->execute([(int)($_GET['id'] ?? 0), $empresaId]);
$p = $st->fetch();
if (!$p) { http_response_code(404); exit('Pedido não encontrado.'); }
$itens = json_decode((string)($p['itens'] ?? ''), true) ?: [];
$moeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
$subtotal = array_sum(array_map(fn($i) => $i['preco'] * $i['qtd'], $itens));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pedido nº <?= (int)$p['id'] ?></title>
<style>
    @page { size: 80mm auto; margin: 4mm; }
    * { box-sizing: border-box; }
    body { margin: 0 auto; width: 72mm; font: 13px/1.35 'Courier New', monospace; color: #000; background: #fff; }
    h1 { font-size: 16px; text-align: center; margin: 4px 0; }
    .c { text-align: center; } .g { font-size: 22px; font-weight: 700; }
    hr { border: 0; border-top: 1px dashed #000; margin: 8px 0; }
    table { width: 100%; border-collapse: collapse; } td { vertical-align: top; padding: 2px 0; } td:last-child { text-align: right; white-space: nowrap; padding-left: 6px; }
    .total td { font-weight: 700; font-size: 15px; }
    .obs { border: 1px solid #000; padding: 6px; margin-top: 6px; font-weight: 700; }
    .sem-print { text-align: center; margin: 14px 0; }
    .sem-print button { font: 600 14px system-ui, sans-serif; padding: 10px 18px; border-radius: 8px; border: 0; background: #111; color: #fff; cursor: pointer; }
    @media print { .sem-print { display: none; } }
</style>
</head>
<body>
    <h1><?= e($p['empresa_nome']) ?></h1>
    <div class="c g">PEDIDO Nº <?= (int)$p['id'] ?></div>
    <div class="c"><?= date('d/m/Y H:i', strtotime($p['criado_em'])) ?><?= ($p['origem'] ?? '') === 'site' ? ' · pelo site' : '' ?></div>
    <hr>
    <div><strong><?= e($p['cliente_nome'] ?? '—') ?></strong><?= $p['telefone'] ? '<br>' . e($p['telefone']) : '' ?></div>
    <?php if (($p['tipo'] ?? '') === 'delivery'): ?>
        <div style="margin-top:6px"><strong><?= ($p['tipo_entrega'] ?? '') === 'retirada' ? 'RETIRADA NO LOCAL' : 'ENTREGA' ?></strong><?= ($p['tipo_entrega'] ?? '') !== 'retirada' && $p['endereco_entrega'] ? '<br>' . e($p['endereco_entrega']) : '' ?></div>
    <?php endif; ?>
    <hr>
    <?php if ($itens): ?>
    <table>
        <?php foreach ($itens as $i): ?><tr><td><?= (int)$i['qtd'] ?>x <?= e($i['nome']) ?></td><td><?= $moeda($i['preco'] * $i['qtd']) ?></td></tr><?php endforeach; ?>
        <tr><td colspan="2"><hr></td></tr>
        <tr><td>Subtotal</td><td><?= $moeda($subtotal) ?></td></tr>
        <?php if ((float)$p['taxa_entrega'] > 0): ?><tr><td>Entrega</td><td><?= $moeda($p['taxa_entrega']) ?></td></tr><?php endif; ?>
        <tr class="total"><td>TOTAL</td><td><?= $moeda($p['valor']) ?></td></tr>
    </table>
    <div style="margin-top:6px">Pagamento: <strong><?= e($p['forma_pagamento'] ?: '—') ?></strong><?= $p['troco_para'] ? '<br>Troco para ' . $moeda($p['troco_para']) . ' (levar ' . $moeda((float)$p['troco_para'] - (float)$p['valor']) . ')' : '' ?></div>
    <?php else: ?>
        <?php if (!empty($p['servico_nome'])): ?><strong><?= e($p['servico_nome']) ?></strong><br><?php endif; ?>
        <?= nl2br(e($p['descricao'])) ?>
        <table style="margin-top:6px"><tr class="total"><td>VALOR</td><td><?= $moeda($p['valor']) ?></td></tr></table>
    <?php endif; ?>
    <?php if (!empty($p['observacoes'])): ?><div class="obs">OBS: <?= e($p['observacoes']) ?></div><?php endif; ?>
    <hr>
    <div class="c">Obrigado pela preferência!</div>
    <div class="sem-print"><button onclick="print()">Imprimir de novo</button></div>
    <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 250); });</script>
</body>
</html>
