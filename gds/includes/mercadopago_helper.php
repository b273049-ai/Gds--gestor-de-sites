<?php
// ============================================================
// Gera uma cobrança PIX via Mercado Pago. Retorna QR code (base64),
// código copia-e-cola e o ID do pagamento no Mercado Pago.
// Usado tanto pela cobrança de assinatura (admin) quanto pelo
// pagamento do cliente final direto no site (com o token da
// própria empresa, pra o dinheiro cair na conta dela).
// ============================================================
function gerar_cobranca_pix($token, $valor, $descricao, $emailPagador = 'cliente@exemplo.com') {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'erro' => 'Extensão cURL do PHP não habilitada nesta hospedagem.'];
    }
    if (trim($token) === '') {
        return ['ok' => false, 'erro' => 'Nenhum Access Token do Mercado Pago configurado.'];
    }

    $ch = curl_init(rtrim(getenv('GDS_MP_BASE_URL') ?: 'https://api.mercadopago.com', '/') . '/v1/payments');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
            'X-Idempotency-Key: gds-' . uniqid('', true),
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'transaction_amount' => (float)$valor,
            'description' => $descricao,
            'payment_method_id' => 'pix',
            'payer' => ['email' => $emailPagador ?: 'cliente@exemplo.com'],
        ]),
    ]);
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErro = curl_error($ch);
    curl_close($ch);

    if ($curlErro) {
        return ['ok' => false, 'erro' => 'Erro de conexão: ' . $curlErro];
    }

    $data = json_decode($resposta, true);

    if ($httpCode !== 200 && $httpCode !== 201) {
        $msgApi = $data['message'] ?? 'Erro desconhecido';
        return ['ok' => false, 'erro' => "Falha ao gerar cobrança (HTTP $httpCode): $msgApi"];
    }

    $qrBase64 = $data['point_of_interaction']['transaction_data']['qr_code_base64'] ?? '';
    $qrCode = $data['point_of_interaction']['transaction_data']['qr_code'] ?? '';
    $mpId = $data['id'] ?? null;

    if ($qrCode === '') {
        return ['ok' => false, 'erro' => 'O Mercado Pago aceitou mas não retornou QR Code PIX. Verifique se a conta tem PIX habilitado.'];
    }

    return ['ok' => true, 'qr_base64' => $qrBase64, 'qr_code' => $qrCode, 'mp_id' => $mpId];
}

// Consulta um pagamento no Mercado Pago. Retorna o status (approved, pending, rejected, cancelled...) ou null.
function consultar_pagamento_mp($token, $mpId) {
    if (trim((string)$token) === '' || !$mpId || !function_exists('curl_init')) return null;
    $ch = curl_init(rtrim(getenv('GDS_MP_BASE_URL') ?: 'https://api.mercadopago.com', '/') . '/v1/payments/' . rawurlencode((string)$mpId));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token]]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code !== 200) return null;
    return json_decode((string)$r, true)['status'] ?? null;
}

// ============================================================
// CHECKOUT PRO: link de pagamento (Pix, cartão em até 12x e boleto) numa página do Mercado Pago.
// O cliente escolhe como pagar; a confirmação chega pelo webhook (api/mercadopago_webhook.php).
// ============================================================
function mp_base_url() { return rtrim(getenv('GDS_MP_BASE_URL') ?: 'https://api.mercadopago.com', '/'); }

function mp_api($metodo, $caminho, $token, $corpo = null) {
    if (!function_exists('curl_init') || trim((string)$token) === '') return ['code' => 0, 'json' => [], 'erro' => 'Sem Access Token do Mercado Pago.'];
    $ch = curl_init(mp_base_url() . $caminho);
    $h = ['Authorization: Bearer ' . $token, 'Accept: application/json'];
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CUSTOMREQUEST => $metodo];
    if ($corpo !== null) { $o[CURLOPT_POSTFIELDS] = json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); $h[] = 'Content-Type: application/json'; $h[] = 'X-Idempotency-Key: gds-' . bin2hex(random_bytes(8)); }
    $o[CURLOPT_HTTPHEADER] = $h;
    curl_setopt_array($ch, $o);
    $r = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    $j = json_decode((string)$r, true);
    return ['code' => $code, 'json' => is_array($j) ? $j : [], 'erro' => $r === false ? $err : ($code >= 400 ? (string)(($j['message'] ?? '') ?: "HTTP $code") : '')];
}

// $d: titulo, valor, referencia (external_reference), email, notificacao (URL), volta (URL)
function mp_criar_preferencia($token, $d) {
    $corpo = [
        'items' => [['id' => (string)$d['referencia'], 'title' => mb_substr((string)$d['titulo'], 0, 120), 'quantity' => 1, 'currency_id' => 'BRL', 'unit_price' => round((float)$d['valor'], 2)]],
        'external_reference' => (string)$d['referencia'],
        'notification_url' => (string)$d['notificacao'],
        'statement_descriptor' => mb_substr(preg_replace('/[^A-Za-z0-9 ]/', '', (string)($d['fatura'] ?? 'SITE')), 0, 13) ?: 'SITE',
        'expires' => false,
    ];
    if (!empty($d['email']) && filter_var($d['email'], FILTER_VALIDATE_EMAIL) && !str_contains($d['email'], '@clientes.')) $corpo['payer'] = ['email' => $d['email']];
    if (!empty($d['volta'])) { $corpo['back_urls'] = ['success' => $d['volta'], 'pending' => $d['volta'], 'failure' => $d['volta']]; $corpo['auto_return'] = 'approved'; }
    $r = mp_api('POST', '/checkout/preferences', $token, $corpo);
    if (empty($r['json']['init_point'])) return ['ok' => false, 'erro' => 'Mercado Pago: ' . ($r['erro'] ?: 'não gerou o link')];
    return ['ok' => true, 'id' => (string)$r['json']['id'], 'link' => (string)$r['json']['init_point']];
}

// Assinatura do webhook (x-signature: "ts=...,v1=..."), conforme a documentação do Mercado Pago:
// v1 = HMAC-SHA256("id:{data.id};request-id:{x-request-id};ts:{ts};", assinatura secreta)
function mp_assinatura_valida($segredo, $xSignature, $xRequestId, $dataId) {
    $partes = [];
    foreach (explode(',', (string)$xSignature) as $p) { $kv = array_map('trim', explode('=', $p, 2)); if (count($kv) === 2) $partes[$kv[0]] = $kv[1]; }
    if (empty($partes['ts']) || empty($partes['v1']) || $dataId === '') return false;
    $id = ctype_alnum((string)$dataId) ? strtolower((string)$dataId) : (string)$dataId;
    $manifesto = 'id:' . $id . ';' . ($xRequestId !== '' ? 'request-id:' . $xRequestId . ';' : '') . 'ts:' . $partes['ts'] . ';';
    return hash_equals(hash_hmac('sha256', $manifesto, (string)$segredo), (string)$partes['v1']);
}
