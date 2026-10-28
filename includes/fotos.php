<?php
// ============================================================
// FOTOS PROFISSIONAIS (Pexels — gratuito, uso comercial liberado)
// Sites sem fotos próprias ganham fotos reais do tipo de negócio
// (no topo do template e como opção para a IA). A busca é feita uma
// vez por empresa e fica guardada em sites.fotos_banco.
// Chave grátis em https://www.pexels.com/api/ → Admin → Configurações.
// ============================================================

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

// Fotos guardadas do site (busca na primeira vez)
function fotos_do_site($empresa, $forcar = false) {
    global $pdo;
    $salvo = json_decode((string)($empresa['fotos_banco'] ?? ''), true);
    if (!$forcar && is_array($salvo) && isset($salvo['fotos'])) return $salvo['fotos'];
    if (!fotos_configurado()) return [];
    // Não tenta de novo antes de 1 dia se a última busca não trouxe nada
    if (!$forcar && is_array($salvo) && empty($salvo['fotos']) && time() - (int)($salvo['em'] ?? 0) < 86400) return [];
    $termo = fotos_termo_segmento($empresa['segmento_slug'] ?? '', $empresa['segmento_nome'] ?? '', $empresa['segmento_descricao'] ?? '');
    $fotos = fotos_buscar($termo, 8);
    try {
        $pdo->prepare("UPDATE sites SET fotos_banco=? WHERE empresa_id=?")->execute([json_encode(['termo' => $termo, 'em' => time(), 'fotos' => $fotos], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $empresa['id']]);
    } catch (PDOException $e) { /* coluna ainda não existe */ }
    return $fotos;
}
