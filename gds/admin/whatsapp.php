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
            $numPar = wa_numero($_POST['numero'] ?? ''); // sem câmera: código de pareamento para este número
            if ($a['tipo'] === 'ponte') { $nome = adm_wa_instancia(); wa_ponte_pedir($nome, $numPar); responder_json(wa_ponte_situacao($nome)); }
            if (!in_array($a['tipo'], ['evolution', 'wppconnect'], true) || !wa_api_configurada()) responder_json(['ok' => false, 'erro' => 'Disponível com Evolution API ou WPPConnect configurados.']);
            $nome = adm_wa_instancia();
            if ($a['tipo'] === 'wppconnect') {
                $q = wa_wpp('POST', $nome, 'start-session', ['waitQrCode' => true]);
                if (!$q['ok']) responder_json(['ok' => false, 'erro' => $q['erro']]);
                $qr = $q['dados']['qrcode'] ?? null;
                responder_json(['ok' => true, 'qr' => $qr ? (str_starts_with($qr, 'data:') ? $qr : 'data:image/png;base64,' . $qr) : null, 'conectado' => in_array(strtoupper((string)($q['dados']['status'] ?? '')), ['CONNECTED', 'ISLOGGED'], true)]);
            }
            $r = wa_http('POST', $a['url'] . '/instance/create', ['instanceName' => $nome, 'qrcode' => true, 'integration' => 'WHATSAPP-BAILEYS'], ['apikey: ' . $a['chave']], 20);
            if ($r['ok'] && !empty($r['dados']['qrcode']['base64']) && $numPar === '') responder_json(['ok' => true, 'qr' => $r['dados']['qrcode']['base64']]);
            $q = wa_http('GET', $a['url'] . '/instance/connect/' . rawurlencode($nome) . ($numPar !== '' ? '?number=' . $numPar : ''), null, ['apikey: ' . $a['chave']], 25);
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
    // Na ponte a chave é gerada pelo próprio GDS: ignora o campo de senha do modo servidor
    // (o navegador do celular costuma preenchê-lo sozinho e trocava a chave sem ninguém ver)
    if ($tipo !== 'ponte' && trim($_POST['wa_api_chave'] ?? '') !== '') set_config('wa_api_chave', trim($_POST['wa_api_chave']));
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
// Saúde da conexão (conector no celular/computador): situação agora + histórico das quedas com o motivo
$saude = null;
if ($a['tipo'] === 'ponte') {
    $saude = wa_ponte_saude();
    $saude['online'] = wa_ponte_online();
    $saude['plat'] = $a['plat_instancia'] !== '' ? wa_ponte_ler($a['plat_instancia']) : null;
    $saude['nomes'] = [$a['plat_instancia'] => 'Número da plataforma'];
    foreach ($pdo->query("SELECT wa_instancia, nome FROM empresas WHERE COALESCE(wa_instancia, '') <> ''")->fetchAll(PDO::FETCH_KEY_PAIR) as $i => $n) $saude['nomes'][$i] ??= $n;
}
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
            <input type="password" name="wa_api_chave" autocomplete="new-password" placeholder="<?= $chaveSalva ? 'Salva — cole outra para trocar' : '' ?>">
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
            <details class="wa-sem-camera" data-tipo="ponte gds evolution"><summary class="link">📱 Sem câmera? Conectar com o número de telefone (código)</summary>
                <p class="dica">Serve para conectar no <strong>mesmo celular</strong>. Digite o número do chip da plataforma, com DDD.</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
                    <div style="flex:1;min-width:180px"><label>Número do WhatsApp</label><input type="tel" id="numPareamento" placeholder="(11) 99999-9999"></div>
                    <button type="button" class="btn" onclick="conectar(this, true)">Receber código</button>
                </div>
            </details>
            <div id="qrArea" hidden style="margin-top:14px"><div class="wa-qr" id="qrBox"><img id="qrImg" alt="QR code"><span id="qrInfo">Aguardando leitura…</span></div>
                <div class="wa-codigo" id="qrCodigo" hidden><strong id="codigoTexto"></strong>
                    <ol class="dica"><li>No celular: WhatsApp → <strong>⋮ / Configurações → Aparelhos conectados → Conectar um aparelho</strong>.</li>
                        <li>Toque em <strong>Conectar com número de telefone</strong>.</li><li>Digite este código. Ele vale por alguns minutos.</li></ol></div>
                <p class="dica" id="estadoConexao"></p></div>
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
            <?php $siteBase = rtrim(get_config('plataforma_url', '') ?: site_url_base_admin(), '/');
                $cmdSempre = 'pkg update -y && pkg install -y nodejs-lts curl unzip termux-api && curl -fsSL -o ~/conector.zip ' . $siteBase . '/assets/conector/whatsapp-conector.zip'
                    . ' && mkdir -p ~/whatsapp-conector && unzip -o -q ~/conector.zip -d ~/whatsapp-conector && bash ~/whatsapp-conector/sempre-ligado.sh ' . $siteBase . ' ' . ($a['tipo'] === 'ponte' && $a['chave'] !== '' ? $a['chave'] : 'SUA_CHAVE'); ?>
            <p class="dica"><strong>Celular Android</strong> (um aparelho antigo serve, sempre no carregador e no Wi-Fi). O conector fica <strong>sempre ligado</strong>: liga sozinho quando o celular reinicia, religa se cair e um vigia o liga de novo se o Android fechá-lo.</p>
            <ol class="dica" style="line-height:1.8">
                <li>Pelo <strong>F-Droid</strong> (f-droid.org) instale <strong>Termux</strong>, <strong>Termux:Boot</strong> e <strong>Termux:API</strong> (os três de lá; a versão da Play Store é antiga). Abra o Termux:Boot <strong>uma vez</strong>.</li>
                <li>No Termux, cole este comando (instala, liga e deixa sempre ligado — pode repetir para atualizar; o login do WhatsApp continua):
                    <textarea id="cmdSempre" readonly rows="4" style="width:100%;margin-top:6px;font:12px/1.5 ui-monospace,monospace" aria-label="Comando para o Termux"><?= e($cmdSempre) ?></textarea>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="copiarCmd(this)">Copiar comando</button></li>
                <li>No celular: Configurações → Apps → <strong>Termux</strong>, <strong>Termux:Boot</strong> e <strong>Termux:API</strong> → Bateria → <strong>Sem restrições</strong>. Android 14 ou mais novo: Opções do desenvolvedor → <strong>Desativar restrições de processos filhos</strong>.</li>
                <li>Aqui em cima deve aparecer <strong>Conector ligado</strong>. Clique em <strong>Gerar QR code</strong> e leia com o WhatsApp do chip.</li>
            </ol>
            <p class="dica">Para desligar de vez: <code>bash ~/whatsapp-conector/desligar.sh</code>. Para ver o que ele está fazendo: <code>tail -f ~/whatsapp-conector/conector.log</code>.</p>
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

    <?php if ($saude): $info = $saude['info']; $plat = $saude['plat'];
        $platEstado = !$saude['online'] ? ['Sem notícia do conector', 'pill-aviso'] : (!$plat ? ['Ainda não conectado', 'pill-neutro']
            : ($plat['estado'] === 'open' ? ['Conectado', 'pill-ok'] : ($plat['estado'] === 'connecting' ? ['Religando sozinho…', 'pill-aviso'] : ['Desconectado — gere o QR code', 'pill-aviso'])));
        $sistema = ['android' => 'Android', 'win32' => 'Windows', 'darwin' => 'Mac', 'linux' => 'Linux'][$info['plataforma'] ?? ''] ?? ''; ?>
    <section class="form-box largo" id="saude" aria-labelledby="tituloSaude">
        <h3 id="tituloSaude">Saúde da conexão</h3>
        <p class="dica">O que aconteceu com o WhatsApp nas últimas 24 horas e por quê. Atualiza sozinho a cada volta do conector.</p>
        <div class="cards" style="margin-top:14px">
            <div class="card"><div class="label"><?= icone('celular', 16) ?> Conector</div>
                <div class="value" style="font-size:18px"><span class="pill <?= $saude['online'] ? 'pill-ok' : 'pill-aviso' ?>"><?= $saude['online'] ? 'Ligado' : 'Desligado' ?></span></div>
                <div class="dica"><?= $saude['versao'] !== '' ? 'Versão ' . e($saude['versao']) . ($sistema ? ' · ' . e($sistema) : '') : 'Versão 1.2 ou anterior' ?><?= !empty($info['inicio']) && $saude['online'] ? ' · ligado há ' . e(wa_ponte_duracao(time() - (int)$info['inicio'])) : '' ?></div></div>
            <div class="card"><div class="label"><?= icone('chat', 16) ?> WhatsApp da plataforma</div>
                <div class="value" style="font-size:18px"><span class="pill <?= $platEstado[1] ?>"><?= e($platEstado[0]) ?></span></div>
                <div class="dica"><?= $plat && $plat['numero'] ? '+' . e($plat['numero']) : 'Número ainda não lido' ?></div></div>
            <div class="card"><div class="label"><?= icone('historico', 16) ?> Quedas em 24 h</div>
                <div class="value"><?= (int)$saude['quedas_24h'] ?></div>
                <div class="dica"><?= $saude['fora_24h'] > 0 ? 'Fora do ar no total: ' . e(wa_ponte_duracao($saude['fora_24h'])) : 'Sem tempo fora do ar registrado' ?></div></div>
        </div>
        <?php if ($saude['desatualizado']): ?>
        <div class="alert alert-aviso" role="status"><strong>Atualize o conector para a versão <?= e(WA_CONECTOR_VERSAO) ?>.</strong>
            <?= $saude['versao'] !== '' ? 'O seu está na ' . e($saude['versao']) . '.' : 'O seu é de uma versão antiga e não conta o motivo das quedas.' ?>
            Pare o conector, troque os arquivos <code>server.js</code> e <code>package.json</code> pelos novos e ligue de novo. O login do WhatsApp continua (não apague a pasta <code>dados</code>).</div>
        <?php endif; ?>
        <?php if ($saude['grupos']): $g = $saude['grupos'][0]; ?>
        <div class="alert <?= $g['motivo'][3] === 'grave' ? 'alert-erro' : ($g['motivo'][3] === 'aviso' ? 'alert-aviso' : 'alert-info') ?>" role="status">
            <strong>O que mais derrubou: <?= e($g['motivo'][0]) ?></strong> (<?= (int)$g['n'] ?>× nas últimas 24 h)<br>
            <?= e($g['motivo'][1]) ?><?php if ($g['motivo'][2] !== ''): ?><br><strong>O que fazer:</strong> <?= e($g['motivo'][2]) ?><?php endif; ?></div>
        <?php elseif ($saude['online'] && $plat && $plat['estado'] === 'open'): ?>
        <div class="alert alert-sucesso" role="status">Nenhuma queda nas últimas 24 horas.</div>
        <?php endif; ?>
        <?php if ($saude['recentes']): ?>
        <div class="tabela-wrap"><table>
            <thead><tr><th>Quando</th><th>O que aconteceu</th><th>Número</th></tr></thead>
            <tbody>
            <?php foreach ($saude['recentes'] as $ev): $m = wa_ponte_motivo($ev); ?>
            <tr><td><?= date('d/m H:i', (int)$ev['em']) ?></td>
                <td><span class="pill <?= $m[3] === 'grave' ? 'pill-aviso' : ($ev['tipo'] === 'conectou' ? 'pill-ok' : 'pill-neutro') ?>" style="white-space:normal"><?= e($m[0]) ?></span><span class="mini"><?= e($m[1]) ?></span></td>
                <td><?= e($saude['nomes'][$ev['inst'] ?? ''] ?? (($ev['inst'] ?? '') !== '' ? $ev['inst'] : 'Conector')) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php else: ?><p class="dica">Ainda sem histórico. Ele começa a ser registrado assim que o conector fala com o site.</p><?php endif; ?>
    </section>
    <?php endif; ?>

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
let timerConexao = null;
function mostrarCodigo(c) {
    if (!c) return;
    document.getElementById('qrCodigo').hidden = false;
    document.getElementById('codigoTexto').textContent = String(c).replace(/^(.{4})(.{4})$/, '$1-$2');
}
async function conectar(b, porCodigo) {
    const el = (id) => document.getElementById(id);
    const dados = { acao: 'conectar' };
    if (porCodigo) {
        const num = el('numPareamento').value.replace(/\D/g, '');
        if (num.length < 10) { alert('Digite o número com DDD.'); return; }
        dados.numero = num;
    }
    b.disabled = true; const r = await post(dados); b.disabled = false;
    if (!r.ok) { alert(r.erro); return; }
    el('qrArea').hidden = false; el('qrBox').hidden = !!porCodigo; el('qrCodigo').hidden = !porCodigo;
    if (porCodigo) el('codigoTexto').textContent = 'Gerando o código…';
    if (r.qr) el('qrImg').src = r.qr.startsWith('data:') ? r.qr : 'data:image/png;base64,' + r.qr;
    else if (r.aguardando) el('qrInfo').textContent = 'Pedindo o QR ao conector…';
    if (porCodigo) mostrarCodigo(r.codigo);
    clearInterval(timerConexao);
    timerConexao = setInterval(async () => {
        const e = await post({ acao: 'estado' });
        if (e.conectado) { clearInterval(timerConexao); el('qrInfo').textContent = '✅ Conectado!'; el('estadoConexao').textContent = '✅ WhatsApp conectado!'; return; }
        if (porCodigo) mostrarCodigo(e.codigo);
        else if (e.qr) { el('qrImg').src = e.qr; el('qrInfo').textContent = 'Aguardando leitura…'; }
    }, 3000);
    setTimeout(() => clearInterval(timerConexao), 300000);
}
async function copiarCmd(b) {
    const t = document.getElementById('cmdSempre');
    try { await navigator.clipboard.writeText(t.value); } catch (e) { t.select(); document.execCommand('copy'); }
    b.textContent = 'Copiado!'; setTimeout(() => { b.textContent = 'Copiar comando'; }, 2500);
}
async function rotina(b) {
    b.disabled = true; const r = await post({ acao: 'rotina' }); b.disabled = false;
    alert(r.ok ? 'Pronto: ' + (r.lembretes || 0) + ' lembrete(s) e ' + (r.reenvios || 0) + ' reenvio(s).' : 'Falhou.');
}
</script>
</body>
</html>
