<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$empresaId = (int)($_GET['empresa_id'] ?? 0);
$empresa = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();
if (!$empresa) { header('Location: empresas.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $urls = array_values(array_filter(array_map('url_valida_ou_vazia', preg_split('/[\r\n,]+/', $_POST['galeria_imagens'] ?? ''))));
    $pdo->prepare("UPDATE sites SET titulo=?, subtitulo=?, sobre=?, diferenciais=?, objetivo=?, galeria_imagens=?, seo_titulo=?, seo_descricao=?, publicado=?, atualizado_em=NOW() WHERE empresa_id=?")
        ->execute([
            trim($_POST['titulo'] ?? ''), trim($_POST['subtitulo'] ?? ''), trim($_POST['sobre'] ?? ''), trim($_POST['diferenciais'] ?? ''), trim($_POST['objetivo'] ?? ''),
            implode(',', $urls), mb_substr(trim($_POST['seo_titulo'] ?? ''), 0, 150), mb_substr(trim($_POST['seo_descricao'] ?? ''), 0, 255),
            isset($_POST['publicado']) ? 'true' : 'false', $empresaId,
        ]);
    flash('sucesso', 'Site atualizado!');
    header("Location: site_editor.php?empresa_id=$empresaId"); exit;
}

$site = $pdo->prepare("SELECT * FROM sites WHERE empresa_id=?");
$site->execute([$empresaId]); $site = $site->fetch();
$modoIa = pg_bool($site['ia_modo_total'] ?? false) && !empty($site['html_gerado']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Editor de Conteúdo - GDS Admin') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <a href="site_hub.php?empresa_id=<?= $empresaId ?>" class="link-voltar">← Site de <?= e($empresa['nome']) ?></a>
    <div class="page-header"><div><h1>Conteúdo e SEO</h1><p class="subtitulo">Link público: <a class="link" href="/<?= e($empresa['slug']) ?>" target="_blank">/<?= e($empresa['slug']) ?></a></p></div></div>
    <?= flash_render() ?>
    <?php if ($modoIa): ?><?= alerta('info', 'Este site usa o design criado pela IA: o texto "Sobre" e a galeria aparecem nele; título e subtítulo valem só para o template. Para mudar o design, use o Estúdio IA.') ?><?php endif; ?>

    <div class="duo">
    <div class="col-form">
    <form method="post" class="form-box">
        <?= csrf_campo() ?>
        <h3>Identidade</h3>
        <label>Título</label><input type="text" name="titulo" value="<?= e($site['titulo']) ?>">
        <label>Subtítulo</label><input type="text" name="subtitulo" value="<?= e($site['subtitulo']) ?>">
        <label>Objetivo principal do site</label><input type="text" name="objetivo" value="<?= e($site['objetivo']) ?>" placeholder="Ex: receber agendamentos pelo celular">

        <h3 style="margin-top:22px">O que torna este negócio único</h3>
        <label>Diferenciais (a IA usa isso para não escrever textos genéricos)</label>
        <textarea name="diferenciais" id="diferenciais" rows="3" placeholder="Ex: atendimento aos domingos, produtos veganos, 15 anos de experiência..."><?= e($site['diferenciais']) ?></textarea>

        <h3 style="margin-top:22px">Conteúdo</h3>
        <label>Sobre</label>
        <textarea name="sobre" id="sobre" rows="5"><?= e($site['sobre']) ?></textarea>
        <button type="button" class="btn btn-ghost btn-sm" onclick="gerarIA('sobre', this)" style="margin-top:8px"><?= icone('brilho', 14) ?> Escrever com IA</button>

        <label style="margin-top:18px">Galeria de fotos (uma URL de imagem por linha)</label>
        <textarea name="galeria_imagens" rows="4" placeholder="https://exemplo.com/foto1.jpg"><?= e(str_replace(',', "\n", $site['galeria_imagens'] ?? '')) ?></textarea>

        <h3 style="margin-top:22px">SEO (como aparece no Google)</h3>
        <label>Título de SEO <span class="muted" id="contTitulo"></span></label>
        <input type="text" name="seo_titulo" id="seo_titulo" value="<?= e($site['seo_titulo']) ?>" maxlength="60" oninput="contar()">
        <label>Meta descrição <span class="muted" id="contDesc"></span></label>
        <textarea name="seo_descricao" id="seo_descricao" rows="2" maxlength="160" oninput="contar()"><?= e($site['seo_descricao']) ?></textarea>
        <button type="button" class="btn btn-ghost btn-sm" onclick="gerarIA('seo', this)" style="margin-top:8px"><?= icone('brilho', 14) ?> Gerar com IA</button>

        <label class="check" style="margin-top:22px"><input type="checkbox" name="publicado" <?= pg_bool($site['publicado']) ? 'checked' : '' ?>> Site publicado (visível para todos)</label>
        <button type="submit">Salvar</button>
    </form>
    <?php if (!ia_configurada()): ?><p class="dica">Os botões de IA precisam de uma chave em Configurações.</p><?php endif; ?>
    </div>

    <div class="col-preview">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <strong>Pré-visualização</strong>
            <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('previewFrame').src='/site/preview.php?empresa_id=<?= $empresaId ?>&modo=<?= $modoIa ? 'ia' : 'template' ?>&t='+Date.now()"><?= icone('atualizar', 14) ?> Atualizar</button>
        </div>
        <iframe id="previewFrame" src="/site/preview.php?empresa_id=<?= $empresaId ?>&modo=<?= $modoIa ? 'ia' : 'template' ?>" title="Pré-visualização"></iframe>
        <p class="dica">Salve para ver as mudanças na pré-visualização.</p>
    </div>
    </div>
</main>
</div>
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
function contar() {
    document.getElementById('contTitulo').textContent = '(' + document.getElementById('seo_titulo').value.length + '/60)';
    document.getElementById('contDesc').textContent = '(' + document.getElementById('seo_descricao').value.length + '/160)';
}
contar();
async function gerarIA(acao, btn) {
    const original = btn.innerHTML; btn.disabled = true; btn.textContent = 'Gerando…';
    const fd = new FormData();
    fd.append('acao', acao);
    fd.append('empresa_id', '<?= $empresaId ?>');
    fd.append('diferenciais_atual', document.getElementById('diferenciais').value);
    try {
        const r = await fetch('ia_endpoint.php', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await r.json();
        if (acao === 'seo' && data.seo_titulo) {
            document.getElementById('seo_titulo').value = data.seo_titulo;
            document.getElementById('seo_descricao').value = data.seo_descricao;
            contar();
        } else if (data.texto) {
            document.getElementById('sobre').value = data.texto;
        } else { alert(data.erro || 'Não foi possível gerar agora.'); }
    } catch (e) { alert('Erro ao chamar a IA.'); }
    btn.disabled = false; btn.innerHTML = original;
}
</script>
</body>
</html>
