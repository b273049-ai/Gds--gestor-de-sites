<?php
// ============================================================
// MARKETING COM IA — PLANO COM OS POSTS PRONTOS
// Um clique monta a semana ou o mês: cada dia já vem com o formato, o melhor
// horário, a arte (com foto feita por IA), a legenda, as hashtags, o roteiro
// do Reels, a narração, os stories com o adesivo certo e o prompt para animar
// a foto no Kling/Dreamina. A IA trabalha item por item (pela tela ou pelo cron),
// e na hora de postar chega um lembrete no seu WhatsApp.
//
// O que a pesquisa mostra (aplicado nos prompts e no calendário):
//  • Reels: gancho nos 2–3 primeiros segundos, texto na tela (muita gente vê sem som),
//    cortes a cada 3–5 s, 15–30 s, final que pede para salvar/compartilhar.
//  • Carrossel: capa com gancho, 5–8 slides de 1 ideia cada, último com a chamada.
//  • Legenda: gancho nas primeiras ~125 letras (antes do "mais"), 3–5 hashtags certeiras.
//  • Foto: pessoa real do público, rosto, luz natural, vertical e espaço livre para o título.
//  • Horários (médias Brasil): Reels à noite (19h–21h, ter–qui mais fortes), feed no almoço
//    ou fim de tarde, stories de manhã, almoço e noite. O seu Insights vale mais que a média.
// ============================================================
require_once __DIR__ . '/marketing_banco.php';
require_once __DIR__ . '/fotos.php';
require_once __DIR__ . '/whatsapp.php';

const MKT_TIPOS = ['reels' => 'Reels', 'carrossel' => 'Carrossel', 'feed' => 'Post', 'story' => 'Stories'];

function mkt_formato_do_tipo($tipo) {
    return in_array($tipo, ['reels', 'story'], true) ? 'story' : 'feed';
}

// Melhor horário por formato e dia (1 = segunda … 7 = domingo)
function mkt_horario($tipo, $dow) {
    $fim = $dow >= 6;
    return match ($tipo) {
        'reels' => $fim ? '19:00' : (in_array($dow, [2, 3, 4], true) ? '19:30' : '20:00'),
        'carrossel' => $fim ? '10:30' : '12:00',
        'feed' => $fim ? '11:00' : '18:30',
        default => '08:30', // stories: 1º às 8h30, depois 12h30 e 20h30
    };
}

function mkt_horarios_story($n) {
    return array_slice(['08:30', '12:30', '20:30', '17:30', '10:00'], 0, max(1, $n));
}

// Dias da semana com post e o formato de cada um (Reels ~40%, Carrossel ~30%, Post ~30%)
function mkt_grade_semana($porSemana) {
    $dias = [3 => [2, 4, 6], 5 => [1, 2, 3, 4, 5], 7 => [1, 2, 3, 4, 5, 6, 7]][$porSemana] ?? [1, 2, 3, 4, 5];
    $tipos = ['reels', 'carrossel', 'feed', 'reels', 'carrossel', 'reels', 'feed'];
    $out = [];
    foreach ($dias as $i => $d) $out[$d] = $tipos[$i % count($tipos)];
    return $out;
}

// Funil do mês: atrair → educar → provar → converter
function mkt_objetivos_semana($semana) {
    return [1 => ['dor', 'educativo', 'bastidores'], 2 => ['educativo', 'recursos', 'objecao'], 3 => ['prova', 'antes_depois', 'bastidores'], 4 => ['objecao', 'oferta', 'prova']][(($semana - 1) % 4) + 1];
}

