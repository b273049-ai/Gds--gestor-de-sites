<?php
// ============================================================
// FOTOS PROFISSIONAIS (Pexels — gratuito, uso comercial liberado)
// Sites sem fotos próprias ganham fotos reais do tipo de negócio
// (no topo do template e como opção para a IA). A busca é feita uma
// vez por empresa e fica guardada em sites.fotos_banco.
// Chave grátis em https://www.pexels.com/api/ → Admin → Configurações.
// ============================================================
require_once __DIR__ . '/imagens_ia.php';

// Termos em inglês dão resultados muito melhores no Pexels
function fotos_termo_segmento($slug, $nome = '', $descricao = '') {
    $mapa = [
        'barbearia' => 'barbershop', 'salao' => 'hair salon', 'estetica' => 'beauty spa skincare', 'clinica' => 'medical clinic', 'odontologia' => 'dentist',
        'psicologia' => 'therapy session calm', 'nutricao' => 'healthy food nutrition', 'academia' => 'gym workout', 'petshop' => 'dog grooming', 'mecanica' => 'car mechanic',
        'advogado' => 'law office', 'fotografia' => 'photographer camera', 'dj' => 'dj party lights', 'eventos' => 'event party decoration', 'construcao' => 'home renovation',
        'agencia' => 'creative agency team', 'tecnologia' => 'technology startup', 'consultoria' => 'business meeting', 'imobiliaria' => 'modern house interior',
        'restaurante' => 'restaurant food', 'loja' => 'boutique store', 'hotel' => 'hotel room', 'educacao' => 'classroom students', 'hamburgueria' => 'burger',
        'pizzaria' => 'pizza', 'fast-food' => 'fast food burger fries', 'delivery' => 'food delivery', 'marmitaria' => 'homemade food', 'japones' => 'sushi',
        'churrascaria' => 'barbecue grill', 'acai' => 'acai bowl', 'padaria' => 'bakery bread', 'doceria' => 'cake dessert', 'cafeteria' => 'coffee shop',
        'bar' => 'cocktail bar', 'food-truck' => 'food truck', 'buffet' => 'catering buffet', 'adega' => 'wine shop', 'mercearia' => 'grocery vegetables',
        'manicure' => 'manicure nails', 'sobrancelhas' => 'eyelash extension', 'maquiagem' => 'makeup artist', 'depilacao' => 'spa wax', 'spa' => 'spa massage',
        'tatuagem' => 'tattoo artist', 'trancas' => 'braids hairstyle', 'fisioterapia' => 'physiotherapy', 'veterinario' => 'veterinarian', 'pilates' => 'pilates',
        'yoga' => 'yoga', 'crossfit' => 'crossfit', 'lutas' => 'martial arts', 'danca' => 'dance class', 'natacao' => 'swimming pool', 'banho-tosa' => 'dog grooming',
        'decoracao-festas' => 'party decoration', 'salao-festas' => 'event venue', 'cerimonial' => 'wedding', 'filmagem' => 'videographer', 'banda' => 'live band',
        'eletricista' => 'electrician', 'encanador' => 'plumber', 'pintor' => 'house painter', 'marcenaria' => 'carpentry furniture', 'arquitetura' => 'interior design',
        'jardinagem' => 'garden landscaping', 'limpeza' => 'house cleaning', 'ar-condicionado' => 'air conditioning technician', 'energia-solar' => 'solar panels',
        'lava-jato' => 'car wash detailing', 'funilaria' => 'car body repair', 'autoescola' => 'driving lesson', 'contabilidade' => 'accountant office',
        'sites-apps' => 'web design laptop', 'social-media' => 'social media marketing', 'design' => 'graphic designer', 'idiomas' => 'language class',
        'escola' => 'school children', 'moda' => 'fashion clothing store', 'calcados' => 'shoes store', 'joias' => 'jewelry', 'cosmeticos' => 'cosmetics',
        'floricultura' => 'flower bouquet', 'moveis' => 'furniture living room', 'agencia-viagens' => 'travel beach', 'igreja' => 'church community',
    ];
    if (isset($mapa[$slug])) return $mapa[$slug];
    return trim($descricao ?: $nome) ?: 'small business';
}

