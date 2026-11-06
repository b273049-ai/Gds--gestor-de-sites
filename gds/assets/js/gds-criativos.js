/* ============================================================
   GDS CRIATIVOS — gera artes (PNG) e vídeos curtos (WebM/MP4) para
   Instagram e TikTok direto no navegador, com <canvas>.
   GDSCriativos.desenhar(canvas, conteudo, opcoes)
   GDSCriativos.gravarVideo(canvas, cenas, opcoes) → Promise<Blob>
   conteudo: { titulo, subtitulo, itens[], cta, selo, citacao, autor, antes[], depois[], preco }
   opcoes:   { modelo: impacto|lista|antes_depois|depoimento|oferta|foto_titulo|foto_split|pergunta|numero|slide|kinetic,
               formato: feed|story|quadrado, cor: '#6d7cff', cor2: '#22d3ee', marca: 'GDS', arroba: '@gds',
               site: 'gds.com.br', escuro: true, estilo: neon|editorial|bold|pop|minimal|luxo, foto: <img>|null }
   GDSCriativos.estilos / .paletas = presets para variar o visual de cada post.
   ============================================================ */
(function () {
    'use strict';
    var FORMATOS = { feed: [1080, 1350], story: [1080, 1920], quadrado: [1080, 1080] };
    var FONTE_T = '"Sora", "Inter", system-ui, sans-serif';
    var FONTE_C = '"Inter", system-ui, sans-serif';
    var PESO_T = '800', CAIXA = false;
    // Estilos visuais: fonte do título + tipo de fundo (cada post pode sair com uma cara diferente)
    var ESTILOS = {
        neon:      { nome: 'Neon',      fonte: '"Sora", "Inter", sans-serif',                  peso: '800', fundo: 'bolhas' },
        editorial: { nome: 'Editorial', fonte: '"Playfair Display", Georgia, serif',          peso: '700', fundo: 'papel' },
        bold:      { nome: 'Bold',      fonte: '"Bebas Neue", "Sora", sans-serif',            peso: '400', fundo: 'solido', caixa: true },
        pop:       { nome: 'Pop',       fonte: '"Bricolage Grotesque", "Sora", sans-serif',   peso: '800', fundo: 'listras' },
        minimal:   { nome: 'Minimal',   fonte: '"Inter", system-ui, sans-serif',              peso: '800', fundo: 'liso' },
        luxo:      { nome: 'Luxo',      fonte: '"Cormorant Garamond", Georgia, serif',        peso: '700', fundo: 'grao' },
    };
    var PALETAS = [
        { nome: 'Elétrico', cor: '#6d7cff', cor2: '#22d3ee', escuro: true },
        { nome: 'Fogo',     cor: '#ff5a1f', cor2: '#ffb020', escuro: true },
        { nome: 'Menta',    cor: '#10b981', cor2: '#a3e635', escuro: true },
        { nome: 'Rosa',     cor: '#e11d74', cor2: '#f9a8d4', escuro: false },
        { nome: 'Ouro',     cor: '#c9a227', cor2: '#f4e1a1', escuro: true },
        { nome: 'Oceano',   cor: '#0ea5e9', cor2: '#1e3a8a', escuro: false },
        { nome: 'Uva',      cor: '#a855f7', cor2: '#f472b6', escuro: true },
        { nome: 'Terra',    cor: '#b45309', cor2: '#1c1917', escuro: false },
    ];

    function hexRgb(h) { h = (h || '#6d7cff').replace('#', ''); return [parseInt(h.substr(0, 2), 16), parseInt(h.substr(2, 2), 16), parseInt(h.substr(4, 2), 16)]; }
    function rgba(h, a) { var c = hexRgb(h); return 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',' + a + ')'; }
    function contraste(h) { var c = hexRgb(h); return (0.299 * c[0] + 0.587 * c[1] + 0.114 * c[2]) / 255 > 0.6 ? '#0b0d17' : '#ffffff'; }
    function rr(ctx, x, y, w, h, r) { ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r); ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath(); }

    // Quebra o texto em linhas que cabem na largura; reduz a fonte até caber em maxLinhas
    function caber(ctx, texto, larg, tam, peso, fonte, maxLinhas, minTam) {
        texto = String(texto || '');
        for (var t = tam; t >= (minTam || 28); t -= 4) {
            ctx.font = peso + ' ' + t + 'px ' + fonte;
            var linhas = quebrar(ctx, texto, larg);
            if (linhas.length <= maxLinhas) return { linhas: linhas, tam: t };
        }
        ctx.font = peso + ' ' + (minTam || 28) + 'px ' + fonte;
        return { linhas: quebrar(ctx, texto, larg).slice(0, maxLinhas), tam: minTam || 28 };
    }
    function quebrar(ctx, texto, larg) {
        var saida = [];
        texto.split('\n').forEach(function (par) {
            var pal = par.split(/\s+/), linha = '';
            pal.forEach(function (p) {
                var teste = linha ? linha + ' ' + p : p;
                if (ctx.measureText(teste.replace(/\*/g, '')).width > larg && linha) { saida.push(linha); linha = p; } else linha = teste;
            });
            if (linha) saida.push(linha);
        });
        return saida;
    }
    // Escreve linhas; palavras entre *asteriscos* ficam na cor de destaque
    function escrever(ctx, bloco, x, y, alturaLinha, cor, corDestaque, alinhar) {
        var alinhAntes = ctx.textAlign; ctx.textAlign = 'left';
        var destacando = false; // o *destaque* pode atravessar palavras e linhas
        bloco.linhas.forEach(function (l, i) {
            var partes = [], atual = '', estado = destacando;
            for (var k = 0; k < l.length; k++) {
                if (l[k] === '*') { if (atual) partes.push([atual, estado]); atual = ''; estado = !estado; continue; }
                atual += l[k];
            }
            if (atual) partes.push([atual, estado]);
            destacando = estado;
            var total = partes.reduce(function (s2, p) { return s2 + ctx.measureText(p[0]).width; }, 0);
            var cx = alinhar === 'center' ? x - total / 2 : x;
            partes.forEach(function (p) {
                ctx.fillStyle = p[1] ? corDestaque : cor;
                ctx.fillText(p[0], cx, y + i * alturaLinha);
                cx += ctx.measureText(p[0]).width;
            });
        });
        ctx.textAlign = alinhAntes;
        return y + bloco.linhas.length * alturaLinha;
    }

    function fundo(ctx, W, H, o, t) {
        t = t || 0;
        var base = o.escuro ? '#080a16' : '#f6f4ff', tipo = o.fundo || 'bolhas';
        if (tipo === 'solido') base = o.escuro ? '#0b0b0d' : o.cor;
        if (tipo === 'papel') base = o.escuro ? '#14110e' : '#f4efe6';
        if (tipo === 'grao') base = o.escuro ? '#0d0b09' : '#efe9df';
        ctx.fillStyle = base; ctx.fillRect(0, 0, W, H);
        if (tipo === 'bolhas' || tipo === 'grao') {
            var bolhas = [[0.15 + Math.sin(t * 0.9) * 0.05, 0.12, 0.7, o.cor], [0.9, 0.55 + Math.cos(t * 0.7) * 0.05, 0.6, o.cor2], [0.3, 0.95, 0.55, o.cor]];
            bolhas.forEach(function (b) {
                var g = ctx.createRadialGradient(b[0] * W, b[1] * H, 0, b[0] * W, b[1] * H, b[2] * W);
                g.addColorStop(0, rgba(b[3], (o.escuro ? 0.55 : 0.35) * (tipo === 'grao' ? 0.5 : 1))); g.addColorStop(1, rgba(b[3], 0));
                ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
            });
        }
        if (tipo === 'bolhas') {
            ctx.strokeStyle = o.escuro ? 'rgba(255,255,255,.035)' : 'rgba(10,10,40,.04)'; ctx.lineWidth = 1;
            for (var gx = 0; gx < W; gx += 60) { ctx.beginPath(); ctx.moveTo(gx, 0); ctx.lineTo(gx, H); ctx.stroke(); }
            for (var gy = 0; gy < H; gy += 60) { ctx.beginPath(); ctx.moveTo(0, gy); ctx.lineTo(W, gy); ctx.stroke(); }
        } else if (tipo === 'listras') {
            ctx.save(); ctx.globalAlpha = o.escuro ? 0.12 : 0.18; ctx.strokeStyle = o.cor; ctx.lineWidth = 26;
            var d = (t * 40) % 90;
            for (var x = -H; x < W + H; x += 90) { ctx.beginPath(); ctx.moveTo(x + d, 0); ctx.lineTo(x + d + H, H); ctx.stroke(); }
            ctx.restore();
        } else if (tipo === 'solido') {
            ctx.fillStyle = o.escuro ? o.cor : rgba('#000000', 0.08);
            ctx.beginPath(); ctx.arc(W * 1.02, H * 0.06, W * 0.42, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = o.escuro ? rgba(o.cor2, 0.9) : rgba('#ffffff', 0.18);
            ctx.fillRect(0, H - 22, W, 22);
        } else if (tipo === 'papel') {
            ctx.strokeStyle = o.escuro ? 'rgba(255,255,255,.08)' : 'rgba(30,20,10,.12)'; ctx.lineWidth = 2;
            ctx.strokeRect(36, 36, W - 72, H - 72);
            ctx.fillStyle = o.cor; ctx.fillRect(70, H * 0.105, 90, 8);
        } else if (tipo === 'liso') {
            ctx.fillStyle = rgba(o.cor, o.escuro ? 0.22 : 0.12); ctx.fillRect(0, 0, 18, H);
        }
        if (tipo === 'grao') {
            // ruído leve (determinístico para não "piscar" no vídeo)
            var sem = 7; ctx.fillStyle = o.escuro ? 'rgba(255,255,255,.035)' : 'rgba(0,0,0,.04)';
            for (var k = 0; k < 2600; k++) { sem = (sem * 9301 + 49297) % 233280; var rx = sem / 233280 * W; sem = (sem * 9301 + 49297) % 233280; ctx.fillRect(rx, sem / 233280 * H, 2, 2); }
        }
    }

    // Foto cobrindo a área (com zoom/pan lento "Ken Burns" no vídeo)
    function fotoCobrir(ctx, img, x, y, w, h, t, sentido) {
        if (!img || !img.width) return false;
        var esc = 1.06 + (t || 0) * 0.035, ir = img.width / img.height, ar = w / h;
        var dw = ir > ar ? h * ir : w, dh = ir > ar ? h : w / ir;
        dw *= esc; dh *= esc;
        var px = (sentido || 1) * (t || 0) * 14;
        ctx.save(); ctx.beginPath(); ctx.rect(x, y, w, h); ctx.clip();
        ctx.drawImage(img, x + (w - dw) / 2 + px, y + (h - dh) / 2, dw, dh);
        ctx.restore();
        return true;
    }
    function semFoto(ctx, x, y, w, h, o) {
        var g = ctx.createLinearGradient(x, y, x + w, y + h); g.addColorStop(0, o.cor); g.addColorStop(1, o.cor2);
        ctx.fillStyle = g; ctx.fillRect(x, y, w, h);
        ctx.fillStyle = 'rgba(255,255,255,.75)'; ctx.font = '600 30px ' + FONTE_C; ctx.textAlign = 'center';
        ctx.fillText('Escolha uma foto ao lado →', x + w / 2, y + h / 2); ctx.textAlign = 'left';
    }
    function realce(o) { return o.escuro || o.fundo === 'solido' ? o.cor2 : o.cor; }
    function titulo(ctx, texto, larg, tam, maxLinhas, minTam) {
        var b = caber(ctx, CAIXA ? String(texto || '').toUpperCase() : texto, larg, CAIXA ? tam * 1.25 : tam, PESO_T, FONTE_T, maxLinhas, minTam);
        ctx.font = PESO_T + ' ' + b.tam + 'px ' + FONTE_T;
        return b;
    }

    function marcaTopo(ctx, W, o, txt) {
        ctx.font = '800 40px ' + FONTE_T; ctx.fillStyle = txt; ctx.textBaseline = 'alphabetic';
        rr(ctx, 70, 66, 64, 64, 18); var g = ctx.createLinearGradient(70, 66, 134, 130); g.addColorStop(0, o.cor); g.addColorStop(1, o.cor2); ctx.fillStyle = g; ctx.fill();
        ctx.fillStyle = '#fff'; ctx.font = '800 34px ' + FONTE_T; ctx.textAlign = 'center'; ctx.fillText((o.marca || 'G').charAt(0), 102, 111);
        ctx.textAlign = 'left'; ctx.fillStyle = txt; ctx.font = '700 34px ' + FONTE_T; ctx.fillText(o.marca || 'GDS', 152, 110);
        if (o.arroba) { ctx.textAlign = 'right'; ctx.font = '500 28px ' + FONTE_C; ctx.fillStyle = rgba(txt === '#ffffff' ? '#ffffff' : '#0b0d17', 0.6); ctx.fillText(o.arroba, W - 70, 108); ctx.textAlign = 'left'; }
    }
    function rodape(ctx, W, H, o, txt) {
        ctx.font = '600 28px ' + FONTE_C; ctx.fillStyle = rgba(txt === '#ffffff' ? '#ffffff' : '#0b0d17', 0.65);
        ctx.textAlign = 'center'; ctx.fillText(o.site || '', W / 2, H - 60); ctx.textAlign = 'left';
    }
    function botao(ctx, x, y, texto, o, centro) {
        ctx.font = '800 40px ' + FONTE_T;
        var w = ctx.measureText(texto).width + 110, h = 104;
        if (centro) x = x - w / 2;
        ctx.save(); ctx.shadowColor = rgba(o.cor, 0.6); ctx.shadowBlur = 50; ctx.shadowOffsetY = 18;
        rr(ctx, x, y, w, h, h / 2); var g = ctx.createLinearGradient(x, y, x + w, y + h); g.addColorStop(0, o.cor); g.addColorStop(1, o.cor2); ctx.fillStyle = g; ctx.fill(); ctx.restore();
        ctx.fillStyle = contraste(o.cor); ctx.fillText(texto + '  →', x + 55, y + 66);
        return y + h;
    }
    function selo(ctx, x, y, texto, o, txt) {
        ctx.font = '700 28px ' + FONTE_C;
        var w = ctx.measureText(texto).width + 70;
        rr(ctx, x, y, w, 58, 29); ctx.fillStyle = rgba(o.cor, 0.18); ctx.fill(); ctx.strokeStyle = rgba(o.cor, 0.55); ctx.lineWidth = 2; ctx.stroke();
        ctx.beginPath(); ctx.arc(x + 30, y + 29, 7, 0, Math.PI * 2); ctx.fillStyle = o.cor; ctx.fill();
        ctx.fillStyle = txt; ctx.fillText(texto, x + 48, y + 39);
        return y + 58;
    }

    // Celular com um site dentro (mockup do produto)
    function celular(ctx, x, y, w, o, t, notifDentro) {
        t = t || 0;
        var h = w * 2.05, r = w * 0.14;
        ctx.save();
        ctx.shadowColor = 'rgba(0,0,0,.45)'; ctx.shadowBlur = 80; ctx.shadowOffsetY = 40;
        rr(ctx, x, y, w, h, r); ctx.fillStyle = '#0d0f1a'; ctx.fill(); ctx.restore();
        var m = w * 0.045, sx = x + m, sy = y + m, sw = w - m * 2, sh = h - m * 2;
        ctx.save(); rr(ctx, sx, sy, sw, sh, r * 0.8); ctx.clip();
        ctx.fillStyle = '#0b0e1f'; ctx.fillRect(sx, sy, sw, sh);
        // topo do site
        var g = ctx.createLinearGradient(sx, sy, sx + sw, sy + sh * 0.5); g.addColorStop(0, o.cor); g.addColorStop(1, o.cor2);
        ctx.fillStyle = g; ctx.fillRect(sx, sy, sw, sh * 0.46);
        ctx.fillStyle = 'rgba(0,0,0,.18)'; ctx.fillRect(sx, sy, sw, sh * 0.46);
        ctx.fillStyle = 'rgba(255,255,255,.95)'; rr(ctx, sx + sw * 0.08, sy + sh * 0.05, sw * 0.32, sh * 0.022, 6); ctx.fill();
        ctx.font = '800 ' + Math.round(sw * 0.105) + 'px ' + FONTE_T; ctx.fillStyle = '#fff';
        ctx.fillText(o.siteTitulo1 || 'Seu negócio', sx + sw * 0.08, sy + sh * 0.2);
        ctx.fillText(o.siteTitulo2 || 'online hoje.', sx + sw * 0.08, sy + sh * 0.2 + sw * 0.12);
        ctx.fillStyle = 'rgba(255,255,255,.75)'; rr(ctx, sx + sw * 0.08, sy + sh * 0.31, sw * 0.7, sh * 0.014, 5); ctx.fill(); rr(ctx, sx + sw * 0.08, sy + sh * 0.335, sw * 0.5, sh * 0.014, 5); ctx.fill();
        var pulso = 1 + Math.sin(t * 4) * 0.03;
        ctx.save(); ctx.translate(sx + sw * 0.08 + sw * 0.25, sy + sh * 0.39 + sh * 0.02); ctx.scale(pulso, pulso);
        rr(ctx, -sw * 0.25, -sh * 0.02, sw * 0.5, sh * 0.045, sh * 0.022); ctx.fillStyle = '#fff'; ctx.fill();
        ctx.font = '700 ' + Math.round(sw * 0.045) + 'px ' + FONTE_C; ctx.fillStyle = '#111'; ctx.textAlign = 'center'; ctx.fillText(o.siteBotao || 'Agendar agora', 0, sh * 0.009); ctx.textAlign = 'left';
        ctx.restore();
        // cartões de serviço
        for (var i = 0; i < 3; i++) {
            var cy = sy + sh * 0.5 + i * sh * 0.155;
            rr(ctx, sx + sw * 0.06, cy, sw * 0.88, sh * 0.13, 16); ctx.fillStyle = 'rgba(255,255,255,.06)'; ctx.fill();
            ctx.strokeStyle = 'rgba(255,255,255,.08)'; ctx.stroke();
            ctx.fillStyle = 'rgba(255,255,255,.85)'; rr(ctx, sx + sw * 0.11, cy + sh * 0.03, sw * 0.4, sh * 0.016, 5); ctx.fill();
            ctx.fillStyle = 'rgba(255,255,255,.35)'; rr(ctx, sx + sw * 0.11, cy + sh * 0.06, sw * 0.28, sh * 0.012, 5); ctx.fill();
            rr(ctx, sx + sw * 0.62, cy + sh * 0.04, sw * 0.26, sh * 0.05, sh * 0.025); ctx.fillStyle = o.cor; ctx.fill();
        }
        ctx.restore();
        // notificação de WhatsApp
        var ny = y + h * (notifDentro ? 0.62 : 0.3) + Math.sin(t * 2) * 6;
        if (notifDentro) x = x + w * 0.42 + w * 0.025; // bolha sobre a tela, sem invadir o texto ao lado
        ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.35)'; ctx.shadowBlur = 40; ctx.shadowOffsetY = 14;
        rr(ctx, x - w * 0.42, ny, w * 0.95, w * 0.26, 26); ctx.fillStyle = '#ffffff'; ctx.fill(); ctx.restore();
        ctx.beginPath(); ctx.arc(x - w * 0.42 + w * 0.13, ny + w * 0.13, w * 0.075, 0, Math.PI * 2); ctx.fillStyle = '#25d366'; ctx.fill();
        ctx.fillStyle = '#fff'; ctx.font = '800 ' + Math.round(w * 0.07) + 'px ' + FONTE_C; ctx.textAlign = 'center'; ctx.fillText('✓', x - w * 0.42 + w * 0.13, ny + w * 0.155); ctx.textAlign = 'left';
        ctx.fillStyle = '#0b0d17'; ctx.font = '700 ' + Math.round(w * 0.058) + 'px ' + FONTE_C; ctx.fillText(o.notifTitulo || 'Novo agendamento!', x - w * 0.42 + w * 0.25, ny + w * 0.115);
        ctx.fillStyle = '#5b6170'; ctx.font = '500 ' + Math.round(w * 0.048) + 'px ' + FONTE_C; ctx.fillText(o.notifTexto || 'Maria · amanhã às 15:00', x - w * 0.42 + w * 0.25, ny + w * 0.185);
    }

    // ---------- modelos ----------
    var MODELOS = {
        // Foto inteira com título forte embaixo (o mais "Instagram" de todos)
        foto_titulo: function (ctx, W, H, c, o, t) {
            if (!fotoCobrir(ctx, o.foto, 0, 0, W, H, t, 1)) semFoto(ctx, 0, 0, W, H, o);
            var g = ctx.createLinearGradient(0, H * 0.3, 0, H); g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(0.55, 'rgba(0,0,0,.62)'); g.addColorStop(1, 'rgba(0,0,0,.88)');
            ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
            var gt = ctx.createLinearGradient(0, 0, 0, 220); gt.addColorStop(0, 'rgba(0,0,0,.55)'); gt.addColorStop(1, 'rgba(0,0,0,0)'); ctx.fillStyle = gt; ctx.fillRect(0, 0, W, 220);
            marcaTopo(ctx, W, o, '#ffffff');
            var b = titulo(ctx, c.titulo, W - 140, H / W > 1.5 ? 100 : 86, 4, 48);
            var s = c.subtitulo ? caber(ctx, c.subtitulo, W - 140, 38, '500', FONTE_C, 3, 26) : null;
            var altura = b.linhas.length * b.tam * 1.05 + (s ? s.linhas.length * s.tam * 1.35 + 30 : 0) + (c.cta ? 150 : 0);
            var y = H - 190 - altura;
            if (c.selo) { selo(ctx, 70, y - 90, c.selo, o, '#ffffff'); }
            var entra = t ? Math.min(1, t / 0.6) : 1;
            ctx.globalAlpha = entra;
            ctx.font = PESO_T + ' ' + b.tam + 'px ' + FONTE_T;
            y = escrever(ctx, b, 70, y + b.tam + (1 - entra) * 40, b.tam * 1.05, '#ffffff', realce(o));
            if (s) { ctx.font = '500 ' + s.tam + 'px ' + FONTE_C; y = escrever(ctx, s, 70, y + s.tam + 24, s.tam * 1.35, 'rgba(255,255,255,.86)', realce(o)); }
            if (c.cta) botao(ctx, 70, y + 40, c.cta, o);
            ctx.globalAlpha = 1;
            rodape(ctx, W, H, o, '#ffffff');
        },
        // Foto em cima, bloco de cor com o texto embaixo
        foto_split: function (ctx, W, H, c, o, t) {
            var txt = o.escuro ? '#ffffff' : '#0b0d17';
            fundo(ctx, W, H, o, t);
            var hf = Math.round(H * (H / W > 1.5 ? 0.5 : 0.48));
            if (!fotoCobrir(ctx, o.foto, 0, 0, W, hf, t, -1)) semFoto(ctx, 0, 0, W, hf, o);
            ctx.fillStyle = o.cor; ctx.fillRect(0, hf - 10, W, 10);
            var gt = ctx.createLinearGradient(0, 0, 0, 200); gt.addColorStop(0, 'rgba(0,0,0,.5)'); gt.addColorStop(1, 'rgba(0,0,0,0)'); ctx.fillStyle = gt; ctx.fillRect(0, 0, W, 200);
            marcaTopo(ctx, W, o, '#ffffff');
            var y = hf + 60;
            if (c.selo) y = selo(ctx, 70, y, c.selo, o, txt) + 30;
            var b = titulo(ctx, c.titulo, W - 140, 74, 3, 44);
            y = escrever(ctx, b, 70, y + b.tam, b.tam * 1.06, txt, o.cor);
            if (c.subtitulo) { var s = caber(ctx, c.subtitulo, W - 140, 34, '500', FONTE_C, 3, 24); ctx.font = '500 ' + s.tam + 'px ' + FONTE_C; y = escrever(ctx, s, 70, y + s.tam + 20, s.tam * 1.35, rgba(txt, 0.75), o.cor); }
            if (c.cta && y + 150 < H - 110) botao(ctx, 70, y + 36, c.cta, o);
            rodape(ctx, W, H, o, txt);
        },
        // Pergunta-gancho gigante (para o 1º slide do carrossel ou a capa do Reels)
        pergunta: function (ctx, W, H, c, o, t) {
            var txt = o.escuro ? '#ffffff' : '#0b0d17';
            fundo(ctx, W, H, o, t); marcaTopo(ctx, W, o, txt);
            if (o.foto) { ctx.save(); ctx.globalAlpha = 0.22; fotoCobrir(ctx, o.foto, 0, 0, W, H, t); ctx.restore(); }
            ctx.font = PESO_T + ' ' + Math.round(W * 0.5) + 'px ' + FONTE_T; ctx.fillStyle = rgba(o.cor, 0.22); ctx.textAlign = 'right'; ctx.fillText('?', W - 40, H * 0.62); ctx.textAlign = 'left';
            var b = titulo(ctx, c.titulo, W - 160, H / W > 1.5 ? 116 : 96, 5, 52);
            var alt = b.linhas.length * b.tam * 1.04;
            var y = escrever(ctx, b, 70, (H - alt) / 2 + b.tam * 0.8, b.tam * 1.04, txt, o.cor);
            if (c.subtitulo) { var s = caber(ctx, c.subtitulo, W - 160, 36, '500', FONTE_C, 2, 26); ctx.font = '500 ' + s.tam + 'px ' + FONTE_C; escrever(ctx, s, 70, y + s.tam + 26, s.tam * 1.35, rgba(txt, 0.7), o.cor); }
            ctx.font = '700 30px ' + FONTE_C; ctx.fillStyle = o.cor; ctx.textAlign = 'right'; ctx.fillText('arraste →', W - 70, H - 64); ctx.textAlign = 'left';
            ctx.fillStyle = rgba(txt, 0.55); ctx.font = '600 28px ' + FONTE_C; ctx.fillText(o.arroba || '', 70, H - 64);
        },
        // Um número/afirmação enorme (ex.: "24h" agenda aberta)
        numero: function (ctx, W, H, c, o, t) {
            var txt = o.escuro ? '#ffffff' : '#0b0d17';
            fundo(ctx, W, H, o, t); marcaTopo(ctx, W, o, txt);
            var n = String(c.preco || c.selo || '24h');
            var tam = Math.min(W * 0.5, (W - 140) / Math.max(1, n.length * 0.55));
            ctx.font = PESO_T + ' ' + Math.round(tam) + 'px ' + FONTE_T;
            var g = ctx.createLinearGradient(0, H * 0.2, W, H * 0.5); g.addColorStop(0, o.cor); g.addColorStop(1, o.cor2);
            ctx.fillStyle = g; var esc = t ? Math.min(1, 0.85 + t * 0.4) : 1;
            ctx.save(); ctx.translate(70, H * 0.2 + tam * 0.85); ctx.scale(esc, esc); ctx.fillText(n, 0, 0); ctx.restore();
            var y = H * 0.2 + tam + 30;
            var b = titulo(ctx, c.titulo, W - 140, 70, 4, 42);
            y = escrever(ctx, b, 70, y + b.tam, b.tam * 1.08, txt, o.cor);
            if (c.subtitulo) { var s = caber(ctx, c.subtitulo, W - 140, 34, '500', FONTE_C, 3, 24); ctx.font = '500 ' + s.tam + 'px ' + FONTE_C; y = escrever(ctx, s, 70, y + s.tam + 20, s.tam * 1.35, rgba(txt, 0.72), o.cor); }
            if (c.cta && y + 160 < H - 110) botao(ctx, 70, y + 40, c.cta, o);
            rodape(ctx, W, H, o, txt);
        },
        // Slide numerado do carrossel (c.numero, c.total, c.titulo, c.subtitulo)
        slide: function (ctx, W, H, c, o, t) {
            var txt = o.escuro ? '#ffffff' : '#0b0d17';
            fundo(ctx, W, H, o, t); marcaTopo(ctx, W, o, txt);
            ctx.font = PESO_T + ' 220px ' + FONTE_T; ctx.fillStyle = rgba(o.cor, 0.9); ctx.fillText(String(c.numero || 1).padStart(2, '0'), 64, H * 0.36);
            var y = H * 0.36 + 60;
            var b = titulo(ctx, c.titulo, W - 140, 80, 4, 46);
            y = escrever(ctx, b, 70, y + b.tam, b.tam * 1.08, txt, realce(o));
            if (c.subtitulo) { var s = caber(ctx, c.subtitulo, W - 140, 36, '500', FONTE_C, 4, 26); ctx.font = '500 ' + s.tam + 'px ' + FONTE_C; escrever(ctx, s, 70, y + s.tam + 24, s.tam * 1.4, rgba(txt, 0.75), realce(o)); }
            if (o.foto) { var r = 150; ctx.save(); ctx.beginPath(); ctx.arc(W - 70 - r, H * 0.2, r, 0, Math.PI * 2); ctx.clip(); fotoCobrir(ctx, o.foto, W - 70 - 2 * r, H * 0.2 - r, 2 * r, 2 * r, t); ctx.restore(); ctx.strokeStyle = o.cor; ctx.lineWidth = 8; ctx.beginPath(); ctx.arc(W - 70 - r, H * 0.2, r, 0, Math.PI * 2); ctx.stroke(); }
            // bolinhas de progresso do carrossel
            var tot = c.total || 1, bx = W / 2 - (tot * 26) / 2;
            for (var i = 0; i < tot; i++) { ctx.beginPath(); ctx.arc(bx + i * 26 + 8, H - 70, i + 1 === (c.numero || 1) ? 9 : 6, 0, Math.PI * 2); ctx.fillStyle = i + 1 === (c.numero || 1) ? o.cor : rgba(txt, 0.25); ctx.fill(); }
        },
        // Tipografia em movimento: as palavras entram uma a uma, gigantes (cena de vídeo)
        kinetic: function (ctx, W, H, c, o, t) {
            var claro = !o.escuro;
            ctx.fillStyle = claro ? o.cor : '#07070a'; ctx.fillRect(0, 0, W, H);
            if (o.foto) { ctx.save(); ctx.globalAlpha = 0.3; fotoCobrir(ctx, o.foto, 0, 0, W, H, t); ctx.restore(); }
            var palavras = String(c.titulo || '').replace(/\*/g, '').split(/\s+/).filter(Boolean).slice(0, 9);
            var vis = t ? Math.min(palavras.length, Math.floor(t / 0.32) + 1) : palavras.length;
            var tam = Math.min(H / W > 1.5 ? 150 : 120, (W - 140) / 5);
            ctx.font = PESO_T + ' ' + tam + 'px ' + FONTE_T;
            var linhas = [], atual = '';
            palavras.slice(0, vis).forEach(function (p) { var tst = atual ? atual + ' ' + p : p; if (ctx.measureText(CAIXA ? tst.toUpperCase() : tst).width > W - 140 && atual) { linhas.push(atual); atual = p; } else atual = tst; });
            if (atual) linhas.push(atual);
            var y0 = H / 2 - (linhas.length * tam * 1.02) / 2 + tam * 0.8;
            linhas.forEach(function (l, i) {
                var ult = i === linhas.length - 1;
                ctx.fillStyle = ult ? (claro ? '#ffffff' : o.cor) : (claro ? contraste(o.cor) : '#ffffff');
                ctx.fillText(CAIXA ? l.toUpperCase() : l, 70, y0 + i * tam * 1.02);
            });
            ctx.font = '700 30px ' + FONTE_C; ctx.fillStyle = claro ? contraste(o.cor) : 'rgba(255,255,255,.6)'; ctx.fillText(o.marca || '', 70, H - 70);
        },

        impacto: function (ctx, W, H, c, o, t) {
            var txt = o.escuro ? '#ffffff' : '#0b0d17', suave = rgba(txt === '#ffffff' ? '#ffffff' : '#0b0d17', 0.75);
            fundo(ctx, W, H, o, t); marcaTopo(ctx, W, o, txt);
            var vertical = H / W > 1.5; // story/reels: texto em cima, celular embaixo
            var colTexto = vertical ? W - 140 : W * 0.52;
            var y = H * (vertical ? 0.13 : 0.16);
            if (c.selo) y = selo(ctx, 70, y, c.selo, o, txt) + 36;
            var b = caber(ctx, c.titulo, colTexto, vertical ? 104 : 84, PESO_T, FONTE_T, vertical ? 4 : 6, 48);
            ctx.font = PESO_T + ' ' + b.tam + 'px ' + FONTE_T;
            y = escrever(ctx, b, 70, y + b.tam, b.tam * 1.06, txt, realce(o));
            if (c.subtitulo) { var s = caber(ctx, c.subtitulo, colTexto, vertical ? 42 : 36, '500', FONTE_C, 4, 26); ctx.font = '500 ' + s.tam + 'px ' + FONTE_C; y = escrever(ctx, s, 70, y + s.tam + 22, s.tam * 1.35, suave, realce(o)); }
            if (c.cta) y = botao(ctx, 70, y + 44, c.cta, o);
            if (vertical) {
                var cw = W * 0.5;
                celular(ctx, W / 2 - cw / 2 + 60, Math.max(y + 80, H * 0.52), cw, o, t);
            } else {
                var cw2 = W * 0.36;
                celular(ctx, W - cw2 - 50, H * 0.2, cw2, o, t, true);
            }
            // faixa inferior com o site (o celular passa por trás)
            var g = ctx.createLinearGradient(0, H - 170, 0, H);
            g.addColorStop(0, rgba(o.escuro ? '#080a16' : '#f6f4ff', 0)); g.addColorStop(0.6, o.escuro ? '#080a16' : '#f6f4ff');
            ctx.fillStyle = g; ctx.fillRect(0, H - 170, W, 170);
            rodape(ctx, W, H, o, txt);
        },
        lista: function (ctx, W, H, c, o, t) {
            var txt = o.escuro ? '#ffffff' : '#0b0d17';
            fundo(ctx, W, H, o, t); marcaTopo(ctx, W, o, txt);
            var y = H * 0.14;
            if (c.selo) y = selo(ctx, 70, y, c.selo, o, txt) + 30;
            var b = caber(ctx, c.titulo, W - 140, 84, PESO_T, FONTE_T, 3, 52);
            ctx.font = PESO_T + ' ' + b.tam + 'px ' + FONTE_T; y = escrever(ctx, b, 70, y + b.tam, b.tam * 1.08, txt, realce(o)) + 40;
            var itens = (c.itens || []).slice(0, 6);
            var ih = Math.max(78, Math.min(118, (H - 300 - y) / Math.max(1, itens.length) - 18)), meio = ih / 2;
            itens.forEach(function (it, i) {
                var aparece = Math.min(1, Math.max(0, (t - 0.4 - i * 0.35) * 2.5));
                if (t === 0) aparece = 1;
                ctx.globalAlpha = aparece;
                var desloc = (1 - aparece) * 40;
                rr(ctx, 70 + desloc, y, W - 140, ih, 26); ctx.fillStyle = o.escuro ? 'rgba(255,255,255,.06)' : 'rgba(255,255,255,.75)'; ctx.fill();
                ctx.strokeStyle = rgba(o.cor, 0.35); ctx.lineWidth = 2; ctx.stroke();
                ctx.beginPath(); ctx.arc(130 + desloc, y + meio, 28, 0, Math.PI * 2); ctx.fillStyle = o.cor; ctx.fill();
                ctx.fillStyle = contraste(o.cor); ctx.font = '800 30px ' + FONTE_C; ctx.textAlign = 'center'; ctx.fillText('✓', 130 + desloc, y + meio + 10); ctx.textAlign = 'left';
                var l = caber(ctx, it, W - 330, ih > 100 ? 38 : 32, '600', FONTE_C, 2, 24); ctx.font = '600 ' + l.tam + 'px ' + FONTE_C;
                escrever(ctx, l, 185 + desloc, y + meio + (l.linhas.length > 1 ? -l.tam * 0.25 : l.tam * 0.35), l.tam * 1.2, txt, realce(o));
                ctx.globalAlpha = 1;
                y += ih + 18;
            });
            if (c.cta) botao(ctx, W / 2, Math.min(y + 40, H - 230), c.cta, o, true);
            rodape(ctx, W, H, o, txt);
        },
        antes_depois: function (ctx, W, H, c, o, t) {
            var txt = o.escuro ? '#ffffff' : '#0b0d17';
            fundo(ctx, W, H, o, t); marcaTopo(ctx, W, o, txt);
            var b = caber(ctx, c.titulo, W - 140, 76, PESO_T, FONTE_T, 3, 48);
            ctx.font = PESO_T + ' ' + b.tam + 'px ' + FONTE_T; var y = escrever(ctx, b, 70, H * 0.14 + b.tam, b.tam * 1.08, txt, realce(o)) + 50;
            var col = (W - 160) / 2, alto = H - y - 300;
            [['ANTES', c.antes || [], '#ef4444', 70], ['DEPOIS', c.depois || [], '#22c55e', 90 + col]].forEach(function (lado, k) {
                var x = lado[3];
                rr(ctx, x, y, col, alto, 32); ctx.fillStyle = k ? rgba(o.cor, 0.16) : (o.escuro ? 'rgba(255,255,255,.04)' : 'rgba(0,0,0,.04)'); ctx.fill();
                ctx.strokeStyle = k ? rgba(o.cor, 0.6) : 'rgba(128,128,128,.25)'; ctx.lineWidth = 2; ctx.stroke();
                ctx.font = '800 34px ' + FONTE_T; ctx.fillStyle = lado[2]; ctx.fillText(lado[0], x + 40, y + 70);
                var yy = y + 130;
                lado[1].slice(0, 5).forEach(function (it) {
                    ctx.fillStyle = lado[2]; ctx.font = '800 32px ' + FONTE_C; ctx.fillText(k ? '✓' : '✕', x + 40, yy);
                    var l = caber(ctx, it, col - 120, 32, '600', FONTE_C, 3, 24); ctx.font = '600 ' + l.tam + 'px ' + FONTE_C;
                    yy = escrever(ctx, l, x + 90, yy, l.tam * 1.25, txt, realce(o)) + 30;
                });
            });
            if (c.cta) botao(ctx, W / 2, H - 230, c.cta, o, true);
            rodape(ctx, W, H, o, txt);
        },
        depoimento: function (ctx, W, H, c, o, t) {
            var txt = o.escuro ? '#ffffff' : '#0b0d17';
            fundo(ctx, W, H, o, t); marcaTopo(ctx, W, o, txt);
            ctx.font = '800 300px ' + FONTE_T; ctx.fillStyle = rgba(o.cor, 0.35); ctx.fillText('“', 50, H * 0.36);
            var b = caber(ctx, c.citacao || c.titulo, W - 160, 70, '700', FONTE_T, 7, 42);
            ctx.font = '700 ' + b.tam + 'px ' + FONTE_T;
            var y = escrever(ctx, b, 80, H * 0.36, b.tam * 1.22, txt, realce(o)) + 50;
            ctx.fillStyle = o.cor2; ctx.font = '700 36px ' + FONTE_C; ctx.fillText('★★★★★', 80, y);
            ctx.fillStyle = rgba(txt === '#ffffff' ? '#ffffff' : '#0b0d17', 0.75); ctx.font = '600 34px ' + FONTE_C; ctx.fillText(c.autor || '', 80, y + 60);
            if (c.cta) botao(ctx, 80, H - 230, c.cta, o);
            rodape(ctx, W, H, o, txt);
        },
        oferta: function (ctx, W, H, c, o, t) {
            var txt = o.escuro ? '#ffffff' : '#0b0d17', suave = rgba(txt === '#ffffff' ? '#ffffff' : '#0b0d17', 0.75);
            fundo(ctx, W, H, o, t); marcaTopo(ctx, W, o, txt);
            var y = H * 0.15;
            if (c.selo) { ctx.font = '700 28px ' + FONTE_C; y = selo(ctx, W / 2 - (ctx.measureText(c.selo).width + 70) / 2, y, c.selo, o, txt) + 36; }
            var b = caber(ctx, c.titulo, W - 140, 90, PESO_T, FONTE_T, 3, 52); ctx.font = PESO_T + ' ' + b.tam + 'px ' + FONTE_T;
            y = escrever(ctx, b, W / 2, y + b.tam, b.tam * 1.06, txt, realce(o), 'center') + 20;
            var s = c.subtitulo ? caber(ctx, c.subtitulo, W - 200, 38, '500', FONTE_C, 3, 28) : null;
            var altoSub = s ? s.linhas.length * s.tam * 1.35 + 30 : 0;
            var limite = H - (c.cta ? 260 : 140); // o botão e o rodapé ficam livres
            if (c.preco) {
                var raio = Math.max(95, Math.min(175, (limite - y - altoSub - 40) / 2));
                var esc = 1 + (t ? Math.sin(t * 3) * 0.025 : 0);
                ctx.save(); ctx.translate(W / 2, y + 20 + raio); ctx.scale(esc, esc);
                ctx.beginPath(); ctx.arc(0, 0, raio, 0, Math.PI * 2); var g = ctx.createLinearGradient(-raio, -raio, raio, raio); g.addColorStop(0, o.cor); g.addColorStop(1, o.cor2); ctx.fillStyle = g; ctx.fill();
                var pc = caber(ctx, c.preco, raio * 1.6, Math.round(raio * 0.5), '800', FONTE_T, 3, 26); ctx.font = '800 ' + pc.tam + 'px ' + FONTE_T;
                var alt = pc.linhas.length * pc.tam * 1.05;
                escrever(ctx, pc, 0, -alt / 2 + pc.tam * 0.85, pc.tam * 1.05, contraste(o.cor), contraste(o.cor), 'center');
                ctx.restore();
                y += raio * 2 + 40;
            }
            if (s) { ctx.font = '500 ' + s.tam + 'px ' + FONTE_C; y = escrever(ctx, s, W / 2, y + s.tam + 10, s.tam * 1.35, suave, realce(o), 'center'); }
            if (c.cta) botao(ctx, W / 2, Math.max(y + 40, Math.min(H - 230, y + 160)), c.cta, o, true);
            rodape(ctx, W, H, o, txt);
        },
    };

    function desenhar(canvas, conteudo, opcoes, t) {
        var o = Object.assign({ modelo: 'impacto', formato: 'feed', cor: '#6d7cff', cor2: '#22d3ee', escuro: true, estilo: 'neon' }, opcoes || {});
        var est = ESTILOS[o.estilo] || ESTILOS.neon;
        FONTE_T = est.fonte; PESO_T = est.peso; CAIXA = !!est.caixa;
        if (!o.fundo) o.fundo = est.fundo;
        var tam = FORMATOS[o.formato] || FORMATOS.feed;
        if (canvas.width !== tam[0] || canvas.height !== tam[1]) { canvas.width = tam[0]; canvas.height = tam[1]; }
        var ctx = canvas.getContext('2d');
        ctx.textBaseline = 'alphabetic'; ctx.textAlign = 'left'; ctx.globalAlpha = 1;
        (MODELOS[o.modelo] || MODELOS.impacto)(ctx, tam[0], tam[1], conteudo || {}, o, t || 0);
    }

    // Vídeo: cada cena = { modelo, conteudo, segundos }. Transição com fade entre cenas.
    // opcoes.audio (ArrayBuffer de um MP3/WAV) entra como narração; as cenas se esticam para caber a fala.
    function decodificarAudio(ab) {
        var C = window.AudioContext || window.webkitAudioContext;
        if (!ab || !C) return Promise.resolve(null);
        var actx = new C();
        return new Promise(function (ok) { actx.decodeAudioData(ab.slice(0), function (buf) { ok({ ctx: actx, buf: buf }); }, function () { actx.close(); ok(null); }); });
    }
    function gravarVideo(canvas, cenas, opcoes) {
        var o = Object.assign({ formato: 'story' }, opcoes || {});
        return decodificarAudio(o.audio).then(function (aud) {
            return new Promise(function (resolve, reject) {
                if (!canvas.captureStream || typeof MediaRecorder === 'undefined') { reject(new Error('Este navegador não grava vídeo. Use o Chrome no computador.')); return; }
                var tam = FORMATOS[o.formato] || FORMATOS.story;
                canvas.width = tam[0]; canvas.height = tam[1];
                var tipos = aud ? ['video/mp4;codecs=avc1,mp4a.40.2', 'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm']
                                : ['video/mp4;codecs=avc1', 'video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm'];
                var tipo = tipos.filter(function (t) { return MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(t); })[0] || 'video/webm';
                var stream = canvas.captureStream(30);
                var fonteAudio = null;
                if (aud) {
                    var dest = aud.ctx.createMediaStreamDestination();
                    fonteAudio = aud.ctx.createBufferSource();
                    fonteAudio.buffer = aud.buf;
                    fonteAudio.connect(dest);
                    dest.stream.getAudioTracks().forEach(function (t) { stream.addTrack(t); });
                }
                var rec = new MediaRecorder(stream, { mimeType: tipo, videoBitsPerSecond: 8000000 });
                var partes = [];
                rec.ondataavailable = function (e) { if (e.data && e.data.size) partes.push(e.data); };
                rec.onstop = function () { if (aud) aud.ctx.close(); resolve(new Blob(partes, { type: tipo.split(';')[0] })); };
                var duracoes = cenas.map(function (c) { return (c.segundos || 2.8) * 1000; });
                var total = duracoes.reduce(function (a, b) { return a + b; }, 0);
                if (aud) {
                    var alvo = aud.buf.duration * 1000 + 700;
                    if (alvo > total) { var f = alvo / total; duracoes = duracoes.map(function (d) { return d * f; }); total = alvo; }
                }
                var ctx = canvas.getContext('2d');
                var t0 = null;
                var quadro = function (agora) {
                    if (!t0) t0 = agora;
                    var dt = agora - t0;
                    if (dt >= total) { rec.stop(); return; }
                    var acum = 0, i = 0;
                    while (i < cenas.length - 1 && dt > acum + duracoes[i]) { acum += duracoes[i]; i++; }
                    var local = (dt - acum) / 1000;
                    desenhar(canvas, cenas[i].conteudo, Object.assign({}, o, { modelo: cenas[i].modelo || o.modelo }, cenas[i].foto !== undefined ? { foto: cenas[i].foto } : {}), local + 0.0001);
                    // entrada (zoom suave) e saída (escurece)
                    var entra = Math.min(1, local / 0.35), sai = Math.max(0, (local - duracoes[i] / 1000 + 0.3) / 0.3);
                    if (entra < 1 || sai > 0) { ctx.fillStyle = 'rgba(0,0,0,' + Math.max(1 - entra, sai) + ')'; ctx.fillRect(0, 0, canvas.width, canvas.height); }
                    requestAnimationFrame(quadro);
                };
                rec.start(250);
                if (fonteAudio) fonteAudio.start();
                requestAnimationFrame(quadro);
            });
        });
    }

    window.GDSCriativos = { desenhar: desenhar, gravarVideo: gravarVideo, formatos: FORMATOS, modelos: Object.keys(MODELOS), estilos: ESTILOS, paletas: PALETAS };
})();
