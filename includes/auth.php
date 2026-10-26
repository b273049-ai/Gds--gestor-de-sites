<?php
// Cookie de sessão endurecido: JS não consegue ler (HttpOnly), não vaza em
// requisições de outros sites (SameSite), e só trafega em HTTPS quando disponível.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
require_once __DIR__ . '/../config/db.php';

// Parâmetros GET que alteram dados (links tipo ?excluir=3). Todos exigem token CSRF.
const GDS_ACOES_GET = ['excluir', 'ativar', 'desativar', 'subir', 'descer', 'ocultar', 'mostrar', 'aprovar', 'reprovar', 'restaurar'];

// Exige que o usuário esteja logado. Se $role for passado, exige aquele papel específico.
// Também barra automaticamente qualquer POST (ou link de ação) sem token CSRF válido —
// assim nenhuma tela do painel fica vulnerável por esquecimento.
function exigir_login($role = null) {
    if (empty($_SESSION['usuario_id'])) {
        if (eh_requisicao_ajax()) { responder_json(['ok' => false, 'erro' => 'Sua sessão expirou. Entre novamente.'], 401); }
        header('Location: /login.php');
        exit;
    }
    if ($role !== null && ($_SESSION['role'] ?? null) !== $role) {
        if (eh_requisicao_ajax()) { responder_json(['ok' => false, 'erro' => 'Acesso negado.'], 403); }
        header('Location: /login.php?erro=acesso_negado');
        exit;
    }

    gds_garantir_schema();

    $ehPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    $temAcaoGet = !$ehPost && count(array_intersect(array_keys($_GET), GDS_ACOES_GET)) > 0;
    if (($ehPost || $temAcaoGet) && !csrf_valido()) {
        if (eh_requisicao_ajax()) { responder_json(['ok' => false, 'erro' => 'Sessão expirada. Recarregue a página e tente de novo.'], 419); }
        flash('erro', 'Sessão expirada ou link inválido. Tente novamente.');
        header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? '/', '?') . (isset($_GET['empresa_id']) ? '?empresa_id=' . (int)$_GET['empresa_id'] : ''));
        exit;
    }
}

function usuario_logado() {
    return $_SESSION['usuario_id'] ?? null;
}

function is_admin() {
    return ($_SESSION['role'] ?? null) === 'admin';
}

function empresa_id_atual() {
    // Regra de ouro: TODA query do painel empresa deve filtrar por este id.
    // Isso garante que uma empresa NUNCA veja dados de outra.
    return $_SESSION['empresa_id'] ?? null;
}

if (!function_exists('e')) {
    function e($str) {
        // Escapa saída para evitar XSS
        return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// ============================================================
// PROTEÇÃO CSRF — um token por sessão, validado em todo POST do
// painel (campo csrf_token, cabeçalho X-CSRF-Token ou ?csrf= em links de ação).
// ============================================================
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_campo() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

// Para links de ação: href="?excluir=3" + csrf_qs() (vira &csrf=TOKEN)
function csrf_qs() {
    return '&csrf=' . csrf_token();
}

function csrf_valido() {
    if (empty($_SESSION['csrf_token'])) return false;
    $enviado = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_GET['csrf'] ?? ''));
    return is_string($enviado) && $enviado !== '' && hash_equals($_SESSION['csrf_token'], $enviado);
}

function eh_requisicao_ajax() {
    return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function responder_json($dados, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// MENSAGENS "FLASH" (aparecem uma vez depois de um redirect)
// ============================================================
function flash($tipo, $mensagem) {
    $_SESSION['flash'][] = ['tipo' => $tipo, 'msg' => $mensagem];
}

function flash_render() {
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $html .= alerta($f['tipo'], $f['msg']);
    }
    unset($_SESSION['flash']);
    return $html;
}

function alerta($tipo, $mensagem) {
    $classes = ['sucesso' => 'alert-sucesso', 'erro' => 'alert-erro', 'info' => 'alert-info', 'aviso' => 'alert-aviso'];
    return '<div class="alert ' . ($classes[$tipo] ?? 'alert-info') . '">' . e($mensagem) . '</div>';
}

// ============================================================
// UTILITÁRIOS
// ============================================================

// "Clínica São José" -> "clinica-sao-jose" (sem acento, sem traço sobrando nas pontas)
function slugify($texto) {
    $texto = trim((string)$texto);
    if (function_exists('iconv')) {
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if ($conv !== false) $texto = $conv;
    }
    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    $texto = trim($texto, '-');
    return $texto !== '' ? $texto : 'empresa';
}

// Garante que o slug ainda não existe na tabela (acrescenta -2, -3...)
function slug_unico($tabela, $base, $ignorarId = null) {
    global $pdo;
    // Nomes de pastas reais da plataforma não podem virar endereço de site (nunca abririam)
    $reservados = ['admin', 'empresa', 'site', 'assets', 'includes', 'config', 'sql', 'login', 'logout', 'install', 'index'];
    $slug = in_array($base, $reservados, true) ? $base . '-site' : $base;
    $base = $slug; $n = 2;
    while (true) {
        $sql = "SELECT 1 FROM $tabela WHERE slug=?" . ($ignorarId ? " AND id<>?" : "");
        $st = $pdo->prepare($sql);
        $st->execute($ignorarId ? [$slug, $ignorarId] : [$slug]);
        if (!$st->fetch()) return $slug;
        $slug = $base . '-' . $n++;
    }
}

function so_digitos($s) {
    return preg_replace('/\D/', '', (string)$s);
}

function valor_decimal($s, $padrao = 0) {
    $s = trim((string)$s);
    if ($s === '') return $padrao;
    // Aceita "1.234,56" e "1234.56"
    if (str_contains($s, ',')) { $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s); }
    return is_numeric($s) ? (float)$s : $padrao;
}

function url_valida_ou_vazia($url) {
    $url = trim((string)$url);
    if ($url === '') return '';
    return preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}

function cor_hex_valida($cor, $padrao) {
    return preg_match('/^#[0-9a-fA-F]{6}$/', (string)$cor) ? $cor : $padrao;
}

// A empresa logada tem acesso ao Estúdio IA? (plano com IA + liberado pelo admin)
function empresa_pode_usar_estudio($empresaId) {
    global $pdo;
    if (get_config('ia_estudio_empresas', '0') !== '1') return false;
    try {
        $st = $pdo->prepare("SELECT p.permite_ia FROM empresas e LEFT JOIN planos p ON p.id = e.plano_id WHERE e.id=?");
        $st->execute([$empresaId]);
        $row = $st->fetch();
        return $row && pg_bool($row['permite_ia']);
    } catch (PDOException $e) {
        return false;
    }
}

require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/ia_helper.php';
require_once __DIR__ . '/migracoes.php';
require_once __DIR__ . '/atendimento.php';

// Link wa.me a partir de um telefone brasileiro (acrescenta o 55 quando vier só DDD + número)
function link_whatsapp($telefone, $mensagem = '') {
    $d = so_digitos($telefone);
    if ($d === '') return '';
    if (strlen($d) <= 11) $d = '55' . $d;
    return 'https://wa.me/' . $d . ($mensagem !== '' ? '?text=' . rawurlencode($mensagem) : '');
}
