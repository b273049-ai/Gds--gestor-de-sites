<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');

$empresaId = (int)empresa_id_atual();
if (!empresa_pode_usar_estudio($empresaId)) {
    responder_json(['ok' => false, 'erro' => 'O Estúdio IA não está liberado no seu plano. Fale com o administrador da plataforma.'], 403);
}

require __DIR__ . '/../includes/estudio_api.php';
