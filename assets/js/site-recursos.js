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
