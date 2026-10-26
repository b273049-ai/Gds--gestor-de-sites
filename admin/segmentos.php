<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$modos = atendimento_modos();
$grupos = $pdo->query("SELECT DISTINCT grupo FROM segmentos WHERE grupo IS NOT NULL AND grupo<>'' ORDER BY grupo")->fetchAll(PDO::FETCH_COLUMN);

// "✨ Preencher com IA": a partir do nome, a IA sugere como esse tipo de negócio atende e o que o site precisa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'preencher_ia') {
    $nome = trim($_POST['nome'] ?? '');
    if ($nome === '') responder_json(['ok' => false, 'erro' => 'Escreva o nome do segmento primeiro.']);
    $prompt = <<<PROMPT
Você ajuda uma plataforma brasileira que cria sites para pequenos negócios. Analise o segmento de negócio "$nome" e diga
como um cliente normalmente contrata esse tipo de negócio pela internet.

Formas possíveis:
- "agendamento": o cliente marca dia e horário (barbearia, clínica, salão...).
- "orcamento": o cliente informa o que precisa (data do evento, local, detalhes) e pede orçamento (DJ, fotógrafo, obras...).
- "contato": o cliente conversa pelo WhatsApp/formulário para tirar dúvidas ou pedir proposta (imobiliária, software...).
- "catalogo": o cliente escolhe produtos/pratos com preço e pede pelo WhatsApp (restaurante, loja).

Grupos existentes (use um deles se servir, ou crie um curto): GRUPOS

