<?php
// ============================================================
// ENTREGADORES E FECHAMENTO DO DIA (delivery)
// - Cadastro dos motoboys (o gestor pergunta quem levou cada pedido)
// - Fechamento: vendas por forma de pagamento e por canal, taxas de entrega,
//   acerto de cada entregador (quanto receber e quanto ele traz em dinheiro)
//   e cancelamentos com o motivo. Pode imprimir.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
exigir_modulo('delivery');
$empresaId = (int)empresa_id_atual();
$temTabela = (bool)$pdo->query("SELECT to_regclass('entregadores') IS NOT NULL")->fetchColumn();

if ($temTabela && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_entregador'])) {
    $id = (int)($_POST['entregador_id'] ?? 0);
    $nome = mb_substr(trim(strip_tags($_POST['nome'] ?? '')), 0, 100);
    $tel = mb_substr(trim($_POST['telefone'] ?? ''), 0, 20) ?: null;
    $valor = max(0, valor_decimal($_POST['valor_entrega'] ?? '0'));
    if ($nome === '') flash('erro', 'Informe o nome do entregador.');
    elseif ($id) { $pdo->prepare("UPDATE entregadores SET nome=?, telefone=?, valor_entrega=? WHERE id=? AND empresa_id=?")->execute([$nome, $tel, $valor, $id, $empresaId]); flash('sucesso', 'Entregador atualizado.'); }
    else { $pdo->prepare("INSERT INTO entregadores (empresa_id, nome, telefone, valor_entrega) VALUES (?,?,?,?)")->execute([$empresaId, $nome, $tel, $valor]); flash('sucesso', 'Entregador cadastrado. No Gestor, ao tocar em "Saiu para entrega", escolha quem vai levar.'); }
    header('Location: entregas.php'); exit;
}
if ($temTabela && isset($_GET['alternar'])) {
    $pdo->prepare("UPDATE entregadores SET ativo = NOT ativo WHERE id=? AND empresa_id=?")->execute([(int)$_GET['alternar'], $empresaId]);
    header('Location: entregas.php'); exit;
}

$dia = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dia'] ?? '') ? $_GET['dia'] : date('Y-m-d');
$entregadores = [];
if ($temTabela) { $s = $pdo->prepare("SELECT * FROM entregadores WHERE empresa_id=? ORDER BY ativo DESC, nome"); $s->execute([$empresaId]); $entregadores = $s->fetchAll(); }
$editando = null;
foreach ($entregadores as $en) if ((int)$en['id'] === (int)($_GET['editar'] ?? 0)) $editando = $en;

