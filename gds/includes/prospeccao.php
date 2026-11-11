<?php
// ============================================================
// CENTRAL DE PROSPECÇÃO
// - Caçada em massa: vários segmentos × várias regiões, cada região dividida
//   em quadrantes (varredura em grade) para passar do limite de 60 por busca.
//   Roda em fila (tela ou cron).
// - Pontuação de 0 a 100, concorrência do bairro/cidade, prévia do site pronta
//   por lead (com rastreamento de abertura), cadência de 3 mensagens e mapa.
// Fontes: Google Places API (New) ou OpenStreetMap (grátis, sem chave).
// ============================================================

require_once __DIR__ . '/cache_pagina.php';

function prosp_chave_google() {
    return get_config('google_places_key', '') ?: get_config('google_maps_key', '');
}

// Instagram, Facebook, Linktree, iFood etc. não são "site próprio"
function prosp_site_proprio($url) {
    $url = strtolower(trim((string)$url));
    if ($url === '') return false;
    $host = parse_url(str_contains($url, '://') ? $url : 'http://' . $url, PHP_URL_HOST) ?: '';
    if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) return false; // site de empresa não é um IP solto
    $redes = ['instagram.com', 'facebook.com', 'fb.com', 'linktr.ee', 'wa.me', 'whatsapp.com', 'api.whatsapp.com', 'ifood.com.br', 'business.site',
              'goo.gl', 'g.page', 'bit.ly', 'beacons.ai', 'linkbio', 'tiktok.com', 'youtube.com', 'twitter.com', 'x.com', 'linkedin.com',
              'booksy.com', 'trinks.com', 'anota.ai', 'goomer.app', 'aiqfome.com', 'cardapioweb.com', 'menudino.com', 'olaclick.com', 'sites.google.com',
              'doctoralia.com.br', 'getninjas.com.br', 'casamentos.com.br', 'appbarber.com.br', 'linkme.bio', 'taplink.cc'];
    foreach ($redes as $r) if ($host === $r || str_ends_with($host, '.' . $r)) return false;
    return true;
}

function prosp_tipo_link($url) {
    $u = strtolower((string)$url);
    if ($u === '') return 'nenhum';
    if (str_contains($u, 'instagram')) return 'instagram';
    if (str_contains($u, 'facebook') || str_contains($u, 'fb.com')) return 'facebook';
    if (str_contains($u, 'wa.me') || str_contains($u, 'whatsapp')) return 'whatsapp';
    return prosp_site_proprio($u) ? 'site' : 'outro';
}

// Telefone brasileiro → [exibição, número para WhatsApp (só celular) ou '']
function prosp_telefone($tel) {
    $d = preg_replace('/\D/', '', (string)$tel);
    if (str_starts_with($d, '55') && strlen($d) >= 12) $d = substr($d, 2);
    $d = ltrim($d, '0');
    if (strlen($d) === 10 && in_array($d[2], ['6', '7', '8', '9'], true)) $d = substr($d, 0, 2) . '9' . substr($d, 2); // celular antigo sem o 9
    $celular = strlen($d) === 11 && $d[2] === '9';
    $exib = strlen($d) === 11 ? sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7)) : (strlen($d) === 10 ? sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6)) : (string)$tel);
    return [$exib, $celular ? '55' . $d : ''];
}

// "Rua X, 123 - Moema, São Paulo - SP, 04000-000, Brasil" → "Moema"
function prosp_bairro($endereco) {
    if (preg_match('/\d[^,]*?\s-\s([^,]+),/u', (string)$endereco, $m)) return mb_substr(trim($m[1]), 0, 120);
    return '';
}

// Nichos que mais compram site (agenda, pedido, orçamento)
function prosp_nicho_quente($texto) {
    return (bool)preg_match('/barbe|sal[aã]o|cabel|est[eé]tic|manicure|unha|sobrancel|c[ií]lio|tatu|pet|banho e tosa|veterin|dent|odonto|cl[ií]nica|fisio|psic|nutri|pilates|academia|personal|hamburg|pizz|a[cç]a[ií]|lanch|marmit|restaur|delivery|confeit|doce|bolo|padaria|mec[aâ]nic|oficina|lava|est[eé]tica automotiva|fot[oó]graf|dj|buffet|decora|eventos|advog|contab|pousada|escola|curso/iu', (string)$texto);
}

// Pontuação 0-100: quanto mais alto, mais chance de comprar um site agora
function prosp_avaliar(array $p) {
    $pts = 0; $motivos = [];
    $tipo = prosp_tipo_link($p['site'] ?? '');
    if ($tipo === 'nenhum') { $pts += 35; $motivos[] = 'Sem site'; }
    elseif ($tipo !== 'site') { $pts += 28; $motivos[] = 'Só ' . ($tipo === 'outro' ? 'link de terceiros' : ucfirst($tipo)) . ', sem site próprio'; }
    else { $motivos[] = 'Já tem site'; }
    if (!empty($p['whatsapp'])) { $pts += 20; $motivos[] = 'Celular (dá para chamar no WhatsApp)'; }
    elseif (!empty($p['telefone'])) { $pts += 6; $motivos[] = 'Só telefone fixo'; }
    $av = (int)($p['avaliacoes'] ?? 0); $nota = (float)($p['nota'] ?? 0);
    if ($av >= 100) { $pts += 20; $motivos[] = "$av avaliações (negócio com movimento)"; }
    elseif ($av >= 30) { $pts += 15; $motivos[] = "$av avaliações"; }
    elseif ($av >= 8) { $pts += 8; $motivos[] = "$av avaliações"; }
    if ($nota >= 4.5) { $pts += 10; $motivos[] = number_format($nota, 1, ',', '') . '★ (clientes gostam)'; }
    elseif ($nota >= 4.0) $pts += 6;
    if (prosp_nicho_quente(($p['categoria'] ?? '') . ' ' . ($p['nome'] ?? '') . ' ' . ($p['busca'] ?? ''))) { $pts += 10; $motivos[] = 'Ramo que usa agenda/pedido/orçamento'; }
    if ($tipo === 'site') $pts = min($pts, 30); // quem já tem site é cliente para "site melhor", não prioridade
    return [min(100, $pts), $motivos];
}

function prosp_temperatura($pts, $abriu = false) {
    if ($abriu) return ['quente', '🔥 Abriu a prévia'];
    return $pts >= 70 ? ['quente', '🔥 Quente'] : ($pts >= 45 ? ['morno', '🌤 Morno'] : ['frio', '❄ Frio']);
}

function prosp_http($url, $corpo = null, $cab = [], $timeout = 25) {
    $ch = curl_init($url);
    $op = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_HTTPHEADER => $cab, CURLOPT_FOLLOWLOCATION => true,
           CURLOPT_USERAGENT => 'GDS-Gestor-de-Sites/1.0 (prospeccao)'];
    if ($corpo !== null) { $op[CURLOPT_POST] = true; $op[CURLOPT_POSTFIELDS] = $corpo; }
    curl_setopt_array($ch, $op);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); $tipo = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return [$code, $r === false ? null : $r, $err, $tipo];
}

