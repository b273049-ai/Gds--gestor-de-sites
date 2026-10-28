<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
exigir_modulo('orcamentos', 'delivery');
$empresaId = (int)empresa_id_atual();
$empresa = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();

$statusNomes = ['novo' => 'Novo', 'orcado' => 'Orçamento enviado', 'aprovado' => 'Aprovado', 'em_andamento' => 'Em andamento',
                'pronto' => 'Pronto p/ retirada', 'saiu_entrega' => 'Saiu para entrega', 'concluido' => 'Concluído', 'cancelado' => 'Cancelado'];
$statusDelivery = ['em_andamento' => 'Em preparo', 'concluido' => 'Entregue'];
$nomeStatus = fn($p) => (($p['tipo'] ?? '') === 'delivery' ? ($statusDelivery[$p['status']] ?? null) : null) ?? ($statusNomes[$p['status']] ?? $p['status']);
$voltar = fn() => 'pedidos.php' . (($q = http_build_query(array_intersect_key($_GET, ['status' => 1, 'tipo' => 1]))) ? '?' . $q : '');

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
    if (!$p) flash('erro', 'Pedido não encontrado.');
    elseif ($valor < 0) flash('erro', 'Informe o valor do orçamento.');
    else {
        if (empty($p['token'])) $pdo->prepare("UPDATE pedidos SET token=? WHERE id=?")->execute([bin2hex(random_bytes(16)), $p['id']]);
        $pdo->prepare("UPDATE pedidos SET valor=?, resposta=?, status='orcado', atualizado_em=NOW() WHERE id=?")
            ->execute([$valor, mb_substr(trim($_POST['resposta'] ?? ''), 0, 2000) ?: null, $p['id']]);
        wa_adiar();
        flash('sucesso', 'Orçamento de R$ ' . number_format($valor, 2, ',', '.') . ' registrado.' . wa_resultado_para_tela(wa_avisar_status_pedido($empresa, $p['id'], 'orcado')));
    }
    header('Location: ' . $voltar()); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mudar_status']) && isset($statusNomes[$_POST['status'] ?? ''])) {
    $p = pedido_da_empresa((int)$_POST['pedido_id'], $empresaId);
    if ($p && $p['status'] !== $_POST['status']) {
        $motivo = mb_substr(trim($_POST['motivo'] ?? ''), 0, 300);
        $pdo->prepare("UPDATE pedidos SET status=?, atualizado_em=NOW()" . ($_POST['status'] === 'cancelado' && $motivo !== '' ? ', resposta=?' : '') . " WHERE id=?")
            ->execute($_POST['status'] === 'cancelado' && $motivo !== '' ? [$_POST['status'], $motivo, $p['id']] : [$_POST['status'], $p['id']]);
        wa_adiar();
        flash('sucesso', 'Pedido nº ' . $p['id'] . ': ' . $nomeStatus(['tipo' => $p['tipo'] ?? '', 'status' => $_POST['status']]) . '.' . wa_resultado_para_tela(wa_avisar_status_pedido($empresa, $p['id'], $_POST['status'])));
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
<head><?= painel_head(($novos ? "($novos) " : '') . modulos_rotulo_pedidos() . ' - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1><?= e(modulos_rotulo_pedidos()) ?></h1>
        <p class="subtitulo">Em aberto nesta lista: R$ <?= number_format($totAberto, 2, ',', '.') ?><?= $novos ? ' · <strong>' . $novos . ' novo(s) esperando você</strong>' : '' ?>
        · <a class="link" href="whatsapp.php">o cliente é avisado no WhatsApp a cada passo</a></p></div></div>
    <?= flash_render() ?>
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
                    <?php if (($p['origem'] ?? '') === 'site'): ?><span class="badge badge-site">pelo site</span><?php endif; ?></div>
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
                    <?php if ($itens): ?>
                        <ul class="pedido-itens"><?php foreach ($itens as $i): ?><li><b><?= (int)$i['qtd'] ?>×</b> <?= e($i['nome']) ?> <span>R$ <?= number_format($i['preco'] * $i['qtd'], 2, ',', '.') ?></span></li><?php endforeach; ?></ul>
                        <p><?= ($p['tipo_entrega'] ?? '') === 'retirada' ? '🏪 Retirada no local' : '🛵 Entrega: ' . e($p['endereco_entrega']) ?><?= (float)$p['taxa_entrega'] > 0 ? ' (taxa R$ ' . number_format((float)$p['taxa_entrega'], 2, ',', '.') . ')' : '' ?></p>
                        <p>💳 <?= e($p['forma_pagamento']) ?><?= $p['troco_para'] ? ' — troco para R$ ' . number_format((float)$p['troco_para'], 2, ',', '.') : '' ?></p>
                    <?php else: ?>
                        <?php if (!empty($p['servico_nome'])): ?><strong><?= e($p['servico_nome']) ?></strong><br><?php endif; ?><?= nl2br(e($p['descricao'])) ?>
                        <?php if (!empty($p['data_evento'])): ?><span class="mini">📅 <?= date('d/m/Y', strtotime($p['data_evento'])) ?></span><?php endif; ?>
                        <?php if (!empty($p['local_evento'])): ?><span class="mini">📍 <?= e($p['local_evento']) ?></span><?php endif; ?>
                        <?php if ($p['veiculo_info']): ?><span class="mini">🚗 <?= e($p['veiculo_info']) ?></span><?php endif; ?>
                    <?php endif; ?>
                    <?php if (!empty($p['observacoes'])): ?><p class="pedido-obs">📝 <?= e($p['observacoes']) ?></p><?php endif; ?>
                    <?php if (!empty($p['resposta'])): ?><p class="pedido-obs">💬 <?= nl2br(e($p['resposta'])) ?></p><?php endif; ?>
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
                        <div class="form-row">
                            <div><label>Valor (R$)</label><input type="text" inputmode="decimal" name="valor" required value="<?= (float)$p['valor'] > 0 ? number_format((float)$p['valor'], 2, ',', '') : '' ?>" placeholder="1.500,00"></div>
                            <div></div>
                        </div>
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
                <?php if (!empty($p['token'])): ?><a class="btn btn-ghost btn-sm" target="_blank" href="/site/pedido.php?t=<?= e($p['token']) ?>"><?= icone('olho', 14) ?> Ver como o cliente</a><?php endif; ?>
            </footer>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    <?php if (!$pedidos): ?><div class="vazio-box"><?= icone('prancheta', 28) ?><p>Nenhum pedido aqui ainda. Os pedidos e orçamentos feitos pelo site aparecem nesta tela.</p></div><?php endif; ?>
    </div>
</main>
</div>
<?php if ($temDelivery || $novos): ?>
<script>
// Atualiza sozinho a cada 45s para mostrar pedidos novos (sem atrapalhar quem está digitando)
setInterval(() => {
    if (document.querySelector('details[open]') || document.activeElement?.matches('input, textarea, select')) return;
    location.reload();
}, 45000);
</script>
<?php endif; ?>
</body>
</html>
