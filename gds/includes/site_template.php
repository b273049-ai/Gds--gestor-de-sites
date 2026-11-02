<?php
// ============================================================
// TEMPLATE PREMIUM DOS SITES (quando o site não usa o design da IA)
// Página única com rolagem suave, topo com foto/gradiente animado,
// faixa rolando, números animados, "como funciona" conforme o tipo
// de negócio, componentes reais e rodapé completo. 5 estilos visuais.
// ============================================================
require_once __DIR__ . '/site_nichos.php';

function site_estilos() {
    return [
        'aurora'   => ['nome' => 'Aurora', 'desc' => 'Escuro moderno, gradientes vivos e brilho. Tecnologia, serviços, automotivo.',
                       'fontes' => 'Sora:wght@500;600;700;800&family=Inter:wght@400;500;600', 'titulo' => "'Sora'", 'corpo' => "'Inter'",
                       'bg' => '#070a18', 'bg2' => '#0d1126', 'superficie' => 'rgba(255,255,255,.04)', 'texto' => '#eef1ff', 'suave' => '#a3a9c8', 'linha' => 'rgba(255,255,255,.09)', 'accent' => '#6d7cff', 'escuro' => true],
        'elegante' => ['nome' => 'Elegante', 'desc' => 'Claro, sofisticado, serifado. Beleza, estética, noivas, turismo.',
                       'fontes' => 'Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700', 'titulo' => "'Cormorant Garamond'", 'corpo' => "'Manrope'",
                       'bg' => '#f7f2ec', 'bg2' => '#efe7dd', 'superficie' => '#fffdfa', 'texto' => '#211c18', 'suave' => '#6f655b', 'linha' => 'rgba(33,28,24,.1)', 'accent' => '#a67c52', 'escuro' => false],
        'vibrante' => ['nome' => 'Vibrante', 'desc' => 'Cores fortes, letras grandes, energia. Comida, delivery, pets, esporte.',
                       'fontes' => 'Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Inter:wght@400;500;600', 'titulo' => "'Bricolage Grotesque'", 'corpo' => "'Inter'",
                       'bg' => '#fff8ef', 'bg2' => '#ffeedd', 'superficie' => '#ffffff', 'texto' => '#1a120b', 'suave' => '#6e5e50', 'linha' => 'rgba(26,18,11,.1)', 'accent' => '#ff5a1f', 'escuro' => false],
        'minimal'  => ['nome' => 'Minimalista', 'desc' => 'Branco, limpo, muito respiro. Saúde, clínicas, educação, lojas.',
                       'fontes' => 'Plus+Jakarta+Sans:wght@400;500;600;700;800', 'titulo' => "'Plus Jakarta Sans'", 'corpo' => "'Plus Jakarta Sans'",
                       'bg' => '#ffffff', 'bg2' => '#f4f6f8', 'superficie' => '#ffffff', 'texto' => '#0f1720', 'suave' => '#5b6672', 'linha' => 'rgba(15,23,32,.09)', 'accent' => '#0f8f7e', 'escuro' => false],
        'noite'    => ['nome' => 'Noite', 'desc' => 'Preto e dourado, luxo e festa. Eventos, DJ, bares, tatuagem, barbearia premium.',
                       'fontes' => 'Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600', 'titulo' => "'Playfair Display'", 'corpo' => "'Inter'",
                       'bg' => '#0b0a09', 'bg2' => '#14120f', 'superficie' => 'rgba(255,255,255,.035)', 'texto' => '#f5efe4', 'suave' => '#b3a995', 'linha' => 'rgba(245,239,228,.1)', 'accent' => '#d4a24c', 'escuro' => true],
    ];
}

// Estilo do site: o escolhido, ou um que combine com o tipo de negócio
function site_estilo_da_empresa($empresa) {
    $estilos = site_estilos();
    if (!empty($empresa['estilo']) && isset($estilos[$empresa['estilo']])) return $empresa['estilo'];
    $slug = $empresa['segmento_slug'] ?? '';
    if (in_array($slug, ['dj', 'eventos', 'bar', 'tatuagem', 'banda', 'barbearia'], true)) return 'noite';
    return [
        'Beleza e Estética' => 'elegante', 'Saúde' => 'minimal', 'Alimentação e Bebidas' => 'vibrante', 'Eventos e Festas' => 'noite',
        'Pets' => 'vibrante', 'Educação' => 'minimal', 'Comércio e Lojas' => 'minimal', 'Turismo e Hospedagem' => 'elegante',
        'Esporte e Bem-estar' => 'aurora', 'Pessoal e Criativo' => 'noite', 'Imóveis e Condomínios' => 'elegante',
    ][$empresa['segmento_grupo'] ?? ''] ?? 'aurora';
}

// Identidade visual: o estilo escolhido no painel, ou a identidade do tipo de negócio (nicho)
function site_identidade($empresa) {
    $estilos = site_estilos();
    $nk = site_nicho_da_empresa($empresa);
    $N = site_nichos()[$nk];
    if (!empty($empresa['estilo']) && isset($estilos[$empresa['estilo']])) return [$empresa['estilo'], $estilos[$empresa['estilo']], $nk, $N];
    $E = $N['cores'] + ['nome' => $N['nome'], 'fontes' => $N['fontes'], 'titulo' => $N['titulo'], 'corpo' => $N['corpo']];
    return ['nicho', $E, $nk, $N];
}

// Pacote de animações: cinema (padrão, com rolagem suave e cenas) | suave | leve
function site_animacoes_da_empresa($empresa) {
    $a = $empresa['animacoes'] ?? '';
    return in_array($a, ['cinema', 'suave', 'leve'], true) ? $a : 'cinema';
}

// A frase de apoio do nicho só serve se combinar com a forma de atendimento
function site_frase_combina($txt, $modo) {
    $t = mb_strtolower((string)$txt);
    if ($t === '') return false;
    if (preg_match('/agend|horário livre|barbeiro e o horário/u', $t)) return $modo === 'agendamento';
    if (str_contains($t, 'orçamento')) return $modo === 'orcamento';
    if (preg_match('/pedido|peça|peca|monte seu|entrega/u', $t)) return $modo === 'catalogo';
    return true;
}

