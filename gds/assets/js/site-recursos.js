// Recursos do site (includes/recursos_site.php): stories, antes e depois, cartão fidelidade,
// cupom de aniversário, instalar o app e vídeo no topo. Sem bibliotecas.
(function () {
  'use strict';
  var calmo = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ---------- vídeo no topo: "reduzir movimento" = parado na capa ----------
  document.querySelectorAll('video.lp-video').forEach(function (v) {
    if (calmo) { v.removeAttribute('autoplay'); try { v.pause(); } catch (e) {} }
  });

  // ---------- antes e depois ----------
  document.querySelectorAll('.lp-ad-box').forEach(function (box) {
    var r = box.querySelector('input[type=range]');
    if (!r) return;
    var por = function () { box.style.setProperty('--pos', r.value + '%'); };
    r.addEventListener('input', por); por();
  });

  // ---------- stories ----------
  var dadosEl = document.getElementById('lpStoriesDados');
  if (dadosEl) {
    var itens = []; try { itens = JSON.parse(dadosEl.textContent) || []; } catch (e) {}
    var ov = null, idx = 0, timer = null, origem = null;
    var fechar = function () { if (!ov) return; clearTimeout(timer); ov.remove(); ov = null; document.documentElement.style.overflow = ''; if (origem) origem.focus(); };
    var mostrar = function (i) {
      if (i < 0) i = 0;
      if (i >= itens.length) { fechar(); return; }
      idx = i;
      ov.querySelector('img').src = itens[i].img;
      ov.querySelector('img').alt = itens[i].texto || 'Novidade';
      var t = ov.querySelector('.lp-sv-txt'); t.textContent = itens[i].texto || ''; t.hidden = !itens[i].texto;
      ov.querySelectorAll('.lp-sv-barras i').forEach(function (b, k) { b.className = k < i ? 'cheia' : (k === i ? 'atual' : ''); });
      clearTimeout(timer);
      if (!calmo) timer = setTimeout(function () { mostrar(idx + 1); }, 5000);
    };
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-story]');
      if (!b) return;
      origem = b;
      ov = document.createElement('div');
      ov.className = 'lp-sv' + (calmo ? ' calmo' : '');
      ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-modal', 'true'); ov.setAttribute('aria-label', 'Novidades');
      ov.innerHTML = '<div class="lp-sv-barras">' + itens.map(function () { return '<i></i>'; }).join('') + '</div>' +
        '<button type="button" class="lp-sv-x" aria-label="Fechar">×</button><img alt=""><p class="lp-sv-txt"></p>' +
        '<button type="button" class="lp-sv-ant" aria-label="Anterior"></button><button type="button" class="lp-sv-prox" aria-label="Próxima"></button>';
      document.body.appendChild(ov);
      document.documentElement.style.overflow = 'hidden';
      ov.querySelector('.lp-sv-x').onclick = fechar;
      ov.querySelector('.lp-sv-ant').onclick = function () { mostrar(idx - 1); };
      ov.querySelector('.lp-sv-prox').onclick = function () { mostrar(idx + 1); };
      mostrar(parseInt(b.getAttribute('data-story'), 10) || 0);
      ov.querySelector('.lp-sv-x').focus();
    });
    document.addEventListener('keydown', function (e) {
      if (!ov) return;
      if (e.key === 'Escape') fechar();
      if (e.key === 'ArrowRight') mostrar(idx + 1);
      if (e.key === 'ArrowLeft') mostrar(idx - 1);
    });
  }

  // ---------- formulários (fidelidade e aniversário) ----------
  var enviar = function (form, acao, eid, cb) {
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var res = form.querySelector('[role=status]'), bt = form.querySelector('button[type=submit]');
      var fd = new FormData(form); fd.append('acao', acao); fd.append('e', eid);
      bt.disabled = true; res.textContent = 'Um instante…';
      fetch('/site/recursos.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) { res.textContent = j.msg || ''; bt.disabled = false; if (cb) cb(j); })
        .catch(function () { res.textContent = 'Não foi possível agora. Tente de novo.'; bt.disabled = false; });
    });
  };
  document.querySelectorAll('.lp-fid').forEach(function (box) {
    var f = box.querySelector('form'); if (!f) return;
    enviar(f, 'fidelidade', box.getAttribute('data-fid'), function (j) {
      if (!j.ok) return;
      box.querySelectorAll('.lp-selo').forEach(function (s, k) { s.classList.toggle('marcado', k < j.selos); });
    });
  });
  document.querySelectorAll('.lp-aniv').forEach(function (box) {
    var f = box.querySelector('form'); if (!f) return;
    enviar(f, 'aniversario', box.getAttribute('data-aniv'), function (j) { if (j.ok) f.reset(); });
  });

  // ---------- instalar o app ----------
  var bApp = document.getElementById('lpApp');
  if (bApp) {
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('/gds-sw.js', { scope: '/' }).catch(function () {});
    var pedido = null;
    var ios = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.navigator.standalone;
    window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); pedido = e; bApp.hidden = false; });
    if (ios) bApp.hidden = false;
    bApp.addEventListener('click', function () {
      if (pedido) { pedido.prompt(); pedido.userChoice.finally(function () { pedido = null; bApp.hidden = true; }); return; }
      var d = document.getElementById('lpAppIos'); if (d) { d.hidden = false; var b = d.querySelector('button'); if (b) b.focus(); }
    });
    document.addEventListener('click', function (e) { if (e.target.closest('[data-fechar-ios]')) { document.getElementById('lpAppIos').hidden = true; bApp.focus(); } });
  }
})();

