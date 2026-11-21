<?php
// ============================================================
// CONTATOS E NEGOCIAÇÕES (CRM) — funil no estilo RD Station/Pipedrive/Kommo,
// simples para pequeno negócio: cada contato do site (ou anotado à mão) anda pelas
// etapas, com valor, próximo contato (lembrete) e anotações.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = (int)empresa_id_atual();
$temCrm = (bool)$pdo->query("SELECT 1 FROM information_schema.columns WHERE table_name='leads' AND column_name='etapa'")->fetchColumn();
$temImoveis = (bool)$pdo->query("SELECT to_regclass('imoveis') IS NOT NULL")->fetchColumn();
const CRM_ETAPAS = ['novo' => '🆕 Novo', 'conversa' => '💬 Em conversa', 'visita' => '📅 Visita / reunião', 'proposta' => '📝 Proposta', 'ganho' => '✅ Fechado', 'perdido' => '✖ Perdido'];

$ajax = eh_requisicao_ajax();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $temCrm && isset($_POST['etapa'], $_POST['lead_id']) && !isset($_POST['salvar_lead'])) {
    $et = array_key_exists($_POST['etapa'], CRM_ETAPAS) ? $_POST['etapa'] : 'novo';
    $s = $pdo->prepare("UPDATE leads SET etapa=?, atualizado_em=NOW() WHERE id=? AND empresa_id=?"); $s->execute([$et, (int)$_POST['lead_id'], $empresaId]);
    if ($ajax) responder_json(['ok' => $s->rowCount() > 0]);
    header('Location: leads.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $temCrm && isset($_POST['salvar_lead'])) {
    $id = (int)($_POST['lead_id'] ?? 0);
    $t = fn($k, $n) => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $n);
    $prox = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['proximo_contato'] ?? '') ? $_POST['proximo_contato'] : null;
    $valor = valor_decimal($_POST['valor'] ?? '', -1);
    $et = array_key_exists($_POST['etapa'] ?? '', CRM_ETAPAS) ? $_POST['etapa'] : 'novo';
    $imovel = (int)($_POST['imovel_id'] ?? 0) ?: null;
    if ($id) {
        $pdo->prepare("UPDATE leads SET etapa=?, valor=?, proximo_contato=?, notas=?, imovel_id=COALESCE(?, imovel_id), atualizado_em=NOW() WHERE id=? AND empresa_id=?")
            ->execute([$et, $valor >= 0 ? $valor : null, $prox, $t('notas', 5000) ?: null, $imovel, $id, $empresaId]);
        flash('sucesso', 'Negociação atualizada.');
    } else {
        $nome = $t('nome', 120);
        if ($nome === '') { flash('erro', 'Informe o nome.'); header('Location: leads.php'); exit; }
        $pdo->prepare("INSERT INTO leads (empresa_id, nome, telefone, email, mensagem, assunto, origem, etapa, valor, proximo_contato, notas, imovel_id, atualizado_em) VALUES (?,?,?,?,?,?, 'manual', ?,?,?,?,?, NOW())")
            ->execute([$empresaId, $nome, $t('telefone', 20) ?: null, $t('email', 150) ?: null, $t('mensagem', 2000) ?: 'Contato anotado no painel', $t('assunto', 80) ?: null, $et, $valor >= 0 ? $valor : null, $prox, $t('notas', 5000) ?: null, $imovel]);
        flash('sucesso', 'Contato adicionado ao funil.');
    }
    header('Location: leads.php'); exit;
}
if (isset($_GET['excluir'])) {
    $pdo->prepare("DELETE FROM leads WHERE id=? AND empresa_id=?")->execute([(int)$_GET['excluir'], $empresaId]);
    flash('sucesso', 'Contato excluído.');
    header('Location: leads.php'); exit;
}
// Transforma o contato em cliente cadastrado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['virar_cliente'])) {
    $l = $pdo->prepare("SELECT * FROM leads WHERE id=? AND empresa_id=?");
    $l->execute([(int)$_POST['lead_id'], $empresaId]);
    if ($l = $l->fetch()) {
        $pdo->prepare("INSERT INTO clientes (empresa_id, nome, email, telefone, observacoes) VALUES (?,?,?,?,?)")
            ->execute([$empresaId, $l['nome'] ?: 'Contato do site', $l['email'], $l['telefone'], 'Veio pelo site: ' . mb_substr((string)$l['mensagem'], 0, 300)]);
        flash('sucesso', 'Contato adicionado aos clientes.');
    }
    header('Location: leads.php'); exit;
}

