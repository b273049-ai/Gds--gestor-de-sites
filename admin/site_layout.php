<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$empresaId = (int)($_GET['empresa_id'] ?? 0);
$empresa = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();
if (!$empresa) { header('Location: empresas.php'); exit; }

$secaoLabels = [
    'sobre' => 'Sobre', 'servicos' => 'Serviços', 'equipe' => 'Equipe',
    'galeria' => 'Galeria', 'blocos' => 'Destaques (blocos)', 'cupons' => 'Cupons',
    'agendar' => 'Agendar', 'avaliacoes' => 'Avaliações', 'contato' => 'Contato',
];
$ordemPadrao = ['sobre','servicos','equipe','galeria','blocos','cupons','agendar','avaliacoes','contato'];

$site = $pdo->prepare("SELECT * FROM sites WHERE empresa_id=?");
$site->execute([$empresaId]); $site = $site->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_estilo'])) {
    $pdo->prepare("UPDATE sites SET layout=?, banner_imagem=? WHERE empresa_id=?")
        ->execute([$_POST['layout'], $_POST['banner_imagem'], $empresaId]);
    header("Location: site_layout.php?empresa_id=$empresaId&ok=1"); exit;
}

if (isset($_GET['subir']) || isset($_GET['descer'])) {
    $ordemAtual = !empty($site['secoes']) ? explode(',', $site['secoes']) : $ordemPadrao;
    $chave = $_GET['subir'] ?? $_GET['descer'];
    $i = array_search($chave, $ordemAtual);
    if ($i !== false) {
        $j = isset($_GET['subir']) ? $i - 1 : $i + 1;
        if ($j >= 0 && $j < count($ordemAtual)) {
            [$ordemAtual[$i], $ordemAtual[$j]] = [$ordemAtual[$j], $ordemAtual[$i]];
            $pdo->prepare("UPDATE sites SET secoes=? WHERE empresa_id=?")->execute([implode(',', $ordemAtual), $empresaId]);
        }
    }
    header("Location: site_layout.php?empresa_id=$empresaId"); exit;
}

if (isset($_GET['ocultar'])) {
    $ordemAtual = !empty($site['secoes']) ? explode(',', $site['secoes']) : $ordemPadrao;
    $ordemAtual = array_values(array_diff($ordemAtual, [$_GET['ocultar']]));
    $pdo->prepare("UPDATE sites SET secoes=? WHERE empresa_id=?")->execute([implode(',', $ordemAtual), $empresaId]);
    header("Location: site_layout.php?empresa_id=$empresaId"); exit;
}
if (isset($_GET['mostrar'])) {
    $ordemAtual = !empty($site['secoes']) ? explode(',', $site['secoes']) : $ordemPadrao;
    if (!in_array($_GET['mostrar'], $ordemAtual)) { $ordemAtual[] = $_GET['mostrar']; }
    $pdo->prepare("UPDATE sites SET secoes=? WHERE empresa_id=?")->execute([implode(',', $ordemAtual), $empresaId]);
    header("Location: site_layout.php?empresa_id=$empresaId"); exit;
}

$site = $pdo->prepare("SELECT * FROM sites WHERE empresa_id=?");
$site->execute([$empresaId]); $site = $site->fetch();
$ordemAtual = !empty($site['secoes']) ? explode(',', $site['secoes']) : $ordemPadrao;
$ocultas = array_diff($ordemPadrao, $ordemAtual);

