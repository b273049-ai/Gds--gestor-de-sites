<?php
// Página do cliente final: acompanhar o pedido (delivery) ou o orçamento pelo link recebido no WhatsApp
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/site_render.php';
require_once __DIR__ . '/../includes/pagamentos.php';
require_once __DIR__ . '/../includes/orcamento.php';
if (!function_exists('e')) { function e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); } }
header('X-Robots-Tag: noindex');

$token = preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? $_POST['t'] ?? ''));
$p = false;
if (strlen($token) === 32) {
    try {
        $st = $pdo->prepare("SELECT p.*, c.nome AS cliente_nome, sv.nome AS servico_nome, e.nome AS empresa_nome, e.slug, e.whatsapp, e.endereco, e.id AS emp_id,
                e.pix_ativo, e.mercadopago_access_token, e.orc_sinal_percentual, e.tempo_entrega, e.email AS empresa_email, e.notificar_email,
                si.cor_primaria, si.ia_tema, si.html_gerado, si.ia_modo_total
            FROM pedidos p JOIN empresas e ON e.id = p.empresa_id JOIN sites si ON si.empresa_id = e.id
            LEFT JOIN clientes c ON c.id = p.cliente_id LEFT JOIN servicos sv ON sv.id = p.servico_id
            WHERE p.token = ? LIMIT 1");
        $st->execute([$token]);
        $p = $st->fetch();
    } catch (PDOException $e) { $p = false; }
}

// O cliente aprova o orçamento pela página
$msg = '';
if ($p && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['aprovar'])) {
    if (!hash_equals($_SESSION['gds_pedido_csrf'] ?? '', (string)($_POST['k'] ?? ''))) $msg = 'Página expirada. Tente de novo.';
    elseif ($p['status'] !== 'orcado') $msg = 'Este orçamento não pode mais ser aprovado por aqui.';
    elseif (!empty($p['validade']) && strtotime($p['validade']) < strtotime('today')) $msg = 'Este orçamento venceu. Fale com a empresa para atualizar.';
    else {
        // Aceite registrado (vale como comprovante: nome, data, hora e IP)
        $nomeAceite = mb_substr(trim(strip_tags((string)($_POST['aceite_nome'] ?? ''))), 0, 150) ?: $p['cliente_nome'];
        $pdo->prepare("UPDATE pedidos SET status='aprovado', aceite_em=NOW(), aceite_ip=?, aceite_nome=?, atualizado_em=NOW() WHERE id=?")
            ->execute([mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 60), $nomeAceite, $p['id']]);
        $p['status'] = 'aprovado'; $p['aceite_em'] = date('Y-m-d H:i:s'); $p['aceite_nome'] = $nomeAceite;
        // Sinal pelo Pix para reservar (ex.: 30% para reservar a data do evento)
        $perc = (int)($p['orc_sinal_percentual'] ?? 0);
        if ($perc > 0 && (float)$p['valor'] > 0 && pag_disponivel(['id' => $p['emp_id']] + $p)) {
            pag_criar(['id' => $p['emp_id']] + $p, round((float)$p['valor'] * $perc / 100, 2), "Sinal ($perc%) do orçamento nº {$p['id']} - {$p['empresa_nome']}", 'sinal', ['pedido_id' => $p['id']], $p['cliente_id']);
        }
        if ($emp = wa_carregar_empresa($p['emp_id'])) {
            wa_adiar();
            wa_notificar_empresa($emp, 'orcamento', ['nome' => $p['cliente_nome'], 'codigo' => $p['id'], 'telefone' => '', 'servico' => '✅ O cliente APROVOU o orçamento de ' . wa_dinheiro($p['valor']) . ' pelo site.'], 'pedido', $p['id']);
            wa_avisar_status_pedido($emp, $p['id'], 'aprovado');
            site_notificar_empresa($emp, 'Orçamento aprovado: ' . $p['cliente_nome'], [$p['cliente_nome'] . ' aprovou o orçamento nº ' . $p['id'] . ' (' . wa_dinheiro($p['valor']) . ').']);
        }
        $msg = 'ok';
    }
}
// Cliente cancela enquanto a loja ainda não aceitou (como no iFood)
if ($p && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['cancelar_pedido'])) {
    if (!hash_equals($_SESSION['gds_pedido_csrf'] ?? '', (string)($_POST['k'] ?? ''))) $msg = 'Página expirada. Tente de novo.';
    elseif ($p['status'] !== 'novo' || ($p['tipo'] ?? '') !== 'delivery') $msg = 'A loja já começou a preparar. Para cancelar, fale com ela pelo WhatsApp.';
    else {
        $motivo = mb_substr(trim(strip_tags((string)($_POST['motivo'] ?? ''))), 0, 200) ?: 'Cancelado pelo cliente';
        $pdo->prepare("UPDATE pedidos SET status='cancelado', resposta=?, atualizado_em=NOW() WHERE id=? AND status='novo'")->execute(['Cancelado pelo cliente: ' . $motivo, $p['id']]);
        try { foreach ((array)json_decode((string)$p['itens'], true) as $i) if (!empty($i['id'])) $pdo->prepare("UPDATE servicos SET estoque = estoque + ? WHERE id=? AND estoque IS NOT NULL")->execute([(int)$i['qtd'], (int)$i['id']]); } catch (PDOException $e) {}
        site_notificar_empresa(['nome' => $p['empresa_nome'], 'email' => $p['empresa_email'], 'notificar_email' => $p['notificar_email']], 'Pedido nº ' . $p['id'] . ' cancelado pelo cliente', [$p['cliente_nome'] . ' cancelou o pedido nº ' . $p['id'] . '.', 'Motivo: ' . $motivo]);
        if ($empresaWa = wa_carregar_empresa($p['emp_id'])) { wa_adiar(); wa_notificar_empresa($empresaWa, 'contato', ['nome' => $p['cliente_nome'], 'telefone' => '', 'mensagem' => '❌ Cancelou o pedido nº ' . $p['id'] . ' (antes de você aceitar). Motivo: ' . $motivo], 'pedido', $p['id']); }
        $p['status'] = 'cancelado'; $msg = 'cancelado';
    }
}
// Avaliação do pedido (estrelas + comentário) — vai para Avaliações no painel
$jaAvaliou = false;
if ($p) { try { $q = $pdo->prepare("SELECT 1 FROM avaliacoes WHERE pedido_id=? LIMIT 1"); $q->execute([$p['id']]); $jaAvaliou = (bool)$q->fetchColumn(); } catch (PDOException $e) {} }
if ($p && !$jaAvaliou && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['avaliar_pedido'])) {
    $nota = max(1, min(5, (int)($_POST['nota'] ?? 0)));
    if (!hash_equals($_SESSION['gds_pedido_csrf'] ?? '', (string)($_POST['k'] ?? ''))) $msg = 'Página expirada. Tente de novo.';
    elseif ($p['status'] !== 'concluido') $msg = 'Você pode avaliar quando o pedido for entregue.';
    else {
        try {
            $pdo->prepare("INSERT INTO avaliacoes (empresa_id, cliente_id, nota, comentario, nome_autor, aprovado, origem, pedido_id) VALUES (?,?,?,?,?,false,'pedido',?)")
                ->execute([$p['emp_id'], $p['cliente_id'], $nota, mb_substr(trim(strip_tags((string)($_POST['comentario'] ?? ''))), 0, 1000) ?: null, mb_substr((string)$p['cliente_nome'], 0, 100) ?: 'Cliente', $p['id']]);
            $jaAvaliou = true; $msg = 'avaliado';
        } catch (PDOException $e) { $msg = 'Não deu para enviar agora. Tente de novo.'; }
    }
}
$_SESSION['gds_pedido_csrf'] = bin2hex(random_bytes(16));
// Previsão de chegada: maior número do "tempo de entrega" (ex.: "40–60 min" → 60) a partir do pedido
$previsao = null;
if ($p && ($p['tipo'] ?? '') === 'delivery' && empty($p['mesa']) && in_array($p['status'], ['novo', 'em_andamento', 'pronto', 'saiu_entrega'], true) && empty($p['agendado_para'])) {
    $mins = preg_match_all('/\d+/', (string)($p['tempo_entrega'] ?? ''), $mm) ? max(array_map('intval', $mm[0])) : 0;
    if ($mins >= 5 && $mins <= 240) $previsao = strtotime($p['criado_em']) + $mins * 60;
}