// Texto com os dias e o horário de funcionamento ("seg a sáb, 09:00–19:00")
function site_texto_horario($agenda) {
    $nomes = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
    $dias = array_values(array_unique(array_map('intval', array_filter(explode(',', (string)$agenda['dias']), 'strlen'))));
    sort($dias);
    $seq = $dias && $dias === range($dias[0], end($dias));
    $txt = !$dias ? '' : ($seq && count($dias) > 2 ? $nomes[$dias[0]] . ' a ' . $nomes[end($dias)] : implode(', ', array_map(fn($d) => $nomes[$d], $dias)));
    return trim($txt . ', das ' . $agenda['abertura'] . ' às ' . $agenda['fechamento'], ', ');
}

function site_render_template_premium($empresa, $dados, $estado, $preview = false) {
    [$chaveEstilo, $E, $chaveNicho, $N] = site_identidade($empresa);
    $nome = $empresa['nome'];
    $modo = $dados['modo'];
    $f = site_fragmentos_marcadores($empresa, $dados, $estado, ['variante' => $N['servicos']]);
    $anim = site_animacoes_da_empresa($empresa);
    $modulos = $dados['modulos'] ?? [];

    // Cor de destaque: a da empresa (se ela escolheu uma) ou a da identidade
    $corEmpresa = cor_hex_valida_site($empresa['cor_primaria'] ?? '', '');
    $padroes = ['', '#4f7cff', '#1a1a2e', '#2563eb'];
    $accent = in_array(strtolower($corEmpresa), $padroes, true) ? $E['accent'] : $corEmpresa;
    if (abs(frag_luminancia($E['bg']) - frag_luminancia($accent)) < 0.12) $accent = $E['accent']; // destaque sumiria no fundo

    // Fotos: banner > galeria > fotos exclusivas (IA) > banco de fotos profissionais
    $fotosBanco = (empty($empresa['banner_imagem']) || count($dados['galeria']) < 3) ? fotos_do_site($empresa) : [];
    $fotoHero = $empresa['banner_imagem'] ?: ($dados['galeria'][0] ?? ($fotosBanco[0]['url'] ?? ''));
    $candidatas = array_values(array_unique(array_filter(array_merge(array_slice($dados['galeria'], 1), array_map(fn($x) => $x['medio'], $fotosBanco)), fn($u) => $u !== $fotoHero)));
    $fotoSobre = $candidatas[0] ?? '';
    $fotoFinal = $candidatas[1] ?? $fotoHero;
    $creditos = [];
    foreach ($fotosBanco as $fb) if (!empty($fb['autor']) && in_array($fb['url'], [$fotoHero], true) || (!empty($fb['autor']) && in_array($fb['medio'], [$fotoSobre, $fotoFinal], true))) $creditos[] = $fb['autor'];
    $creditos = array_values(array_unique($creditos));

    // Frase principal: a da empresa > a do segmento > a do nicho
    $tituloProprio = trim((string)($empresa['titulo'] ?? ''));
    if (mb_strtolower($tituloProprio) === mb_strtolower($nome)) $tituloProprio = '';
    $hs = site_headline_segmento($empresa['segmento_slug'] ?? '');
    $headline = $tituloProprio ?: ($hs[0] ?? ($N['headline'] ?: $nome));
    $subPadrao = [
        'agendamento' => 'Agende online em menos de um minuto e receba a confirmação no WhatsApp.',
        'orcamento' => 'Conte o que você precisa e receba seu orçamento rapidinho no WhatsApp.',
        'catalogo' => 'Escolha, peça pelo site e acompanhe tudo pelo WhatsApp.',
        'contato' => 'Fale com a gente — atendimento rápido e sem complicação.',
    ][$modo];
    $sub = $empresa['subtitulo'] ?: (!$tituloProprio && $hs && site_frase_combina($hs[1], $modo) ? $hs[1] : (!$tituloProprio && site_frase_combina($N['sub'], $modo) ? $N['sub'] : $subPadrao));
    $mostraNomeNoTopo = $headline !== $nome;

    $titulo = $empresa['seo_titulo'] ?: ($nome . ($empresa['segmento_nome'] && ($empresa['segmento_slug'] ?? '') !== 'outro' ? ' — ' . $empresa['segmento_nome'] : '') . ($empresa['endereco'] ? ' em ' . trim(explode(',', $empresa['endereco'])[0]) : ''));
    $descricao = $empresa['seo_descricao'] ?: ('Conheça ' . $nome . ($empresa['segmento_nome'] ? ' — ' . $empresa['segmento_nome'] : '') . ($empresa['endereco'] ? ' em ' . $empresa['endereco'] : '') . '. ' . $sub);
    $ctaTexto = site_texto_cta($empresa, $modo);
    $temAgendar = in_array($modo, ['agendamento', 'orcamento'], true);
    $ctaHref = $temAgendar ? '#agendar' : ($modo === 'catalogo' ? '#servicos' : '#contato');
    $linkWhats = so_digitos_frag($empresa['whatsapp'] ?? '') ? frag_link_whatsapp($empresa['whatsapp'], trim((string)($empresa['whatsapp_mensagem'] ?? '')) ?: 'Olá! Vim pelo site e gostaria de mais informações.') : '';
    $linkRota = $empresa['endereco'] ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($empresa['endereco']) : '';
    $tel = so_digitos_frag($empresa['telefone'] ?? '') ?: so_digitos_frag($empresa['whatsapp'] ?? '');
    $nota = $dados['avaliacoes_resumo'];
    $status = $dados['status'] ?? [];
    $rotCatalogo = site_titulo_catalogo($empresa, $modo);
    $bairro = $empresa['endereco'] ? trim(explode('-', $empresa['endereco'])[1] ?? explode(',', $empresa['endereco'])[0]) : '';
    $segNome = $empresa['segmento_nome'] && ($empresa['segmento_slug'] ?? '') !== 'outro' ? $empresa['segmento_nome'] : ($empresa['segmento_descricao'] ?: '');
    $icone = fn($paths, $cls = '') => '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
    $estrelas = fn($m) => '<span class="pt-estrelas" style="--nota:' . round($m / 5 * 100) . '%" aria-hidden="true">★★★★★</span>';
    $formas = array_values(array_filter(array_map('trim', explode(',', (string)($empresa['formas_pagamento'] ?? '')))));
    $lig = fn($c) => !isset($empresa[$c]) || $empresa[$c] === null || pg_bool($empresa[$c]);
    $waAuto = ($empresa['wa_modo'] ?? 'plataforma') !== 'desligado';

    // ---------- cartões que flutuam no topo (prova real, sem inventar nada) ----------
    $cartoes = [];
    if ($nota['total'] > 0) {
        $dep = null;
        foreach ($dados['avaliacoes'] as $av) if ((int)$av['nota'] >= 4 && mb_strlen(trim((string)$av['comentario'])) >= 12) { $dep = $av; break; }
        $cartoes[] = '<div class="pt-flut pt-flut-nota"><div class="pt-flut-topo"><strong>' . number_format($nota['media'], 1, ',', '') . '</strong>' . $estrelas($nota['media']) . '</div>'
            . '<span class="pt-flut-mini">' . (int)$nota['total'] . ' avaliaç' . ($nota['total'] > 1 ? 'ões' : 'ão') . ' de clientes</span>'
            . ($dep ? '<p>“' . e(mb_strimwidth(trim($dep['comentario']), 0, 90, '…')) . '”<small>— ' . e(explode(' ', trim($dep['autor']))[0]) . '</small></p>' : '') . '</div>';
    }
    if ($modo === 'agendamento' && in_array('agenda', $modulos, true)) {
        $prox = null;
        try { $prox = agenda_proximo_livre($empresa); } catch (Throwable $e) { $prox = null; }
        if ($prox) $cartoes[] = '<a class="pt-flut pt-flut-hora" href="#agendar"><span class="pt-flut-ico">' . frag_icone('calend') . '</span><span><span class="pt-flut-mini">Próximo horário livre</span><strong>' . e($prox['texto']) . '</strong></span><span class="pt-flut-seta" aria-hidden="true">→</span></a>';
    } elseif ($modo === 'catalogo') {
        $partes = array_filter([!empty($empresa['tempo_entrega']) ? '⏱ ' . $empresa['tempo_entrega'] : '', $lig('aceita_entrega') ? ((float)($empresa['taxa_entrega'] ?? 0) > 0 ? 'Entrega ' . frag_preco($empresa['taxa_entrega']) : 'Entrega grátis') : '', $lig('aceita_retirada') ? 'Retirada' : '']);
        if ($partes) $cartoes[] = '<div class="pt-flut pt-flut-hora"><span class="pt-flut-ico">' . frag_icone('sacola') . '</span><span><span class="pt-flut-mini">' . e(!empty($status['aberto']) ? 'Aberto agora — peça já' : 'Pedidos pelo site') . '</span><strong>' . e(implode(' · ', $partes)) . '</strong></span></div>';
    } elseif ($modo === 'orcamento') {
        $cartoes[] = '<a class="pt-flut pt-flut-hora" href="#agendar"><span class="pt-flut-ico">' . frag_icone('whats') . '</span><span><span class="pt-flut-mini">Sem compromisso</span><strong>Orçamento no seu WhatsApp</strong></span><span class="pt-flut-seta" aria-hidden="true">→</span></a>';
    }

    // ---------- seções ----------
    $sec = []; $nav = [];
    $secao = function ($id, $html, $rotuloNav = null) use (&$sec, &$nav) { $sec[$id] = $html; if ($rotuloNav) $nav[$id] = $rotuloNav; };
    $cabecalho = function ($sobre, $titulo, $texto = '') {
        return '<div class="pt-cab" data-stagger="90"><span class="pt-sobretitulo">' . e($sobre) . '</span><h2>' . e($titulo) . '</h2>' . ($texto ? '<p>' . e($texto) . '</p>' : '') . '</div>';
    };

    if (trim($empresa['sobre'] ?? '') !== '') {
        $nums = [];
        if ($nota['total'] > 0) $nums[] = ['<span data-counter="' . number_format($nota['media'], 1, '.', '') . '">0</span>★', $nota['total'] . ' avaliações'];
        if (count($dados['servicos']) > 2) $nums[] = ['<span data-counter="' . count($dados['servicos']) . '">0</span>', $modo === 'catalogo' ? 'opções no ' . mb_strtolower($rotCatalogo) : 'serviços'];
        if (count($dados['profissionais']) > 1) $nums[] = ['<span data-counter="' . count($dados['profissionais']) . '">0</span>', 'profissionais'];
        $paragrafos = array_filter(array_map('trim', preg_split('/\n\s*\n/', (string)$empresa['sobre'])));
        $h = '<div class="pt-sobre' . ($fotoSobre ? '' : ' sem-foto') . '"><div class="pt-sobre-texto">'
           . '<span class="pt-sobretitulo" data-anim="fade-up">Sobre nós</span><h2 data-split>' . e($nome) . '</h2>';
        foreach ($paragrafos as $i => $p) $h .= '<p data-anim="fade-up" data-delay="' . (120 + $i * 80) . '">' . nl2br(e($p)) . '</p>';
        if ($nums) {
            $h .= '<div class="pt-numeros" data-stagger="120">';
            foreach ($nums as [$v, $r]) $h .= '<div><strong>' . $v . '</strong><span>' . e($r) . '</span></div>';
            $h .= '</div>';
        }
        $h .= '</div>' . ($fotoSobre ? '<figure class="pt-sobre-foto" data-anim="clip-up" data-cena="foto"><img src="' . e($fotoSobre) . '" alt="' . e($nome) . '" loading="lazy" data-parallax="0.06"><span class="pt-sobre-selo">' . $icone($N['icone']) . '</span></figure>' : '') . '</div>';
        $secao('sobre', '<section id="sobre" class="pt-secao"><div class="pt-container">' . $h . '</div></section>', 'Sobre');
    }

    if (!empty($dados['imoveis'])) {
        // Imobiliária: vitrine de imóveis com filtros (e os serviços, se houver, logo abaixo)
        $secao('servicos', '<section id="servicos" class="pt-secao pt-secao-alt pt-motivo"><div class="pt-container">' . $cabecalho('Imóveis', 'Encontre o seu imóvel', 'Filtre por bairro, preço e quartos. Gostou? Agende a visita em 1 minuto.') . '<div data-anim="fade-up">' . $f['{{SERVICOS}}'] . '</div></div></section>', 'Imóveis');
    } elseif ($dados['servicos']) {
        $titSv = $N['servicos_titulo'] ?? '';
        if ($titSv === '' || ($modo === 'catalogo' && $rotCatalogo === 'Cardápio')) $titSv = ['catalogo' => $rotCatalogo === 'Cardápio' ? 'Escolha o seu favorito' : 'Escolha e peça', 'orcamento' => 'O que fazemos', 'agendamento' => 'Escolha seu serviço', 'contato' => 'Como podemos ajudar'][$modo];
        $txtSv = ['catalogo' => 'Adicione ao pedido e finalize em segundos — entrega ou retirada.', 'orcamento' => 'Escolha um serviço e peça seu orçamento sem compromisso.', 'agendamento' => 'Preços claros, sem surpresa. Escolha e reserve o melhor horário.', 'contato' => ''][$modo];
        $secao('servicos', '<section id="servicos" class="pt-secao pt-secao-alt pt-motivo"><div class="pt-container">' . $cabecalho($rotCatalogo, $titSv, $txtSv) . '<div data-anim="fade-up" data-cena="lista">' . $f['{{SERVICOS}}'] . '</div></div></section>', $rotCatalogo);
    }

    // Por que nós: feito só com o que é verdade sobre a empresa
    $motivos = [];
    if ($nota['total'] >= 3) $motivos[] = ['estrela', number_format($nota['media'], 1, ',', '') . ' de nota', 'Avaliado por ' . $nota['total'] . ' clientes reais aqui no site.'];
    if ($modo === 'agendamento' && in_array('agenda', $modulos, true)) $motivos[] = ['calend', 'Agenda aberta 24h', 'Escolha o horário quando quiser — sem esperar alguém responder.'];
    if ($modo === 'catalogo' && site_pedido_online($empresa, $modo)) $motivos[] = ['sacola', 'Pedido em segundos', 'Monte o pedido aqui no site, sem baixar aplicativo.'];
    if ($modo === 'orcamento') $motivos[] = ['check', 'Orçamento sem compromisso', 'Você conta o que precisa e decide com calma.'];
    if ($waAuto && $linkWhats) $motivos[] = ['whats', ['agendamento' => 'Confirmação no WhatsApp', 'catalogo' => 'Acompanhe pelo WhatsApp', 'orcamento' => 'Resposta no WhatsApp', 'contato' => 'Atendimento pelo WhatsApp'][$modo], ['agendamento' => 'Confirmação na hora e lembrete antes do horário.', 'catalogo' => 'Cada etapa do pedido chega no seu celular.', 'orcamento' => 'O valor chega direto na sua conversa.', 'contato' => 'Fale direto com quem resolve.'][$modo]];
    if (count($dados['profissionais']) > 1) $motivos[] = ['estrela', count($dados['profissionais']) . ' profissionais', 'Escolha com quem você quer ser atendido.'];
    if ($modo === 'catalogo' && $formas) $motivos[] = ['check', 'Pague como preferir', implode(', ', array_slice($formas, 0, 3)) . '.'];
    if ($bairro) $motivos[] = ['pin', 'Fácil de chegar', 'Estamos em ' . mb_strimwidth($bairro, 0, 40, '…') . '.' ];
    if ($dados['blocos']) {
        $h = '<section id="blocos" class="pt-secao"><div class="pt-container">' . $cabecalho('Diferenciais', 'Por que escolher a gente') . '<div class="pt-blocos" data-stagger="110">';
        foreach ($dados['blocos'] as $b) $h .= '<article data-tilt><h3>' . e($b['titulo']) . '</h3><p>' . nl2br(e($b['conteudo'])) . '</p></article>';
        $secao('blocos', $h . '</div></div></section>');
    } elseif (count($motivos) >= 3) {
        $h = '<section id="blocos" class="pt-secao"><div class="pt-container">' . $cabecalho('Por que a gente', 'Feito para ser fácil pra você') . '<div class="pt-motivos" data-stagger="110">';
        foreach (array_slice($motivos, 0, 4) as [$ic, $t, $d]) $h .= '<article><span class="pt-motivo-ico">' . frag_icone($ic) . '</span><h3>' . e($t) . '</h3><p>' . e($d) . '</p></article>';
        $secao('blocos', $h . '</div></div></section>');
    }

    // Como funciona (conforme a forma de atendimento)
    $passos = [
        'agendamento' => [['Escolha o serviço', 'Veja preços e duração de cada opção.'], ['Escolha dia e horário', 'Só aparecem horários realmente livres.'], ['Pronto!', 'Confirmação e lembrete chegam no seu WhatsApp.']],
        'orcamento'   => [['Conte o que precisa', 'Data, local e detalhes — leva 1 minuto.'], ['Receba o orçamento', 'O valor chega direto no seu WhatsApp.'], ['Aprove e relaxe', 'Aprovou? A gente cuida de tudo.']],
        'catalogo'    => [['Monte seu pedido', 'Adicione os itens que quiser.'], ['Entrega ou retirada', 'Escolha como prefere receber e pagar.'], ['Acompanhe', 'Cada etapa chega no seu WhatsApp.']],
        'contato'     => [['Chame a gente', 'Pelo WhatsApp ou pelo formulário.'], ['Entendemos seu caso', 'Tiramos todas as dúvidas.'], ['Proposta sob medida', 'Sem compromisso e sem enrolação.']],
    ][$modo];
    $h = '<section id="como-funciona" class="pt-secao pt-secao-alt"><div class="pt-container">' . $cabecalho('Simples assim', 'Como funciona') . '<ol class="pt-passos" data-stagger="140" data-cena="passos">';
    foreach ($passos as $i => [$t, $d]) $h .= '<li data-tilt><span class="pt-passo-num">0' . ($i + 1) . '</span><h3>' . e($t) . '</h3><p>' . e($d) . '</p></li>';
    $secao('como', $h . '</ol></div></section>');

    if ($dados['profissionais'] && in_array('equipe', $modulos ?: ['equipe'], true)) {
        $secao('equipe', '<section id="equipe" class="pt-secao"><div class="pt-container">' . $cabecalho('Equipe', $chaveNicho === 'barbearia' ? 'Escolha seu barbeiro' : 'Quem cuida de você') . '<div data-anim="fade-up">' . $f['{{EQUIPE}}'] . '</div></div></section>', 'Equipe');
    }
    if ($dados['galeria']) {
        $secao('galeria', '<section id="galeria" class="pt-secao"><div class="pt-container">' . $cabecalho('Galeria', $N['galeria_titulo'] ?? ($modo === 'orcamento' ? 'Trabalhos que já fizemos' : 'Por dentro do nosso dia a dia')) . '<div data-anim="fade-up">' . $f['{{GALERIA}}'] . '</div></div></section>', 'Galeria');
    }
    if ($dados['cupons']) {
        $secao('cupons', '<section id="cupons" class="pt-secao"><div class="pt-container">' . $cabecalho('Promoções', 'Cupons de desconto') . '<div data-anim="fade-up">' . $f['{{CUPONS}}'] . '</div></div></section>');
    }
    if (!empty($dados['planos'])) {
        $temPacote = (bool)array_filter($dados['planos'], fn($p) => $p['tipo'] === 'pacote');
        $temAssin = (bool)array_filter($dados['planos'], fn($p) => $p['tipo'] !== 'pacote');
        $secao('clube', '<section id="clube" class="pt-secao pt-secao-alt"><div class="pt-container">' . $cabecalho($temAssin ? 'Clube' : 'Pacotes', $temAssin ? 'Assine e pague menos' : 'Pacotes de sessões', $temAssin ? 'Um valor fixo por mês e você agenda quando quiser, sem pagar a cada visita.' : 'Compre as sessões juntas e economize.') . '<div data-anim="fade-up">' . $f['{{CLUBE}}'] . '</div></div></section>', $temAssin ? 'Clube' : 'Pacotes');
    }
    if ($modo === 'agendamento') {
        $secao('agendar', '<section id="agendar" class="pt-secao pt-secao-destaque"><div class="pt-container pt-estreito">' . $cabecalho('Agendamento online', 'Agende seu horário', 'Escolha o serviço, o profissional e o melhor horário — leva menos de um minuto.') . '<div data-anim="fade-up">' . $f['{{AGENDAMENTO}}'] . '</div></div></section>', 'Agendar');
    } elseif ($modo === 'orcamento') {
        $secao('agendar', '<section id="agendar" class="pt-secao pt-secao-destaque"><div class="pt-container pt-estreito">' . $cabecalho('Sem compromisso', 'Peça seu orçamento', 'Conte o que você precisa — respondemos com o valor no seu WhatsApp.') . '<div data-anim="fade-up">' . $f['{{ORCAMENTO}}'] . '</div></div></section>', 'Orçamento');
    }
    if (in_array('avaliacoes', $modulos ?: ['avaliacoes'], true)) {
        $secao('avaliacoes', '<section id="avaliacoes" class="pt-secao pt-secao-alt"><div class="pt-container">' . $cabecalho('Depoimentos', 'O que dizem nossos clientes') . '<div data-anim="fade-up">' . $f['{{AVALIACOES}}'] . '</div></div></section>', 'Avaliações');
    }

    // Perguntas frequentes (respostas tiradas dos dados reais da empresa)
    $faq = [];
    if ($modo === 'agendamento') {
        $faq[] = ['Como faço para agendar?', 'Clique em "' . $ctaTexto . '", escolha o serviço' . (count($dados['profissionais']) > 1 ? ', o profissional' : '') . ' e um horário livre. ' . ($waAuto ? 'A confirmação chega no seu WhatsApp.' : 'Pronto, seu horário fica reservado.')];
        if ($linkWhats) $faq[] = ['Preciso remarcar. E agora?', 'Sem problema: é só chamar a gente no WhatsApp que encontramos outro horário.'];
    } elseif ($modo === 'orcamento') {
        $faq[] = ['Pedir orçamento tem algum custo?', 'Não. O orçamento é gratuito e sem compromisso — você só fecha se gostar.'];
        $faq[] = ['Como recebo o valor?', 'Preencha o pedido de orçamento aqui no site. A resposta chega ' . ($linkWhats ? 'no seu WhatsApp.' : 'pelo contato que você informar.')];
    } elseif ($modo === 'catalogo') {
        $ent = [];
        if ($lig('aceita_entrega')) $ent[] = 'Entregamos' . ((float)($empresa['taxa_entrega'] ?? 0) > 0 ? ' (taxa de ' . frag_preco($empresa['taxa_entrega']) . ')' : ' sem taxa') . (!empty($empresa['tempo_entrega']) ? ', em ' . $empresa['tempo_entrega'] : '') . '.';
        if ($lig('aceita_retirada')) $ent[] = 'Você também pode retirar no local.';
        if ((float)($empresa['pedido_minimo'] ?? 0) > 0) $ent[] = 'Pedido mínimo: ' . frag_preco($empresa['pedido_minimo']) . '.';
        if ($ent) $faq[] = ['Vocês entregam?', implode(' ', $ent)];
        if ($formas) $faq[] = ['Quais as formas de pagamento?', implode(', ', $formas) . '.'];
    }
    $faq[] = ['Qual o horário de atendimento?', ucfirst(site_texto_horario($dados['agenda'])) . '.'];
    if ($empresa['endereco']) $faq[] = ['Onde vocês ficam?', $empresa['endereco'] . '.'];
    if (count($faq) >= 3) {
        $h = '<section id="duvidas" class="pt-secao"><div class="pt-container pt-estreito">' . $cabecalho('Dúvidas', 'Perguntas frequentes') . '<div class="pt-faq" data-stagger="80">';
        foreach ($faq as $i => [$p, $r]) $h .= '<details' . ($i === 0 ? ' open' : '') . '><summary>' . e($p) . '<span aria-hidden="true"></span></summary><p>' . e($r) . '</p></details>';
        $secao('duvidas', $h . '</div></div></section>');
    }

    $secao('contato', '<section id="contato" class="pt-secao pt-secao-alt"><div class="pt-container">' . $cabecalho('Contato', 'Fale com a gente')
        . '<div data-anim="fade-up">' . $f['{{CONTATO}}'] . '</div>'
        . '<div class="pt-contato-extra">' . ($empresa['endereco'] ? '<div data-anim="fade-up">' . $f['{{MAPA}}'] . '</div>' : '')
        . '<div data-anim="fade-up" data-delay="120"><h3 class="pt-h3">Horário de atendimento</h3>' . $f['{{HORARIOS}}'] . '</div></div></div></section>', 'Contato');

    // Ordem: a escolhida no painel (as seções novas entram nos lugares naturais)
    $ordemPadrao = ['servicos', 'blocos', 'sobre', 'equipe', 'galeria', 'como', 'clube', 'cupons', 'agendar', 'avaliacoes', 'duvidas', 'contato'];
    $escolhida = !empty($empresa['secoes']) ? array_values(array_filter(explode(',', $empresa['secoes']))) : null;
    $ordem = $escolhida ? array_values(array_unique(array_merge(array_slice($escolhida, 0, 2), ['como'], array_slice($escolhida, 2)))) : $ordemPadrao;
    if (!in_array('blocos', $ordem, true)) array_splice($ordem, min(1, count($ordem)), 0, ['blocos']);
    if (!in_array('contato', $ordem, true)) $ordem[] = 'contato';
    if (!in_array('duvidas', $ordem, true)) array_splice($ordem, array_search('contato', $ordem, true), 0, ['duvidas']);
    if (!in_array('clube', $ordem, true)) array_splice($ordem, max(0, (int)array_search('contato', $ordem, true) - 1), 0, ['clube']);
    if ($temAgendar && !in_array('agendar', $ordem, true)) array_splice($ordem, max(0, count($ordem) - 3), 0, ['agendar']);

    $faixa = array_slice(array_map(fn($s) => $s['nome'], $dados['servicos']), 0, 12);
    $fontes = 'https://fonts.googleapis.com/css2?family=' . $E['fontes'] . '&display=swap';
    $ano = date('Y');
    $heroTipo = $fotoHero ? ($N['hero'] === 'split' ? 'split' : 'foto') : 'texto';
    $caixa = !empty($N['titulo_caixa']) && $chaveEstilo === 'nicho';
    $classesHtml = 'pt-estilo-' . $chaveEstilo . ' pt-nicho-' . $chaveNicho . ' pt-motivo-' . $N['motivo'] . ' pt-anim-' . $anim . ($caixa ? ' pt-caixa' : '') . ($E['escuro'] ? ' pt-escuro' : ' pt-claro');
    $temAcoesMob = $linkWhats || $linkRota || $tel;
    // Aula/avaliação experimental vira o botão principal do topo (academia, pilates, escolas, clínicas)
    $experimental = $modo === 'agendamento' ? (array_values(array_filter($dados['servicos'], fn($s) => pg_bool($s['experimental'] ?? false)))[0] ?? null) : null;
    $ld = [];
    if (count($faq) >= 3) $ld = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faq)];

    ob_start(); ?>