// ---------- Google Places API (New): Text Search ----------
// $caixa = ['s','w','n','e'] limita a busca a um quadrante (varredura em grade)
function prosp_buscar_google($termo, $cidade, $paginas = 3, $caixa = null) {
    $chave = prosp_chave_google();
    $base = getenv('GDS_PLACES_BASE_URL') ?: 'https://places.googleapis.com';
    $ricos = get_config('prosp_dados_ricos', '1') === '1';
    $campos = 'places.id,places.displayName,places.formattedAddress,places.nationalPhoneNumber,places.internationalPhoneNumber,places.websiteUri,'
            . 'places.rating,places.userRatingCount,places.businessStatus,places.googleMapsUri,places.primaryTypeDisplayName,places.location,'
            . 'places.regularOpeningHours.weekdayDescriptions,nextPageToken' . ($ricos ? ',places.photos,places.reviews' : '');
    $lista = []; $token = '';
    for ($i = 0; $i < $paginas; $i++) {
        $corpo = ['textQuery' => $caixa ? $termo : trim("$termo em $cidade"), 'languageCode' => 'pt-BR', 'regionCode' => 'BR', 'pageSize' => 20];
        if ($caixa) $corpo['locationRestriction'] = ['rectangle' => ['low' => ['latitude' => $caixa['s'], 'longitude' => $caixa['w']], 'high' => ['latitude' => $caixa['n'], 'longitude' => $caixa['e']]]];
        if ($token !== '') $corpo['pageToken'] = $token;
        [$code, $resp, $err] = prosp_http(rtrim($base, '/') . '/v1/places:searchText', json_encode($corpo),
            ['Content-Type: application/json', 'X-Goog-Api-Key: ' . $chave, 'X-Goog-FieldMask: ' . $campos]);
        $d = json_decode((string)$resp, true);
        if ($code !== 200) {
            $msg = $d['error']['message'] ?? ($err ?: "código $code");
            if (!$lista) return ['ok' => false, 'erro' => 'Google Places: ' . $msg . (preg_match('/not been used|disabled|PERMISSION/i', (string)$msg) ? ' — ative a "Places API (New)" no Google Cloud para essa chave.' : '')];
            break;
        }
        foreach ((array)($d['places'] ?? []) as $pl) {
            if (($pl['businessStatus'] ?? 'OPERATIONAL') !== 'OPERATIONAL') continue;
            $telBruto = $pl['nationalPhoneNumber'] ?? ($pl['internationalPhoneNumber'] ?? '');
            [$tel, $wa] = prosp_telefone($telBruto);
            $revs = [];
            foreach (array_slice((array)($pl['reviews'] ?? []), 0, 5) as $rv) {
                $txt = trim((string)($rv['text']['text'] ?? $rv['originalText']['text'] ?? ''));
                if ($txt === '' || (int)($rv['rating'] ?? 0) < 4) continue;
                $revs[] = ['autor' => mb_substr((string)($rv['authorAttribution']['displayName'] ?? 'Cliente'), 0, 60), 'nota' => (int)$rv['rating'], 'texto' => mb_substr($txt, 0, 400)];
                if (count($revs) >= 3) break;
            }
            $lista[] = ['origem_id' => 'g:' . $pl['id'], 'fonte' => 'google', 'nome' => $pl['displayName']['text'] ?? '', 'categoria' => $pl['primaryTypeDisplayName']['text'] ?? $termo,
                'endereco' => $pl['formattedAddress'] ?? '', 'telefone' => $telBruto !== '' ? $tel : '', 'whatsapp' => $wa, 'site' => $pl['websiteUri'] ?? '',
                'nota' => $pl['rating'] ?? null, 'avaliacoes' => (int)($pl['userRatingCount'] ?? 0), 'maps_url' => $pl['googleMapsUri'] ?? '',
                'lat' => $pl['location']['latitude'] ?? null, 'lng' => $pl['location']['longitude'] ?? null,
                'fotos' => array_slice(array_map(fn($f) => $f['name'] ?? '', (array)($pl['photos'] ?? [])), 0, 6),
                'avaliacoes_texto' => $revs, 'horario' => (array)($pl['regularOpeningHours']['weekdayDescriptions'] ?? [])];
        }
        $token = $d['nextPageToken'] ?? '';
        if ($token === '') break;
    }
    return ['ok' => true, 'lista' => $lista];
}

// ---------- OpenStreetMap (grátis): Nominatim + Overpass ----------
function prosp_tags_osm($termo) {
    $t = mb_strtolower($termo);
    $mapa = [
        'barbe' => '["shop"="hairdresser"]', 'sal[aã]o|cabelei' => '["shop"="hairdresser"]', 'est[eé]tica|manicure|unha|sobrancelha|depila' => '["shop"="beauty"]',
        'tatu' => '["shop"="tattoo"]', 'hamburg|lanchonete|fast' => '["amenity"="fast_food"]', 'pizz' => '["cuisine"~"pizza"]', 'restaur|marmit' => '["amenity"="restaurant"]',
        'padaria' => '["shop"="bakery"]', 'confeit|doce|bolo' => '["shop"~"confectionery|pastry"]', 'caf[eé]' => '["amenity"="cafe"]', 'a[cç]a[ií]|sorvet' => '["amenity"="ice_cream"]',
        'bar\b|boteco' => '["amenity"~"bar|pub"]', 'academia' => '["leisure"="fitness_centre"]', 'dent|odonto' => '["amenity"="dentist"]', 'cl[ií]nica|m[eé]dic' => '["amenity"~"clinic|doctors"]',
        'pet|banho' => '["shop"="pet"]', 'veterin' => '["amenity"="veterinary"]', 'mec[aâ]nic|oficina' => '["shop"="car_repair"]', 'lava' => '["amenity"="car_wash"]',
        'advog' => '["office"="lawyer"]', 'contab' => '["office"="accountant"]', 'imobili' => '["office"="estate_agent"]', 'flor' => '["shop"="florist"]',
        '[oó]tica' => '["shop"="optician"]', 'roupa|moda|boutique' => '["shop"="clothes"]', 'fot[oó]gra' => '["shop"="photo"]', 'pousada|hotel|hostel' => '["tourism"~"guest_house|hotel|hostel"]',
        'escola|curso|idioma' => '["amenity"~"school|language_school|college"]',
    ];
    foreach ($mapa as $re => $tag) if (preg_match('/' . $re . '/u', $t)) return $tag;
    return null;
}

// Caixa (s,w,n,e) de uma cidade/bairro; guardada em cache para não repetir
function prosp_geocodificar($area) {
    $chaveCache = 'prosp_geo_' . md5(mb_strtolower(trim($area)));
    if (($c = get_config($chaveCache, '')) !== '' && ($j = json_decode($c, true))) return $j;
    $nomi = getenv('GDS_NOMINATIM_URL') ?: 'https://nominatim.openstreetmap.org';
    [$code, $resp] = prosp_http(rtrim($nomi, '/') . '/search?' . http_build_query(['q' => $area . ', Brasil', 'format' => 'json', 'limit' => 1]), null, ['Accept-Language: pt-BR'], 15);
    $g = json_decode((string)$resp, true)[0] ?? null;
    if ($code !== 200 || !$g || empty($g['boundingbox'])) return null;
    [$s, $n, $w, $e] = array_map('floatval', $g['boundingbox']);
    $caixa = ['s' => $s, 'w' => $w, 'n' => $n, 'e' => $e];
    set_config($chaveCache, json_encode($caixa));
    return $caixa;
}

