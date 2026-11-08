/* ============================================================
   RASTREIO — motor do mapa (Leaflet + OpenStreetMap, sem chave)
   Usado pelo painel da empresa, pela TV e pelo link do cliente.
   RZMapa.criar(el, {escuro, centro, zoom})  →  mapa
   new RZMapa.Frota(mapa, {nomes, rastro})   →  .atualizar(listaDeMotoboys), .ajustar(extras)
   Os marcadores deslizam até a nova posição (sem pulos) e mostram a direção.
   ============================================================ */
(function () {
  'use strict';
  var reduzir = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };

  function criar(el, o) {
    o = o || {};
    if (o.escuro) el.classList.add('rz-escuro');
    var mapa = L.map(el, { zoomControl: o.zoomControle !== false, attributionControl: true, preferCanvas: true });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(mapa);
    mapa.attributionControl.setPrefix('<a href="https://leafletjs.com" target="_blank" rel="noopener">Leaflet</a>');
    mapa.setView(o.centro || [-14.24, -51.93], o.zoom || (o.centro ? 15 : 4));
    // Se a pessoa mexer no mapa, para de reenquadrar sozinho por 2 minutos
    mapa._rzMexeu = 0;
    var mexeu = function () { mapa._rzMexeu = Date.now(); };
    mapa.on('dragstart', mexeu);
    el.addEventListener('wheel', mexeu, { passive: true });
    el.addEventListener('click', function (ev) { if (ev.target.closest && ev.target.closest('.leaflet-control-zoom')) mexeu(); });
    return mapa;
  }

  function iconeMoto(m, comNome) {
    var cls = 'rz-moto' + (m.sinal ? (m.situacao !== 'loja' ? ' ao-vivo' : '') : ' sem-sinal') + (m.rumo == null || !m.kmh ? ' sem-rumo' : '');
    var html = '<div class="' + cls + '" style="--cor:' + esc(m.cor || '#f5c518') + ';--rumo:' + (m.rumo || 0) + 'deg">'
      + '<span class="rz-moto-seta"></span><span class="rz-moto-pin">' + esc(m.iniciais || '•') + '</span>'
      + (comNome ? '<span class="rz-moto-nome">' + esc(m.nome) + '</span>' : '') + '</div>';
    return L.divIcon({ className: '', html: html, iconSize: [44, 44], iconAnchor: [22, 22] });
  }

  function iconePonto(tipo) {
    var s = tipo === 'loja' ? '🏪' : '🏠';
    return L.divIcon({ className: '', html: '<div class="rz-ponto rz-ponto-' + tipo + '"><span aria-hidden="true">' + s + '</span></div>', iconSize: [30, 30], iconAnchor: [4, 30] });
  }

  // Desliza o marcador da posição atual até a nova em ~1 s
  function deslizar(marcador, alvo, ms) {
    var de = marcador.getLatLng(), t0 = null;
    if (reduzir || !de || de.distanceTo(alvo) > 3000) { marcador.setLatLng(alvo); return; }
    ms = ms || 1000;
    if (marcador._rzAnim) cancelAnimationFrame(marcador._rzAnim);
    var passo = function (t) {
      if (!t0) t0 = t;
      var p = Math.min(1, (t - t0) / ms), e = p < .5 ? 2 * p * p : 1 - Math.pow(-2 * p + 2, 2) / 2;
      marcador.setLatLng([de.lat + (alvo[0] - de.lat) * e, de.lng + (alvo[1] - de.lng) * e]);
      if (p < 1) marcador._rzAnim = requestAnimationFrame(passo);
    };
    marcador._rzAnim = requestAnimationFrame(passo);
  }

  function Frota(mapa, o) {
    this.mapa = mapa; this.o = o || {}; this.itens = {}; this.ajustado = false;
  }
  Frota.prototype.atualizar = function (lista) {
    var vistos = {}, self = this;
    (lista || []).forEach(function (m) {
      if (m.lat == null || m.lng == null) return;
      vistos[m.id] = true;
      var it = self.itens[m.id], chave = [m.sinal, m.situacao, m.rumo == null || !m.kmh ? 'x' : Math.round(m.rumo / 10), m.nome, m.cor].join('|');
      if (!it) {
        it = self.itens[m.id] = { marcador: L.marker([m.lat, m.lng], { icon: iconeMoto(m, self.o.nomes !== false), keyboard: false, zIndexOffset: 500 }).addTo(self.mapa), chave: chave };
        if (self.o.aoClicar) it.marcador.on('click', function () { self.o.aoClicar(m.id); });
      } else {
        if (it.chave !== chave) { it.marcador.setIcon(iconeMoto(m, self.o.nomes !== false)); it.chave = chave; }
        deslizar(it.marcador, [m.lat, m.lng]);
      }
      if (self.o.rastro !== false) {
        var pts = (m.rastro || []).concat([[m.lat, m.lng]]);
        if (m.situacao !== 'loja' && pts.length > 1) {
          if (!it.linha) it.linha = L.polyline(pts, { color: m.cor || '#f5c518', weight: 4, opacity: .55, lineCap: 'round', dashArray: '1 8' }).addTo(self.mapa);
          else it.linha.setLatLngs(pts);
        } else if (it.linha) { self.mapa.removeLayer(it.linha); it.linha = null; }
      }
    });
    Object.keys(this.itens).forEach(function (id) {
      if (!vistos[id]) { var it = self.itens[id]; self.mapa.removeLayer(it.marcador); if (it.linha) self.mapa.removeLayer(it.linha); delete self.itens[id]; }
    });
  };
  // Enquadra todos (e os pontos extras: loja, destinos). Respeita quem mexeu no mapa há pouco.
  Frota.prototype.ajustar = function (extras, forcar) {
    if (!forcar && this.mapa._rzMexeu && Date.now() - this.mapa._rzMexeu < 120000) return;
    var pts = [];
    Object.keys(this.itens).forEach(function (id) { var ll = this.itens[id].marcador.getLatLng(); pts.push([ll.lat, ll.lng]); }, this);
    (extras || []).forEach(function (p) { if (p && p.lat != null) pts.push([p.lat, p.lng]); });
    if (!pts.length) return;
    if (pts.length === 1) this.mapa.setView(pts[0], Math.max(this.mapa.getZoom(), 15), { animate: !reduzir });
    else this.mapa.fitBounds(L.latLngBounds(pts).pad(0.25), { maxZoom: 16, animate: !reduzir });
    this.ajustado = true;
  };

  // Marcador fixo (loja / destino) que pode ser movido de lugar
  function pontoFixo(mapa, tipo) {
    var m = null;
    return function (p, titulo) {
      if (!p || p.lat == null) { if (m) { mapa.removeLayer(m); m = null; } return; }
      if (!m) m = L.marker([p.lat, p.lng], { icon: iconePonto(tipo), keyboard: false, title: titulo || '' }).addTo(mapa);
      else m.setLatLng([p.lat, p.lng]);
    };
  }

  function tempo(seg) {
    if (seg == null) return 'sem sinal ainda';
    if (seg < 60) return 'agora';
    if (seg < 3600) return 'há ' + Math.round(seg / 60) + ' min';
    return 'há ' + Math.round(seg / 3600) + ' h';
  }

  window.RZMapa = { criar: criar, Frota: Frota, pontoFixo: pontoFixo, deslizar: deslizar, iconeMoto: iconeMoto, iconePonto: iconePonto, esc: esc, tempo: tempo };
})();
