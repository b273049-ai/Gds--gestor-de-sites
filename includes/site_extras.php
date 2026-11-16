<?php
// ============================================================
// TEMA DE ÉPOCA + CÓDIGO PERSONALIZADO DO SITE (Admin → Site → Conteúdo e SEO)
// Aplicado por cima de qualquer visual (Limpo, Completo, design da IA), na hora de sair a página:
//   • Época (Natal, Ano Novo, Black Friday, Dia das Mães, Dia dos Namorados): faixa no topo com a
//     mensagem e o botão principal do site, enfeite discreto (neve e luzinhas no Natal) e data para
//     sair sozinho. Respeita "reduzir movimento" (sem animação).
//   • Código: CSS e HTML próprios do site (o HTML entra no fim da página). O agendamento, o carrinho
//     e os formulários continuam funcionando, porque o código só é somado ao que já existe.
// ============================================================

// chave => [nome, emoji, fim padrão (mês-dia), cores da faixa [fundo, texto, destaque], mensagens por forma de atendimento]
const SITE_EPOCAS = [
    'natal' => ['Natal', '🎄', '12-26', ['#7f1022', '#fff7f0', '#f6c453'], [
        'agenda' => 'Natal chegando: garanta seu horário para as festas!', 'catalogo' => 'Encomendas de Natal abertas: faça já o seu pedido!',
        'orcamento' => 'Fim de ano concorrido: peça seu orçamento com antecedência!', 'contato' => 'Feliz Natal! Fale com a gente para as festas.']],
    'ano_novo' => ['Ano Novo', '✨', '01-02', ['#14110a', '#fff8e6', '#e8c35a'], [
        'agenda' => 'Comece o ano com tudo: garanta seu horário!', 'catalogo' => 'Pedidos para a virada abertos: peça já!',
        'orcamento' => 'Planeje o novo ano: peça seu orçamento!', 'contato' => 'Feliz Ano Novo! Conte com a gente no ano que vem.']],
    'black_friday' => ['Black Friday', '🏷️', '12-01', ['#0b0b0b', '#ffffff', '#ffd400'], [
        'agenda' => 'Black Friday: horários especiais, garanta o seu!', 'catalogo' => 'Black Friday: aproveite e faça seu pedido!',
        'orcamento' => 'Black Friday: peça seu orçamento esta semana!', 'contato' => 'Black Friday: fale com a gente!']],
    'maes' => ['Dia das Mães', '💐', '05-15', ['#8a2a4b', '#fff5f8', '#ffd1dc'], [
        'agenda' => 'Dia das Mães: um presente de cuidado. Agende já!', 'catalogo' => 'Dia das Mães: encomende o presente dela!',
        'orcamento' => 'Dia das Mães: surpreenda, peça seu orçamento!', 'contato' => 'Feliz Dia das Mães!']],
    'namorados' => ['Dia dos Namorados', '❤️', '06-13', ['#9d0b28', '#fff4f5', '#ffb3c1'], [
        'agenda' => 'Dia dos Namorados: um cuidado especial para a data. Agende!', 'catalogo' => 'Dia dos Namorados: peça algo especial!',
        'orcamento' => 'Dia dos Namorados: peça seu orçamento!', 'contato' => 'Feliz Dia dos Namorados!']],
];

// Data em que a época sai sozinha, se ninguém escolher outra
function site_epoca_fim_padrao($chave) {
    if (!isset(SITE_EPOCAS[$chave])) return null;
    [$m, $d] = array_map('intval', explode('-', SITE_EPOCAS[$chave][2]));
    $ano = (int)date('Y');
    $fim = mktime(0, 0, 0, $m, $d, $ano);
    if ($fim < strtotime('today') - 86400 * 20) $fim = mktime(0, 0, 0, $m, $d, $ano + 1);   // ex.: Ano Novo escolhido em dezembro
    return date('Y-m-d', $fim);
}

function site_extras_dados($empresaId) {
    global $pdo;
    try {
        $st = $pdo->prepare("SELECT tema_epoca, tema_epoca_ate, tema_epoca_msg, codigo_css, codigo_html FROM sites WHERE empresa_id=?");
        $st->execute([(int)$empresaId]);
        return $st->fetch() ?: [];
    } catch (PDOException $e) { return []; }   // banco ainda sem a migração 38
}

// A época está valendo hoje?
function site_epoca_ativa($x) {
    $k = (string)($x['tema_epoca'] ?? '');
    if (!isset(SITE_EPOCAS[$k])) return null;
    $ate = $x['tema_epoca_ate'] ?: site_epoca_fim_padrao($k);
    return $ate && $ate >= date('Y-m-d') ? $k : null;
}

// Para onde vai o botão da faixa: o primeiro destino que existir na página
function site_epoca_destino($html) {
    foreach (['agendar' => 'Agendar', 'gds-agendamento' => 'Agendar', 'cardapio' => 'Fazer pedido', 'gds-servicos' => 'Ver', 'orcamento' => 'Pedir orçamento', 'contato' => 'Falar com a gente'] as $id => $rot) {
        if (str_contains($html, 'id="' . $id . '"')) return ['#' . $id, $rot];
    }
    return [null, null];
}

