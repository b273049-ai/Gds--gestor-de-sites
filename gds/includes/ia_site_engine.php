<?php
// ============================================================
// MOTOR DO "ESTÚDIO IA" — cria e edita o site inteiro por conversa
// (no estilo Lovable): a pessoa descreve o negócio, a IA planeja o
// design, escreve o HTML/CSS/JS e depois aceita pedidos de ajuste
// ("deixa o topo mais escuro", "adiciona uma seção de perguntas").
// Cada resultado vira uma VERSÃO que pode ser restaurada.
//
// As partes que precisam ser reais (agendamento, serviços, contato...)
// ficam como marcadores {{...}} e são trocadas por HTML de verdade na
// hora de exibir (ver includes/site_fragmentos.php).
// ============================================================

require_once __DIR__ . '/ia_helper.php';

const IA_MARCADORES = ['{{SOBRE}}', '{{SERVICOS}}', '{{EQUIPE}}', '{{GALERIA}}', '{{AGENDAMENTO}}', '{{AVALIACOES}}', '{{CONTATO}}', '{{MAPA}}', '{{CUPONS}}', '{{WHATSAPP_FLOAT}}'];
const IA_MARCADORES_OBRIGATORIOS = ['{{CONTATO}}'];

function ia_direcoes_visuais() {
    return [
        'brutalismo'   => ['Brutalismo Digital', 'cru, alto contraste, tipografia grotesca ou monoespaçada, bordas grossas retas (sem arredondamento), linhas de grade expostas como elemento gráfico.'],
        'suico'        => ['Editorial Suíço', 'grid rigoroso, muito espaço em branco, uma única cor de destaque, sans-serif neo-grotesca, assimetria controlada, parece página de revista impressa.'],
        'glass'        => ['Glassmorphism Tecnológico', 'painéis de vidro fosco (backdrop-filter blur), gradientes suaves atrás do vidro, cartões flutuantes com luz vindo do canto superior.'],
        'luxo'         => ['Luxo Escuro', 'fundo quase preto, linhas finas em dourado ou metálico como acento, tipografia serifada elegante, espaço negativo generoso.'],
        'synthwave'    => ['Retrofuturismo Anos 80', 'neon magenta e ciano sobre azul-marinho escuro, grade de horizonte synthwave, títulos com efeito cromado.'],
        'organico'     => ['Orgânico e Acolhedor', 'formas irregulares tipo blob no fundo, paleta terrosa e quente, tipografia arredondada amigável, toques desenhados à mão via SVG.'],
        'colagem'      => ['Maximalismo Colagem', 'elementos sobrepostos em camadas, tamanhos de fonte misturados com leves rotações, cores saturadas que colidem de propósito.'],
        'neobrutal'    => ['Neobrutalismo Pop', 'cores chapadas e vibrantes, contornos pretos grossos, sombras duras deslocadas (não desfocadas).'],
        'minimal'      => ['Monocromático Minimalista', 'uma única cor além de preto/branco, espaço em branco generoso, tipografia quieta e confiante.'],
        'revista'      => ['Editorial de Revista', 'citações grandes em destaque, letra capitular, serifada nos títulos combinada com sans limpa no corpo.'],
        'analogico'    => ['Analógico e Quente', 'textura sutil de grão de filme (via CSS), tons sépia/terrosos, tipografia serifada, sensação tátil.'],
        'grid'         => ['Grid Cinético', 'linhas de grade finas visíveis como elemento de design, tudo alinhado a uma grade, números/rótulos estilo ficha técnica.'],
        'saas'         => ['SaaS Moderno Claro', 'fundo claro com muito respiro, gradiente sutil no topo, cartões com bordas finas, tipografia sans geométrica, um destaque vibrante — estilo das startups atuais.'],
        'clinico'      => ['Clean Clínico', 'branco e tons frios suaves, cantos arredondados generosos, ícones lineares em SVG, sensação de confiança, higiene e calma.'],
    ];
}

