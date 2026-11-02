<?php
// ============================================================
// GESTOR DE PEDIDOS (delivery) — no estilo dos gestores do iFood/Anota AI:
// quadro em colunas que se atualiza sozinho, cronômetro em cada pedido, som de
// pedido novo, aceitar/avançar com um toque e comanda impressa ao aceitar.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedidos_fluxo.php';
exigir_login('empresa');
exigir_modulo('delivery');
$empresaId = (int)empresa_id_atual();
$carregar = function () use ($pdo, $empresaId) { $s = $pdo->prepare("SELECT * FROM empresas WHERE id=?"); $s->execute([$empresaId]); return $s->fetch(); };
$empresa = $carregar();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    switch ($_POST['acao']) {
        case 'status':
            $r = pedido_mudar_status($empresa, (int)($_POST['id'] ?? 0), (string)($_POST['status'] ?? ''), (string)($_POST['motivo'] ?? ''));
            responder_json($r['ok'] ? ['ok' => true, 'msg' => trim(strip_tags($r['msg']))] : $r);
        case 'loja':
            $pausar = ($_POST['pausada'] ?? '') === '1';
            $pdo->prepare("UPDATE empresas SET loja_pausada=? WHERE id=?")->execute([$pausar ? 'true' : 'false', $empresaId]);
            responder_json(['ok' => true, 'pausada' => $pausar]);
        case 'imprimir_auto':
            try { $pdo->prepare("UPDATE empresas SET gestor_imprimir=? WHERE id=?")->execute([($_POST['ligado'] ?? '') === '1' ? 'true' : 'false', $empresaId]); } catch (PDOException $e) {}
            responder_json(['ok' => true]);
    }
    responder_json(['ok' => false, 'erro' => 'Ação inválida.'], 400);
}

