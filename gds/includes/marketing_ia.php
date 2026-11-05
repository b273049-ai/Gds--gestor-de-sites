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
require_once __DIR__ . '/redes_sociais.php';

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

// Cada post tem UMA meta: viralizar (alcançar quem não te segue) ou trazer cliente (comentário → direct)
function mkt_meta_do($tipo, $objetivo) {
    if ($tipo === 'story') return 'lead';
    return in_array($objetivo, ['oferta', 'prova', 'objecao', 'antes_depois'], true) ? 'lead' : 'viralizar';
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
    $ins = $pdo->prepare("INSERT INTO marketing_itens (plano_id, data, hora, tipo, objetivo, framework, tema, gancho, meta, publicar) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $auto = !empty($opts['postar_sozinho']) && soc_redes_ativas();
    foreach ($slots as $i => $s) {
        $t = $temas[$i] ?? [];
        $obj = mkt_objetivos_semana($s['semana']);
        $objetivo = $t['objetivo'] ?? $obj[$i % 3];
        $meta = in_array($t['meta'] ?? '', ['viralizar', 'lead'], true) && $s['tipo'] !== 'story' ? $t['meta'] : mkt_meta_do($s['tipo'], $objetivo);
        $ins->execute([$planoId, $s['data'], mkt_horario($s['tipo'], $s['dow']), $s['tipo'], $objetivo, $t['framework'] ?? '',
            mb_substr((string)($t['tema'] ?? ''), 0, 300), mb_substr((string)($t['gancho'] ?? ''), 0, 300), $meta, $auto ? 'true' : 'false']);
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
                . "Cada post tem UMA meta: \"viralizar\" (tema que o dono de negócio quer MANDAR para outro dono: dor do dia a dia, erro comum, curiosidade; ~60% dos posts) "
                . "ou \"lead\" (traz cliente: prova, antes e depois, oferta, quebra de objeção, terminando com \"comente PALAVRA que eu te mando…\"; ~40%). "
                . "Ganchos que param o dedo: contraste (\"pare de…\"), número específico, erro comum, pergunta que dói, promessa concreta. Nada genérico como \"Você sabia?\". "
                . soc_aprendizado()
                . "\nResponda APENAS JSON: {\"itens\":[{\"tema\":\"...\",\"gancho\":\"primeira frase\",\"meta\":\"viralizar|lead\",\"objetivo\":\"dor|antes_depois|recursos|prova|oferta|educativo|bastidores|objecao\",\"framework\":\"aida|pas|bab|4u|historia|mito|lista\"}]}\n\nCALENDÁRIO:\n$lista";
        $r = chamar_ia_json($prompt, 6000, 90, 0.9, ['esforco' => 'low']);
        if ($r['ok'] && !empty($r['dados']['itens'])) return array_values((array)$r['dados']['itens']);
    }
    // Sem IA: temas do banco, girando
    $banco = mkt_semana_sem_ia($publico);
    return array_map(fn($s, $i) => $banco[$i % count($banco)], $slots, array_keys($slots));
}

// Gera o conteúdo completo de UM item (texto + foto). Retorna ['ok'=>bool,'erro'=>...]
function mkt_item_gerar($item, $semIa = false) {
    global $pdo;
    $plano = $pdo->prepare("SELECT * FROM marketing_planos WHERE id=?"); $plano->execute([$item['plano_id']]); $plano = $plano->fetch();
    $marca = get_config('mkt_marca', 'GDS Sites');
    $preco = get_config('mkt_preco', '');
    $publico = $plano['publico'] ?: 'pequenos negócios locais';
    $tipo = $item['tipo'];
    $nStories = max(1, min(5, (int)$plano['stories_dia']));
    $post = null;
    if (ia_configurada() && !$semIa) {
        $regrasTipo = [
            'reels' => 'É um REELS (vídeo vertical 9:16). O gancho aparece escrito na tela em até 2 segundos. Roteiro de 5 a 7 cenas curtas (15 a 30 s no total), uma ideia por cena, final pedindo para SALVAR ou MANDAR para um amigo dono de negócio. "narracao" é o texto falado inteiro, natural, 40 a 70 palavras.',
            'carrossel' => 'É um CARROSSEL. "conteudo.titulo" é a capa (gancho que faz arrastar). "conteudo.itens" são 5 a 7 slides, cada um com UMA ideia em até 12 palavras. O último slide é a chamada (vai em "cta").',
            'feed' => 'É um POST de imagem única. Título forte e curto, subtítulo que completa a ideia.',
            'story' => 'É uma SEQUÊNCIA DE STORIES (conversa e bastidor). Capriche nos "stories": cada um com texto curto e um adesivo que gera resposta.',
        ][$tipo];
        $meta = $item['meta'] ?: mkt_meta_do($tipo, $item['objetivo']);
        $palavra = mb_strtoupper(get_config('mkt_palavra', 'QUERO') ?: 'QUERO');
        $isca = get_config('mkt_isca', '') ?: 'a prévia grátis do site do seu negócio';
        $regraMeta = $meta === 'lead'
            ? "META: TRAZER CLIENTE. Termine com UMA chamada só: \"Comente PALAVRA que eu te mando $isca\". A PALAVRA (\"palavra_chave\") é 1 palavra curta em maiúsculas ligada ao tema (ex.: SITE, AGENDA, PRÉVIA, $palavra) e aparece no \"cta\" da arte, no fim do vídeo e na legenda. Quem comentar recebe sozinho no direct o \"dm_texto\": a entrega da isca em até 300 letras, sem link, terminando com 1 pergunta (qual é o seu negócio?). "
            : "META: VIRALIZAR (chegar em quem não te segue). O Instagram entrega para não seguidores o que é MANDADO por mensagem e assistido até o fim. Faça um conteúdo identificável que um dono de negócio quer mandar para outro, sem cara de anúncio, e termine com UMA chamada: \"Manda pra aquele amigo que tem [negócio]\" ou \"Salva pra não esquecer\". Mesmo assim, ponha \"palavra_chave\" e \"dm_texto\" (quem comentar a palavra recebe a isca: $isca). ";
        $prompt = "Você é social media e copywriter de resposta direta que vende para pequenos negócios brasileiros. Crie o conteúdo para a marca \"$marca\" "
                . "(sites com IA para negócios locais: agendamento online, confirmação e lembrete no WhatsApp, orçamento, delivery, domínio próprio). " . ($preco ? "Preço/condição: $preco. " : '')
                . "\nPÚBLICO: $publico\nTEMA: {$item['tema']}\nGANCHO SUGERIDO: {$item['gancho']}\nOBJETIVO: {$item['objetivo']}" . ($item['framework'] ? "\nESTRUTURA DA COPY: {$item['framework']}" : '')
                . "\n$regrasTipo\n$regraMeta\n"
                . "Regras: português do Brasil, simples e direto, sem números inventados nem promessas exageradas. Título com no máximo 9 palavras e *asteriscos* em 1 a 3 palavras de destaque. "
                . "Legenda: a 1ª linha (até 125 letras) é o gancho; 3 a 6 linhas curtas; sem link (não é clicável); use naturalmente 2 ou 3 termos que o cliente PESQUISA (ex.: \"site para barbearia\", \"agendamento online\") — vão em \"busca\". 3 a 5 hashtags certeiras (nicho + local), sem #. "
                . "\"legenda_tiktok\": versão para o TikTok, até 150 letras, tom mais solto, com 1 termo de busca. "
                . ($tipo === 'reels' ? "Reels: a 1ª cena tem o gancho ESCRITO na tela; a última frase conecta com a primeira (o vídeo dá a volta e a pessoa assiste de novo). " : '')
                . ($tipo === 'story' && $meta === 'lead' ? "Um dos stories convida: \"Responde PALAVRA aqui que eu te mando…\" (quem responder recebe o direct sozinho). " : '')
                . ""
                . "\"cena_foto\": EM INGLÊS, foto realista e vertical de uma pessoa real do público no ambiente do negócio, rosto visível, luz natural, espaço livre no topo para o título, sem texto na imagem. "
                . "\"prompt_video\": EM INGLÊS, como animar essa foto num gerador de vídeo (movimento de câmera sutil + ação da pessoa, 5 segundos). "
                . ($tipo === 'story' ? "\"stories\": $nStories stories para postar no dia, cada um {\"texto\":\"até 14 palavras\",\"adesivo\":\"enquete|pergunta|quiz|contagem|link|nenhum\",\"opcoes\":[\"para enquete/quiz\"]}. " : "\"stories\": []. ")
                . "Modelo de arte (VARIE): impacto | lista | antes_depois | oferta | foto_titulo | foto_split | pergunta | numero. Estilo: neon | editorial | bold | pop | minimal | luxo.\n"
                . 'Responda APENAS JSON: {"modelo":"","estilo":"","cena_foto":"","prompt_video":"","conteudo":{"selo":"até 5 palavras","titulo":"","subtitulo":"","itens":[],"antes":[],"depois":[],"preco":"","cta":"até 4 palavras"},'
                . '"legenda":"","legenda_tiktok":"","hashtags":[],"busca":[],"palavra_chave":"","dm_texto":"","roteiro":[{"tempo":"0-3s","texto_tela":"","fala":""}],"narracao":"","stories":[]}';
        $r = chamar_ia_json($prompt, 2500, 75, 0.9, ['esforco' => 'low']);
        if ($r['ok']) $post = $r['dados'];
        elseif (mkt_erro_cota($r)) return ['ok' => false, 'erro' => $r['erro'], 'cota' => true];
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
    $meta = $item['meta'] ?: mkt_meta_do($tipo, $item['objetivo']);
    $post['meta'] = $meta;
    $post['palavra_chave'] = mb_strtoupper(preg_replace('/[^\p{L}\p{N}]/u', '', (string)($post['palavra_chave'] ?? ''))) ?: mb_strtoupper(get_config('mkt_palavra', 'QUERO') ?: 'QUERO');
    $post['dm_texto'] = mb_substr(trim((string)($post['dm_texto'] ?? '')), 0, 400);
    if ($meta === 'lead' && $tipo !== 'story' && mb_stripos((string)($post['legenda'] ?? ''), $post['palavra_chave']) === false) {
        $post['legenda'] = rtrim((string)($post['legenda'] ?? '')) . "\n\n👇 Comente " . $post['palavra_chave'] . ' que eu te mando ' . (get_config('mkt_isca', '') ?: 'a prévia grátis do site do seu negócio') . '.';
    }
    if (!in_array($post['modelo'] ?? '', ['impacto', 'lista', 'antes_depois', 'depoimento', 'oferta', 'foto_titulo', 'foto_split', 'pergunta', 'numero'], true)) $post['modelo'] = 'foto_titulo';
    if ($tipo === 'carrossel' && empty($post['conteudo']['itens'])) $post['conteudo']['itens'] = array_map(fn($c) => $c['texto_tela'] ?? '', array_slice((array)($post['roteiro'] ?? []), 1, 5));
    if (!in_array($post['estilo'] ?? '', ['neon', 'editorial', 'bold', 'pop', 'minimal', 'luxo'], true)) $post['estilo'] = ['neon', 'editorial', 'bold', 'pop', 'minimal', 'luxo'][random_int(0, 5)];
    $post['stories'] = $tipo === 'story' ? array_slice(array_values(array_filter((array)($post['stories'] ?? []), fn($s) => is_array($s) && !empty($s['texto']))), 0, 5) : [];
    if ($tipo === 'story' && !$post['stories']) $post['stories'] = mkt_stories_sem_ia($item, $nStories);
    $post['hashtags'] = array_slice(array_values(array_filter(array_map(fn($h) => preg_replace('/[#\s]+/u', '', (string)$h), (array)($post['hashtags'] ?? [])))), 0, 5);
    $post['busca'] = array_slice(array_values(array_filter(array_map('strval', (array)($post['busca'] ?? [])))), 0, 4);
    $nota = mkt_nota_post($tipo, $meta, $post);
    $post['nota'] = $nota['nota'];
    $post['nota_dicas'] = $nota['dicas'];
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

// Nota antes de postar: confere o que mais pesa para viralizar ou converter
function mkt_nota_post($tipo, $meta, $p) {
    $pts = 100; $dicas = [];
    $menos = function ($n, $dica) use (&$pts, &$dicas) { $pts -= $n; $dicas[] = $dica; };
    $c = (array)($p['conteudo'] ?? []);
    if ($tipo === 'story') {
        $st = (array)($p['stories'] ?? []);
        if (!array_filter($st, fn($s) => in_array($s['adesivo'] ?? '', ['enquete', 'pergunta', 'quiz'], true))) $menos(20, 'Coloque 1 adesivo de enquete, pergunta ou quiz: resposta nos stories faz o Instagram mostrar mais.');
        if ($meta === 'lead' && !array_filter($st, fn($s) => mb_stripos((string)($s['texto'] ?? ''), (string)($p['palavra_chave'] ?? '#')) !== false)) $menos(10, 'Convide a responder a palavra-chave no direct (quem responde recebe a oferta sozinho).');
        return ['nota' => max(0, $pts), 'dicas' => $dicas];
    }
    $tit = trim(str_replace('*', '', (string)($c['titulo'] ?? '')));
    if (count(preg_split('/\s+/u', $tit, -1, PREG_SPLIT_NO_EMPTY)) > 10) $menos(15, 'Gancho da capa com mais de 10 palavras: corte para parar o dedo.');
    $leg = trim((string)($p['legenda'] ?? ''));
    $l1 = trim(strtok($leg, "\n") ?: '');
    if (mb_strlen($l1) > 125) $menos(10, 'A 1ª linha da legenda passa de 125 letras e some no "… mais".');
    if (preg_match('#https?://|www\.#i', $leg)) $menos(10, 'Link na legenda não é clicável: use a palavra-chave no comentário.');
    $nh = count((array)($p['hashtags'] ?? []));
    if ($nh < 3 || $nh > 5) $menos(5, 'Use de 3 a 5 hashtags certeiras (nicho + cidade).');
    if (empty($p['busca'])) $menos(5, 'Coloque na legenda o que o cliente pesquisa (ex.: "site para barbearia"): o Instagram também é buscador.');
    if ($meta === 'lead' && mb_stripos($leg, (string)($p['palavra_chave'] ?? '#')) === false) $menos(20, 'Falta o "Comente PALAVRA" na legenda: é ele que transforma comentário em cliente.');
    if ($meta === 'viralizar' && !preg_match('/mand|envi|compartilh|salv|marc/iu', $leg . ' ' . ($c['cta'] ?? ''))) $menos(15, 'Peça para MANDAR para um amigo ou SALVAR: envios levam o post a quem não te segue.');
    if ($tipo === 'reels') {
        if (count((array)($p['roteiro'] ?? [])) < 3) $menos(10, 'Reels com poucas cenas: troque de cena a cada 3–5 segundos.');
        if (trim((string)($p['roteiro'][0]['texto_tela'] ?? '')) === '') $menos(10, 'Escreva o gancho na tela na 1ª cena (muita gente assiste sem som).');
        if (mb_strlen((string)($p['narracao'] ?? '')) < 40) $menos(5, 'Sem narração: vídeo com voz segura mais.');
    }
    if ($tipo === 'carrossel' && count((array)($c['itens'] ?? [])) < 4) $menos(10, 'Carrossel com poucos slides: 5 a 8 seguram mais (cada arrastada conta).');
    return ['nota' => max(0, $pts), 'dicas' => $dicas];
}

function mkt_stories_sem_ia($item, $n) {
    $base = [['texto' => 'Bom dia! Hoje é dia de ' . mb_strtolower(MKT_TIPOS[$item['tipo']] ?? 'novidade') . ' por aqui ☀️', 'adesivo' => 'nenhum'],
             ['texto' => 'Seu cliente consegue marcar com você às 22h de domingo?', 'adesivo' => 'enquete', 'opcoes' => ['Consegue', 'Ainda não']],
             ['texto' => 'Qual a sua maior dor no atendimento?', 'adesivo' => 'pergunta'],
             ['texto' => 'Responde ' . mb_strtoupper(get_config('mkt_palavra', 'QUERO') ?: 'QUERO') . ' aqui que eu te mando a prévia grátis do seu site 📩', 'adesivo' => 'nenhum'],
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

// Cota da IA grátis (por minuto/dia) ou IA sobrecarregada: vale esperar, não é defeito do post
function mkt_erro_cota($r) {
    $e = mb_strtolower((string)($r['erro'] ?? ''));
    return !empty($r['temporario']) && (str_contains($e, 'limite de uso') || str_contains($e, 'sobrecarregad') || str_contains($e, 'quota') || str_contains($e, 'rate'));
}

// Processa o próximo item pendente (de um plano ou de qualquer um). Retorna o progresso.
// Se a IA grátis bater o limite, pausa um pouco e continua; depois de várias pausas seguidas
// (cota do dia acabou), termina o plano com o banco de posts prontos para não ficar pela metade.
function mkt_plano_processar($planoId = null) {
    global $pdo;
    $falta = (int)get_config('mkt_pausa_ate', '0') - time();
    if ($falta > 0) {
        $c = $planoId ? $pdo->query("SELECT COUNT(*) total, COUNT(*) FILTER (WHERE status IN ('pronto','postado','erro')) feitos, COUNT(*) FILTER (WHERE status='erro') erros FROM marketing_itens WHERE plano_id=" . (int)$planoId)->fetch() : ['total' => 0, 'feitos' => 0, 'erros' => 0];
        return ['feitos' => (int)$c['feitos'], 'total' => (int)$c['total'], 'erros' => (int)$c['erros'], 'terminou' => false, 'item' => null, 'pausa' => $falta];
    }
    $pdo->exec("UPDATE marketing_itens SET status='pendente' WHERE status='gerando' AND atualizado_em < NOW() - INTERVAL '4 minutes'");
    $sql = "SELECT * FROM marketing_itens WHERE status='pendente'" . ($planoId ? ' AND plano_id=' . (int)$planoId : '') . " ORDER BY data, hora, id LIMIT 1 FOR UPDATE SKIP LOCKED";
    $pdo->beginTransaction();
    $it = $pdo->query($sql)->fetch();
    if ($it) $pdo->prepare("UPDATE marketing_itens SET status='gerando', tentativas=tentativas+1, atualizado_em=NOW() WHERE id=?")->execute([$it['id']]);
    $pdo->commit();
    if ($it) {
        $semIa = (int)get_config('mkt_pausas', '0') >= 5;
        try { $r = mkt_item_gerar($it, $semIa); } catch (Throwable $e) { $r = ['ok' => false, 'erro' => $e->getMessage()]; }
        if ($r['ok']) { if (!$semIa) set_config('mkt_pausas', '0'); }
        elseif (!empty($r['cota'])) {
            // Volta para a fila sem gastar tentativa e espera a cota liberar (60 s, 90 s, 2 min…)
            $n = (int)get_config('mkt_pausas', '0') + 1;
            set_config('mkt_pausas', (string)$n);
            set_config('mkt_pausa_ate', (string)(time() + min(180, 45 + 15 * $n)));
            $pdo->prepare("UPDATE marketing_itens SET status='pendente', tentativas=GREATEST(tentativas-1,0), erro=?, atualizado_em=NOW() WHERE id=?")->execute([mb_substr((string)$r['erro'], 0, 300), $it['id']]);
            $r['ok'] = true; // não é erro final
        }
        if (!$r['ok']) {
            $final = empty($r['tentar_de_novo']) || (int)$it['tentativas'] + 1 >= 3;
            $pdo->prepare("UPDATE marketing_itens SET status=?, erro=?, atualizado_em=NOW() WHERE id=?")->execute([$final ? 'erro' : 'pendente', mb_substr((string)$r['erro'], 0, 300), $it['id']]);
        }
    }
    $pid = $planoId ?: ($it['plano_id'] ?? null);
    if (!$pid) return ['feitos' => 0, 'total' => 0, 'terminou' => true];
    $c = $pdo->query("SELECT COUNT(*) total, COUNT(*) FILTER (WHERE status IN ('pronto','postado','erro')) feitos, COUNT(*) FILTER (WHERE status='erro') erros FROM marketing_itens WHERE plano_id=" . (int)$pid)->fetch();
    if ((int)$c['feitos'] >= (int)$c['total']) { $pdo->prepare("UPDATE marketing_planos SET status='pronto' WHERE id=?")->execute([$pid]); set_config('mkt_pausas', '0'); }
    $pausa = max(0, (int)get_config('mkt_pausa_ate', '0') - time());
    return ['pausa' => $pausa, 'sem_ia' => (int)get_config('mkt_pausas', '0') >= 5, 'feitos' => (int)$c['feitos'], 'total' => (int)$c['total'], 'erros' => (int)$c['erros'], 'terminou' => (int)$c['feitos'] >= (int)$c['total'], 'item' => $it ? (int)$it['id'] : null];
}

// Cron / de carona: continua os planos sozinho e manda os lembretes de postar
function mkt_rotina() {
    $res = [];
    try {
        $t0 = time();
        for ($i = 0; $i < 3 && time() - $t0 < 60; $i++) { $r = mkt_plano_processar(); if (empty($r['item'])) break; }
        $res['mkt_itens'] = $i;
        $res['mkt_lembretes'] = mkt_lembretes();
        $res += soc_rotina();
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
    // Os que vão sair sozinhos não precisam de lembrete (só os stories, por causa dos adesivos)
    $st = $pdo->query("SELECT * FROM marketing_itens WHERE status='pronto' AND lembrado_em IS NULL
        AND NOT (COALESCE(publicar, false) AND midia IS NOT NULL AND tipo <> 'story')
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
