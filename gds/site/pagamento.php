<?php
// Endpoint público: situação de um Pix (?t=TOKEN). A tela do cliente consulta a cada poucos segundos.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/site_render.php';
require_once __DIR__ . '/../includes/pagamentos.php';
require_once __DIR__ . '/../includes/clube.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$token = preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? ''));
$pag = strlen($token) === 32 ? pag_por_token($token) : null;
if (!$pag) { echo json_encode(['ok' => false]); exit; }
echo json_encode(['ok' => true, 'status' => pag_verificar($pag)]);