// Divide a caixa em N quadrantes (1, 4, 9, 16)
function prosp_grade($caixa, $n) {
    $lado = max(1, (int)round(sqrt($n)));
    $dLat = ($caixa['n'] - $caixa['s']) / $lado; $dLng = ($caixa['e'] - $caixa['w']) / $lado;
    $cel = [];
    for ($i = 0; $i < $lado; $i++) for ($j = 0; $j < $lado; $j++)
        $cel[] = ['s' => round($caixa['s'] + $i * $dLat, 6), 'n' => round($caixa['s'] + ($i + 1) * $dLat, 6), 'w' => round($caixa['w'] + $j * $dLng, 6), 'e' => round($caixa['w'] + ($j + 1) * $dLng, 6)];
    return $cel;
}

function prosp_buscar_osm($termo, $cidade, $caixa = null) {
    $caixa = $caixa ?: prosp_geocodificar($cidade);
    if (!$caixa) return ['ok' => false, 'erro' => 'Não encontrei "' . $cidade . '" no mapa.'];
    $bbox = "{$caixa['s']},{$caixa['w']},{$caixa['n']},{$caixa['e']}";
    $tag = prosp_tags_osm($termo);
    $nomeRe = str_replace('"', '', preg_quote(mb_strtolower($termo), '/'));
    $filtro = $tag ? "nwr$tag($bbox);" : "nwr[\"name\"~\"$nomeRe\",i][~\"^(shop|amenity|office|craft|leisure)$\"~\".\"]($bbox);";
    $q = "[out:json][timeout:25];($filtro);out center 150;";
    $over = getenv('GDS_OVERPASS_URL') ?: 'https://overpass-api.de/api/interpreter';
    [$code, $resp, $err] = prosp_http($over, http_build_query(['data' => $q]), ['Content-Type: application/x-www-form-urlencoded'], 40);
    if ($code !== 200) return ['ok' => false, 'erro' => 'OpenStreetMap não respondeu agora (' . ($err ?: "código $code") . '). Tente de novo em instantes.'];
    $lista = [];
    foreach ((array)(json_decode((string)$resp, true)['elements'] ?? []) as $el) {
        $t = (array)($el['tags'] ?? []);
        if (empty($t['name'])) continue;
        $telBruto = $t['contact:whatsapp'] ?? $t['phone'] ?? $t['contact:phone'] ?? $t['contact:mobile'] ?? '';
        [$tel, $wa] = prosp_telefone(explode(';', $telBruto)[0]);
        $end = trim(implode(', ', array_filter([trim(($t['addr:street'] ?? '') . ' ' . ($t['addr:housenumber'] ?? '')), $t['addr:suburb'] ?? '', $t['addr:city'] ?? $cidade])), ', ');
        $lat = $el['lat'] ?? ($el['center']['lat'] ?? null); $lon = $el['lon'] ?? ($el['center']['lon'] ?? null);
        $lista[] = ['origem_id' => 'o:' . $el['type'] . $el['id'], 'fonte' => 'osm', 'nome' => $t['name'], 'categoria' => $termo, 'endereco' => $end,
            'telefone' => $telBruto ? $tel : '', 'whatsapp' => $wa, 'site' => $t['website'] ?? $t['contact:website'] ?? $t['contact:instagram'] ?? $t['contact:facebook'] ?? '',
            'nota' => null, 'avaliacoes' => 0, 'lat' => $lat, 'lng' => $lon, 'bairro' => $t['addr:suburb'] ?? '', 'fotos' => [], 'avaliacoes_texto' => [], 'horario' => [],
            'maps_url' => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($t['name'] . ' ' . $cidade)];
    }
    return ['ok' => true, 'lista' => $lista];
}

function prosp_fonte_efetiva($fonte) {
    return $fonte === 'osm' || ($fonte === 'auto' && prosp_chave_google() === '') ? 'osm' : 'google';
}

// Guarda a lista (todas as empresas, inclusive as que já têm site — servem para comparar concorrentes)
function prosp_salvar_lista($lista, $busca, $cidade, $segSlug = null, $cacadaId = null) {
    global $pdo;
    $novos = 0; $semSite = 0; $total = 0;
    $sql = $pdo->prepare("INSERT INTO prospects (origem_id, fonte, nome, categoria, endereco, cidade, bairro, telefone, whatsapp, site, tem_site, nota, avaliacoes, maps_url,
            pontuacao, motivos, busca, segmento_slug, lat, lng, fotos, avaliacoes_texto, horario, cacada_id, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, ?)
        ON CONFLICT (origem_id) DO UPDATE SET nome=EXCLUDED.nome, telefone=EXCLUDED.telefone, whatsapp=EXCLUDED.whatsapp, site=EXCLUDED.site, tem_site=EXCLUDED.tem_site,
            nota=EXCLUDED.nota, avaliacoes=EXCLUDED.avaliacoes, maps_url=EXCLUDED.maps_url, pontuacao=EXCLUDED.pontuacao, motivos=EXCLUDED.motivos,
            lat=COALESCE(EXCLUDED.lat, prospects.lat), lng=COALESCE(EXCLUDED.lng, prospects.lng), fotos=COALESCE(EXCLUDED.fotos, prospects.fotos),
            avaliacoes_texto=COALESCE(EXCLUDED.avaliacoes_texto, prospects.avaliacoes_texto), horario=COALESCE(EXCLUDED.horario, prospects.horario),
            segmento_slug=COALESCE(prospects.segmento_slug, EXCLUDED.segmento_slug), atualizado_em=NOW()
        RETURNING (xmax = 0) AS novo");
    foreach ($lista as $p) {
        if (trim($p['nome']) === '') continue;
        $p['busca'] = $busca;
        $temSite = prosp_site_proprio($p['site']);
        [$pts, $motivos] = prosp_avaliar($p);
        $json = fn($v) => $v ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $sql->execute([$p['origem_id'], $p['fonte'], mb_substr($p['nome'], 0, 200), mb_substr((string)$p['categoria'], 0, 120), mb_substr((string)$p['endereco'], 0, 300),
            mb_substr($cidade, 0, 120), mb_substr(($p['bairro'] ?? '') ?: prosp_bairro($p['endereco']), 0, 120), mb_substr((string)$p['telefone'], 0, 40), $p['whatsapp'] ?: null,
            mb_substr((string)$p['site'], 0, 300), $temSite ? 'true' : 'false', $p['nota'], (int)$p['avaliacoes'], mb_substr((string)$p['maps_url'], 0, 400),
            $pts, json_encode($motivos, JSON_UNESCAPED_UNICODE), $busca, $segSlug, $p['lat'] ?? null, $p['lng'] ?? null,
            $json(array_values(array_filter($p['fotos'] ?? []))), $json($p['avaliacoes_texto'] ?? []), $json($p['horario'] ?? []), $cacadaId, $temSite ? 'tem_site' : 'novo']);
        $total++;
        if (!$temSite) $semSite++;
        if (pg_bool($sql->fetchColumn()) && !$temSite) $novos++;
    }
    return ['salvos' => $total, 'novos' => $novos, 'sem_site' => $semSite, 'com_site' => $total - $semSite];
}

