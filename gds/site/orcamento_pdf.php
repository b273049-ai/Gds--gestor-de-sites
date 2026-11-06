<?php
// Orçamento em formato de documento (imprimir ou "Salvar como PDF"), pelo link do pedido.
// Serve para o cliente (link do WhatsApp) e para a empresa (botão PDF no painel).
require_once __DIR__ . '/../config/db.php';
if (!function_exists('e')) { function e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); } }
header('X-Robots-Tag: noindex');

$token = preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? ''));
$p = false;
if (strlen($token) === 32) {
    $st = $pdo->prepare("SELECT p.*, c.nome AS cliente_nome, c.telefone AS cliente_telefone, c.email AS cliente_email, sv.nome AS servico_nome,
            e.nome AS empresa_nome, e.whatsapp, e.endereco, e.email AS empresa_email, e.logo, e.instagram, si.cor_primaria
        FROM pedidos p JOIN empresas e ON e.id = p.empresa_id LEFT JOIN sites si ON si.empresa_id = e.id
        LEFT JOIN clientes c ON c.id = p.cliente_id LEFT JOIN servicos sv ON sv.id = p.servico_id
        WHERE p.token = ? AND COALESCE(p.tipo,'') <> 'delivery' LIMIT 1");
    $st->execute([$token]);
    $p = $st->fetch();
}
if (!$p) { http_response_code(404); echo 'Orçamento não encontrado.'; exit; }
$itens = json_decode((string)($p['itens'] ?? ''), true) ?: [];
$cor = preg_match('/^#[0-9a-f]{6}$/i', (string)$p['cor_primaria']) ? $p['cor_primaria'] : '#2563eb';
$m = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
$respostas = json_decode((string)($p['respostas'] ?? ''), true) ?: [];
$status = ['novo' => 'Em análise', 'orcado' => 'Aguardando aprovação', 'aprovado' => 'Aprovado', 'em_andamento' => 'Em andamento', 'concluido' => 'Concluído', 'cancelado' => 'Cancelado'][$p['status']] ?? $p['status'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Orçamento nº <?= (int)$p['id'] ?> — <?= e($p['empresa_nome']) ?></title>
<style>
* { box-sizing: border-box; }
body { margin: 0; background: #eef0f4; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color: #1c1f2b; }
.folha { max-width: 800px; margin: 24px auto; background: #fff; padding: 40px 44px; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,.08); }
.topo { display: flex; justify-content: space-between; gap: 20px; align-items: flex-start; border-bottom: 4px solid <?= $cor ?>; padding-bottom: 18px; }
.topo img { max-height: 64px; max-width: 180px; object-fit: contain; }
.topo h1 { margin: 0; font-size: 22px; } .topo .contato { font-size: 13px; color: #555; line-height: 1.6; }
.doc { text-align: right; } .doc strong { display: block; font-size: 20px; color: <?= $cor ?>; } .doc span { font-size: 13px; color: #555; display: block; }
.bloco { margin-top: 22px; } .bloco h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: #777; margin: 0 0 6px; }
table { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 14px; }
th { text-align: left; background: #f4f5f8; padding: 9px 10px; font-size: 12px; text-transform: uppercase; color: #666; }
td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: top; } td.n { text-align: right; white-space: nowrap; }
.total { display: flex; justify-content: flex-end; margin-top: 12px; } .total div { background: <?= $cor ?>; color: #fff; padding: 12px 18px; border-radius: 8px; font-size: 18px; font-weight: 700; }
.obs { white-space: pre-line; font-size: 14px; line-height: 1.6; background: #fafbfc; border-left: 3px solid <?= $cor ?>; padding: 10px 14px; }
.assin { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 50px; font-size: 13px; text-align: center; color: #555; }
.assin div { border-top: 1px solid #999; padding-top: 6px; }
.aceite { margin-top: 18px; font-size: 13px; color: #166534; background: #ecfdf3; padding: 10px 12px; border-radius: 8px; }
.acoes { max-width: 800px; margin: 0 auto 30px; display: flex; gap: 10px; justify-content: center; }
.acoes button, .acoes a { font: inherit; padding: 11px 18px; border-radius: 10px; border: 0; background: <?= $cor ?>; color: #fff; cursor: pointer; text-decoration: none; }
.acoes a { background: #fff; color: #1c1f2b; border: 1px solid #ddd; }
@media (max-width: 600px) { .folha { padding: 22px 18px; margin: 0; border-radius: 0; } .topo { flex-direction: column; } .doc { text-align: left; } .assin { grid-template-columns: 1fr; } }
@media print { body { background: #fff; } .folha { box-shadow: none; margin: 0; max-width: none; border-radius: 0; } .acoes { display: none; } }
</style>
</head>
<body>
<div class="folha">
    <div class="topo">
        <div>
            <?php if ($p['logo']): ?><img src="<?= e($p['logo']) ?>" alt=""><?php endif; ?>
            <h1><?= e($p['empresa_nome']) ?></h1>
            <div class="contato"><?= e($p['endereco']) ?><?= $p['whatsapp'] ? '<br>WhatsApp: ' . e($p['whatsapp']) : '' ?><?= $p['empresa_email'] ? ' · ' . e($p['empresa_email']) : '' ?><?= $p['instagram'] ? ' · @' . e(ltrim($p['instagram'], '@')) : '' ?></div>
        </div>
        <div class="doc"><strong>ORÇAMENTO Nº <?= (int)$p['id'] ?></strong><span>Emitido em <?= date('d/m/Y', strtotime($p['atualizado_em'] ?: $p['criado_em'])) ?></span>
            <?php if (!empty($p['validade'])): ?><span>Válido até <b><?= date('d/m/Y', strtotime($p['validade'])) ?></b></span><?php endif; ?><span><?= e($status) ?></span></div>
    </div>
    <div class="bloco"><h2>Cliente</h2><strong><?= e($p['cliente_nome'] ?: '—') ?></strong><?= $p['cliente_telefone'] ? ' · ' . e($p['cliente_telefone']) : '' ?><?= $p['cliente_email'] ? ' · ' . e($p['cliente_email']) : '' ?></div>
    <div class="bloco"><h2>Pedido</h2>
        <?php if ($p['servico_nome']): ?><strong><?= e($p['servico_nome']) ?></strong><br><?php endif; ?>
        <span style="white-space:pre-line;font-size:14px"><?= e($p['descricao']) ?></span>
        <?php if (!empty($p['data_evento'])): ?><br><small>📅 Data: <?= date('d/m/Y', strtotime($p['data_evento'])) ?></small><?php endif; ?>
        <?php if (!empty($p['local_evento'])): ?><br><small>📍 Local: <?= e($p['local_evento']) ?></small><?php endif; ?>
        <?php if (!empty($p['veiculo_info'])): ?><br><small>🚗 Veículo: <?= e($p['veiculo_info']) ?></small><?php endif; ?>
        <?php foreach ($respostas as $r): if (is_array($r) && count($r) >= 2): ?><br><small><?= e($r[0]) ?> <b><?= e($r[1]) ?></b></small><?php endif; endforeach; ?>
    </div>
    <div class="bloco"><h2>Valores</h2>
        <table>
            <thead><tr><th>Descrição</th><th style="text-align:right">Qtd</th><th style="text-align:right">Unitário</th><th style="text-align:right">Total</th></tr></thead>
            <tbody>
            <?php if ($itens): foreach ($itens as $i): ?>
                <tr><td><?= e($i['nome'] ?? '') ?></td><td class="n"><?= (int)($i['qtd'] ?? 1) ?></td><td class="n"><?= $m($i['preco'] ?? 0) ?></td><td class="n"><?= $m((float)($i['preco'] ?? 0) * (int)($i['qtd'] ?? 1)) ?></td></tr>
            <?php endforeach; else: ?>
                <tr><td><?= e($p['servico_nome'] ?: 'Serviço conforme descrição') ?></td><td class="n">1</td><td class="n"><?= $m($p['valor']) ?></td><td class="n"><?= $m($p['valor']) ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <div class="total"><div>Total: <?= $m($p['valor']) ?></div></div>
    </div>
    <?php if (!empty($p['resposta'])): ?><div class="bloco"><h2>Condições e observações</h2><div class="obs"><?= e($p['resposta']) ?></div></div><?php endif; ?>
    <?php if (!empty($p['aceite_em'])): ?><div class="aceite">✔ Aprovado eletronicamente por <b><?= e($p['aceite_nome']) ?></b> em <?= date('d/m/Y \à\s H:i', strtotime($p['aceite_em'])) ?> (IP <?= e($p['aceite_ip']) ?>).</div>
    <?php else: ?><div class="assin"><div><?= e($p['empresa_nome']) ?></div><div><?= e($p['cliente_nome'] ?: 'Cliente') ?></div></div><?php endif; ?>
</div>
<div class="acoes"><button type="button" onclick="print()">🖨 Imprimir / salvar PDF</button><a href="/site/pedido.php?t=<?= e($token) ?>">Ver online e aprovar</a></div>
</body>
</html>
