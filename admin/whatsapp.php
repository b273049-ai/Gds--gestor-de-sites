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
            $r = wa_enviar_agora(['canal' => 'plataforma', 'tipo' => $a['tipo'], 'instancia' => $a['plat_instancia'], 'token' => $a['plat_token']], $num, "✅ Teste do WhatsApp da plataforma GDS. Está funcionando!");
            responder_json($r['ok'] ? ['ok' => true, 'msg' => 'Mensagem enviada para ' . wa_numero($num) . '!'] : ['ok' => false, 'erro' => $r['erro']]);
        case 'conectar':
            if ($a['tipo'] !== 'evolution' || !wa_api_configurada()) responder_json(['ok' => false, 'erro' => 'Disponível só com a Evolution API configurada.']);
            $nome = adm_wa_instancia();
            $r = wa_http('POST', $a['url'] . '/instance/create', ['instanceName' => $nome, 'qrcode' => true, 'integration' => 'WHATSAPP-BAILEYS'], ['apikey: ' . $a['chave']], 20);
            if ($r['ok'] && !empty($r['dados']['qrcode']['base64'])) responder_json(['ok' => true, 'qr' => $r['dados']['qrcode']['base64']]);
            $q = wa_http('GET', $a['url'] . '/instance/connect/' . rawurlencode($nome), null, ['apikey: ' . $a['chave']]);
            if (!$q['ok']) responder_json(['ok' => false, 'erro' => $q['erro'] ?: $r['erro']]);
            responder_json(['ok' => true, 'qr' => $q['dados']['base64'] ?? null, 'codigo' => $q['dados']['pairingCode'] ?? null, 'conectado' => ($q['dados']['instance']['state'] ?? '') === 'open']);
        case 'estado':
            $nome = adm_wa_instancia();
            if ($a['tipo'] === 'zapi') {
                $r = wa_http('GET', wa_zapi_base($a['plat_instancia'], $a['plat_token']) . '/status', null, $a['chave'] !== '' ? ['Client-Token: ' . $a['chave']] : []);
                responder_json(['ok' => $r['ok'], 'conectado' => !empty($r['dados']['connected']), 'erro' => $r['erro']]);
            }
            $r = wa_http('GET', $a['url'] . '/instance/connectionState/' . rawurlencode($nome), null, ['apikey: ' . $a['chave']]);
            responder_json(['ok' => $r['ok'], 'conectado' => ($r['dados']['instance']['state'] ?? '') === 'open', 'erro' => $r['erro']]);
        case 'rotina':
            responder_json(['ok' => true] + wa_rotina(true));
    }
    responder_json(['ok' => false, 'erro' => 'Ação inválida.'], 400);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar'])) {
    $tipo = in_array($_POST['wa_api_tipo'] ?? '', ['', 'evolution', 'zapi', 'webhook'], true) ? $_POST['wa_api_tipo'] : '';
    set_config('wa_api_tipo', $tipo);
    set_config('wa_api_url', url_valida_ou_vazia($_POST['wa_api_url'] ?? ''));
    if (trim($_POST['wa_api_chave'] ?? '') !== '') set_config('wa_api_chave', trim($_POST['wa_api_chave']));
    if (isset($_POST['limpar_chave'])) set_config('wa_api_chave', '');
    set_config('wa_plataforma_instancia', trim($_POST['wa_plataforma_instancia'] ?? ''));
    if (trim($_POST['wa_plataforma_token'] ?? '') !== '') set_config('wa_plataforma_token', trim($_POST['wa_plataforma_token']));
    set_config('wa_plataforma_ativo', isset($_POST['wa_plataforma_ativo']) ? '1' : '0');
    set_config('wa_empresas_conectar', isset($_POST['wa_empresas_conectar']) ? '1' : '0');
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
            <option value="" <?= $a['tipo'] === '' ? 'selected' : '' ?>>Nenhum (só envio manual pelo painel)</option>
            <option value="evolution" <?= $a['tipo'] === 'evolution' ? 'selected' : '' ?>>Evolution API — gratuita, conecta por QR code (recomendado)</option>
            <option value="zapi" <?= $a['tipo'] === 'zapi' ? 'selected' : '' ?>>Z-API — paga, brasileira</option>
            <option value="webhook" <?= $a['tipo'] === 'webhook' ? 'selected' : '' ?>>Webhook (n8n, Make ou outro gateway)</option>
        </select>
        <div data-tipo="evolution webhook">
            <label id="rotUrl">URL do servidor</label>
            <input type="url" name="wa_api_url" value="<?= e($a['url']) ?>" placeholder="https://evolution.seudominio.com.br">
        </div>
        <div data-tipo="evolution zapi webhook">
            <label id="rotChave">Chave</label>
            <input type="password" name="wa_api_chave" autocomplete="off" placeholder="<?= $chaveSalva ? 'Salva — cole outra para trocar' : '' ?>">
            <?php if ($chaveSalva): ?><label class="check" style="font-size:12px"><input type="checkbox" name="limpar_chave"> remover a chave salva</label><?php endif; ?>
        </div>

        <h3 style="margin-top:22px">2. Número da plataforma</h3>
        <p class="dica">Um número que envia para os clientes das empresas que não conectaram o próprio. A mensagem vai assinada com o nome da empresa.</p>
        <div data-tipo="evolution zapi webhook">
            <label id="rotInst">Nome da instância</label>
            <input type="text" name="wa_plataforma_instancia" value="<?= e($a['plat_instancia']) ?>" placeholder="gds-plataforma">
        </div>
        <div data-tipo="zapi"><label>Token da instância (Z-API)</label><input type="password" name="wa_plataforma_token" autocomplete="off" placeholder="<?= $a['plat_token'] !== '' ? 'Salvo — cole outro para trocar' : '' ?>"></div>
        <label class="check" style="margin-top:10px"><input type="checkbox" name="wa_plataforma_ativo" <?= $a['plat_ativo'] ? 'checked' : '' ?>> Usar o número da plataforma para as empresas</label>
        <label class="check" data-tipo="evolution zapi"><input type="checkbox" name="wa_empresas_conectar" <?= $a['empresas_conectar'] ? 'checked' : '' ?>> Deixar cada empresa conectar o próprio WhatsApp (QR code no painel dela)</label>
        <button type="submit" name="salvar" value="1">Salvar</button>
    </form>

    <section class="form-box largo">
        <h3>3. Conectar e testar</h3>
        <div data-tipo="evolution">
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

        <details style="margin-top:18px" open><summary class="link" style="cursor:pointer">Passo a passo: Evolution API na Hostinger (recomendado)</summary>
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
    evolution: ['URL do servidor da Evolution API', 'Chave global (AUTHENTICATION_API_KEY)', 'Nome da instância'],
    zapi: ['', 'Client-Token da conta (Segurança, opcional)', 'ID da instância'],
    webhook: ['URL que recebe o POST (JSON com numero e texto)', 'Token enviado no cabeçalho X-GDS-Token (opcional)', 'Identificador (opcional)'],
};
function mostrarTipo() {
    const t = document.getElementById('tipo').value;
    document.querySelectorAll('[data-tipo]').forEach(el => el.hidden = !t || !el.dataset.tipo.split(' ').includes(t));
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
    const t = setInterval(async () => {
        const e = await post({ acao: 'estado' });
        if (e.conectado) { clearInterval(t); document.getElementById('qrInfo').textContent = '✅ Conectado!'; }
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
