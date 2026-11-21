/* ============================================================
   APP DO MOTOBOY — GPS ao vivo + Saí / Entreguei / Voltei
   • Manda a posição a cada ~8 s em rota (ou ao andar 25 m) e a cada 30 s na loja.
   • Em rota, mantém a tela acesa (Wake Lock): o navegador só manda o GPS com o app aberto.
   • Ao chegar perto da loja depois de sair, o site encerra a saída sozinho (os links expiram).
   ============================================================ */
(function () {
  'use strict';
  var raiz = document.getElementById('app');
  var T = raiz.dataset.t, EMPRESA = raiz.dataset.empresa, EMPRESA_NOME = raiz.dataset.empresaNome;
  var API = '/api/rastreio.php?acao=app';
  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var estado = { situacao: 'loja', entregas: [] };
  var ultimo = null, ultimoEnvio = 0, enviando = false, posEnviada = null, bateria = null, travaTela = null, gpsId = null;

  try { localStorage.setItem('gds_motoboy_' + EMPRESA, T); } catch (e) {}

  function aviso(txt, tipo) {
    var a = $('aviso');
    if (!txt) { a.hidden = true; return; }
    a.className = tipo === 'ok' ? 'rz-aviso' : 'rz-erro'; a.textContent = txt; a.hidden = false;
    if (tipo === 'ok') setTimeout(function () { a.hidden = true; }, 6000);
  }

  function chamar(op, extra) {
    var corpo = Object.assign({ t: T, op: op }, extra || {});
    return fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(corpo), cache: 'no-store' })
      .then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); })
      .then(function (j) {
        if (j.sair) { try { localStorage.removeItem('gds_motoboy_' + EMPRESA); } catch (e) {} location.reload(); }
        return j;
      });
  }

  function telWa(t) { t = String(t || '').replace(/\D/g, ''); if (t.length === 10 || t.length === 11) t = '55' + t; return t; }
  function linkRota(e) {
    if (e.destino) return 'https://www.google.com/maps/dir/?api=1&destination=' + e.destino.lat + ',' + e.destino.lng;
    return e.endereco ? 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(e.endereco) : null;
  }
  function linkWa(e) {
    var t = telWa(e.telefone);
    if (!t) return null;
    var msg = 'Olá' + (e.cliente ? ' ' + e.cliente.split(' ')[0] : '') + '! Aqui é o motoboy da ' + EMPRESA_NOME + '. Seu pedido está comigo 🛵\nAcompanhe ao vivo: ' + e.link;
    return 'https://wa.me/' + t + '?text=' + encodeURIComponent(msg);
  }

  function qtd(n, um, varios) { return n + ' ' + (n === 1 ? um : varios); }
  function render(j) {
    if (!j || !j.ok) return;
    estado = j;
    var s = j.situacao || 'loja';
    var tit = { loja: 'Na loja', rota: 'Em rota', voltando: 'Voltando para a loja' }[s] || s;
    var abertas = j.entregas.filter(function (e) { return e.status === 'aguardando'; }).length;
    var naRua = j.entregas.filter(function (e) { return e.status === 'rota'; }).length;
    $('status').dataset.s = s;
    $('statusTit').textContent = tit;
    $('statusSub').textContent = (s === 'loja' ? (abertas ? qtd(abertas, 'entrega para levar', 'entregas para levar') : 'Nenhuma entrega esperando') : (naRua ? qtd(naRua, 'entrega em andamento', 'entregas em andamento') : 'Todas entregues'))
      + ' · ' + qtd(j.entregues_hoje, 'entregue hoje', 'entregues hoje');
    var b = $('btnPrincipal');
    if (s === 'loja') { b.textContent = abertas ? 'Saí para entregar (' + abertas + ')' : 'Saí para entregar'; b.className = 'rz-btn rz-btn-faixa rz-btn-grande'; b.dataset.op = 'sair'; }
    else { b.textContent = 'Voltei para a loja'; b.className = 'rz-btn rz-btn-volta rz-btn-grande'; b.dataset.op = 'voltei'; }
    var lista = $('lista');
    if (!j.entregas.length) { lista.innerHTML = '<div class="rz-lista-vazia">Nenhuma entrega agora. A loja pode cadastrar, ou toque em “+ Nova entrega”.</div>'; }
    else {
      lista.innerHTML = j.entregas.map(function (e) {
        var rota = linkRota(e), wa = linkWa(e);
        var chip = { aguardando: 'Esperando sair', rota: 'A caminho', entregue: 'Entregue ' + (e.entregue || '') }[e.status];
        return '<article class="rz-entrega">'
          + '<div class="rz-entrega-topo"><div><h3>' + esc(e.cliente || ('Pedido ' + (e.codigo || '#' + e.id))) + '</h3>'
          + (e.codigo && e.cliente ? '<span class="rz-mute">Pedido ' + esc(e.codigo) + '</span>' : '') + '</div>'
          + '<span class="rz-chip" data-s="' + esc(e.status) + '">' + esc(chip) + '</span></div>'
          + (e.endereco ? '<div class="rz-end">' + esc(e.endereco) + '</div>' : '')
          + (e.observacao ? '<div class="rz-end">Obs.: ' + esc(e.observacao) + '</div>' : '')
          + (e.status !== 'entregue' ? '<div class="rz-entrega-acoes">'
            + (rota ? '<a class="rz-btn rz-btn-linha" href="' + esc(rota) + '" target="_blank" rel="noopener">Rota no mapa</a>' : '')
            + (wa ? '<a class="rz-btn rz-btn-linha" href="' + esc(wa) + '" target="_blank" rel="noopener">WhatsApp</a>' : '<button class="rz-btn rz-btn-linha" type="button" data-copiar="' + esc(e.link) + '">Copiar link</button>')
            + '<button class="rz-btn rz-btn-ok" type="button" data-entreguei="' + e.id + '">Entreguei</button></div>' : '')
          + '</article>';
      }).join('');
    }
    telaAcesa(s !== 'loja');
  }

  function atualizar() { return chamar('estado').then(render).catch(function () { aviso('Sem internet agora. Tentando de novo…'); }); }

  // ---------- GPS ----------
  function gpsSelo(ok, txt) { var g = $('gpsSelo'); g.classList.toggle('parado', !ok); g.textContent = txt; }
  function distancia(a, b) {
    var r = 6371000, p1 = a.lat * Math.PI / 180, p2 = b.lat * Math.PI / 180, dl = (b.lng - a.lng) * Math.PI / 180, dp = p2 - p1;
    var x = Math.sin(dp / 2) * Math.sin(dp / 2) + Math.cos(p1) * Math.cos(p2) * Math.sin(dl / 2) * Math.sin(dl / 2);
    return 2 * r * Math.asin(Math.min(1, Math.sqrt(x)));
  }
  function talvezEnviar(forcar) {
    if (!ultimo || enviando) return;
    var agora = Date.now(), emRota = estado.situacao !== 'loja';
    var intervalo = emRota ? 8000 : 30000;
    var andou = posEnviada ? distancia(posEnviada, ultimo) : Infinity;
    if (!forcar && agora - ultimoEnvio < intervalo && !(emRota && andou > 25 && agora - ultimoEnvio > 3000)) return;
    enviando = true; ultimoEnvio = agora;
    var p = ultimo;
    chamar('posicao', { lat: p.lat, lng: p.lng, precisao: p.acc, velocidade: p.vel, rumo: p.rumo, bateria: bateria })
      .then(function (j) {
        enviando = false;
        if (j.ok) { posEnviada = p; $('gpsInfo').textContent = 'Precisão ' + Math.round(p.acc || 0) + ' m · enviado ' + new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + (bateria != null ? ' · bateria ' + bateria + '%' : ''); }
        if (j.voltou_auto) { aviso('Você chegou na loja: a saída foi encerrada e os links dos clientes expiraram.', 'ok'); atualizar(); }
        else if (j.situacao && j.situacao !== estado.situacao) atualizar();
      })
      .catch(function () { enviando = false; });
  }
  function ligarGps() {
    if (!('geolocation' in navigator)) { gpsSelo(false, 'Sem GPS'); aviso('Este navegador não tem GPS. Use o Chrome do celular.'); return; }
    if (gpsId != null) navigator.geolocation.clearWatch(gpsId);
    gpsId = navigator.geolocation.watchPosition(function (pos) {
      var c = pos.coords;
      ultimo = { lat: c.latitude, lng: c.longitude, acc: c.accuracy, vel: c.speed != null && c.speed >= 0 ? c.speed : null, rumo: c.heading != null && !isNaN(c.heading) ? c.heading : null };
      gpsSelo(true, c.accuracy <= 50 ? 'GPS ao vivo' : 'GPS fraco');
      aviso('');
      talvezEnviar(false);
    }, function (err) {
      gpsSelo(false, 'GPS desligado');
      if (err.code === 1) aviso('Permita o acesso à localização: toque no cadeado ao lado do endereço → Permissões → Localização → Permitir. Depois recarregue.');
      else aviso('Não consegui pegar o GPS. Ligue a localização do celular (alta precisão).');
    }, { enableHighAccuracy: true, maximumAge: 5000, timeout: 30000 });
  }
  setInterval(function () { talvezEnviar(false); }, 4000);

  // Tela acesa enquanto estiver em rota
  function telaAcesa(ligar) {
    if (!('wakeLock' in navigator)) return;
    if (ligar && !travaTela) navigator.wakeLock.request('screen').then(function (t) { travaTela = t; t.addEventListener('release', function () { travaTela = null; }); }).catch(function () {});
    if (!ligar && travaTela) { travaTela.release(); travaTela = null; }
  }
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') { telaAcesa(estado.situacao !== 'loja'); talvezEnviar(true); atualizar(); }
  });
  if (navigator.getBattery) navigator.getBattery().then(function (b) { var f = function () { bateria = Math.round(b.level * 100); }; f(); b.addEventListener('levelchange', f); }).catch(function () {});

  // ---------- botões ----------
  $('btnPrincipal').addEventListener('click', function () {
    var b = this, op = b.dataset.op || 'sair';
    if (op === 'voltei' && estado.entregas.some(function (e) { return e.status === 'rota'; }) && !confirm('Ainda tem entrega marcada como "a caminho". Voltar mesmo assim? Os links dos clientes vão expirar.')) return;
    b.disabled = true;
    chamar(op).then(function (j) { b.disabled = false; render(j); talvezEnviar(true); if (op === 'sair') aviso('Boa entrega! Deixe esta tela aberta.', 'ok'); })
      .catch(function () { b.disabled = false; aviso('Sem internet. Tente de novo.'); });
  });
  $('lista').addEventListener('click', function (ev) {
    var b = ev.target.closest('button');
    if (!b) return;
    if (b.dataset.entreguei) {
      b.disabled = true;
      chamar('entreguei', { id: +b.dataset.entreguei }).then(render).catch(function () { b.disabled = false; aviso('Sem internet. Tente de novo.'); });
    } else if (b.dataset.copiar) {
      var l = b.dataset.copiar;
      (navigator.clipboard ? navigator.clipboard.writeText(l) : Promise.reject()).then(function () { b.textContent = 'Copiado!'; }).catch(function () { prompt('Copie o link:', l); });
    }
  });
  var dlg = $('dlgNova');
  $('btnNova').addEventListener('click', function () { $('formNova').reset(); $('novaErro').hidden = true; dlg.showModal(); $('nCliente').focus(); });
  $('formNova').addEventListener('submit', function (ev) {
    if (ev.submitter && ev.submitter.value === 'cancelar') return;
    ev.preventDefault();
    var f = this, btn = $('btnCriar');
    btn.disabled = true;
    chamar('nova', { cliente: f.cliente.value, telefone: f.telefone.value, endereco: f.endereco.value, codigo: f.codigo.value }).then(function (j) {
      btn.disabled = false;
      if (!j.ok) { $('novaErro').textContent = j.erro || 'Não deu certo.'; $('novaErro').hidden = false; return; }
      dlg.close(); render(j);
      var nova = j.nova && j.entregas.find(function (e) { return e.id === j.nova.id; });
      var wa = nova && linkWa(nova);
      if (wa) window.open(wa, '_blank', 'noopener');
      else if (nova) aviso('Entrega criada. Link do cliente: ' + nova.link, 'ok');
    }).catch(function () { btn.disabled = false; $('novaErro').textContent = 'Sem internet. Tente de novo.'; $('novaErro').hidden = false; });
  });

  // Instalar na tela inicial (Android/Chrome)
  var pedidoInstalar = null;
  window.addEventListener('beforeinstallprompt', function (ev) { ev.preventDefault(); pedidoInstalar = ev; $('btnInstalar').hidden = false; });
  $('btnInstalar').addEventListener('click', function () { if (pedidoInstalar) { pedidoInstalar.prompt(); pedidoInstalar = null; this.hidden = true; } });

  atualizar().then(ligarGps);
  setInterval(atualizar, 20000);   // entregas novas que a loja cadastrar aparecem sozinhas
})();
