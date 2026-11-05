<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

// Cobranças vencidas viram "atrasado" automaticamente
$pdo->exec("UPDATE pagamentos_assinaturas SET status='atrasado' WHERE status='pendente' AND vencimento < CURRENT_DATE");

// Marcar como pago: ativa a empresa, empurra o vencimento e já cria a próxima cobrança
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_pago'])) {
    $pg = $pdo->prepare("SELECT pa.*, p.ciclo, p.valor AS valor_plano, e.plano_id AS plano_atual FROM pagamentos_assinaturas pa
        JOIN empresas e ON e.id = pa.empresa_id LEFT JOIN planos p ON p.id = e.plano_id WHERE pa.id=? AND pa.status <> 'pago'");
    $pg->execute([(int)$_POST['pagamento_id']]);
    if ($pg = $pg->fetch()) {
        $intervalo = ($pg['ciclo'] ?? 'mensal') === 'anual' ? '1 year' : '1 month';
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE pagamentos_assinaturas SET status='pago', pago_em=NOW(), metodo=COALESCE(metodo, ?) WHERE id=?")->execute([$_POST['metodo'] ?? 'manual', $pg['id']]);
        $novoVenc = $pdo->prepare("SELECT (GREATEST(?::date, CURRENT_DATE) + INTERVAL '$intervalo')::date AS d");
        $novoVenc->execute([$pg['vencimento']]); $novoVenc = $novoVenc->fetch()['d'];
        $pdo->prepare("UPDATE empresas SET status='ativo', vencimento=? WHERE id=?")->execute([$novoVenc, $pg['empresa_id']]);
        $pdo->prepare("UPDATE usuarios SET ativo=true WHERE empresa_id=?")->execute([$pg['empresa_id']]);
        if (isset($_POST['gerar_proxima']) && $pg['plano_atual']) {
            $pdo->prepare("INSERT INTO pagamentos_assinaturas (empresa_id, plano_id, valor, vencimento, status) VALUES (?,?,?,?,'pendente')")
                ->execute([$pg['empresa_id'], $pg['plano_atual'], $pg['valor_plano'] ?? $pg['valor'], $novoVenc]);
        }
        $pdo->commit();
        flash('sucesso', 'Pagamento registrado. Empresa ativa até ' . date('d/m/Y', strtotime($novoVenc)) . '.');
    }
    header('Location: financeiro.php'); exit;
}
// Link de pagamento do Mercado Pago (Pix, cartão ou boleto): quando pagar, dá baixa sozinho
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gerar_link'])) {
    require_once __DIR__ . '/../includes/mp_assinaturas.php';
    $r = mp_link_assinatura((int)$_POST['pagamento_id']);
    flash($r['ok'] ? 'sucesso' : 'erro', $r['ok'] ? 'Link de pagamento pronto (copie ou mande no WhatsApp na linha da cobrança). Quando o cliente pagar, a baixa é automática.' : $r['erro']);
    header('Location: financeiro.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar'])) {
    $pdo->prepare("UPDATE pagamentos_assinaturas SET status='cancelado' WHERE id=? AND status <> 'pago'")->execute([(int)$_POST['pagamento_id']]);
    flash('sucesso', 'Cobrança cancelada.');
    header('Location: financeiro.php'); exit;
}