$s = $pdo->prepare("SELECT p.*, c.nome AS cliente_nome FROM pedidos p LEFT JOIN clientes c ON c.id=p.cliente_id
    WHERE p.empresa_id=? AND p.tipo='delivery' AND p.criado_em::date = ? ORDER BY p.id");
$s->execute([$empresaId, $dia]);
$pedidos = $s->fetchAll();
$validos = array_values(array_filter($pedidos, fn($p) => $p['status'] !== 'cancelado'));
$cancelados = array_values(array_filter($pedidos, fn($p) => $p['status'] === 'cancelado'));
$total = array_sum(array_map(fn($p) => (float)$p['valor'], $validos));
$taxas = array_sum(array_map(fn($p) => (float)$p['taxa_entrega'], $validos));
$descontos = array_sum(array_map(fn($p) => (float)($p['desconto'] ?? 0), $validos));
$porForma = []; $porCanal = []; $porEnt = [];
$canais = ['site' => '🌐 Site', 'telefone' => '📞 Telefone', 'balcao' => '🧾 Balcão', 'mesa' => '🍽 Mesa (QR)'];
$nomesEnt = []; foreach ($entregadores as $en) $nomesEnt[(int)$en['id']] = $en;
foreach ($validos as $p) {
    $f = trim((string)$p['forma_pagamento']) ?: 'Não informado';
    $porForma[$f] = ($porForma[$f] ?? 0) + (float)$p['valor'];
    $c = !empty($p['mesa']) ? 'mesa' : (isset($canais[$p['origem']]) ? $p['origem'] : 'site');
    $porCanal[$c]['n'] = ($porCanal[$c]['n'] ?? 0) + 1; $porCanal[$c]['v'] = ($porCanal[$c]['v'] ?? 0) + (float)$p['valor'];
    if (!empty($p['entregador_id']) && isset($nomesEnt[(int)$p['entregador_id']])) {
        $en = $nomesEnt[(int)$p['entregador_id']]; $k = (int)$en['id'];
        $porEnt[$k]['nome'] = $en['nome'];
        $porEnt[$k]['n'] = ($porEnt[$k]['n'] ?? 0) + 1;
        // A pagar: valor fixo por entrega ou, se zero, a taxa cobrada do cliente
        $porEnt[$k]['pagar'] = ($porEnt[$k]['pagar'] ?? 0) + ((float)$en['valor_entrega'] > 0 ? (float)$en['valor_entrega'] : (float)$p['taxa_entrega']);
        // Dinheiro que ele recebeu do cliente e traz para o caixa
        if (stripos($f, 'dinheiro') !== false && !pg_bool($p['pago'] ?? false)) $porEnt[$k]['dinheiro'] = ($porEnt[$k]['dinheiro'] ?? 0) + (float)$p['valor'];
    }
}
arsort($porForma);
$semEntregador = count(array_filter($validos, fn($p) => ($p['tipo_entrega'] ?? '') === 'entrega' && empty($p['mesa']) && empty($p['entregador_id'])));
$moeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Entregadores e fechamento - Meu Negócio') ?>
<style>
.fc-grade { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(280px, .7fr); gap: 16px; align-items: start; }
@media (max-width: 1000px) { .fc-grade { grid-template-columns: 1fr; } }
.fc-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin: 12px 0; }
.fc-kpis div { background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; padding: 10px 12px; }
.fc-kpis small { display: block; color: var(--text-dim); font-size: 12px; } .fc-kpis strong { font-size: 20px; }
@media print { .sidebar, .no-print, .menu-mobile { display: none !important; } .content { margin: 0 !important; padding: 0 !important; } .fc-grade { grid-template-columns: 1fr; } body { background: #fff; color: #000; } }
</style>
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header">
        <div><h1>Entregadores e fechamento</h1><p class="subtitulo">O resumo do dia para fechar o caixa e acertar com os motoboys.</p></div>
        <div class="acoes no-print"><a class="btn btn-ghost" href="gestor.php">← Gestor</a><button type="button" class="btn" onclick="print()">🖨 Imprimir fechamento</button></div>
    </div>
    <?= flash_render() ?>
    <div class="fc-grade">
        <section class="form-box largo">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
                <h3 style="margin:0">Fechamento de <?= date('d/m/Y', strtotime($dia)) ?></h3>
                <form method="get" class="no-print"><input type="date" name="dia" value="<?= e($dia) ?>" onchange="this.form.submit()" style="margin:0"></form>
            </div>
            <div class="fc-kpis">
                <div><small>Vendido</small><strong><?= $moeda($total) ?></strong></div>
                <div><small>Pedidos</small><strong><?= count($validos) ?></strong></div>
                <div><small>Ticket médio</small><strong><?= $moeda($validos ? $total / count($validos) : 0) ?></strong></div>
                <div><small>Taxas de entrega</small><strong><?= $moeda($taxas) ?></strong></div>
                <?php if ($descontos > 0): ?><div><small>Descontos (cupons)</small><strong>−<?= $moeda($descontos) ?></strong></div><?php endif; ?>
                <div><small>Cancelados</small><strong><?= count($cancelados) ?></strong></div>
            </div>
            <h4>Por forma de pagamento</h4>
            <?php foreach ($porForma as $f => $v): ?><div class="barra-linha"><div class="rotulo"><span><?= e($f) ?></span><span><?= $moeda($v) ?></span></div><div class="progresso"><div style="width:<?= $total > 0 ? round(100 * $v / $total) : 0 ?>%"></div></div></div><?php endforeach; ?>
            <?php if (!$porForma): ?><p class="dica">Nenhum pedido neste dia.</p><?php endif; ?>
            <?php if ($porCanal): ?>
            <h4 style="margin-top:14px">Por canal</h4>
            <div class="tabela-wrap"><table><thead><tr><th>Canal</th><th>Pedidos</th><th>Valor</th></tr></thead><tbody>
            <?php foreach ($porCanal as $c => $x): ?><tr><td><?= $canais[$c] ?? e($c) ?></td><td><?= (int)$x['n'] ?></td><td><?= $moeda($x['v']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php endif; ?>
            <h4 style="margin-top:14px">Acerto dos entregadores</h4>
            <?php if ($porEnt): ?>
            <div class="tabela-wrap"><table><thead><tr><th>Entregador</th><th>Entregas</th><th>A pagar</th><th>Dinheiro com ele</th></tr></thead><tbody>
            <?php foreach ($porEnt as $x): ?><tr><td><?= e($x['nome']) ?></td><td><?= (int)$x['n'] ?></td><td><strong><?= $moeda($x['pagar']) ?></strong></td><td><?= $moeda($x['dinheiro'] ?? 0) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <p class="dica">"Dinheiro com ele" = pedidos pagos em dinheiro na entrega, que o entregador recebeu do cliente e traz para o caixa.</p>
            <?php else: ?><p class="dica">Nenhuma entrega com entregador registrado neste dia.</p><?php endif; ?>
            <?php if ($semEntregador): ?><p class="dica">🛵 <?= $semEntregador ?> entrega(s) sem entregador marcado.</p><?php endif; ?>
            <?php if ($cancelados): ?>
            <h4 style="margin-top:14px">Cancelados</h4>
            <ul class="dica"><?php foreach ($cancelados as $p): ?><li>nº <?= (int)$p['id'] ?> · <?= e($p['cliente_nome'] ?: 'Cliente') ?> · <?= $moeda($p['valor']) ?><?= $p['resposta'] ? ' — ' . e($p['resposta']) : '' ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </section>

        <section class="form-box no-print">
            <h3 style="margin-top:0">Entregadores</h3>
            <?php if (!$temTabela): ?><p class="dica">Atualizando o banco… recarregue a página.</p><?php else: ?>
            <form method="post">
                <?= csrf_campo() ?><input type="hidden" name="entregador_id" value="<?= (int)($editando['id'] ?? 0) ?>">
                <label>Nome</label><input type="text" name="nome" maxlength="100" required value="<?= e($editando['nome'] ?? '') ?>">
                <label>WhatsApp (recebe a rota de cada entrega)</label><input type="tel" name="telefone" maxlength="20" value="<?= e($editando['telefone'] ?? '') ?>">
                <label>Quanto você paga por entrega (R$)</label><input type="text" name="valor_entrega" inputmode="decimal" value="<?= number_format((float)($editando['valor_entrega'] ?? 0), 2, ',', '') ?>">
                <p class="dica">Deixe 0 para repassar a taxa de entrega cobrada do cliente.</p>
                <button type="submit" name="salvar_entregador" value="1"><?= $editando ? 'Salvar' : 'Cadastrar entregador' ?></button>
                <?php if ($editando): ?><a class="btn btn-ghost" href="entregas.php" style="margin-top:18px">Cancelar</a><?php endif; ?>
            </form>
            <ul style="list-style:none;padding:0;margin:14px 0 0;display:grid;gap:6px">
            <?php foreach ($entregadores as $en): ?>
                <li style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 10px;border:1px solid var(--border);border-radius:10px;<?= pg_bool($en['ativo']) ? '' : 'opacity:.5' ?>">
                    <span><strong>🛵 <?= e($en['nome']) ?></strong><span class="mini" style="display:block;color:var(--text-dim);font-size:12px"><?= e($en['telefone'] ?: 'sem WhatsApp') ?> · <?= (float)$en['valor_entrega'] > 0 ? $moeda($en['valor_entrega']) . ' por entrega' : 'recebe a taxa' ?></span></span>
                    <span style="display:flex;gap:4px"><a class="btn btn-ghost btn-sm" href="?editar=<?= (int)$en['id'] ?>"><?= icone('lapis', 14) ?></a><a class="btn btn-ghost btn-sm" href="?alternar=<?= (int)$en['id'] ?><?= csrf_qs() ?>"><?= pg_bool($en['ativo']) ? 'Pausar' : 'Ativar' ?></a></span>
                </li>
            <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    </div>
</main>
</div>
</body>
</html>
