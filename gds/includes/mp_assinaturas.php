<?php
// ============================================================
// COBRANÇA DAS ASSINATURAS PELO MERCADO PAGO (sem baixa manual)
// • Link de pagamento (Checkout Pro: Pix, cartão e boleto) para cada cobrança.
// • A vendedora IA manda o link quando fecha a venda.
// • Pagou → o webhook (ou a rotina, de reserva) confirma, ativa a empresa,
//   avança o vencimento e avisa você e o cliente no WhatsApp.
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/mercadopago_helper.php';

function mp_plat_token() { return trim(get_config('mercadopago_access_token', '')); }
function mp_url_base() {
    $cfg = rtrim(get_config('plataforma_url', ''), '/');
    if ($cfg !== '') return $cfg;
    $host = preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['HTTP_HOST'] ?? '');
    $https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https://' : 'http://') . $host;
}
function mp_webhook_url() { return mp_url_base() . '/api/mercadopago_webhook.php'; }

// Link de pagamento de uma cobrança (reaproveita o que já foi gerado)
function mp_link_assinatura($pagamentoId) {
    global $pdo;
    $st = $pdo->prepare("SELECT pa.*, e.nome, e.email, e.slug FROM pagamentos_assinaturas pa JOIN empresas e ON e.id=pa.empresa_id WHERE pa.id=?");
    $st->execute([(int)$pagamentoId]);
    $pa = $st->fetch();
    if (!$pa) return ['ok' => false, 'erro' => 'Cobrança não encontrada.'];
    if ($pa['status'] === 'pago') return ['ok' => false, 'erro' => 'Esta cobrança já foi paga.'];
    if (!empty($pa['link_pagamento'])) return ['ok' => true, 'link' => $pa['link_pagamento']];
    if (mp_plat_token() === '') return ['ok' => false, 'erro' => 'Cole o Access Token do Mercado Pago em Configurações → Pagamentos.'];
    $marca = get_config('mkt_marca', '') ?: 'GDS Sites';
    $r = mp_criar_preferencia(mp_plat_token(), [
        'titulo' => "$marca · site da {$pa['nome']}", 'valor' => (float)$pa['valor'], 'referencia' => 'gds-assin-' . (int)$pa['id'],
        'email' => (string)$pa['email'], 'notificacao' => mp_webhook_url(), 'volta' => mp_url_base() . '/' . $pa['slug'], 'fatura' => $marca,
    ]);
    if (!$r['ok']) return $r;
    $pdo->prepare("UPDATE pagamentos_assinaturas SET link_pagamento=?, mp_preferencia=?, metodo=COALESCE(metodo, 'mercadopago') WHERE id=?")->execute([$r['link'], $r['id'], $pa['id']]);
    return ['ok' => true, 'link' => $r['link']];
}

// Cria a 1ª cobrança de uma empresa nova (ex.: a vendedora IA fechou) e devolve o link
function mp_cobranca_nova($empresaId, $valor, $planoId = null, $dias = 3) {
    global $pdo;
    if ((float)$valor <= 0 || mp_plat_token() === '') return ['ok' => false, 'erro' => 'Sem valor ou sem Mercado Pago configurado.'];
    $ja = $pdo->prepare("SELECT id FROM pagamentos_assinaturas WHERE empresa_id=? AND status IN ('pendente','atrasado') ORDER BY id DESC LIMIT 1");
    $ja->execute([(int)$empresaId]);
    $id = (int)$ja->fetchColumn();
    if (!$id) {
        $st = $pdo->prepare("INSERT INTO pagamentos_assinaturas (empresa_id, plano_id, valor, vencimento, status) VALUES (?,?,?, CURRENT_DATE + ?::int, 'pendente') RETURNING id");
        $st->execute([(int)$empresaId, $planoId ?: null, round((float)$valor, 2), (int)$dias]);
        $id = (int)$st->fetchColumn();
    }
    return mp_link_assinatura($id) + ['pagamento_id' => $id];
}

