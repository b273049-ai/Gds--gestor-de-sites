// Marketing → Plano com os posts prontos: gera (IA no servidor, item por item), mostra o calendário
// e entrega cada post pronto: arte, carrossel, stories, vídeo narrado, legenda e o ZIP de tudo.
(function () {
    'use strict';
    var CFG = window.MKT_CFG || {};
    var M = CFG.marca || {};
    var TIPOS = CFG.tipos || {};
    var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var $ = function (id) { return document.getElementById(id); };
    var DIAS = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
    var HORAS_STORY = ['08:30', '12:30', '20:30', '17:30', '10:00'];
    var itens = [], rodando = false;

    function api(dados) {
        var fd = new FormData(); fd.append('csrf_token', CSRF);
        Object.keys(dados).forEach(function (k) { fd.append(k, dados[k]); });
        return fetch('marketing.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); }).catch(function () { return { ok: false, erro: 'Falha de conexão.' }; });
    }
    function toast(msg, tipo) { var t = document.createElement('div'); t.className = 'toast toast-' + (tipo || 'info'); t.textContent = msg; ($('toasts') || document.body).appendChild(t); setTimeout(function () { t.remove(); }, 5000); }
    function el(tag, cls, txt) { var e = document.createElement(tag); if (cls) e.className = cls; if (txt !== undefined) e.textContent = txt; return e; }
    function botao(txt, fn, cls) { var b = el('button', 'btn btn-ghost btn-sm' + (cls ? ' ' + cls : ''), txt); b.type = 'button'; b.addEventListener('click', function () { fn(b); }); return b; }

    // ---------- botões de escolha ----------
    ['plPeriodo', 'plPorSemana', 'plStories'].forEach(function (id) {
        document.querySelectorAll('#' + id + ' button').forEach(function (b) {
            b.addEventListener('click', function () { document.querySelectorAll('#' + id + ' button').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); });
        });
    });
    function escolhido(id) { var b = document.querySelector('#' + id + ' button[aria-pressed="true"]'); return b ? b.dataset.v : ''; }

    // ---------- imagens ----------
    var cacheImg = {};
    function imagem(url) {
        if (!url) return Promise.resolve(null);
        if (!cacheImg[url]) cacheImg[url] = new Promise(function (ok) { var i = new Image(); i.onload = function () { ok(i); }; i.onerror = function () { ok(null); }; i.src = url; });
        return cacheImg[url];
    }
    function formatoDo(tipo) { return tipo === 'reels' || tipo === 'story' ? 'story' : 'feed'; }
    function opcoes(item, modelo, foto, formato) {
        var p = item.post || {};
        return { modelo: modelo || p.modelo || 'foto_titulo', estilo: p.estilo || 'bold', formato: formato || formatoDo(item.tipo), cor: M.cor, cor2: M.cor2, escuro: M.escuro,
                 marca: M.marca, arroba: M.arroba, site: M.site, foto: foto };
    }
    function desenhar(canvas, conteudo, o) { window.GDSCriativos.desenhar(canvas, conteudo || {}, o); return canvas; }
    function blobDe(canvas, tipo) { return new Promise(function (ok) { canvas.toBlob(ok, tipo || 'image/png', 0.92); }); }
    function baixar(blob, nome) { var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = nome; a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000); }
    function nomeBase(item) { return item.data.slice(8, 10) + '-' + item.data.slice(5, 7) + '-' + (TIPOS[item.tipo] || item.tipo).toLowerCase() + '-' + item.hora.replace(':', 'h'); }

    // Peças de cada post: arte, slides do carrossel e stories
    function pecas(item, foto) {
        var p = item.post || {}, c = p.conteudo || {}, out = [];
        if (item.tipo !== 'story') {
            if (item.tipo === 'carrossel') {
                var slides = (c.itens || []).slice(0, 7), total = slides.length + 2;
                out.push({ nome: 'slide-01.png', conteudo: Object.assign({}, c, { cta: '' }), o: opcoes(item, foto ? 'foto_titulo' : 'pergunta', foto) });
                slides.forEach(function (s, i) { out.push({ nome: 'slide-' + String(i + 2).padStart(2, '0') + '.png', conteudo: { numero: i + 1, total: total, titulo: s, subtitulo: '' }, o: opcoes(item, 'slide', foto) }); });
                out.push({ nome: 'slide-' + String(total).padStart(2, '0') + '.png', conteudo: c, o: opcoes(item, c.preco ? 'oferta' : 'impacto', null) });
            } else out.push({ nome: (item.tipo === 'reels' ? 'capa-do-reels' : 'arte') + '.png', conteudo: c, o: opcoes(item, null, foto) });
        }
        (p.stories || []).forEach(function (s, i, arr) {
            out.push({ nome: 'story-' + (i + 1) + '-' + (HORAS_STORY[i] || '').replace(':', 'h') + '.png', story: s,
                conteudo: { selo: i === 0 ? (M.arroba || M.marca || '') : '', titulo: s.texto, subtitulo: '', cta: s.adesivo === 'link' ? 'Toque no link' : '' },
                o: opcoes(item, i === 0 && foto ? 'foto_titulo' : 'pergunta', i === 0 ? foto : null, 'story') });
        });
        return out;
    }
    function dicaAdesivo(s) {
        var op = (s.opcoes || []).filter(Boolean).join(' / ');
        return { enquete: '📊 Adesivo ENQUETE' + (op ? ': ' + op : ''), pergunta: '💬 Adesivo CAIXA DE PERGUNTA', quiz: '❓ Adesivo QUIZ' + (op ? ': ' + op : ''),
                 link: '🔗 Adesivo LINK para o site', contagem: '⏳ Adesivo CONTAGEM REGRESSIVA' }[s.adesivo] || 'Sem adesivo';
    }
    function legendaDe(item) { var p = item.post || {}; return (p.legenda || '') + '\n\n' + (p.hashtags || []).map(function (h) { return '#' + h; }).join(' '); }

    // ---------- calendário ----------
    function render() {
        var box = $('calendario'); if (!box) return;
        box.innerHTML = '';
        if (!itens.length) return;
        var topo = el('div', 'cal-acoes');
        topo.appendChild(botao('⬇ Baixar tudo (ZIP)', zip));
        topo.appendChild(el('span', 'muted', itens.filter(function (i) { return i.status === 'pronto' || i.status === 'postado'; }).length + ' de ' + itens.length + ' prontos · horários sugeridos pelas médias do Brasil (confira os do seu Insights)'));
        box.appendChild(topo);
        var porDia = {};
        itens.forEach(function (i) { (porDia[i.data] = porDia[i.data] || []).push(i); });
        Object.keys(porDia).sort().forEach(function (d) {
            var dt = new Date(d + 'T12:00:00');
            var sec = el('section', 'cal-dia');
            sec.appendChild(el('h4', '', DIAS[dt.getDay()] + ', ' + d.slice(8, 10) + '/' + d.slice(5, 7)));
            var grade = el('div', 'cal-itens');
            porDia[d].forEach(function (it) { grade.appendChild(cartao(it)); });
            sec.appendChild(grade);
            box.appendChild(sec);
        });
        if (location.hash.indexOf('#item-') === 0) { var alvo = document.querySelector(location.hash); if (alvo) alvo.scrollIntoView({ block: 'center' }); }
    }
    function cartao(it) {
        var c = el('article', 'cal-item' + (it.status === 'postado' ? ' postado' : '')); c.id = 'item-' + it.id;
        var topo = el('div', 'cal-topo');
        topo.appendChild(el('span', 'cal-tipo', (TIPOS[it.tipo] || it.tipo) + ' · ' + (it.tipo === 'story' ? HORAS_STORY.slice(0, Math.max(1, (it.post && it.post.stories || []).length)).join(', ') : it.hora)));
        topo.appendChild(el('span', 'muted', { pendente: '⏳ na fila', gerando: '⚙ gerando…', pronto: '✅ pronto', postado: '✔ postado', erro: '⚠ erro' }[it.status] || it.status));
        c.appendChild(topo);
        if (it.post) {
            var img = el('img', 'cal-thumb' + (formatoDo(it.tipo) === 'story' ? ' vertical' : '')); img.alt = 'Prévia: ' + (it.tema || TIPOS[it.tipo]); img.loading = 'lazy';
            c.appendChild(img); miniatura(it, img);
        }
        c.appendChild(el('p', 'cal-tema', it.tema || ''));
        if (it.post && (it.post.stories || []).length) {
            var ul = el('ol', 'cal-stories');
            it.post.stories.forEach(function (s, i) { ul.appendChild(el('li', '', (HORAS_STORY[i] || '') + ' — ' + s.texto + ' · ' + dicaAdesivo(s))); });
            c.appendChild(ul);
        }
        if (it.erro && it.status === 'erro') c.appendChild(el('p', 'cal-tema', '⚠ ' + it.erro));
        var ac = el('div', 'cal-acoes');
        if (it.post) {
            if (it.tipo === 'story') ac.appendChild(botao('⬇ Stories', function () { baixarPecas(it, true); }));
            else ac.appendChild(botao(it.tipo === 'carrossel' ? '⬇ Carrossel' : (it.tipo === 'reels' ? '⬇ Capa' : '⬇ Arte'), function () { baixarPecas(it, false); }));
            if (it.tipo === 'reels') ac.appendChild(botao('🎬 Vídeo narrado', function (b) { video(it, b); }));
            if (it.tipo !== 'story') ac.appendChild(botao('📋 Legenda', function () { copiar(legendaDe(it), 'Legenda copiada!'); }));
            if (it.tipo === 'reels' && it.post.prompt_video) ac.appendChild(botao('✨ Animar no Kling', function () { copiar(it.post.prompt_video, 'Prompt copiado! Cole em klingai.com → Image to Video, junto com a capa.'); window.open('https://klingai.com/', '_blank', 'noopener'); }));
            ac.appendChild(botao(it.status === 'postado' ? '↺ Desmarcar' : '✔ Postei', function () { api({ acao: 'item_postado', id: it.id }).then(carregar); }));
        }
        if (['pronto', 'erro', 'postado'].indexOf(it.status) >= 0) ac.appendChild(botao('↻ Refazer', function () { api({ acao: 'item_refazer', id: it.id }).then(function () { processar(); }); }));
        c.appendChild(ac);
        return c;
    }
    var fila = Promise.resolve();
    function miniatura(it, img) {
        fila = fila.then(function () {
            return imagem(it.foto).then(function (foto) {
                var pc = pecas(it, foto)[0]; if (!pc) return;
                var cv = desenhar(document.createElement('canvas'), pc.conteudo, pc.o);
                try { img.src = cv.toDataURL('image/jpeg', 0.7); } catch (e) { /* foto de outro site: sem miniatura */ }
            });
        });
    }
    function copiar(txt, msg) { (navigator.clipboard ? navigator.clipboard.writeText(txt) : Promise.reject()).then(function () { toast(msg, 'sucesso'); }, function () { window.prompt('Copie:', txt); }); }

    function baixarPecas(it, soStories) {
        return imagem(it.foto).then(function (foto) {
            var ps = pecas(it, foto).filter(function (p) { return soStories ? !!p.story : !p.story; });
            var seq = Promise.resolve();
            ps.forEach(function (p) {
                seq = seq.then(function () { return blobDe(desenhar(document.createElement('canvas'), p.conteudo, p.o)).then(function (b) { baixar(b, nomeBase(it) + '-' + p.nome); return new Promise(function (ok) { setTimeout(ok, 350); }); }); });
            });
            return seq.then(function () { toast(ps.length + (ps.length > 1 ? ' imagens baixadas — poste na ordem.' : ' imagem baixada.'), 'sucesso'); });
        });
    }

    // ---------- vídeo narrado ----------
    function vozDe(texto) {
        if (!texto) return Promise.resolve(null);
        if (CFG.voz) {
            return api({ acao: 'voz', texto: texto }).then(function (r) {
                if (!r.ok) { toast(r.erro, 'erro'); return null; }
                return fetch(r.url).then(function (x) { return x.arrayBuffer(); });
            });
        }
        // Sem Azure: voz grátis do Puter no navegador (pede login grátis na 1ª vez)
        var carregar = window.puter ? Promise.resolve() : new Promise(function (ok, erro) { var sc = document.createElement('script'); sc.src = 'https://js.puter.com/v2/'; sc.onload = ok; sc.onerror = erro; document.head.appendChild(sc); });
        var limite = new Promise(function (ok, erro) { setTimeout(function () { erro(new Error('tempo')); }, 20000); }); // sem login/rede não trava o vídeo
        return Promise.race([limite, carregar.then(function () { return window.puter.ai.txt2speech(texto, { language: 'pt-BR' }); })
            .then(function (audio) { return fetch(audio.src).then(function (x) { return x.arrayBuffer(); }); })])
            .catch(function () { toast('Sem narração agora (ligue a voz da Azure em Configurações). O vídeo sai com legendas.', 'info'); return null; });
    }
    function video(it, btn) {
        var p = it.post || {}, c = p.conteudo || {};
        var passos = (p.roteiro || []).slice(0, 7);
        if (!passos.length) passos = [{ texto_tela: c.titulo }, { texto_tela: c.subtitulo }, { texto_tela: c.cta }];
        var narracao = p.narracao || passos.map(function (x) { return x.fala || ''; }).join(' ');
        btn.disabled = true; var txt = btn.textContent; btn.textContent = 'Preparando a voz…';
        Promise.all([imagem(it.foto), vozDe(narracao)]).then(function (r) {
            var foto = r[0], audio = r[1];
            var cenas = passos.map(function (x, i, arr) {
                var t = x.texto_tela || c.titulo;
                if (i === 0) return { modelo: 'kinetic', segundos: 2.6, conteudo: { titulo: t }, foto: foto };
                if (i === arr.length - 1) return { modelo: c.preco ? 'oferta' : (foto ? 'foto_titulo' : 'impacto'), segundos: 3.4, conteudo: Object.assign({}, c, { titulo: t }), foto: foto };
                return foto ? { modelo: i % 2 ? 'foto_titulo' : 'foto_split', segundos: 3, conteudo: { titulo: t, subtitulo: '' }, foto: foto } : { modelo: i % 2 ? 'impacto' : 'numero', segundos: 3, conteudo: { titulo: t, preco: c.preco || '' } };
            });
            var tela = document.createElement('canvas'); tela.style.cssText = 'position:fixed;left:-99999px;top:0'; document.body.appendChild(tela);
            btn.textContent = 'Gravando o vídeo…';
            return window.GDSCriativos.gravarVideo(tela, cenas, Object.assign(opcoes(it, null, foto, 'story'), { audio: audio })).then(function (blob) {
                tela.remove();
                baixar(blob, nomeBase(it) + (blob.type.indexOf('mp4') >= 0 ? '.mp4' : '.webm'));
                toast(audio ? 'Vídeo narrado pronto!' : 'Vídeo pronto (sem narração).', 'sucesso');
            });
        }).catch(function (e) { toast(e.message || 'Não foi possível gravar o vídeo.', 'erro'); })
          .then(function () { btn.disabled = false; btn.textContent = txt; });
    }

    // ---------- ZIP com tudo ----------
    function zip(btn) {
        if (!window.JSZip) { toast('Carregando… tente de novo em 2 segundos.', 'info'); return; }
        var z = new window.JSZip(), prontos = itens.filter(function (i) { return i.post; });
        btn.disabled = true; btn.textContent = 'Montando o ZIP…';
        var seq = Promise.resolve();
        prontos.forEach(function (it, n) {
            seq = seq.then(function () {
                return imagem(it.foto).then(function (foto) {
                    var pasta = z.folder(String(n + 1).padStart(2, '0') + '-' + nomeBase(it));
                    var p = it.post;
                    if (it.tipo !== 'story') pasta.file('legenda.txt', legendaDe(it));
                    if (it.tipo === 'reels') pasta.file('roteiro-e-narracao.txt', (p.roteiro || []).map(function (r) { return (r.tempo || '') + ' | TELA: ' + (r.texto_tela || '') + ' | FALA: ' + (r.fala || ''); }).join('\n') + '\n\nNARRAÇÃO:\n' + (p.narracao || '') + '\n\nPROMPT PARA ANIMAR A FOTO (Kling/Dreamina):\n' + (p.prompt_video || ''));
                    if ((p.stories || []).length) pasta.file('stories.txt', p.stories.map(function (s, i) { return 'Story ' + (i + 1) + ' (' + (HORAS_STORY[i] || '') + '): ' + s.texto + '\n   ' + dicaAdesivo(s); }).join('\n'));
                    var ps = pecas(it, foto), q = Promise.resolve();
                    ps.forEach(function (pc) { q = q.then(function () { return blobDe(desenhar(document.createElement('canvas'), pc.conteudo, pc.o)).then(function (b) { pasta.file(pc.nome, b); }); }); });
                    return q;
                });
            });
        });
        seq.then(function () { return z.generateAsync({ type: 'blob' }); })
           .then(function (b) { baixar(b, 'plano-de-marketing.zip'); toast('ZIP com ' + prontos.length + ' posts baixado. Os vídeos saem no botão "Vídeo narrado" de cada Reels.', 'sucesso'); })
           .catch(function (e) { toast('Não montou o ZIP: ' + e.message, 'erro'); })
           .then(function () { btn.disabled = false; btn.textContent = '⬇ Baixar tudo (ZIP)'; });
    }

    // ---------- gerar ----------
    function plano() { return Number(($('calendario') || {}).dataset ? $('calendario').dataset.plano : 0) || 0; }
    function carregar() {
        var pid = plano(); if (!pid) return Promise.resolve();
        return api({ acao: 'plano_itens', plano_id: pid }).then(function (r) { if (r.ok) { itens = r.itens; render(); } return r; });
    }
    function barra(feitos, total) {
        $('plBarra').hidden = false; $('plBarraIn').style.width = (total ? Math.round(100 * feitos / total) : 0) + '%';
        $('plStatus').textContent = feitos >= total ? '✅ Plano pronto! ' + total + ' posts e stories prontos para postar.' : 'A IA está criando o post ' + Math.min(total, feitos + 1) + ' de ' + total + ' (texto + foto)… pode deixar a tela aberta ou voltar depois.';
    }
    function processar() {
        if (rodando || !plano()) return;
        rodando = true;
        var volta = function (n) {
            return api({ acao: 'plano_processar', plano_id: plano() }).then(function (r) {
                if (!r.ok) { $('plStatus').textContent = r.erro || 'Erro'; rodando = false; return; }
                barra(r.feitos, r.total);
                if (n % 2 === 0 || r.terminou) carregar();
                if (r.terminou || !r.item) { rodando = false; carregar(); return; }
                return volta(n + 1);
            });
        };
        volta(0);
    }
    function criar(btn) {
        btn.disabled = true; $('plStatus').textContent = 'Montando o calendário (temas e horários)…';
        api({ acao: 'plano_criar', periodo: escolhido('plPeriodo') || 'semana', por_semana: escolhido('plPorSemana') || 5, stories_dia: escolhido('plStories') || 2, publico: ($('plPublico') || {}).value || '' })
            .then(function (r) {
                btn.disabled = false;
                if (!r.ok) { toast(r.erro, 'erro'); return; }
                $('calendario').dataset.plano = r.plano_id;
                history.replaceState(null, '', '?plano=' + r.plano_id + '#plano');
                carregar().then(processar);
            });
    }
    function lembrete(soNumero) {
        var b = $('plLembrete');
        if (!soNumero) b.setAttribute('aria-pressed', b.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
        api({ acao: 'lembrete_salvar', ligado: b.getAttribute('aria-pressed') === 'true' ? '1' : '0', numero: $('plNumero').value }).then(function (r) { if (r.ok) toast('Salvo!', 'sucesso'); });
    }

    window.MKTPLANO = { criar: criar, lembrete: lembrete, carregar: carregar };
    // Abre o plano escolhido e continua sozinho se ainda tiver post na fila
    function iniciar() { carregar().then(function () { if (itens.some(function (i) { return i.status === 'pendente' || i.status === 'gerando'; })) processar(); }); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();
})();