// Cria o plano e os itens (temas e ganchos pela IA; sem IA, do banco de temas)
function mkt_plano_criar($opts) {
    global $pdo;
    $mes = ($opts['periodo'] ?? 'semana') === 'mes';
    $porSemana = in_array((int)($opts['por_semana'] ?? 5), [3, 5, 7], true) ? (int)$opts['por_semana'] : 5;
    $storiesDia = max(0, min(3, (int)($opts['stories_dia'] ?? 2)));
    $publico = mb_substr(trim((string)($opts['publico'] ?? '')), 0, 120);
    $inicio = strtotime('tomorrow');
    $semanas = $mes ? 4 : 1;
    $grade = mkt_grade_semana($porSemana);
    $slots = [];
    for ($d = 0; $d < 7 * $semanas; $d++) {
        $ts = $inicio + $d * 86400;
        $dow = (int)date('N', $ts);
        $semana = intdiv($d, 7) + 1;
        if (isset($grade[$dow])) $slots[] = ['data' => date('Y-m-d', $ts), 'dow' => $dow, 'tipo' => $grade[$dow], 'semana' => $semana];
        if ($storiesDia > 0) $slots[] = ['data' => date('Y-m-d', $ts), 'dow' => $dow, 'tipo' => 'story', 'semana' => $semana];
    }
    $temas = mkt_plano_temas($slots, $publico, $mes);
    $st = $pdo->prepare("INSERT INTO marketing_planos (nome, publico, periodo, inicio, por_semana, stories_dia, redes) VALUES (?,?,?,?,?,?,?) RETURNING id");
    $st->execute([($mes ? 'Mês de ' : 'Semana de ') . date('d/m', $inicio) . ($publico ? ' · ' . $publico : ''), $publico, $mes ? 'mes' : 'semana', date('Y-m-d', $inicio), $porSemana, $storiesDia, (string)($opts['redes'] ?? '')]);
    $planoId = (int)$st->fetchColumn();
    $ins = $pdo->prepare("INSERT INTO marketing_itens (plano_id, data, hora, tipo, objetivo, framework, tema, gancho) VALUES (?,?,?,?,?,?,?,?)");
    foreach ($slots as $i => $s) {
        $t = $temas[$i] ?? [];
        $obj = mkt_objetivos_semana($s['semana']);
        $ins->execute([$planoId, $s['data'], mkt_horario($s['tipo'], $s['dow']), $s['tipo'], $t['objetivo'] ?? $obj[$i % 3], $t['framework'] ?? '',
            mb_substr((string)($t['tema'] ?? ''), 0, 300), mb_substr((string)($t['gancho'] ?? ''), 0, 300)]);
    }
    return $planoId;
}

function mkt_plano_temas($slots, $publico, $mes) {
    $marca = get_config('mkt_marca', 'GDS Sites');
    if (ia_configurada()) {
        $lista = implode("\n", array_map(fn($s, $i) => ($i + 1) . ". semana {$s['semana']} · " . MKT_TIPOS[$s['tipo']], $slots, array_keys($slots)));
        $prompt = "Você é estrategista de conteúdo de uma marca que vende sites com IA para negócios locais (\"$marca\": agendamento online, WhatsApp automático, orçamento, delivery, domínio próprio). "
                . "Público: " . ($publico ?: 'pequenos negócios locais') . ". Crie o tema de CADA item do calendário abaixo, na mesma ordem, seguindo o funil "
                . ($mes ? '(semana 1 atrai, 2 educa, 3 prova, 4 converte com oferta no fim)' : '(atrair, educar, provar e converter dentro da semana)') . ". "
                . "Stories são conversa e bastidor (enquete, pergunta, contagem), não anúncio. Cada gancho deve ser diferente (pergunta, número, erro comum, contraste, história). "
                . "Responda APENAS JSON: {\"itens\":[{\"tema\":\"...\",\"gancho\":\"primeira frase\",\"objetivo\":\"dor|antes_depois|recursos|prova|oferta|educativo|bastidores|objecao\",\"framework\":\"aida|pas|bab|4u|historia|mito|lista\"}]}\n\nCALENDÁRIO:\n$lista";
        $r = chamar_ia_json($prompt, 6000, 90, 0.9, ['esforco' => 'low']);
        if ($r['ok'] && !empty($r['dados']['itens'])) return array_values((array)$r['dados']['itens']);
    }
    // Sem IA: temas do banco, girando
    $banco = mkt_semana_sem_ia($publico);
    return array_map(fn($s, $i) => $banco[$i % count($banco)], $slots, array_keys($slots));
}