// ------------------------------------------------------------
// Coleta TUDO que existe de real sobre o negócio pra IA não inventar
// ------------------------------------------------------------
function ia_contexto_empresa($empresaId) {
    global $pdo;
    $st = $pdo->prepare("
        SELECT e.*, seg.nome AS segmento_nome, s.titulo, s.subtitulo, s.sobre, s.diferenciais, s.objetivo,
               s.cor_primaria, s.cor_secundaria, s.banner_imagem, s.galeria_imagens, s.ia_briefing
        FROM empresas e
        LEFT JOIN segmentos seg ON seg.id = e.segmento_id
        LEFT JOIN sites s ON s.empresa_id = e.id
        WHERE e.id = ?");
    $st->execute([$empresaId]);
    $emp = $st->fetch();
    if (!$emp) return null;

    $q = function ($sql) use ($pdo, $empresaId) {
        $s = $pdo->prepare($sql); $s->execute([$empresaId]); return $s->fetchAll();
    };
    $emp['servicos'] = $q("SELECT nome, descricao, preco, duracao_minutos FROM servicos WHERE empresa_id=? AND ativo=true ORDER BY nome LIMIT 30");
    $emp['profissionais'] = $q("SELECT nome, especialidade, foto FROM profissionais WHERE empresa_id=? AND ativo=true ORDER BY nome LIMIT 20");
    $emp['cupons'] = $q("SELECT codigo, desconto_percentual FROM cupons WHERE empresa_id=? AND ativo=true AND (validade IS NULL OR validade >= CURRENT_DATE)");
    $aval = $q("SELECT COUNT(*) AS total, COALESCE(AVG(nota),0) AS media FROM avaliacoes WHERE empresa_id=?");
    $emp['avaliacoes_total'] = (int)($aval[0]['total'] ?? 0);
    $emp['avaliacoes_media'] = round((float)($aval[0]['media'] ?? 0), 1);
    $emp['galeria'] = array_values(array_filter(array_map('trim', explode(',', $emp['galeria_imagens'] ?? ''))));
    return $emp;
}

function ia_contexto_texto($ctx) {
    $l = [];
    $l[] = "Nome: {$ctx['nome']}";
    $l[] = 'Segmento: ' . ($ctx['segmento_nome'] ?: 'não informado');
    if ($ctx['endereco']) $l[] = "Endereço: {$ctx['endereco']}";
    if ($ctx['whatsapp']) $l[] = "WhatsApp: {$ctx['whatsapp']}";
    if ($ctx['telefone']) $l[] = "Telefone: {$ctx['telefone']}";
    if ($ctx['email']) $l[] = "E-mail: {$ctx['email']}";
    if (!empty($ctx['horario_abertura']) && !empty($ctx['horario_fechamento'])) {
        $l[] = 'Horário de atendimento: ' . substr($ctx['horario_abertura'], 0, 5) . ' às ' . substr($ctx['horario_fechamento'], 0, 5);
    }
    if ($ctx['diferenciais']) $l[] = "Diferenciais (o que torna o negócio único): {$ctx['diferenciais']}";
    if ($ctx['objetivo']) $l[] = "Objetivo do site: {$ctx['objetivo']}";
    if ($ctx['sobre']) $l[] = "Texto 'sobre' já cadastrado: {$ctx['sobre']}";
    if ($ctx['servicos']) {
        $l[] = 'Serviços reais (com preço):';
        foreach ($ctx['servicos'] as $s) {
            $l[] = '  - ' . $s['nome'] . ' — R$ ' . number_format((float)$s['preco'], 2, ',', '.')
                 . ($s['duracao_minutos'] ? " ({$s['duracao_minutos']} min)" : '')
                 . ($s['descricao'] ? ": {$s['descricao']}" : '');
        }
    }
    if ($ctx['profissionais']) {
        $l[] = 'Equipe: ' . implode('; ', array_map(fn($p) => $p['nome'] . ($p['especialidade'] ? " ({$p['especialidade']})" : ''), $ctx['profissionais']));
    }
    if ($ctx['avaliacoes_total'] > 0) $l[] = "Avaliações de clientes: {$ctx['avaliacoes_total']} (média {$ctx['avaliacoes_media']} de 5)";
    if ($ctx['cupons']) $l[] = 'Cupons ativos: ' . implode(', ', array_map(fn($c) => $c['codigo'] . ' (' . (float)$c['desconto_percentual'] . '%)', $ctx['cupons']));

    $imagens = [];
    if ($ctx['logo']) $imagens[] = "logo: {$ctx['logo']}";
    if ($ctx['banner_imagem']) $imagens[] = "foto de destaque/banner: {$ctx['banner_imagem']}";
    foreach (array_slice($ctx['galeria'], 0, 8) as $i => $g) $imagens[] = 'foto ' . ($i + 1) . ": $g";
    $l[] = $imagens
        ? "Imagens REAIS do negócio (use estas URLs exatas onde fizer sentido):\n  - " . implode("\n  - ", $imagens)
        : 'Imagens: o negócio ainda não tem fotos cadastradas — crie a identidade visual com CSS, gradientes, formas e SVG inline. NÃO use URLs de imagens externas inventadas (ficariam quebradas).';
    return implode("\n", $l);
}

// ------------------------------------------------------------
// ETAPA 1 — decisões de design (antes de escrever qualquer código)
// ------------------------------------------------------------
function ia_planejar_site($ctx, $briefing, $direcaoChave = null, $preferencias = '') {
    $direcoes = ia_direcoes_visuais();
    if (!$direcaoChave || !isset($direcoes[$direcaoChave])) {
        $chaves = array_keys($direcoes);
        $direcaoChave = $chaves[random_int(0, count($chaves) - 1)];
    }
    [$direcaoNome, $direcaoDesc] = $direcoes[$direcaoChave];
    $dados = ia_contexto_texto($ctx);
    $pref = trim($preferencias) !== '' ? "\nPREFERÊNCIAS EXPLÍCITAS DO DONO (têm prioridade): \"$preferencias\"" : '';

    $prompt = <<<PROMPT
Você é um diretor de arte digital sênior. Tome DECISÕES CONCRETAS E FINAIS de design pra este negócio específico —
nada de teoria: decisões que um desenvolvedor vai seguir ao pé da letra.

DADOS REAIS DO NEGÓCIO:
$dados

O QUE O DONO CONTOU: "$briefing"$pref

DIREÇÃO VISUAL BASE: $direcaoNome — $direcaoDesc

Responda APENAS com JSON válido (sem markdown), exatamente neste formato:
{"fonte_titulo":"fonte do Google Fonts","fonte_corpo":"outra fonte do Google Fonts que combine","paleta":{"fundo":"#hex","superficie":"#hex","texto":"#hex","destaque":"#hex","destaque_2":"#hex"},"conceito":"1-2 frases com a metáfora visual específica DESTE negócio","motivos_visuais":["elemento gráfico concreto 1","elemento 2","elemento 3"],"headline":"título principal do site, curto e marcante, em português","subheadline":"frase de apoio","cta":"texto do botão principal","paginas":[{"chave":"inicio","titulo":"Início","conteudo":"o que vai nessa página"}],"marcadores":["{{CONTATO}}"]}

Regras:
- "paginas": de 3 a 6 páginas. A primeira é sempre "inicio". Chaves em minúsculo sem acento.
- "marcadores": escolha entre {{SOBRE}} {{SERVICOS}} {{EQUIPE}} {{GALERIA}} {{AGENDAMENTO}} {{AVALIACOES}} {{CONTATO}} {{MAPA}} {{CUPONS}} {{WHATSAPP_FLOAT}},
  só os que fazem sentido. {{CONTATO}} é obrigatório. {{AGENDAMENTO}} só se o negócio marca horário com clientes.
  {{EQUIPE}} só se houver equipe cadastrada; {{GALERIA}} só se houver fotos; {{CUPONS}} só se houver cupons; {{MAPA}} só se houver endereço.
- A paleta precisa de contraste real entre fundo e texto (WCAG AA), e o destaque deve guiar o olhar.
PROMPT;

    $r = chamar_ia_json($prompt, 1500, 60, 0.9, ['esforco' => 'low']);
    $plano = $r['ok'] ? $r['dados'] : null;

    // Se o planejamento falhar, segue com um plano padrão em vez de travar tudo
    if (!is_array($plano) || empty($plano['fonte_titulo'])) {
        $plano = [
            'fonte_titulo' => 'Space Grotesk', 'fonte_corpo' => 'Inter',
            'paleta' => ['fundo' => '#0b0e1f', 'superficie' => '#12162e', 'texto' => '#eef0fa',
                         'destaque' => $ctx['cor_primaria'] ?: '#4f7cff', 'destaque_2' => $ctx['cor_secundaria'] ?: '#16213e'],
            'conceito' => $briefing, 'motivos_visuais' => [],
            'headline' => $ctx['titulo'] ?: $ctx['nome'], 'subheadline' => $ctx['subtitulo'] ?: '', 'cta' => 'Fale com a gente',
            'paginas' => [['chave' => 'inicio', 'titulo' => 'Início', 'conteudo' => 'apresentação'], ['chave' => 'servicos', 'titulo' => 'Serviços', 'conteudo' => 'serviços'], ['chave' => 'contato', 'titulo' => 'Contato', 'conteudo' => 'contato']],
            'marcadores' => ['{{SOBRE}}', '{{SERVICOS}}', '{{CONTATO}}', '{{WHATSAPP_FLOAT}}'],
        ];
    }
    $plano['marcadores'] = array_values(array_intersect(IA_MARCADORES, array_unique(array_merge($plano['marcadores'] ?? [], IA_MARCADORES_OBRIGATORIOS))));
    $plano['direcao_chave'] = $direcaoChave;
    $plano['direcao_nome'] = $direcaoNome;
    $plano['direcao_desc'] = $direcaoDesc;
    return $plano;
}

// ------------------------------------------------------------
// Regras técnicas comuns à geração e à edição (o "contrato")
// ------------------------------------------------------------
function ia_regras_tecnicas() {
    return <<<REGRAS
CONTRATO TÉCNICO (obrigatório):
1. Responda APENAS o documento HTML completo, de <!DOCTYPE html> até </html>. Sem explicação, sem markdown.
2. HTML/CSS/JS puro, tudo num arquivo só. Proibido: React, Vue, Bootstrap, Tailwind, jQuery, GSAP, AOS ou qualquer script externo.
   Fontes: pode usar <link> do Google Fonts.
3. <head> com <meta charset="UTF-8">, <meta name="viewport" content="width=device-width, initial-scale=1">, <title> e
   <meta name="description"> escritos pra SEO local do negócio, e <html lang="pt-BR">.
4. Navegação por páginas (não scroll único): cada página é um <section data-page="chave">; só a primeira visível.
   Os links do menu têm data-page-link="chave". Defina no JS a função global window.gdsMostrarPagina(chave) que troca a
   página visível, marca o link ativo e rola pro topo. Ao carregar, abra a página do #hash da URL se existir.
5. MARCADORES: cada marcador {{...}} fica sozinho em sua própria linha, dentro de um container seu, e NUNCA dentro de
   atributo, <a>, <p>, <button> ou <form>. Eles viram HTML real do banco. O {{AGENDAMENTO}} já vem com um calendário
   clicável completo e funcional — não construa calendário nem formulário seu, só estilize.
   Classes do conteúdo injetado (crie estilos bonitos para todas): .gds-servico-item (h3, p, .gds-preco, .gds-duracao),
   .gds-equipe-item (img ou .gds-avatar-fallback, h4, p), .gds-galeria-item (img), .gds-avaliacao-item (strong, .gds-stars, p),
   .gds-cupom-item, .gds-mapa (iframe), .gds-form (label, input, select, textarea, button), .gds-msg-sucesso, .gds-msg-erro,
   .gds-confirmacao, .gds-btn-secundario, .gds-whatsapp-float (botão flutuante fixo), e do calendário: .gds-calendario,
   .gds-cal-header, .gds-cal-dias-semana, .gds-cal-grid, .gds-cal-dia, .gds-cal-dia-selecionado, .gds-cal-dia-passado,
   .gds-horarios, .gds-horario, .gds-horario-selecionado, .gds-horario-ocupado.
   Não escreva você mesmo listas de serviços, equipe, avaliações ou formulários — isso vem dos marcadores.
6. MOBILE-FIRST: sem largura fixa que quebre abaixo de 400px; títulos com clamp(); toque mínimo 44x44px; inputs com
   font-size >= 16px; nada de rolagem horizontal em 360px; menu vira hambúrguer acessível no celular.
7. Acessibilidade: contraste AA, foco visível (:focus-visible), botões de verdade (<button>) para ações, textos alternativos
   nas imagens, e @media (prefers-reduced-motion: reduce) desligando animações.
8. Animações sutis e com propósito: entrada escalonada com IntersectionObserver (opacity + translateY, cubic-bezier(.16,1,.3,1)),
   hover em botões com leve scale e sombra. Sem exagero que atrapalhe a leitura.
9. Use somente as imagens reais informadas (URLs exatas). Sem imagens: crie visuais com CSS/SVG inline.
10. Textos em português do Brasil, específicos deste negócio (nada genérico que serviria pra qualquer concorrente).
    Não invente preços, prêmios, números ou depoimentos que não foram informados.
11. Evite clichês: hero centralizado com gradiente roxo-azul genérico, cards idênticos com a mesma sombra, grade de 3
    ícones genéricos, emoji no lugar de ícone.
REGRAS;
}

// ------------------------------------------------------------
// ETAPA 2 — escreve o site seguindo o plano
// ------------------------------------------------------------
function ia_gerar_html_site($ctx, $briefing, $plano) {
    $dados = ia_contexto_texto($ctx);
    $p = $plano['paleta'] ?? [];
    $paleta = is_array($p) ? implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($p), $p)) : (string)$p;
    $motivos = implode('; ', (array)($plano['motivos_visuais'] ?? []));
    $marcadores = implode(' ', $plano['marcadores']);
    $paginas = implode("\n", array_map(fn($pg) => "  - data-page=\"" . ($pg['chave'] ?? '') . "\" (" . ($pg['titulo'] ?? '') . "): " . ($pg['conteudo'] ?? ''), (array)($plano['paginas'] ?? [])));
    $regras = ia_regras_tecnicas();

    $prompt = <<<PROMPT
