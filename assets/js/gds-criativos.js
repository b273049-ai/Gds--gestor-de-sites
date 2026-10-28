/* ============================================================
   GDS CRIATIVOS — gera artes (PNG) e vídeos curtos (WebM/MP4) para
   Instagram e TikTok direto no navegador, com <canvas>.
   GDSCriativos.desenhar(canvas, conteudo, opcoes)
   GDSCriativos.gravarVideo(canvas, cenas, opcoes) → Promise<Blob>
   conteudo: { titulo, subtitulo, itens[], cta, selo, citacao, autor, antes[], depois[], preco }
   opcoes:   { modelo: impacto|lista|antes_depois|depoimento|oferta, formato: feed|story|quadrado,
               cor: '#6d7cff', cor2: '#22d3ee', marca: 'GDS', arroba: '@gds', site: 'gds.com.br', escuro: true }
   ============================================================ */
(function () {
    'use strict';
    var FORMATOS = { feed: [1080, 1350], story: [1080, 1920], quadrado: [1080, 1080] };
    var FONTE_T = '"Sora", "Inter", system-ui, sans-serif';
    var FONTE_C = '"Inter", system-ui, sans-serif';

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
        ctx.fillStyle = o.escuro ? '#080a16' : '#f6f4ff';
        ctx.fillRect(0, 0, W, H);
        var bolhas = [[0.15 + Math.sin(t * 0.9) * 0.05, 0.12, 0.7, o.cor], [0.9, 0.55 + Math.cos(t * 0.7) * 0.05, 0.6, o.cor2], [0.3, 0.95, 0.55, o.cor]];
        bolhas.forEach(function (b) {
            var g = ctx.createRadialGradient(b[0] * W, b[1] * H, 0, b[0] * W, b[1] * H, b[2] * W);
            g.addColorStop(0, rgba(b[3], o.escuro ? 0.55 : 0.35)); g.addColorStop(1, rgba(b[3], 0));
            ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
        });
        // grade sutil
        ctx.strokeStyle = o.escuro ? 'rgba(255,255,255,.035)' : 'rgba(10,10,40,.04)'; ctx.lineWidth = 1;
        for (var gx = 0; gx < W; gx += 60) { ctx.beginPath(); ctx.moveTo(gx, 0); ctx.lineTo(gx, H); ctx.stroke(); }
        for (var gy = 0; gy < H; gy += 60) { ctx.beginPath(); ctx.moveTo(0, gy); ctx.lineTo(W, gy); ctx.stroke(); }
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
        impacto: function (ctx, W, H, c, o, t) {
            var txt = o.escuro ? '#ffffff' : '#0b0d17', suave = rgba(txt === '#ffffff' ? '#ffffff' : '#0b0d17', 0.75);
            fundo(ctx, W, H, o, t); marcaTopo(ctx, W, o, txt);
            var vertical = H / W > 1.5; // story/reels: texto em cima, celular embaixo
            var colTexto = vertical ? W - 140 : W * 0.52;
            var y = H * (vertical ? 0.13 : 0.16);
            if (c.selo) y = selo(ctx, 70, y, c.selo, o, txt) + 36;
            var b = caber(ctx, c.titulo, colTexto, vertical ? 104 : 84, '800', FONTE_T, vertical ? 4 : 6, 48);
            ctx.font = '800 ' + b.tam + 'px ' + FONTE_T;
            y = escrever(ctx, b, 70, y + b.tam, b.tam * 1.06, txt, o.cor2);
            if (c.subtitulo) { var s = caber(ctx, c.subtitulo, colTexto, vertical ? 42 : 36, '500', FONTE_C, 4, 26); ctx.font = '500 ' + s.tam + 'px ' + FONTE_C; y = escrever(ctx, s, 70, y + s.tam + 22, s.tam * 1.35, suave, o.cor2); }
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
            var b = caber(ctx, c.titulo, W - 140, 84, '800', FONTE_T, 3, 52);
            ctx.font = '800 ' + b.tam + 'px ' + FONTE_T; y = escrever(ctx, b, 70, y + b.tam, b.tam * 1.08, txt, o.cor2) + 40;
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
                escrever(ctx, l, 185 + desloc, y + meio + (l.linhas.length > 1 ? -l.tam * 0.25 : l.tam * 0.35), l.tam * 1.2, txt, o.cor2);
                ctx.globalAlpha = 1;
                y += ih + 18;
            });
            if (c.cta) botao(ctx, W / 2, Math.min(y + 40, H - 230), c.cta, o, true);
            rodape(ctx, W, H, o, txt);
        },
        antes_depois: function (ctx, W, H, c, o, t) {
            var txt = o.escuro ? '#ffffff' : '#0b0d17';
            fundo(ctx, W, H, o, t); marcaTopo(ctx, W, o, txt);
            var b = caber(ctx, c.titulo, W - 140, 76, '800', FONTE_T, 3, 48);
            ctx.font = '800 ' + b.tam + 'px ' + FONTE_T; var y = escrever(ctx, b, 70, H * 0.14 + b.tam, b.tam * 1.08, txt, o.cor2) + 50;
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
                    yy = escrever(ctx, l, x + 90, yy, l.tam * 1.25, txt, o.cor2) + 30;
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
            var y = escrever(ctx, b, 80, H * 0.36, b.tam * 1.22, txt, o.cor2) + 50;
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
            var b = caber(ctx, c.titulo, W - 140, 90, '800', FONTE_T, 3, 52); ctx.font = '800 ' + b.tam + 'px ' + FONTE_T;
            y = escrever(ctx, b, W / 2, y + b.tam, b.tam * 1.06, txt, o.cor2, 'center') + 20;
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
            if (s) { ctx.font = '500 ' + s.tam + 'px ' + FONTE_C; y = escrever(ctx, s, W / 2, y + s.tam + 10, s.tam * 1.35, suave, o.cor2, 'center'); }
            if (c.cta) botao(ctx, W / 2, Math.max(y + 40, Math.min(H - 230, y + 160)), c.cta, o, true);
            rodape(ctx, W, H, o, txt);
        },
    };

    function desenhar(canvas, conteudo, opcoes, t) {
        var o = Object.assign({ modelo: 'impacto', formato: 'feed', cor: '#6d7cff', cor2: '#22d3ee', escuro: true }, opcoes || {});
        var tam = FORMATOS[o.formato] || FORMATOS.feed;
        if (canvas.width !== tam[0] || canvas.height !== tam[1]) { canvas.width = tam[0]; canvas.height = tam[1]; }
        var ctx = canvas.getContext('2d');
        ctx.textBaseline = 'alphabetic'; ctx.textAlign = 'left'; ctx.globalAlpha = 1;
        (MODELOS[o.modelo] || MODELOS.impacto)(ctx, tam[0], tam[1], conteudo || {}, o, t || 0);
    }

    // Vídeo: cada cena = { modelo, conteudo, segundos }. Transição com fade entre cenas.
    function gravarVideo(canvas, cenas, opcoes) {
        return new Promise(function (resolve, reject) {
            if (!canvas.captureStream || typeof MediaRecorder === 'undefined') { reject(new Error('Este navegador não grava vídeo. Use o Chrome no computador.')); return; }
            var o = Object.assign({ formato: 'story' }, opcoes || {});
            var tam = FORMATOS[o.formato] || FORMATOS.story;
            canvas.width = tam[0]; canvas.height = tam[1];
            var tipos = ['video/mp4;codecs=avc1', 'video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm'];
            var tipo = tipos.filter(function (t) { return MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(t); })[0] || 'video/webm';
            var stream = canvas.captureStream(30);
            var rec = new MediaRecorder(stream, { mimeType: tipo, videoBitsPerSecond: 8000000 });
            var partes = [];
            rec.ondataavailable = function (e) { if (e.data && e.data.size) partes.push(e.data); };
            rec.onstop = function () { resolve(new Blob(partes, { type: tipo.split(';')[0] })); };
            var duracoes = cenas.map(function (c) { return (c.segundos || 2.8) * 1000; });
            var total = duracoes.reduce(function (a, b) { return a + b; }, 0);
            var ctx = canvas.getContext('2d');
            var t0 = null;
            var quadro = function (agora) {
                if (!t0) t0 = agora;
                var dt = agora - t0;
                if (dt >= total) { rec.stop(); return; }
                var acum = 0, i = 0;
                while (i < cenas.length - 1 && dt > acum + duracoes[i]) { acum += duracoes[i]; i++; }
                var local = (dt - acum) / 1000;
                desenhar(canvas, cenas[i].conteudo, Object.assign({}, o, { modelo: cenas[i].modelo || o.modelo }), local + 0.0001);
                // entrada (zoom suave) e saída (escurece)
                var entra = Math.min(1, local / 0.35), sai = Math.max(0, (local - duracoes[i] / 1000 + 0.3) / 0.3);
                if (entra < 1 || sai > 0) { ctx.fillStyle = 'rgba(0,0,0,' + Math.max(1 - entra, sai) + ')'; ctx.fillRect(0, 0, canvas.width, canvas.height); }
                requestAnimationFrame(quadro);
            };
            rec.start(250);
            requestAnimationFrame(quadro);
        });
    }

    window.GDSCriativos = { desenhar: desenhar, gravarVideo: gravarVideo, formatos: FORMATOS, modelos: Object.keys(MODELOS) };
})();
