<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
header('Content-Type: application/json');
echo json_encode(['ok' => false, 'erro' => 'A criação de site agora é feita pelo administrador da plataforma.']);
