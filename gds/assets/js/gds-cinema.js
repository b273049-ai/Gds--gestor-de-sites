/* ============================================================
   GDS CINEMA — extensão de animações dos sites (opcional).
   Usa GSAP + ScrollTrigger + Lenis (carregados com versão fixa).
   Cenas: rolagem suave, topo que "sai de cena", luz seguindo o
   mouse, faixa que entorta com a velocidade, passos que acendem
   em sequência, itens do cardápio entrando em cascata e foto
   final em zoom. Se as bibliotecas não carregarem, nada quebra.
   ============================================================ */
(function () {
    'use strict';
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    function iniciar() {
        var g = window.gsap, ST = window.ScrollTrigger;
        if (!g || !ST) return;
        g.registerPlugin(ST);
        document.documentElement.classList.add('gc-ativo');
        var toque = window.matchMedia('(hover: none)').matches;
        var $ = function (s, el) { return (el || document).querySelector(s); };
        var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };

        // Rolagem suave (só no computador; no celular a rolagem nativa é melhor)
        if (window.Lenis && !toque) {
            var lenis = new window.Lenis({ duration: 1.15, smoothWheel: true });
            lenis.on('scroll', ST.update);
            g.ticker.add(function (t) { lenis.raf(t * 1000); });
            g.ticker.lagSmoothing(0);
            document.addEventListener('click', function (ev) {
                var a = ev.target.closest && ev.target.closest('a[href^="#"]');
                if (!a) return;
                var alvo = a.getAttribute('href').length > 1 && document.getElementById(a.getAttribute('href').slice(1));
                if (!alvo) return;
                ev.preventDefault();
                document.body.classList.remove('gm-menu-aberto');
                lenis.scrollTo(alvo, { offset: -70 });
            });
            window.gdsMostrarPagina = function (k) { var el = document.getElementById(k); if (el) lenis.scrollTo(el, { offset: -70 }); };
        }

        // Topo: o texto sobe e some, a foto aproxima enquanto você rola
        var hero = $('[data-cena="hero"]');
        if (hero) {
            var tl = g.timeline({ scrollTrigger: { trigger: hero, start: 'top top', end: 'bottom top', scrub: true } });
            var texto = $('.pt-hero-in', hero);
            if (texto) tl.to(texto, { yPercent: -18, opacity: 0.15, ease: 'none' }, 0);
            var fig = $('.pt-hero-fig', hero);
            if (fig) tl.to(fig, { scale: 0.9, ease: 'none' }, 0);
            var cartoes = $('.pt-hero-cartoes', hero);
            if (cartoes) tl.to(cartoes, { yPercent: -40, ease: 'none' }, 0);
            // Luz que acompanha o mouse
            if (!toque) {
                hero.classList.add('gc-luz');
                hero.addEventListener('pointermove', function (e) {
                    var r = hero.getBoundingClientRect();
                    hero.style.setProperty('--gc-x', ((e.clientX - r.left) / r.width * 100).toFixed(1) + '%');
                    hero.style.setProperty('--gc-y', ((e.clientY - r.top) / r.height * 100).toFixed(1) + '%');
                });
            }
        }

        // Faixa entorta conforme a velocidade da rolagem
        var faixa = $('.pt-faixa');
        if (faixa) {
            var entortar = g.quickTo(faixa, 'skewY', { duration: 0.5, ease: 'power3' });
            ST.create({ onUpdate: function (s) { entortar(g.utils.clamp(-4, 4, s.getVelocity() / -400)); } });
        }

        // Como funciona: linha de progresso e passos acendendo em sequência
        var passos = $('[data-cena="passos"]');
        if (passos) {
            var itens = $$('li', passos);
            var linha = document.createElement('li'); // dentro de <ol> só pode <li> (acessibilidade)
            linha.className = 'gc-linha'; linha.setAttribute('aria-hidden', 'true'); linha.setAttribute('role', 'presentation');
            passos.appendChild(linha);
            ST.create({
                trigger: passos, start: 'top 75%', end: 'bottom 45%', scrub: true,
                onUpdate: function (s) {
                    linha.style.transform = 'scaleX(' + s.progress.toFixed(3) + ')';
                    var n = Math.min(itens.length - 1, Math.floor(s.progress * itens.length));
                    itens.forEach(function (li, i) { li.classList.toggle('gc-aceso', i <= n); });
                }
            });
        }

        // Itens de serviços/cardápio entram em cascata
        ST.batch($$('.gds-menu-item, .gds-svc, .pt-motivos article, .pt-faq details'), {
            start: 'top 92%', once: true,
            onEnter: function (els) { g.fromTo(els, { y: 34, opacity: 0 }, { y: 0, opacity: 1, duration: 0.8, stagger: 0.07, ease: 'power3.out', overwrite: true, clearProps: 'transform' }); }
        });

        // Títulos de seção: as letras ganham espaço ao entrar
        $$('.pt-cab h2').forEach(function (h) {
            g.fromTo(h, { letterSpacing: '0.06em' }, { letterSpacing: getComputedStyle(h).letterSpacing, ease: 'none', scrollTrigger: { trigger: h, start: 'top 95%', end: 'top 55%', scrub: true } });
        });

        // Chamada final: foto aproximando
        var fim = $('[data-cena="final"] .pt-cta-foto img');
        if (fim) g.fromTo(fim, { scale: 1.25 }, { scale: 1, ease: 'none', scrollTrigger: { trigger: fim.closest('section'), start: 'top bottom', end: 'bottom bottom', scrub: true } });

        window.addEventListener('load', function () { ST.refresh(); });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();
})();
