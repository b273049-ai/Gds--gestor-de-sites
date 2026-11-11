<?php
// ============================================================
// VISUAL "LIMPO" DOS SITES (padrão)
// Primeira impressão com o essencial: o nome, uma frase, um botão. O resto vem
// quando o cliente rola, sem selos, faixas, cartões flutuando nem animação em tudo.
//
// Cada empresa sai diferente, de propósito:
//  • Personalidade (tom): fontes e fundo pensados para o ramo (8 tons × 2 pares de fontes).
//  • Composição do topo: foto inteira, foto ao lado, foto em moldura ou só tipografia —
//    escolhida entre as que combinam com o ramo, pelo nome da empresa (duas barbearias
//    não ficam iguais). O dono pode fixar a dele no painel.
//  • Cor: a da marca (logo/Instagram/escolhida), ajustada para ter contraste.
// Regras de design seguidas (skill frontend-design do projeto): uma coisa marcante por
// página, tipografia como personalidade, sem rótulos em caixa-alta em cima de cada título,
// sem "01/02/03" onde não é sequência, sem setas em todo botão, um único momento de animação.
// ============================================================
require_once __DIR__ . '/site_template.php';

function site_limpo_tons() {
    // fontes: [url do Google Fonts, família do título, família do texto, peso do título]
    return [
        'acolhedor' => ['nome' => 'Acolhedor', 'bg' => '#fbf8f3', 'sup' => '#ffffff', 'txt' => '#2a2118', 'suave' => '#6e5f50', 'linha' => 'rgba(42,33,24,.12)', 'escuro' => false, 'raio' => '20px',
            'fontes' => [['Fraunces:opsz,wght@9..144,400..700&family=Nunito+Sans:wght@400;600;700', "'Fraunces'", "'Nunito Sans'", 600],
                         ['Young+Serif&family=Figtree:wght@400;500;600;700', "'Young Serif'", "'Figtree'", 400]]],
        'elegante' => ['nome' => 'Elegante', 'bg' => '#f8f5f1', 'sup' => '#fffdfa', 'txt' => '#221d1a', 'suave' => '#6d625a', 'linha' => 'rgba(34,29,26,.12)', 'escuro' => false, 'raio' => '4px',
            'fontes' => [['Cormorant+Garamond:wght@500;600;700&family=Jost:wght@400;500;600', "'Cormorant Garamond'", "'Jost'", 600],
                         ['Gilda+Display&family=Mulish:wght@400;600;700', "'Gilda Display'", "'Mulish'", 400]]],
        'firme' => ['nome' => 'Firme', 'bg' => '#131211', 'sup' => '#1c1a18', 'txt' => '#f2efe9', 'suave' => '#b3aca1', 'linha' => 'rgba(242,239,233,.12)', 'escuro' => true, 'raio' => '6px',
            'fontes' => [['Archivo:wdth,wght@62..125,500..800', "'Archivo'", "'Archivo'", 800, 'condensado'],
                         ['Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600', "'Barlow Condensed'", "'Barlow'", 700]]],
        'claro' => ['nome' => 'Claro', 'bg' => '#ffffff', 'sup' => '#f5f7f8', 'txt' => '#13202a', 'suave' => '#56646f', 'linha' => 'rgba(19,32,42,.1)', 'escuro' => false, 'raio' => '14px',
            'fontes' => [['Figtree:wght@400;500;600;700;800', "'Figtree'", "'Figtree'", 700],
                         ['Onest:wght@400;500;600;700', "'Onest'", "'Onest'", 650]]],
        'editorial' => ['nome' => 'Editorial', 'bg' => '#f7f7f4', 'sup' => '#ffffff', 'txt' => '#16181d', 'suave' => '#5b606b', 'linha' => 'rgba(22,24,29,.12)', 'escuro' => false, 'raio' => '2px',
            'fontes' => [['Newsreader:opsz,wght@6..72,400..700&family=Instrument+Sans:wght@400;500;600', "'Newsreader'", "'Instrument Sans'", 500],
                         ['Literata:opsz,wght@7..72,400..700&family=Public+Sans:wght@400;500;600', "'Literata'", "'Public Sans'", 600]]],
        'noturno' => ['nome' => 'Noturno', 'bg' => '#0f0e12', 'sup' => '#18161d', 'txt' => '#f3f1f7', 'suave' => '#aca7b8', 'linha' => 'rgba(243,241,247,.12)', 'escuro' => true, 'raio' => '10px',
            'fontes' => [['Syne:wght@600;700;800&family=Manrope:wght@400;500;600', "'Syne'", "'Manrope'", 700],
                         ['Unbounded:wght@500;600;700&family=Outfit:wght@400;500;600', "'Unbounded'", "'Outfit'", 600]]],
        'sabor' => ['nome' => 'Sabor', 'bg' => '#fffaf4', 'sup' => '#ffffff', 'txt' => '#241811', 'suave' => '#6f5b4c', 'linha' => 'rgba(36,24,17,.12)', 'escuro' => false, 'raio' => '16px',
            'fontes' => [['Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Rubik:wght@400;500;600', "'Bricolage Grotesque'", "'Rubik'", 750],
                         ['Gloock&family=Albert+Sans:wght@400;500;600;700', "'Gloock'", "'Albert Sans'", 400]]],
        'loja' => ['nome' => 'Loja', 'bg' => '#ffffff', 'sup' => '#f6f4f1', 'txt' => '#141414', 'suave' => '#5f5f5f', 'linha' => 'rgba(20,20,20,.1)', 'escuro' => false, 'raio' => '10px',
            'fontes' => [['Schibsted+Grotesk:wght@400;500;600;700;800', "'Schibsted Grotesk'", "'Schibsted Grotesk'", 700],
                         ['Hanken+Grotesk:wght@400;500;600;700;800', "'Hanken Grotesk'", "'Hanken Grotesk'", 700]]],
    ];
}

