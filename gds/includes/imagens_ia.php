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

// Monta um prompt de foto com cara de fotógrafo de verdade, não de "IA".
// O que entrega uma foto de IA: rosto de modelo perfeito olhando de lado, pele de plástico, fundo
// todo desfocado com bolinhas de luz, brilho e cor saturada demais, tudo simétrico e limpo demais.
// Por isso: foto documental, luz do lugar, detalhes e mãos no trabalho, gente só de costas/fora de foco.
function img_ia_prompt_foto($cena, $estilo = '') {
    return "Candid documentary photograph taken by a professional photographer for a real small business in Brazil: $cena. " . ($estilo ? "$estilo. " : '')
         . 'Available natural light from windows or the room lights, true-to-life muted colors, realistic textures with small everyday imperfections, '
         . 'shot at eye level with a 35mm lens at f/4 so the background stays recognisable, subtle film grain, slightly off-center composition. '
         . 'People only as hands at work, from behind or naturally out of focus; nobody posing or looking at the camera. '
         . 'Avoid: model faces, glamour retouching, plastic skin, bokeh orbs, glow, lens flare, HDR, oversaturated colors, perfect symmetry, stock-photo look. '
         . 'No text, no letters, no watermark, no logos.';
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

// Cenas certeiras por tipo de negócio (o que mostrar nas fotos): o trabalho, o lugar e o detalhe —
// como num ensaio de fotógrafo de verdade para pequeno negócio. Sem rosto de modelo.
// A 1ª vira a foto do topo (larga), as outras vão para o "Sobre" (em pé).
function img_ia_cenas($slug, $nome, $descricao = '') {
    $c = [
        'barbearia' => ['interior of a lived-in neighbourhood barbershop in late afternoon light, empty vintage barber chair, worn wooden counter with clippers, combs and a towel, mirror reflections', "barber's hands trimming a beard with scissors, seen over the client's shoulder, client's face out of frame", 'detail of barber tools laid on a used leather tray: straight razor, brushes, clippers with cables'],
        'salao' => ['small independent hair salon on a sunny morning, styling station with round brushes, hair dryer and products in use, a chair turned toward the mirror, plants by the window', "hairstylist's hands blow-drying long brown hair with a round brush, seen from behind, client's face not visible", 'detail of a salon trolley with colour bowls, brushes, foils and clips, some product stains, natural window light'],
        'estetica' => ['calm treatment room of a small aesthetics clinic, made-up treatment bed with folded towels, soft daylight through a linen curtain', 'aesthetician hands applying a cream mask with a brush, close crop, no face visible', 'skincare bottles and folded towels on a wooden shelf, real labels blurred'],
        'hamburgueria' => ['smash burger with melted cheese and fries in a paper-lined basket on a steel counter of a small burger joint, kitchen in the background', 'cook pressing burger patties on a hot griddle with steam, hands and spatula only', 'counter of a casual burger place at night with sauce bottles and order tickets'],
        'pizzaria' => ['pizza with bubbling mozzarella just out of a wood-fired oven on a floured wooden peel', "pizzaiolo's hands stretching dough on a floured marble counter", 'brick oven with burning wood in a small family pizzeria'],
        'dj' => ['wedding dance floor at night seen from behind the DJ booth, guests dancing out of focus, coloured stage lights and light haze', "DJ's hands on a mixer and controller with cables and headphones", 'empty event hall being set up with speakers on stands and moving head lights'],
        'fotografia' => ['camera with a worn strap on a wooden table next to printed photos and a laptop', "photographer seen from behind shooting a couple walking at golden hour, couple out of focus", 'small photo studio with a paper backdrop and softboxes'],
        'clinica' => ['bright tidy reception of a small medical clinic with chairs and plants, nobody posing', 'doctor writing on a clipboard at a desk, only hands and white coat sleeve visible', 'clean consultation room with examination bed and natural light'],
        'odontologia' => ['modern dental chair room with natural light, instruments tray ready', "dentist's gloved hands arranging instruments on a tray", 'clean reception of a small dental office'],
        'academia' => ['weights area of a neighbourhood gym in morning light, dumbbells on the rack, chalk marks', 'person seen from behind lifting a barbell, motion slightly blurred', 'detail of kettlebells and ropes on a rubber floor'],
        'petshop' => ['small dog sitting calmly on a grooming table after a bath, fluffy coat, real pet salon in the background', "groomer's hands brushing a fluffy dog, groomer's face out of frame", 'pet shop shelf with leashes, toys and bags of food'],
        'mecanica' => ['clean independent auto repair shop with a car on a lift, tools on the wall', "mechanic's hands with a wrench on an engine, a little grease", 'organised tool cart with sockets and wrenches'],
        'restaurante' => ['plated Brazilian home-style dish on a rustic table by the window of a small restaurant', "cook's hands plating food in a busy small kitchen, steam", 'restaurant dining room before opening, tables set, warm lamps'],
        'acai' => ['acai bowl with banana, strawberries and granola on a wooden counter, top-down, natural light', 'hands adding granola to an acai cup at the shop counter', 'colourful toppings containers at the counter of a small acai shop'],
        'construcao' => ['recently renovated living room with natural light, finished floor and walls, a few tools still in a corner', "builder's hands laying tiles with a trowel", 'architectural plans, tape measure and pencil on a work table on site'],
    ];
    if (isset($c[$slug])) return $c[$slug];
    $t = trim($descricao ?: $nome) ?: 'a local small business';
    return ["$t — the real workplace, tidy and in use, nobody posing", "$t — hands doing the work, close crop, no faces", "$t — detail of the tools or products on a real surface"];
}
