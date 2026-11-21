<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');
$empresaId = (int)($_GET['empresa_id'] ?? 0);
header('Location: ' . ($empresaId ? "site_hub.php?empresa_id=$empresaId" : 'empresas.php'));
exit;
