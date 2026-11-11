<?php
// Webhook do Mercado Pago: pagamento das assinaturas (links do Checkout Pro).
// Cadastre em mercadopago.com.br/developers → Suas integrações → (sua aplicação) → Webhooks:
//   URL: https://SEU-DOMINIO/api/mercadopago_webhook.php · Evento: Pagamentos
// e cole a "Assinatura secreta" em Admin → Configurações → Mercado Pago.
// O aviso só diz "o pagamento X mudou"; a plataforma confere o status na API antes de dar baixa.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/migracoes.php';
require_once __DIR__ . '/../includes/whatsapp.php';
require_once __DIR__ . '/../includes/mp_assinaturas.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$corpo = json_decode((string)file_get_contents('php://input', false, null, 0, 65536), true) ?: [];
$tipo = (string)($_GET['type'] ?? $_GET['topic'] ?? $corpo['type'] ?? $corpo['topic'] ?? '');
$dataId = (string)($_GET['data_id'] ?? $corpo['data']['id'] ?? $_GET['id'] ?? '');
$segredo = get_config('mp_webhook_secret', '');
if ($segredo !== '' && !mp_assinatura_valida($segredo, (string)($_SERVER['HTTP_X_SIGNATURE'] ?? ''), (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? ''), $dataId)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Assinatura inválida.']);
    exit;
}
// Responde 200 na hora (o Mercado Pago reenvia se demorar) e processa depois
echo json_encode(['ok' => true]);
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
if ($tipo !== 'payment' || !preg_match('/^\d{1,20}$/', $dataId)) exit;
try { gds_garantir_schema(); mp_processar_pagamento($dataId); }
catch (Throwable $e) { error_log('GDS Mercado Pago webhook: ' . $e->getMessage()); }
