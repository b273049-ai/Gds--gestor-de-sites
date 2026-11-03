<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
exigir_modulo('orcamentos', 'delivery');
require_once __DIR__ . '/../includes/orcamento.php';
require_once __DIR__ . '/../includes/pedidos_fluxo.php';
$empresaId = (int)empresa_id_atual();
$empresa = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();

$statusNomes = ['novo' => 'Novo', 'orcado' => 'Orçamento enviado', 'aprovado' => 'Aprovado', 'em_andamento' => 'Em andamento',
                'pronto' => 'Pronto p/ retirada', 'saiu_entrega' => 'Saiu para entrega', 'concluido' => 'Concluído', 'cancelado' => 'Cancelado'];
$statusDelivery = ['em_andamento' => 'Em preparo', 'concluido' => 'Entregue'];
$nomeStatus = fn($p) => (($p['tipo'] ?? '') === 'delivery' ? ($statusDelivery[$p['status']] ?? null) : null) ?? ($statusNomes[$p['status']] ?? $p['status']);
$voltar = fn() => 'pedidos.php' . (($q = http_build_query(array_intersect_key($_GET, ['status' => 1, 'tipo' => 1]))) ? '?' . $q : '');

// Contador para o alerta sonoro (a tela pergunta a cada 15s se chegou pedido novo)
if (isset($_GET['contar'])) {
    $st = $pdo->prepare("SELECT COUNT(*) FILTER (WHERE status='novo') novos, COALESCE(MAX(id), 0) ultimo FROM pedidos WHERE empresa_id=?");
    $st->execute([$empresaId]);
    responder_json(['ok' => true] + array_map('intval', $st->fetch()));
}
// Abrir / pausar a loja (pedidos pelo site)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['loja_pausada'])) {
    try {
        $pdo->prepare("UPDATE empresas SET loja_pausada=? WHERE id=?")->execute([$_POST['loja_pausada'] === '1' ? 'true' : 'false', $empresaId]);
        flash('sucesso', $_POST['loja_pausada'] === '1' ? 'Loja pausada: o site parou de receber pedidos (os clientes veem "Pedir no WhatsApp").' : 'Loja aberta: o site voltou a receber pedidos.');
    } catch (PDOException $e) { flash('erro', 'Atualize o banco (Admin → Diagnóstico) para usar esta função.'); }
    header('Location: ' . $voltar()); exit;
}

function pedido_da_empresa($id, $empresaId) {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM pedidos WHERE id=? AND empresa_id=?");
    $st->execute([$id, $empresaId]);
    return $st->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_pedido'])) {
    $clienteId = (int)($_POST['cliente_id'] ?? 0) ?: null;
    if ($clienteId) { $c = $pdo->prepare("SELECT 1 FROM clientes WHERE id=? AND empresa_id=?"); $c->execute([$clienteId, $empresaId]); if (!$c->fetch()) $clienteId = null; }
    $descricao = trim($_POST['descricao'] ?? '');
    if ($descricao === '') flash('erro', 'Descreva o pedido/orçamento.');
    else {
        $pdo->prepare("INSERT INTO pedidos (empresa_id, cliente_id, descricao, valor, veiculo_info, tipo, token) VALUES (?,?,?,?,?, 'manual', ?)")
            ->execute([$empresaId, $clienteId, $descricao, max(0, valor_decimal($_POST['valor'] ?? '0')), trim($_POST['veiculo_info'] ?? '') ?: null, bin2hex(random_bytes(16))]);
        flash('sucesso', 'Pedido registrado.');
    }
    header('Location: ' . $voltar()); exit;
}

