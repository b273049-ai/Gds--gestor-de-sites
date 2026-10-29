<?php
// ============================================================
// PROSPECÇÃO: acha empresas no Google Maps (Places API New) ou no
// OpenStreetMap (grátis, sem chave), descobre quem NÃO tem site próprio,
// dá uma nota de "quão bom cliente é" e monta a mensagem de WhatsApp.
// ============================================================

function prosp_chave_google() {
    return get_config('google_places_key', '') ?: get_config('google_maps_key', '');
}

// Instagram, Facebook, Linktree, iFood etc. não são "site próprio"
function prosp_site_proprio($url) {
    $url = strtolower(trim((string)$url));
    if ($url === '') return false;
    $host = parse_url(str_contains($url, '://') ? $url : 'http://' . $url, PHP_URL_HOST) ?: '';
    $redes = ['instagram.com', 'facebook.com', 'fb.com', 'linktr.ee', 'wa.me', 'whatsapp.com', 'api.whatsapp.com', 'ifood.com.br', 'business.site',
              'goo.gl', 'g.page', 'bit.ly', 'beacons.ai', 'linkbio', 'tiktok.com', 'youtube.com', 'twitter.com', 'x.com', 'linkedin.com',
              'booksy.com', 'trinks.com', 'anota.ai', 'goomer.app', 'aiqfome.com', 'cardapioweb.com', 'menudino.com', 'olaclick.com', 'sites.google.com'];
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

// Nichos que mais compram site (agenda, pedido, orçamento)
function prosp_nicho_quente($texto) {
    return (bool)preg_match('/barbe|sal[aã]o|cabel|est[eé]tic|manicure|unha|sobrancel|c[ií]lio|tatu|pet|banho e tosa|veterin|dent|odonto|cl[ií]nica|fisio|psic|nutri|pilates|academia|personal|hamburg|pizz|a[cç]a[ií]|lanch|marmit|restaur|delivery|confeit|doce|bolo|padaria|mec[aâ]nic|oficina|lava|est[eé]tica automotiva|fot[oó]graf|dj|buffet|decora|eventos|advog|contab/iu', (string)$texto);
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

function prosp_temperatura($pts) {
    return $pts >= 70 ? ['quente', '🔥 Quente'] : ($pts >= 45 ? ['morno', '🌤 Morno'] : ['frio', '❄ Frio']);
}

function prosp_http($url, $corpo = null, $cab = [], $timeout = 25) {
    $ch = curl_init($url);
    $op = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_HTTPHEADER => $cab,
           CURLOPT_USERAGENT => 'GDS-Gestor-de-Sites/1.0 (prospeccao)'];
    if ($corpo !== null) { $op[CURLOPT_POST] = true; $op[CURLOPT_POSTFIELDS] = $corpo; }
    curl_setopt_array($ch, $op);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch);
    curl_close($ch);
    return [$code, $r === false ? null : $r, $err];
}

