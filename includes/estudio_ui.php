<?php
// ============================================================
// Tela do Estúdio IA (compartilhada entre admin e empresa).
// Espera: $empresa, $site, $apiUrl, $sidebar (arquivo), $ehAdmin
// ============================================================
require_once __DIR__ . '/ia_site_engine.php';

$empresaIdEst = (int)$empresa['id'];
$temSite = !empty($site['html_gerado']);
$publicado = pg_bool($site['publicado'] ?? false);
$modoIa = pg_bool($site['ia_modo_total'] ?? false);
$iaPronta = ia_configurada();
$previewUrl = '/site/preview.php?empresa_id=' . $empresaIdEst . '&modo=ia';
$urlPublica = '/' . $empresa['slug'];
$direcoes = ia_direcoes_visuais();
$corBotoes = (json_decode((string)($site['ia_tema'] ?? ''), true) ?: [])['accent'] ?? '';
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $corBotoes)) $corBotoes = preg_match('/^#[0-9a-fA-F]{6}$/', $site['cor_primaria'] ?? '') ? $site['cor_primaria'] : '#2563eb';
$atInfo = atendimento_info_empresa($empresaIdEst);
$modosAt = atendimento_modos();
$semWhats = trim((string)($empresa['whatsapp'] ?? '')) === '';
$qtdServicos = $pdo->prepare("SELECT COUNT(*) c FROM servicos WHERE empresa_id=?");
$qtdServicos->execute([$empresaIdEst]);
$qtdServicos = (int)$qtdServicos->fetch()['c'];
$sugestoes = [
    'Deixe com cara de landing page moderna, estilo SaaS',
    'Deixe o topo mais impactante, com uma headline maior',
    'Troque a paleta para tons mais quentes',
    'Deixe o visual mais claro e minimalista',
    'Adicione uma seção de perguntas frequentes',
    $atInfo['modo'] === 'orcamento' ? 'Destaque melhor o botão de pedir orçamento' : ($atInfo['modo'] === 'agendamento' ? 'Destaque melhor o botão de agendar' : 'Destaque melhor o botão do WhatsApp'),
    'Melhore o menu no celular',
    'Adicione os depoimentos dos clientes',
    'Deixe os textos mais curtos e diretos',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<?= painel_head('Estúdio IA — ' . $empresa['nome']) ?>
</head>
<body class="estudio-body">
<div class="layout">
<?php include $sidebar; ?>
<div class="content estudio">

<header class="estudio-topo">
    <div class="estudio-titulo">
        <?php if ($ehAdmin): ?><a href="site_hub.php?empresa_id=<?= $empresaIdEst ?>" class="link-voltar">← <?= e($empresa['nome']) ?></a><?php endif; ?>
        <h1><?= icone('brilho', 20) ?> Estúdio IA</h1>
        <span id="pillPublicado" class="pill <?= $publicado ? 'pill-ok' : 'pill-neutro' ?>"><?= $publicado ? 'Publicado' : 'Rascunho' ?></span>
        <span id="pillModo" class="pill <?= $modoIa ? 'pill-ok' : 'pill-neutro' ?>" title="Quando ativo, o site público usa o design criado pela IA"><?= $modoIa ? 'Design IA ativo' : 'Usando template' ?></span>
    </div>
    <div class="estudio-acoes">
        <label class="btn btn-ghost cor-botoes" title="Cor dos botões de agendamento, serviços e contato"><input type="color" id="corBotoes" value="<?= e($corBotoes) ?>" onchange="Estudio.salvarCor(this.value)"> <span>Cor dos botões</span></label>
        <div class="seg" role="group" aria-label="Tamanho da tela">
            <button type="button" class="seg-btn ativo" data-device="desktop" title="Computador"><?= icone('desktop') ?></button>
            <button type="button" class="seg-btn" data-device="tablet" title="Tablet"><?= icone('tablet') ?></button>
            <button type="button" class="seg-btn" data-device="mobile" title="Celular"><?= icone('celular') ?></button>
        </div>
        <button type="button" class="btn btn-ghost btn-icone" onclick="Estudio.recarregarPreview()" title="Atualizar pré-visualização"><?= icone('atualizar') ?></button>
        <button type="button" class="btn btn-ghost" onclick="Estudio.abrirCodigo()" <?= $temSite ? '' : 'disabled' ?> id="btnCodigo"><?= icone('codigo') ?> Código</button>
        <a class="btn btn-ghost" href="<?= e($urlPublica) ?>" target="_blank" rel="noopener"><?= icone('link') ?> Ver site</a>
        <button type="button" class="btn <?= $publicado ? 'btn-ghost' : '' ?>" id="btnPublicar" onclick="Estudio.alternarPublicacao()"><?= icone('globo') ?> <span><?= $publicado ? 'Despublicar' : 'Publicar' ?></span></button>
    </div>
</header>

<?php if (!$iaPronta): ?>
<?= alerta('aviso', $ehAdmin ? 'A IA ainda não foi configurada. Vá em Configurações e cole uma chave (a do Google Gemini é gratuita).' : 'A IA ainda não foi configurada pelo administrador da plataforma.') ?>
<?php endif; ?>

<div class="estudio-negocio" id="painelNegocio">
    <div><small>Segmento</small><strong id="negTipo"><?= e($atInfo['segmento_texto']) ?></strong></div>
    <label><small>Como os clientes contratam</small>
        <select id="selModo" onchange="Estudio.salvarAtendimento(this.value)">
            <option value="" <?= $atInfo['modo_origem'] !== 'empresa' ? 'selected' : '' ?>>Automático (<?= e($modosAt[$atInfo['modo_segmento'] ?? '']['nome'] ?? 'pelo segmento') ?>)</option>
            <?php foreach ($modosAt as $k => $m): ?>
            <option value="<?= e($k) ?>" <?= $atInfo['modo_origem'] === 'empresa' && $atInfo['modo'] === $k ? 'selected' : '' ?> title="<?= e($m['desc']) ?>"><?= e($m['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if ($semWhats): ?><span class="pill pill-aviso" title="Os botões de WhatsApp só aparecem com o número cadastrado">⚠ Sem WhatsApp cadastrado</span><?php endif; ?>
</div>

<div class="estudio-grid">
    <!-- ===================== PAINEL ESQUERDO ===================== -->
    <section class="estudio-painel">
        <div class="abas" role="tablist">
            <button type="button" class="aba <?= $temSite ? 'ativa' : '' ?>" data-aba="chat"><?= icone('chat', 16) ?> Conversa</button>
            <button type="button" class="aba <?= $temSite ? '' : 'ativa' ?>" data-aba="novo"><?= icone('mais', 16) ?> Novo site</button>
            <button type="button" class="aba" data-aba="versoes"><?= icone('historico', 16) ?> Versões</button>
        </div>

        <!-- CONVERSA -->
        <div class="aba-conteudo <?= $temSite ? 'ativa' : '' ?>" data-conteudo="chat">
            <div class="chat-lista" id="chatLista">
                <div class="chat-vazio" id="chatVazio">
                    <?= icone('brilho', 28) ?>
                    <p><?= $temSite ? 'Peça qualquer mudança no site em linguagem normal — a IA aplica e cria uma nova versão.' : 'Comece criando o site na aba “Novo site”. Depois é só conversar aqui para ajustar.' ?></p>
                </div>
            </div>
            <div class="chips" id="chipsSugestao">
                <?php foreach ($sugestoes as $s): ?><button type="button" class="chip" data-sugestao="<?= e($s) ?>"><?= e($s) ?></button><?php endforeach; ?>
            </div>
            <div class="chat-anexos" id="chatAnexos" hidden></div>
            <div class="chat-ferramentas">
                <button type="button" class="chip" id="btnSelecionar" onclick="Estudio.modoSelecao()" title="Clique num elemento do site para editar só ele" <?= $temSite ? '' : 'disabled' ?>>🎯 Selecionar elemento</button>
                <button type="button" class="chip" onclick="Estudio.abrirReferencia()" title="Cole o código de um site para copiar o estilo" <?= $temSite ? '' : 'disabled' ?>>📎 Site de referência</button>
            </div>
            <div class="chat-referencia" id="chatReferencia" hidden>
                <label for="refCodigo">Cole o código do site que você quer como referência (HTML, React/Lovable) ou descreva o estilo</label>
                <textarea id="refCodigo" rows="5" placeholder="Cole aqui o código… A IA copia o estilo (cores, fontes, espaçamentos, layout), não o conteúdo."></textarea>
                <div style="display:flex;gap:8px;margin-top:8px"><button type="button" class="btn btn-sm" onclick="Estudio.anexarReferencia()">Anexar</button><button type="button" class="btn btn-ghost btn-sm" onclick="Estudio.abrirReferencia(false)">Cancelar</button></div>
            </div>
            <form class="chat-composer" id="formRefinar" onsubmit="Estudio.refinar(event)">
                <textarea id="inputInstrucao" rows="2" placeholder="<?= $temSite ? 'Ex: troque a cor de destaque para verde e deixe o rodapé mais escuro' : 'Gere o site primeiro…' ?>" <?= $temSite ? '' : 'disabled' ?>></textarea>
                <button type="submit" class="btn btn-icone" id="btnEnviar" title="Enviar (Ctrl+Enter)" <?= $temSite ? '' : 'disabled' ?>><?= icone('enviar') ?></button>
            </form>
        </div>

        <!-- NOVO SITE -->
        <div class="aba-conteudo <?= $temSite ? '' : 'ativa' ?>" data-conteudo="novo">
            <form id="formGerar" class="form-estudio" onsubmit="Estudio.gerar(event)">
                <label for="briefing">Conte sobre o negócio</label>
                <textarea id="briefing" rows="7" placeholder="Ex: Barbearia em Jundiaí há 8 anos, ambiente descontraído com música e cerveja artesanal. Diferencial: navalha e toalha quente. Público de 20 a 40 anos. Quero um visual old-school moderno."><?= e($site['ia_briefing'] ?? '') ?></textarea>
                <button type="button" class="btn btn-ghost btn-sm" onclick="Estudio.melhorarBriefing()" id="btnMelhorar"><?= icone('brilho', 14) ?> Melhorar descrição com IA</button>

                <label>Estilo visual</label>
                <div class="estilos" id="estilos">
                    <button type="button" class="estilo ativo" data-direcao=""><strong>Surpreenda-me</strong><small>A IA sorteia um estilo diferente a cada geração</small></button>
                    <?php foreach ($direcoes as $chave => [$nome, $desc]): ?>
                    <button type="button" class="estilo" data-direcao="<?= e($chave) ?>" title="<?= e($desc) ?>"><strong><?= e($nome) ?></strong><small><?= e(mb_strimwidth($desc, 0, 70, '…')) ?></small></button>
                    <?php endforeach; ?>
                </div>

                <label>Fotos exclusivas com IA</label>
                <div class="fotos-ia" id="fotosIa">
                    <?php foreach (fotos_ia_do_site(['fotos_banco' => $site['fotos_banco'] ?? null]) as $fi): ?>
                    <figure><img src="<?= e($fi['url']) ?>" alt="Foto gerada" loading="lazy"><button type="button" title="Remover" onclick="Estudio.removerFoto(this, <?= e(json_encode($fi['url'])) ?>)">×</button></figure>
                    <?php endforeach; ?>
                </div>
                <?php if (img_ia_disponivel()): ?>
                <button type="button" class="btn btn-ghost btn-sm" id="btnFotos" onclick="Estudio.gerarFotos()"><?= icone('brilho', 14) ?> Gerar 3 fotos exclusivas do meu negócio</button>
                <p class="dica" id="fotosStatus">A IA cria fotos no clima do seu negócio (topo do site, ambiente, detalhe). Leva cerca de 1 minuto.</p>
                <?php else: ?><p class="dica">A geração de fotos com IA ainda não foi configurada pelo administrador.</p><?php endif; ?>

                <label for="referenciaNovo">Site de referência (opcional)</label>
                <textarea id="referenciaNovo" rows="3" placeholder="Cole o código de um site que você gosta (ex.: o que a Lovable gerou) ou descreva. A IA segue o estilo dele com o conteúdo do seu negócio."></textarea>

                <label for="preferencias">Preferências (opcional)</label>
                <input type="text" id="preferencias" placeholder="Ex: usar verde-escuro e dourado, fonte elegante, nada de fundo preto">

                <?php if ($qtdServicos === 0): ?>
                <label class="check"><input type="checkbox" id="criarServicos" checked> Criar os serviços/pacotes sugeridos pela IA (você ainda não cadastrou nenhum)</label>
                <?php endif; ?>

                <button type="submit" class="btn btn-grande" id="btnGerar" <?= $iaPronta ? '' : 'disabled' ?>><?= icone('brilho') ?> <?= $temSite ? 'Gerar um site novo' : 'Gerar meu site' ?></button>
                <p class="dica">A IA estuda o negócio, decide como o cliente contrata (agendamento, orçamento, WhatsApp ou pedido) e cria as páginas com tudo funcionando de verdade, usando os dados reais (serviços, preços, equipe, fotos, endereço). Leva de 1 a 3 minutos.</p>
                <?php if ($temSite): ?><p class="dica">O site atual continua salvo na aba Versões.</p><?php endif; ?>
            </form>
        </div>

        <!-- VERSÕES -->
        <div class="aba-conteudo" data-conteudo="versoes">
            <div id="listaVersoes" class="lista-versoes"><p class="dica">Carregando…</p></div>
            <?php if ($modoIa): ?>
            <button type="button" class="btn btn-ghost btn-sm" style="margin-top:12px" onclick="Estudio.alternarModo(false)">Voltar a usar o template manual</button>
            <?php elseif ($temSite): ?>
            <button type="button" class="btn btn-sm" style="margin-top:12px" onclick="Estudio.alternarModo(true)">Usar o design da IA no site público</button>
            <?php endif; ?>
        </div>
    </section>

    <!-- ===================== PRÉ-VISUALIZAÇÃO ===================== -->
    <section class="estudio-preview">
        <div class="preview-moldura" id="moldura" data-device="desktop">
            <div class="preview-barra"><span></span><span></span><span></span><div class="preview-url"><?= e($_SERVER['HTTP_HOST'] ?? '') . e($urlPublica) ?></div></div>
            <?php if ($temSite): ?>
            <iframe id="previewFrame" src="<?= e($previewUrl) ?>" title="Pré-visualização do site"></iframe>
            <?php else: ?>
            <iframe id="previewFrame" title="Pré-visualização do site" style="display:none"></iframe>
            <div class="preview-vazio" id="previewVazio"><?= icone('layout', 40) ?><p>A pré-visualização do site aparece aqui.</p></div>
            <?php endif; ?>
            <div class="preview-carregando" id="carregando" hidden>
                <div class="spinner"></div>
                <ol class="etapas" id="etapas"></ol>
                <p class="tempo" id="tempo">0s</p>
                <p class="dica">Pode continuar navegando no painel em outra aba — não feche esta.</p>
            </div>
        </div>
    </section>
</div>

<!-- Editor de código -->
<dialog id="dlgCodigo" class="dialogo">
    <form method="dialog" class="dialogo-topo"><strong><?= icone('codigo') ?> Código do site</strong><button class="btn btn-ghost btn-sm" value="cancel">Fechar</button></form>
    <p class="dica">Edite o HTML/CSS livremente. Mantenha os marcadores como <code>{{SERVICOS}}</code> e <code>{{CONTATO}}</code> — eles viram dados reais do banco.</p>
    <textarea id="htmlEditor" spellcheck="false"></textarea>
    <div class="dialogo-rodape"><button type="button" class="btn" onclick="Estudio.salvarCodigo()">Salvar como nova versão</button></div>
</dialog>

</div>
</div>

<script>
const Estudio = (() => {
    const API = <?= json_encode($apiUrl) ?>;
    const EMPRESA_ID = <?= $empresaIdEst ?>;
    const PREVIEW = <?= json_encode($previewUrl) ?>;
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    let publicado = <?= $publicado ? 'true' : 'false' ?>;
    let ocupado = false, timer = null;
    const $ = (id) => document.getElementById(id);

    async function api(acao, dados = {}) {
        const fd = new FormData();
        fd.append('acao', acao);
        fd.append('empresa_id', EMPRESA_ID);
        Object.entries(dados).forEach(([k, v]) => fd.append(k, v));
        let resp;
        try {
            resp = await fetch(API, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
        } catch (e) {
            return { ok: false, erro: 'Sem conexão com o servidor. Verifique a internet e tente de novo.' };
        }
        const texto = await resp.text();
        try { return JSON.parse(texto); }
        catch (e) {
            if ([502, 503, 504, 520, 522, 524].includes(resp.status)) return { ok: false, erro: 'A hospedagem encerrou a requisição por tempo (a IA demorou demais). Tente de novo — ou use uma IA mais rápida (Groq) em Configurações.' };
            // Mostra o começo da resposta do servidor (ajuda a descobrir o problema)
            const limpo = texto.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 240);
            return { ok: false, erro: 'O servidor respondeu com erro (' + resp.status + ')' + (limpo ? ': ' + limpo : '. Veja o log de erros do PHP na hospedagem.') };
        }
    }

    function toast(msg, tipo = 'info') {
        let box = document.getElementById('toasts');
        if (!box) { box = document.createElement('div'); box.id = 'toasts'; document.body.appendChild(box); }
        const t = document.createElement('div');
        t.className = 'toast toast-' + tipo;
        t.setAttribute('role', 'status');
        t.textContent = msg;
        box.appendChild(t);
        setTimeout(() => t.classList.add('saindo'), 4200);
        setTimeout(() => t.remove(), 4700);
    }

    function carregando(etapas) {
        ocupado = true;
        const box = $('carregando'), lista = $('etapas');
        lista.innerHTML = etapas.map((e, i) => `<li data-i="${i}">${e}</li>`).join('');
        box.hidden = false;
        let s = 0; $('tempo').textContent = '0s';
        timer = setInterval(() => { s++; $('tempo').textContent = (s >= 60 ? Math.floor(s / 60) + 'min ' : '') + (s % 60) + 's'; }, 1000);
        document.querySelectorAll('#formGerar button, #formRefinar button, #formRefinar textarea').forEach(b => b.disabled = true);
        etapa(0);
    }
    function etapa(i) {
        document.querySelectorAll('#etapas li').forEach(li => {
            const n = +li.dataset.i;
            li.className = n < i ? 'feita' : (n === i ? 'atual' : '');
        });
    }
    function pronto() {
        ocupado = false;
        clearInterval(timer);
        $('carregando').hidden = true;
        document.querySelectorAll('#formGerar button, #formRefinar button, #formRefinar textarea').forEach(b => b.disabled = false);
    }

    function recarregarPreview() {
        const f = $('previewFrame');
        f.style.display = '';
        if ($('previewVazio')) $('previewVazio').remove();
        f.src = PREVIEW + '&t=' + Date.now();
    }

    function b64(t) { try { return t ? btoa(unescape(encodeURIComponent(t))) : ''; } catch (e) { return ''; } }
    function addMsg(papel, texto, versaoId, extra = {}) {
        $('chatVazio')?.remove();
        const div = document.createElement('div');
        div.className = 'msg msg-' + papel + (extra.classe ? ' ' + extra.classe : '');
        div.textContent = texto;
        if (extra.opcoes && extra.opcoes.length) {
            const box = document.createElement('div'); box.className = 'msg-opcoes';
            extra.opcoes.forEach(o => { const b = document.createElement('button'); b.type = 'button'; b.className = 'chip'; b.textContent = o;
                b.onclick = () => { box.remove(); enviar((extra.original ? extra.original + ' — ' : '') + o); }; box.appendChild(b); });
            div.appendChild(box);
        }
        if (versaoId) {
            const b = document.createElement('button');
            b.type = 'button'; b.className = 'msg-versao';
            b.textContent = 'versão #' + versaoId + ' · restaurar';
            b.onclick = () => restaurar(versaoId);
            div.appendChild(b);
        }
        $('chatLista').appendChild(div);
        $('chatLista').scrollTop = $('chatLista').scrollHeight;
    }

    function liberarChat() {
        $('inputInstrucao').disabled = false;
        $('btnEnviar').disabled = false;
        $('btnCodigo').disabled = false;
        document.querySelectorAll('.chat-ferramentas .chip').forEach(b => b.disabled = false);
        $('inputInstrucao').placeholder = 'Ex: troque a cor de destaque para verde e deixe o rodapé mais escuro';
        marcarModo(true);
    }
    function marcarModo(ativo) {
        const p = $('pillModo');
        p.textContent = ativo ? 'Design IA ativo' : 'Usando template';
        p.className = 'pill ' + (ativo ? 'pill-ok' : 'pill-neutro');
    }

    function abrirAba(nome) {
        document.querySelectorAll('.aba').forEach(a => a.classList.toggle('ativa', a.dataset.aba === nome));
        document.querySelectorAll('.aba-conteudo').forEach(c => c.classList.toggle('ativa', c.dataset.conteudo === nome));
        if (nome === 'versoes') carregarHistorico();
    }

    async function carregarHistorico(soMensagens = false) {
        const r = await api('historico');
        if (!r.ok) return;
        if (!soMensagens) {
            const lista = $('listaVersoes');
            if (!r.versoes.length) { lista.innerHTML = '<p class="dica">Nenhuma versão ainda.</p>'; }
            else {
                const rotulo = { geracao: 'Geração', ajuste: 'Ajuste', manual: 'Código', restauracao: 'Restauração' };
                lista.innerHTML = '';
                r.versoes.forEach((v, i) => {
                    const item = document.createElement('div');
                    item.className = 'versao' + (i === 0 ? ' atual' : '');
                    const data = new Date(v.criado_em.replace(' ', 'T'));
                    item.innerHTML = `<div><span class="pill pill-neutro">${rotulo[v.origem] || v.origem}</span> <strong>#${v.id}</strong>${i === 0 ? ' <span class="pill pill-ok">atual</span>' : ''}<p></p><small>${data.toLocaleString('pt-BR')}</small></div>`;
                    item.querySelector('p').textContent = v.descricao || '';
                    if (i > 0) {
                        const b = document.createElement('button');
                        b.type = 'button'; b.className = 'btn btn-ghost btn-sm'; b.textContent = 'Restaurar';
                        b.onclick = () => restaurar(v.id);
                        item.appendChild(b);
                    }
                    lista.appendChild(item);
                });
            }
        }
        if (r.mensagens.length) {
            $('chatLista').innerHTML = '';
            r.mensagens.forEach(m => addMsg(m.papel, m.texto, m.papel === 'ia' ? m.versao_id : null));
        }
    }

    async function gerar(ev) {
        ev.preventDefault();
        if (ocupado) return;
        const briefing = $('briefing').value.trim();
        if (briefing.length < 10) { toast('Conte um pouco mais sobre o negócio.', 'erro'); $('briefing').focus(); return; }
        const direcao = document.querySelector('.estilo.ativo')?.dataset.direcao || '';
        carregando(['Estudando o negócio e os dados cadastrados', 'Decidindo estilo, cores e tipografia', 'Escrevendo as páginas e o código', 'Conectando agendamento, serviços e contato']);
        const plano = await api('planejar', { briefing, direcao, preferencias: $('preferencias').value, criar_servicos: $('criarServicos')?.checked ? '1' : '0', referencia_b64: b64($('referenciaNovo')?.value || '') });
        if (!plano.ok) { pronto(); toast(plano.erro, 'erro'); return; }
        etapa(2);
        const pal = (plano.plano.paleta || []).map(c => `<span class="cor" style="background:${/^#[0-9a-f]{3,8}$/i.test(c) ? c : '#888'}"></span>`).join('');
        $('etapas').insertAdjacentHTML('beforebegin', `<div class="plano-resumo" id="planoResumo"><strong></strong><p></p><div class="cores">${pal}</div></div>`);
        $('planoResumo').querySelector('strong').textContent = plano.plano.direcao + ' · ' + plano.plano.fonte_titulo + ' + ' + plano.plano.fonte_corpo;
        $('planoResumo').querySelector('p').textContent = (plano.plano.tipo_negocio ? plano.plano.tipo_negocio + ' — ' + plano.plano.modo_nome + '. ' : '') + plano.plano.conceito;
        const r = await api('gerar');
        $('planoResumo')?.remove();
        pronto();
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        toast('Site criado no estilo ' + r.direcao + '!' + (r.servicos_criados ? ' ' + r.servicos_criados + ' serviços sugeridos foram cadastrados.' : ''), 'sucesso');
        if (r.modo && $('selModo') && $('selModo').value === '') $('selModo').options[0].textContent = 'Automático (' + r.modo_nome + ')';
        if ($('criarServicos')) $('criarServicos').closest('label').remove();
        recarregarPreview();
        liberarChat();
        abrirAba('chat');
        carregarHistorico(true);
    }

    // ---------- Chat no estilo Lovable ----------
    let elemento = null, referencia = '', selecionando = false;
    function mostrarAnexos() {
        const box = $('chatAnexos'); box.innerHTML = '';
        const add = (txt, limpar) => { const c = document.createElement('span'); c.className = 'anexo'; c.textContent = txt;
            const x = document.createElement('button'); x.type = 'button'; x.textContent = '×'; x.title = 'Remover'; x.onclick = limpar; c.appendChild(x); box.appendChild(c); };
        if (elemento) add('🎯 ' + elemento.tag + (elemento.texto ? ' · "' + elemento.texto.slice(0, 40) + (elemento.texto.length > 40 ? '…' : '') + '"' : ''), () => { elemento = null; marcarSelecionado(null); mostrarAnexos(); });
        if (referencia) add('📎 Site de referência (' + Math.round(referencia.length / 1000) + ' mil caracteres)', () => { referencia = ''; mostrarAnexos(); });
        box.hidden = !elemento && !referencia;
    }
    function abrirReferencia(abrir = true) { $('chatReferencia').hidden = !abrir; if (abrir) $('refCodigo').focus(); }
    function anexarReferencia() { referencia = $('refCodigo').value.trim(); $('refCodigo').value = ''; abrirReferencia(false); mostrarAnexos();
        if (referencia && !$('inputInstrucao').value) $('inputInstrucao').value = 'Deixe o meu site com o estilo deste site de referência, mantendo o meu negócio'; }
    function atualizarChips(lista) {
        if (!lista || !lista.length) return;
        const box = $('chipsSugestao'); box.innerHTML = '';
        lista.slice(0, 4).forEach(t => { const c = document.createElement('button'); c.type = 'button'; c.className = 'chip'; c.textContent = t;
            c.onclick = () => { const i = $('inputInstrucao'); i.value = t; i.focus(); }; box.appendChild(c); });
    }
    // Modo "selecionar elemento": clique no preview para editar só aquele pedaço
    function docPreview() { try { return $('previewFrame').contentDocument; } catch (e) { return null; } }
    function marcarSelecionado(el) { const d = docPreview(); if (!d) return; d.querySelectorAll('[data-gds-sel]').forEach(x => x.removeAttribute('data-gds-sel')); if (el) el.setAttribute('data-gds-sel', '1'); }
    function prepararSelecao() {
        const d = docPreview(); if (!d || !d.body || d.__gdsSel) return;
        d.__gdsSel = true;
        const st = d.createElement('style');
        st.textContent = 'html.gds-selecionando *{cursor:crosshair!important}html.gds-selecionando [data-gds-hover]{outline:2px dashed #8b5cf6!important;outline-offset:2px}[data-gds-sel]{outline:3px solid #8b5cf6!important;outline-offset:2px}';
        d.head.appendChild(st);
        d.addEventListener('mouseover', e => { if (!selecionando) return; d.querySelectorAll('[data-gds-hover]').forEach(x => x.removeAttribute('data-gds-hover')); e.target.setAttribute('data-gds-hover', '1'); }, true);
        d.addEventListener('click', e => {
            if (!selecionando) return;
            e.preventDefault(); e.stopPropagation();
            const el = e.target; el.removeAttribute('data-gds-hover');
            const sec = el.closest('section,header,footer,[data-page]');
            const comp = el.closest('.gds-c');
            const h = sec && sec.querySelector('h1,h2,h3');
            elemento = { tag: el.tagName.toLowerCase(), id: el.id || '', classes: (el.getAttribute('class') || '').replace('gds-selecionando', ''), texto: (el.innerText || '').trim().replace(/\s+/g, ' ').slice(0, 200),
                html: el.outerHTML.replace(/ data-gds-(hover|sel)="1"/g, '').slice(0, 1500), secao: sec ? ((sec.id || sec.dataset.page || '') + (h ? ' "' + h.innerText.trim().slice(0, 60) + '"' : '')) : '',
                componente: comp ? (comp.id || comp.className).slice(0, 60) : '' };
            marcarSelecionado(el); modoSelecao(false); mostrarAnexos();
            $('inputInstrucao').placeholder = 'O que mudar neste elemento? Ex: deixe maior e em negrito';
            $('inputInstrucao').focus();
        }, true);
    }
    function modoSelecao(ligar) {
        selecionando = ligar === undefined ? !selecionando : ligar;
        prepararSelecao();
        const d = docPreview(); if (d) d.documentElement.classList.toggle('gds-selecionando', selecionando);
        $('btnSelecionar').classList.toggle('ativo', selecionando);
        $('btnSelecionar').textContent = selecionando ? '🎯 Clique no elemento do site…' : '🎯 Selecionar elemento';
    }
    $('previewFrame').addEventListener('load', () => { const d = docPreview(); if (d) d.__gdsSel = false; if (selecionando) modoSelecao(true); });

    async function enviar(instrucao) {
        if (ocupado || !instrucao) return;
        addMsg('usuario', instrucao + (elemento ? '\n🎯 ' + elemento.tag + (elemento.texto ? ' "' + elemento.texto.slice(0, 50) + '"' : '') : '') + (referencia ? '\n📎 com site de referência' : ''));
        $('inputInstrucao').value = '';
        const pensando = document.createElement('div'); pensando.className = 'msg msg-ia msg-pensando'; pensando.textContent = 'Entendendo o pedido…';
        $('chatLista').appendChild(pensando); $('chatLista').scrollTop = $('chatLista').scrollHeight;
        ocupado = true; document.querySelectorAll('#formRefinar button, #formRefinar textarea').forEach(b => b.disabled = true);
        const r = await api('entender', { instrucao, elemento: elemento ? JSON.stringify(elemento) : '', referencia_b64: b64(referencia) });
        pensando.remove(); ocupado = false; document.querySelectorAll('#formRefinar button, #formRefinar textarea').forEach(b => b.disabled = false);
        if (!r.ok) { addMsg('ia', '⚠ ' + r.erro); return; }
        if (r.tipo === 'pergunta') { addMsg('ia', r.texto, null, { opcoes: r.opcoes, original: instrucao }); return; }
        if (r.tipo === 'conversa') { addMsg('ia', r.texto); atualizarChips(r.proximos); return; }
        // Mostra o plano antes de aplicar (como a Lovable)
        const plano = [r.entendi].concat((r.alteracoes || []).map(a => '• ' + a)).join('\n');
        addMsg('ia', plano, null, { classe: 'msg-plano' });
        const global = r.estrategia === 'reescrever';
        carregando(['Entendi o pedido', global ? 'Redesenhando o site (mantendo o seu negócio)' : 'Editando só o que precisa', 'Conferindo e salvando a nova versão']);
        etapa(1);
        const a = await api('aplicar');
        pronto();
        if (!a.ok) { addMsg('ia', '⚠ ' + a.erro); return; }
        addMsg('ia', '✓ ' + a.texto, a.versao_id);
        elemento = null; referencia = ''; mostrarAnexos();
        $('inputInstrucao').placeholder = 'Peça outra mudança…';
        atualizarChips(a.proximos);
        recarregarPreview();
    }
    async function refinar(ev) {
        ev?.preventDefault();
        enviar($('inputInstrucao').value.trim());
    }

    async function restaurar(id) {
        if (ocupado || !confirm('Voltar o site para a versão #' + id + '? (a atual continua salva no histórico)')) return;
        const r = await api('restaurar', { versao_id: id });
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        toast('Versão #' + id + ' restaurada.', 'sucesso');
        recarregarPreview();
        liberarChat();
        carregarHistorico();
    }

    async function melhorarBriefing() {
        const btn = $('btnMelhorar'); btn.disabled = true; const txt = btn.innerHTML; btn.textContent = 'Melhorando…';
        const r = await api('melhorar_briefing', { briefing: $('briefing').value });
        btn.disabled = false; btn.innerHTML = txt;
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        $('briefing').value = r.briefing;
        toast('Descrição melhorada — revise e ajuste se quiser.', 'sucesso');
    }

    async function abrirCodigo() {
        const r = await api('codigo');
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        $('htmlEditor').value = r.html;
        $('dlgCodigo').showModal();
    }
    async function salvarCodigo() {
        const html = $('htmlEditor').value;
        const b64 = btoa(unescape(encodeURIComponent(html)));
        const r = await api('salvar_codigo', { html_b64: b64 });
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        $('dlgCodigo').close();
        toast('Código salvo como versão #' + r.versao_id, 'sucesso');
        recarregarPreview();
    }

    async function alternarPublicacao() {
        const r = await api('publicar', { publicado: publicado ? '0' : '1' });
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        publicado = r.publicado;
        $('pillPublicado').textContent = publicado ? 'Publicado' : 'Rascunho';
        $('pillPublicado').className = 'pill ' + (publicado ? 'pill-ok' : 'pill-neutro');
        $('btnPublicar').querySelector('span').textContent = publicado ? 'Despublicar' : 'Publicar';
        $('btnPublicar').classList.toggle('btn-ghost', publicado);
        toast(publicado ? 'Site publicado! Já está no ar.' : 'Site tirado do ar.', publicado ? 'sucesso' : 'info');
    }

    async function salvarCor(cor) {
        const r = await api('tema', { accent: cor });
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        toast('Cor dos botões atualizada.', 'sucesso');
        recarregarPreview();
    }

    async function alternarModo(ativo) {
        const r = await api('modo', { ativo: ativo ? '1' : '0' });
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        location.reload();
    }

    // ---- eventos ----
    document.querySelectorAll('.aba').forEach(a => a.addEventListener('click', () => abrirAba(a.dataset.aba)));
    document.querySelectorAll('.estilo').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('.estilo').forEach(x => x.classList.remove('ativo')); b.classList.add('ativo');
    }));
    document.querySelectorAll('[data-sugestao]').forEach(c => c.addEventListener('click', () => {
        const i = $('inputInstrucao'); if (i.disabled) return; i.value = c.dataset.sugestao; i.focus();
    }));
    document.querySelectorAll('[data-device]').forEach(b => b.addEventListener('click', () => {
        if (!b.classList.contains('seg-btn')) return;
        document.querySelectorAll('.seg-btn').forEach(x => x.classList.remove('ativo')); b.classList.add('ativo');
        $('moldura').dataset.device = b.dataset.device;
    }));
    $('inputInstrucao').addEventListener('keydown', (e) => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) refinar(e); });
    window.addEventListener('beforeunload', (e) => { if (ocupado) { e.preventDefault(); e.returnValue = ''; } });
    carregarHistorico(true);

    async function salvarAtendimento(modo) {
        const r = await api('atendimento', { modo_atendimento: modo });
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        toast('Forma de atendimento: ' + r.modo_nome + '. O site já usa o componente certo.', 'sucesso');
        recarregarPreview();
    }

    async function gerarFotos() {
        const b = $('btnFotos'); if (!b || b.disabled) return;
        b.disabled = true;
        for (let i = 0; i < 3; i++) {
            $('fotosStatus').textContent = 'Criando a foto ' + (i + 1) + ' de 3…';
            const r = await api('gerar_foto', { indice: i, estilo: $('preferencias').value });
            if (!r.ok) { toast(r.erro, 'erro'); break; }
            $('fotosIa').insertAdjacentHTML('afterbegin', '<figure><img alt="Foto gerada"><button type="button" title="Remover">×</button></figure>');
            const fig = $('fotosIa').firstElementChild;
            fig.querySelector('img').src = r.url;
            fig.querySelector('button').addEventListener('click', () => removerFoto(fig.querySelector('button'), r.url));
        }
        $('fotosStatus').textContent = 'Pronto! As fotos entram no site (topo e "sobre") e a IA usa nas próximas gerações.';
        b.disabled = false;
        recarregarPreview();
    }
    async function removerFoto(btn, url) {
        const r = await api('remover_foto', { url });
        if (r.ok) btn.closest('figure').remove();
    }

    return { modoSelecao, abrirReferencia, anexarReferencia, gerarFotos, removerFoto, salvarAtendimento, salvarCor, gerar, refinar, restaurar, melhorarBriefing, abrirCodigo, salvarCodigo, alternarPublicacao, alternarModo, recarregarPreview };
})();
</script>
</body>
</html>
