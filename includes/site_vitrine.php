<?php
// ============================================================
// VISUAL "VITRINE" (migração 46) — variação do visual Limpo para quem vende produto
// (cafeteria, lanchonete, restaurante, doceria, loja, pet…). Usa o mesmo motor do Limpo
// (seções, carrinho, agendamento, recursos, rodapé, SEO); muda o topo e o cardápio:
//  • Topo de cor forte com o NOME da empresa gigante e o produto recortado na frente;
//    a cor e o produto trocam sozinhos (um destaque de cada vez), com outros produtos flutuando.
//  • Faixa corrida com as categorias.
//  • Cardápio em cartões coloridos (a cor vem da própria foto do produto) com o produto
//    "saindo" do cartão.
//  • Produto em destaque que sobe e gira de leve conforme a pessoa rola a página.
// O produto recortado vem do botão "Tirar o fundo" do painel (gds-recorte.js, grátis). Sem
// recorte, o topo mostra a foto da empresa numa moldura em arco — nunca imagem inventada.
// ============================================================

// Nichos que saem no Vitrine nas prévias (e no site criado pelo "Fechou! Virar cliente")
const VT_NICHOS_PADRAO = ['gastronomia', 'cafe', 'varejo', 'pet', 'eventos', 'fitness'];

function vt_ativa($empresa) { return ($empresa['visual'] ?? '') === 'vitrine'; }

function vt_nicho_padrao($empresa) { return in_array(site_nicho_da_empresa($empresa), VT_NICHOS_PADRAO, true); }

// ---------- cores ----------
function vt_hex_rgb($h) { $c = sscanf(ltrim((string)$h, '#'), '%02x%02x%02x'); return count(array_filter((array)$c, 'is_int')) === 3 ? $c : [120, 120, 120]; }

function vt_rgb_hsl($r, $g, $b) {
    $r /= 255; $g /= 255; $b /= 255; $mx = max($r, $g, $b); $mn = min($r, $g, $b); $l = ($mx + $mn) / 2; $d = $mx - $mn;
    if ($d < 1e-6) return [0, 0, $l];
    $s = $l > 0.5 ? $d / (2 - $mx - $mn) : $d / ($mx + $mn);
    $h = $mx === $r ? fmod(($g - $b) / $d + 6, 6) : ($mx === $g ? ($b - $r) / $d + 2 : ($r - $g) / $d + 4);
    return [$h * 60, $s, $l];
}

function vt_hsl_hex($h, $s, $l) {
    $h = fmod($h + 360, 360); $c = (1 - abs(2 * $l - 1)) * $s; $x = $c * (1 - abs(fmod($h / 60, 2) - 1)); $m = $l - $c / 2;
    [$r, $g, $b] = $h < 60 ? [$c, $x, 0] : ($h < 120 ? [$x, $c, 0] : ($h < 180 ? [0, $c, $x] : ($h < 240 ? [0, $x, $c] : ($h < 300 ? [$x, 0, $c] : [$c, 0, $x]))));
    return sprintf('#%02x%02x%02x', round(($r + $m) * 255), round(($g + $m) * 255), round(($b + $m) * 255));
}

// Cor viva (o topo e os cartões não podem ficar "apagados"): satura e acerta a luz
function vt_cor_viva($hex) {
    [$h, $s, $l] = vt_rgb_hsl(...vt_hex_rgb($hex));
    if ($s < 0.12) return vt_hsl_hex($h, 0.06, 0.24);
    return vt_hsl_hex($h, max(0.62, min(0.9, $s * 1.5)), max(0.4, min(0.5, $l)));
}
// Cartões: texto branco pequeno em cima → contraste 4,5:1
function vt_cor_forte($hex) { return frag_cor_legivel(vt_cor_viva($hex), ['#ffffff'], 4.6); }
// Topo: o branco em cima é o nome gigante (texto grande) → 3:1 basta e a cor fica mais viva
function vt_cor_topo($hex) { return frag_cor_legivel(vt_cor_viva($hex), ['#ffffff'], 3.2); }

// Cores variadas a partir da cor da marca (para produto sem foto própria): mesma família, tons diferentes
function vt_paleta($accent, $i) {
    [$h, $s] = vt_rgb_hsl(...vt_hex_rgb($accent));
    $giros = [0, 32, -28, 150, 64, -60, 200, 110];
    return vt_cor_viva(vt_hsl_hex($h + $giros[$i % count($giros)], max(0.5, $s), 0.45));
}

