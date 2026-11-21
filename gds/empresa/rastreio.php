<?php
// ============================================================
// RASTREAMENTO AO VIVO (painel da empresa)
//   aba "mapa"      → mapa com todos os motoboys, entregas ativas, nova entrega com link do cliente
//   aba "motoboys"  → cadastro (nome, WhatsApp, PIN, veículo, cor) e envio do app
//   aba "historico" → entregas do dia, tempo de cada uma e resumo por motoboy
//   aba "ajustes"   → onde fica a loja, raio de chegada, validade do link e o mapa da TV
// Não depende de pedidos do GDS: o cardápio pode ser de outro sistema.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
exigir_modulo('rastreio');
require_once __DIR__ . '/../includes/rastreio.php';
$empresaId = (int)empresa_id_atual();
$pronto = rastreio_pronto();
$ehPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$abas = ['mapa' => 'Mapa ao vivo', 'motoboys' => 'Motoboys', 'historico' => 'Histórico', 'ajustes' => 'Loja e TV'];
$aba = isset($abas[$_GET['aba'] ?? '']) ? $_GET['aba'] : 'mapa';
$voltar = function ($aba, $extra = '') { header('Location: rastreio.php' . ($aba !== 'mapa' ? '?aba=' . $aba . $extra : ($extra ? '?' . ltrim($extra, '&') : ''))); exit; };

// Motoboy desta empresa (ou null)
$motoboyDaEmpresa = function ($id) use ($pdo, $empresaId) {
    $st = $pdo->prepare("SELECT * FROM entregadores WHERE id=? AND empresa_id=?");
    $st->execute([(int)$id, $empresaId]);
    return $st->fetch() ?: null;
};

