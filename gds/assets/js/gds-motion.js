/* ============================================================
   GDS MOTION — kit de animações dos sites (sem dependências).
   Use com atributos no HTML:
     data-anim="fade-up|fade-down|fade-in|zoom-in|slide-left|slide-right|blur-in|clip-up"
     data-delay="150"            atraso em ms
     data-stagger                filhos diretos entram em sequência
     data-split                  título entra palavra por palavra
     data-counter="250"          número conta até o valor (data-prefixo / data-sufixo)
     data-parallax="0.15"        elemento se move mais devagar que a rolagem
     data-tilt                   cartão inclina em 3D seguindo o mouse
     data-magnetic               botão "puxa" levemente para o cursor
     data-marquee                faixa rolando sem parar (duplica o conteúdo)
     data-progress               barra de progresso de leitura no topo
     data-header-scroll          cabeçalho ganha fundo ao rolar (classe .rolou)
   Respeita "reduzir movimento" do sistema.
   ============================================================ */
(function () {
    'use strict';
    var reduzir = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
    document.documentElement.classList.add('gm-js');

    function iniciar() {
        // Título palavra por palavra
        $$('[data-split]').forEach(function (el) {
            if (el.dataset.splitPronto) return;
            el.dataset.splitPronto = '1';
            var palavras = el.textContent.trim().split(/\s+/);
            el.setAttribute('aria-label', el.textContent.trim());
            el.innerHTML = palavras.map(function (p, i) {
                return '<span class="gm-palavra" aria-hidden="true"><span style="transition-delay:' + (i * 70) + 'ms">' + p.replace(/</g, '&lt;') + '</span></span>';
            }).join(' ');
            if (!el.hasAttribute('data-anim')) el.setAttribute('data-anim', 'split');
        });
        // Sequência nos filhos
        $$('[data-stagger]').forEach(function (pai) {
            var passo = parseInt(pai.getAttribute('data-stagger'), 10) || 90;
            Array.prototype.slice.call(pai.children).forEach(function (f, i) {
                if (!f.hasAttribute('data-anim')) f.setAttribute('data-anim', pai.getAttribute('data-stagger-anim') || 'fade-up');
                if (!f.hasAttribute('data-delay')) f.setAttribute('data-delay', String(i * passo));
            });
        });
        var alvos = $$('[data-anim]');
        alvos.forEach(function (el) { var d = el.getAttribute('data-delay'); if (d) el.style.transitionDelay = d + 'ms'; });
        if (reduzir || !('IntersectionObserver' in window)) { alvos.forEach(function (el) { el.classList.add('gm-visivel'); }); }
        else {
            var io = new IntersectionObserver(function (ents) {
                ents.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('gm-visivel'); io.unobserve(en.target); } });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
            alvos.forEach(function (el) { io.observe(el); });
        }

        // Contadores
        var contar = function (el) {
            var alvo = parseFloat(el.getAttribute('data-counter')) || 0;
            var casas = (el.getAttribute('data-counter').split('.')[1] || '').length;
            var pre = el.getAttribute('data-prefixo') || '', suf = el.getAttribute('data-sufixo') || '';
            var fmt = function (v) { return pre + v.toFixed(casas).replace('.', ',') + suf; };
            if (reduzir) { el.textContent = fmt(alvo); return; }
            var t0 = null, dur = 1400;
            var passo = function (t) { if (!t0) t0 = t; var p = Math.min(1, (t - t0) / dur); el.textContent = fmt(alvo * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(passo); };
            requestAnimationFrame(passo);
        };
        var contadores = $$('[data-counter]');
        if ('IntersectionObserver' in window) {
            var ioc = new IntersectionObserver(function (ents) { ents.forEach(function (en) { if (en.isIntersecting) { contar(en.target); ioc.unobserve(en.target); } }); }, { threshold: 0.5 });
            contadores.forEach(function (el) { ioc.observe(el); });
        } else contadores.forEach(contar);

        // Faixa rolando
        $$('[data-marquee]').forEach(function (el) {
            if (el.dataset.marqueePronto) return;
            el.dataset.marqueePronto = '1';
            var trilho = document.createElement('div');
            trilho.className = 'gm-trilho';
            while (el.firstChild) trilho.appendChild(el.firstChild);
            var copia = trilho.cloneNode(true); copia.setAttribute('aria-hidden', 'true');
            var caixa = document.createElement('div'); caixa.className = 'gm-marquee-caixa';
            caixa.appendChild(trilho); caixa.appendChild(copia);
            el.appendChild(caixa);
        });

        if (reduzir) return;

        // Paralaxe
        var parallax = $$('[data-parallax]');
        if (parallax.length) {
            var agendado = false;
            var mover = function () {
                agendado = false;
                parallax.forEach(function (el) {
                    var r = el.getBoundingClientRect();
                    if (r.bottom < -200 || r.top > innerHeight + 200) return;
                    var v = parseFloat(el.getAttribute('data-parallax')) || 0.15;
                    el.style.transform = 'translate3d(0,' + ((r.top + r.height / 2 - innerHeight / 2) * -v).toFixed(1) + 'px,0)';
                });
            };
            addEventListener('scroll', function () { if (!agendado) { agendado = true; requestAnimationFrame(mover); } }, { passive: true });
            mover();
        }

        // Inclinação 3D
        if (matchMedia('(hover: hover)').matches) {
            $$('[data-tilt]').forEach(function (el) {
                el.addEventListener('mousemove', function (ev) {
                    var r = el.getBoundingClientRect();
                    var x = (ev.clientX - r.left) / r.width - 0.5, y = (ev.clientY - r.top) / r.height - 0.5;
                    el.style.transform = 'perspective(900px) rotateX(' + (-y * 6).toFixed(2) + 'deg) rotateY(' + (x * 8).toFixed(2) + 'deg) translateY(-4px)';
                    el.style.setProperty('--gm-mx', (x + 0.5) * 100 + '%'); el.style.setProperty('--gm-my', (y + 0.5) * 100 + '%');
                });
                el.addEventListener('mouseleave', function () { el.style.transform = ''; });
            });
            $$('[data-magnetic]').forEach(function (el) {
                el.addEventListener('mousemove', function (ev) {
                    var r = el.getBoundingClientRect();
                    el.style.transform = 'translate(' + ((ev.clientX - r.left - r.width / 2) * 0.18).toFixed(1) + 'px,' + ((ev.clientY - r.top - r.height / 2) * 0.25).toFixed(1) + 'px)';
                });
                el.addEventListener('mouseleave', function () { el.style.transform = ''; });
            });
        }
    }

    // Barra de progresso e cabeçalho que muda ao rolar
    function rolagem() {
        var barra = document.querySelector('[data-progress]');
        var cab = $$('[data-header-scroll]');
        var at = function () {
            var h = document.documentElement;
            if (barra) barra.style.transform = 'scaleX(' + (h.scrollTop / Math.max(1, h.scrollHeight - h.clientHeight)) + ')';
            cab.forEach(function (c) { c.classList.toggle('rolou', h.scrollTop > 30); });
        };
        addEventListener('scroll', at, { passive: true }); at();
    }

    // Âncoras com rolagem suave (desconta o cabeçalho fixo)
    document.addEventListener('click', function (ev) {
        var a = ev.target.closest && ev.target.closest('a[href^="#"]:not([href="#"]):not([href^="#gds-"])');
        if (!a || a.hasAttribute('data-page-link')) return;
        var alvo = document.getElementById(a.getAttribute('href').slice(1));
        if (!alvo || alvo.closest('[data-page]')) return;
        ev.preventDefault();
        var cab = document.querySelector('[data-header-scroll]');
        scrollTo({ top: alvo.getBoundingClientRect().top + scrollY - (cab ? cab.offsetHeight + 8 : 0), behavior: reduzir ? 'auto' : 'smooth' });
        if (history.replaceState) history.replaceState(null, '', a.getAttribute('href'));
        document.body.classList.remove('gm-menu-aberto');
    });

    window.GDSMotion = { atualizar: iniciar };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { iniciar(); rolagem(); });
    else { iniciar(); rolagem(); }
})();