// ---------- Google Places API (New): Text Search ----------
function prosp_buscar_google($termo, $cidade, $paginas = 3) {
    $chave = prosp_chave_google();
    $base = getenv('GDS_PLACES_BASE_URL') ?: 'https://places.googleapis.com';
    $campos = 'places.id,places.displayName,places.formattedAddress,places.nationalPhoneNumber,places.internationalPhoneNumber,places.websiteUri,'
            . 'places.rating,places.userRatingCount,places.businessStatus,places.googleMapsUri,places.primaryTypeDisplayName,nextPageToken';
    $lista = []; $token = '';
    for ($i = 0; $i < $paginas; $i++) {
        $corpo = ['textQuery' => trim("$termo em $cidade"), 'languageCode' => 'pt-BR', 'regionCode' => 'BR', 'pageSize' => 20];
        if ($token !== '') $corpo['pageToken'] = $token;
        [$code, $resp, $err] = prosp_http(rtrim($base, '/') . '/v1/places:searchText', json_encode($corpo),
            ['Content-Type: application/json', 'X-Goog-Api-Key: ' . $chave, 'X-Goog-FieldMask: ' . $campos]);
        $d = json_decode((string)$resp, true);
        if ($code !== 200) {
            $msg = $d['error']['message'] ?? ($err ?: "código $code");
            if (!$lista) return ['ok' => false, 'erro' => 'Google Places: ' . $msg . (str_contains((string)$msg, 'not been used') || str_contains((string)$msg, 'disabled') ? ' — ative a "Places API (New)" no Google Cloud para essa chave.' : '')];
            break;
        }
        foreach ((array)($d['places'] ?? []) as $pl) {
            if (($pl['businessStatus'] ?? 'OPERATIONAL') !== 'OPERATIONAL') continue;
            [$tel, $wa] = prosp_telefone($pl['nationalPhoneNumber'] ?? ($pl['internationalPhoneNumber'] ?? ''));
            $lista[] = ['origem_id' => 'g:' . $pl['id'], 'fonte' => 'google', 'nome' => $pl['displayName']['text'] ?? '', 'categoria' => $pl['primaryTypeDisplayName']['text'] ?? $termo,
                'endereco' => $pl['formattedAddress'] ?? '', 'telefone' => $pl['nationalPhoneNumber'] ?? '' ? $tel : '', 'whatsapp' => $wa, 'site' => $pl['websiteUri'] ?? '',
                'nota' => $pl['rating'] ?? null, 'avaliacoes' => (int)($pl['userRatingCount'] ?? 0), 'maps_url' => $pl['googleMapsUri'] ?? ''];
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
        '[oó]tica' => '["shop"="optician"]', 'roupa|moda|boutique' => '["shop"="clothes"]', 'fot[oó]gra' => '["shop"="photo"]',
    ];
    foreach ($mapa as $re => $tag) if (preg_match('/' . $re . '/u', $t)) return $tag;
    return null;
}

function prosp_buscar_osm($termo, $cidade) {
    $nomi = getenv('GDS_NOMINATIM_URL') ?: 'https://nominatim.openstreetmap.org';
    [$code, $resp] = prosp_http(rtrim($nomi, '/') . '/search?' . http_build_query(['q' => $cidade . ', Brasil', 'format' => 'json', 'limit' => 1]), null, ['Accept-Language: pt-BR'], 15);
    $g = json_decode((string)$resp, true)[0] ?? null;
    if ($code !== 200 || !$g || empty($g['boundingbox'])) return ['ok' => false, 'erro' => 'Não encontrei a cidade "' . $cidade . '" no mapa.'];
    [$s, $n, $w, $e] = array_map('floatval', $g['boundingbox']);
    $bbox = "$s,$w,$n,$e";
    $tag = prosp_tags_osm($termo);
    $nomeRe = str_replace('"', '', preg_quote(mb_strtolower($termo), '/'));
    $filtro = $tag ? "nwr$tag($bbox);" : "nwr[\"name\"~\"$nomeRe\",i][~\"^(shop|amenity|office|craft|leisure)$\"~\".\"]($bbox);";
    $q = "[out:json][timeout:25];($filtro);out center 120;";
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
            'nota' => null, 'avaliacoes' => 0,
            'maps_url' => $lat ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($t['name'] . ' ' . $cidade) : ''];
    }
    return ['ok' => true, 'lista' => $lista];
}