// Gera o conteúdo completo de UM item (texto + foto). Retorna ['ok'=>bool,'erro'=>...]
function mkt_item_gerar($item) {
    global $pdo;
    $plano = $pdo->prepare("SELECT * FROM marketing_planos WHERE id=?"); $plano->execute([$item['plano_id']]); $plano = $plano->fetch();
    $marca = get_config('mkt_marca', 'GDS Sites');
    $preco = get_config('mkt_preco', '');
    $publico = $plano['publico'] ?: 'pequenos negócios locais';
    $tipo = $item['tipo'];
    $nStories = max(1, min(5, (int)$plano['stories_dia']));
    $post = null;
    if (ia_configurada()) {
        $regrasTipo = [
            'reels' => 'É um REELS (vídeo vertical 9:16). O gancho aparece escrito na tela em até 2 segundos. Roteiro de 5 a 7 cenas curtas (15 a 30 s no total), uma ideia por cena, final pedindo para SALVAR ou MANDAR para um amigo dono de negócio. "narracao" é o texto falado inteiro, natural, 40 a 70 palavras.',
            'carrossel' => 'É um CARROSSEL. "conteudo.titulo" é a capa (gancho que faz arrastar). "conteudo.itens" são 5 a 7 slides, cada um com UMA ideia em até 12 palavras. O último slide é a chamada (vai em "cta").',
            'feed' => 'É um POST de imagem única. Título forte e curto, subtítulo que completa a ideia.',
            'story' => 'É uma SEQUÊNCIA DE STORIES (conversa e bastidor). Capriche nos "stories": cada um com texto curto e um adesivo que gera resposta.',
        ][$tipo];
        $prompt = "Você é social media e copywriter de resposta direta que vende para pequenos negócios brasileiros. Crie o conteúdo para a marca \"$marca\" "
                . "(sites com IA para negócios locais: agendamento online, confirmação e lembrete no WhatsApp, orçamento, delivery, domínio próprio). " . ($preco ? "Preço/condição: $preco. " : '')
                . "\nPÚBLICO: $publico\nTEMA: {$item['tema']}\nGANCHO SUGERIDO: {$item['gancho']}\nOBJETIVO: {$item['objetivo']}" . ($item['framework'] ? "\nESTRUTURA DA COPY: {$item['framework']}" : '')
                . "\n$regrasTipo\n"
                . "Regras: português do Brasil, simples e direto, sem números inventados nem promessas exageradas. Título com no máximo 9 palavras e *asteriscos* em 1 a 3 palavras de destaque. "
                . "Legenda: a 1ª linha (até 125 letras) é o gancho; 3 a 6 linhas curtas; chamada para comentar, salvar ou chamar no direct. 3 a 5 hashtags certeiras (nicho + local), sem #. "
                . "\"cena_foto\": EM INGLÊS, foto realista e vertical de uma pessoa real do público no ambiente do negócio, rosto visível, luz natural, espaço livre no topo para o título, sem texto na imagem. "
                . "\"prompt_video\": EM INGLÊS, como animar essa foto num gerador de vídeo (movimento de câmera sutil + ação da pessoa, 5 segundos). "
                . ($tipo === 'story' ? "\"stories\": $nStories stories para postar no dia, cada um {\"texto\":\"até 14 palavras\",\"adesivo\":\"enquete|pergunta|quiz|contagem|link|nenhum\",\"opcoes\":[\"para enquete/quiz\"]}. " : "\"stories\": []. ")
                . "Modelo de arte (VARIE): impacto | lista | antes_depois | oferta | foto_titulo | foto_split | pergunta | numero. Estilo: neon | editorial | bold | pop | minimal | luxo.\n"
                . 'Responda APENAS JSON: {"modelo":"","estilo":"","cena_foto":"","prompt_video":"","conteudo":{"selo":"até 5 palavras","titulo":"","subtitulo":"","itens":[],"antes":[],"depois":[],"preco":"","cta":"até 4 palavras"},'
                . '"legenda":"","hashtags":[],"roteiro":[{"tempo":"0-3s","texto_tela":"","fala":""}],"narracao":"","stories":[]}';
        $r = chamar_ia_json($prompt, 2500, 75, 0.9, ['esforco' => 'low']);
        if ($r['ok']) $post = $r['dados'];
        elseif (!empty($r['temporario']) || (int)$item['tentativas'] < 1) return ['ok' => false, 'erro' => $r['erro'], 'tentar_de_novo' => true];
    }
    if (!$post) {
        $post = mkt_post_sem_ia($item['objetivo'], $plano['publico'], $marca, $preco);
        $post['cena_foto'] = $post['cena_foto'] ?? '';
        $post['stories'] = mkt_stories_sem_ia($item, $nStories);
        $post['narracao'] = implode(' ', array_map(fn($c) => $c['fala'], (array)$post['roteiro']));
        $post['prompt_video'] = 'Subtle slow camera push-in, the person smiles and looks at the phone, natural light, 5 seconds, realistic.';
    }
    // Normaliza
    if (!in_array($post['modelo'] ?? '', ['impacto', 'lista', 'antes_depois', 'depoimento', 'oferta', 'foto_titulo', 'foto_split', 'pergunta', 'numero'], true)) $post['modelo'] = 'foto_titulo';
    if ($tipo === 'carrossel' && empty($post['conteudo']['itens'])) $post['conteudo']['itens'] = array_map(fn($c) => $c['texto_tela'] ?? '', array_slice((array)($post['roteiro'] ?? []), 1, 5));
    if (!in_array($post['estilo'] ?? '', ['neon', 'editorial', 'bold', 'pop', 'minimal', 'luxo'], true)) $post['estilo'] = ['neon', 'editorial', 'bold', 'pop', 'minimal', 'luxo'][random_int(0, 5)];
    $post['stories'] = $tipo === 'story' ? array_slice(array_values(array_filter((array)($post['stories'] ?? []), fn($s) => is_array($s) && !empty($s['texto']))), 0, 5) : [];
    if ($tipo === 'story' && !$post['stories']) $post['stories'] = mkt_stories_sem_ia($item, $nStories);
    $post['hashtags'] = array_slice(array_values(array_filter(array_map(fn($h) => preg_replace('/[#\s]+/u', '', (string)$h), (array)($post['hashtags'] ?? [])))), 0, 8);
    // Foto: IA primeiro; se não der, banco de fotos
    $foto = null;
    $cena = trim((string)($post['cena_foto'] ?? '')) ?: 'small business owner smiling while checking appointments on a smartphone in a modern local shop';
    if (img_ia_disponivel()) {
        $g = img_ia_gerar(img_ia_prompt_foto($cena), mkt_formato_do_tipo($tipo) === 'story' ? '9:16' : '4:5', 'ia/marketing');
        if ($g['ok']) { $foto = $g['url']; $post['foto_origem'] = img_ia_nome($g['provedor']); }
    }
    if (!$foto) {
        $foto = mkt_foto_banco($plano['publico'] ?: 'small business', mkt_formato_do_tipo($tipo) === 'story' ? 'portrait' : 'landscape');
        if ($foto) $post['foto_origem'] = 'banco de fotos';
    }
    $pdo->prepare("UPDATE marketing_itens SET post=?, foto_url=?, status='pronto', erro=NULL, atualizado_em=NOW() WHERE id=?")
        ->execute([json_encode($post, JSON_UNESCAPED_UNICODE), $foto, $item['id']]);
    return ['ok' => true];
}