// ---------- restaurante: reserva, fila e chamar garçom (includes/restaurante.php) ----------
(function () {
  'use strict';
  var post = function (dados) {
    var fd = new FormData(); Object.keys(dados).forEach(function (k) { fd.append(k, dados[k]); });
    return fetch('/site/restaurante.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  };
  document.querySelectorAll('form[data-rest]').forEach(function (f) {
    var dataEl = f.querySelector('input[type=date]'), dias = (f.getAttribute('data-dias') || '').split(',').filter(Boolean).map(Number);
    if (dataEl && dias.length) dataEl.addEventListener('change', function () {
      var d = new Date(dataEl.value + 'T12:00:00');
      dataEl.setCustomValidity(dataEl.value && dias.indexOf(d.getDay()) < 0 ? 'Não abrimos neste dia da semana.' : '');
    });
    f.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var res = f.querySelector('[role=status]'), bt = f.querySelector('button[type=submit]');
      var d = { acao: f.getAttribute('data-rest'), e: f.getAttribute('data-e') };
      new FormData(f).forEach(function (v, k) { d[k] = v; });
      if (dataEl && dataEl.validationMessage) { res.textContent = dataEl.validationMessage; return; }
      bt.disabled = true; res.textContent = 'Enviando…';
      post(d).then(function (j) {
        res.textContent = j.msg || ''; bt.disabled = false;
        if (j.ok && j.link) { location.href = j.link; return; }
        if (j.ok) f.reset();
      }).catch(function () { res.textContent = 'Não foi possível agora. Tente de novo.'; bt.disabled = false; });
    });
  });
  document.querySelectorAll('.lp-garcom').forEach(function (box) {
    var res = box.querySelector('[role=status]');
    box.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-chamar]'); if (!b) return;
      b.disabled = true; res.textContent = 'Chamando…';
      post({ acao: 'chamar', e: box.getAttribute('data-e'), mesa: box.getAttribute('data-mesa'), tipo: b.getAttribute('data-chamar') })
        .then(function (j) { res.textContent = j.msg || ''; setTimeout(function () { b.disabled = false; }, 20000); })
        .catch(function () { res.textContent = 'Não foi possível chamar agora.'; b.disabled = false; });
    });
  });
})();