// No Openverse, só os acervos de fotógrafos (o resto é Flickr de qualquer pessoa: vinha foto pessoal
// e até sem relação com o negócio). rawpixel e StockSnap são fotos de estúdio com uso livre.
const FOTOS_OPENVERSE_CURADAS = 'rawpixel,stocksnap';

// Foto de banco que pode ir para o site: dos bancos selecionados, ou do Openverse só dos acervos curados
function fotos_banco_confiavel($x) {
    if (($x['fonte'] ?? '') !== 'openverse') return true;
    $host = strtolower((string)parse_url((string)($x['url'] ?? ''), PHP_URL_HOST));
    return (bool)preg_match('/(^|\.)(rawpixel\.com|stocksnap\.io)$/', $host);
}

// Bancos de fotos grátis, na ordem em que são tentados. O Pexels pausou as chaves novas
// (set/2026): quem já tem continua usando; os outros entram como alternativa.
//   pixabay   → chave grátis em pixabay.com/api/docs (as fotos precisam ser baixadas para o servidor)
//   unsplash  → chave grátis em unsplash.com/developers (usa o link deles e registra o download)
//   openverse → sem chave; só fotos de domínio público / CC0 (não exigem crédito)
function fotos_fontes() {
    $f = [];
    if (get_config('pexels_api_key', '') !== '') $f[] = 'pexels';
    if (get_config('pixabay_api_key', '') !== '') $f[] = 'pixabay';
    if (get_config('unsplash_api_key', '') !== '') $f[] = 'unsplash';
    if (get_config('fotos_openverse', '1') === '1') $f[] = 'openverse';
    return $f;
}

function fotos_configurado() {
    return (bool)fotos_fontes();
}

function fotos_http($url, $cab = [], $timeout = 7) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => $cab, CURLOPT_USERAGENT => 'GDS-Gestor-de-Sites/1.0']);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $resp !== false && $code === 200 ? json_decode($resp, true) : null;
}

// Busca no primeiro banco que trouxer resultado. Retorna [['url','medio','autor','link','alt','fonte','baixar'], ...]
function fotos_buscar($termo, $qtd = 8, $orientacao = 'landscape') {
    if (trim($termo) === '') return [];
    foreach (fotos_fontes() as $fonte) {
        $r = fotos_buscar_em($fonte, $termo, $qtd, $orientacao);
        if ($r) return $r;
    }
    return [];
}

