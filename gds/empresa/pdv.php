<?php
// ============================================================
// PDV — NOVO PEDIDO DE BALCÃO / TELEFONE / MESA (estilo Anota AI / Saipos):
// toca no item do cardápio, escolhe as opções, informa o cliente (busca pelo
// telefone e puxa o último endereço) e lança. O pedido entra no Gestor já
// aceito ("em preparo"), com comanda para imprimir.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/opcoes.php';
require_once __DIR__ . '/../includes/pedidos_fluxo.php';
exigir_login('empresa');
exigir_modulo('delivery');
$empresaId = (int)empresa_id_atual();
$st = $pdo->prepare("SELECT * FROM empresas WHERE id=?"); $st->execute([$empresaId]); $empresa = $st->fetch();

$st = $pdo->prepare("SELECT * FROM servicos WHERE empresa_id=? AND ativo=true ORDER BY COALESCE(NULLIF(categoria,''),'zzz'), destaque DESC, ordem, nome");
$st->execute([$empresaId]);
$catalogo = $st->fetchAll();
$porId = [];
foreach ($catalogo as $s) $porId[(int)$s['id']] = $s;
$bairros = delivery_bairros($empresa);
$formas = array_values(array_filter(array_map('trim', explode(',', (string)($empresa['formas_pagamento'] ?? '') ?: 'Pix, Cartão na entrega, Dinheiro'))));
foreach (['Pix', 'Dinheiro', 'Cartão de crédito', 'Cartão de débito'] as $f) if (!in_array($f, $formas, true)) $formas[] = $f;