<!DOCTYPE html>
<html lang="pt-BR" class="<?= e($classesHtml) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($titulo) ?></title>
<meta name="description" content="<?= e(mb_strimwidth($descricao, 0, 300, '…')) ?>">
<meta property="og:title" content="<?= e($titulo) ?>">
<meta property="og:description" content="<?= e(mb_strimwidth($descricao, 0, 300, '…')) ?>">
<meta property="og:type" content="website">
<?php if ($fotoHero || $empresa['logo']): ?><meta property="og:image" content="<?= e($fotoHero ?: $empresa['logo']) ?>"><?php endif; ?>
<meta name="theme-color" content="<?= e($E['bg']) ?>">
<?php if ($empresa['logo']): ?><link rel="icon" href="<?= e($empresa['logo']) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= e($fontes) ?>">
<?php if ($fotoHero): ?><link rel="preload" as="image" href="<?= e($fotoHero) ?>"><?php endif; ?>
<link rel="stylesheet" href="/assets/css/site-premium.css?v=2">
<style>:root{--bg:<?= $E['bg'] ?>;--bg2:<?= $E['bg2'] ?>;--sup:<?= $E['superficie'] ?>;--txt:<?= $E['texto'] ?>;--suave:<?= $E['suave'] ?>;--linha:<?= $E['linha'] ?>;--accent:<?= $accent ?>;--on-accent:<?= frag_cor_contraste($accent) ?>;--f-titulo:<?= $E['titulo'] ?>,Georgia,serif;--f-corpo:<?= $E['corpo'] ?>,system-ui,sans-serif;color-scheme:<?= $E['escuro'] ? 'dark' : 'light' ?>}</style>
<?= frag_css_apoio(['accent' => $accent]) ?>
<?= site_json_ld($empresa, $dados) ?>
<?= $ld ? '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>' : '' ?>
<?= site_script_animacoes($anim) ?>
<?= $preview ? '' : site_script_analytics($dados['analytics_id']) . site_script_eventos($empresa) ?>
</head>
<body class="hero-<?= $heroTipo ?><?= $temAcoesMob ? ' tem-acoes-mob' : '' ?>">
<div data-progress></div>
<?php if ($preview): ?><div class="pt-preview-aviso">Pré-visualização — visual <?= e($E['nome']) ?> (formulários não enviam de verdade aqui)</div><?php endif; ?>
<a class="pt-pular" href="#conteudo">Pular para o conteúdo</a>

