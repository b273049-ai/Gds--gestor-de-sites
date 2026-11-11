<?php
// ============================================================
// VERIFICAÇÃO DE SITE ANTES DE CHAMAR O LEAD
// O Google Maps nem sempre mostra o site da empresa. Antes de mandar mensagem,
// conferimos em fontes que a própria empresa publica, para não oferecer site a quem já tem:
//   1. Perfil do WhatsApp (pelo Conector GDS): o número TEM WhatsApp? É comercial?
//      Que site/descrição a empresa colocou no perfil? (Linktree e parecidos são abertos)
//   2. Domínios com o nome da empresa (nomedaempresa.com.br / .com), confirmando que a
//      página é mesmo dela (nome ou telefone aparecem).
// Busca no Google e Instagram ficam como botões para o vendedor conferir no navegador.
// ============================================================

const PROSP_DIRETORIOS = ['guiamais.com.br', 'apontador.com.br', 'telelistas.net', 'encontra', 'cnpj', 'econodata', 'solutudo', 'yelp', 'tripadvisor',
    'foursquare', 'google.', 'waze.com', 'ifood.com.br', 'mercadolivre', 'olx.com.br', 'wikipedia.org', 'reclameaqui', 'guiadacidade', 'listamais',
    'kekanto', 'restaurantguru', 'cylex', 'hotfrog', 'brasilbrasileiro', 'empresasdobrasil', 'casadosdados', 'consultas', 'jusbrasil', 'glassdoor',
    'indeed', 'catho', 'infojobs', 'facebook.com', 'instagram.com', 'tiktok.com', 'youtube.com', 'linkedin.com', 'pinterest', 'twitter.com', 'x.com',
    'booksy.com', 'trinks.com', 'doctoralia', 'getninjas', 'habitissimo', 'mapcarta', 'wazeapp', 'maps.apple', 'bing.com', 'duckduckgo.com', 'linktr.ee'];

function prosp_eh_diretorio($url) {
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    foreach (PROSP_DIRETORIOS as $d) if (str_contains($host, $d)) return true;
    return false;
}

