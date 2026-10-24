<?php
// O criador antigo virou o Estúdio IA (conversa + versões + pré-visualização por dispositivo)
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');
header('Location: ia_estudio.php?empresa_id=' . (int)($_GET['empresa_id'] ?? 0));
exit;
