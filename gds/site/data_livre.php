<?php
// Endpoint público do orçamento de eventos: ?e=ID&data=AAAA-MM-DD → {"controla":true,"livre":false}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/orcamento.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$st = $pdo->prepare("SELECT e.* FROM empresas e JOIN sites s ON s.empresa_id = e.id WHERE e.id=? LIMIT 1");
$st->execute([(int)($_GET['e'] ?? 0)]);
$empresa = $st->fetch();
$data = (string)($_GET['data'] ?? '');
if (!$empresa || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) { http_response_code(400); echo json_encode(['controla' => false]); exit; }
$livre = orc_data_livre($empresa, $data);
echo json_encode(['controla' => $livre !== null, 'livre' => (bool)$livre]);