// Cor dominante de uma foto do próprio site (só arquivos em /uploads/; guardada em cache)
function vt_cor_imagem($url) {
    static $memo = [];
    if (!is_string($url) || !str_starts_with($url, '/uploads/') || str_contains($url, '..')) return '';
    if (isset($memo[$url])) return $memo[$url];
    $chave = 'vt_cor_' . md5($url);
    $c = function_exists('get_config') ? (string)get_config($chave, '') : '';
    if ($c === '' && function_exists('imagecreatefromstring')) {
        $arq = __DIR__ . '/..' . $url;
        $bytes = is_file($arq) && filesize($arq) < 12 * 1024 * 1024 ? @file_get_contents($arq) : false;
        $im = $bytes ? @imagecreatefromstring($bytes) : false;
        if ($im) {
            $p = imagecreatetruecolor(24, 24); imagealphablending($p, false); imagesavealpha($p, true);
            imagecopyresampled($p, $im, 0, 0, 0, 0, 24, 24, imagesx($im), imagesy($im));
            $soma = [0, 0, 0]; $peso = 0;
            for ($y = 0; $y < 24; $y++) for ($x = 0; $x < 24; $x++) {
                $v = imagecolorat($p, $x, $y); $a = ($v >> 24) & 0x7f;
                if ($a > 90) continue; // transparente (recorte)
                $r = ($v >> 16) & 255; $g = ($v >> 8) & 255; $b = $v & 255;
                [, $s, $l] = vt_rgb_hsl($r, $g, $b);
                $w = 0.15 + $s * (1 - abs($l - 0.5) * 1.6); // pesa mais as cores vivas (o produto, não o branco do chantilly)
                if ($w <= 0) continue;
                $soma[0] += $r * $w; $soma[1] += $g * $w; $soma[2] += $b * $w; $peso += $w;
            }
            if ($peso > 0) $c = vt_cor_viva(sprintf('#%02x%02x%02x', $soma[0] / $peso, $soma[1] / $peso, $soma[2] / $peso));
        }
        if ($c === '') $c = '-';
        if (function_exists('set_config')) { try { set_config($chave, $c); } catch (Throwable $e) {} }
    }
    return $memo[$url] = ($c === '-' ? '' : $c);
}

// Tamanho real de uma imagem do próprio site (para width/height certos: sem deformar e sem "pulo")
function vt_dim($url, $alt = 600) {
    static $memo = [];
    if (isset($memo[$url])) return $memo[$url];
    $d = is_string($url) && str_starts_with($url, '/uploads/') && !str_contains($url, '..') && is_file(__DIR__ . '/..' . $url) ? @getimagesize(__DIR__ . '/..' . $url) : false;
    if (!$d || !$d[0] || !$d[1]) return $memo[$url] = '';
    return $memo[$url] = ' width="' . (int)round($alt * $d[0] / $d[1]) . '" height="' . (int)$alt . '"';
}

function vt_recorte($s) {
    $u = (string)($s['imagem_recorte'] ?? '');
    return str_starts_with($u, '/uploads/') && !str_contains($u, '..') ? $u : '';
}

// Cor viva do produto (a da foto, ou uma da família da marca); $uso: 'cartao' (4,5:1) ou 'topo' (3:1)
function vt_cor_produto($s, $i, $accent, $uso = 'cartao') {
    $viva = vt_cor_imagem(vt_recorte($s)) ?: (vt_cor_imagem((string)($s['imagem'] ?? '')) ?: vt_paleta($accent, $i));
    return $uso === 'topo' ? vt_cor_topo($viva) : vt_cor_forte($viva);
}

// Nome gigante em até 3 linhas equilibradas: devolve [linhas, maior linha]
function vt_linhas_nome($nome) {
    $nome = trim(preg_replace('/\s+/', ' ', (string)$nome));
    $pal = explode(' ', $nome);
    if (mb_strlen($nome) <= 11 || count($pal) === 1) return [[$nome], max(4, mb_strlen($nome))];
    $melhor = null;
    $n = count($pal);
    for ($k = 2; $k <= min(3, $n); $k++) {
        // divide as palavras em k linhas minimizando a maior
        $tot = mb_strlen($nome); $alvo = $tot / $k; $linhas = []; $atual = '';
        foreach ($pal as $i => $p) {
            $cand = $atual === '' ? $p : "$atual $p";
            if ($atual !== '' && mb_strlen($cand) > $alvo + 2 && count($linhas) < $k - 1) { $linhas[] = $atual; $atual = $p; }
            else $atual = $cand;
        }
        $linhas[] = $atual;
        $maior = max(array_map('mb_strlen', $linhas));
        if (!$melhor || $maior < $melhor[1]) $melhor = [$linhas, $maior];
        if ($maior <= 11) break;
    }
    return [$melhor[0], max(4, $melhor[1])];
}

