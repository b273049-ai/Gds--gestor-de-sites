<?php
// Endpoint público do calendário do site:
//   ?e=ID&data=AAAA-MM-DD&duracao=60&profissional=ID → horários livres e ocupados do dia
//   ?e=ID&mes=AAAA-MM                                  → dias de folga/feriado do mês
// As respostas ficam guardadas por 20 segundos (includes/cache_pagina.php): vários clientes olhando o mesmo dia
// não viram várias idas ao banco. Um agendamento novo ou uma mudança no painel refaz na hora.
require_once __DIR__ . '/../includes/cache_pagina.php';
$empresaId = (int)($_GET['e'] ?? 0);
$gdsJson = 'application/json; charset=utf-8';
$gdsCab = ['Cache-Control: no-store'];
$gdsDia = (string)($_GET['data'] ?? ($_GET['mes'] ?? ''));
$gdsCache = $empresaId > 0 && preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $gdsDia) && abs(strtotime(substr($gdsDia, 0, 7) . '-01') - time()) < 400 * 86400
    ? gds_cache_abrir(['horarios', $empresaId, $gdsDia, (int)($_GET['duracao'] ?? 30), (int)($_GET['profissional'] ?? 0), (int)($_GET['servico'] ?? 0)], ['fresco' => 20, 'vencido' => 0, 'emergencia' => false])
    : null;
if ($gdsCache && $gdsCache['estado'] === 'SERVIDO') exit;
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/agenda_helper.php';
header('Content-Type: ' . $gdsJson);
header('Cache-Control: no-store');

$st = $pdo->prepare("SELECT e.* FROM empresas e JOIN sites s ON s.empresa_id = e.id WHERE e.id=? LIMIT 1");
$st->execute([$empresaId]);
$empresa = $st->fetch();
if (!$empresa) { http_response_code(404); echo json_encode(['livres' => [], 'ocupados' => []]); exit; }

if (preg_match('/^\d{4}-\d{2}$/', $_GET['mes'] ?? '')) {
    $ini = $_GET['mes'] . '-01';
    gds_cache_finalizar($gdsCache, json_encode(['fechados' => agenda_dias_fechados($empresaId, $ini, date('Y-m-t', strtotime($ini)))]), $empresaId, $gdsCab, $gdsJson);
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

$servico = null;
if ($sid = (int)($_GET['servico'] ?? 0)) {
    try { $sv = $pdo->prepare("SELECT id, vagas, duracao_minutos FROM servicos WHERE id=? AND empresa_id=?"); $sv->execute([$sid, $empresaId]); $servico = $sv->fetch() ?: null; } catch (PDOException $e) { $servico = null; }
}
gds_cache_finalizar($gdsCache, json_encode(agenda_horarios_do_dia($empresa, $data, $duracao, $profissionalId, $servico)), $empresaId, $gdsCab, $gdsJson);