// Responder um orçamento: valor + mensagem → cliente recebe no WhatsApp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['responder_orcamento'])) {
    $p = pedido_da_empresa((int)$_POST['pedido_id'], $empresaId);
    $valor = valor_decimal($_POST['valor'] ?? '', -1);
    // Orçamento item a item (descrição × qtd × valor): o total vira o valor do orçamento
    $orcItens = [];
    foreach ((array)($_POST['orc_nome'] ?? []) as $i => $nome) {
        $nome = mb_substr(trim(strip_tags((string)$nome)), 0, 120);
        $preco = max(0, valor_decimal($_POST['orc_preco'][$i] ?? '0'));
        $qtd = max(1, min(9999, (int)($_POST['orc_qtd'][$i] ?? 1)));
        if ($nome !== '') $orcItens[] = ['nome' => $nome, 'qtd' => $qtd, 'preco' => round($preco, 2)];
    }
    if ($orcItens) $valor = round(array_sum(array_map(fn($i) => $i['qtd'] * $i['preco'], $orcItens)), 2);
    if (!$p) flash('erro', 'Pedido não encontrado.');
    elseif ($valor < 0) flash('erro', 'Informe o valor do orçamento.');
    else {
        if (empty($p['token'])) $pdo->prepare("UPDATE pedidos SET token=? WHERE id=?")->execute([bin2hex(random_bytes(16)), $p['id']]);
        $pdo->prepare("UPDATE pedidos SET valor=?, resposta=?, itens=?, status='orcado', atualizado_em=NOW() WHERE id=?")
            ->execute([$valor, mb_substr(trim($_POST['resposta'] ?? ''), 0, 2000) ?: null, $orcItens ? json_encode($orcItens, JSON_UNESCAPED_UNICODE) : null, $p['id']]);
        $dias = max(1, min(180, (int)($_POST['validade_dias'] ?? 15)));
        try { $pdo->prepare("UPDATE pedidos SET validade = CURRENT_DATE + ?::int WHERE id=?")->execute([$dias, $p['id']]); } catch (PDOException $e) {}
        wa_adiar();
        flash('sucesso', 'Orçamento de R$ ' . number_format($valor, 2, ',', '.') . ' registrado.' . wa_resultado_para_tela(wa_avisar_status_pedido($empresa, $p['id'], 'orcado')));
    }
    header('Location: ' . $voltar()); exit;
}

// Atualizar o cliente: etapa com foto (vai para o WhatsApp e para a página do pedido)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_etapa'])) {
    $st = $pdo->prepare("SELECT p.*, c.nome AS cliente_nome, c.telefone AS cliente_telefone FROM pedidos p LEFT JOIN clientes c ON c.id = p.cliente_id WHERE p.id=? AND p.empresa_id=?");
    $st->execute([(int)$_POST['pedido_id'], $empresaId]);
    $p = $st->fetch();
    if (!$p) flash('erro', 'Pedido não encontrado.');
    else {
        if (empty($p['token'])) { $p['token'] = bin2hex(random_bytes(16)); $pdo->prepare("UPDATE pedidos SET token=? WHERE id=?")->execute([$p['token'], $p['id']]); }
        wa_adiar();
        $r = orc_adicionar_etapa($empresa, $p, (string)($_POST['etapa_texto'] ?? ''));
        if (!$r['ok']) flash('erro', $r['erro']);
        else flash('sucesso', 'Atualização registrada no pedido nº ' . $p['id'] . ($r['foto'] ? ' (com foto)' : '') . '.' . ($r['avisado'] ? ' O cliente recebe no WhatsApp com o link para acompanhar.' : ' O cliente vê no link do pedido.'));
    }
    header('Location: ' . $voltar() . '#pedido-' . (int)($_POST['pedido_id'] ?? 0)); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mudar_status']) && isset($statusNomes[$_POST['status'] ?? ''])) {
    $p = pedido_da_empresa((int)$_POST['pedido_id'], $empresaId);
    if ($p && $p['status'] !== $_POST['status']) {
        $mud = pedido_mudar_status($empresa, $p['id'], $_POST['status'], (string)($_POST['motivo'] ?? '')); // guarda a hora da etapa e avisa no WhatsApp
        // Data de evento já com outro evento fechado: avisa (não bloqueia — a empresa decide)
        $conflito = $_POST['status'] === 'aprovado' && !empty($p['data_evento']) && (int)($empresa['eventos_por_dia'] ?? 0) > 0
            && orc_eventos_na_data($empresaId, $p['data_evento'], $p['id']) >= (int)$empresa['eventos_por_dia'];
        flash('sucesso', 'Pedido nº ' . $p['id'] . ': ' . $nomeStatus(['tipo' => $p['tipo'] ?? '', 'status' => $_POST['status']]) . '.' . ($mud['msg'] ?? '')
            . ($conflito ? ' ⚠ Atenção: você já tinha evento fechado em ' . date('d/m/Y', strtotime($p['data_evento'])) . '.' : ''));
    }
    header('Location: ' . $voltar()); exit;
}

