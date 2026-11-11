<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_plano'])) {
    $id = (int)($_POST['plano_id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $valor = valor_decimal($_POST['valor'] ?? '', -1);
    if ($nome === '' || $valor < 0) { flash('erro', 'Informe nome e valor válidos.'); header('Location: planos.php'); exit; }
    $dados = [$nome, $valor, ($_POST['ciclo'] ?? '') === 'anual' ? 'anual' : 'mensal', max(1, (int)($_POST['max_profissionais'] ?? 1)),
              isset($_POST['permite_pix']) ? 'true' : 'false', isset($_POST['permite_ia']) ? 'true' : 'false'];
    if ($id) {
        $pdo->prepare("UPDATE planos SET nome=?, valor=?, ciclo=?, max_profissionais=?, permite_pix=?, permite_ia=? WHERE id=?")->execute([...$dados, $id]);
        flash('sucesso', 'Plano atualizado.');
    } else {
        $pdo->prepare("INSERT INTO planos (nome, valor, ciclo, max_profissionais, permite_pix, permite_ia) VALUES (?,?,?,?,?,?)")->execute($dados);
        flash('sucesso', 'Plano criado.');
    }
    header('Location: planos.php'); exit;
}
if (isset($_GET['desativar']) || isset($_GET['ativar'])) {
    $pdo->prepare("UPDATE planos SET ativo=? WHERE id=?")->execute([isset($_GET['ativar']) ? 'true' : 'false', (int)($_GET['ativar'] ?? $_GET['desativar'])]);
    header('Location: planos.php'); exit;
}

$planos = $pdo->query("SELECT p.*, (SELECT COUNT(*) FROM empresas e WHERE e.plano_id=p.id) AS qtd FROM planos p ORDER BY valor")->fetchAll();
$editando = null;
foreach ($planos as $p) if ((int)$p['id'] === (int)($_GET['editar'] ?? 0)) $editando = $p;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Planos - GDS Admin') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Planos</h1><p class="subtitulo">O que cada assinatura inclui. "Permite IA" libera o Estúdio IA para a própria empresa (se ativado em Configurações).</p></div></div>
    <?= flash_render() ?>
    <form method="post" class="form-box" id="form">
        <?= csrf_campo() ?>
        <h3><?= $editando ? 'Editar plano' : 'Novo plano' ?></h3>
        <input type="hidden" name="plano_id" value="<?= (int)($editando['id'] ?? 0) ?>">
        <div class="form-row">
            <div><label>Nome</label><input type="text" name="nome" required value="<?= e($editando['nome'] ?? '') ?>"></div>
            <div><label>Valor (R$)</label><input type="text" inputmode="decimal" name="valor" required value="<?= e(isset($editando['valor']) ? number_format($editando['valor'], 2, ',', '') : '') ?>" placeholder="99,90"></div>
        </div>
        <div class="form-row">
            <div><label>Ciclo</label><select name="ciclo"><option value="mensal">Mensal</option><option value="anual" <?= ($editando['ciclo'] ?? '') === 'anual' ? 'selected' : '' ?>>Anual</option></select></div>
            <div><label>Máx. de profissionais</label><input type="number" min="1" name="max_profissionais" value="<?= (int)($editando['max_profissionais'] ?? 1) ?>"></div>
        </div>
        <label class="check" style="margin-top:14px"><input type="checkbox" name="permite_pix" <?= !$editando || pg_bool($editando['permite_pix']) ? 'checked' : '' ?>> Permite PIX no site</label>
        <label class="check"><input type="checkbox" name="permite_ia" <?= $editando && pg_bool($editando['permite_ia']) ? 'checked' : '' ?>> Permite IA (Estúdio IA para a empresa)</label>
        <button type="submit" name="salvar_plano" value="1"><?= $editando ? 'Salvar alterações' : 'Criar plano' ?></button>
        <?php if ($editando): ?><a href="planos.php" class="btn btn-ghost" style="margin-top:18px">Cancelar</a><?php endif; ?>
    </form>
    <div class="tabela-wrap"><table>
        <thead><tr><th>Nome</th><th>Valor</th><th>Profissionais</th><th>PIX</th><th>IA</th><th>Empresas</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($planos as $p): $ativo = pg_bool($p['ativo']); ?>
        <tr>
            <td><strong><?= e($p['nome']) ?></strong></td>
            <td>R$ <?= number_format($p['valor'], 2, ',', '.') ?><span class="mini">/<?= $p['ciclo'] === 'anual' ? 'ano' : 'mês' ?></span></td>
            <td><?= (int)$p['max_profissionais'] ?></td>
            <td><?= pg_bool($p['permite_pix']) ? '✓' : '—' ?></td>
            <td><?= pg_bool($p['permite_ia']) ? '✓' : '—' ?></td>
            <td><?= (int)$p['qtd'] ?></td>
            <td><span class="badge badge-<?= $ativo ? 'ativo' : 'inativo' ?>"><?= $ativo ? 'ativo' : 'inativo' ?></span></td>
            <td class="acoes">
                <a class="btn btn-ghost btn-sm" href="?editar=<?= $p['id'] ?>#form"><?= icone('lapis', 14) ?></a>
                <a class="btn btn-ghost btn-sm" href="?<?= $ativo ? 'desativar' : 'ativar' ?>=<?= $p['id'] ?><?= csrf_qs() ?>"><?= $ativo ? 'Desativar' : 'Ativar' ?></a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</main>
</div>
</body>
</html>
