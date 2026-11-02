<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/opcoes.php';
exigir_login('empresa');
$empresaId = (int)empresa_id_atual();
require_once __DIR__ . '/../includes/orcamento.php'; // orc_gravar_imagem (fotos enviadas)

// Ações rápidas da lista (sem recarregar): preço, esgotou, mostrar/ocultar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rapido'])) {
    $sid = (int)($_POST['servico_id'] ?? 0);
    $ok = false;
    if ($_POST['rapido'] === 'preco') {
        $v = valor_decimal($_POST['preco'] ?? '', -1);
        if ($v >= 0) { $st = $pdo->prepare("UPDATE servicos SET preco=? WHERE id=? AND empresa_id=?"); $st->execute([$v, $sid, $empresaId]); $ok = $st->rowCount() > 0; }
    } elseif (in_array($_POST['rapido'], ['esgotado', 'ativo'], true)) {
        try { $st = $pdo->prepare("UPDATE servicos SET {$_POST['rapido']}=? WHERE id=? AND empresa_id=?"); $st->execute([!empty($_POST['valor']) ? 'true' : 'false', $sid, $empresaId]); $ok = $st->rowCount() > 0; } catch (PDOException $e) {}
    } elseif ($_POST['rapido'] === 'categoria_esgotada') {
        // Esgota/repõe a categoria inteira (ex.: acabou o pão → todos os lanches)
        try { $st = $pdo->prepare("UPDATE servicos SET esgotado=? WHERE empresa_id=? AND COALESCE(categoria,'')=?"); $st->execute([!empty($_POST['valor']) ? 'true' : 'false', $empresaId, (string)($_POST['categoria'] ?? '')]); $ok = true; } catch (PDOException $e) {}
    }
    responder_json(['ok' => $ok]);
}
// Duplicar (ex.: X-Burguer → X-Bacon, só muda o nome e o preço)
if (isset($_GET['duplicar'])) {
    $o = $pdo->prepare("SELECT * FROM servicos WHERE id=? AND empresa_id=?"); $o->execute([(int)$_GET['duplicar'], $empresaId]);
    if ($o = $o->fetch()) {
        $cols = array_values(array_diff(array_keys($o), ['id', 'criado_em']));
        $vals = array_map(fn($c) => $c === 'nome' ? mb_substr($o['nome'] . ' (cópia)', 0, 150) : (is_bool($o[$c]) ? ($o[$c] ? 'true' : 'false') : $o[$c]), $cols);
        $pdo->prepare("INSERT INTO servicos (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")")->execute($vals);
        $novo = (int)$pdo->lastInsertId();
        flash('sucesso', 'Cópia criada — ajuste o nome e o preço.');
        header('Location: servicos.php?editar=' . $novo . '#form'); exit;
    }
    header('Location: servicos.php'); exit;
}
// Foto do item com IA (quando a plataforma tem gerador de imagens configurado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['foto_ia'])) {
    require_once __DIR__ . '/../includes/imagens_ia.php';
    $o = $pdo->prepare("SELECT s.*, e.nome AS empresa_nome FROM servicos s JOIN empresas e ON e.id=s.empresa_id WHERE s.id=? AND s.empresa_id=?"); $o->execute([(int)$_POST['servico_id'], $empresaId]);
    if ($o = $o->fetch()) {
        $r = img_ia_gerar(img_ia_prompt_foto('appetizing close-up of ' . $o['nome'] . ($o['descricao'] ? ' (' . $o['descricao'] . ')' : '') . ', served on a plate, food photography, top restaurant menu style', 'warm light, dark wooden table'), '4:5', 'cardapio/' . $empresaId);
        if ($r['ok']) { $pdo->prepare("UPDATE servicos SET imagem=? WHERE id=?")->execute([$r['url'], $o['id']]); flash('sucesso', 'Foto criada com IA para ' . $o['nome'] . '.'); }
        else flash('erro', $r['erro']);
    }
    header('Location: servicos.php#item-' . (int)$_POST['servico_id']); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_servico'])) {
    $id = (int)($_POST['servico_id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $preco = valor_decimal($_POST['preco'] ?? '0', -1);
    $duracao = (int)($_POST['duracao_minutos'] ?? 30);
    if ($nome === '' || $preco < 0 || $duracao < 5 || $duracao > 720) {
        flash('erro', 'Confira os dados: nome obrigatório, preço válido e duração entre 5 e 720 minutos.');
    } else {
        // Foto: arquivo enviado do celular/computador tem prioridade sobre o link
        $foto = url_valida_ou_vazia($_POST['imagem'] ?? '') ?: (str_starts_with((string)($_POST['imagem'] ?? ''), '/uploads/') ? $_POST['imagem'] : null);
        if (($_FILES['foto']['error'] ?? 4) === UPLOAD_ERR_OK) {
            $up = orc_gravar_imagem($_FILES['foto']['tmp_name'], (int)$_FILES['foto']['size'], 'cardapio/' . $empresaId, 'item-' . bin2hex(random_bytes(6)));
            if ($up) $foto = $up; else flash('erro', 'A foto não foi aceita (envie JPG/PNG de até 8 MB).');
        }
        $extras = [trim($_POST['categoria'] ?? '') ?: null, isset($_POST['destaque']) ? 'true' : 'false', $foto,
                   (int)($_POST['ordem'] ?? 0), isset($_POST['preco_a_partir']) ? 'true' : 'false'];
        if ($id) {
            $pdo->prepare("UPDATE servicos SET nome=?, descricao=?, preco=?, duracao_minutos=?, categoria=?, destaque=?, imagem=?, ordem=?, preco_a_partir=? WHERE id=? AND empresa_id=?")
                ->execute([$nome, trim($_POST['descricao'] ?? ''), $preco, $duracao, ...$extras, ...[$id, $empresaId]]);
            flash('sucesso', 'Salvo.');
        } else {
            $pdo->prepare("INSERT INTO servicos (empresa_id, nome, descricao, preco, duracao_minutos, categoria, destaque, imagem, ordem, preco_a_partir) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$empresaId, $nome, trim($_POST['descricao'] ?? ''), $preco, $duracao, ...$extras]);
            flash('sucesso', 'Adicionado — já aparece no site.');
        }
    }
    // Campos novos (opções, sugestão, retorno, turmas, aula experimental, perguntas): salvos à parte
    $sid = $id ?: (int)($pdo->query("SELECT MAX(id) FROM servicos WHERE empresa_id=" . (int)$empresaId)->fetchColumn());
    if ($sid && $nome !== '') {
        try {
            $ops = opc_de_texto($_POST['opcoes'] ?? '');
            $perg = array_slice(array_values(array_filter(array_map(fn($x) => mb_substr(trim($x), 0, 160), preg_split('/\r?\n/', (string)($_POST['perguntas'] ?? ''))))), 0, 8);
            $pdo->prepare("UPDATE servicos SET opcoes=?, sugerir=?, retorno_dias=?, vagas=?, experimental=?, perguntas=? WHERE id=? AND empresa_id=?")->execute([
                $ops ? json_encode($ops, JSON_UNESCAPED_UNICODE) : null, isset($_POST['sugerir']) ? 'true' : 'false', max(0, min(365, (int)($_POST['retorno_dias'] ?? 0))),
                max(1, min(200, (int)($_POST['vagas'] ?? 1))), isset($_POST['experimental']) ? 'true' : 'false', $perg ? json_encode($perg, JSON_UNESCAPED_UNICODE) : null, $sid, $empresaId]);
        } catch (PDOException $e) { /* banco ainda sem as colunas novas */ }
    }
    header('Location: servicos.php'); exit;
}
if (isset($_GET['ativar']) || isset($_GET['desativar'])) {
    $pdo->prepare("UPDATE servicos SET ativo=? WHERE id=? AND empresa_id=?")->execute([isset($_GET['ativar']) ? 'true' : 'false', (int)($_GET['ativar'] ?? $_GET['desativar']), $empresaId]);
    header('Location: servicos.php'); exit;
}
// Esgotado hoje (cardápio/produtos): continua no site, mas sem botão de pedir
if (isset($_GET['esgotar']) || isset($_GET['repor'])) {
    try { $pdo->prepare("UPDATE servicos SET esgotado=? WHERE id=? AND empresa_id=?")->execute([isset($_GET['esgotar']) ? 'true' : 'false', (int)($_GET['esgotar'] ?? $_GET['repor']), $empresaId]); } catch (PDOException $e) {}
    header('Location: servicos.php'); exit;
}
if (isset($_GET['excluir'])) {
    $pdo->prepare("DELETE FROM servicos WHERE id=? AND empresa_id=?")->execute([(int)$_GET['excluir'], $empresaId]);
    flash('sucesso', 'Serviço excluído.');
    header('Location: servicos.php'); exit;
}

$servicos = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM agendamentos a WHERE a.servico_id=s.id) AS usos FROM servicos s WHERE empresa_id=? ORDER BY ativo DESC, destaque DESC, ordem, nome");
$servicos->execute([$empresaId]); $servicos = $servicos->fetchAll();
$editando = null;
foreach ($servicos as $s) if ((int)$s['id'] === (int)($_GET['editar'] ?? 0)) $editando = $s;
$categorias = array_values(array_unique(array_filter(array_map(fn($s) => trim((string)$s['categoria']), $servicos))));
// Quanto cada item vendeu nos últimos 30 dias (pedidos do site, balcão e mesas)
$vendas = [];
$st = $pdo->prepare("SELECT itens FROM pedidos WHERE empresa_id=? AND itens IS NOT NULL AND status <> 'cancelado' AND criado_em > NOW() - INTERVAL '30 days' LIMIT 3000");
$st->execute([$empresaId]);
foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $js) foreach ((array)json_decode((string)$js, true) as $i) {
    $k = isset($i['id']) ? 'id' . (int)$i['id'] : 'n' . mb_strtolower(preg_replace('/\s*\(.*$/u', '', (string)($i['nome'] ?? '')));
    $vendas[$k] = ($vendas[$k] ?? 0) + (int)($i['qtd'] ?? 1);
}
$vendeu = fn($s) => $vendas['id' . $s['id']] ?? $vendas['n' . mb_strtolower($s['nome'])] ?? 0;
$grupos = [];
foreach ($servicos as $s) $grupos[trim((string)$s['categoria']) ?: 'Sem categoria'][] = $s;
$semFoto = count(array_filter($servicos, fn($s) => pg_bool($s['ativo']) && empty($s['imagem'])));
$iaFoto = false;
try { require_once __DIR__ . '/../includes/imagens_ia.php'; $iaFoto = img_ia_disponivel(); } catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="pt-br">
<?php $rotCat = modulos_rotulo_catalogo(); $comAgenda = modulo_ativo('agenda'); $ehCatalogo = modulos_atuais()['modo'] === 'catalogo';
$item = $ehCatalogo ? ($rotCat === 'Cardápio' ? 'item do cardápio' : 'produto') : 'serviço'; ?>
<head><?= painel_head($rotCat . ' - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1><?= e($rotCat) ?></h1><p class="subtitulo"><?= $ehCatalogo ? 'O que você vende — aparece no site com preço e botão de adicionar ao pedido.' : ($comAgenda ? 'O que você oferece — aparece no site com preço e no agendamento online.' : 'O que você oferece — aparece no site com o botão de pedir orçamento/contato.') ?></p></div></div>
    <?= flash_render() ?>
    <details class="form-box" id="form" <?= $editando || !$servicos ? 'open' : '' ?>>
    <summary><?= icone($editando ? 'lapis' : 'mais') ?> <?= $editando ? 'Editar ' . e($editando['nome']) : 'Novo ' . $item ?></summary>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_campo() ?>
        <input type="hidden" name="servico_id" value="<?= (int)($editando['id'] ?? 0) ?>">
        <label>Nome</label><input type="text" name="nome" required maxlength="150" value="<?= e($editando['nome'] ?? '') ?>">
        <label>Descrição (aparece no cartão do serviço no site)</label><textarea name="descricao" rows="2" maxlength="300" placeholder="<?= $ehCatalogo ? 'Ingredientes, tamanho, sabor…' : 'O que está incluso, diferenciais…' ?>"><?= e($editando['descricao'] ?? '') ?></textarea>
        <div class="form-row">
            <div><label>Preço (R$) — 0 = sob consulta</label><input type="text" inputmode="decimal" name="preco" value="<?= e(number_format((float)($editando['preco'] ?? 0), 2, ',', '')) ?>"></div>
            <?php if ($comAgenda): ?><div><label>Duração (minutos)</label><input type="number" min="5" max="720" step="5" name="duracao_minutos" value="<?= (int)($editando['duracao_minutos'] ?? 30) ?>"></div>
            <?php else: ?><input type="hidden" name="duracao_minutos" value="<?= (int)($editando['duracao_minutos'] ?? 60) ?>"><?php endif; ?>
        </div>
        <div class="form-row">
            <div><label>Categoria (agrupa os serviços no site)</label><input type="text" name="categoria" list="cats" maxlength="80" placeholder="<?= $ehCatalogo ? 'Ex: Lanches, Bebidas, Sobremesas' : 'Ex: Cabelo, Barba, Tratamentos' ?>" value="<?= e($editando['categoria'] ?? '') ?>"><datalist id="cats"><?php foreach ($categorias as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
            <div><label>Ordem no site (menor aparece primeiro)</label><input type="number" name="ordem" value="<?= (int)($editando['ordem'] ?? 0) ?>"></div>
        </div>
        <div class="form-row">
            <div><label>Foto <?= $ehCatalogo ? '(item com foto vende bem mais)' : '(opcional)' ?></label><input type="file" name="foto" accept="image/*"></div>
            <div><label>…ou link da foto</label><input type="text" name="imagem" placeholder="https://..." value="<?= e($editando['imagem'] ?? '') ?>"></div>
        </div>
        <?php if (!empty($editando['imagem'])): ?><img src="<?= e($editando['imagem']) ?>" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:10px;margin-top:8px"><?php endif; ?>
        <label class="check" style="margin-top:12px"><input type="checkbox" name="destaque" <?= $editando && pg_bool($editando['destaque'] ?? false) ? 'checked' : '' ?>> Destacar no site (aparece primeiro, com selo)</label>
        <label class="check"><input type="checkbox" name="preco_a_partir" <?= $editando && pg_bool($editando['preco_a_partir'] ?? false) ? 'checked' : '' ?>> Mostrar como "a partir de R$ …" (o preço pode variar)</label>
        <?php $comOrc = modulo_ativo('orcamentos'); ?>
        <?php if ($ehCatalogo): ?>
        <label>Opções do item (adicionais, ponto, tamanho, sabores, cor…) — uma linha por grupo</label>
        <textarea name="opcoes" rows="4" placeholder="Ponto da carne* (1): Mal passado, Ao ponto, Bem passado&#10;Adicionais (até 3): Bacon +4, Cheddar +3,50, Ovo +2&#10;Sabores* (2, maior preço): Calabresa 45, Marguerita 42"><?= e(opc_para_texto(opc_ler($editando ?? []))) ?></textarea>
        <p class="dica">* = obrigatório · (1) escolhe uma · (até 3) escolhe várias · "+4" soma ao preço · "maior preço" = meio a meio cobra o sabor mais caro. Serve também para tamanho (P, M +5, G +10) e cor.</p>
        <label class="check"><input type="checkbox" name="sugerir" <?= $editando && pg_bool($editando['sugerir'] ?? false) ? 'checked' : '' ?>> Sugerir no carrinho ("Peça também") — ótimo para bebida, sobremesa e porção</label>
        <?php endif; ?>
        <?php if ($comAgenda): ?>
        <div class="form-row">
            <div><label>Vagas por horário (1 = individual; 12 = turma)</label><input type="number" name="vagas" min="1" max="200" value="<?= (int)($editando['vagas'] ?? 1) ?: 1 ?>"></div>
            <div><label>Lembrar o cliente de voltar depois de (dias, 0 = não)</label><input type="number" name="retorno_dias" min="0" max="365" value="<?= (int)($editando['retorno_dias'] ?? 0) ?>" placeholder="Ex: 21"></div>
        </div>
        <label class="check"><input type="checkbox" name="experimental" <?= $editando && pg_bool($editando['experimental'] ?? false) ? 'checked' : '' ?>> É uma aula/avaliação experimental (vira botão em destaque no site)</label>
        <?php endif; ?>
        <?php if ($comOrc || !$comAgenda && !$ehCatalogo): ?>
        <label>Perguntas do pedido de orçamento (uma por linha)</label>
        <textarea name="perguntas" rows="3" placeholder="Quantos convidados?&#10;O local tem energia no palco?&#10;Qual a metragem?"><?= e(implode("\n", (array)json_decode((string)($editando['perguntas'] ?? ''), true))) ?></textarea>
        <?php endif; ?>
        <button type="submit" name="salvar_servico" value="1"><?= $editando ? 'Salvar alterações' : 'Adicionar ' . $item ?></button>
        <?php if ($editando): ?><a href="servicos.php" class="btn btn-ghost" style="margin-top:18px">Cancelar</a><?php endif; ?>
    </form>
    </details>
    <?php if ($servicos): ?>
    <div class="cd-barra">
        <input type="search" id="cdBusca" placeholder="Buscar <?= e($item) ?>…" aria-label="Buscar">
        <span class="muted"><?= count($servicos) ?> itens · <?= count($grupos) ?> categoria(s)</span>
        <?php if ($ehCatalogo && $semFoto): ?><span class="pill pill-aviso">📷 <?= $semFoto ?> sem foto</span><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php foreach ($grupos as $cat => $lista): $todosEsg = $ehCatalogo && !array_filter($lista, fn($x) => !pg_bool($x['esgotado'] ?? false)); ?>
    <section class="cd-grupo">
        <header>
            <h3><?= e($cat) ?> <span class="muted">(<?= count($lista) ?>)</span></h3>
            <?php if ($ehCatalogo): ?><label class="cd-chave" title="Esgota ou repõe a categoria inteira"><input type="checkbox" data-cat="<?= e($cat === 'Sem categoria' ? '' : $cat) ?>" <?= $todosEsg ? '' : 'checked' ?>> <span><?= $todosEsg ? 'Categoria esgotada' : 'Vendendo' ?></span></label><?php endif; ?>
        </header>
        <div class="cd-itens">
        <?php foreach ($lista as $s): $ativo = pg_bool($s['ativo']); $esg = pg_bool($s['esgotado'] ?? false); $vend = $vendeu($s); ?>
        <article class="cd-item <?= $ativo ? '' : 'oculto' ?> <?= $esg ? 'esgotado' : '' ?>" id="item-<?= (int)$s['id'] ?>" data-busca="<?= e(mb_strtolower($s['nome'] . ' ' . $s['descricao'] . ' ' . $s['categoria'])) ?>">
            <div class="cd-foto"><?php if ($s['imagem']): ?><img src="<?= e($s['imagem']) ?>" alt="" loading="lazy"><?php else: ?><span>📷</span><?php endif; ?></div>
            <div class="cd-info">
                <strong><?= e($s['nome']) ?></strong><?= pg_bool($s['destaque'] ?? false) ? ' <span class="pill pill-ok">destaque</span>' : '' ?><?= $ehCatalogo && pg_bool($s['sugerir'] ?? false) ? ' <span class="pill pill-neutro">peça também</span>' : '' ?>
                <?php if ($s['descricao']): ?><span class="mini"><?= e(mb_strimwidth($s['descricao'], 0, 90, '…')) ?></span><?php endif; ?>
                <span class="mini"><?= $comAgenda ? (int)$s['duracao_minutos'] . ' min · ' : '' ?><?= $ehCatalogo ? ($vend ? '🔥 ' . $vend . ' vendido(s) em 30 dias' : 'sem vendas em 30 dias') : ((int)$s['usos'] . ' agendamento(s)') ?><?= !empty($s['opcoes']) && $s['opcoes'] !== '[]' ? ' · com opções' : '' ?></span>
            </div>
            <div class="cd-preco"><span>R$</span><input type="text" inputmode="decimal" value="<?= number_format((float)$s['preco'], 2, ',', '') ?>" data-preco="<?= (int)$s['id'] ?>" aria-label="Preço de <?= e($s['nome']) ?>"></div>
            <div class="cd-acoes">
                <?php if ($ehCatalogo): ?><label class="cd-chave" title="Desligue quando acabar"><input type="checkbox" data-campo="esgotado" data-id="<?= (int)$s['id'] ?>" <?= $esg ? '' : 'checked' ?>> <span><?= $esg ? 'Esgotado' : 'Disponível' ?></span></label><?php endif; ?>
                <label class="cd-chave" title="Mostrar no site"><input type="checkbox" data-campo="ativo" data-id="<?= (int)$s['id'] ?>" <?= $ativo ? 'checked' : '' ?>> <span><?= $ativo ? 'No site' : 'Oculto' ?></span></label>
                <a class="btn btn-ghost btn-sm" href="?editar=<?= $s['id'] ?>#form" title="Editar"><?= icone('lapis', 14) ?></a>
                <a class="btn btn-ghost btn-sm" href="?duplicar=<?= $s['id'] ?><?= csrf_qs() ?>" title="Duplicar">⧉</a>
                <?php if ($iaFoto && empty($s['imagem'])): ?><form method="post" style="display:inline"><?= csrf_campo() ?><input type="hidden" name="servico_id" value="<?= (int)$s['id'] ?>"><button class="btn btn-ghost btn-sm" name="foto_ia" value="1" title="Criar foto com IA" onclick="this.textContent='⏳'">✨ Foto IA</button></form><?php endif; ?>
                <a class="btn btn-perigo btn-sm" href="?excluir=<?= $s['id'] ?><?= csrf_qs() ?>" title="Excluir" onclick="return confirm('Excluir <?= e(addslashes($s['nome'])) ?>?<?= $s['usos'] ? ' Ele aparece em ' . (int)$s['usos'] . ' agendamento(s) — prefira ocultar.' : '' ?>')"><?= icone('lixeira', 14) ?></a>
            </div>
        </article>
        <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
    <?php if (!$servicos): ?><p class="dica">Nada cadastrado ainda. Use o formulário acima para adicionar o primeiro.</p><?php endif; ?>
    <p class="dica" id="cdNada" hidden>Nenhum item com esse nome.</p>
</main>
</div>
<style>
.cd-barra { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin: 4px 0 14px; }
.cd-barra input, .cd-preco input { padding: 9px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 14px; }
.cd-barra input { width: 100%; max-width: 340px; margin: 0; }
.cd-barra input:focus, .cd-preco input:focus { border-color: var(--primary); outline: none; }
.cd-grupo { margin-bottom: 18px; }
.cd-grupo > header { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 8px; }
.cd-grupo h3 { margin: 0; font-size: 17px; }
.cd-itens { display: grid; gap: 8px; }
.cd-item { display: grid; grid-template-columns: 64px minmax(0, 1fr) 124px auto; gap: 12px; align-items: center; padding: 10px; border-radius: 14px; background: var(--surface); border: 1px solid var(--border); }
.cd-item.oculto { opacity: .55; } .cd-item.esgotado .cd-foto { filter: grayscale(1); }
.cd-foto { width: 64px; height: 64px; border-radius: 10px; overflow: hidden; background: var(--surface-2); display: grid; place-items: center; font-size: 22px; }
.cd-foto img { width: 100%; height: 100%; object-fit: cover; }
.cd-info .mini { display: block; color: var(--text-dim); font-size: 12px; margin-top: 2px; }
.cd-preco { display: flex; align-items: center; gap: 6px; color: var(--text-dim); } .cd-preco input { width: 92px; margin: 0; text-align: right; font-weight: 600; }
.cd-preco input.salvo { border-color: var(--ok); }
.cd-acoes { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
.cd-chave { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer; white-space: nowrap; margin: 0; }
.cd-chave input { appearance: none; width: 34px; height: 20px; border-radius: 20px; background: var(--border); position: relative; margin: 0; cursor: pointer; transition: background .2s; flex: none; }
.cd-chave input::after { content: ''; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: left .2s; }
.cd-chave input:checked { background: var(--ok, #22c55e); } .cd-chave input:checked::after { left: 16px; }
@media (max-width: 820px) { .cd-item { grid-template-columns: 56px minmax(0, 1fr) 118px; } .cd-acoes { grid-column: 1 / -1; justify-content: flex-start; } .cd-foto { width: 56px; height: 56px; } }
</style>
<script>
(function () {
    var csrf = <?= json_encode(csrf_token()) ?>;
    function enviar(dados) {
        var f = new FormData(); f.append('csrf_token', csrf); f.append('rapido', dados.rapido);
        Object.keys(dados).forEach(function (k) { if (k !== 'rapido') f.append(k, dados[k]); });
        return fetch('servicos.php', { method: 'POST', body: f, headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); });
    }
    document.querySelectorAll('[data-preco]').forEach(function (inp) {
        var antes = inp.value;
        inp.addEventListener('change', function () {
            enviar({ rapido: 'preco', servico_id: inp.dataset.preco, preco: inp.value }).then(function (r) {
                if (r.ok) { antes = inp.value; inp.classList.add('salvo'); setTimeout(function () { inp.classList.remove('salvo'); }, 1500); } else { inp.value = antes; alert('Preço inválido.'); }
            });
        });
        inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); inp.blur(); } });
    });
    document.querySelectorAll('[data-campo]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var campo = cb.dataset.campo, ligado = cb.checked, valor = campo === 'esgotado' ? !ligado : ligado;
            enviar({ rapido: campo, servico_id: cb.dataset.id, valor: valor ? 1 : '' }).then(function (r) {
                if (!r.ok) { cb.checked = !ligado; return; }
                var it = cb.closest('.cd-item');
                cb.nextElementSibling.textContent = campo === 'esgotado' ? (ligado ? 'Disponível' : 'Esgotado') : (ligado ? 'No site' : 'Oculto');
                it.classList.toggle(campo === 'esgotado' ? 'esgotado' : 'oculto', !ligado);
            });
        });
    });
    document.querySelectorAll('[data-cat]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            enviar({ rapido: 'categoria_esgotada', categoria: cb.dataset.cat, valor: cb.checked ? '' : 1 }).then(function (r) {
                if (!r.ok) { cb.checked = !cb.checked; return; }
                cb.nextElementSibling.textContent = cb.checked ? 'Vendendo' : 'Categoria esgotada';
                cb.closest('.cd-grupo').querySelectorAll('[data-campo=esgotado]').forEach(function (x) { x.checked = cb.checked; x.nextElementSibling.textContent = cb.checked ? 'Disponível' : 'Esgotado'; x.closest('.cd-item').classList.toggle('esgotado', !cb.checked); });
            });
        });
    });
    var busca = document.getElementById('cdBusca');
    if (busca) busca.addEventListener('input', function () {
        var q = busca.value.trim().toLowerCase(), algum = false;
        document.querySelectorAll('.cd-grupo').forEach(function (g) {
            var vis = 0; g.querySelectorAll('.cd-item').forEach(function (it) { var m = !q || it.dataset.busca.indexOf(q) >= 0; it.hidden = !m; if (m) vis++; });
            g.hidden = !vis; if (vis) algum = true;
        });
        document.getElementById('cdNada').hidden = algum;
    });
})();
</script>
</body>
</html>
