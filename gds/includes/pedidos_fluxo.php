<?php
// ============================================================
// FLUXO DO PEDIDO (delivery e orçamento): muda a etapa, guarda a hora de cada
// etapa (para o tempo médio de preparo/entrega) e avisa o cliente no WhatsApp.
// Usado pelo Gestor de pedidos e pela lista de pedidos.
// ============================================================

const PEDIDO_STATUS = ['novo', 'orcado', 'aprovado', 'em_andamento', 'pronto', 'saiu_entrega', 'concluido', 'cancelado'];

function pedido_mudar_status($empresa, $pedidoId, $novo, $motivo = '') {
    global $pdo;
    if (!in_array($novo, PEDIDO_STATUS, true)) return ['ok' => false, 'erro' => 'Situação inválida.'];
    $st = $pdo->prepare("SELECT * FROM pedidos WHERE id=? AND empresa_id=?");
    $st->execute([(int)$pedidoId, (int)$empresa['id']]);
    $p = $st->fetch();
    if (!$p) return ['ok' => false, 'erro' => 'Pedido não encontrado.'];
    if ($p['status'] === $novo) return ['ok' => true, 'msg' => '', 'pedido' => $p];
    $motivo = mb_substr(trim((string)$motivo), 0, 300);
    $hora = ['em_andamento' => 'aceito_em', 'pronto' => 'pronto_em', 'saiu_entrega' => 'saiu_em', 'concluido' => 'concluido_em'][$novo] ?? null;
    $sets = ['status=?', 'atualizado_em=NOW()']; $vals = [$novo];
    if ($hora) $sets[] = "$hora = COALESCE($hora, NOW())";
    if ($novo === 'cancelado' && $motivo !== '') { $sets[] = 'resposta=?'; $vals[] = $motivo; }
    $vals[] = $p['id'];
    try {
        $pdo->prepare("UPDATE pedidos SET " . implode(', ', $sets) . " WHERE id=?")->execute($vals);
    } catch (PDOException $e) {
        // Banco ainda sem as colunas de horário: muda só a situação
        $pdo->prepare("UPDATE pedidos SET status=?, atualizado_em=NOW()" . ($novo === 'cancelado' && $motivo !== '' ? ', resposta=?' : '') . " WHERE id=?")
            ->execute($novo === 'cancelado' && $motivo !== '' ? [$novo, $motivo, $p['id']] : [$novo, $p['id']]);
    }
    if (function_exists('wa_adiar')) wa_adiar();
    $msgId = function_exists('wa_avisar_status_pedido') ? wa_avisar_status_pedido($empresa, $p['id'], $novo) : null;
    return ['ok' => true, 'msg' => function_exists('wa_resultado_para_tela') ? wa_resultado_para_tela($msgId) : '', 'pedido' => array_merge($p, ['status' => $novo])];
}

// Próxima etapa de um pedido de delivery (o botão principal do gestor)
function pedido_proxima_etapa($p) {
    $mesa = !empty($p['mesa']);
    $retirada = ($p['tipo_entrega'] ?? '') === 'retirada';
    return match ($p['status']) {
        'novo' => ['em_andamento', 'Aceitar'],
        'em_andamento' => $mesa ? ['concluido', 'Servido na mesa'] : ($retirada ? ['pronto', 'Pronto p/ retirada'] : ['saiu_entrega', 'Saiu para entrega']),
        'pronto' => ['concluido', 'Retirado'],
        'saiu_entrega' => ['concluido', 'Entregue'],
        default => null,
    };
}

// Mensagem de WhatsApp para o entregador: cliente, endereço com rota no Maps e quanto cobrar
function pedido_link_entregador($empresa, $pedidoId, $entregador) {
    global $pdo;
    if (empty($entregador['telefone'])) return null;
    $st = $pdo->prepare("SELECT p.*, c.nome AS cliente_nome, c.telefone AS cliente_telefone FROM pedidos p LEFT JOIN clientes c ON c.id=p.cliente_id WHERE p.id=? AND p.empresa_id=?");
    $st->execute([(int)$pedidoId, (int)$empresa['id']]);
    $p = $st->fetch();
    if (!$p) return null;
    $din = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
    $online = pg_bool($p['pago'] ?? false) || stripos((string)$p['forma_pagamento'], 'pix pelo site') !== false || stripos((string)$p['forma_pagamento'], 'online') !== false;
    $l = ['🛵 *Entrega nº ' . $p['id'] . '* — ' . $empresa['nome'], '', '👤 ' . ($p['cliente_nome'] ?: 'Cliente') . ($p['cliente_telefone'] ? ' · ' . $p['cliente_telefone'] : '')];
    if ($p['endereco_entrega']) { $l[] = '📍 ' . $p['endereco_entrega']; $l[] = '🗺 https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($p['endereco_entrega']); }
    $l[] = $online ? '✅ Já está pago' : '💰 Cobrar ' . $din($p['valor']) . ' (' . $p['forma_pagamento'] . ')' . ($p['troco_para'] ? ' — levar troco para ' . $din($p['troco_para']) : '');
    if ($p['observacoes']) $l[] = '📝 ' . $p['observacoes'];
    return link_whatsapp($entregador['telefone'], implode("\n", $l));
}
