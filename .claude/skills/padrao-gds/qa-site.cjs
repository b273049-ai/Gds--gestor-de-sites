#!/usr/bin/env node
/* ============================================================
   CONTROLE DE QUALIDADE GDS — mede um site de verdade e gera a
   lista de verificação do Padrão GDS (skill padrao-gds).

   Uso:
     node .claude/skills/padrao-gds/qa-site.cjs <url> [url…] [--json saida.json] [--fotos pasta]

   Para cada URL, em 5 telas (320, 390, 768, 1366, 1920 px):
     • rolagem lateral, elementos saindo da tela, botões sobrepostos,
       área de toque pequena no celular
     • imagens quebradas, sem alt, deformadas, sem width/height
     • links mortos (href="#", âncora para id que não existe)
     • erros de JavaScript, requisições com erro (4xx/5xx)
   E uma vez por URL:
     • SEO: <title>, descrição, 1 h1, lang, viewport, Open Graph,
       canonical, dados estruturados (JSON-LD), ordem dos títulos
     • desempenho: LCP, CLS, peso total, nº de requisições, nº de nós
     • acessibilidade: axe-core (WCAG 2.1 AA + boas práticas), se achar
       o axe-core instalado (npm i axe-core) ou conseguir baixá-lo
   Requer Playwright (já vem nas sessões na nuvem do Claude Code).
   ============================================================ */
'use strict';
const fs = require('fs');
const path = require('path');

let chromium;
try { ({ chromium } = require('playwright')); }
catch (e) { console.error('Playwright não encontrado. Instale com: npm i -D playwright && npx playwright install chromium'); process.exit(2); }

const args = process.argv.slice(2);
const opt = (n) => { const i = args.indexOf(n); if (i < 0) return null; const v = args[i + 1]; args.splice(i, 2); return v; };
const saidaJson = opt('--json');
const pastaFotos = opt('--fotos');
const urls = args.filter((a) => /^https?:\/\//.test(a));
if (!urls.length) { console.error('Informe ao menos uma URL. Ex.: node qa-site.cjs http://127.0.0.1:8080/site/index.php?slug=minha-empresa'); process.exit(2); }

const TELAS = [
    { nome: 'celular pequeno', w: 320, h: 640, toque: true },
    { nome: 'celular', w: 390, h: 844, toque: true },
    { nome: 'tablet', w: 768, h: 1024, toque: true },
    { nome: 'notebook', w: 1366, h: 860, toque: false },
    { nome: 'monitor grande', w: 1920, h: 1080, toque: false },
];

async function carregarAxe() {
    const caminhos = [process.env.AXE_PATH, path.join(process.cwd(), 'node_modules/axe-core/axe.min.js'), path.join(__dirname, 'node_modules/axe-core/axe.min.js')];
    try { caminhos.push(require.resolve('axe-core/axe.min.js')); } catch (e) { /* não instalado */ }
    for (const c of caminhos) if (c && fs.existsSync(c)) return fs.readFileSync(c, 'utf8');
    try {
        const r = await fetch('https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.10.2/axe.min.js');
        if (r.ok) return await r.text();
    } catch (e) { /* sem internet */ }
    return null;
}

// Rola a página inteira (dispara revelações e imagens "lazy") e volta ao topo
async function rolarTudo(p) {
    await p.evaluate(async () => {
        const passo = Math.max(300, innerHeight * 0.8);
        for (let y = 0; y < document.documentElement.scrollHeight; y += passo) { scrollTo(0, y); await new Promise((r) => setTimeout(r, 90)); }
        scrollTo(0, document.documentElement.scrollHeight); await new Promise((r) => setTimeout(r, 600));
        scrollTo(0, 0); await new Promise((r) => setTimeout(r, 300));
    });
}

// Verificações de layout em uma tela
function checarTela(toque) {
    const vis = (el) => {
        // checkVisibility também descarta o conteúdo de <details> fechado (content-visibility: hidden)
        if (el.checkVisibility && !el.checkVisibility({ checkOpacity: true, checkVisibilityCSS: true, contentVisibilityAuto: true })) return false;
        const s = getComputedStyle(el); const r = el.getBoundingClientRect(); return s.display !== 'none' && s.visibility !== 'hidden' && +s.opacity > 0.05 && r.width > 0 && r.height > 0; };
    const fixo = (el) => { for (let p = el; p && p !== document.body; p = p.parentElement) { const ps = getComputedStyle(p).position; if (ps === 'fixed' || ps === 'sticky') return true; } return false; };
    const sel = (el) => el.id ? '#' + el.id : el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '');
    const dentroDeRolagem = (el) => { for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) { const o = getComputedStyle(p).overflowX; if (o === 'auto' || o === 'scroll' || o === 'hidden' || o === 'clip') return true; } return false; };
    const W = document.documentElement.clientWidth;
    const out = { rolagemLateral: document.documentElement.scrollWidth > W + 1, saindoDaTela: [], sobrepostos: [], toquePequeno: [] };
    for (const el of document.querySelectorAll('body *')) {
        if (!vis(el) || getComputedStyle(el).position === 'fixed') continue;
        const r = el.getBoundingClientRect();
        if (((r.right > W + 2 && r.left < W) || (r.left < -2 && r.right > 0)) && !dentroDeRolagem(el) && out.saindoDaTela.length < 6) out.saindoDaTela.push(sel(el) + ' (' + Math.round(r.left) + '→' + Math.round(r.right) + 'px)');
    }
    const clicaveis = [...document.querySelectorAll('a[href], button, [role="button"], input[type="submit"]')].filter((el) => vis(el) && !fixo(el));
    for (let i = 0; i < clicaveis.length && out.sobrepostos.length < 6; i++) {
        const a = clicaveis[i].getBoundingClientRect();
        for (let j = i + 1; j < clicaveis.length; j++) {
            if (clicaveis[i].contains(clicaveis[j]) || clicaveis[j].contains(clicaveis[i])) continue;
            const b = clicaveis[j].getBoundingClientRect();
            const x = Math.min(a.right, b.right) - Math.max(a.left, b.left), y = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
            if (x > 6 && y > 6) { out.sobrepostos.push(sel(clicaveis[i]) + ' × ' + sel(clicaveis[j])); break; }
        }
    }
    if (toque) for (const el of clicaveis) {
        const r = el.getBoundingClientRect();
        const emTexto = el.tagName === 'A' && el.closest('p, li, td, small, span') && getComputedStyle(el).display === 'inline';
        if (!emTexto && (r.height < 40 || r.width < 40) && out.toquePequeno.length < 6) out.toquePequeno.push(sel(el) + ' (' + Math.round(r.width) + '×' + Math.round(r.height) + ')');
    }
    return out;
}

