<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$segredos = ['ia_api_key', 'mercadopago_access_token', 'whatsapp_api_token', 'google_maps_key'];

// Testa a IA com uma chamada curtinha (o botão "Testar" chama via fetch)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['testar_ia'])) {
    $t0 = microtime(true);
    $r = chamar_ia('Responda apenas: OK', 50, 30, null, null, ['esforco' => 'low']);
    responder_json($r['ok'] ? ['ok' => true, 'msg' => 'Funcionando! Resposta em ' . round(microtime(true) - $t0, 1) . 's.'] : ['ok' => false, 'msg' => $r['erro']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_config'])) {
    set_config('ia_provider', in_array($_POST['ia_provider'] ?? '', ['gemini', 'anthropic'], true) ? $_POST['ia_provider'] : 'gemini');
    set_config('ia_modelo_gemini', trim($_POST['ia_modelo_gemini'] ?? '') ?: GDS_MODELO_GEMINI_PADRAO);
    set_config('ia_modelo_anthropic', trim($_POST['ia_modelo_anthropic'] ?? '') ?: GDS_MODELO_ANTHROPIC_PADRAO);
    set_config('ia_estudio_empresas', isset($_POST['ia_estudio_empresas']) ? '1' : '0');
    set_config('ia_limite_diario_empresa', (string)max(0, (int)($_POST['ia_limite_diario_empresa'] ?? 20)));
    set_config('google_analytics_id', trim($_POST['google_analytics_id'] ?? ''));
    // Campos secretos: vazio = manter o valor atual (a tela nunca mostra a chave inteira)
    foreach ($segredos as $chave) {
        $v = trim($_POST[$chave] ?? '');
        if (isset($_POST['limpar_' . $chave])) set_config($chave, '');
        elseif ($v !== '') set_config($chave, $v);
    }
    flash('sucesso', 'Configurações salvas!');
    header('Location: configuracoes.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trocar_senha'])) {
    $usuario = $pdo->prepare("SELECT * FROM usuarios WHERE id=?");
    $usuario->execute([usuario_logado()]); $usuario = $usuario->fetch();
    $nova = $_POST['senha_nova'] ?? '';
    if (!password_verify($_POST['senha_atual'] ?? '', $usuario['senha_hash'])) flash('erro', 'Senha atual incorreta.');
    elseif (strlen($nova) < 8) flash('erro', 'A nova senha precisa ter no mínimo 8 caracteres.');
    elseif ($nova !== ($_POST['senha_confirmar'] ?? '')) flash('erro', 'A confirmação não confere.');
    else {
        $pdo->prepare("UPDATE usuarios SET senha_hash=? WHERE id=?")->execute([password_hash($nova, PASSWORD_DEFAULT), usuario_logado()]);
        flash('sucesso', 'Senha alterada com sucesso!');
    }
    header('Location: configuracoes.php#senha'); exit;
}

function mascarar($v) {
    $v = (string)$v;
    if ($v === '') return '';
    return strlen($v) <= 8 ? str_repeat('•', strlen($v)) : substr($v, 0, 4) . str_repeat('•', 8) . substr($v, -4);
}
$cfg = [];
foreach (array_merge($segredos, ['ia_provider', 'google_analytics_id']) as $c) $cfg[$c] = get_config($c, '');
$cfg['ia_provider'] = $cfg['ia_provider'] ?: 'gemini';
$modeloGemini = get_config('ia_modelo_gemini', GDS_MODELO_GEMINI_PADRAO);
$modeloAnthropic = get_config('ia_modelo_anthropic', GDS_MODELO_ANTHROPIC_PADRAO);
$estudioEmpresas = get_config('ia_estudio_empresas', '0') === '1';
$limite = get_config('ia_limite_diario_empresa', '20');

