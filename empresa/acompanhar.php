<?php
// Acompanhe o atendimento: a equipe toca a etapa e, se quiser, manda uma foto. O cliente vê no link
// do agendamento e recebe o aviso de "pronto" no WhatsApp (includes/recursos_servicos.php).
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
require_once __DIR__ . '/../includes/recursos_servicos.php';
$empresaId = (int)empresa_id_atual();
$st = $pdo->prepare("SELECT e.*, seg.slug AS segmento_slug, seg.nome AS segmento_nome, seg.grupo AS segmento_grupo FROM empresas e LEFT JOIN segmentos seg ON seg.id=e.segmento_id WHERE e.id=?");
$st->execute([$empresaId]); $empresa = $st->fetch();
$etapas = acomp_etapas(site_nicho_da_empresa($empresa));
$agId = (int)($_GET['ag'] ?? $_POST['ag'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $foto = null;
    if (($_FILES['foto']['error'] ?? 4) === UPLOAD_ERR_OK) {
        require_once __DIR__ . '/../includes/orcamento.php';
        $foto = orc_gravar_imagem($_FILES['foto']['tmp_name'], (int)$_FILES['foto']['size'], 'atendimentos/' . $empresaId, 'ag' . $agId . '-' . bin2hex(random_bytes(5)));
        if (!$foto) { flash('erro', 'A foto não foi aceita (JPG, PNG ou WebP, até 8 MB).'); header('Location: acompanhar.php?ag=' . $agId); exit; }
    }
    wa_adiar();
    try { [$ok, $msg] = acomp_avancar($empresa, $agId, (int)($_POST['etapa'] ?? 0), $foto); } catch (PDOException $e) { [$ok, $msg] = [false, 'O banco ainda está sendo atualizado. Recarregue a página.']; }
    flash($ok ? 'sucesso' : 'erro', $msg);
    header('Location: acompanhar.php?ag=' . $agId); exit;
}
$a = null;
try {
    $st = $pdo->prepare("SELECT a.*, c.nome AS cliente_nome, c.telefone, s.nome AS servico_nome FROM agendamentos a LEFT JOIN clientes c ON c.id=a.cliente_id LEFT JOIN servicos s ON s.id=a.servico_id WHERE a.id=? AND a.empresa_id=?");
    $st->execute([$agId, $empresaId]); $a = $st->fetch();
} catch (PDOException $e) {}
if (!$a) { flash('erro', 'Agendamento não encontrado.'); header('Location: agenda.php'); exit; }
$atual = (int)($a['etapa'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Acompanhar atendimento - Meu Negócio') ?>
<style>.ac-etapas{display:grid;gap:10px;margin:14px 0}.ac-etapas form{margin:0}.ac-etapas button{width:100%;text-align:left;display:flex;align-items:center;gap:12px;min-height:56px;font-size:1rem}
.ac-etapas .feito{opacity:.75}.ac-num{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;border:2px solid currentColor;font-weight:700;flex:none}
.ac-foto img{max-width:280px;width:100%;border-radius:12px}</style></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Acompanhar atendimento</h1><p class="subtitulo"><a class="link" href="agenda.php">← Agenda</a> · <?= e($a['cliente_nome'] ?: 'Cliente') ?><?= $a['servico_nome'] ? ' · ' . e($a['servico_nome']) : '' ?> · <?= date('d/m H:i', strtotime($a['data_hora'])) ?></p></div></div>
    <?= flash_render() ?>
    <div class="form-box largo">
        <h3>Toque na etapa em que o atendimento está</h3>
        <p class="dica"><?= $a['telefone'] ? 'Na primeira etapa o cliente recebe o link para acompanhar; na última, o aviso de pronto.' : 'Este cliente não tem WhatsApp cadastrado: as etapas aparecem só no link do agendamento.' ?></p>
        <div class="ac-etapas">
        <?php foreach ($etapas as $i => $nome): $n = $i + 1; ?>
            <form method="post" enctype="multipart/form-data"><?= csrf_campo() ?><input type="hidden" name="ag" value="<?= (int)$a['id'] ?>"><input type="hidden" name="etapa" value="<?= $n ?>">
                <button type="submit" class="btn <?= $n === $atual ? '' : 'btn-ghost' ?><?= $n < $atual ? ' feito' : '' ?>"<?= $n === $atual ? ' aria-current="step"' : '' ?>><span class="ac-num" aria-hidden="true"><?= $n <= $atual ? '✓' : $n ?></span><?= e($nome) ?></button></form>
        <?php endforeach; ?>
        </div>
        <form method="post" enctype="multipart/form-data" class="form-row"><?= csrf_campo() ?><input type="hidden" name="ag" value="<?= (int)$a['id'] ?>"><input type="hidden" name="etapa" value="<?= max(1, $atual) ?>">
            <div><label for="acFoto">Mandar uma foto agora</label><input id="acFoto" type="file" name="foto" accept="image/*" capture="environment" required></div>
            <div style="align-self:end"><button type="submit">📸 Enviar foto</button></div></form>
        <?php if (!empty($a['etapa_foto'])): ?><div class="ac-foto"><p class="dica">Última foto enviada:</p><img src="<?= e($a['etapa_foto']) ?>" alt="Última foto do atendimento"></div><?php endif; ?>
        <p class="dica">Link que o cliente acompanha: <a class="link" target="_blank" rel="noopener" href="/site/agendamento.php?t=<?= e($a['token']) ?>">abrir</a></p>
    </div>
</main>
</div>
</body>
</html>