// Busca simples (um termo, uma cidade)
function prosp_buscar($termo, $cidade, $soSemSite = true, $fonte = 'auto') {
    $f = prosp_fonte_efetiva($fonte);
    $r = $f === 'google' ? prosp_buscar_google($termo, $cidade) : prosp_buscar_osm($termo, $cidade);
    if (!$r['ok']) return $r;
    $s = prosp_salvar_lista($r['lista'], mb_substr("$termo · $cidade", 0, 200), $cidade, prosp_slug_termo($termo));
    return ['ok' => true, 'encontrados' => count($r['lista']), 'salvos' => $s['sem_site'], 'novos' => $s['novos'], 'com_site' => $s['com_site'], 'fonte' => $f === 'google' ? 'Google Maps' : 'OpenStreetMap'];
}

function prosp_slug_termo($termo) {
    global $pdo;
    try { $st = $pdo->prepare("SELECT slug FROM segmentos WHERE LOWER(nome)=LOWER(?) OR slug=? LIMIT 1"); $st->execute([$termo, $termo]); return $st->fetchColumn() ?: null; }
    catch (PDOException $e) { return null; }
}

// ---------- CAÇADA EM MASSA ----------
// Monta a fila: cada segmento × cada região × cada quadrante = 1 tarefa
function prosp_criar_cacada($segmentos, $areas, $densidade, $fonte, $nome = '') {
    global $pdo;
    $segmentos = array_slice($segmentos, 0, 200); $areas = array_slice(array_values(array_filter(array_map('trim', $areas))), 0, 30);
    if (!$segmentos || !$areas) return ['ok' => false, 'erro' => 'Escolha pelo menos um segmento e uma cidade/bairro.'];
    $densidade = in_array((int)$densidade, [1, 4, 9, 16], true) ? (int)$densidade : 1;
    $f = prosp_fonte_efetiva($fonte);
    $caixas = []; $faltou = [];
    foreach ($areas as $a) { $c = prosp_geocodificar($a); if ($c) $caixas[$a] = $c; else $faltou[] = $a; }
    if (!$caixas) return ['ok' => false, 'erro' => 'Não encontrei no mapa: ' . implode(', ', $faltou) . '. Escreva como "Bairro, Cidade - UF".'];
    $total = count($segmentos) * count($caixas) * ($f === 'google' ? $densidade : 1);
    if ($total > 1500) return ['ok' => false, 'erro' => "Essa caçada teria $total buscas. Diminua segmentos, regiões ou quadrantes (máximo 1.500 por caçada)."];
    $pdo->beginTransaction();
    $st = $pdo->prepare("INSERT INTO prospect_cacadas (nome, segmentos, areas, densidade, fonte, total) VALUES (?,?,?,?,?,?) RETURNING id");
    $st->execute([mb_substr($nome ?: (count($segmentos) . ' segmentos em ' . implode(', ', array_keys($caixas))), 0, 150),
        json_encode($segmentos, JSON_UNESCAPED_UNICODE), json_encode(array_keys($caixas), JSON_UNESCAPED_UNICODE), $densidade, $f, $total]);
    $id = (int)$st->fetchColumn();
    $ins = $pdo->prepare("INSERT INTO prospect_tarefas (cacada_id, segmento_slug, termo, area, caixa) VALUES (?,?,?,?,?)");
    foreach ($caixas as $area => $caixa) {
        $cels = $f === 'google' ? prosp_grade($caixa, $densidade) : [$caixa];
        foreach ($segmentos as $sg) foreach ($cels as $cel) $ins->execute([$id, $sg['slug'] ?? null, mb_substr($sg['nome'], 0, 120), mb_substr($area, 0, 150), json_encode($cel)]);
    }
    $pdo->commit();
    return ['ok' => true, 'id' => $id, 'total' => $total, 'faltou' => $faltou, 'fonte' => $f];
}