// ---------- topo ----------
// $c: nome, sub, headline, ctaTexto, ctaHref, accent, fotoHero, galeria, modo, rotCatalogo, status, nota, empresa, servicos
function vt_hero($c) {
    $empresa = $c['empresa'];
    $slides = [];
    $lista = $c['servicos'];
    usort($lista, fn($a, $b) => (int)pg_bool($b['destaque'] ?? false) <=> (int)pg_bool($a['destaque'] ?? false));
    foreach ($lista as $i => $s) {
        if (!($r = vt_recorte($s))) continue;
        $slides[] = ['img' => $r, 'nome' => $s['nome'], 'desc' => mb_strimwidth(trim((string)($s['descricao'] ?? '')), 0, 120, '…'), 'cor' => vt_cor_produto($s, $i, $c['accent'], 'topo'), 'preco' => (float)$s['preco'] > 0 ? frag_preco($s['preco']) : ''];
        if (count($slides) >= 4) break;
    }
    [$linhas, $maior] = vt_linhas_nome($c['nome']);
    $corBase = $slides ? $slides[0]['cor'] : vt_cor_topo($c['accent']);
    $palavra = max(4, max(array_map('mb_strlen', explode(' ', trim((string)$c['nome'])))));
    $h = '<section id="inicio" class="vt-hero' . ($slides ? ' vt-com-produto' : ($c['fotoHero'] ? ' vt-com-foto' : ' vt-sem-imagem')) . (count($linhas) > 1 ? ' vt-nome-' . count($linhas) : '') . '" style="--vt-bg:' . e($corBase) . ';--vt-len:' . (int)$maior . ';--vt-palavra:' . (int)$palavra . '" data-vt-hero>';
    $h .= '<div class="vt-palco">';
    $h .= '<span class="vt-bolha vt-b1" aria-hidden="true"></span><span class="vt-bolha vt-b2" aria-hidden="true"></span><span class="vt-bolha vt-b3" aria-hidden="true"></span><span class="vt-bolha vt-b4" aria-hidden="true"></span>';
    $h .= '<h1 class="vt-nome">' . implode('', array_map(fn($l, $i) => '<span class="vt-linha" style="--i:' . $i . '">' . e($l) . '</span>', $linhas, array_keys($linhas))) . '</h1>';
    if ($slides) {
        $h .= '<div class="vt-produtos">';
        foreach ($slides as $i => $sl)
            $h .= '<figure class="vt-prod' . ($i === 0 ? ' ativo' : '') . '" data-vt-slide="' . $i . '" data-cor="' . e($sl['cor']) . '" data-nome="' . e($sl['nome']) . '" data-desc="' . e($sl['desc']) . '"' . ($i ? ' aria-hidden="true"' : '') . '>'
                . '<img src="' . e($sl['img']) . '" alt="' . e($sl['nome']) . '"' . ($i === 0 ? ' fetchpriority="high"' : ' loading="lazy"') . vt_dim($sl['img']) . '></figure>';
        $h .= '</div>';
        // os outros produtos flutuando dos lados (decorativos)
        $outros = array_slice(array_merge(array_slice($slides, 1), $slides), 0, 2);
        foreach ($outros as $k => $o) $h .= '<img class="vt-flut vt-flut-' . ($k ? 'd' : 'e') . '" src="' . e($o['img']) . '" alt="" aria-hidden="true"' . vt_dim($o['img'], 220) . '>';
    } elseif ($c['fotoHero']) {
        $h .= '<figure class="vt-arco"><img src="' . e($c['fotoHero']) . '" alt="' . e($c['nome']) . '" fetchpriority="high" width="560" height="700"></figure>';
        foreach (array_slice(array_values(array_filter($c['galeria'], fn($u) => $u !== $c['fotoHero'])), 0, 2) as $k => $g)
            $h .= '<img class="vt-flut vt-flut-foto vt-flut-' . ($k ? 'd' : 'e') . '" src="' . e($g) . '" alt="" aria-hidden="true" loading="lazy" width="200" height="200">';
    }
    $h .= '</div>';
    // base branca: texto do produto, botões e números reais
    $nomeBase = $slides ? $slides[0]['nome'] : $c['headline'];
    $descBase = $slides ? ($slides[0]['desc'] ?: $c['sub']) : $c['sub'];
    // sem produto: o título da base não pode repetir a frase de baixo → usa o ramo (ex.: "Pet shop")
    $norm = fn($t) => mb_strtolower(trim(preg_replace('/[^\p{L}\p{N} ]+/u', '', (string)$t)));
    if (!$slides && ($nomeBase === '' || str_contains($norm($descBase), $norm($nomeBase)) || str_contains($norm($nomeBase), $norm($descBase)))) $nomeBase = $c['segNome'] ?: $c['nome'];
    $dadosVivos = [];
    if (($c['nota']['total'] ?? 0) >= 3) $dadosVivos[] = ['<span aria-hidden="true">★</span> ' . number_format($c['nota']['media'], 1, ',', ''), (int)$c['nota']['total'] . ' avaliações' . (($c['nota']['fonte'] ?? '') === 'google' ? ' no Google' : '')];
    if ($c['modo'] === 'catalogo' && !empty($empresa['tempo_entrega'])) $dadosVivos[] = [e($empresa['tempo_entrega']), 'para entregar'];
    if (!empty($c['status']['texto'])) $dadosVivos[] = ['<span class="vt-pulso' . (!empty($c['status']['aberto']) ? ' aberto' : '') . '" aria-hidden="true"></span>' . (!empty($c['status']['aberto']) ? 'Aberto' : 'Fechado'), e(preg_replace('/^(Aberto|Fechado)( agora)?\s*[·,-]?\s*/iu', '', $c['status']['texto']) ?: 'agora')];
    $h .= '<div class="vt-base"><div class="vt-base-in">'
        . '<div class="vt-base-texto" aria-live="polite"><p class="vt-base-nome" data-vt-nome>' . e($nomeBase) . '</p><p class="vt-base-desc" data-vt-desc>' . e($descBase) . '</p></div>'
        . '<div class="vt-base-acoes"><a class="vt-pilula vt-pilula-cheia" href="' . e($c['ctaHref']) . '" data-ev="' . ($c['modo'] === 'catalogo' ? 'pedido' : 'cta') . '">' . e($c['ctaTexto']) . '</a>'
        . (!in_array($c['ctaHref'], ['#servicos', '#gds-servicos'], true) && $c['modo'] !== 'contato' && $c['temServicos'] ? '<a class="vt-pilula" href="#gds-servicos">Ver ' . e(mb_strtolower($c['rotCatalogo'])) . '</a>'
            : ($c['linkWhats'] ? '<a class="vt-pilula" href="' . e($c['linkWhats']) . '" target="_blank" rel="noopener" data-ev="whatsapp">' . frag_icone('whats') . ' WhatsApp</a>' : ''))
        . (count($slides) > 1 ? '<span class="vt-setas"><button type="button" class="vt-seta" data-vt-ir="-1" aria-label="Produto anterior">‹</button><button type="button" class="vt-seta" data-vt-ir="1" aria-label="Próximo produto">›</button></span>' : '') . '</div>'
        . ($dadosVivos ? '<dl class="vt-base-dados">' . implode('', array_map(fn($d) => '<div><dt>' . $d[0] . '</dt><dd>' . $d[1] . '</dd></div>', $dadosVivos)) . '</dl>' : '')
        . '</div></div></section>';
    return $h;
}

