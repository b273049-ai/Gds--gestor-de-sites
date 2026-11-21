/* GDS — visual Vitrine: troca do produto do topo, flutuação com o mouse, cartões que entram
   ao rolar e o produto em destaque que sobe e gira com a rolagem. Respeita "reduzir movimento". */
(function () {
    'use strict';
    var html = document.documentElement;
    var calmo = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!calmo) html.classList.add('vt-js');

    // ---------- topo: um produto de cada vez, a cor do fundo acompanha ----------
    var hero = document.querySelector('[data-vt-hero]');
    if (hero) {
        var slides = [].slice.call(hero.querySelectorAll('[data-vt-slide]'));
        var nome = hero.querySelector('[data-vt-nome]'), desc = hero.querySelector('[data-vt-desc]'), texto = hero.querySelector('.vt-base-texto');
        var atual = 0, timer = null, pausa = false;
        var ir = function (n) {
            if (slides.length < 2) return;
            n = (n + slides.length) % slides.length;
            if (n === atual) return;
            var velho = slides[atual], novo = slides[n];
            velho.classList.remove('ativo'); velho.classList.add('sai'); velho.setAttribute('aria-hidden', 'true');
            setTimeout(function () { velho.classList.remove('sai'); }, 900);
            novo.classList.add('entra'); novo.removeAttribute('aria-hidden');
            void novo.offsetWidth;
            novo.classList.remove('entra'); novo.classList.add('ativo');
            hero.style.setProperty('--vt-bg', novo.getAttribute('data-cor'));
            if (nome) nome.textContent = novo.getAttribute('data-nome');
            if (desc) desc.textContent = novo.getAttribute('data-desc') || '';
            if (texto) { texto.classList.remove('troca'); void texto.offsetWidth; texto.classList.add('troca'); }
            var tema = document.querySelector('meta[name=theme-color]'); if (tema) tema.setAttribute('content', novo.getAttribute('data-cor'));
            atual = n;
        };
        var auto = function () { clearInterval(timer); if (!calmo && slides.length > 1) timer = setInterval(function () { if (!pausa && !document.hidden) ir(atual + 1); }, 4800); };
        [].forEach.call(hero.querySelectorAll('[data-vt-ir]'), function (b) { b.addEventListener('click', function () { ir(atual + parseInt(b.getAttribute('data-vt-ir'), 10)); auto(); }); });
        hero.addEventListener('mouseenter', function () { pausa = true; });
        hero.addEventListener('mouseleave', function () { pausa = false; });
        hero.addEventListener('focusin', function () { pausa = true; });
        hero.addEventListener('focusout', function () { pausa = false; });
        auto();
        // produtos flutuando acompanham o mouse de leve (só computador)
        var fluts = [].slice.call(hero.querySelectorAll('.vt-flut'));
        if (!calmo && fluts.length && matchMedia('(pointer: fine)').matches) {
            var raf = 0;
            hero.addEventListener('pointermove', function (e) {
                if (raf) return;
                raf = requestAnimationFrame(function () {
                    raf = 0;
                    var r = hero.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
                    fluts.forEach(function (f, i) { var k = i ? -18 : 26; f.style.translate = (x * k) + 'px ' + (y * k) + 'px'; });
                });
            });
        }
    }

    // ---------- cartões entram ao rolar ----------
    var cards = [].slice.call(document.querySelectorAll('.vt-card'));
    if (!calmo && 'IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('visto'); io.unobserve(e.target); } }); }, { rootMargin: '0px 0px -6% 0px' });
        cards.forEach(function (c) { io.observe(c); });
        // segurança: se algo falhar, nada fica escondido
        setTimeout(function () { cards.forEach(function (c) { if (c.getBoundingClientRect().top < innerHeight) c.classList.add('visto'); }); }, 1800);
    } else cards.forEach(function (c) { c.classList.add('visto'); });

    // ---------- destaque: sobe e gira conforme a rolagem ----------
    var dest = document.querySelector('[data-vt-rola]');
    if (dest && !calmo) {
        var pend = false;
        var calc = function () {
            pend = false;
            var r = dest.getBoundingClientRect(), p = 1 - (r.top + r.height / 2) / (innerHeight + r.height / 2);
            dest.style.setProperty('--p', Math.max(0, Math.min(1, p)).toFixed(3));
        };
        addEventListener('scroll', function () { if (!pend) { pend = true; requestAnimationFrame(calc); } }, { passive: true });
        calc();
    }
})();
