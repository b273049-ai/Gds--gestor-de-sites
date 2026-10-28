<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

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
];

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
            if (!ia_configurada()) responder_json(['ok' => false, 'erro' => 'Configure a IA em Configurações primeiro.']);
            $obj = $objetivos[$_POST['objetivo'] ?? ''] ?? trim($_POST['objetivo'] ?? 'Divulgar a plataforma');
            $publico = trim($_POST['publico'] ?? '') ?: 'pequenos negócios locais em geral';
            $formato = in_array($_POST['formato'] ?? '', ['feed', 'story', 'quadrado'], true) ? $_POST['formato'] : 'feed';
            $extra = mb_substr(trim($_POST['extra'] ?? ''), 0, 400);
            $preco = get_config('mkt_preco', '');
            $prompt = <<<PROMPT
Você é um social media e copywriter de resposta direta especialista em vender serviços para pequenos negócios brasileiros.
Crie UM post para Instagram/TikTok que venda a plataforma "$marca": sites profissionais para negócios locais, criados
com IA, com agendamento online, confirmação e lembrete automáticos no WhatsApp, pedido de orçamento com valor pelo
WhatsApp, delivery com carrinho, domínio próprio e painel simples. Preço/condição (use só se fizer sentido): "$preco".

OBJETIVO DO POST: $obj
PÚBLICO: $publico
FORMATO DA ARTE: $formato
OBSERVAÇÕES DO DONO: "$extra"

Regras: português do Brasil, linguagem simples e direta, sem promessas exageradas ou números inventados (nada de
"aumente 300%"). Use *asteriscos* em 1 a 3 palavras do título para destacá-las na arte. Título com no máximo 9 palavras.
Escolha o "modelo" de arte que mais combina: impacto | lista | antes_depois | depoimento | oferta
(depoimento: deixe claro em "autor" que é um exemplo para trocar por um depoimento real).