function fotos_buscar_em($fonte, $termo, $qtd, $orientacao) {
    $qtd = max(3, min(20, $qtd));
    $fotos = [];
    if ($fonte === 'pexels') {
        $base = getenv('GDS_PEXELS_BASE_URL') ?: 'https://api.pexels.com';
        $d = fotos_http($base . '/v1/search?' . http_build_query(['query' => $termo, 'per_page' => $qtd, 'orientation' => $orientacao]), ['Authorization: ' . get_config('pexels_api_key', '')]);
        foreach ((array)($d['photos'] ?? []) as $f) {
            if (empty($f['src']['large2x'])) continue;
            $fotos[] = ['url' => $f['src']['large2x'], 'medio' => $f['src']['large'] ?? $f['src']['large2x'], 'autor' => $f['photographer'] ?? '', 'link' => $f['url'] ?? '', 'alt' => $f['alt'] ?? '', 'fonte' => 'pexels'];
        }
    } elseif ($fonte === 'pixabay') {
        $base = getenv('GDS_PIXABAY_BASE_URL') ?: 'https://pixabay.com';
        $d = fotos_http($base . '/api/?' . http_build_query(['key' => get_config('pixabay_api_key', ''), 'q' => mb_substr($termo, 0, 100), 'image_type' => 'photo',
            'orientation' => $orientacao === 'portrait' ? 'vertical' : 'horizontal', 'per_page' => $qtd, 'safesearch' => 'true', 'order' => 'popular']));
        foreach ((array)($d['hits'] ?? []) as $f) {
            if (empty($f['largeImageURL'])) continue;
            // O Pixabay não deixa usar o link deles direto: a foto é baixada para o servidor
            $fotos[] = ['url' => $f['largeImageURL'], 'medio' => $f['largeImageURL'], 'autor' => $f['user'] ?? '', 'link' => $f['pageURL'] ?? '', 'alt' => $f['tags'] ?? '', 'fonte' => 'pixabay', 'baixar' => true];
        }
    } elseif ($fonte === 'unsplash') {
        $base = getenv('GDS_UNSPLASH_BASE_URL') ?: 'https://api.unsplash.com';
        $d = fotos_http($base . '/search/photos?' . http_build_query(['query' => $termo, 'per_page' => min(30, $qtd), 'orientation' => $orientacao === 'portrait' ? 'portrait' : 'landscape', 'content_filter' => 'high']),
            ['Authorization: Client-ID ' . get_config('unsplash_api_key', ''), 'Accept-Version: v1']);
        foreach ((array)($d['results'] ?? []) as $f) {
            if (empty($f['urls']['regular'])) continue;
            $fotos[] = ['url' => $f['urls']['raw'] . '&w=1920&q=80&fm=jpg&fit=max', 'medio' => $f['urls']['regular'], 'autor' => $f['user']['name'] ?? '',
                'link' => ($f['links']['html'] ?? '') . '?utm_source=gds&utm_medium=referral', 'alt' => $f['alt_description'] ?? '', 'fonte' => 'unsplash', 'rastrear' => $f['links']['download_location'] ?? ''];
        }
    } elseif ($fonte === 'openverse') {
        $base = getenv('GDS_OPENVERSE_BASE_URL') ?: 'https://api.openverse.org';
        $d = fotos_http($base . '/v1/images/?' . http_build_query(['q' => $termo, 'license' => 'cc0,pdm', 'category' => 'photograph', 'source' => FOTOS_OPENVERSE_CURADAS, 'page_size' => $qtd,
            'aspect_ratio' => $orientacao === 'portrait' ? 'tall' : 'wide', 'mature' => 'false']), ['Accept: application/json'], 8);
        foreach ((array)($d['results'] ?? []) as $f) {
            if (empty($f['url']) || (int)($f['width'] ?? 0) && (int)$f['width'] < 900) continue; // descarta foto pequena
            // O Openverse não é selecionado (ex.: "spa" trazia festa em asilo): só fica a foto cujo título/etiquetas
            // têm as palavras da busca — sem foto o site usa o topo "Só o nome", melhor que foto errada
            if (!fotos_combina($termo, ($f['title'] ?? '') . ' ' . implode(' ', array_map(fn($t) => $t['name'] ?? '', (array)($f['tags'] ?? []))))) continue;
            $fotos[] = ['url' => $f['url'], 'medio' => $f['url'], 'autor' => $f['creator'] ?? '', 'link' => $f['foreign_landing_url'] ?? '', 'alt' => $f['title'] ?? '', 'fonte' => 'openverse', 'conferida' => 1, 'curada' => 1];
        }
    }
    return $fotos;
}

// A foto fala do mesmo assunto da busca? Busca de 1 palavra: ela precisa aparecer; de 2 ou mais: pelo menos 2
// (compara o começo da palavra: "groom" vale para grooming/groomer)
function fotos_combina($termo, $texto) {
    $raiz = fn($w) => mb_substr($w, 0, 5);
    $ignorar = ['the', 'and', 'with', 'modern', 'small', 'business', 'session', 'calm', 'class', 'store', 'shop', 'office', 'room', 'para', 'com'];
    $palavras = array_values(array_unique(array_filter(preg_split('/[^a-z]+/', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $termo) ?: $termo)), fn($w) => strlen($w) >= 3 && !in_array($w, $ignorar, true))));
    if (!$palavras) return true;
    $tem = array_flip(array_map($raiz, array_filter(preg_split('/[^a-z]+/', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $texto) ?: $texto)), fn($w) => strlen($w) >= 3)));
    $achou = count(array_filter($palavras, fn($w) => isset($tem[$raiz($w)])));
    return $achou >= min(2, count($palavras));
}