function prosp_sem_acento($t) {
    $t = mb_strtolower((string)$t);
    return strtr($t, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'í' => 'i', 'î' => 'i', 'ó' => 'o', 'ô' => 'o',
        'õ' => 'o', 'ö' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n', '&' => 'e']);
}

// Palavras do nome que identificam o negócio ("Barbearia do Zé Navalha" → zé, navalha)
function prosp_palavras_nome($nome) {
    $genericas = ['barbearia', 'barber', 'shop', 'salao', 'studio', 'estudio', 'espaco', 'clinica', 'centro', 'restaurante', 'pizzaria', 'hamburgueria',
        'lanchonete', 'academia', 'pet', 'shop', 'oficina', 'auto', 'center', 'loja', 'casa', 'beleza', 'estetica', 'odontologia', 'do', 'da', 'de', 'dos', 'das',
        'e', 'the', 'and', 'ltda', 'me', 'eireli', 'cia', 'sao', 'paulo'];
    $p = preg_split('/[^a-z0-9]+/', prosp_sem_acento($nome), -1, PREG_SPLIT_NO_EMPTY);
    $fortes = array_values(array_filter($p, fn($w) => strlen($w) >= 3 && !in_array($w, $genericas, true)));
    return $fortes ?: array_values(array_filter($p, fn($w) => strlen($w) >= 3));
}

// A página é mesmo desta empresa? (nome ou telefone aparecem nela)
function prosp_pagina_da_empresa($html, $p) {
    $txt = prosp_sem_acento(html_entity_decode(strip_tags((string)$html), ENT_QUOTES, 'UTF-8'));
    if (preg_match('/dominio (a venda|esta a venda)|domain (is )?for sale|parked|registro\.br|este dominio|coming soon|em construcao/', $txt)) return false;
    $digitos = preg_replace('/\D/', '', (string)($p['whatsapp'] ?: $p['telefone']));
    if (strlen($digitos) >= 8 && str_contains(preg_replace('/\D/', '', $txt), substr($digitos, -8))) return true;
    $palavras = prosp_palavras_nome($p['nome'] ?? '');
    if (!$palavras) return false;
    $achadas = count(array_filter($palavras, fn($w) => str_contains($txt, $w)));
    return $achadas / count($palavras) >= 0.6;
}

function prosp_buscar_pagina($url, $timeout = 7) {
    if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
    [$code, $html] = prosp_http($url, null, ['Accept: text/html', 'Accept-Language: pt-BR,pt;q=0.9'], $timeout);
    return $code >= 200 && $code < 400 && $html ? mb_substr((string)$html, 0, 400000) : null;
}

// Linktree, Beacons, etc.: abre e pega os links que são site próprio
function prosp_expandir_agregador($url) {
    $html = prosp_buscar_pagina($url, 8);
    if (!$html) return [];
    preg_match_all('#href="(https?://[^"]+)"#i', $html, $m);
    $out = [];
    foreach (array_unique($m[1]) as $u) {
        $u = html_entity_decode($u);
        if (prosp_site_proprio($u) && !prosp_eh_diretorio($u) && !preg_match('#linktr|beacons|linkme|taplink|cdn|static|privacy|cookie|terms#i', $u)) $out[] = $u;
    }
    return array_slice(array_values(array_unique($out)), 0, 5);
}

// Domínios escritos em texto ("www.minhaloja.com.br", "minhaloja.com")
function prosp_urls_no_texto($texto) {
    preg_match_all('#(?:https?://)?(?:www\.)?([a-z0-9][a-z0-9-]{1,60}\.(?:com\.br|com|net\.br|net|app|site|online|store|shop|br))(?:/[^\s]*)?#i', (string)$texto, $m);
    return array_values(array_unique(array_map(fn($d) => 'https://' . strtolower($d), $m[1])));
}

// Palpites de domínio a partir do nome
function prosp_palpites_dominio($nome) {
    $base = preg_replace('/[^a-z0-9 ]/', '', prosp_sem_acento($nome));
    $tudo = str_replace(' ', '', $base);
    $semGenerico = implode('', prosp_palavras_nome($nome));
    $hifen = preg_replace('/\s+/', '-', trim($base));
    $out = [];
    foreach (array_unique(array_filter([$tudo, $semGenerico, $hifen], fn($x) => strlen($x) >= 4 && strlen($x) <= 40)) as $s) {
        $out[] = "$s.com.br"; $out[] = "$s.com";
    }
    return array_slice($out, 0, 6);
}

function prosp_testar_dominios($p) {
    foreach (prosp_palpites_dominio($p['nome'] ?? '') as $dom) {
        if (!getenv('GDS_DOMINIO_SEM_DNS') && !@checkdnsrr($dom, 'A') && !@checkdnsrr('www.' . $dom, 'A')) continue;
        $html = prosp_buscar_pagina('https://' . $dom, 6) ?? prosp_buscar_pagina('http://' . $dom, 6);
        if ($html && prosp_pagina_da_empresa($html, $p)) return 'https://' . $dom;
    }
    return null;
}

// Usuário do Instagram a partir de URLs/textos ("instagram.com/barbeariaze", "@barbeariaze")
function prosp_achar_instagram($fontes) {
    foreach ($fontes as $f) {
        if (preg_match('#instagram\.com/([a-z0-9._]{2,30})#i', (string)$f, $m) && !in_array(strtolower($m[1]), ['p', 'reel', 'reels', 'explore', 'stories', 'accounts', 'tv'], true)) return strtolower($m[1]);
    }
    return null;
}

// Junta o que o conector achou com as checagens daqui e decide
function prosp_concluir_verificacao($id, $conector = null) {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM prospects WHERE id=?"); $st->execute([$id]);
    $p = $st->fetch();
    if (!$p) return null;
    $conector = is_array($conector) ? $conector : [];
    $wa = is_array($conector['wa'] ?? null) ? $conector['wa'] : null;
    $ig = is_array($conector['instagram'] ?? null) ? $conector['instagram'] : null;
    $busca = array_slice(array_values(array_filter((array)($conector['busca'] ?? []), 'is_string')), 0, 12);
    $relato = ['fontes' => [], 'erros' => array_slice((array)($conector['erros'] ?? []), 0, 5)];
    $site = null; $fonte = null;

    // 1) Site informado pelo próprio dono no WhatsApp comercial
    if ($wa) {
        $relato['fontes']['whatsapp'] = !empty($wa['existe']) ? (!empty($wa['comercial']) ? 'WhatsApp Business' : 'WhatsApp comum') : 'número sem WhatsApp';
        $declarados = array_merge((array)($wa['sites'] ?? []), prosp_urls_no_texto($wa['descricao'] ?? ''));
        foreach ($declarados as $u) {
            if (!is_string($u) || $u === '') continue;
            if (prosp_site_proprio($u) && !prosp_eh_diretorio($u)) { $site = $u; $fonte = 'whatsapp'; break; }
            foreach (prosp_expandir_agregador($u) as $x) { $site = $x; $fonte = 'whatsapp'; break 2; }
        }
    }
    // 2) Instagram: link da bio / Linktree / domínio escrito na bio
    $usuario = $ig['usuario'] ?? ($p['instagram'] ?: prosp_achar_instagram(array_merge([$p['site']], $busca, [$wa['descricao'] ?? ''])));
    if ($ig || $usuario) $relato['fontes']['instagram'] = $usuario ? '@' . $usuario . (!empty($ig['bloqueado']) ? ' (não deu para abrir)' : '') : 'não achado';
    if (!$site && $ig) {
        foreach (array_merge((array)($ig['links'] ?? []), [$ig['site'] ?? ''], prosp_urls_no_texto($ig['bio'] ?? '')) as $u) {
            if (!is_string($u) || $u === '') continue;
            if (prosp_site_proprio($u) && !prosp_eh_diretorio($u)) { $site = $u; $fonte = 'instagram'; break; }
            foreach (prosp_expandir_agregador($u) as $x) { $site = $x; $fonte = 'instagram'; break 2; }
        }
    }
    // 3) Resultados da busca: só conta se a página for mesmo da empresa
    if ($busca) $relato['fontes']['busca'] = count($busca) . ' resultado(s) conferido(s)';
    if (!$site) {
        $conferidos = 0;
        foreach ($busca as $u) {
            if (!prosp_site_proprio($u) || prosp_eh_diretorio($u)) continue;
            if (++$conferidos > 3) break;
            $html = prosp_buscar_pagina($u, 6);
            if ($html && prosp_pagina_da_empresa($html, $p)) { $site = $u; $fonte = 'busca'; break; }
        }
    }
    // 4) Domínio com o nome da empresa
    if (!$site && ($d = prosp_testar_dominios($p))) { $site = $d; $fonte = 'dominio'; }
    $relato['fontes']['dominio'] = $fonte === 'dominio' ? 'achado' : 'nenhum domínio com o nome';

    $motivos = (array)json_decode((string)$p['motivos'], true);
    $motivos = array_values(array_filter($motivos, fn($m) => !str_starts_with($m, 'Site achado') && !str_starts_with($m, 'Número sem WhatsApp') && !str_starts_with($m, '✔ Verificado')));
    $pts = (int)$p['pontuacao'];
    $whats = $p['whatsapp'];
    $waExiste = $wa ? !empty($wa['existe']) : null;
    $nomesFonte = ['whatsapp' => 'no perfil do WhatsApp', 'instagram' => 'no Instagram', 'busca' => 'na busca', 'dominio' => 'pelo nome do domínio'];
    if ($site) { $motivos[] = 'Site achado ' . $nomesFonte[$fonte] . ': ' . preg_replace('#^https?://(www\.)?#', '', rtrim($site, '/')); $pts = min($pts, 30); }
    elseif ($waExiste === false) { $motivos[] = 'Número sem WhatsApp'; $pts = max(0, $pts - 20); $whats = null; }
    else $motivos[] = '✔ Verificado: sem site ' . ($wa ? 'no perfil do WhatsApp e ' : '') . 'em domínio com o nome';

    $logo = $wa['foto'] ?? ($ig['foto'] ?? null);
    $pdo->prepare("UPDATE prospects SET verificado_em=NOW(), verificacao=?, wa_existe=?, instagram=COALESCE(?, instagram), logo_url=COALESCE(?, logo_url),
            whatsapp=?, motivos=?, pontuacao=?, atualizado_em=NOW(),
            tem_site = tem_site OR ?, site = CASE WHEN ? THEN ? ELSE site END, site_fonte = CASE WHEN ? THEN ? ELSE site_fonte END,
            status = CASE WHEN ? AND status='novo' THEN 'tem_site' ELSE status END,
            cor = CASE WHEN ? IS NOT NULL AND cor_fonte IS DISTINCT FROM 'logo' THEN NULL ELSE cor END
        WHERE id=?")
        ->execute([json_encode($relato + ['site' => $site, 'fonte' => $fonte], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $waExiste === null ? null : ($waExiste ? 'true' : 'false'), $usuario ? mb_substr($usuario, 0, 80) : null,
            is_string($logo) && preg_match('#^https://#', $logo) ? mb_substr($logo, 0, 600) : null,
            $whats, json_encode($motivos, JSON_UNESCAPED_UNICODE), $pts,
            $site ? 'true' : 'false', $site ? 'true' : 'false', $site ? mb_substr($site, 0, 300) : null, $site ? 'true' : 'false', $fonte,
            $site ? 'true' : 'false', is_string($logo) ? $logo : null, $id]);
    return ['site' => $site, 'fonte' => $fonte, 'wa_existe' => $waExiste, 'instagram' => $usuario, 'relato' => $relato];
}

// ---------- Quem verifica: conector (ponte ou servidor) ou só o site ----------
function prosp_verif_modo() {
    if (!function_exists('wa_api')) return 'servidor';
    $a = wa_api();
    if ($a['tipo'] === 'ponte' && function_exists('wa_ponte_online') && wa_ponte_online()) return 'ponte';
    if ($a['tipo_salvo'] === 'gds' && $a['url'] !== '') return 'conector';
    return 'servidor';
}

// Verifica um lead agora (ou deixa na fila do conector, no modo ponte)
function prosp_verificar_lead($p) {
    global $pdo;
    $modo = prosp_verif_modo();
    if ($modo === 'ponte') {
        $pdo->prepare("UPDATE prospects SET verif_pedido_em=NULL, verificado_em=NULL WHERE id=?")->execute([$p['id']]);
        return ['ok' => true, 'pendente' => true, 'msg' => 'Na fila do conector: a resposta chega em alguns segundos.'];
    }
    $conector = null;
    if ($modo === 'conector') {
        $a = wa_api();
        $r = wa_http('POST', $a['url'] . '/gds/verificar/' . rawurlencode($a['plat_instancia'] ?: 'gds-plataforma'),
            ['numero' => $p['whatsapp'], 'nome' => $p['nome'], 'cidade' => $p['cidade'], 'instagram' => $p['instagram']], ['apikey: ' . $a['chave']], 45);
        if ($r['ok'] && is_array($r['dados'])) $conector = $r['dados'];
    }
    $res = prosp_concluir_verificacao($p['id'], $conector);
    return ['ok' => true, 'pendente' => false, 'resultado' => $res, 'parcial' => $conector === null];
}

// Leads que o conector (modo ponte) deve verificar agora — os próximos da fila primeiro
function prosp_verificacoes_para_ponte($limite = 2) {
    global $pdo;
    $porDia = max(10, (int)get_config('prosp_verif_dia', '80'));
    try {
        $hoje = (int)$pdo->query("SELECT COUNT(*) FROM prospects WHERE verif_pedido_em::date = CURRENT_DATE")->fetchColumn();
        if ($hoje >= $porDia) return [];
        $st = $pdo->query("SELECT id, whatsapp, nome, cidade, bairro, instagram FROM prospects
            WHERE NOT tem_site AND verificado_em IS NULL AND status NOT IN ('fechado','descartado','tem_site') AND COALESCE(whatsapp,'') <> ''
              AND (verif_pedido_em IS NULL OR verif_pedido_em < NOW() - INTERVAL '15 minutes')
            ORDER BY (passo = 0 AND status='novo') DESC, pontuacao DESC, avaliacoes DESC LIMIT " . (int)$limite);
        $lista = $st->fetchAll();
    } catch (PDOException $e) { return []; }
    if ($lista) $pdo->exec("UPDATE prospects SET verif_pedido_em=NOW() WHERE id IN (" . implode(',', array_map(fn($l) => (int)$l['id'], $lista)) . ")");
    return array_map(fn($l) => ['id' => (int)$l['id'], 'numero' => $l['whatsapp'], 'nome' => $l['nome'], 'cidade' => $l['cidade'], 'bairro' => $l['bairro'], 'instagram' => $l['instagram']], $lista);
}

// ---------- COR DA MARCA (para a prévia combinar com a empresa) ----------
// Cor mais marcante da imagem (ignora branco, preto, cinza e tons de pele/madeira apagados)
function prosp_cor_da_imagem($bytes, $logo = false) {
    if (!function_exists('imagecreatefromstring') || !($im = @imagecreatefromstring($bytes))) return null;
    $w = imagesx($im); $h = imagesy($im);
    $t = imagecreatetruecolor(48, 48);
    imagecopyresampled($t, $im, 0, 0, 0, 0, 48, 48, $w, $h);
    imagedestroy($im);
    $baldes = [];
    for ($x = 0; $x < 48; $x++) for ($y = 0; $y < 48; $y++) {
        $c = imagecolorat($t, $x, $y);
        $r = ($c >> 16) & 255; $g = ($c >> 8) & 255; $b = $c & 255;
        [$hh, $ss, $ll] = prosp_rgb_hsl($r, $g, $b);
        if ($ss < 0.28 || $ll < 0.12 || $ll > 0.88) continue;
        $peso = $ss * (1 - abs(2 * $ll - 1));
        if (!$logo && $hh >= 15 && $hh <= 45 && $ss < 0.6) $peso *= 0.3; // pele, madeira, piso: não é a marca
        $k = (int)floor($hh / 15);
        $baldes[$k]['p'] = ($baldes[$k]['p'] ?? 0) + $peso;
        $baldes[$k]['r'] = ($baldes[$k]['r'] ?? 0) + $r * $peso; $baldes[$k]['g'] = ($baldes[$k]['g'] ?? 0) + $g * $peso; $baldes[$k]['b'] = ($baldes[$k]['b'] ?? 0) + $b * $peso;
    }
    imagedestroy($t);
    if (!$baldes) return null;
    uasort($baldes, fn($a, $b) => $b['p'] <=> $a['p']);
    $top = reset($baldes);
    if ($top['p'] < ($logo ? 1.5 : 6)) return null; // pouca cor: a imagem é neutra
    return prosp_cor_ajustada((int)round($top['r'] / $top['p']), (int)round($top['g'] / $top['p']), (int)round($top['b'] / $top['p']));
}

function prosp_rgb_hsl($r, $g, $b) {
    $r /= 255; $g /= 255; $b /= 255;
    $max = max($r, $g, $b); $min = min($r, $g, $b); $l = ($max + $min) / 2;
    if ($max == $min) return [0, 0, $l];
    $d = $max - $min;
    $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
    $h = $max == $r ? ($g - $b) / $d + ($g < $b ? 6 : 0) : ($max == $g ? ($b - $r) / $d + 2 : ($r - $g) / $d + 4);
    return [$h * 60, $s, $l];
}

function prosp_hsl_hex($h, $s, $l) {
    $c = (1 - abs(2 * $l - 1)) * $s; $x = $c * (1 - abs(fmod($h / 60, 2) - 1)); $m = $l - $c / 2;
    [$r, $g, $b] = $h < 60 ? [$c, $x, 0] : ($h < 120 ? [$x, $c, 0] : ($h < 180 ? [0, $c, $x] : ($h < 240 ? [0, $x, $c] : ($h < 300 ? [$x, 0, $c] : [$c, 0, $x]))));
    return sprintf('#%02x%02x%02x', round(($r + $m) * 255), round(($g + $m) * 255), round(($b + $m) * 255));
}

// Deixa a cor boa para botão e destaque (nem apagada, nem clara ou escura demais)
function prosp_cor_ajustada($r, $g, $b) {
    [$h, $s, $l] = prosp_rgb_hsl($r, $g, $b);
    return prosp_hsl_hex($h, max(0.5, min(0.9, $s)), max(0.38, min(0.55, $l)));
}

// Cores escritas no nome ("Açaí Roxo", "Barbearia Black", "Pet Verde")
function prosp_cor_do_nome($nome) {
    $t = prosp_sem_acento($nome);
    $mapa = ['vermelh' => '#d62828', 'red' => '#d62828', 'roxo' => '#7b2cbf', 'purple' => '#7b2cbf', 'lilas' => '#9d4edd', 'azul' => '#1d4ed8', 'blue' => '#1d4ed8',
        'verde' => '#15803d', 'green' => '#15803d', 'amarel' => '#f2b705', 'yellow' => '#f2b705', 'laranja' => '#f26a1b', 'orange' => '#f26a1b',
        'rosa' => '#e0457b', 'pink' => '#e0457b', 'dourad' => '#c9a227', 'gold' => '#c9a227', 'ouro' => '#c9a227', 'turquesa' => '#0e9aa7', 'vinho' => '#8c1c3a'];
    foreach ($mapa as $k => $cor) if (preg_match('/\b' . $k . '/', $t)) return $cor;
    return null;
}

// Logo do lead (foto do perfil do WhatsApp/Instagram), guardado aqui: o link original expira
function prosp_logo_local($p) {
    $dir = __DIR__ . '/../uploads/prospects/' . (int)$p['id'];
    $arq = "$dir/logo.jpg";
    if (is_file($arq)) return '/uploads/prospects/' . (int)$p['id'] . '/logo.jpg';
    if (empty($p['logo_url']) || empty($p['id'])) return null;
    [$code, $bytes, , $tipo] = prosp_http($p['logo_url'], null, [], 10);
    if ($code !== 200 || !$bytes || !str_starts_with((string)$tipo, 'image/') || !function_exists('imagecreatefromstring') || !($im = @imagecreatefromstring($bytes))) return null;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { imagedestroy($im); return null; }
    if (function_exists('img_ia_pasta') && !is_file(__DIR__ . '/../uploads/.htaccess')) img_ia_pasta('prospects');
    $t = imagecreatetruecolor(256, 256);
    imagecopyresampled($t, $im, 0, 0, 0, 0, 256, 256, imagesx($im), imagesy($im));
    $ok = imagejpeg($t, $arq, 88);
    imagedestroy($im); imagedestroy($t);
    return $ok ? '/uploads/prospects/' . (int)$p['id'] . '/logo.jpg' : null;
}

// Ajusta a cor para aparecer bem no fundo do site (clareia no fundo escuro, escurece no claro)
function prosp_cor_para_fundo($cor, $fundo) {
    if (!function_exists('frag_luminancia')) return $cor;
    $r = hexdec(substr($cor, 1, 2)); $g = hexdec(substr($cor, 3, 2)); $b = hexdec(substr($cor, 5, 2));
    [$h, $sat, $l] = prosp_rgb_hsl($r, $g, $b);
    $escuro = frag_luminancia($fundo) < 0.3;
    for ($i = 0; $i < 12 && abs(frag_luminancia(prosp_hsl_hex($h, $sat, $l)) - frag_luminancia($fundo)) < 0.22; $i++) $l += $escuro ? 0.04 : -0.04;
    return prosp_hsl_hex($h, $sat, max(0.15, min(0.8, $l)));
}

// Cor da marca do lead (guardada depois da primeira vez)
function prosp_cor_marca($p) {
    global $pdo;
    if (!empty($p['cor']) && preg_match('/^#[0-9a-f]{6}$/i', $p['cor'])) return $p['cor'];
    $cor = null; $fonte = null;
    // 1) Logo (foto do perfil do WhatsApp/Instagram)
    if (($logo = prosp_logo_local($p)) && ($cor = prosp_cor_da_imagem(file_get_contents(__DIR__ . '/..' . $logo), true))) $fonte = 'logo';
    // 2) Nome
    if (!$cor && ($cor = prosp_cor_do_nome($p['nome'] ?? ''))) $fonte = 'nome';
    // 3) Fotos reais do Google (fachada, placa, ambiente)
    // (se as fotos já foram analisadas e não deram cor, não repete a análise a cada visita da prévia — só depois de 6 h)
    $semCor = __DIR__ . '/../uploads/prospects/' . (int)($p['id'] ?? 0) . '/sem_cor';
    if (!$cor && function_exists('prosp_fotos_previa') && !(is_file($semCor) && time() - (int)filemtime($semCor) < 21600)) {
        $contagem = [];
        foreach (array_slice(prosp_fotos_previa($p), 0, 3) as $u) {
            $arq = __DIR__ . '/..' . $u;
            if (is_file($arq) && ($c = prosp_cor_da_imagem(file_get_contents($arq)))) $contagem[] = $c;
        }
        if ($contagem) { $cor = $contagem[0]; $fonte = 'fotos'; }
        elseif (is_dir(dirname($semCor))) @touch($semCor);
    }
    if ($cor && !empty($p['id'])) $pdo->prepare("UPDATE prospects SET cor=?, cor_fonte=? WHERE id=?")->execute([$cor, $fonte, $p['id']]);
    return $cor;
}
