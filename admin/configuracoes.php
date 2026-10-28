<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$segredos = ['ia_api_key_gemini', 'ia_api_key_anthropic', 'ia_api_key_openai', 'mercadopago_access_token', 'google_maps_key', 'pexels_api_key'];
$provedores = ['gemini', 'anthropic', 'openai'];

// Testa a IA com uma chamada curtinha (o botão "Testar" chama via fetch)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['testar_ia'])) {
    $t0 = microtime(true);
    $prov = in_array($_POST['provedor'] ?? '', $provedores, true) ? $_POST['provedor'] : get_config('ia_provider', 'gemini');
    $r = chamar_ia_provedor($prov, 'Responda apenas: OK', 50, 30, null, null, ['esforco' => 'low']);
    responder_json($r['ok'] ? ['ok' => true, 'msg' => ia_nome_provedor($prov) . ' funcionando! Resposta em ' . round(microtime(true) - $t0, 1) . 's.'] : ['ok' => false, 'msg' => $r['erro']]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['listar_modelos'])) {
    $prov = in_array($_POST['provedor'] ?? '', $provedores, true) ? $_POST['provedor'] : 'gemini';
    responder_json(ia_listar_modelos($prov));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_config'])) {
    // A chave antiga (única) passa a ser a chave do provedor que estava em uso
    $antiga = trim(get_config('ia_api_key', ''));
    if ($antiga !== '' && get_config('ia_api_key_' . get_config('ia_provider', 'gemini'), '') === '') {
        set_config('ia_api_key_' . get_config('ia_provider', 'gemini'), $antiga);
    }
    set_config('ia_provider', in_array($_POST['ia_provider'] ?? '', $provedores, true) ? $_POST['ia_provider'] : 'gemini');
    $reserva = in_array($_POST['ia_provider_reserva'] ?? '', $provedores, true) && $_POST['ia_provider_reserva'] !== $_POST['ia_provider'] ? $_POST['ia_provider_reserva'] : '';
    set_config('ia_provider_reserva', $reserva);
    set_config('ia_modelo_gemini', trim($_POST['ia_modelo_gemini'] ?? '') ?: GDS_MODELO_GEMINI_PADRAO);
    set_config('ia_modelo_gemini_reserva', trim($_POST['ia_modelo_gemini_reserva'] ?? ''));
    set_config('ia_modelo_anthropic', trim($_POST['ia_modelo_anthropic'] ?? '') ?: GDS_MODELO_ANTHROPIC_PADRAO);
    set_config('ia_openai_preset', array_key_exists($_POST['ia_openai_preset'] ?? '', ia_presets_openai()) ? $_POST['ia_openai_preset'] : 'groq');
    set_config('ia_openai_url', url_valida_ou_vazia($_POST['ia_openai_url'] ?? '') ?: 'https://api.groq.com/openai/v1');
    set_config('ia_modelo_openai', trim($_POST['ia_modelo_openai'] ?? ''));
    set_config('ia_estudio_empresas', isset($_POST['ia_estudio_empresas']) ? '1' : '0');
    set_config('ia_limite_diario_empresa', (string)max(0, (int)($_POST['ia_limite_diario_empresa'] ?? 20)));
    set_config('google_analytics_id', trim($_POST['google_analytics_id'] ?? ''));
    // Campos secretos: vazio = manter o valor atual (a tela nunca mostra a chave inteira)
    foreach ($segredos as $chave) {
        $v = trim($_POST[$chave] ?? '');
        if (isset($_POST['limpar_' . $chave])) {
            set_config($chave, '');
            if (str_starts_with($chave, 'ia_api_key_') && substr($chave, 11) === get_config('ia_provider', 'gemini')) set_config('ia_api_key', '');
        }
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
foreach (array_merge($segredos, ['ia_provider', 'ia_provider_reserva', 'google_analytics_id']) as $c) $cfg[$c] = get_config($c, '');
foreach ($provedores as $pv) $cfg['ia_api_key_' . $pv] = ia_chave($pv);
$presets = ia_presets_openai();
$presetAtual = get_config('ia_openai_preset', 'groq');
$openaiUrl = get_config('ia_openai_url', $presets['groq']['url']);
$modeloOpenai = get_config('ia_modelo_openai', '');
$modeloGeminiReserva = get_config('ia_modelo_gemini_reserva', '');
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
        <label>IA principal</label>
        <select name="ia_provider" id="provedor" onchange="trocarProvedor()">
            <option value="gemini" <?= $cfg['ia_provider'] == 'gemini' ? 'selected' : '' ?>>Google Gemini (tem plano gratuito)</option>
            <option value="openai" <?= $cfg['ia_provider'] == 'openai' ? 'selected' : '' ?>>Groq / OpenRouter / Mistral (planos gratuitos)</option>
            <option value="anthropic" <?= $cfg['ia_provider'] == 'anthropic' ? 'selected' : '' ?>>Anthropic Claude (pago — melhor qualidade de design)</option>
        </select>
        <label>IA reserva (usada automaticamente quando a principal estoura o limite grátis ou fica fora do ar)</label>
        <select name="ia_provider_reserva">
            <option value="">Nenhuma</option>
            <option value="gemini" <?= $cfg['ia_provider_reserva'] == 'gemini' ? 'selected' : '' ?>>Google Gemini</option>
            <option value="openai" <?= $cfg['ia_provider_reserva'] == 'openai' ? 'selected' : '' ?>>Groq / OpenRouter / Mistral</option>
            <option value="anthropic" <?= $cfg['ia_provider_reserva'] == 'anthropic' ? 'selected' : '' ?>>Anthropic Claude</option>
        </select>
        <p class="dica">Dica: com IAs grátis, configure duas (ex.: Gemini + Groq) — quando uma bate o limite do dia, a outra assume.</p>

        <div class="bloco-ia" data-prov="gemini">
            <h3 style="margin-top:20px">Google Gemini</h3>
            <?= campo_segredo('ia_api_key_gemini', 'Chave do Gemini', $cfg['ia_api_key_gemini'], 'AIza...') ?>
            <p class="dica">Grátis em aistudio.google.com → "Get API key" (sem cartão).</p>
            <label>Modelo</label><input type="text" name="ia_modelo_gemini" list="modelos-gemini" value="<?= e($modeloGemini) ?>">
            <label>Modelo reserva do Gemini (opcional)</label><input type="text" name="ia_modelo_gemini_reserva" list="modelos-gemini" value="<?= e($modeloGeminiReserva) ?>" placeholder="ex.: um modelo &quot;flash-lite&quot;, que costuma ter mais cota grátis">
            <datalist id="modelos-gemini"></datalist>
            <div class="linha-botoes"><button type="button" class="btn btn-ghost btn-sm" onclick="listarModelos('gemini', this)">Ver modelos disponíveis</button><button type="button" class="btn btn-ghost btn-sm" onclick="testarIA('gemini', this)">Testar</button></div>
            <p class="dica resultado" data-res="gemini"></p>
        </div>

        <div class="bloco-ia" data-prov="openai">
            <h3 style="margin-top:20px">Groq / OpenRouter / Mistral</h3>
            <label>Serviço</label>
            <select name="ia_openai_preset" id="preset" onchange="aplicarPreset()">
                <?php foreach ($presets as $k => $pr): ?><option value="<?= $k ?>" data-url="<?= e($pr['url']) ?>" data-modelo="<?= e($pr['modelo']) ?>" data-chave="<?= e($pr['chave']) ?>" <?= $presetAtual === $k ? 'selected' : '' ?>><?= e($pr['nome']) ?></option><?php endforeach; ?>
            </select>
            <?= campo_segredo('ia_api_key_openai', 'Chave', $cfg['ia_api_key_openai'], 'Cole a chave do serviço escolhido') ?>
            <p class="dica" id="ondeChave"></p>
            <label>Endereço da API</label><input type="url" name="ia_openai_url" id="openaiUrl" value="<?= e($openaiUrl) ?>">
            <label>Modelo</label><input type="text" name="ia_modelo_openai" id="openaiModelo" list="modelos-openai" value="<?= e($modeloOpenai) ?>">
            <datalist id="modelos-openai"></datalist>
            <div class="linha-botoes"><button type="button" class="btn btn-ghost btn-sm" onclick="listarModelos('openai', this)">Ver modelos disponíveis</button><button type="button" class="btn btn-ghost btn-sm" onclick="testarIA('openai', this)">Testar</button></div>
            <p class="dica resultado" data-res="openai"></p>
        </div>

        <div class="bloco-ia" data-prov="anthropic">
            <h3 style="margin-top:20px">Anthropic Claude</h3>
            <?= campo_segredo('ia_api_key_anthropic', 'Chave da Anthropic', $cfg['ia_api_key_anthropic'], 'sk-ant-...') ?>
            <p class="dica">Em console.anthropic.com → API Keys (pago).</p>
            <label>Modelo</label><input type="text" name="ia_modelo_anthropic" list="modelos-anthropic" value="<?= e($modeloAnthropic) ?>">
            <datalist id="modelos-anthropic"></datalist>
            <div class="linha-botoes"><button type="button" class="btn btn-ghost btn-sm" onclick="listarModelos('anthropic', this)">Ver modelos disponíveis</button><button type="button" class="btn btn-ghost btn-sm" onclick="testarIA('anthropic', this)">Testar</button></div>
            <p class="dica resultado" data-res="anthropic"></p>
        </div>
        <p class="dica">Salve antes de testar se você colou uma chave nova.</p>

        <h3 style="margin-top:24px">Estúdio IA para as empresas</h3>
        <label class="check"><input type="checkbox" name="ia_estudio_empresas" <?= $estudioEmpresas ? 'checked' : '' ?>> Empresas com plano "Permite IA" podem criar e editar o próprio site no Estúdio IA</label>
        <label>Limite de gerações/ajustes por empresa por dia (0 = sem limite)</label>
        <input type="number" min="0" name="ia_limite_diario_empresa" value="<?= e($limite) ?>">

        <h3 style="margin-top:24px">Pagamentos e comunicação</h3>
        <?= campo_segredo('mercadopago_access_token', 'Mercado Pago — Access Token (cobrança das assinaturas)', $cfg['mercadopago_access_token'], 'APP_USR-...') ?>
        <p class="dica">WhatsApp automático e domínios próprios têm telas próprias: <a class="link" href="whatsapp.php">WhatsApp</a> · <a class="link" href="dominios.php">Domínios</a>.</p>

        <h3 style="margin-top:24px">Fotos profissionais para os sites</h3>
        <?= campo_segredo('pexels_api_key', 'Pexels — API Key (grátis)', $cfg['pexels_api_key'], 'Cole a chave do pexels.com/api') ?>
        <p class="dica">Sites sem fotos próprias ganham fotos reais do tipo de negócio (topo do site e no Estúdio IA). Crie a chave grátis em <a class="link" href="https://www.pexels.com/api/" target="_blank" rel="noopener">pexels.com/api</a> → "Your API Key".</p>

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
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
function trocarProvedor() {
    // Mostra a configuração da IA principal e da reserva; esconde as outras
    const usados = [document.getElementById('provedor').value, document.querySelector('[name=ia_provider_reserva]').value];
    document.querySelectorAll('.bloco-ia').forEach(b => b.hidden = !usados.includes(b.dataset.prov));
}
document.querySelector('[name=ia_provider_reserva]').addEventListener('change', trocarProvedor);
trocarProvedor();
function aplicarPreset(inicial) {
    const o = document.getElementById('preset').selectedOptions[0];
    document.getElementById('ondeChave').textContent = 'Onde pegar a chave: ' + o.dataset.chave;
    if (inicial) return;
    if (o.dataset.url) document.getElementById('openaiUrl').value = o.dataset.url;
    document.getElementById('openaiModelo').value = o.dataset.modelo;
}
aplicarPreset(true);
async function post(dados) {
    const fd = new FormData(); Object.entries(dados).forEach(([k, v]) => fd.append(k, v));
    const r = await fetch('configuracoes.php', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' } });
    return r.json();
}
function resultado(prov, txt, ok) {
    const el = document.querySelector('[data-res="' + prov + '"]');
    el.textContent = txt; el.style.color = ok === undefined ? '' : (ok ? 'var(--ok)' : 'var(--danger)');
}
async function testarIA(prov, btn) {
    btn.disabled = true; resultado(prov, 'Testando…');
    try { const d = await post({ testar_ia: 1, provedor: prov }); resultado(prov, d.msg, d.ok); }
    catch (e) { resultado(prov, 'Falha ao testar.', false); }
    btn.disabled = false;
}
async function listarModelos(prov, btn) {
    btn.disabled = true; resultado(prov, 'Buscando modelos…');
    try {
        const d = await post({ listar_modelos: 1, provedor: prov });
        if (!d.ok) { resultado(prov, d.erro, false); }
        else {
            const lista = document.getElementById('modelos-' + prov);
            lista.innerHTML = d.modelos.map(m => '<option value="' + m.replace(/"/g, '') + '">').join('');
            const gratis = prov === 'openai' ? d.modelos.filter(m => m.endsWith(':free')).length : 0;
            resultado(prov, d.modelos.length + ' modelos disponíveis para a sua chave' + (gratis ? ' (' + gratis + ' grátis, terminados em ":free")' : '') + ' — clique no campo Modelo para escolher.', true);
        }
    } catch (e) { resultado(prov, 'Falha ao buscar modelos.', false); }
    btn.disabled = false;
}
</script>
</body>
</html>