// ---------- faixa corrida ----------
function vt_faixa($servicos, $nicho) {
    $itens = array_values(array_unique(array_filter(array_map(fn($s) => trim((string)($s['categoria'] ?? '')), $servicos))));
    if (count($itens) < 3) $itens = array_values(array_unique(array_merge($itens, array_map(fn($s) => trim((string)$s['nome']), array_slice($servicos, 0, 8)))));
    $itens = array_slice(array_filter($itens, fn($t) => $t !== '' && mb_strlen($t) <= 28), 0, 10);
    if (count($itens) < 3) return '';
    $um = '';
    foreach ($itens as $t) $um .= '<span>' . e($t) . '</span><i aria-hidden="true"></i>';
    return '<div class="vt-faixa"><div class="vt-faixa-trilho"><div class="vt-faixa-grupo">' . $um . '</div><div class="vt-faixa-grupo" aria-hidden="true">' . $um . '</div></div></div>';
}

// ---------- cardápio em cartões coloridos (variante "vitrine" de frag_servicos) ----------
function frag_servicos_vitrine($servicos, $opts) {
    $accent = $opts['accent'] ?? '#2563eb';
    $categorias = array_values(array_unique(array_filter(array_map(fn($s) => trim((string)($s['categoria'] ?? '')), $servicos))));
    $cardapio = ($opts['modo'] ?? '') === 'catalogo';
    $maisPedidos = array_flip(array_map('intval', (array)($opts['mais_pedidos'] ?? [])));
    $html = '<div class="gds-c gds-servicos vt-cardapio" id="gds-servicos" data-gds-filtravel>';
    $temBusca = count($servicos) >= ($cardapio ? 10 : 12);
    if ($temBusca) $html .= '<div class="gds-busca-cardapio"><input type="search" data-gds-busca placeholder="' . ($cardapio ? 'Buscar no cardápio' : 'Buscar serviço') . '" aria-label="' . ($cardapio ? 'Buscar no cardápio' : 'Buscar serviço') . '"></div>';
    if (count($categorias) > 1) {
        $html .= '<div class="gds-filtros vt-filtros" role="group" aria-label="Filtrar por categoria"><button type="button" class="gds-filtro" aria-pressed="true" data-gds-filtro="">Todos</button>';
        foreach ($categorias as $c) $html .= '<button type="button" class="gds-filtro" aria-pressed="false" data-gds-filtro="' . e($c) . '">' . e($c) . '</button>';
        $html .= '</div>';
    }
    $html .= '<div class="vt-grade">';
    foreach ($servicos as $i => $s) {
        $rec = vt_recorte($s);
        $foto = (string)($s['imagem'] ?? '');
        $cor = vt_cor_produto($s, $i, $accent);
        $aPartir = pg_bool($s['preco_a_partir'] ?? false);
        $selo = pg_bool($s['destaque'] ?? false) ? 'Destaque' : (isset($maisPedidos[(int)$s['id']]) ? ($cardapio ? 'Mais pedido' : 'Mais procurado') : '');
        $html .= '<article class="gds-svc vt-card' . ($rec ? ' vt-card-recorte' : ($foto ? ' vt-card-foto' : ' vt-card-sem')) . '" style="--vt-cor:' . e($cor) . ';--i:' . ($i % 12) . '" data-gds-cat="' . e(trim((string)($s['categoria'] ?? ''))) . '" data-busca="' . e(mb_strtolower($s['nome'] . ' ' . ($s['descricao'] ?? '') . ' ' . ($s['categoria'] ?? ''))) . '">';
        if ($rec) $html .= '<img class="vt-card-img" src="' . e($rec) . '" alt="' . e($s['nome']) . '" loading="lazy"' . (vt_dim($rec, 190) ?: ' width="160" height="190"') . '>';
        elseif ($foto) $html .= '<img class="vt-card-img" src="' . e($foto) . '" alt="' . e($s['nome']) . '" loading="lazy" width="400" height="300">';
        else $html .= '<span class="vt-card-letra" aria-hidden="true">' . e(mb_strtoupper(mb_substr(trim($s['nome']), 0, 1))) . '</span>';
        $html .= '<div class="vt-card-corpo">' . ($selo ? '<span class="vt-card-selo">' . e($selo) . '</span>' : '')
            . (!empty($s['categoria']) && count($categorias) > 1 ? '<span class="vt-card-cat">' . e($s['categoria']) . '</span>' : '')
            . '<h3>' . e($s['nome']) . '</h3>'
            . (!empty($s['descricao']) ? '<p class="vt-card-desc">' . e($s['descricao']) . '</p>' : '');
        $meta = [];
        if (($opts['modo'] ?? '') === 'agendamento' && ($d = frag_duracao($s['duracao_minutos'] ?? 0))) $meta[] = $d;
        if ($cardapio && !empty($s['serve'])) $meta[] = 'Serve ' . (int)$s['serve'] . ((int)$s['serve'] > 1 ? ' pessoas' : ' pessoa');
        if (!empty($s['modelo_3d'])) $meta[] = 'Veja em 3D';
        if ($meta) $html .= '<p class="vt-card-meta">' . e(implode(' · ', $meta)) . '</p>';
        $html .= '<div class="vt-card-rodape"><span class="vt-card-preco' . ((float)$s['preco'] > 0 ? '' : ' vt-sem-preco') . '">' . frag_preco_de($s) . ($aPartir && (float)$s['preco'] > 0 ? '<small>a partir de</small> R$ ' . number_format((float)$s['preco'], 2, ',', '.') : frag_preco($s['preco'])) . '</span>'
            . frag_botao_servico($s, $opts) . '</div></div></article>';
    }
    $html .= '</div>';
    if ($temBusca) $html .= '<p class="gds-mute" data-gds-busca-vazio hidden>Nada encontrado. Tente outra palavra.</p>';
    return $html . '</div>';
}

