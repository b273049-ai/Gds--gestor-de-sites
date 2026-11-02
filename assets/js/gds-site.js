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
            var svSel = marcado('ag_servico_id');
            var url = '/site/horarios.php?e=' + cfg.empresa + '&data=' + iso(diaEscolhido) + '&duracao=' + duracao() + (prof && prof.value ? '&profissional=' + prof.value : '') + (svSel && svSel.value ? '&servico=' + svSel.value : '');
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
                    var vg = d.vagas && h[1] ? d.vagas[h[0]] : null;
                    html += '<button type="button" class="gds-hora' + (vg ? ' gds-hora-turma' : '') + '" data-hora="' + h[0] + '"' + (h[1] ? '' : ' disabled title="' + (d.turma ? 'Turma lotada' : 'Ocupado') + '"') + ' aria-pressed="' + (h[0] === horaEscolhida ? 'true' : 'false') + '">' + h[0]
                        + (vg ? '<small>' + (vg === 1 ? 'última vaga' : vg + ' vagas') + '</small>' : '') + '</button>';
                });
                html += '</div></div>';
            });
            if (!livres) html += '<p class="gds-mute">Sem horários livres neste dia' + (marcado('ag_profissional_id') && marcado('ag_profissional_id').value ? ' com esse profissional' : '') + '. Tente outro dia.</p>';
            // Lista de espera: se alguém cancelar, a pessoa é avisada no WhatsApp
            if (!livres && cfg.espera) html += '<div class="gds-espera" data-gds-espera><p class="gds-rotulo">Quer esse dia mesmo? Entre na lista de espera</p><p class="gds-mute" style="margin:0 0 10px">Se alguém cancelar, avisamos você no WhatsApp na hora.</p>'
                + '<div class="gds-linha2"><div class="gds-campo"><label for="esp_nome">Seu nome</label><input id="esp_nome" type="text" maxlength="120"></div>'
                + '<div class="gds-campo"><label for="esp_tel">WhatsApp</label><input id="esp_tel" type="tel" inputmode="tel" data-gds-tel placeholder="(00) 00000-0000"></div></div>'
                + '<button type="button" class="gds-btn-2" data-gds-espera-entrar>Entrar na lista de espera</button><p class="gds-mute" data-gds-espera-msg role="status"></p></div>';
            horasBox.innerHTML = html;
            var esp = horasBox.querySelector('[data-gds-espera-entrar]');
            if (esp) esp.addEventListener('click', function () {
                var nome = horasBox.querySelector('#esp_nome').value.trim(), tel = horasBox.querySelector('#esp_tel').value.trim(), msg = horasBox.querySelector('[data-gds-espera-msg]');
                if (nome.length < 2 || tel.replace(/\D/g, '').length < 10) { msg.textContent = 'Informe seu nome e WhatsApp com DDD.'; return; }
                var sv = marcado('ag_servico_id'), pr = marcado('ag_profissional_id'), fd = new FormData();
                fd.append('e', cfg.empresa); fd.append('data', iso(diaEscolhido)); fd.append('nome', nome); fd.append('telefone', tel);
                if (sv && sv.value) fd.append('servico', sv.value); if (pr && pr.value) fd.append('profissional', pr.value);
                esp.disabled = true;
                fetch('/site/espera.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (d) {
                    msg.textContent = d.ok ? '✅ Pronto! Você está na lista de espera. Se abrir um horário, avisamos no WhatsApp.' : (d.erro || 'Não foi possível agora.');
                    if (!d.ok) esp.disabled = false;
                }).catch(function () { esp.disabled = false; msg.textContent = 'Sem conexão. Tente de novo.'; });
            });
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

    // ---------------- PEDIDO DE ORÇAMENTO ----------------
    $$('[data-gds-data]').forEach(function (inp) {
        inp.addEventListener('input', function () {
            var d = inp.value.replace(/\D/g, '').slice(0, 8);
            inp.value = d.length > 4 ? d.slice(0, 2) + '/' + d.slice(2, 4) + '/' + d.slice(4) : (d.length > 2 ? d.slice(0, 2) + '/' + d.slice(2) : d);
            // Eventos: avisa na hora se a data já está reservada
            var emp = inp.getAttribute('data-gds-data-livre'), msg = inp.parentNode.querySelector('[data-gds-data-msg]');
            if (!emp || !msg) return;
            msg.textContent = ''; msg.className = 'gds-data-livre';
            if (d.length !== 8) return;
            var iso = d.slice(4) + '-' + d.slice(2, 4) + '-' + d.slice(0, 2);
            fetch('/site/data_livre.php?e=' + emp + '&data=' + iso).then(function (r) { return r.json(); }).then(function (j) {
                if (!j.controla || inp.value.replace(/\D/g, '') !== d) return;
                msg.textContent = j.livre ? '✓ Data disponível' : '✕ Data já reservada — escolha outra ou fale com a gente';
                msg.className = 'gds-data-livre ' + (j.livre ? 'gds-ok' : 'gds-ruim');
            }).catch(function () {});
        });
    });
    $$('[data-gds-form-orcamento]').forEach(function (form) {
        // Perguntas do serviço escolhido
        function perguntas() {
            var r = $('input[name="orc_servico_id"]:checked', form), id = r ? r.value : '';
            $$('[data-gds-perguntas]', form).forEach(function (b) { b.hidden = b.getAttribute('data-gds-perguntas') !== id; });
        }
        form.addEventListener('change', function (ev) { if (ev.target.name === 'orc_servico_id') perguntas(); });
        form._gdsPerguntas = perguntas;
        var fotos = $('[data-gds-fotos]', form);
        if (fotos) fotos.addEventListener('change', function () {
            var max = +fotos.getAttribute('data-gds-fotos');
            if (fotos.files.length > max) { fotos.setCustomValidity('Envie no máximo ' + max + ' fotos'); fotos.reportValidity(); fotos.value = ''; fotos.setCustomValidity(''); }
        });
        form.addEventListener('submit', function (ev) {
            var nome = $('#orc_nome', form), tel = $('#orc_tel', form);
            if (!nome.value.trim()) { ev.preventDefault(); nome.focus(); nome.reportValidity && nome.reportValidity(); return; }
            if (tel.value.replace(/\D/g, '').length < 10) { ev.preventDefault(); tel.setCustomValidity('Informe o WhatsApp com DDD'); tel.reportValidity(); tel.setCustomValidity(''); tel.focus(); return; }
            var b = $('button[type=submit]', form); b.disabled = true; b.textContent = 'Enviando…';
        });
    });

    // Botões "Pedir orçamento" dos serviços: abre o formulário já com o serviço escolhido
    document.addEventListener('click', function (ev) {
        var a = ev.target.closest('[data-gds-orcar-servico]');
        if (!a) return;
        var alvo = document.getElementById('gds-orcamento');
        if (!alvo) return;
        ev.preventDefault();
        var r = $('input[name="orc_servico_id"][value="' + a.getAttribute('data-gds-orcar-servico') + '"]', alvo);
        if (r) r.checked = true;
        var f = $('[data-gds-form-orcamento]', alvo);
        if (f && f._gdsPerguntas) f._gdsPerguntas();
        mostrarPaginaDe(alvo);
        setTimeout(function () { var c = $('#orc_detalhes', alvo); if (c) c.focus({ preventScroll: true }); }, 500);
    });

    // Links para os componentes (#gds-agendamento, #gds-orcamento, #gds-contato, #gds-servicos):
    // funcionam com qualquer navegação que a IA tenha criado (troca de "página" e rolagem)
    document.addEventListener('click', function (ev) {
        var a = ev.target.closest('a[href^="#gds-"]');
        if (!a || a.hasAttribute('data-gds-agendar-servico') || a.hasAttribute('data-gds-agendar-profissional') || a.hasAttribute('data-gds-orcar-servico')) return;
        var alvo = document.getElementById(a.getAttribute('href').slice(1));
        if (!alvo) return;
        ev.preventDefault();
        mostrarPaginaDe(alvo);
    });

    // WhatsApp sempre abre fora do site (numa aba nova / no app). Dentro de iframes
    // (pré-visualização, alguns construtores) o wa.me se recusa a carregar no mesmo quadro.
    document.addEventListener('click', function (ev) {
        var a = ev.target.closest && ev.target.closest('a[href*="wa.me/"], a[href*="api.whatsapp.com"]');
        if (!a || ev.ctrlKey || ev.metaKey || ev.shiftKey) return;
        ev.preventDefault();
        ev.stopPropagation();
        var w = window.open(a.href, '_blank'); // (com 'noopener' o retorno é sempre null)
        if (w) { try { w.opener = null; } catch (e) { } }
        else { try { window.top.location.href = a.href; } catch (e) { window.location.href = a.href; } }
    }, true);

    // Depois de enviar um formulário, abre a página onde está o resultado
    document.addEventListener('DOMContentLoaded', function () {
        var alvo = $$('.gds-c [data-gds-sucesso], .gds-c .gds-sucesso, .gds-c .gds-alerta').filter(function (el) { return !el.closest('[data-gds-carrinho]'); })[0];
        if (alvo) mostrarPaginaDe(alvo.closest('.gds-c'));
    });

    // ---------- IMÓVEIS: filtros, ficha completa com fotos, visita e link de cada imóvel (#imovel-COD) ----------
    (function () {
        var raiz = $('[data-gds-imoveis]');
        if (!raiz) return;
        var cards = $$('[data-gds-im]', raiz), filtros = $('[data-gds-im-filtros]', raiz), modal = $('[data-gds-im-modal]', raiz);
        // A janela sai de dentro da seção (que tem animações com transform) e vai para o body, acima do cabeçalho
        var casca = document.createElement('div'); casca.className = 'gds-c gds-im-casca'; document.body.appendChild(casca); casca.appendChild(modal);
        var num = function (s) { return parseFloat(String(s || '').replace(/\./g, '').replace(',', '.').replace(/[^0-9.]/g, '')) || 0; };
        var dados = function (c) { try { return JSON.parse(c.getAttribute('data-gds-im')); } catch (e) { return {}; } };
        function filtrar() {
            var f = {}; $$('[data-f]', filtros).forEach(function (x) { f[x.getAttribute('data-f')] = x.value.trim(); });
            var vis = 0, max = num(f.preco), q = (f.busca || '').toLowerCase();
            cards.forEach(function (c) {
                var d = dados(c), m = (!f.finalidade || d.fin === f.finalidade) && (!f.tipo || d.tipo === f.tipo) && (!f.bairro || d.bairro === f.bairro)
                    && (!f.quartos || d.q >= +f.quartos) && (!max || !d.preco || d.preco <= max) && (!q || c.getAttribute('data-busca').indexOf(q) >= 0);
                c.hidden = !m; if (m) vis++;
            });
            $('[data-gds-im-contagem]', raiz).textContent = vis + (vis === 1 ? ' imóvel encontrado' : ' imóveis encontrados');
            $('[data-gds-im-vazio]', raiz).hidden = vis > 0;
        }
        if (filtros) { filtros.addEventListener('input', filtrar); filtros.addEventListener('change', filtrar); }
        var din = function (v) { return 'R$ ' + Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 0 }); };
        function esc(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; }
        function abrir(c, semHash) {
            var d = dados(c), h = '';
            var fotos = d.fotos || [];
            h += '<div class="gds-im-galeria">' + (fotos.length ? '<img data-gds-im-foto src="' + encodeURI(fotos[0]) + '" alt="">' + (fotos.length > 1 ? '<button type="button" class="gds-im-nav ant" data-nav="-1" aria-label="Foto anterior">‹</button><button type="button" class="gds-im-nav prox" data-nav="1" aria-label="Próxima foto">›</button><span class="gds-im-contador" data-gds-im-contador>1/' + fotos.length + '</span>' : '') : '<div class="gds-im-semfoto">🏠</div>') + '</div>';
            if (fotos.length > 1) { h += '<div class="gds-im-miniaturas">'; fotos.forEach(function (f, i) { h += '<button type="button" data-ir="' + i + '" style="background-image:url(\'' + encodeURI(f) + '\')" aria-label="Foto ' + (i + 1) + '"></button>'; }); h += '</div>'; }
            h += '<div class="gds-im-ficha"><span class="gds-chip">' + esc(d.fin_txt) + '</span> <span class="gds-chip">' + esc(d.tipo_txt) + '</span>' + (d.sit !== 'disponivel' ? ' <span class="gds-chip gds-chip-accent">' + esc(d.sit_txt) + '</span>' : '') + ' <span class="gds-mute">Cód. ' + esc(d.cod) + '</span>';
            h += '<h3>' + esc(d.titulo) + '</h3><p class="gds-mute">' + esc([d.bairro, d.cidade].filter(Boolean).join(', ')) + '</p>';
            h += '<div class="gds-im-precos"><strong>' + esc(d.preco_txt) + '</strong>' + (d.cond ? '<span>Condomínio ' + din(d.cond) + '</span>' : '') + (d.iptu ? '<span>IPTU ' + din(d.iptu) + '</span>' : '') + '</div>';
            var pl = function (n, um, varios) { return n === 1 ? um : varios; };
            var num2 = [[d.q, pl(d.q, 'quarto', 'quartos'), '🛏'], [d.s, pl(d.s, 'suíte', 'suítes'), '🛁'], [d.b, pl(d.b, 'banheiro', 'banheiros'), '🚿'], [d.v, pl(d.v, 'vaga', 'vagas'), '🚗'], [d.area, 'm² úteis', '📐'], [d.terreno, 'm² terreno', '🌳']].filter(function (x) { return x[0]; });
            if (num2.length) { h += '<ul class="gds-im-nums">'; num2.forEach(function (x) { h += '<li><span>' + x[2] + '</span><b>' + Number(x[0]).toLocaleString('pt-BR') + '</b> ' + x[1] + '</li>'; }); h += '</ul>'; }
            var tags = (d.carac || []).slice(); if (d.fin_ok) tags.unshift('Aceita financiamento'); if (d.permuta) tags.unshift('Aceita permuta'); if (d.mob) tags.push('Mobiliado'); if (d.pet) tags.push('Aceita pet');
            if (tags.length) { h += '<div class="gds-im-tags">'; tags.forEach(function (t) { h += '<span>✓ ' + esc(t) + '</span>'; }); h += '</div>'; }
            if (d.desc) h += '<p class="gds-im-desc">' + esc(d.desc) + '</p>';
            if (d.video) h += '<p><a class="gds-btn-2" target="_blank" rel="noopener" href="' + esc(d.video) + '">▶ Ver vídeo / tour</a></p>';
            h += '</div>';
            $('[data-gds-im-conteudo]', modal).innerHTML = h;
            $('[data-gds-im-id]', modal).value = d.id;
            $('[data-gds-im-assunto]', modal).value = 'Visita: imóvel cód. ' + d.cod;
            var wa = $('[data-gds-im-wa]', modal);
            if (wa) wa.href = wa.getAttribute('data-base') + '?text=' + encodeURIComponent('Olá! Tenho interesse no imóvel cód. ' + d.cod + ' (' + d.titulo + ') — ' + d.preco_txt + '. ' + location.origin + location.pathname + '#imovel-' + d.cod);
            var atual = 0, img = $('[data-gds-im-foto]', modal), cont = $('[data-gds-im-contador]', modal);
            var ir = function (i) { if (!fotos.length) return; atual = (i + fotos.length) % fotos.length; img.src = fotos[atual]; if (cont) cont.textContent = (atual + 1) + '/' + fotos.length; };
            modal.onclick = function (e) {
                if (e.target === modal || e.target.closest('[data-gds-im-fechar]')) return fechar();
                var n = e.target.closest('[data-nav]'); if (n) ir(atual + +n.getAttribute('data-nav'));
                var m = e.target.closest('[data-ir]'); if (m) ir(+m.getAttribute('data-ir'));
            };
            var x0 = null; img && img.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
            img && img.addEventListener('touchend', function (e) { if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 40) ir(atual + (dx < 0 ? 1 : -1)); x0 = null; });
            modal.hidden = false; document.documentElement.classList.add('gds-sem-rolagem');
            if (!semHash) history.replaceState(null, '', '#imovel-' + d.cod);
            try { navigator.sendBeacon && navigator.sendBeacon('/site/imovel_ver.php?id=' + d.id); } catch (e) { }
        }
        function fechar() { modal.hidden = true; document.documentElement.classList.remove('gds-sem-rolagem'); if (/^#imovel-/.test(location.hash)) history.replaceState(null, '', location.pathname + location.search); }
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) fechar(); });
        cards.forEach(function (c) { c.addEventListener('click', function () { abrir(c); }); c.addEventListener('keydown', function (e) { if (e.key === 'Enter') abrir(c); }); });
        filtrar();
        var m = location.hash.match(/^#imovel-(.+)$/);
        if (m) { var alvo = cards.filter(function (c) { return String(dados(c).cod) === decodeURIComponent(m[1]); })[0]; if (alvo) setTimeout(function () { abrir(alvo, true); }, 300); }
    })();

    // ---------- CARRINHO (pedido pelo site) ----------
    (function () {
        var raiz = $('[data-gds-carrinho]');
        if (!raiz) return;
        var chave = 'gds-carrinho-' + raiz.getAttribute('data-empresa');
        var taxa = parseFloat(raiz.getAttribute('data-taxa')) || 0;
        var minimo = parseFloat(raiz.getAttribute('data-minimo')) || 0;
        var painel = $('.gds-carrinho', raiz), fundo = $('.gds-carrinho-fundo', raiz), barra = $('[data-gds-carrinho-abrir]', raiz);
        var form = $('[data-gds-carrinho-form]', raiz);
        var itens = [];
        var ler = function () { try { itens = JSON.parse(localStorage.getItem(chave) || '[]') || []; } catch (e) { itens = []; } };
        var gravar = function () { try { localStorage.setItem(chave, JSON.stringify(itens)); } catch (e) { } };
        if (raiz.getAttribute('data-limpar') === '1') { itens = []; gravar(); } else { ler(); }
        if (form) {
            // Volta de um erro: o servidor devolve os itens que estavam no formulário
            var doForm = $('[data-gds-itens]', form).value;
            if (doForm) { try { var lista = JSON.parse(doForm); if (lista && lista.length) { itens = lista; gravar(); } } catch (e) { } }
        }
        var subtotal = function () { return itens.reduce(function (t, i) { return t + i.preco * i.qtd; }, 0); };
        var qtdTotal = function () { return itens.reduce(function (t, i) { return t + i.qtd; }, 0); };
        var cupomPct = 0;
        var ehEntrega = function () { var r = form && $('input[name=pd_tipo_entrega]:checked', form); return r ? r.value === 'entrega' : false; };

        function abrir() { painel.hidden = false; fundo.hidden = false; document.documentElement.classList.add('gds-sem-rolagem'); var f = $('button, input', painel); if (f) setTimeout(function () { f.focus(); }, 50); }
        function fechar() { painel.hidden = true; fundo.hidden = true; document.documentElement.classList.remove('gds-sem-rolagem'); }

        function render() {
            var sub = subtotal(), qtd = qtdTotal();
            barra.hidden = qtd === 0 || !painel.hidden;
            $$('[data-gds-qtd]', raiz).forEach(function (el) { el.textContent = qtd; });
            $$('[data-gds-subtotal]', raiz).forEach(function (el) { el.textContent = dinheiro(sub); });
            if (!form) return;
            var lista = $('[data-gds-lista]', form);
            lista.innerHTML = '';
            itens.forEach(function (i, idx) {
                var li = document.createElement('li');
                li.innerHTML = '<span class="gds-ci-nome"></span><span class="gds-ci-qtd"><button type="button" aria-label="Menos">−</button><b></b><button type="button" aria-label="Mais">+</button></span><span class="gds-ci-preco"></span>';
                $('.gds-ci-nome', li).textContent = i.nome;
                $('b', li).textContent = i.qtd;
                $('.gds-ci-preco', li).textContent = dinheiro(i.preco * i.qtd);
                var bts = $$('button', li);
                bts[0].addEventListener('click', function () { i.qtd--; if (i.qtd <= 0) itens.splice(idx, 1); gravar(); render(); });
                bts[1].addEventListener('click', function () { if (i.qtd < 99) i.qtd++; gravar(); render(); });
                lista.appendChild(li);
            });
            $('[data-gds-vazio]', form).hidden = itens.length > 0;
            $('[data-gds-dados]', form).hidden = itens.length === 0;
            var selB = $('[data-gds-bairro] select', form), optB = selB && selB.options[selB.selectedIndex];
            var t = ehEntrega() ? (selB ? (optB && optB.value ? parseFloat(optB.getAttribute('data-taxa')) || 0 : 0) : taxa) : 0;
            var caixaB = $('[data-gds-bairro]', form);
            if (caixaB) { caixaB.hidden = !ehEntrega(); selB.required = ehEntrega(); }
            var linhaTaxa = $('[data-gds-linha-taxa]', form);
            linhaTaxa.hidden = !ehEntrega();
            $('[data-gds-taxa]', form).textContent = t > 0 ? dinheiro(t) : 'Grátis';
            var desc = Math.round(sub * cupomPct) / 100, linhaD = $('[data-gds-linha-desc]', form);
            if (linhaD) { linhaD.hidden = !desc; $('[data-gds-desc]', form).textContent = '−' + dinheiro(desc); }
            $('[data-gds-total]', form).textContent = dinheiro(sub - desc + t);
            // "Peça também" não sugere o que já está no pedido
            var sug = $('[data-gds-sugestoes]', form);
            if (sug) {
                var visiveis = 0;
                $$('[data-gds-add]', sug).forEach(function (b) { var tem = itens.some(function (i) { return String(i.id) === b.getAttribute('data-gds-add'); }); b.hidden = tem; if (!tem) visiveis++; });
                sug.hidden = visiveis === 0;
            }
            var end = $('[data-gds-endereco]', form);
            if (end) {
                end.hidden = !ehEntrega();
                var semN = $('[data-gds-sem-numero]', end), semNum = semN && semN.checked;
                ['#pd_cep', '#pd_rua', '#pd_bairro_txt'].forEach(function (q) { var x = $(q, end); if (x) x.required = ehEntrega(); });
                var num = $('#pd_numero', end); if (num) { num.required = ehEntrega() && !semNum; num.disabled = semNum; if (semNum) num.value = ''; }
                var ref = $('#pd_referencia', end); if (ref) ref.required = ehEntrega() && semNum;
                var unico = $('#pd_endereco', end); if (unico) unico.required = ehEntrega();
            }
            // Frete grátis a partir de um valor: avisa quanto falta (como nos apps)
            var fg = parseFloat(raiz.getAttribute('data-frete-gratis')) || 0, avisoFg = $('[data-gds-frete-aviso]', form);
            if (fg > 0 && ehEntrega() && sub >= fg) { t = 0; $('[data-gds-taxa]', form).textContent = 'Grátis 🎉'; $('[data-gds-total]', form).textContent = dinheiro(sub - desc); }
            if (avisoFg) { avisoFg.hidden = !ehEntrega() || !sub; avisoFg.textContent = sub >= fg ? '🎉 Você ganhou frete grátis!' : '🛵 Faltam ' + dinheiro(fg - sub) + ' para o frete grátis'; }
            var abaixo = minimo > 0 && sub < minimo;
            var aviso = $('[data-gds-minimo-aviso]', form);
            if (aviso) aviso.hidden = !abaixo;
            $('[data-gds-finalizar]', form).disabled = itens.length === 0 || abaixo;
            $('[data-gds-itens]', form).value = JSON.stringify(itens.map(function (i) { return { id: i.id, nome: i.nome, qtd: i.qtd, preco: i.preco, opcoes: i.opcoes || [] }; }));
        }

        // Endereço: CEP preenche rua, bairro e cidade (ViaCEP); "sem número" exige referência;
        // dados lembrados no aparelho para o próximo pedido
        if (form && $('[data-gds-endereco]', form)) {
            var fe = $('[data-gds-endereco]', form), cep = $('[data-gds-cep]', fe), msgCep = $('[data-gds-cep-msg]', fe);
            var chaveEnd = 'gds-endereco-' + raiz.getAttribute('data-empresa');
            var campos = ['pd_cep', 'pd_cidade', 'pd_rua', 'pd_numero', 'pd_complemento', 'pd_bairro', 'pd_bairro_txt', 'pd_referencia', 'pd_nome', 'pd_telefone'];
            var normal = function (t) { return String(t || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]/g, ''); };
            var escolherBairro = function (nome) {
                var sel = $('#pd_bairro', fe); if (!sel) { var tx = $('#pd_bairro_txt', fe); if (tx && nome) tx.value = nome; return true; }
                var alvo = normal(nome), achou = false;
                Array.prototype.forEach.call(sel.options, function (o) { if (!achou && o.value && normal(o.value) === alvo) { sel.value = o.value; achou = true; } });
                return achou;
            };
            var buscarCep = function () {
                var d = cep.value.replace(/\D/g, '');
                if (d.length === 8) cep.value = d.slice(0, 5) + '-' + d.slice(5);
                if (d.length !== 8) return;
                msgCep.textContent = 'Buscando o endereço…'; msgCep.className = 'gds-cep-msg';
                fetch('https://viacep.com.br/ws/' + d + '/json/').then(function (r) { return r.json(); }).then(function (r) {
                    if (r.erro) { msgCep.textContent = 'CEP não encontrado. Confira ou preencha a rua e o bairro.'; msgCep.className = 'gds-cep-msg erro'; return; }
                    if (r.logradouro) $('#pd_rua', fe).value = r.logradouro;
                    $('#pd_cidade', fe).value = r.localidade + (r.uf ? '/' + r.uf : '');
                    var ok = escolherBairro(r.bairro);
                    msgCep.textContent = ok || !$('#pd_bairro', fe) ? '✓ ' + (r.logradouro ? r.logradouro + ', ' : '') + (r.bairro ? r.bairro + ' — ' : '') + r.localidade : '⚠ O bairro ' + r.bairro + ' não está na lista de entrega. Escolha o mais próximo ou chame no WhatsApp.';
                    msgCep.className = 'gds-cep-msg ' + (ok || !$('#pd_bairro', fe) ? 'ok' : 'erro');
                    render();
                    var n = $('#pd_numero', fe); if (n && !n.value && !n.disabled) n.focus();
                }).catch(function () { msgCep.textContent = 'Não deu para buscar o CEP agora. Preencha a rua e o bairro.'; });
            };
            cep.addEventListener('input', function () { if (cep.value.replace(/\D/g, '').length === 8) buscarCep(); });
            cep.addEventListener('blur', buscarCep);
            var sn = $('[data-gds-sem-numero]', fe);
            if (sn) sn.addEventListener('change', function () { var r = $('[data-gds-ref-rotulo]', fe); if (r) r.textContent = sn.checked ? '(obrigatório sem número)' : '(ajuda o entregador)'; render(); if (sn.checked) $('#pd_referencia', fe).focus(); });
            try {
                var salvo = JSON.parse(localStorage.getItem(chaveEnd) || 'null');
                if (salvo && !$('#pd_rua', fe).value) campos.forEach(function (c) { var x = form.querySelector('[name="' + c + '"]'); if (x && salvo[c] && !x.value) x.value = salvo[c]; });
                if (salvo && salvo.pd_sem_numero && sn) { sn.checked = true; }
            } catch (e) { }
            form.addEventListener('submit', function () {
                var lembrar = $('[data-gds-lembrar]', fe);
                try {
                    if (lembrar && lembrar.checked) { var o = {}; campos.forEach(function (c) { var x = form.querySelector('[name="' + c + '"]'); if (x) o[c] = x.value; }); o.pd_sem_numero = sn && sn.checked; localStorage.setItem(chaveEnd, JSON.stringify(o)); }
                    else localStorage.removeItem(chaveEnd);
                } catch (e) { }
            });
        }
        // Cupom no carrinho: confere no servidor e mostra o desconto (o servidor recalcula ao finalizar)
        if (form && $('[data-gds-pd-aplicar]', form)) {
            var campoC = $('[data-gds-pd-cupom]', form), msgC = $('[data-gds-pd-cupom-msg]', form);
            var aplicarC = function () {
                var cod = campoC.value.trim();
                if (!cod) { cupomPct = 0; msgC.textContent = ''; render(); return; }
                fetch('/site/cupom.php?e=' + raiz.getAttribute('data-empresa') + '&c=' + encodeURIComponent(cod)).then(function (r) { return r.json(); }).then(function (r) {
                    cupomPct = r.ok ? r.desconto : 0; msgC.textContent = r.ok ? '✓ ' + String(r.desconto).replace('.', ',') + '% de desconto aplicado' : r.erro; msgC.className = 'gds-cupom-msg ' + (r.ok ? 'ok' : 'erro'); render();
                }).catch(function () { });
            };
            $('[data-gds-pd-aplicar]', form).addEventListener('click', aplicarC);
            campoC.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); aplicarC(); } });
            if (campoC.value.trim()) aplicarC();
        }
        // "Pedir de novo" (link do acompanhamento): #gds-repetir=[{id,qtd,opcoes,nome,preco}]
        (function () {
            var m = location.hash.match(/^#gds-repetir=(.+)$/);
            if (!m) return;
            try {
                var lista = JSON.parse(decodeURIComponent(m[1])), novos = [];
                lista.forEach(function (x) {
                    var b = document.querySelector('[data-gds-add="' + parseInt(x.id, 10) + '"]');
                    if (!b || b.disabled) return; // saiu do cardápio ou esgotou
                    var semOp = !(x.opcoes && x.opcoes.length);
                    novos.push({ id: parseInt(x.id, 10), nome: semOp ? b.getAttribute('data-nome') : String(x.nome || ''), preco: semOp ? parseFloat(b.getAttribute('data-preco')) || 0 : parseFloat(x.preco) || 0, qtd: Math.max(1, Math.min(99, parseInt(x.qtd, 10) || 1)), opcoes: x.opcoes || [] });
                });
                if (novos.length) { itens = novos; gravar(); }
                history.replaceState(null, '', location.pathname + location.search);
                if (novos.length) setTimeout(function () { render(); abrir(); }, 300);
            } catch (e) { }
        })();

        // Item com opções (adicionais, ponto, meio a meio, tamanho...): janela de escolha com preço ao vivo
        function escolherOpcoes(b, grupos, pronto) {
            var base = parseFloat(b.getAttribute('data-preco')) || 0, nome = b.getAttribute('data-nome');
            var fundo = document.createElement('div'); fundo.className = 'gds-op-fundo';
            var box = document.createElement('div'); box.className = 'gds-op-janela'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true');
            var h = '<h3></h3>';
            grupos.forEach(function (g, gi) {
                var tipo = g.max > 1 ? 'checkbox' : 'radio';
                h += '<fieldset class="gds-op-grupo" data-g="' + gi + '"><legend><span></span><small>' + (g.min > 0 ? 'obrigatório' : 'opcional') + (g.max > 1 ? ' · até ' + g.max : '') + (g.regra === 'maior' ? ' · vale o maior preço' : '') + '</small></legend>';
                g.itens.forEach(function (it, ii) {
                    var preco = it.preco > 0 ? (g.regra === 'maior' ? dinheiro(it.preco) : '+ ' + dinheiro(it.preco)) : '';
                    h += '<label class="gds-op-item"><input type="' + tipo + '" name="og' + gi + '" value="' + ii + '"><span class="gds-op-nome"></span><span class="gds-op-preco">' + preco + '</span></label>';
                });
                h += '</fieldset>';
            });
            h += '<p class="gds-op-erro" hidden></p><div class="gds-op-acoes"><button type="button" class="gds-btn-2" data-x>Cancelar</button><button type="button" class="gds-btn" data-ok>Adicionar · <span data-total></span></button></div>';
            box.innerHTML = h;
            box.querySelector('h3').textContent = nome;
            grupos.forEach(function (g, gi) {
                var fs = box.querySelector('[data-g="' + gi + '"]'); fs.querySelector('legend span').textContent = g.nome;
                fs.querySelectorAll('.gds-op-nome').forEach(function (el, ii) { el.textContent = g.itens[ii].nome; });
            });
            var escolhas = function () { var r = []; box.querySelectorAll('input:checked').forEach(function (c) { r.push([parseInt(c.closest('[data-g]').dataset.g, 10), parseInt(c.value, 10)]); }); return r; };
            var calcular = function () {
                var p = base, extra = 0, partes = [];
                grupos.forEach(function (g, gi) {
                    var sel = escolhas().filter(function (e) { return e[0] === gi; }).map(function (e) { return g.itens[e[1]]; });
                    if (!sel.length) return;
                    if (g.regra === 'maior') { p = Math.max(p, Math.max.apply(null, sel.map(function (x) { return x.preco; }))); partes.push(sel.map(function (x) { return x.nome; }).join(' / ')); }
                    else { extra += sel.reduce(function (t, x) { return t + x.preco; }, 0); partes.push(sel.map(function (x) { return x.nome; }).join(', ')); }
                });
                return { preco: Math.round((p + extra) * 100) / 100, nome: nome + (partes.length ? ' (' + partes.join('; ') + ')' : '') };
            };
            var atualizar = function () {
                grupos.forEach(function (g, gi) { // trava o máximo de cada grupo
                    var cs = box.querySelectorAll('[data-g="' + gi + '"] input'), n = 0; cs.forEach(function (c) { if (c.checked) n++; });
                    if (g.max > 1) cs.forEach(function (c) { c.disabled = !c.checked && n >= g.max; });
                });
                box.querySelector('[data-total]').textContent = dinheiro(calcular().preco);
            };
            var fechar = function () { fundo.remove(); box.remove(); };
            box.addEventListener('change', atualizar);
            box.querySelector('[data-x]').onclick = fechar; fundo.onclick = fechar;
            box.querySelector('[data-ok]').onclick = function () {
                var falta = grupos.filter(function (g, gi) { return g.min > 0 && !escolhas().some(function (e) { return e[0] === gi; }); })[0];
                var erro = box.querySelector('.gds-op-erro');
                if (falta) { erro.hidden = false; erro.textContent = 'Escolha: ' + falta.nome; return; }
                var c = calcular(); fechar(); pronto(escolhas(), c.preco, c.nome);
            };
            raiz.appendChild(fundo); raiz.appendChild(box); atualizar();
            var primeiro = box.querySelector('input'); if (primeiro) primeiro.focus();
        }
        function adicionar(b, id, opcoes, preco, nome) {
            var chave = id + '|' + JSON.stringify(opcoes || []);
            var it = itens.filter(function (i) { return (i.id + '|' + JSON.stringify(i.opcoes || [])) === chave; })[0];
            if (it) it.qtd = Math.min(99, it.qtd + 1);
            else itens.push({ id: id, nome: nome, preco: preco, qtd: 1, opcoes: opcoes || [] });
            gravar(); render();
            b.classList.add('gds-adicionado');
            var txt = b.innerHTML; b.innerHTML = '✓ Adicionado';
            setTimeout(function () { b.innerHTML = txt; b.classList.remove('gds-adicionado'); }, 1100);
            barra.classList.remove('gds-pulo'); void barra.offsetWidth; barra.classList.add('gds-pulo');
        }
        document.addEventListener('click', function (ev) {
            var b = ev.target.closest('[data-gds-add]');
            if (!b) return;
            ev.preventDefault();
            var id = parseInt(b.getAttribute('data-gds-add'), 10);
            var grupos = []; try { grupos = JSON.parse(b.getAttribute('data-gds-opcoes') || '[]'); } catch (e) { grupos = []; }
            if (grupos.length) { escolherOpcoes(b, grupos, function (op, preco, nome) { adicionar(b, id, op, preco, nome); }); return; }
            adicionar(b, id, [], parseFloat(b.getAttribute('data-preco')) || 0, b.getAttribute('data-nome'));
        });
        barra.addEventListener('click', function () { abrir(); render(); });
        $$('[data-gds-carrinho-fechar]', raiz).forEach(function (el) { el.addEventListener('click', function () { fechar(); render(); }); });
        document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && !painel.hidden) { fechar(); render(); } });
        if (form) {
            form.addEventListener('change', render);
            var pag = $('[data-gds-pagamento]', form), troco = $('[data-gds-troco]', form);
            var atTroco = function () { troco.hidden = !/dinheiro/i.test(pag.value); };
            if (pag && troco) { pag.addEventListener('change', atTroco); atTroco(); }
            form.addEventListener('submit', function (ev) {
                render();
                var falta = $$('input[required]', form).filter(function (i) { return !i.closest('[hidden]') && !i.value.trim(); })[0];
                if (falta) { ev.preventDefault(); falta.focus(); falta.classList.add('gds-invalido'); return; }
                var bt = $('[data-gds-finalizar]', form);
                bt.disabled = true; bt.textContent = 'Enviando…';
            });
        }
        // Link "#gds-carrinho" (ex.: botão principal da IA) abre o painel
        document.addEventListener('click', function (ev) {
            var a = ev.target.closest('a[href="#gds-carrinho"]');
            if (a) { ev.preventDefault(); abrir(); render(); }
        });
        render();
        if (raiz.getAttribute('data-aberto') === '1') { abrir(); render(); }
    })();
})();

// ---------- PIX: confirma sozinho quando o pagamento cai ----------
(function () {
    var caixas = document.querySelectorAll('[data-gds-pix]');
    Array.prototype.forEach.call(caixas, function (box) {
        var token = box.getAttribute('data-gds-pix'), tentativas = 0;
        var status = box.querySelector('[data-gds-pix-status]');
        var conferir = function () {
            if (++tentativas > 360) return; // para depois de ~30 min
            fetch('/site/pagamento.php?t=' + encodeURIComponent(token), { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (d) {
                if (d && d.status === 'pago') {
                    box.classList.add('gds-pix-pago');
                    if (status) status.textContent = '✅ Pagamento confirmado! Obrigado.';
                    var img = box.querySelector('img'); if (img) img.style.opacity = '.25';
                    return;
                }
                if (d && d.status === 'cancelado') { if (status) status.textContent = 'Este Pix expirou. Fale com a empresa para gerar outro.'; return; }
                setTimeout(conferir, 5000);
            }).catch(function () { setTimeout(conferir, 8000); });
        };
        setTimeout(conferir, 4000);
    });
})();
