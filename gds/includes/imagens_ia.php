<?php
// ============================================================
// FOTOS GERADAS POR IA — fotos exclusivas para os sites e para o
// marketing (cada uma diferente, do jeito do negócio).
//   cloudflare    → Cloudflare Workers AI (FLUX / Lucid Origin) — grátis, ~200 fotos/dia, sem cartão
//   pollinations  → Pollinations.ai (chave grátis em enter.pollinations.ai; sem chave usa o modo antigo, lento)
//   openai        → OpenAI gpt-image-1 (pago)
//   gemini        → Gemini (desde 2026 o plano grátis do Google NÃO gera imagem: precisa de faturamento)
// No modo automático a plataforma tenta um por um, na ordem, até algum gerar —
// se um serviço cair ou acabar a cota do dia, o próximo assume sozinho.
// As imagens ficam em /uploads/ia/... (servidas pelo próprio site).
// ============================================================

require_once __DIR__ . '/ia_helper.php';

function img_ia_provedores() {
    return [
        'auto' => 'Automático — tenta todos os configurados, um por um, até gerar (recomendado)',
        'cloudflare' => 'Cloudflare Workers AI — FLUX grátis (cerca de 200 fotos por dia)',
        'pollinations' => 'Pollinations.ai (chave grátis em enter.pollinations.ai)',
        'openai' => 'OpenAI gpt-image-1 (pago)',
        'gemini' => 'Google Gemini (só com faturamento ativo — o plano grátis não gera imagem)',
        'desligado' => 'Desligado',
    ];
}

function img_ia_cf_modelos() {
    return [
        '@cf/black-forest-labs/flux-1-schnell' => 'FLUX.1 schnell — rápido, cabe mais fotos por dia',
        '@cf/leonardo/lucid-origin' => 'Leonardo Lucid Origin — mais caprichado, gasta mais da cota',
    ];
}

// O serviço tem o que precisa para gerar?
function img_ia_tem($p) {
    return match ($p) {
        'cloudflare' => trim(get_config('img_cf_conta', '')) !== '' && trim(get_config('img_cf_token', '')) !== '',
        'pollinations' => true, // com chave usa a API nova; sem chave, o modo antigo (1 foto a cada ~45s)
        'openai' => get_config('img_openai_key', '') !== '',
        'gemini' => ia_chave('gemini') !== '',
        default => false,
    };
}

// Ordem em que os serviços são tentados (o escolhido primeiro; os outros configurados como reserva)
function img_ia_cadeia() {
    $p = get_config('img_ia_provedor', 'auto');
    if ($p === 'desligado') return [];
    $lista = ($p !== 'auto' && isset(img_ia_provedores()[$p]) && img_ia_tem($p)) ? [$p] : [];
    $polKey = get_config('img_pollinations_key', '') !== '';
    foreach (['cloudflare', 'pollinations', 'openai'] as $x) {
        if ($x === 'pollinations' && !$polKey) continue; // o modo sem chave fica por último (é lento)
        if (!in_array($x, $lista, true) && img_ia_tem($x)) $lista[] = $x;
    }
    // Gemini só entra se foi escolhido (no plano grátis ele sempre recusa)
    if (!in_array('pollinations', $lista, true)) $lista[] = 'pollinations';
    return $lista;
}

function img_ia_provedor() {
    return img_ia_cadeia()[0] ?? 'desligado';
}

function img_ia_disponivel() {
    return (bool)img_ia_cadeia();
}