Construa o site completo deste negócio seguindo EXATAMENTE as decisões de design abaixo. Você não decide mais nada de
estilo — executa com excelência técnica, nível de premiação (Awwwards).

DADOS REAIS DO NEGÓCIO:
$dados

O QUE O DONO CONTOU: "$briefing"

DECISÕES DE DESIGN (siga à risca):
- Direção: {$plano['direcao_nome']} — {$plano['direcao_desc']}
- Fonte de título: {$plano['fonte_titulo']} | Fonte de corpo: {$plano['fonte_corpo']}
- Paleta: $paleta
- Conceito: {$plano['conceito']}
- Motivos visuais: $motivos
- Headline: "{$plano['headline']}" | Subheadline: "{$plano['subheadline']}" | Botão principal: "{$plano['cta']}"
- Páginas:
$paginas

MARCADORES A INCLUIR (só estes, cada um uma única vez): $marcadores
O botão principal do topo deve levar à página que contém {{AGENDAMENTO}} (se existir) ou {{CONTATO}}.

$regras
PROMPT;

    return ia_executar_geracao_html($prompt, $plano['marcadores'], 1.0);
}

// ------------------------------------------------------------
// EDIÇÃO POR CONVERSA — aplica um pedido em cima do HTML atual
// ------------------------------------------------------------
function ia_refinar_html_site($ctx, $htmlAtual, $instrucao) {
    $dados = ia_contexto_texto($ctx);
    $regras = ia_regras_tecnicas();
    $marcadoresAtuais = array_values(array_filter(IA_MARCADORES, fn($m) => str_contains($htmlAtual, $m)));
    $lista = implode(' ', $marcadoresAtuais);

    $prompt = <<<PROMPT
Você é o desenvolvedor front-end que criou o site abaixo. O dono pediu uma alteração. Aplique SOMENTE o que foi pedido,
com capricho, mantendo todo o resto (design, textos, estrutura, scripts) idêntico. Se o pedido for vago ("mais moderno",
"mais bonito"), faça a melhoria visual mais impactante coerente com o estilo atual.

PEDIDO DO DONO: "$instrucao"

DADOS REAIS DO NEGÓCIO (para consulta, se o pedido precisar):
$dados

Marcadores que existem hoje e DEVEM continuar no resultado (a menos que o pedido seja explicitamente removê-los): $lista
Marcadores que podem ser adicionados se o pedido pedir: {{SOBRE}} {{SERVICOS}} {{EQUIPE}} {{GALERIA}} {{AGENDAMENTO}} {{AVALIACOES}} {{CONTATO}} {{MAPA}} {{CUPONS}} {{WHATSAPP_FLOAT}}

$regras

HTML ATUAL:
$htmlAtual
PROMPT;

    return ia_executar_geracao_html($prompt, array_intersect($marcadoresAtuais, IA_MARCADORES_OBRIGATORIOS), 0.7);
}

