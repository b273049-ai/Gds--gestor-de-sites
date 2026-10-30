<?php
// ============================================================
// PAGAMENTOS PIX DO CLIENTE FINAL (dinheiro cai na conta Mercado Pago da empresa)
// Usado em: sinal/total do agendamento, pedido do delivery, sinal do orçamento
// aprovado, assinatura do clube e pacote de sessões.
// A confirmação é automática: a tela do cliente consulta a cada poucos
// segundos (site/pagamento.php) e o cron confere os pendentes.
// ============================================================
require_once __DIR__ . '/mercadopago_helper.php';

function pag_disponivel($empresa) {
    return pg_bool($empresa['pix_ativo'] ?? false) && trim((string)($empresa['mercadopago_access_token'] ?? '')) !== '';
}

// Quanto cobrar de sinal num agendamento (0 = não cobra)
function pag_valor_sinal($empresa, $valorServico, $clienteId = null) {
    global $pdo;
    if (!pag_disponivel($empresa) || $valorServico <= 0) return 0;
    $modo = $empresa['sinal_modo'] ?? 'nenhum';
    if ($modo === 'nenhum' || $modo === null || $modo === '') $modo = 'total'; // compatível com o "Pix no agendamento" antigo
    $quem = $empresa['sinal_quem'] ?? 'todos';
    if ($clienteId && $quem !== 'todos') {
        try {
            if ($quem === 'novos') {
                $st = $pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE cliente_id=? AND status='concluido'"); $st->execute([$clienteId]);
                if ((int)$st->fetchColumn() > 0) return 0;
            } elseif ($quem === 'faltosos') {
                $st = $pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE cliente_id=? AND status='faltou'"); $st->execute([$clienteId]);
                if ((int)$st->fetchColumn() === 0) return 0;
            }
        } catch (PDOException $e) { return 0; }
    } elseif (!$clienteId && $quem === 'faltosos') return 0;
    $v = match ($modo) {
        'fixo' => (float)($empresa['sinal_valor'] ?? 0),
        'percentual' => $valorServico * (float)($empresa['sinal_valor'] ?? 0) / 100,
        default => $valorServico,
    };
    return round(min($v, $valorServico), 2);
}

// Gera o Pix e guarda. $ref = ['agendamento_id'|'pedido_id'|'assinatura_id' => id]
function pag_criar($empresa, $valor, $descricao, $tipo, $ref, $clienteId = null, $email = '') {
    global $pdo;
    if (!pag_disponivel($empresa) || $valor <= 0) return ['ok' => false, 'erro' => 'Pagamento online não configurado.'];
    $cob = gerar_cobranca_pix($empresa['mercadopago_access_token'], round($valor, 2), mb_substr($descricao, 0, 120), $email);
    if (!$cob['ok']) return $cob;
    $token = bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO pagamentos_clientes (empresa_id, agendamento_id, pedido_id, assinatura_id, cliente_id, valor, status, referencia_gateway, tipo, descricao, qr_code, qr_base64, token)
        VALUES (?,?,?,?,?,?, 'pendente', ?,?,?,?,?,?)")
        ->execute([$empresa['id'], $ref['agendamento_id'] ?? null, $ref['pedido_id'] ?? null, $ref['assinatura_id'] ?? null, $clienteId, round($valor, 2),
                   (string)$cob['mp_id'], $tipo, mb_substr($descricao, 0, 200), $cob['qr_code'], $cob['qr_base64'], $token]);
    return ['ok' => true, 'token' => $token, 'qr_code' => $cob['qr_code'], 'qr_base64' => $cob['qr_base64'], 'valor' => round($valor, 2), 'tipo' => $tipo];
}

function pag_por_token($token) {
    global $pdo;
    $st = $pdo->prepare("SELECT pc.*, e.mercadopago_access_token, e.nome AS empresa_nome FROM pagamentos_clientes pc JOIN empresas e ON e.id = pc.empresa_id WHERE pc.token=?");
    $st->execute([$token]);
    return $st->fetch() ?: null;
}