// ---------- produto em destaque (sobe e gira de leve ao rolar) ----------
function vt_destaque($servicos, $opts) {
    $lista = $servicos;
    usort($lista, fn($a, $b) => (int)pg_bool($b['destaque'] ?? false) <=> (int)pg_bool($a['destaque'] ?? false));
    $alvo = null; $idx = 0;
    foreach ($lista as $i => $s) if (vt_recorte($s)) { $alvo = $s; $idx = $i; break; }
    if (!$alvo) return '';
    $cor = vt_cor_produto($alvo, $idx, $opts['accent'] ?? '#2563eb');
    $opc = [];
    if (function_exists('opc_ler')) foreach (opc_ler($alvo) as $g) if (count($g['itens'] ?? []) >= 2) { $opc = ['nome' => $g['nome'], 'itens' => array_slice(array_map(fn($x) => $x['nome'], $g['itens']), 0, 5)]; break; }
    $letra = mb_strtoupper(mb_substr(trim($alvo['nome']), 0, 1));
    return '<section id="destaque" class="vt-destaque gds-c" style="--vt-cor:' . e($cor) . '" data-vt-rola aria-labelledby="vtDestTit">'
        . '<span class="vt-dest-letra" aria-hidden="true">' . e($letra) . '</span>'
        . '<div class="lp-larg vt-dest-in">'
        . '<div class="vt-dest-texto"><p class="vt-dest-sobre">O queridinho da casa</p><h2 id="vtDestTit">' . e($alvo['nome']) . '</h2>'
        . (!empty($alvo['descricao']) ? '<p class="vt-dest-desc">' . e($alvo['descricao']) . '</p>' : '')
        . ($opc ? '<p class="vt-dest-opc-tit">' . e($opc['nome']) . '</p><ul class="vt-dest-opc">' . implode('', array_map(fn($x) => '<li>' . e($x) . '</li>', $opc['itens'])) . '</ul>' : '')
        . '<div class="vt-dest-acao"><span class="vt-card-preco">' . ((float)$alvo['preco'] > 0 ? frag_preco($alvo['preco']) : '') . '</span>' . frag_botao_servico($alvo, $opts) . '</div></div>'
        . '<figure class="vt-dest-fig"><img src="' . e(vt_recorte($alvo)) . '" alt="' . e($alvo['nome']) . '" loading="lazy"' . vt_dim(vt_recorte($alvo), 560) . '></figure>'
        . '<div class="vt-dest-cartao" aria-hidden="true"><span class="vt-dest-cartao-nome">' . e($alvo['nome']) . '</span>' . (!empty($alvo['categoria']) ? '<span class="vt-dest-cartao-cat">' . e($alvo['categoria']) . '</span>' : '')
        . ((float)$alvo['preco'] > 0 ? '<span class="vt-dest-cartao-preco">' . frag_preco($alvo['preco']) . '</span>' : '') . '<span class="vt-dest-cartao-letra">' . e($letra) . '</span></div>'
        . '</div></section>';
}