// Unsplash pede que cada foto usada seja "registrada" (conta para o fotógrafo)
function fotos_registrar_uso($foto) {
    if (empty($foto['rastrear']) || get_config('unsplash_api_key', '') === '') return;
    $ch = curl_init($foto['rastrear']);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3, CURLOPT_HTTPHEADER => ['Authorization: Client-ID ' . get_config('unsplash_api_key', '')]]);
    curl_exec($ch); curl_close($ch);
}

// Baixa para /uploads as fotos que não podem (ou não devem) ser usadas pelo link de fora
function fotos_localizar(array $fotos, $sub = 'fotos-banco', $max = 6) {
    $pasta = img_ia_pasta($sub);
    $out = [];
    foreach ($fotos as $f) {
        fotos_registrar_uso($f);
        if (empty($f['baixar'])) { $out[] = $f; continue; }
        if (!$pasta || count($out) >= $max) continue;
        $nome = 'b-' . substr(md5($f['url']), 0, 14);
        $existe = glob("$pasta/$nome.*");
        if ($existe) { $arq = $existe[0]; }
        else {
            $ch = curl_init($f['url']);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'GDS-Gestor-de-Sites/1.0']);
            $bytes = curl_exec($ch); $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200; curl_close($ch);
            $arq = $ok && $bytes ? img_ia_salvar($bytes, "$pasta/$nome", 1920) : null;
        }
        if (!$arq) continue;
        $local = '/uploads/' . trim($sub, '/') . '/' . basename($arq);
        $out[] = ['url' => $local, 'medio' => $local] + $f;
    }
    return $out;
}

// Fotos geradas por IA para o site (guardadas junto, em fotos_banco).
//   • ia2 → estilo novo (documental, sem rosto de modelo, luz natural): podem ir para o site.
//   • ia  → as antigas (gente "perfeita" de IA, desfoque de estúdio): ficam no Estúdio para o dono
//           decidir, mas só vão para o site se ele escolher "Incluir as fotos antigas de IA".
function fotos_ia_do_site($empresa) {
    $salvo = json_decode((string)($empresa['fotos_banco'] ?? ''), true);
    $f = fn($u, $antiga) => ['url' => $u, 'medio' => $u, 'autor' => '', 'link' => '', 'alt' => 'Foto exclusiva', 'ia' => true, 'antiga' => $antiga];
    return array_merge(array_map(fn($u) => $f($u, false), array_values((array)($salvo['ia2'] ?? []))),
                       array_map(fn($u) => $f($u, true), array_values((array)($salvo['ia'] ?? []))));
}