// Executa a próxima tarefa pendente de uma caçada (ou de qualquer uma, para o cron)
function prosp_processar_proxima($cacadaId = null) {
    global $pdo;
    $where = $cacadaId ? 't.cacada_id=' . (int)$cacadaId : "c.status='rodando'";
    $pdo->beginTransaction();
    $t = $pdo->query("SELECT t.*, c.fonte FROM prospect_tarefas t JOIN prospect_cacadas c ON c.id=t.cacada_id
        WHERE $where AND t.status='pendente' AND c.status='rodando' ORDER BY t.id LIMIT 1 FOR UPDATE SKIP LOCKED")->fetch();
    if (!$t) { $pdo->commit(); if ($cacadaId) prosp_fechar_cacada($cacadaId); return ['ok' => true, 'fim' => true]; }
    $pdo->prepare("UPDATE prospect_tarefas SET status='rodando' WHERE id=?")->execute([$t['id']]);
    $pdo->commit();
    $caixa = json_decode((string)$t['caixa'], true);
    $r = $t['fonte'] === 'google' ? prosp_buscar_google($t['termo'], $t['area'], 3, $caixa) : prosp_buscar_osm($t['termo'], $t['area'], $caixa);
    if (!$r['ok']) {
        $pdo->prepare("UPDATE prospect_tarefas SET status='erro', resultado=?, feita_em=NOW() WHERE id=?")->execute([mb_substr($r['erro'], 0, 250), $t['id']]);
        $pdo->prepare("UPDATE prospect_cacadas SET feitas=feitas+1 WHERE id=?")->execute([$t['cacada_id']]);
        return ['ok' => true, 'erro_tarefa' => $r['erro'], 'tarefa' => $t['termo'] . ' · ' . $t['area']];
    }
    $s = prosp_salvar_lista($r['lista'], mb_substr($t['termo'] . ' · ' . $t['area'], 0, 200), $t['area'], $t['segmento_slug'], (int)$t['cacada_id']);
    $pdo->prepare("UPDATE prospect_tarefas SET status='feita', resultado=?, feita_em=NOW() WHERE id=?")->execute([count($r['lista']) . ' encontradas, ' . $s['sem_site'] . ' sem site', $t['id']]);
    $pdo->prepare("UPDATE prospect_cacadas SET feitas=feitas+1, encontrados=encontrados+?, novos=novos+?, sem_site=sem_site+? WHERE id=?")
        ->execute([count($r['lista']), $s['novos'], $s['sem_site'], $t['cacada_id']]);
    return ['ok' => true, 'tarefa' => $t['termo'] . ' · ' . $t['area'], 'encontrados' => count($r['lista']), 'novos' => $s['novos']];
}

function prosp_fechar_cacada($id) {
    global $pdo;
    $pdo->prepare("UPDATE prospect_cacadas SET status='concluida', concluida_em=NOW() WHERE id=? AND status='rodando'
        AND NOT EXISTS (SELECT 1 FROM prospect_tarefas WHERE cacada_id=? AND status IN ('pendente','rodando'))")->execute([$id, $id]);
}

function prosp_cacada($id) {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM prospect_cacadas WHERE id=?"); $st->execute([$id]);
    return $st->fetch() ?: null;
}

// Rotina (cron): continua as caçadas mesmo com a tela fechada
function prosp_rotina($limite = 6) {
    $feitas = 0;
    try {
        for ($i = 0; $i < $limite; $i++) { $r = prosp_processar_proxima(); if (!empty($r['fim'])) break; $feitas++; }
    } catch (Throwable $e) { return ['prospeccao' => 'erro']; }
    // Verificação de site dos próximos da fila (no modo ponte quem faz é o conector, a cada volta)
    $verificados = 0;
    if (get_config('prosp_verificar', '1') === '1' && prosp_verif_modo() !== 'ponte') {
        global $pdo;
        try {
            $st = $pdo->query("SELECT * FROM prospects WHERE NOT tem_site AND verificado_em IS NULL AND status NOT IN ('fechado','descartado','tem_site')
                AND COALESCE(whatsapp,'') <> '' ORDER BY (passo = 0 AND status='novo') DESC, pontuacao DESC LIMIT 8");
            foreach ($st->fetchAll() as $p) { prosp_verificar_lead($p); $verificados++; }
        } catch (Throwable $e) { error_log('GDS verificação: ' . $e->getMessage()); }
    }
    return ['prospeccao' => ($feitas ? "$feitas busca(s)" : 'nada a fazer') . ($verificados ? ", $verificados verificado(s)" : '')];
}

// ---------- CONCORRÊNCIA ----------
// Compara o lead com os do mesmo segmento na mesma cidade (dados das próprias caçadas)
function prosp_concorrencia($p) {
    global $pdo;
    $chave = $p['segmento_slug'] ?: null;
    $st = $pdo->prepare("SELECT COUNT(*) total, COUNT(*) FILTER (WHERE tem_site) com_site, AVG(nota) FILTER (WHERE nota > 0) media,
            COUNT(*) FILTER (WHERE bairro=? AND bairro<>'') no_bairro, COUNT(*) FILTER (WHERE bairro=? AND bairro<>'' AND tem_site) no_bairro_site
        FROM prospects WHERE cidade=? AND " . ($chave ? 'segmento_slug=?' : 'categoria=?'));
    $st->execute([$p['bairro'] ?? '', $p['bairro'] ?? '', $p['cidade'], $chave ?: $p['categoria']]);
    $c = $st->fetch();
    $c['total'] = (int)$c['total']; $c['com_site'] = (int)$c['com_site']; $c['no_bairro'] = (int)$c['no_bairro']; $c['no_bairro_site'] = (int)$c['no_bairro_site'];
    $c['media'] = $c['media'] !== null ? round((float)$c['media'], 1) : null;
    $nicho = mb_strtolower(trim((string)($p['categoria'] ?? 'negócios')));
    $txt = '';
    if ($c['no_bairro'] >= 3 && $c['no_bairro_site'] > 0) $txt = "Das {$c['no_bairro']} empresas de $nicho que encontrei aqui em {$p['bairro']}, {$c['no_bairro_site']} já têm site próprio.";
    elseif ($c['total'] >= 3 && $c['com_site'] > 0) $txt = "Das {$c['total']} empresas de $nicho que encontrei em {$p['cidade']}, {$c['com_site']} já têm site próprio.";
    if ($txt && $c['media'] && (float)$p['nota'] > $c['media']) $txt .= ' A nota de vocês (' . number_format((float)$p['nota'], 1, ',', '') . ') é maior que a média (' . number_format($c['media'], 1, ',', '') . ') — merecem aparecer mais.';
    $c['texto'] = $txt;
    return $c;
}

// ---------- PRÉVIA DO SITE ----------
function prosp_token($p) {
    global $pdo;
    if (!empty($p['token'])) return $p['token'];
    $t = bin2hex(random_bytes(10));
    $pdo->prepare("UPDATE prospects SET token=? WHERE id=? AND token IS NULL")->execute([$t, $p['id']]);
    $st = $pdo->prepare("SELECT token FROM prospects WHERE id=?"); $st->execute([$p['id']]);
    return $st->fetchColumn() ?: $t;
}

function prosp_url_base() {
    $host = preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['HTTP_HOST'] ?? '');
    $https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return rtrim(get_config('plataforma_url', ''), '/') ?: (($https ? 'https://' : 'http://') . $host);
}

function prosp_link_previa($p) {
    return prosp_url_base() . '/previa/' . prosp_token($p);
}

// Baixa (uma vez) as fotos reais do Google para a prévia
function prosp_fotos_previa($p) {
    static $memoria = [];                       // a prévia pede as fotos duas vezes (galeria e cor da marca)
    $id = (int)($p['id'] ?? 0);
    if (isset($memoria[$id])) return $memoria[$id];
    $nomes = array_values(array_filter((array)json_decode((string)($p['fotos'] ?? ''), true)));
    if (!$nomes || prosp_chave_google() === '') return $memoria[$id] = [];
    $pasta = __DIR__ . '/../uploads/prospects/' . $id;
    $urls = [];
    foreach (array_slice($nomes, 0, 4) as $i => $nome) {
        $arq = "$pasta/$i.jpg";
        if (!is_file($arq)) {
            if (!is_dir($pasta) && !@mkdir($pasta, 0755, true)) break;
            // Foto que o Google recusou (referência vencida, cota): não tenta de novo a cada visita da prévia —
            // isso segurava a página por mais de 1 segundo. Nova tentativa só depois de 6 horas.
            $falhou = "$pasta/$i.falhou";
            if (is_file($falhou) && time() - (int)filemtime($falhou) < 21600) continue;
            $base = getenv('GDS_PLACES_BASE_URL') ?: 'https://places.googleapis.com';
            [$code, $bytes, , $tipo] = prosp_http(rtrim($base, '/') . '/v1/' . $nome . '/media?' . http_build_query(['maxWidthPx' => 1600, 'key' => prosp_chave_google()]), null, [], 8);
            if ($code !== 200 || !$bytes || !str_starts_with($tipo, 'image/')) { @touch($falhou); continue; }
            file_put_contents($arq, $bytes);
        }
        $urls[] = '/uploads/prospects/' . $id . "/$i.jpg";
    }
    if (!is_file(__DIR__ . '/../uploads/.htaccess') && function_exists('img_ia_pasta')) img_ia_pasta('prospects');
    return $memoria[$id] = $urls;
}

// Visitas das prévias contadas em arquivo (página servida do cache) → banco. Chamado pelo cron.
function prosp_previa_descarregar_visitas() {
    global $pdo;
    $pasta = gds_cache_pasta();
    if (!$pasta) return 0;
    $total = 0;
    foreach ((array)glob($pasta . '/pv_*.n') as $f) {
        if (!preg_match('/pv_([a-f0-9]+)\.n$/', $f, $m)) continue;
        $n = gds_cache_contador_pegar('pv_' . $m[1]);
        if ($n < 1) continue;
        $pdo->prepare("UPDATE prospects SET previa_visitas=previa_visitas+?, previa_aberta_em=COALESCE(previa_aberta_em, NOW()), atualizado_em=NOW() WHERE token=?")->execute([$n, $m[1]]);
        $total += $n;
    }
    return $total;
}

// Serviços de exemplo do segmento (do cadastro de segmentos), sem preço inventado
function prosp_servicos_exemplo($slug, $categoria) {
    global $pdo;
    $lista = [];
    try {
        $st = $pdo->prepare("SELECT exemplos_servicos FROM segmentos WHERE slug=?"); $st->execute([$slug ?: '']);
        $lista = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string)$st->fetchColumn()))));
    } catch (PDOException $e) {}
    if (!$lista) $lista = ['Atendimento', 'Orçamento', 'Consultoria'];
    return array_map(fn($n, $i) => ['id' => 900000 + $i, 'nome' => $n, 'descricao' => '', 'preco' => 0, 'duracao_minutos' => 45, 'categoria' => '', 'destaque' => $i < 2, 'esgotado' => false], array_slice($lista, 0, 8), array_keys(array_slice($lista, 0, 8)));
}

