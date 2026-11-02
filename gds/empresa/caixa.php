<?php
// ============================================================
// CAIXA E COMANDAS (agenda: barbearia, salão, estética, clínica...) — no estilo
// AppBarber/Trinks: fecha o atendimento somando produtos e serviços extras,
// desconto e forma de pagamento; comanda avulsa (cliente que chegou sem marcar);
// caixa do dia por forma de pagamento e por profissional, com a comissão.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
exigir_modulo('agenda');
$empresaId = (int)empresa_id_atual();
$st = $pdo->prepare("SELECT * FROM empresas WHERE id=?"); $st->execute([$empresaId]); $empresa = $st->fetch();
const CAIXA_FORMAS = ['Pix', 'Dinheiro', 'Cartão de crédito', 'Cartão de débito', 'Clube/pacote'];
$moeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
$dia = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dia'] ?? '') ? $_GET['dia'] : date('Y-m-d');

$catalogo = $pdo->prepare("SELECT id, nome, preco, COALESCE(categoria,'') categoria FROM servicos WHERE empresa_id=? AND ativo=true ORDER BY categoria NULLS FIRST, nome");
$catalogo->execute([$empresaId]); $catalogo = $catalogo->fetchAll();
$porId = []; foreach ($catalogo as $c) $porId[(int)$c['id']] = $c;
$profissionais = $pdo->prepare("SELECT id, nome, COALESCE(comissao,0) comissao FROM profissionais WHERE empresa_id=? AND ativo=true ORDER BY nome");
$profissionais->execute([$empresaId]); $profissionais = $profissionais->fetchAll();

// ---------- fechar comanda ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fechar_comanda'])) {
    $agId = (int)($_POST['agendamento_id'] ?? 0) ?: null;
    $ag = null;
    if ($agId) {
        $s = $pdo->prepare("SELECT * FROM agendamentos WHERE id=? AND empresa_id=?"); $s->execute([$agId, $empresaId]); $ag = $s->fetch();
        if (!$ag) { flash('erro', 'Atendimento não encontrado.'); header('Location: caixa.php'); exit; }
    }
    // Itens: só id e quantidade vêm da tela; nome e preço saem do cadastro (avulso tem nome e valor digitados)
    $itens = [];
    foreach ((array)($_POST['item_id'] ?? []) as $i => $sid) {
        $sid = (int)$sid; $qtd = max(1, min(99, (int)($_POST['item_qtd'][$i] ?? 1)));
        if (isset($porId[$sid])) $itens[] = ['nome' => $porId[$sid]['nome'], 'qtd' => $qtd, 'preco' => (float)$porId[$sid]['preco'], 'tipo' => 'catalogo', 'servico_id' => $sid];
    }
    foreach ((array)($_POST['avulso_nome'] ?? []) as $i => $nome) {
        $nome = mb_substr(trim(strip_tags((string)$nome)), 0, 80); $val = valor_decimal($_POST['avulso_valor'][$i] ?? '0');
        if ($nome !== '' && $val > 0) $itens[] = ['nome' => $nome, 'qtd' => 1, 'preco' => round($val, 2), 'tipo' => 'avulso'];
    }
    // O serviço do atendimento entra sozinho (pelo valor combinado; zero se saiu do clube/pacote)
    if ($ag && !empty($_POST['incluir_servico']) && $ag['servico_id'] && isset($porId[(int)$ag['servico_id']])) {
        $valorAg = $ag['assinatura_id'] ? 0 : (float)($ag['valor'] ?? $porId[(int)$ag['servico_id']]['preco']);
        array_unshift($itens, ['nome' => $porId[(int)$ag['servico_id']]['nome'] . ($ag['assinatura_id'] ? ' (clube/pacote)' : ''), 'qtd' => 1, 'preco' => $valorAg, 'tipo' => 'atendimento', 'servico_id' => (int)$ag['servico_id']]);
    }
    if (!$itens) { flash('erro', 'Adicione pelo menos um item na comanda.'); header('Location: caixa.php' . ($agId ? '?agendamento=' . $agId : '')); exit; }
    $subtotal = round(array_sum(array_map(fn($i) => $i['preco'] * $i['qtd'], $itens)), 2);
    $desconto = min($subtotal, max(0, valor_decimal($_POST['desconto'] ?? '0')));
    $forma = in_array($_POST['forma_pagamento'] ?? '', CAIXA_FORMAS, true) ? $_POST['forma_pagamento'] : 'Pix';
    $clienteId = $ag['cliente_id'] ?? null;
    if (!$clienteId && ($cid = (int)($_POST['cliente_id'] ?? 0))) { $c = $pdo->prepare("SELECT 1 FROM clientes WHERE id=? AND empresa_id=?"); $c->execute([$cid, $empresaId]); if ($c->fetch()) $clienteId = $cid; }
    if (!$clienteId && trim($_POST['cliente_nome'] ?? '') !== '') {
        $n = $pdo->prepare("INSERT INTO clientes (empresa_id, nome, telefone) VALUES (?,?,?) RETURNING id");
        $n->execute([$empresaId, mb_substr(trim($_POST['cliente_nome']), 0, 150), trim($_POST['cliente_telefone'] ?? '') ?: null]);
        $clienteId = (int)$n->fetchColumn();
    }
    $profId = $ag['profissional_id'] ?? null;
    if (!$profId && ($pid = (int)($_POST['profissional_id'] ?? 0))) foreach ($profissionais as $p) if ((int)$p['id'] === $pid) $profId = $pid;
    try {
        $pdo->prepare("INSERT INTO comandas (empresa_id, agendamento_id, cliente_id, profissional_id, itens, subtotal, desconto, total, forma_pagamento, observacao) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$empresaId, $agId, $clienteId, $profId, json_encode($itens, JSON_UNESCAPED_UNICODE), $subtotal, $desconto, round($subtotal - $desconto, 2), $forma,
                mb_substr(trim($_POST['observacao'] ?? ''), 0, 300) ?: null]);
    } catch (PDOException $e) {
        flash('erro', str_contains($e->getMessage(), 'idx_comandas_ag') ? 'Este atendimento já tem comanda fechada.' : 'Não foi possível salvar a comanda.');
        header('Location: caixa.php'); exit;
    }
    // Fechou a comanda = atendimento concluído (o cliente recebe o pedido de avaliação no WhatsApp)
    $aviso = '';
    if ($ag && $ag['status'] !== 'concluido') {
        $pdo->prepare("UPDATE agendamentos SET status='concluido' WHERE id=?")->execute([$agId]);
        wa_adiar();
        $aviso = wa_resultado_para_tela(wa_avisar_status_agendamento($empresa, $agId, 'concluido'));
    }
    flash('sucesso', 'Comanda fechada: ' . $moeda($subtotal - $desconto) . " no $forma." . $aviso);
    header('Location: caixa.php?dia=' . date('Y-m-d')); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['estornar'])) {
    $pdo->prepare("DELETE FROM comandas WHERE id=? AND empresa_id=?")->execute([(int)$_POST['comanda_id'], $empresaId]);
    flash('sucesso', 'Comanda estornada (saiu do caixa).');
    header('Location: caixa.php?dia=' . $dia); exit;
}

