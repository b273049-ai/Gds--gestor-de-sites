<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ia_helper.php';
exigir_login("admin");
header('Content-Type: application/json');

$empresaId = (int)($_GET["empresa_id"] ?? 0);
$acao = $_GET['acao'] ?? '';

$empresa = $pdo->prepare("
    SELECT e.*, seg.nome as segmento_nome, s.diferenciais
    FROM empresas e
    LEFT JOIN segmentos seg ON seg.id = e.segmento_id
    LEFT JOIN sites s ON s.empresa_id = e.id
    WHERE e.id=?
");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();
$segmentoNome = $empresa['segmento_nome'] ?? 'negócios em geral';
// Prioriza o valor que está NA TELA agora (ainda não salvo) sobre o que já está no banco,
// pra evitar gerar texto genérico só porque o usuário ainda não clicou em Salvar.
$diferenciaisTela = trim($_GET['diferenciais_atual'] ?? $_POST['diferenciais_atual'] ?? '');
$diferenciais = $diferenciaisTela !== '' ? $diferenciaisTela : trim($empresa['diferenciais'] ?? '');

// Instrução base: sempre pensa em estética moderna/tecnológica para o site
$base = "Você é um assistente de criação de sites para pequenos negócios dentro de uma plataforma SaaS moderna (GDS - Gestor de Sites). "
      . "Responda sempre em português do Brasil. Suas sugestões devem sempre valorizar uma estética digital, moderna e tecnológica "
      . "(evite clichês piegas ou floreios antiquados). Seja direto e prático. "
      . "MUITO IMPORTANTE: evite texto genérico que serviria pra qualquer empresa do mesmo segmento — "
      . "use os detalhes específicos abaixo para deixar o resultado único desse negócio. ";

if ($diferenciais !== '') {
    $base .= "O que torna ESTE negócio específico e diferente dos concorrentes: \"$diferenciais\". Incorpore isso de forma natural sempre que fizer sentido. ";
} else {
    $base .= "O dono ainda não descreveu diferenciais específicos (campo vazio em Meu Site) — nesse caso, gere algo plausível mas avise no fim, entre colchetes, que o resultado ficará mais específico se ele preencher o campo 'Diferenciais'. ";
}

$servicoId = null;

switch ($acao) {
    case 'sobre':
        $prompt = $base . "Escreva um parágrafo curto (até 60 palavras) de 'Sobre nós' para o site da empresa '{$empresa['nome']}', do segmento $segmentoNome.";
        break;

    case 'descricao_servico':
        $servicoId = (int)($_GET['servico_id'] ?? 0);
        $srv = $pdo->prepare("SELECT * FROM servicos WHERE id=? AND empresa_id=?");
        $srv->execute([$servicoId, $empresaId]); $srv = $srv->fetch();
        if (!$srv) { echo json_encode(['erro' => 'Serviço não encontrado.']); exit; }
        $prompt = $base . "Escreva uma descrição curta e atraente (até 30 palavras) para o serviço '{$srv['nome']}', oferecido por um negócio do segmento $segmentoNome.";
        break;

    case 'seo':
        $prompt = $base . "Sugira um título de SEO (até 60 caracteres) e uma meta descrição (até 155 caracteres) para o site da empresa "
                 . "'{$empresa['nome']}', segmento $segmentoNome. Responda EXATAMENTE neste formato, sem mais nada:\nTITULO: (o título aqui)\nDESCRICAO: (a descrição aqui)";
        break;

    case 'tema':
        $prompt = $base . "Sugira, em até 80 palavras, uma direção visual moderna e tecnológica (paleta de cores, tipografia, estilo geral) "
                 . "para o site de um negócio do segmento $segmentoNome, pensando em transmitir inovação e confiança.";
        break;

    case 'pergunta':
        $pergunta = trim($_GET['pergunta'] ?? '');
        if ($pergunta === '') { echo json_encode(['erro' => 'Digite uma pergunta.']); exit; }
        $prompt = $base . "O dono do negócio '{$empresa['nome']}' (segmento $segmentoNome) perguntou: \"$pergunta\". "
                 . "Responda de forma útil, prática e objetiva, sempre pensando em como isso melhora o site e a presença digital dele na plataforma.";
        break;

    default:
        echo json_encode(['erro' => 'Ação inválida.']); exit;
}

$resultado = chamar_ia($prompt, 800);

if (!$resultado['ok']) {
    echo json_encode(['erro' => $resultado['erro']]);
    exit;
}

$pdo->prepare("INSERT INTO ia_logs (empresa_id, usuario_id, prompt, resposta) VALUES (?,?,?,?)")
    ->execute([$empresaId, usuario_logado(), $prompt, $resultado['texto']]);

// Ações que já salvam o resultado direto no lugar certo
if ($acao === 'descricao_servico' && $servicoId) {
    $pdo->prepare("UPDATE servicos SET descricao=? WHERE id=? AND empresa_id=?")->execute([$resultado['texto'], $servicoId, $empresaId]);
}
if ($acao === 'sobre') {
    $pdo->prepare("UPDATE sites SET sobre=? WHERE empresa_id=?")->execute([$resultado['texto'], $empresaId]);
}
if ($acao === 'seo') {
    $seoTitulo = null; $seoDescricao = null;
    if (preg_match('/TITULO:\s*(.+)/i', $resultado['texto'], $m1)) $seoTitulo = trim($m1[1]);
    if (preg_match('/DESCRICAO:\s*(.+)/is', $resultado['texto'], $m2)) $seoDescricao = trim($m2[1]);
    if ($seoTitulo && $seoDescricao) {
        $pdo->prepare("UPDATE sites SET seo_titulo=?, seo_descricao=? WHERE empresa_id=?")->execute([$seoTitulo, $seoDescricao, $empresaId]);
    }
    echo json_encode(['texto' => $resultado['texto'], 'seo_titulo' => $seoTitulo, 'seo_descricao' => $seoDescricao]);
    exit;
}

echo json_encode(['texto' => $resultado['texto']]);
