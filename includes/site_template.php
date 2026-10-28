<?php
// ============================================================
// TEMPLATE PREMIUM DOS SITES (quando o site não usa o design da IA)
// Página única com rolagem suave, topo com foto/gradiente animado,
// faixa rolando, números animados, "como funciona" conforme o tipo
// de negócio, componentes reais e rodapé completo. 5 estilos visuais.
// ============================================================

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

function site_render_template_premium($empresa, $dados, $estado, $preview = false) {
    $estilos = site_estilos();
    $chaveEstilo = site_estilo_da_empresa($empresa);
    $E = $estilos[$chaveEstilo];
    $nome = $empresa['nome'];
    $modo = $dados['modo'];
    $f = site_fragmentos_marcadores($empresa, $dados, $estado);

    // Cor de destaque: a da empresa (se ela escolheu uma) ou a do estilo
    $corEmpresa = cor_hex_valida_site($empresa['cor_primaria'] ?? '', '');
    $padroes = ['', '#4f7cff', '#1a1a2e', '#2563eb'];
    $accent = in_array(strtolower($corEmpresa), $padroes, true) ? $E['accent'] : $corEmpresa;
    $lumBg = frag_luminancia($E['bg']); $lumAc = frag_luminancia($accent);
    if (abs($lumBg - $lumAc) < 0.12) $accent = $E['accent']; // destaque some no fundo: usa o do estilo

    // Fotos: banner > galeria > banco de fotos profissionais
    $fotosBanco = (empty($empresa['banner_imagem']) || count($dados['galeria']) < 2) ? fotos_do_site($empresa) : [];
    $fotoHero = $empresa['banner_imagem'] ?: ($dados['galeria'][0] ?? ($fotosBanco[0]['url'] ?? ''));
    $fotoSobre = $dados['galeria'][1] ?? ($fotosBanco[1]['medio'] ?? ($dados['galeria'][0] ?? ''));
    if ($fotoSobre === $fotoHero) $fotoSobre = $fotosBanco[2]['medio'] ?? '';
    $creditos = array_values(array_filter([$fotosBanco[0]['autor'] ?? '', $fotosBanco[1]['autor'] ?? ''], fn($a) => $a !== '' && ($fotoHero === ($fotosBanco[0]['url'] ?? '') || $fotoSobre === ($fotosBanco[1]['medio'] ?? ''))));

    $titulo = $empresa['seo_titulo'] ?: ($empresa['titulo'] ?: $nome);
    $descricao = $empresa['seo_descricao'] ?: ('Conheça ' . $nome . ($empresa['segmento_nome'] ? ' — ' . $empresa['segmento_nome'] : '') . ($empresa['endereco'] ? ' em ' . $empresa['endereco'] : ''));
    $headline = $empresa['titulo'] ?: $nome;
    $sub = $empresa['subtitulo'] ?: [
        'agendamento' => 'Agende online em menos de um minuto e receba a confirmação no WhatsApp.',
        'orcamento' => 'Conte o que você precisa e receba seu orçamento rapidinho no WhatsApp.',
        'catalogo' => 'Escolha, peça pelo site e acompanhe tudo pelo WhatsApp.',
        'contato' => 'Fale com a gente — atendimento rápido e sem complicação.',
    ][$modo];
    $ctaTexto = site_texto_cta($empresa, $modo);
    $temAgendar = in_array($modo, ['agendamento', 'orcamento'], true);
    $ctaHref = $temAgendar ? '#agendar' : ($modo === 'catalogo' ? '#servicos' : '#contato');
    $linkWhats = so_digitos_frag($empresa['whatsapp'] ?? '') ? frag_link_whatsapp($empresa['whatsapp'], trim((string)($empresa['whatsapp_mensagem'] ?? '')) ?: 'Olá! Vim pelo site e gostaria de mais informações.') : '';
    $nota = $dados['avaliacoes_resumo'];
    $status = $dados['status'] ?? [];
    $rotCatalogo = site_titulo_catalogo($empresa, $modo);

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
        $h .= '</div>' . ($fotoSobre ? '<figure class="pt-sobre-foto" data-anim="clip-up"><img src="' . e($fotoSobre) . '" alt="' . e($nome) . '" loading="lazy" data-parallax="0.06"></figure>' : '') . '</div>';
        $secao('sobre', '<section id="sobre" class="pt-secao"><div class="pt-container">' . $h . '</div></section>', 'Sobre');
    }

    if ($dados['servicos']) {
        $titSv = ['catalogo' => $rotCatalogo === 'Cardápio' ? 'Escolha o seu favorito' : 'Escolha e peça', 'orcamento' => 'O que fazemos', 'agendamento' => 'Escolha seu serviço', 'contato' => 'Como podemos ajudar'][$modo];
        $txtSv = ['catalogo' => 'Adicione ao pedido e finalize em segundos — entrega ou retirada.', 'orcamento' => 'Escolha um serviço e peça seu orçamento sem compromisso.', 'agendamento' => 'Preços claros. Clique em "Agendar" e escolha o melhor horário.', 'contato' => ''][$modo];
        $secao('servicos', '<section id="servicos" class="pt-secao pt-secao-alt"><div class="pt-container">' . $cabecalho($rotCatalogo, $titSv, $txtSv) . '<div data-anim="fade-up">' . $f['{{SERVICOS}}'] . '</div></div></section>', $rotCatalogo);
    }

    // Como funciona (conforme a forma de atendimento)
    $passos = [
        'agendamento' => [['Escolha o serviço', 'Veja preços e duração de cada opção.'], ['Escolha dia e horário', 'Só aparecem horários realmente livres.'], ['Pronto!', 'Confirmação e lembrete chegam no seu WhatsApp.']],
        'orcamento'   => [['Conte o que precisa', 'Data, local e detalhes — leva 1 minuto.'], ['Receba o orçamento', 'O valor chega direto no seu WhatsApp.'], ['Aprove e relaxe', 'Aprovou? A gente cuida de tudo.']],
        'catalogo'    => [['Monte seu pedido', 'Adicione os itens que quiser.'], ['Entrega ou retirada', 'Escolha como prefere receber e pagar.'], ['Acompanhe', 'Cada etapa chega no seu WhatsApp.']],
        'contato'     => [['Chame a gente', 'Pelo WhatsApp ou pelo formulário.'], ['Entendemos seu caso', 'Tiramos todas as dúvidas.'], ['Proposta sob medida', 'Sem compromisso e sem enrolação.']],
    ][$modo];
    $h = '<section id="como-funciona" class="pt-secao"><div class="pt-container">' . $cabecalho('Simples assim', 'Como funciona') . '<ol class="pt-passos" data-stagger="140">';
    foreach ($passos as $i => [$t, $d]) $h .= '<li data-tilt><span class="pt-passo-num">0' . ($i + 1) . '</span><h3>' . e($t) . '</h3><p>' . e($d) . '</p></li>';
    $secao('como', $h . '</ol></div></section>');

    if ($dados['profissionais'] && in_array('equipe', $dados['modulos'] ?? ['equipe'], true)) {
        $secao('equipe', '<section id="equipe" class="pt-secao pt-secao-alt"><div class="pt-container">' . $cabecalho('Equipe', 'Quem cuida de você') . '<div data-anim="fade-up">' . $f['{{EQUIPE}}'] . '</div></div></section>', 'Equipe');
    }
    if ($dados['galeria']) {
        $secao('galeria', '<section id="galeria" class="pt-secao"><div class="pt-container">' . $cabecalho('Galeria', $modo === 'orcamento' ? 'Trabalhos que já fizemos' : 'Por dentro do nosso dia a dia') . '<div data-anim="fade-up">' . $f['{{GALERIA}}'] . '</div></div></section>', 'Galeria');
    }
    if ($dados['blocos']) {
        $h = '<section id="blocos" class="pt-secao pt-secao-alt"><div class="pt-container">' . $cabecalho('Diferenciais', 'Por que escolher a gente') . '<div class="pt-blocos" data-stagger="110">';
        foreach ($dados['blocos'] as $b) $h .= '<article data-tilt><h3>' . e($b['titulo']) . '</h3><p>' . nl2br(e($b['conteudo'])) . '</p></article>';
        $secao('blocos', $h . '</div></div></section>');
    }
    if ($dados['cupons']) {
        $secao('cupons', '<section id="cupons" class="pt-secao"><div class="pt-container">' . $cabecalho('Promoções', 'Cupons de desconto') . '<div data-anim="fade-up">' . $f['{{CUPONS}}'] . '</div></div></section>');
    }
    if ($modo === 'agendamento') {
        $secao('agendar', '<section id="agendar" class="pt-secao pt-secao-destaque"><div class="pt-container pt-estreito">' . $cabecalho('Agendamento online', 'Agende seu horário', 'Escolha o serviço, o profissional e o melhor horário — leva menos de um minuto.') . '<div data-anim="fade-up">' . $f['{{AGENDAMENTO}}'] . '</div></div></section>', 'Agendar');
    } elseif ($modo === 'orcamento') {
        $secao('agendar', '<section id="agendar" class="pt-secao pt-secao-destaque"><div class="pt-container pt-estreito">' . $cabecalho('Sem compromisso', 'Peça seu orçamento', 'Conte o que você precisa — respondemos com o valor no seu WhatsApp.') . '<div data-anim="fade-up">' . $f['{{ORCAMENTO}}'] . '</div></div></section>', 'Orçamento');
    }
    if (in_array('avaliacoes', $dados['modulos'] ?? ['avaliacoes'], true)) {
        $secao('avaliacoes', '<section id="avaliacoes" class="pt-secao"><div class="pt-container">' . $cabecalho('Depoimentos', 'O que dizem nossos clientes') . '<div data-anim="fade-up">' . $f['{{AVALIACOES}}'] . '</div></div></section>', 'Avaliações');
    }
    $secao('contato', '<section id="contato" class="pt-secao pt-secao-alt"><div class="pt-container">' . $cabecalho('Contato', 'Fale com a gente')
        . '<div data-anim="fade-up">' . $f['{{CONTATO}}'] . '</div>'
        . '<div class="pt-contato-extra">' . ($empresa['endereco'] ? '<div data-anim="fade-up">' . $f['{{MAPA}}'] . '</div>' : '')
        . '<div data-anim="fade-up" data-delay="120"><h3 class="pt-h3">Horário de atendimento</h3>' . $f['{{HORARIOS}}'] . '</div></div></div></section>', 'Contato');

    // Ordem: a escolhida no painel (as seções novas entram nos lugares naturais)
    $ordemPadrao = ['sobre', 'servicos', 'como', 'equipe', 'galeria', 'blocos', 'cupons', 'agendar', 'avaliacoes', 'contato'];
    $escolhida = !empty($empresa['secoes']) ? array_values(array_filter(explode(',', $empresa['secoes']))) : null;
    $ordem = $escolhida ? array_values(array_unique(array_merge(array_slice($escolhida, 0, 2), ['como'], array_slice($escolhida, 2)))) : $ordemPadrao;
    if (!in_array('contato', $ordem, true)) $ordem[] = 'contato';
    if ($temAgendar && !in_array('agendar', $ordem, true)) array_splice($ordem, max(0, count($ordem) - 2), 0, ['agendar']);

    $faixa = array_slice(array_map(fn($s) => $s['nome'], $dados['servicos']), 0, 12);
    $fontes = 'https://fonts.googleapis.com/css2?family=' . $E['fontes'] . '&display=swap';
    $bairro = $empresa['endereco'] ? trim(explode('-', $empresa['endereco'])[1] ?? explode(',', $empresa['endereco'])[0]) : '';
    $ano = date('Y');

    ob_start(); ?>
