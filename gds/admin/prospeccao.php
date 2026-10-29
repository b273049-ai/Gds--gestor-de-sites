<?php
// Prospecção de clientes: empresas do Google Maps sem site próprio, com nota de qualificação,
// funil (novo → contatado → respondeu → negociando → fechado) e WhatsApp com mensagem pronta.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/prospeccao.php';
exigir_login('admin');
gds_garantir_schema();

$statusNomes = ['novo' => 'Novo', 'contatado' => 'Contatado', 'respondeu' => 'Respondeu', 'negociando' => 'Negociando', 'fechado' => 'Fechado ✅', 'descartado' => 'Descartado'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    @set_time_limit(120);
    $id = (int)($_POST['id'] ?? 0);
    switch ($_POST['acao']) {
        case 'buscar':
            session_write_close();
            $termo = mb_substr(trim($_POST['termo'] ?? ''), 0, 80);
            $cidade = mb_substr(trim($_POST['cidade'] ?? ''), 0, 80);
            if ($termo === '' || $cidade === '') responder_json(['ok' => false, 'erro' => 'Informe o tipo de negócio e a cidade.']);
            $fonte = in_array($_POST['fonte'] ?? '', ['auto', 'google', 'osm'], true) ? $_POST['fonte'] : 'auto';
            if ($fonte === 'google' && prosp_chave_google() === '') responder_json(['ok' => false, 'erro' => 'Cadastre a chave do Google Places em Configurações.']);
            responder_json(prosp_buscar($termo, $cidade, ($_POST['so_sem_site'] ?? '1') === '1', $fonte));
        case 'status':
            $st = array_key_exists($_POST['status'] ?? '', $statusNomes) ? $_POST['status'] : 'novo';
            $pdo->prepare("UPDATE prospects SET status=?, contatado_em = CASE WHEN ?='contatado' AND contatado_em IS NULL THEN NOW() ELSE contatado_em END, atualizado_em=NOW() WHERE id=?")->execute([$st, $st, $id]);
            responder_json(['ok' => true]);
        case 'obs':
            $pdo->prepare("UPDATE prospects SET observacoes=?, atualizado_em=NOW() WHERE id=?")->execute([mb_substr(trim($_POST['observacoes'] ?? ''), 0, 2000), $id]);
            responder_json(['ok' => true]);
        case 'modelo':
            set_config('prosp_mensagem', mb_substr(trim($_POST['modelo'] ?? ''), 0, 1500));
            responder_json(['ok' => true]);
        case 'mensagem_ia':
            session_write_close();
            if (!ia_configurada()) responder_json(['ok' => false, 'erro' => 'Configure a IA em Configurações.']);
            $st = $pdo->prepare("SELECT * FROM prospects WHERE id=?"); $st->execute([$id]); $p = $st->fetch();
            if (!$p) responder_json(['ok' => false, 'erro' => 'Não encontrado.']);
            $prompt = "Escreva UMA mensagem curta de WhatsApp (no máximo 5 linhas, tom humano e educado, sem parecer robô nem spam) para o dono da empresa abaixo, "
                    . "oferecendo um site profissional com " . prosp_recurso($p['categoria'] . ' ' . $p['nome']) . " e confirmação automática no WhatsApp. "
                    . "Cite um detalhe real da empresa (nota/avaliações ou o bairro) para mostrar que não é mensagem em massa. Termine com uma pergunta simples "
                    . "(ex.: posso te mostrar uma prévia?). Não invente números nem promessas. Português do Brasil, no máximo 1 emoji. Responda só com a mensagem.\n\n"
                    . "Empresa: {$p['nome']}\nRamo: {$p['categoria']}\nEndereço: {$p['endereco']}\nNota: " . ($p['nota'] ?: 'sem nota') . " · {$p['avaliacoes']} avaliações\n"
                    . 'Site atual: ' . ($p['site'] ?: 'nenhum') . "\nMeu nome/empresa: " . get_config('mkt_marca', 'GDS Sites');
            $r = chamar_ia($prompt, 500, 40, 0.8, null, ['esforco' => 'low']);
            responder_json($r['ok'] ? ['ok' => true, 'mensagem' => trim($r['texto'], " \n\"")] : ['ok' => false, 'erro' => $r['erro']]);
        case 'excluir':
            $pdo->prepare("DELETE FROM prospects WHERE id=?")->execute([$id]);
            responder_json(['ok' => true]);
    }
    responder_json(['ok' => false, 'erro' => 'Ação inválida.'], 400);
}

