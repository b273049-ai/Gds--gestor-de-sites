<?php
// ============================================================
// IMÓVEIS — cadastro no padrão dos portais (ZAP/VivaReal/OLX) e vitrine no site
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/imoveis.php';
exigir_login('empresa');
exigir_modulo('imoveis');
$empresaId = (int)empresa_id_atual();
$temTabela = (bool)$pdo->query("SELECT to_regclass('imoveis') IS NOT NULL")->fetchColumn();

$carregar = function ($id) use ($pdo, $empresaId) { $s = $pdo->prepare("SELECT * FROM imoveis WHERE id=? AND empresa_id=?"); $s->execute([(int)$id, $empresaId]); return $s->fetch(); };

if ($temTabela && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_imovel'])) {
    $id = (int)($_POST['imovel_id'] ?? 0);
    $atual = $id ? $carregar($id) : null;
    if ($id && !$atual) { header('Location: imoveis.php'); exit; }
    $t = fn($k, $n) => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $n);
    $n = fn($k) => ($v = valor_decimal($_POST[$k] ?? '', -1)) >= 0 ? $v : null;
    $i = fn($k) => max(0, min(99, (int)($_POST[$k] ?? 0)));
    $titulo = $t('titulo', 150);
    if ($titulo === '') { flash('erro', 'Dê um título ao imóvel (ex.: Casa 3 quartos com piscina no Jardim América).'); header('Location: imoveis.php' . ($id ? '?editar=' . $id : '') . '#form'); exit; }
    $fotos = $atual ? imovel_fotos($atual) : [];
    foreach ((array)($_POST['remover_foto'] ?? []) as $rf) $fotos = array_values(array_filter($fotos, fn($f) => $f !== $rf));
    if (($capa = (string)($_POST['capa'] ?? '')) !== '' && in_array($capa, $fotos, true)) $fotos = array_values(array_unique(array_merge([$capa], $fotos)));
    $fotos = imovel_salvar_fotos($empresaId, $fotos);
    $vals = [
        'codigo' => $t('codigo', 20) ?: null, 'titulo' => $titulo,
        'finalidade' => array_key_exists($_POST['finalidade'] ?? '', IMOVEL_FINALIDADES) ? $_POST['finalidade'] : 'venda',
        'tipo' => array_key_exists($_POST['tipo'] ?? '', IMOVEL_TIPOS) ? $_POST['tipo'] : 'casa',
        'preco' => $n('preco') ?? 0, 'condominio' => $n('condominio'), 'iptu' => $n('iptu'),
        'quartos' => $i('quartos'), 'suites' => $i('suites'), 'banheiros' => $i('banheiros'), 'vagas' => $i('vagas'),
        'area' => $n('area'), 'area_terreno' => $n('area_terreno'),
        'bairro' => $t('bairro', 100) ?: null, 'cidade' => $t('cidade', 100) ?: null, 'endereco' => $t('endereco', 200) ?: null,
        'descricao' => mb_substr(trim(strip_tags((string)($_POST['descricao'] ?? ''))), 0, 6000) ?: null,
        'caracteristicas' => implode(', ', array_slice(array_values(array_unique(array_filter(array_map(fn($x) => mb_substr(trim($x), 0, 40), array_merge((array)($_POST['carac'] ?? []), explode(',', (string)($_POST['carac_extra'] ?? ''))))))), 0, 40)) ?: null,
        'fotos' => json_encode($fotos), 'video' => url_valida_ou_vazia($_POST['video'] ?? '') ?: null,
        'financiamento' => isset($_POST['financiamento']) ? 'true' : 'false', 'permuta' => isset($_POST['permuta']) ? 'true' : 'false',
        'mobiliado' => isset($_POST['mobiliado']) ? 'true' : 'false', 'aceita_pet' => isset($_POST['aceita_pet']) ? 'true' : 'false',
        'destaque' => isset($_POST['destaque']) ? 'true' : 'false',
        'situacao' => array_key_exists($_POST['situacao'] ?? '', IMOVEL_SITUACOES) ? $_POST['situacao'] : 'disponivel',
        'proprietario' => $t('proprietario', 150) ?: null, 'proprietario_tel' => $t('proprietario_tel', 20) ?: null,
    ];
    if ($id) {
        $pdo->prepare("UPDATE imoveis SET " . implode(', ', array_map(fn($k) => "$k=?", array_keys($vals))) . ", atualizado_em=NOW() WHERE id=? AND empresa_id=?")->execute([...array_values($vals), $id, $empresaId]);
        flash('sucesso', 'Imóvel atualizado.');
    } else {
        $pdo->prepare("INSERT INTO imoveis (empresa_id, " . implode(', ', array_keys($vals)) . ") VALUES (?" . str_repeat(",?", count($vals)) . ")")->execute([$empresaId, ...array_values($vals)]);
        $id = (int)$pdo->lastInsertId();
        if (!$vals['codigo']) $pdo->prepare("UPDATE imoveis SET codigo=? WHERE id=?")->execute([(string)(1000 + $id), $id]);
        flash('sucesso', 'Imóvel publicado no site.');
    }
    header('Location: imoveis.php' . (!empty($_POST['continuar']) ? '?editar=' . $id . '#form' : '')); exit;
}
if ($temTabela && isset($_GET['situacao'], $_GET['id']) && array_key_exists($_GET['situacao'], IMOVEL_SITUACOES)) {
    $pdo->prepare("UPDATE imoveis SET situacao=?, atualizado_em=NOW() WHERE id=? AND empresa_id=?")->execute([$_GET['situacao'], (int)$_GET['id'], $empresaId]);
    header('Location: imoveis.php'); exit;
}
if ($temTabela && isset($_GET['alternar'])) {
    $pdo->prepare("UPDATE imoveis SET ativo = NOT ativo WHERE id=? AND empresa_id=?")->execute([(int)$_GET['alternar'], $empresaId]);
    header('Location: imoveis.php'); exit;
}
if ($temTabela && isset($_GET['duplicar']) && ($o = $carregar($_GET['duplicar']))) {
    unset($o['id'], $o['criado_em'], $o['atualizado_em']);
    $o['titulo'] = mb_substr($o['titulo'] . ' (cópia)', 0, 150); $o['codigo'] = null; $o['visualizacoes'] = 0; $o['fotos'] = '[]';
    foreach ($o as $k => $v) if (is_bool($v)) $o[$k] = $v ? 'true' : 'false';
    $pdo->prepare("INSERT INTO imoveis (" . implode(',', array_keys($o)) . ") VALUES (" . implode(',', array_fill(0, count($o), '?')) . ")")->execute(array_values($o));
    $novo = (int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE imoveis SET codigo=? WHERE id=?")->execute([(string)(1000 + $novo), $novo]);
    flash('sucesso', 'Cópia criada (sem fotos). Ajuste e salve.');
    header('Location: imoveis.php?editar=' . $novo . '#form'); exit;
}
if ($temTabela && isset($_GET['excluir'])) {
    $pdo->prepare("DELETE FROM imoveis WHERE id=? AND empresa_id=?")->execute([(int)$_GET['excluir'], $empresaId]);
    flash('sucesso', 'Imóvel excluído.'); header('Location: imoveis.php'); exit;
}

$imoveis = [];
$leadsPor = [];
if ($temTabela) {
    $s = $pdo->prepare("SELECT * FROM imoveis WHERE empresa_id=? ORDER BY ativo DESC, situacao='disponivel' DESC, atualizado_em DESC LIMIT 500"); $s->execute([$empresaId]); $imoveis = $s->fetchAll();
    try { $s = $pdo->prepare("SELECT imovel_id, COUNT(*) n FROM leads WHERE empresa_id=? AND imovel_id IS NOT NULL GROUP BY imovel_id"); $s->execute([$empresaId]); foreach ($s->fetchAll() as $r) $leadsPor[(int)$r['imovel_id']] = (int)$r['n']; } catch (PDOException $e) {}
}
$ed = isset($_GET['editar']) ? $carregar($_GET['editar']) : null;
$novo = isset($_GET['novo']) || !$imoveis;
$caracPadrao = ['Piscina', 'Churrasqueira', 'Área gourmet', 'Varanda', 'Quintal', 'Jardim', 'Portaria 24h', 'Elevador', 'Academia', 'Salão de festas', 'Playground', 'Ar-condicionado', 'Armários planejados', 'Closet', 'Lavanderia', 'Escritório', 'Energia solar', 'Portão eletrônico', 'Câmeras', 'Condomínio fechado', 'Vista livre', 'Próximo a escolas', 'Documentação ok', 'Escriturado'];
$caracSel = $ed ? array_map('trim', explode(',', (string)$ed['caracteristicas'])) : [];
$extra = implode(', ', array_diff(array_filter($caracSel), $caracPadrao));
$v = fn($k) => e($ed[$k] ?? '');
$num = fn($k, $dec = 2) => isset($ed[$k]) && $ed[$k] !== null && (float)$ed[$k] > 0 ? number_format((float)$ed[$k], $dec, ',', '') : '';
$tot = ['disp' => 0, 'venda' => 0, 'aluguel' => 0, 'vis' => 0];
foreach ($imoveis as $im) { if ($im['situacao'] === 'disponivel' && pg_bool($im['ativo'])) { $tot['disp']++; $tot[$im['finalidade'] === 'venda' ? 'venda' : 'aluguel']++; } $tot['vis'] += (int)$im['visualizacoes']; }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Imóveis - Meu Negócio') ?>
<style>
.im-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 14px; }
.im-kpis div { background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; padding: 10px 12px; } .im-kpis small { display: block; color: var(--text-dim); font-size: 12px; } .im-kpis strong { font-size: 20px; }
.im-lista { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
.im-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; display: flex; flex-direction: column; }
.im-card.off { opacity: .5; }
.im-card .foto { aspect-ratio: 16/10; background: var(--surface-2) center/cover; display: grid; place-items: center; font-size: 34px; position: relative; }
.im-card .foto span { position: absolute; top: 8px; left: 8px; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px; background: var(--primary); color: #fff; }
.im-card .foto span.sit { left: auto; right: 8px; background: #111827; }
.im-card .corpo { padding: 10px 12px; flex: 1; } .im-card .corpo strong { font-size: 17px; } .im-card .mini { display: block; color: var(--text-dim); font-size: 12px; margin-top: 2px; }
.im-card .acoes { display: flex; gap: 6px; flex-wrap: wrap; padding: 0 12px 12px; }
.im-card select { width: auto; padding: 5px 8px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 12px; }
.im-carac { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 4px 12px; margin: 6px 0 10px; }
.im-carac label { margin: 0; font-size: 13px; display: flex; gap: 6px; align-items: center; font-weight: 400; }
.im-fotos { display: flex; gap: 8px; flex-wrap: wrap; margin: 8px 0; }
.im-fotos figure { margin: 0; width: 120px; } .im-fotos img { width: 120px; height: 84px; object-fit: cover; border-radius: 8px; display: block; }
.im-fotos figcaption { font-size: 11px; display: flex; flex-direction: column; gap: 2px; margin-top: 4px; } .im-fotos figcaption label { margin: 0; font-weight: 400; display: flex; gap: 4px; align-items: center; }
.im-busca { width: 100%; max-width: 360px; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); margin-bottom: 12px; }
</style>
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header">
        <div><h1>Imóveis</h1><p class="subtitulo">Sua vitrine no site, com filtros, fotos e "agendar visita" — como nos portais, mas sem pagar por anúncio.</p></div>
        <div class="acoes"><a class="btn" href="?novo=1#form"><?= icone('mais', 16) ?> Cadastrar imóvel</a></div>
    </div>
    <?= flash_render() ?>
    <?php if (!$temTabela): ?><?= alerta('aviso', 'Atualizando o banco de dados… recarregue a página em instantes.') ?><?php else: ?>
    <div class="im-kpis">
        <div><small>Disponíveis</small><strong><?= $tot['disp'] ?></strong></div>
        <div><small>À venda</small><strong><?= $tot['venda'] ?></strong></div>
        <div><small>Para alugar</small><strong><?= $tot['aluguel'] ?></strong></div>
        <div><small>Visualizações no site</small><strong><?= number_format($tot['vis'], 0, ',', '.') ?></strong></div>
        <div><small>Interessados</small><strong><?= array_sum($leadsPor) ?></strong></div>
    </div>

    <?php if ($ed || $novo): ?>
    <form method="post" enctype="multipart/form-data" class="form-box largo" id="form">
        <?= csrf_campo() ?><input type="hidden" name="imovel_id" value="<?= (int)($ed['id'] ?? 0) ?>">
        <h3 style="margin-top:0"><?= $ed ? 'Editar imóvel cód. ' . e($ed['codigo']) : 'Novo imóvel' ?></h3>
        <div class="form-row">
            <div style="flex:3"><label>Título do anúncio</label><input type="text" name="titulo" maxlength="150" required value="<?= $v('titulo') ?>" placeholder="Ex.: Casa 3 quartos com piscina no Jardim América"></div>
            <div><label>Código</label><input type="text" name="codigo" maxlength="20" value="<?= $v('codigo') ?>" placeholder="automático"></div>
        </div>
        <div class="form-row">
            <div><label>Finalidade</label><select name="finalidade"><?php foreach (IMOVEL_FINALIDADES as $k => $r): ?><option value="<?= $k ?>" <?= ($ed['finalidade'] ?? 'venda') === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
            <div><label>Tipo</label><select name="tipo"><?php foreach (IMOVEL_TIPOS as $k => $r): ?><option value="<?= $k ?>" <?= ($ed['tipo'] ?? 'casa') === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
            <div><label>Situação</label><select name="situacao"><?php foreach (IMOVEL_SITUACOES as $k => $r): ?><option value="<?= $k ?>" <?= ($ed['situacao'] ?? 'disponivel') === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-row">
            <div><label>Preço (R$) <small class="muted">venda, aluguel/mês ou diária</small></label><input type="text" inputmode="decimal" name="preco" value="<?= $num('preco') ?>" placeholder="450.000,00"></div>
            <div><label>Condomínio (R$/mês)</label><input type="text" inputmode="decimal" name="condominio" value="<?= $num('condominio') ?>"></div>
            <div><label>IPTU (R$/ano)</label><input type="text" inputmode="decimal" name="iptu" value="<?= $num('iptu') ?>"></div>
        </div>
        <div class="form-row">
            <div><label>Quartos</label><input type="number" min="0" max="99" name="quartos" value="<?= (int)($ed['quartos'] ?? 0) ?>"></div>
            <div><label>Suítes</label><input type="number" min="0" max="99" name="suites" value="<?= (int)($ed['suites'] ?? 0) ?>"></div>
            <div><label>Banheiros</label><input type="number" min="0" max="99" name="banheiros" value="<?= (int)($ed['banheiros'] ?? 0) ?>"></div>
            <div><label>Vagas</label><input type="number" min="0" max="99" name="vagas" value="<?= (int)($ed['vagas'] ?? 0) ?>"></div>
        </div>
        <div class="form-row">
            <div><label>Área útil/construída (m²)</label><input type="text" inputmode="decimal" name="area" value="<?= $num('area', 0) ?>"></div>
            <div><label>Área do terreno (m²)</label><input type="text" inputmode="decimal" name="area_terreno" value="<?= $num('area_terreno', 0) ?>"></div>
        </div>
        <div class="form-row">
            <div><label>Bairro</label><input type="text" name="bairro" maxlength="100" value="<?= $v('bairro') ?>"></div>
            <div><label>Cidade</label><input type="text" name="cidade" maxlength="100" value="<?= $v('cidade') ?>"></div>
        </div>
        <label>Endereço completo <small class="muted">(não aparece no site — só para a equipe)</small></label><input type="text" name="endereco" maxlength="200" value="<?= $v('endereco') ?>">
        <label>Descrição</label><textarea name="descricao" rows="5" maxlength="6000" placeholder="Conte os diferenciais: sol da manhã, rua tranquila, perto de escola, reformada em 2023…"><?= $v('descricao') ?></textarea>
        <label>Características</label>
        <div class="im-carac"><?php foreach ($caracPadrao as $c): ?><label><input type="checkbox" name="carac[]" value="<?= e($c) ?>" <?= in_array($c, $caracSel, true) ? 'checked' : '' ?>> <?= e($c) ?></label><?php endforeach; ?></div>
        <input type="text" name="carac_extra" maxlength="400" value="<?= e($extra) ?>" placeholder="Outras (separe com vírgula): poço artesiano, edícula…">
        <div class="im-carac" style="margin-top:10px">
            <label><input type="checkbox" name="financiamento" <?= pg_bool($ed['financiamento'] ?? false) ? 'checked' : '' ?>> Aceita financiamento</label>
            <label><input type="checkbox" name="permuta" <?= pg_bool($ed['permuta'] ?? false) ? 'checked' : '' ?>> Aceita permuta</label>
            <label><input type="checkbox" name="mobiliado" <?= pg_bool($ed['mobiliado'] ?? false) ? 'checked' : '' ?>> Mobiliado</label>
            <label><input type="checkbox" name="aceita_pet" <?= pg_bool($ed['aceita_pet'] ?? false) ? 'checked' : '' ?>> Aceita pet</label>
            <label><input type="checkbox" name="destaque" <?= pg_bool($ed['destaque'] ?? false) ? 'checked' : '' ?>> ★ Destaque no site</label>
        </div>
        <label>Fotos <small class="muted">(até <?= IMOVEL_MAX_FOTOS ?> — a primeira é a capa; pode enviar várias de uma vez)</small></label>
        <?php if ($ed && ($fs = imovel_fotos($ed))): ?><div class="im-fotos"><?php foreach ($fs as $k => $f): ?><figure><img src="<?= e($f) ?>" alt=""><figcaption><label><input type="radio" name="capa" value="<?= e($f) ?>" <?= $k === 0 ? 'checked' : '' ?>> capa</label><label><input type="checkbox" name="remover_foto[]" value="<?= e($f) ?>"> remover</label></figcaption></figure><?php endforeach; ?></div><?php endif; ?>
        <input type="file" name="fotos_novas[]" accept="image/*" multiple>
        <label>Vídeo / tour virtual (link do YouTube, Instagram…)</label><input type="url" name="video" maxlength="255" value="<?= $v('video') ?>" placeholder="https://">
        <div class="form-row">
            <div><label>Proprietário <small class="muted">(só a equipe vê)</small></label><input type="text" name="proprietario" maxlength="150" value="<?= $v('proprietario') ?>"></div>
            <div><label>Telefone do proprietário</label><input type="tel" name="proprietario_tel" maxlength="20" value="<?= $v('proprietario_tel') ?>"></div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <button type="submit" name="salvar_imovel" value="1"><?= $ed ? 'Salvar' : 'Publicar imóvel' ?></button>
            <button type="submit" name="salvar_imovel" value="1" class="btn btn-ghost" onclick="this.form.insertAdjacentHTML('beforeend','<input type=hidden name=continuar value=1>')">Salvar e continuar editando</button>
            <a class="btn btn-ghost" href="imoveis.php">Cancelar</a>
        </div>
    </form>
    <?php endif; ?>

    <?php if ($imoveis): ?>
    <input type="search" class="im-busca" id="imBusca" placeholder="Buscar por código, título, bairro…">
    <div class="im-lista">
    <?php foreach ($imoveis as $im): $fs = imovel_fotos($im); $ativo = pg_bool($im['ativo']); ?>
        <article class="im-card <?= $ativo ? '' : 'off' ?>" data-busca="<?= e(mb_strtolower($im['codigo'] . ' ' . $im['titulo'] . ' ' . $im['bairro'] . ' ' . $im['cidade'])) ?>">
            <div class="foto" style="<?= $fs ? "background-image:url('" . e($fs[0]) . "')" : '' ?>"><?= $fs ? '' : '🏠' ?><span><?= e(IMOVEL_FINALIDADES[$im['finalidade']] ?? '') ?></span><?php if ($im['situacao'] !== 'disponivel'): ?><span class="sit"><?= e(IMOVEL_SITUACOES[$im['situacao']]) ?></span><?php endif; ?></div>
            <div class="corpo"><strong><?= e(imovel_preco_txt($im)) ?></strong><?= pg_bool($im['destaque']) ? ' <span class="pill pill-ok">★</span>' : '' ?>
                <span class="mini" style="color:var(--text);font-size:13.5px;margin-top:4px"><?= e($im['titulo']) ?></span>
                <span class="mini">Cód. <?= e($im['codigo']) ?> · <?= e(IMOVEL_TIPOS[$im['tipo']] ?? '') ?><?= $im['bairro'] ? ' · ' . e($im['bairro']) : '' ?></span>
                <span class="mini"><?= e(imovel_resumo($im)) ?></span>
                <span class="mini">👁 <?= (int)$im['visualizacoes'] ?> visualizações · 🙋 <?= $leadsPor[(int)$im['id']] ?? 0 ?> interessado(s) · <?= count($fs) ?> foto(s)<?= !$fs ? ' — <b style="color:var(--perigo,#f87171)">sem foto vende menos</b>' : '' ?></span></div>
            <div class="acoes">
                <a class="btn btn-sm" href="?editar=<?= (int)$im['id'] ?>#form"><?= icone('lapis', 14) ?> Editar</a>
                <select onchange="location='?id=<?= (int)$im['id'] ?>&situacao='+this.value+'<?= csrf_qs() ?>'" aria-label="Situação"><?php foreach (IMOVEL_SITUACOES as $k => $r): ?><option value="<?= $k ?>" <?= $im['situacao'] === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select>
                <a class="btn btn-ghost btn-sm" href="?duplicar=<?= (int)$im['id'] ?><?= csrf_qs() ?>" title="Duplicar">⧉</a>
                <a class="btn btn-ghost btn-sm" href="?alternar=<?= (int)$im['id'] ?><?= csrf_qs() ?>"><?= $ativo ? 'Ocultar' : 'Mostrar' ?></a>
                <a class="btn btn-ghost btn-sm" target="_blank" href="/<?= e(modulos_atuais()['empresa']['slug'] ?? '') ?>#imovel-<?= e($im['codigo']) ?>" title="Ver no site"><?= icone('olho', 14) ?></a>
                <?php if ($im['proprietario_tel']): ?><a class="btn btn-ghost btn-sm" target="_blank" href="<?= e(link_whatsapp($im['proprietario_tel'], 'Olá, ' . explode(' ', (string)$im['proprietario'])[0] . '! Sobre o imóvel cód. ' . $im['codigo'] . ':')) ?>" title="Falar com o proprietário">🔑</a><?php endif; ?>
                <a class="btn btn-perigo btn-sm" href="?excluir=<?= (int)$im['id'] ?><?= csrf_qs() ?>" onclick="return confirm('Excluir este imóvel?')"><?= icone('lixeira', 14) ?></a>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
    <script>document.getElementById('imBusca').addEventListener('input', function () { var q = this.value.toLowerCase(); document.querySelectorAll('.im-card').forEach(function (c) { c.hidden = q && c.dataset.busca.indexOf(q) < 0; }); });</script>
    <?php endif; ?>
    <?php endif; ?>
</main>
</div>
</body>
</html>