// Verificações que não dependem da tela
function checarPagina() {
    const q = (s) => document.querySelector(s), qa = (s) => [...document.querySelectorAll(s)];
    const meta = (n) => (q('meta[name="' + n + '"]') || q('meta[property="' + n + '"]'))?.getAttribute('content') || '';
    const imgs = qa('img').filter((i) => i.getBoundingClientRect().width > 0);
    const deformadas = imgs.filter((i) => {
        if (!i.naturalWidth || !i.naturalHeight) return false;
        const fit = getComputedStyle(i).objectFit; if (fit === 'cover' || fit === 'contain' || fit === 'scale-down') return false;
        const r = i.getBoundingClientRect(); const a = i.naturalWidth / i.naturalHeight, b = r.width / r.height;
        return Math.abs(a - b) / a > 0.06;
    }).map((i) => i.src.slice(-60));
    const ids = new Set(qa('[id]').map((e) => e.id));
    const ancorasMortas = qa('a[href^="#"]').map((a) => a.getAttribute('href')).filter((h) => h.length > 1 && !h.startsWith('#gds-') && !ids.has(decodeURIComponent(h.slice(1))));
    const hs = qa('h1,h2,h3,h4,h5,h6').map((h) => +h.tagName[1]);
    const pulos = hs.filter((n, i) => i > 0 && n > hs[i - 1] + 1).length;
    return {
        titulo: document.title, tituloTam: document.title.length,
        descricao: meta('description'), descricaoTam: meta('description').length,
        h1: qa('h1').length, lang: document.documentElement.lang, viewport: !!q('meta[name="viewport"]'),
        og: { titulo: !!meta('og:title'), descricao: !!meta('og:description'), imagem: !!meta('og:image') },
        canonical: !!q('link[rel="canonical"]'), jsonld: qa('script[type="application/ld+json"]').length,
        pulosDeTitulo: pulos, nos: document.getElementsByTagName('*').length,
        imagens: { total: imgs.length, quebradas: imgs.filter((i) => i.complete && i.naturalWidth === 0).map((i) => i.src.slice(-60)),
            semAlt: imgs.filter((i) => !i.hasAttribute('alt')).length, semTamanho: imgs.filter((i) => !i.getAttribute('width') && !i.getAttribute('height')).length, deformadas },
        linksMortos: qa('a').filter((a) => !a.getAttribute('href') || a.getAttribute('href') === '#').filter((a) => !a.hasAttribute('role') && !a.hasAttribute('onclick')).length,
        ancorasMortas: [...new Set(ancorasMortas)].slice(0, 8),
    };
}