function site_epoca_css($k) {
    [, , , [$bg, $txt, $dest]] = SITE_EPOCAS[$k];
    $css = ".gds-epoca{position:relative;z-index:60;background:$bg;color:$txt;font:600 14px/1.35 system-ui,-apple-system,'Segoe UI',sans-serif;"
         . "display:flex;flex-wrap:wrap;gap:6px 14px;align-items:center;justify-content:center;padding:9px 16px;text-align:center}@media(max-width:560px){.gds-epoca{font-size:13px;padding:8px 12px}.gds-epoca a{padding:4px 12px}}"
         . ".gds-epoca a{color:$bg;background:$dest;text-decoration:none;font-weight:800;padding:5px 14px;border-radius:999px;white-space:nowrap}"
         . ".gds-epoca a:focus-visible{outline:3px solid $txt;outline-offset:2px}"
         . ".gds-epoca b{font-weight:800}";
    if ($k === 'natal') {
        // Luzinhas embaixo da faixa e neve leve caindo por cima da página (não atrapalha cliques)
        $css .= ".gds-luzes{height:10px;background:radial-gradient(circle at 6px 5px,#f6c453 0 3px,transparent 3.5px) 0 0/48px 10px,"
              . "radial-gradient(circle at 18px 5px,#e23b4b 0 3px,transparent 3.5px) 0 0/48px 10px,radial-gradient(circle at 30px 5px,#3fbf6f 0 3px,transparent 3.5px) 0 0/48px 10px,"
              . "radial-gradient(circle at 42px 5px,#5aa9ff 0 3px,transparent 3.5px) 0 0/48px 10px,linear-gradient($bg,$bg);animation:gds-pisca 2.4s steps(2) infinite}"
              . "@keyframes gds-pisca{50%{filter:brightness(1.6) saturate(1.3)}}"
              . ".gds-neve{position:fixed;inset:0;pointer-events:none;z-index:9990;overflow:hidden}"
              . ".gds-neve i{position:absolute;top:-12px;width:6px;height:6px;border-radius:50%;background:#fff;opacity:.85;box-shadow:0 0 0 1px rgba(120,140,170,.35),0 0 4px rgba(255,255,255,.7);animation:gds-cai linear infinite}"
              . "@keyframes gds-cai{to{transform:translate3d(30px,105vh,0)}}"
              . "@media (prefers-reduced-motion:reduce){.gds-neve{display:none}.gds-luzes{animation:none}}";
    } elseif ($k === 'ano_novo') {
        $css .= ".gds-epoca{background:linear-gradient(90deg,$bg,#2a2210,$bg)}";
    } elseif ($k === 'namorados' || $k === 'maes') {
        $css .= ".gds-epoca::before{content:'" . SITE_EPOCAS[$k][1] . "';font-size:16px}";
    }
    return $css;
}

function site_epoca_html($k, $msg, $destino) {
    [$nome, $emoji] = SITE_EPOCAS[$k];
    [$href, $rot] = $destino;
    $h = '<aside class="gds-epoca" aria-label="Aviso de ' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '"><span>' . ($k === 'namorados' || $k === 'maes' ? '' : $emoji . ' ')
       . '<b>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</b></span>'
       . ($href ? '<a href="' . $href . '">' . htmlspecialchars($rot, ENT_QUOTES, 'UTF-8') . '</a>' : '') . '</aside>';
    if ($k === 'natal') {
        $h .= '<div class="gds-luzes" aria-hidden="true"></div>';
        $neve = '';
        mt_srand(crc32($msg));   // mesma neve a cada carregamento (a página pode ficar em cache)
        for ($i = 0; $i < 22; $i++) {
            $neve .= sprintf('<i style="left:%d%%;animation-duration:%ds;animation-delay:-%ds;transform:scale(%.2f)"></i>', mt_rand(0, 99), mt_rand(9, 18), mt_rand(0, 18), mt_rand(5, 11) / 10);
        }
        mt_srand();
        $h .= '<div class="gds-neve" aria-hidden="true">' . $neve . '</div>';
    }
    return $h;
}

// Soma a época e o código personalizado ao HTML pronto do site
function site_extras_aplicar($html, $empresa, $modo = '') {
    if (empty($empresa['id'])) return $html;
    $x = site_extras_dados($empresa['id']);
    if (!$x) return $html;
    $css = ''; $topo = ''; $fim = '';
    if ($k = site_epoca_ativa($x)) {
        $modoK = in_array($modo, ['agendamento', 'agenda'], true) ? 'agenda' : ($modo === 'catalogo' ? 'catalogo' : ($modo === 'orcamento' ? 'orcamento' : ($modo === 'contato' ? 'contato' : 'agenda')));
        $msg = trim((string)($x['tema_epoca_msg'] ?? '')) ?: SITE_EPOCAS[$k][4][$modoK];
        $css .= site_epoca_css($k);
        $topo = site_epoca_html($k, $msg, site_epoca_destino($html));
    }
    $cssProprio = trim((string)($x['codigo_css'] ?? ''));
    if ($cssProprio !== '') $css .= "\n/* código personalizado */\n" . str_ireplace('</style', '<\/style', $cssProprio);
    $htmlProprio = trim((string)($x['codigo_html'] ?? ''));
    if ($htmlProprio !== '') $fim = "\n<!-- código personalizado -->\n" . $htmlProprio . "\n";
    // (callback: um "$" no texto da mensagem ou do CSS não pode virar referência da expressão regular)
    if ($css !== '') $html = preg_replace_callback('#</head>#i', fn() => '<style id="gds-extras">' . $css . "</style>\n</head>", $html, 1);
    if ($topo !== '') $html = preg_replace_callback('#<body\b[^>]*>#i', fn($m) => $m[0] . $topo, $html, 1);
    if ($fim !== '') {
        $pos = strripos($html, '</body>');
        $html = $pos !== false ? substr($html, 0, $pos) . $fim . substr($html, $pos) : $html . $fim;
    }
    return $html;
}