<!DOCTYPE html>
<html lang="pt-BR" class="pt-estilo-<?= e($chaveEstilo) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?></title>
<meta name="description" content="<?= e($descricao) ?>">
<meta property="og:title" content="<?= e($titulo) ?>">
<meta property="og:description" content="<?= e($descricao) ?>">
<meta property="og:type" content="website">
<?php if ($fotoHero || $empresa['logo']): ?><meta property="og:image" content="<?= e($fotoHero ?: $empresa['logo']) ?>"><?php endif; ?>
<meta name="theme-color" content="<?= e($E['bg']) ?>">
<?php if ($empresa['logo']): ?><link rel="icon" href="<?= e($empresa['logo']) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= e($fontes) ?>">
<?php if ($fotoHero): ?><link rel="preload" as="image" href="<?= e($fotoHero) ?>"><?php endif; ?>
<link rel="stylesheet" href="/assets/css/site-premium.css?v=1">
<style>:root{--bg:<?= $E['bg'] ?>;--bg2:<?= $E['bg2'] ?>;--sup:<?= $E['superficie'] ?>;--txt:<?= $E['texto'] ?>;--suave:<?= $E['suave'] ?>;--linha:<?= $E['linha'] ?>;--accent:<?= $accent ?>;--on-accent:<?= frag_cor_contraste($accent) ?>;--f-titulo:<?= $E['titulo'] ?>,Georgia,serif;--f-corpo:<?= $E['corpo'] ?>,system-ui,sans-serif;color-scheme:<?= $E['escuro'] ? 'dark' : 'light' ?>}</style>
<?= frag_css_apoio(['accent' => $accent]) ?>
<?= site_json_ld($empresa, $dados) ?>
<?= $preview ? '' : site_script_analytics($dados['analytics_id']) ?>
</head>
<body class="<?= $fotoHero ? 'hero-foto' : '' ?>">
<div data-progress></div>
<?php if ($preview): ?><div class="pt-preview-aviso">Pré-visualização — estilo <?= e($E['nome']) ?> (formulários não enviam de verdade aqui)</div><?php endif; ?>
<a class="pt-pular" href="#conteudo">Pular para o conteúdo</a>

