/* ============================================================
   RASTREIO — painel da empresa (/empresa/rastreio.php)
   • mapa ao vivo: motoboys deslizando, rastro, destinos, contadores, listas
   • nova entrega por fetch (o mapa não recarrega) → link do cliente + WhatsApp
   • aba "Loja e TV": marcar a loja no mapa (GPS, endereço ou toque)
   ============================================================ */
(function () {
  'use strict';
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var meta = document.querySelector('meta[name="csrf-token"]');
  var csrf = meta ? meta.content : '';
  var CAB = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };

  // Copiar (qualquer botão com data-copiar)
  function copiar(texto, botao) {
    var ok = function () { if (!botao) return; var o = botao.textContent; botao.textContent = 'Copiado!'; setTimeout(function () { botao.textContent = o; }, 1600); };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(texto).then(ok, function () { prompt('Copie o link:', texto); });
    else prompt('Copie o link:', texto);
  }
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-copiar]');
    if (b) copiar(b.getAttribute('data-copiar'), b);
  });

  function postar(dados) {
    var fd = dados instanceof FormData ? dados : new FormData();
    if (!(dados instanceof FormData)) Object.keys(dados).forEach(function (k) { fd.append(k, dados[k]); });
    if (!fd.get('csrf_token')) fd.append('csrf_token', csrf);
    return fetch('rastreio.php', { method: 'POST', body: fd, headers: CAB, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, erro: 'Resposta inválida do servidor.' }; }); })
      .then(function (j) { if (!j.ok) throw new Error(j.erro || 'Não deu certo. Tente de novo.'); return j; });
  }

  function whats(tel, texto) {
    var t = String(tel || '').replace(/\D/g, '');
    if (t && t.length <= 11) t = '55' + t;
    return 'https://wa.me/' + t + '?text=' + encodeURIComponent(texto);
  }
  function msgCliente(e, empresa) {
    var oi = e.cliente ? 'Olá, ' + e.cliente.split(' ')[0] + '!' : 'Olá!';
    var st = e.status === 'rota' ? 'Seu pedido saiu para entrega.' : 'Seu pedido já está sendo preparado.';
    return oi + ' Aqui é da ' + empresa + '. ' + st + ' Acompanhe o motoboy ao vivo pelo link: ' + e.link;
  }

  /* ---------------- mapa ao vivo ---------------- */
  var painel = document.getElementById('rzPainel');
  if (painel && window.RZMapa && window.L) {
    var empresa = painel.getAttribute('data-empresa') || 'loja';
    var lojaIni = JSON.parse(painel.getAttribute('data-loja') || 'null');
    var mapa = RZMapa.criar(document.getElementById('rzMapa'), { escuro: true, centro: lojaIni ? [lojaIni.lat, lojaIni.lng] : null, zoom: 14 });
    var frota = new RZMapa.Frota(mapa, { nomes: true, rastro: true, aoClicar: function (id) { focar(id, true); } });
    var marcaLoja = RZMapa.pontoFixo(mapa, 'loja');
    var destinos = {};
    var ultimo = null, ultimoAjuste = 0, timer = null, motosAtuais = [], assinaturaEnt = '', assinaturaMot = '';
    var SIT = { loja: 'Na loja', rota: 'Em rota', voltando: 'Voltando' };
    var STA = { aguardando: 'Preparando', rota: 'Em rota', entregue: 'Entregue' };
    var $ = function (id) { return document.getElementById(id); };

    function vivo(ok, hora) {
      var v = $('rzVivo');
      v.classList.toggle('parado', !ok);
      v.textContent = ok ? 'Ao vivo' : 'Sem conexão — tentando de novo';
      if (hora) $('rzHora').textContent = 'atualizado ' + hora.slice(0, 5);
    }

    function extras() {
      var x = [];
      if (ultimo && ultimo.loja) x.push(ultimo.loja);
      (ultimo && ultimo.entregas || []).forEach(function (e) { if (e.status === 'rota' && e.destino) x.push(e.destino); });
      return x;
    }

    function focar(id, deClique) {
      var it = frota.itens[id];
      if (it) { mapa._rzMexeu = Date.now(); mapa.setView(it.marcador.getLatLng(), Math.max(mapa.getZoom(), 16), { animate: true }); }
      document.querySelectorAll('.rz-linha-moto').forEach(function (l) { l.classList.toggle('foco', l.getAttribute('data-id') === String(id)); });
      if (deClique) { var l = document.querySelector('.rz-linha-moto[data-id="' + id + '"]'); if (l && l.scrollIntoView) l.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
    }

    function linhaSinal(m) {
      var p = [];
      if (m.idade == null) p.push('GPS nunca enviado');
      else if (!m.sinal) p.push('sem sinal ' + RZMapa.tempo(m.idade));
      else p.push('GPS ' + RZMapa.tempo(m.idade));
      if (m.sinal && m.kmh != null && m.situacao !== 'loja') p.push(m.kmh + ' km/h');
      if (m.bateria != null) p.push('bateria ' + m.bateria + '%');
      if (m.fora_min != null) p.push('fora há ' + m.fora_min + ' min');
      if (m.pendentes) p.push(m.pendentes + (m.pendentes > 1 ? ' entregas' : ' entrega'));
      return p.join(' · ');
    }

    function desenharMotos(lista) {
      var alvo = $('listaMotos');
      var ass = JSON.stringify(lista.map(function (m) { return [m.id, m.situacao, m.sinal, Math.round((m.idade || 0) / 30), m.kmh, m.bateria, m.fora_min, m.pendentes]; }));
      if (ass === assinaturaMot) return; assinaturaMot = ass;
      if (!lista.length) { alvo.innerHTML = '<p class="dica">Nenhum motoboy ativo. <a href="?aba=motoboys">Cadastre o primeiro</a>.</p>'; return; }
      alvo.innerHTML = lista.map(function (m) {
        var s = m.situacao !== 'loja' && !m.sinal ? 'sem' : m.situacao;
        return '<div class="rz-linha-moto" data-id="' + m.id + '">'
          + '<button type="button" class="rz-av" style="--cor:' + esc(m.cor) + '" data-focar="' + m.id + '" aria-label="Ver ' + esc(m.nome) + ' no mapa">' + esc(m.iniciais) + '</button>'
          + '<div><strong>' + esc(m.nome) + '</strong> <span class="rz-chip" data-s="' + s + '">' + (s === 'sem' ? SIT[m.situacao] + ' · sem sinal' : SIT[m.situacao] || m.situacao) + '</span>'
          + '<small class="rz-num">' + esc(linhaSinal(m)) + '</small></div>'
          + (m.situacao !== 'loja' ? '<button type="button" class="btn btn-sm btn-ghost" data-voltou="' + m.id + '" title="Use se o celular dele desligou">Voltou</button>' : '<span></span>')
          + '</div>';
      }).join('');
    }

    function desenharEntregas(lista) {
      var alvo = $('listaEntregas');
      if (alvo.contains(document.activeElement) && document.activeElement.tagName === 'SELECT') return; // não atrapalha quem está escolhendo
      var ass = JSON.stringify([lista, motosAtuais.map(function (m) { return m.id; })]);
      if (ass === assinaturaEnt) return; assinaturaEnt = ass;
      if (!lista.length) { alvo.innerHTML = '<p class="dica">Nenhuma entrega agora. Crie uma acima ou peça para o motoboy criar no app.</p>'; return; }
      alvo.innerHTML = lista.map(function (e) {
        var titulo = e.cliente || (e.codigo ? 'Pedido nº ' + e.codigo : 'Entrega ' + e.id);
        var ativa = e.status === 'aguardando' || e.status === 'rota';
        var quem = ativa
          ? '<label class="rz-quem">Motoboy <select data-atribuir="' + e.id + '"><option value="">Ninguém ainda</option>' + motosAtuais.map(function (m) { return '<option value="' + m.id + '"' + (m.id === e.motoboy_id ? ' selected' : '') + '>' + esc(m.nome.split(' ')[0]) + '</option>'; }).join('') + '</select></label>'
          : (e.motoboy_id ? '<span>' + esc((motosAtuais.filter(function (m) { return m.id === e.motoboy_id; })[0] || {}).nome || '') + '</span>' : '');
        var tempos = ['criada ' + e.criado];
        if (e.saiu) tempos.push('saiu ' + e.saiu);
        if (e.entregue) tempos.push('entregue ' + e.entregue + (e.minutos != null ? ' (' + e.minutos + ' min)' : ''));
        else if (e.saiu && e.minutos != null) tempos.push('há ' + e.minutos + ' min na rua');
        return '<div class="rz-linha-entrega" data-id="' + e.id + '">'
          + '<div class="rz-linha-topo"><strong>' + esc(titulo) + (e.cliente && e.codigo ? ' <small>nº ' + esc(e.codigo) + '</small>' : '') + '</strong><span class="rz-chip" data-s="' + e.status + '">' + (STA[e.status] || e.status) + '</span></div>'
          + (e.endereco ? '<small>' + esc(e.endereco) + (e.destino ? '' : ' · <span title="O endereço não foi achado no mapa">sem ponto no mapa</span>') + '</small>' : '')
          + '<small class="rz-num">' + esc(tempos.join(' · ')) + '</small>'
          + '<div class="acoes">' + quem
          + '<a class="btn btn-sm" href="' + esc(whats(e.telefone, msgCliente(e, empresa))) + '" target="_blank" rel="noopener">WhatsApp</a>'
          + '<button type="button" class="btn btn-sm btn-ghost" data-copiar="' + esc(e.link) + '">Copiar link</button>'
          + (ativa ? '<button type="button" class="btn btn-sm btn-ghost" data-entregue="' + e.id + '">Entregue</button><button type="button" class="btn btn-sm btn-perigo" data-cancelar="' + e.id + '">Cancelar</button>' : '')
          + '</div></div>';
      }).join('');
    }

    function desenharDestinos(lista) {
      var vistos = {};
      lista.forEach(function (e) {
        if (!e.destino || (e.status !== 'rota' && e.status !== 'aguardando')) return;
        vistos[e.id] = true;
        if (!destinos[e.id]) destinos[e.id] = L.marker([e.destino.lat, e.destino.lng], { icon: RZMapa.iconePonto('destino'), keyboard: false, title: e.cliente || e.endereco || '' }).addTo(mapa);
        else destinos[e.id].setLatLng([e.destino.lat, e.destino.lng]);
        destinos[e.id].setOpacity(e.status === 'rota' ? 1 : .55);
      });
      Object.keys(destinos).forEach(function (id) { if (!vistos[id]) { mapa.removeLayer(destinos[id]); delete destinos[id]; } });
    }

    function desenhar(d) {
      ultimo = d; motosAtuais = d.motoboys || [];
      frota.atualizar(motosAtuais);
      marcaLoja(d.loja, 'Loja');
      desenharDestinos(d.entregas || []);
      var r = d.resumo || {};
      $('kRota').textContent = r.rota || 0; $('kVoltando').textContent = r.voltando || 0; $('kLoja').textContent = r.loja || 0;
      $('kHoje').textContent = r.entregues_hoje || 0; $('kMedia').textContent = r.media_min != null ? r.media_min + ' min' : '–';
      desenharMotos(motosAtuais);
      desenharEntregas(d.entregas || []);
      if (!frota.ajustado || Date.now() - ultimoAjuste > 30000) { frota.ajustar(extras()); ultimoAjuste = Date.now(); }
    }

    function carregar() {
      clearTimeout(timer);
      fetch('rastreio.php?acao=mapa', { headers: CAB, cache: 'no-store', credentials: 'same-origin' })
        .then(function (r) { if (r.status === 401) { location.href = '/login.php'; throw new Error('sessao'); } return r.json(); })
        .then(function (d) { if (!d.ok) throw new Error(d.erro); desenhar(d); vivo(true, d.agora); })
        .catch(function () { vivo(false); })
        .then(function () { timer = setTimeout(carregar, document.hidden ? 30000 : 5000); });
    }
    document.addEventListener('visibilitychange', function () { if (!document.hidden) carregar(); });
    $('rzAjustar').addEventListener('click', function () { mapa._rzMexeu = 0; frota.ajustar(extras(), true); ultimoAjuste = Date.now(); });

    // Ações das listas
    painel.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-focar],[data-voltou],[data-entregue],[data-cancelar]');
      if (!b) return;
      if (b.hasAttribute('data-focar')) return focar(+b.getAttribute('data-focar'));
      var falha = function (e) { alert(e.message); };
      if (b.hasAttribute('data-voltou')) {
        if (confirm('Marcar que ele voltou para a loja? Os links dos clientes desta saída expiram.')) postar({ acao: 'voltou', id: b.getAttribute('data-voltou') }).then(carregar, falha);
      } else if (b.hasAttribute('data-entregue')) {
        postar({ acao: 'entregue', id: b.getAttribute('data-entregue') }).then(carregar, falha);
      } else if (confirm('Cancelar esta entrega? O link do cliente para de funcionar.')) {
        postar({ acao: 'cancelar', id: b.getAttribute('data-cancelar') }).then(carregar, falha);
      }
    });
    painel.addEventListener('change', function (ev) {
      var s = ev.target.closest('[data-atribuir]'); if (!s) return;
      s.disabled = true;
      postar({ acao: 'atribuir', id: s.getAttribute('data-atribuir'), motoboy: s.value }).then(function () { s.blur(); assinaturaEnt = ''; carregar(); }, function (e) { alert(e.message); s.disabled = false; });
    });

    // Nova entrega
    var form = $('formNova');
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var btn = $('nvBotao'), erro = $('nvErro'), fd = new FormData(form);
      erro.hidden = true; btn.disabled = true; btn.textContent = 'Criando…';
      var info = { cliente: (fd.get('cliente') || '').trim(), telefone: fd.get('telefone') || '', status: fd.get('motoboy') ? 'rota' : 'aguardando' };
      postar(fd).then(function (j) {
        info.link = j.link;
        var moto = motosAtuais.filter(function (m) { return String(m.id) === String(fd.get('motoboy')); })[0];
        if (!moto || moto.situacao === 'loja') info.status = 'aguardando';
        $('nvLink').value = j.link;
        $('nvWa').href = whats(info.telefone, msgCliente(info, empresa));
        $('nvPronto').querySelector('.dica').textContent = j.geocodificado || !fd.get('endereco')
          ? 'O link mostra o motoboy no mapa só enquanto ele vai até o cliente e expira quando ele volta para a loja.'
          : 'Não achei esse endereço no mapa: o cliente acompanha o motoboy, mas sem a previsão de chegada.';
        form.hidden = true; $('nvPronto').hidden = false; $('nvWa').focus();
        assinaturaEnt = ''; carregar();
      }).catch(function (e) { erro.textContent = e.message; erro.hidden = false; })
        .then(function () { btn.disabled = false; btn.textContent = 'Criar e gerar o link do cliente'; });
    });
    $('nvCopiar').addEventListener('click', function () { copiar($('nvLink').value, this); });
    $('nvOutra').addEventListener('click', function () {
      var manter = form.querySelector('input[name="motoboy"]:checked');
      form.reset(); if (manter) manter.checked = true;
      form.hidden = false; $('nvPronto').hidden = true; $('nvCliente').focus();
    });

    carregar();
  }

  /* ---------------- marcar a loja (aba "Loja e TV") ---------------- */
  var lojaEl = document.getElementById('lojaMapa');
  if (lojaEl && window.RZMapa && window.L) {
    var pos = JSON.parse(lojaEl.getAttribute('data-loja') || 'null');
    var m2 = RZMapa.criar(lojaEl, { escuro: true, centro: pos ? [pos.lat, pos.lng] : null, zoom: pos ? 17 : 4 });
    var marca = null, circulo = null;
    var lat = document.getElementById('lojaLat'), lng = document.getElementById('lojaLng'), info = document.getElementById('lojaInfo');
    var raio = document.querySelector('[name="rastreio_raio"]');
    function marcar(a, b, zoom, texto) {
      a = Math.round(a * 1e6) / 1e6; b = Math.round(b * 1e6) / 1e6;
      lat.value = a; lng.value = b;
      if (!marca) {
        marca = L.marker([a, b], { icon: RZMapa.iconePonto('loja'), draggable: true, title: 'Loja' }).addTo(m2);
        marca.on('dragend', function () { var p = marca.getLatLng(); marcar(p.lat, p.lng, null, 'Marcador movido. Toque em Salvar para guardar.'); });
        circulo = L.circle([a, b], { radius: +raio.value || 80, color: '#f5c518', weight: 2, fillOpacity: .12 }).addTo(m2);
      } else { marca.setLatLng([a, b]); circulo.setLatLng([a, b]); }
      if (zoom) m2.setView([a, b], zoom);
      info.textContent = texto || 'Loja marcada. Toque em Salvar para guardar.';
    }
    if (pos) marcar(pos.lat, pos.lng, null, info.textContent);
    raio.addEventListener('input', function () { if (circulo) circulo.setRadius(+raio.value || 80); });
    m2.on('click', function (ev) { marcar(ev.latlng.lat, ev.latlng.lng, null, 'Loja marcada no ponto que você tocou. Toque em Salvar para guardar.'); });
    document.getElementById('lojaAqui').addEventListener('click', function () {
      var b = this;
      if (!navigator.geolocation) { info.textContent = 'Este navegador não informa a localização. Digite o endereço ou toque no mapa.'; return; }
      b.disabled = true; info.textContent = 'Pegando a sua localização…';
      navigator.geolocation.getCurrentPosition(function (p) {
        b.disabled = false;
        marcar(p.coords.latitude, p.coords.longitude, 18, 'Localização marcada (precisão de ~' + Math.round(p.coords.accuracy) + ' m). Confira no mapa e toque em Salvar.');
      }, function (e) {
        b.disabled = false;
        info.textContent = e.code === 1 ? 'A localização foi bloqueada. Permita no cadeado ao lado do endereço do site, ou digite o endereço.' : 'Não consegui a localização agora. Digite o endereço ou toque no mapa.';
      }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
    });
    function buscar() {
      var campo = document.getElementById('lojaEndereco'), v = campo.value.trim();
      if (v.length < 6) { info.textContent = 'Digite rua, número, bairro e cidade.'; campo.focus(); return; }
      info.textContent = 'Procurando no mapa…';
      postar({ acao: 'buscar_endereco', endereco: v }).then(function (j) { marcar(j.lat, j.lng, 17, 'Achei! Confira se o marcador está na porta da loja (arraste se precisar) e toque em Salvar.'); }, function (e) { info.textContent = e.message; });
    }
    document.getElementById('lojaBuscar').addEventListener('click', buscar);
    document.getElementById('lojaEndereco').addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); buscar(); } });
  }
})();
