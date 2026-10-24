<?php
@set_time_limit(170); // duas chamadas de IA (planejamento + código) demoram mais que o padrão de hospedagem
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ia_helper.php';
exigir_login('admin');
header('Content-Type: application/json');

$empresaId = (int)($_POST['empresa_id'] ?? 0);
$briefing = trim($_POST['briefing'] ?? '');

if ($briefing === '') {
    echo json_encode(['ok' => false, 'erro' => 'Conte pelo menos um pouco sobre o negócio.']);
    exit;
}

$empresa = $pdo->prepare("SELECT e.*, seg.nome as segmento_nome FROM empresas e LEFT JOIN segmentos seg ON seg.id = e.segmento_id WHERE e.id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();
if (!$empresa) { echo json_encode(['ok' => false, 'erro' => 'Empresa não encontrada.']); exit; }

$site = $pdo->prepare("SELECT * FROM sites WHERE empresa_id=?");
$site->execute([$empresaId]); $site = $site->fetch();

$MODELO = 'gemini-3.5-flash'; // o 3.6 mudou as regras de parâmetros da API e estava rejeitando as chamadas

// Direção visual SORTEADA pelo sistema (não pela IA) — garante variedade real entre gerações
$direcoesVisuais = [
    "Brutalismo Digital: cru, alto contraste, tipografia grotesca ou monoespaçada, bordas grossas retas (sem arredondamento), linhas de grade expostas como elemento gráfico.",
    "Editorial Suíço: grid rigoroso, muitíssimo espaço em branco, uma única cor de destaque, sans-serif tipo Helvetica, assimetria controlada, parece página de revista impressa.",
    "Glassmorphism Tecnológico: painéis de vidro fosco (backdrop-filter blur), gradientes suaves atrás do vidro, cartões flutuantes com luz vindo do canto superior esquerdo.",
    "Luxo Escuro: fundo quase preto, linhas finas em dourado ou metálico como acento, tipografia serifada elegante, espaço negativo generoso.",
    "Retrofuturismo Anos 80: neon magenta e ciano sobre azul-marinho escuro, linhas de grade tipo horizonte synthwave, títulos com efeito cromado.",
    "Orgânico e Blob: formas irregulares tipo blob como fundo, paleta terrosa e quente, tipografia arredondada amigável, toques desenhados à mão via SVG.",
    "Maximalismo Colagem: elementos sobrepostos em camadas, tamanhos de fonte misturados com leves rotações, cores saturadas que colidem de propósito.",
    "Neobrutalismo Pop: cores chapadas e vibrantes, contornos pretos grossos, sombras duras deslocadas (não desfocadas).",
    "Monocromático Minimalista: uma única cor além de preto/branco, espaço em branco generoso, tipografia quieta e confiante.",
    "Editorial de Revista: citações grandes em destaque, letra capitular, serifada nos títulos combinada com sans limpa no corpo.",
    "Analógico e Quente: textura sutil de grão de filme (via CSS), tons sépia/terrosos quentes, tipografia serifada, sensação tátil.",
    "Grid Cinético: linhas de grade finas visíveis como elemento de design, tudo alinhado a uma grade percebida, números/rótulos como spec sheet.",
];
$direcaoEscolhida = $direcoesVisuais[random_int(0, count($direcoesVisuais) - 1)];

$marcadoresObrigatorios = ['{{CONTATO}}'];

// ============================================================
// ETAPA 1: A IA "estuda" a empresa e toma decisões CONCRETAS de
// design (fonte exata, paleta exata, conceito) antes de escrever
// qualquer código. Isso evita que ela decida tudo simultaneamente
// enquanto também escreve HTML/CSS/JS, o que gerava resultados
// medianos. Decisão primeiro, execução depois.
// ============================================================
$promptPlanejamento = <<<PROMPT
Você é um diretor de arte digital sênior. Baseado nos dados reais abaixo, tome DECISÕES CONCRETAS E FINAIS de
design pra este negócio específico — nada de teoria, decisões que um dev vai seguir ao pé da letra depois.

DADOS DO NEGÓCIO:
Nome: {$empresa['nome']}
Segmento: {$empresa['segmento_nome']}
O que foi contado: "{$briefing}"

DIREÇÃO VISUAL OBRIGATÓRIA (a base de tudo): "{$direcaoEscolhida}"

Responda ESTRITAMENTE em JSON válido, sem markdown, sem explicação, exatamente neste formato:
{"fonte_titulo":"nome exato de uma fonte do Google Fonts adequada à direção visual e ao negócio","fonte_corpo":"outra fonte do Google Fonts que combine com a de título","paleta":["#hex_fundo","#hex_superficie","#hex_texto","#hex_destaque_principal","#hex_destaque_secundario"],"conceito":"1-2 frases da metáfora visual específica DESTE negócio dentro da direção visual dada — não genérico","motivos_visuais":["elemento gráfico concreto 1","elemento gráfico concreto 2","elemento gráfico concreto 3"],"marcadores":["{{CONTATO}}","..."]}

Onde "marcadores" é a lista escolhida entre estas opções, incluindo APENAS as que fazem sentido pro negócio descrito
(nunca force todas): {{SOBRE}} {{SERVICOS}} {{EQUIPE}} {{GALERIA}} {{AGENDAMENTO}} {{AVALIACOES}} {{CONTATO}} {{MAPA}} {{CUPONS}} {{WHATSAPP_FLOAT}}
{{CONTATO}} é sempre obrigatório. {{AGENDAMENTO}} só entra se o negócio realmente marcar horário com clientes
(portfólio, vitrine de produtos, cafeteria sem pedido online etc. NÃO usam {{AGENDAMENTO}}).
A paleta deve ter contraste real entre fundo e texto (nunca duas cores parecidas), e a cor de destaque deve ser
vibrante o suficiente pra guiar o olhar.
PROMPT;

$planejamento = chamar_ia($promptPlanejamento, 800, 40, 0.9, $MODELO);

$decisao = null;
if ($planejamento['ok']) {
    $jsonTexto = trim($planejamento['texto']);
    $jsonTexto = preg_replace('/^```(?:json)?\s*/i', '', $jsonTexto);
    $jsonTexto = preg_replace('/```\s*$/', '', $jsonTexto);
    $decisao = json_decode(trim($jsonTexto), true);
}

// Se o planejamento falhar por qualquer motivo, segue com valores padrão em vez de travar tudo
if (!is_array($decisao) || empty($decisao['fonte_titulo'])) {
    $decisao = [
        'fonte_titulo' => 'Space Grotesk',
        'fonte_corpo' => 'Inter',
        'paleta' => [$site['cor_primaria'] ?: '#4f7cff', $site['cor_secundaria'] ?: '#16213e', '#0b0e1f', '#12162e', '#eef0fa'],
        'conceito' => $briefing,
        'motivos_visuais' => [],
        'marcadores' => ['{{SOBRE}}','{{SERVICOS}}','{{CONTATO}}','{{WHATSAPP_FLOAT}}'],
    ];
}
$marcadoresEscolhidos = array_unique(array_merge($decisao['marcadores'] ?? [], $marcadoresObrigatorios));
$paletaStr = implode(', ', $decisao['paleta'] ?? []);
$motivosStr = implode('; ', $decisao['motivos_visuais'] ?? []);
$marcadoresStr = implode(' ', $marcadoresEscolhidos);

// ============================================================
// ETAPA 2: agora só executa — as decisões já foram tomadas.
// ============================================================
$prompt = <<<PROMPT
Você é um desenvolvedor front-end sênior premiado (nível Awwwards). Construa o código seguindo EXATAMENTE as
decisões de design já tomadas abaixo — você não decide mais nada de estilo, só executa com excelência técnica.

NEGÓCIO: {$empresa['nome']} ({$empresa['segmento_nome']}). WhatsApp: {$empresa['whatsapp']}. Endereço: {$empresa['endereco']}.
CONTEXTO DO DONO: "{$briefing}"

DECISÕES DE DESIGN JÁ TOMADAS (siga à risca, não mude):
- Fonte de título: {$decisao['fonte_titulo']}
- Fonte de corpo: {$decisao['fonte_corpo']}
- Paleta de cores (nesta ordem: fundo, superfície, texto, destaque principal, destaque secundário): {$paletaStr}
- Conceito visual: {$decisao['conceito']}
- Motivos visuais a usar: {$motivosStr}
- Direção geral: {$direcaoEscolhida}

MARCADORES A INCLUIR (só estes, cada um sozinho em sua própria linha, em qualquer lugar do design que fizer sentido): {$marcadoresStr}
Eles serão substituídos por HTML real do banco de dados. O {{AGENDAMENTO}}, se usado, já vem com um CALENDÁRIO
MENSAL CLICÁVEL PRONTO E FUNCIONAL — não construa lógica de calendário, só estilize. Classes fixas que o conteúdo
injetado usa (crie estilos pra elas): .gds-servico-item, .gds-equipe-item (com <img> ou .gds-avatar-fallback),
.gds-galeria-item (<img>), .gds-avaliacao-item (com .gds-stars), .gds-cupom-item, .gds-mapa (iframe), .gds-form
(input/select/textarea/button filhos), .gds-msg-sucesso, .gds-msg-erro, .gds-whatsapp-float (já com position:fixed
inline), e do calendário: .gds-calendario, .gds-cal-header, .gds-cal-dia, .gds-cal-dia-selecionado,
.gds-cal-dia-passado, .gds-cal-dias-semana, .gds-cal-grid.

ANIMAÇÃO — exemplo de qualidade esperada (adapte, não copie literal), mostrando entrada escalonada com easing orgânico:
```css
.item { opacity:0; transform: translateY(24px); transition: opacity .6s cubic-bezier(.16,1,.3,1), transform .6s cubic-bezier(.16,1,.3,1); }
.item.visivel { opacity:1; transform: translateY(0); }
```
```js
document.querySelectorAll('.item').forEach((el, i) => { el.style.transitionDelay = (i * 80) + 'ms'; });
const obs = new IntersectionObserver((entradas) => {
  entradas.forEach((e) => { if (e.isIntersecting) e.target.classList.add('visivel'); });
}, { threshold: 0.15 });
document.querySelectorAll('.item').forEach((el) => obs.observe(el));
```
Use esse padrão (delay incremental, cubic-bezier, opacity+translateY) pra qualquer entrada de grupo de elementos.
Botões: hover com scale 1.02-1.04 e sombra crescendo; clique com scale 0.97. Envolva animações intensas em
@media (prefers-reduced-motion: reduce). Nada de exagero — sutil, não deve atrapalhar leitura.

REGRAS DE ESTRUTURA:
1. Responda APENAS o HTML completo, de <!DOCTYPE html> até </html>. Sem explicação, sem markdown.
2. NÃO use frameworks externos (React, Vue, Bootstrap, Tailwind CDN, jQuery, GSAP, AOS). HTML/CSS/JS puro.
3. Navegação por páginas, não scroll único: cada seção principal fica num <div data-page="chave">, todas com
   display:none exceto a primeira, e a nav do topo troca via JavaScript (sem framework).
4. Escreva você mesmo os textos com base no contexto do dono — só os marcadores ficam intactos.
5. MOBILE-FIRST OBRIGATÓRIO — a maioria dos visitantes reais é celular, isso não é opcional:
   - Inclua <meta name="viewport" content="width=device-width, initial-scale=1"> no <head>.
   - Nenhuma largura fixa em pixels que quebre abaixo de 400px — use %, rem, clamp(), grid/flex com wrap.
   - Textos de título usando clamp() (ex: font-size: clamp(28px, 8vw, 56px)) pra escalar sem estourar a tela.
   - Todo botão e link clicável com no mínimo 44x44px de área de toque.
   - Inputs de formulário com font-size mínimo de 16px (senão o iOS dá zoom automático sozinho, o que é ruim).
   - Nenhum elemento com overflow horizontal — teste mentalmente em 360px de largura.
   - A navegação do topo não pode quebrar feio em telas pequenas: ou vira menu hambúrguer, ou vira uma barra
     com scroll horizontal (overflow-x:auto, sem quebra de linha esquisita).
6. NUNCA: hero centralizado com gradiente roxo-azul genérico, fonte Inter/Poppins só por padrão (aqui já foi
   decidido: use as fontes indicadas acima), cards idênticos com sombra suave igual em tudo, botão com seta "→",
   grid de 3 ícones genéricos, emoji no lugar de ícone.
PROMPT;

$resultado = chamar_ia($prompt, 30000, 145, 1.1, $MODELO);

if (!$resultado['ok']) {
    echo json_encode(['ok' => false, 'erro' => $resultado['erro']]);
    exit;
}

$html = trim($resultado['texto']);
$html = preg_replace('/^```(?:html)?\s*/i', '', $html);
$html = preg_replace('/```\s*$/', '', $html);
$html = trim($html);

$valido = (stripos($html, '</html>') !== false);
$faltando = [];
foreach ($marcadoresObrigatorios as $m) {
    if (strpos($html, $m) === false) { $faltando[] = $m; }
}

if (!$valido || !empty($faltando)) {
    $detalhe = !$valido ? 'a resposta veio incompleta/cortada' : ('faltaram os marcadores: ' . implode(', ', $faltando));
    echo json_encode(['ok' => false, 'erro' => "A IA não gerou o site corretamente ($detalhe). Tente novamente — às vezes uma segunda tentativa resolve."]);
    exit;
}

$pdo->prepare("UPDATE sites SET html_gerado=?, ia_briefing=?, ia_modo_total=true WHERE empresa_id=?")
    ->execute([$html, $briefing, $empresaId]);

$pdo->prepare("INSERT INTO ia_logs (empresa_id, usuario_id, prompt, resposta) VALUES (?,?,?,?)")
    ->execute([$empresaId, usuario_logado(), 'Geração de site — direção: ' . $direcaoEscolhida . ' | conceito: ' . ($decisao['conceito'] ?? ''), 'HTML gerado com ' . strlen($html) . ' caracteres']);

echo json_encode(['ok' => true, 'direcao_visual' => $direcaoEscolhida, 'conceito' => $decisao['conceito'] ?? '']);