$busca = trim($_GET['q'] ?? '');
$sql = "SELECT l.*" . ($temImoveis && $temCrm ? ", im.codigo AS imovel_codigo, im.titulo AS imovel_titulo" : "") . " FROM leads l" . ($temImoveis && $temCrm ? " LEFT JOIN imoveis im ON im.id = l.imovel_id" : "") . " WHERE l.empresa_id=?";
$params = [$empresaId];
if ($busca !== '') { $sql .= " AND (l.nome ILIKE ? OR l.telefone ILIKE ? OR l.mensagem ILIKE ? OR COALESCE(l.assunto,'') ILIKE ?)"; array_push($params, "%$busca%", "%$busca%", "%$busca%", "%$busca%"); }
$st = $pdo->prepare($sql . " ORDER BY l.criado_em DESC LIMIT 500"); $st->execute($params); $leads = $st->fetchAll();
$cols = array_fill_keys(array_keys(CRM_ETAPAS), []);
foreach ($leads as $l) {
    $et = $temCrm ? ($l['etapa'] ?: 'novo') : 'novo';
    if (in_array($et, ['ganho', 'perdido'], true) && strtotime($l['atualizado_em'] ?? $l['criado_em']) < strtotime('-60 days')) continue; // fechados antigos saem do quadro
    $cols[$et][] = $l;
}
$hoje = date('Y-m-d');
$atrasados = array_values(array_filter($leads, fn($l) => $temCrm && !empty($l['proximo_contato']) && $l['proximo_contato'] <= $hoje && !in_array($l['etapa'], ['ganho', 'perdido'], true)));
$semana = count(array_filter($leads, fn($l) => strtotime($l['criado_em']) >= strtotime('-7 days')));
$ganhos = count(array_filter($leads, fn($l) => ($l['etapa'] ?? '') === 'ganho' && strtotime($l['atualizado_em'] ?? $l['criado_em']) >= strtotime('-90 days')));
$perdidos = count(array_filter($leads, fn($l) => ($l['etapa'] ?? '') === 'perdido' && strtotime($l['atualizado_em'] ?? $l['criado_em']) >= strtotime('-90 days')));
$emAberto = array_sum(array_map(fn($l) => in_array($l['etapa'] ?? 'novo', ['ganho', 'perdido'], true) ? 0 : (float)($l['valor'] ?? 0), $leads));
$imoveis = [];
if ($temImoveis && modulo_ativo('imoveis')) { $q = $pdo->prepare("SELECT id, codigo, titulo FROM imoveis WHERE empresa_id=? AND ativo=true ORDER BY titulo"); $q->execute([$empresaId]); $imoveis = $q->fetchAll(); }
$moeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Contatos e negociações - Meu Negócio') ?>
<style>
.crm-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 14px; }
.crm-kpis div { background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; padding: 10px 12px; } .crm-kpis small { display: block; color: var(--text-dim); font-size: 12px; } .crm-kpis strong { font-size: 20px; }
.crm-quadro { display: grid; grid-template-columns: repeat(6, minmax(230px, 1fr)); gap: 10px; overflow-x: auto; padding-bottom: 10px; align-items: start; }
.crm-col { background: var(--surface-2); border: 1px solid var(--border); border-radius: 14px; padding: 8px; min-height: 160px; }
.crm-col h3 { font-size: 13.5px; margin: 4px 6px 8px; display: flex; justify-content: space-between; } .crm-col h3 b { font-size: 12px; background: rgba(255,255,255,.08); border-radius: 999px; padding: 1px 8px; }
.crm-col.alvo { outline: 2px dashed var(--primary); }
.crm-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 10px; margin-bottom: 8px; font-size: 13px; cursor: grab; }
.crm-card.atrasado { border-color: rgba(248,113,113,.7); }
.crm-card strong { font-size: 14px; } .crm-card .mini { display: block; color: var(--text-dim); font-size: 12px; margin-top: 3px; }
.crm-card .msg { margin: 6px 0; color: var(--text); opacity: .85; white-space: pre-wrap; max-height: 60px; overflow: hidden; }
.crm-card .linha { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; margin-top: 6px; }
.crm-card select { width: auto; padding: 4px 6px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 12px; }
.crm-card details summary { cursor: pointer; font-size: 12px; color: var(--primary); margin-top: 6px; }
.crm-card details input, .crm-card details textarea, .crm-card details select { width: 100%; padding: 6px 8px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font: inherit; font-size: 12.5px; margin: 2px 0 6px; }
.crm-card details label { font-size: 11.5px; margin: 0; }
.crm-busca { display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; } .crm-busca input { max-width: 320px; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); }
</style>
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Contatos e negociações</h1><p class="subtitulo">Quem chamou pelo site (ou que você anotou) anda pelo funil até fechar. Arraste o cartão ou mude a etapa.</p></div></div>
    <?= flash_render() ?>
    <div class="crm-kpis">
        <div><small>Novos (7 dias)</small><strong><?= $semana ?></strong></div>
        <div><small>Retornos para hoje</small><strong style="<?= $atrasados ? 'color:var(--perigo,#f87171)' : '' ?>"><?= count($atrasados) ?></strong></div>
        <div><small>Em negociação</small><strong><?= $moeda($emAberto) ?></strong></div>
        <div><small>Taxa de fechamento (90 dias)</small><strong><?= $ganhos + $perdidos ? round(100 * $ganhos / ($ganhos + $perdidos)) . '%' : '—' ?></strong></div>
    </div>
    <?php if ($atrasados): ?><?= alerta('aviso', '📞 ' . count($atrasados) . ' contato(s) com retorno marcado para hoje ou atrasado: ' . e(implode(', ', array_slice(array_map(fn($l) => $l['nome'], $atrasados), 0, 5))) . (count($atrasados) > 5 ? '…' : '') . '.') ?><?php endif; ?>
    <?php if (!$temCrm): ?><?= alerta('aviso', 'Atualizando o banco… recarregue a página para ver o funil.') ?><?php endif; ?>
    <details class="form-box">
        <summary><?= icone('mais') ?> Anotar contato (telefone, Instagram, indicação…)</summary>
        <form method="post">
            <?= csrf_campo() ?>
            <div class="form-row"><div><label>Nome</label><input type="text" name="nome" required maxlength="120"></div><div><label>WhatsApp</label><input type="tel" name="telefone" maxlength="20"></div></div>
            <div class="form-row"><div><label>Interesse</label><input type="text" name="assunto" maxlength="80" placeholder="Ex.: apartamento 2 quartos até 300 mil"></div><div><label>Valor estimado (R$)</label><input type="text" name="valor" inputmode="decimal"></div></div>
            <?php if ($imoveis): ?><label>Imóvel</label><select name="imovel_id"><option value="">—</option><?php foreach ($imoveis as $im): ?><option value="<?= (int)$im['id'] ?>">Cód. <?= e($im['codigo']) ?> — <?= e($im['titulo']) ?></option><?php endforeach; ?></select><?php endif; ?>
            <div class="form-row"><div><label>Etapa</label><select name="etapa"><?php foreach (CRM_ETAPAS as $k => $r): ?><option value="<?= $k ?>"><?= $r ?></option><?php endforeach; ?></select></div><div><label>Próximo contato</label><input type="date" name="proximo_contato"></div></div>
            <label>Anotações</label><textarea name="notas" rows="2"></textarea>
            <button type="submit" name="salvar_lead" value="1">Adicionar ao funil</button>
        </form>
    </details>
    <form class="crm-busca" method="get"><input type="search" name="q" value="<?= e($busca) ?>" placeholder="Buscar nome, telefone, mensagem…"><button class="btn btn-ghost">Buscar</button><?php if ($busca): ?><a class="link" href="leads.php">limpar</a><?php endif; ?></form>
    <div class="crm-quadro">
    <?php foreach (CRM_ETAPAS as $et => $rot): ?>
        <section class="crm-col" data-etapa="<?= $et ?>"><h3><?= $rot ?> <b><?= count($cols[$et]) ?></b></h3>
        <?php foreach ($cols[$et] as $l): $atr = !empty($l['proximo_contato']) && $l['proximo_contato'] <= $hoje && !in_array($et, ['ganho', 'perdido'], true);
            $prim = explode(' ', (string)$l['nome'])[0]; ?>
            <article class="crm-card <?= $atr ? 'atrasado' : '' ?>" draggable="true" data-id="<?= (int)$l['id'] ?>">
                <strong><?= e($l['nome']) ?></strong>
                <span class="mini"><?= date('d/m H:i', strtotime($l['criado_em'])) ?><?= ($l['origem'] ?? '') === 'manual' ? ' · anotado' : ' · pelo site' ?><?= !empty($l['valor']) ? ' · <b>' . $moeda($l['valor']) . '</b>' : '' ?></span>
                <?php if (!empty($l['assunto'])): ?><span class="mini">🏷 <?= e($l['assunto']) ?></span><?php endif; ?>
                <?php if (!empty($l['imovel_codigo'])): ?><span class="mini">🏠 Cód. <?= e($l['imovel_codigo']) ?> — <?= e(mb_strimwidth((string)$l['imovel_titulo'], 0, 40, '…')) ?></span><?php endif; ?>
                <div class="msg"><?= e(mb_strimwidth((string)$l['mensagem'], 0, 180, '…')) ?></div>
                <?php if (!empty($l['proximo_contato'])): ?><span class="mini" style="<?= $atr ? 'color:var(--perigo,#f87171);font-weight:600' : '' ?>">📞 Retorno: <?= date('d/m', strtotime($l['proximo_contato'])) ?><?= $atr ? ' (hoje/atrasado)' : '' ?></span><?php endif; ?>
                <div class="linha">
                    <?php if ($temCrm): ?><select data-mudar="<?= (int)$l['id'] ?>" aria-label="Etapa"><?php foreach (CRM_ETAPAS as $k => $r): ?><option value="<?= $k ?>" <?= $k === $et ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select><?php endif; ?>
                    <?php if ($l['telefone']): ?><a class="btn btn-ghost btn-sm" target="_blank" href="<?= e(link_whatsapp($l['telefone'], 'Olá, ' . $prim . '! ' . (!empty($l['imovel_codigo']) ? 'Sobre o imóvel cód. ' . $l['imovel_codigo'] . ' que você viu no site: ' : 'Recebemos sua mensagem pelo site. '))) ?>" title="WhatsApp"><?= icone('chat', 14) ?></a><?php endif; ?>
                    <?php if ($l['email']): ?><a class="btn btn-ghost btn-sm" href="mailto:<?= e($l['email']) ?>" title="E-mail">✉</a><?php endif; ?>
                </div>
                <?php if ($temCrm): ?>
                <details><summary>Detalhes e anotações</summary>
                    <form method="post"><?= csrf_campo() ?><input type="hidden" name="lead_id" value="<?= (int)$l['id'] ?>"><input type="hidden" name="etapa" value="<?= $et ?>">
                        <label>Valor (R$)</label><input type="text" name="valor" inputmode="decimal" value="<?= !empty($l['valor']) ? number_format((float)$l['valor'], 2, ',', '') : '' ?>">
                        <label>Próximo contato</label><input type="date" name="proximo_contato" value="<?= e($l['proximo_contato'] ?? '') ?>">
                        <?php if ($imoveis): ?><label>Imóvel</label><select name="imovel_id"><option value="">—</option><?php foreach ($imoveis as $im): ?><option value="<?= (int)$im['id'] ?>" <?= (int)($l['imovel_id'] ?? 0) === (int)$im['id'] ? 'selected' : '' ?>>Cód. <?= e($im['codigo']) ?></option><?php endforeach; ?></select><?php endif; ?>
                        <label>Anotações</label><textarea name="notas" rows="3"><?= e($l['notas'] ?? '') ?></textarea>
                        <button type="submit" name="salvar_lead" value="1" class="btn btn-sm">Salvar</button>
                    </form>
                    <form method="post" style="display:inline"><?= csrf_campo() ?><input type="hidden" name="lead_id" value="<?= (int)$l['id'] ?>"><button class="btn btn-ghost btn-sm" name="virar_cliente" value="1"><?= icone('usuarios', 13) ?> Virar cliente</button></form>
                    <a class="btn btn-perigo btn-sm" href="?excluir=<?= (int)$l['id'] ?><?= csrf_qs() ?>" onclick="return confirm('Excluir este contato?')"><?= icone('lixeira', 13) ?></a>
                </details>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if (!$cols[$et]): ?><p class="dica" style="margin:6px">—</p><?php endif; ?>
        </section>
    <?php endforeach; ?>
    </div>
    <?php if (!$leads): ?><p class="dica">Nenhum contato ainda. Quem preencher o formulário do site (ou pedir visita de um imóvel) aparece aqui.</p><?php endif; ?>