<header class="pt-topo" data-header-scroll>
    <div class="pt-barra">
        <div class="pt-container pt-barra-in">
            <span class="pt-barra-status"><span class="pt-ponto<?= !empty($status['aberto']) ? ' aberto' : '' ?>"></span><?= e($status['texto'] ?? '') ?></span>
            <?php if ($nota['total'] > 0): ?><a class="pt-barra-item" href="#avaliacoes"><?= $estrelas($nota['media']) ?> <strong><?= number_format($nota['media'], 1, ',', '') ?></strong> (<?= (int)$nota['total'] ?>)</a><?php endif; ?>
            <?php if ($linkRota): ?><a class="pt-barra-item pt-barra-end" href="<?= e($linkRota) ?>" target="_blank" rel="noopener"><?= frag_icone('pin') ?> <?= e(mb_strimwidth($empresa['endereco'], 0, 60, '…')) ?></a><?php endif; ?>
            <?php if ($tel): ?><a class="pt-barra-item pt-barra-tel" href="tel:<?= e($tel) ?>"><?= frag_icone('tel') ?> <?= e($empresa['telefone'] ?: $empresa['whatsapp']) ?></a><?php endif; ?>
        </div>
    </div>
    <div class="pt-container pt-topo-in">
        <a class="pt-marca" href="#inicio"><?php if ($empresa['logo']): ?><img src="<?= e($empresa['logo']) ?>" alt=""><?php else: ?><span class="pt-marca-ico"><?= $icone($N['icone']) ?></span><?php endif; ?><span><?= e($nome) ?></span></a>
        <nav class="pt-menu" aria-label="Menu principal">
            <?php foreach ($ordem as $k): if (isset($sec[$k], $nav[$k])): ?><a href="#<?= e($k) ?>"><?= e($nav[$k]) ?></a><?php endif; endforeach; ?>
        </nav>
        <?php if ($linkWhats): ?><a class="pt-topo-wa" href="<?= e($linkWhats) ?>" target="_blank" rel="noopener" aria-label="WhatsApp" data-ev="whatsapp"><?= frag_icone('whats') ?></a><?php endif; ?>
        <a class="pt-btn pt-btn-sm pt-topo-cta" href="<?= e($ctaHref) ?>" data-magnetic><?= e($ctaTexto) ?></a>
        <button class="pt-hamburguer" type="button" aria-label="Abrir menu" aria-expanded="false" onclick="var a=document.body.classList.toggle('gm-menu-aberto');this.setAttribute('aria-expanded',a)"><span></span><span></span></button>
    </div>
