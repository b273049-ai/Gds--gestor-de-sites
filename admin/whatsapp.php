<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

// Instância "da plataforma" na Evolution: o admin conecta o número por QR aqui mesmo
function adm_wa_instancia() {
    $nome = get_config('wa_plataforma_instancia', '');
    if ($nome === '') { $nome = 'gds-plataforma'; set_config('wa_plataforma_instancia', $nome); }
    return $nome;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    @set_time_limit(60);
    $a = wa_api();
    switch ($_POST['acao']) {
        case 'testar':
            if (!wa_api_configurada()) responder_json(['ok' => false, 'erro' => 'Configure e salve a integração primeiro.']);
            $num = trim($_POST['numero'] ?? '');
            if (wa_numero($num) === '') responder_json(['ok' => false, 'erro' => 'Informe um número com DDD.']);
            if ($a['tipo'] === 'ponte') {
                if (!wa_ponte_online()) responder_json(['ok' => false, 'erro' => 'O conector está desligado. Ligue o celular/computador com o conector.']);
                $p = wa_ponte_ler($a['plat_instancia']);
                if (!$p || $p['estado'] !== 'open') responder_json(['ok' => false, 'erro' => 'Conecte o número da plataforma primeiro (Gerar QR code).']);
                wa_ponte_enfileirar_teste($num, "✅ Teste do WhatsApp da plataforma GDS. Está funcionando!");
                responder_json(['ok' => true, 'msg' => 'Na fila: o conector envia para ' . wa_numero($num) . ' em alguns segundos.']);
            }
            $r = wa_enviar_agora(['canal' => 'plataforma', 'tipo' => $a['tipo'], 'instancia' => $a['plat_instancia'], 'token' => $a['plat_token']], $num, "✅ Teste do WhatsApp da plataforma GDS. Está funcionando!");
            responder_json($r['ok'] ? ['ok' => true, 'msg' => 'Mensagem enviada para ' . wa_numero($num) . '!'] : ['ok' => false, 'erro' => $r['erro']]);
        case 'conectar':
            if ($a['tipo'] === 'ponte') { $nome = adm_wa_instancia(); wa_ponte_pedir($nome); responder_json(wa_ponte_situacao($nome)); }
            if (!in_array($a['tipo'], ['evolution', 'wppconnect'], true) || !wa_api_configurada()) responder_json(['ok' => false, 'erro' => 'Disponível com Evolution API ou WPPConnect configurados.']);
            $nome = adm_wa_instancia();
            if ($a['tipo'] === 'wppconnect') {
                $q = wa_wpp('POST', $nome, 'start-session', ['waitQrCode' => true]);
                if (!$q['ok']) responder_json(['ok' => false, 'erro' => $q['erro']]);
                $qr = $q['dados']['qrcode'] ?? null;
                responder_json(['ok' => true, 'qr' => $qr ? (str_starts_with($qr, 'data:') ? $qr : 'data:image/png;base64,' . $qr) : null, 'conectado' => in_array(strtoupper((string)($q['dados']['status'] ?? '')), ['CONNECTED', 'ISLOGGED'], true)]);
            }
            $r = wa_http('POST', $a['url'] . '/instance/create', ['instanceName' => $nome, 'qrcode' => true, 'integration' => 'WHATSAPP-BAILEYS'], ['apikey: ' . $a['chave']], 20);
            if ($r['ok'] && !empty($r['dados']['qrcode']['base64'])) responder_json(['ok' => true, 'qr' => $r['dados']['qrcode']['base64']]);
            $q = wa_http('GET', $a['url'] . '/instance/connect/' . rawurlencode($nome), null, ['apikey: ' . $a['chave']]);
            if (!$q['ok']) responder_json(['ok' => false, 'erro' => $q['erro'] ?: $r['erro']]);
            responder_json(['ok' => true, 'qr' => $q['dados']['base64'] ?? null, 'codigo' => $q['dados']['pairingCode'] ?? null, 'conectado' => ($q['dados']['instance']['state'] ?? '') === 'open']);
        case 'estado':
            $nome = adm_wa_instancia();
            if ($a['tipo'] === 'ponte') responder_json(wa_ponte_situacao($nome));
            if ($a['tipo'] === 'zapi') {
                $r = wa_http('GET', wa_zapi_base($a['plat_instancia'], $a['plat_token']) . '/status', null, $a['chave'] !== '' ? ['Client-Token: ' . $a['chave']] : []);
                responder_json(['ok' => $r['ok'], 'conectado' => !empty($r['dados']['connected']), 'erro' => $r['erro']]);
            }
            if ($a['tipo'] === 'wppconnect') {
                $r = wa_wpp('GET', $nome, 'status-session');
                responder_json(['ok' => $r['ok'], 'conectado' => in_array(strtoupper((string)($r['dados']['status'] ?? '')), ['CONNECTED', 'ISLOGGED'], true), 'erro' => $r['erro']]);
            }
            $r = wa_http('GET', $a['url'] . '/instance/connectionState/' . rawurlencode($nome), null, ['apikey: ' . $a['chave']]);
            responder_json(['ok' => $r['ok'], 'conectado' => ($r['dados']['instance']['state'] ?? '') === 'open', 'erro' => $r['erro']]);
        case 'rotina':
            responder_json(['ok' => true] + wa_rotina(true));
    }
    responder_json(['ok' => false, 'erro' => 'Ação inválida.'], 400);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar'])) {
    $tipo = in_array($_POST['wa_api_tipo'] ?? '', ['', 'ponte', 'gds', 'evolution', 'wppconnect', 'zapi', 'ultramsg', 'cloud', 'webhook'], true) ? $_POST['wa_api_tipo'] : '';
    set_config('wa_api_tipo', $tipo);
    set_config('wa_api_url', url_valida_ou_vazia($_POST['wa_api_url'] ?? ''));
    if (trim($_POST['wa_api_chave'] ?? '') !== '') set_config('wa_api_chave', trim($_POST['wa_api_chave']));
    if (isset($_POST['limpar_chave'])) set_config('wa_api_chave', '');
    set_config('wa_plataforma_instancia', trim($_POST['wa_plataforma_instancia'] ?? ''));
    if (trim($_POST['wa_plataforma_token'] ?? '') !== '') set_config('wa_plataforma_token', trim($_POST['wa_plataforma_token']));
    set_config('wa_plataforma_ativo', isset($_POST['wa_plataforma_ativo']) ? '1' : '0');
    set_config('wa_empresas_conectar', isset($_POST['wa_empresas_conectar']) ? '1' : '0');
    set_config('wa_cloud_modelo', preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['wa_cloud_modelo'] ?? ''))));
    set_config('wa_cloud_idioma', preg_replace('/[^A-Za-z_]/', '', trim($_POST['wa_cloud_idioma'] ?? '')) ?: 'pt_BR');
    // Ponte: a chave é gerada aqui e colada no conector
    if ($tipo === 'ponte' && (get_config('wa_api_chave', '') === '' || isset($_POST['nova_chave_ponte']))) set_config('wa_api_chave', bin2hex(random_bytes(16)));
    flash('sucesso', 'Integração do WhatsApp salva.');
    header('Location: whatsapp.php'); exit;
}