// Exportar planilha
if (isset($_GET['exportar'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="prospects.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Nome', 'Ramo', 'Cidade', 'Endereço', 'Telefone', 'WhatsApp', 'Site', 'Nota', 'Avaliações', 'Pontuação', 'Status', 'Observações', 'Google Maps'], ';');
    foreach ($pdo->query("SELECT * FROM prospects ORDER BY pontuacao DESC, avaliacoes DESC") as $p)
        fputcsv($out, [$p['nome'], $p['categoria'], $p['cidade'], $p['endereco'], $p['telefone'], $p['whatsapp'], $p['site'], $p['nota'], $p['avaliacoes'], $p['pontuacao'], $statusNomes[$p['status']] ?? $p['status'], $p['observacoes'], $p['maps_url']], ';');
    exit;
}

$filtroStatus = array_key_exists($_GET['status'] ?? '', $statusNomes) ? $_GET['status'] : '';
$filtroTemp = in_array($_GET['temp'] ?? '', ['quente', 'morno', 'frio'], true) ? $_GET['temp'] : '';
$soWhats = isset($_GET['whats']);
$where = ['1=1']; $params = [];
if ($filtroStatus) { $where[] = 'status=?'; $params[] = $filtroStatus; } else $where[] = "status <> 'descartado'";
if ($filtroTemp === 'quente') $where[] = 'pontuacao >= 70'; elseif ($filtroTemp === 'morno') $where[] = 'pontuacao BETWEEN 45 AND 69'; elseif ($filtroTemp === 'frio') $where[] = 'pontuacao < 45';
if ($soWhats) $where[] = "COALESCE(whatsapp,'') <> ''";
if (($q = trim($_GET['q'] ?? '')) !== '') { $where[] = '(nome ILIKE ? OR categoria ILIKE ? OR cidade ILIKE ? OR busca ILIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%", "%$q%"); }
$st = $pdo->prepare("SELECT * FROM prospects WHERE " . implode(' AND ', $where) . " ORDER BY (status='novo') DESC, pontuacao DESC, avaliacoes DESC LIMIT 300");
$st->execute($params); $prospects = $st->fetchAll();
$funil = []; foreach ($pdo->query("SELECT status, COUNT(*) c FROM prospects GROUP BY status") as $r) $funil[$r['status']] = (int)$r['c'];
$quentes = (int)$pdo->query("SELECT COUNT(*) FROM prospects WHERE pontuacao >= 70 AND status='novo'")->fetchColumn();
$segmentos = $pdo->query("SELECT nome FROM segmentos WHERE ativo=true AND slug<>'outro' ORDER BY nome")->fetchAll(PDO::FETCH_COLUMN);
$temGoogle = prosp_chave_google() !== '';
$modelo = get_config('prosp_mensagem', '') ?: prosp_mensagem_padrao();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Prospecção de clientes - GDS Admin') ?>
<style>
.funil { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-bottom: 18px; }
.funil a { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; text-decoration: none; color: var(--text); }
.funil a.ativo { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(91,124,255,.2); }
.funil strong { display: block; font-size: 24px; } .funil span { font-size: 12px; color: var(--text-dim); }
.prosp { display: grid; gap: 12px; }
.prosp-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 16px; display: grid; grid-template-columns: 1fr auto; gap: 10px 18px; }
.prosp-card.st-descartado { opacity: .55; }
.prosp-card h3 { margin: 0 0 4px; font-size: 16px; }
.prosp-info { font-size: 13px; color: var(--text-dim); display: flex; flex-wrap: wrap; gap: 4px 14px; }
.prosp-motivos { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.prosp-motivos span { font-size: 11.5px; background: var(--surface-2); border: 1px solid var(--border); padding: 3px 8px; border-radius: 999px; color: var(--text-dim); }
.prosp-nota { text-align: right; } .prosp-nota strong { font-size: 26px; display: block; line-height: 1; }
.temp-quente { color: #ff7a45; } .temp-morno { color: #f5b94c; } .temp-frio { color: #6cb6ff; }
.prosp-acoes { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.prosp-acoes select { width: auto; }
.prosp-msg { grid-column: 1 / -1; }
.prosp-msg textarea { width: 100%; min-height: 96px; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 10px; padding: 10px; font: inherit; font-size: 13.5px; }
.btn-wa { background: #25d366 !important; color: #06240f !important; border-color: #25d366 !important; font-weight: 700; }
.busca-grade { display: grid; grid-template-columns: 1.2fr 1fr auto; gap: 10px; align-items: end; }
@media (max-width: 760px) { .busca-grade { grid-template-columns: 1fr; } .prosp-card { grid-template-columns: 1fr; } .prosp-nota { text-align: left; } }
</style>
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Prospecção de clientes</h1>
        <p class="subtitulo">Encontre no Google Maps empresas que ainda não têm site, veja quem tem mais chance de comprar e chame no WhatsApp com a mensagem pronta.</p></div>
        <div class="acoes"><a class="btn btn-ghost" href="?exportar=1"><?= icone('download', 16) ?> Exportar planilha</a></div></div>
    <?= flash_render() ?>
    <?php if (!$temGoogle): ?><?= alerta('info', 'Sem a chave do Google, a busca usa o OpenStreetMap (grátis, mas com menos empresas e sem as avaliações). Para buscar direto no Google Maps, cadastre a chave da "Places API (New)" em Configurações → Mapas.') ?><?php endif; ?>

    <section class="form-box largo">
        <h3>1. Buscar empresas</h3>
        <div class="busca-grade">
            <div><label>Tipo de negócio</label><input type="text" id="termo" list="listaSeg" placeholder="Ex: barbearia, hamburgueria, dentista"><datalist id="listaSeg"><?php foreach ($segmentos as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist></div>
            <div><label>Cidade (e bairro, se quiser)</label><input type="text" id="cidade" placeholder="Ex: Jundiaí SP ou Moema, São Paulo"></div>
            <button type="button" class="btn" id="btnBuscar" onclick="PR.buscar()"><?= icone('olho', 16) ?> Buscar</button>
        </div>
        <div style="display:flex;gap:18px;flex-wrap:wrap;margin-top:10px">
            <label class="check"><input type="checkbox" id="soSemSite" checked> Só empresas sem site próprio (quem só tem Instagram/Facebook entra)</label>
            <?php if ($temGoogle): ?><label>Fonte <select id="fonte" style="width:auto"><option value="google">Google Maps</option><option value="osm">OpenStreetMap (grátis)</option></select></label><?php else: ?><input type="hidden" id="fonte" value="osm"><?php endif; ?>
        </div>
        <p class="dica" id="statusBusca">Cada busca traz até 60 empresas. Busque por bairro para achar mais.</p>
    </section>

    <div class="funil">
        <a href="prospeccao.php" class="<?= !$filtroStatus && !$filtroTemp ? 'ativo' : '' ?>"><strong><?= array_sum($funil) - ($funil['descartado'] ?? 0) ?></strong><span>Na lista</span></a>
        <a href="?temp=quente&status=novo" class="<?= $filtroTemp === 'quente' ? 'ativo' : '' ?>"><strong class="temp-quente"><?= $quentes ?></strong><span>🔥 Quentes para chamar</span></a>
        <?php foreach ($statusNomes as $k => $v): ?><a href="?status=<?= $k ?>" class="<?= $filtroStatus === $k ? 'ativo' : '' ?>"><strong><?= (int)($funil[$k] ?? 0) ?></strong><span><?= e($v) ?></span></a><?php endforeach; ?>
    </div>

    <details class="form-box largo">
        <summary>✉ Mensagem padrão do WhatsApp</summary>
        <p class="dica">Variáveis: {nome} {nicho} {cidade} {nota} {avaliacoes} {avaliacoes_txt} {recurso} {link}. Cada empresa também tem o botão "Escrever com IA" para uma mensagem única.</p>
        <textarea id="modelo" rows="6" style="width:100%"><?= e($modelo) ?></textarea>
        <button type="button" class="btn btn-sm" style="margin-top:8px" onclick="PR.salvarModelo()">Salvar mensagem</button>
        <p class="dica">⚠ Para o seu WhatsApp não ser bloqueado: mande uma por vez, personalize, no máximo 20 a 30 por dia, e marque como "Descartado" quem pedir para não receber.</p>
    </details>

    <form class="toolbar" method="get">
        <input type="search" name="q" placeholder="Filtrar por nome, ramo ou cidade…" value="<?= e($_GET['q'] ?? '') ?>">
        <?php if ($filtroStatus): ?><input type="hidden" name="status" value="<?= e($filtroStatus) ?>"><?php endif; ?>
        <label class="check"><input type="checkbox" name="whats" <?= $soWhats ? 'checked' : '' ?> onchange="this.form.submit()"> Só com WhatsApp</label>
        <button class="btn btn-ghost">Filtrar</button>
    </form>

    <div class="prosp" id="lista">
    <?php foreach ($prospects as $p): [$tk, $tr] = prosp_temperatura((int)$p['pontuacao']); $msg = prosp_mensagem($p); ?>
        <article class="prosp-card st-<?= e($p['status']) ?>" data-id="<?= (int)$p['id'] ?>" data-wa="<?= e($p['whatsapp']) ?>">
            <div>
                <h3><?= e($p['nome']) ?></h3>
                <div class="prosp-info">
                    <span><?= e($p['categoria']) ?></span>
                    <?php if ($p['nota']): ?><span>⭐ <?= number_format((float)$p['nota'], 1, ',', '') ?> (<?= (int)$p['avaliacoes'] ?>)</span><?php endif; ?>
                    <?php if ($p['telefone']): ?><span>📞 <?= e($p['telefone']) ?></span><?php endif; ?>
                    <?php if ($p['endereco']): ?><span>📍 <?= e(mb_strimwidth($p['endereco'], 0, 70, '…')) ?></span><?php endif; ?>
                    <?php if ($p['site']): ?><span>🔗 <a class="link" href="<?= e(preg_match('#^https?://#', $p['site']) ? $p['site'] : 'https://' . $p['site']) ?>" target="_blank" rel="noopener"><?= e(mb_strimwidth(preg_replace('#^https?://(www\.)?#', '', $p['site']), 0, 40, '…')) ?></a></span><?php endif; ?>
                </div>
                <div class="prosp-motivos"><?php foreach ((array)json_decode((string)$p['motivos'], true) as $m): ?><span><?= e($m) ?></span><?php endforeach; ?></div>
            </div>
            <div class="prosp-nota"><strong class="temp-<?= $tk ?>"><?= (int)$p['pontuacao'] ?></strong><span class="temp-<?= $tk ?>"><?= $tr ?></span></div>
            <details class="prosp-msg">
                <summary class="link" style="cursor:pointer;font-size:13px">Ver / editar a mensagem</summary>
                <textarea class="msg-texto"><?= e($msg) ?></textarea>
                <div style="display:flex;gap:8px;margin-top:6px"><button type="button" class="btn btn-ghost btn-sm" onclick="PR.ia(this)"><?= icone('brilho', 14) ?> Escrever com IA</button>
                <input type="text" class="obs" placeholder="Anotação (ex.: falar com o João à tarde)" value="<?= e($p['observacoes'] ?? '') ?>" style="flex:1" onchange="PR.obs(this)"></div>
            </details>
            <div class="prosp-acoes">
                <?php if ($p['whatsapp']): ?><button type="button" class="btn btn-sm btn-wa" onclick="PR.whats(this)"><?= icone('chat', 14) ?> Chamar no WhatsApp</button>
                <?php elseif ($p['telefone']): ?><a class="btn btn-sm btn-ghost" href="tel:<?= e(preg_replace('/\D/', '', $p['telefone'])) ?>" onclick="PR.marcar(this,'contatado')">📞 Ligar (fixo)</a><?php endif; ?>
                <?php if ($p['maps_url']): ?><a class="btn btn-sm btn-ghost" href="<?= e($p['maps_url']) ?>" target="_blank" rel="noopener">Ver no Maps</a><?php endif; ?>
                <select onchange="PR.status(this)" aria-label="Situação"><?php foreach ($statusNomes as $k => $v): ?><option value="<?= $k ?>" <?= $p['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
                <?php if ($p['contatado_em']): ?><span class="mini">contatado em <?= date('d/m', strtotime($p['contatado_em'])) ?></span><?php endif; ?>
                <button type="button" class="btn btn-sm btn-ghost" title="Excluir" onclick="PR.excluir(this)"><?= icone('lixeira', 14) ?></button>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$prospects): ?><div class="vazio-box"><?= icone('olho', 28) ?><p>Nenhuma empresa aqui ainda. Faça uma busca acima — ex.: "barbearia" em "Campinas SP".</p></div><?php endif; ?>
    </div>
</main>
</div>
<div id="toasts"></div>
<script>
const PR = (() => {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (id) => document.getElementById(id);
    async function api(dados) {
        const fd = new FormData(); fd.append('csrf_token', CSRF); Object.entries(dados).forEach(([k, v]) => fd.append(k, v));
        try { return await (await fetch('prospeccao.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })).json(); }
        catch (e) { return { ok: false, erro: 'Falha de conexão.' }; }
    }
    function toast(msg, tipo) { const t = document.createElement('div'); t.className = 'toast toast-' + (tipo || 'info'); t.textContent = msg; $('toasts').appendChild(t); setTimeout(() => t.remove(), 5000); }
    const card = (el) => el.closest('.prosp-card');
    async function buscar() {
        const termo = $('termo').value.trim(), cidade = $('cidade').value.trim();
        if (!termo || !cidade) { toast('Informe o tipo de negócio e a cidade.', 'erro'); return; }
        const b = $('btnBuscar'); b.disabled = true; $('statusBusca').textContent = 'Buscando no mapa… (até 30 segundos)';
        const r = await api({ acao: 'buscar', termo, cidade, so_sem_site: $('soSemSite').checked ? '1' : '0', fonte: $('fonte').value });
        b.disabled = false;
        if (!r.ok) { $('statusBusca').textContent = r.erro; toast(r.erro, 'erro'); return; }
        $('statusBusca').textContent = `${r.fonte}: ${r.encontrados} empresas encontradas · ${r.com_site} já têm site (ignoradas) · ${r.salvos} na lista (${r.novos} novas).`;
        toast(r.novos + ' empresas novas na lista!', 'sucesso');
        setTimeout(() => location.reload(), 1200);
    }
    async function status(sel) { const r = await api({ acao: 'status', id: card(sel).dataset.id, status: sel.value }); if (r.ok) { card(sel).className = 'prosp-card st-' + sel.value; toast('Situação salva.', 'sucesso'); } }
    async function marcar(el, st) { const c = card(el); const s = c.querySelector('select'); if (s.value === 'novo') { s.value = st; await status(s); } }
    function whats(btn) {
        const c = card(btn), txt = c.querySelector('.msg-texto').value;
        window.open('https://wa.me/' + c.dataset.wa + '?text=' + encodeURIComponent(txt), '_blank', 'noopener');
        marcar(btn, 'contatado');
    }
    async function ia(btn) {
        const c = card(btn); btn.disabled = true; const t = btn.innerHTML; btn.textContent = 'Escrevendo…';
        const r = await api({ acao: 'mensagem_ia', id: c.dataset.id });
        btn.disabled = false; btn.innerHTML = t;
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        c.querySelector('.msg-texto').value = r.mensagem;
    }
    async function obs(inp) { const r = await api({ acao: 'obs', id: card(inp).dataset.id, observacoes: inp.value }); if (r.ok) toast('Anotação salva.', 'sucesso'); }
    async function salvarModelo() { const r = await api({ acao: 'modelo', modelo: $('modelo').value }); if (r.ok) { toast('Mensagem salva!', 'sucesso'); setTimeout(() => location.reload(), 600); } }
    async function excluir(btn) { if (!confirm('Excluir esta empresa da lista?')) return; const c = card(btn); const r = await api({ acao: 'excluir', id: c.dataset.id }); if (r.ok) c.remove(); }
    $('termo').addEventListener('keydown', e => { if (e.key === 'Enter') buscar(); });
    $('cidade').addEventListener('keydown', e => { if (e.key === 'Enter') buscar(); });
    return { buscar, status, marcar, whats, ia, obs, salvarModelo, excluir };
})();
</script>
</body>
</html>
