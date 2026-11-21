<?php
// Clube de assinatura e pacotes de sessões: planos, assinantes, ativação manual e receita recorrente
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pagamentos.php';
require_once __DIR__ . '/../includes/clube.php';
exigir_login('empresa');
$empresaId = (int)empresa_id_atual();
gds_garantir_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_plano'])) {
    $id = (int)($_POST['plano_id'] ?? 0);
    $nome = mb_substr(trim($_POST['nome'] ?? ''), 0, 120);
    $tipo = ($_POST['tipo'] ?? '') === 'pacote' ? 'pacote' : 'assinatura';
    $preco = max(0, valor_decimal($_POST['preco'] ?? '0'));
    $servs = array_values(array_filter(array_map('intval', (array)($_POST['servicos'] ?? []))));
    $dados = [$tipo, $nome, mb_substr(trim($_POST['descricao'] ?? ''), 0, 400) ?: null, $preco, max(0, min(999, (int)($_POST['quantidade'] ?? 0))),
              max(1, min(730, (int)($_POST['validade_dias'] ?? 30))), $servs ? json_encode($servs) : null, isset($_POST['destaque']) ? 'true' : 'false'];
    if ($nome === '' || $preco <= 0) flash('erro', 'Informe o nome e o preço do plano.');
    elseif ($id) { $pdo->prepare("UPDATE planos_cliente SET tipo=?, nome=?, descricao=?, preco=?, quantidade=?, validade_dias=?, servicos=?, destaque=? WHERE id=? AND empresa_id=?")->execute([...$dados, $id, $empresaId]); flash('sucesso', 'Plano atualizado.'); }
    else { $pdo->prepare("INSERT INTO planos_cliente (tipo, nome, descricao, preco, quantidade, validade_dias, servicos, destaque, empresa_id) VALUES (?,?,?,?,?,?,?,?,?)")->execute([...$dados, $empresaId]); flash('sucesso', 'Plano criado — já aparece no site.'); }
    header('Location: clube.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_plano'])) {
    $pid = (int)$_POST['plano_id'];
    if ($_POST['acao_plano'] === 'alternar') $pdo->prepare("UPDATE planos_cliente SET ativo = NOT ativo WHERE id=? AND empresa_id=?")->execute([$pid, $empresaId]);
    header('Location: clube.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_assinatura'])) {
    $aid = (int)$_POST['assinatura_id'];
    $st = $pdo->prepare("SELECT id FROM assinaturas WHERE id=? AND empresa_id=?"); $st->execute([$aid, $empresaId]);
    if ($st->fetch()) {
        if ($_POST['acao_assinatura'] === 'ativar') { clube_ativar($aid); flash('sucesso', 'Plano ativado (pagamento recebido por fora). O cliente foi avisado no WhatsApp.'); }
        elseif ($_POST['acao_assinatura'] === 'cancelar') { $pdo->prepare("UPDATE assinaturas SET status='cancelada' WHERE id=?")->execute([$aid]); flash('sucesso', 'Assinatura cancelada.'); }
        elseif ($_POST['acao_assinatura'] === 'uso') { clube_usar($aid, 1); flash('sucesso', 'Uso registrado.'); }
    }
    header('Location: clube.php#assinantes'); exit;
}
// Nova adesão feita no balcão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adesao_balcao'])) {
    $emp = wa_carregar_empresa($empresaId);
    $r = clube_assinar($emp, (int)$_POST['plano_id'], (string)$_POST['nome'], (string)$_POST['telefone']);
    if ($r['ok']) { if (isset($_POST['ja_pago'])) clube_ativar($r['id']); flash('sucesso', isset($_POST['ja_pago']) ? 'Adesão registrada e ativada.' : 'Adesão registrada. Mande o link de pagamento ao cliente: ' . clube_url($emp, $r['token'])); }
    else flash('erro', $r['erro']);
    header('Location: clube.php#assinantes'); exit;
}

