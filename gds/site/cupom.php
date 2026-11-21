<?php
// Endpoint público: confere um cupom de desconto da empresa (?e=ID&c=CODIGO)
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$codigo = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_GET['c'] ?? '')));
$st = $pdo->prepare("SELECT desconto_percentual, validade, ativo FROM cupons WHERE empresa_id=? AND UPPER(codigo)=? LIMIT 1");
$st->execute([(int)($_GET['e'] ?? 0), $codigo]);
$c = $st->fetch();
if (!$c || !pg_bool($c['ativo'])) { echo json_encode(['ok' => false, 'erro' => 'Cupom não encontrado.']); exit; }
if ($c['validade'] && strtotime($c['validade']) < strtotime('today')) { echo json_encode(['ok' => false, 'erro' => 'Esse cupom já venceu.']); exit; }
echo json_encode(['ok' => true, 'desconto' => (float)$c['desconto_percentual']]);