// ============================================================
// VITRINE NOS SITES FEITOS PELA IA (Estúdio IA)
// A IA desenha o resto do site e encaixa os componentes vivos do Vitrine pelos marcadores
// {{VITRINE_TOPO}} {{VITRINE_FAIXA}} {{VITRINE_CARDAPIO}} {{VITRINE_DESTAQUE}} — com os mesmos produtos
// recortados, cores tiradas das fotos, animações e carrinho dos sites padrão.
// ============================================================
const VT_MARCADORES_IA = ['{{VITRINE_TOPO}}', '{{VITRINE_FAIXA}}', '{{VITRINE_CARDAPIO}}', '{{VITRINE_DESTAQUE}}'];

function vt_usa_ia($html) { return is_string($html) && str_contains($html, '{{VITRINE_'); }

// Cor de destaque da empresa com contraste (a mesma regra do visual Limpo)
function vt_accent_empresa($empresa, $dados) {
    $c = cor_hex_valida_site($empresa['cor_primaria'] ?? '', '');
    $acc = $c !== '' ? $c : (($dados['tema']['accent'] ?? '') ?: '#1f6feb');
    return frag_contraste($acc, '#ffffff') >= 4.5 ? $acc : frag_cor_legivel($acc, ['#ffffff'], 4.6);
}

