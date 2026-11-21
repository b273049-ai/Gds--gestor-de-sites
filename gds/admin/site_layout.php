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
$ordemPadrao = array_keys($secaoLabels);
require_once __DIR__ . '/../includes/site_render.php';
$estilos = ['' => ['Automático', 'Escolhe o estilo que combina com o tipo de negócio.']];
foreach (site_estilos() as $k => $est) $estilos[$k] = [$est['nome'], $est['desc']];

$site = $pdo->prepare("SELECT * FROM sites WHERE empresa_id=?");
$site->execute([$empresaId]); $site = $site->fetch();
$ordemAtual = !empty($site['secoes']) ? array_values(array_intersect(explode(',', $site['secoes']), $ordemPadrao)) : $ordemPadrao;
$salvarOrdem = function ($ordem) use ($pdo, $empresaId) {
    $pdo->prepare("UPDATE sites SET secoes=? WHERE empresa_id=?")->execute([implode(',', $ordem), $empresaId]);
    header("Location: site_layout.php?empresa_id=$empresaId"); exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_estilo'])) {
    $estilo = isset($estilos[$_POST['layout'] ?? '']) ? $_POST['layout'] : '';
    $pdo->prepare("UPDATE sites SET estilo=?, banner_imagem=?, cor_primaria=?, cor_secundaria=? WHERE empresa_id=?")
        ->execute([$estilo ?: null, url_valida_ou_vazia($_POST['banner_imagem'] ?? ''), cor_hex_valida($_POST['cor_primaria'] ?? '', '#4f7cff'), cor_hex_valida($_POST['cor_secundaria'] ?? '', '#16213e'), $empresaId]);
    if (isset($_POST['novas_fotos'])) { $pdo->prepare("UPDATE sites SET fotos_banco=NULL WHERE empresa_id=?")->execute([$empresaId]); }
    flash('sucesso', 'Estilo atualizado!');
    header("Location: site_layout.php?empresa_id=$empresaId"); exit;
}
if (isset($_GET['subir']) || isset($_GET['descer'])) {
    $chave = $_GET['subir'] ?? $_GET['descer'];
    $i = array_search($chave, $ordemAtual, true);
    if ($i !== false) {
        $j = isset($_GET['subir']) ? $i - 1 : $i + 1;
        if ($j >= 0 && $j < count($ordemAtual)) [$ordemAtual[$i], $ordemAtual[$j]] = [$ordemAtual[$j], $ordemAtual[$i]];
    }
    $salvarOrdem($ordemAtual);
}
if (isset($_GET['ocultar']) && !in_array($_GET['ocultar'], ['agendar', 'contato'], true)) {
    $salvarOrdem(array_values(array_diff($ordemAtual, [$_GET['ocultar']])));
}
if (isset($_GET['mostrar']) && isset($secaoLabels[$_GET['mostrar']]) && !in_array($_GET['mostrar'], $ordemAtual, true)) {
    $ordemAtual[] = $_GET['mostrar'];
    $salvarOrdem($ordemAtual);
}
$ocultas = array_diff($ordemPadrao, $ordemAtual);
$q = '?empresa_id=' . $empresaId . csrf_qs();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Layout do Site - GDS Admin') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <a href="site_hub.php?empresa_id=<?= $empresaId ?>" class="link-voltar">← Site de <?= e($empresa['nome']) ?></a>
    <div class="page-header"><div><h1>Layout do template</h1><p class="subtitulo">Vale quando o site não está usando o design criado pela IA.</p></div></div>
    <?= flash_render() ?>
    <?php if (pg_bool($site['ia_modo_total'] ?? false)): ?><?= alerta('info', 'Agora o site usa o design da IA. Estas opções passam a valer se você voltar para o template (no Estúdio IA → Versões).') ?><?php endif; ?>

    <div class="duo">
    <div class="col-form">
    <form method="post" class="form-box">
        <?= csrf_campo() ?>
        <h3>Estilo visual</h3>
        <?php foreach ($estilos as $val => [$nome, $desc]): ?>
        <label class="estilo-opcao"><input type="radio" name="layout" value="<?= $val ?>" <?= (string)($site['estilo'] ?? '') === (string)$val ? 'checked' : '' ?>><span><strong><?= $nome ?></strong><small><?= $desc ?></small></span></label>
        <?php endforeach; ?>
        <div class="form-row">
            <div><label>Cor primária</label><input type="color" name="cor_primaria" value="<?= e(cor_hex_valida($site['cor_primaria'] ?? '', '#4f7cff')) ?>"></div>
            <div><label>Cor secundária</label><input type="color" name="cor_secundaria" value="<?= e(cor_hex_valida($site['cor_secundaria'] ?? '', '#16213e')) ?>"></div>
        </div>
        <p class="dica">Cor primária azul padrão (#4f7cff) = usa a cor do estilo.</p>
        <label>Foto do topo do site (opcional — sem ela, usa a galeria ou fotos profissionais do Pexels)</label>
        <input type="url" name="banner_imagem" value="<?= e($site['banner_imagem']) ?>" placeholder="https://exemplo.com/banner.jpg">
        <label class="check" style="margin-top:10px"><input type="checkbox" name="novas_fotos"> Buscar novas fotos profissionais</label>
        <button type="submit" name="salvar_estilo" value="1">Salvar estilo</button>
    </form>

    <div class="form-box">
        <h3>Ordem e visibilidade das seções</h3>
        <p class="dica" style="margin-bottom:12px">Seções sem conteúdo cadastrado não aparecem no site mesmo se estiverem ativas aqui.</p>
        <div class="tabela-wrap"><table><tbody>
            <?php foreach ($ordemAtual as $i => $chave): ?>
            <tr>
                <td><?= e($secaoLabels[$chave]) ?></td>
                <td class="acoes">
                    <?php if ($i > 0): ?><a class="btn btn-ghost btn-sm" href="<?= $q ?>&subir=<?= $chave ?>" aria-label="Subir">↑</a><?php endif; ?>
                    <?php if ($i < count($ordemAtual) - 1): ?><a class="btn btn-ghost btn-sm" href="<?= $q ?>&descer=<?= $chave ?>" aria-label="Descer">↓</a><?php endif; ?>
                    <?php if (!in_array($chave, ['agendar', 'contato'], true)): ?><a class="btn btn-ghost btn-sm" href="<?= $q ?>&ocultar=<?= $chave ?>">Ocultar</a><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php foreach ($ocultas as $chave): ?>
            <tr style="opacity:.55"><td><?= e($secaoLabels[$chave]) ?> (oculta)</td><td class="acoes"><a class="btn btn-ghost btn-sm" href="<?= $q ?>&mostrar=<?= $chave ?>">Mostrar</a></td></tr>
            <?php endforeach; ?>
        </tbody></table></div>
    </div>
    </div>

    <div class="col-preview">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;"><strong>Pré-visualização</strong>
            <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('previewFrame').src='/site/preview.php?empresa_id=<?= $empresaId ?>&t='+Date.now()"><?= icone('atualizar', 14) ?> Atualizar</button></div>
        <iframe id="previewFrame" src="/site/preview.php?empresa_id=<?= $empresaId ?>" title="Pré-visualização"></iframe>
    </div>
    </div>
</main>
</div>
</body>
</html>
