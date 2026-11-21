<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
exigir_modulo('equipe');
$empresaId = (int)empresa_id_atual();

// Limite do plano (antes não era verificado em lugar nenhum)
$lim = $pdo->prepare("SELECT p.max_profissionais FROM empresas e LEFT JOIN planos p ON p.id=e.plano_id WHERE e.id=?");
$lim->execute([$empresaId]);
$maxProf = (int)($lim->fetch()['max_profissionais'] ?? 0);
$ativos = function () use ($pdo, $empresaId) { $s = $pdo->prepare("SELECT COUNT(*) c FROM profissionais WHERE empresa_id=? AND ativo=true"); $s->execute([$empresaId]); return (int)$s->fetch()['c']; };
$limiteAtingido = fn() => $maxProf > 0 && $ativos() >= $maxProf;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_profissional'])) {
    $id = (int)($_POST['profissional_id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $foto = url_valida_ou_vazia($_POST['foto'] ?? '');
    if ($nome === '') flash('erro', 'Informe o nome.');
    elseif (!$id && $limiteAtingido()) flash('erro', "Seu plano permite até $maxProf profissional(is) ativo(s). Desative alguém ou fale com o administrador para mudar de plano.");
    elseif ($id) {
        $pdo->prepare("UPDATE profissionais SET nome=?, especialidade=?, foto=? WHERE id=? AND empresa_id=?")->execute([$nome, trim($_POST['especialidade'] ?? ''), $foto ?: null, $id, $empresaId]);
        flash('sucesso', 'Profissional atualizado.');
    } else {
        $pdo->prepare("INSERT INTO profissionais (empresa_id, nome, especialidade, foto) VALUES (?,?,?,?)")->execute([$empresaId, $nome, trim($_POST['especialidade'] ?? ''), $foto ?: null]);
        flash('sucesso', 'Profissional cadastrado.');
    }
    // Comissão (%): coluna nova, salva à parte para não travar se o banco ainda não tiver
    $salvoId = $id ?: (int)$pdo->lastInsertId();
    if ($nome !== '' && $salvoId) {
        try { $pdo->prepare("UPDATE profissionais SET comissao=? WHERE id=? AND empresa_id=?")->execute([max(0, min(100, (float)str_replace(',', '.', $_POST['comissao'] ?? '0'))), $salvoId, $empresaId]); } catch (PDOException $e) {}
    }
    header('Location: profissionais.php'); exit;
}
if (isset($_GET['desativar'])) {
    $pdo->prepare("UPDATE profissionais SET ativo=false WHERE id=? AND empresa_id=?")->execute([(int)$_GET['desativar'], $empresaId]);
    header('Location: profissionais.php'); exit;
}
if (isset($_GET['ativar'])) {
    if ($limiteAtingido()) flash('erro', "Seu plano permite até $maxProf profissional(is) ativo(s).");
    else $pdo->prepare("UPDATE profissionais SET ativo=true WHERE id=? AND empresa_id=?")->execute([(int)$_GET['ativar'], $empresaId]);
    header('Location: profissionais.php'); exit;
}

$profissionais = $pdo->prepare("SELECT * FROM profissionais WHERE empresa_id=? ORDER BY ativo DESC, nome");
$profissionais->execute([$empresaId]); $profissionais = $profissionais->fetchAll();
$editando = null;
foreach ($profissionais as $p) if ((int)$p['id'] === (int)($_GET['editar'] ?? 0)) $editando = $p;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Profissionais - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Profissionais</h1><p class="subtitulo"><?= $ativos() ?> ativo(s)<?= $maxProf ? " de $maxProf permitidos no seu plano" : '' ?>. Eles aparecem no site e na escolha do agendamento.</p></div></div>
    <?= flash_render() ?>
    <form method="post" class="form-box" id="form">
        <?= csrf_campo() ?>
        <h3><?= $editando ? 'Editar profissional' : 'Novo profissional' ?></h3>
        <input type="hidden" name="profissional_id" value="<?= (int)($editando['id'] ?? 0) ?>">
        <div class="form-row">
            <div><label>Nome</label><input type="text" name="nome" required value="<?= e($editando['nome'] ?? '') ?>"></div>
            <div><label>Especialidade</label><input type="text" name="especialidade" placeholder="Ex: Barbeiro, Dentista…" value="<?= e($editando['especialidade'] ?? '') ?>"></div>
        </div>
        <label>Comissão (%) <span class="muted">— opcional, para o relatório em Desempenho</span></label>
        <input type="text" name="comissao" inputmode="decimal" placeholder="Ex: 40" style="max-width:140px" value="<?= isset($editando['comissao']) && (float)$editando['comissao'] > 0 ? e(rtrim(rtrim(number_format((float)$editando['comissao'], 2, ',', ''), '0'), ',')) : '' ?>">
        <label>Foto (URL da imagem, opcional)</label><input type="url" name="foto" placeholder="https://..." value="<?= e($editando['foto'] ?? '') ?>">
        <button type="submit" name="salvar_profissional" value="1" <?= !$editando && $limiteAtingido() ? 'disabled title="Limite do plano atingido"' : '' ?>><?= $editando ? 'Salvar alterações' : 'Adicionar' ?></button>
        <?php if ($editando): ?><a href="profissionais.php" class="btn btn-ghost" style="margin-top:18px">Cancelar</a><?php endif; ?>
    </form>
    <div class="tabela-wrap"><table>
        <thead><tr><th>Profissional</th><th>Especialidade</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($profissionais as $p): $ativo = pg_bool($p['ativo']); ?>
        <tr style="<?= $ativo ? '' : 'opacity:.6' ?>">
            <td style="display:flex;gap:10px;align-items:center"><?php if ($p['foto']): ?><img src="<?= e($p['foto']) ?>" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover"><?php else: ?><span class="avatar"><?= e(mb_strtoupper(mb_substr($p['nome'], 0, 1))) ?></span><?php endif; ?><strong><?= e($p['nome']) ?></strong></td>
            <td><?= e($p['especialidade']) ?></td>
            <td><span class="badge badge-<?= $ativo ? 'ativo' : 'inativo' ?>"><?= $ativo ? 'ativo' : 'inativo' ?></span></td>
            <td class="acoes">
                <a class="btn btn-ghost btn-sm" href="?editar=<?= $p['id'] ?>#form"><?= icone('lapis', 14) ?></a>
                <a class="btn btn-ghost btn-sm" href="?<?= $ativo ? 'desativar' : 'ativar' ?>=<?= $p['id'] ?><?= csrf_qs() ?>"><?= $ativo ? 'Desativar' : 'Ativar' ?></a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$profissionais): ?><tr class="vazio"><td colspan="4">Nenhum profissional cadastrado.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</main>
</div>
</body>
</html>
