<?php
// Endpoint público: registra uma visita ou um clique importante do site
// (anônimo — só um id aleatório do navegador). Alimenta "Desempenho do site".
require_once __DIR__ . '/../config/db.php';
header('Cache-Control: no-store');
http_response_code(204);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

$tipos = ['visita', 'whatsapp', 'ligar', 'rota', 'agendar', 'orcamento', 'pedido', 'contato', 'instagram'];
$empresaId = (int)($_POST['e'] ?? 0);
$tipo = (string)($_POST['t'] ?? '');
if ($empresaId <= 0 || !in_array($tipo, $tipos, true)) exit;
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|headless|lighthouse/i', $ua)) exit;

$visitante = substr(preg_replace('/[^a-z0-9]/i', '', (string)($_POST['v'] ?? '')), 0, 40);
if ($visitante === '') $visitante = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . $ua . date('Y-m-d')), 0, 24);

// De onde veio: utm_source, ou o site que trouxe a pessoa
$origem = 'direto';
parse_str(ltrim((string)($_POST['u'] ?? ''), '?'), $q);
$ref = strtolower((string)parse_url((string)($_POST['r'] ?? ''), PHP_URL_HOST));
if (!empty($q['utm_source'])) $origem = substr(strtolower(preg_replace('/[^a-z0-9_.-]/i', '', $q['utm_source'])), 0, 60);
elseif (!empty($q['fbclid']) || preg_match('/instagram|facebook|fb\.|l\.instagram/', $ref)) $origem = 'instagram';
elseif (!empty($q['gclid']) || str_contains($ref, 'google')) $origem = 'google';
elseif (str_contains($ref, 'tiktok')) $origem = 'tiktok';
elseif (preg_match('/whatsapp|wa\.me/', $ref)) $origem = 'whatsapp';
elseif ($ref !== '' && $ref !== strtolower((string)($_SERVER['HTTP_HOST'] ?? ''))) $origem = substr($ref, 0, 60);
elseif ($ref !== '') $origem = 'interno';
$dispositivo = preg_match('/Mobi|Android|iPhone|iPad/i', $ua) ? 'celular' : 'computador';

try {
    // Visita conta 1 vez a cada 30 min por pessoa; cliques, 1 vez por minuto do mesmo tipo
    $janela = $tipo === 'visita' ? '30 minutes' : '1 minute';
    $st = $pdo->prepare("SELECT 1 FROM site_eventos WHERE empresa_id=? AND visitante=? AND tipo=? AND criado_em > NOW() - INTERVAL '$janela' LIMIT 1");
    $st->execute([$empresaId, $visitante, $tipo]);
    if ($st->fetch()) exit;
    if ($tipo === 'visita' && $origem === 'interno') exit;
    $pdo->prepare("INSERT INTO site_eventos (empresa_id, tipo, visitante, origem, dispositivo) SELECT id, ?, ?, ?, ? FROM empresas WHERE id=?")
        ->execute([$tipo, $visitante, $origem, $dispositivo, $empresaId]);
} catch (Throwable $e) { /* tabela ainda não existe: ignora */ }