async function medirDesempenho(p) {
    return p.evaluate(() => new Promise((res) => {
        let lcp = 0, cls = 0;
        try { new PerformanceObserver((l) => { for (const e of l.getEntries()) lcp = Math.max(lcp, e.renderTime || e.loadTime || e.startTime); }).observe({ type: 'largest-contentful-paint', buffered: true }); } catch (e) {}
        try { new PerformanceObserver((l) => { for (const e of l.getEntries()) if (!e.hadRecentInput) cls += e.value; }).observe({ type: 'layout-shift', buffered: true }); } catch (e) {}
        setTimeout(() => {
            const rec = performance.getEntriesByType('resource');
            const nav = performance.getEntriesByType('navigation')[0];
            const bytes = rec.reduce((s, r) => s + (r.transferSize || r.encodedBodySize || 0), 0) + (nav ? nav.transferSize || 0 : 0);
            res({ lcpMs: Math.round(lcp), cls: +cls.toFixed(3), requisicoes: rec.length + 1, pesoKB: Math.round(bytes / 1024), domPronto: nav ? Math.round(nav.domContentLoadedEventEnd) : null });
        }, 1200);
    }));
}

const ok = (c) => (c ? '✅' : '❌');
const aviso = (c) => (c ? '✅' : '⚠️');