$pixPendente = null;
if ($p) {
    try {
        $st = $pdo->prepare("SELECT * FROM pagamentos_clientes WHERE pedido_id=? AND token IS NOT NULL ORDER BY id DESC LIMIT 1"); $st->execute([$p['id']]);
        $pixPendente = $st->fetch() ?: null;
    } catch (PDOException $e) {}
}
$tema = $p ? site_tema($p) : ['accent' => '#2563eb'];
$delivery = $p && ($p['tipo'] ?? '') === 'delivery';
$itens = $p ? (json_decode((string)($p['itens'] ?? ''), true) ?: []) : [];
// Linha do tempo
if ($delivery && !empty($p['mesa'])) {
    $etapas = ['novo' => 'Pedido recebido', 'em_andamento' => 'Em preparo', 'concluido' => 'Servido na mesa ' . $p['mesa']];
    if ($p['status'] === 'pronto') $p['status'] = 'em_andamento';
} elseif ($delivery) {
    $etapas = ['novo' => 'Pedido recebido', 'em_andamento' => 'Em preparo', ($p['tipo_entrega'] === 'retirada' ? 'pronto' : 'saiu_entrega') => $p['tipo_entrega'] === 'retirada' ? 'Pronto para retirar' : 'Saiu para entrega', 'concluido' => $p['tipo_entrega'] === 'retirada' ? 'Retirado' : 'Entregue'];
} else {
    $etapas = ['novo' => 'Pedido recebido', 'orcado' => 'Orçamento enviado', 'aprovado' => 'Aprovado', 'em_andamento' => 'Em andamento', 'concluido' => 'Concluído'];
}
$ordem = array_keys($etapas);
$statusAtual = $p['status'] ?? 'novo';
if ($delivery && $statusAtual === 'pronto' && !isset($etapas['pronto'])) $statusAtual = 'saiu_entrega';
if ($delivery && $statusAtual === 'saiu_entrega' && !isset($etapas['saiu_entrega'])) $statusAtual = 'pronto';
$idxAtual = array_search($statusAtual, $ordem, true);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= $p ? 'Pedido nº ' . (int)$p['id'] . ' — ' . e($p['empresa_nome']) : 'Pedido não encontrado' ?></title>
<?= frag_css_apoio($tema) ?>
<?php if ($p && !in_array($p['status'], ['concluido', 'cancelado', 'orcado'], true) && !($pixPendente && $pixPendente['status'] === 'pendente')): ?><meta http-equiv="refresh" content="60"><?php endif; ?>
<style>
body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;background:#f4f5f8;color:#1c1f2b;display:flex;justify-content:center;padding:32px 16px}
main{width:100%;max-width:520px}
.linha{list-style:none;margin:18px 0;padding:0}
.linha li{position:relative;padding:0 0 18px 34px;color:#8a8f9c}
.linha li::before{content:"";position:absolute;left:8px;top:4px;width:14px;height:14px;border-radius:50%;background:#d6d9e0}
.linha li::after{content:"";position:absolute;left:14px;top:20px;bottom:-2px;width:2px;background:#e3e5ea}
.linha li:last-child::after{display:none}
.linha li.feito{color:#1c1f2b}.linha li.feito::before{background:var(--gds-tema-accent,#2563eb)}
.linha li.atual{font-weight:700;color:#1c1f2b}.linha li.atual::before{background:var(--gds-tema-accent,#2563eb);box-shadow:0 0 0 5px color-mix(in srgb,var(--gds-tema-accent,#2563eb) 22%,transparent)}
</style>
</head>
<body>
<main class="gds-c">
<?php if (!$p): ?>
    <div class="gds-card" style="background:#fff"><h3>Pedido não encontrado</h3><p class="gds-mute" style="margin-top:8px">Confira se o link está completo.</p></div>
<?php else: ?>
    <div class="gds-card" style="background:#fff">
        <p class="gds-mute" style="font-size:.85rem"><?= e($p['empresa_nome']) ?></p>
        <h3 style="font-size:1.4rem;margin:4px 0 6px"><?= $delivery ? 'Seu pedido' : 'Seu orçamento' ?> nº <?= (int)$p['id'] ?></h3>
        <?php if ($msg === 'ok'): ?><div class="gds-alerta gds-alerta-ok">Orçamento aprovado! Avisamos a empresa.</div>
        <?php elseif ($msg === 'cancelado'): ?><div class="gds-alerta gds-alerta-ok">Pedido cancelado. Avisamos a loja.</div>
        <?php elseif ($msg === 'avaliado'): ?><div class="gds-alerta gds-alerta-ok">Obrigado pela avaliação! 💛</div>
        <?php elseif ($msg): ?><div class="gds-alerta gds-alerta-erro"><?= e($msg) ?></div><?php endif; ?>

        <?php if ($p['status'] === 'cancelado'): ?>
            <div class="gds-alerta gds-alerta-erro" style="margin-top:12px">Este <?= $delivery ? 'pedido' : 'orçamento' ?> foi cancelado.</div>
        <?php else: ?>
            <?php if ($previsao): ?><div class="gds-previsao"><span><?= ($p['tipo_entrega'] ?? '') === 'retirada' ? 'Pronto por volta de' : 'Previsão de entrega' ?></span><strong><?= $previsao < time() ? 'em instantes' : 'até ' . date('H:i', $previsao) ?></strong></div><?php endif; ?>
            <?php if (!empty($p['agendado_para']) && $delivery): ?><div class="gds-previsao"><span>Agendado para</span><strong><?= e($p['agendado_para']) ?></strong></div><?php endif; ?>
            <ol class="linha">
            <?php foreach ($etapas as $k => $rot): $i = array_search($k, $ordem, true); ?>
                <li class="<?= $idxAtual !== false && $i < $idxAtual ? 'feito' : ($i === $idxAtual ? 'atual' : '') ?>"><?= e($rot) ?></li>
            <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <div class="gds-resumo">
            <?php if ($itens && !$delivery && ($p['servico_nome'] ?: $p['descricao'])): ?><div><span>Pedido</span><span style="white-space:pre-line;text-align:right"><?= e($p['servico_nome'] ?: $p['descricao']) ?></span></div><?php endif; ?>
            <?php if ($itens): foreach ($itens as $i): ?>
                <div><span><?= (int)$i['qtd'] ?>× <?= e($i['nome']) ?><?php if (!empty($i['obs'])): ?><small class="gds-mute" style="display:block">📝 <?= e($i['obs']) ?></small><?php endif; ?></span><span>R$ <?= number_format($i['preco'] * $i['qtd'], 2, ',', '.') ?></span></div>
            <?php endforeach; else: ?>
                <div><span>Pedido</span><span style="white-space:pre-line;text-align:right"><?= e($p['servico_nome'] ?: $p['descricao']) ?></span></div>
            <?php endif; ?>
            <?php if ($delivery && (float)($p['desconto'] ?? 0) > 0): ?><div><span>Cupom <?= e($p['cupom_codigo'] ?? '') ?></span><span>−R$ <?= number_format((float)$p['desconto'], 2, ',', '.') ?></span></div><?php endif; ?>
            <?php if ($delivery && (float)$p['taxa_entrega'] > 0): ?><div><span>Entrega</span><span>R$ <?= number_format((float)$p['taxa_entrega'], 2, ',', '.') ?></span></div><?php endif; ?>
            <?php if ($delivery && !empty($p['mesa'])): ?><div><span>Consumo no local</span><span>Mesa <?= e($p['mesa']) ?></span></div>
            <?php elseif ($delivery): ?><div><span><?= $p['tipo_entrega'] === 'retirada' ? 'Retirada' : 'Entrega em' ?></span><span><?= e($p['tipo_entrega'] === 'retirada' ? ($p['endereco'] ?: 'no local') : $p['endereco_entrega']) ?></span></div><?php endif; ?>
            <?php if ($delivery): ?><div><span>Pagamento</span><span><?= e($p['forma_pagamento']) ?><?= $p['troco_para'] ? ' (troco p/ R$ ' . number_format((float)$p['troco_para'], 2, ',', '.') . ')' : '' ?></span></div><?php endif; ?>
            <?php if ($delivery || in_array($p['status'], ['orcado', 'aprovado', 'em_andamento', 'concluido'], true)): ?>
            <div class="gds-total"><span><?= $delivery ? 'Total' : 'Valor' ?></span><span><?= (float)$p['valor'] > 0 ? 'R$ ' . number_format((float)$p['valor'], 2, ',', '.') : 'a combinar' ?></span></div>
            <?php endif; ?>
        </div>
        <?php if (!empty($p['resposta'])): ?><p style="margin-top:14px;white-space:pre-line"><?= e($p['resposta']) ?></p><?php endif; ?>
        <?php if (!$delivery): ?><?= frag_anexos($p['anexos'] ?? '') ?><?= frag_etapas(orc_etapas($p['id'])) ?><?php endif; ?>
        <?php if (!empty($p['aceite_em'])): ?><p class="gds-mute" style="font-size:.82rem;margin-top:10px">✔ Aprovado por <?= e($p['aceite_nome']) ?> em <?= date('d/m/Y \à\s H:i', strtotime($p['aceite_em'])) ?>.</p><?php endif; ?>
        <?php if ($pixPendente && $pixPendente['status'] === 'pendente'): ?><?= frag_pix_pagamento($pixPendente, $pixPendente['tipo'] === 'sinal' ? 'Pague o sinal para reservar' : 'Pague com Pix') ?>
        <?php elseif ($pixPendente && $pixPendente['status'] === 'pago'): ?><div class="gds-alerta gds-alerta-ok" style="margin-top:12px">💰 Pagamento de <?= e(frag_preco($pixPendente['valor'])) ?> confirmado.</div><?php endif; ?>

        <div class="gds-acoes">
            <?php $vencido = $p['status'] === 'orcado' && !empty($p['validade']) && strtotime($p['validade']) < strtotime('today'); ?>
            <?php if (!$delivery && (float)$p['valor'] > 0 && in_array($p['status'], ['orcado', 'aprovado', 'em_andamento', 'concluido'], true)): ?><a class="gds-btn-2" target="_blank" href="/site/orcamento_pdf.php?t=<?= e($token) ?>">📄 Baixar orçamento (PDF)</a><?php endif; ?>
            <?php if ($vencido): ?><div class="gds-alerta gds-alerta-erro" style="width:100%">Este orçamento venceu em <?= date('d/m/Y', strtotime($p['validade'])) ?>. Fale com a empresa para atualizar os valores.</div><?php endif; ?>
            <?php if ($p['status'] === 'orcado' && !$vencido): ?>
            <?php if (!empty($p['validade'])): ?><p class="gds-mute" style="font-size:.85rem;margin:0;width:100%">Válido até <?= date('d/m/Y', strtotime($p['validade'])) ?>.</p><?php endif; ?>
            <form method="post" style="margin:0;display:grid;gap:8px;width:100%"><input type="hidden" name="t" value="<?= e($token) ?>"><input type="hidden" name="k" value="<?= e($_SESSION['gds_pedido_csrf']) ?>">
                <div class="gds-campo" style="margin:0"><label for="aceite_nome">Seu nome completo (fica registrado como aceite)</label><input type="text" id="aceite_nome" name="aceite_nome" required maxlength="150" value="<?= e($p['cliente_nome']) ?>"></div>
                <?php if ((int)($p['orc_sinal_percentual'] ?? 0) > 0 && (float)$p['valor'] > 0 && pag_disponivel(['id' => $p['emp_id']] + $p)): ?><p class="gds-mute" style="font-size:.85rem;margin:0">Para reservar, o sinal de <?= (int)$p['orc_sinal_percentual'] ?>% (<?= e(frag_preco(round((float)$p['valor'] * (int)$p['orc_sinal_percentual'] / 100, 2))) ?>) é pago por Pix logo depois de aprovar.</p><?php endif; ?>
                <button type="submit" name="aprovar" value="1" class="gds-btn"><?= frag_icone('check') ?> Aprovar orçamento</button></form>
            <?php endif; ?>
            <?php if ($delivery && $itens && empty($p['mesa']) && in_array($p['status'], ['concluido', 'saiu_entrega', 'pronto'], true)):
                $rep = array_map(fn($i) => ['id' => (int)($i['id'] ?? 0), 'qtd' => (int)($i['qtd'] ?? 1), 'opcoes' => $i['opcoes'] ?? [], 'nome' => (string)($i['nome'] ?? ''), 'preco' => (float)($i['preco'] ?? 0), 'obs' => (string)($i['obs'] ?? '')], array_filter($itens, fn($i) => !empty($i['id']))); ?>
            <?php if ($rep): ?><a class="gds-btn" href="/<?= e($p['slug']) ?>#gds-repetir=<?= e(rawurlencode(json_encode(array_values($rep), JSON_UNESCAPED_UNICODE))) ?>">🔁 Pedir de novo</a><?php endif; ?>
            <?php endif; ?>
            <?php if ($p['whatsapp']): ?><a class="gds-btn gds-btn-wa" target="_blank" rel="noopener" href="<?= e(frag_link_whatsapp($p['whatsapp'], 'Olá! Sobre o meu ' . ($delivery ? 'pedido' : 'orçamento') . ' nº ' . $p['id'] . ':')) ?>"><?= frag_icone('whats') ?> Falar com a empresa</a><?php endif; ?>
        </div>
        <?php if ($delivery && $p['status'] === 'novo'): ?>
        <details style="margin-top:16px"><summary class="gds-btn-2" style="color:var(--gds-erro)">Cancelar pedido</summary>
            <form method="post" style="margin-top:10px"><input type="hidden" name="t" value="<?= e($token) ?>"><input type="hidden" name="k" value="<?= e($_SESSION['gds_pedido_csrf']) ?>">
                <div class="gds-campo"><label for="motivo">Motivo (opcional)</label><select id="motivo" name="motivo"><option>Pedi errado</option><option>Demorou para aceitar</option><option>Mudei de ideia</option><option>Vou pedir de novo com outra coisa</option><option>Outro motivo</option></select></div>
                <button type="submit" name="cancelar_pedido" value="1" class="gds-btn" style="background:var(--gds-erro);color:#fff" onclick="return confirm('Cancelar este pedido?')">Confirmar cancelamento</button>
            </form><p class="gds-mute" style="font-size:.8rem">Depois que a loja aceitar, o cancelamento é só pelo WhatsApp.</p></details>
        <?php endif; ?>
        <?php if ($delivery && $p['status'] === 'concluido' && !$jaAvaliou): ?>
        <form method="post" class="gds-avaliar-pedido"><input type="hidden" name="t" value="<?= e($token) ?>"><input type="hidden" name="k" value="<?= e($_SESSION['gds_pedido_csrf']) ?>">
            <h4>Como foi seu pedido?</h4>
            <div class="gds-estrelas" role="radiogroup" aria-label="Nota"><?php for ($n = 5; $n >= 1; $n--): ?><input type="radio" id="nota<?= $n ?>" name="nota" value="<?= $n ?>" required><label for="nota<?= $n ?>" title="<?= $n ?> estrela(s)">★</label><?php endfor; ?></div>
            <textarea name="comentario" rows="2" maxlength="1000" placeholder="Conte o que achou (opcional)"></textarea>
            <button type="submit" name="avaliar_pedido" value="1" class="gds-btn">Enviar avaliação</button>
        </form>
        <?php endif; ?>
        <?php if (!in_array($p['status'], ['concluido', 'cancelado'], true)): ?><p class="gds-mute" style="font-size:.8rem;margin-top:14px">Esta página se atualiza sozinha.</p><?php endif; ?>
    </div>
    <p style="text-align:center;margin-top:16px;font-size:.85rem"><a href="/<?= e($p['slug']) ?>" style="color:inherit">← Voltar ao site</a></p>
<?php endif; ?>
</main>
</body>
</html>
