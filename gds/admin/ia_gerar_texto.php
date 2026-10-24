<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ia_helper.php';
exigir_login('admin');
header('Content-Type: application/json');

$empresaId = (int)($_GET['empresa_id'] ?? 0);

$empresa = $pdo->prepare("
    SELECT e.nome, seg.nome as segmento_nome, s.diferenciais
    FROM empresas e
    LEFT JOIN segmentos seg ON seg.id = e.segmento_id
    LEFT JOIN sites s ON s.empresa_id = e.id
    WHERE e.id=?
");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();
$empresaNome = $empresa['nome'] ?? 'a empresa';
$segmentoNome = $empresa['segmento_nome'] ?? 'negócios em geral';

$diferenciaisTela = trim($_GET['diferenciais_atual'] ?? '');
$diferenciais = $diferenciaisTela !== '' ? $diferenciaisTela : trim($empresa['diferenciais'] ?? '');

$prompt = "Você é um assistente de criação de sites para pequenos negócios, em uma plataforma SaaS moderna. "
        . "Escreva um parágrafo curto (até 60 palavras) de 'Sobre nós' para o site da empresa '$empresaNome', "
        . "do segmento $segmentoNome, em português do Brasil, tom profissional e acolhedor, com uma pegada moderna e tecnológica. "
        . "Evite texto genérico que serviria pra qualquer empresa do segmento.";

if ($diferenciais !== '') {
    $prompt .= " O que torna ESTE negócio específico e diferente dos concorrentes: \"$diferenciais\". Incorpore isso de forma natural.";
}

$resultado = chamar_ia($prompt);

if (!$resultado['ok']) {
    http_response_code(400);
    echo json_encode(['erro' => $resultado['erro']]);
    exit;
}

$pdo->prepare("INSERT INTO ia_logs (empresa_id, usuario_id, prompt, resposta) VALUES (?,?,?,?)")
    ->execute([$empresaId, usuario_logado(), $prompt, $resultado['texto']]);

echo json_encode(['texto' => $resultado['texto']]);