function img_ia_nome($p) {
    return ['cloudflare' => 'Cloudflare FLUX', 'pollinations' => 'Pollinations', 'openai' => 'OpenAI', 'gemini' => 'Gemini'][$p] ?? $p;
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
    $erros = [];
    foreach (img_ia_cadeia() as $prov) {
        $r = img_ia_gerar_com($prov, $prompt, $formato, $tam);
        if (empty($r['bytes'])) { $erros[] = img_ia_nome($prov) . ': ' . ($r['erro'] ?: 'não gerou'); continue; }
        $nome = date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $arquivo = img_ia_salvar($r['bytes'], "$pasta/$nome", $tam);
        if (!$arquivo) { $erros[] = img_ia_nome($prov) . ': a imagem veio corrompida'; continue; }
        return ['ok' => true, 'url' => '/uploads/' . trim(preg_replace('/[^a-z0-9\/_-]/', '', strtolower($sub)), '/') . '/' . basename($arquivo),
                'provedor' => $prov, 'tentativas' => $erros];
    }
    return ['ok' => false, 'erro' => $erros ? 'Nenhum gerador conseguiu agora — ' . implode(' · ', $erros) : 'Não foi possível gerar a imagem.'];
}

// Um serviço só. Retorna ['bytes' => ...] ou ['erro' => ...]
function img_ia_gerar_com($prov, $prompt, $formato, $tam) {
    $bytes = null; $erro = '';
    if ($prov === 'cloudflare') {
        $modelo = get_config('img_cf_modelo', '');
        if (!isset(img_ia_cf_modelos()[$modelo])) $modelo = '@cf/black-forest-labs/flux-1-schnell';
        $base = getenv('GDS_CF_AI_BASE_URL') ?: 'https://api.cloudflare.com/client/v4';
        $url = rtrim($base, '/') . '/accounts/' . rawurlencode(trim(get_config('img_cf_conta', ''))) . '/ai/run/' . $modelo;
        // FLUX schnell só aceita prompt e passos (sai quadrada; o recorte para o formato é feito ao salvar)
        $corpo = str_contains($modelo, 'lucid-origin')
            ? ['prompt' => mb_substr($prompt, 0, 2000), 'width' => min(2500, $tam[0]), 'height' => min(2500, $tam[1])]
            : ['prompt' => mb_substr($prompt, 0, 2048), 'steps' => 8];
        $r = img_ia_requisicao($url, $corpo, ['Authorization: Bearer ' . trim(get_config('img_cf_token', ''))], 90);
        if ($r['imagem']) $bytes = $r['imagem'];
        elseif ($r['ok'] && !empty($r['dados']['result']['image'])) $bytes = base64_decode($r['dados']['result']['image']);
        else {
            $msg = $r['dados']['errors'][0]['message'] ?? '';
            $erro = $r['status'] === 429 || stripos($msg, 'neuron') !== false ? 'acabou a cota grátis de hoje (renova às 21h de Brasília)'
                  : ($r['status'] === 401 || $r['status'] === 403 ? 'token ou ID da conta inválido' : ($msg ?: $r['erro']));
        }
    } elseif ($prov === 'gemini') {
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
        } else $erro = $r['status'] === 429 ? 'o plano grátis do Google não gera imagem (precisa ativar o faturamento no Google AI Studio)' : $r['erro'];
    } elseif ($prov === 'openai') {
        $base = getenv('GDS_OPENAI_IMG_BASE_URL') ?: 'https://api.openai.com';
        $size = $tam[0] > $tam[1] ? '1536x1024' : ($tam[0] < $tam[1] ? '1024x1536' : '1024x1024');
        $r = img_ia_post(rtrim($base, '/') . '/v1/images/generations', ['model' => 'gpt-image-1', 'prompt' => $prompt, 'size' => $size, 'n' => 1], ['Authorization: Bearer ' . get_config('img_openai_key', '')], 120);
        if ($r['ok'] && !empty($r['dados']['data'][0]['b64_json'])) $bytes = base64_decode($r['dados']['data'][0]['b64_json']);
        else $erro = $r['erro'] ?: 'A OpenAI não devolveu imagem.';
    } elseif (($chave = get_config('img_pollinations_key', '')) !== '') {
        // API nova (gen.pollinations.ai): só chaves sk_ autenticam; medidas em múltiplos de 16
        $base = getenv('GDS_POLLINATIONS_GEN_URL') ?: 'https://gen.pollinations.ai';
        $m16 = fn($v) => (int)(round($v / 16) * 16);
        $q = ['width' => $m16(min(1536, $tam[0])), 'height' => $m16(min(1536, $tam[1]) * ($tam[0] > 1536 ? 1536 / $tam[0] : 1)), 'seed' => random_int(1, 2147483000)];
        if (($mod = trim(get_config('img_poll_modelo', ''))) !== '') $q['model'] = $mod;
        $r = img_ia_requisicao(rtrim($base, '/') . '/image/' . rawurlencode(mb_substr($prompt, 0, 1500)) . '?' . http_build_query($q), null, ['Authorization: Bearer ' . $chave], 120);
        if ($r['imagem']) $bytes = $r['imagem'];
        else $erro = $r['status'] === 401 ? 'chave recusada (use a chave que começa com sk_)' : ($r['status'] === 402 || $r['status'] === 429 ? 'acabou o saldo grátis (Pollen) por agora' : ($r['dados']['error']['message'] ?? $r['dados']['error'] ?? $r['erro']));
        if (is_array($erro)) $erro = json_encode($erro, JSON_UNESCAPED_UNICODE);
    } else {
        $base = getenv('GDS_POLLINATIONS_BASE_URL') ?: 'https://image.pollinations.ai';
        $url = rtrim($base, '/') . '/prompt/' . rawurlencode(mb_substr($prompt, 0, 900)) . '?' . http_build_query(['width' => $tam[0], 'height' => $tam[1], 'nologo' => 'true', 'seed' => random_int(1, 999999), 'model' => 'flux']);
        // Sem chave o Pollinations libera 1 imagem a cada ~45s por servidor: se bater no limite, espera e tenta de novo uma vez
        $chave = '';
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
    return $bytes ? ['bytes' => $bytes] : ['erro' => (string)$erro];
}