// Cliente pelo telefone: nome e último endereço de entrega (atendimento por telefone fica rápido)
if (isset($_GET['cliente'])) {
    $tel = preg_replace('/\D/', '', (string)$_GET['cliente']);
    if (strlen($tel) < 8) responder_json(['ok' => false]);
    $c = $pdo->prepare("SELECT c.id, c.nome, c.telefone,
            (SELECT endereco_entrega FROM pedidos p WHERE p.cliente_id=c.id AND p.endereco_entrega IS NOT NULL ORDER BY p.id DESC LIMIT 1) endereco,
            (SELECT COUNT(*) FROM pedidos p WHERE p.cliente_id=c.id AND p.status <> 'cancelado') pedidos,
            (SELECT descricao FROM pedidos p WHERE p.cliente_id=c.id AND p.status <> 'cancelado' AND p.tipo='delivery' ORDER BY p.id DESC LIMIT 1) ultimo
        FROM clientes c WHERE c.empresa_id=? AND right(regexp_replace(COALESCE(c.telefone,''), '\D', '', 'g'), 11) = right(?, 11) ORDER BY c.id DESC LIMIT 1");
    $c->execute([$empresaId, $tel]);
    $r = $c->fetch();
    responder_json($r ? ['ok' => true] + $r : ['ok' => false]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lancar'])) {
    $itens = []; $subtotal = 0; $erro = '';
    foreach ((array)json_decode((string)($_POST['itens'] ?? ''), true) as $it) {
        $id = (int)($it['id'] ?? 0); $qtd = max(0, min(99, (int)($it['qtd'] ?? 0)));
        if ($qtd < 1 || !isset($porId[$id])) continue;
        $pr = opc_precificar($porId[$id], (array)($it['opcoes'] ?? []));   // preço sempre do banco
        if (!$pr['ok']) { $erro = $pr['erro']; break; }
        $nome = $pr['nome'];
        $obsItem = mb_substr(trim(strip_tags((string)($it['obs'] ?? ''))), 0, 80);
        if ($obsItem !== '') $nome .= ' — ' . $obsItem;
        $itens[] = ['id' => $id, 'nome' => $nome, 'qtd' => $qtd, 'preco' => $pr['preco'], 'opcoes' => array_values((array)($it['opcoes'] ?? []))];
        $subtotal += $pr['preco'] * $qtd;
    }
    $tipo = in_array($_POST['tipo'] ?? '', ['retirada', 'entrega', 'mesa'], true) ? $_POST['tipo'] : 'retirada';
    $nome = mb_substr(trim(strip_tags($_POST['nome'] ?? '')), 0, 150);
    $tel = mb_substr(trim($_POST['telefone'] ?? ''), 0, 20);
    $mesa = $tipo === 'mesa' ? mb_substr(preg_replace('/[^0-9A-Za-z]/', '', (string)($_POST['mesa'] ?? '')), 0, 10) : '';
    $endereco = $tipo === 'entrega' ? mb_substr(trim(strip_tags($_POST['endereco'] ?? '')), 0, 220) : '';
    $taxa = 0.0;
    if ($tipo === 'entrega') {
        $taxa = (float)($empresa['taxa_entrega'] ?? 0);
        foreach ($bairros as $b) if ($b['bairro'] === ($_POST['bairro'] ?? '')) { $taxa = $b['taxa']; if (mb_stripos($endereco, $b['bairro']) === false) $endereco = trim($endereco . ' — ' . $b['bairro']); }
        if (isset($_POST['taxa']) && trim($_POST['taxa']) !== '') $taxa = max(0, valor_decimal($_POST['taxa']));
    }
    $desconto = min($subtotal, max(0, valor_decimal($_POST['desconto'] ?? '0')));
    $total = round($subtotal + $taxa - $desconto, 2);
    $pagamento = in_array($_POST['pagamento'] ?? '', $formas, true) ? $_POST['pagamento'] : 'Dinheiro';
    $troco = valor_decimal($_POST['troco'] ?? '', 0);
    $obs = mb_substr(trim(strip_tags($_POST['obs'] ?? '')), 0, 500);
    if ($erro === '') {
        if (!$itens) $erro = 'Adicione pelo menos um item.';
        elseif ($tipo === 'entrega' && mb_strlen($endereco) < 6) $erro = 'Informe o endereço da entrega.';
        elseif ($tipo === 'mesa' && $mesa === '') $erro = 'Informe o número da mesa.';
        elseif ($troco > 0 && $troco < $total) $erro = 'O troco precisa ser maior que o total.';
    }
    if ($erro !== '') responder_json(['ok' => false, 'erro' => $erro]);

    $clienteId = null;
    $telDig = preg_replace('/\D/', '', $tel);
    if (strlen($telDig) >= 8) {
        $b = $pdo->prepare("SELECT id FROM clientes WHERE empresa_id=? AND right(regexp_replace(COALESCE(telefone,''), '\D', '', 'g'), 11) = right(?, 11) LIMIT 1");
        $b->execute([$empresaId, $telDig]);
        $clienteId = ($r = $b->fetch()) ? (int)$r['id'] : null;
    }
    if (!$clienteId && $nome !== '') {
        $n = $pdo->prepare("INSERT INTO clientes (empresa_id, nome, telefone) VALUES (?,?,?) RETURNING id");
        $n->execute([$empresaId, $nome, $tel ?: null]);
        $clienteId = (int)$n->fetch()['id'];
    }
    $descricao = implode(', ', array_map(fn($i) => $i['qtd'] . 'x ' . $i['nome'], $itens));
    if ($desconto > 0) $obs = trim('Desconto de R$ ' . number_format($desconto, 2, ',', '.') . '. ' . $obs);
    $origem = ['retirada' => 'balcao', 'entrega' => 'telefone', 'mesa' => 'mesa'][$tipo];
    $ins = $pdo->prepare("INSERT INTO pedidos (empresa_id, cliente_id, descricao, valor, status, origem, tipo, itens, tipo_entrega, endereco_entrega, forma_pagamento, troco_para, taxa_entrega, observacoes, token, mesa, pago, aceito_em)
        VALUES (?,?,?,?, 'em_andamento', ?, 'delivery', ?,?,?,?,?,?,?,?,?,?, NOW()) RETURNING id");
    $ins->execute([$empresaId, $clienteId, $descricao, $total, $origem, json_encode($itens, JSON_UNESCAPED_UNICODE), $tipo === 'mesa' ? 'retirada' : $tipo,
        $endereco ?: null, $pagamento, $troco > 0 ? $troco : null, $taxa, $obs ?: null, bin2hex(random_bytes(16)), $mesa ?: null, !empty($_POST['pago']) ? 'true' : 'false']);
    $id = (int)$ins->fetch()['id'];
    // Cliente com WhatsApp recebe a confirmação (mesmo aviso de "em preparo" do site)
    $msg = '';
    if ($telDig !== '' && function_exists('wa_avisar_status_pedido')) { wa_adiar(); $msg = trim(strip_tags(wa_resultado_para_tela(wa_avisar_status_pedido($empresa, $id, 'em_andamento')))); }
    unset($_SESSION['wa_manual']); // balcão/telefone: o cliente já está falando com a loja
    responder_json(['ok' => true, 'id' => $id, 'total' => $total, 'msg' => $msg]);
}

$cats = [];
foreach ($catalogo as $s) $cats[trim((string)$s['categoria']) ?: 'Outros'][] = $s;
$js = array_map(fn($s) => ['id' => (int)$s['id'], 'nome' => $s['nome'], 'preco' => (float)$s['preco'], 'cat' => trim((string)$s['categoria']) ?: 'Outros',
    'img' => $s['imagem'] ?: '', 'esgotado' => pg_bool($s['esgotado'] ?? false), 'opcoes' => opc_ler($s)], $catalogo);
$moeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Novo pedido (PDV) - Meu Negócio') ?>
<style>
.pdv { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 16px; align-items: start; }
@media (max-width: 1000px) { .pdv { grid-template-columns: 1fr; } }
.pdv-busca { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
.pdv input:not([type=checkbox]):not([type=radio]), .pdv select, .pdv textarea { width: 100%; padding: 9px 11px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg); color: var(--text); font: inherit; font-size: 14px; }
.pdv-busca input { flex: 1; min-width: 180px; }
.pdv-tabs { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 10px; }
.pdv-tabs button { white-space: nowrap; }
.pdv-grade { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; }
.pdv-prod { text-align: left; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 8px; color: inherit; cursor: pointer; display: flex; flex-direction: column; gap: 6px; font: inherit; }
.pdv-prod:hover { border-color: var(--primary); } .pdv-prod:disabled { opacity: .45; cursor: not-allowed; }
.pdv-prod .img { height: 74px; border-radius: 8px; background: var(--surface-2) center/cover; display: grid; place-items: center; font-size: 22px; }
.pdv-prod strong { font-size: 13.5px; line-height: 1.25; } .pdv-prod span { color: var(--text-dim); font-size: 13px; }
.pdv-conta { position: sticky; top: 12px; }
.pdv-linha { display: grid; grid-template-columns: 1fr auto; gap: 4px 8px; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 13.5px; }
.pdv-linha .qtd { display: flex; align-items: center; gap: 6px; } .pdv-linha .qtd button { width: 26px; height: 26px; padding: 0; border-radius: 8px; }
.pdv-linha input { grid-column: 1 / -1; font-size: 12.5px !important; padding: 5px 8px !important; }
.pdv-tipo { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; margin: 10px 0; }
.pdv-tipo label { margin: 0; text-align: center; padding: 8px 4px; border: 1px solid var(--border); border-radius: 10px; cursor: pointer; font-size: 13px; }
.pdv-tipo input { display: none; } .pdv-tipo label:has(input:checked) { border-color: var(--primary); background: rgba(91,124,255,.12); }
.pdv-tot { display: flex; justify-content: space-between; font-size: 14px; margin: 4px 0; } .pdv-tot.grande { font-size: 22px; font-weight: 700; }
.pdv-cliente-info { font-size: 12.5px; color: var(--text-dim); margin: 4px 0 0; }
.pdv-modal { position: fixed; inset: 0; background: rgba(0,0,0,.55); display: grid; place-items: center; z-index: 50; padding: 16px; }
.pdv-modal > div { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 18px; width: min(460px, 100%); max-height: 85vh; overflow: auto; }
.pdv-modal h4 { margin: 12px 0 6px; } .pdv-modal label { display: flex; justify-content: space-between; gap: 8px; padding: 6px 0; margin: 0; cursor: pointer; }
</style>
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header">
        <div><h1>Novo pedido</h1><p class="subtitulo">Balcão, telefone ou mesa: toque nos itens, informe o cliente e lance. O pedido cai no Gestor já em preparo.</p></div>
        <div class="acoes"><a class="btn btn-ghost" href="gestor.php">← Gestor de pedidos</a></div>
    </div>
    <div class="pdv">
        <section>
            <div class="pdv-busca"><input type="search" id="busca" placeholder="Buscar item (ex.: x-bacon)" aria-label="Buscar item"></div>
            <div class="pdv-tabs" id="tabs"><button type="button" class="btn btn-sm" data-cat="">Tudo</button><?php foreach (array_keys($cats) as $c): ?><button type="button" class="btn btn-ghost btn-sm" data-cat="<?= e($c) ?>"><?= e($c) ?></button><?php endforeach; ?></div>
            <div class="pdv-grade" id="grade"></div>
            <?php if (!$catalogo): ?><p class="dica">Cadastre os itens em <a class="link" href="servicos.php">Cardápio</a> para lançar pedidos aqui.</p><?php endif; ?>
        </section>
        <aside class="form-box pdv-conta">
            <h3 style="margin-top:0">Pedido</h3>
            <div id="linhas"><p class="dica" id="vazio">Toque nos itens ao lado.</p></div>
            <div class="pdv-tipo">
                <label><input type="radio" name="tipo" value="retirada" checked>🏪 Balcão</label>
                <label><input type="radio" name="tipo" value="entrega">🛵 Entrega</label>
                <label><input type="radio" name="tipo" value="mesa">🍽 Mesa</label>
            </div>
            <label>Telefone / WhatsApp</label><input type="tel" id="telefone" placeholder="(11) 99999-9999" autocomplete="off">
            <p class="pdv-cliente-info" id="clienteInfo"></p>
            <label>Nome do cliente</label><input type="text" id="nome" maxlength="150">
            <div id="blocoEntrega" hidden>
                <?php if ($bairros): ?><label>Bairro</label><select id="bairro"><option value="">— escolha —</option><?php foreach ($bairros as $b): ?><option value="<?= e($b['bairro']) ?>" data-taxa="<?= $b['taxa'] ?>"><?= e($b['bairro']) ?> (<?= $moeda($b['taxa']) ?>)</option><?php endforeach; ?></select><?php endif; ?>
                <label>Endereço</label><input type="text" id="endereco" maxlength="220" placeholder="Rua, número, complemento">
                <label>Taxa de entrega (R$)</label><input type="text" id="taxa" inputmode="decimal" value="<?= number_format((float)($empresa['taxa_entrega'] ?? 0), 2, ',', '') ?>">
            </div>
            <div id="blocoMesa" hidden><label>Mesa</label><input type="text" id="mesa" maxlength="10" placeholder="Ex.: 7"></div>
            <div class="form-row">
                <div><label>Pagamento</label><select id="pagamento"><?php foreach ($formas as $f): ?><option><?= e($f) ?></option><?php endforeach; ?></select></div>
                <div><label>Desconto (R$)</label><input type="text" id="desconto" inputmode="decimal" value="0,00"></div>
            </div>
            <div id="blocoTroco"><label>Troco para (R$, se for dinheiro)</label><input type="text" id="troco" inputmode="decimal" placeholder="Ex.: 100,00"></div>
            <label class="check" style="margin-top:8px"><input type="checkbox" id="pago"> Já está pago</label>
            <label>Observação</label><input type="text" id="obs" maxlength="500" placeholder="Ex.: interfone quebrado, ligar ao chegar">
            <div style="margin-top:12px">
                <div class="pdv-tot"><span>Itens</span><span id="tSub">R$ 0,00</span></div>
                <div class="pdv-tot" id="linhaTaxa" hidden><span>Entrega</span><span id="tTaxa"></span></div>
                <div class="pdv-tot" id="linhaDesc" hidden><span>Desconto</span><span id="tDesc"></span></div>
                <div class="pdv-tot grande"><span>Total</span><span id="tTotal">R$ 0,00</span></div>
            </div>
            <p class="dica" id="erro" style="color:var(--perigo,#f87171)" hidden></p>
            <button type="button" class="btn" id="lancar" style="width:100%;margin-top:6px">Lançar pedido</button>
            <label class="check" style="margin-top:8px"><input type="checkbox" id="imprimir" <?= pg_bool($empresa['gestor_imprimir'] ?? false) ? 'checked' : '' ?>> Imprimir comanda ao lançar</label>
        </aside>
    </div>
</main>
</div>
<div class="pdv-modal" id="modal" hidden><div><h3 id="mNome" style="margin:0"></h3><div id="mGrupos"></div>
    <label style="display:block;margin-top:10px">Observação do item</label><input type="text" id="mObs" maxlength="80" placeholder="Ex.: sem cebola" style="width:100%;padding:9px 11px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text)">
    <p class="dica" id="mErro" hidden style="color:var(--perigo,#f87171)"></p>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px"><button type="button" class="btn btn-ghost" id="mCancelar">Cancelar</button><button type="button" class="btn" id="mOk">Adicionar</button></div></div></div>
<script>
(function () {
    var CAT = <?= json_encode($js, JSON_UNESCAPED_UNICODE) ?>, csrf = <?= json_encode(csrf_token()) ?>;
    var din = function (v) { return 'R$ ' + Number(v || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
    var num = function (s) { s = String(s || '').trim(); if (s.indexOf(',') >= 0) s = s.replace(/\./g, '').replace(',', '.'); return parseFloat(s.replace(/[^0-9.]/g, '')) || 0; };
    var $ = function (id) { return document.getElementById(id); };
    var carrinho = [], catAtual = '';
    function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    function grade() {
        var q = $('busca').value.trim().toLowerCase(), h = '';
        CAT.forEach(function (p, i) {
            if (catAtual && p.cat !== catAtual) return;
            if (q && p.nome.toLowerCase().indexOf(q) < 0) return;
            h += '<button type="button" class="pdv-prod" data-i="' + i + '"' + (p.esgotado ? ' disabled title="Esgotado"' : '') + '><div class="img"' + (p.img ? ' style="background-image:url(\'' + encodeURI(p.img) + '\')"' : '') + '>' + (p.img ? '' : '🍽') + '</div><strong>' + esc(p.nome) + '</strong><span>' + (p.esgotado ? 'Esgotado' : din(p.preco) + (p.opcoes.length ? ' · opções' : '')) + '</span></button>';
        });
        $('grade').innerHTML = h || '<p class="dica">Nenhum item encontrado.</p>';
    }
    // Preço no navegador só para mostrar; o servidor recalcula tudo
    function precoCom(p, esc) {
        var extra = 0, base = p.preco;
        p.opcoes.forEach(function (g, gi) {
            var sel = esc.filter(function (e) { return e[0] === gi; }).map(function (e) { return g.itens[e[1]].preco; });
            if (!sel.length) return;
            if (g.regra === 'maior') base = Math.max(base, Math.max.apply(null, sel));
            else extra += sel.reduce(function (a, b) { return a + b; }, 0);
        });
        return base + extra;
    }
    function nomeCom(p, esc) {
        var partes = [];
        p.opcoes.forEach(function (g, gi) { var n = esc.filter(function (e) { return e[0] === gi; }).map(function (e) { return g.itens[e[1]].nome; }); if (n.length) partes.push(n.join(', ')); });
        return p.nome + (partes.length ? ' (' + partes.join('; ') + ')' : '');
    }
    var emEdicao = null;
    function abrirOpcoes(i) {
        var p = CAT[i]; emEdicao = i; $('mNome').textContent = p.nome; $('mObs').value = ''; $('mErro').hidden = true;
        var h = '';
        p.opcoes.forEach(function (g, gi) {
            var multi = g.max > 1;
            h += '<h4>' + esc(g.nome) + (g.min ? ' <small class="muted">(obrigatório)</small>' : '') + (multi ? ' <small class="muted">até ' + g.max + '</small>' : '') + '</h4>';
            g.itens.forEach(function (it, ii) { h += '<label><span><input type="' + (multi ? 'checkbox' : 'radio') + '" name="g' + gi + '" value="' + ii + '" data-g="' + gi + '" data-max="' + g.max + '"> ' + esc(it.nome) + '</span><span class="muted">' + (it.preco ? (g.regra === 'maior' ? din(it.preco) : '+' + din(it.preco)) : '') + '</span></label>'; });
        });
        $('mGrupos').innerHTML = h; $('modal').hidden = false;
    }
    function adicionar(i, escolhas, obs) {
        var p = CAT[i], chave = p.id + '|' + JSON.stringify(escolhas) + '|' + obs;
        var ja = carrinho.find(function (l) { return l.chave === chave; });
        if (ja) ja.qtd++; else carrinho.push({ chave: chave, id: p.id, opcoes: escolhas, obs: obs, qtd: 1, nome: nomeCom(p, escolhas), preco: precoCom(p, escolhas) });
        render();
    }
    $('grade').addEventListener('click', function (e) {
        var b = e.target.closest('.pdv-prod'); if (!b) return;
        var i = +b.dataset.i; if (CAT[i].opcoes.length) abrirOpcoes(i); else adicionar(i, [], '');
    });
    $('mGrupos').addEventListener('change', function (e) {
        var t = e.target; if (t.type !== 'checkbox') return;
        var marcados = $('mGrupos').querySelectorAll('input[data-g="' + t.dataset.g + '"]:checked');
        if (marcados.length > +t.dataset.max) t.checked = false;
    });
    $('mCancelar').onclick = function () { $('modal').hidden = true; };
    $('mOk').onclick = function () {
        var p = CAT[emEdicao], esc = [], falta = '';
        $('mGrupos').querySelectorAll('input:checked').forEach(function (x) { esc.push([+x.dataset.g, +x.value]); });
        p.opcoes.forEach(function (g, gi) { if (g.min && esc.filter(function (e) { return e[0] === gi; }).length < g.min) falta = falta || g.nome; });
        if (falta) { $('mErro').textContent = 'Escolha: ' + falta; $('mErro').hidden = false; return; }
        adicionar(emEdicao, esc, $('mObs').value.trim()); $('modal').hidden = true;
    };
    function tipo() { return document.querySelector('input[name=tipo]:checked').value; }
    function render() {
        var h = '', sub = 0;
        carrinho.forEach(function (l, k) {
            sub += l.preco * l.qtd;
            h += '<div class="pdv-linha"><span><b>' + esc(l.nome) + '</b>' + (l.obs ? '<br><small class="muted">' + esc(l.obs) + '</small>' : '') + '</span><span class="qtd"><button type="button" class="btn btn-ghost" data-menos="' + k + '" aria-label="Menos">−</button>' + l.qtd + '<button type="button" class="btn btn-ghost" data-mais="' + k + '" aria-label="Mais">+</button> <b>' + din(l.preco * l.qtd) + '</b></span></div>';
        });
        $('linhas').innerHTML = h || '<p class="dica">Toque nos itens ao lado.</p>';
        var t = tipo(), taxa = t === 'entrega' ? num($('taxa').value) : 0, desc = Math.min(sub, num($('desconto').value));
        $('tSub').textContent = din(sub);
        $('linhaTaxa').hidden = !taxa; $('tTaxa').textContent = din(taxa);
        $('linhaDesc').hidden = !desc; $('tDesc').textContent = '−' + din(desc);
        $('tTotal').textContent = din(sub + taxa - desc);
        $('blocoEntrega').hidden = t !== 'entrega'; $('blocoMesa').hidden = t !== 'mesa';
        $('blocoTroco').hidden = !/dinheiro/i.test($('pagamento').value);
    }
    $('linhas').addEventListener('click', function (e) {
        var m = e.target.dataset.mais, n = e.target.dataset.menos;
        if (m !== undefined) carrinho[m].qtd++;
        if (n !== undefined) { carrinho[n].qtd--; if (carrinho[n].qtd < 1) carrinho.splice(n, 1); }
        render();
    });
    document.querySelectorAll('input[name=tipo]').forEach(function (r) { r.onchange = render; });
    ['taxa', 'desconto', 'pagamento'].forEach(function (id) { $(id).addEventListener('input', render); $(id).addEventListener('change', render); });
    if ($('bairro')) $('bairro').onchange = function () { var o = $('bairro').selectedOptions[0]; if (o && o.dataset.taxa) $('taxa').value = Number(o.dataset.taxa).toFixed(2).replace('.', ','); render(); };
    $('busca').oninput = grade;
    $('tabs').onclick = function (e) {
        var b = e.target.closest('[data-cat]'); if (!b) return; catAtual = b.dataset.cat;
        $('tabs').querySelectorAll('button').forEach(function (x) { x.classList.toggle('btn-ghost', x !== b); }); grade();
    };
    // Telefone conhecido → puxa nome e último endereço
    var tBusca;
    $('telefone').addEventListener('input', function () {
        clearTimeout(tBusca);
        tBusca = setTimeout(function () {
            var d = $('telefone').value.replace(/\D/g, ''); if (d.length < 10) { $('clienteInfo').textContent = ''; return; }
            fetch('pdv.php?cliente=' + d).then(function (r) { return r.json(); }).then(function (c) {
                if (!c.ok) { $('clienteInfo').textContent = 'Cliente novo — será cadastrado.'; return; }
                if (!$('nome').value) $('nome').value = c.nome;
                if (c.endereco && !$('endereco').value) $('endereco').value = c.endereco;
                $('clienteInfo').textContent = '✓ ' + c.nome + ' · ' + c.pedidos + ' pedido(s)' + (c.ultimo ? ' · último: ' + c.ultimo : '');
            });
        }, 350);
    });
    $('lancar').onclick = function () {
        var f = new FormData(), b = $('lancar');
        f.append('csrf_token', csrf); f.append('lancar', '1');
        f.append('itens', JSON.stringify(carrinho.map(function (l) { return { id: l.id, qtd: l.qtd, opcoes: l.opcoes, obs: l.obs }; })));
        f.append('tipo', tipo());
        ['nome', 'telefone', 'endereco', 'mesa', 'taxa', 'pagamento', 'desconto', 'troco', 'obs'].forEach(function (k) { f.append(k, $(k).value); });
        if ($('bairro')) f.append('bairro', $('bairro').value);
        if ($('pago').checked) f.append('pago', '1');
        b.disabled = true; b.textContent = 'Lançando…'; $('erro').hidden = true;
        fetch('pdv.php', { method: 'POST', body: f, headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); }).then(function (r) {
            b.disabled = false; b.textContent = 'Lançar pedido';
            if (!r.ok) { $('erro').textContent = r.erro || 'Não foi possível lançar.'; $('erro').hidden = false; return; }
            if ($('imprimir').checked) window.open('pedido_imprimir.php?id=' + r.id, '_blank', 'width=420,height=640');
            location.href = 'gestor.php?lancado=' + r.id;
        }).catch(function () { b.disabled = false; b.textContent = 'Lançar pedido'; $('erro').textContent = 'Sem conexão. Tente de novo.'; $('erro').hidden = false; });
    };
    grade(); render();
})();
</script>
</body>
</html>
