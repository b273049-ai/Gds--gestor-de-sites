<?php
// ============================================================
// FOTOS GERADAS POR IA — fotos exclusivas para os sites e para o
// marketing (cada uma diferente, do jeito do negócio).
//   gemini        → Gemini (usa a mesma chave do Google da IA de texto)
//   openai        → OpenAI gpt-image-1 (chave da OpenAI)
//   pollinations  → Pollinations.ai (grátis, sem chave; mais simples)
// As imagens ficam em /uploads/ia/... (servidas pelo próprio site).
// ============================================================

require_once __DIR__ . '/ia_helper.php';

function img_ia_provedores() {
    return [
        'auto' => 'Automático (Gemini se houver chave do Google; senão Pollinations grátis)',
        'gemini' => 'Google Gemini (mesma chave do Gemini)',
        'openai' => 'OpenAI gpt-image-1 (chave da OpenAI)',
        'pollinations' => 'Pollinations.ai (grátis, sem chave)',
        'desligado' => 'Desligado',
    ];
}

function img_ia_provedor() {
    $p = get_config('img_ia_provedor', 'auto');
    if ($p === 'auto') return ia_chave('gemini') !== '' ? 'gemini' : 'pollinations';
    return isset(img_ia_provedores()[$p]) ? $p : 'desligado';
}

function img_ia_disponivel() {
    $p = img_ia_provedor();
    if ($p === 'gemini') return ia_chave('gemini') !== '';
    if ($p === 'openai') return get_config('img_openai_key', '') !== '';
    return $p === 'pollinations';
}

// Pasta pública para as imagens (cria e protege na primeira vez)
function img_ia_pasta($sub) {
    $base = __DIR__ . '/../uploads';
    if (!is_dir($base)) @mkdir($base, 0755, true);
    if (!is_file("$base/.htaccess")) @file_put_contents("$base/.htaccess", "# Só imagens: nada aqui pode ser executado\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)$\">\n  Require all denied\n</FilesMatch>\nOptions -Indexes\n");
    $sub = preg_replace('/[^a-z0-9\/_-]/', '', strtolower($sub));
    $dir = "$base/$sub";
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return is_writable($dir) ? $dir : null;
}

// Monta um prompt de foto profissional (sem texto, sem logo)
function img_ia_prompt_foto($cena, $estilo = '') {
    return "Professional editorial photograph: $cena. " . ($estilo ? "$estilo. " : '')
         . 'Shot on a full-frame camera, natural cinematic light, shallow depth of field, rich detail, authentic Brazilian small business atmosphere, '
         . 'magazine quality. No text, no letters, no watermark, no logos.';
}