// POST JSON (ou GET sem corpo) que pode responder com a imagem pura ou com JSON
function img_ia_requisicao($url, $corpo, $cab, $timeout) {
    $ch = curl_init($url);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTPHEADER => $cab];
    if ($corpo !== null) $opts += [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($corpo, JSON_UNESCAPED_UNICODE), CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $cab)];
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tipo = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) return ['ok' => false, 'status' => 0, 'imagem' => null, 'dados' => null, 'erro' => 'sem conexão (' . $err . ')'];
    $ok = $code >= 200 && $code < 300;
    if ($ok && str_starts_with($tipo, 'image/')) return ['ok' => true, 'status' => $code, 'imagem' => $resp, 'dados' => null, 'erro' => ''];
    $dados = json_decode($resp, true);
    return ['ok' => $ok, 'status' => $code, 'imagem' => null, 'dados' => is_array($dados) ? $dados : null,
            'erro' => $ok ? '' : 'erro ' . $code . (is_array($dados) ? '' : ': ' . mb_substr(strip_tags((string)$resp), 0, 120))];
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
        // Recorta no centro para o formato pedido (ex.: a FLUX gera quadrada e o post é 4:5)
        if (is_array($tam) && $tam[0] > 0 && $tam[1] > 0 && abs($w / $h - $tam[0] / $tam[1]) > 0.02) {
            $alvo = $tam[0] / $tam[1];
            if ($w / $h > $alvo) { $cw = (int)round($h * $alvo); $ch = $h; $x = (int)(($w - $cw) / 2); $y = 0; }
            else { $cw = $w; $ch = (int)round($w / $alvo); $x = 0; $y = (int)(($h - $ch) / 3); } // corta mais embaixo: rosto costuma ficar em cima
            $novo = imagecreatetruecolor($cw, $ch);
            imagecopy($novo, $im, 0, 0, $x, $y, $cw, $ch);
            imagedestroy($im); $im = $novo; $w = $cw; $h = $ch;
        }
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