</main>
</div>
<script>
(function () {
    var csrf = <?= json_encode(csrf_token()) ?>;
    function mover(id, etapa) {
        var f = new FormData(); f.append('csrf_token', csrf); f.append('lead_id', id); f.append('etapa', etapa);
        return fetch('leads.php', { method: 'POST', body: f, headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); });
    }
    function contar() { document.querySelectorAll('.crm-col').forEach(function (c) { var n = c.querySelectorAll('.crm-card').length; c.querySelector('h3 b').textContent = n; var d = c.querySelector(':scope > .dica'); if (d) d.hidden = n > 0; }); }
    document.querySelectorAll('[data-mudar]').forEach(function (s) {
        s.addEventListener('change', function () {
            var card = s.closest('.crm-card');
            mover(s.dataset.mudar, s.value).then(function (r) { if (r.ok) { document.querySelector('.crm-col[data-etapa="' + s.value + '"]').appendChild(card); card.querySelector('input[name=etapa]') && (card.querySelector('input[name=etapa]').value = s.value); contar(); } });
        });
    });
    var arrastando = null;
    document.querySelectorAll('.crm-card').forEach(function (c) { c.addEventListener('dragstart', function () { arrastando = c; }); });
    document.querySelectorAll('.crm-col').forEach(function (col) {
        col.addEventListener('dragover', function (e) { e.preventDefault(); col.classList.add('alvo'); });
        col.addEventListener('dragleave', function () { col.classList.remove('alvo'); });
        col.addEventListener('drop', function (e) {
            e.preventDefault(); col.classList.remove('alvo'); if (!arrastando) return;
            var c = arrastando, et = col.dataset.etapa; arrastando = null;
            mover(c.dataset.id, et).then(function (r) { if (r.ok) { col.appendChild(c); var s = c.querySelector('[data-mudar]'); if (s) s.value = et; var h = c.querySelector('input[name=etapa]'); if (h) h.value = et; contar(); } });
        });
    });
})();
</script>
</body>
</html>