<header class="pt-topo" data-header-scroll>
    <div class="pt-container pt-topo-in">
        <a class="pt-marca" href="#inicio"><?php if ($empresa['logo']): ?><img src="<?= e($empresa['logo']) ?>" alt=""><?php endif; ?><span><?= e($nome) ?></span></a>
        <nav class="pt-menu" aria-label="Menu principal">
            <?php foreach ($ordem as $k): if (isset($sec[$k], $nav[$k])): ?><a href="#<?= e($k) ?>"><?= e($nav[$k]) ?></a><?php endif; endforeach; ?>
        </nav>
        <a class="pt-btn pt-btn-sm pt-topo-cta" href="<?= e($ctaHref) ?>" data-magnetic><?= e($ctaTexto) ?></a>
        <button class="pt-hamburguer" type="button" aria-label="Abrir menu" aria-expanded="false" onclick="var a=document.body.classList.toggle('gm-menu-aberto');this.setAttribute('aria-expanded',a)"><span></span><span></span></button>
    </div>
</header>

<main id="conteudo">
<section id="inicio" class="pt-hero<?= $fotoHero ? ' com-foto' : '' ?>">
    <?php if ($fotoHero): ?><div class="pt-hero-foto" data-parallax="0.18"><img src="<?= e($fotoHero) ?>" alt="" fetchpriority="high"></div><?php endif; ?>
    <div class="pt-hero-luzes" aria-hidden="true"><span></span><span></span><span></span></div>
    <div class="pt-container pt-hero-in">
        <span class="pt-selo" data-anim="fade-down"><span class="pt-ponto<?= !empty($status['aberto']) ? ' aberto' : '' ?>"></span><?= e(!empty($status['aberto']) ? $status['texto'] : ($empresa['segmento_nome'] && ($empresa['segmento_slug'] ?? '') !== 'outro' ? $empresa['segmento_nome'] : ($empresa['segmento_descricao'] ?: 'Bem-vindo'))) ?></span>
        <h1 data-split><?= e($headline) ?></h1>
        <p class="pt-hero-sub" data-anim="fade-up" data-delay="350"><?= e($sub) ?></p>
        <div class="pt-hero-acoes" data-anim="fade-up" data-delay="500">
            <a class="pt-btn" href="<?= e($ctaHref) ?>" data-magnetic><?= e($ctaTexto) ?> <span aria-hidden="true">→</span></a>
            <?php if ($linkWhats): ?><a class="pt-btn pt-btn-vidro" href="<?= e($linkWhats) ?>"><?= frag_icone('whats') ?> WhatsApp</a><?php endif; ?>
        </div>
        <ul class="pt-confianca" data-stagger="100">
            <?php if ($nota['total'] > 0): ?><li><strong><?= number_format($nota['media'], 1, ',', '') ?> ★</strong> <?= (int)$nota['total'] ?> avaliações</li><?php endif; ?>
            <li><?= ['agendamento' => '⚡ Agendamento em 1 minuto', 'orcamento' => '💬 Orçamento pelo WhatsApp', 'catalogo' => '🛵 Entrega ou retirada', 'contato' => '💬 Resposta rápida'][$modo] ?></li>
            <?php if ($bairro): ?><li>📍 <?= e(mb_strimwidth($bairro, 0, 40, '…')) ?></li><?php endif; ?>
        </ul>
    </div>
    <a class="pt-rolar" href="#<?= e($ordem[0] ?? 'contato') ?>" aria-label="Rolar para baixo"><span></span></a>
