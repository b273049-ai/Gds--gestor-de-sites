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

function fotos_configurado() {
    return get_config('pexels_api_key', '') !== '';
}

// Busca no Pexels. Retorna [['url','medio','autor','link','alt'], ...]
function fotos_buscar($termo, $qtd = 8, $orientacao = 'landscape') {
    $chave = get_config('pexels_api_key', '');
    if ($chave === '' || trim($termo) === '') return [];
    $base = getenv('GDS_PEXELS_BASE_URL') ?: 'https://api.pexels.com';
    $url = $base . '/v1/search?' . http_build_query(['query' => $termo, 'per_page' => max(1, min(20, $qtd)), 'orientation' => $orientacao]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_HTTPHEADER => ['Authorization: ' . $chave]]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false || $code !== 200) return [];
    $d = json_decode($resp, true);
    $fotos = [];
    foreach ((array)($d['photos'] ?? []) as $f) {
        if (empty($f['src']['large2x'])) continue;
        $fotos[] = ['url' => $f['src']['large2x'], 'medio' => $f['src']['large'] ?? $f['src']['large2x'], 'autor' => $f['photographer'] ?? '', 'link' => $f['url'] ?? '', 'alt' => $f['alt'] ?? ''];
    }
    return $fotos;
}

// Fotos geradas por IA para o site (guardadas junto, em fotos_banco.ia)
function fotos_ia_do_site($empresa) {
    $salvo = json_decode((string)($empresa['fotos_banco'] ?? ''), true);
    return array_map(fn($u) => ['url' => $u, 'medio' => $u, 'autor' => '', 'link' => '', 'alt' => 'Foto exclusiva', 'ia' => true], array_values((array)($salvo['ia'] ?? [])));
}

function fotos_ia_adicionar($empresaId, $url) {
    global $pdo;
    $st = $pdo->prepare("SELECT fotos_banco FROM sites WHERE empresa_id=?");
    $st->execute([$empresaId]);
    $salvo = json_decode((string)$st->fetchColumn(), true) ?: [];
    $salvo['ia'] = array_slice(array_values(array_unique(array_merge([$url], (array)($salvo['ia'] ?? [])))), 0, 9);
    $pdo->prepare("UPDATE sites SET fotos_banco=? WHERE empresa_id=?")->execute([json_encode($salvo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $empresaId]);
    return $salvo['ia'];
}

// Fotos do site: as exclusivas (IA) primeiro, depois as do banco de imagens (busca na primeira vez)
function fotos_do_site($empresa, $forcar = false) {
    $ia = fotos_ia_do_site($empresa);
    return array_merge($ia, fotos_banco_pexels($empresa, $forcar));
}

function fotos_banco_pexels($empresa, $forcar = false) {
    global $pdo;
    $salvo = json_decode((string)($empresa['fotos_banco'] ?? ''), true);
    if (!$forcar && is_array($salvo) && isset($salvo['fotos'])) return $salvo['fotos'];
    if (!fotos_configurado()) return [];
    // Não tenta de novo antes de 1 dia se a última busca não trouxe nada
    if (!$forcar && is_array($salvo) && empty($salvo['fotos']) && time() - (int)($salvo['em'] ?? 0) < 86400) return [];
    $termo = fotos_termo_segmento($empresa['segmento_slug'] ?? '', $empresa['segmento_nome'] ?? '', $empresa['segmento_descricao'] ?? '');
    $fotos = fotos_buscar($termo, 8);
    try {
        $novo = ['termo' => $termo, 'em' => time(), 'fotos' => $fotos] + (is_array($salvo) && isset($salvo['ia']) ? ['ia' => $salvo['ia']] : []);
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
        $ia = (array)($salvo['ia'] ?? []);
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
        $atual['ia'] = array_slice(array_values(array_unique(array_merge((array)($atual['ia'] ?? []), [$r['url']]))), 0, 9);
        $pdo->prepare("UPDATE sites SET fotos_banco=? WHERE empresa_id=?")->execute([json_encode($atual, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $s['id']]);
        return ['fotos_ia' => 'empresa ' . $s['id'] . ': ' . count($atual['ia']) . ' foto(s)'];
    }
    return ['fotos_ia' => 'nada a fazer'];
}