// ---------- MENSAGENS (cadência de 3 toques) ----------
// O que mais faz o dono responder "não, obrigado": mensagem longa, link de número
// desconhecido logo de cara, cara de vendedor e afirmar que ele "não tem site".
// Por isso a abertura é curta, se apresenta, NÃO tem link e termina com uma pergunta
// fácil de responder. O link só vai quando ele disser "pode mandar" (ou no 2º toque).
function prosp_mensagens_padrao() {
    return [
        1 => "Oi, tudo bem? É da {nome}? 😊\n{vendedor_txt}Vi vocês no Google aqui {bairro_em}{nota_txt} e montei, por conta própria, uma página de demonstração com o nome e as fotos de vocês.\n\nPosso te mandar o link pra dar uma olhada? Não precisa comprar nada.",
        '1b' => "Oi, tudo bem? É da {nome}?\n{vendedor_txt}Uma pergunta rápida: hoje os clientes de vocês {acao_cliente} só pelo WhatsApp, ou tem algum link onde dá pra ver {o_que} e {verbo} sozinho?",
        2 => "Oi! Passando só pra deixar o link da demonstração que montei pra {nome}{elogio_txt}, caso queira ver quando tiver um tempinho:\n{previa}\n\nSe não fizer sentido pra vocês, tudo bem também 🙂",
        3 => "Oi! Última mensagem, prometo 🙂\nSe um dia quiserem {recurso} sem precisar responder um por um no WhatsApp, é só me chamar aqui. Boa semana pra vocês!",
    ];
}

// Textos antigos que ficaram salvos no banco: viram o padrão novo sozinhos
function prosp_mensagens_antigas() {
    return [
        "Oi! Tudo bem? Encontrei a {nome} no Google{avaliacoes_txt} 👏\n\n{concorrentes_txt}\n\nMontei uma prévia de como ficaria o site de vocês, com {recurso} e confirmação automática no WhatsApp:\n{previa}\n\nO que acharam?",
        "Oi! Conseguiram ver a prévia do site da {nome}? {previa}\n\nSe quiserem, eu ajusto cores, fotos e textos do jeito de vocês, sem compromisso.",
        "Oi! Última mensagem sobre o site da {nome}, prometo 🙂\n\nA prévia continua no ar por mais alguns dias: {previa}\n\nSe não for o momento, sem problema — é só me avisar que eu não mando mais.",
    ];
}

// Respostas prontas para o que o dono costuma responder
function prosp_respostas_padrao() {
    return [
        'link' => ['rotulo' => '👍 "Pode mandar"', 'texto' => "Aqui está 👇\n{previa}\n\nFiz com as fotos e as avaliações de vocês do Google, abre no celular mesmo. O que você mudaria nela?"],
        'ja_tenho' => ['rotulo' => '🌐 "Já tenho site"', 'texto' => "Ah, que bom! Não apareceu no Google pra mim — vale colocar o link no perfil de vocês lá, ajuda muito a ser encontrado.\nSe um dia quiser comparar, essa versão já vem com {recurso}: {previa} 🙂"],
        'preco' => ['rotulo' => '💰 "Quanto custa?"', 'texto' => "{preco_txt}Antes de falar de valor, vê se faz sentido pra vocês: {previa}\nQuer que eu te mostre rapidinho como funciona {recurso}?"],
        'quem' => ['rotulo' => '🤔 "Quem é você?"', 'texto' => "Desculpa, nem me apresentei direito! Sou {vendedor_nome}, da {marca} — a gente faz sites pra negócios aqui da região. Achei vocês no Google e montei a demonstração por conta própria, sem compromisso: {previa}"],
        'depois' => ['rotulo' => '⏳ "Agora não"', 'texto' => "Sem problema! Te chamo daqui a uns dias então. A demonstração fica no ar: {previa}"],
        'nao' => ['rotulo' => '🙅 "Não tenho interesse"', 'texto' => "Tranquilo, obrigado por responder! Não vou mais te mandar mensagem. Sucesso aí pra vocês 🙌"],
    ];
}

function prosp_normalizar_texto($t) { return trim(preg_replace("/\r\n?/", "\n", (string)$t)); }

// $passo: 1, '1b', 2 ou 3
function prosp_modelo($passo) {
    $chave = $passo === '1b' ? '1b' : max(1, min(3, (int)$passo));
    $salvo = prosp_normalizar_texto(get_config('prosp_mensagem_' . $chave, '') ?: ($chave === 1 ? get_config('prosp_mensagem', '') : ''));
    if ($salvo !== '' && in_array($salvo, array_map('prosp_normalizar_texto', prosp_mensagens_antigas()), true)) $salvo = '';
    return $salvo !== '' ? $salvo : prosp_mensagens_padrao()[$chave];
}

function prosp_resposta_modelo($chave) {
    $padrao = prosp_respostas_padrao();
    if (!isset($padrao[$chave])) return '';
    return prosp_normalizar_texto(get_config('prosp_resposta_' . $chave, '')) ?: $padrao[$chave]['texto'];
}

// Versão da 1ª mensagem (teste A/B): alterna entre as duas, a não ser que uma esteja vazia/desligada
function prosp_variante($p) {
    if (!empty($p['variante'])) return $p['variante'];
    if (get_config('prosp_ab', '1') !== '1') return 'a';
    return ((int)($p['id'] ?? 0)) % 2 === 0 ? 'a' : 'b';
}

