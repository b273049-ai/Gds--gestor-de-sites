<?php
// ============================================================
// Rotas públicas dos recursos do site (includes/recursos_site.php):
//   ?acao=manifest&e=ID   → manifest do "app" da empresa (JSON)
//   POST acao=fidelidade  → selos do cliente (e, tel)
//   POST acao=aniversario → cadastro do aniversário (e, nome, tel, dia, mes)
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/migracoes.php';
require_once __DIR__ . '/../includes/whatsapp.php';
require_once __DIR__ . '/../includes/atendimento.php';
require_once __DIR__ . '/../includes/recursos_site.php';
if (!function_exists('e')) { function e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); } }
gds_garantir_schema();

$acao = (string)($_GET['acao'] ?? $_POST['acao'] ?? '');
$eid = (int)($_GET['e'] ?? $_POST['e'] ?? 0);
$st = $pdo->prepare("SELECT e.*, s.recursos, s.cor_primaria, s.publicado FROM empresas e JOIN sites s ON s.empresa_id=e.id WHERE e.id=?");
$st->execute([$eid]);
$emp = $st->fetch();
$json = function ($d, $cod = 200) { http_response_code($cod); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; };
if (!$emp || !pg_bool($emp['publicado'] ?? true)) $json(['ok' => false, 'msg' => 'Site não encontrado.'], 404);
$r = rec_ler($emp);

// Limite simples por IP (arquivo temporário): 20 consultas por minuto
$limite = function () use ($json) {
    $arq = sys_get_temp_dir() . '/gds_rec_' . md5(($_SERVER['REMOTE_ADDR'] ?? '') . date('YmdHi'));
    $n = (int)@file_get_contents($arq) + 1; @file_put_contents($arq, (string)$n);
    if ($n > 20) $json(['ok' => false, 'msg' => 'Muitas tentativas. Espere um minuto.'], 429);
};

if ($acao === 'manifest') {
    if (!rec_ativo($r, 'app')) $json(['ok' => false], 404);
    header('Content-Type: application/manifest+json; charset=utf-8'); header('Cache-Control: public, max-age=3600');
    echo json_encode(rec_manifest($emp), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') $json(['ok' => false], 405);
$limite();
if ($acao === 'fidelidade') {
    if (!rec_ativo($r, 'fidelidade')) $json(['ok' => false, 'msg' => 'Cartão fidelidade desligado.']);
    $tel = rec_tel($_POST['tel'] ?? '');
    if (strlen($tel) < 10) $json(['ok' => false, 'msg' => 'Digite o seu WhatsApp com DDD.']);
    $meta = $r['fidelidade']['meta']; $selos = fid_selos($emp['id'], $tel, $r['fidelidade']);
    $premio = trim((string)$r['fidelidade']['premio']) ?: 'o seu prêmio';
    $atual = $selos % $meta === 0 && $selos > 0 ? $meta : $selos % $meta;
    $msg = $selos >= $meta ? "🎉 Você completou o cartão! Mostre esta tela para ganhar {$premio}." : ($selos ? "Você tem {$selos} de {$meta} selos. Faltam " . ($meta - $selos) . " para ganhar {$premio}." : "Ainda não encontramos selos neste número. Eles entram a cada " . fid_unidade(atendimento_modo_da_linha($emp) ?? '', false) . " concluído.");
    $json(['ok' => true, 'selos' => min($selos, $meta), 'meta' => $meta, 'msg' => $msg]);
}
if ($acao === 'aniversario') {
    if (!rec_ativo($r, 'aniversario')) $json(['ok' => false, 'msg' => 'Cadastro de aniversário desligado.']);
    if (trim((string)($_POST['site_url'] ?? '')) !== '') $json(['ok' => true, 'msg' => 'Prontinho!']);   // robô
    [$ok, $msg] = aniv_cadastrar($emp['id'], $_POST['nome'] ?? '', $_POST['tel'] ?? '', $_POST['dia'] ?? 0, $_POST['mes'] ?? 0);
    $json(['ok' => $ok, 'msg' => $msg]);
}
$json(['ok' => false, 'msg' => 'Ação desconhecida.'], 400);