Responda APENAS com JSON válido:
{"modelo":"impacto","conteudo":{"selo":"até 5 palavras","titulo":"...","subtitulo":"até 18 palavras","itens":["até 6 itens curtos (lista)"],
"antes":["3 itens"],"depois":["3 itens"],"citacao":"","autor":"","preco":"","cta":"até 4 palavras"},
"legenda":"legenda pronta com gancho na 1ª linha, 3-6 linhas curtas, emojis com moderação e chamada para o direct/link",
"hashtags":["8 a 15 hashtags relevantes, sem #"],
"roteiro":[{"tempo":"0-3s","texto_tela":"texto curto que aparece na tela","fala":"o que falar/narrar"}],
"dica_gravacao":"1 dica prática para gravar o Reels/TikTok com o celular"}
O roteiro deve ter 4 a 6 cenas e durar de 15 a 30 segundos, com gancho forte nos 3 primeiros segundos.
PROMPT;
            $r = chamar_ia_json($prompt, 2000, 60, 0.9, ['esforco' => 'low']);
            if (!$r['ok']) responder_json(['ok' => false, 'erro' => $r['erro']]);
            $d = $r['dados'];
            if (!in_array($d['modelo'] ?? '', ['impacto', 'lista', 'antes_depois', 'depoimento', 'oferta'], true)) $d['modelo'] = 'impacto';
            $pdo->prepare("INSERT INTO marketing_posts (rede, formato, tema, conteudo) VALUES (?,?,?,?)")
                ->execute(['instagram/tiktok', $formato, mb_substr($obj, 0, 150), json_encode($d, JSON_UNESCAPED_UNICODE)]);
            responder_json(['ok' => true, 'post' => $d, 'formato' => $formato]);
        case 'gerar_semana':
            if (!ia_configurada()) responder_json(['ok' => false, 'erro' => 'Configure a IA em Configurações primeiro.']);
            $publico = trim($_POST['publico'] ?? '') ?: 'pequenos negócios locais';
            $prompt = "Você é estrategista de conteúdo. Monte o calendário de 7 dias de posts (Instagram + TikTok) para vender a plataforma \"$marca\" "
                    . "(sites com IA para negócios locais, agendamento, WhatsApp automático, orçamento, delivery, domínio próprio) para o público: $publico. "
                    . "Misture: dor, prova, educativo, bastidores, oferta e quebra de objeção. Responda APENAS JSON: "
                    . '{"dias":[{"dia":"Segunda","formato":"Reels|Carrossel|Story|Feed","tema":"...","gancho":"primeira frase do post","objetivo":"dor|antes_depois|recursos|prova|oferta|educativo|bastidores|objecao"}]}';
            $r = chamar_ia_json($prompt, 1500, 60, 0.9, ['esforco' => 'low']);
            if (!$r['ok']) responder_json(['ok' => false, 'erro' => $r['erro']]);
            responder_json(['ok' => true, 'dias' => array_slice((array)($r['dados']['dias'] ?? []), 0, 7)]);
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
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@500;700;800&family=Inter:wght@400;500;600;700;800&display=swap">
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
</style>
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Marketing com IA</h1>
        <p class="subtitulo">Gere posts prontos para vender os sites: arte (PNG), vídeo para Reels/TikTok, legenda, hashtags e roteiro. Tudo com a IA configurada na plataforma.</p></div></div>
    <?= flash_render() ?>
    <?php if (!ia_configurada()): ?><?= alerta('aviso', 'Configure a IA em Configurações para gerar os textos. Você ainda pode montar as artes à mão.') ?><?php endif; ?>

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
            <div class="mkt-acoes">
                <button type="button" class="btn" id="btnGerar" onclick="MKT.gerar()"><?= icone('brilho', 16) ?> Gerar post com IA</button>
                <button type="button" class="btn btn-ghost" onclick="MKT.semana()"><?= icone('calendario', 16) ?> Plano da semana</button>
            </div>
        </section>

        <section class="form-box largo" id="semana" hidden>
            <h3>Calendário da semana</h3>
            <div class="tabela-wrap"><table class="mkt-semana"><thead><tr><th>Dia</th><th>Formato</th><th>Tema e gancho</th><th></th></tr></thead><tbody id="semanaCorpo"></tbody></table></div>
        </section>

        <section class="form-box largo">
            <h3>2. Ajuste a arte</h3>
            <label>Modelo</label>
            <div class="mkt-modelos" id="modelos">
                <?php foreach (['impacto' => 'Impacto + celular', 'lista' => 'Lista', 'antes_depois' => 'Antes × Depois', 'depoimento' => 'Depoimento', 'oferta' => 'Oferta'] as $k => $v): ?>
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
            <div data-para="oferta"><label>Preço / destaque no círculo</label><input type="text" data-c="preco" maxlength="20"></div>
        </section>

        <section class="form-box largo">
            <h3>3. Legenda, hashtags e roteiro</h3>
            <label>Legenda</label><textarea id="legenda" class="mkt-legenda"></textarea>
            <label>Hashtags</label><textarea id="hashtags" rows="2"></textarea>
            <div class="mkt-acoes"><button type="button" class="btn btn-ghost btn-sm" onclick="MKT.copiar()">Copiar legenda + hashtags</button></div>
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
            <button type="button" class="btn btn-ghost" id="btnVideo" onclick="MKT.video()"><?= icone('celular', 16) ?> Gerar vídeo (Reels/TikTok)</button>
        </div>
        <p class="dica" id="statusVideo">O vídeo usa o roteiro: cada cena vira uma tela animada (9:16). Grave no Chrome do computador.</p>
    </div>
    </div>
