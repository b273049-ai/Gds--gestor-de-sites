<?php
// Admin → Site da empresa → Recursos do site (o mesmo painel que a empresa vê em "Recursos do site")
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');
require_once __DIR__ . '/../includes/recursos_site.php';
require_once __DIR__ . '/../includes/atendimento.php';
$empresaId = (int)($_GET['empresa_id'] ?? 0);
$st = $pdo->prepare("SELECT e.*, seg.modo_atendimento AS modo_segmento FROM empresas e LEFT JOIN segmentos seg ON seg.id=e.segmento_id WHERE e.id=?"); $st->execute([$empresaId]); $empresa = $st->fetch();
if (!$empresa) { header('Location: empresas.php'); exit; }
$st = $pdo->prepare("SELECT * FROM sites WHERE empresa_id=?"); $st->execute([$empresaId]); $site = $st->fetch() ?: [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rec_acao'])) {
    try { [$ok, $msg] = rec_salvar_post($empresaId, $site); } catch (PDOException $e) { [$ok, $msg] = [false, 'O banco ainda está sendo atualizado. Recarregue a página e tente de novo.']; }
    flash($ok ? 'sucesso' : 'erro', $msg);
    header("Location: site_recursos.php?empresa_id=$empresaId"); exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Recursos do site - ' . $empresa['nome']) ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Recursos do site — <?= e($empresa['nome']) ?></h1><p class="subtitulo"><a href="site_hub.php?empresa_id=<?= $empresaId ?>">← Voltar ao site da empresa</a></p></div>
        <a class="btn btn-ghost" href="/<?= e($empresa['slug']) ?>" target="_blank" rel="noopener">Ver o site</a></div>
    <?= flash_render() ?>
    <?= rec_form_html($empresa, $site, atendimento_modo_da_linha($empresa)) ?>
</main>
</div>
</body>
</html>