// Chama a IA, emenda a resposta se vier cortada, e valida/conserta o HTML
function ia_executar_geracao_html($prompt, $marcadoresEsperados, $temperatura) {
    $sistema = 'Você é um designer e desenvolvedor front-end sênior especializado em sites para pequenos negócios brasileiros. '
             . 'Você entrega sempre um único documento HTML completo e funcional, sem nenhum texto fora do HTML.';
    $r = chamar_ia($prompt, 32000, 170, $temperatura, null, ['sistema' => $sistema, 'esforco' => 'medium']);
    if (!$r['ok']) return $r;

    $html = ia_limpar_resposta_html($r['texto']);

    // Resposta cortada pelo limite de tamanho: pede pra IA continuar de onde parou (uma vez)
    if (stripos($html, '</html>') === false) {
        $final = substr($html, -2500);
        $cont = chamar_ia(
            "Você estava escrevendo um documento HTML e a resposta foi cortada. Continue EXATAMENTE de onde parou, sem repetir nada, "
            . "até fechar </html>. Responda só com a continuação (sem markdown). Trecho final do que já foi escrito:\n\n" . $final,
            16000, 120, 0.3, null, ['sistema' => $sistema, 'esforco' => 'low']
        );
        if ($cont['ok']) {
            $html .= preg_replace('/^```(?:html)?\s*/i', '', preg_replace('/```\s*$/', '', $cont['texto']));
        }
    }
    if (stripos($html, '</html>') === false || stripos($html, '<body') === false) {
        return ['ok' => false, 'erro' => 'A IA não terminou de escrever o site (a resposta veio cortada). Tente novamente — às vezes uma segunda tentativa resolve, ou use um pedido mais curto.'];
    }

    return ['ok' => true, 'html' => ia_pos_processar_html($html, $marcadoresEsperados)];
}