function campo_segredo($nome, $rotulo, $valorAtual, $placeholder) {
    $h = '<label>' . e($rotulo) . '</label><input type="password" name="' . $nome . '" autocomplete="off" placeholder="' . e($valorAtual !== '' ? 'Salvo: ' . mascarar($valorAtual) . ' — cole outra para trocar' : $placeholder) . '">';
    if ($valorAtual !== '') $h .= '<label class="check" style="margin-top:6px;font-size:12px"><input type="checkbox" name="limpar_' . $nome . '"> remover a chave salva</label>';
    return $h;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Configurações - GDS Admin') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Configurações</h1><p class="subtitulo">Chaves de integração e regras da plataforma.</p></div></div>
    <?= flash_render() ?>

    <div class="grid-2">
    <form method="post" class="form-box largo">
        <?= csrf_campo() ?>
        <h3><?= icone('brilho', 16) ?> Inteligência Artificial</h3>
        <label>Provedor</label>
        <select name="ia_provider" id="provedor" onchange="trocarProvedor()">
            <option value="gemini" <?= $cfg['ia_provider'] == 'gemini' ? 'selected' : '' ?>>Google Gemini (tem plano gratuito)</option>
            <option value="anthropic" <?= $cfg['ia_provider'] == 'anthropic' ? 'selected' : '' ?>>Anthropic Claude (pago — melhor qualidade de design)</option>
        </select>
        <?= campo_segredo('ia_api_key', 'Chave de API', $cfg['ia_api_key'], 'Cole sua chave aqui') ?>
        <div id="cfgGemini"><label>Modelo Gemini</label><input type="text" name="ia_modelo_gemini" value="<?= e($modeloGemini) ?>"><p class="dica">Chave grátis em aistudio.google.com → "Get API key".</p></div>
        <div id="cfgAnthropic"><label>Modelo Claude</label><input type="text" name="ia_modelo_anthropic" value="<?= e($modeloAnthropic) ?>"><p class="dica">Chave em console.anthropic.com → API Keys. Padrão: <?= GDS_MODELO_ANTHROPIC_PADRAO ?>.</p></div>
        <div style="display:flex;gap:10px;align-items:center;margin-top:12px"><button type="button" class="btn btn-ghost btn-sm" onclick="testarIA(this)">Testar conexão com a IA</button><span id="resTeste" class="dica" style="margin:0"></span></div>

        <h3 style="margin-top:24px">Estúdio IA para as empresas</h3>
        <label class="check"><input type="checkbox" name="ia_estudio_empresas" <?= $estudioEmpresas ? 'checked' : '' ?>> Empresas com plano "Permite IA" podem criar e editar o próprio site no Estúdio IA</label>
        <label>Limite de gerações/ajustes por empresa por dia (0 = sem limite)</label>
        <input type="number" min="0" name="ia_limite_diario_empresa" value="<?= e($limite) ?>">

        <h3 style="margin-top:24px">Pagamentos e comunicação</h3>
        <?= campo_segredo('mercadopago_access_token', 'Mercado Pago — Access Token (cobrança das assinaturas)', $cfg['mercadopago_access_token'], 'APP_USR-...') ?>
        <?= campo_segredo('whatsapp_api_token', 'WhatsApp Cloud API — Token', $cfg['whatsapp_api_token'], 'Opcional') ?>

        <h3 style="margin-top:24px">Mapas e Analytics</h3>
        <?= campo_segredo('google_maps_key', 'Google Maps — API Key', $cfg['google_maps_key'], 'Opcional (sem chave usa o mapa simples)') ?>
        <label>Google Analytics — ID</label>
        <input type="text" name="google_analytics_id" value="<?= e($cfg['google_analytics_id']) ?>" placeholder="G-XXXXXXXXXX">

        <button type="submit" name="salvar_config" value="1">Salvar configurações</button>
    </form>

    <form method="post" class="form-box largo" id="senha">
        <?= csrf_campo() ?>
        <h3>Trocar minha senha</h3>
        <label>Senha atual</label><input type="password" name="senha_atual" required autocomplete="current-password">
        <label>Nova senha (mín. 8)</label><input type="password" name="senha_nova" required minlength="8" autocomplete="new-password">
        <label>Confirmar nova senha</label><input type="password" name="senha_confirmar" required minlength="8" autocomplete="new-password">
        <button type="submit" name="trocar_senha" value="1">Trocar senha</button>
    </form>
    </div>
</main>
</div>
<script>
function trocarProvedor() {
    const p = document.getElementById('provedor').value;
    document.getElementById('cfgGemini').hidden = p !== 'gemini';
    document.getElementById('cfgAnthropic').hidden = p !== 'anthropic';
}
trocarProvedor();
async function testarIA(btn) {
    const res = document.getElementById('resTeste');
    btn.disabled = true; res.textContent = 'Testando… (salve antes se trocou a chave)';
    const fd = new FormData(); fd.append('testar_ia', '1');
    try {
        const r = await fetch('configuracoes.php', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest' } });
        const d = await r.json();
        res.textContent = d.msg; res.style.color = d.ok ? 'var(--ok)' : 'var(--danger)';
    } catch (e) { res.textContent = 'Falha ao testar.'; }
    btn.disabled = false;
}
</script>
</body>
</html>