// Gera UMA imagem. $formato: 16:9 | 4:5 | 1:1 | 9:16. Retorna ['ok'=>true,'url'=>'/uploads/...'] ou ['ok'=>false,'erro'=>...]
function img_ia_gerar($prompt, $formato = '16:9', $sub = 'ia/geral') {
    if (!img_ia_disponivel()) return ['ok' => false, 'erro' => 'Geração de fotos com IA não configurada (Admin → Configurações → Fotos).'];
    $pasta = img_ia_pasta($sub);
    if (!$pasta) return ['ok' => false, 'erro' => 'A pasta uploads/ não tem permissão de escrita no servidor.'];
    $tam = ['16:9' => [1600, 900], '4:5' => [1080, 1350], '1:1' => [1080, 1080], '9:16' => [1080, 1920], '3:4' => [1200, 1600]][$formato] ?? [1600, 900];
    $prov = img_ia_provedor();
    $bytes = null; $erro = '';
    if ($prov === 'gemini') {
        $base = getenv('GDS_GEMINI_BASE_URL') ?: 'https://generativelanguage.googleapis.com';
        $modelo = get_config('img_gemini_modelo', '') ?: 'gemini-2.5-flash-image';
        $url = rtrim($base, '/') . '/v1beta/models/' . rawurlencode($modelo) . ':generateContent';
        $corpo = ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['responseModalities' => ['IMAGE'], 'imageConfig' => ['aspectRatio' => $formato]]];
        $r = img_ia_post($url, $corpo, ['x-goog-api-key: ' . ia_chave('gemini')], 90);
        if (!$r['ok'] && $r['status'] === 400) { unset($corpo['generationConfig']['imageConfig']); $corpo['generationConfig']['responseModalities'] = ['TEXT', 'IMAGE']; $r = img_ia_post($url, $corpo, ['x-goog-api-key: ' . ia_chave('gemini')], 90); }
        if ($r['ok']) {
            foreach ((array)($r['dados']['candidates'][0]['content']['parts'] ?? []) as $parte) {
                $d = $parte['inlineData']['data'] ?? ($parte['inline_data']['data'] ?? null);
                if ($d) { $bytes = base64_decode($d); break; }
            }
            if (!$bytes) $erro = 'O Gemini não devolveu imagem (o modelo de imagem pode não estar liberado para a sua chave).';
        } else $erro = $r['erro'];
    } elseif ($prov === 'openai') {
        $base = getenv('GDS_OPENAI_IMG_BASE_URL') ?: 'https://api.openai.com';
        $size = $tam[0] > $tam[1] ? '1536x1024' : ($tam[0] < $tam[1] ? '1024x1536' : '1024x1024');
        $r = img_ia_post(rtrim($base, '/') . '/v1/images/generations', ['model' => 'gpt-image-1', 'prompt' => $prompt, 'size' => $size, 'n' => 1], ['Authorization: Bearer ' . get_config('img_openai_key', '')], 120);
        if ($r['ok'] && !empty($r['dados']['data'][0]['b64_json'])) $bytes = base64_decode($r['dados']['data'][0]['b64_json']);
        else $erro = $r['erro'] ?: 'A OpenAI não devolveu imagem.';
    } else {
        $base = getenv('GDS_POLLINATIONS_BASE_URL') ?: 'https://image.pollinations.ai';
        $url = rtrim($base, '/') . '/prompt/' . rawurlencode(mb_substr($prompt, 0, 900)) . '?' . http_build_query(['width' => $tam[0], 'height' => $tam[1], 'nologo' => 'true', 'seed' => random_int(1, 999999), 'model' => 'flux']);
        // Sem chave o Pollinations libera 1 imagem a cada ~45s por servidor: se bater no limite, espera e tenta de novo uma vez
        $chave = get_config('img_pollinations_key', '');
        for ($tentativa = 0; $tentativa < 2; $tentativa++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => $chave !== '' ? ['Authorization: Bearer ' . $chave] : []]);
            $resp = curl_exec($ch);
            $tipo = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($resp !== false && $code === 200 && str_starts_with($tipo, 'image/')) { $bytes = $resp; break; }
            $limite = in_array($code, [402, 429], true);
            $erro = $limite ? 'O gerador grátis libera 1 foto a cada 45 segundos. Aguarde um pouco e tente de novo (ou cadastre uma chave do Pollinations/Gemini em Configurações).'
                            : 'O serviço grátis de imagens não respondeu agora (código ' . $code . '). Tente de novo em instantes.';
            if (!$limite || $tentativa > 0) break;
            $espera = preg_match('/after (\d+)s/i', (string)$resp, $m) ? (int)$m[1] + 2 : 46;
            if ($espera > 50) break;
            sleep($espera);
        }
    }
    if (!$bytes) return ['ok' => false, 'erro' => $erro ?: 'Não foi possível gerar a imagem.'];
    $nome = date('Ymd-His') . '-' . bin2hex(random_bytes(4));
    $arquivo = img_ia_salvar($bytes, "$pasta/$nome", $tam);
    if (!$arquivo) return ['ok' => false, 'erro' => 'A imagem gerada veio corrompida. Tente de novo.'];
    return ['ok' => true, 'url' => '/uploads/' . trim(preg_replace('/[^a-z0-9\/_-]/', '', strtolower($sub)), '/') . '/' . basename($arquivo), 'provedor' => $prov];
}

function img_ia_post($url, $corpo, $cab, $timeout) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $cab), CURLOPT_POSTFIELDS => json_encode($corpo)]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) return ['ok' => false, 'status' => 0, 'erro' => 'Sem conexão com o serviço de imagens (' . $err . ')', 'dados' => null];
    $dados = json_decode($resp, true);
    $ok = $code >= 200 && $code < 300;
    $msg = $ok ? '' : ('Erro ' . $code . ': ' . mb_substr((string)($dados['error']['message'] ?? $resp), 0, 200));
    return ['ok' => $ok, 'status' => $code, 'erro' => $msg, 'dados' => $dados];
}

