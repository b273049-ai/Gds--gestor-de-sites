<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ia_helper.php';
exigir_login('empresa');
header('Content-Type: application/json');

$empresaId = empresa_id_atual();
$empresa = $pdo->prepare("SELECT nome FROM empresas WHERE id=?");
$empresa->execute([$empresaId]);
$empresaNome = $empresa->fetch()['nome'] ?? 'a empresa';

$prompt = "Você é um assistente de criação de sites para pequenos negócios, em uma plataforma SaaS moderna. Escreva um parágrafo curto (até 60 palavras) de 'Sobre nós' para o site da empresa '$empresaNome', em português do Brasil, tom profissional e acolhedor, com uma pegada moderna e tecnológica.";

$resultado = chamar_ia($prompt);

if (!$resultado['ok']) {
    http_response_code(400);
    echo json_encode(['erro' => $resultado['erro']]);
    exit;
}

$pdo->prepare("INSERT INTO ia_logs (empresa_id, usuario_id, prompt, resposta) VALUES (?,?,?,?)")
    ->execute([$empresaId, usuario_logado(), $prompt, $resultado['texto']]);

echo json_encode(['texto' => $resultado['texto']]);
