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
require_once __DIR__ . '/fotos.php';

const IA_MARCADORES = ['{{SOBRE}}', '{{SERVICOS}}', '{{EQUIPE}}', '{{GALERIA}}', '{{AGENDAMENTO}}', '{{ORCAMENTO}}', '{{AVALIACOES}}', '{{CONTATO}}', '{{MAPA}}', '{{CUPONS}}', '{{HORARIOS}}', '{{WHATSAPP_FLOAT}}', '{{CLUBE}}'];
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
        SELECT e.*, seg.nome AS segmento_nome, seg.slug AS segmento_slug, seg.grupo AS segmento_grupo, s.titulo, s.subtitulo, s.sobre, s.diferenciais, s.objetivo,
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
    $emp['atendimento'] = atendimento_info_empresa($empresaId);
    // Fotos profissionais do tipo de negócio (Pexels) quando o negócio tem poucas fotos próprias
    $emp['fotos_banco'] = [];
    if (count($emp['galeria']) < 4 && function_exists('fotos_do_site')) {
        try {
            $s2 = $pdo->prepare("SELECT s.fotos_banco, seg.slug AS segmento_slug FROM sites s JOIN empresas e ON e.id = s.empresa_id LEFT JOIN segmentos seg ON seg.id = e.segmento_id WHERE s.empresa_id=?");
            $s2->execute([$empresaId]);
            $extra = $s2->fetch() ?: [];
            $emp['fotos_banco'] = fotos_do_site(['id' => $empresaId, 'fotos_banco' => $extra['fotos_banco'] ?? null, 'segmento_slug' => $extra['segmento_slug'] ?? '',
                'segmento_nome' => $emp['segmento_nome'] ?? '', 'segmento_descricao' => $emp['segmento_descricao'] ?? '']);
        } catch (PDOException $e) { $emp['fotos_banco'] = []; }
    }
    return $emp;
}