function ia_limpar_resposta_html($texto) {
    $html = trim((string)$texto);
    $html = preg_replace('/^```(?:html)?\s*/i', '', $html);
    $html = preg_replace('/```\s*$/', '', $html);
    // Às vezes a IA escreve uma frase antes do HTML — corta tudo antes do <!DOCTYPE / <html
    if (preg_match('/<!DOCTYPE html|<html/i', $html, $m, PREG_OFFSET_CAPTURE) && $m[0][1] > 0) {
        $html = substr($html, $m[0][1]);
    }
    $fim = strripos($html, '</html>');
    if ($fim !== false) $html = substr($html, 0, $fim + 7);
    return trim($html);
}

// Garante o mínimo que o sistema precisa, consertando em vez de rejeitar
function ia_pos_processar_html($html, $marcadoresEsperados = IA_MARCADORES_OBRIGATORIOS) {
    if (stripos($html, '<!DOCTYPE') !== 0) $html = "<!DOCTYPE html>\n" . $html;
    if (!preg_match('/<html[^>]*\blang=/i', $html)) $html = preg_replace('/<html\b/i', '<html lang="pt-BR"', $html, 1);
    if (stripos($html, '<head') === false) $html = preg_replace('/<html([^>]*)>/i', '<html$1><head></head>', $html, 1);
    if (stripos($html, 'name="viewport"') === false && stripos($html, "name='viewport'") === false) {
        $html = preg_replace('/<head([^>]*)>/i', '<head$1><meta name="viewport" content="width=device-width, initial-scale=1">', $html, 1);
    }
    if (stripos($html, 'charset') === false) {
        $html = preg_replace('/<head([^>]*)>/i', '<head$1><meta charset="UTF-8">', $html, 1);
    }
    // Scripts externos não são permitidos (podem quebrar o site ou vazar dados dos visitantes)
    $html = preg_replace('#<script\b[^>]*\bsrc\s*=\s*["\']?https?://[^>]*>\s*</script>#i', '', $html);

    // Marcador obrigatório faltando: em vez de falhar, coloca ele de volta dentro da última
    // página/seção do site (assim ele segue a navegação que a IA criou)
    foreach ($marcadoresEsperados as $m) {
        if (str_contains($html, $m)) continue;
        $bloco = "\n<div class=\"gds-secao-auto\" style=\"padding:32px 0;\">\n<h2>Fale com a gente</h2>\n$m\n</div>\n";
        $pos = strripos($html, '</section>');
        $html = $pos !== false
            ? substr($html, 0, $pos) . $bloco . substr($html, $pos)
            : preg_replace('/<\/body>/i', str_replace('$', '\\$', $bloco) . '</body>', $html, 1);
    }
    return $html;
}