function site_limpo_composicoes() {
    return ['foto' => 'Foto inteira no topo', 'lado' => 'Foto ao lado do título', 'moldura' => 'Título e foto em moldura embaixo', 'tipografia' => 'Só o nome em letras grandes'];
}

// Personalidade e composições que combinam com cada ramo
function site_limpo_perfil_nicho($nicho) {
    return [
        'barbearia' => ['firme', ['foto', 'lado']], 'tatuagem' => ['noturno', ['foto', 'tipografia']], 'beleza' => ['elegante', ['lado', 'moldura']],
        'gastronomia' => ['sabor', ['foto', 'moldura']], 'cafe' => ['acolhedor', ['moldura', 'lado']], 'saude' => ['claro', ['lado', 'moldura']],
        'eventos' => ['noturno', ['foto', 'tipografia']], 'fitness' => ['firme', ['foto', 'lado']], 'pet' => ['acolhedor', ['lado', 'moldura']],
        'auto' => ['firme', ['foto', 'lado']], 'casa' => ['loja', ['foto', 'lado']], 'profissional' => ['editorial', ['lado', 'tipografia']],
        'tecnologia' => ['claro', ['tipografia', 'lado']], 'educacao' => ['claro', ['lado', 'moldura']], 'varejo' => ['loja', ['moldura', 'lado']],
        'turismo' => ['editorial', ['foto', 'moldura']], 'geral' => ['claro', ['lado', 'moldura', 'tipografia']],
    ][$nicho] ?? ['claro', ['lado', 'moldura']];
}

// Escolhas finais de visual da empresa (o dono pode fixar cada uma no painel)
function site_limpo_escolhas($empresa, $temFoto) {
    $nk = site_nicho_da_empresa($empresa);
    [$tomPadrao, $comps] = site_limpo_perfil_nicho($nk);
    $tons = site_limpo_tons();
    $h = crc32(mb_strtolower(trim((string)$empresa['nome'])) . '|' . ($empresa['id'] ?? ''));
    $tom = isset($tons[$empresa['tom'] ?? '']) ? $empresa['tom'] : $tomPadrao;
    $comp = isset(site_limpo_composicoes()[$empresa['composicao'] ?? '']) ? $empresa['composicao'] : $comps[$h % count($comps)];
    if (!$temFoto && $comp !== 'tipografia') $comp = 'tipografia';
    $par = $tons[$tom]['fontes'][($h >> 3) % count($tons[$tom]['fontes'])];
    return ['nicho' => $nk, 'tom' => $tom, 'T' => $tons[$tom], 'comp' => $comp, 'fonte' => $par, 'h' => $h];
}