function mkt_stories_sem_ia($item, $n) {
    $base = [['texto' => 'Bom dia! Hoje é dia de ' . mb_strtolower(MKT_TIPOS[$item['tipo']] ?? 'novidade') . ' por aqui ☀️', 'adesivo' => 'nenhum'],
             ['texto' => 'Seu cliente consegue marcar com você às 22h de domingo?', 'adesivo' => 'enquete', 'opcoes' => ['Consegue', 'Ainda não']],
             ['texto' => 'Qual a sua maior dor no atendimento?', 'adesivo' => 'pergunta'],
             ['texto' => 'Site pronto em minutos, com agendamento e WhatsApp automático', 'adesivo' => 'link'],
             ['texto' => 'Quanto tempo você gasta respondendo "tem horário?" por dia?', 'adesivo' => 'quiz', 'opcoes' => ['10 min', '1 hora', 'O dia todo']]];
    return array_slice($base, 0, $n);
}

// Uma foto do banco de fotos, baixada para o servidor (a arte precisa da imagem no mesmo site)
function mkt_foto_banco($termo, $orientacao = 'landscape') {
    if (!fotos_configurado()) return null;
    $termo = fotos_termo_segmento(mb_strtolower(preg_replace('/\s+/', '-', trim($termo))), $termo, $termo);
    $pasta = img_ia_pasta('ia/marketing');
    if (!$pasta) return null;
    $fs = fotos_buscar($termo, 6, $orientacao);
    if (!$fs) return null;
    shuffle($fs);
    foreach (array_slice($fs, 0, 3) as $f) {
        $ch = curl_init($f['medio']); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_FOLLOWLOCATION => true]);
        $bytes = curl_exec($ch); $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200; curl_close($ch);
        if (!$ok || !$bytes) continue;
        fotos_registrar_uso($f);
        $salvo = img_ia_salvar($bytes, $pasta . '/banco-' . substr(md5($f['medio']), 0, 12), 1600);
        if ($salvo) return '/uploads/ia/marketing/' . basename($salvo);
    }
    return null;
}

