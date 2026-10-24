<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$empresaId = (int)($_GET['empresa_id'] ?? 0);
$empresa = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();
if (!$empresa) { header('Location: empresas.php'); exit; }

$site = $pdo->prepare("SELECT * FROM sites WHERE empresa_id=?");
$site->execute([$empresaId]); $site = $site->fetch();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Site de <?= e($empresa['nome']) ?> - GDS Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Site — <?= e($empresa['nome']) ?></h1>
    <p style="color:#9aa0c0;margin-bottom:20px;">
        Status: <span class="badge badge-<?= pg_bool($site['publicado'] ?? false) ? 'ativo' : 'pendente' ?>"><?= pg_bool($site['publicado'] ?? false) ? 'publicado' : 'não publicado' ?></span>
        <?php if (pg_bool($site['ia_modo_total'] ?? false)): ?> · <span class="badge badge-ativo">modo IA total ativo</span><?php endif; ?>
        &nbsp;·&nbsp;<a href="/<?= e($empresa['slug']) ?>" target="_blank" style="color:#4f7cff;">ver site público</a>
    </p>

    <div class="card" style="background:linear-gradient(135deg,#20254f,#161a3a);border:1px solid #4f7cff;margin-bottom:20px;">
        <div class="label" style="font-size:16px;">✨ Criador de Site com IA</div>
        <p style="font-size:13px;color:#9aa0c0;margin:8px 0 12px;">Caminho recomendado: conte sobre o negócio e a IA desenha o site inteiro (layout, cores, animações, e só as funções que fizerem sentido pro negócio). Ativa sozinho assim que gerar.</p>
        <a class="btn" href="ia_criador_site.php?empresa_id=<?= $empresaId ?>">Abrir Criador de IA</a>
    </div>

    <p style="color:#5c6199;font-size:12px;margin-bottom:8px;">Ajustes manuais (opcional, depois de gerar com IA):</p>
    <div class="cards" style="flex-wrap:wrap;">
        <div class="card" style="min-width:220px;">
            <div class="label">Editor de Conteúdo</div>
            <p style="font-size:13px;color:#9aa0c0;margin:6px 0 10px;">Título, sobre, diferenciais, galeria, SEO e publicar.</p>
            <a class="btn btn-sm" href="site_editor.php?empresa_id=<?= $empresaId ?>">Abrir</a>
        </div>
        <div class="card" style="min-width:220px;">
            <div class="label">Layout do Site</div>
            <p style="font-size:13px;color:#9aa0c0;margin:6px 0 10px;">Cor e estilo visual (só usado quando o modo IA está desativado).</p>
            <a class="btn btn-sm" href="site_layout.php?empresa_id=<?= $empresaId ?>">Abrir</a>
        </div>
        <div class="card" style="min-width:220px;">
            <div class="label">Blocos de Conteúdo</div>
            <p style="font-size:13px;color:#9aa0c0;margin:6px 0 10px;">Seções livres — diferenciais, FAQ, promoções.</p>
            <a class="btn btn-sm" href="site_blocos.php?empresa_id=<?= $empresaId ?>">Abrir</a>
        </div>
    </div>

    <div style="margin-top:20px;">
        <strong style="font-size:14px;">Pré-visualização atual</strong>
        <iframe src="/site/preview.php?empresa_id=<?= $empresaId ?>&modo=<?= pg_bool($site['ia_modo_total'] ?? false) ? 'ia' : 'template' ?>"
            style="width:100%;height:500px;border:1px solid #2a2f5c;border-radius:12px;background:#fff;margin-top:8px;"></iframe>
    </div>
</div>
</div>
</body>
</html>