// Próximos horários livres de verdade (hoje ou no próximo dia de atendimento), até 4
function site_limpo_livres($empresa) {
    if (!function_exists('agenda_horarios_do_dia')) return null;
    try {
        $cfg = agenda_config($empresa);
        $dias = array_map('intval', explode(',', $cfg['dias']));
        $semana = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
        for ($i = 0; $i <= 6; $i++) {
            $ts = strtotime("+$i day");
            if (!in_array((int)date('w', $ts), $dias, true)) continue;
            $data = date('Y-m-d', $ts);
            $h = agenda_horarios_do_dia($empresa, $data, $cfg['intervalo']);
            $livres = array_values(array_filter((array)($h['livres'] ?? []), fn($hora) => $i > 0 || strtotime("$data $hora") >= time() + 30 * 60));
            if ($livres) return ['quando' => $i === 0 ? 'hoje' : ($i === 1 ? 'amanhã' : $semana[(int)date('w', $ts)]), 'horas' => array_slice($livres, 0, 4)];
        }
    } catch (Throwable $e) {}
    return null;
}

// Painel ao vivo do topo: o estado real da empresa agora (aberto, horários livres / entrega, nota).
// É a "tecnologia à mostra" do site — um só elemento, no lugar de selos e cartões espalhados.
function site_limpo_painel_vivo($empresa, $dados, $modo, $ctaHref) {
    $status = $dados['status'] ?? [];
    $nota = $dados['avaliacoes_resumo'];
    $lig = fn($c) => !isset($empresa[$c]) || $empresa[$c] === null || pg_bool($empresa[$c]);
    $rot = ''; $chips = [];
    if ($modo === 'agendamento') {
        $l = site_limpo_livres($empresa);
        if ($l) { $rot = 'Horários livres ' . $l['quando']; $chips = $l['horas']; }
        else { $rot = 'Agenda online'; $chips = ['24 horas', 'Confirmação no WhatsApp']; }
    } elseif ($modo === 'catalogo') {
        $rot = 'Peça pelo site';
        if (!empty($empresa['tempo_entrega'])) $chips[] = 'Entrega em ' . $empresa['tempo_entrega'];
        if ($lig('aceita_entrega')) $chips[] = (float)($empresa['taxa_entrega'] ?? 0) > 0 ? 'Taxa ' . frag_preco($empresa['taxa_entrega']) : 'Entrega grátis';
        if ($lig('aceita_retirada')) $chips[] = 'Retirada';
        if (!$chips) $chips = ['Sem baixar app'];
    } elseif ($modo === 'orcamento') {
        $rot = 'Orçamento online'; $chips = ['Sem compromisso', 'Resposta no WhatsApp'];
    } elseif (!empty($dados['imoveis'])) {
        $n = count($dados['imoveis']);
        $rot = 'No nosso site agora';
        $chips[] = $n . ($n > 1 ? ' imóveis disponíveis' : ' imóvel disponível');
        foreach (array_unique(array_map(fn($i) => $i['finalidade'] ?? '', $dados['imoveis'])) as $fin) if ($fin !== '' && defined('IMOVEL_FINALIDADES') && isset(IMOVEL_FINALIDADES[$fin])) $chips[] = IMOVEL_FINALIDADES[$fin];
        $chips = array_slice($chips, 0, 4);
    } else {
        $rot = 'Atendimento'; $chips = array_values(array_filter([so_digitos_frag($empresa['whatsapp'] ?? '') ? 'Resposta pelo WhatsApp' : '', !empty($empresa['endereco']) ? 'Atendimento presencial' : '']));
    }
    $h = '<span class="lp-vivo-status"><span class="lp-pulso' . (!empty($status['aberto']) ? ' aberto' : '') . '" aria-hidden="true"></span>' . e($status['texto'] ?? 'Atendimento online') . '</span>'
       . ($chips ? '<span class="lp-vivo-rot">' . e($rot) . '</span><span class="lp-vivo-chips">' . implode('', array_map(fn($c) => '<span>' . e($c) . '</span>', $chips)) . '</span>' : '');
    if ($nota['total'] >= 3) {
        $media = number_format($nota['media'], 1, ',', '');
        $h .= '<span class="lp-vivo-nota"><strong data-conta="' . number_format($nota['media'], 1, '.', '') . '">' . $media . '</strong>'
            . '<span class="lp-estrelas" style="--nota:' . round($nota['media'] / 5 * 100) . '%" aria-hidden="true">★★★★★</span>'
            . '<span>' . (int)$nota['total'] . ' avaliações' . (($nota['fonte'] ?? '') === 'google' ? ' no Google' : '') . '</span></span>';
    }
    $link = in_array($modo, ['agendamento', 'orcamento', 'catalogo'], true) || !empty($dados['imoveis']);
    return $link ? '<a class="lp-vivo" href="' . e(!empty($dados['imoveis']) && $modo !== 'agendamento' ? '#servicos' : $ctaHref) . '" data-ev="painel">' . $h . '</a>' : '<div class="lp-vivo">' . $h . '</div>';
}

