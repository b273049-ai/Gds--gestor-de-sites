<?php
// Cookie de sessão endurecido: JS não consegue ler (HttpOnly), não vaza em
// requisições de outros sites (SameSite), e só trafega em HTTPS quando disponível.
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
require_once __DIR__ . '/../config/db.php';

// Exige que o usuário esteja logado. Se $role for passado, exige aquele papel específico.
function exigir_login($role = null) {
    if (empty($_SESSION['usuario_id'])) {
        header('Location: /login.php');
        exit;
    }
    if ($role !== null && $_SESSION['role'] !== $role) {
        header('Location: /login.php?erro=acesso_negado');
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

function e($str) {
    // Escapa saída para evitar XSS
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// ============================================================
// PROTEÇÃO CSRF — usado nos formulários mais sensíveis (login,
// troca de senha). Gera um token por sessão e valida no envio.
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

function csrf_valido() {
    return isset($_POST['csrf_token']) && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}
