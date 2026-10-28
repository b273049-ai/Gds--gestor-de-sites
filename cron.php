<?php
// Rotina automática: lembretes de agendamento, reenvio de mensagens de WhatsApp
// e fotos exclusivas por IA para os sites que ainda não têm foto.
// Configure na hospedagem (Hostinger: Avançado → Cron Jobs), a cada 5 minutos:
//   curl -s "https://SEU-DOMINIO/cron.php?chave=CHAVE" >/dev/null
// A chave aparece em Admin → WhatsApp.
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/migracoes.php';
require_once __DIR__ . '/includes/whatsapp.php';
require_once __DIR__ . '/includes/fotos.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$chave = get_config('cron_chave', '');
$enviada = (string)($_GET['chave'] ?? (PHP_SAPI === 'cli' ? ($argv[1] ?? '') : ''));
if ($chave === '' || !hash_equals($chave, $enviada)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'erro' => 'Chave inválida.']);
    exit;
}
@set_time_limit(120);
gds_garantir_schema();
$res = ['ok' => true] + wa_rotina(true);
// Fotos exclusivas por IA para sites sem foto (uma por execução, para não estourar o tempo)
try { $res += fotos_ia_rotina(); } catch (Throwable $e) { $res['fotos_ia'] = 'erro: ' . mb_substr($e->getMessage(), 0, 160); }
echo json_encode($res);
