<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');

$empresaId = (int)empresa_id_atual();
if (!empresa_pode_usar_estudio($empresaId)) { header('Location: /empresa/meu_site.php'); exit; }

$empresa = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();
$site = $pdo->prepare("SELECT * FROM sites WHERE empresa_id=?");
$site->execute([$empresaId]); $site = $site->fetch();
if (!$empresa || !$site) { header('Location: /empresa/index.php'); exit; }

$apiUrl = '/empresa/ia_estudio_api.php';
$sidebar = __DIR__ . '/_sidebar.php';
$ehAdmin = false;
require __DIR__ . '/../includes/estudio_ui.php';