// Mistura duas cores (para o fundo levar um tiquinho da cor da marca)
function site_limpo_misturar($a, $b, $p) {
    $c1 = sscanf(ltrim($a, '#'), '%02x%02x%02x'); $c2 = sscanf(ltrim($b, '#'), '%02x%02x%02x');
    if (count(array_filter($c1, 'is_int')) < 3 || count(array_filter($c2, 'is_int')) < 3) return $a;
    return sprintf('#%02x%02x%02x', ...array_map(fn($x, $y) => (int)round($x * (1 - $p) + $y * $p), $c1, $c2));
}

function site_render_limpo($empresa, $dados, $estado, $preview = false) {
    [, $E, $chaveNicho, $N] = site_identidade($empresa);
    $nome = $empresa['nome'];
    $modo = $dados['modo'];
    $modulos = $dados['modulos'] ?? [];
    // Lista de serviços compacta (cardápio de delivery continua em cartões, por causa do carrinho)
    $f = site_fragmentos_marcadores($empresa, $dados, $estado, ['variante' => $modo === 'catalogo' ? $N['servicos'] : 'menu']);

    // Fotos: as da empresa primeiro; banco de fotos só para não deixar o topo vazio
    $galeria = $dados['galeria'];
    $fotosBanco = (empty($empresa['banner_imagem']) && count($galeria) < 2) ? fotos_do_site($empresa) : [];
    $fotoHero = $empresa['banner_imagem'] ?: ($galeria[0] ?? ($fotosBanco[0]['url'] ?? ''));
    $fotoSobre = array_values(array_filter(array_merge(array_slice($galeria, $empresa['banner_imagem'] ? 0 : 1), array_map(fn($x) => $x['medio'], array_slice($fotosBanco, 1))), fn($u) => $u && $u !== $fotoHero))[0] ?? '';
    $creditos = array_values(array_unique(array_filter(array_map(fn($x) => in_array($x['url'], [$fotoHero], true) || in_array($x['medio'], [$fotoSobre], true) ? ($x['autor'] ?? '') : '', $fotosBanco))));

    $V = site_limpo_escolhas($empresa, (bool)$fotoHero);
    $T = $V['T'];
    $comp = $V['comp'];
    // Cor da marca (a escolhida ou a do ramo), legível no fundo
    $corEmpresa = cor_hex_valida_site($empresa['cor_primaria'] ?? '', '');
    $accent = in_array(strtolower($corEmpresa), ['', '#4f7cff', '#1a1a2e', '#2563eb'], true) ? $E['accent'] : $corEmpresa;
    // Cor "do meio" (ex.: vermelho #e23b2a): nem texto preto nem branco chega a 4,5:1 nos botões.
    // Escurece só o necessário para o texto branco ficar legível (a cor da marca continua reconhecível).
    $onAccent = frag_cor_contraste($accent);
    if (frag_contraste($accent, $onAccent) < 4.5) { $accent = frag_cor_legivel($accent, ['#ffffff'], 4.6); $onAccent = '#ffffff'; }
    $bg = $T['escuro'] ? $T['bg'] : site_limpo_misturar($T['bg'], $accent, ($V['h'] % 2) ? 0.035 : 0.0);
    $fundos = [$bg, $T['sup']];
    $accentTxt = frag_cor_legivel($accent, $fundos);

    // Textos
    $tituloProprio = trim((string)($empresa['titulo'] ?? ''));
    if (mb_strtolower($tituloProprio) === mb_strtolower($nome)) $tituloProprio = '';
    $hs = site_headline_segmento($empresa['segmento_slug'] ?? '');
    $headline = $tituloProprio ?: ($hs[0] ?? ($N['headline'] ?: $nome));
    if ($comp === 'tipografia') $headline = $nome; // só texto: o nome é a estrela
    $subPadrao = ['agendamento' => 'Agende online em menos de um minuto e receba a confirmação no WhatsApp.', 'orcamento' => 'Conte o que você precisa e receba o orçamento no seu WhatsApp.',
        'catalogo' => 'Escolha, peça pelo site e acompanhe tudo pelo WhatsApp.', 'contato' => 'Fale com a gente: atendimento rápido e sem complicação.'][$modo];
    $sub = $empresa['subtitulo'] ?: (!$tituloProprio && $hs && site_frase_combina($hs[1], $modo) ? $hs[1] : (!$tituloProprio && site_frase_combina($N['sub'], $modo) ? $N['sub'] : $subPadrao));
    if ($comp === 'tipografia' && $hs && $headline === $nome) $sub = $hs[0] . ' ' . ($empresa['subtitulo'] ?: $subPadrao);
    $ctaTexto = site_texto_cta($empresa, $modo);
    $temAgendar = in_array($modo, ['agendamento', 'orcamento'], true);
    $ctaHref = $temAgendar ? '#agendar' : ($modo === 'catalogo' ? '#servicos' : '#contato');
    $linkWhats = so_digitos_frag($empresa['whatsapp'] ?? '') ? frag_link_whatsapp($empresa['whatsapp'], trim((string)($empresa['whatsapp_mensagem'] ?? '')) ?: 'Olá! Vim pelo site e gostaria de mais informações.') : '';
    $linkRota = $empresa['endereco'] ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($empresa['endereco']) : '';
    $tel = so_digitos_frag($empresa['telefone'] ?? '') ?: so_digitos_frag($empresa['whatsapp'] ?? '');
    $nota = $dados['avaliacoes_resumo'];
    $segNome = $empresa['segmento_nome'] && ($empresa['segmento_slug'] ?? '') !== 'outro' ? $empresa['segmento_nome'] : ($empresa['segmento_descricao'] ?: '');
    $bairro = $empresa['endereco'] ? trim(explode('-', $empresa['endereco'])[1] ?? explode(',', $empresa['endereco'])[0]) : '';
    $rotCatalogo = site_titulo_catalogo($empresa, $modo);
    // Uma única informação de confiança no topo: a nota, ou o bairro
    $prova = $nota['total'] >= 3
        ? 'Nota ' . number_format($nota['media'], 1, ',', '') . ' de ' . (int)$nota['total'] . ' avaliações' . (($nota['fonte'] ?? '') === 'google' ? ' no Google' : '')
        : ($bairro ? 'Em ' . mb_strimwidth($bairro, 0, 40, '…') : '');
    $titulo = $empresa['seo_titulo'] ?: ($nome . ($segNome ? ' | ' . $segNome : '') . ($bairro ? ' em ' . $bairro : ''));
    $descricao = $empresa['seo_descricao'] ?: (mb_strimwidth($nome . ($segNome ? ', ' . mb_strtolower($segNome) : '') . ($bairro ? ' em ' . $bairro : '') . '. ' . $sub, 0, 300, '…'));

    // ---------- seções (só as que têm conteúdo de verdade) ----------
    $sec = []; $nav = [];
    $secao = function ($id, $tit, $corpo, $intro = '', $menu = null, $classe = '') use (&$sec, &$nav) {
        $sec[$id] = '<section id="' . $id . '" class="lp-secao ' . $classe . '"><div class="lp-larg"><header class="lp-cab"><h2>' . e($tit) . '</h2>' . ($intro ? '<p>' . e($intro) . '</p>' : '') . '</header>' . $corpo . '</div></section>';
        if ($menu) $nav[$id] = $menu;
    };
    if (!empty($dados['imoveis'])) $secao('servicos', 'Imóveis', $f['{{SERVICOS}}'], 'Filtre por bairro, preço e quartos.', 'Imóveis');
    elseif ($dados['servicos']) $secao('servicos', $rotCatalogo, $f['{{SERVICOS}}'], '', $rotCatalogo);
    if ($modo === 'agendamento') $secao('agendar', 'Agende seu horário', $f['{{AGENDAMENTO}}'], 'Escolha o serviço, o dia e o horário. A confirmação chega no seu WhatsApp.', 'Agendar', 'lp-secao-destaque');
    elseif ($modo === 'orcamento') $secao('agendar', 'Peça seu orçamento', $f['{{ORCAMENTO}}'], 'Conte o que precisa. A resposta chega no seu WhatsApp.', 'Orçamento', 'lp-secao-destaque');
    // Sobre: texto curto + 1 foto
    if (trim((string)($empresa['sobre'] ?? '')) !== '') {
        $pars = array_slice(array_filter(array_map('trim', preg_split('/\n\s*\n/', (string)$empresa['sobre']))), 0, 3);
        $txt = implode('', array_map(fn($p) => '<p>' . nl2br(e($p)) . '</p>', $pars));
        $sec['sobre'] = '<section id="sobre" class="lp-secao"><div class="lp-larg lp-sobre' . ($fotoSobre ? '' : ' sem-foto') . '"><div><h2>Sobre a ' . e($nome) . '</h2>' . $txt . '</div>'
            . ($fotoSobre ? '<figure><img src="' . e($fotoSobre) . '" alt="" loading="lazy"></figure>' : '') . '</div></section>';
        $nav['sobre'] = 'Sobre';
    }
    if (count($dados['profissionais']) > 1 && in_array('equipe', $modulos ?: ['equipe'], true)) $secao('equipe', $chaveNicho === 'barbearia' ? 'Nossos barbeiros' : 'Quem atende você', $f['{{EQUIPE}}']);
    if (count($galeria) >= 3) $secao('galeria', $N['galeria_titulo'] ?? 'Fotos', $f['{{GALERIA}}']);
    if (!empty($dados['planos'])) $secao('clube', 'Planos', $f['{{CLUBE}}'], 'Um valor fixo por mês e você vem quando quiser.');
    if (!empty($dados['cupons'])) $secao('cupons', 'Promoções', $f['{{CUPONS}}']);
    if (in_array('avaliacoes', $modulos ?: ['avaliacoes'], true) && ($nota['total'] > 0 || $dados['avaliacoes'])) $secao('avaliacoes', 'O que dizem os clientes', $f['{{AVALIACOES}}'], '', 'Avaliações');
    // Onde estamos: endereço, horário, botões e mapa (sem formulário quando há WhatsApp)
    $horas = !empty($dados['horario_lista'])
        ? '<ul class="lp-horas">' . implode('', array_map(fn($h) => '<li>' . e($h) . '</li>', $dados['horario_lista'])) . '</ul>'
        : $f['{{HORARIOS}}'];
    $visite = '<div class="lp-visite"><div class="lp-visite-info">'
        . ($empresa['endereco'] ? '<p class="lp-end">' . e($empresa['endereco']) . '</p>' : '')
        . '<div class="lp-botoes">' . ($linkWhats ? '<a class="lp-btn" href="' . e($linkWhats) . '" target="_blank" rel="noopener" data-ev="whatsapp">' . frag_icone('whats') . ' Chamar no WhatsApp</a>' : '')
        . ($linkRota ? '<a class="lp-btn lp-btn-2" href="' . e($linkRota) . '" target="_blank" rel="noopener" data-ev="rota">Como chegar</a>' : '')
        . (!$linkWhats && $tel ? '<a class="lp-btn" href="tel:' . e($tel) . '" data-ev="ligar">Ligar</a>' : '') . '</div>'
        . '<h3>Horário</h3>' . $horas
        . (!empty($empresa['instagram']) ? '<p><a class="lp-link" href="https://instagram.com/' . e(ltrim($empresa['instagram'], '@')) . '" target="_blank" rel="noopener" data-ev="instagram">Instagram @' . e(ltrim($empresa['instagram'], '@')) . '</a></p>' : '')
        . '</div>' . ($empresa['endereco'] ? '<div class="lp-mapa"><iframe title="Mapa: ' . e($empresa['endereco']) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="' . e($dados['maps_key'] !== '' ? 'https://www.google.com/maps/embed/v1/place?key=' . rawurlencode($dados['maps_key']) . '&q=' . urlencode($empresa['endereco']) : 'https://maps.google.com/maps?q=' . urlencode($empresa['endereco']) . '&output=embed') . '"></iframe></div>' : '') . '</div>'
        . ($modo === 'contato' || !$linkWhats ? '<div class="lp-form">' . $f['{{CONTATO}}'] . '</div>' : '');
    $secao('contato', $empresa['endereco'] ? 'Onde estamos' : 'Fale com a gente', $visite, '', 'Contato');
    $ordem = ['servicos', 'agendar', 'sobre', 'equipe', 'galeria', 'clube', 'cupons', 'avaliacoes', 'contato'];
    $menu = array_slice(array_intersect_key($nav, array_flip(['servicos', 'agendar', 'sobre', 'avaliacoes', 'contato'])), 0, 4, true);

    $fonteUrl = 'https://fonts.googleapis.com/css2?family=' . $V['fonte'][0] . '&display=swap';
    $classes = 'lp lp-tom-' . $V['tom'] . ' lp-comp-' . $comp . ($comp === 'tipografia' && (($V['h'] >> 5) % 2) ? ' lp-tipo-b' : '') . ($T['escuro'] ? ' lp-escuro' : '') . (!empty($V['fonte'][4]) ? ' lp-condensado' : '');
    $painel = site_limpo_painel_vivo($empresa, $dados, $modo, $ctaHref);
    $logo = $empresa['logo'] ? '<img src="' . e($empresa['logo']) . '" alt="">' : '';
    ob_start(); ?>
<!DOCTYPE html>
<html lang="pt-BR" class="<?= e($classes) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($titulo) ?></title>
<meta name="description" content="<?= e($descricao) ?>">
<meta property="og:title" content="<?= e($titulo) ?>">
<meta property="og:description" content="<?= e($descricao) ?>">
<meta property="og:type" content="website">
<?php if ($fotoHero || $empresa['logo']): ?><meta property="og:image" content="<?= e($fotoHero ?: $empresa['logo']) ?>"><?php endif; ?>
<meta name="theme-color" content="<?= e($bg) ?>">
<?php if ($empresa['logo']): ?><link rel="icon" href="<?= e($empresa['logo']) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= e($fonteUrl) ?>">
<?php if ($fotoHero): ?><link rel="preload" as="image" href="<?= e($fotoHero) ?>"><?php endif; ?>
<link rel="stylesheet" href="/assets/css/site-limpo.css?v=2">
<script>(function(h){if(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches)return;h.classList.add('lp-js');setTimeout(function(){if(!h.classList.contains('lp-vivo-ok'))h.classList.remove('lp-js')},2500)})(document.documentElement)</script>
<style>:root{--bg:<?= $bg ?>;--sup:<?= $T['sup'] ?>;--txt:<?= $T['txt'] ?>;--suave:<?= $T['suave'] ?>;--linha:<?= $T['linha'] ?>;--accent:<?= $accent ?>;--on-accent:<?= $onAccent ?>;--accent-txt:<?= $accentTxt ?>;--raio:<?= $T['raio'] ?>;--f-tit:<?= $V['fonte'][1] ?>,Georgia,serif;--f-txt:<?= $V['fonte'][2] ?>,system-ui,sans-serif;--peso-tit:<?= (int)$V['fonte'][3] ?>;color-scheme:<?= $T['escuro'] ? 'dark' : 'light' ?>}</style>
<?= frag_css_apoio(['accent' => $accent, 'fundos' => $fundos]) ?>
<?= site_json_ld($empresa, $dados) ?>
<?= $preview ? '' : site_script_analytics($dados['analytics_id']) . site_script_eventos($empresa) ?>
</head>
<body>
<?php if ($preview): ?><div class="pt-preview-aviso">Pré-visualização (formulários não enviam de verdade aqui)</div><?php endif; ?>
<div class="lp-progresso" aria-hidden="true"></div>
<a class="lp-pular" href="#conteudo">Pular para o conteúdo</a>
<header class="lp-topo">
    <div class="lp-larg lp-topo-in">
        <a class="lp-marca" href="#inicio"><?= $logo ?><span><?= e($nome) ?></span></a>
        <?php if (count($menu) >= 3): ?><nav class="lp-menu" aria-label="Menu principal"><?php foreach ($menu as $k => $rot): ?><a href="#<?= e($k) ?>"><?= e($rot) ?></a><?php endforeach; ?></nav><?php endif; ?>
        <a class="lp-btn lp-btn-topo" href="<?= e($ctaHref) ?>"><?= e($ctaTexto) ?></a>
    </div>
</header>

<main id="conteudo">
<section id="inicio" class="lp-hero" data-luz>
    <div class="lp-luz" aria-hidden="true"><span></span><span></span><span></span></div>
    <?php if ($comp === 'foto'): ?><div class="lp-hero-foto"><img src="<?= e($fotoHero) ?>" alt="" fetchpriority="high"></div><?php endif; ?>
    <div class="lp-larg lp-hero-in">
        <div class="lp-hero-texto">
            <?php if ($comp !== 'tipografia' && $headline !== $nome): ?><p class="lp-hero-nome lp-entra"><?= e($nome) ?></p><?php endif; ?>
            <h1 data-palavras><?= e($headline) ?></h1>
            <p class="lp-hero-sub lp-entra"><?= e($sub) ?></p>
            <div class="lp-botoes lp-entra">
                <a class="lp-btn lp-btn-brilho" href="<?= e($ctaHref) ?>" data-ima data-ev="<?= $temAgendar ? ($modo === 'orcamento' ? 'orcamento' : 'agendar') : ($modo === 'catalogo' ? 'pedido' : 'contato') ?>"><?= e($ctaTexto) ?></a>
                <?php if ($linkWhats): ?><a class="lp-link-wa" href="<?= e($linkWhats) ?>" target="_blank" rel="noopener" data-ev="whatsapp"><?= frag_icone('whats') ?> WhatsApp</a><?php endif; ?>
            </div>
        </div>
        <?php if ($comp === 'lado' || $comp === 'moldura'): ?><figure class="lp-hero-fig"><img src="<?= e($fotoHero) ?>" alt="<?= e($nome) ?>" fetchpriority="high"><?= $painel ?></figure>
        <?php else: ?><div class="lp-hero-painel"><?= $painel ?></div><?php endif; ?>
    </div>
</section>

<?php foreach ($ordem as $k): if (isset($sec[$k])) echo $sec[$k], "\n"; endforeach; ?>
<section class="lp-fim" data-luz aria-label="<?= e($ctaTexto) ?>">
    <div class="lp-luz" aria-hidden="true"><span></span><span></span><span></span></div>
    <div class="lp-larg lp-fim-in">
        <h2 data-palavras><?= e(['agendamento' => 'Seu próximo horário está a um clique', 'orcamento' => 'Conte sua ideia, a gente responde', 'catalogo' => 'Bateu a vontade? Peça agora', 'contato' => 'Vamos conversar?'][$modo]) ?></h2>
        <div class="lp-botoes"><a class="lp-btn lp-btn-brilho" href="<?= e($ctaHref) ?>" data-ima><?= e($ctaTexto) ?></a><?php if ($linkWhats): ?><a class="lp-link-wa" href="<?= e($linkWhats) ?>" target="_blank" rel="noopener" data-ev="whatsapp"><?= frag_icone('whats') ?> WhatsApp</a><?php endif; ?></div>
    </div>
</section>
</main>

<footer class="lp-rodape">
    <div class="lp-larg lp-rodape-in">
        <span>© <?= date('Y') ?> <?= e($nome) ?></span>
        <span><?= $creditos ? 'Fotos: ' . e(implode(', ', $creditos)) . ' · ' : '' ?>Site feito com GDS</span>
    </div>
</footer>

<nav class="lp-barra" aria-label="Ações rápidas">
    <a class="lp-btn" href="<?= e($ctaHref) ?>"><?= e($ctaTexto) ?></a>
    <?php if ($linkWhats): ?><a class="lp-barra-wa" href="<?= e($linkWhats) ?>" target="_blank" rel="noopener" aria-label="WhatsApp" data-ev="whatsapp"><?= frag_icone('whats') ?></a><?php endif; ?>
</nav>
<?= site_pedido_online($empresa, $modo) ? frag_carrinho($empresa, $estado['pedido'] ?? [], $dados['servicos']) : '' ?>
<script>
(function () {
    // A barra de ações do celular só aparece depois que o topo sai da tela
    var hero = document.getElementById('inicio'), barra = document.querySelector('.lp-barra');
    if (hero && barra && 'IntersectionObserver' in window) new IntersectionObserver(function (es) { barra.classList.toggle('visivel', !es[0].isIntersecting); }).observe(hero);
    var forcada = <?= json_encode($estado['pagina_forcada']) ?>;
    var alvo = forcada && document.getElementById(forcada === 'orcamento' ? 'agendar' : forcada);
    if (alvo) setTimeout(function () { alvo.scrollIntoView({ block: 'start' }); }, 60);
    var topo = document.querySelector('.lp-topo');
    if (topo) { var marca = function () { topo.classList.toggle('rolou', window.scrollY > 40); }; marca(); window.addEventListener('scroll', marca, { passive: true }); }
    window.gdsMostrarPagina = function (k) { var el = document.getElementById(k); if (el) el.scrollIntoView({ behavior: 'smooth' }); };
})();
</script>
<script src="/assets/js/site-vivo.js?v=1" defer></script>
<?= $preview ? site_script_preview() : '' ?>
</body>
</html>
<?php
    return ob_get_clean();
}
