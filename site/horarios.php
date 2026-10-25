<?php
// Endpoint público do calendário do site:
//   ?e=ID&data=AAAA-MM-DD&duracao=60&profissional=ID → horários livres e ocupados do dia
//   ?e=ID&mes=AAAA-MM                                  → dias de folga/feriado do mês
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/agenda_helper.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$empresaId = (int)($_GET['e'] ?? 0);
$st = $pdo->prepare("SELECT e.* FROM empresas e JOIN sites s ON s.empresa_id = e.id WHERE e.id=? LIMIT 1");
$st->execute([$empresaId]);
$empresa = $st->fetch();
if (!$empresa) { http_response_code(404); echo json_encode(['livres' => [], 'ocupados' => []]); exit; }

if (preg_match('/^\d{4}-\d{2}$/', $_GET['mes'] ?? '')) {
    $ini = $_GET['mes'] . '-01';
    echo json_encode(['fechados' => agenda_dias_fechados($empresaId, $ini, date('Y-m-t', strtotime($ini)))]);
    exit;
}

$data = $_GET['data'] ?? '';
$duracao = max(10, min(600, (int)($_GET['duracao'] ?? 30)));
$profissionalId = (int)($_GET['profissional'] ?? 0) ?: null;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) { http_response_code(400); echo json_encode(['livres' => [], 'ocupados' => []]); exit; }

if ($profissionalId) {
    $p = $pdo->prepare("SELECT 1 FROM profissionais WHERE id=? AND empresa_id=?");
    $p->execute([$profissionalId, $empresaId]);
    if (!$p->fetch()) $profissionalId = null;
}

echo json_encode(agenda_horarios_do_dia($empresa, $data, $duracao, $profissionalId));
