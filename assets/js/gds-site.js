/* ============================================================
   GDS — comportamento dos componentes do site público
   (agendamento passo a passo, calendário com horários livres,
   cupom, filtros de serviços, galeria ampliada, copiar código,
   balão do WhatsApp). Sem dependências.
   ============================================================ */
(function () {
    'use strict';
    document.documentElement.classList.add('gds-js');

    var $ = function (sel, el) { return (el || document).querySelector(sel); };
    var $$ = function (sel, el) { return Array.prototype.slice.call((el || document).querySelectorAll(sel)); };
    var pad = function (n) { return String(n).padStart(2, '0'); };
    var iso = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
    var MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    var SEMANA = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
    var dinheiro = function (v) { return 'R$ ' + Number(v).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };

    // Mostra a "página" do site (navegação feita pela IA ou pelo template) onde o elemento está
    function mostrarPaginaDe(el) {
        if (!el) return;
        var pag = el.closest('[data-page]');
        if (pag) {
            var chave = pag.getAttribute('data-page');
            if (typeof window.gdsMostrarPagina === 'function') { try { window.gdsMostrarPagina(chave); } catch (e) { } }
            if (getComputedStyle(pag).display === 'none') {
                $$('[data-page]').forEach(function (p) { p.style.setProperty('display', p === pag ? 'block' : 'none', 'important'); });
            }
        }
        setTimeout(function () { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 80);
    }
    window.gdsMostrarPaginaDe = mostrarPaginaDe;

    // ---------------- máscara de telefone ----------------
    $$('[data-gds-tel]').forEach(function (inp) {
        inp.addEventListener('input', function () {
            var d = inp.value.replace(/\D/g, '').slice(0, 11);
            if (d.length > 10) inp.value = '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
            else if (d.length > 6) inp.value = '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
            else if (d.length > 2) inp.value = '(' + d.slice(0, 2) + ') ' + d.slice(2);
            else inp.value = d;
        });
    });

    // ---------------- copiar (cupom, PIX) ----------------
    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-gds-copiar]');
        if (!b) return;
        var txt = b.getAttribute('data-gds-copiar');
        var ok = function () { var t = b.textContent; b.textContent = 'Copiado! ✓'; setTimeout(function () { b.textContent = t; }, 1800); };
        if (navigator.clipboard) navigator.clipboard.writeText(txt).then(ok, ok);
        else { var ta = document.createElement('textarea'); ta.value = txt; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch (e) { } ta.remove(); ok(); }
    });

    // ---------------- filtro de categorias dos serviços ----------------
    $$('[data-gds-filtravel]').forEach(function (box) {
        $$('[data-gds-filtro]', box).forEach(function (btn) {
            btn.addEventListener('click', function () {
                var cat = btn.getAttribute('data-gds-filtro');
                $$('[data-gds-filtro]', box).forEach(function (b) { b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'); });
                $$('.gds-svc', box).forEach(function (c) { c.hidden = cat !== '' && c.getAttribute('data-gds-cat') !== cat; });
            });
        });
    });

    // ---------------- galeria ampliada ----------------
    $$('[data-gds-galeria]').forEach(function (gal) {
        var fotos = $$('[data-gds-foto]', gal).map(function (b) { return b.getAttribute('data-gds-foto'); });
        $$('[data-gds-foto]', gal).forEach(function (btn, i) { btn.addEventListener('click', function () { abrir(i); }); });
        function abrir(i) {
            var atual = i, origem = document.activeElement;
            var lb = document.createElement('div');
            lb.className = 'gds-lightbox'; lb.setAttribute('role', 'dialog'); lb.setAttribute('aria-modal', 'true');
            lb.innerHTML = '<img alt=""><button class="gds-lb-fechar" aria-label="Fechar">✕</button>' +
                (fotos.length > 1 ? '<button class="gds-lb-ant" aria-label="Anterior">‹</button><button class="gds-lb-prox" aria-label="Próxima">›</button><span class="gds-lb-cont"></span>' : '');
            var img = $('img', lb);
            function mostrar() { img.src = fotos[atual]; var c = $('.gds-lb-cont', lb); if (c) c.textContent = (atual + 1) + ' / ' + fotos.length; }
            function fechar() { lb.remove(); document.removeEventListener('keydown', tecla); document.body.style.overflow = ''; if (origem) origem.focus(); }
            function passo(d) { atual = (atual + d + fotos.length) % fotos.length; mostrar(); }
            function tecla(e) { if (e.key === 'Escape') fechar(); if (e.key === 'ArrowLeft') passo(-1); if (e.key === 'ArrowRight') passo(1); }
            lb.addEventListener('click', function (e) {
                if (e.target === lb || e.target.closest('.gds-lb-fechar')) fechar();
                else if (e.target.closest('.gds-lb-ant')) passo(-1);
                else if (e.target.closest('.gds-lb-prox')) passo(1);
            });
            var x0 = null;
            lb.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
            lb.addEventListener('touchend', function (e) { if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 50) passo(dx < 0 ? 1 : -1); x0 = null; });
            document.addEventListener('keydown', tecla);
            document.body.style.overflow = 'hidden';
            document.body.appendChild(lb); mostrar(); $('.gds-lb-fechar', lb).focus();
        }
    });

    // ---------------- balão do WhatsApp ----------------
    $$('[data-gds-wa]').forEach(function (wa) {
        var balao = $('.gds-wa-balao', wa);
        var chave = 'gds-wa-fechado';
        var fechado = false;
        try { fechado = sessionStorage.getItem(chave) === '1'; } catch (e) { }
        if (balao && !fechado && !document.querySelector('[data-gds-sucesso]')) {
            setTimeout(function () { balao.hidden = false; }, 7000);
            $('button', balao).addEventListener('click', function () {
                balao.hidden = true;
                try { sessionStorage.setItem(chave, '1'); } catch (e) { }
            });
        }
    });

    // ---------------- AGENDAMENTO PASSO A PASSO ----------------
    $$('[data-gds-agenda]').forEach(function (raiz) {
        var cfg = JSON.parse(raiz.getAttribute('data-gds-agenda'));
        var form = $('form', raiz);
        var passos = $$('[data-gds-passo]', raiz);
        var nav = $('[data-gds-nav]', raiz);
        var btnVoltar = $('[data-gds-voltar]', raiz), btnAvancar = $('[data-gds-avancar]', raiz);
        var valor = $('[data-gds-valor]', raiz);
        var horasBox = $('[data-gds-horas]', raiz), grade = $('[data-gds-dias]', raiz), rotMes = $('[data-gds-mes-rot]', raiz);
        var resumo = $('[data-gds-resumo]', raiz);
        var hoje = new Date(); hoje.setHours(0, 0, 0, 0);
        var limite = new Date(hoje); limite.setDate(limite.getDate() + (cfg.maxDias > 0 ? cfg.maxDias : 3650));
        var mesVisto = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
        var diaEscolhido = null, horaEscolhida = null, fechados = {}, cupom = null, req = 0, atual = 0;

        // Volta para o estado de um envio com erro (dia/hora já escolhidos)
        if (valor.value && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(valor.value)) {
            var p = valor.value.split('T'); var d = p[0].split('-');
            diaEscolhido = new Date(+d[0], +d[1] - 1, +d[2]); horaEscolhida = p[1];
            mesVisto = new Date(diaEscolhido.getFullYear(), diaEscolhido.getMonth(), 1);
        }

        function marcado(nome) { return $('input[name="' + nome + '"]:checked', form); }
        function duracao() { var s = marcado('ag_servico_id'); var v = s ? parseInt(s.getAttribute('data-duracao'), 10) : 0; return v > 0 ? v : cfg.intervalo; }

        function irPara(i, focar) {
            atual = Math.max(0, Math.min(passos.length - 1, i));
            passos.forEach(function (p, k) { p.classList.toggle('ativo', k === atual); });
            var chave = passos[atual].getAttribute('data-gds-passo');
            $$('[data-gds-ind]', raiz).forEach(function (ind) {
                var k = passos.findIndex(function (p) { return p.getAttribute('data-gds-passo') === ind.getAttribute('data-gds-ind'); });
                ind.classList.toggle('feito', k < atual); ind.classList.toggle('atual', k === atual);
            });
            nav.hidden = false;
            btnVoltar.style.visibility = atual === 0 ? 'hidden' : 'visible';
            btnAvancar.hidden = chave === 'dados';
            atualizarAvancar();
            if (chave === 'data') { renderMes(); if (diaEscolhido) carregarHoras(); }
            if (chave === 'dados') montarResumo();
            if (focar) raiz.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        function passoValido(chave) {
            if (chave === 'servico') return !!marcado('ag_servico_id');
            if (chave === 'data') return !!valor.value;
            return true;
        }
        function atualizarAvancar() {
            var chave = passos[atual].getAttribute('data-gds-passo');
            btnAvancar.disabled = !passoValido(chave);
        }
        btnAvancar.addEventListener('click', function () { if (passoValido(passos[atual].getAttribute('data-gds-passo'))) irPara(atual + 1, true); });
        btnVoltar.addEventListener('click', function () { irPara(atual - 1, true); });

        // Escolher serviço/profissional já avança sozinho
        // ("click" e não "change": clicar na opção que já vem marcada, como "Sem preferência", também avança)
        $$('input[name="ag_servico_id"], input[name="ag_profissional_id"]', form).forEach(function (r) {
            r.addEventListener('change', function () { horaEscolhida = null; valor.value = ''; atualizarAvancar(); });
            r.addEventListener('click', function () {
                var passoDoRadio = r.closest('[data-gds-passo]');
                if (!passoDoRadio || !passoDoRadio.classList.contains('ativo')) return;
                setTimeout(function () { irPara(atual + 1, false); }, 220);
            });
        });

        // --- calendário ---
        function carregarFechados() {
            var chave = mesVisto.getFullYear() + '-' + pad(mesVisto.getMonth() + 1);
            if (fechados[chave]) return;
            fechados[chave] = [];
            fetch('/site/horarios.php?e=' + cfg.empresa + '&mes=' + chave).then(function (r) { return r.json(); }).then(function (d) {
                fechados[chave] = d.fechados || []; renderMes();
            }).catch(function () { });
        }
        function renderMes() {
            var chaveMes = mesVisto.getFullYear() + '-' + pad(mesVisto.getMonth() + 1);
            carregarFechados();
            rotMes.textContent = MESES[mesVisto.getMonth()] + ' ' + mesVisto.getFullYear();
            grade.innerHTML = '';
            var primeiro = new Date(mesVisto.getFullYear(), mesVisto.getMonth(), 1).getDay();
            var total = new Date(mesVisto.getFullYear(), mesVisto.getMonth() + 1, 0).getDate();
            for (var i = 0; i < primeiro; i++) grade.appendChild(document.createElement('span'));
            for (var dia = 1; dia <= total; dia++) {
                var data = new Date(mesVisto.getFullYear(), mesVisto.getMonth(), dia);
                var b = document.createElement('button');
                b.type = 'button'; b.className = 'gds-dia'; b.textContent = dia;
                var fechado = cfg.dias.indexOf(data.getDay()) === -1 || (fechados[chaveMes] || []).indexOf(iso(data)) !== -1;
                b.disabled = data < hoje || data > limite || fechado;
                if (fechado && data >= hoje) b.classList.add('fechado');
                b.setAttribute('aria-label', dia + ' de ' + MESES[data.getMonth()] + (fechado ? ', fechado' : ''));
                if (data.getTime() === hoje.getTime()) b.classList.add('hoje');
                b.setAttribute('aria-pressed', diaEscolhido && data.getTime() === diaEscolhido.getTime() ? 'true' : 'false');
                (function (d) { b.addEventListener('click', function () { diaEscolhido = d; horaEscolhida = null; valor.value = ''; atualizarAvancar(); renderMes(); carregarHoras(); }); })(data);
                grade.appendChild(b);
            }
            var ant = $('[data-gds-mes="-1"]', raiz);
            ant.disabled = mesVisto <= new Date(hoje.getFullYear(), hoje.getMonth(), 1);
            $('[data-gds-mes="1"]', raiz).disabled = new Date(mesVisto.getFullYear(), mesVisto.getMonth() + 1, 1) > limite;
        }
        $$('[data-gds-mes]', raiz).forEach(function (b) {
            b.addEventListener('click', function () { mesVisto.setMonth(mesVisto.getMonth() + parseInt(b.getAttribute('data-gds-mes'), 10)); renderMes(); });
        });

        function carregarHoras() {
            if (!diaEscolhido) return;
            var id = ++req;
            horasBox.innerHTML = '<p class="gds-carregando">Buscando horários livres…</p>';
            var prof = marcado('ag_profissional_id');
            var url = '/site/horarios.php?e=' + cfg.empresa + '&data=' + iso(diaEscolhido) + '&duracao=' + duracao() + (prof && prof.value ? '&profissional=' + prof.value : '');
            fetch(url).then(function (r) { return r.json(); }).then(function (d) {
                if (id !== req) return;
                renderHoras(d);
            }).catch(function () { horasBox.innerHTML = '<p class="gds-mute">Não foi possível carregar os horários. Tente de novo.</p>'; });
        }
        function renderHoras(d) {
            var agora = new Date(), ehHoje = diaEscolhido.getTime() === hoje.getTime();
            var minAgora = agora.getHours() * 60 + agora.getMinutes() + 30;
            var dSem = SEMANA[diaEscolhido.getDay()];
            var titulo = '<p class="gds-rotulo" style="margin-bottom:12px">' + dSem.charAt(0).toUpperCase() + dSem.slice(1) + ', ' + diaEscolhido.getDate() + ' de ' + MESES[diaEscolhido.getMonth()] + '</p>';
            if (d.fechado) { horasBox.innerHTML = titulo + '<p class="gds-mute">Não vamos atender neste dia. Escolha outra data.</p>'; return; }
            var todos = (d.livres || []).map(function (h) { return [h, true]; }).concat((d.ocupados || []).map(function (h) { return [h, false]; }));
            todos.sort(function (a, b) { return a[0] < b[0] ? -1 : 1; });
            todos = todos.filter(function (t) { var p = t[0].split(':'); return !ehHoje || (+p[0] * 60 + +p[1]) > minAgora; });
            var turnos = [['Manhã', 0, 720], ['Tarde', 720, 1080], ['Noite', 1080, 1440]];
            var html = titulo, livres = 0;
            turnos.forEach(function (t) {
                var lista = todos.filter(function (h) { var p = h[0].split(':'); var m = +p[0] * 60 + +p[1]; return m >= t[1] && m < t[2]; });
                if (!lista.length) return;
                html += '<div class="gds-turno"><p class="gds-turno-tit">' + t[0] + '</p><div class="gds-horas">';
                lista.forEach(function (h) {
                    if (h[1]) livres++;
                    html += '<button type="button" class="gds-hora" data-hora="' + h[0] + '"' + (h[1] ? '' : ' disabled title="Ocupado"') + ' aria-pressed="' + (h[0] === horaEscolhida ? 'true' : 'false') + '">' + h[0] + '</button>';
                });
                html += '</div></div>';
            });
            if (!livres) html += '<p class="gds-mute">Sem horários livres neste dia' + (marcado('ag_profissional_id') && marcado('ag_profissional_id').value ? ' com esse profissional' : '') + '. Tente outro dia.</p>';
            horasBox.innerHTML = html;
            $$('.gds-hora:not([disabled])', horasBox).forEach(function (b) {
                b.addEventListener('click', function () {
                    horaEscolhida = b.getAttribute('data-hora');
                    valor.value = iso(diaEscolhido) + 'T' + horaEscolhida;
                    $$('.gds-hora', horasBox).forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
                    atualizarAvancar();
                    setTimeout(function () { irPara(atual + 1, false); }, 250);
                });
            });
        }

        // --- resumo e cupom ---
        function montarResumo() {
            if (!resumo) return;
            var s = marcado('ag_servico_id'), p = marcado('ag_profissional_id');
            var linhas = [];
            if (s) linhas.push(['Serviço', s.getAttribute('data-nome')]);
            if (p && p.value) linhas.push(['Profissional', p.getAttribute('data-nome')]);
            if (diaEscolhido && horaEscolhida) linhas.push(['Quando', SEMANA[diaEscolhido.getDay()] + ', ' + pad(diaEscolhido.getDate()) + '/' + pad(diaEscolhido.getMonth() + 1) + ' às ' + horaEscolhida]);
            var preco = s ? parseFloat(s.getAttribute('data-preco')) : 0;
            var html = linhas.map(function (l) { return '<div><span>' + l[0] + '</span><span></span></div>'; }).join('');
            if (preco > 0) {
                if (cupom) html += '<div><span>Cupom ' + cupom.codigo + '</span><span>−' + cupom.desconto + '%</span></div>';
                html += '<div class="gds-total"><span>Total</span><span>' + dinheiro(cupom ? preco * (1 - cupom.desconto / 100) : preco) + '</span></div>';
            }
            resumo.innerHTML = html;
            // textos entram via textContent (nada de HTML vindo do usuário)
            $$('div', resumo).slice(0, linhas.length).forEach(function (div, i) { div.lastChild.textContent = linhas[i][1]; });
            resumo.hidden = !html;
        }
        var btnCupom = $('[data-gds-aplicar-cupom]', raiz);
        if (btnCupom) {
            var campoCupom = $('#ag_cupom', raiz), msgCupom = $('[data-gds-cupom-msg]', raiz);
            var aplicar = function () {
                var c = campoCupom.value.trim().toUpperCase();
                if (!c) { cupom = null; msgCupom.textContent = ''; montarResumo(); return; }
                msgCupom.textContent = 'Verificando…'; msgCupom.style.color = '';
                fetch('/site/cupom.php?e=' + cfg.empresa + '&c=' + encodeURIComponent(c)).then(function (r) { return r.json(); }).then(function (d) {
                    if (d.ok) { cupom = { codigo: c, desconto: d.desconto }; msgCupom.textContent = '✓ Cupom aplicado: ' + d.desconto + '% de desconto'; msgCupom.style.color = 'var(--gds-ok)'; }
                    else { cupom = null; msgCupom.textContent = d.erro || 'Cupom inválido.'; msgCupom.style.color = 'var(--gds-erro)'; }
                    montarResumo();
                }).catch(function () { msgCupom.textContent = ''; });
            };
            btnCupom.addEventListener('click', aplicar);
            campoCupom.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); aplicar(); } });
            if (campoCupom.value) aplicar();
        }

        // --- envio ---
        form.addEventListener('submit', function (ev) {
            var faltando = null;
            passos.some(function (p, i) { if (!passoValido(p.getAttribute('data-gds-passo'))) { faltando = i; return true; } return false; });
            if (faltando !== null) { ev.preventDefault(); irPara(faltando, true); return; }
            var nome = $('#ag_nome', form), tel = $('#ag_telefone', form);
            if (!nome.value.trim()) { ev.preventDefault(); nome.focus(); nome.reportValidity && nome.reportValidity(); return; }
            if (tel.value.replace(/\D/g, '').length < 10) { ev.preventDefault(); tel.setCustomValidity('Informe o WhatsApp com DDD'); tel.reportValidity(); tel.setCustomValidity(''); tel.focus(); return; }
            var b = $('[data-gds-confirmar]', form); b.disabled = true; b.textContent = 'Enviando…';
        });

        // --- atalhos "Agendar este serviço" / "Agendar com fulano" ---
        document.addEventListener('click', function (ev) {
            var a = ev.target.closest('[data-gds-agendar-servico], [data-gds-agendar-profissional]');
            if (!a) return;
            ev.preventDefault();
            var sId = a.getAttribute('data-gds-agendar-servico'), pId = a.getAttribute('data-gds-agendar-profissional');
            var r = sId ? $('input[name="ag_servico_id"][value="' + sId + '"]', form) : $('input[name="ag_profissional_id"][value="' + pId + '"]', form);
            if (r) { r.checked = true; horaEscolhida = null; valor.value = ''; }
            var alvo = passos.findIndex(function (p) { return !passoValido(p.getAttribute('data-gds-passo')); });
            var seguinte = passos.findIndex(function (p) { return p.getAttribute('data-gds-passo') === (sId ? 'profissional' : 'data'); });
            irPara(sId ? (seguinte > -1 ? seguinte : alvo) : (alvo > -1 && alvo < seguinte ? alvo : seguinte), false);
            mostrarPaginaDe(raiz);
        });

        // Passo inicial: depois de um erro, volta para onde fazia sentido
        var inicio = 0;
        if ($('.gds-alerta-erro', raiz)) {
            inicio = passos.findIndex(function (p) { return !passoValido(p.getAttribute('data-gds-passo')); });
            if (inicio < 0) inicio = passos.length - 1;
        }
        irPara(inicio, false);
    });

    // Depois de enviar um formulário, abre a página onde está o resultado
    document.addEventListener('DOMContentLoaded', function () {
        var alvo = document.querySelector('.gds-c [data-gds-sucesso], .gds-c .gds-sucesso, .gds-c .gds-alerta');
        if (alvo) mostrarPaginaDe(alvo.closest('.gds-c'));
    });
})();