</section>

<?php if (count($faixa) >= 3): ?>
<div class="pt-faixa" data-marquee style="--gm-velocidade:<?= max(18, count($faixa) * 4) ?>s">
    <?php foreach ($faixa as $item): ?><span><?= e($item) ?></span><span class="pt-faixa-sep" aria-hidden="true">✦</span><?php endforeach; ?>
</div>
<?php endif; ?>

<?php foreach ($ordem as $k): if (isset($sec[$k])) echo $sec[$k], "\n"; endforeach; ?>

<section class="pt-cta-final">
    <div class="pt-container" data-anim="zoom-in">
        <h2 data-split><?= e(['agendamento' => 'Seu próximo horário está a um clique', 'orcamento' => 'Vamos tirar sua ideia do papel?', 'catalogo' => 'Bateu a vontade? Peça agora', 'contato' => 'Vamos conversar?'][$modo]) ?></h2>
        <div class="pt-hero-acoes" style="justify-content:center">
            <a class="pt-btn" href="<?= e($ctaHref) ?>" data-magnetic><?= e($ctaTexto) ?> <span aria-hidden="true">→</span></a>
            <?php if ($linkWhats): ?><a class="pt-btn pt-btn-vidro" href="<?= e($linkWhats) ?>"><?= frag_icone('whats') ?> Chamar no WhatsApp</a><?php endif; ?>
        </div>
    </div>