</header>

<main id="conteudo">
<section id="inicio" class="pt-hero pt-hero--<?= $heroTipo ?> pt-motivo<?= $heroTipo === 'foto' ? ' com-foto' : '' ?>" data-cena="hero">
    <?php if ($heroTipo === 'foto'): ?><div class="pt-hero-foto" data-parallax="0.18"><img src="<?= e($fotoHero) ?>" alt="" fetchpriority="high"></div><?php endif; ?>
    <div class="pt-hero-luzes" aria-hidden="true"><span></span><span></span><span></span></div>
    <div class="pt-container pt-hero-grade">
        <div class="pt-hero-in">
            <span class="pt-selo" data-anim="fade-down"><span class="pt-selo-ico"><?= $icone($N['icone']) ?></span><?= e($mostraNomeNoTopo ? $nome : ($segNome ?: 'Bem-vindo')) ?><?php if ($mostraNomeNoTopo && $segNome): ?><span class="pt-selo-sep">·</span><span class="pt-selo-seg"><?= e($segNome) ?></span><?php endif; ?></span>
            <h1 data-split<?= mb_strlen($headline) > 34 ? ' class="pt-h1-longo"' : '' ?>><?= e($headline) ?></h1>
            <p class="pt-hero-sub" data-anim="fade-up" data-delay="350"><?= e($sub) ?></p>
            <div class="pt-hero-acoes" data-anim="fade-up" data-delay="500">
                <?php if ($experimental): ?><a class="pt-btn" href="#gds-agendamento" data-gds-agendar-servico="<?= (int)$experimental['id'] ?>" data-ev="agendar"><?= e((float)$experimental['preco'] > 0 ? 'Agendar ' . mb_strtolower($experimental['nome']) : mb_strtoupper(mb_substr($experimental['nome'], 0, 1)) . mb_substr($experimental['nome'], 1) . ' grátis') ?> <span aria-hidden="true">→</span></a><?php endif; ?>
                <a class="pt-btn<?= $experimental ? ' pt-btn-vidro' : '' ?>" href="<?= e($ctaHref) ?>" data-magnetic data-ev="<?= $temAgendar ? ($modo === 'orcamento' ? 'orcamento' : 'agendar') : ($modo === 'catalogo' ? 'pedido' : 'contato') ?>"><?= e($ctaTexto) ?> <span aria-hidden="true">→</span></a>
                <?php if ($linkWhats): ?><a class="pt-btn pt-btn-vidro" href="<?= e($linkWhats) ?>" target="_blank" rel="noopener" data-ev="whatsapp"><?= frag_icone('whats') ?> WhatsApp</a><?php endif; ?>
            </div>
            <ul class="pt-confianca" data-stagger="100">
                <?php if (count($dados['profissionais']) > 1): ?><li><?= frag_icone('check') ?> <?= count($dados['profissionais']) ?> profissionais</li><?php endif; ?>
                <?php if ($bairro): ?><li><?= frag_icone('pin') ?> <?= e(mb_strimwidth($bairro, 0, 40, '…')) ?></li><?php endif; ?>
                <li><?= frag_icone('check') ?> <?= e(['agendamento' => 'Agendamento em 1 minuto', 'orcamento' => 'Orçamento sem compromisso', 'catalogo' => 'Peça sem baixar app', 'contato' => 'Resposta rápida'][$modo]) ?></li>
            </ul>
        </div>
        <?php if ($heroTipo === 'split'): ?>
        <div class="pt-hero-midia" data-anim="clip-up" data-delay="200">
            <figure class="pt-hero-fig"><img src="<?= e($fotoHero) ?>" alt="<?= e($nome) ?>" fetchpriority="high" data-parallax="0.08"></figure>
            <?php foreach ($cartoes as $i => $c): ?><div class="pt-flut-pos pt-flut-pos-<?= $i ?>" data-anim="fade-up" data-delay="<?= 700 + $i * 180 ?>"><?= $c ?></div><?php endforeach; ?>
        </div>
        <?php elseif ($cartoes): ?>
        <div class="pt-hero-cartoes" data-stagger="160"><?php foreach ($cartoes as $c) echo $c; ?></div>
        <?php endif; ?>
    </div>
    <a class="pt-rolar" href="#<?= e($ordem[0] ?? 'contato') ?>" aria-label="Rolar para baixo"><span></span></a>
