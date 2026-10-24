<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$empresaId = (int)($_POST['empresa_id'] ?? 0);
$existe = $pdo->prepare("SELECT 1 FROM empresas WHERE id=?");
$existe->execute([$empresaId]);
if (!$existe->fetch()) responder_json(['ok' => false, 'erro' => 'Empresa não encontrada.'], 404);

require __DIR__ . '/../includes/estudio_api.php';
