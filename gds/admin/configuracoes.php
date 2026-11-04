<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$segredos = ['ia_api_key_gemini', 'ia_api_key_anthropic', 'ia_api_key_openai', 'mercadopago_access_token', 'google_maps_key', 'pexels_api_key', 'img_openai_key', 'img_pollinations_key', 'smtp_senha', 'google_places_key', 'pixabay_api_key', 'unsplash_api_key', 'img_cf_token', 'azure_tts_key'];
$provedores = ['gemini', 'anthropic', 'openai'];

// Testa a IA com uma chamada curtinha (o botão "Testar" chama via fetch)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['testar_ia'])) {
    $t0 = microtime(true);
    $prov = in_array($_POST['provedor'] ?? '', $provedores, true) ? $_POST['provedor'] : get_config('ia_provider', 'gemini');
    $r = chamar_ia_provedor($prov, 'Responda apenas: OK', 50, 30, null, null, ['esforco' => 'low']);
    responder_json($r['ok'] ? ['ok' => true, 'msg' => ia_nome_provedor($prov) . ' funcionando! Resposta em ' . round(microtime(true) - $t0, 1) . 's.'] : ['ok' => false, 'msg' => $r['erro']]);
}
// Testa a geração de foto: tenta os serviços na ordem e diz qual funcionou
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['testar_imagem'])) {
    @set_time_limit(240); session_write_close();
    $t0 = microtime(true);
    $r = img_ia_gerar(img_ia_prompt_foto('a cozy local coffee shop counter with a smiling barista, morning light'), '4:5', 'ia/teste');
    responder_json($r['ok'] ? ['ok' => true, 'url' => $r['url'], 'msg' => 'Gerou com ' . img_ia_nome($r['provedor']) . ' em ' . round(microtime(true) - $t0) . 's.' . ($r['tentativas'] ? ' Antes falhou: ' . implode(' · ', $r['tentativas']) : '')]
                             : ['ok' => false, 'msg' => $r['erro']]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['testar_voz'])) {
    require_once __DIR__ . '/../includes/marketing_ia.php';
    session_write_close();
    $r = mkt_voz('Olá! Esta é a voz dos seus vídeos. Seu cliente marca o horário sozinho, e a confirmação chega no WhatsApp.');
    responder_json($r['ok'] ? ['ok' => true, 'msg' => 'Voz funcionando — ouça abaixo.', 'audio' => $r['url']] : ['ok' => false, 'msg' => $r['erro']]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['testar_fotos'])) {
    session_write_close();
    $out = [];
    foreach (fotos_fontes() as $f) { $n = count(fotos_buscar_em($f, 'barbershop', 4, 'landscape')); $out[] = ucfirst($f) . ': ' . ($n ? "✔ $n fotos" : '✖ não respondeu'); }
    responder_json(['ok' => (bool)$out, 'msg' => $out ? implode(' · ', $out) : 'Nenhum banco de fotos ligado.']);
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
    set_config('img_ia_provedor', array_key_exists($_POST['img_ia_provedor'] ?? '', img_ia_provedores()) ? $_POST['img_ia_provedor'] : 'auto');
    set_config('smtp_host', preg_replace('/[^a-z0-9.\-]/i', '', trim($_POST['smtp_host'] ?? '')));
    set_config('smtp_porta', (string)(in_array((int)($_POST['smtp_porta'] ?? 465), [25, 465, 587, 2525], true) ? (int)$_POST['smtp_porta'] : 465));
    set_config('smtp_usuario', filter_var(trim($_POST['smtp_usuario'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '');
    set_config('smtp_nome', mb_substr(trim($_POST['smtp_nome'] ?? ''), 0, 60));
    set_config('img_ia_automatico', isset($_POST['img_ia_automatico']) ? '1' : '0');
    set_config('img_gemini_modelo', preg_replace('/[^a-z0-9.\-]/', '', strtolower(trim($_POST['img_gemini_modelo'] ?? ''))));
    set_config('img_cf_conta', preg_replace('/[^a-f0-9]/', '', strtolower(trim($_POST['img_cf_conta'] ?? ''))));
    set_config('img_cf_modelo', array_key_exists($_POST['img_cf_modelo'] ?? '', img_ia_cf_modelos()) ? $_POST['img_cf_modelo'] : '');
    set_config('img_poll_modelo', preg_replace('/[^a-z0-9.\/_\-]/', '', strtolower(trim($_POST['img_poll_modelo'] ?? ''))));
    set_config('fotos_openverse', isset($_POST['fotos_openverse']) ? '1' : '0');
    set_config('azure_tts_regiao', preg_replace('/[^a-z0-9]/', '', strtolower(trim($_POST['azure_tts_regiao'] ?? ''))) ?: 'brazilsouth');
    require_once __DIR__ . '/../includes/marketing_ia.php';
    set_config('azure_tts_voz', array_key_exists($_POST['azure_tts_voz'] ?? '', mkt_vozes()) ? $_POST['azure_tts_voz'] : 'pt-BR-FranciscaNeural');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['testar_email'])) {
    require_once __DIR__ . '/../includes/email.php';
    $para = trim($_POST['email_teste'] ?? '');
    $r = gds_enviar_email($para, 'Teste de e-mail da plataforma', email_modelo('Funcionou! ✅', ['Este é um e-mail de teste da sua plataforma. Se chegou, a recuperação de senha e os avisos por e-mail estão funcionando.']));
    flash($r['ok'] ? 'sucesso' : 'erro', $r['ok'] ? "E-mail de teste enviado para $para (via " . strtoupper($r['via']) . '). Confira também o spam.' : 'Não enviou: ' . $r['erro']);
    header('Location: configuracoes.php#email'); exit;
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
        <h3 id="ia"><?= icone('brilho', 16) ?> Inteligência Artificial</h3>
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

        <h3 style="margin-top:24px" id="fotos">Fotos profissionais para os sites (banco de fotos grátis)</h3>
        <p class="dica">Sites sem fotos próprias ganham fotos reais do tipo de negócio, e o Marketing busca fotos para as artes. A plataforma tenta os bancos na ordem: Pexels → Pixabay → Unsplash → Openverse.</p>
        <label class="check"><input type="checkbox" name="fotos_openverse" value="1" <?= get_config('fotos_openverse', '1') === '1' ? 'checked' : '' ?>> Openverse — <strong>sem chave</strong>, só fotos de domínio público (não precisam de crédito)</label>
        <?= campo_segredo('pixabay_api_key', 'Pixabay — chave grátis', $cfg['pixabay_api_key'], 'Cole a chave do Pixabay') ?>
        <p class="dica">Crie a conta em <a class="link" href="https://pixabay.com/accounts/register/" target="_blank" rel="noopener">pixabay.com</a> e copie a chave em <a class="link" href="https://pixabay.com/api/docs/" target="_blank" rel="noopener">pixabay.com/api/docs</a> (aparece no meio da página, em "key"). As fotos são baixadas para o seu servidor, como o Pixabay exige.</p>
        <?= campo_segredo('unsplash_api_key', 'Unsplash — Access Key grátis', $cfg['unsplash_api_key'], 'Access Key do Unsplash') ?>
        <p class="dica">Em <a class="link" href="https://unsplash.com/oauth/applications" target="_blank" rel="noopener">unsplash.com/oauth/applications</a> → New Application → copie a <strong>Access Key</strong> (50 buscas por hora no modo demo).</p>
        <?= campo_segredo('pexels_api_key', 'Pexels — API Key (só se você já tiver)', $cfg['pexels_api_key'], 'Chave antiga do Pexels') ?>
        <p class="dica">O Pexels pausou a criação de chaves novas. Se você já tem uma, ela continua funcionando.</p>
        <div class="acoes-form" style="margin-top:6px"><button type="button" class="btn btn-ghost btn-sm" onclick="testarMidia('testar_fotos', this)">Testar bancos de fotos</button></div>

        <h3 style="margin-top:24px" id="fotos-ia">Fotos exclusivas geradas por IA</h3>
        <p class="dica"><strong>O plano grátis do Google Gemini não gera mais imagem</strong> (só com faturamento). O caminho grátis que funciona hoje é o <strong>Cloudflare Workers AI</strong> (FLUX, cerca de 200 fotos por dia, sem cartão) — e o Pollinations como reserva.</p>
        <label>Quem gera as fotos</label>
        <select name="img_ia_provedor"><?php foreach (img_ia_provedores() as $k => $v): ?><option value="<?= $k ?>" <?= get_config('img_ia_provedor', 'auto') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
        <details class="dica-passos" <?= get_config('img_cf_conta', '') === '' ? 'open' : '' ?>><summary><strong>Cloudflare (grátis) — como pegar o ID e o token em 2 minutos</strong></summary>
            <ol class="dica"><li>Crie a conta grátis em <a class="link" href="https://dash.cloudflare.com/sign-up" target="_blank" rel="noopener">dash.cloudflare.com</a> (não pede cartão).</li>
            <li>No menu, abra <strong>AI → Workers AI</strong> e clique em <strong>Use REST API</strong>.</li>
            <li>Copie o <strong>Account ID</strong> e clique em <strong>Create a Workers AI API Token</strong> → Create → copie o token.</li></ol></details>
        <div class="form-row">
            <div><label>Cloudflare — Account ID</label><input type="text" name="img_cf_conta" value="<?= e(get_config('img_cf_conta', '')) ?>" placeholder="32 letras e números" autocomplete="off"></div>
            <div><label>Modelo</label><select name="img_cf_modelo"><?php foreach (img_ia_cf_modelos() as $k => $v): ?><option value="<?= e($k) ?>" <?= get_config('img_cf_modelo', '') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        </div>
        <?= campo_segredo('img_cf_token', 'Cloudflare — API Token (Workers AI)', $cfg['img_cf_token'], 'Token do Workers AI') ?>
        <?= campo_segredo('img_pollinations_key', 'Pollinations — chave sk_ (grátis em enter.pollinations.ai/keys)', $cfg['img_pollinations_key'], 'sk_...') ?>
        <?= campo_segredo('img_openai_key', 'OpenAI — API Key (pago, opcional)', $cfg['img_openai_key'], 'sk-...') ?>
        <details><summary class="dica">Avançado</summary>
            <label>Modelo do Pollinations (vazio = padrão)</label><input type="text" name="img_poll_modelo" value="<?= e(get_config('img_poll_modelo', '')) ?>" placeholder="black-forest-labs/flux.1-schnell">
            <label>Modelo de imagem do Gemini (só com faturamento)</label><input type="text" name="img_gemini_modelo" value="<?= e(get_config('img_gemini_modelo', '')) ?>" placeholder="gemini-2.5-flash-image">
        </details>
        <label class="check"><input type="checkbox" name="img_ia_automatico" value="1" <?= get_config('img_ia_automatico', '1') === '1' ? 'checked' : '' ?>> Gerar sozinho 3 fotos para cada site publicado sem foto (pela rotina automática / cron)</label>
        <p class="dica">Ordem que a plataforma vai tentar agora: <strong><?= e(implode(' → ', array_map('img_ia_nome', img_ia_cadeia())) ?: 'nenhum') ?></strong>. Se um falhar ou acabar a cota do dia, o próximo gera.</p>
        <div class="acoes-form" style="margin-top:6px"><button type="button" class="btn btn-ghost btn-sm" onclick="testarMidia('testar_imagem', this)">Testar geração de foto</button></div>
        <div id="resTesteMidia" class="dica" aria-live="polite"></div>

        <h3 style="margin-top:24px" id="voz">Voz dos vídeos (narração com IA)</h3>
        <?php require_once __DIR__ . '/../includes/marketing_ia.php'; ?>
        <p class="dica">Os Reels do Marketing saem narrados por uma voz neural em português do Brasil. Grátis no plano F0 da <strong>Azure Speech</strong>: 500 mil caracteres por mês (uns 1.000 vídeos curtos).</p>
        <details class="dica-passos" <?= get_config('azure_tts_key', '') === '' ? 'open' : '' ?>><summary><strong>Como pegar a chave grátis em 3 minutos</strong></summary>
            <ol class="dica"><li>Crie a conta grátis em <a class="link" href="https://azure.microsoft.com/free" target="_blank" rel="noopener">azure.microsoft.com/free</a>.</li>
            <li>No <a class="link" href="https://portal.azure.com/#create/Microsoft.CognitiveServicesSpeechServices" target="_blank" rel="noopener">portal</a>, crie um recurso <strong>Speech</strong>: região <strong>Brazil South</strong> e tipo de preço <strong>Free F0</strong>.</li>
            <li>Abra o recurso → <strong>Chaves e Ponto de Extremidade</strong> → copie a <strong>Chave 1</strong> e cole abaixo. A região fica <code>brazilsouth</code>.</li></ol></details>
        <div class="form-row">
            <div><label>Região</label><input type="text" name="azure_tts_regiao" value="<?= e(get_config('azure_tts_regiao', 'brazilsouth')) ?>" placeholder="brazilsouth"></div>
            <div><label>Voz</label><select name="azure_tts_voz"><?php foreach (mkt_vozes() as $k => $v): ?><option value="<?= e($k) ?>" <?= get_config('azure_tts_voz', '') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        </div>
        <?= campo_segredo('azure_tts_key', 'Azure Speech — Chave 1', $cfg['azure_tts_key'], 'Chave do recurso Speech') ?>
        <p class="dica">Sem a Azure, o Marketing oferece a voz grátis do <strong>Puter</strong> (no navegador, pede um login grátis na 1ª vez).</p>
        <div class="acoes-form" style="margin-top:6px"><button type="button" class="btn btn-ghost btn-sm" onclick="testarMidia('testar_voz', this, 'resTesteVoz')">Testar a voz</button></div>
        <div id="resTesteVoz" class="dica" aria-live="polite"></div>

        <h3 style="margin-top:24px" id="email">E-mail (recuperação de senha e avisos)</h3>
        <p class="dica">Sem isso, os e-mails saem pela função mail() da hospedagem e quase sempre não chegam. Na Hostinger: crie um e-mail (ex.: contato@seudominio.com.br) em <strong>E-mails</strong> e use servidor <code>smtp.hostinger.com</code>, porta <code>465</code>, usuário = o e-mail completo e a senha dele.</p>
        <div class="form-row">
            <div><label>Servidor SMTP</label><input type="text" name="smtp_host" value="<?= e(get_config('smtp_host', '')) ?>" placeholder="smtp.hostinger.com"></div>
            <div><label>Porta</label><select name="smtp_porta"><?php foreach ([465 => '465 (SSL — recomendado)', 587 => '587 (STARTTLS)', 2525 => '2525', 25 => '25'] as $pt => $rot): ?><option value="<?= $pt ?>" <?= (int)get_config('smtp_porta', '465') === $pt ? 'selected' : '' ?>><?= $rot ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-row">
            <div><label>E-mail (usuário SMTP e remetente)</label><input type="email" name="smtp_usuario" value="<?= e(get_config('smtp_usuario', '')) ?>" placeholder="contato@seudominio.com.br"></div>
            <div><label>Nome do remetente</label><input type="text" name="smtp_nome" value="<?= e(get_config('smtp_nome', '')) ?>" placeholder="GDS Sites"></div>
        </div>
        <?= campo_segredo('smtp_senha', 'Senha do e-mail', $cfg['smtp_senha'], 'Senha da caixa de e-mail') ?>
        <?php if (get_config('email_ultimo', '')): ?><p class="dica">Último envio: <?= e(get_config('email_ultimo', '')) ?></p><?php endif; ?>

        <h3 style="margin-top:24px">Mapas e Analytics</h3>
        <?= campo_segredo('google_maps_key', 'Google Maps — API Key', $cfg['google_maps_key'], 'Opcional (sem chave usa o mapa simples)') ?>
        <?= campo_segredo('google_places_key', 'Google Places API (New) — para a Prospecção de clientes (se vazio, usa a chave do Maps)', $cfg['google_places_key'], 'AIza...') ?>
        <label>Google Analytics — ID</label>
        <input type="text" name="google_analytics_id" value="<?= e($cfg['google_analytics_id']) ?>" placeholder="G-XXXXXXXXXX">

        <button type="submit" name="salvar_config" value="1">Salvar configurações</button>
    </form>

    <form method="post" class="form-box largo">
        <?= csrf_campo() ?>
        <h3>Testar o envio de e-mail</h3>
        <label>Enviar um e-mail de teste para</label>
        <input type="email" name="email_teste" required value="<?= e($_SESSION['email'] ?? '') ?>">
        <button type="submit" name="testar_email" value="1">Enviar teste</button>
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
// Testa fotos (salve antes: o teste usa as chaves já gravadas)
async function testarMidia(acao, btn, alvo) {
    const el = document.getElementById(alvo || 'resTesteMidia');
    btn.disabled = true; el.style.color = ''; el.textContent = acao === 'testar_imagem' ? 'Gerando uma foto de teste (pode levar até 1 minuto)…' : acao === 'testar_voz' ? 'Gerando a voz…' : 'Consultando os bancos de fotos…';
    try {
        const d = await post({ [acao]: 1 });
        el.style.color = d.ok ? 'var(--ok)' : 'var(--danger)';
        el.textContent = d.msg;
        if (d.audio) { const a = document.createElement('audio'); a.controls = true; a.src = d.audio; a.style.cssText = 'display:block;margin-top:8px'; el.appendChild(a); a.play().catch(() => {}); }
        if (d.url) { const i = new Image(); i.src = d.url; i.alt = 'Foto de teste gerada'; i.style.cssText = 'display:block;max-width:220px;border-radius:10px;margin-top:8px'; el.appendChild(i); }
    } catch (e) { el.textContent = 'O teste demorou demais ou falhou. Tente de novo.'; }
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