// Dá baixa: pago, empresa ativa, vencimento avança 1 ciclo (mesma regra do Financeiro)
function mp_baixar_assinatura($pagamentoId, $metodo, $refGateway = null) {
    global $pdo;
    $pg = $pdo->prepare("SELECT pa.*, p.ciclo FROM pagamentos_assinaturas pa JOIN empresas e ON e.id = pa.empresa_id LEFT JOIN planos p ON p.id = e.plano_id WHERE pa.id=? AND pa.status <> 'pago'");
    $pg->execute([(int)$pagamentoId]);
    if (!($pg = $pg->fetch())) return null;
    $intervalo = ($pg['ciclo'] ?? 'mensal') === 'anual' ? '1 year' : '1 month';
    $pdo->beginTransaction();
    $up = $pdo->prepare("UPDATE pagamentos_assinaturas SET status='pago', pago_em=NOW(), metodo=?, referencia_gateway=COALESCE(?, referencia_gateway) WHERE id=? AND status <> 'pago'");
    $up->execute([mb_substr((string)$metodo, 0, 30), $refGateway, $pg['id']]);
    if ($up->rowCount() !== 1) { $pdo->rollBack(); return null; } // outro processo já deu baixa
    $nv = $pdo->prepare("SELECT (GREATEST(COALESCE(e.vencimento, CURRENT_DATE), CURRENT_DATE) + INTERVAL '$intervalo')::date FROM empresas e WHERE e.id=?");
    $nv->execute([$pg['empresa_id']]); $novoVenc = $nv->fetchColumn();
    $pdo->prepare("UPDATE empresas SET status='ativo', vencimento=? WHERE id=?")->execute([$novoVenc, $pg['empresa_id']]);
    $pdo->prepare("UPDATE usuarios SET ativo=true WHERE empresa_id=?")->execute([$pg['empresa_id']]);
    $pdo->commit();
    return $novoVenc;
}

// Confere um pagamento no Mercado Pago (o webhook só diz "mudou": a verdade vem da API)
function mp_processar_pagamento($mpPaymentId) {
    global $pdo;
    $r = mp_api('GET', '/v1/payments/' . rawurlencode((string)$mpPaymentId), mp_plat_token());
    $p = $r['json'];
    if (empty($p['id'])) return ['ok' => false, 'erro' => $r['erro'] ?: 'pagamento não encontrado'];
    if (!preg_match('/^gds-assin-(\d+)$/', (string)($p['external_reference'] ?? ''), $m)) return ['ok' => true, 'ignorado' => 'não é assinatura'];
    $pagId = (int)$m[1];
    if (($p['status'] ?? '') !== 'approved') return ['ok' => true, 'status' => $p['status'] ?? '?'];
    $st = $pdo->prepare("SELECT pa.valor, e.id empresa_id, e.nome, e.whatsapp, e.slug FROM pagamentos_assinaturas pa JOIN empresas e ON e.id=pa.empresa_id WHERE pa.id=?");
    $st->execute([$pagId]);
    $info = $st->fetch();
    if (!$info) return ['ok' => false, 'erro' => 'cobrança não existe'];
    if ((float)($p['transaction_amount'] ?? 0) + 0.01 < (float)$info['valor']) return ['ok' => false, 'erro' => 'valor pago menor que o cobrado'];
    $venc = mp_baixar_assinatura($pagId, 'mercadopago ' . ($p['payment_type_id'] ?? ''), (string)$p['id']);
    if ($venc === null) return ['ok' => true, 'ja_pago' => true];
    mp_avisar_pago($info, $venc);
    return ['ok' => true, 'pago' => true, 'vencimento' => $venc];
}

function mp_avisar_pago($info, $venc) {
    if (!function_exists('prosp_alertar') && is_file(__DIR__ . '/prospeccao.php')) require_once __DIR__ . '/prospeccao.php';
    if (function_exists('prosp_alertar')) prosp_alertar("💰 Pagamento confirmado: {$info['nome']} pagou R$ " . number_format((float)$info['valor'], 2, ',', '.') . " pelo Mercado Pago.\nEmpresa ativa até " . date('d/m/Y', strtotime($venc)) . '.');
    if (!empty($info['whatsapp']) && function_exists('wa_remetente') && ($rem = wa_remetente(['wa_modo' => 'plataforma']))) {
        try { wa_enviar_agora($rem, wa_numero($info['whatsapp']), "✅ Pagamento confirmado! O site da {$info['nome']} está ativo até " . date('d/m/Y', strtotime($venc)) . ". Obrigado pela confiança! 🙌\n" . mp_url_base() . '/' . $info['slug']); } catch (Throwable $e) {}
    }
}

// Reserva do webhook: procura no Mercado Pago as cobranças com link que ainda estão em aberto
function mp_rotina($limite = 15) {
    global $pdo;
    if (mp_plat_token() === '') return ['mp' => 'sem token'];
    try {
        $abertas = $pdo->query("SELECT id FROM pagamentos_assinaturas WHERE status IN ('pendente','atrasado') AND link_pagamento IS NOT NULL
            AND criado_em > NOW() - INTERVAL '45 days' ORDER BY id DESC LIMIT " . (int)$limite)->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) { return ['mp' => 'sem coluna']; }
    $pagos = 0;
    foreach ($abertas as $id) {
        $r = mp_api('GET', '/v1/payments/search?' . http_build_query(['external_reference' => 'gds-assin-' . (int)$id, 'status' => 'approved', 'limit' => 1]), mp_plat_token());
        $pid = $r['json']['results'][0]['id'] ?? null;
        if ($pid && !empty(mp_processar_pagamento($pid)['pago'])) $pagos++;
    }
    return ['mp' => count($abertas) . " cobrança(s) conferida(s), $pagos paga(s)"];
}
