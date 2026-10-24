<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$empresaId = (int)($_GET['empresa_id'] ?? 0);
$empresa = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();
if (!$empresa) { header('Location: empresas.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $urls = array_filter(array_map('trim', explode("\n", $_POST['galeria_imagens'] ?? '')));
    $stmt = $pdo->prepare("UPDATE sites SET titulo=?, subtitulo=?, sobre=?, diferenciais=?, galeria_imagens=?, seo_titulo=?, seo_descricao=?, publicado=? WHERE empresa_id=?");
    $stmt->execute([
        $_POST['titulo'], $_POST['subtitulo'], $_POST['sobre'], $_POST['diferenciais'], implode(',', $urls),
        $_POST['seo_titulo'], $_POST['seo_descricao'],
        isset($_POST['publicado']) ? 'true' : 'false', $empresaId
    ]);
    header("Location: site_editor.php?empresa_id=$empresaId&ok=1"); exit;
}

$site = $pdo->prepare("SELECT * FROM sites WHERE empresa_id=?");
$site->execute([$empresaId]); $site = $site->fetch();
$iaConfigurada = trim(get_config('ia_api_key', '')) !== '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Editor de Conteúdo - GDS Admin</title>
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
    <h1>Editor de Conteúdo — <?= e($empresa['nome']) ?></h1>
    <p style="margin-bottom:14px;"><a href="site_hub.php?empresa_id=<?= $empresaId ?>" style="color:#4f7cff;font-size:13px;">← voltar pro painel de site desta empresa</a></p>
    <?php if (isset($_GET['ok'])): ?><div class="alert-erro" style="background:#1f4d2e;color:#5ee06c;">Site atualizado!</div><?php endif; ?>
    <p style="color:#9aa0c0;margin-bottom:14px;">
        Link público: <a href="/<?= e($empresa['slug']) ?>" target="_blank" style="color:#4f7cff;">/<?= e($empresa['slug']) ?></a>
    </p>

    <div class="duo">
    <div class="col-form">
    <div class="form-box">
        <form method="post">
            <h3>Identidade</h3>
            <label>Título</label>
            <input type="text" name="titulo" id="titulo" value="<?= e($site['titulo']) ?>">
            <label>Subtítulo</label>
            <input type="text" name="subtitulo" value="<?= e($site['subtitulo']) ?>">

            <h3 style="margin-top:22px;">O que torna este negócio único</h3>
            <label>Diferenciais (isso evita a IA gerar textos genéricos)</label>
            <textarea name="diferenciais" id="diferenciais" rows="3" placeholder="Ex: Atendimento aos domingos, produtos veganos, 15 anos de experiência..."><?= e($site['diferenciais']) ?></textarea>

            <h3 style="margin-top:22px;">Conteúdo</h3>
            <label>Sobre</label>
            <textarea name="sobre" id="sobre" rows="4"><?= e($site['sobre']) ?></textarea>
            <button type="button" class="btn btn-sm" onclick="gerarComIA('sobre','sobre')" style="margin-top:8px;">✨ Melhorar com IA</button>
            <span id="statusSobre" style="font-size:12px;color:#9aa0c0;margin-left:8px;"></span>

            <label style="margin-top:18px;">Galeria de fotos (uma URL de imagem por linha, opcional)</label>
            <textarea name="galeria_imagens" rows="3" placeholder="https://exemplo.com/foto1.jpg"><?= e(str_replace(',', "\n", $site['galeria_imagens'] ?? '')) ?></textarea>

            <h3 style="margin-top:22px;">SEO</h3>
            <label>Título de SEO</label>
            <input type="text" name="seo_titulo" id="seo_titulo" value="<?= e($site['seo_titulo']) ?>" maxlength="60">
            <label>Meta descrição</label>
            <textarea name="seo_descricao" id="seo_descricao" rows="2" maxlength="155"><?= e($site['seo_descricao']) ?></textarea>
            <button type="button" class="btn btn-sm" onclick="gerarSEO()" style="margin-top:8px;">✨ Gerar com IA</button>
            <span id="statusSeo" style="font-size:12px;color:#9aa0c0;margin-left:8px;"></span>

            <label style="display:flex;align-items:center;gap:8px;margin-top:22px;">
                <input type="checkbox" name="publicado" style="width:auto;" <?= pg_bool($site['publicado']) ? 'checked' : '' ?>> Publicar site
            </label>
            <button type="submit" style="margin-top:14px;">Salvar</button>
        </form>
    </div>
    <?php if (!$iaConfigurada): ?>
    <p style="color:#5c6199;font-size:12px;margin-top:10px;">Os botões de IA precisam de uma chave em Configurações.</p>
    <?php endif; ?>
    </div>

    <div class="col-preview">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <strong style="font-size:14px;">Pré-visualização</strong>
            <button type="button" class="btn btn-sm" onclick="atualizarPreview()">🔄 Atualizar</button>
        </div>
        <iframe id="previewFrame" src="/site/preview.php?empresa_id=<?= $empresaId ?>"></iframe>
    </div>
    </div>
</div>
</div>

<script>
function atualizarPreview() {
    document.getElementById('previewFrame').src = '/site/preview.php?empresa_id=<?= $empresaId ?>&t=' + Date.now();
}
async function gerarComIA(acao, campoId) {
    const status = document.getElementById('status' + campoId.charAt(0).toUpperCase() + campoId.slice(1));
    status.innerText = 'Gerando...';
    const diferenciaisEl = document.getElementById('diferenciais');
    const diferenciaisAtual = diferenciaisEl ? encodeURIComponent(diferenciaisEl.value) : '';
    try {
        const resp = await fetch('ia_endpoint.php?empresa_id=<?= $empresaId ?>&acao=' + acao + '&diferenciais_atual=' + diferenciaisAtual);
        const data = await resp.json();
        if (data.texto) {
            document.getElementById(campoId).value = data.texto;
            status.innerText = 'Sugestão aplicada!';
        } else {
            status.innerText = data.erro || 'Erro ao gerar.';
        }
    } catch (e) { status.innerText = 'Erro ao chamar IA.'; }
}
async function gerarSEO() {
    const status = document.getElementById('statusSeo');
    status.innerText = 'Gerando...';
    const diferenciaisEl = document.getElementById('diferenciais');
    const diferenciaisAtual = diferenciaisEl ? encodeURIComponent(diferenciaisEl.value) : '';
    try {
        const resp = await fetch('ia_endpoint.php?empresa_id=<?= $empresaId ?>&acao=seo&diferenciais_atual=' + diferenciaisAtual);
        const data = await resp.json();
        if (data.seo_titulo && data.seo_descricao) {
            document.getElementById('seo_titulo').value = data.seo_titulo;
            document.getElementById('seo_descricao').value = data.seo_descricao;
            status.innerText = 'Sugestão aplicada!';
        } else {
            status.innerText = data.erro || 'Tente de novo.';
        }
    } catch (e) { status.innerText = 'Erro ao chamar IA.'; }
}
</script>
</body>
</html>