$filtro = $_GET['status'] ?? 'abertos';
$where = match ($filtro) {
    'pago' => "pa.status='pago'",
    'todos' => "TRUE",
    default => "pa.status IN ('pendente','atrasado')",
};
$pagamentos = $pdo->query("
    SELECT pa.*, e.nome AS empresa_nome, e.id AS empresa_id, e.whatsapp AS empresa_whatsapp
    FROM pagamentos_assinaturas pa JOIN empresas e ON e.id = pa.empresa_id
    WHERE $where ORDER BY pa.vencimento DESC LIMIT 300")->fetchAll();

$tot = $pdo->query("SELECT
    COALESCE(SUM(valor) FILTER (WHERE status='pago'),0) pago,
    COALESCE(SUM(valor) FILTER (WHERE status='pendente'),0) pendente,
    COALESCE(SUM(valor) FILTER (WHERE status='atrasado'),0) atrasado,
    COALESCE(SUM(valor) FILTER (WHERE status='pago' AND date_trunc('month', pago_em)=date_trunc('month', CURRENT_DATE)),0) mes
    FROM pagamentos_assinaturas")->fetch();
$temToken = trim(get_config('mercadopago_access_token', '')) !== '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Financeiro - GDS Admin') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Financeiro</h1><p class="subtitulo">Assinaturas das empresas na plataforma.</p></div></div>
    <?= flash_render() ?>
    <div class="cards">
        <div class="card"><div class="label">Recebido no mês</div><div class="value">R$ <?= number_format($tot['mes'], 2, ',', '.') ?></div></div>
        <div class="card"><div class="label">Total recebido</div><div class="value">R$ <?= number_format($tot['pago'], 2, ',', '.') ?></div></div>
        <div class="card"><div class="label">A vencer</div><div class="value">R$ <?= number_format($tot['pendente'], 2, ',', '.') ?></div></div>
        <div class="card"><div class="label">Atrasado</div><div class="value" style="color:<?= $tot['atrasado'] > 0 ? 'var(--danger)' : 'inherit' ?>">R$ <?= number_format($tot['atrasado'], 2, ',', '.') ?></div></div>
    </div>
    <?php if (!$temToken): ?><?= alerta('info', 'Configure o Access Token do Mercado Pago em Configurações para usar "Gerar PIX". Sem token, marque os pagamentos manualmente.') ?><?php endif; ?>

    <div class="toolbar">
        <?php foreach (['abertos' => 'Em aberto', 'pago' => 'Pagos', 'todos' => 'Todos'] as $v => $l): ?>
        <a class="btn btn-sm <?= $filtro === $v ? '' : 'btn-ghost' ?>" href="?status=<?= $v ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </div>
    <div class="tabela-wrap"><table>
        <thead><tr><th>Empresa</th><th>Valor</th><th>Vencimento</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pagamentos as $p): ?>
        <tr>
            <td><a class="link" href="empresa_editar.php?id=<?= $p['empresa_id'] ?>"><?= e($p['empresa_nome']) ?></a><?php if ($p['pago_em']): ?><span class="mini">pago em <?= date('d/m/Y', strtotime($p['pago_em'])) ?><?= $p['metodo'] ? ' · ' . e($p['metodo']) : '' ?></span><?php endif; ?></td>
            <td>R$ <?= number_format($p['valor'], 2, ',', '.') ?></td>
            <td><?= date('d/m/Y', strtotime($p['vencimento'])) ?></td>
            <td><span class="badge badge-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
            <td class="acoes">
                <?php if (in_array($p['status'], ['pendente', 'atrasado'], true)): ?>
                <form method="post" onsubmit="return confirm('Confirmar recebimento de R$ <?= number_format($p['valor'], 2, ',', '.') ?>?')">
                    <?= csrf_campo() ?><input type="hidden" name="pagamento_id" value="<?= $p['id'] ?>"><input type="hidden" name="gerar_proxima" value="1">
                    <button class="btn btn-sm" type="submit" name="marcar_pago" value="1">Marcar pago</button>
                </form>
                <?php if ($temToken && empty($p['link_pagamento'])): ?><form method="post"><?= csrf_campo() ?><input type="hidden" name="pagamento_id" value="<?= $p['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit" name="gerar_link" value="1">🔗 Link de pagamento</button></form><?php endif; ?>
                <?php if (!empty($p['link_pagamento'])): $msgPg = 'Olá! Segue o link para ativar o site (Pix, cartão ou boleto): ' . $p['link_pagamento']; ?>
                <a class="btn btn-wa btn-sm" target="_blank" rel="noopener" href="https://wa.me/<?= e(($p['empresa_whatsapp'] ?? '') !== '' ? (strlen(preg_replace('/\D/', '', $p['empresa_whatsapp'])) <= 11 ? '55' : '') . preg_replace('/\D/', '', $p['empresa_whatsapp']) : '') ?>?text=<?= rawurlencode($msgPg) ?>">Mandar link</a>
                <button type="button" class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText(<?= e(json_encode($p['link_pagamento'])) ?>).then(() => this.textContent = 'Copiado ✔')">Copiar link</button>
                <?php endif; ?>
                <?php if ($temToken): ?><form method="post" action="mp_gerar_cobranca.php"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">Gerar PIX</button></form><?php endif; ?>
                <form method="post" onsubmit="return confirm('Cancelar esta cobrança?')"><?= csrf_campo() ?><input type="hidden" name="pagamento_id" value="<?= $p['id'] ?>"><button class="btn btn-perigo btn-sm" type="submit" name="cancelar" value="1">Cancelar</button></form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$pagamentos): ?><tr class="vazio"><td colspan="5">Nada por aqui.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <p class="dica">Ao marcar como pago, a empresa fica ativa, o vencimento avança um ciclo do plano e a próxima cobrança é criada automaticamente.</p>
</main>
</div>
</body>
</html>