$clientes = $pdo->prepare("SELECT id, nome FROM clientes WHERE empresa_id=? ORDER BY nome"); $clientes->execute([$empresaId]); $clientes = $clientes->fetchAll();
$filtro = $_GET['status'] ?? '';
$filtroTipo = $_GET['tipo'] ?? '';
$params = [$empresaId];
$sql = "SELECT p.*, c.nome AS cliente_nome, c.telefone, c.email AS cliente_email, sv.nome AS servico_nome FROM pedidos p LEFT JOIN clientes c ON c.id = p.cliente_id LEFT JOIN servicos sv ON sv.id = p.servico_id WHERE p.empresa_id=?";
if ($filtro === 'abertos') $sql .= " AND p.status NOT IN ('concluido','cancelado')";
elseif (isset($statusNomes[$filtro])) { $sql .= " AND p.status=?"; $params[] = $filtro; }
if (in_array($filtroTipo, ['delivery', 'orcamento', 'manual'], true)) { $sql .= " AND COALESCE(p.tipo,'manual')=?"; $params[] = $filtroTipo; }
$pedidos = $pdo->prepare($sql . " ORDER BY (p.status='novo') DESC, p.criado_em DESC LIMIT 300"); $pedidos->execute($params); $pedidos = $pedidos->fetchAll();
$novos = count(array_filter($pedidos, fn($p) => $p['status'] === 'novo'));
$totAberto = array_sum(array_map(fn($p) => !in_array($p['status'], ['concluido', 'cancelado'], true) ? (float)$p['valor'] : 0, $pedidos));
$temDelivery = (bool)array_filter($pedidos, fn($p) => ($p['tipo'] ?? '') === 'delivery');