// Processa o próximo item pendente (de um plano ou de qualquer um). Retorna o progresso.
function mkt_plano_processar($planoId = null) {
    global $pdo;
    $pdo->exec("UPDATE marketing_itens SET status='pendente' WHERE status='gerando' AND atualizado_em < NOW() - INTERVAL '4 minutes'");
    $sql = "SELECT * FROM marketing_itens WHERE status='pendente'" . ($planoId ? ' AND plano_id=' . (int)$planoId : '') . " ORDER BY data, hora, id LIMIT 1 FOR UPDATE SKIP LOCKED";
    $pdo->beginTransaction();
    $it = $pdo->query($sql)->fetch();
    if ($it) $pdo->prepare("UPDATE marketing_itens SET status='gerando', tentativas=tentativas+1, atualizado_em=NOW() WHERE id=?")->execute([$it['id']]);
    $pdo->commit();
    if ($it) {
        try { $r = mkt_item_gerar($it); } catch (Throwable $e) { $r = ['ok' => false, 'erro' => $e->getMessage()]; }
        if (!$r['ok']) {
            $final = empty($r['tentar_de_novo']) || (int)$it['tentativas'] + 1 >= 3;
            $pdo->prepare("UPDATE marketing_itens SET status=?, erro=?, atualizado_em=NOW() WHERE id=?")->execute([$final ? 'erro' : 'pendente', mb_substr((string)$r['erro'], 0, 300), $it['id']]);
        }
    }
    $pid = $planoId ?: ($it['plano_id'] ?? null);
    if (!$pid) return ['feitos' => 0, 'total' => 0, 'terminou' => true];
    $c = $pdo->query("SELECT COUNT(*) total, COUNT(*) FILTER (WHERE status IN ('pronto','postado','erro')) feitos, COUNT(*) FILTER (WHERE status='erro') erros FROM marketing_itens WHERE plano_id=" . (int)$pid)->fetch();
    if ((int)$c['feitos'] >= (int)$c['total']) $pdo->prepare("UPDATE marketing_planos SET status='pronto' WHERE id=?")->execute([$pid]);
    return ['feitos' => (int)$c['feitos'], 'total' => (int)$c['total'], 'erros' => (int)$c['erros'], 'terminou' => (int)$c['feitos'] >= (int)$c['total'], 'item' => $it ? (int)$it['id'] : null];
}