// ------------------------------------------------------------
// VERSÕES e HISTÓRICO DA CONVERSA
// ------------------------------------------------------------
function ia_salvar_versao($empresaId, $html, $origem, $descricao, $usuarioId = null, $meta = null) {
    global $pdo;
    $st = $pdo->prepare("INSERT INTO site_versoes (empresa_id, html, origem, descricao, usuario_id, meta) VALUES (?,?,?,?,?,?) RETURNING id");
    $st->execute([$empresaId, $html, $origem, mb_substr((string)$descricao, 0, 500), $usuarioId, $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null]);
    $id = $st->fetch()['id'];
    // Mantém só as 30 versões mais recentes por empresa
    $pdo->prepare("DELETE FROM site_versoes WHERE empresa_id=? AND id NOT IN (SELECT id FROM site_versoes WHERE empresa_id=? ORDER BY id DESC LIMIT 30)")
        ->execute([$empresaId, $empresaId]);
    return $id;
}

function ia_listar_versoes($empresaId, $limite = 30) {
    global $pdo;
    $st = $pdo->prepare("SELECT id, origem, descricao, criado_em, LENGTH(html) AS tamanho FROM site_versoes WHERE empresa_id=? ORDER BY id DESC LIMIT " . (int)$limite);
    $st->execute([$empresaId]);
    return $st->fetchAll();
}

