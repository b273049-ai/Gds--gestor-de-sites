<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$empresaId = (int)($_GET['empresa_id'] ?? 0);
$empresa = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();
if (!$empresa) { header('Location: empresas.php'); exit; }

$site = $pdo->prepare("SELECT * FROM sites WHERE empresa_id=?");
$site->execute([$empresaId]); $site = $site->fetch();
if (!$site) {
    $pdo->prepare("INSERT INTO sites (empresa_id, titulo, publicado) VALUES (?, ?, false)")->execute([$empresaId, $empresa['nome']]);
    header("Location: site_hub.php?empresa_id=$empresaId"); exit;
}
$modoIa = pg_bool($site['ia_modo_total'] ?? false) && !empty($site['html_gerado']);

$conta = function ($tabela, $extra = '') use ($pdo, $empresaId) {
    $s = $pdo->prepare("SELECT COUNT(*) c FROM $tabela WHERE empresa_id=? $extra"); $s->execute([$empresaId]); return (int)$s->fetch()['c'];
};
$checklist = [
    ['Serviços cadastrados', $conta('servicos', 'AND ativo=true') > 0],
    ['Profissionais cadastrados', $conta('profissionais', 'AND ativo=true') > 0],
    ['WhatsApp e endereço', !empty($empresa['whatsapp']) && !empty($empresa['endereco'])],
    ['Logo ou fotos', !empty($empresa['logo']) || !empty($site['galeria_imagens']) || !empty($site['banner_imagem'])],
    ['Site criado (IA ou template)', $modoIa || trim($site['sobre'] ?? '') !== ''],
    ['SEO (título e descrição)', !empty($site['seo_titulo']) && !empty($site['seo_descricao'])],
    ['Publicado', pg_bool($site['publicado'])],
];
$feitos = count(array_filter($checklist, fn($c) => $c[1]));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Site de ' . $empresa['nome'] . ' - GDS Admin') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <a href="empresas.php" class="link-voltar">← Empresas</a>
    <div class="page-header">
        <div>
            <h1>Site — <?= e($empresa['nome']) ?></h1>
            <p class="subtitulo">
                <span class="badge badge-<?= pg_bool($site['publicado']) ? 'ativo' : 'pendente' ?>"><?= pg_bool($site['publicado']) ? 'publicado' : 'rascunho' ?></span>
                <span class="pill <?= $modoIa ? 'pill-ok' : 'pill-neutro' ?>"><?= $modoIa ? 'design criado pela IA' : 'template padrão' ?></span>
                · <a class="link" href="/<?= e($empresa['slug']) ?>" target="_blank">/<?= e($empresa['slug']) ?></a>
            </p>
        </div>
        <div class="acoes"><a class="btn btn-ghost" href="empresa_editar.php?id=<?= $empresaId ?>"><?= icone('lapis') ?> Dados da empresa</a></div>
    </div>
    <?= flash_render() ?>

    <div class="grid-2">
        <div class="card card-ia">
            <div class="label" style="font-size:15px;color:#fff"><?= icone('brilho') ?> Estúdio IA <span class="pill pill-ok">recomendado</span></div>
            <p style="font-size:13px;color:var(--text-dim);margin:6px 0 14px">Descreva o negócio e a IA cria o site inteiro (layout, cores, textos e animações) usando os dados reais. Depois é só pedir ajustes no chat — com histórico de versões e visualização em celular/tablet/computador.</p>
            <a class="btn btn-ia" href="ia_estudio.php?empresa_id=<?= $empresaId ?>"><?= icone('brilho') ?> <?= empty($site['html_gerado']) ? 'Criar site com IA' : 'Abrir Estúdio IA' ?></a>
        </div>
        <div class="card">
            <div class="label">Prontidão do site — <?= $feitos ?>/<?= count($checklist) ?></div>
            <div class="progresso" style="margin:6px 0 12px"><div style="width:<?= round($feitos / count($checklist) * 100) ?>%"></div></div>
            <ul class="checklist"><?php foreach ($checklist as [$rotulo, $ok]): ?><li class="<?= $ok ? 'ok' : '' ?>"><?= $ok ? '✓' : '○' ?> <?= e($rotulo) ?></li><?php endforeach; ?></ul>
        </div>
    </div>

    <h2>Ajustes manuais</h2>
    <div class="acoes-grid">
        <div class="card"><div class="label"><?= icone('lapis', 16) ?> Conteúdo e SEO</div><p>Título, sobre, diferenciais, galeria, SEO e publicação.</p><a class="btn btn-ghost btn-sm" href="site_editor.php?empresa_id=<?= $empresaId ?>">Abrir</a></div>
        <div class="card"><div class="label"><?= icone('layout', 16) ?> Layout do template</div><p>Estilo, banner e ordem das seções (quando não usa o design da IA).</p><a class="btn btn-ghost btn-sm" href="site_layout.php?empresa_id=<?= $empresaId ?>">Abrir</a></div>
        <div class="card"><div class="label"><?= icone('prancheta', 16) ?> Blocos de conteúdo</div><p>Seções livres: diferenciais, FAQ, promoções.</p><a class="btn btn-ghost btn-sm" href="site_blocos.php?empresa_id=<?= $empresaId ?>">Abrir</a></div>
    </div>

    <h2>Pré-visualização atual</h2>
    <iframe class="preview-iframe" src="/site/preview.php?empresa_id=<?= $empresaId ?>&modo=<?= $modoIa ? 'ia' : 'template' ?>" title="Pré-visualização"></iframe>
</main>
</div>
</body>
</html>
