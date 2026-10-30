<?php
// Endpoint público: entrar na lista de espera de um dia lotado
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false]); exit; }
$empresaId = (int)($_POST['e'] ?? 0);
$data = (string)($_POST['data'] ?? '');
$nome = mb_substr(trim(strip_tags((string)($_POST['nome'] ?? ''))), 0, 150);
$tel = preg_replace('/\D/', '', (string)($_POST['telefone'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || $data < date('Y-m-d') || $data > date('Y-m-d', strtotime('+120 days'))) { echo json_encode(['ok' => false, 'erro' => 'Data inválida.']); exit; }
if (mb_strlen($nome) < 2 || strlen($tel) < 10 || strlen($tel) > 13) { echo json_encode(['ok' => false, 'erro' => 'Informe seu nome e WhatsApp com DDD.']); exit; }
try {
    $st = $pdo->prepare("SELECT id, COALESCE(lista_espera, true) AS ativa FROM empresas WHERE id=? AND status IN ('ativo','pendente')"); $st->execute([$empresaId]);
    $emp = $st->fetch();
    if (!$emp || !pg_bool($emp['ativa'])) { echo json_encode(['ok' => false, 'erro' => 'Lista de espera indisponível.']); exit; }
    $st = $pdo->prepare("SELECT COUNT(*) FROM lista_espera WHERE empresa_id=? AND data=? AND right(regexp_replace(telefone, '\D', '', 'g'), 11)=right(?, 11) AND status='aguardando'");
    $st->execute([$empresaId, $data, $tel]);
    if ((int)$st->fetchColumn() > 0) { echo json_encode(['ok' => true, 'ja' => true]); exit; }
    $pdo->prepare("INSERT INTO lista_espera (empresa_id, servico_id, profissional_id, data, nome, telefone) VALUES (?,?,?,?,?,?)")
        ->execute([$empresaId, (int)($_POST['servico'] ?? 0) ?: null, (int)($_POST['profissional'] ?? 0) ?: null, $data, $nome, $tel]);
    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível agora. Tente de novo.']);
}