</section>

<?php if (count($faixa) >= 3): ?>
<div class="pt-faixa" data-marquee style="--gm-velocidade:<?= max(18, count($faixa) * 4) ?>s">
    <?php foreach ($faixa as $item): ?><span><?= e($item) ?></span><span class="pt-faixa-sep" aria-hidden="true"><?= $icone($N['icone']) ?></span><?php endforeach; ?>
</div>
<?php endif; ?>

<?php foreach ($ordem as $k): if (isset($sec[$k])) echo $sec[$k], "\n"; endforeach; ?>

<section class="pt-cta-final<?= $fotoFinal ? ' com-foto' : '' ?>" data-cena="final">
    <?php if ($fotoFinal): ?><div class="pt-cta-foto" aria-hidden="true"><img src="<?= e($fotoFinal) ?>" alt="" loading="lazy"></div><?php endif; ?>
    <div class="pt-container" data-anim="zoom-in">
        <span class="pt-sobretitulo"><?= e($nome) ?></span>
        <h2 data-split><?= e(['agendamento' => 'Seu próximo horário está a um clique', 'orcamento' => 'Vamos tirar sua ideia do papel?', 'catalogo' => 'Bateu a vontade? Peça agora', 'contato' => 'Vamos conversar?'][$modo]) ?></h2>
        <div class="pt-hero-acoes" style="justify-content:center">
            <a class="pt-btn" href="<?= e($ctaHref) ?>" data-magnetic><?= e($ctaTexto) ?> <span aria-hidden="true">→</span></a>
            <?php if ($linkWhats): ?><a class="pt-btn pt-btn-vidro" href="<?= e($linkWhats) ?>" target="_blank" rel="noopener" data-ev="whatsapp"><?= frag_icone('whats') ?> Chamar no WhatsApp</a><?php endif; ?>
        </div>
    </div>