// Modo do negócio → como o cliente dele compra
function prosp_modo_negocio($texto) {
    $t = mb_strtolower((string)$texto);
    if (preg_match('/hamburg|pizz|a[cç]a[ií]|lanch|marmit|restaur|delivery|padaria|confeit|doce|bolo|sorvet|pastel|espetinho|japon|sushi/u', $t)) return 'pedido';
    if (preg_match('/fot[oó]graf|dj|buffet|decora|eventos|mec[aâ]nic|oficina|advog|reforma|pintor|eletric|encanad|marcen|serralher|vidra|gráfica|grafica|contab/u', $t)) return 'orcamento';
    return 'agenda';
}

function prosp_recurso($texto) {
    return ['pedido' => 'cardápio online e pedido pelo site', 'orcamento' => 'pedido de orçamento online', 'agenda' => 'agendamento online'][prosp_modo_negocio($texto)];
}

// O que os clientes mais elogiam nas avaliações reais do Google ("o atendimento", "o ambiente"...)
function prosp_elogio($p) {
    $revs = (array)json_decode((string)($p['avaliacoes_texto'] ?? ''), true);
    $txt = mb_strtolower(implode(' ', array_map(fn($r) => (string)($r['texto'] ?? ''), $revs)));
    if ($txt === '') return '';
    $temas = ['o atendimento' => '/atendiment|atencios|educad|simpati|acolhed/u', 'o ambiente' => '/ambiente|aconcheg|lugar (lindo|incr|agrad)|espaço/u',
        'a pontualidade' => '/pontual|no horário|sem atraso/u', 'o preço justo' => '/preço (justo|bom|ótimo)|barato|custo.benef/u',
        'a limpeza' => '/limp[oa]|higien/u', 'o sabor' => '/sabor|delicios|gostos|saboros/u', 'a entrega rápida' => '/entrega rápida|chegou rápido|entregou rápido/u',
        'o capricho no serviço' => '/capricho|caprich|detalhist|perfeit|impecáve/u', 'os profissionais' => '/profission|competent|experient/u'];
    $melhor = ''; $max = 0;
    foreach ($temas as $tema => $re) { $n = preg_match_all($re, $txt); if ($n > $max) { $max = $n; $melhor = $tema; } }
    return $melhor;
}

// "Centro" → "no Centro", "Vila Mariana" → "na Vila Mariana", "Moema" → "em Moema"
function prosp_no_lugar($lugar) {
    $lugar = trim((string)$lugar);
    if ($lugar === '') return 'na região';
    $l = mb_strtolower($lugar);
    if (preg_match('/^(centro|jardim|parque|bairro|recreio|setor|distrito|bosque|morro|alto|portal|residencial|conjunto|núcleo|nucleo)\b/u', $l)) return 'no ' . $lugar;
    if (preg_match('/^(vila|cidade|chácara|chacara|colônia|colonia|praia|serra|ilha|barra|lagoa|zona|região|regiao)\b/u', $l)) return 'na ' . $lugar;
    return 'em ' . $lugar;
}

function prosp_variaveis($p) {
    $av = (int)($p['avaliacoes'] ?? 0); $nota = (float)($p['nota'] ?? 0);
    $txtNicho = ($p['categoria'] ?? '') . ' ' . ($p['nome'] ?? '') . ' ' . ($p['busca'] ?? '');
    $modo = prosp_modo_negocio($txtNicho);
    $vendNome = trim(get_config('prosp_vendedor', ''));
    $marca = get_config('mkt_marca', '') ?: 'GDS Sites';
    $preco = trim(get_config('mkt_preco', ''));
    return [
        '{nome}' => $p['nome'] ?? '', '{nicho}' => mb_strtolower(trim((string)($p['categoria'] ?? '')) ?: 'negócios como o seu'),
        '{cidade}' => $p['cidade'] ?? '', '{bairro}' => ($p['bairro'] ?? '') ?: ($p['cidade'] ?? 'na região'),
        '{bairro_em}' => prosp_no_lugar(($p['bairro'] ?? '') ?: ($p['cidade'] ?? '')),
        '{elogio}' => ($el = prosp_elogio($p)),
        '{elogio_txt}' => $el ? " (coloquei as avaliações em que os clientes elogiam $el de vocês)" : '',
        '{nota}' => $nota ? number_format($nota, 1, ',', '') : '', '{avaliacoes}' => (string)$av,
        '{avaliacoes_txt}' => $av >= 5 ? ' — ' . ($nota ? number_format($nota, 1, ',', '') . ' estrelas com ' : '') . $av . ' avaliações, parabéns' : '',
        '{nota_txt}' => $av >= 5 && $nota >= 4 ? ' (' . number_format($nota, 1, ',', '') . ' estrelas com ' . $av . ' avaliações, parabéns)' : '',
        '{recurso}' => prosp_recurso($txtNicho),
        '{acao_cliente}' => ['pedido' => 'fazem pedido', 'orcamento' => 'pedem orçamento', 'agenda' => 'marcam horário'][$modo],
        '{o_que}' => ['pedido' => 'o cardápio', 'orcamento' => 'os serviços', 'agenda' => 'os horários livres'][$modo],
        '{verbo}' => ['pedido' => 'pedir', 'orcamento' => 'pedir orçamento', 'agenda' => 'marcar'][$modo],
        '{vendedor_nome}' => $vendNome ?: 'eu', '{marca}' => $marca,
        '{vendedor_txt}' => $vendNome ? "Aqui é $vendNome, da $marca.\n" : '',
        '{preco_txt}' => $preco ? "Fica $preco. " : '',
        '{link}' => prosp_url_base(),
    ];
}

function prosp_aplicar($modelo, $p) {
    $vars = prosp_variaveis($p);
    $vars['{concorrentes_txt}'] = !empty($p['id']) && str_contains($modelo, '{concorrentes_txt}') ? prosp_concorrencia($p)['texto'] : '';
    $vars['{previa}'] = !empty($p['id']) && str_contains($modelo, '{previa}') ? prosp_link_previa($p) : '';
    $txt = strtr($modelo, $vars);
    $txt = preg_replace("/[ \t]+\n/", "\n", $txt);
    return trim(preg_replace("/\n{3,}/", "\n\n", $txt));
}

function prosp_mensagem($p, $passo = null, $modelo = null) {
    $passo = $passo ?: min(3, (int)($p['passo'] ?? 0) + 1);
    if (!$modelo) $modelo = prosp_modelo((int)$passo === 1 && prosp_variante($p) === 'b' ? '1b' : $passo);
    return prosp_aplicar($modelo, $p);
}