// Próximos passos de cada pedido (botões)
function pedido_proximos($p) {
    $s = $p['status'];
    if (($p['tipo'] ?? '') === 'delivery' && !empty($p['mesa'])) {
        return match ($s) { 'novo' => [['em_andamento', 'Confirmar e preparar', true]], 'em_andamento', 'pronto' => [['concluido', 'Servido na mesa', true]], default => [] };
    }
    if (($p['tipo'] ?? '') === 'delivery') {
        $final = ($p['tipo_entrega'] ?? '') === 'retirada' ? ['pronto', 'Pronto para retirada'] : ['saiu_entrega', 'Saiu para entrega'];
        return match ($s) {
            'novo' => [['em_andamento', 'Confirmar e preparar', true]],
            'em_andamento' => [[$final[0], $final[1], true]],
            'pronto', 'saiu_entrega' => [['concluido', ($p['tipo_entrega'] ?? '') === 'retirada' ? 'Retirado' : 'Entregue', true]],
            default => [],
        };
    }
    return match ($s) {
        'novo' => [], // "Responder orçamento" tem formulário próprio
        'orcado' => [['aprovado', 'Cliente aprovou', true]],
        'aprovado' => [['em_andamento', 'Iniciar', true]],
        'em_andamento' => [['concluido', 'Concluir', true]],
        default => [],
    };
}
$tipos = ['delivery' => '🛵 Delivery', 'orcamento' => '📝 Orçamento', 'manual' => 'Manual'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head(($novos ? "($novos) " : '') . modulos_rotulo_pedidos() . ' - Meu Negócio') ?>
<style>
.gds-anexos{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0}.gds-anexos img{width:72px;height:72px;object-fit:cover;border-radius:8px;display:block}
.pedido-etapas summary{cursor:pointer;font-size:.85rem;color:var(--text-dim)}.gds-etapas h4{display:none}.gds-etapas ol{list-style:none;padding:0;margin:8px 0;display:grid;gap:8px}
.gds-etapas li{border-left:3px solid var(--primary,#2563eb);padding-left:10px}.gds-etapas time{font-size:.75rem;opacity:.7}.gds-etapas p{margin:2px 0}.gds-etapas img{max-width:160px;border-radius:8px}
</style></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1><?= e(modulos_rotulo_pedidos()) ?></h1>
        <p class="subtitulo">Em aberto nesta lista: R$ <?= number_format($totAberto, 2, ',', '.') ?><?= $novos ? ' · <strong>' . $novos . ' novo(s) esperando você</strong>' : '' ?>
        · <a class="link" href="whatsapp.php">o cliente é avisado no WhatsApp a cada passo</a></p></div>
        <div class="acoes">
            <button type="button" class="btn btn-ghost btn-sm" id="alerta-som" title="Toca um aviso quando chegar pedido novo">🔔 Ativar alerta sonoro</button>
            <?php if (modulo_ativo('delivery')): $pausada = pg_bool($empresa['loja_pausada'] ?? false); ?>
            <form method="post" class="inline"><?= csrf_campo() ?><input type="hidden" name="loja_pausada" value="<?= $pausada ? '0' : '1' ?>">
                <button type="submit" class="btn btn-sm loja-status <?= $pausada ? 'fechada' : 'aberta' ?>" title="<?= $pausada ? 'Voltar a receber pedidos pelo site' : 'Parar de receber pedidos pelo site por enquanto' ?>"><span></span><?= $pausada ? 'Loja pausada — abrir' : 'Loja aberta — pausar' ?></button></form>
            <?php endif; ?>
        </div></div>
    <?php if (modulo_ativo('delivery') && pg_bool($empresa['loja_pausada'] ?? false)): ?><?= alerta('aviso', 'Sua loja está pausada: o carrinho do site está desligado e os clientes só conseguem pedir pelo WhatsApp.') ?><?php endif; ?>
    <?= flash_render() ?>
    <?php if (modulo_ativo('orcamentos')):
        $parados = [];
        try {
            $q = $pdo->prepare("SELECT p.id, p.valor, p.status, p.validade, p.criado_em, p.atualizado_em, p.token, c.nome, c.telefone,
                    EXTRACT(DAY FROM (LOCALTIMESTAMP - COALESCE(p.atualizado_em, p.criado_em)))::int AS dias
                FROM pedidos p LEFT JOIN clientes c ON c.id=p.cliente_id
                WHERE p.empresa_id=? AND COALESCE(p.tipo,'') <> 'delivery' AND ((p.status='orcado' AND COALESCE(p.atualizado_em, p.criado_em) < NOW() - INTERVAL '3 days') OR (p.status='novo' AND p.criado_em < NOW() - INTERVAL '1 day'))
                ORDER BY p.status='novo' DESC, p.atualizado_em LIMIT 8");
            $q->execute([$empresaId]); $parados = $q->fetchAll();
        } catch (PDOException $e) {}
        if ($parados): ?>
    <section class="form-box largo" style="border-color:rgba(250,204,21,.45)">
        <h3 style="margin-top:0">⏰ Precisam de atenção</h3>
        <p class="dica" style="margin-top:-6px">Orçamento enviado e sem resposta há 3+ dias, ou pedido sem orçamento há 1+ dia. Um "conseguiu ver?" fecha muito negócio.</p>
        <ul style="list-style:none;padding:0;margin:0;display:grid;gap:6px">
        <?php foreach ($parados as $x): $prim = explode(' ', (string)$x['nome'])[0];
            $txt = $x['status'] === 'novo' ? 'Oi, ' . $prim . '! Recebemos seu pedido de orçamento nº ' . $x['id'] . ' e já estamos finalizando. Posso tirar alguma dúvida rapidinho?'
                : 'Oi, ' . $prim . '! Conseguiu ver o orçamento nº ' . $x['id'] . ' (R$ ' . number_format((float)$x['valor'], 2, ',', '.') . ')? ' . (!empty($x['validade']) ? 'Ele vale até ' . date('d/m', strtotime($x['validade'])) . '. ' : '') . 'Se quiser, ajusto alguma coisa pra caber no seu orçamento 🙂' . (!empty($x['token']) ? ' ' . wa_url_publica($empresa, '/site/pedido.php?t=' . $x['token']) : ''); ?>
            <li style="display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap;padding:8px 10px;border:1px solid var(--border);border-radius:10px">
                <span>nº <?= (int)$x['id'] ?> · <strong><?= e($x['nome'] ?: 'Cliente') ?></strong> · <?= $x['status'] === 'novo' ? '<span class="pill pill-aviso">sem orçamento há ' . max(1, (int)$x['dias']) . ' dia(s)</span>' : 'R$ ' . number_format((float)$x['valor'], 2, ',', '.') . ' · <span class="pill pill-neutro">sem resposta há ' . (int)$x['dias'] . ' dias</span>' ?><?= !empty($x['validade']) && strtotime($x['validade']) < strtotime('today') ? ' <span class="pill pill-aviso">vencido</span>' : '' ?></span>
                <?php if ($x['telefone']): ?><a class="btn btn-sm" target="_blank" href="<?= e(link_whatsapp($x['telefone'], $txt)) ?>"><?= icone('chat', 14) ?> <?= $x['status'] === 'novo' ? 'Avisar' : 'Cobrar resposta' ?></a><?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; endif; ?>
    <details class="form-box">
        <summary><?= icone('mais') ?> Registrar <?= modulo_ativo('delivery') && !modulo_ativo('orcamentos') ? 'pedido' : 'orçamento' ?> manualmente</summary>
        <form method="post">
            <?= csrf_campo() ?>
            <div class="form-row">
                <div><label>Cliente</label><select name="cliente_id"><option value="">—</option><?php foreach ($clientes as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['nome']) ?></option><?php endforeach; ?></select></div>
                <div><label>Valor (R$)</label><input type="text" inputmode="decimal" name="valor" value="0,00"></div>
            </div>
            <label>Descrição / itens</label><textarea name="descricao" rows="3" required placeholder="<?= modulo_ativo('delivery') && !modulo_ativo('orcamentos') ? 'Ex: 2 smash burger + 1 batata (pedido por telefone)' : 'Ex: som e iluminação para casamento, 5h' ?>"></textarea>
            <?php if ((modulos_atuais()['empresa']['segmento_slug'] ?? '') === 'mecanica'): ?><label>Veículo (marca/modelo/ano)</label><input type="text" name="veiculo_info" placeholder="Ex: Honda Civic 2019"><?php endif; ?>
            <button type="submit" name="criar_pedido" value="1">Salvar</button>
        </form>
    </details>
    <div class="toolbar">
        <a class="btn btn-sm <?= $filtro === '' && $filtroTipo === '' ? '' : 'btn-ghost' ?>" href="pedidos.php">Todos</a>
        <a class="btn btn-sm <?= $filtro === 'abertos' ? '' : 'btn-ghost' ?>" href="?status=abertos">Em aberto</a>
        <a class="btn btn-sm <?= $filtro === 'novo' ? '' : 'btn-ghost' ?>" href="?status=novo">Novos</a>
        <?php if (modulo_ativo('delivery') && modulo_ativo('orcamentos')): ?>
        <a class="btn btn-sm <?= $filtroTipo === 'delivery' ? '' : 'btn-ghost' ?>" href="?tipo=delivery">Delivery</a>
        <a class="btn btn-sm <?= $filtroTipo === 'orcamento' ? '' : 'btn-ghost' ?>" href="?tipo=orcamento">Orçamentos</a>
        <?php endif; ?>
        <a class="btn btn-sm <?= $filtro === 'concluido' ? '' : 'btn-ghost' ?>" href="?status=concluido">Concluídos</a>
        <a class="btn btn-sm <?= $filtro === 'cancelado' ? '' : 'btn-ghost' ?>" href="?status=cancelado">Cancelados</a>
    </div>

    <div class="pedidos-lista">
    <?php foreach ($pedidos as $p):
        $tipo = $p['tipo'] ?: 'manual';
        $itens = json_decode((string)($p['itens'] ?? ''), true) ?: [];
        $aberto = !in_array($p['status'], ['concluido', 'cancelado'], true); ?>
        <article class="pedido-card status-<?= e($p['status']) ?>" id="pedido-<?= (int)$p['id'] ?>">
            <header>
                <div><strong>nº <?= (int)$p['id'] ?></strong> <span class="badge badge-<?= $tipo === 'delivery' ? 'novo' : 'site' ?>"><?= $tipos[$tipo] ?? $tipo ?></span>
                    <?php if (($p['origem'] ?? '') === 'site'): ?><span class="badge badge-site">pelo site</span><?php endif; ?>
                    <?php if (pg_bool($p['pago'] ?? false)): ?><span class="pill pill-ok">💰 Pago no Pix</span><?php endif; ?>
                    <?php if (!empty($p['mesa'])): ?><span class="pill pill-aviso">🍽 Mesa <?= e($p['mesa']) ?></span><?php endif; ?>
                    <?php if (!empty($p['aceite_em'])): ?><span class="pill pill-neutro" title="Aceite registrado: <?= e($p['aceite_nome']) ?> · IP <?= e($p['aceite_ip']) ?>">✔ aceite <?= date('d/m H:i', strtotime($p['aceite_em'])) ?></span><?php endif; ?></div>
                <span class="pill <?= $p['status'] === 'novo' ? 'pill-aviso' : ($p['status'] === 'concluido' ? 'pill-ok' : 'pill-neutro') ?>"><?= e($nomeStatus($p)) ?></span>
            </header>
            <div class="pedido-corpo">
                <div class="pedido-cliente">
                    <strong><?= e($p['cliente_nome'] ?? '—') ?></strong>
                    <?php if ($p['telefone']): ?><a class="link" target="_blank" rel="noopener" href="<?= e(link_whatsapp($p['telefone'])) ?>"><?= icone('chat', 13) ?> <?= e($p['telefone']) ?></a><?php endif; ?>
                    <?php if (!empty($p['cliente_email'])): ?><a class="link" href="mailto:<?= e($p['cliente_email']) ?>">✉ <?= e($p['cliente_email']) ?></a><?php endif; ?>
                    <small><?= date('d/m/Y H:i', strtotime($p['criado_em'])) ?></small>
                </div>
                <div class="pedido-detalhe">
                    <?php if ($itens && $tipo === 'delivery'): ?>
                        <ul class="pedido-itens"><?php foreach ($itens as $i): ?><li><b><?= (int)$i['qtd'] ?>×</b> <?= e($i['nome']) ?><?= !empty($i['obs']) ? '<em style="display:block;color:#fbbf24;font-style:normal;font-weight:600">⚠ ' . e($i['obs']) . '</em>' : '' ?> <span>R$ <?= number_format($i['preco'] * $i['qtd'], 2, ',', '.') ?></span></li><?php endforeach; ?></ul>
                        <p><?= !empty($p['mesa']) ? '🍽 Consumo no local — mesa ' . e($p['mesa']) : (($p['tipo_entrega'] ?? '') === 'retirada' ? '🏪 Retirada no local' : '🛵 Entrega: ' . e($p['endereco_entrega'])) ?><?= (float)$p['taxa_entrega'] > 0 ? ' (taxa R$ ' . number_format((float)$p['taxa_entrega'], 2, ',', '.') . ')' : '' ?></p>
                        <p>💳 <?= e($p['forma_pagamento']) ?><?= $p['troco_para'] ? ' — troco para R$ ' . number_format((float)$p['troco_para'], 2, ',', '.') : '' ?></p>
                    <?php else: ?>
                        <?php if (!empty($p['servico_nome'])): ?><strong><?= e($p['servico_nome']) ?></strong><br><?php endif; ?><?= nl2br(e($p['descricao'])) ?>
                        <?php if (!empty($p['data_evento'])): ?><span class="mini">📅 <?= date('d/m/Y', strtotime($p['data_evento'])) ?></span><?php endif; ?>
                        <?php if (!empty($p['local_evento'])): ?><span class="mini">📍 <?= e($p['local_evento']) ?></span><?php endif; ?>
                        <?php if ($p['veiculo_info']): ?><span class="mini">🚗 <?= e($p['veiculo_info']) ?></span><?php endif; ?>
                        <?php if ($itens): ?><ul class="pedido-itens" style="margin-top:8px"><?php foreach ($itens as $i): ?><li><b><?= (int)$i['qtd'] ?>×</b> <?= e($i['nome']) ?><?= !empty($i['obs']) ? '<em style="display:block;color:#fbbf24;font-style:normal;font-weight:600">⚠ ' . e($i['obs']) . '</em>' : '' ?> <span>R$ <?= number_format($i['preco'] * $i['qtd'], 2, ',', '.') ?></span></li><?php endforeach; ?></ul><?php endif; ?>
                    <?php endif; ?>
                    <?php if (!empty($p['observacoes'])): ?><p class="pedido-obs">📝 <?= e($p['observacoes']) ?></p><?php endif; ?>
                    <?php if (!empty($p['resposta'])): ?><p class="pedido-obs">💬 <?= nl2br(e($p['resposta'])) ?></p><?php endif; ?>
                    <?= frag_anexos($p['anexos'] ?? '') ?>
                    <?php if ($tipo !== 'delivery' && ($etapas = orc_etapas($p['id']))): ?><details class="pedido-etapas"><summary><?= count($etapas) ?> atualização(ões) enviada(s) ao cliente</summary><?= frag_etapas($etapas) ?></details><?php endif; ?>
                </div>
                <div class="pedido-valor"><small><?= $tipo === 'orcamento' && $p['status'] === 'novo' ? 'Referência' : 'Valor' ?></small>R$ <?= number_format((float)$p['valor'], 2, ',', '.') ?></div>
            </div>
            <?php if ($aberto): ?>
            <footer>
                <?php if ($tipo === 'orcamento' && in_array($p['status'], ['novo', 'orcado'], true)): ?>
                <details class="pedido-responder" <?= $p['status'] === 'novo' ? '' : '' ?>>
                    <summary class="btn btn-sm"><?= $p['status'] === 'novo' ? '💰 Responder orçamento' : '✏️ Alterar orçamento' ?></summary>
                    <form method="post">
                        <?= csrf_campo() ?><input type="hidden" name="pedido_id" value="<?= (int)$p['id'] ?>">
                        <label>Itens do orçamento <span class="muted">(opcional: o total soma sozinho)</span></label>
                        <div class="orc-linhas">
                            <?php foreach ($itens ?: [] as $i): ?><div class="orc-linha"><input name="orc_nome[]" maxlength="120" value="<?= e($i['nome']) ?>" placeholder="Descrição"><input name="orc_qtd[]" type="number" min="1" value="<?= (int)$i['qtd'] ?>" aria-label="Quantidade"><input name="orc_preco[]" inputmode="decimal" value="<?= number_format((float)$i['preco'], 2, ',', '') ?>" placeholder="Valor unit."><button type="button" class="btn btn-ghost btn-sm" onclick="orcRemover(this)" aria-label="Remover">✕</button></div><?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="orcLinha(this)" style="margin-bottom:8px">+ Adicionar item</button>
                        <div class="form-row">
                            <div><label>Valor total (R$)</label><input type="text" inputmode="decimal" name="valor" required value="<?= (float)$p['valor'] > 0 ? number_format((float)$p['valor'], 2, ',', '') : '' ?>" placeholder="1.500,00" <?= $itens ? 'readonly' : '' ?>></div>
                            <div></div>
                        </div>
                        <div class="form-row"><div><label>Válido por (dias)</label><input type="number" name="validade_dias" min="1" max="180" value="<?= !empty($p['validade']) ? max(1, (int)round((strtotime($p['validade']) - strtotime('today')) / 86400)) : 15 ?>"></div><div></div></div>
                        <label>Mensagem para o cliente (o que está incluso, prazo, forma de pagamento…)</label>
                        <textarea name="resposta" rows="3" maxlength="2000" placeholder="Ex: Inclui som, iluminação e 5h de festa. 50% na reserva."><?= e($p['resposta'] ?? '') ?></textarea>
                        <button type="submit" name="responder_orcamento" value="1" class="btn btn-sm">Enviar orçamento ao cliente</button>
                    </form>
                </details>
                <?php endif; ?>
                <?php foreach (pedido_proximos($p) as [$st, $rot]): ?>
                <form method="post" class="inline"><?= csrf_campo() ?><input type="hidden" name="pedido_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="status" value="<?= e($st) ?>">
                    <button type="submit" name="mudar_status" value="1" class="btn btn-sm"><?= e($rot) ?></button></form>
                <?php endforeach; ?>
                <details class="pedido-mais">
                    <summary class="btn btn-ghost btn-sm">Mais…</summary>
                    <form method="post"><?= csrf_campo() ?><input type="hidden" name="pedido_id" value="<?= (int)$p['id'] ?>">
                        <label>Mudar para</label>
                        <select name="status"><?php foreach ($statusNomes as $v => $l): ?><option value="<?= $v ?>" <?= $p['status'] === $v ? 'selected' : '' ?>><?= e($nomeStatus(['tipo' => $tipo, 'status' => $v])) ?></option><?php endforeach; ?></select>
                        <label>Motivo (se cancelar — vai na mensagem ao cliente)</label><input type="text" name="motivo" maxlength="300" placeholder="Opcional">
                        <button type="submit" name="mudar_status" value="1" class="btn btn-ghost btn-sm">Salvar</button>
                    </form>
                </details>
                <?php if ($tipo !== 'delivery' && in_array($p['status'], ['aprovado', 'em_andamento'], true)): ?>
                <details class="pedido-responder">
                    <summary class="btn btn-sm">📸 Atualizar o cliente</summary>
                    <form method="post" enctype="multipart/form-data">
                        <?= csrf_campo() ?><input type="hidden" name="pedido_id" value="<?= (int)$p['id'] ?>">
                        <label>O que foi feito / próxima etapa</label>
                        <textarea name="etapa_texto" rows="2" maxlength="500" required placeholder="Ex: Peça nova chegou e já foi instalada. Amanhã fazemos o teste final."></textarea>
                        <label>Foto (opcional)</label><input type="file" name="etapa_foto" accept="image/jpeg,image/png,image/webp">
                        <button type="submit" name="registrar_etapa" value="1" class="btn btn-sm">Enviar ao cliente</button>
                    </form>
                </details>
                <?php endif; ?>
                <a class="btn btn-ghost btn-sm" target="_blank" href="pedido_imprimir.php?id=<?= (int)$p['id'] ?>">🖨 Imprimir</a>
                <?php if (!empty($p['token'])): ?><a class="btn btn-ghost btn-sm" target="_blank" href="/site/pedido.php?t=<?= e($p['token']) ?>"><?= icone('olho', 14) ?> Ver como o cliente</a><?php endif; ?>
                <?php if (!empty($p['token']) && $tipo === 'orcamento' && (float)$p['valor'] > 0): ?><a class="btn btn-ghost btn-sm" target="_blank" href="/site/orcamento_pdf.php?t=<?= e($p['token']) ?>">📄 PDF</a><?php endif; ?>
            </footer>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    <?php if (!$pedidos): ?><div class="vazio-box"><?= icone('prancheta', 28) ?><p>Nenhum pedido aqui ainda. Os pedidos e orçamentos feitos pelo site aparecem nesta tela.</p></div><?php endif; ?>
    </div>
</main>
</div>
<script>
// Pedido novo: toca um aviso (se ativado), pisca o título e recarrega a lista
(function () {
    var ultimo = <?= (int)max(array_merge([0], array_map(fn($p) => (int)$p['id'], $pedidos))) ?>, ctx = null;
    var btn = document.getElementById('alerta-som');
    var ligado = function () { try { return localStorage.getItem('gds-alerta-som') === '1'; } catch (e) { return false; } };
    function bip() {
        try {
            ctx = ctx || new (window.AudioContext || window.webkitAudioContext)();
            [0, 0.25, 0.5].forEach(function (t) {
                var o = ctx.createOscillator(), g = ctx.createGain();
                o.frequency.value = 880; o.type = 'sine'; o.connect(g); g.connect(ctx.destination);
                g.gain.setValueAtTime(0.0001, ctx.currentTime + t); g.gain.exponentialRampToValueAtTime(0.4, ctx.currentTime + t + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + t + 0.2); o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + 0.22);
            });
        } catch (e) { }
    }
    function marcar() { btn.textContent = ligado() ? '🔔 Alerta sonoro ligado' : '🔕 Ativar alerta sonoro'; btn.classList.toggle('btn-ghost', !ligado()); }
    btn.addEventListener('click', function () { try { localStorage.setItem('gds-alerta-som', ligado() ? '0' : '1'); } catch (e) { } if (ligado()) bip(); marcar(); });
    marcar();
    if (ligado()) document.addEventListener('click', function () { ctx = ctx || new (window.AudioContext || window.webkitAudioContext)(); }, { once: true });
    setInterval(function () {
        fetch('pedidos.php?contar=1', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d.ok || d.ultimo <= ultimo) return;
            ultimo = d.ultimo;
            if (ligado()) bip();
            var t = document.title, n = 0, pisca = setInterval(function () { document.title = (n++ % 2 ? '🔔 Pedido novo! ' : '') + t.replace(/^🔔 Pedido novo! /, ''); if (n > 8) clearInterval(pisca); }, 700);
            // Recarrega quando ninguém está digitando
            var tenta = setInterval(function () {
                if (document.querySelector('details[open]') || (document.activeElement && document.activeElement.matches('input, textarea, select'))) return;
                clearInterval(tenta); location.reload();
            }, 1500);
        }).catch(function () { });
    }, 15000);
})();
</script>
<style>
.orc-linha { display: grid; grid-template-columns: 1fr 70px 110px 36px; gap: 6px; margin-bottom: 6px; }
.orc-linha input { margin: 0; }
@media (max-width: 560px) { .orc-linha { grid-template-columns: 1fr 60px 90px 32px; } }
</style>
<script>
// Orçamento item a item: soma sozinho e trava o total enquanto houver itens
function orcNum(s) { s = String(s || '').trim(); if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.'); return parseFloat(s.replace(/[^0-9.]/g, '')) || 0; }
function orcSomar(form) {
    var linhas = form.querySelectorAll('.orc-linha'), t = 0, alguma = false;
    linhas.forEach(function (l) { if (l.querySelector('[name="orc_nome[]"]').value.trim()) { alguma = true; t += (parseInt(l.querySelector('[name="orc_qtd[]"]').value, 10) || 1) * orcNum(l.querySelector('[name="orc_preco[]"]').value); } });
    var v = form.querySelector('[name=valor]');
    v.readOnly = alguma; if (alguma) v.value = t.toFixed(2).replace('.', ',');
}
function orcLinha(btn) {
    var form = btn.closest('form'), d = document.createElement('div'); d.className = 'orc-linha';
    d.innerHTML = '<input name="orc_nome[]" maxlength="120" placeholder="Descrição (ex.: mão de obra)"><input name="orc_qtd[]" type="number" min="1" value="1" aria-label="Quantidade"><input name="orc_preco[]" inputmode="decimal" placeholder="Valor unit."><button type="button" class="btn btn-ghost btn-sm" onclick="orcRemover(this)" aria-label="Remover">✕</button>';
    form.querySelector('.orc-linhas').appendChild(d); d.querySelector('input').focus();
}
function orcRemover(b) { var form = b.closest('form'); b.closest('.orc-linha').remove(); orcSomar(form); }
document.addEventListener('input', function (e) { if (e.target.closest('.orc-linha')) orcSomar(e.target.closest('form')); });
</script>
</body>
</html>