// Confere no Mercado Pago e, se pago, aplica (uma única vez)
function pag_verificar($pag) {
    if (!$pag || $pag['status'] === 'pago') return $pag['status'] ?? null;
    $st = consultar_pagamento_mp($pag['mercadopago_access_token'], $pag['referencia_gateway']);
    if ($st === 'approved') { pag_confirmar((int)$pag['id']); return 'pago'; }
    if (in_array($st, ['cancelled', 'rejected', 'expired'], true)) {
        global $pdo; $pdo->prepare("UPDATE pagamentos_clientes SET status='cancelado' WHERE id=? AND status='pendente'")->execute([$pag['id']]);
        return 'cancelado';
    }
    return $pag['status'];
}

function pag_confirmar($id) {
    global $pdo;
    $st = $pdo->prepare("UPDATE pagamentos_clientes SET status='pago', pago_em=NOW() WHERE id=? AND status<>'pago' RETURNING *");
    $st->execute([$id]);
    $pag = $st->fetch();
    if (!$pag) return false; // já tinha sido confirmado
    $emp = function_exists('wa_carregar_empresa') ? wa_carregar_empresa($pag['empresa_id']) : null;
    $valor = 'R$ ' . number_format((float)$pag['valor'], 2, ',', '.');
    if ($pag['agendamento_id']) {
        $pdo->prepare("UPDATE agendamentos SET pago=true, sinal_valor=?, status=CASE WHEN status='agendado' THEN 'confirmado' ELSE status END WHERE id=?")->execute([$pag['valor'], $pag['agendamento_id']]);
        if ($emp) {
            try { wa_avisar_status_agendamento($emp, $pag['agendamento_id'], 'confirmado'); } catch (Throwable $e) {}
            wa_notificar_empresa($emp, 'agendamento', ['nome' => 'Pagamento', 'servico' => "💰 Pix de $valor recebido ({$pag['descricao']}). O horário foi confirmado."], 'agendamento', $pag['agendamento_id']);
        }
    } elseif ($pag['pedido_id']) {
        $pdo->prepare("UPDATE pedidos SET pago=true, atualizado_em=NOW() WHERE id=?")->execute([$pag['pedido_id']]);
        if ($emp) wa_notificar_empresa($emp, 'pedido', ['nome' => 'Pagamento', 'codigo' => $pag['pedido_id'], 'itens' => "💰 Pix de $valor recebido no pedido nº {$pag['pedido_id']}."], 'pedido', $pag['pedido_id']);
    } elseif ($pag['assinatura_id']) {
        if (function_exists('clube_ativar')) clube_ativar((int)$pag['assinatura_id']);
    }
    return true;
}

// Rotina (cron): confere os Pix pendentes das últimas 24h
function pag_rotina($limite = 25) {
    global $pdo;
    try {
        $pend = $pdo->query("SELECT pc.*, e.mercadopago_access_token FROM pagamentos_clientes pc JOIN empresas e ON e.id = pc.empresa_id
            WHERE pc.status='pendente' AND pc.token IS NOT NULL AND pc.criado_em > NOW() - INTERVAL '24 hours' ORDER BY pc.id DESC LIMIT " . (int)$limite)->fetchAll();
    } catch (PDOException $e) { return ['pix' => 'sem tabela']; }
    $pagos = 0;
    foreach ($pend as $p) if (pag_verificar($p) === 'pago') $pagos++;
    return ['pix' => count($pend) ? count($pend) . " conferido(s), $pagos pago(s)" : 'nada pendente'];
}

// Bloco de Pix para mostrar ao cliente (confirma sozinho)
function frag_pix_pagamento($pag, $titulo = 'Pague com Pix') {
    if (empty($pag['qr_code'])) return '';
    return '<div class="gds-pix" data-gds-pix="' . e($pag['token']) . '"><h3>' . e($titulo) . ' · ' . e(frag_preco($pag['valor'])) . '</h3>'
         . (!empty($pag['qr_base64']) ? '<img src="data:image/png;base64,' . e($pag['qr_base64']) . '" alt="QR Code Pix">' : '')
         . '<div class="gds-campo"><label for="gds-pix-' . e($pag['token']) . '">Pix copia e cola</label><textarea id="gds-pix-' . e($pag['token']) . '" readonly rows="3">' . e($pag['qr_code']) . '</textarea></div>'
         . '<button type="button" class="gds-btn" data-gds-copiar="' . e($pag['qr_code']) . '">Copiar código Pix</button>'
         . '<p class="gds-pix-status" data-gds-pix-status role="status">⏳ Aguardando o pagamento… esta tela confirma sozinha.</p></div>';
}
