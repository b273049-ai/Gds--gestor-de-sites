<?php
// Textos curtos com IA para o Editor de Conteúdo (sobre, SEO, descrição de serviço).
// Só GERA a sugestão — quem salva é o botão "Salvar" da tela (antes salvava direto no banco).
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder_json(['erro' => 'Método inválido.'], 405);

$empresaId = (int)($_POST['empresa_id'] ?? 0);
$acao = $_POST['acao'] ?? '';

$empresa = $pdo->prepare("
    SELECT e.*, seg.nome AS segmento_nome, s.diferenciais
    FROM empresas e
    LEFT JOIN segmentos seg ON seg.id = e.segmento_id
    LEFT JOIN sites s ON s.empresa_id = e.id
    WHERE e.id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();
if (!$empresa) responder_json(['erro' => 'Empresa não encontrada.'], 404);

$segmentoNome = $empresa['segmento_nome'] ?? 'negócios em geral';
// Prioriza o que está NA TELA agora (ainda não salvo) sobre o que já está no banco
$diferenciaisTela = trim($_POST['diferenciais_atual'] ?? '');
$diferenciais = $diferenciaisTela !== '' ? $diferenciaisTela : trim($empresa['diferenciais'] ?? '');

$sistema = "Você escreve textos para sites de pequenos negócios brasileiros. Responda sempre em português do Brasil, direto e prático, "
         . "sem clichês e sem texto genérico que serviria para qualquer concorrente. Nunca invente prêmios, números ou preços. "
         . "Responda apenas com o texto pedido, sem aspas e sem comentários.";
$base = "Negócio: '{$empresa['nome']}' (segmento: $segmentoNome)" . ($empresa['endereco'] ? ", em {$empresa['endereco']}" : '') . ". ";
$base .= $diferenciais !== ''
    ? "O que torna ESTE negócio diferente: \"$diferenciais\". Use isso de forma natural. "
    : "O dono ainda não descreveu diferenciais — escreva algo plausível e sóbrio. ";

switch ($acao) {
    case 'sobre':
        $prompt = $base . "Escreva um parágrafo de 'Sobre nós' com até 70 palavras.";
        break;
    case 'descricao_servico':
        $srv = $pdo->prepare("SELECT * FROM servicos WHERE id=? AND empresa_id=?");
        $srv->execute([(int)($_POST['servico_id'] ?? 0), $empresaId]); $srv = $srv->fetch();
        if (!$srv) responder_json(['erro' => 'Serviço não encontrado.']);
        $prompt = $base . "Escreva uma descrição curta e atraente (até 25 palavras) para o serviço '{$srv['nome']}'.";
        break;
    case 'seo':
        $prompt = $base . "Sugira um título de SEO (até 60 caracteres, com o nome do negócio e a cidade se houver) e uma meta descrição (até 155 caracteres). "
                . "Responda EXATAMENTE neste formato:\nTITULO: ...\nDESCRICAO: ...";
        break;
    default:
        responder_json(['erro' => 'Ação inválida.'], 400);
}

$resultado = chamar_ia($prompt, 600, 40, 0.8, null, ['sistema' => $sistema, 'esforco' => 'low']);
if (!$resultado['ok']) responder_json(['erro' => $resultado['erro']]);

$pdo->prepare("INSERT INTO ia_logs (empresa_id, usuario_id, prompt, resposta) VALUES (?,?,?,?)")
    ->execute([$empresaId, usuario_logado(), $prompt, $resultado['texto']]);

if ($acao === 'seo') {
    $t = null; $d = null;
    if (preg_match('/TITULO:\s*(.+)/iu', $resultado['texto'], $m1)) $t = mb_substr(trim($m1[1], " \"'*"), 0, 70);
    if (preg_match('/DESCRICAO:\s*(.+)/isu', $resultado['texto'], $m2)) $d = mb_substr(trim($m2[1], " \"'*\n"), 0, 160);
    responder_json(['texto' => $resultado['texto'], 'seo_titulo' => $t, 'seo_descricao' => $d]);
}
responder_json(['texto' => trim($resultado['texto'], " \"\n")]);
