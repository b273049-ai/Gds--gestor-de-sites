<?php
// Recursos do site: cartão fidelidade, app no celular, novidades (stories), antes e depois,
// cupom de aniversário e vídeo no topo (includes/recursos_site.php)
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
require_once __DIR__ . '/../includes/recursos_site.php';
require_once __DIR__ . '/../includes/atendimento.php';
$empresaId = (int)empresa_id_atual();
$st = $pdo->prepare("SELECT * FROM sites WHERE empresa_id=?"); $st->execute([$empresaId]); $site = $st->fetch() ?: [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rec_acao'])) {
    try { [$ok, $msg] = rec_salvar_post($empresaId, $site); } catch (PDOException $e) { [$ok, $msg] = [false, 'O banco ainda está sendo atualizado. Recarregue a página e tente de novo.']; }
    flash($ok ? 'sucesso' : 'erro', $msg);
    header('Location: recursos.php'); exit;
}
$st = $pdo->prepare("SELECT e.*, seg.modo_atendimento AS modo_segmento FROM empresas e LEFT JOIN segmentos seg ON seg.id=e.segmento_id WHERE e.id=?"); $st->execute([$empresaId]); $empresa = $st->fetch();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Recursos do site - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Recursos do site</h1><p class="subtitulo">Funções que fazem o cliente voltar: cartão fidelidade, app no celular, novidades, antes e depois, presente de aniversário e vídeo.</p></div></div>
    <?= flash_render() ?>
    <?= rec_form_html($empresa, $site, atendimento_modo_da_linha($empresa)) ?>
</main>
</div>
</body>
</html>