function ia_contexto_texto($ctx) {
    $at = $ctx['atendimento'] ?? [];
    $l = [];
    $l[] = "Nome: {$ctx['nome']}";
    $l[] = 'Segmento cadastrado: ' . ($at['segmento_texto'] ?? ($ctx['segmento_nome'] ?: 'não informado'))
         . (!empty($at['descricao_ia']) ? "\n  Como esse tipo de negócio costuma funcionar: {$at['descricao_ia']}" : '');
    if ($ctx['endereco']) $l[] = "Endereço: {$ctx['endereco']}";
    $l[] = $ctx['whatsapp'] ? "WhatsApp: {$ctx['whatsapp']}" : 'WhatsApp: NÃO cadastrado (não prometa atendimento por WhatsApp)';
    if ($ctx['telefone']) $l[] = "Telefone: {$ctx['telefone']}";
    if ($ctx['email']) $l[] = "E-mail: {$ctx['email']}";
    if (!empty($ctx['instagram'])) $l[] = "Instagram: {$ctx['instagram']}";
    if (!empty($ctx['horario_abertura']) && !empty($ctx['horario_fechamento'])) {
        $l[] = 'Horário de atendimento: ' . substr($ctx['horario_abertura'], 0, 5) . ' às ' . substr($ctx['horario_fechamento'], 0, 5);
    }
    if ($ctx['diferenciais']) $l[] = "Diferenciais (o que torna o negócio único): {$ctx['diferenciais']}";
    if ($ctx['objetivo']) $l[] = "Objetivo do site: {$ctx['objetivo']}";
    if ($ctx['sobre']) $l[] = "Texto 'sobre' já cadastrado: {$ctx['sobre']}";
    if ($ctx['servicos']) {
        $l[] = 'Serviços/produtos reais cadastrados:';
        foreach ($ctx['servicos'] as $s) {
            $l[] = '  - ' . $s['nome'] . ' — ' . ((float)$s['preco'] > 0 ? 'R$ ' . number_format((float)$s['preco'], 2, ',', '.') : 'sob consulta')
                 . ($s['duracao_minutos'] ? " ({$s['duracao_minutos']} min)" : '')
                 . ($s['descricao'] ? ": {$s['descricao']}" : '');
        }
    } else {
        $l[] = 'Serviços/produtos: nenhum cadastrado ainda.';
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
    foreach (array_slice($ctx['fotos_banco'] ?? [], 0, 6) as $i => $f) $imagens[] = 'foto profissional ' . ($i + 1) . ' (banco de imagens, ' . ($f['alt'] ?: 'tema do negócio') . "): {$f['url']}";
    $l[] = $imagens
        ? "Imagens REAIS do negócio (use estas URLs exatas onde fizer sentido):\n  - " . implode("\n  - ", $imagens)
        : 'Imagens: o negócio ainda não tem fotos cadastradas — crie a identidade visual com CSS, gradientes, formas e SVG inline. NÃO use URLs de imagens externas inventadas (ficariam quebradas).';
    return implode("\n", $l);
}

// Texto que explica as 4 formas de atendimento para a IA escolher/seguir
function ia_texto_formas_atendimento() {
    return <<<TXT
FORMAS DE ATENDIMENTO (como o site transforma visitante em cliente):
- "agendamento": o cliente marca dia e horário (barbearia, salão, clínica, dentista, estética, terapia, pet shop...).
- "orcamento": o cliente descreve o que precisa (data do evento, local, detalhes) e pede orçamento — NÃO existe
  horário marcado (DJ, fotógrafo, buffet/eventos, obras e reformas, mecânica, advocacia, agência, consultoria...).
- "contato": o cliente conversa pelo WhatsApp/formulário para tirar dúvidas ou pedir proposta (imobiliária, software,
  cursos, hotel...).
- "catalogo": o cliente escolhe produto/prato com preço e pede pelo WhatsApp (restaurante, delivery, loja).
TXT;
}

// Marcadores obrigatórios para cada forma de atendimento
function ia_marcadores_do_modo($modo, $ctx) {
    $m = ['{{CONTATO}}'];
    if ($modo === 'agendamento') $m[] = '{{AGENDAMENTO}}';
    if ($modo === 'orcamento') $m[] = '{{ORCAMENTO}}';
    if ($modo === 'catalogo' || !empty($ctx['servicos'])) $m[] = '{{SERVICOS}}';
    if (!empty($ctx['whatsapp'])) $m[] = '{{WHATSAPP_FLOAT}}';
    return $m;
}

// ------------------------------------------------------------
// ETAPA 1 — a IA ESTUDA o negócio (tipo, forma de atendimento, público,
// serviços, perguntas frequentes) e toma as decisões de design
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
    $at = $ctx['atendimento'] ?? ['modo' => 'agendamento', 'modo_origem' => 'padrao'];
    $formas = ia_texto_formas_atendimento();
    $regraModo = $at['modo_origem'] === 'empresa'
        ? "A forma de atendimento deste negócio JÁ FOI DEFINIDA pelo dono: \"{$at['modo']}\". Use exatamente essa."
        : ($at['modo_origem'] === 'segmento'
            ? "O segmento cadastrado sugere \"{$at['modo']}\", mas o cadastro pode estar errado: decida pela DESCRIÇÃO DO DONO e pelos dados reais."
            : 'Decida a forma de atendimento pela descrição do dono e pelos dados reais.');

    // Identidade de referência do ramo (a mesma do site padrão), para a primeira impressão "parecer" o negócio
    require_once __DIR__ . '/site_nichos.php';
    $N = site_nichos()[site_nicho_da_empresa($ctx)];
    $dados .= "\nREFERÊNCIA DE IDENTIDADE DO RAMO ({$N['nome']}) — ponto de partida para a primeira impressão, pode refinar: fundo {$N['cores']['bg']}, "
            . "destaque {$N['cores']['accent']}, fontes " . trim($N['titulo'], "'") . ' + ' . trim($N['corpo'], "'") . ", textura \"{$N['motivo']}\""
            . ($N['headline'] ? ", frase de impacto no tom de: \"{$N['headline']}\"" : '') . '.';

    $prompt = <<<PROMPT
Você é um estrategista de marketing digital e diretor de arte sênior. Primeiro ESTUDE este negócio (o que ele vende,
para quem, e o que o visitante do site precisa fazer para virar cliente). Depois tome DECISÕES CONCRETAS de design
que um desenvolvedor vai seguir ao pé da letra.

DADOS REAIS DO NEGÓCIO:
$dados

O QUE O DONO CONTOU: "$briefing"$pref

$formas
$regraModo

DIREÇÃO VISUAL BASE: $direcaoNome — $direcaoDesc

Responda APENAS com JSON válido (sem markdown), exatamente neste formato:
{"analise":{"tipo_negocio":"o que o negócio é, em poucas palavras (ex.: DJ para casamentos e festas)","modo_atendimento":"agendamento|orcamento|contato|catalogo","motivo_modo":"1 frase","publico":"quem é o cliente ideal","o_que_o_visitante_quer":"1 frase","tom_de_voz":"ex.: animado e jovem","servicos_sugeridos":[{"nome":"...","descricao":"até 15 palavras","preco":null,"duracao_minutos":null}],"perguntas_frequentes":[{"pergunta":"...","resposta":"..."}]},
"fonte_titulo":"fonte do Google Fonts","fonte_corpo":"outra fonte do Google Fonts que combine","paleta":{"fundo":"#hex","superficie":"#hex","texto":"#hex","destaque":"#hex","destaque_2":"#hex"},"conceito":"1-2 frases com a metáfora visual específica DESTE negócio","motivos_visuais":["elemento gráfico concreto 1","elemento 2","elemento 3"],"headline":"título principal, curto e marcante","subheadline":"frase de apoio","cta":"texto do botão principal (coerente com a forma de atendimento)","paginas":[{"chave":"inicio","titulo":"Início","conteudo":"o que vai nessa página"}],"marcadores":["{{CONTATO}}"]}

Regras:
- "servicos_sugeridos": de 3 a 8 serviços/pacotes/produtos típicos DESTE negócio, baseados no que o dono contou.
  "preco" só se o dono informou valores (senão null). "duracao_minutos" só se for agendamento (senão null).
- "perguntas_frequentes": 4 a 6 dúvidas reais que um cliente desse negócio tem antes de contratar, com respostas
  curtas e genéricas o bastante para não inventar fatos (sem preços, prêmios ou números inventados).
- "paginas": de 3 a 6. A primeira é sempre "inicio". Chaves em minúsculo sem acento. Inclua uma página
  "agendar" (forma agendamento) ou "orcamento" (forma orcamento) e sempre "contato".
- "marcadores": escolha entre {{SOBRE}} {{SERVICOS}} {{EQUIPE}} {{GALERIA}} {{AGENDAMENTO}} {{ORCAMENTO}} {{AVALIACOES}}
  {{CONTATO}} {{MAPA}} {{CUPONS}} {{HORARIOS}} {{WHATSAPP_FLOAT}}. {{CONTATO}} sempre. {{AGENDAMENTO}} SOMENTE na forma
  "agendamento"; {{ORCAMENTO}} SOMENTE na forma "orcamento". {{EQUIPE}} só se houver equipe; {{GALERIA}} só se houver
  fotos; {{CUPONS}} só se houver cupons; {{MAPA}} e {{HORARIOS}} só se houver endereço físico de atendimento.
- A paleta precisa de contraste real entre fundo e texto (WCAG AA), e o destaque deve guiar o olhar.
- IDENTIDADE PRÓPRIA (skill frontend-design): tire as escolhas do mundo DESTE negócio (materiais, ferramentas,
  vocabulário, lugar). Antes de responder, compare com o que você faria para qualquer negócio parecido e troque o que
  for padrão. Evite os sinais de "site feito por IA" (a não ser que o dono peça): fundo creme (#F4F1EA) com serifa e
  acento terracota (#D97757); fundo quase preto com um único verde-limão/vermelhão; kit de cartões SaaS iguais com a
  mesma sombra; selo em CAIXA ALTA espaçada acima de todo título; "A · B · C"; "→" em todo botão; número 01/02/03 em
  conteúdo que não é sequência. Uma ou duas fontes, bem diferentes entre si, escolhidas para este negócio.
PROMPT;

    $r = chamar_ia_json($prompt, 3000, 75, 0.9, ['esforco' => 'low']);
    $plano = $r['ok'] ? $r['dados'] : null;

    // Se o planejamento falhar, segue com um plano padrão em vez de travar tudo
    if (!is_array($plano) || empty($plano['fonte_titulo'])) {
        $plano = [
            'fonte_titulo' => 'Space Grotesk', 'fonte_corpo' => 'Inter',
            'paleta' => ['fundo' => '#0b0e1f', 'superficie' => '#12162e', 'texto' => '#eef0fa',
                         'destaque' => $ctx['cor_primaria'] ?: '#4f7cff', 'destaque_2' => $ctx['cor_secundaria'] ?: '#16213e'],
            'conceito' => $briefing, 'motivos_visuais' => [],
            'headline' => $ctx['titulo'] ?: $ctx['nome'], 'subheadline' => $ctx['subtitulo'] ?: '', 'cta' => atendimento_modos()[$at['modo']]['cta'],
            'paginas' => [['chave' => 'inicio', 'titulo' => 'Início', 'conteudo' => 'apresentação'], ['chave' => 'servicos', 'titulo' => 'Serviços', 'conteudo' => 'serviços'], ['chave' => 'contato', 'titulo' => 'Contato', 'conteudo' => 'contato']],
            'marcadores' => ['{{SOBRE}}', '{{SERVICOS}}', '{{CONTATO}}', '{{WHATSAPP_FLOAT}}'],
        ];
    }
    $analise = is_array($plano['analise'] ?? null) ? $plano['analise'] : [];
    // Forma de atendimento final: a do dono (se definida) > a que a IA concluiu > a do segmento
    $modo = $at['modo_origem'] === 'empresa' ? $at['modo']
          : (atendimento_modo_valido($analise['modo_atendimento'] ?? null) ? $analise['modo_atendimento'] : $at['modo']);
    $analise['modo_atendimento'] = $modo;
    $plano['analise'] = $analise;
    $plano['modo'] = $modo;

    $permitidos = array_values(array_filter(IA_MARCADORES, fn($m) => !($m === '{{AGENDAMENTO}}' && $modo !== 'agendamento') && !($m === '{{ORCAMENTO}}' && $modo !== 'orcamento')));
    $plano['marcadores'] = array_values(array_intersect($permitidos, array_unique(array_merge($plano['marcadores'] ?? [], ia_marcadores_do_modo($modo, $ctx)))));
    $plano['obrigatorios'] = ia_marcadores_do_modo($modo, $ctx);
    $plano['direcao_chave'] = $direcaoChave;
    $plano['direcao_nome'] = $direcaoNome;
    $plano['direcao_desc'] = $direcaoDesc;
    return $plano;
}

// Estratégia de conversão que vai no prompt de geração, conforme a forma de atendimento
function ia_estrategia_conversao($modo, $temWhats) {
    $whats = $temWhats ? ' Use também {{LINK_WHATSAPP}} num botão secundário "Chamar no WhatsApp".' : '';
    return [
        'agendamento' => "O objetivo do site é o visitante AGENDAR. O botão principal (topo e final das páginas) leva a {{LINK_PRINCIPAL}} e o texto pode ser {{TEXTO_PRINCIPAL}}. Mostre serviços com preço — cada serviço já tem o botão \"Agendar\" que abre o agendamento com ele escolhido.$whats",
        'orcamento'   => "O objetivo do site é o visitante PEDIR ORÇAMENTO (não existe agendamento de horário). O botão principal leva a {{LINK_PRINCIPAL}} (formulário de orçamento). Mostre os serviços/pacotes — cada um já tem o botão \"Pedir orçamento\". Prova social (eventos/obras/trabalhos já feitos, galeria, depoimentos) é essencial.$whats",
        'contato'     => "O objetivo do site é o visitante ENTRAR EM CONTATO. O botão principal leva a {{LINK_PRINCIPAL}}" . ($temWhats ? ' (WhatsApp)' : ' (contato)') . '. Explique bem a proposta de valor, como funciona e por que escolher este negócio.',
        'catalogo'    => "O objetivo do site é o visitante ESCOLHER E PEDIR. O botão principal leva a {{LINK_PRINCIPAL}} (cardápio/produtos). Cada item já tem o botão \"Adicionar\" e o carrinho de pedido aparece sozinho (entrega/retirada, pagamento) — não crie carrinho. Destaque preços, entrega/retirada, formas de pagamento e horário.",
    ][$modo] ?? '';
}

// ------------------------------------------------------------
// Regras técnicas comuns à geração e à edição (o "contrato")
// ------------------------------------------------------------
function ia_regras_tecnicas() {
    return <<<REGRAS
CONTRATO TÉCNICO (obrigatório):
1. Responda APENAS o documento HTML completo, de <!DOCTYPE html> até </html>. Sem explicação, sem markdown.
2. HTML/CSS/JS puro, tudo num arquivo só. Proibido: React, Vue, Bootstrap, Tailwind, jQuery, AOS ou outro script externo.
   Fontes: pode usar <link> do Google Fonts. ÚNICOS scripts externos liberados (opcionais, para cenas de rolagem de
   cinema), exatamente estas URLs com defer:
   https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js
   https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js
   https://cdn.jsdelivr.net/npm/lenis@1.1.13/dist/lenis.min.js
   Se usar, o site tem que funcionar igual sem eles (confira if (window.gsap) antes de usar).
3. <head> com <meta charset="UTF-8">, <meta name="viewport" content="width=device-width, initial-scale=1">, <title> e
   <meta name="description"> escritos pra SEO local do negócio, e <html lang="pt-BR">.
4. Navegação por páginas (não scroll único): cada página é um <section data-page="chave">; só a primeira visível.
   Os links do menu têm data-page-link="chave". Defina no JS a função global window.gdsMostrarPagina(chave) que troca a
   página visível, marca o link ativo e rola pro topo. Ao carregar, abra a página do #hash da URL se existir.
5. MARCADORES: cada marcador de componente {{...}} fica sozinho em sua própria linha, dentro de um container seu, e
   NUNCA dentro de atributo, <a>, <p>, <button> ou <form>. Na hora de exibir, cada um vira um COMPONENTE PRONTO, já com
   design profissional próprio que herda a sua fonte e a cor do texto e usa a cor de destaque da paleta:
   {{SERVICOS}} = cartões de serviço com preço e o botão certo para a forma de atendimento ("Agendar", "Pedir
   orçamento", "Pedir" ou WhatsApp) | {{AGENDAMENTO}} = agendamento passo a passo (serviço → profissional → calendário
   com horários livres → dados) | {{ORCAMENTO}} = formulário de pedido de orçamento (serviço, data do evento, local,
   detalhes, contato) | {{CONTATO}} = canais (WhatsApp, telefone, e-mail, endereço) + formulário | {{EQUIPE}} = cartões
   da equipe | {{AVALIACOES}} = nota média + depoimentos + formulário | {{GALERIA}} = grade com ampliação |
   {{CUPONS}} = cupons | {{CLUBE}} = planos de assinatura/pacotes de sessões (só se o negócio tiver) | {{MAPA}} = mapa com "Como chegar" | {{HORARIOS}} = horário da semana | {{WHATSAPP_FLOAT}} =
   botão flutuante do WhatsApp.
   Por isso: NÃO estilize classes que começam com "gds-" e NÃO crie você mesmo listas de serviços, equipe, avaliações,
   formulários, calendário, mapa ou botão de WhatsApp — só dê a cada componente uma seção com título, um respiro
   (padding) e largura máxima confortável (ex.: max-width 1100px; agendamento/orçamento ~760px).
   Os botões "Agendar"/"Pedir orçamento" dos serviços levam sozinhos até o componente certo, em qualquer página.
5b. LINKS PRONTOS (estes PODEM e DEVEM ir dentro de atributo/texto): {{LINK_PRINCIPAL}} = destino da ação principal
   (agendamento, orçamento, WhatsApp ou contato, conforme o negócio); {{TEXTO_PRINCIPAL}} = texto certo desse botão;
   {{LINK_WHATSAPP}} = conversa no WhatsApp do negócio já com mensagem pronta. Exemplos:
   <a class="btn" href="{{LINK_PRINCIPAL}}">{{TEXTO_PRINCIPAL}}</a>   <a href="{{LINK_WHATSAPP}}">Chamar no WhatsApp</a>
   NUNCA escreva você mesmo links wa.me, api.whatsapp.com, tel: ou mailto: com números inventados ou digitados —
   use sempre {{LINK_WHATSAPP}} (o sistema monta com o número real, e abre em nova aba sozinho).
6. MOBILE-FIRST: sem largura fixa que quebre abaixo de 400px; títulos com clamp(); toque mínimo 44x44px; inputs com
   font-size >= 16px; nada de rolagem horizontal em 360px; menu vira hambúrguer acessível no celular.
7. Acessibilidade: contraste AA, foco visível (:focus-visible), botões de verdade (<button>) para ações, textos alternativos
   nas imagens, e @media (prefers-reduced-motion: reduce) desligando animações.
8. ANIMAÇÕES — o site já carrega o KIT GDS MOTION (não escreva IntersectionObserver nem bibliotecas de animação).
   Basta usar atributos no HTML:
   data-anim="fade-up|fade-down|fade-in|zoom-in|slide-left|slide-right|blur-in|clip-up" (+ data-delay="150" em ms)
   data-stagger no pai = filhos entram em sequência · data-split no título = entra palavra por palavra
   data-counter="12" = número sobe animado (só números reais!) · data-parallax="0.15" em imagens de fundo
   data-tilt em cartões (inclinação 3D) · data-magnetic no botão principal · data-marquee numa faixa de palavras
   rolando (ex.: nomes dos serviços) · <div data-progress></div> = barra de leitura · data-header-scroll no
   cabeçalho fixo (ganha a classe .rolou ao rolar — estilize .rolou com fundo e blur).
   Use com intenção e RESTRIÇÃO: um único momento orquestrado (ex.: a entrada do topo com data-split) vale mais que
   efeito espalhado — NÃO coloque data-anim em todas as seções nem data-tilt em todo cartão (isso denuncia site
   genérico). Movimento que responde ao clique/toque (abrir, confirmar) é bem-vindo. Respeita "reduzir movimento" sozinho.
9. Use somente as imagens informadas (URLs exatas: fotos do negócio e fotos profissionais do banco de imagens).
   Topo com foto grande + sobreposição em gradiente para o texto ficar legível; object-fit: cover; loading="lazy"
   (exceto a primeira). Sem nenhuma imagem: crie visuais com CSS (gradientes em malha, formas, ruído) e SVG inline.
10. Textos em português do Brasil, específicos deste negócio (nada genérico que serviria pra qualquer concorrente).
    Não invente preços, prêmios, números ou depoimentos que não foram informados.
11. CONFIANÇA E ATENÇÃO (o visitante decide em 3 segundos): logo no topo mostre o que o negócio é (o ramo tem que ser
    óbvio pela foto, cor, fonte e frase), o status "aberto agora" e a nota das avaliações quando houver, e a ação
    principal sempre visível. No celular, uma barra fixa embaixo com a ação principal + WhatsApp. Prova (nota,
    depoimento real) perto do topo, preços claros, perguntas frequentes com dados reais e endereço com "Como chegar".
12. PADRÃO DE ACABAMENTO (nível Lovable/Vercel): defina um sistema de design em :root (cores, raio, sombras, espaçamento
    em escala 4/8/12/16/24/32/48/64/96/128px, escala tipográfica com clamp()); seções com respiro generoso (96-140px no
    computador, 64-88px no celular) e largura máxima ~1200px; hierarquia clara (selo pequeno → título grande → texto
    curto → botões); botões com estados hover/focus/active e transição de 200-300ms; cartões com borda fina de 1px e
    sombra suave; ícones em SVG inline do mesmo traço; imagens com object-fit e cantos arredondados; cabeçalho fixo que
    ganha fundo ao rolar; nada desalinhado, nada encostado na borda no celular, nada com texto cinza ilegível.
13. Evite clichês: hero centralizado com gradiente roxo-azul genérico, cards idênticos com a mesma sombra, grade de 3
    ícones genéricos, emoji no lugar de ícone, uma palavra só do título em outra cor/itálico, rótulos em CAIXA ALTA,
    selos/rótulos desnecessários acima do conteúdo. Ousadia em UM lugar (o elemento memorável); o resto quieto e
    disciplinado. Linhas de texto com menos de ~80 caracteres. Textos de botão dizem o que acontece ("Ver cardápio",
    "Agendar horário"), voz ativa, frases curtas, sem enchimento.
14. CHECKLIST DE INTERFACE (Vercel Web Interface Guidelines) — o site é reprovado se faltar:
    - botão só com ícone tem aria-label; ícone decorativo tem aria-hidden="true"; toda imagem tem alt (alt="" se
      decorativa) e width/height (evita pulo de layout); imagens abaixo da dobra com loading="lazy", a do topo com
      fetchpriority="high";
    - um único <h1>, títulos em ordem (h2, h3…); link "Pular para o conteúdo" no começo; scroll-margin-top nas seções
      (o cabeçalho fixo não cobre o título);
    - foco visível com :focus-visible em tudo que é clicável (nunca outline:none sem substituto); hover em botões e links;
    - nunca "transition: all" (liste as propriedades) e anime só transform/opacity;
    - touch-action: manipulation nos botões; overscroll-behavior: contain no menu do celular; respeitar
      env(safe-area-inset-bottom) na barra fixa de baixo;
    - <meta name="theme-color"> com a cor do fundo; se o fundo for escuro, color-scheme: dark no <html>;
    - "…" (reticências de verdade), nunca "...", e sem user-scalable=no / maximum-scale=1.
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
    $modo = $plano['modo'] ?? 'agendamento';
    $estrategia = ia_estrategia_conversao($modo, !empty($ctx['whatsapp']));
    $an = (array)($plano['analise'] ?? []);
    $analise = '';
    foreach (['tipo_negocio' => 'Tipo de negócio', 'publico' => 'Público', 'o_que_o_visitante_quer' => 'O que o visitante quer', 'tom_de_voz' => 'Tom de voz'] as $k => $rot) {
        if (!empty($an[$k]) && is_string($an[$k])) $analise .= "- $rot: {$an[$k]}\n";
    }
    $faq = '';
    foreach (array_slice((array)($an['perguntas_frequentes'] ?? []), 0, 6) as $f) {
        if (!empty($f['pergunta']) && !empty($f['resposta'])) $faq .= "  P: {$f['pergunta']}\n  R: {$f['resposta']}\n";
    }
    $faqTxt = $faq !== ''
        ? "PERGUNTAS FREQUENTES (crie uma seção de FAQ acessível — <details>/<summary> — com estas):\n$faq"
        : '';
    $referencia = !empty($plano['referencia']) ? "\nSITE DE REFERÊNCIA DO DONO — o estilo dele manda (cores, fontes, espaçamentos, componentes e layout); o conteúdo é deste negócio, nunca o da referência:\n" . mb_substr($plano['referencia'], 0, 9000) . "\n" : '';
    $semWhats = empty($ctx['whatsapp']) ? "\nATENÇÃO: o negócio NÃO tem WhatsApp cadastrado — não crie botões de WhatsApp; use {{LINK_PRINCIPAL}}." : '';

    $prompt = <<<PROMPT
Construa o site completo deste negócio seguindo EXATAMENTE as decisões de design abaixo. Você não decide mais nada de
estilo — executa com excelência técnica, nível de premiação (Awwwards).

DADOS REAIS DO NEGÓCIO:
$dados

O QUE O DONO CONTOU: "$briefing"

ESTUDO DO NEGÓCIO (escreva para este público, neste tom):
$analise
FORMA DE ATENDIMENTO: "$modo".
ESTRATÉGIA DE CONVERSÃO: $estrategia$semWhats

$faqTxt

DECISÕES DE DESIGN (siga à risca):
- Direção: {$plano['direcao_nome']} — {$plano['direcao_desc']}
- Fonte de título: {$plano['fonte_titulo']} | Fonte de corpo: {$plano['fonte_corpo']}
- Paleta: $paleta
- Conceito: {$plano['conceito']}
- Motivos visuais: $motivos
- Headline: "{$plano['headline']}" | Subheadline: "{$plano['subheadline']}" | Botão principal: "{$plano['cta']}"
- Páginas:
$paginas

MARCADORES DE COMPONENTE A INCLUIR (só estes, cada um uma única vez): $marcadores
Os botões de chamada (topo, fim de cada página) usam href="{{LINK_PRINCIPAL}}".
$referencia
$regras
PROMPT;

    return ia_executar_geracao_html($prompt, $plano['obrigatorios'] ?? $plano['marcadores'], 1.0, $modo);
}

// ------------------------------------------------------------
// EDIÇÃO POR CONVERSA — aplica um pedido em cima do HTML atual
// ------------------------------------------------------------
function ia_refinar_html_site($ctx, $htmlAtual, $instrucao, $ent = null, $elemento = null, $referencia = '') {
    $dados = ia_contexto_texto($ctx);
    $regras = ia_regras_tecnicas();
    $marcadoresAtuais = array_values(array_filter(IA_MARCADORES, fn($m) => str_contains($htmlAtual, $m)));
    $lista = implode(' ', $marcadoresAtuais);
    $modo = $ctx['atendimento']['modo'] ?? 'agendamento';
    $obrigatorios = ia_marcadores_do_modo($modo, $ctx);
    $estrategia = ia_estrategia_conversao($modo, !empty($ctx['whatsapp']));
    $outroModo = $modo === 'agendamento' ? '{{ORCAMENTO}}' : ($modo === 'orcamento' ? '{{AGENDAMENTO}}' : '{{AGENDAMENTO}} e {{ORCAMENTO}}');
    $nome = $ctx['nome'] ?? '';
    $glossario = IA_GLOSSARIO_ESTILOS;
    $combinado = '';
    if ($ent) {
        $combinado = "\nO QUE VOCÊ COMBINOU COM O DONO: " . $ent['entendi']
            . ($ent['alteracoes'] ? "\nMUDANÇAS: - " . implode("\n- ", $ent['alteracoes']) : '')
            . ($ent['preservar'] ? "\nNÃO MUDE: - " . implode("\n- ", $ent['preservar']) : '')
            . ($ent['brief_design'] !== '' ? "\nDIREÇÃO DE DESIGN: " . $ent['brief_design'] : '');
    }
    $el = $elemento ? "\nELEMENTO SELECIONADO NO PREVIEW (mude só ele):\n" . ia_texto_elemento($elemento) : '';
    $ref = $referencia !== '' ? "\nSITE DE REFERÊNCIA (copie o estilo — cores, fontes, espaçamentos, componentes, layout — NÃO o conteúdo):\n" . mb_substr($referencia, 0, 9000) : '';

    $prompt = <<<PROMPT
Você é o desenvolvedor front-end que criou o site abaixo. O dono pediu uma alteração. Aplique o que foi pedido com
capricho de designer sênior, mantendo todo o resto. Se o pedido for vago ("mais moderno", "mais bonito"), faça a
melhoria visual mais impactante coerente com o estilo atual.

O SITE É DO NEGÓCIO "$nome" E CONTINUA SENDO: mesmo nome, mesmos serviços, mesmos contatos, mesma forma de atendimento,
mesmos textos sobre o negócio (você pode reescrever títulos e chamadas para combinar com o novo visual, sempre falando
deste negócio). Mudança de visual NÃO é mudança de negócio.

PEDIDO DO DONO: "$instrucao"$combinado$el$ref

$glossario

DADOS REAIS DO NEGÓCIO (para consulta, se o pedido precisar):
$dados

Marcadores que existem hoje e DEVEM continuar no resultado (a menos que o pedido seja explicitamente removê-los): $lista
Marcadores que podem ser adicionados se o pedido pedir: {{SOBRE}} {{SERVICOS}} {{EQUIPE}} {{GALERIA}} {{AVALIACOES}} {{CONTATO}} {{MAPA}} {{CUPONS}} {{HORARIOS}} {{WHATSAPP_FLOAT}}
FORMA DE ATENDIMENTO DO NEGÓCIO: "$modo". $estrategia
NÃO use $outroModo neste site (não combina com a forma de atendimento); se existir, troque pelo correto.

$regras

HTML ATUAL:
$htmlAtual
PROMPT;

    return ia_executar_geracao_html($prompt, array_values(array_intersect($obrigatorios, ['{{CONTATO}}', '{{AGENDAMENTO}}', '{{ORCAMENTO}}'])), 0.7, $modo);
}

// Chama a IA, emenda a resposta se vier cortada, e valida/conserta o HTML
function ia_executar_geracao_html($prompt, $marcadoresEsperados, $temperatura, $modo = null) {
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

    // Componente da forma de atendimento errada (ex.: agendamento num site de DJ) vira o certo, no mesmo lugar
    if ($modo) $html = ia_trocar_componente_errado($html, $modo);
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
    // (exceto as bibliotecas de animação liberadas, com versão fixa)
    $html = preg_replace_callback('#<script\b[^>]*\bsrc\s*=\s*["\']?(https?://[^"\'\s>]+)[^>]*>\s*</script>#i', function ($m) {
        $liberados = ['https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js', 'https://cdn.jsdelivr.net/npm/lenis@1.1.13/dist/lenis.min.js'];
        return in_array($m[1], $liberados, true) ? '<script src="' . $m[1] . '" defer></script>' : '';
    }, $html);

    // Marcador obrigatório faltando: em vez de falhar, coloca ele de volta dentro da última
    // página/seção do site (assim ele segue a navegação que a IA criou)
    $titulos = ['{{AGENDAMENTO}}' => 'Agende seu horário', '{{ORCAMENTO}}' => 'Peça seu orçamento', '{{SERVICOS}}' => 'Serviços', '{{CONTATO}}' => 'Fale com a gente'];
    foreach ($marcadoresEsperados as $m) {
        if (str_contains($html, $m)) continue;
        if ($m === '{{WHATSAPP_FLOAT}}') { $html = preg_replace('/<\/body>/i', "$m\n</body>", $html, 1); continue; }
        $titulo = $titulos[$m] ?? '';
        $bloco = "\n<div class=\"gds-secao-auto\" style=\"padding:32px 16px;max-width:1100px;margin:0 auto;\">\n" . ($titulo ? "<h2>$titulo</h2>\n" : '') . "$m\n</div>\n";
        $pos = strripos($html, '</section>');
        $html = $pos !== false
            ? substr($html, 0, $pos) . $bloco . substr($html, $pos)
            : preg_replace('/<\/body>/i', str_replace('$', '\\$', $bloco) . '</body>', $html, 1);
    }
    return $html;
}

// Site gerado num modo e o dono trocou a forma de atendimento: o componente
// errado vira o certo (agendamento <-> orçamento) sem precisar gerar de novo
function ia_trocar_componente_errado($html, $modo) {
    $certo = ['agendamento' => '{{AGENDAMENTO}}', 'orcamento' => '{{ORCAMENTO}}'][$modo] ?? null;
    if (!$certo) return $html; // contato/catálogo: mantém o que o dono tiver pedido
    $errado = $certo === '{{AGENDAMENTO}}' ? '{{ORCAMENTO}}' : '{{AGENDAMENTO}}';
    if (!str_contains($html, $errado)) return $html;
    if (str_contains($html, $certo)) return str_replace($errado, '', $html);
    $html = preg_replace('/' . preg_quote($errado, '/') . '/', $certo, $html, 1);
    return str_replace($errado, '', $html);
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

// Guarda as cores do design (os componentes do site — agendamento, serviços, contato — usam a mesma cor de destaque)
function ia_salvar_tema($empresaId, $plano) {
    global $pdo;
    $p = (array)($plano['paleta'] ?? []);
    $accent = $p['destaque'] ?? (array_values($p)[3] ?? null);
    if (!is_string($accent) || !preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) return;
    try {
        $pdo->prepare("UPDATE sites SET ia_tema=? WHERE empresa_id=?")
            ->execute([json_encode(['accent' => $accent, 'fundo' => $p['fundo'] ?? null, 'texto' => $p['texto'] ?? null]), $empresaId]);
    } catch (PDOException $e) {
        // banco sem a coluna (adicional_12): os componentes usam a cor achada no CSS do site
    }
}

// ============================================================
// AGENTE DE EDIÇÃO (no estilo Lovable)
// 1) ENTENDER: lê a conversa, o site atual e o pedido, e diz o que vai fazer
//    (ou faz uma pergunta quando o pedido é ambíguo).
// 2) APLICAR: edição cirúrgica por blocos BUSCAR/TROCAR (mexe só no trecho
//    certo) ou, para mudanças de visual do site todo, reescreve mantendo o
//    negócio, os textos e os componentes.
// ============================================================

// Referências de estilo: SEMPRE significam "aplicar essa linguagem visual", nunca "mudar de negócio"
const IA_GLOSSARIO_ESTILOS = <<<TXT
REFERÊNCIAS DE ESTILO (quando o dono cita uma delas, é o VISUAL/LAYOUT que ele quer, aplicado ao negócio DELE):
- "SaaS", "startup", "software", "app", "tech", "Lovable", "Vercel", "Linear", "Stripe": landing page moderna de produto —
  navegação fixa com fundo em vidro, selo pequeno acima do título, título enorme com palavra em gradiente, subtítulo curto,
  dois botões (principal e secundário), um "mockup"/cartão de vidro com foto ou prévia do serviço no topo, faixa de
  confiança (nota, nº de avaliações, anos de bairro), grade de benefícios em "bento" (cartões de tamanhos diferentes,
  bordas finas, ícones), seção "como funciona" em 3 passos numerados, cartões de serviço/preço tipo planos (um em
  destaque), depoimentos em cartões, perguntas frequentes em acordeão, chamada final grande, rodapé organizado;
  fundo com gradientes suaves/aurora, muito espaço em branco, cantos arredondados 16-24px, sombras suaves, fonte
  sem serifa geométrica (Inter, Geist, Plus Jakarta Sans, Sora).
- "Apple", "minimalista", "clean": muito branco, tipografia grande, fotos grandes, quase sem cor, animações suaves.
- "luxo", "premium", "elegante": fundo escuro ou creme, serifada fina, dourado/champanhe, espaçamento generoso.
- "Nubank", "iFood", "colorido", "jovem": cor forte de marca, formas arredondadas, ilustrações/ícones grossos.
- "revista", "editorial": grade assimétrica, serifada grande, fotos recortadas, numeração de seções.
- "dark", "escuro", "neon", "gamer": fundo quase preto, brilhos, bordas iluminadas.
- "igual ao site X" / código colado: copie o estilo (cores, fontes, espaçamento, componentes, layout), não o conteúdo.
O NEGÓCIO NUNCA MUDA por causa de uma referência: uma barbearia "estilo SaaS" continua sendo a barbearia, com os mesmos
serviços, nome, contatos e forma de atendimento. Só mude o que o negócio é/vende se o dono disser isso com todas as letras
(ex.: "agora meu negócio é uma loja de roupas").
TXT;

// Raio-X compacto do site atual (para a IA entender sem ler 60 mil caracteres)
function ia_resumo_site($html) {
    $l = [];
    if (preg_match('#<title>(.*?)</title>#is', $html, $m)) $l[] = 'Título da página: ' . trim(strip_tags($m[1]));
    if (preg_match_all('#fonts\.googleapis\.com/css2?\?family=([^"\'&]+)#i', $html, $m)) $l[] = 'Fontes: ' . implode(', ', array_unique(array_map(fn($f) => urldecode(explode(':', $f)[0]), $m[1])));
    if (preg_match('#:root\s*\{([^}]{0,700})#i', $html, $m)) $l[] = 'Variáveis de cor/estilo: ' . preg_replace('/\s+/', ' ', trim($m[1]));
    $navs = [];
    if (preg_match_all('#data-page-link=["\']([^"\']+)["\'][^>]*>(.*?)</#is', $html, $m)) foreach ($m[1] as $i => $k) $navs[$k] = trim(strip_tags($m[2][$i]));
    if ($navs) $l[] = 'Menu: ' . implode(' | ', array_map(fn($k, $v) => "$v ($k)", array_keys($navs), $navs));
    if (preg_match_all('#<(section|header|footer)\b([^>]*)>(.*?)</\1>#is', $html, $m)) {
        $s = [];
        foreach ($m[0] as $i => $bloco) {
            $attr = $m[2][$i];
            $id = preg_match('#(?:id|data-page)=["\']([^"\']+)#', $attr, $a) ? $a[1] : '';
            $classe = preg_match('#class=["\']([^"\']+)#', $attr, $a) ? $a[1] : '';
            $tit = preg_match('#<h[1-3][^>]*>(.*?)</h[1-3]>#is', $m[3][$i], $h) ? trim(preg_replace('/\s+/', ' ', strip_tags($h[1]))) : '';
            preg_match_all('/\{\{[A-Z_]+\}\}/', $bloco, $mk);
            $s[] = '- <' . $m[1][$i] . ($id ? " #$id" : '') . ($classe ? ' .' . mb_substr($classe, 0, 40) : '') . '>' . ($tit ? ' "' . mb_substr($tit, 0, 80) . '"' : '')
                 . ($mk[0] ? ' ' . implode(' ', array_unique($mk[0])) : '');
            if (count($s) >= 25) break;
        }
        $l[] = "Seções:\n" . implode("\n", $s);
    }
    return implode("\n", $l);
}

// Site de referência colado pelo dono: extrai o "DNA" visual (CSS, fontes, estrutura) sem o conteúdo
function ia_extrair_referencia($codigo) {
    $codigo = (string)$codigo;
    if (mb_strlen(trim($codigo)) < 40) return '';
    if (!preg_match('/<[a-z][\s\S]*>/i', $codigo) && !preg_match('/\{[^}]*:[^}]*\}/', $codigo)) return 'Descrição da referência: ' . mb_substr(trim($codigo), 0, 1500);
    $partes = [];
    if (preg_match_all('#fonts\.googleapis\.com/css2?\?family=([^"\'&]+)#i', $codigo, $m)) $partes[] = 'Fontes: ' . implode(', ', array_unique(array_map(fn($f) => urldecode(explode(':', $f)[0]), $m[1])));
    $css = '';
    if (preg_match_all('#<style[^>]*>(.*?)</style>#is', $codigo, $m)) $css = implode("\n", $m[1]);
    elseif (!str_contains($codigo, '<')) $css = $codigo;
    // Projetos React/Tailwind (como os da Lovable): as classes utilitárias dizem o estilo
    if (preg_match_all('#class(?:Name)?=["\']([^"\']{20,})["\']#', $codigo, $m)) {
        $classes = array_slice(array_unique($m[1]), 0, 60);
        $partes[] = "Classes usadas (Tailwind/utilitárias), na ordem em que aparecem:\n" . mb_substr(implode("\n", $classes), 0, 4000);
    }
    if ($css !== '') $partes[] = "CSS da referência:\n" . mb_substr(preg_replace('/\s+/', ' ', $css), 0, 7000);
    if (preg_match_all('#<(section|header|nav|footer)\b[^>]*>#i', $codigo, $m)) $partes[] = 'Blocos principais: ' . implode(' ', array_slice($m[0], 0, 20));
    if (preg_match_all('#<h[1-3][^>]*>(.*?)</h[1-3]>#is', $codigo, $m)) $partes[] = 'Títulos (só para entender o ritmo das seções, NÃO copie o texto): ' . mb_substr(implode(' | ', array_map(fn($t) => trim(strip_tags($t)), array_slice($m[1], 0, 15))), 0, 800);
    return implode("\n\n", $partes);
}

function ia_historico_texto($empresaId, $limite = 8) {
    $msgs = array_slice(ia_listar_mensagens($empresaId, 60), -$limite);
    return implode("\n", array_map(fn($m) => ($m['papel'] === 'usuario' ? 'DONO: ' : 'VOCÊ: ') . mb_substr(preg_replace('/\s+/', ' ', $m['texto']), 0, 400), $msgs));
}

// ETAPA 1 — entender o pedido
function ia_entender_pedido($ctx, $html, $instrucao, $historico = '', $elemento = null, $referencia = '') {
    $dados = ia_contexto_texto($ctx);
    $resumo = ia_resumo_site($html);
    $nome = $ctx['nome'] ?? '';
    $glossario = IA_GLOSSARIO_ESTILOS;
    $el = $elemento ? "\nO DONO SELECIONOU ESTE ELEMENTO NO PREVIEW (o pedido vale só para ele, salvo se disser o contrário):\n" . ia_texto_elemento($elemento) : '';
    $ref = $referencia !== '' ? "\nO DONO MANDOU UM SITE DE REFERÊNCIA (copiar o estilo, não o conteúdo):\n" . mb_substr($referencia, 0, 3000) : '';
    $hist = $historico !== '' ? "\nCONVERSA ATÉ AGORA (mais recente por último):\n$historico" : '';
    $prompt = <<<PROMPT
ENTENDER O PEDIDO. Você é o assistente de um construtor de sites (como a Lovable). Antes de mexer em qualquer coisa,
entenda exatamente o que o dono quer, como um designer experiente entenderia.

NEGÓCIO: "$nome"
$dados

RAIO-X DO SITE ATUAL:
$resumo
$hist$el$ref

PEDIDO AGORA: "$instrucao"

$glossario

Classifique e planeje. Regras:
- Pedidos de estilo/visual/"modelo de"/"igual a"/"parecido com" = mudança de VISUAL; o conteúdo e o negócio ficam.
- Interprete referências a mensagens anteriores ("volta como estava", "a cor de antes", "isso aí") usando a conversa.
- Só pergunte (tipo "pergunta") quando houver duas leituras realmente diferentes e plausíveis; dê 2 a 4 opções curtas
  e clicáveis. Na dúvida entre perguntar e fazer o mais provável, FAÇA o mais provável e diga o que entendeu.
- Se for só uma pergunta/conversa ("o que você acha?", "por que...?"), responda em "resposta" com tipo "conversa".
- "estrategia": "editar" quando a mudança é localizada (um texto, uma cor, uma seção, um botão, adicionar/remover um
  bloco); "reescrever" quando muda o visual/layout do site inteiro.

Responda APENAS com JSON:
{"tipo":"visual_global|visual_local|texto|adicionar|remover|corrigir|conversa|pergunta",
"entendi":"1 a 2 frases em 1ª pessoa dizendo o que você vai fazer, ex.: Vou deixar o site com cara de landing page de SaaS, mantendo a barbearia, os serviços e o agendamento.",
"alteracoes":["lista curta do que vai mudar"],
"preservar":["o que NÃO vai mudar"],
"estrategia":"editar|reescrever",
"brief_design":"se for visual: instruções concretas de design (layout, cores, fontes, componentes) para o desenvolvedor",
"pergunta":"", "opcoes":[], "resposta":"",
"proximos":["3 sugestões curtas do que o dono pode pedir em seguida"]}
PROMPT;
    $r = chamar_ia_json($prompt, 1800, 60, 0.4, ['esforco' => 'low']);
    if (!$r['ok']) return $r;
    $d = $r['dados'];
    $tipos = ['visual_global', 'visual_local', 'texto', 'adicionar', 'remover', 'corrigir', 'conversa', 'pergunta'];
    $d['tipo'] = in_array($d['tipo'] ?? '', $tipos, true) ? $d['tipo'] : 'visual_local';
    $d['estrategia'] = ($d['estrategia'] ?? '') === 'reescrever' || $d['tipo'] === 'visual_global' ? 'reescrever' : 'editar';
    if ($elemento && $d['tipo'] === 'visual_global') { $d['tipo'] = 'visual_local'; $d['estrategia'] = 'editar'; }
    if ($d['tipo'] === 'pergunta' && count((array)($d['opcoes'] ?? [])) < 2) $d['tipo'] = 'visual_local'; // pergunta sem opções: faz o mais provável
    foreach (['alteracoes', 'preservar', 'opcoes', 'proximos'] as $k) $d[$k] = array_values(array_filter(array_map(fn($x) => mb_substr(trim((string)$x), 0, 160), (array)($d[$k] ?? []))));
    foreach (['entendi', 'brief_design', 'pergunta', 'resposta'] as $k) $d[$k] = trim((string)($d[$k] ?? ''));
    return ['ok' => true, 'entendimento' => $d];
}

function ia_texto_elemento($el) {
    $el = (array)$el;
    return 'Tag: <' . preg_replace('/[^a-z0-9]/i', '', (string)($el['tag'] ?? '')) . '>'
         . (!empty($el['classes']) ? ' classes: ' . mb_substr((string)$el['classes'], 0, 120) : '')
         . (!empty($el['id']) ? ' id: ' . mb_substr((string)$el['id'], 0, 60) : '')
         . (!empty($el['secao']) ? ' dentro da seção: ' . mb_substr((string)$el['secao'], 0, 80) : '')
         . "\nTexto: \"" . mb_substr((string)($el['texto'] ?? ''), 0, 200) . '"'
         . (!empty($el['componente']) ? "\nATENÇÃO: é parte de um componente pronto do sistema ({$el['componente']}); o conteúdo dele vem do cadastro. Você só pode mudar a seção em volta (título, fundo, espaçamento), não o componente." : '')
         . "\nHTML (como aparece no preview, pode diferir um pouco do código):\n" . mb_substr((string)($el['html'] ?? ''), 0, 1500);
}

// Aplica blocos BUSCAR/TROCAR no HTML. Aceita pequenas diferenças de espaços.
function ia_aplicar_blocos($html, $texto) {
    preg_match_all('/<{5,}\s*BUSCAR\s*\n(.*?)\n={5,}\s*\n(.*?)\n?>{5,}\s*(?:FIM|TROCAR)?/s', $texto, $m, PREG_SET_ORDER);
    $aplicados = 0; $falhas = [];
    foreach ($m as $b) {
        [$_, $busca, $troca] = $b;
        if (trim($busca) === '') { $falhas[] = '(vazio)'; continue; }
        if (substr_count($html, $busca) >= 1) { $html = preg_replace('/' . preg_quote($busca, '/') . '/', str_replace(['\\', '$'], ['\\\\', '\\$'], $troca), $html, 1); $aplicados++; continue; }
        // tolerância a espaços/quebras diferentes
        $padrao = '/' . implode('\s+', array_map(fn($p) => preg_quote($p, '/'), preg_split('/\s+/', trim($busca)))) . '/s';
        if (@preg_match($padrao, $html) === 1) { $html = preg_replace($padrao, str_replace(['\\', '$'], ['\\\\', '\\$'], $troca), $html, 1); $aplicados++; continue; }
        $falhas[] = mb_substr(trim($busca), 0, 80);
    }
    return [$html, $aplicados, $falhas, count($m)];
}

// ETAPA 2a — edição cirúrgica
function ia_editar_por_blocos($ctx, $html, $instrucao, $ent, $elemento = null, $referencia = '') {
    $modo = $ctx['atendimento']['modo'] ?? 'agendamento';
    $estrategia = ia_estrategia_conversao($modo, !empty($ctx['whatsapp']));
    $plano = '- ' . implode("\n- ", $ent['alteracoes'] ?: [$instrucao]);
    $manter = $ent['preservar'] ? '- ' . implode("\n- ", $ent['preservar']) : '- todo o resto';
    $el = $elemento ? "\nELEMENTO SELECIONADO (mude só ele e o que for necessário para ele funcionar):\n" . ia_texto_elemento($elemento) : '';
    $ref = $referencia !== '' ? "\nREFERÊNCIA DE ESTILO:\n" . mb_substr($referencia, 0, 5000) : '';
    $dados = ia_contexto_texto($ctx);
    $prompt = <<<PROMPT
BLOCOS DE EDIÇÃO. Você é o desenvolvedor que mantém o site abaixo. Faça a alteração com o MÍNIMO de mudanças, como um
editor de código: devolva só os trechos que mudam, no formato exato abaixo (pode repetir quantos blocos precisar):

<<<<<<< BUSCAR
trecho copiado EXATAMENTE do HTML atual (curto, mas único no arquivo)
=======
trecho novo
>>>>>>> FIM

Depois dos blocos, escreva uma linha: RESUMO: o que você mudou, em 1 frase para o dono.

PEDIDO DO DONO: "$instrucao"
O QUE FOI COMBINADO FAZER:
$plano
O QUE NÃO PODE MUDAR:
$manter$el$ref
{$ent['brief_design']}

Regras: não toque nos marcadores {{...}} a não ser que o pedido seja sobre eles; mantenha o HTML válido; para mudar cor
ou fonte do site, prefira trocar as variáveis CSS; textos em português do Brasil e coerentes com o negócio; não invente
preços, números ou depoimentos. Forma de atendimento: "$modo". $estrategia

DADOS REAIS DO NEGÓCIO (consulta):
$dados

HTML ATUAL:
$html
PROMPT;
    $r = chamar_ia($prompt, 12000, 150, 0.3, null, ['esforco' => 'low']);
    if (!$r['ok']) return $r;
    [$novo, $aplicados, $falhas, $total] = ia_aplicar_blocos($html, $r['texto']);
    if ($total === 0 || $aplicados === 0 || $falhas) return ['ok' => false, 'erro' => 'blocos', 'falhas' => $falhas];
    $resumo = preg_match('/RESUMO:\s*(.+)/u', $r['texto'], $m) ? trim($m[1]) : '';
    if ($novo === $html) return ['ok' => false, 'erro' => 'blocos'];
    return ['ok' => true, 'html' => ia_pos_processar_html($novo, IA_MARCADORES_OBRIGATORIOS), 'resumo' => $resumo, 'metodo' => 'edicao'];
}

// Orquestra: edição cirúrgica quando dá, reescrita quando precisa; confere se o negócio continuou o mesmo
function ia_editar_site($ctx, $html, $instrucao, $ent, $elemento = null, $referencia = '') {
    $r = null;
    if ($ent['estrategia'] === 'editar') {
        $r = ia_editar_por_blocos($ctx, $html, $instrucao, $ent, $elemento, $referencia);
        if (!$r['ok'] && ($r['erro'] ?? '') !== 'blocos') return $r;
    }
    if (!$r || !$r['ok']) {
        $r = ia_refinar_html_site($ctx, $html, $instrucao, $ent, $elemento, $referencia);
        if (!$r['ok']) return $r;
        $r['metodo'] = 'reescrita';
    }
    // O nome do negócio sumiu sem o dono pedir? Isso é sinal de que a IA "trocou de negócio": refaz com aviso forte.
    $nome = trim((string)($ctx['nome'] ?? ''));
    $trocaNome = preg_match('/\b(nome|chamar|renomear)\b/iu', $instrucao);
    if ($nome !== '' && mb_stripos(strip_tags($html), $nome) !== false && mb_stripos(strip_tags($r['html']), $nome) === false && !$trocaNome) {
        $ent['preservar'][] = "o nome \"$nome\" e o que o negócio vende — o site continua sendo de \"$nome\"";
        $r2 = ia_refinar_html_site($ctx, $html, $instrucao, $ent, $elemento, $referencia);
        if ($r2['ok'] && mb_stripos(strip_tags($r2['html']), $nome) !== false) { $r = $r2; $r['metodo'] = 'reescrita'; }
    }
    return $r;
}
