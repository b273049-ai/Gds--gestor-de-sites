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

const IA_MARCADORES = ['{{SOBRE}}', '{{SERVICOS}}', '{{EQUIPE}}', '{{GALERIA}}', '{{AGENDAMENTO}}', '{{ORCAMENTO}}', '{{AVALIACOES}}', '{{CONTATO}}', '{{MAPA}}', '{{CUPONS}}', '{{HORARIOS}}', '{{WHATSAPP_FLOAT}}'];
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
   {{CUPONS}} = cupons | {{MAPA}} = mapa com "Como chegar" | {{HORARIOS}} = horário da semana | {{WHATSAPP_FLOAT}} =
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
   Use com intenção: título do topo com data-split, seções com data-anim, listas com data-stagger, uma faixa
   data-marquee, hover caprichado nos botões. Respeita "reduzir movimento" sozinho.
9. Use somente as imagens informadas (URLs exatas: fotos do negócio e fotos profissionais do banco de imagens).
   Topo com foto grande + sobreposição em gradiente para o texto ficar legível; object-fit: cover; loading="lazy"
   (exceto a primeira). Sem nenhuma imagem: crie visuais com CSS (gradientes em malha, formas, ruído) e SVG inline.
10. Textos em português do Brasil, específicos deste negócio (nada genérico que serviria pra qualquer concorrente).
    Não invente preços, prêmios, números ou depoimentos que não foram informados.
11. CONFIANÇA E ATENÇÃO (o visitante decide em 3 segundos): logo no topo mostre o que o negócio é (o ramo tem que ser
    óbvio pela foto, cor, fonte e frase), o status "aberto agora" e a nota das avaliações quando houver, e a ação
    principal sempre visível. No celular, uma barra fixa embaixo com a ação principal + WhatsApp. Prova (nota,
    depoimento real) perto do topo, preços claros, perguntas frequentes com dados reais e endereço com "Como chegar".
12. Evite clichês: hero centralizado com gradiente roxo-azul genérico, cards idênticos com a mesma sombra, grade de 3
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

$regras
PROMPT;

    return ia_executar_geracao_html($prompt, $plano['obrigatorios'] ?? $plano['marcadores'], 1.0, $modo);
}

// ------------------------------------------------------------
// EDIÇÃO POR CONVERSA — aplica um pedido em cima do HTML atual
// ------------------------------------------------------------
function ia_refinar_html_site($ctx, $htmlAtual, $instrucao) {
    $dados = ia_contexto_texto($ctx);
    $regras = ia_regras_tecnicas();
    $marcadoresAtuais = array_values(array_filter(IA_MARCADORES, fn($m) => str_contains($htmlAtual, $m)));
    $lista = implode(' ', $marcadoresAtuais);
    $modo = $ctx['atendimento']['modo'] ?? 'agendamento';
    $obrigatorios = ia_marcadores_do_modo($modo, $ctx);
    $estrategia = ia_estrategia_conversao($modo, !empty($ctx['whatsapp']));
    $outroModo = $modo === 'agendamento' ? '{{ORCAMENTO}}' : ($modo === 'orcamento' ? '{{AGENDAMENTO}}' : '{{AGENDAMENTO}} e {{ORCAMENTO}}');

    $prompt = <<<PROMPT
Você é o desenvolvedor front-end que criou o site abaixo. O dono pediu uma alteração. Aplique SOMENTE o que foi pedido,
com capricho, mantendo todo o resto (design, textos, estrutura, scripts) idêntico. Se o pedido for vago ("mais moderno",
"mais bonito"), faça a melhoria visual mais impactante coerente com o estilo atual.

PEDIDO DO DONO: "$instrucao"

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