// ---------- serviços: grade de aulas, quiz, calculadora e tour 360 (includes/recursos_servicos.php) ----------
(function () {
  'use strict';
  var moeda = function (v) { return 'R$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };

  // grade: abas dos dias (setas do teclado também)
  document.querySelectorAll('[data-grade]').forEach(function (g) {
    var abas = [].slice.call(g.querySelectorAll('[role=tab]'));
    var abrir = function (a, foco) {
      abas.forEach(function (x) { var on = x === a; x.setAttribute('aria-selected', on); x.tabIndex = on ? 0 : -1; document.getElementById(x.getAttribute('aria-controls')).hidden = !on; });
      if (foco) a.focus();
    };
    abas.forEach(function (a, i) {
      a.addEventListener('click', function () { abrir(a); });
      a.addEventListener('keydown', function (e) { var d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0; if (d) { e.preventDefault(); abrir(abas[(i + d + abas.length) % abas.length], true); } });
    });
  });

  // quiz: categoria → tempo → preço → sugestão
  document.querySelectorAll('[data-quiz]').forEach(function (q) {
    var dados = []; try { dados = JSON.parse(q.querySelector('.lp-quiz-dados').textContent); } catch (e) { return; }
    var passo = q.querySelector('.lp-quiz-passo'), acao = q.getAttribute('data-acao'), esc = {};
    var cats = dados.map(function (d) { return d.cat; }).filter(function (c, i, a) { return c && a.indexOf(c) === i; });
    var precos = dados.map(function (d) { return d.preco; }).filter(function (p) { return p > 0; }).sort(function (a, b) { return a - b; });
    var temTempo = dados.some(function (d) { return d.min > 0; });
    var perguntas = [];
    if (cats.length >= 2) perguntas.push({ k: 'cat', t: 'O que você procura?', o: cats.slice(0, 6).map(function (c) { return [c, c]; }).concat([['', 'Ainda não sei']]) });
    if (temTempo) perguntas.push({ k: 'tempo', t: 'Quanto tempo você tem?', o: [['30', 'Até 30 minutos'], ['60', 'Até 1 hora'], ['999', 'Tenho tempo']] });
    if (precos.length >= 3) { var m = precos[Math.floor(precos.length / 2)]; perguntas.push({ k: 'preco', t: 'Quanto quer investir?', o: [[String(m), 'Até ' + moeda(m)], ['999999', 'O melhor resultado']] }); }
    if (!perguntas.length) perguntas.push({ k: 'cat', t: 'Vamos começar?', o: [['', 'Ver sugestões']] });
    var mostrar = function (i) {
      if (i >= perguntas.length) return resultado();
      var p = perguntas[i];
      passo.innerHTML = '<p class="lp-quiz-n">Pergunta ' + (i + 1) + ' de ' + perguntas.length + '</p><h3 tabindex="-1"></h3><div class="lp-quiz-op"></div>';
      passo.querySelector('h3').textContent = p.t;
      p.o.forEach(function (o) {
        var b = document.createElement('button'); b.type = 'button'; b.className = 'lp-btn lp-btn-2'; b.textContent = o[1];
        b.onclick = function () { esc[p.k] = o[0]; mostrar(i + 1); };
        passo.querySelector('.lp-quiz-op').appendChild(b);
      });
      if (i > 0) passo.querySelector('h3').focus();
    };
    var resultado = function () {
      var lim = function (v, d) { return v === undefined || v === '' ? d : Number(v); };
      var nota = function (d) {
        var n = 0;
        if (esc.cat && d.cat === esc.cat) n += 3;
        if (d.min && d.min <= lim(esc.tempo, 999)) n += 2; else if (!d.min) n += 1;
        if (!d.preco || d.preco <= lim(esc.preco, 999999)) n += 2;
        return n;
      };
      var top = dados.slice().sort(function (a, b) { return nota(b) - nota(a); }).slice(0, 3);
      passo.innerHTML = '<h3 tabindex="-1">Nossa sugestão para você</h3><ul class="lp-quiz-res"></ul><button type="button" class="lp-link lp-quiz-de-novo">Responder de novo</button>';
      top.forEach(function (d) {
        var li = document.createElement('li');
        li.innerHTML = '<div><strong></strong><small></small></div>';
        li.querySelector('strong').textContent = d.nome;
        li.querySelector('small').textContent = [d.preco ? moeda(d.preco) : '', d.min ? d.min + ' min' : '', d.desc].filter(Boolean).join(' · ');
        var a = document.createElement('a'); a.className = 'lp-btn';
        if (acao === 'agendar') { a.href = '#gds-agendamento'; a.setAttribute('data-gds-agendar-servico', d.id); a.textContent = 'Agendar'; }
        else if (acao === 'orcar') { a.href = '#agendar'; a.setAttribute('data-gds-orcar-servico', d.id); a.textContent = 'Pedir orçamento'; }
        else { a.href = '#servicos'; a.textContent = 'Ver no cardápio'; }
        li.appendChild(a); passo.querySelector('.lp-quiz-res').appendChild(li);
      });
      passo.querySelector('.lp-quiz-de-novo').onclick = function () { esc = {}; mostrar(0); };
      passo.querySelector('h3').focus();
    };
    mostrar(0);
  });

  // calculadora
  document.querySelectorAll('[data-calc]').forEach(function (f) {
    var total = f.querySelector('.lp-calc-total strong');
    var conta = function () {
      var t = 0;
      f.querySelectorAll('input[type=checkbox]').forEach(function (c) {
        var q = f.querySelector('[data-qtd="' + c.name + '"]');
        q.disabled = !c.checked;
        if (c.checked) t += Number(c.value) * Math.max(1, parseInt(q.value, 10) || 1);
      });
      total.textContent = moeda(t);
    };
    f.addEventListener('input', conta); f.addEventListener('change', conta); conta();
    var pedir = f.querySelector('[data-calc-pedir]');
    if (pedir) pedir.addEventListener('click', function () {
      var itens = [];
      f.querySelectorAll('input[type=checkbox]:checked').forEach(function (c) { var q = f.querySelector('[data-qtd="' + c.name + '"]'); itens.push((parseInt(q.value, 10) || 1) + '× ' + c.getAttribute('data-nome')); });
      var campo = document.querySelector('#agendar textarea');
      if (campo && itens.length) { campo.value = (campo.value ? campo.value + '\n' : '') + 'Simulei no site: ' + itens.join(', ') + ' (estimativa ' + total.textContent + ').'; }
    });
  });

  // tour 360 (Pannellum, carregado só quando a seção aparece)
  var tour = document.querySelector('[data-tour]');
  if (tour) {
    var tela = tour.querySelector('#lpTour360'), visor = null, carregado = false;
    var abrir = function (img) {
      if (!window.pannellum) return;
      if (visor) visor.destroy();
      visor = pannellum.viewer(tela, { type: 'equirectangular', panorama: img, autoLoad: true, showControls: true, compass: false, autoRotate: matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : -2, haov: 360, vaov: 180 });
    };
    var carregar = function () {
      if (carregado) return; carregado = true;
      var css = document.createElement('link'); css.rel = 'stylesheet'; css.href = 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css'; document.head.appendChild(css);
      var js = document.createElement('script'); js.src = 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js';
      js.onload = function () { var b = tour.querySelector('[data-tour-img]'); if (b) abrir(b.getAttribute('data-tour-img')); };
      document.head.appendChild(js);
    };
    if ('IntersectionObserver' in window) new IntersectionObserver(function (es, o) { if (es[0].isIntersecting) { carregar(); o.disconnect(); } }, { rootMargin: '200px' }).observe(tour); else carregar();
    tour.addEventListener('click', function (e) {
      var b = e.target.closest('[data-tour-img]'); if (!b) return;
      tour.querySelectorAll('[data-tour-img]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      abrir(b.getAttribute('data-tour-img'));
    });
  }
})();

// ---------- vale-presente (includes/treino_vale.php) ----------
(function () {
  'use strict';
  document.querySelectorAll('form[data-vale]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var res = f.querySelector('[role=status]'), bt = f.querySelector('button[type=submit]');
      var fd = new FormData(f); fd.append('acao', 'vale'); fd.append('e', f.getAttribute('data-e'));
      bt.disabled = true; res.textContent = 'Preparando o seu vale…';
      fetch('/site/recursos.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); })
        .then(function (j) { res.textContent = j.msg || ''; bt.disabled = false; if (j.ok && j.link) location.href = j.link; })
        .catch(function () { res.textContent = 'Não foi possível agora. Tente de novo.'; bt.disabled = false; });
    });
  });
})();