</section>
</main>

<footer class="pt-rodape">
    <div class="pt-container pt-rodape-grade">
        <div><a class="pt-marca" href="#inicio"><?php if ($empresa['logo']): ?><img src="<?= e($empresa['logo']) ?>" alt=""><?php else: ?><span class="pt-marca-ico"><?= $icone($N['icone']) ?></span><?php endif; ?><span><?= e($nome) ?></span></a>
            <p><?= e(mb_strimwidth($descricao, 0, 160, '…')) ?></p></div>
        <div><h4>Navegação</h4><?php foreach ($ordem as $k): if (isset($sec[$k], $nav[$k])): ?><a href="#<?= e($k) ?>"><?= e($nav[$k]) ?></a><?php endif; endforeach; ?></div>
        <div><h4>Contato</h4>
            <?php if ($linkWhats): ?><a href="<?= e($linkWhats) ?>" target="_blank" rel="noopener" data-ev="whatsapp">WhatsApp</a><?php endif; ?>
            <?php if (!empty($empresa['telefone'])): ?><a href="tel:<?= e(so_digitos_frag($empresa['telefone'])) ?>"><?= e($empresa['telefone']) ?></a><?php endif; ?>
            <?php if (!empty($empresa['instagram'])): ?><a href="https://instagram.com/<?= e(ltrim($empresa['instagram'], '@')) ?>" target="_blank" rel="noopener" data-ev="instagram">Instagram</a><?php endif; ?>
            <?php if ($empresa['endereco']): ?><a href="<?= e($linkRota) ?>" target="_blank" rel="noopener"><?= e($empresa['endereco']) ?></a><?php endif; ?>
            <span><?= e(ucfirst(site_texto_horario($dados['agenda']))) ?></span></div>
    </div>
    <div class="pt-container pt-rodape-base"><span>© <?= $ano ?> <?= e($nome) ?></span><span><?= $creditos ? 'Fotos: ' . e(implode(', ', $creditos)) . ' / Pexels · ' : '' ?>Site por GDS</span></div>