// Dados do quadro (a tela pergunta a cada poucos segundos)
if (isset($_GET['dados'])) {
    $st = $pdo->prepare("SELECT p.*, c.nome AS cliente_nome, c.telefone AS cliente_telefone,
            EXTRACT(EPOCH FROM (LOCALTIMESTAMP - p.criado_em))::bigint AS idade_seg,
            (SELECT COUNT(*) FROM pedidos p2 WHERE p2.cliente_id = p.cliente_id AND p2.status <> 'cancelado' AND p2.id < p.id) AS pedidos_antes
        FROM pedidos p LEFT JOIN clientes c ON c.id = p.cliente_id
        WHERE p.empresa_id=? AND COALESCE(p.tipo,'') = 'delivery'
          AND (p.status IN ('novo','em_andamento','pronto','saiu_entrega') OR (p.status IN ('concluido','cancelado') AND p.atualizado_em::date = CURRENT_DATE))
        ORDER BY p.criado_em ASC LIMIT 200");
    $st->execute([$empresaId]);
    $pedidos = [];
    foreach ($st->fetchAll() as $p) {
        $prox = pedido_proxima_etapa($p);
        // Idade calculada no banco (mesmo fuso do criado_em) → horário de chegada no relógio do servidor
        $pedidos[] = ['id' => (int)$p['id'], 'status' => $p['status'], 'ts' => time() - max(0, (int)$p['idade_seg']), 'cliente' => $p['cliente_nome'] ?: 'Cliente',
            'telefone' => preg_replace('/\D/', '', (string)$p['cliente_telefone']), 'novo_cliente' => (int)$p['pedidos_antes'] === 0,
            'itens' => array_map(fn($i) => ['qtd' => (int)$i['qtd'], 'nome' => (string)$i['nome'], 'preco' => (float)$i['preco']], json_decode((string)$p['itens'], true) ?: []),
            'total' => (float)$p['valor'], 'taxa' => (float)$p['taxa_entrega'], 'entrega' => $p['mesa'] ? 'mesa' : ($p['tipo_entrega'] ?: 'entrega'), 'mesa' => $p['mesa'],
            'endereco' => $p['endereco_entrega'], 'pagamento' => $p['forma_pagamento'], 'troco' => $p['troco_para'] !== null ? (float)$p['troco_para'] : null,
            'pago' => pg_bool($p['pago'] ?? false), 'obs' => $p['observacoes'], 'agendado' => $p['agendado_para'],
            'proximo' => $prox ? ['status' => $prox[0], 'rotulo' => $prox[1]] : null];
    }
    $k = ['pedidos' => 0, 'faturamento' => 0, 'ticket' => 0, 'preparo' => null, 'total_min' => null];
    try {
        $r = $pdo->prepare("SELECT COUNT(*) FILTER (WHERE status <> 'cancelado') n, COALESCE(SUM(valor) FILTER (WHERE status <> 'cancelado'), 0) v,
                AVG(EXTRACT(EPOCH FROM (COALESCE(pronto_em, saiu_em) - aceito_em)) / 60) FILTER (WHERE aceito_em IS NOT NULL AND COALESCE(pronto_em, saiu_em) IS NOT NULL) preparo,
                AVG(EXTRACT(EPOCH FROM (concluido_em - criado_em)) / 60) FILTER (WHERE concluido_em IS NOT NULL) total_min
            FROM pedidos WHERE empresa_id=? AND tipo='delivery' AND criado_em::date = CURRENT_DATE");
        $r->execute([$empresaId]); $r = $r->fetch();
        $k = ['pedidos' => (int)$r['n'], 'faturamento' => (float)$r['v'], 'ticket' => $r['n'] ? (float)$r['v'] / (int)$r['n'] : 0,
              'preparo' => $r['preparo'] !== null ? (int)round($r['preparo']) : null, 'total_min' => $r['total_min'] !== null ? (int)round($r['total_min']) : null];
    } catch (PDOException $e) {}
    $empresa = $carregar();
    responder_json(['ok' => true, 'agora' => time(), 'pedidos' => $pedidos, 'kpis' => $k, 'pausada' => pg_bool($empresa['loja_pausada'] ?? false)]);
}
$pausada = pg_bool($empresa['loja_pausada'] ?? false);
$imprimirAuto = pg_bool($empresa['gestor_imprimir'] ?? false);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Gestor de pedidos - Meu Negócio') ?>
<style>
.gst-topo { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.gst-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 18px; }
.gst-kpis div { background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; }
.gst-kpis small { color: var(--text-dim); display: block; font-size: 12px; }
.gst-kpis strong { font-size: 22px; }
.gst-ctrl { display: flex; gap: 8px; flex-wrap: wrap; }
.gst-quadro { display: grid; grid-template-columns: repeat(4, minmax(250px, 1fr)); gap: 12px; align-items: start; overflow-x: auto; padding-bottom: 8px; }
.gst-col { background: var(--surface-2); border: 1px solid var(--border); border-radius: 14px; padding: 10px; min-height: 240px; }
.gst-col h3 { display: flex; justify-content: space-between; align-items: center; font-size: 14px; margin: 2px 4px 10px; }
.gst-col h3 b { background: var(--surface-3, rgba(255,255,255,.08)); border-radius: 999px; padding: 2px 10px; font-size: 12px; }
.gst-col[data-col="novo"] { border-color: rgba(255,170,60,.5); }
.gst-col[data-col="novo"] h3 { color: #ffb347; }
.gst-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 12px; margin-bottom: 10px; font-size: 13.5px; }
.gst-card.alerta { border-color: rgba(255,90,90,.7); box-shadow: 0 0 0 2px rgba(255,90,90,.15); }
.gst-card.novo-chegou { animation: chegou 1.2s ease 3; }
@keyframes chegou { 50% { box-shadow: 0 0 0 4px rgba(255,170,60,.45); } }
.gst-cab { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 6px; }
.gst-cab strong { font-size: 15px; }
.gst-tempo { white-space: nowrap; flex: none; font-variant-numeric: tabular-nums; font-weight: 700; padding: 2px 8px; border-radius: 999px; font-size: 12px; background: rgba(61,220,132,.14); color: #5be09a; }
.gst-tempo.amarelo { background: rgba(245,194,107,.16); color: #f5c26b; }
.gst-tempo.vermelho { background: rgba(255,90,90,.16); color: #ff8a8a; }
.gst-itens { list-style: none; padding: 0; margin: 6px 0; display: grid; gap: 2px; }
.gst-itens b { margin-right: 4px; }
.gst-info { color: var(--text-dim); font-size: 12.5px; display: grid; gap: 2px; }
.gst-tags { display: flex; flex-wrap: wrap; gap: 4px; margin: 6px 0; }
.gst-tags span { font-size: 11px; padding: 2px 8px; border-radius: 999px; border: 1px solid var(--border); }
.gst-tags .t-pago { color: #5be09a; border-color: rgba(61,220,132,.4); }
.gst-tags .t-novo { color: #ffb347; border-color: rgba(255,170,60,.4); }
.gst-acoes { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
.gst-acoes .btn { margin: 0; flex: 1 1 auto; }
.gst-vazio { color: var(--text-dim); font-size: 13px; text-align: center; padding: 24px 8px; }
@media (max-width: 1100px) { .gst-quadro { grid-template-columns: repeat(4, 280px); } }
</style></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="gst-topo">
        <div><h1 style="margin:0">Gestor de pedidos</h1><p class="subtitulo" style="margin:4px 0 0">Atualiza sozinho. Deixe esta tela aberta no balcão ou na cozinha.</p></div>
        <div class="gst-ctrl">
            <button type="button" class="btn btn-sm <?= $pausada ? 'btn-ghost' : '' ?>" id="btnLoja" onclick="GST.loja()"><?= $pausada ? '⏸ Loja pausada — abrir' : '🟢 Loja aberta — pausar' ?></button>
            <button type="button" class="btn btn-ghost btn-sm" id="btnSom" onclick="GST.som()">🔕 Ativar som</button>
            <label class="check" style="margin:0;align-self:center"><input type="checkbox" id="impAuto" <?= $imprimirAuto ? 'checked' : '' ?> onchange="GST.imprimirAuto(this)"> Imprimir comanda ao aceitar</label>
            <a class="btn btn-ghost btn-sm" href="pedidos.php">Histórico</a>
        </div>
    </div>
    <div class="gst-kpis">
        <div><small>Pedidos hoje</small><strong id="kPedidos">—</strong></div>
        <div><small>Faturamento hoje</small><strong id="kFat">—</strong></div>
        <div><small>Ticket médio</small><strong id="kTicket">—</strong></div>
        <div><small>Tempo médio de preparo</small><strong id="kPreparo">—</strong></div>
        <div><small>Do pedido à entrega</small><strong id="kTotal">—</strong></div>
    </div>
    <div class="gst-quadro" id="quadro">
        <section class="gst-col" data-col="novo"><h3>🔔 Novos <b data-n>0</b></h3><div data-lista></div></section>
        <section class="gst-col" data-col="preparo"><h3>👨‍🍳 Em preparo <b data-n>0</b></h3><div data-lista></div></section>
        <section class="gst-col" data-col="rota"><h3>🛵 Prontos / a caminho <b data-n>0</b></h3><div data-lista></div></section>
        <section class="gst-col" data-col="fim"><h3>✅ Concluídos hoje <b data-n>0</b></h3><div data-lista></div></section>
    </div>
</main>
</div>
<div id="toasts"></div>
<script>
const GST = (() => {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (id) => document.getElementById(id);
    const din = (v) => 'R$ ' + Number(v || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    let pedidos = [], conhecidos = null, difRelogio = 0, ctx = null, pausada = <?= $pausada ? 'true' : 'false' ?>;
    const somLigado = () => { try { return localStorage.getItem('gds-gestor-som') === '1'; } catch (e) { return false; } };
    function toast(msg, tipo) { const t = document.createElement('div'); t.className = 'toast toast-' + (tipo || 'info'); t.textContent = msg; $('toasts').appendChild(t); setTimeout(() => t.remove(), 5000); }
    async function post(d) {
        const fd = new FormData(); fd.append('csrf_token', CSRF); Object.entries(d).forEach(([k, v]) => fd.append(k, v));
        try { return await (await fetch('gestor.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })).json(); }
        catch (e) { return { ok: false, erro: 'Sem conexão.' }; }
    }
    function bip() {
        if (!somLigado()) return;
        try {
            ctx = ctx || new (window.AudioContext || window.webkitAudioContext)();
            [0, .22, .44, .9, 1.12, 1.34].forEach(t => { const o = ctx.createOscillator(), g = ctx.createGain(); o.frequency.value = t < .8 ? 880 : 1175; o.connect(g); g.connect(ctx.destination);
                g.gain.setValueAtTime(.0001, ctx.currentTime + t); g.gain.exponentialRampToValueAtTime(.35, ctx.currentTime + t + .02); g.gain.exponentialRampToValueAtTime(.0001, ctx.currentTime + t + .18);
                o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + .2); });
        } catch (e) {}
    }
    const coluna = (p) => p.status === 'novo' ? 'novo' : p.status === 'em_andamento' ? 'preparo' : (p.status === 'pronto' || p.status === 'saiu_entrega') ? 'rota' : 'fim';
    function minutos(p) { return Math.max(0, Math.floor((Date.now() / 1000 - difRelogio - p.ts) / 60)); }
    function el(tag, cls, txt) { const e = document.createElement(tag); if (cls) e.className = cls; if (txt !== undefined) e.textContent = txt; return e; }
    function cartao(p, novoAgora) {
        const c = el('article', 'gst-card' + (novoAgora ? ' novo-chegou' : '')); c.dataset.id = p.id;
        const cab = el('div', 'gst-cab'); cab.append(el('strong', '', 'nº ' + p.id + ' · ' + p.cliente));
        if (coluna(p) !== 'fim') { const t = el('span', 'gst-tempo'); t.dataset.tempo = p.id; cab.append(t); }
        c.append(cab);
        const tags = el('div', 'gst-tags');
        tags.append(el('span', '', p.entrega === 'mesa' ? '🍽 Mesa ' + p.mesa : p.entrega === 'retirada' ? '🏪 Retirada' : '🛵 Entrega'));
        if (p.pago) tags.append(el('span', 't-pago', '💰 Pago no Pix'));
        if (p.novo_cliente) tags.append(el('span', 't-novo', '⭐ Cliente novo'));
        if (p.agendado) tags.append(el('span', '', '🕒 ' + p.agendado));
        if (p.status === 'cancelado') tags.append(el('span', '', '❌ Cancelado'));
        c.append(tags);
        const ul = el('ul', 'gst-itens');
        p.itens.forEach(i => { const li = el('li'); li.append(el('b', '', i.qtd + '×'), document.createTextNode(i.nome)); ul.append(li); });
        c.append(ul);
        const info = el('div', 'gst-info');
        if (p.entrega === 'entrega' && p.endereco) info.append(el('span', '', '📍 ' + p.endereco));
        info.append(el('span', '', '💳 ' + (p.pagamento || '—') + (p.troco ? ' · troco p/ ' + din(p.troco) : '') + ' · ' + din(p.total)));
        if (p.obs) info.append(el('span', '', '📝 ' + p.obs));
        c.append(info);
        const ac = el('div', 'gst-acoes');
        if (p.proximo) { const b = el('button', 'btn btn-sm', p.proximo.rotulo); b.type = 'button'; b.onclick = () => avancar(p, p.proximo.status, b); ac.append(b); }
        if (p.status === 'novo') { const r = el('button', 'btn btn-ghost btn-sm', 'Recusar'); r.type = 'button'; r.onclick = () => recusar(p, r); ac.append(r); }
        if (coluna(p) !== 'fim') {
            const imp = el('a', 'btn btn-ghost btn-sm', '🖨'); imp.href = 'pedido_imprimir.php?id=' + p.id; imp.target = '_blank'; imp.title = 'Imprimir comanda'; ac.append(imp);
            if (p.telefone) { const w = el('a', 'btn btn-ghost btn-sm', '💬'); w.href = 'https://wa.me/' + (p.telefone.length <= 11 ? '55' : '') + p.telefone; w.target = '_blank'; w.rel = 'noopener'; w.title = 'Falar com o cliente'; ac.append(w); }
        }
        if (ac.childElementCount) c.append(ac);
        return c;
    }
    function render(novos) {
        const grupos = { novo: [], preparo: [], rota: [], fim: [] };
        pedidos.forEach(p => grupos[coluna(p)].push(p));
        grupos.fim.reverse();
        document.querySelectorAll('.gst-col').forEach(col => {
            const lista = col.querySelector('[data-lista]'), itens = grupos[col.dataset.col];
            lista.innerHTML = ''; col.querySelector('[data-n]').textContent = itens.length;
            if (!itens.length) lista.append(el('p', 'gst-vazio', col.dataset.col === 'novo' ? 'Nenhum pedido novo. Assim que chegar, toca o aviso.' : '—'));
            itens.slice(0, col.dataset.col === 'fim' ? 15 : 100).forEach(p => lista.append(cartao(p, novos.has(p.id))));
        });
        relogio();
    }
    function relogio() {
        document.querySelectorAll('[data-tempo]').forEach(t => {
            const p = pedidos.find(x => x.id == t.dataset.tempo); if (!p) return;
            const m = minutos(p); t.textContent = m + ' min';
            t.className = 'gst-tempo' + (m >= 40 ? ' vermelho' : m >= 20 ? ' amarelo' : '');
            t.closest('.gst-card').classList.toggle('alerta', p.status === 'novo' && m >= 5);
        });
    }
    async function atualizar() {
        try {
            const r = await (await fetch('gestor.php?dados=1', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })).json();
            if (!r.ok) return;
            difRelogio = Date.now() / 1000 - r.agora;
            const ids = new Set(r.pedidos.filter(p => p.status === 'novo').map(p => p.id));
            const novos = new Set(conhecidos ? [...ids].filter(i => !conhecidos.has(i)) : []);
            if (novos.size) { bip(); toast(novos.size + ' pedido(s) novo(s)!', 'sucesso'); document.title = '🔔 Pedido novo! · Gestor'; setTimeout(() => document.title = 'Gestor de pedidos - Meu Negócio', 8000); }
            conhecidos = new Set([...(conhecidos || []), ...ids]);
            pedidos = r.pedidos; render(novos);
            const k = r.kpis;
            $('kPedidos').textContent = k.pedidos; $('kFat').textContent = din(k.faturamento); $('kTicket').textContent = din(k.ticket);
            $('kPreparo').textContent = k.preparo === null ? '—' : k.preparo + ' min'; $('kTotal').textContent = k.total_min === null ? '—' : k.total_min + ' min';
            if (r.pausada !== pausada) { pausada = r.pausada; marcarLoja(); }
        } catch (e) {}
    }
    async function avancar(p, status, b) {
        b.disabled = true;
        const r = await post({ acao: 'status', id: p.id, status });
        if (!r.ok) { toast(r.erro, 'erro'); b.disabled = false; return; }
        if (status === 'em_andamento' && $('impAuto').checked) window.open('pedido_imprimir.php?id=' + p.id, '_blank');
        toast('Pedido nº ' + p.id + ' atualizado.' + (r.msg ? ' ' + r.msg : ''), 'sucesso');
        atualizar();
    }
    async function recusar(p, b) {
        const motivo = prompt('Motivo da recusa (vai na mensagem para o cliente):', 'Não conseguimos atender agora.');
        if (motivo === null) return;
        b.disabled = true;
        const r = await post({ acao: 'status', id: p.id, status: 'cancelado', motivo });
        if (!r.ok) { toast(r.erro, 'erro'); b.disabled = false; return; }
        toast('Pedido nº ' + p.id + ' recusado.', 'sucesso'); atualizar();
    }
    function marcarLoja() { const b = $('btnLoja'); b.textContent = pausada ? '⏸ Loja pausada — abrir' : '🟢 Loja aberta — pausar'; b.classList.toggle('btn-ghost', pausada); }
    async function loja() { const r = await post({ acao: 'loja', pausada: pausada ? '0' : '1' }); if (r.ok) { pausada = r.pausada; marcarLoja(); toast(pausada ? 'Loja pausada: o site parou de receber pedidos.' : 'Loja aberta: o site voltou a receber pedidos.', 'sucesso'); } }
    function marcarSom() { $('btnSom').textContent = somLigado() ? '🔔 Som ligado' : '🔕 Ativar som'; $('btnSom').classList.toggle('btn-ghost', !somLigado()); }
    function som() { try { localStorage.setItem('gds-gestor-som', somLigado() ? '0' : '1'); } catch (e) {} marcarSom(); if (somLigado()) bip(); }
    async function imprimirAuto(c) { await post({ acao: 'imprimir_auto', ligado: c.checked ? '1' : '0' }); }
    marcarSom(); atualizar(); setInterval(atualizar, 8000); setInterval(relogio, 15000);
    return { loja, som, imprimirAuto };
})();
</script>
</body>
</html>