// ---------- "Monte o seu" (tela do produto com 2+ grupos de opções) ----------
(function () {
  'use strict';
  if (!window.gdsMonteAtivo) return;
  window.gdsMonte = function (box, grupos) {
    var fs = [].slice.call(box.querySelectorAll('.gds-op-grupo'));
    if (fs.length < 2) return;
    box.classList.add('gds-monte');
    // montagem visual: cada escolha vira uma camada (a última escolhida fica em cima)
    var pilha = document.createElement('div');
    pilha.className = 'gds-monte-pilha'; pilha.setAttribute('aria-live', 'polite');
    pilha.innerHTML = '<p class="gds-monte-tit">Sua montagem</p><ol></ol><p class="gds-monte-vazio">Escolha os ingredientes e veja o seu pedido tomando forma.</p>';
    var cab = box.querySelector('.gds-prod-cab'); cab.parentNode.insertBefore(pilha, cab.nextSibling);
    var nav = document.createElement('div');
    nav.className = 'gds-monte-nav';
    nav.innerHTML = '<span class="gds-monte-passo"></span><span><button type="button" class="gds-btn-2" data-volta>Voltar</button> <button type="button" class="gds-btn" data-segue>Próximo</button></span>';
    var obs = box.querySelector('.gds-prod-obs'); obs.parentNode.insertBefore(nav, obs);
    var passo = 0;
    var escolhidos = function () {
      var out = [];
      fs.forEach(function (f, gi) {
        f.querySelectorAll('input:checked').forEach(function (c) { out.push({ g: gi, nome: grupos[gi].itens[+c.value].nome }); });
        f.querySelectorAll('.gds-op-qtd').forEach(function (l) { var n = parseInt(l.querySelector('b').textContent, 10) || 0; for (var k = 0; k < n; k++) out.push({ g: gi, nome: grupos[gi].itens[+l.dataset.i].nome }); });
      });
      return out;
    };
    var ultimo = null;
    var desenhar = function () {
      var es = escolhidos(), ol = pilha.querySelector('ol'), chave = JSON.stringify(es);
      if (chave === ultimo) return;   // só redesenha quando a escolha muda (sem piscar)
      var antes = ultimo ? JSON.parse(ultimo).map(function (x) { return x.g + ':' + x.nome; }) : [];
      ultimo = chave;
      ol.innerHTML = '';
      es.slice().reverse().forEach(function (x) {
        var li = document.createElement('li'); li.className = 'gds-monte-camada c' + (x.g % 5) + (antes.indexOf(x.g + ':' + x.nome) < 0 ? ' nova' : ''); li.textContent = x.nome; ol.appendChild(li);
      });
      pilha.querySelector('.gds-monte-vazio').hidden = es.length > 0;
    };
    var ir = function (p) {
      passo = Math.max(0, Math.min(fs.length, p));
      fs.forEach(function (f, i) { f.hidden = i !== passo; });
      var fim = passo >= fs.length;
      obs.hidden = !fim;
      nav.querySelector('.gds-monte-passo').textContent = fim ? 'Tudo certo? Confira e adicione.' : 'Passo ' + (passo + 1) + ' de ' + fs.length;
      nav.querySelector('[data-volta]').hidden = passo === 0;
      nav.querySelector('[data-segue]').hidden = fim;
      var alvo = fim ? obs : fs[passo];
      if (alvo && alvo.scrollIntoView) alvo.scrollIntoView({ block: 'nearest' });
    };
    nav.querySelector('[data-segue]').onclick = function () {
      var g = grupos[passo], n = escolhidos().filter(function (x) { return x.g === passo; }).length;
      if (g.min > 0 && n < g.min) { fs[passo].classList.add('gds-op-falta'); var err = box.querySelector('.gds-op-erro'); err.hidden = false; err.textContent = 'Escolha: ' + g.nome + (g.min > 1 ? ' (' + g.min + ')' : ''); return; }
      ir(passo + 1);
    };
    nav.querySelector('[data-volta]').onclick = function () { ir(passo - 1); };
    // o botão "Adicionar" volta ao grupo que faltou
    box.querySelector('[data-ok]').addEventListener('click', function () { setTimeout(function () { var f = box.querySelector('.gds-op-falta'); if (f) ir(fs.indexOf(f)); }, 0); }, true);
    box.addEventListener('change', desenhar); box.addEventListener('click', function () { setTimeout(desenhar, 0); });
    desenhar(); ir(0);
  };
})();
