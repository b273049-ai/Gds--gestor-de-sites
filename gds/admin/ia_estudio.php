<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$empresaId = (int)($_GET['empresa_id'] ?? 0);
$empresa = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();
if (!$empresa) { header('Location: empresas.php'); exit; }

$site = $pdo->prepare("SELECT * FROM sites WHERE empresa_id=?");
$site->execute([$empresaId]); $site = $site->fetch();
if (!$site) {
    $pdo->prepare("INSERT INTO sites (empresa_id, titulo, publicado) VALUES (?, ?, false)")->execute([$empresaId, $empresa['nome']]);
    header("Location: ia_estudio.php?empresa_id=$empresaId"); exit;
}

$apiUrl = '/admin/ia_estudio_api.php';
$sidebar = __DIR__ . '/_sidebar.php';
$ehAdmin = true;
require __DIR__ . '/../includes/estudio_ui.php';