function fotos_ia_adicionar($empresaId, $url) {
    global $pdo;
    $st = $pdo->prepare("SELECT fotos_banco FROM sites WHERE empresa_id=?");
    $st->execute([$empresaId]);
    $salvo = json_decode((string)$st->fetchColumn(), true) ?: [];
    $salvo['ia2'] = array_slice(array_values(array_unique(array_merge([$url], (array)($salvo['ia2'] ?? [])))), 0, 9);
    $pdo->prepare("UPDATE sites SET fotos_banco=? WHERE empresa_id=?")->execute([json_encode($salvo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $empresaId]);
    return $salvo['ia2'];
}

// De onde vêm as fotos do topo e do "Sobre" (sites.fotos_fonte). As fotos que a empresa sobe
// (banner e galeria) valem sempre, menos em "nenhuma".
const FOTOS_FONTES = [
    ''        => ['Automático (recomendado)', 'Fotos da empresa; sem elas, fotos reais de banco; IA só no estilo novo, sem rosto de modelo.'],
    'reais'   => ['Só fotos de verdade', 'Nunca usar foto feita por IA. Sem foto real, o topo fica só com o nome em letras grandes.'],
    'nenhuma' => ['Sem fotos no topo', 'Topo tipográfico, elegante e rápido. A galeria com as fotos da empresa continua aparecendo.'],
    'todas'   => ['Incluir as fotos antigas de IA', 'Usa também as fotos de IA do estilo antigo, com pessoas de cara artificial (não recomendado).'],
];

// Acabamento das fotos (sites.foto_tratamento): o mesmo em todas, como faz um fotógrafo ao entregar um ensaio
const FOTOS_TRATAMENTOS = [
    ''        => ['Automático', 'Natural nas fotos reais; "Filme" nas feitas por IA (tira o brilho artificial).'],
    'natural' => ['Natural', 'Cores fiéis, só um ajuste fino de contraste.'],
    'filme'   => ['Filme', 'Cores suaves e grão leve de fotografia analógica.'],
    'quente'  => ['Quente', 'Tons dourados, clima acolhedor.'],
    'pb'      => ['Preto e branco', 'Clássico e sofisticado; a cor fica por conta da marca.'],
    'marca'   => ['Na cor da marca', 'Fotos monocromáticas tingidas com a cor do site (duotom).'],
];

// Fotos do site para o topo e o "Sobre", na ordem de preferência de um designer:
// fotos reais de banco (feitas por fotógrafos) → IA no estilo novo → (opcional) IA antiga.
function fotos_do_site($empresa, $forcar = false) {
    $fonte = (string)($empresa['fotos_fonte'] ?? '');
    if ($fonte === 'nenhuma') return [];
    $banco = array_values(array_filter(fotos_banco_pexels($empresa, $forcar), 'fotos_banco_confiavel'));
    if ($fonte === 'reais') return $banco;
    $ia = array_values(array_filter(fotos_ia_do_site($empresa), fn($x) => $fonte === 'todas' || empty($x['antiga'])));
    return array_merge($banco, $ia);
}

// Medidas e luz de uma foto guardada no servidor (para não esticar foto pequena nem usar foto escura).
// Resultado guardado num arquivo ao lado, para medir uma vez só.
function fotos_medir($url) {
    static $mem = [];
    if (isset($mem[$url])) return $mem[$url];
    if (!is_string($url) || !preg_match('#^/uploads/[A-Za-z0-9/_.-]+\.(jpe?g|png|webp)$#i', $url) || str_contains($url, '..')) return $mem[$url] = null;
    $arq = __DIR__ . '/..' . $url;
    if (!is_file($arq)) return $mem[$url] = null;
    $cache = $arq . '.medida';
    if (is_file($cache) && filemtime($cache) >= filemtime($arq) && ($m = json_decode((string)@file_get_contents($cache), true))) return $mem[$url] = $m;
    $info = @getimagesize($arq);
    if (!$info) return $mem[$url] = null;
    $m = ['w' => (int)$info[0], 'h' => (int)$info[1], 'luz' => null];
    // Luz média numa miniatura 24x24 (barato) — 0 = preto, 255 = branco
    if (function_exists('imagecreatefromstring') && filesize($arq) < 12 * 1024 * 1024 && ($im = @imagecreatefromstring((string)file_get_contents($arq)))) {
        $p = imagecreatetruecolor(24, 24);
        imagecopyresampled($p, $im, 0, 0, 0, 0, 24, 24, imagesx($im), imagesy($im));
        $soma = 0;
        for ($x = 0; $x < 24; $x++) for ($y = 0; $y < 24; $y++) { $c = imagecolorat($p, $x, $y); $soma += 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255); }
        $m['luz'] = (int)round($soma / 576);
        imagedestroy($p); imagedestroy($im);
    }
    @file_put_contents($cache, json_encode($m));
    return $mem[$url] = $m;
}

