<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');
require_once __DIR__ . '/../includes/marketing_banco.php';

// Ideias de conteúdo que vendem sites para negócios locais
$objetivos = [
    'dor'          => 'Dor do empreendedor (perde cliente por não ter site / responder WhatsApp o dia todo)',
    'antes_depois' => 'Antes e depois: negócio sem site × com site GDS',
    'recursos'     => 'Mostrar recursos: agendamento, WhatsApp automático, orçamento, delivery, domínio',
    'prova'        => 'Prova social / resultado de cliente',
    'oferta'       => 'Oferta / condição especial para fechar agora',
    'educativo'    => 'Dica educativa (Google, Instagram x site, confiança)',
    'bastidores'   => 'Bastidores: site sendo criado com IA em minutos',
    'objecao'      => 'Quebrar objeção ("é caro", "não preciso", "Instagram basta")',
    'dados'        => 'Dados reais da sua base (números verdadeiros da prospecção)',
];

// Modelos de copy consagrados (a IA escreve seguindo a estrutura escolhida)
$frameworks = [
    'auto'      => ['A IA escolhe o melhor', ''],
    'aida'      => ['AIDA — Atenção, Interesse, Desejo, Ação', 'AIDA: 1ª linha prende a atenção, depois desperta interesse com um fato do dia a dia do público, cria desejo mostrando o resultado e termina com uma ação clara.'],
    'pas'       => ['PAS — Problema, Agitação, Solução', 'PAS: nomeie o problema do público, agite (o que ele perde por causa disso, sem exagero) e apresente a solução.'],
    'bab'       => ['Antes → Depois → Ponte', 'Antes-Depois-Ponte: como é a rotina hoje (antes), como fica com a solução (depois) e como chegar lá (ponte = a plataforma).'],
    '4u'        => ['4U — Útil, Urgente, Único, Específico', '4U: título útil, com urgência real (sem inventar prazo), único e bem específico para o público.'],
    'historia'  => ['História curta de um dono de negócio', 'Storytelling: uma mini-história de um dono de negócio do público (deixe claro que é um exemplo), com virada e moral.'],
    'mito'      => ['Mito × Verdade', 'Mito x verdade: derrube uma crença comum do público sobre ter site/agendamento online, com argumento simples.'],
    'lista'     => ['Lista rápida (3 a 5 dicas)', 'Lista: 3 a 5 dicas curtas e práticas que o público pode usar hoje; a plataforma aparece como a forma mais fácil.'],
];
$redes = ['instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'whatsapp_status' => 'Status do WhatsApp', 'google' => 'Perfil no Google'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    @set_time_limit(120);
    $marca = get_config('mkt_marca', 'GDS Sites');
    switch ($_POST['acao']) {
        case 'salvar_marca':
            foreach (['mkt_marca' => 60, 'mkt_arroba' => 40, 'mkt_site' => 80, 'mkt_preco' => 40, 'mkt_whatsapp' => 30] as $k => $max) set_config($k, mb_substr(trim($_POST[$k] ?? ''), 0, $max));
            set_config('mkt_cor', cor_hex_valida($_POST['mkt_cor'] ?? '', '#6d7cff'));
            set_config('mkt_cor2', cor_hex_valida($_POST['mkt_cor2'] ?? '', '#22d3ee'));
            set_config('mkt_escuro', ($_POST['mkt_escuro'] ?? '1') === '1' ? '1' : '0');
            responder_json(['ok' => true]);
        case 'gerar_post':
            $chaveObj = (string)($_POST['objetivo'] ?? '');
            $obj = $objetivos[$chaveObj] ?? trim($_POST['objetivo'] ?? 'Divulgar a plataforma');
            $publico = trim($_POST['publico'] ?? '') ?: 'pequenos negócios locais em geral';
            $formato = in_array($_POST['formato'] ?? '', ['feed', 'story', 'quadrado'], true) ? $_POST['formato'] : 'feed';
            $extra = mb_substr(trim($_POST['extra'] ?? ''), 0, 400);
            $fw = $frameworks[$_POST['framework'] ?? 'auto'] ?? $frameworks['auto'];
            $redesPost = array_values(array_intersect(explode(',', (string)($_POST['redes'] ?? '')), array_keys($redes)));
            $adaptar = array_values(array_diff($redesPost, ['instagram', 'tiktok']));
            $preco = get_config('mkt_preco', '');
            $dadosReais = $chaveObj === 'dados' ? mkt_dados_reais(trim($_POST['publico'] ?? '')) : null;
            if ($chaveObj === 'dados' && !$dadosReais) responder_json(['ok' => false, 'erro' => 'Ainda não há dados suficientes desse público na Prospecção (precisa de pelo menos 8 empresas numa cidade). Rode uma caçada ou deixe o público em branco.']);
            // Sem chave de IA: banco de posts prontos (ou o post com os números reais)
            if (!ia_configurada() || !empty($_POST['sem_ia'])) {
                $d = $dadosReais ? mkt_post_dados($dadosReais, $marca) : mkt_post_sem_ia($chaveObj, trim($_POST['publico'] ?? ''), $marca, $preco);
                $pdo->prepare("INSERT INTO marketing_posts (rede, formato, tema, conteudo) VALUES (?,?,?,?)")
                    ->execute(['instagram/tiktok', $formato, mb_substr($obj, 0, 150), json_encode($d, JSON_UNESCAPED_UNICODE)]);
                responder_json(['ok' => true, 'post' => $d, 'formato' => $formato, 'origem' => $d['origem']]);
            }
            if ($dadosReais) $extra = trim($extra . ' DADO REAL (use exatamente estes números, sem arredondar nem inventar outros): ' . $dadosReais['frase']);
            $prompt = <<<PROMPT
Você é um social media e copywriter de resposta direta especialista em vender serviços para pequenos negócios brasileiros.
Crie UM post para Instagram/TikTok que venda a plataforma "$marca": sites profissionais para negócios locais, criados
com IA, com agendamento online, confirmação e lembrete automáticos no WhatsApp, pedido de orçamento com valor pelo
WhatsApp, delivery com carrinho, domínio próprio e painel simples. Preço/condição (use só se fizer sentido): "$preco".

OBJETIVO DO POST: $obj
PÚBLICO: $publico
FORMATO DA ARTE: $formato
OBSERVAÇÕES DO DONO: "$extra"
MODELO DE COPY: {$fw[1]}

Regras: português do Brasil, linguagem simples e direta, sem promessas exageradas ou números inventados (nada de
"aumente 300%"). Use *asteriscos* em 1 a 3 palavras do título para destacá-las na arte. Título com no máximo 9 palavras.
Escolha o "modelo" de arte que mais combina (VARIE — não use sempre o mesmo): impacto | lista | antes_depois |
depoimento | oferta | foto_titulo (foto grande + título) | foto_split (foto + bloco de texto) | pergunta (gancho em
forma de pergunta) | numero (um número/palavra gigante em "preco", ex.: "24h", "0 faltas", "1 min")
(depoimento: deixe claro em "autor" que é um exemplo para trocar por um depoimento real).
Em "cena_foto" descreva EM INGLÊS uma foto realista que combina com o post (pessoa real do público no ambiente do
negócio, sem texto na imagem), e em "estilo" escolha: neon | editorial | bold | pop | minimal | luxo.

Responda APENAS com JSON válido:
{"modelo":"impacto","estilo":"neon","cena_foto":"...","conteudo":{"selo":"até 5 palavras","titulo":"...","subtitulo":"até 18 palavras","itens":["até 6 itens curtos (lista)"],
"antes":["3 itens"],"depois":["3 itens"],"citacao":"","autor":"","preco":"","cta":"até 4 palavras"},
"legenda":"legenda pronta com gancho na 1ª linha, 3-6 linhas curtas, emojis com moderação e chamada para o direct/link",
"hashtags":["8 a 15 hashtags relevantes, sem #"],
"roteiro":[{"tempo":"0-3s","texto_tela":"texto curto que aparece na tela","fala":"o que falar/narrar"}],
"dica_gravacao":"1 dica prática para gravar o Reels/TikTok com o celular"}
O roteiro deve ter 4 a 6 cenas e durar de 15 a 30 segundos, com gancho forte nos 3 primeiros segundos.
PROMPT;
            // Versões para teste A/B e adaptação para as outras redes escolhidas
            $prompt .= "\nInclua também no JSON: \"variacoes\": [2 versões alternativas, cada uma {\"titulo\",\"subtitulo\",\"cta\"} com ganchos de outro tipo (pergunta, número, contraste)]";
            if ($adaptar) $prompt .= " e \"adaptacoes\": {" . implode(', ', array_map(fn($r) => '"' . $r . '": "' . ['facebook' => 'texto para Facebook (pode ser um pouco mais longo, tom de conversa)', 'linkedin' => 'texto para LinkedIn (tom profissional, foco no dono do negócio, sem hashtags em excesso)', 'whatsapp_status' => 'até 2 linhas curtíssimas para o Status do WhatsApp com chamada para responder', 'google' => 'post para o Perfil da Empresa no Google (até 300 caracteres, sem hashtags, com chamada)'][$r] . '"', $adaptar)) . '}';
            $prompt .= '.';
            $r = chamar_ia_json($prompt, 2000, 60, 0.9, ['esforco' => 'low']);
            if (!$r['ok']) responder_json(['ok' => false, 'erro' => $r['erro']]);
            $d = $r['dados'];
            $d['variacoes'] = array_slice(array_values(array_filter((array)($d['variacoes'] ?? []), fn($v) => is_array($v) && !empty($v['titulo']))), 0, 3);
            $d['adaptacoes'] = array_intersect_key((array)($d['adaptacoes'] ?? []), array_flip($adaptar));
            if (!in_array($d['modelo'] ?? '', ['impacto', 'lista', 'antes_depois', 'depoimento', 'oferta', 'foto_titulo', 'foto_split', 'pergunta', 'numero'], true)) $d['modelo'] = 'impacto';
            if (!in_array($d['estilo'] ?? '', ['neon', 'editorial', 'bold', 'pop', 'minimal', 'luxo'], true)) $d['estilo'] = ['neon', 'editorial', 'bold', 'pop', 'minimal', 'luxo'][random_int(0, 5)];
            $pdo->prepare("INSERT INTO marketing_posts (rede, formato, tema, conteudo) VALUES (?,?,?,?)")
                ->execute(['instagram/tiktok', $formato, mb_substr($obj, 0, 150), json_encode($d, JSON_UNESCAPED_UNICODE)]);
            responder_json(['ok' => true, 'post' => $d, 'formato' => $formato]);
        case 'gerar_semana':
            $mes = ($_POST['periodo'] ?? '') === 'mes';
            $porSemana = in_array((int)($_POST['por_semana'] ?? 5), [3, 5, 7], true) ? (int)$_POST['por_semana'] : 5;
            $total = $mes ? $porSemana * 4 : min(7, $porSemana);
            if (!ia_configurada()) {
                $dias = [];
                for ($i = 0; count($dias) < $total && $i < 6; $i++) $dias = array_merge($dias, mkt_semana_sem_ia(trim($_POST['publico'] ?? '')));
                $dias = array_slice($dias, 0, $total);
                if ($mes) foreach ($dias as $i => &$d) $d['dia'] = 'Semana ' . (intdiv($i, $porSemana) + 1) . ' · ' . ($d['dia'] ?? ''); unset($d);
                responder_json(['ok' => true, 'dias' => $dias, 'origem' => 'banco']);
            }
            $publico = trim($_POST['publico'] ?? '') ?: 'pequenos negócios locais';
            $prompt = "Você é estrategista de conteúdo. Monte o calendário de " . ($mes ? "4 semanas ($porSemana posts por semana, $total no total)" : "$total posts da semana") . " (Instagram, TikTok e Status do WhatsApp) para vender a plataforma \"$marca\" "
                    . "(sites com IA para negócios locais, agendamento, WhatsApp automático, orçamento, delivery, domínio próprio) para o público: $publico. "
                    . "Use pilares fixos e alterne: 40% educativo/dica útil, 25% prova e bastidores, 20% dor e quebra de objeção, 15% oferta. "
                    . ($mes ? "Crie uma progressão: semana 1 atrai, semana 2 educa, semana 3 prova, semana 4 converte (com oferta no fim). " : '')
                    . "Cada gancho deve ser específico e diferente (pergunta, número, contraste, erro comum, história). Responda APENAS JSON: "
                    . '{"dias":[{"dia":"' . ($mes ? 'Semana 1 · Segunda' : 'Segunda') . '","formato":"Reels|Carrossel|Story|Feed","tema":"...","gancho":"primeira frase do post","objetivo":"dor|antes_depois|recursos|prova|oferta|educativo|bastidores|objecao","framework":"aida|pas|bab|4u|historia|mito|lista"}]}';
            $r = chamar_ia_json($prompt, $mes ? 4000 : 1500, 90, 0.9, ['esforco' => 'low']);
            if (!$r['ok']) responder_json(['ok' => false, 'erro' => $r['erro']]);
            responder_json(['ok' => true, 'dias' => array_slice((array)($r['dados']['dias'] ?? []), 0, $total)]);
        case 'foto_ia':
            // Foto exclusiva para a arte (gerada por IA e guardada em uploads/ia/marketing)
            session_write_close();
            if (!img_ia_disponivel()) responder_json(['ok' => false, 'erro' => 'A geração de fotos está desligada em Configurações → Fotos exclusivas geradas por IA.']);
            $cena = mb_substr(trim($_POST['cena'] ?? ''), 0, 300) ?: 'small business owner smiling while checking appointments on a smartphone in a modern local shop';
            $fmt = ($_POST['formato'] ?? '') === 'story' ? '9:16' : (($_POST['formato'] ?? '') === 'quadrado' ? '1:1' : '4:5');
            $r = img_ia_gerar(img_ia_prompt_foto($cena), $fmt, 'ia/marketing');
            responder_json($r['ok'] ? ['ok' => true, 'url' => $r['url'], 'provedor' => img_ia_nome($r['provedor'])] : ['ok' => false, 'erro' => $r['erro'] . ' Configure um gerador grátis em Configurações → Fotos exclusivas geradas por IA (Cloudflare).']);
        case 'foto_banco':
            // Fotos profissionais (Pexels) baixadas para o servidor (a arte precisa da imagem no mesmo site para exportar)
            session_write_close();
            if (!fotos_configurado()) responder_json(['ok' => false, 'erro' => 'Ligue um banco de fotos em Configurações (Pixabay, Unsplash ou o Openverse, que não precisa de chave).']);
            $termo = mb_substr(trim($_POST['termo'] ?? ''), 0, 80) ?: 'small business';
            $termo = fotos_termo_segmento(mb_strtolower(preg_replace('/\s+/', '-', $termo)), $termo, $termo);
            $pasta = img_ia_pasta('ia/marketing');
            if (!$pasta) responder_json(['ok' => false, 'erro' => 'A pasta uploads/ não tem permissão de escrita no servidor.']);
            $urls = [];
            foreach (array_slice(fotos_buscar($termo, 6, ($_POST['formato'] ?? '') === 'story' ? 'portrait' : 'landscape'), 0, 6) as $i => $f) {
                $ch = curl_init($f['medio']); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_FOLLOWLOCATION => true]);
                $bytes = curl_exec($ch); $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200; curl_close($ch);
                if (!$ok || !$bytes) continue;
                fotos_registrar_uso($f);
                $salvo = img_ia_salvar($bytes, $pasta . '/banco-' . substr(md5($f['medio']), 0, 12), 1600);
                if ($salvo) $urls[] = ['url' => '/uploads/ia/marketing/' . basename($salvo), 'autor' => $f['autor'], 'fonte' => $f['fonte'] ?? ''];
            }
            responder_json($urls ? ['ok' => true, 'fotos' => $urls] : ['ok' => false, 'erro' => 'Nenhuma foto encontrada para "' . $termo . '".']);
        case 'historico':
            $id = (int)($_POST['id'] ?? 0);
            $st = $pdo->prepare("SELECT * FROM marketing_posts WHERE id=?"); $st->execute([$id]); $p = $st->fetch();
            responder_json($p ? ['ok' => true, 'post' => json_decode($p['conteudo'], true), 'formato' => $p['formato']] : ['ok' => false, 'erro' => 'Não encontrado.']);
    }
    responder_json(['ok' => false, 'erro' => 'Ação inválida.'], 400);
}