</footer>

<?php if ($temAcoesMob): ?>
<nav class="pt-acoes-mob" aria-label="Ações rápidas">
    <a class="pt-acao-principal" href="<?= e($ctaHref) ?>"><?= frag_icone($modo === 'catalogo' ? 'sacola' : ($modo === 'agendamento' ? 'calend' : 'seta')) ?><span><?= e($ctaTexto) ?></span></a>
    <?php if ($linkWhats): ?><a href="<?= e($linkWhats) ?>" target="_blank" rel="noopener" data-ev="whatsapp"><?= frag_icone('whats') ?><span>WhatsApp</span></a><?php endif; ?>
    <?php if ($linkRota): ?><a href="<?= e($linkRota) ?>" target="_blank" rel="noopener" data-ev="rota"><?= frag_icone('rota') ?><span>Como chegar</span></a>
    <?php elseif ($tel): ?><a href="tel:<?= e($tel) ?>" data-ev="ligar"><?= frag_icone('tel') ?><span>Ligar</span></a><?php endif; ?>
</nav>
<?php endif; ?>
<?= site_mostrar_whatsapp_float($empresa) ? frag_whatsapp_float($empresa['whatsapp'], $empresa['whatsapp_mensagem'] ?? '', $nome) : '' ?>
<?= site_pedido_online($empresa, $modo) ? frag_carrinho($empresa, $estado['pedido'] ?? [], $dados['servicos']) : '' ?>
<script>
// Depois de enviar um formulário, volta para a seção certa
document.addEventListener('DOMContentLoaded', function () {
    var forcada = <?= json_encode($estado['pagina_forcada']) ?>;
    var alvo = forcada && document.getElementById(forcada === 'orcamento' ? 'agendar' : forcada);
    if (alvo) setTimeout(function () { alvo.scrollIntoView({ block: 'start' }); }, 60);
});
window.gdsMostrarPagina = function (k) { var el = document.getElementById(k); if (el) el.scrollIntoView({ behavior: 'smooth' }); };
</script>
<?= $preview ? site_script_preview() : '' ?>
</body>
</html>
<?php
    return ob_get_clean();
}

// Extensão de animações "cinema": rolagem suave + cenas no scroll (GSAP + Lenis, versões fixas).
// Se a internet do visitante bloquear esses arquivos, o site continua com as animações normais.
function site_script_animacoes($anim) {
    if ($anim !== 'cinema') return '';
    return '<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>'
         . '<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js" defer></script>'
         . '<script src="https://cdn.jsdelivr.net/npm/lenis@1.1.13/dist/lenis.min.js" defer></script>'
         . '<script src="/assets/js/gds-cinema.js?v=1" defer></script>';
}

// Contador de visitas e cliques do site (anônimo; vira o painel "Desempenho do site")
function site_script_eventos($empresa) {
    return '<script>(function(){var E=' . (int)$empresa['id'] . ',U="/site/evento.php";'
         . 'function v(){try{var k="gds_v",x=localStorage.getItem(k);if(!x){x=Math.random().toString(36).slice(2)+Date.now().toString(36);localStorage.setItem(k,x)}return x}catch(e){return""}}'
         . 'function env(t){var d=new FormData();d.append("e",E);d.append("t",t);d.append("v",v());d.append("r",document.referrer||"");d.append("u",location.search||"");'
         . 'if(navigator.sendBeacon)navigator.sendBeacon(U,d);else fetch(U,{method:"POST",body:d,keepalive:true})}'
         . 'window.gdsEvento=env;env("visita");'
         . 'document.addEventListener("click",function(ev){var a=ev.target.closest&&ev.target.closest("a,button");if(!a)return;var t=a.getAttribute("data-ev"),h=a.getAttribute("href")||"";'
         . 'if(!t){if(/wa\\.me|whatsapp/i.test(h))t="whatsapp";else if(/^tel:/.test(h))t="ligar";else if(/maps/.test(h))t="rota";else if(/instagram\\.com/.test(h))t="instagram"}'
         . 'if(t)env(t)},true);'
         . 'document.addEventListener("submit",function(ev){var f=ev.target;if(f.querySelector("[name=fazer_pedido]"))env("pedido");else if(f.querySelector("[name=pedir_orcamento]"))env("orcamento");else if(f.querySelector("[name=fazer_agendamento]"))env("agendar");else if(f.querySelector("[name=enviar_lead]"))env("contato")},true);'
         . '})();</script>';
}
