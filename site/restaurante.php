<?php
// ============================================================
// Rotas públicas do restaurante (includes/restaurante.php):
//   POST acao=reservar|fila|chamar  (e = empresa)  → JSON
//   GET  ?fila=TOKEN                → "minha posição na fila" (POST sair=1 sai da fila)
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/migracoes.php';
require_once __DIR__ . '/../includes/whatsapp.php';
require_once __DIR__ . '/../includes/recursos_site.php';
require_once __DIR__ . '/../includes/restaurante.php';
if (!function_exists('e')) { function e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); } }
gds_garantir_schema();
$json = function ($d, $cod = 200) { http_response_code($cod); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; };

// Página da fila
if (isset($_GET['fila'])) {
    $tok = preg_replace('/[^a-f0-9]/', '', (string)$_GET['fila']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sair'])) {
        $pdo->prepare("UPDATE fila_espera SET status='desistiu' WHERE token=? AND status='aguardando'")->execute([$tok]);
        header('Location: restaurante.php?fila=' . $tok, true, 303); exit;
    }
    $f = $tok !== '' ? fila_posicao($tok) : null;
    header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
    if (!$f) { http_response_code(404); echo 'Fila não encontrada.'; exit; }
    $cor = $pdo->prepare("SELECT s.cor_primaria FROM sites s JOIN empresas e ON e.id=s.empresa_id WHERE e.slug=?"); $cor->execute([$f['slug']]);
    $c = (string)$cor->fetchColumn();
    echo fila_pagina_html($tok, $f, preg_match('/^#[0-9a-f]{6}$/i', $c) ? $c : '#1b1b1b'); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') $json(['ok' => false], 405);
// limite simples por IP: 15 envios por minuto
$arq = sys_get_temp_dir() . '/gds_rest_' . md5(($_SERVER['REMOTE_ADDR'] ?? '') . date('YmdHi'));
$n = (int)@file_get_contents($arq) + 1; @file_put_contents($arq, (string)$n);
if ($n > 15) $json(['ok' => false, 'msg' => 'Muitas tentativas. Espere um minuto.'], 429);

$st = $pdo->prepare("SELECT e.*, s.recursos, s.publicado FROM empresas e JOIN sites s ON s.empresa_id=e.id WHERE e.id=?");
$st->execute([(int)($_POST['e'] ?? 0)]);
$emp = $st->fetch();
if (!$emp || !pg_bool($emp['publicado'] ?? true)) $json(['ok' => false, 'msg' => 'Site não encontrado.'], 404);
$r = rec_ler($emp);
$acao = (string)($_POST['acao'] ?? '');
if ($acao === 'reservar') {
    if (!rec_ativo($r, 'reserva')) $json(['ok' => false, 'msg' => 'Reservas pelo site desligadas.']);
    [$ok, $msg] = res_criar($emp, $_POST); $json(['ok' => $ok, 'msg' => $msg]);
}
if ($acao === 'fila') {
    if (!rec_ativo($r, 'fila')) $json(['ok' => false, 'msg' => 'Fila pelo site desligada.']);
    [$ok, $msg, $tok] = fila_entrar($emp, $_POST);
    $json(['ok' => $ok, 'msg' => $msg, 'link' => $tok ? '/site/restaurante.php?fila=' . $tok : null]);
}
if ($acao === 'chamar') {
    if (!rec_ativo($r, 'garcom')) $json(['ok' => false, 'msg' => 'Chamada pelo site desligada.']);
    [$ok, $msg] = mesa_chamar($emp, $_POST['mesa'] ?? '', (string)($_POST['tipo'] ?? '')); $json(['ok' => $ok, 'msg' => $msg]);
}
$json(['ok' => false, 'msg' => 'Ação desconhecida.'], 400);
