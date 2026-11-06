/* ============================================================
   SITE VIVO — o movimento do visual "Limpo" (sem bibliotecas).
   • Entrada orquestrada do topo: o título sobe palavra por palavra,
     depois o texto, os botões e o painel ao vivo (horários um a um).
   • Luz da cor da marca que segue o mouse (computador).
   • Linha de progresso da leitura e cabeçalho que encolhe ao rolar.
   • Títulos de seção revelados ao chegar; itens entram em sequência.
   • Botão principal "imã", nota que conta até o valor.
   Respeita "reduzir movimento": tudo aparece pronto, sem animação.
   ============================================================ */
(function () {
    'use strict';
    var d = document, raiz = d.documentElement;
    var reduzir = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var mouse = window.matchMedia && matchMedia('(hover: hover) and (pointer: fine)').matches;
    var $$ = function (s, el) { return Array.prototype.slice.call((el || d).querySelectorAll(s)); };

    function palavras(el) {
        if (el.dataset.pronto) return;
        el.dataset.pronto = '1';
        var txt = el.textContent.trim();
        el.setAttribute('aria-label', txt);
        el.innerHTML = txt.split(/\s+/).map(function (p, i) {
            return '<span class="lp-pal" aria-hidden="true"><span style="--i:' + i + '">' + p.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</span></span>';
        }).join(' ');
    }

    function contar(el) {
        var alvo = parseFloat(el.getAttribute('data-conta')) || 0, t0 = null;
        if (reduzir) return;
        var passo = function (t) {
            if (!t0) t0 = t;
            var p = Math.min(1, (t - t0) / 1300);
            el.textContent = (alvo * (1 - Math.pow(1 - p, 3))).toFixed(1).replace('.', ',');
            if (p < 1) requestAnimationFrame(passo);
        };
        el.textContent = '0,0';
        requestAnimationFrame(passo);
    }

    function iniciar() {
        if (reduzir) return;
        raiz.classList.add('lp-js', 'lp-vivo-ok');
        $$('[data-palavras]').forEach(palavras);

        // 1) Entrada do topo (um único momento orquestrado)
        var hero = d.getElementById('inicio');
        requestAnimationFrame(function () { requestAnimationFrame(function () { if (hero) hero.classList.add('lp-no-ar'); }); });
        var nota = hero && hero.querySelector('[data-conta]');
        if (nota) setTimeout(function () { contar(nota); }, 900);

        // 2) Revelar ao chegar: títulos (palavra por palavra) e itens em sequência
        var alvos = $$('.lp-cab h2, .lp-sobre h2, .lp-fim h2, .lp-sobre figure, .lp-mapa');
        $$('.lp-cab h2, .lp-sobre h2').forEach(function (h) { h.setAttribute('data-palavras', ''); palavras(h); });
        var listas = $$('.lp-secao .gds-menu-item, .lp-secao .gds-svc, .lp-secao .gds-av, .lp-secao .gds-prof').filter(function (el) { return el.tagName !== 'FORM' && !el.querySelector('form'); });
        listas.forEach(function (el) {
            var irmaos = Array.prototype.indexOf.call(el.parentNode.children, el);
            el.style.setProperty('--i', Math.min(irmaos, 8));
            el.classList.add('lp-item');
        });
        alvos = alvos.concat(listas);
        alvos.forEach(function (el) { el.classList.add('lp-espera'); });
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (es) {
                es.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('lp-visto'); io.unobserve(en.target); } });
            }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
            alvos.forEach(function (el) { io.observe(el); });
        } else alvos.forEach(function (el) { el.classList.add('lp-visto'); });
        // Teclado: o que recebe foco aparece na hora
        d.addEventListener('focusin', function (e) { var x = e.target.closest && e.target.closest('.lp-espera'); if (x) x.classList.add('lp-visto'); });

        // 3) Luz que segue o mouse
        if (mouse) $$('[data-luz]').forEach(function (sec) {
            var pend = false, x = 50, y = 40;
            sec.addEventListener('pointermove', function (e) {
                var r = sec.getBoundingClientRect();
                x = (e.clientX - r.left) / r.width * 100; y = (e.clientY - r.top) / r.height * 100;
                if (pend) return; pend = true;
                requestAnimationFrame(function () { pend = false; sec.style.setProperty('--mx', x.toFixed(1) + '%'); sec.style.setProperty('--my', y.toFixed(1) + '%'); });
            });
        });

        // 4) Botão imã e brilho que segue o cursor nos itens
        if (mouse) {
            $$('[data-ima]').forEach(function (b) {
                b.addEventListener('pointermove', function (e) {
                    var r = b.getBoundingClientRect();
                    b.style.transform = 'translate(' + ((e.clientX - r.left - r.width / 2) * 0.16).toFixed(1) + 'px,' + ((e.clientY - r.top - r.height / 2) * 0.22).toFixed(1) + 'px)';
                });
                b.addEventListener('pointerleave', function () { b.style.transform = ''; });
            });
            d.addEventListener('pointermove', function (e) {
                var it = e.target.closest && e.target.closest('.lp-item, .lp-vivo');
                if (!it) return;
                var r = it.getBoundingClientRect();
                it.style.setProperty('--px', (e.clientX - r.left) + 'px'); it.style.setProperty('--py', (e.clientY - r.top) + 'px');
            }, { passive: true });
        }
    }

    // 5) Progresso, cabeçalho e o topo "saindo de cena" ao rolar
    function rolagem() {
        var barra = d.querySelector('.lp-progresso'), topo = d.querySelector('.lp-topo'), hero = d.getElementById('inicio');
        var texto = hero && hero.querySelector('.lp-hero-texto'), agendado = false;
        var at = function () {
            agendado = false;
            var y = scrollY, max = Math.max(1, raiz.scrollHeight - innerHeight);
            if (barra) barra.style.transform = 'scaleX(' + (y / max).toFixed(4) + ')';
            if (topo) topo.classList.toggle('rolou', y > 40);
            if (texto && !reduzir && hero.classList.contains('lp-pronto')) {
                var p = Math.min(1, y / Math.max(1, hero.offsetHeight));
                texto.style.transform = p > 0 ? 'translate3d(0,' + (p * -60).toFixed(1) + 'px,0)' : '';
                texto.style.opacity = p > 0 ? (1 - p * 0.75).toFixed(3) : '';
            }
        };
        addEventListener('scroll', function () { if (!agendado) { agendado = true; requestAnimationFrame(at); } }, { passive: true });
        at();
        // o efeito de rolagem do topo só começa depois da entrada
        if (hero) setTimeout(function () { hero.classList.add('lp-pronto'); }, 1600);
    }

    if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', function () { iniciar(); rolagem(); });
    else { iniciar(); rolagem(); }
})();