</main>
</div>
<div id="toasts"></div>
<script src="/assets/js/gds-criativos.js?v=1"></script>
<script>
const MKT = (() => {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (id) => document.getElementById(id);
    const MARCA = <?= json_encode($marca, JSON_UNESCAPED_UNICODE) ?>;
    let modelo = 'impacto';
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
    function opcoes() { return { modelo, formato: $('formato').value, cor: MARCA.cor, cor2: MARCA.cor2, marca: MARCA.marca, arroba: MARCA.arroba, site: MARCA.site, escuro: MARCA.escuro }; }
    function desenhar() { GDSCriativos.desenhar($('tela'), conteudo, opcoes()); }
    function preencher() {
        document.querySelectorAll('[data-c]').forEach(el => { const v = conteudo[el.dataset.c]; el.value = Array.isArray(v) ? v.join('\n') : (v || ''); });
        document.querySelectorAll('[data-modelo]').forEach(b => b.classList.toggle('btn-ghost', b.dataset.modelo !== modelo));
        document.querySelectorAll('[data-para]').forEach(el => el.hidden = el.dataset.para !== modelo);
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
        conteudo = Object.assign(conteudo, p.conteudo || {});
        $('legenda').value = p.legenda || '';
        $('hashtags').value = (p.hashtags || []).map(h => '#' + String(h).replace(/^#/, '').replace(/\s+/g, '')).join(' ');
        roteiro = p.roteiro || [];
        $('roteiro').innerHTML = '';
        roteiro.forEach(c => { const li = document.createElement('li'); li.innerHTML = '<strong></strong><small></small>'; li.querySelector('strong').textContent = (c.tempo ? c.tempo + ' · ' : '') + (c.texto_tela || ''); li.querySelector('small').textContent = c.fala || ''; $('roteiro').appendChild(li); });
        $('dicaGravacao').textContent = p.dica_gravacao ? '🎬 ' + p.dica_gravacao : '';
        preencher();
    }
    async function gerar() {
        const b = $('btnGerar'); b.disabled = true; const txt = b.innerHTML; b.textContent = 'Criando… (até 1 min)';
        const r = await api({ acao: 'gerar_post', objetivo: $('objetivo').value, publico: $('publico').value, formato: $('formato').value, extra: $('extra').value });
        b.disabled = false; b.innerHTML = txt;
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        mostrarPost(r.post); toast('Post criado! Ajuste o que quiser e baixe.', 'sucesso');
    }
    async function semana() {
        toast('Montando a semana…');
        const r = await api({ acao: 'gerar_semana', publico: $('publico').value });
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        const corpo = $('semanaCorpo'); corpo.innerHTML = '';
        r.dias.forEach(d => {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td></td><td></td><td><strong></strong><br><small></small></td><td><button type="button" class="btn btn-ghost btn-sm">Criar</button></td>';
            tr.children[0].textContent = d.dia || ''; tr.children[1].textContent = d.formato || '';
            tr.querySelector('strong').textContent = d.tema || ''; tr.querySelector('small').textContent = d.gancho || '';
            tr.querySelector('button').addEventListener('click', () => {
                if (d.objetivo && $('objetivo').querySelector('option[value="' + d.objetivo + '"]')) $('objetivo').value = d.objetivo;
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
    async function video() {
        const cenas = roteiro.length ? roteiro.slice(0, 6).map((c, i, arr) => ({
            modelo: i === 0 ? 'impacto' : (i === arr.length - 1 ? (conteudo.preco ? 'oferta' : 'impacto') : 'lista'),
            segundos: 3,
            conteudo: i === 0 ? Object.assign({}, conteudo, { titulo: c.texto_tela || conteudo.titulo, cta: '' })
                    : (i === arr.length - 1 ? Object.assign({}, conteudo, { titulo: c.texto_tela || conteudo.titulo })
                    : { titulo: c.texto_tela || '', itens: (conteudo.itens || []).slice(0, Math.min(4, i + 1)) })
        })) : [{ modelo, segundos: 4, conteudo }, { modelo: 'lista', segundos: 4, conteudo }, { modelo: 'oferta', segundos: 3, conteudo }];
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
    async function abrir(id) { const r = await api({ acao: 'historico', id }); if (r.ok) { $('formato').value = r.formato || 'feed'; mostrarPost(r.post); scrollTo({ top: 0, behavior: 'smooth' }); } }
    document.fonts.ready.then(preencher);
    preencher();
    return { gerar, semana, baixarPng, video, copiar, salvarMarca, abrir };
})();
</script>
</body>
</html>