function vt_marcadores_ia($empresa, $dados, $opts) {
    $modo = $dados['modo'];
    $accent = vt_accent_empresa($empresa, $dados);
    $nome = $empresa['nome'];
    $hs = function_exists('site_headline_segmento') ? site_headline_segmento($empresa['segmento_slug'] ?? '') : [];
    $sub = $empresa['subtitulo'] ?: ($hs[1] ?? '');
    $rot = site_titulo_catalogo($empresa, $modo);
    $segNome = $empresa['segmento_nome'] ?? '';
    $galeria = $dados['galeria'] ?? [];
    $foto = $empresa['banner_imagem'] ?: ($galeria[0] ?? '');
    $optsVt = $opts + ['accent' => $accent];
    // o cardápio e o destaque já trazem o "gds-c" dos componentes; o topo e a faixa não (o estilo deles é o do Vitrine)
    $embrulho = fn($h) => $h === '' ? '' : '<div class="lp-vitrine vt-ia">' . $h . '</div>';
    return [
        '{{VITRINE_TOPO}}' => $embrulho(vt_hero(['empresa' => $empresa, 'nome' => $nome, 'sub' => $sub, 'headline' => $hs[0] ?? $sub, 'ctaTexto' => $opts['texto_principal'], 'ctaHref' => $opts['link_principal'],
            'accent' => $accent, 'fotoHero' => $foto, 'galeria' => $galeria, 'modo' => $modo, 'rotCatalogo' => $rot, 'status' => $dados['status'] ?? [], 'nota' => $dados['avaliacoes_resumo'] ?? ['total' => 0],
            'servicos' => $dados['servicos'], 'temServicos' => (bool)$dados['servicos'], 'linkWhats' => $opts['link_whats'], 'segNome' => $segNome])),
        '{{VITRINE_FAIXA}}' => $embrulho(vt_faixa($dados['servicos'], site_nicho_da_empresa($empresa))),
        '{{VITRINE_CARDAPIO}}' => $dados['servicos'] ? $embrulho(frag_servicos_vitrine($dados['servicos'], $optsVt)) : '',
        '{{VITRINE_DESTAQUE}}' => $embrulho(vt_destaque($dados['servicos'], $optsVt)),
    ];
}

// O que o site da IA precisa carregar quando usa o Vitrine (fontes, CSS, cores e o script das animações)
function vt_ia_cabeca($empresa, $dados) {
    $acc = vt_accent_empresa($empresa, $dados);
    return '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anton&family=Instrument+Serif:ital@0;1&family=DM+Sans:opsz,wght@9..40,400..700&display=swap">'
        . '<link rel="stylesheet" href="/assets/css/site-vitrine.css?v=3">'
        . '<style>.vt-ia{--accent:' . $acc . ';--on-accent:#ffffff;--accent-txt:' . frag_cor_legivel($acc, ['#fbfaf8', '#ffffff'], 4.6) . '}</style>'
        . '<script>(function(h){if(!document.fonts||!document.fonts.load)return;h.classList.add(\'vt-espera\');var f=function(){h.classList.remove(\'vt-espera\')};Promise.race([document.fonts.load(\'400 40px Anton\'),new Promise(function(r){setTimeout(r,2500)})]).then(f,f)})(document.documentElement)</script>';
}
function vt_ia_corpo() { return '<script src="/assets/js/site-vitrine.js?v=3" defer></script>'; }