// Salva como JPEG otimizado (com GD) ou do jeito que veio
function img_ia_salvar($bytes, $semExtensao, $tam) {
    if (function_exists('imagecreatefromstring') && ($im = @imagecreatefromstring($bytes))) {
        $w = imagesx($im); $h = imagesy($im);
        $max = 1920;
        if ($w > $max || $h > $max) {
            $esc = $max / max($w, $h);
            $nw = (int)round($w * $esc); $nh = (int)round($h * $esc);
            $novo = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($novo, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($im); $im = $novo;
        }
        $arq = "$semExtensao.jpg";
        $ok = imagejpeg($im, $arq, 84);
        imagedestroy($im);
        return $ok ? $arq : null;
    }
    $info = @getimagesizefromstring($bytes);
    if (!$info) return null;
    $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$info['mime']] ?? null;
    if (!$ext) return null;
    return file_put_contents("$semExtensao.$ext", $bytes) ? "$semExtensao.$ext" : null;
}

// Cenas certeiras por tipo de negócio (o que mostrar nas fotos)
function img_ia_cenas($slug, $nome, $descricao = '') {
    $c = [
        'barbearia' => ['a skilled barber giving a sharp fade haircut in a moody vintage barbershop with leather chairs, warm tungsten light', 'close-up of a straight razor beard shave with hot towel, barbershop details', 'stylish barbershop interior with wooden shelves, mirrors and classic barber chairs, dusk light'],
        'salao' => ['hairstylist styling glossy long hair in a bright modern beauty salon', 'close-up of hair coloring brush and foils, elegant salon', 'chic minimalist hair salon interior with plants and soft daylight'],
        'estetica' => ['serene aesthetic clinic treatment room with a woman receiving a facial treatment, soft light', 'skincare products on marble with towels, spa mood', 'calm clinic reception with neutral tones'],
        'hamburgueria' => ['juicy smash burger with melted cheese and crispy fries on a dark wooden board, dramatic food photography', 'burger being assembled in an urban burger joint kitchen, flames', 'neon-lit casual burger restaurant with friends eating'],
        'pizzaria' => ['wood-fired pizza with bubbling mozzarella coming out of the oven', 'pizza slice cheese pull close-up', 'cozy pizzeria with brick oven'],
        'dj' => ['DJ performing at a wedding party with crowd dancing, colorful stage lights and haze', 'close-up of DJ hands on a mixer with neon lights', 'elegant event venue dance floor with moving head lights'],
        'fotografia' => ['photographer capturing a couple at golden hour', 'professional camera and lenses on a table', 'photo studio with softboxes'],
        'clinica' => ['friendly doctor talking with a patient in a modern bright clinic', 'clean modern medical reception', 'medical team smiling in a bright corridor'],
        'odontologia' => ['dentist with smiling patient in a modern dental office', 'bright modern dental chair room', 'close-up of a perfect healthy smile'],
        'academia' => ['athlete training with dumbbells in a modern gym with dramatic light', 'group functional training class', 'modern gym interior'],
        'petshop' => ['happy dog after grooming with a bow, pet salon', 'groomer brushing a fluffy dog', 'colorful pet shop interior'],
        'mecanica' => ['mechanic working on a car engine in a clean modern workshop', 'car on a lift in a professional garage', 'mechanic shaking hands with a customer'],
        'restaurante' => ['beautifully plated Brazilian dish on a rustic table, restaurant ambience', 'chef plating food in a busy kitchen', 'warm restaurant interior at night'],
        'acai' => ['acai bowl with fruits and granola, vibrant colors, top view', 'acai shop counter with toppings', 'friends enjoying acai on a sunny day'],
        'construcao' => ['renovated modern living room interior, finished construction', 'construction team working on a house renovation', 'architect reviewing plans on site'],
    ];
    if (isset($c[$slug])) return $c[$slug];
    $t = trim($descricao ?: $nome) ?: 'a local small business';
    return ["$t — main scene showing the service being provided, welcoming", "$t — detail close-up of the work or product", "$t — the place / environment, inviting and premium"];
}
