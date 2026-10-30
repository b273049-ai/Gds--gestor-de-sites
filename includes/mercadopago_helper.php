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