// ---------- chamadas do mapa (fetch) ----------
$acao = (string)($_GET['acao'] ?? ($_POST['acao'] ?? ''));
if ($pronto && $acao !== '') {
    try {
        if ($acao === 'mapa') responder_json(rastreio_mapa($empresaId));
        if (!$ehPost) responder_json(['ok' => false, 'erro' => 'Use POST.'], 405);
        if ($acao === 'nova') {
            $d = ['cliente' => $_POST['cliente'] ?? '', 'telefone' => $_POST['telefone'] ?? '', 'endereco' => $_POST['endereco'] ?? '', 'codigo' => $_POST['codigo'] ?? '', 'observacao' => $_POST['observacao'] ?? ''];
            if (trim($d['cliente']) === '' && trim($d['endereco']) === '' && trim($d['codigo']) === '') responder_json(['ok' => false, 'erro' => 'Informe pelo menos o cliente, o endereço ou o número do pedido.'], 422);
            $nova = rastreio_criar_entrega($empresaId, $d, (int)($_POST['motoboy'] ?? 0) ?: null);
            responder_json(['ok' => true] + $nova);
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($acao === 'cancelar') {
            $pdo->prepare("UPDATE rastreio_entregas SET status='cancelada', encerrada_em=NOW() WHERE id=? AND empresa_id=? AND status IN ('aguardando','rota','entregue')")->execute([$id, $empresaId]);
            responder_json(['ok' => true]);
        }
        if ($acao === 'entregue') {
            $st = $pdo->prepare("SELECT entregador_id FROM rastreio_entregas WHERE id=? AND empresa_id=?"); $st->execute([$id, $empresaId]);
            $en = $st->fetchColumn();
            if ($en) rastreio_entreguei(['id' => (int)$en], $id);
            else $pdo->prepare("UPDATE rastreio_entregas SET status='entregue', entregue_em=NOW(), saiu_em=COALESCE(saiu_em, NOW()) WHERE id=? AND empresa_id=? AND status IN ('aguardando','rota')")->execute([$id, $empresaId]);
            responder_json(['ok' => true]);
        }
        if ($acao === 'atribuir') {
            $mb = (int)($_POST['motoboy'] ?? 0);
            $m = $mb ? $motoboyDaEmpresa($mb) : null;
            if ($mb && (!$m || !pg_bool($m['ativo']))) responder_json(['ok' => false, 'erro' => 'Motoboy não encontrado.'], 404);
            $pdo->prepare("UPDATE rastreio_entregas SET entregador_id=?, status=CASE WHEN ?::int IS NULL THEN 'aguardando' ELSE status END, saiu_em=CASE WHEN ?::int IS NULL THEN NULL ELSE saiu_em END
                WHERE id=? AND empresa_id=? AND status IN ('aguardando','rota')")->execute([$m ? (int)$m['id'] : null, $m ? (int)$m['id'] : null, $m ? (int)$m['id'] : null, $id, $empresaId]);
            if ($m && in_array($m['situacao'], ['rota', 'voltando'], true)) {
                // Já está na rua: a entrega entra em rota na hora
                $pdo->prepare("UPDATE rastreio_entregas SET status='rota', saiu_em=COALESCE(saiu_em, NOW()) WHERE id=? AND empresa_id=? AND status='aguardando'")->execute([$id, $empresaId]);
                $pdo->prepare("UPDATE entregadores SET situacao='rota' WHERE id=? AND situacao='voltando'")->execute([(int)$m['id']]);
            }
            responder_json(['ok' => true]);
        }
        if ($acao === 'voltou') {
            if ($m = $motoboyDaEmpresa($id)) rastreio_voltei((int)$m['id']);
            responder_json(['ok' => true]);
        }
        if ($acao === 'buscar_endereco') {
            $g = rastreio_geocodificar((string)($_POST['endereco'] ?? ''), ['loja_lat' => null, 'loja_lng' => null]);
            responder_json($g ? ['ok' => true] + $g : ['ok' => false, 'erro' => 'Não achei esse endereço no mapa. Escreva rua, número, bairro e cidade, ou toque no mapa.'], $g ? 200 : 404);
        }
        responder_json(['ok' => false, 'erro' => 'Ação desconhecida.'], 400);
    } catch (Throwable $e) {
        error_log('GDS rastreio painel: ' . $e->getMessage());
        responder_json(['ok' => false, 'erro' => 'Falha no servidor. Tente de novo.'], 500);
    }
}

// ---------- formulários (com recarga) ----------
if ($pronto && $ehPost) {
    if (isset($_POST['salvar_motoboy'])) {
        $id = (int)($_POST['motoboy_id'] ?? 0);
        $nome = mb_substr(trim(strip_tags((string)($_POST['nome'] ?? ''))), 0, 100);
        $tel = mb_substr(preg_replace('/\D/', '', (string)($_POST['telefone'] ?? '')), 0, 20);
        $veiculo = mb_substr(trim(strip_tags((string)($_POST['veiculo'] ?? ''))), 0, 60) ?: null;
        $cor = in_array($_POST['cor'] ?? '', RASTREIO_CORES, true) ? $_POST['cor'] : null;
        $pin = trim((string)($_POST['pin'] ?? ''));
        $atual = $id ? $motoboyDaEmpresa($id) : null;
        if ($nome === '') { flash('erro', 'Informe o nome do motoboy.'); $voltar('motoboys', $id ? '&editar=' . $id : ''); }
        if (strlen($tel) < 10) { flash('erro', 'Informe o WhatsApp do motoboy com DDD (é com ele que ele entra no app).'); $voltar('motoboys', $id ? '&editar=' . $id : ''); }
        if ($pin !== '' && !preg_match('/^\d{4,8}$/', $pin)) { flash('erro', 'O PIN precisa ter de 4 a 8 números.'); $voltar('motoboys', $id ? '&editar=' . $id : ''); }
        $hash = $pin !== '' ? password_hash($pin, PASSWORD_DEFAULT) : null;
        if ($atual) {
            $pdo->prepare("UPDATE entregadores SET nome=?, telefone=?, veiculo=?, cor=?, pin_hash=COALESCE(?, pin_hash), app_token=COALESCE(app_token, ?) WHERE id=? AND empresa_id=?")
                ->execute([$nome, $tel, $veiculo, $cor, $hash, rastreio_token(), $id, $empresaId]);
            flash('sucesso', 'Motoboy atualizado.');
        } else {
            $pdo->prepare("INSERT INTO entregadores (empresa_id, nome, telefone, veiculo, cor, pin_hash, app_token) VALUES (?,?,?,?,?,?,?)")
                ->execute([$empresaId, $nome, $tel, $veiculo, $cor, $hash, rastreio_token()]);
            flash('sucesso', 'Motoboy cadastrado. Toque em "Enviar o app" para mandar o acesso pelo WhatsApp.');
        }
        $voltar('motoboys');
    }
    if (isset($_POST['alternar_motoboy']) && ($m = $motoboyDaEmpresa($_POST['alternar_motoboy']))) {
        if (pg_bool($m['ativo'])) rastreio_voltei((int)$m['id']);
        $pdo->prepare("UPDATE entregadores SET ativo = NOT ativo WHERE id=? AND empresa_id=?")->execute([(int)$m['id'], $empresaId]);
        flash('sucesso', pg_bool($m['ativo']) ? $m['nome'] . ' foi pausado: o app dele para de funcionar até você ativar de novo.' : $m['nome'] . ' está ativo de novo.');
        $voltar('motoboys');
    }
    if (isset($_POST['novo_link']) && ($m = $motoboyDaEmpresa($_POST['novo_link']))) {
        $pdo->prepare("UPDATE entregadores SET app_token=? WHERE id=? AND empresa_id=?")->execute([rastreio_token(), (int)$m['id'], $empresaId]);
        flash('sucesso', 'Link novo criado para ' . $m['nome'] . '. O link antigo parou de funcionar; envie o novo pelo WhatsApp.');
        $voltar('motoboys');
    }
    if (isset($_POST['salvar_loja'])) {
        $lat = filter_var($_POST['loja_lat'] ?? '', FILTER_VALIDATE_FLOAT); $lng = filter_var($_POST['loja_lng'] ?? '', FILTER_VALIDATE_FLOAT);
        $ok = $lat !== false && $lng !== false && abs($lat) <= 90 && abs($lng) <= 180 && !($lat == 0 && $lng == 0);
        $raio = max(30, min(500, (int)($_POST['rastreio_raio'] ?? 80)));
        $validade = max(1, min(24, (int)($_POST['rastreio_validade'] ?? 4)));
        $pdo->prepare("UPDATE empresas SET loja_lat=?, loja_lng=?, rastreio_raio=?, rastreio_validade=? WHERE id=?")
            ->execute([$ok ? $lat : null, $ok ? $lng : null, $raio, $validade, $empresaId]);
        flash('sucesso', $ok ? 'Pronto! A loja está marcada no mapa e o "voltei" automático está ligado.' : 'Ajustes salvos. Marque a loja no mapa para ligar o "voltei" automático.');
        $voltar('ajustes');
    }
    if (isset($_POST['tv_gerar'])) {
        $pdo->prepare("UPDATE empresas SET rastreio_tv_token=? WHERE id=?")->execute([rastreio_token(), $empresaId]);
        flash('sucesso', 'Link da TV criado. Abra no navegador da TV ou do computador.');
        $voltar('ajustes');
    }
    if (isset($_POST['tv_desligar'])) {
        $pdo->prepare("UPDATE empresas SET rastreio_tv_token=NULL WHERE id=?")->execute([$empresaId]);
        flash('sucesso', 'Link da TV desligado. A tela que estava aberta para de atualizar.');
        $voltar('ajustes');
    }
}

// ---------- dados da tela ----------
$empresa = [];
$motoboys = [];
if ($pronto) {
    $st = $pdo->prepare("SELECT e.*, s.slug AS segmento_slug FROM empresas e LEFT JOIN segmentos s ON s.id = e.segmento_id WHERE e.id=?");
    $st->execute([$empresaId]);
    $empresa = $st->fetch() ?: [];
    $st = $pdo->prepare("SELECT * FROM entregadores WHERE empresa_id=? ORDER BY ativo DESC, nome");
    $st->execute([$empresaId]);
    $motoboys = $st->fetchAll();
}
$ativos = array_values(array_filter($motoboys, fn($m) => pg_bool($m['ativo'])));
$temLoja = ($empresa['loja_lat'] ?? null) !== null;
$linkSite = rastreio_url_base() . '/' . ($empresa['slug'] ?? '');
$linkTv = !empty($empresa['rastreio_tv_token']) ? rastreio_link_tv($empresa['rastreio_tv_token']) : '';
$wa = function ($tel, $texto) {
    $t = preg_replace('/\D/', '', (string)$tel);
    if ($t !== '' && strlen($t) <= 11) $t = '55' . $t;
    return 'https://wa.me/' . $t . '?text=' . rawurlencode($texto);
};
$fmtTel = function ($t) { $d = preg_replace('/\D/', '', (string)$t); return strlen($d) === 11 ? sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7)) : (strlen($d) === 10 ? sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6)) : $t); };
$rotulos = ['aguardando' => 'Preparando', 'rota' => 'Em rota', 'entregue' => 'Entregue', 'encerrada' => 'Concluída', 'cancelada' => 'Cancelada'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head($abas[$aba] . ' · Rastreamento') ?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow:wght@500;600;700&family=Barlow+Semi+Condensed:wght@600;700&display=swap">
<?php if (in_array($aba, ['mapa', 'ajustes'], true)): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="/assets/js/rastreio-mapa.js?v=1"></script>
<?php endif; ?>
<link rel="stylesheet" href="/assets/css/rastreio.css?v=1">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header">
        <div><h1><?= e($abas[$aba]) ?></h1><p class="subtitulo"><?= [
            'mapa' => 'Todos os motoboys ao vivo. Crie a entrega, mande o link para o cliente e acompanhe até ele voltar.',
            'motoboys' => 'Cadastre quem entrega para você e mande o app pelo WhatsApp.',
            'historico' => 'Quantas entregas, quanto tempo cada uma levou e quem levou.',
            'ajustes' => 'Onde fica a loja, quanto tempo o link do cliente vale e o mapa para a TV.'][$aba] ?></p></div>
        <nav class="acoes rz-abas" aria-label="Seções do rastreamento">
            <?php foreach ($abas as $k => $r): ?><a class="btn btn-sm <?= $k === $aba ? 'btn-ligado' : 'btn-ghost' ?>" href="rastreio.php<?= $k !== 'mapa' ? '?aba=' . $k : '' ?>"<?= $k === $aba ? ' aria-current="page"' : '' ?>><?= e($r) ?></a><?php endforeach; ?>
            <?php if ($linkTv): ?><a class="btn btn-sm btn-ghost" href="<?= e($linkTv) ?>" target="_blank" rel="noopener"><?= icone('desktop', 14) ?> Abrir mapa da TV</a><?php endif; ?>
        </nav>
    </div>
    <?= flash_render() ?>
<?php if (!$pronto): ?>
    <div class="vazio-box"><h2>Preparando o rastreamento…</h2><p>O banco está sendo atualizado. Recarregue a página em alguns segundos.</p></div>
<?php elseif ($aba === 'mapa'): ?>
    <?php if (!$ativos || !$temLoja): ?>
    <div class="rz-primeiros form-box largo">
        <h3>Para começar</h3>
        <ol>
            <li class="<?= $ativos ? 'feito' : '' ?>"><a href="?aba=motoboys">Cadastre os motoboys</a> e mande o app para eles pelo WhatsApp.</li>
            <li class="<?= $temLoja ? 'feito' : '' ?>"><a href="?aba=ajustes">Marque onde fica a loja</a>: assim o link do cliente expira sozinho quando o motoboy volta.</li>
            <li>Crie a entrega aqui (ou o motoboy cria no app) e mande o link para o cliente.</li>
        </ol>
    </div>
    <?php endif; ?>
    <div class="rz-painel" id="rzPainel" data-loja='<?= e(json_encode($temLoja ? ['lat' => (float)$empresa['loja_lat'], 'lng' => (float)$empresa['loja_lng']] : null)) ?>' data-empresa="<?= e($empresa['nome'] ?? '') ?>">
        <div class="rz-painel-mapa">
            <div class="rz-mapa" id="rzMapa" role="region" aria-label="Mapa com a posição dos motoboys"></div>
            <div class="rz-mapa-selo"><span class="rz-vivo" id="rzVivo">Conectando…</span><span class="rz-mute rz-num" id="rzHora"></span></div>
            <button type="button" class="btn btn-sm btn-ghost rz-mapa-ajustar" id="rzAjustar">Ver todos</button>
        </div>
        <div class="rz-painel-lado">
            <div class="rz-kpis" aria-live="polite">
                <div><strong class="rz-num" id="kRota">0</strong><span>em rota</span></div>
                <div><strong class="rz-num" id="kVoltando">0</strong><span>voltando</span></div>
                <div><strong class="rz-num" id="kLoja">0</strong><span>na loja</span></div>
                <div><strong class="rz-num" id="kHoje">0</strong><span>entregues hoje</span></div>
                <div><strong class="rz-num" id="kMedia">–</strong><span>tempo médio</span></div>
            </div>

            <section class="form-box largo rz-caixa">
                <h3>Nova entrega</h3>
                <form id="formNova" autocomplete="off">
                    <?= csrf_campo() ?><input type="hidden" name="acao" value="nova">
                    <label>Quem leva?</label>
                    <div class="rz-escolha" role="radiogroup" aria-label="Motoboy">
                        <label><input type="radio" name="motoboy" value="" checked><span>Ainda não sei</span></label>
                        <?php foreach ($ativos as $m): ?><label><input type="radio" name="motoboy" value="<?= (int)$m['id'] ?>"><span><i style="--cor:<?= e(rastreio_cor($m)) ?>"></i><?= e(rastreio_primeiro_nome($m['nome'])) ?></span></label><?php endforeach; ?>
                    </div>
                    <div class="rz-duas">
                        <div><label for="nvCliente">Cliente</label><input id="nvCliente" name="cliente" maxlength="100" placeholder="Nome"></div>
                        <div><label for="nvTel">WhatsApp do cliente</label><input id="nvTel" name="telefone" type="tel" inputmode="tel" maxlength="20" placeholder="(11) 99999-9999"></div>
                    </div>
                    <label for="nvEnd">Endereço</label><input id="nvEnd" name="endereco" maxlength="255" placeholder="Rua, número, bairro">
                    <div class="rz-duas">
                        <div><label for="nvCod">Nº do pedido</label><input id="nvCod" name="codigo" maxlength="30" inputmode="numeric" placeholder="Opcional"></div>
                        <div><label for="nvObs">Observação</label><input id="nvObs" name="observacao" maxlength="255" placeholder="Opcional"></div>
                    </div>
                    <div id="nvErro" class="alert alert-erro" role="alert" hidden></div>
                    <button type="submit" id="nvBotao">Criar e gerar o link do cliente</button>
                </form>
                <div id="nvPronto" class="rz-pronto" hidden>
                    <strong>Link do cliente pronto</strong>
                    <input type="text" readonly id="nvLink" aria-label="Link do cliente">
                    <div class="rz-pronto-acoes"><a class="btn btn-sm" id="nvWa" target="_blank" rel="noopener"><?= icone('chat', 14) ?> Enviar no WhatsApp</a><button type="button" class="btn btn-sm btn-ghost" id="nvCopiar">Copiar link</button><button type="button" class="btn btn-sm btn-ghost" id="nvOutra">Outra entrega</button></div>
                    <p class="dica">O link mostra o motoboy no mapa só enquanto ele vai até o cliente e expira quando ele volta para a loja.</p>
                </div>
            </section>

            <section class="form-box largo rz-caixa">
                <h3>Motoboys</h3>
                <div id="listaMotos"><p class="dica">Carregando…</p></div>
            </section>

            <section class="form-box largo rz-caixa">
                <h3>Entregas de agora</h3>
                <div id="listaEntregas"><p class="dica">Carregando…</p></div>
            </section>
        </div>
    </div>
    <script src="/assets/js/rastreio-painel.js?v=1" defer></script>

<?php elseif ($aba === 'motoboys'):
    $editando = null;
    foreach ($motoboys as $m) if ((int)$m['id'] === (int)($_GET['editar'] ?? 0)) $editando = $m;
    $veiculos = ['Moto', 'Bicicleta', 'Carro', 'A pé'];
?>
    <div class="rz-motoboys">
        <section class="form-box largo">
            <h3><?= $editando ? 'Editar ' . e($editando['nome']) : 'Cadastrar motoboy' ?></h3>
            <form method="post" action="rastreio.php?aba=motoboys">
                <?= csrf_campo() ?><input type="hidden" name="motoboy_id" value="<?= (int)($editando['id'] ?? 0) ?>">
                <label for="mbNome">Nome</label><input id="mbNome" name="nome" maxlength="100" required value="<?= e($editando['nome'] ?? '') ?>">
                <label for="mbTel">WhatsApp (com DDD)</label><input id="mbTel" name="telefone" type="tel" inputmode="tel" maxlength="20" required value="<?= e(!empty($editando['telefone']) ? $fmtTel($editando['telefone']) : '') ?>" placeholder="(11) 99999-9999">
                <label for="mbPin">PIN do app (4 a 8 números)</label><input id="mbPin" name="pin" inputmode="numeric" pattern="[0-9]{4,8}" maxlength="8" autocomplete="new-password" placeholder="<?= !empty($editando['pin_hash']) ? 'Deixe em branco para manter o atual' : 'Ex.: 4821' ?>">
                <p class="dica">Com o WhatsApp e o PIN, ele entra pelo site <strong><?= e(preg_replace('#^https?://#', '', $linkSite)) ?></strong> em qualquer celular. Sem PIN, ele só entra pelo link que você mandar.</p>
                <label>Veículo</label>
                <input type="hidden" name="veiculo" value="<?= e($editando['veiculo'] ?? 'Moto') ?>">
                <div class="ed-presets" data-preset-alvo="veiculo"><?php foreach ($veiculos as $v): ?><button type="button" class="btn btn-ghost btn-sm" data-preset="<?= e($v) ?>"><?= e($v) ?></button><?php endforeach; ?></div>
                <label>Cor no mapa</label>
                <div class="rz-cores" role="radiogroup" aria-label="Cor no mapa">
                    <?php $corAtual = $editando ? rastreio_cor($editando) : RASTREIO_CORES[count($motoboys) % count(RASTREIO_CORES)];
                    $nomesCor = ['Amarelo', 'Azul', 'Rosa', 'Lilás', 'Verde', 'Laranja', 'Vermelho', 'Ciano'];
                    foreach (RASTREIO_CORES as $i => $c): ?><label title="<?= e($nomesCor[$i] ?? '') ?>"><input type="radio" name="cor" value="<?= e($c) ?>" aria-label="<?= e($nomesCor[$i] ?? 'Cor ' . ($i + 1)) ?>"<?= $c === $corAtual ? ' checked' : '' ?>><span style="--cor:<?= e($c) ?>"></span></label><?php endforeach; ?>
                </div>
                <button type="submit" name="salvar_motoboy" value="1"><?= $editando ? 'Salvar' : 'Cadastrar motoboy' ?></button>
                <?php if ($editando): ?><a class="btn btn-ghost" href="rastreio.php?aba=motoboys" style="margin-top:18px">Cancelar</a><?php endif; ?>
            </form>
        </section>
        <section class="form-box largo">
            <h3>Equipe de entrega</h3>
            <?php if (!$motoboys): ?><p class="dica">Ninguém cadastrado ainda. Cadastre o primeiro motoboy ao lado.</p><?php endif; ?>
            <ul class="rz-equipe">
            <?php foreach ($motoboys as $m):
                $on = pg_bool($m['ativo']);
                $linkApp = $m['app_token'] ? rastreio_link_app($m['app_token']) : '';
                $msg = 'Oi, ' . rastreio_primeiro_nome($m['nome']) . '! Este é o seu app de entregas da ' . ($empresa['nome'] ?? '') . ":\n" . $linkApp
                     . "\n\nAbra no celular, toque em \"Saí para entregar\" quando sair e deixe a tela aberta durante a entrega. Dica: no menu do navegador, toque em \"Adicionar à tela inicial\".";
            ?>
                <li class="<?= $on ? '' : 'pausado' ?>">
                    <span class="rz-av" style="--cor:<?= e(rastreio_cor($m)) ?>"><?= e(rastreio_iniciais($m['nome'])) ?></span>
                    <div>
                        <strong><?= e($m['nome']) ?></strong>
                        <small><?= e($m['telefone'] ? $fmtTel($m['telefone']) : 'sem WhatsApp') ?> · <?= e($m['veiculo'] ?: 'Moto') ?> · <?= !empty($m['pin_hash']) ? 'com PIN' : 'sem PIN' ?><?= $on ? '' : ' · pausado' ?></small>
                    </div>
                    <div class="rz-equipe-acoes">
                        <?php if ($on && $linkApp): ?>
                        <a class="btn btn-sm" href="<?= e($wa($m['telefone'], $msg)) ?>" target="_blank" rel="noopener"><?= icone('chat', 14) ?> Enviar o app</a>
                        <button type="button" class="btn btn-sm btn-ghost" data-copiar="<?= e($linkApp) ?>">Copiar link</button>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-ghost" href="?aba=motoboys&amp;editar=<?= (int)$m['id'] ?>" aria-label="Editar <?= e($m['nome']) ?>"><?= icone('lapis', 14) ?></a>
                        <form method="post" action="rastreio.php?aba=motoboys"><?= csrf_campo() ?><button type="submit" class="btn btn-sm btn-ghost" name="alternar_motoboy" value="<?= (int)$m['id'] ?>"><?= $on ? 'Pausar' : 'Ativar' ?></button></form>
                        <?php if ($on): ?><form method="post" action="rastreio.php?aba=motoboys" onsubmit="return confirm('Criar um link novo? O celular que usa o link antigo sai do app.')"><?= csrf_campo() ?><button type="submit" class="btn btn-sm btn-ghost" name="novo_link" value="<?= (int)$m['id'] ?>">Link novo</button></form><?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
            </ul>
            <p class="dica">"Link novo" desconecta o celular antigo (ex.: trocou de aparelho ou saiu da equipe). "Pausar" bloqueia o app até você ativar de novo.</p>
        </section>
    </div>
    <script src="/assets/js/rastreio-painel.js?v=1" defer></script>

<?php elseif ($aba === 'historico'):
    $dia = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dia'] ?? '') ? $_GET['dia'] : date('Y-m-d');
    $st = $pdo->prepare("SELECT r.*, en.nome AS motoboy_nome, ROUND(EXTRACT(EPOCH FROM r.entregue_em - r.saiu_em) / 60) AS minutos
        FROM rastreio_entregas r LEFT JOIN entregadores en ON en.id = r.entregador_id
        WHERE r.empresa_id=? AND r.criado_em::date = ? ORDER BY r.id DESC LIMIT 300");
    $st->execute([$empresaId, $dia]);
    $lista = $st->fetchAll();
    $feitas = array_filter($lista, fn($r) => $r['entregue_em'] !== null);
    $porMoto = [];
    foreach ($feitas as $r) {
        $k = $r['motoboy_nome'] ?: 'Sem motoboy';
        $porMoto[$k]['n'] = ($porMoto[$k]['n'] ?? 0) + 1;
        if ($r['minutos'] !== null) $porMoto[$k]['min'][] = (int)$r['minutos'];
    }
    $tempos = array_filter(array_map(fn($r) => $r['minutos'], $feitas), fn($x) => $x !== null);
?>
    <section class="form-box largo">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
            <h3 style="margin:0">Entregas de <?= date('d/m/Y', strtotime($dia)) ?></h3>
            <form method="get" class="rz-dia"><input type="hidden" name="aba" value="historico">
                <a class="btn btn-sm btn-ghost" href="?aba=historico&amp;dia=<?= date('Y-m-d', strtotime($dia . ' -1 day')) ?>" aria-label="Dia anterior">←</a>
                <input type="date" name="dia" value="<?= e($dia) ?>" onchange="this.form.submit()" aria-label="Escolher o dia" style="margin:0">
                <?php if ($dia < date('Y-m-d')): ?><a class="btn btn-sm btn-ghost" href="?aba=historico&amp;dia=<?= date('Y-m-d', strtotime($dia . ' +1 day')) ?>" aria-label="Próximo dia">→</a><?php endif; ?>
            </form>
        </div>
        <div class="rz-kpis rz-kpis-claros">
            <div><strong class="rz-num"><?= count($lista) ?></strong><span>entregas criadas</span></div>
            <div><strong class="rz-num"><?= count($feitas) ?></strong><span>entregues</span></div>
            <div><strong class="rz-num"><?= $tempos ? round(array_sum($tempos) / count($tempos)) . ' min' : '–' ?></strong><span>tempo médio</span></div>
            <div><strong class="rz-num"><?= $tempos ? max($tempos) . ' min' : '–' ?></strong><span>a mais demorada</span></div>
        </div>
        <?php if ($porMoto): ?>
        <h4>Por motoboy</h4>
        <div class="tabela-wrap" tabindex="0" role="region" aria-label="Entregas por motoboy"><table><thead><tr><th>Motoboy</th><th>Entregas</th><th>Tempo médio</th></tr></thead><tbody>
        <?php foreach ($porMoto as $nome => $x): ?><tr><td><?= e($nome) ?></td><td><?= (int)$x['n'] ?></td><td><?= !empty($x['min']) ? round(array_sum($x['min']) / count($x['min'])) . ' min' : '–' ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
        <h4 style="margin-top:14px">Todas</h4>
        <?php if (!$lista): ?><p class="dica">Nenhuma entrega neste dia.</p><?php else: ?>
        <div class="tabela-wrap" tabindex="0" role="region" aria-label="Todas as entregas do dia"><table><thead><tr><th>Criada</th><th>Pedido</th><th>Cliente</th><th>Motoboy</th><th>Saiu</th><th>Entregue</th><th>Tempo</th><th>Situação</th></tr></thead><tbody>
        <?php foreach ($lista as $r): ?><tr>
            <td><?= date('H:i', strtotime($r['criado_em'])) ?></td><td><?= e($r['codigo'] ?: '–') ?></td>
            <td><?= e($r['cliente_nome'] ?: '–') ?><?php if ($r['endereco']): ?><small style="display:block;color:var(--text-dim)"><?= e($r['endereco']) ?></small><?php endif; ?></td>
            <td><?= e($r['motoboy_nome'] ?: '–') ?></td>
            <td><?= $r['saiu_em'] ? date('H:i', strtotime($r['saiu_em'])) : '–' ?></td><td><?= $r['entregue_em'] ? date('H:i', strtotime($r['entregue_em'])) : '–' ?></td>
            <td><?= $r['minutos'] !== null ? (int)$r['minutos'] . ' min' : '–' ?></td>
            <td><span class="rz-chip" data-s="<?= e($r['status']) ?>"><?= e($rotulos[$r['status']] ?? $r['status']) ?></span></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
        <p class="dica">O trajeto de cada motoboy fica guardado só por 3 dias. O cliente perde o acesso ao link quando o motoboy volta.</p>
    </section>

<?php else: /* ajustes */ ?>
    <div class="rz-ajustes">
        <form method="post" action="rastreio.php?aba=ajustes" class="form-box largo" id="formLoja">
            <?= csrf_campo() ?>
            <h3>Onde fica a loja</h3>
            <p class="dica" style="margin-bottom:10px">É daqui que os motoboys saem. Quando um motoboy volta para perto da loja, o app marca "voltei" sozinho e os links dos clientes expiram.</p>
            <div class="rz-loja-mapa" id="lojaMapa" role="region" aria-label="Mapa para marcar a loja" data-loja='<?= e(json_encode($temLoja ? ['lat' => (float)$empresa['loja_lat'], 'lng' => (float)$empresa['loja_lng']] : null)) ?>'></div>
            <div class="rz-loja-botoes">
                <button type="button" class="btn btn-sm" id="lojaAqui"><?= icone('casa', 14) ?> Estou na loja: usar minha localização</button>
                <span class="rz-busca"><input type="text" id="lojaEndereco" placeholder="Ou digite o endereço da loja" aria-label="Endereço da loja"><button type="button" class="btn btn-sm btn-ghost" id="lojaBuscar">Buscar</button></span>
            </div>
            <p class="dica" id="lojaInfo"><?= $temLoja ? 'Loja marcada. Toque no mapa ou arraste o marcador para ajustar.' : 'Toque no mapa para marcar a loja.' ?></p>
            <input type="hidden" name="loja_lat" id="lojaLat" value="<?= e($empresa['loja_lat'] ?? '') ?>"><input type="hidden" name="loja_lng" id="lojaLng" value="<?= e($empresa['loja_lng'] ?? '') ?>">
            <div class="rz-duas">
                <div>
                    <label>Chegou na loja quando estiver a</label>
                    <input type="hidden" name="rastreio_raio" value="<?= (int)($empresa['rastreio_raio'] ?? 80) ?>">
                    <div class="ed-presets" data-preset-alvo="rastreio_raio"><?php foreach ([50, 80, 150, 300] as $r): ?><button type="button" class="btn btn-ghost btn-sm" data-preset="<?= $r ?>"><?= $r ?> m</button><?php endforeach; ?></div>
                </div>
                <div>
                    <label>O link do cliente vale no máximo</label>
                    <input type="hidden" name="rastreio_validade" value="<?= (int)($empresa['rastreio_validade'] ?? 4) ?>">
                    <div class="ed-presets" data-preset-alvo="rastreio_validade"><?php foreach ([2, 4, 6, 12] as $h): ?><button type="button" class="btn btn-ghost btn-sm" data-preset="<?= $h ?>"><?= $h ?> horas</button><?php endforeach; ?></div>
                </div>
            </div>
            <p class="dica">A validade é uma segurança extra: o link já expira quando o motoboy volta. Ela vale caso ninguém marque a volta.</p>
            <button type="submit" name="salvar_loja" value="1">Salvar</button>
        </form>

        <section class="form-box largo">
            <h3>Mapa para a TV</h3>
            <p class="dica" style="margin-bottom:10px">Uma tela cheia com todos os motoboys ao vivo, para deixar aberta na TV da loja ou no computador. Não mostra nome nem endereço de cliente.</p>
            <?php if ($linkTv): ?>
            <input type="text" readonly value="<?= e($linkTv) ?>" aria-label="Link do mapa da TV" onclick="this.select()">
            <div class="rz-loja-botoes">
                <a class="btn btn-sm" href="<?= e($linkTv) ?>" target="_blank" rel="noopener"><?= icone('desktop', 14) ?> Abrir agora</a>
                <button type="button" class="btn btn-sm btn-ghost" data-copiar="<?= e($linkTv) ?>">Copiar link</button>
                <form method="post" action="rastreio.php?aba=ajustes" onsubmit="return confirm('Gerar um link novo? A TV com o link antigo para de atualizar.')"><?= csrf_campo() ?><button type="submit" class="btn btn-sm btn-ghost" name="tv_gerar" value="1">Link novo</button></form>
                <form method="post" action="rastreio.php?aba=ajustes"><?= csrf_campo() ?><button type="submit" class="btn btn-sm btn-perigo" name="tv_desligar" value="1">Desligar</button></form>
            </div>
            <p class="dica">Na TV: abra o navegador, digite o link e aperte "tela cheia". Dica: mande o link para você no WhatsApp e abra pelo celular para conferir.</p>
            <?php else: ?>
            <form method="post" action="rastreio.php?aba=ajustes"><?= csrf_campo() ?><button type="submit" name="tv_gerar" value="1"><?= icone('desktop', 14) ?> Criar o link da TV</button></form>
            <?php endif; ?>
        </section>

        <section class="form-box largo">
            <h3>Site dos motoboys</h3>
            <p class="dica" style="margin-bottom:10px">O seu site é a entrada do app: o motoboy abre, digita o WhatsApp e o PIN e já começa.</p>
            <input type="text" readonly value="<?= e($linkSite) ?>" aria-label="Site dos motoboys" onclick="this.select()">
            <div class="rz-loja-botoes"><a class="btn btn-sm btn-ghost" href="<?= e($linkSite) ?>" target="_blank" rel="noopener"><?= icone('globo', 14) ?> Abrir o site</a><button type="button" class="btn btn-sm btn-ghost" data-copiar="<?= e($linkSite) ?>">Copiar</button></div>
        </section>
    </div>
    <script src="/assets/js/rastreio-painel.js?v=1" defer></script>
<?php endif; ?>
</main>
</div>
</body>
</html>