$planos = clube_planos($empresaId, false);
$servicos = $pdo->prepare("SELECT id, nome FROM servicos WHERE empresa_id=? AND ativo=true ORDER BY nome"); $servicos->execute([$empresaId]); $servicos = $servicos->fetchAll();
$assin = $pdo->prepare("SELECT a.*, p.nome AS plano_nome, p.tipo, p.quantidade, p.preco, c.nome AS cliente_nome, c.telefone FROM assinaturas a
    JOIN planos_cliente p ON p.id = a.plano_id LEFT JOIN clientes c ON c.id = a.cliente_id WHERE a.empresa_id=? ORDER BY (a.status='ativa') DESC, a.vence_em NULLS LAST, a.id DESC LIMIT 300");
$assin->execute([$empresaId]); $assin = $assin->fetchAll();
$ativas = array_filter($assin, fn($a) => $a['status'] === 'ativa');
$mrr = array_sum(array_map(fn($a) => $a['tipo'] === 'assinatura' ? (float)$a['preco'] : 0, $ativas));
$pendentes = count(array_filter($assin, fn($a) => $a['status'] === 'pendente'));
$editando = null; foreach ($planos as $p) if ((int)$p['id'] === (int)($_GET['editar'] ?? 0)) $editando = $p;
$selServ = $editando ? clube_servicos_ids($editando) : [];
$rot = ['pendente' => ['Aguardando pagamento', 'pill-aviso'], 'ativa' => ['Ativo', 'pill-ok'], 'vencida' => ['Vencido', 'pill-neutro'], 'cancelada' => ['Cancelado', 'pill-neutro'], 'usada' => ['Sessões usadas', 'pill-neutro']];
$empresa = wa_carregar_empresa($empresaId);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Clube e pacotes - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Clube e pacotes</h1><p class="subtitulo">Assinatura mensal e pacotes de sessões: receita certa todo mês e cliente que volta.</p></div></div>
    <?= flash_render() ?>
    <?php if (!pag_disponivel($empresa)): ?><?= alerta('info', 'Ative o Pix em Configurações → Pagamento online para os clientes assinarem e pagarem sozinhos pelo site. Sem isso, você confirma o pagamento aqui.') ?><?php endif; ?>
    <div class="cards">
        <div class="card"><div class="label"><?= icone('usuarios', 16) ?> Assinantes ativos</div><div class="value"><?= count($ativas) ?></div><div class="sub"><?= $pendentes ?> aguardando pagamento</div></div>
        <div class="card"><div class="label"><?= icone('dinheiro', 16) ?> Receita recorrente</div><div class="value">R$ <?= number_format($mrr, 2, ',', '.') ?></div><div class="sub">por período, das assinaturas ativas</div></div>
        <div class="card"><div class="label"><?= icone('estrela', 16) ?> Planos no site</div><div class="value"><?= count(array_filter($planos, fn($p) => pg_bool($p['ativo']))) ?></div><div class="sub">aparecem na seção "Clube" do site</div></div>
    </div>

    <form method="post" class="form-box largo" id="form">
        <?= csrf_campo() ?>
        <h3><?= $editando ? 'Editar plano' : 'Novo plano ou pacote' ?></h3>
        <input type="hidden" name="plano_id" value="<?= (int)($editando['id'] ?? 0) ?>">
        <div class="form-row">
            <div><label>Tipo</label><select name="tipo"><option value="assinatura">Assinatura (cobra a cada período)</option><option value="pacote" <?= ($editando['tipo'] ?? '') === 'pacote' ? 'selected' : '' ?>>Pacote de sessões (paga uma vez)</option></select></div>
            <div><label>Nome</label><input type="text" name="nome" required maxlength="120" placeholder="Ex: Clube do Corte · 10 sessões de drenagem" value="<?= e($editando['nome'] ?? '') ?>"></div>
        </div>
        <div class="form-row">
            <div><label>Preço (R$)</label><input type="text" inputmode="decimal" name="preco" required value="<?= $editando ? number_format((float)$editando['preco'], 2, ',', '') : '' ?>"></div>
            <div><label>Quantidade (usos por período ou sessões do pacote; 0 = ilimitado)</label><input type="number" name="quantidade" min="0" max="999" value="<?= (int)($editando['quantidade'] ?? 4) ?>"></div>
            <div><label>Validade (dias)</label><input type="number" name="validade_dias" min="1" max="730" value="<?= (int)($editando['validade_dias'] ?? 30) ?>"></div>
        </div>
        <label>Serviços incluídos (nenhum marcado = todos)</label>
        <div style="display:flex;flex-wrap:wrap;gap:6px 16px"><?php foreach ($servicos as $s): ?><label class="check" style="margin:0"><input type="checkbox" name="servicos[]" value="<?= (int)$s['id'] ?>" <?= in_array((int)$s['id'], $selServ, true) ? 'checked' : '' ?>> <?= e($s['nome']) ?></label><?php endforeach; ?></div>
        <label>Descrição (opcional)</label><input type="text" name="descricao" maxlength="400" placeholder="Ex: Corte toda semana + 10% em produtos" value="<?= e($editando['descricao'] ?? '') ?>">
        <label class="check"><input type="checkbox" name="destaque" <?= $editando && pg_bool($editando['destaque']) ? 'checked' : '' ?>> Destacar como "Mais escolhido"</label>
        <button type="submit" name="salvar_plano" value="1"><?= $editando ? 'Salvar' : 'Criar plano' ?></button>
        <?php if ($editando): ?><a class="btn btn-ghost" href="clube.php" style="margin-top:18px">Cancelar</a><?php endif; ?>
    </form>

    <?php if ($planos): ?>
    <h2>Planos</h2>
    <div class="tabela-wrap" style="margin-bottom:22px"><table>
        <thead><tr><th>Plano</th><th>Preço</th><th>Regra</th><th>Assinantes</th><th>Situação</th><th></th></tr></thead><tbody>
        <?php foreach ($planos as $p): $n = count(array_filter($ativas, fn($a) => (int)$a['plano_id'] === (int)$p['id'])); ?>
        <tr><td><strong><?= e($p['nome']) ?></strong><span class="mini"><?= $p['tipo'] === 'pacote' ? 'Pacote' : 'Assinatura' ?></span></td><td>R$ <?= number_format((float)$p['preco'], 2, ',', '.') ?></td>
            <td><?= (int)$p['quantidade'] ?: 'ilimitado' ?> · <?= (int)$p['validade_dias'] ?> dias</td><td><?= $n ?></td>
            <td><span class="pill <?= pg_bool($p['ativo']) ? 'pill-ok' : 'pill-neutro' ?>"><?= pg_bool($p['ativo']) ? 'no site' : 'oculto' ?></span></td>
            <td class="acoes"><a class="btn btn-ghost btn-sm" href="?editar=<?= (int)$p['id'] ?>#form"><?= icone('lapis', 14) ?></a>
                <form method="post" class="inline"><?= csrf_campo() ?><input type="hidden" name="plano_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-ghost btn-sm" name="acao_plano" value="alternar"><?= pg_bool($p['ativo']) ? 'Ocultar' : 'Mostrar' ?></button></form></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>

    <details class="form-box largo">
        <summary>+ Registrar adesão feita no balcão</summary>
        <form method="post"><?= csrf_campo() ?>
            <div class="form-row"><div><label>Plano</label><select name="plano_id"><?php foreach ($planos as $p): if (pg_bool($p['ativo'])): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['nome']) ?></option><?php endif; endforeach; ?></select></div>
                <div><label>Nome do cliente</label><input type="text" name="nome" required></div><div><label>WhatsApp</label><input type="tel" name="telefone" required></div></div>
            <label class="check"><input type="checkbox" name="ja_pago" checked> Já recebi o pagamento (ativar agora)</label>
            <button type="submit" name="adesao_balcao" value="1">Registrar</button>
        </form>
    </details>
    <?php endif; ?>

    <h2 id="assinantes">Assinantes</h2>
    <div class="tabela-wrap"><table>
        <thead><tr><th>Cliente</th><th>Plano</th><th>Usos</th><th>Vencimento</th><th>Situação</th><th></th></tr></thead><tbody>
        <?php foreach ($assin as $a): [$rt, $cl] = $rot[$a['status']] ?? [$a['status'], 'pill-neutro']; ?>
        <tr><td><strong><?= e($a['cliente_nome'] ?? '—') ?></strong><?php if ($a['telefone']): ?><span class="mini"><a class="link" target="_blank" href="<?= e(link_whatsapp($a['telefone'], 'Olá, ' . explode(' ', (string)$a['cliente_nome'])[0] . '! Sobre o seu ' . $a['plano_nome'] . ': ' . clube_url($empresa, $a['token']))) ?>"><?= e($a['telefone']) ?></a></span><?php endif; ?></td>
            <td><?= e($a['plano_nome']) ?></td><td><?= (int)$a['usos'] ?><?= (int)$a['quantidade'] ? ' / ' . (int)$a['quantidade'] : '' ?></td>
            <td><?= $a['vence_em'] ? date('d/m/Y', strtotime($a['vence_em'])) : '—' ?></td><td><span class="pill <?= $cl ?>"><?= $rt ?></span></td>
            <td class="acoes"><form method="post" class="inline"><?= csrf_campo() ?><input type="hidden" name="assinatura_id" value="<?= (int)$a['id'] ?>">
                <?php if ($a['status'] !== 'ativa'): ?><button class="btn btn-sm" name="acao_assinatura" value="ativar" title="Pagamento recebido por fora">Ativar</button><?php else: ?><button class="btn btn-ghost btn-sm" name="acao_assinatura" value="uso" title="Registrar um uso feito no balcão">+1 uso</button><?php endif; ?>
                <?php if (!in_array($a['status'], ['cancelada'], true)): ?><button class="btn btn-ghost btn-sm" name="acao_assinatura" value="cancelar" onclick="return confirm('Cancelar esta assinatura?')">Cancelar</button><?php endif; ?></form></td></tr>
        <?php endforeach; ?>
        <?php if (!$assin): ?><tr class="vazio"><td colspan="6">Ninguém assinou ainda. Crie um plano acima: ele aparece no seu site na hora.</td></tr><?php endif; ?>
        </tbody></table></div>
    <p class="dica">Como funciona: o cliente assina pelo site e paga por Pix (ativa sozinho) — ou você ativa aqui. Ao agendar pelo site com o mesmo WhatsApp, o serviço incluído sai sem custo e conta um uso. 3 dias antes de vencer, ele recebe o lembrete de renovação com o Pix.</p>
</main>
</div>
</body>
</html>