// ---------- dados da tela ----------
$abertos = $pdo->prepare("SELECT a.id, a.data_hora, a.status, a.valor, a.assinatura_id, c.nome cliente, s.nome servico, s.preco, p.nome profissional
    FROM agendamentos a LEFT JOIN clientes c ON c.id=a.cliente_id LEFT JOIN servicos s ON s.id=a.servico_id LEFT JOIN profissionais p ON p.id=a.profissional_id
    WHERE a.empresa_id=? AND a.data_hora::date = CURRENT_DATE AND a.status IN ('agendado','confirmado','concluido')
      AND NOT EXISTS (SELECT 1 FROM comandas k WHERE k.agendamento_id = a.id) ORDER BY a.data_hora");
$abertos->execute([$empresaId]); $abertos = $abertos->fetchAll();
$agSel = null;
if ($agQ = (int)($_GET['agendamento'] ?? 0)) foreach ($abertos as $a) if ((int)$a['id'] === $agQ) $agSel = $a;
$comandas = $pdo->prepare("SELECT k.*, c.nome cliente, p.nome profissional, COALESCE(p.comissao,0) comissao FROM comandas k
    LEFT JOIN clientes c ON c.id=k.cliente_id LEFT JOIN profissionais p ON p.id=k.profissional_id
    WHERE k.empresa_id=? AND k.criado_em::date = ? ORDER BY k.criado_em DESC");
$comandas->execute([$empresaId, $dia]); $comandas = $comandas->fetchAll();
$total = array_sum(array_map(fn($k) => (float)$k['total'], $comandas));
$porForma = []; $porProf = [];
foreach ($comandas as $k) {
    $porForma[$k['forma_pagamento'] ?: '—'] = ($porForma[$k['forma_pagamento'] ?: '—'] ?? 0) + (float)$k['total'];
    $nomeP = $k['profissional'] ?: 'Sem profissional';
    // Comissão sobre os serviços (produtos e avulsos não entram), proporcional ao desconto
    $itensK = json_decode($k['itens'], true) ?: [];
    $servicos = array_sum(array_map(fn($i) => in_array($i['tipo'], ['atendimento', 'catalogo'], true) ? $i['preco'] * $i['qtd'] : 0, $itensK));
    $fator = (float)$k['subtotal'] > 0 ? ((float)$k['total'] / (float)$k['subtotal']) : 1;
    $porProf[$nomeP]['total'] = ($porProf[$nomeP]['total'] ?? 0) + (float)$k['total'];
    $porProf[$nomeP]['comissao'] = ($porProf[$nomeP]['comissao'] ?? 0) + $servicos * $fator * (float)$k['comissao'] / 100;
    $porProf[$nomeP]['n'] = ($porProf[$nomeP]['n'] ?? 0) + 1;
}
arsort($porForma);
$clientes = $pdo->prepare("SELECT id, nome FROM clientes WHERE empresa_id=? ORDER BY nome LIMIT 1000"); $clientes->execute([$empresaId]); $clientes = $clientes->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Caixa e comandas - Meu Negócio') ?>
<style>
.cx-grade { display: grid; grid-template-columns: minmax(320px, 1.1fr) minmax(300px, .9fr); gap: 16px; align-items: start; }
@media (max-width: 1000px) { .cx-grade { grid-template-columns: 1fr; } }
.cx-abertos { list-style: none; padding: 0; margin: 8px 0 0; display: grid; gap: 6px; }
.cx-abertos a { display: flex; justify-content: space-between; gap: 10px; padding: 10px 12px; border-radius: 10px; background: var(--surface-2); border: 1px solid var(--border); text-decoration: none; color: inherit; }
.cx-abertos a.ativo, .cx-abertos a:hover { border-color: var(--primary); }
.cx-abertos .mini { display: block; color: var(--text-dim); font-size: 12px; margin-top: 2px; }
.cx-abertos a > span:last-child { white-space: nowrap; }
.cx-linha { display: grid; grid-template-columns: 1fr 70px 110px 36px; gap: 6px; align-items: center; margin-bottom: 6px; }
.cx-linha input, .cx-linha select { margin: 0; }
.cx-total { display: flex; justify-content: space-between; align-items: baseline; padding: 12px 0; border-top: 1px solid var(--border); margin-top: 10px; }
.cx-total strong { font-size: 26px; }
.cx-kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 12px; }
.cx-kpis div { background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; padding: 10px 12px; }
.cx-kpis small { display: block; color: var(--text-dim); font-size: 12px; }
.cx-kpis strong { font-size: 20px; }
</style></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Caixa e comandas</h1><p class="subtitulo">Feche cada atendimento com o que foi vendido e acompanhe o caixa do dia e a comissão da equipe.</p></div></div>
    <?= flash_render() ?>
    <div class="cx-grade">
    <section class="form-box largo">
        <h3>1. Atendimentos de hoje para fechar</h3>
        <ul class="cx-abertos">
            <?php foreach ($abertos as $a): ?>
            <li><a href="?agendamento=<?= (int)$a['id'] ?>#comanda" class="<?= $agSel && (int)$agSel['id'] === (int)$a['id'] ? 'ativo' : '' ?>">
                <span><strong><?= date('H:i', strtotime($a['data_hora'])) ?></strong> · <?= e($a['cliente'] ?: 'Cliente') ?><span class="mini"><?= e($a['servico'] ?: '—') ?><?= $a['profissional'] ? ' · ' . e($a['profissional']) : '' ?></span></span>
                <span><?= $a['assinatura_id'] ? '⭐ clube' : $moeda($a['valor'] ?? $a['preco']) ?></span></a></li>
            <?php endforeach; ?>
            <?php if (!$abertos): ?><li class="dica">Nenhum atendimento de hoje esperando comanda. Use a comanda avulsa para quem chegou sem marcar.</li><?php endif; ?>
        </ul>

        <h3 id="comanda" style="margin-top:22px">2. <?= $agSel ? 'Comanda de ' . e($agSel['cliente'] ?: 'Cliente') : 'Comanda avulsa (sem agendamento)' ?></h3>
        <form method="post" id="formComanda">
            <?= csrf_campo() ?><input type="hidden" name="fechar_comanda" value="1">
            <?php if ($agSel): ?>
                <input type="hidden" name="agendamento_id" value="<?= (int)$agSel['id'] ?>">
                <label class="check"><input type="checkbox" name="incluir_servico" value="1" checked data-valor="<?= $agSel['assinatura_id'] ? 0 : (float)($agSel['valor'] ?? $agSel['preco']) ?>"> <?= e($agSel['servico']) ?> — <?= $agSel['assinatura_id'] ? 'saiu do clube/pacote' : $moeda($agSel['valor'] ?? $agSel['preco']) ?></label>
            <?php else: ?>
                <div class="form-row">
                    <div><label>Cliente</label><select name="cliente_id"><option value="">Novo cliente / sem cadastro</option><?php foreach ($clientes as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['nome']) ?></option><?php endforeach; ?></select></div>
                    <div><label>Profissional</label><select name="profissional_id"><option value="">—</option><?php foreach ($profissionais as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['nome']) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="form-row">
                    <div><label>Nome (se for cliente novo)</label><input type="text" name="cliente_nome" maxlength="150"></div>
                    <div><label>WhatsApp (opcional)</label><input type="tel" name="cliente_telefone" maxlength="20"></div>
                </div>
            <?php endif; ?>
            <label style="margin-top:12px">Produtos e serviços</label>
            <div id="linhas"></div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin:6px 0 10px">
                <button type="button" class="btn btn-ghost btn-sm" onclick="CX.item()">+ Do catálogo</button>
                <button type="button" class="btn btn-ghost btn-sm" onclick="CX.avulso()">+ Item avulso</button>
            </div>
            <div class="form-row">
                <div><label>Desconto (R$)</label><input type="text" name="desconto" inputmode="decimal" value="0,00" oninput="CX.somar()"></div>
                <div><label>Forma de pagamento</label><select name="forma_pagamento"><?php foreach (CAIXA_FORMAS as $f): ?><option <?= $agSel && $agSel['assinatura_id'] && $f === 'Clube/pacote' ? 'selected' : '' ?>><?= $f ?></option><?php endforeach; ?></select></div>
            </div>
            <label>Observação (opcional)</label><input type="text" name="observacao" maxlength="300" placeholder="Ex.: pagou metade no Pix e metade em dinheiro">
            <div class="cx-total"><span>Total</span><strong id="total">R$ 0,00</strong></div>
            <button type="submit" class="btn">Fechar comanda</button>
        </form>
    </section>

    <section class="form-box largo">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
            <h3 style="margin:0">Caixa do dia</h3>
            <form method="get" style="display:flex;gap:6px;align-items:center"><input type="date" name="dia" value="<?= e($dia) ?>" onchange="this.form.submit()" style="margin:0"></form>
        </div>
        <div class="cx-kpis" style="margin-top:12px">
            <div><small>Total</small><strong><?= $moeda($total) ?></strong></div>
            <div><small>Comandas</small><strong><?= count($comandas) ?></strong></div>
            <div><small>Ticket médio</small><strong><?= $moeda($comandas ? $total / count($comandas) : 0) ?></strong></div>
        </div>
        <h4>Por forma de pagamento</h4>
        <?php foreach ($porForma as $f => $v): ?><div class="barra-linha"><div class="rotulo"><span><?= e($f) ?></span><span><?= $moeda($v) ?></span></div><div class="progresso"><div style="width:<?= $total > 0 ? round(100 * $v / $total) : 0 ?>%"></div></div></div><?php endforeach; ?>
        <?php if (!$porForma): ?><p class="dica">Nenhuma comanda neste dia.</p><?php endif; ?>
        <?php if ($porProf): ?>
        <h4 style="margin-top:14px">Por profissional</h4>
        <div class="tabela-wrap"><table><thead><tr><th>Profissional</th><th>Comandas</th><th>Vendeu</th><th>Comissão</th></tr></thead><tbody>
        <?php foreach ($porProf as $nome => $p): ?><tr><td><?= e($nome) ?></td><td><?= (int)$p['n'] ?></td><td><?= $moeda($p['total']) ?></td><td><?= $p['comissao'] > 0 ? $moeda($p['comissao']) : '—' ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <p class="dica">A comissão conta só os serviços (não os produtos), com o desconto descontado. O % de cada um fica em Equipe.</p>
        <?php endif; ?>
        <?php if ($comandas): ?>
        <h4 style="margin-top:14px">Comandas</h4>
        <?php foreach ($comandas as $k): ?>
        <details class="cx-comanda"><summary><?= date('H:i', strtotime($k['criado_em'])) ?> · <?= e($k['cliente'] ?: 'Cliente') ?> · <strong><?= $moeda($k['total']) ?></strong> · <?= e($k['forma_pagamento']) ?></summary>
            <ul class="dica"><?php foreach (json_decode($k['itens'], true) ?: [] as $i): ?><li><?= (int)$i['qtd'] ?>× <?= e($i['nome']) ?> — <?= $moeda($i['preco'] * $i['qtd']) ?></li><?php endforeach; ?>
                <?php if ((float)$k['desconto'] > 0): ?><li>Desconto: −<?= $moeda($k['desconto']) ?></li><?php endif; ?><?php if ($k['observacao']): ?><li>📝 <?= e($k['observacao']) ?></li><?php endif; ?></ul>
            <form method="post" onsubmit="return confirm('Estornar esta comanda? Ela sai do caixa.')"><?= csrf_campo() ?><input type="hidden" name="comanda_id" value="<?= (int)$k['id'] ?>"><button type="submit" name="estornar" value="1" class="btn btn-ghost btn-sm">Estornar</button></form>
        </details>
        <?php endforeach; endif; ?>
    </section>
    </div>
</main>
</div>
<script>
const CX = (() => {
    const CATALOGO = <?= json_encode(array_map(fn($c) => ['id' => (int)$c['id'], 'nome' => $c['nome'], 'preco' => (float)$c['preco'], 'cat' => $c['categoria']], $catalogo), JSON_UNESCAPED_UNICODE) ?>;
    const din = (v) => 'R$ ' + Number(v || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const num = (s) => { s = String(s || '').trim(); if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.'); return parseFloat(s.replace(/[^0-9.]/g, '')) || 0; };
    const lin = document.getElementById('linhas');
    function remover(b) { b.closest('.cx-linha').remove(); somar(); }
    function item() {
        const d = document.createElement('div'); d.className = 'cx-linha';
        const sel = document.createElement('select'); sel.name = 'item_id[]';
        CATALOGO.forEach(c => { const o = document.createElement('option'); o.value = c.id; o.textContent = (c.cat ? c.cat + ' · ' : '') + c.nome + ' — ' + din(c.preco); o.dataset.preco = c.preco; sel.appendChild(o); });
        const q = document.createElement('input'); q.type = 'number'; q.name = 'item_qtd[]'; q.min = 1; q.max = 99; q.value = 1; q.setAttribute('aria-label', 'Quantidade');
        const v = document.createElement('span'); v.className = 'mini';
        const x = document.createElement('button'); x.type = 'button'; x.className = 'btn btn-ghost btn-sm'; x.textContent = '✕'; x.setAttribute('aria-label', 'Remover'); x.onclick = () => remover(x);
        sel.onchange = q.oninput = somar; d.append(sel, q, v, x); lin.appendChild(d); somar();
    }
    function avulso() {
        const d = document.createElement('div'); d.className = 'cx-linha';
        const n = document.createElement('input'); n.name = 'avulso_nome[]'; n.placeholder = 'Ex.: pomada modeladora'; n.maxLength = 80;
        const q = document.createElement('span'); q.textContent = '1×'; q.className = 'mini';
        const v = document.createElement('input'); v.name = 'avulso_valor[]'; v.placeholder = '0,00'; v.inputMode = 'decimal'; v.oninput = somar;
        const x = document.createElement('button'); x.type = 'button'; x.className = 'btn btn-ghost btn-sm'; x.textContent = '✕'; x.setAttribute('aria-label', 'Remover'); x.onclick = () => remover(x);
        d.append(n, q, v, x); lin.appendChild(d); n.focus();
    }
    function somar() {
        let t = 0;
        const base = document.querySelector('[name=incluir_servico]'); if (base && base.checked) t += parseFloat(base.dataset.valor) || 0;
        lin.querySelectorAll('.cx-linha').forEach(l => {
            const sel = l.querySelector('select');
            if (sel) { const p = parseFloat(sel.selectedOptions[0].dataset.preco) || 0, q = parseInt(l.querySelector('input[type=number]').value, 10) || 1; l.querySelector('.mini').textContent = din(p * q); t += p * q; }
            else t += num(l.querySelector('[name="avulso_valor[]"]').value);
        });
        t = Math.max(0, t - num(document.querySelector('[name=desconto]').value));
        document.getElementById('total').textContent = din(t);
    }
    const base = document.querySelector('[name=incluir_servico]'); if (base) base.addEventListener('change', somar);
    somar();
    return { item, avulso, somar };
})();
</script>
</body>
</html>