$a = wa_api();
if (get_config('cron_chave', '') === '') set_config('cron_chave', bin2hex(random_bytes(12)));
$cronUrl = (get_config('plataforma_url', '') ?: site_url_base_admin()) . '/cron.php?chave=' . get_config('cron_chave', '');
function site_url_base_admin() {
    $host = preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    return ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https://' : 'http://') . $host;
}
$resumo = $pdo->query("SELECT status, COUNT(*) c FROM whatsapp_mensagens WHERE criado_em > NOW() - INTERVAL '7 days' GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$empresasProprio = (int)$pdo->query("SELECT COUNT(*) FROM empresas WHERE wa_modo='proprio' AND wa_status='conectado'")->fetchColumn();
$ultimas = $pdo->query("SELECT m.*, e.nome AS empresa_nome FROM whatsapp_mensagens m LEFT JOIN empresas e ON e.id = m.empresa_id ORDER BY m.id DESC LIMIT 25")->fetchAll();
$chaveSalva = $a['chave'] !== '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('WhatsApp - GDS Admin') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>WhatsApp automático</h1>
        <p class="subtitulo">A plataforma envia sozinha as mensagens de agendamento, lembrete, orçamento e delivery para os clientes de todas as empresas.</p></div></div>
    <?= flash_render() ?>

    <div class="cards">
        <div class="card"><div class="label"><?= icone('enviar', 16) ?> Enviadas (7 dias)</div><div class="value"><?= (int)($resumo['enviado'] ?? 0) ?></div></div>
        <div class="card"><div class="label"><?= icone('chat', 16) ?> Falharam</div><div class="value"><?= (int)($resumo['erro'] ?? 0) ?></div></div>
        <div class="card"><div class="label"><?= icone('celular', 16) ?> Envio manual</div><div class="value"><?= (int)($resumo['manual'] ?? 0) ?></div><div class="sub">sem integração ativa</div></div>
        <div class="card"><div class="label"><?= icone('predio', 16) ?> Empresas com número próprio</div><div class="value"><?= $empresasProprio ?></div></div>
    </div>

    <div class="grid-2">
    <form method="post" class="form-box largo">
        <?= csrf_campo() ?>
        <h3>1. Integração</h3>
        <label>Serviço de WhatsApp</label>
        <select name="wa_api_tipo" id="tipo" onchange="mostrarTipo()">
            <option value="" <?= $a['tipo_salvo'] === '' ? 'selected' : '' ?>>Nenhum (só envio manual pelo painel)</option>
            <option value="ponte" <?= $a['tipo_salvo'] === 'ponte' ? 'selected' : '' ?>>Conector GDS no celular ou computador — grátis, funciona em qualquer hospedagem (recomendado)</option>
            <option value="gds" <?= $a['tipo_salvo'] === 'gds' ? 'selected' : '' ?>>Conector GDS em servidor (app Node.js ou VPS) — grátis, conecta por QR code</option>
            <option value="evolution" <?= $a['tipo_salvo'] === 'evolution' ? 'selected' : '' ?>>Evolution API — gratuita, conecta por QR code</option>
            <option value="wppconnect" <?= $a['tipo'] === 'wppconnect' ? 'selected' : '' ?>>WPPConnect — gratuita, código aberto, conecta por QR code</option>
            <option value="cloud" <?= $a['tipo'] === 'cloud' ? 'selected' : '' ?>>WhatsApp Cloud API OFICIAL (Meta) — sem risco de bloqueio</option>
            <option value="zapi" <?= $a['tipo'] === 'zapi' ? 'selected' : '' ?>>Z-API — paga, brasileira</option>
            <option value="ultramsg" <?= $a['tipo'] === 'ultramsg' ? 'selected' : '' ?>>UltraMsg — paga, simples</option>
            <option value="webhook" <?= $a['tipo'] === 'webhook' ? 'selected' : '' ?>>Webhook (n8n, Make ou outro gateway)</option>
        </select>
        <div data-tipo="ponte" class="ponte-box">
            <?php $ponteOn = $a['tipo'] === 'ponte' && wa_ponte_online(); ?>
            <p style="margin:12px 0 6px"><span class="pill <?= $ponteOn ? 'pill-ok' : 'pill-aviso' ?>"><?= $ponteOn ? 'Conector ligado' : 'Conector desligado' ?></span>
                <?php if (!$ponteOn && get_config('wa_ponte_visto', '') !== ''): ?><span class="dica">visto pela última vez em <?= date('d/m H:i', (int)get_config('wa_ponte_visto')) ?></span><?php endif; ?></p>
            <?php if ($a['tipo'] === 'ponte' && $chaveSalva): ?>
            <label>Endereço do site (cole no conector)</label>
            <input type="text" readonly value="<?= e((get_config('plataforma_url', '') ?: site_url_base_admin())) ?>" onclick="this.select()">
            <label>Chave do conector (cole no conector)</label>
            <input type="text" readonly value="<?= e($a['chave']) ?>" onclick="this.select()">
            <label class="check" style="font-size:12px"><input type="checkbox" name="nova_chave_ponte"> gerar uma chave nova (o conector precisa da nova)</label>
            <?php else: ?><p class="dica">Salve para gerar a chave do conector.</p><?php endif; ?>
        </div>
        <div data-tipo="gds evolution wppconnect webhook">
            <label id="rotUrl">URL do servidor</label>
            <input type="url" name="wa_api_url" value="<?= e($a['url']) ?>" placeholder="https://evolution.seudominio.com.br">
        </div>
        <div data-tipo="gds evolution wppconnect zapi webhook">
            <label id="rotChave">Chave</label>
            <input type="password" name="wa_api_chave" autocomplete="off" placeholder="<?= $chaveSalva ? 'Salva — cole outra para trocar' : '' ?>">
            <?php if ($chaveSalva): ?><label class="check" style="font-size:12px"><input type="checkbox" name="limpar_chave"> remover a chave salva</label><?php endif; ?>
        </div>

        <h3 style="margin-top:22px">2. Número da plataforma</h3>
        <p class="dica">Um número que envia para os clientes das empresas que não conectaram o próprio. A mensagem vai assinada com o nome da empresa.</p>
        <div data-tipo="ponte gds evolution wppconnect zapi ultramsg cloud webhook">
            <label id="rotInst">Nome da instância</label>
            <input type="text" name="wa_plataforma_instancia" value="<?= e($a['plat_instancia']) ?>" placeholder="gds-plataforma">
        </div>
        <div data-tipo="cloud" class="form-row">
            <div><label>Nome do modelo aprovado (opcional, recomendado)</label><input type="text" name="wa_cloud_modelo" value="<?= e($a['cloud_modelo']) ?>" placeholder="aviso_cliente"></div>
            <div><label>Idioma do modelo</label><input type="text" name="wa_cloud_idioma" value="<?= e($a['cloud_idioma']) ?>" placeholder="pt_BR"></div>
        </div>
        <p class="dica" data-tipo="cloud">Na Meta, crie um modelo da categoria <strong>Utilidade</strong> com o corpo: <code>Olá! Mensagem de {{1}}: {{2}}</code>. Ele é usado quando o cliente não falou com o número nas últimas 24h (regra da Meta).</p>
        <div data-tipo="zapi ultramsg cloud"><label id="rotToken">Token da instância</label><input type="password" name="wa_plataforma_token" autocomplete="off" placeholder="<?= $a['plat_token'] !== '' ? 'Salvo — cole outro para trocar' : '' ?>"></div>
        <label class="check" style="margin-top:10px"><input type="checkbox" name="wa_plataforma_ativo" <?= $a['plat_ativo'] ? 'checked' : '' ?>> Usar o número da plataforma para as empresas</label>
        <label class="check" data-tipo="ponte gds evolution wppconnect zapi ultramsg"><input type="checkbox" name="wa_empresas_conectar" <?= $a['empresas_conectar'] ? 'checked' : '' ?>> Deixar cada empresa conectar o próprio WhatsApp (QR code no painel dela)</label>
        <button type="submit" name="salvar" value="1">Salvar</button>
    </form>

    <section class="form-box largo">
        <h3>3. Conectar e testar</h3>
        <div data-tipo="ponte gds evolution wppconnect">
            <p class="dica">Conecte o número da plataforma lendo o QR code com o WhatsApp do celular (Aparelhos conectados → Conectar um aparelho).</p>
            <button type="button" class="btn" onclick="conectar(this)"><?= icone('celular', 16) ?> Gerar QR code</button>
            <div id="qrArea" hidden style="margin-top:14px"><div class="wa-qr"><img id="qrImg" alt="QR code"><span id="qrInfo">Aguardando leitura…</span></div></div>
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end;margin-top:14px;flex-wrap:wrap">
            <div style="flex:1;min-width:180px"><label>Enviar teste para</label><input type="tel" id="numTeste" placeholder="(11) 99999-9999"></div>
            <button type="button" class="btn btn-ghost" style="margin-top:0" onclick="testar(this)">Enviar teste</button>
        </div>
        <p class="dica" id="resTeste"></p>

        <h3 style="margin-top:22px">4. Rotina automática (lembretes)</h3>
        <p class="dica">Os lembretes e os reenvios rodam sozinhos quando alguém usa o painel. Para ficar 100% pontual, crie um <strong>Cron Job</strong> na hospedagem (Hostinger: Avançado → Cron Jobs) a cada 5 minutos com:</p>
        <code style="display:block;word-break:break-all;padding:10px;background:var(--surface-3);border-radius:8px">curl -s "<?= e($cronUrl) ?>" &gt;/dev/null</code>
        <button type="button" class="btn btn-ghost btn-sm" style="margin-top:10px" onclick="rotina(this)">Rodar agora</button>

        <details style="margin-top:18px" open data-tipo="ponte"><summary class="link" style="cursor:pointer">Passo a passo: conector no celular Android ou no computador (grátis)</summary>
            <p class="dica">O conector fica num aparelho sempre ligado e <strong>busca</strong> as mensagens aqui no site a cada poucos segundos. Não precisa de outra hospedagem.</p>
            <p class="dica"><strong>Celular Android</strong> (um aparelho antigo serve, sempre no carregador e no Wi-Fi):</p>
            <ol class="dica" style="line-height:1.8">
                <li>Instale o <strong>Termux</strong> pelo F-Droid (f-droid.org). A versão da Play Store é antiga.</li>
                <li>No Termux, cole: <code>pkg update -y && pkg install -y nodejs-lts unzip</code></li>
                <li>Copie a pasta <code>whatsapp-conector</code> para o celular (ou baixe o zip) e entre nela: <code>cd whatsapp-conector && npm install</code></li>
                <li>Ligue: <code>termux-wake-lock; GDS_SITE=<?= e((get_config('plataforma_url', '') ?: site_url_base_admin())) ?> GDS_CHAVE=<?= e($a['tipo'] === 'ponte' ? $a['chave'] : 'SUA_CHAVE') ?> npm start</code></li>
                <li>Aqui em cima deve aparecer <strong>Conector ligado</strong>. Clique em <strong>Gerar QR code</strong> e leia com o WhatsApp do chip.</li>
            </ol>
            <p class="dica"><strong>Computador (Windows):</strong> instale o Node.js (nodejs.org, versão LTS), abra a pasta <code>whatsapp-conector</code>, edite o arquivo <code>ponte.txt</code> com o endereço e a chave acima e dê dois cliques em <code>ligar-windows.bat</code>.</p>
            <p class="dica">Use um chip só para isso e envie só o que o cliente pediu: disparo em massa faz o WhatsApp bloquear o número.</p></details>
        <details style="margin-top:18px" open data-tipo="gds"><summary class="link" style="cursor:pointer">Passo a passo: Conector GDS (grátis)</summary>
            <ol class="dica" style="line-height:1.8">
                <li>Pegue a pasta <code>whatsapp-conector</code> que vem junto com o GDS.</li>
                <li>Ligue em um lugar que fique <strong>sempre ligado</strong>: app Node.js da Hostinger (plano Business ou maior), um VPS, ou o computador do escritório. Comando: <code>npm install</code> e depois <code>npm start</code>.</li>
                <li>Ao ligar, ele mostra a <strong>chave</strong>. Abra o endereço dele no navegador: tem que aparecer "Conector WhatsApp GDS no ar".</li>
                <li>Aqui: Serviço <strong>Conector GDS</strong>, cole o endereço e a chave, instância <code>gds-plataforma</code>, marque as opções e salve.</li>
                <li>Clique em <strong>Gerar QR code</strong>, leia com o WhatsApp do chip (Aparelhos conectados → Conectar um aparelho) e use <strong>Enviar teste</strong>.</li>
            </ol>
            <p class="dica">Ele não é da Meta: use um chip só para isso e mande só as mensagens que o cliente pediu (confirmações, lembretes, pedidos). Disparo em massa faz o WhatsApp bloquear o número.</p></details>
        <details style="margin-top:18px" data-tipo="evolution"><summary class="link" style="cursor:pointer">Passo a passo: Evolution API na Hostinger</summary>
            <ol class="dica" style="line-height:1.8">
                <li>No hPanel, contrate um <strong>VPS</strong> (o mais simples serve) e escolha o aplicativo <strong>Evolution API</strong> (instalação em 1 clique).</li>
                <li>Anote o <strong>IP do VPS</strong> e a <strong>AUTHENTICATION_API_KEY</strong> (no Docker Manager, nas variáveis do projeto da Evolution API).</li>
                <li>Teste <code>http://IP:8080</code> no navegador (aparece a mensagem de boas-vindas). Se puder, crie um subdomínio com HTTPS apontando para o VPS.</li>
                <li>Aqui: Serviço <strong>Evolution API</strong>, URL do servidor, chave e instância <code>gds-plataforma</code>. Marque as duas opções e salve.</li>
                <li>Clique em <strong>Gerar QR code</strong> e leia com o WhatsApp do chip da plataforma (Aparelhos conectados → Conectar um aparelho).</li>
                <li>Use <strong>Enviar teste</strong> e crie o Cron Job dos lembretes (item 4).</li>
            </ol>
            <p class="dica"><strong>Z-API:</strong> crie a instância em z-api.io e cole aqui o ID e o Token da instância (e o Client-Token, se ativou). <strong>Webhook:</strong> a GDS envia um POST JSON com <code>numero</code>, <code>texto</code>, <code>empresa_id</code> e <code>evento</code>.</p>
            <p class="dica">Use um número dedicado (chip só para isso). A GDS só envia mensagens que o cliente pediu; nunca use o número para disparo em massa.</p></details>
    </section>
    </div>

    <section class="form-box largo">
        <h3>Últimas mensagens (todas as empresas)</h3>
        <div class="tabela-wrap"><table>
            <thead><tr><th>Quando</th><th>Empresa</th><th>Evento</th><th>Para</th><th>Situação</th></tr></thead>
            <tbody>
            <?php foreach ($ultimas as $m): ?>
            <tr><td><?= date('d/m H:i', strtotime($m['criado_em'])) ?></td><td><?= e($m['empresa_nome'] ?? '—') ?></td><td><?= e($m['evento']) ?><span class="mini"><?= e($m['canal'] ?? '') ?></span></td>
                <td>+<?= e($m['telefone']) ?></td><td><?= e($m['status']) ?><?php if ($m['erro'] && $m['status'] !== 'enviado'): ?><span class="mini" style="color:var(--danger)"><?= e($m['erro']) ?></span><?php endif; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$ultimas): ?><tr class="vazio"><td colspan="5">Nenhuma mensagem ainda.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
