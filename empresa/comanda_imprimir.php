<?php
// Recibo da comanda (agenda: barbearia, salão, estética…) para impressora térmica (80mm) ou papel comum.
// Mesmo padrão da comanda do delivery (pedido_imprimir.php). Abre e já chama a impressão.
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = (int)empresa_id_atual();
$st = $pdo->prepare("SELECT k.*, c.nome AS cliente_nome, c.telefone, pr.nome AS profissional_nome, e.nome AS empresa_nome, e.endereco, e.whatsapp,
        a.data_hora, s.nome AS servico_nome
    FROM comandas k JOIN empresas e ON e.id = k.empresa_id LEFT JOIN clientes c ON c.id = k.cliente_id
    LEFT JOIN profissionais pr ON pr.id = k.profissional_id LEFT JOIN agendamentos a ON a.id = k.agendamento_id LEFT JOIN servicos s ON s.id = a.servico_id
    WHERE k.id=? AND k.empresa_id=?");
$st->execute([(int)($_GET['id'] ?? 0), $empresaId]);
$k = $st->fetch();
if (!$k) { http_response_code(404); exit('Comanda não encontrada.'); }
$itens = json_decode((string)($k['itens'] ?? ''), true) ?: [];
$moeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recibo nº <?= (int)$k['id'] ?> — <?= e($k['empresa_nome']) ?></title>
<style>
    @page { size: 80mm auto; margin: 4mm; }
    * { box-sizing: border-box; }
    body { margin: 0 auto; width: 72mm; font: 13px/1.35 'Courier New', monospace; color: #000; background: #fff; }
    h1 { font-size: 16px; text-align: center; margin: 4px 0; }
    .c { text-align: center; } .g { font-size: 18px; font-weight: 700; }
    hr { border: 0; border-top: 1px dashed #000; margin: 8px 0; }
    table { width: 100%; border-collapse: collapse; } td { vertical-align: top; padding: 2px 0; } td:last-child { text-align: right; white-space: nowrap; padding-left: 6px; }
    .total td { font-weight: 700; font-size: 15px; }
    .sem-print { text-align: center; margin: 14px 0; }
    .sem-print button { font: 600 14px system-ui, sans-serif; padding: 10px 18px; border-radius: 8px; border: 0; background: #111; color: #fff; cursor: pointer; }
    @media print { .sem-print { display: none; } }
</style>
</head>
<body>
<main>
    <h1><?= e($k['empresa_nome']) ?></h1>
    <?php if ($k['endereco']): ?><div class="c"><?= e($k['endereco']) ?></div><?php endif; ?>
    <div class="c g">RECIBO Nº <?= (int)$k['id'] ?></div>
    <div class="c"><?= date('d/m/Y H:i', strtotime($k['criado_em'])) ?></div>
    <hr>
    <div><strong><?= e($k['cliente_nome'] ?: 'Cliente') ?></strong><?= $k['telefone'] ? '<br>' . e($k['telefone']) : '' ?></div>
    <?php if ($k['profissional_nome']): ?><div>Atendido por: <?= e($k['profissional_nome']) ?></div><?php endif; ?>
    <?php if ($k['data_hora']): ?><div>Horário: <?= date('d/m H:i', strtotime($k['data_hora'])) ?><?= $k['servico_nome'] ? ' — ' . e($k['servico_nome']) : '' ?></div><?php endif; ?>
    <hr>
    <table>
        <?php foreach ($itens as $i): ?><tr><td><?= (int)($i['qtd'] ?? 1) ?>x <?= e($i['nome'] ?? '') ?></td><td><?= $moeda((float)($i['preco'] ?? 0) * (int)($i['qtd'] ?? 1)) ?></td></tr><?php endforeach; ?>
        <tr><td colspan="2"><hr></td></tr>
        <tr><td>Subtotal</td><td><?= $moeda($k['subtotal']) ?></td></tr>
        <?php if ((float)$k['desconto'] > 0): ?><tr><td>Desconto</td><td>−<?= $moeda($k['desconto']) ?></td></tr><?php endif; ?>
        <tr class="total"><td>TOTAL</td><td><?= $moeda($k['total']) ?></td></tr>
    </table>
    <div style="margin-top:6px">Pagamento: <strong><?= e($k['forma_pagamento'] ?: '—') ?></strong></div>
    <?php if (!empty($k['observacao'])): ?><div style="margin-top:6px">Obs.: <?= e($k['observacao']) ?></div><?php endif; ?>
    <hr>
    <div class="c">Obrigado pela preferência!<?= $k['whatsapp'] ? '<br>Agende de novo: ' . e($k['whatsapp']) : '' ?></div>
    <div class="sem-print"><button type="button" onclick="print()">Imprimir de novo</button></div>
</main>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 250); });</script>
</body>
</html>
