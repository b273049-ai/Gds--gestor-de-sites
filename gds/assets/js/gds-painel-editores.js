// ============================================================
// EDITORES COM BOTÕES DO PAINEL — a empresa monta tudo tocando em botões,
// sem decorar nenhuma "fórmula" de texto. Cada editor grava num <input hidden>.
//   [data-editor-opcoes]  opções do item (ponto, adicionais, retirar, molhos, tamanho, meio a meio)
//   [data-editor-bairros] taxa de entrega por bairro
//   [data-editor-lista]   lista simples (perguntas do orçamento)
//   [data-editor-chips]   escolher em botões (formas de pagamento, etiquetas, características)
//   [data-preset]         botões que preenchem um campo (tempo de entrega)
// ============================================================
(function () {
    'use strict';
    function el(tag, cls, txt) { var e = document.createElement(tag); if (cls) e.className = cls; if (txt !== undefined) e.textContent = txt; return e; }
    function btn(txt, cls, fn, titulo) { var b = el('button', 'btn btn-sm ' + (cls || 'btn-ghost'), txt); b.type = 'button'; if (titulo) b.title = titulo; b.addEventListener('click', fn); return b; }
    function num(s) { s = String(s == null ? '' : s).trim(); if (s.indexOf(',') >= 0) s = s.replace(/\./g, '').replace(',', '.'); var n = parseFloat(s.replace(/[^0-9.]/g, '')); return isNaN(n) ? 0 : Math.round(n * 100) / 100; }
    function din(v) { return (Number(v) || 0).toFixed(2).replace('.', ','); }
    function ler(inp, padrao) { try { var v = JSON.parse(inp.value || ''); return v == null ? padrao : v; } catch (e) { return padrao; } }

    // ---------- OPÇÕES DO ITEM ----------
    var MODELOS = [
        ['🥩 Ponto da carne', { nome: 'Ponto da carne', min: 1, max: 1, regra: 'soma', itens: [{ nome: 'Mal passado', preco: 0 }, { nome: 'Ao ponto', preco: 0 }, { nome: 'Bem passado', preco: 0 }] }],
        ['➕ Adicionais', { nome: 'Adicionais', min: 0, max: 5, regra: 'soma', qtd: true, itens: [{ nome: 'Bacon', preco: 4 }, { nome: 'Cheddar', preco: 3.5 }, { nome: 'Ovo', preco: 2 }] }],
        ['🚫 Retirar ingredientes', { nome: 'Retirar', min: 0, max: 4, regra: 'soma', itens: [{ nome: 'Cebola', preco: 0 }, { nome: 'Tomate', preco: 0 }, { nome: 'Alface', preco: 0 }, { nome: 'Picles', preco: 0 }] }],
        ['🥫 Molhos', { nome: 'Molhos', min: 0, max: 2, regra: 'soma', itens: [{ nome: 'Barbecue', preco: 0 }, { nome: 'Maionese verde', preco: 0 }, { nome: 'Ketchup', preco: 0 }] }],
        ['📏 Tamanho', { nome: 'Tamanho', min: 1, max: 1, regra: 'soma', itens: [{ nome: 'Pequeno', preco: 0 }, { nome: 'Médio', preco: 5 }, { nome: 'Grande', preco: 10 }] }],
        ['🍕 Sabores (meio a meio)', { nome: 'Sabores', min: 1, max: 2, regra: 'maior', itens: [{ nome: 'Calabresa', preco: 45 }, { nome: 'Marguerita', preco: 42 }, { nome: 'Portuguesa', preco: 48 }] }],
        ['🥤 Bebida do combo', { nome: 'Escolha a bebida', min: 1, max: 1, regra: 'soma', itens: [{ nome: 'Coca-Cola lata', preco: 0 }, { nome: 'Guaraná lata', preco: 0 }, { nome: 'Suco natural', preco: 3 }] }],
        ['🍟 Acompanhamento', { nome: 'Acompanhamento', min: 0, max: 1, regra: 'soma', itens: [{ nome: 'Batata pequena', preco: 8 }, { nome: 'Onion rings', preco: 10 }] }],
        ['＋ Grupo em branco', { nome: '', min: 0, max: 1, regra: 'soma', itens: [{ nome: '', preco: 0 }] }]
    ];
    function editorOpcoes(inp) {
        var grupos = ler(inp, []); if (!Array.isArray(grupos)) grupos = [];
        var raiz = el('div', 'ed-opcoes'); inp.after(raiz);
        var lista = el('div', 'ed-grupos'), barra = el('div', 'ed-modelos');
        barra.append(el('span', 'ed-rotulo', 'Adicionar:'));
        MODELOS.forEach(function (m) { barra.append(btn(m[0], 'btn-ghost', function () { grupos.push(JSON.parse(JSON.stringify(m[1]))); salvar(); desenhar(); var u = lista.lastElementChild; if (u) { u.scrollIntoView({ behavior: 'smooth', block: 'center' }); var n = u.querySelector('input'); if (n && !n.value) n.focus(); } })); });
        raiz.append(lista, barra);
        function salvar() { inp.value = JSON.stringify(grupos); }
        function passo(rotulo, valor, minimo, maximo, mudar) {
            var w = el('span', 'ed-passo'); w.append(el('small', '', rotulo));
            var menos = btn('−', 'btn-ghost', function () { if (valor > minimo) mudar(valor - 1); }, 'Menos');
            var mais = btn('+', 'btn-ghost', function () { if (valor < maximo) mudar(valor + 1); }, 'Mais');
            menos.disabled = valor <= minimo; mais.disabled = valor >= maximo;
            w.append(menos, el('b', '', String(valor)), mais); return w;
        }
        function chave(rotulo, ligado, mudar, dica) {
            var b = btn((ligado ? '✓ ' : '') + rotulo, ligado ? 'btn-ligado' : 'btn-ghost', function () { mudar(!ligado); }, dica);
            b.setAttribute('aria-pressed', ligado ? 'true' : 'false'); return b;
        }
        function resumo(g) {
            var q = g.itens.filter(function (i) { return i.nome; }).length;
            var r = g.min > 0 ? (g.min === g.max ? 'escolhe ' + g.min : 'escolhe de ' + g.min + ' a ' + g.max) : (g.max > 1 ? 'opcional, até ' + g.max : 'opcional, 1');
            return 'O cliente vê: ' + (g.nome || 'Sem nome') + ' — ' + (g.min > 0 ? 'OBRIGATÓRIO, ' : '') + r + (g.qtd ? ' (com + e −)' : '') + (g.regra === 'maior' ? ' · cobra o sabor mais caro' : '') + ' · ' + q + ' opção(ões)';
        }
        function desenhar() {
            lista.innerHTML = '';
            if (!grupos.length) lista.append(el('p', 'dica', 'Sem opções: o cliente só escolhe a quantidade e pode escrever uma observação. Toque num modelo abaixo para adicionar.'));
            grupos.forEach(function (g, gi) {
                g.itens = g.itens || []; g.min = g.min | 0; g.max = Math.max(1, g.max | 0);
                var card = el('div', 'ed-grupo');
                var topo = el('div', 'ed-grupo-topo');
                var nome = el('input'); nome.type = 'text'; nome.maxLength = 60; nome.placeholder = 'Nome do grupo (ex.: Adicionais)'; nome.value = g.nome || '';
                nome.addEventListener('input', function () { g.nome = nome.value; salvar(); card.querySelector('.ed-resumo').textContent = resumo(g); });
                topo.append(nome,
                    btn('↑', 'btn-ghost', function () { if (gi > 0) { grupos.splice(gi - 1, 0, grupos.splice(gi, 1)[0]); salvar(); desenhar(); } }, 'Subir'),
                    btn('↓', 'btn-ghost', function () { if (gi < grupos.length - 1) { grupos.splice(gi + 1, 0, grupos.splice(gi, 1)[0]); salvar(); desenhar(); } }, 'Descer'),
                    btn('🗑', 'btn-perigo', function () { if (confirm('Remover o grupo "' + (g.nome || 'sem nome') + '"?')) { grupos.splice(gi, 1); salvar(); desenhar(); } }, 'Remover grupo'));
                var regras = el('div', 'ed-regras');
                var maxPossivel = g.qtd ? 20 : Math.max(1, g.itens.length);
                regras.append(
                    chave('Obrigatório', g.min > 0, function (v) { g.min = v ? 1 : 0; if (g.min > g.max) g.max = g.min; salvar(); desenhar(); }, 'O cliente precisa escolher antes de adicionar'),
                    chave('Cliente escolhe a quantidade (2x)', !!g.qtd, function (v) { g.qtd = v; if (v) g.regra = 'soma'; else g.max = Math.min(g.max, Math.max(1, g.itens.length)); salvar(); desenhar(); }, 'Mostra + e − em cada opção (ex.: 2x bacon)'),
                    chave('Meio a meio (cobra o mais caro)', g.regra === 'maior', function (v) { g.regra = v ? 'maior' : 'soma'; if (v) { g.qtd = false; if (g.max < 2) g.max = 2; } salvar(); desenhar(); }, 'Para pizza de 2 sabores: o preço é o do sabor mais caro'),
                    passo('Mínimo', g.min, 0, g.max, function (v) { g.min = v; salvar(); desenhar(); }),
                    passo('Máximo', Math.min(g.max, maxPossivel), Math.max(1, g.min), maxPossivel, function (v) { g.max = v; salvar(); desenhar(); }));
                var itens = el('div', 'ed-itens');
                g.itens.forEach(function (it, ii) {
                    var l = el('div', 'ed-item');
                    var n = el('input'); n.type = 'text'; n.maxLength = 60; n.placeholder = 'Opção (ex.: Bacon)'; n.value = it.nome || '';
                    var p = el('input'); p.type = 'text'; p.inputMode = 'decimal'; p.placeholder = g.regra === 'maior' ? 'Preço' : '+ R$ 0,00'; p.value = it.preco ? din(it.preco) : '';
                    n.addEventListener('input', function () { it.nome = n.value; salvar(); });
                    p.addEventListener('input', function () { it.preco = num(p.value); salvar(); });
                    p.addEventListener('blur', function () { p.value = it.preco ? din(it.preco) : ''; });
                    var esg = btn(it.off ? 'Esgotou' : 'Tem', it.off ? 'btn-perigo' : 'btn-ghost', function () { it.off = !it.off; salvar(); desenhar(); }, it.off ? 'Esgotado — toque quando voltar a ter' : 'Disponível — toque se acabar');
                    esg.setAttribute('aria-pressed', it.off ? 'true' : 'false');
                    l.append(n, el('span', 'ed-rs', g.regra === 'maior' ? 'R$' : '+ R$'), p, esg, btn('✕', 'btn-ghost', function () { g.itens.splice(ii, 1); if (!g.qtd && g.max > Math.max(1, g.itens.length)) g.max = Math.max(1, g.itens.length); salvar(); desenhar(); }, 'Remover opção'));
                    if (it.off) l.classList.add('ed-item-off');
                    itens.append(l);
                });
                itens.append(btn('+ Adicionar opção', 'btn-ghost', function () { g.itens.push({ nome: '', preco: 0 }); salvar(); desenhar(); var ls = card.querySelectorAll('.ed-item input[type=text]'); var alvo = lista.children[gi].querySelectorAll('.ed-item'); var u = alvo[alvo.length - 1]; if (u) u.querySelector('input').focus(); }));
                card.append(topo, regras, itens, el('p', 'ed-resumo', resumo(g)));
                lista.append(card);
            });
        }
        salvar(); desenhar();
    }

    // ---------- BAIRROS E TAXAS ----------
    function editorBairros(inp) {
        var l = ler(inp, []); if (!Array.isArray(l)) l = [];
        var raiz = el('div', 'ed-bairros'); inp.after(raiz);
        function salvar() { inp.value = JSON.stringify(l.filter(function (b) { return (b.bairro || '').trim(); })); }
        function desenhar() {
            raiz.innerHTML = '';
            l.forEach(function (b, i) {
                var r = el('div', 'ed-item');
                var n = el('input'); n.type = 'text'; n.placeholder = 'Bairro'; n.maxLength = 80; n.value = b.bairro || '';
                var t = el('input'); t.type = 'text'; t.inputMode = 'decimal'; t.placeholder = '0,00 = grátis'; t.value = b.taxa ? din(b.taxa) : '';
                n.addEventListener('input', function () { b.bairro = n.value; salvar(); });
                t.addEventListener('input', function () { b.taxa = num(t.value); salvar(); });
                t.addEventListener('blur', function () { t.value = b.taxa ? din(b.taxa) : ''; });
                r.append(n, el('span', 'ed-rs', 'R$'), t, btn('✕', 'btn-ghost', function () { l.splice(i, 1); salvar(); desenhar(); }, 'Remover bairro'));
                raiz.append(r);
            });
            var acoes = el('div', 'ed-modelos');
            acoes.append(btn('+ Adicionar bairro', 'btn-ghost', function () { l.push({ bairro: '', taxa: l.length ? l[l.length - 1].taxa : 0 }); salvar(); desenhar(); var ins = raiz.querySelectorAll('.ed-item input'); if (ins.length) ins[ins.length - 2].focus(); }));
            if (l.length) acoes.append(btn('Mesma taxa para todos', 'btn-ghost', function () { var v = prompt('Taxa para todos os bairros (R$):', l[0] ? din(l[0].taxa) : '5,00'); if (v === null) return; var n = num(v); l.forEach(function (b) { b.taxa = n; }); salvar(); desenhar(); }));
            if (!l.length) acoes.append(el('span', 'dica', 'Sem bairros cadastrados: vale a taxa única acima para qualquer endereço.'));
            raiz.append(acoes);
        }
        salvar(); desenhar();
    }

    // ---------- LISTA SIMPLES (perguntas) ----------
    function editorLista(inp) {
        var l = ler(inp, []); if (!Array.isArray(l)) l = [];
        var sug = (inp.getAttribute('data-sugestoes') || '').split('|').filter(Boolean);
        var raiz = el('div', 'ed-bairros'); inp.after(raiz);
        function salvar() { inp.value = JSON.stringify(l.map(function (x) { return String(x || '').trim(); }).filter(Boolean)); }
        function desenhar() {
            raiz.innerHTML = '';
            l.forEach(function (x, i) {
                var r = el('div', 'ed-item ed-item-1');
                var n = el('input'); n.type = 'text'; n.maxLength = 160; n.placeholder = 'Pergunta'; n.value = x || '';
                n.addEventListener('input', function () { l[i] = n.value; salvar(); });
                r.append(n, btn('✕', 'btn-ghost', function () { l.splice(i, 1); salvar(); desenhar(); }, 'Remover'));
                raiz.append(r);
            });
            var acoes = el('div', 'ed-modelos');
            if (l.length < 8) {
                acoes.append(btn('+ Escrever pergunta', 'btn-ghost', function () { l.push(''); salvar(); desenhar(); var ins = raiz.querySelectorAll('.ed-item input'); if (ins.length) ins[ins.length - 1].focus(); }));
                sug.filter(function (s) { return l.indexOf(s) < 0; }).forEach(function (s) { acoes.append(btn('+ ' + s, 'btn-ghost', function () { l.push(s); salvar(); desenhar(); })); });
            }
            raiz.append(acoes);
        }
        salvar(); desenhar();
    }

    // ---------- BOTÕES DE ESCOLHA (formas de pagamento, etiquetas, características) ----------
    function editorChips(inp) {
        var opcoes = (inp.getAttribute('data-opcoes') || '').split('|').filter(Boolean);
        var sel = String(inp.value || '').split(',').map(function (x) { return x.trim(); }).filter(Boolean);
        sel.forEach(function (s) { if (opcoes.indexOf(s) < 0) opcoes.push(s); });
        var raiz = el('div', 'ed-chips'); inp.after(raiz);
        function salvar() { inp.value = sel.join(', '); }
        function desenhar() {
            raiz.innerHTML = '';
            opcoes.forEach(function (o) {
                var on = sel.indexOf(o) >= 0;
                var b = btn((on ? '✓ ' : '') + o, on ? 'btn-ligado' : 'btn-ghost', function () { if (on) sel.splice(sel.indexOf(o), 1); else sel.push(o); salvar(); desenhar(); });
                b.setAttribute('aria-pressed', on ? 'true' : 'false'); raiz.append(b);
            });
            raiz.append(btn('+ Outra', 'btn-ghost', function () {
                var v = prompt('Escreva a nova opção:'); if (!v) return; v = v.replace(/,/g, ' ').trim().slice(0, 40); if (!v) return;
                if (opcoes.indexOf(v) < 0) opcoes.push(v); if (sel.indexOf(v) < 0) sel.push(v); salvar(); desenhar();
            }));
        }
        salvar(); desenhar();
    }

    // ---------- BOTÕES QUE PREENCHEM UM CAMPO ----------
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-preset]'); if (!b) return;
        var box = b.closest('[data-preset-alvo]'); if (!box) return;
        var alvo = box.parentElement.querySelector('[name="' + box.getAttribute('data-preset-alvo') + '"]');
        if (alvo) { alvo.value = b.getAttribute('data-preset'); alvo.dispatchEvent(new Event('input', { bubbles: true })); }
        box.querySelectorAll('[data-preset]').forEach(function (x) { x.classList.toggle('btn-ligado', x === b); x.classList.toggle('btn-ghost', x !== b); });
    });

    // ---------- ACESSIBILIDADE AUTOMÁTICA (Web Interface Guidelines / axe) ----------
    // O painel inteiro segue o padrão <label>Texto</label><input>: aqui cada rótulo é ligado ao seu campo,
    // botão só com ícone ganha nome, cabeçalho de tabela vazio vira "Ações" e há o link "Pular para o conteúdo".
    var NOME_ICONE = { lapis: 'Editar', lixeira: 'Excluir', chat: 'Abrir WhatsApp', olho: 'Ver', mais: 'Adicionar', download: 'Baixar',
        dinheiro: 'Fechar comanda', calendario: 'Agenda', usuarios: 'Clientes', casa: 'Imóveis', estrela: 'Avaliações', engrenagem: 'Configurações' };
    var seq = 0;
    function semNome(c) {
        if (c.getAttribute('aria-label') || c.getAttribute('aria-labelledby') || c.title) return false;
        if (c.id && document.querySelector('label[for="' + c.id + '"]')) return false;
        return !c.closest('label');
    }
    function acessibilidade() {
        var controles = 'input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea';
        document.querySelectorAll('label:not([for])').forEach(function (lb) {
            if (lb.querySelector(controles)) return;
            var alvo = lb.nextElementSibling;
            if (alvo && !alvo.matches(controles)) alvo = alvo.matches('div,span') ? alvo.querySelector(controles) : null;
            if (!alvo || !alvo.matches || !alvo.matches(controles) || !semNome(alvo)) return;
            if (!alvo.id) alvo.id = 'gdsc' + (++seq);
            lb.htmlFor = alvo.id;
        });
        document.querySelectorAll(controles).forEach(function (c) {
            if (!semNome(c)) return;
            var txt = c.placeholder || (c.closest('[data-rotulo]') && c.closest('[data-rotulo]').getAttribute('data-rotulo')) || '';
            if (!txt && c.type === 'date') txt = 'Data';
            if (!txt && c.type === 'search') txt = 'Buscar';
            if (!txt && c.name) txt = c.name.replace(/[_\[\]]+/g, ' ').trim();
            if (txt) c.setAttribute('aria-label', txt.replace(/[…:.]+$/, ''));
        });
        document.querySelectorAll('a, button').forEach(function (b) {
            if (b.getAttribute('aria-label') || b.textContent.trim()) return;
            var ic = b.querySelector('[data-icone]');
            var nome = b.title || (ic && NOME_ICONE[ic.getAttribute('data-icone')]) || (/excluir|apagar/.test(b.getAttribute('href') || '') ? 'Excluir' : /editar/.test(b.getAttribute('href') || '') ? 'Editar' : '');
            if (nome) b.setAttribute('aria-label', nome);
        });
        document.querySelectorAll('th').forEach(function (th) { if (!th.textContent.trim()) th.innerHTML = '<span class="sr-only">Ações</span>'; });
        document.querySelectorAll('img:not([alt])').forEach(function (i) { i.alt = ''; });
        // quadros com rolagem lateral (funil, gestor) alcançáveis pelo teclado
        document.querySelectorAll('.crm-quadro, .gst-quadro, #quadro').forEach(function (q) { if (!q.hasAttribute('tabindex')) { q.tabIndex = 0; q.setAttribute('role', 'region'); q.setAttribute('aria-label', 'Quadro (role para os lados)'); } });
        // títulos em ordem para leitores de tela (h1 → h2 → h3), sem mudar o visual
        var nivel = 0;
        document.querySelectorAll('main h1, main h2, main h3, main h4, main h5, main h6').forEach(function (h) {
            var n = +h.tagName[1];
            if (nivel && n > nivel + 1) { h.setAttribute('role', 'heading'); h.setAttribute('aria-level', String(nivel + 1)); n = nivel + 1; }
            nivel = n;
        });
        var main = document.querySelector('main');
        if (main && !document.querySelector('.pular-conteudo')) {
            if (!main.id) main.id = 'conteudo';
            var a = el('a', 'pular-conteudo', 'Pular para o conteúdo'); a.href = '#' + main.id; document.body.prepend(a);
        }
    }

    function iniciar() {
        acessibilidade();
        document.querySelectorAll('[data-editor-opcoes]').forEach(editorOpcoes);
        document.querySelectorAll('[data-editor-bairros]').forEach(editorBairros);
        document.querySelectorAll('[data-editor-lista]').forEach(editorLista);
        document.querySelectorAll('[data-editor-chips]').forEach(editorChips);
        acessibilidade(); // de novo: os editores acabaram de criar campos e botões
        document.querySelectorAll('[data-preset-alvo]').forEach(function (box) {
            var alvo = box.parentElement.querySelector('[name="' + box.getAttribute('data-preset-alvo') + '"]');
            if (alvo) box.querySelectorAll('[data-preset]').forEach(function (x) { var on = x.getAttribute('data-preset') === alvo.value.trim(); x.classList.toggle('btn-ligado', on); x.classList.toggle('btn-ghost', !on); });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();
})();