</section>
</main>

<footer class="pt-rodape">
    <div class="pt-container pt-rodape-grade">
        <div><a class="pt-marca" href="#inicio"><?php if ($empresa['logo']): ?><img src="<?= e($empresa['logo']) ?>" alt=""><?php endif; ?><span><?= e($nome) ?></span></a>
            <p><?= e(mb_strimwidth($descricao, 0, 160, '…')) ?></p></div>
        <div><h4>Navegação</h4><?php foreach ($ordem as $k): if (isset($sec[$k], $nav[$k])): ?><a href="#<?= e($k) ?>"><?= e($nav[$k]) ?></a><?php endif; endforeach; ?></div>
        <div><h4>Contato</h4>
            <?php if ($linkWhats): ?><a href="<?= e($linkWhats) ?>">WhatsApp</a><?php endif; ?>
            <?php if (!empty($empresa['telefone'])): ?><a href="tel:<?= e(so_digitos_frag($empresa['telefone'])) ?>"><?= e($empresa['telefone']) ?></a><?php endif; ?>
            <?php if (!empty($empresa['instagram'])): ?><a href="https://instagram.com/<?= e(ltrim($empresa['instagram'], '@')) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
            <?php if ($empresa['endereco']): ?><span><?= e($empresa['endereco']) ?></span><?php endif; ?></div>
    </div>
    <div class="pt-container pt-rodape-base"><span>© <?= $ano ?> <?= e($nome) ?></span><span><?= $creditos ? 'Fotos: ' . e(implode(', ', $creditos)) . ' / Pexels · ' : '' ?>Site por GDS</span></div>
</footer>
<?= site_mostrar_whatsapp_float($empresa) ? frag_whatsapp_float($empresa['whatsapp'], $empresa['whatsapp_mensagem'] ?? '', $nome) : '' ?>
<?= site_pedido_online($empresa, $modo) ? frag_carrinho($empresa, $estado['pedido'] ?? []) : '' ?>
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
