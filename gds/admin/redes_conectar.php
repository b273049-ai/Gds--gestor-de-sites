<?php
// Conectar Instagram / TikTok pelo login oficial (OAuth). Este é o "endereço de retorno"
// que você cadastra no app da Meta e do TikTok: https://SEU-DOMINIO/admin/redes_conectar.php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');
require_once __DIR__ . '/../includes/marketing_ia.php';

$voltar = function ($tipo, $msg) {
    flash($tipo, $msg);
    header('Location: /admin/marketing.php#redes');
    exit;
};

// 1) Começar: vai para a tela de login da rede
if (isset($_GET['rede']) && !isset($_GET['code'])) {
    $rede = $_GET['rede'] === 'tiktok' ? 'tiktok' : 'instagram';
    if ($rede === 'instagram' && !soc_ig_app_ok()) $voltar('erro', 'Cole primeiro o ID e a chave secreta do app do Instagram.');
    if ($rede === 'tiktok' && !soc_tt_app_ok()) $voltar('erro', 'Cole primeiro a Client Key e a Client Secret do app do TikTok.');
    $state = $rede . '.' . bin2hex(random_bytes(16));
    $_SESSION['soc_state'] = $state;
    header('Location: ' . ($rede === 'tiktok' ? soc_tt_link_autorizar($state) : soc_ig_link_autorizar($state)));
    exit;
}

// 2) Voltou da rede
$state = (string)($_GET['state'] ?? '');
$esperado = (string)($_SESSION['soc_state'] ?? '');
unset($_SESSION['soc_state']);
if ($esperado === '' || !hash_equals($esperado, $state)) $voltar('erro', 'A conexão expirou ou veio de outro lugar. Clique em "Conectar" de novo.');
$rede = str_starts_with($state, 'tiktok.') ? 'tiktok' : 'instagram';
if (!empty($_GET['error'])) {
    $voltar('erro', ($rede === 'tiktok' ? 'O TikTok' : 'O Instagram') . ' não liberou: ' . mb_substr(strip_tags((string)($_GET['error_description'] ?? $_GET['error_reason'] ?? $_GET['error'])), 0, 160));
}
$code = preg_replace('/#_$/', '', (string)($_GET['code'] ?? ''));
if ($code === '') $voltar('erro', 'Não veio o código de acesso. Tente de novo.');
$r = $rede === 'tiktok' ? soc_tt_conectar($code) : soc_ig_conectar($code);
if (!$r['ok']) $voltar('erro', $r['erro']);
$voltar('sucesso', $rede === 'tiktok'
    ? 'TikTok conectado' . ($r['usuario'] ? ' (' . $r['usuario'] . ')' : '') . '! Os Reels e carrosséis do plano vão para o seu TikTok na hora certa.'
    : 'Instagram conectado (@' . $r['usuario'] . ')! Agora o plano posta sozinho e quem comentar a palavra-chave recebe a oferta no direct.');