(async () => {
    const axe = await carregarAxe();
    const nav = await chromium.launch();
    const relatorio = [];
    for (const url of urls) {
        const r = { url, telas: {}, erros: [], falhasRede: [] };
        const ctx = await nav.newContext({ viewport: { width: 1366, height: 860 } });
        const p = await ctx.newPage();
        p.on('pageerror', (e) => r.erros.push('JS: ' + e.message.slice(0, 160)));
        p.on('console', (m) => { if (m.type() === 'error') r.erros.push('console: ' + m.text().slice(0, 160)); });
        p.on('response', (res) => { const u = res.url(); if (res.status() >= 400 && u.startsWith(new URL(url).origin)) r.falhasRede.push(res.status() + ' ' + u.slice(-80)); });
        const resp = await p.goto(url, { waitUntil: 'networkidle', timeout: 45000 }).catch((e) => { r.erros.push('não abriu: ' + e.message.slice(0, 120)); return null; });
        r.status = resp ? resp.status() : 0;
        if (!resp) { relatorio.push(r); await ctx.close(); continue; }
        r.desempenho = await medirDesempenho(p);
        await rolarTudo(p);
        r.pagina = await p.evaluate(checarPagina);
        if (axe) {
            for (const w of [390, 1366]) {
                await p.setViewportSize({ width: w, height: w < 800 ? 844 : 860 });
                await rolarTudo(p);
                await p.addScriptTag({ content: axe });
                r['axe' + w] = await p.evaluate(async () => (await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice'] } })).violations.map((v) => v.id + ' (' + v.nodes.length + ': ' + v.nodes[0].target.join(' ').slice(0, 60) + ')'));
            }
        }
        for (const t of TELAS) {
            const c = await nav.newContext({ viewport: { width: t.w, height: t.h }, hasTouch: t.toque, isMobile: t.toque && t.w < 800 });
            const pg = await c.newPage();
            await pg.goto(url, { waitUntil: 'networkidle', timeout: 45000 }).catch(() => {});
            await rolarTudo(pg);
            r.telas[t.w] = await pg.evaluate(checarTela, t.toque);
            if (pastaFotos) { fs.mkdirSync(pastaFotos, { recursive: true }); await pg.screenshot({ path: path.join(pastaFotos, (relatorio.length + 1) + '-' + t.w + '.png'), fullPage: false }); }
            await c.close();
        }
        await ctx.close();
        relatorio.push(r);
    }
    await nav.close();

    // ---------- relatório ----------
    let falhasGraves = 0;
    for (const r of relatorio) {
        console.log('\n## ' + r.url + '\n');
        if (!r.pagina) { console.log('❌ A página não abriu: ' + r.erros.join('; ')); falhasGraves++; continue; }
        const g = r.pagina, d = r.desempenho;
        const linhas = [];
        const add = (item, simb, det) => { linhas.push('| ' + item + ' | ' + simb + ' | ' + (det || '') + ' |'); if (simb === '❌') falhasGraves++; };
        add('Página responde', ok(r.status < 400), 'HTTP ' + r.status);
        const lat = Object.entries(r.telas).filter(([, v]) => v.rolagemLateral).map(([w]) => w + 'px');
        add('Sem rolagem lateral', ok(!lat.length), lat.join(', '));
        const fora = Object.entries(r.telas).flatMap(([w, v]) => v.saindoDaTela.map((s) => w + ': ' + s));
        add('Nada saindo da tela', ok(!fora.length), fora.slice(0, 3).join('; '));
        const sob = Object.entries(r.telas).flatMap(([w, v]) => v.sobrepostos.map((s) => w + ': ' + s));
        add('Botões sem sobreposição', ok(!sob.length), sob.slice(0, 3).join('; '));
        const toq = Object.entries(r.telas).flatMap(([w, v]) => v.toquePequeno.map((s) => w + ': ' + s));
        add('Área de toque ≥ 40px (celular)', aviso(!toq.length), toq.slice(0, 3).join('; '));
        add('Sem erros de JavaScript', ok(!r.erros.length), r.erros.slice(0, 2).join('; '));
        add('Sem requisições com erro', ok(!r.falhasRede.length), r.falhasRede.slice(0, 2).join('; '));
        add('Imagens carregam', ok(!g.imagens.quebradas.length), g.imagens.quebradas.slice(0, 2).join('; '));
        add('Imagens sem deformação', ok(!g.imagens.deformadas.length), g.imagens.deformadas.slice(0, 2).join('; '));
        add('Imagens com alt', ok(!g.imagens.semAlt), g.imagens.semAlt + ' sem alt de ' + g.imagens.total);
        add('Imagens com width/height (evita pulo)', aviso(!g.imagens.semTamanho), g.imagens.semTamanho + ' sem tamanho');
        add('Sem links/botões mortos', ok(!g.linksMortos && !g.ancorasMortas.length), (g.linksMortos ? g.linksMortos + ' href vazio/#; ' : '') + g.ancorasMortas.join(' '));
        add('Título (10–70 car.)', ok(g.tituloTam >= 10 && g.tituloTam <= 70), g.tituloTam + ': ' + g.titulo.slice(0, 70));
        add('Descrição (50–170 car.)', aviso(g.descricaoTam >= 50 && g.descricaoTam <= 170), String(g.descricaoTam));
        add('Um único h1', ok(g.h1 === 1), String(g.h1));
        add('Títulos em ordem', aviso(!g.pulosDeTitulo), g.pulosDeTitulo + ' pulo(s)');
        add('lang e viewport', ok(!!g.lang && g.viewport), 'lang=' + g.lang);
        add('Open Graph (título, descrição, imagem)', aviso(g.og.titulo && g.og.descricao && g.og.imagem), JSON.stringify(g.og));
        add('Canonical', aviso(g.canonical), '');
        add('Dados estruturados (JSON-LD)', aviso(g.jsonld > 0), String(g.jsonld));
        add('LCP ≤ 2,5 s (local)', aviso(d.lcpMs <= 2500), d.lcpMs + ' ms');
        add('CLS ≤ 0,1', ok(d.cls <= 0.1), String(d.cls));
        add('Peso ≤ 1,5 MB', aviso(d.pesoKB <= 1536), d.pesoKB + ' KB em ' + d.requisicoes + ' requisições');
        add('DOM ≤ 1500 nós', aviso(g.nos <= 1500), String(g.nos));
        if (r.axe390) { add('Acessibilidade axe (390px)', ok(!r.axe390.length), r.axe390.join(', ')); add('Acessibilidade axe (1366px)', ok(!r.axe1366.length), r.axe1366.join(', ')); }
        else add('Acessibilidade axe', '⚠️', 'axe-core não encontrado (npm i axe-core) — não executado');
        console.log('| Item | Resultado | Detalhe |\n|---|---|---|\n' + linhas.join('\n'));
    }
    console.log('\nResumo: ' + (falhasGraves ? falhasGraves + ' falha(s) ❌ — o site NÃO pode ser considerado pronto.' : 'nenhuma falha ❌ (confira os ⚠️ e a avaliação visual).'));
    if (saidaJson) fs.writeFileSync(saidaJson, JSON.stringify(relatorio, null, 2));
    process.exit(falhasGraves ? 1 : 0);
})();