// A foto aguenta ocupar o topo inteiro? (larga o bastante e nem escura nem estourada demais)
function fotos_boa_para_topo($url, $larguraMin = 1100) {
    $m = fotos_medir($url);
    if (!$m) return true;   // foto externa (link) ou sem como medir: confia
    return $m['w'] >= $larguraMin && ($m['luz'] === null || ($m['luz'] >= 28 && $m['luz'] <= 235));
}

function fotos_banco_pexels($empresa, $forcar = false) {
    global $pdo;
    $salvo = json_decode((string)($empresa['fotos_banco'] ?? ''), true);
    // Fotos do Openverse guardadas antes do filtro de assunto são buscadas de novo (uma vez)
    $antigas = is_array($salvo) && array_filter((array)($salvo['fotos'] ?? []), fn($x) => ($x['fonte'] ?? '') === 'openverse' && (empty($x['conferida']) || empty($x['curada'])));
    if (!$forcar && !$antigas && is_array($salvo) && isset($salvo['fotos'])) return $salvo['fotos'];
    if ($antigas) $forcar = true;
    if (!fotos_configurado()) return [];
    // Não tenta de novo antes de 1 dia se a última busca não trouxe nada
    if (!$forcar && is_array($salvo) && empty($salvo['fotos']) && time() - (int)($salvo['em'] ?? 0) < 86400) return [];
    $termo = fotos_termo_segmento($empresa['segmento_slug'] ?? '', $empresa['segmento_nome'] ?? '', $empresa['segmento_descricao'] ?? '');
    // Prévia (empresa ainda não existe): guarda por termo por 7 dias, para não buscar a cada visita
    if (empty($empresa['id'])) {
        $chave = 'fotos_cache_' . substr(md5($termo), 0, 12);
        $c = json_decode((string)get_config($chave, ''), true);
        if (!$forcar && is_array($c) && time() - (int)($c['em'] ?? 0) < 7 * 86400) return (array)($c['fotos'] ?? []);
        $fotos = fotos_localizar(fotos_buscar($termo, 12), 'fotos-banco', 6);
        set_config($chave, json_encode(['em' => time(), 'fotos' => $fotos], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $fotos;
    }
    $fotos = fotos_localizar(fotos_buscar($termo, 12), 'fotos-banco', 6);
    try {
        $novo = ['termo' => $termo, 'em' => time(), 'fotos' => $fotos] + array_intersect_key(is_array($salvo) ? $salvo : [], ['ia' => 1, 'ia2' => 1, 'ia_tentativa' => 1]);
        $pdo->prepare("UPDATE sites SET fotos_banco=? WHERE empresa_id=?")->execute([json_encode($novo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $empresa['id']]);
    } catch (PDOException $e) { /* coluna ainda não existe */ }
    return $fotos;
}

// Rotina (cron): sites publicados sem foto própria ganham fotos exclusivas geradas por IA,
// uma por vez (cada uma leva de 5 a 40s), até ter 3. Desliga em Admin → Configurações.
function fotos_ia_rotina() {
    global $pdo;
    if (!img_ia_disponivel() || get_config('img_ia_automatico', '1') !== '1') return ['fotos_ia' => 'desligado'];
    try {
        $sites = $pdo->query("SELECT e.id, e.nome, e.segmento_descricao, seg.slug, seg.nome AS segmento_nome, s.fotos_banco, s.galeria_imagens
            FROM sites s JOIN empresas e ON e.id = s.empresa_id LEFT JOIN segmentos seg ON seg.id = e.segmento_id
            WHERE s.publicado = true AND COALESCE(s.banner_imagem, '') = '' AND COALESCE(s.ia_modo_total, false) = false
            ORDER BY s.empresa_id LIMIT 200")->fetchAll();
    } catch (PDOException $e) { return ['fotos_ia' => 'sem tabela']; }
    foreach ($sites as $s) {
        if (count(array_filter(explode(',', (string)$s['galeria_imagens']))) >= 2) continue;
        $salvo = json_decode((string)$s['fotos_banco'], true) ?: [];
        $ia = (array)($salvo['ia2'] ?? []);     // conta só as do estilo novo (as antigas não vão mais para o site)
        if (count($ia) >= 3 || time() - (int)($salvo['ia_tentativa'] ?? 0) < 1800) continue;
        $salvo['ia_tentativa'] = time();
        $pdo->prepare("UPDATE sites SET fotos_banco=? WHERE empresa_id=?")->execute([json_encode($salvo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $s['id']]);
        $cenas = img_ia_cenas($s['slug'] ?? '', $s['segmento_nome'] ?? '', $s['segmento_descricao'] ?? '');
        $i = min(count($ia), count($cenas) - 1);
        $r = img_ia_gerar(img_ia_prompt_foto($cenas[$i]), $i === 0 ? '16:9' : '4:5', 'ia/empresa-' . $s['id']);
        if (!$r['ok']) return ['fotos_ia' => 'erro: ' . $r['erro']];
        // Junta com a lista atual (a 1ª gerada vira a foto do topo)
        $st = $pdo->prepare("SELECT fotos_banco FROM sites WHERE empresa_id=?"); $st->execute([$s['id']]);
        $atual = json_decode((string)$st->fetchColumn(), true) ?: [];
        $atual['ia2'] = array_slice(array_values(array_unique(array_merge((array)($atual['ia2'] ?? []), [$r['url']]))), 0, 9);
        $pdo->prepare("UPDATE sites SET fotos_banco=? WHERE empresa_id=?")->execute([json_encode($atual, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $s['id']]);
        return ['fotos_ia' => 'empresa ' . $s['id'] . ': ' . count($atual['ia2']) . ' foto(s)'];
    }
    return ['fotos_ia' => 'nada a fazer'];
}

// Botões do painel "De onde vêm as fotos" e "Acabamento das fotos" (empresa → Configurações e admin → Site)
function fotos_opcoes_html($site) {
    $grupo = function ($campo, $opcoes, $atual, $titulo) {
        $h = '<div class="fotos-op"><label>' . e($titulo) . '</label><input type="hidden" name="' . $campo . '" value="' . e($atual) . '">'
           . '<div class="ed-presets" data-preset-alvo="' . $campo . '" style="display:flex;flex-wrap:wrap;gap:6px">';
        foreach ($opcoes as $k => [$nome]) $h .= '<button type="button" class="btn btn-sm ' . ($k === $atual ? 'btn-ligado' : 'btn-ghost') . '" data-preset="' . e($k) . '">' . e($nome) . '</button>';
        $h .= '</div><ul class="dica" style="margin:6px 0 0;padding-left:18px">';
        foreach ($opcoes as [$nome, $desc]) $h .= '<li><b>' . e($nome) . ':</b> ' . e($desc) . '</li>';
        return $h . '</ul></div>';
    };
    $fonte = (string)($site['fotos_fonte'] ?? ''); $trat = (string)($site['foto_tratamento'] ?? '');
    return $grupo('fotos_fonte', FOTOS_FONTES, isset(FOTOS_FONTES[$fonte]) ? $fonte : '', 'De onde vêm as fotos do topo')
         . $grupo('foto_tratamento', FOTOS_TRATAMENTOS, isset(FOTOS_TRATAMENTOS[$trat]) ? $trat : '', 'Acabamento das fotos');
}

// Grava a escolha (valida contra as listas; vazio = automático)
function fotos_opcoes_salvar($empresaId, $post) {
    global $pdo;
    $fonte = (string)($post['fotos_fonte'] ?? ''); $trat = (string)($post['foto_tratamento'] ?? '');
    $pdo->prepare("UPDATE sites SET fotos_fonte=?, foto_tratamento=? WHERE empresa_id=?")
        ->execute([isset(FOTOS_FONTES[$fonte]) && $fonte !== '' ? $fonte : null, isset(FOTOS_TRATAMENTOS[$trat]) && $trat !== '' ? $trat : null, (int)$empresaId]);
}