// Cron / de carona: continua os planos sozinho e manda os lembretes de postar
function mkt_rotina() {
    $res = [];
    try {
        $t0 = time();
        for ($i = 0; $i < 3 && time() - $t0 < 60; $i++) { $r = mkt_plano_processar(); if (empty($r['item'])) break; }
        $res['mkt_itens'] = $i;
        $res['mkt_lembretes'] = mkt_lembretes();
    } catch (Throwable $e) { $res['mkt'] = 'erro: ' . $e->getMessage(); }
    return $res;
}

// "Hora de postar": manda no seu WhatsApp a legenda pronta e o link do post (15 min antes)
function mkt_lembretes() {
    global $pdo;
    if (get_config('mkt_lembrete', '1') !== '1') return 0;
    $numero = get_config('mkt_whatsapp', '') ?: get_config('prosp_whatsapp_alerta', '');
    if ($numero === '' || !function_exists('wa_remetente')) return 0;
    $rem = wa_remetente(['wa_modo' => 'plataforma']);
    if (!$rem) return 0;
    $st = $pdo->query("SELECT * FROM marketing_itens WHERE status='pronto' AND lembrado_em IS NULL
        AND (data + hora::time) BETWEEN NOW() - INTERVAL '10 minutes' AND NOW() + INTERVAL '15 minutes' ORDER BY data, hora LIMIT 3");
    $n = 0;
    foreach ($st->fetchAll() as $it) {
        $pdo->prepare("UPDATE marketing_itens SET lembrado_em=NOW() WHERE id=?")->execute([$it['id']]);
        $p = json_decode((string)$it['post'], true) ?: [];
        $link = rtrim(get_config('plataforma_url', '') ?: (function_exists('prosp_url_base') ? prosp_url_base() : ''), '/') . '/admin/marketing.php?plano=' . (int)$it['plano_id'] . '#item-' . (int)$it['id'];
        $txt = "⏰ Hora de postar (" . (MKT_TIPOS[$it['tipo']] ?? $it['tipo']) . " das {$it['hora']}): " . mb_strimwidth((string)$it['tema'], 0, 80, '…') . "\n\n"
             . ($it['tipo'] === 'story' ? 'Stories: ' . implode(' | ', array_map(fn($s) => $s['texto'], (array)($p['stories'] ?? []))) : "Legenda pronta:\n" . ($p['legenda'] ?? '') . "\n\n" . implode(' ', array_map(fn($h) => '#' . $h, (array)($p['hashtags'] ?? []))))
             . "\n\nArte e vídeo: $link";
        try { if (wa_enviar_agora($rem, $numero, $txt)['ok'] ?? false) $n++; } catch (Throwable $e) { /* tenta no próximo */ }
    }
    return $n;
}

// ---------- Voz (narração dos vídeos) ----------
// Azure Speech: plano grátis F0 com vozes neurais em português do Brasil (500 mil caracteres/mês)
function mkt_vozes() {
    return ['pt-BR-FranciscaNeural' => 'Francisca (feminina, calorosa)', 'pt-BR-AntonioNeural' => 'Antônio (masculina, firme)',
            'pt-BR-ThalitaMultilingualNeural' => 'Thalita (feminina, jovem)', 'pt-BR-DonatoNeural' => 'Donato (masculina, calma)', 'pt-BR-BrendaNeural' => 'Brenda (feminina, animada)'];
}

function mkt_voz_configurada() {
    return get_config('azure_tts_key', '') !== '' && get_config('azure_tts_regiao', '') !== '';
}

function mkt_voz($texto, $voz = '') {
    if (!mkt_voz_configurada()) return ['ok' => false, 'erro' => 'Narração não configurada (Configurações → Voz dos vídeos).'];
    $texto = trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('*', '', (string)$texto))));
    if ($texto === '') return ['ok' => false, 'erro' => 'Sem texto para narrar.'];
    $texto = mb_substr($texto, 0, 1200);
    $voz = isset(mkt_vozes()[$voz]) ? $voz : (get_config('azure_tts_voz', '') ?: 'pt-BR-FranciscaNeural');
    $pasta = img_ia_pasta('ia/voz');
    if (!$pasta) return ['ok' => false, 'erro' => 'A pasta uploads/ não tem permissão de escrita.'];
    $arq = $pasta . '/' . md5($voz . $texto) . '.mp3';
    $url = '/uploads/ia/voz/' . basename($arq);
    if (is_file($arq) && filesize($arq) > 1000) return ['ok' => true, 'url' => $url];
    $regiao = preg_replace('/[^a-z0-9]/', '', strtolower(get_config('azure_tts_regiao', 'brazilsouth')));
    $base = getenv('GDS_AZURE_TTS_URL') ?: "https://$regiao.tts.speech.microsoft.com";
    $ssml = "<speak version='1.0' xml:lang='pt-BR'><voice name='$voz'><prosody rate='+6%'>" . htmlspecialchars($texto, ENT_XML1) . '</prosody></voice></speak>';
    $ch = curl_init(rtrim($base, '/') . '/cognitiveservices/v1');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 40, CURLOPT_POSTFIELDS => $ssml,
        CURLOPT_HTTPHEADER => ['Ocp-Apim-Subscription-Key: ' . get_config('azure_tts_key', ''), 'Content-Type: application/ssml+xml',
            'X-Microsoft-OutputFormat: audio-24khz-48kbitrate-mono-mp3', 'User-Agent: GDS']]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false || $code !== 200 || strlen($resp) < 1000) {
        return ['ok' => false, 'erro' => $code === 401 ? 'Chave da Azure recusada (confira a chave e a região).' : ($code === 429 ? 'Acabou a cota grátis de voz deste mês.' : 'A Azure não gerou a voz agora (código ' . $code . ').')];
    }
    file_put_contents($arq, $resp);
    return ['ok' => true, 'url' => $url];
}

// Situação das IAs do marketing (para o assistente "Ligar IAs grátis")
function mkt_ias_status() {
    $cad = img_ia_cadeia();
    $fotoBoa = in_array('cloudflare', $cad, true) || get_config('img_pollinations_key', '') !== '' || in_array('openai', $cad, true);
    return [
        'texto' => ['ok' => ia_configurada(), 'nome' => ia_configurada() ? ia_nome_provedor(get_config('ia_provider', 'gemini')) : ''],
        'foto' => ['ok' => $fotoBoa, 'nome' => implode(' → ', array_map('img_ia_nome', $cad))],
        'banco' => ['ok' => fotos_configurado(), 'nome' => implode(', ', array_map('ucfirst', fotos_fontes()))],
        'voz' => ['ok' => mkt_voz_configurada(), 'nome' => mkt_voz_configurada() ? (mkt_vozes()[get_config('azure_tts_voz', '')] ?? 'Francisca') : ''],
        'whats' => ['ok' => function_exists('wa_plataforma_disponivel') && wa_plataforma_disponivel() && (get_config('mkt_whatsapp', '') . get_config('prosp_whatsapp_alerta', '')) !== ''],
    ];
}