// Busca, pontua e guarda (sem apagar o que você já anotou em quem já estava na lista)
function prosp_buscar($termo, $cidade, $soSemSite = true, $fonte = 'auto') {
    global $pdo;
    $usarGoogle = $fonte === 'google' || ($fonte === 'auto' && prosp_chave_google() !== '');
    $r = $usarGoogle ? prosp_buscar_google($termo, $cidade) : prosp_buscar_osm($termo, $cidade);
    if (!$r['ok']) return $r;
    $novos = 0; $total = 0; $ignorados = 0;
    $busca = mb_substr("$termo · $cidade", 0, 200);
    $sql = $pdo->prepare("INSERT INTO prospects (origem_id, fonte, nome, categoria, endereco, cidade, telefone, whatsapp, site, nota, avaliacoes, maps_url, pontuacao, motivos, busca)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON CONFLICT (origem_id) DO UPDATE SET nome=EXCLUDED.nome, telefone=EXCLUDED.telefone, whatsapp=EXCLUDED.whatsapp, site=EXCLUDED.site, nota=EXCLUDED.nota,
            avaliacoes=EXCLUDED.avaliacoes, maps_url=EXCLUDED.maps_url, pontuacao=EXCLUDED.pontuacao, motivos=EXCLUDED.motivos, atualizado_em=NOW()
        RETURNING (xmax = 0) AS novo");
    foreach ($r['lista'] as $p) {
        if ($p['nome'] === '') continue;
        $p['busca'] = $busca;
        if ($soSemSite && prosp_site_proprio($p['site'])) { $ignorados++; continue; }
        [$pts, $motivos] = prosp_avaliar($p);
        $sql->execute([$p['origem_id'], $p['fonte'], mb_substr($p['nome'], 0, 200), mb_substr((string)$p['categoria'], 0, 120), mb_substr((string)$p['endereco'], 0, 300), mb_substr($cidade, 0, 120),
            mb_substr((string)$p['telefone'], 0, 40), $p['whatsapp'] ?: null, mb_substr((string)$p['site'], 0, 300), $p['nota'], (int)$p['avaliacoes'], mb_substr((string)$p['maps_url'], 0, 400),
            $pts, json_encode($motivos, JSON_UNESCAPED_UNICODE), $busca]);
        $total++;
        if (pg_bool($sql->fetchColumn())) $novos++;
    }
    return ['ok' => true, 'encontrados' => count($r['lista']), 'salvos' => $total, 'novos' => $novos, 'com_site' => $ignorados, 'fonte' => $usarGoogle ? 'Google Maps' : 'OpenStreetMap'];
}

function prosp_mensagem_padrao() {
    return "Oi! Tudo bem? Encontrei a {nome} no Google{avaliacoes_txt} 👏\n\n"
         . "Vi que vocês ainda não têm um site próprio. Eu crio sites para {nicho} com {recurso} e confirmação automática no WhatsApp — o cliente resolve sozinho, até de madrugada.\n\n"
         . "Montei uma ideia de como ficaria o site de vocês. Posso te mostrar em 2 minutinhos?";
}

function prosp_recurso($texto) {
    $t = mb_strtolower((string)$texto);
    if (preg_match('/hamburg|pizz|a[cç]a[ií]|lanch|marmit|restaur|delivery|padaria|confeit|doce|bolo/u', $t)) return 'cardápio online e pedido pelo site';
    if (preg_match('/fot[oó]graf|dj|buffet|decora|eventos|mec[aâ]nic|oficina|advog|reforma|pintor|eletric|encanad|marcen/u', $t)) return 'pedido de orçamento online';
    return 'agendamento online';
}

function prosp_mensagem($p, $modelo = null) {
    $modelo = $modelo ?: (get_config('prosp_mensagem', '') ?: prosp_mensagem_padrao());
    $av = (int)($p['avaliacoes'] ?? 0); $nota = (float)($p['nota'] ?? 0);
    $avTxt = $av >= 5 ? ' — ' . ($nota ? number_format($nota, 1, ',', '') . ' estrelas com ' : '') . $av . ' avaliações, parabéns' : '';
    $nicho = trim((string)($p['categoria'] ?? '')) ?: 'negócios como o seu';
    return strtr($modelo, ['{nome}' => $p['nome'], '{nicho}' => mb_strtolower($nicho), '{cidade}' => $p['cidade'] ?? '', '{nota}' => $nota ? number_format($nota, 1, ',', '') : '',
        '{avaliacoes}' => (string)$av, '{avaliacoes_txt}' => $avTxt, '{recurso}' => prosp_recurso(($p['categoria'] ?? '') . ' ' . ($p['nome'] ?? '') . ' ' . ($p['busca'] ?? '')),
        '{link}' => rtrim(get_config('plataforma_url', ''), '/')]);
}