</main>
</div>
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const ROT = {
    gds: ['Endereço do Conector GDS (ex.: https://whats.seudominio.com.br)', 'Chave do conector (aparece no log ao ligar, ou em dados/chave.txt)', 'Nome da instância'],
    evolution: ['URL do servidor da Evolution API', 'Chave global (AUTHENTICATION_API_KEY)', 'Nome da instância'],
    zapi: ['', 'Client-Token da conta (Segurança, opcional)', 'ID da instância'],
    wppconnect: ['URL do servidor WPPConnect', 'SECRET_KEY do servidor (config)', 'Nome da sessão'],
    ultramsg: ['', '', 'ID da instância (ex.: instance12345)'],
    ponte: ['', '', 'Nome da instância'],
    cloud: ['', '', 'Phone Number ID (Meta → WhatsApp → Configuração da API)'],
    webhook: ['URL que recebe o POST (JSON com numero e texto)', 'Token enviado no cabeçalho X-GDS-Token (opcional)', 'Identificador (opcional)'],
};
function mostrarTipo() {
    const t = document.getElementById('tipo').value;
    document.querySelectorAll('[data-tipo]').forEach(el => el.hidden = !t || !el.dataset.tipo.split(' ').includes(t));
    const rt = document.getElementById('rotToken'); if (rt) rt.textContent = t === 'cloud' ? 'Token de acesso permanente (usuário do sistema)' : 'Token da instância';
    if (ROT[t]) { document.getElementById('rotUrl').textContent = ROT[t][0]; document.getElementById('rotChave').textContent = ROT[t][1]; document.getElementById('rotInst').textContent = ROT[t][2]; }
}
mostrarTipo();
async function post(dados) {
    const fd = new FormData(); fd.append('csrf_token', CSRF); Object.entries(dados).forEach(([k, v]) => fd.append(k, v));
    try { return await (await fetch('whatsapp.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })).json(); }
    catch (e) { return { ok: false, erro: 'Falha de conexão.' }; }
}
async function testar(b) {
    b.disabled = true; const r = await post({ acao: 'testar', numero: document.getElementById('numTeste').value }); b.disabled = false;
    const el = document.getElementById('resTeste'); el.textContent = r.ok ? r.msg : r.erro; el.style.color = r.ok ? 'var(--ok)' : 'var(--danger)';
}
async function conectar(b) {
    b.disabled = true; const r = await post({ acao: 'conectar' }); b.disabled = false;
    if (!r.ok) { alert(r.erro); return; }
    document.getElementById('qrArea').hidden = false;
    if (r.qr) document.getElementById('qrImg').src = r.qr.startsWith('data:') ? r.qr : 'data:image/png;base64,' + r.qr;
    else if (r.aguardando) document.getElementById('qrInfo').textContent = 'Pedindo o QR ao conector…';
    const t = setInterval(async () => {
        const e = await post({ acao: 'estado' });
        if (e.conectado) { clearInterval(t); document.getElementById('qrInfo').textContent = '✅ Conectado!'; }
        else if (e.qr) { document.getElementById('qrImg').src = e.qr; document.getElementById('qrInfo').textContent = 'Aguardando leitura…'; }
    }, 3000);
    setTimeout(() => clearInterval(t), 180000);
}
async function rotina(b) {
    b.disabled = true; const r = await post({ acao: 'rotina' }); b.disabled = false;
    alert(r.ok ? 'Pronto: ' + (r.lembretes || 0) + ' lembrete(s) e ' + (r.reenvios || 0) + ' reenvio(s).' : 'Falhou.');
}
</script>
</body>
</html>