$estilos = [
    'classico'  => ['nome' => 'Clássico', 'desc' => 'Hero à esquerda, tom tech discreto. Seguro e versátil.'],
    'bold'      => ['nome' => 'Bold', 'desc' => 'Centralizado, tipografia enorme, alto impacto visual.'],
    'editorial' => ['nome' => 'Editorial', 'desc' => 'Tipografia serifada, estilo revista, mais sofisticado.'],
    'grid'      => ['nome' => 'Grid Visual', 'desc' => 'Banner de foto em destaque, ótimo se você tem boas fotos.'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Layout do Site - GDS Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.duo { display:flex; gap:20px; flex-wrap:wrap; align-items:flex-start; }
.duo > .col-form { flex:1 1 380px; min-width:300px; }
.duo > .col-preview { flex:1 1 380px; min-width:300px; position:sticky; top:20px; }
.duo iframe { width:100%; height:520px; border:1px solid #2a2f5c; border-radius:12px; background:#fff; }
</style>
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Layout do Site — <?= e($empresa['nome']) ?></h1>
    <p style="margin-bottom:14px;"><a href="site_hub.php?empresa_id=<?= $empresaId ?>" style="color:#4f7cff;font-size:13px;">← voltar pro painel de site desta empresa</a></p>
    <?php if (isset($_GET['ok'])): ?><div class="alert-erro" style="background:#1f4d2e;color:#5ee06c;">Estilo atualizado!</div><?php endif; ?>

    <div class="duo">
    <div class="col-form">
    <div class="form-box" style="margin-bottom:24px;">
        <h3>Estilo visual</h3>
        <form method="post">
            <?php foreach ($estilos as $val => $info): ?>
            <label style="display:flex;align-items:flex-start;gap:10px;font-weight:normal;color:#e8e8f0;margin-top:12px;">
                <input type="radio" name="layout" value="<?= $val ?>" style="width:auto;margin-top:4px;" <?= ($site['layout'] ?: 'classico')==$val?'checked':'' ?>>
                <span><strong><?= $info['nome'] ?></strong><br><span style="color:#9aa0c0;font-size:13px;"><?= $info['desc'] ?></span></span>
            </label>
            <?php endforeach; ?>

            <label style="margin-top:18px;">Banner (imagem de destaque, usada no estilo "Grid Visual")</label>
            <input type="text" name="banner_imagem" value="<?= e($site['banner_imagem']) ?>" placeholder="https://exemplo.com/banner.jpg">

            <button type="submit" name="salvar_estilo" style="margin-top:16px;">Salvar Estilo</button>
        </form>
    </div>

    <div class="form-box">
        <h3>Ordem e visibilidade das seções</h3>
        <p style="color:#9aa0c0;font-size:13px;margin-bottom:12px;">Seções sem conteúdo cadastrado não aparecem no site mesmo se estiverem ativas aqui.</p>
        <table>
            <tr><th>Seção</th><th>Ação</th></tr>
            <?php foreach ($ordemAtual as $i => $chave): if (!isset($secaoLabels[$chave])) continue; ?>
            <tr>
                <td><?= e($secaoLabels[$chave]) ?></td>
                <td>
                    <?php if ($i > 0): ?><a class="btn btn-sm" href="?empresa_id=<?= $empresaId ?>&subir=<?= $chave ?>">↑</a><?php endif; ?>
                    <?php if ($i < count($ordemAtual)-1): ?><a class="btn btn-sm" href="?empresa_id=<?= $empresaId ?>&descer=<?= $chave ?>">↓</a><?php endif; ?>
                    <?php if (!in_array($chave, ['agendar','contato'])): ?>
                    <a class="btn btn-sm" href="?empresa_id=<?= $empresaId ?>&ocultar=<?= $chave ?>">Ocultar</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php foreach ($ocultas as $chave): if (!isset($secaoLabels[$chave])) continue; ?>
            <tr style="opacity:0.5;">
                <td><?= e($secaoLabels[$chave]) ?> (oculta)</td>
                <td><a class="btn btn-sm" href="?empresa_id=<?= $empresaId ?>&mostrar=<?= $chave ?>">Mostrar</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    </div>

    <div class="col-preview">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <strong style="font-size:14px;">Pré-visualização</strong>
            <button type="button" class="btn btn-sm" onclick="document.getElementById('previewFrame').src='/site/preview.php?empresa_id=<?= $empresaId ?>&t='+Date.now()">🔄 Atualizar</button>
        </div>
        <iframe id="previewFrame" src="/site/preview.php?empresa_id=<?= $empresaId ?>"></iframe>
    </div>
    </div>
</div>
</div>
</body>
</html>