$marca = ['marca' => get_config('mkt_marca', 'GDS Sites'), 'arroba' => get_config('mkt_arroba', '@gdssites'), 'site' => get_config('mkt_site', ''),
          'preco' => get_config('mkt_preco', ''), 'whatsapp' => get_config('mkt_whatsapp', ''), 'cor' => get_config('mkt_cor', '#6d7cff'),
          'cor2' => get_config('mkt_cor2', '#22d3ee'), 'escuro' => get_config('mkt_escuro', '1') === '1'];
if ($marca['site'] === '') $marca['site'] = preg_replace('#^https?://#', '', get_config('plataforma_url', '')) ?: ($_SERVER['HTTP_HOST'] ?? '');
$segmentos = $pdo->query("SELECT nome FROM segmentos WHERE ativo=true AND slug<>'outro' ORDER BY nome")->fetchAll(PDO::FETCH_COLUMN);
$historico = $pdo->query("SELECT id, formato, tema, criado_em FROM marketing_posts ORDER BY id DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Marketing com IA - GDS Admin') ?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@500;700;800&family=Inter:wght@400;500;600;700;800&family=Bebas+Neue&family=Playfair+Display:wght@700;800&family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Cormorant+Garamond:wght@600;700&display=swap">
<style>
.mkt-grade { display: grid; grid-template-columns: minmax(320px, 1fr) minmax(300px, 460px); gap: 20px; align-items: start; }
@media (max-width: 1050px) { .mkt-grade { grid-template-columns: 1fr; } }
.mkt-previa { position: sticky; top: 16px; }
.mkt-previa canvas { width: 100%; height: auto; border-radius: 16px; box-shadow: 0 30px 60px -30px rgba(0,0,0,.6); background: #111; }
.mkt-modelos { display: flex; flex-wrap: wrap; gap: 6px; margin: 6px 0 4px; }
.mkt-modelos button { margin: 0; }
.mkt-roteiro li { margin-bottom: 8px; }
.mkt-roteiro small { color: var(--text-dim); display: block; }
.mkt-acoes { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.mkt-acoes .btn { margin: 0; }
.mkt-semana td { vertical-align: top; }
textarea.mkt-legenda { min-height: 160px; }
.mkt-fotos { display: grid; grid-template-columns: repeat(auto-fill, minmax(84px, 1fr)); gap: 8px; margin-top: 10px; }
.mkt-fotos button { padding: 0; border: 2px solid transparent; border-radius: 10px; overflow: hidden; aspect-ratio: 1; background: var(--bg); cursor: pointer; margin: 0; }
.mkt-fotos button.ativo { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(91,124,255,.25); }
.mkt-fotos img { width: 100%; height: 100%; object-fit: cover; display: block; }
.mkt-chips { display: flex; flex-wrap: wrap; gap: 6px; margin: 4px 0 8px; }
.mkt-chips button { margin: 0; }
.mkt-chips button[aria-pressed="true"] { background: var(--primary-btn, #4a63e8); color: #fff; border-color: transparent; }
.mkt-adapt { margin-top: 12px; } .mkt-adapt textarea { min-height: 90px; }
.mkt-paletas { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
.mkt-paletas button { width: 38px; height: 38px; border-radius: 50%; border: 2px solid var(--border); padding: 0; margin: 0; cursor: pointer; }
.mkt-paletas button.ativo { border-color: #fff; box-shadow: 0 0 0 3px var(--primary); }
</style>
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Marketing com IA</h1>
        <p class="subtitulo">Gere posts prontos para vender os sites: arte (PNG), vídeo para Reels/TikTok, legenda, hashtags e roteiro. Tudo com a IA configurada na plataforma.</p></div></div>
    <?= flash_render() ?>
    <?php if (!ia_configurada()): ?><?= alerta('aviso', 'Sem chave de IA, os posts saem do banco de posts prontos da plataforma (variando modelo, estilo e roteiro) e dos dados reais da sua prospecção. Com a IA ligada (o Google Gemini é grátis), cada post é escrito sob medida.') ?><?php endif; ?>

    <div class="mkt-grade">
    <div>
        <section class="form-box largo">
            <h3>1. O que você quer postar?</h3>
            <label>Objetivo</label>
            <select id="objetivo"><?php foreach ($objetivos as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select>
            <div class="form-row">
                <div><label>Para quem (público)</label><input type="text" id="publico" list="listaSeg" placeholder="Ex: Barbearia, Hamburgueria, Clínica…"><datalist id="listaSeg"><?php foreach ($segmentos as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist></div>
                <div><label>Formato</label><select id="formato"><option value="feed">Feed 4:5 (1080×1350)</option><option value="story">Story / Reels / TikTok 9:16</option><option value="quadrado">Quadrado 1:1</option></select></div>
            </div>
            <label>Observações (opcional)</label><input type="text" id="extra" placeholder="Ex: promoção de lançamento até sexta, foco em agendamento">
            <label>Modelo de copy</label>
            <select id="framework"><?php foreach ($frameworks as $k => $f): ?><option value="<?= $k ?>"><?= e($f[0]) ?></option><?php endforeach; ?></select>
            <label>Onde vai postar <small class="muted">(a IA adapta o texto para cada rede marcada)</small></label>
            <div class="mkt-chips" id="redes"><?php foreach ($redes as $k => $v): ?><button type="button" class="btn btn-ghost btn-sm" data-rede="<?= $k ?>" aria-pressed="<?= in_array($k, ['instagram', 'tiktok'], true) ? 'true' : 'false' ?>"><?= e($v) ?></button><?php endforeach; ?></div>
            <div class="mkt-acoes">
                <button type="button" class="btn" id="btnGerar" onclick="MKT.gerar()"><?= icone('brilho', 16) ?> <?= ia_configurada() ? 'Gerar post com IA' : 'Gerar post' ?></button>
            </div>
            <label style="margin-top:14px">Planejamento</label>
            <div class="mkt-chips" id="periodo"><button type="button" class="btn btn-ghost btn-sm" data-v="semana" aria-pressed="true">Esta semana</button><button type="button" class="btn btn-ghost btn-sm" data-v="mes" aria-pressed="false">O mês todo (4 semanas)</button></div>
            <div class="mkt-chips" id="porSemana"><?php foreach ([3 => '3 posts/semana', 5 => '5 posts/semana', 7 => 'Todo dia'] as $k => $v): ?><button type="button" class="btn btn-ghost btn-sm" data-v="<?= $k ?>" aria-pressed="<?= $k === 5 ? 'true' : 'false' ?>"><?= $v ?></button><?php endforeach; ?></div>
            <div class="mkt-acoes"><button type="button" class="btn btn-ghost" onclick="MKT.semana()"><?= icone('calendario', 16) ?> Montar o calendário</button></div>
        </section>

        <section class="form-box largo" id="semana" hidden>
            <h3 id="semanaTit">Calendário</h3>
            <div class="tabela-wrap"><table class="mkt-semana"><thead><tr><th>Dia</th><th>Formato</th><th>Tema e gancho</th><th></th></tr></thead><tbody id="semanaCorpo"></tbody></table></div>
        </section>

        <section class="form-box largo">
            <h3>2. Ajuste a arte</h3>
            <div id="variacoes" hidden><label>Versões para testar (A/B)</label><div class="mkt-chips" id="variacoesBtns"></div></div>
            <label>Modelo</label>
            <div class="mkt-modelos" id="modelos">
                <?php foreach (['foto_titulo' => '📷 Foto + título', 'foto_split' => '📷 Foto + bloco', 'pergunta' => '❓ Pergunta', 'numero' => '🔢 Número', 'impacto' => 'Impacto + celular', 'lista' => 'Lista', 'antes_depois' => 'Antes × Depois', 'depoimento' => 'Depoimento', 'oferta' => 'Oferta'] as $k => $v): ?>
                <button type="button" class="btn btn-ghost btn-sm" data-modelo="<?= $k ?>"><?= $v ?></button><?php endforeach; ?>
            </div>
            <div class="form-row">
                <div><label>Selo</label><input type="text" data-c="selo" maxlength="40"></div>
                <div><label>Botão</label><input type="text" data-c="cta" maxlength="30"></div>
            </div>
            <label>Título (use *asteriscos* para destacar)</label><input type="text" data-c="titulo" maxlength="120">
            <label>Subtítulo</label><input type="text" data-c="subtitulo" maxlength="160">
            <div data-para="lista"><label>Itens (um por linha)</label><textarea data-c="itens" rows="4"></textarea></div>
            <div class="form-row" data-para="antes_depois">
                <div><label>Antes (um por linha)</label><textarea data-c="antes" rows="3"></textarea></div>
                <div><label>Depois (um por linha)</label><textarea data-c="depois" rows="3"></textarea></div>
            </div>
            <div data-para="depoimento"><label>Depoimento</label><textarea data-c="citacao" rows="3"></textarea><label>Autor</label><input type="text" data-c="autor"></div>
            <div data-para="oferta numero"><label>Preço / número em destaque</label><input type="text" data-c="preco" maxlength="20"></div>
            <div class="form-row">
                <div><label>Estilo visual</label><select id="estilo"><?php foreach (['neon' => 'Neon (moderno, brilho)', 'editorial' => 'Editorial (revista, serifado)', 'bold' => 'Bold (letras gigantes)', 'pop' => 'Pop (listras, energia)', 'minimal' => 'Minimal (limpo)', 'luxo' => 'Luxo (dourado, sofisticado)'] as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                <div><label>Paleta</label><div class="mkt-paletas" id="paletas"></div></div>
            </div>
            <div class="mkt-acoes"><button type="button" class="btn btn-ghost btn-sm" onclick="MKT.variar()">🎲 Variar visual (estilo, cores e foto)</button></div>
        </section>

        <section class="form-box largo">
            <h3>Fotos da arte</h3>
            <p class="dica">Fotos de verdade fazem o post parar o dedo. Gere uma exclusiva com IA, busque no banco de fotos ou use a sua.</p>
            <label>Descreva a foto (ou deixe a IA sugerir ao gerar o post)</label>
            <input type="text" id="cenaFoto" placeholder="Ex: barbeiro sorrindo mostrando o celular com a agenda, barbearia moderna">
            <div class="mkt-acoes">
                <button type="button" class="btn btn-sm" id="btnFotoIa" onclick="MKT.fotoIa()"><?= icone('brilho', 14) ?> Gerar foto com IA</button>
                <button type="button" class="btn btn-ghost btn-sm" onclick="MKT.fotoBanco()">🔎 Buscar no banco de fotos</button>
                <button type="button" class="btn btn-ghost btn-sm" id="btnPuter" onclick="MKT.fotoPuter()" title="Gera no seu navegador com uma conta grátis do Puter (abre um login na 1ª vez)">🌐 Gerar no navegador (Puter)</button>
                <label class="btn btn-ghost btn-sm" style="margin:0;cursor:pointer">⬆ Usar minha foto<input type="file" accept="image/*" multiple hidden onchange="MKT.minhaFoto(this)"></label>
                <button type="button" class="btn btn-ghost btn-sm" onclick="MKT.semFoto()">Sem foto</button>
            </div>
            <div class="mkt-fotos" id="fotos"></div>
        </section>

        <section class="form-box largo">
            <h3>3. Legenda, hashtags e roteiro</h3>
            <label>Legenda</label><textarea id="legenda" class="mkt-legenda"></textarea>
            <label>Hashtags</label><textarea id="hashtags" rows="2"></textarea>
            <div class="mkt-acoes"><button type="button" class="btn btn-ghost btn-sm" onclick="MKT.copiar()">Copiar legenda + hashtags</button></div>
            <div id="adaptacoes"></div>
            <h3 style="margin-top:18px">Roteiro do Reels/TikTok</h3>
            <ol class="mkt-roteiro" id="roteiro"><li class="dica">Gere um post para ver o roteiro.</li></ol>
            <p class="dica" id="dicaGravacao"></p>
        </section>

        <form class="form-box largo" id="formMarca" onsubmit="MKT.salvarMarca(event)">
            <h3>Sua marca nas artes</h3>
            <div class="form-row">
                <div><label>Nome da marca</label><input type="text" name="mkt_marca" value="<?= e($marca['marca']) ?>"></div>
                <div><label>@ do Instagram/TikTok</label><input type="text" name="mkt_arroba" value="<?= e($marca['arroba']) ?>"></div>
            </div>
            <div class="form-row">
                <div><label>Site (rodapé da arte)</label><input type="text" name="mkt_site" value="<?= e($marca['site']) ?>"></div>
                <div><label>Preço/condição (a IA usa nas ofertas)</label><input type="text" name="mkt_preco" value="<?= e($marca['preco']) ?>" placeholder="Ex: a partir de R$ 97/mês"></div>
            </div>
            <div class="form-row">
                <div><label>Cor 1</label><input type="color" name="mkt_cor" value="<?= e($marca['cor']) ?>"></div>
                <div><label>Cor 2</label><input type="color" name="mkt_cor2" value="<?= e($marca['cor2']) ?>"></div>
            </div>
            <label>Fundo</label><select name="mkt_escuro"><option value="1" <?= $marca['escuro'] ? 'selected' : '' ?>>Escuro</option><option value="0" <?= $marca['escuro'] ? '' : 'selected' ?>>Claro</option></select>
            <input type="hidden" name="mkt_whatsapp" value="<?= e($marca['whatsapp']) ?>">
            <button type="submit">Salvar marca</button>
        </form>

        <?php if ($historico): ?>
        <section class="form-box largo">
            <h3>Posts gerados</h3>
            <div class="tabela-wrap"><table><tbody>
            <?php foreach ($historico as $h): ?>
                <tr><td><?= date('d/m H:i', strtotime($h['criado_em'])) ?></td><td><?= e(mb_strimwidth($h['tema'], 0, 70, '…')) ?></td><td><?= e($h['formato']) ?></td>
                    <td class="acoes"><button type="button" class="btn btn-ghost btn-sm" onclick="MKT.abrir(<?= (int)$h['id'] ?>)">Abrir</button></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </section>
        <?php endif; ?>
    </div>

    <div class="mkt-previa">
        <canvas id="tela" width="1080" height="1350" aria-label="Prévia da arte"></canvas>
        <div class="mkt-acoes">
            <button type="button" class="btn" onclick="MKT.baixarPng()"><?= icone('download', 16) ?> Baixar imagem</button>
            <button type="button" class="btn btn-ghost" onclick="MKT.carrossel()">🗂 Baixar carrossel</button>
            <button type="button" class="btn btn-ghost" id="btnVideo" onclick="MKT.video()"><?= icone('celular', 16) ?> Gerar vídeo (Reels/TikTok)</button>
        </div>
        <p class="dica" id="statusVideo">O vídeo usa o roteiro: cada cena vira uma tela animada (9:16) com as suas fotos em movimento e texto entrando palavra por palavra. Grave no Chrome do computador.</p>
    </div>
    </div>
</main>
</div>
<div id="toasts"></div>
<script src="/assets/js/gds-criativos.js?v=2"></script>
<script>
const MKT = (() => {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (id) => document.getElementById(id);
    const MARCA = <?= json_encode($marca, JSON_UNESCAPED_UNICODE) ?>;
    let modelo = 'foto_titulo', estilo = 'neon', paleta = null, fotos = [], fotoAtual = null;
    let conteudo = { selo: 'Sites para negócios locais', titulo: 'Seu cliente procura no Google. *Ele te acha?*', subtitulo: 'Site profissional com agendamento e WhatsApp automático.', cta: 'Quero meu site',
        itens: ['Agendamento online 24h', 'Confirmação automática no WhatsApp', 'Orçamento com valor pelo WhatsApp', 'Domínio próprio'], antes: ['Responde WhatsApp o dia todo', 'Cliente esquece o horário', 'Perde orçamento'], depois: ['Cliente agenda sozinho', 'Lembrete automático', 'Orçamento em minutos'],
        citacao: '', autor: '', preco: '' };
    let roteiro = [];
    async function api(dados) {
        const fd = new FormData(); fd.append('csrf_token', CSRF); Object.entries(dados).forEach(([k, v]) => fd.append(k, v));
        try { return await (await fetch('marketing.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })).json(); }
        catch (e) { return { ok: false, erro: 'Falha de conexão.' }; }
    }
    function toast(msg, tipo) { const t = document.createElement('div'); t.className = 'toast toast-' + (tipo || 'info'); t.textContent = msg; $('toasts').appendChild(t); setTimeout(() => t.remove(), 5000); }
    function opcoes() {
        const p = paleta || { cor: MARCA.cor, cor2: MARCA.cor2, escuro: MARCA.escuro };
        return { modelo, estilo, formato: $('formato').value, cor: p.cor, cor2: p.cor2, escuro: p.escuro, marca: MARCA.marca, arroba: MARCA.arroba, site: MARCA.site, foto: fotoAtual ? fotoAtual.img : null };
    }
    // ---------- paletas e estilo ----------
    function montarPaletas() {
        const box = $('paletas'); box.innerHTML = '';
        [{ nome: 'Minha marca', cor: MARCA.cor, cor2: MARCA.cor2, escuro: MARCA.escuro }].concat(GDSCriativos.paletas).forEach((p, i) => {
            const b = document.createElement('button'); b.type = 'button'; b.title = p.nome;
            b.style.background = 'linear-gradient(135deg,' + p.cor + ' 0 50%,' + (p.escuro ? '#0b0b10' : p.cor2) + ' 50% 100%)';
            b.classList.toggle('ativo', i === 0 ? !paleta : paleta && paleta.nome === p.nome);
            b.addEventListener('click', () => { paleta = i === 0 ? null : p; montarPaletas(); desenhar(); });
            box.appendChild(b);
        });
    }
    $('estilo').addEventListener('change', () => { estilo = $('estilo').value; desenhar(); });
    function variar() {
        const ests = Object.keys(GDSCriativos.estilos), pals = GDSCriativos.paletas;
        estilo = ests[Math.floor(Math.random() * ests.length)]; $('estilo').value = estilo;
        paleta = pals[Math.floor(Math.random() * pals.length)];
        if (fotos.length) fotoAtual = fotos[Math.floor(Math.random() * fotos.length)];
        const comFoto = ['foto_titulo', 'foto_split', 'pergunta', 'numero', 'impacto', 'lista'];
        if (fotoAtual && !['antes_depois', 'depoimento', 'oferta'].includes(modelo)) modelo = comFoto[Math.floor(Math.random() * comFoto.length)];
        montarPaletas(); montarFotos(); preencher();
    }
    // ---------- fotos ----------
    function carregar(url) { return new Promise((ok, erro) => { const i = new Image(); i.onload = () => ok(i); i.onerror = () => erro(new Error('Não carregou a foto.')); i.src = url; }); }
    async function addFoto(url, selecionar) {
        try { const img = await carregar(url); const f = { url, img }; fotos.unshift(f); fotos = fotos.slice(0, 18); if (selecionar || !fotoAtual) fotoAtual = f; montarFotos(); desenhar(); }
        catch (e) { toast(e.message, 'erro'); }
    }
    function montarFotos() {
        const box = $('fotos'); box.innerHTML = '';
        fotos.forEach(f => { const b = document.createElement('button'); b.type = 'button'; b.className = f === fotoAtual ? 'ativo' : ''; b.innerHTML = '<img alt="">'; b.querySelector('img').src = f.url;
            b.addEventListener('click', () => { fotoAtual = f; if (!['foto_titulo', 'foto_split', 'pergunta', 'slide', 'numero'].includes(modelo)) modelo = 'foto_titulo'; montarFotos(); preencher(); }); box.appendChild(b); });
    }
    async function fotoIa() {
        const b = $('btnFotoIa'); b.disabled = true; const t = b.innerHTML; b.textContent = 'Gerando foto… (5 a 40s)';
        const r = await api({ acao: 'foto_ia', cena: $('cenaFoto').value, formato: $('formato').value });
        b.disabled = false; b.innerHTML = t;
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        await addFoto(r.url, true); if (!['foto_titulo', 'foto_split'].includes(modelo)) { modelo = 'foto_titulo'; preencher(); }
        if (r.provedor) toast('Foto gerada com ' + r.provedor + '.', 'sucesso');
    }
    // Reserva sem chave: gera no navegador com o Puter (o próprio usuário entra com uma conta grátis do Puter)
    async function fotoPuter() {
        const b = $('btnPuter'); b.disabled = true; const t = b.textContent; b.textContent = 'Gerando no navegador…';
        try {
            if (!window.puter) await new Promise((ok, erro) => { const sc = document.createElement('script'); sc.src = 'https://js.puter.com/v2/'; sc.onload = ok; sc.onerror = () => erro(new Error('Não carregou o Puter.')); document.head.appendChild(sc); });
            const cena = $('cenaFoto').value || 'small business owner smiling while checking appointments on a smartphone in a modern local shop';
            const img = await puter.ai.txt2img('Professional editorial photograph: ' + cena + '. Natural light, realistic, authentic Brazilian small business. No text, no logos.');
            // Converte para dados locais (a arte precisa da imagem no próprio site para baixar)
            const blob = await (await fetch(img.src)).blob();
            const url = await new Promise(ok => { const rd = new FileReader(); rd.onload = () => ok(rd.result); rd.readAsDataURL(blob); });
            await addFoto(url, true); if (!['foto_titulo', 'foto_split'].includes(modelo)) { modelo = 'foto_titulo'; preencher(); }
            toast('Foto gerada no navegador (Puter).', 'sucesso');
        } catch (e) { toast('O Puter não gerou agora: ' + (e.message || e) + '. Use a geração com IA (Cloudflare) ou o banco de fotos.', 'erro'); }
        b.disabled = false; b.textContent = t;
    }
    async function fotoBanco() {
        toast('Buscando fotos…');
        const r = await api({ acao: 'foto_banco', termo: $('publico').value || $('cenaFoto').value || 'small business', formato: $('formato').value });
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        for (const f of r.fotos.reverse()) await addFoto(f.url, false);
        toast(r.fotos.length + ' fotos adicionadas. Clique numa para usar.', 'sucesso');
    }
    function minhaFoto(input) { [...input.files].slice(0, 6).forEach(f => { const rd = new FileReader(); rd.onload = () => addFoto(rd.result, true); rd.readAsDataURL(f); }); input.value = ''; }
    function semFoto() { fotoAtual = null; if (['foto_titulo', 'foto_split'].includes(modelo)) modelo = 'impacto'; montarFotos(); preencher(); }
    function desenhar() { GDSCriativos.desenhar($('tela'), conteudo, opcoes()); }
    function preencher() {
        document.querySelectorAll('[data-c]').forEach(el => { const v = conteudo[el.dataset.c]; el.value = Array.isArray(v) ? v.join('\n') : (v || ''); });
        document.querySelectorAll('[data-modelo]').forEach(b => b.classList.toggle('btn-ghost', b.dataset.modelo !== modelo));
        document.querySelectorAll('[data-para]').forEach(el => el.hidden = !el.dataset.para.split(' ').includes(modelo));
        desenhar();
    }
    document.querySelectorAll('[data-c]').forEach(el => el.addEventListener('input', () => {
        conteudo[el.dataset.c] = el.tagName === 'TEXTAREA' && ['itens', 'antes', 'depois'].includes(el.dataset.c) ? el.value.split('\n').filter(Boolean) : el.value;
        desenhar();
    }));
    document.querySelectorAll('[data-modelo]').forEach(b => b.addEventListener('click', () => { modelo = b.dataset.modelo; preencher(); }));
    $('formato').addEventListener('change', desenhar);

    function mostrarPost(p) {
        modelo = p.modelo || 'impacto';
        if (p.estilo && GDSCriativos.estilos[p.estilo]) { estilo = p.estilo; $('estilo').value = estilo; }
        if (p.cena_foto) $('cenaFoto').value = p.cena_foto;
        if (!fotoAtual && ['foto_titulo', 'foto_split'].includes(modelo)) toast('Esse post pede uma foto: clique em "Gerar foto com IA" ou busque no banco.', 'info');
        conteudo = Object.assign(conteudo, p.conteudo || {});
        $('legenda').value = p.legenda || '';
        $('hashtags').value = (p.hashtags || []).map(h => '#' + String(h).replace(/^#/, '').replace(/\s+/g, '')).join(' ');
        roteiro = p.roteiro || [];
        $('roteiro').innerHTML = '';
        roteiro.forEach(c => { const li = document.createElement('li'); li.innerHTML = '<strong></strong><small></small>'; li.querySelector('strong').textContent = (c.tempo ? c.tempo + ' · ' : '') + (c.texto_tela || ''); li.querySelector('small').textContent = c.fala || ''; $('roteiro').appendChild(li); });
        $('dicaGravacao').textContent = p.dica_gravacao ? '🎬 ' + p.dica_gravacao : '';
        preencher();
    }
    // botões de escolha (redes: vários; período e frequência: um)
    document.querySelectorAll('#redes button').forEach(b => b.addEventListener('click', () => b.setAttribute('aria-pressed', b.getAttribute('aria-pressed') === 'true' ? 'false' : 'true')));
    ['periodo', 'porSemana'].forEach(id => document.querySelectorAll('#' + id + ' button').forEach(b => b.addEventListener('click', () => document.querySelectorAll('#' + id + ' button').forEach(x => x.setAttribute('aria-pressed', x === b ? 'true' : 'false')))));
    const escolhido = (id) => (document.querySelector('#' + id + ' button[aria-pressed="true"]') || {}).dataset?.v;
    const redesMarcadas = () => [...document.querySelectorAll('#redes button[aria-pressed="true"]')].map(b => b.dataset.rede);
    const NOME_REDE = { facebook: 'Facebook', linkedin: 'LinkedIn', whatsapp_status: 'Status do WhatsApp', google: 'Perfil no Google' };
    function mostrarExtras(p) {
        const vs = [{ titulo: conteudo.titulo, subtitulo: conteudo.subtitulo, cta: conteudo.cta }].concat(p.variacoes || []);
        $('variacoes').hidden = vs.length < 2; $('variacoesBtns').innerHTML = '';
        vs.forEach((v, i) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-ghost btn-sm'; b.setAttribute('aria-pressed', i === 0 ? 'true' : 'false');
            b.textContent = 'Versão ' + 'ABC'[i] + ': ' + String(v.titulo || '').replace(/\*/g, '').slice(0, 48);
            b.addEventListener('click', () => { Object.assign(conteudo, { titulo: v.titulo || conteudo.titulo, subtitulo: v.subtitulo ?? conteudo.subtitulo, cta: v.cta || conteudo.cta });
                $('variacoesBtns').querySelectorAll('button').forEach(x => x.setAttribute('aria-pressed', x === b ? 'true' : 'false')); preencher(); });
            $('variacoesBtns').appendChild(b); });
        const box = $('adaptacoes'); box.innerHTML = '';
        Object.entries(p.adaptacoes || {}).forEach(([rede, txt]) => {
            const d = document.createElement('div'); d.className = 'mkt-adapt';
            d.innerHTML = '<label></label><textarea></textarea><button type="button" class="btn btn-ghost btn-sm">Copiar</button>';
            d.querySelector('label').textContent = 'Texto para ' + (NOME_REDE[rede] || rede); d.querySelector('textarea').value = txt;
            d.querySelector('button').addEventListener('click', () => { navigator.clipboard.writeText(d.querySelector('textarea').value); toast('Copiado!', 'sucesso'); });
            box.appendChild(d);
        });
    }
    async function gerar() {
        const b = $('btnGerar'); b.disabled = true; const txt = b.innerHTML; b.textContent = 'Criando… (até 1 min)';
        const r = await api({ acao: 'gerar_post', objetivo: $('objetivo').value, publico: $('publico').value, formato: $('formato').value, extra: $('extra').value, framework: $('framework').value, redes: redesMarcadas().join(',') });
        b.disabled = false; b.innerHTML = txt;
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        mostrarPost(r.post); mostrarExtras(r.post); toast(r.origem === 'dados' ? 'Post criado com números reais da sua prospecção!' : r.origem === 'banco' ? 'Post pronto do banco da plataforma (sem IA). Ajuste o que quiser e baixe.' : 'Post criado! Ajuste o que quiser e baixe.', 'sucesso');
    }
    async function semana() {
        const mes = escolhido('periodo') === 'mes';
        toast(mes ? 'Montando o mês… (até 1 min)' : 'Montando a semana…');
        const r = await api({ acao: 'gerar_semana', publico: $('publico').value, periodo: mes ? 'mes' : 'semana', por_semana: escolhido('porSemana') || 5 });
        $('semanaTit').textContent = mes ? 'Calendário do mês' : 'Calendário da semana';
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        const corpo = $('semanaCorpo'); corpo.innerHTML = '';
        r.dias.forEach(d => {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td></td><td></td><td><strong></strong><br><small></small></td><td><button type="button" class="btn btn-ghost btn-sm">Criar</button></td>';
            tr.children[0].textContent = d.dia || ''; tr.children[1].textContent = d.formato || '';
            tr.querySelector('strong').textContent = d.tema || ''; tr.querySelector('small').textContent = d.gancho || '';
            tr.querySelector('button').addEventListener('click', () => {
                if (d.objetivo && $('objetivo').querySelector('option[value="' + d.objetivo + '"]')) $('objetivo').value = d.objetivo;
                if (d.framework && $('framework').querySelector('option[value="' + d.framework + '"]')) $('framework').value = d.framework;
                $('extra').value = (d.tema || '') + ' — gancho: ' + (d.gancho || '');
                $('formato').value = /reels|story/i.test(d.formato || '') ? 'story' : 'feed';
                gerar();
            });
            corpo.appendChild(tr);
        });
        $('semana').hidden = false;
    }
    function baixar(blob, nome) { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = nome; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 4000); }
    function nomeArquivo(ext) { return (MARCA.marca || 'post').toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-' + modelo + '-' + $('formato').value + '.' + ext; }
    function baixarPng() { desenhar(); $('tela').toBlob(b => baixar(b, nomeArquivo('png')), 'image/png'); }
    // Carrossel: capa (pergunta/foto) + 1 slide por item + slide final com o botão
    async function carrossel() {
        const itens = (conteudo.itens && conteudo.itens.length ? conteudo.itens : (conteudo.depois || [])).slice(0, 6);
        if (!itens.length) { toast('Preencha os "Itens" (modelo Lista) para montar o carrossel.', 'erro'); return; }
        const total = itens.length + 2, tela = document.createElement('canvas'), o = opcoes();
        const salvar = (nome) => new Promise(ok => tela.toBlob(b => { baixar(b, nome); setTimeout(ok, 350); }, 'image/png'));
        const base = (MARCA.marca || 'post').toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-carrossel-';
        GDSCriativos.desenhar(tela, Object.assign({}, conteudo, { cta: '' }), Object.assign({}, o, { modelo: fotoAtual ? 'foto_titulo' : 'pergunta' }));
        await salvar(base + '01.png');
        for (let i = 0; i < itens.length; i++) {
            const f = fotos.length > 1 ? fotos[(i + 1) % fotos.length] : fotoAtual;
            GDSCriativos.desenhar(tela, { numero: i + 1, total, titulo: itens[i], subtitulo: '' }, Object.assign({}, o, { modelo: 'slide', foto: f ? f.img : null }));
            await salvar(base + String(i + 2).padStart(2, '0') + '.png');
        }
        GDSCriativos.desenhar(tela, conteudo, Object.assign({}, o, { modelo: conteudo.preco ? 'oferta' : 'impacto' }));
        await salvar(base + String(total).padStart(2, '0') + '.png');
        toast(total + ' imagens do carrossel baixadas — poste na ordem.', 'sucesso');
    }
    async function video() {
        // Cenas: gancho em tipografia gigante → fotos em movimento com o texto do roteiro → oferta/CTA
        const fotoDe = (i) => fotos.length ? fotos[i % fotos.length].img : null;
        const passos = roteiro.length ? roteiro.slice(0, 6) : [{ texto_tela: conteudo.titulo }, { texto_tela: (conteudo.itens || [])[0] || conteudo.subtitulo }, { texto_tela: conteudo.cta }];
        const cenas = passos.map((c, i, arr) => {
            const texto = c.texto_tela || conteudo.titulo;
            if (i === 0) return { modelo: 'kinetic', segundos: 2.6, conteudo: { titulo: texto }, foto: fotoDe(0) };
            if (i === arr.length - 1) return { modelo: conteudo.preco ? 'oferta' : (fotos.length ? 'foto_titulo' : 'impacto'), segundos: 3.4, conteudo: Object.assign({}, conteudo, { titulo: texto }), foto: fotoDe(i) };
            if (fotos.length) return { modelo: i % 2 ? 'foto_titulo' : 'foto_split', segundos: 3, conteudo: { titulo: texto, subtitulo: '' }, foto: fotoDe(i) };
            return { modelo: i % 2 ? 'lista' : 'numero', segundos: 3, conteudo: { titulo: texto, itens: (conteudo.itens || []).slice(0, Math.min(4, i + 1)), preco: conteudo.preco || '' } };
        });
        $('btnVideo').disabled = true; $('statusVideo').textContent = 'Gravando o vídeo… (' + cenas.reduce((s, c) => s + c.segundos, 0) + 's)';
        try {
            const blob = await GDSCriativos.gravarVideo($('tela'), cenas, Object.assign(opcoes(), { formato: 'story' }));
            baixar(blob, nomeArquivo(blob.type.includes('mp4') ? 'mp4' : 'webm'));
            $('statusVideo').textContent = blob.type.includes('mp4') ? 'Vídeo MP4 pronto — é só postar.' : 'Vídeo pronto (WebM). Instagram e TikTok aceitam pelo computador; no celular, converta em MP4 se precisar.';
        } catch (e) { $('statusVideo').textContent = e.message; }
        $('btnVideo').disabled = false;
        desenhar();
    }
    function copiar() { navigator.clipboard.writeText($('legenda').value + '\n\n' + $('hashtags').value); toast('Copiado!', 'sucesso'); }
    async function salvarMarca(ev) {
        ev.preventDefault();
        const dados = Object.fromEntries(new FormData($('formMarca')));
        const r = await api(Object.assign({ acao: 'salvar_marca' }, dados));
        if (r.ok) { Object.assign(MARCA, { marca: dados.mkt_marca, arroba: dados.mkt_arroba, site: dados.mkt_site, cor: dados.mkt_cor, cor2: dados.mkt_cor2, escuro: dados.mkt_escuro === '1' }); desenhar(); toast('Marca salva!', 'sucesso'); }
    }
    async function abrir(id) { const r = await api({ acao: 'historico', id }); if (r.ok) { $('formato').value = r.formato || 'feed'; mostrarPost(r.post); mostrarExtras(r.post); scrollTo({ top: 0, behavior: 'smooth' }); } }
    montarPaletas();
    document.fonts.ready.then(preencher);
    preencher();
    return { gerar, semana, baixarPng, video, copiar, salvarMarca, abrir, variar, fotoIa, fotoBanco, fotoPuter, minhaFoto, semFoto, carrossel };
})();
</script>
</body>
</html>