function ia_registrar_mensagem($empresaId, $papel, $texto, $versaoId = null) {
    global $pdo;
    $pdo->prepare("INSERT INTO ia_mensagens (empresa_id, papel, texto, versao_id) VALUES (?,?,?,?)")
        ->execute([$empresaId, $papel, mb_substr((string)$texto, 0, 4000), $versaoId]);
}

function ia_listar_mensagens($empresaId, $limite = 60) {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM (SELECT id, papel, texto, versao_id, criado_em FROM ia_mensagens WHERE empresa_id=? ORDER BY id DESC LIMIT " . (int)$limite . ") t ORDER BY id ASC");
    $st->execute([$empresaId]);
    return $st->fetchAll();
}

function ia_aplicar_html_no_site($empresaId, $html, $briefing = null) {
    global $pdo;
    if ($briefing !== null) {
        $pdo->prepare("UPDATE sites SET html_gerado=?, ia_briefing=?, ia_modo_total=true, atualizado_em=NOW() WHERE empresa_id=?")
            ->execute([$html, $briefing, $empresaId]);
    } else {
        $pdo->prepare("UPDATE sites SET html_gerado=?, ia_modo_total=true, atualizado_em=NOW() WHERE empresa_id=?")
            ->execute([$html, $empresaId]);
    }
}