Responda APENAS JSON válido:
{"grupo":"...","modo_atendimento":"agendamento|orcamento|contato|catalogo","cta":"texto curto do botão principal (até 4 palavras)","descricao_ia":"2-3 frases orientando um designer: público, o que transmitir, o que o visitante quer fazer no site","exemplos_servicos":["serviço típico 1","2","3","4","5"]}
PROMPT;
    $prompt = str_replace('GRUPOS', implode(', ', $grupos), $prompt);
    $r = chamar_ia_json($prompt, 800, 45, 0.5, ['esforco' => 'low']);
    if (!$r['ok']) responder_json(['ok' => false, 'erro' => $r['erro']]);
    $d = $r['dados'];
    responder_json(['ok' => true,
        'modo_atendimento' => atendimento_modo_valido($d['modo_atendimento'] ?? null) ? $d['modo_atendimento'] : 'contato',
        'grupo' => mb_substr(trim((string)($d['grupo'] ?? '')), 0, 60),
        'cta' => mb_substr(trim((string)($d['cta'] ?? '')), 0, 80),
        'descricao_ia' => trim((string)($d['descricao_ia'] ?? '')),
        'exemplos_servicos' => implode("\n", array_slice(array_filter(array_map(fn($x) => trim((string)$x), (array)($d['exemplos_servicos'] ?? []))), 0, 10)),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_segmento'])) {
    $id = (int)($_POST['segmento_id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $modo = $_POST['modo_atendimento'] ?? '';
    if ($nome === '') { flash('erro', 'Informe o nome.'); header('Location: segmentos.php' . ($id ? "?editar=$id" : '')); exit; }
    $dados = [$nome, atendimento_modo_valido($modo) ? $modo : null, mb_substr(trim($_POST['cta'] ?? ''), 0, 80) ?: null,
              trim($_POST['descricao_ia'] ?? '') ?: null, trim($_POST['exemplos_servicos'] ?? '') ?: null, mb_substr(trim($_POST['grupo'] ?? ''), 0, 60) ?: null];
    if ($id) {
        $pdo->prepare("UPDATE segmentos SET nome=?, modo_atendimento=?, cta=?, descricao_ia=?, exemplos_servicos=?, grupo=? WHERE id=?")->execute([...$dados, $id]);
        flash('sucesso', 'Segmento atualizado.');
    } else {
        $pdo->prepare("INSERT INTO segmentos (nome, modo_atendimento, cta, descricao_ia, exemplos_servicos, grupo, slug) VALUES (?,?,?,?,?,?,?)")
            ->execute([...$dados, slug_unico('segmentos', slugify($nome))]);
        flash('sucesso', 'Segmento criado.');
    }
    header('Location: segmentos.php'); exit;
}
if (isset($_GET['desativar']) || isset($_GET['ativar'])) {
    $pdo->prepare("UPDATE segmentos SET ativo=? WHERE id=?")->execute([isset($_GET['ativar']) ? 'true' : 'false', (int)($_GET['ativar'] ?? $_GET['desativar'])]);
    header('Location: segmentos.php'); exit;
}
$segmentos = $pdo->query("SELECT s.*, (SELECT COUNT(*) FROM empresas e WHERE e.segmento_id=s.id) AS qtd FROM segmentos s " . SEGMENTOS_ORDEM)->fetchAll();
$editando = null;
foreach ($segmentos as $s) if ((int)$s['id'] === (int)($_GET['editar'] ?? 0)) $editando = $s;
$iaPronta = ia_configurada();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Segmentos - GDS Admin') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Segmentos de negócio</h1><p class="subtitulo">Cada segmento diz à IA como esse tipo de negócio conquista clientes: agendamento, orçamento, contato ou catálogo. Isso decide o botão principal e os componentes do site.</p></div></div>
    <?= flash_render() ?>
    <form method="post" class="form-box" id="formSeg" style="max-width:760px">
        <?= csrf_campo() ?>
        <h3><?= $editando ? 'Editar segmento' : 'Novo segmento' ?></h3>
        <input type="hidden" name="segmento_id" value="<?= (int)($editando['id'] ?? 0) ?>">
        <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            <div style="flex:1;min-width:220px"><label for="segNome">Nome</label><input type="text" id="segNome" name="nome" required value="<?= e($editando['nome'] ?? '') ?>" placeholder="Ex: Estúdio de Tatuagem"></div>
            <button type="button" class="btn btn-ghost" id="btnIa" style="margin-top:0" <?= $iaPronta ? '' : 'disabled title="Configure a IA em Configurações"' ?>><?= icone('brilho', 14) ?> Preencher com IA</button>
        </div>
        <label for="segGrupo">Grupo</label>
        <input type="text" id="segGrupo" name="grupo" list="listaGrupos" maxlength="60" value="<?= e($editando['grupo'] ?? '') ?>" placeholder="Ex: Alimentação e Bebidas">
        <datalist id="listaGrupos"><?php foreach ($grupos as $g): ?><option value="<?= e($g) ?>"><?php endforeach; ?></datalist>
        <div class="form-row">
            <div>
                <label for="segModo">Como os clientes contratam</label>
                <select name="modo_atendimento" id="segModo">
                    <option value="">— não definido (agendamento) —</option>
                    <?php foreach ($modos as $k => $m): ?><option value="<?= e($k) ?>" <?= ($editando['modo_atendimento'] ?? '') === $k ? 'selected' : '' ?>><?= e($m['nome']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label for="segCta">Texto do botão principal</label><input type="text" id="segCta" name="cta" maxlength="80" value="<?= e($editando['cta'] ?? '') ?>" placeholder="Ex: Pedir orçamento"></div>
        </div>
        <label for="segDesc">Orientação para a IA</label>
        <textarea id="segDesc" name="descricao_ia" rows="3" placeholder="Público, o que o site deve transmitir e o que o visitante quer fazer"><?= e($editando['descricao_ia'] ?? '') ?></textarea>
        <label for="segEx">Serviços típicos (um por linha)</label>
        <textarea id="segEx" name="exemplos_servicos" rows="4" placeholder="Casamento&#10;Aniversário&#10;Evento corporativo"><?= e($editando['exemplos_servicos'] ?? '') ?></textarea>
        <button type="submit" name="salvar_segmento" value="1"><?= $editando ? 'Salvar alterações' : 'Criar segmento' ?></button>
        <?php if ($editando): ?><a href="segmentos.php" class="btn btn-ghost" style="margin-top:18px">Cancelar</a><?php endif; ?>
    </form>
    <div class="toolbar">
        <input type="search" id="buscaSeg" placeholder="Buscar segmento… (<?= count($segmentos) ?>)" style="max-width:280px">
        <select id="filtroGrupo" style="max-width:240px"><option value="">Todos os grupos</option><?php foreach ($grupos as $g): ?><option><?= e($g) ?></option><?php endforeach; ?></select>
    </div>
    <div class="tabela-wrap"><table id="tabSeg">
        <thead><tr><th>Nome</th><th>Grupo</th><th>Como contrata</th><th>Botão</th><th>Empresas</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($segmentos as $s): $ativo = pg_bool($s['ativo']); ?>
        <tr data-grupo="<?= e($s['grupo'] ?? '') ?>">
            <td><strong><?= e($s['nome']) ?></strong><?php if (!empty($s['descricao_ia'])): ?><br><small style="color:var(--text-dim)"><?= e(mb_strimwidth($s['descricao_ia'], 0, 90, '…')) ?></small><?php endif; ?></td>
            <td><?= e($s['grupo'] ?? '—') ?></td>
            <td><?= e($modos[$s['modo_atendimento'] ?? '']['nome'] ?? '—') ?></td>
            <td><?= e($s['cta'] ?? '') ?></td>
            <td><?= (int)$s['qtd'] ?></td>
            <td><span class="badge badge-<?= $ativo ? 'ativo' : 'inativo' ?>"><?= $ativo ? 'ativo' : 'inativo' ?></span></td>
            <td class="acoes">
                <a class="btn btn-ghost btn-sm" href="?editar=<?= $s['id'] ?>#formSeg">Editar</a>
                <a class="btn btn-ghost btn-sm" href="?<?= $ativo ? 'desativar' : 'ativar' ?>=<?= $s['id'] ?><?= csrf_qs() ?>"><?= $ativo ? 'Desativar' : 'Ativar' ?></a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</main>
</div>
<script>
document.getElementById('btnIa').addEventListener('click', async (ev) => {
    const btn = ev.currentTarget, nome = document.getElementById('segNome').value.trim();
    if (!nome) { alert('Escreva o nome do segmento primeiro.'); return; }
    const txt = btn.innerHTML; btn.disabled = true; btn.textContent = 'Pensando…';
    try {
        const fd = new FormData();
        fd.append('acao', 'preencher_ia'); fd.append('nome', nome);
        fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        const r = await (await fetch('segmentos.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })).json();
        if (!r.ok) { alert(r.erro || 'Não foi possível preencher.'); return; }
        document.getElementById('segModo').value = r.modo_atendimento;
        if (r.grupo && !document.getElementById('segGrupo').value) document.getElementById('segGrupo').value = r.grupo;
        document.getElementById('segCta').value = r.cta;
        document.getElementById('segDesc').value = r.descricao_ia;
        document.getElementById('segEx').value = r.exemplos_servicos;
    } catch (e) { alert('Falha de conexão.'); }
    finally { btn.disabled = false; btn.innerHTML = txt; }
});
// Busca e filtro por grupo na tabela
(() => {
    const busca = document.getElementById('buscaSeg'), grupo = document.getElementById('filtroGrupo');
    const norm = (t) => t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const filtrar = () => {
        const q = norm(busca.value.trim()), g = grupo.value;
        document.querySelectorAll('#tabSeg tbody tr').forEach(tr => {
            tr.hidden = (q && !norm(tr.textContent).includes(q)) || (g && tr.dataset.grupo !== g);
        });
    };
    busca.addEventListener('input', filtrar); grupo.addEventListener('change', filtrar);
})();
</script>
</body>
</html>