// O dono respondeu: guarda (conta para o teste A/B) e aplica o efeito da resposta pronta
function prosp_registrar_resposta($id, $chave) {
    global $pdo;
    $pdo->prepare("UPDATE prospects SET respondeu_em=COALESCE(respondeu_em, NOW()), atualizado_em=NOW(),
            status = CASE WHEN status IN ('novo','contatado') THEN 'respondeu' ELSE status END WHERE id=?")->execute([$id]);
    if ($chave === 'nao') $pdo->prepare("UPDATE prospects SET status='descartado', proximo_contato=NULL WHERE id=?")->execute([$id]);
    elseif ($chave === 'depois') $pdo->prepare("UPDATE prospects SET proximo_contato=CURRENT_DATE + 7, passo=LEAST(passo, 2) WHERE id=?")->execute([$id]);
    elseif ($chave === 'ja_tenho') $pdo->prepare("UPDATE prospects SET tem_site=true, site_fonte=COALESCE(site_fonte, 'cliente'), proximo_contato=NULL WHERE id=?")->execute([$id]);
    elseif (in_array($chave, ['link', 'preco', 'quem'], true)) $pdo->prepare("UPDATE prospects SET proximo_contato=CURRENT_DATE + 2, passo=GREATEST(passo, 2) WHERE id=?")->execute([$id]);
}

// O que está funcionando: taxa de resposta por segmento, por horário e por dia da semana.
// Precisa de histórico (quem recebeu mensagem e se respondeu) — fica mais certeiro a cada semana.
function prosp_aprendizado() {
    global $pdo;
    $out = ['segmentos' => [], 'horas' => [], 'dias' => [], 'dica' => '', 'base' => 0];
    try {
        $out['base'] = (int)$pdo->query("SELECT COUNT(*) FROM prospects WHERE contatado_em IS NOT NULL")->fetchColumn();
        $out['segmentos'] = $pdo->query("SELECT COALESCE(s.nome, p.categoria, 'Outros') seg, COUNT(*) enviados, COUNT(*) FILTER (WHERE p.respondeu_em IS NOT NULL) responderam,
                COUNT(*) FILTER (WHERE p.status IN ('negociando','fechado')) avancaram
            FROM prospects p LEFT JOIN segmentos s ON s.slug = p.segmento_slug WHERE p.contatado_em IS NOT NULL GROUP BY 1 HAVING COUNT(*) >= 3
            ORDER BY (COUNT(*) FILTER (WHERE p.respondeu_em IS NOT NULL))::float / COUNT(*) DESC, COUNT(*) DESC LIMIT 12")->fetchAll();
        $out['horas'] = $pdo->query("SELECT (EXTRACT(HOUR FROM contatado_em)::int / 2) * 2 faixa, COUNT(*) enviados, COUNT(*) FILTER (WHERE respondeu_em IS NOT NULL) responderam
            FROM prospects WHERE contatado_em IS NOT NULL GROUP BY 1 ORDER BY 1")->fetchAll();
        $out['dias'] = $pdo->query("SELECT EXTRACT(ISODOW FROM contatado_em)::int dia, COUNT(*) enviados, COUNT(*) FILTER (WHERE respondeu_em IS NOT NULL) responderam
            FROM prospects WHERE contatado_em IS NOT NULL GROUP BY 1 ORDER BY 1")->fetchAll();
    } catch (PDOException $e) { return $out; }
    $taxa = fn($l) => $l['enviados'] ? $l['responderam'] / $l['enviados'] : 0;
    $boasHoras = array_values(array_filter($out['horas'], fn($h) => $h['enviados'] >= 5));
    usort($boasHoras, fn($a, $b) => $taxa($b) <=> $taxa($a));
    $nomesDia = [1 => 'segunda', 2 => 'terça', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sábado', 7 => 'domingo'];
    $bonsDias = array_values(array_filter($out['dias'], fn($d) => $d['enviados'] >= 5));
    usort($bonsDias, fn($a, $b) => $taxa($b) <=> $taxa($a));
    $partes = [];
    if ($boasHoras && $taxa($boasHoras[0]) > 0) $partes[] = 'entre ' . $boasHoras[0]['faixa'] . 'h e ' . ($boasHoras[0]['faixa'] + 2) . 'h (' . round(100 * $taxa($boasHoras[0])) . '% respondem)';
    if ($bonsDias && $taxa($bonsDias[0]) > 0) $partes[] = 'na ' . $nomesDia[$bonsDias[0]['dia']];
    $segBom = array_values(array_filter($out['segmentos'], fn($s) => $s['enviados'] >= 5 && $s['responderam'] > 0));
    if ($partes) $out['dica'] = 'Seus leads respondem mais ' . implode(', ', $partes) . '.' . ($segBom ? ' O segmento que mais responde é ' . $segBom[0]['seg'] . ' (' . round(100 * $taxa($segBom[0])) . '%).' : '');
    elseif ($out['base'] < 20) $out['dica'] = 'Com umas 20 mensagens enviadas (e as respostas marcadas), aparece aqui o melhor horário, dia e segmento para chamar.';
    return $out;
}

// Resultado do teste A/B da 1ª mensagem
function prosp_resultado_ab() {
    global $pdo;
    try {
        $r = $pdo->query("SELECT variante, COUNT(*) enviados, COUNT(*) FILTER (WHERE respondeu_em IS NOT NULL) responderam,
                COUNT(*) FILTER (WHERE status IN ('negociando','fechado')) avancaram
            FROM prospects WHERE passo > 0 AND variante IN ('a','b') GROUP BY variante")->fetchAll();
    } catch (PDOException $e) { return []; }
    $out = [];
    foreach ($r as $l) $out[$l['variante']] = ['enviados' => (int)$l['enviados'], 'responderam' => (int)$l['responderam'], 'avancaram' => (int)$l['avancaram'],
        'taxa' => $l['enviados'] ? round(100 * $l['responderam'] / $l['enviados']) : 0];
    return $out;
}

// Depois de mandar a mensagem do passo N: agenda o próximo toque (dia 2 e dia 5)
function prosp_registrar_envio($id) {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM prospects WHERE id=?"); $st->execute([$id]);
    if (($p = $st->fetch()) && (int)$p['passo'] === 0 && empty($p['variante'])) $pdo->prepare("UPDATE prospects SET variante=? WHERE id=?")->execute([prosp_variante($p), $id]);
    $pdo->prepare("UPDATE prospects SET passo = LEAST(passo + 1, 3),
            status = CASE WHEN status IN ('novo') THEN 'contatado' ELSE status END,
            contatado_em = COALESCE(contatado_em, NOW()), ultimo_envio_em = NOW(),
            proximo_contato = CASE WHEN passo + 1 = 1 THEN CURRENT_DATE + 2 WHEN passo + 1 = 2 THEN CURRENT_DATE + 3 ELSE NULL END,
            atualizado_em = NOW() WHERE id=?")->execute([$id]);
}

// Avisa o dono da plataforma no WhatsApp (abriu a prévia / quer o site)
function prosp_alertar($texto) {
    $numero = get_config('mkt_whatsapp', '') ?: get_config('prosp_whatsapp_alerta', '');
    if ($numero === '' || !function_exists('wa_remetente')) return false;
    $rem = wa_remetente(['wa_modo' => 'plataforma']);
    if (!$rem) return false;
    try { return (bool)(wa_enviar_agora($rem, $numero, $texto)['ok'] ?? false); } catch (Throwable $e) { return false; }
}

require_once __DIR__ . '/prospeccao_verificacao.php';
