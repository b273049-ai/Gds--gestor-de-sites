<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = (int)empresa_id_atual();

function wa_empresa_atual($id) {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
    $st->execute([$id]);
    return $st->fetch();
}
$empresa = wa_empresa_atual($empresaId);
$api = wa_api();

// ---------- Ações por fetch (conectar, QR, estado, teste...) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    @set_time_limit(60);
    switch ($_POST['acao']) {
        case 'conectar':
            if (!wa_pode_conectar_proprio()) responder_json(['ok' => false, 'erro' => 'A conexão do número próprio não está disponível. Fale com o administrador da plataforma.']);
            if (in_array($api['tipo'], ['zapi', 'ultramsg'], true)) {
                $inst = trim($_POST['zapi_instancia'] ?? ''); $tok = trim($_POST['zapi_token'] ?? '');
                if ($inst === '' || $tok === '') responder_json(['ok' => false, 'erro' => 'Informe o ID e o token da sua instância Z-API.']);
                $pdo->prepare("UPDATE empresas SET wa_instancia=?, wa_instancia_token=?, wa_status='aguardando' WHERE id=?")->execute([$inst, $tok, $empresaId]);
                $empresa = wa_empresa_atual($empresaId);
            }
            $pdo->prepare("UPDATE empresas SET wa_modo='proprio' WHERE id=?")->execute([$empresaId]);
            $r = wa_conexao_criar(wa_empresa_atual($empresaId));
            responder_json($r);
        case 'qr':
            $r = wa_conexao_qr($empresa, trim($_POST['numero'] ?? ''));
            responder_json($r);
        case 'estado':
            responder_json(wa_conexao_estado($empresa));
        case 'desconectar':
            responder_json(wa_conexao_desconectar($empresa));
        case 'testar':
            $num = trim($_POST['numero'] ?? '') ?: ($empresa['whatsapp'] ?? '');
            if (wa_numero($num) === '') responder_json(['ok' => false, 'erro' => 'Informe um número de WhatsApp com DDD para o teste.']);
            $rem = wa_remetente($empresa);
            if (!$rem) responder_json(['ok' => false, 'erro' => 'Nenhum WhatsApp automático ativo. Escolha "WhatsApp da plataforma" ou conecte o seu número.']);
            $texto = "✅ Teste do WhatsApp automático da *{$empresa['nome']}*.\nSe você recebeu esta mensagem, está tudo funcionando!";
            $id = wa_enfileirar($empresa, $num, $texto, 'teste', 'empresa');
            $st = $pdo->prepare("SELECT status, erro FROM whatsapp_mensagens WHERE id=?"); $st->execute([$id]); $m = $st->fetch();
            responder_json($m && $m['status'] === 'enviado' ? ['ok' => true, 'msg' => 'Mensagem de teste enviada para ' . wa_numero($num) . '!']
                                                           : ['ok' => false, 'erro' => 'Não foi possível enviar: ' . ($m['erro'] ?? 'erro desconhecido')]);
        case 'reenviar':
            $st = $pdo->prepare("SELECT * FROM whatsapp_mensagens WHERE id=? AND empresa_id=?");
            $st->execute([(int)($_POST['id'] ?? 0), $empresaId]);
            $m = $st->fetch();
            $rem = wa_remetente($empresa);
            if (!$m || !$rem) responder_json(['ok' => false, 'erro' => 'Não foi possível reenviar.']);
            $pdo->prepare("UPDATE whatsapp_mensagens SET tentativas=0, canal=? WHERE id=?")->execute([$rem['canal'], $m['id']]);
            $r = wa_tentar_envio($m['id'], $rem, $m['telefone'], $m['texto']);
            responder_json($r['ok'] ? ['ok' => true, 'msg' => 'Mensagem enviada.'] : ['ok' => false, 'erro' => $r['erro']]);
    }
    responder_json(['ok' => false, 'erro' => 'Ação inválida.'], 400);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_modo'])) {
    $modo = in_array($_POST['wa_modo'] ?? '', ['desligado', 'plataforma', 'proprio'], true) ? $_POST['wa_modo'] : 'plataforma';
    $pdo->prepare("UPDATE empresas SET wa_modo=?, wa_avisar_empresa=?, wa_lembrete_horas=? WHERE id=?")
        ->execute([$modo, isset($_POST['wa_avisar_empresa']) ? 'true' : 'false', max(1, min(72, (int)($_POST['wa_lembrete_horas'] ?? 24))), $empresaId]);
    flash('sucesso', 'Preferências do WhatsApp salvas.');
    header('Location: whatsapp.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_mensagens'])) {
    $cfg = [];
    foreach (wa_eventos() as $chave => $ev) {
        $texto = trim(str_replace("\r\n", "\n", (string)($_POST['texto'][$chave] ?? '')));
        $cfg[$chave] = ['ativo' => isset($_POST['ativo'][$chave]), 'texto' => $texto !== '' && $texto !== $ev['texto'] ? mb_substr($texto, 0, 1500) : ''];
    }
    $pdo->prepare("UPDATE empresas SET wa_mensagens=? WHERE id=?")->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE), $empresaId]);
    flash('sucesso', 'Mensagens salvas.');
    header('Location: whatsapp.php#mensagens'); exit;
}

$empresa = wa_empresa_atual($empresaId);
$modo = $empresa['wa_modo'] ?: 'plataforma';
$remetente = wa_remetente($empresa);
$podeProprio = wa_pode_conectar_proprio();
$temPlataforma = wa_plataforma_disponivel();
$conectado = ($empresa['wa_status'] ?? '') === 'conectado' && !empty($empresa['wa_instancia']);
$cfgMsgs = wa_config_empresa($empresa);
$historico = $pdo->prepare("SELECT * FROM whatsapp_mensagens WHERE empresa_id=? ORDER BY id DESC LIMIT 40");
$historico->execute([$empresaId]); $historico = $historico->fetchAll();
$exemplo = ['nome' => 'Maria', 'empresa' => $empresa['nome'], 'servico' => 'Corte + Barba', 'profissional' => ' com Ana', 'data' => 'sexta, 14/11', 'hora' => '15:30',
            'valor' => '💰 R$ 85,00', 'total' => 'R$ 92,00', 'itens' => "2x Smash burger — R$ 64,00\n1x Batata — R$ 18,00", 'entrega' => '🛵 Entrega em: Rua das Flores, 120',
            'pagamento' => 'Pix', 'mensagem' => 'Inclui som e iluminação.', 'codigo' => '123', 'link' => wa_url_publica($empresa), 'endereco' => $empresa['endereco'] ?: 'Rua Exemplo, 100', 'telefone' => ''];
// Só as mensagens dos módulos que a empresa usa
$grupos = [];
$modGrupo = ['Agendamentos' => 'agenda', 'Orçamentos' => 'orcamentos', 'Pedidos / Delivery' => 'delivery'];
foreach (wa_eventos() as $k => $ev) {
    $g = $ev['grupo'];
    if ($k === 'pedido_cancelado' && !modulo_ativo('delivery') && modulo_ativo('orcamentos')) $g = 'Orçamentos';
    if (isset($modGrupo[$g]) && !modulo_ativo($modGrupo[$g])) continue;
    $grupos[$g][$k] = $ev;
}
$statusMsg = ['enviado' => ['Enviada', 'pill-ok'], 'pendente' => ['Na fila', 'pill-neutro'], 'erro' => ['Falhou', 'pill-aviso'], 'manual' => ['Envio manual', 'pill-neutro']];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('WhatsApp automático - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>WhatsApp automático</h1>
        <p class="subtitulo">Seu cliente recebe no WhatsApp, sozinho: <?= e(implode(', ', array_filter([modulo_ativo('agenda') ? 'confirmação de agendamento e lembrete' : '', modulo_ativo('orcamentos') ? 'orçamento com o valor' : '', modulo_ativo('delivery') ? 'pedido recebido e saiu para entrega' : '']) ?: ['as respostas do seu negócio'])) ?>.</p></div></div>
    <?= flash_render() ?>

    <section class="form-box largo">
        <div class="wa-status">
            <span class="wa-bolinha <?= $remetente ? 'ok' : 'aviso' ?>"></span>
            <div>
                <strong><?= $remetente ? ($remetente['canal'] === 'proprio' ? 'Enviando pelo SEU WhatsApp' . ($empresa['wa_numero_conectado'] ? ' (+' . e($empresa['wa_numero_conectado']) . ')' : '') : 'Enviando pelo WhatsApp da plataforma') : 'Mensagens automáticas desligadas' ?></strong>
                <p class="dica" style="margin:2px 0 0"><?= $remetente ? 'Tudo certo: as mensagens saem sozinhas.' : 'As mensagens ficam prontas no painel com um botão "Enviar pelo WhatsApp" — você clica e envia.' ?></p>
            </div>
            <?php if ($remetente): ?><button type="button" class="btn btn-ghost btn-sm" style="margin-left:auto" onclick="WA.testar()"><?= icone('enviar', 14) ?> Enviar teste</button><?php endif; ?>
        </div>

        <form method="post" style="margin-top:16px">
            <?= csrf_campo() ?>
            <label>De onde as mensagens saem</label>
            <div class="wa-modos">
                <label class="wa-modo <?= $temPlataforma ? '' : 'desativado' ?>"><input type="radio" name="wa_modo" value="plataforma" <?= $modo === 'plataforma' ? 'checked' : '' ?> <?= $temPlataforma ? '' : 'disabled' ?>>
                    <span><strong>WhatsApp da plataforma</strong><small><?= $temPlataforma ? 'Mais fácil: não precisa configurar nada. A mensagem leva o nome do seu negócio.' : 'Ainda não disponível nesta plataforma.' ?></small></span></label>
                <label class="wa-modo <?= $podeProprio ? '' : 'desativado' ?>"><input type="radio" name="wa_modo" value="proprio" <?= $modo === 'proprio' ? 'checked' : '' ?> <?= $podeProprio ? '' : 'disabled' ?>>
                    <span><strong>Meu próprio WhatsApp</strong><small><?= $podeProprio ? 'As mensagens saem do seu número. Conecte lendo um QR code (como no WhatsApp Web).' : 'Ainda não disponível nesta plataforma.' ?></small></span></label>
                <label class="wa-modo"><input type="radio" name="wa_modo" value="desligado" <?= $modo === 'desligado' ? 'checked' : '' ?>>
                    <span><strong>Desligado</strong><small>Nada é enviado sozinho. Você envia pelos botões do painel.</small></span></label>
            </div>
            <div class="form-row">
                <div><label class="check" style="margin-top:12px"><input type="checkbox" name="wa_avisar_empresa" <?= ($empresa['wa_avisar_empresa'] ?? null) === null || pg_bool($empresa['wa_avisar_empresa']) ? 'checked' : '' ?>> Me avisar no meu WhatsApp (<?= e($empresa['whatsapp'] ?: 'cadastre em Configurações') ?>) a cada <?= e(implode(', ', array_filter([modulo_ativo('agenda') ? 'agendamento' : '', modulo_ativo('delivery') ? 'pedido' : '', modulo_ativo('orcamentos') ? 'orçamento' : '', 'contato'])) ) ?> novo</label></div>
                <div <?= modulo_ativo('agenda') ? '' : 'hidden' ?>><label>Lembrete do agendamento</label><select name="wa_lembrete_horas">
                    <?php foreach ([1 => '1 hora antes', 2 => '2 horas antes', 3 => '3 horas antes', 6 => '6 horas antes', 12 => '12 horas antes', 24 => '1 dia antes', 48 => '2 dias antes'] as $h => $rot): ?>
                    <option value="<?= $h ?>" <?= (int)($empresa['wa_lembrete_horas'] ?? 24) === $h ? 'selected' : '' ?>><?= $rot ?></option><?php endforeach; ?></select></div>
            </div>
            <button type="submit" name="salvar_modo" value="1">Salvar</button>
        </form>
    </section>

    <?php if ($podeProprio): ?>
    <section class="form-box largo" id="conectar">
        <h3><?= icone('celular', 16) ?> Meu próprio WhatsApp</h3>
        <?php if ($conectado): ?>
            <p><span class="pill pill-ok">Conectado</span> <?= $empresa['wa_numero_conectado'] ? 'Número +' . e($empresa['wa_numero_conectado']) : '' ?></p>
            <p class="dica">Deixe o celular com internet. Se trocar de aparelho ou sair do WhatsApp Web, conecte de novo aqui.</p>
            <button type="button" class="btn btn-ghost btn-sm" onclick="WA.desconectar()">Desconectar</button>
        <?php else: ?>
            <ol class="dica" style="line-height:1.8">
                <li>Clique em <strong>Gerar QR code</strong>.</li>
                <li>No celular, abra o WhatsApp → <strong>⋮ / Configurações</strong> → <strong>Aparelhos conectados</strong> → <strong>Conectar um aparelho</strong>.</li>
                <li>Aponte a câmera para o QR code. Pronto!</li>
            </ol>
            <?php if (in_array($api['tipo'], ['zapi', 'ultramsg'], true)): ?>
            <div class="form-row">
                <div><label>ID da instância <?= $api['tipo'] === 'zapi' ? 'Z-API' : 'UltraMsg' ?></label><input type="text" id="zapiInst" value="<?= e($empresa['wa_instancia'] ?? '') ?>"></div>
                <div><label>Token da instância</label><input type="text" id="zapiTok" value="<?= e($empresa['wa_instancia_token'] ?? '') ?>"></div>
            </div>
            <?php endif; ?>
            <button type="button" class="btn" id="btnConectar" onclick="WA.conectar()"><?= icone('celular', 16) ?> Gerar QR code</button>
            <div id="qrArea" hidden style="margin-top:16px">
                <div class="wa-qr"><img id="qrImg" alt="QR code para conectar o WhatsApp"><span id="qrInfo">Aguardando leitura…</span></div>
                <p class="dica" id="qrCodigo" hidden></p>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <form method="post" class="form-box largo" id="mensagens">
        <?= csrf_campo() ?>
        <h3><?= icone('chat', 16) ?> Mensagens</h3>
        <p class="dica">Ligue/desligue cada aviso e edite o texto se quiser. Variáveis: <span class="wa-vars"><code>{nome}</code> <code>{empresa}</code> <code>{servico}</code> <code>{data}</code> <code>{hora}</code> <code>{valor}</code> <code>{itens}</code> <code>{total}</code> <code>{entrega}</code> <code>{pagamento}</code> <code>{codigo}</code> <code>{mensagem}</code> <code>{link}</code> <code>{endereco}</code></span>. Use *texto* para negrito.</p>
        <?php foreach ($grupos as $grupo => $evs): ?>
            <h4 style="margin:18px 0 8px"><?= e($grupo) ?></h4>
            <div class="wa-eventos">
            <?php foreach ($evs as $k => $ev): ?>
                <details class="wa-evento">
                    <summary><span><strong><?= e($ev['rotulo']) ?></strong><small><?= e($ev['quando']) ?></small></span>
                        <label class="switch wa-sw" onclick="event.stopPropagation()" title="Ligar/desligar"><input type="checkbox" name="ativo[<?= $k ?>]" <?= $cfgMsgs[$k]['ativo'] ? 'checked' : '' ?>><span></span></label></summary>
                    <div class="wa-evento-corpo">
                        <div><textarea name="texto[<?= $k ?>]" rows="7" data-previa="previa-<?= $k ?>" data-padrao="<?= e($ev['texto']) ?>"><?= e($cfgMsgs[$k]['texto']) ?></textarea>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="WA.padrao(this)">Restaurar texto padrão</button></div>
                        <div class="wa-previa" id="previa-<?= $k ?>"></div>
                    </div>
                </details>
            <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <button type="submit" name="salvar_mensagens" value="1">Salvar mensagens</button>
    </form>

    <section class="form-box largo">
        <h3><?= icone('historico', 16) ?> Últimas mensagens</h3>
        <div class="tabela-wrap"><table>
            <thead><tr><th>Quando</th><th>Para</th><th>Mensagem</th><th>Situação</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($historico as $m): [$rot, $cls] = $statusMsg[$m['status']] ?? [$m['status'], 'pill-neutro']; ?>
            <tr>
                <td><?= date('d/m H:i', strtotime($m['criado_em'])) ?></td>
                <td>+<?= e($m['telefone']) ?><span class="mini"><?= $m['para'] === 'empresa' ? 'você' : 'cliente' ?></span></td>
                <td style="max-width:420px"><span title="<?= e($m['texto']) ?>"><?= e(mb_strimwidth(str_replace("\n", ' ', $m['texto']), 0, 110, '…')) ?></span><?php if ($m['erro'] && $m['status'] !== 'enviado'): ?><span class="mini" style="color:var(--danger)"><?= e($m['erro']) ?></span><?php endif; ?></td>
                <td><span class="pill <?= $cls ?>"><?= e($rot) ?></span></td>
                <td class="acoes">
                    <?php if (in_array($m['status'], ['manual', 'erro'], true)): ?><a class="btn btn-sm btn-wa" target="_blank" rel="noopener" href="<?= e(wa_link_manual($m['telefone'], $m['texto'])) ?>">Enviar</a><?php endif; ?>
                    <?php if ($m['status'] === 'erro' && $remetente): ?><button type="button" class="btn btn-ghost btn-sm" onclick="WA.reenviar(<?= (int)$m['id'] ?>, this)">Tentar de novo</button><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$historico): ?><tr class="vazio"><td colspan="5">Nenhuma mensagem ainda.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
</main>
</div>
<div id="toasts" class="toasts"></div>
<script>
const WA = (() => {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const EX = <?= json_encode($exemplo, JSON_UNESCAPED_UNICODE) ?>;
    const $ = (id) => document.getElementById(id);
    let timer = null;
    async function api(acao, dados = {}) {
        const fd = new FormData(); fd.append('acao', acao); fd.append('csrf_token', CSRF);
        Object.entries(dados).forEach(([k, v]) => fd.append(k, v));
        try {
            const r = await fetch('whatsapp.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            return await r.json();
        } catch (e) { return { ok: false, erro: 'Falha de conexão. Tente de novo.' }; }
    }
    function toast(msg, tipo = 'info') {
        const t = document.createElement('div'); t.className = 'toast toast-' + tipo; t.textContent = msg;
        $('toasts').appendChild(t); setTimeout(() => t.remove(), 5000);
    }
    function previa(ta) {
        const txt = ta.value.replace(/\{([a-z_]+)\}/g, (m, k) => EX[k] ?? '').replace(/\n{3,}/g, '\n\n').trim();
        const el = $(ta.dataset.previa);
        el.innerHTML = '';
        // *negrito* do WhatsApp, sem innerHTML com texto do usuário
        txt.split(/(\*[^*\n]+\*)/).forEach(parte => {
            if (/^\*[^*\n]+\*$/.test(parte)) { const b = document.createElement('b'); b.textContent = parte.slice(1, -1); el.appendChild(b); }
            else el.appendChild(document.createTextNode(parte));
        });
    }
    document.querySelectorAll('textarea[data-previa]').forEach(ta => { previa(ta); ta.addEventListener('input', () => previa(ta)); });
    function padrao(btn) { const ta = btn.parentElement.querySelector('textarea'); ta.value = ta.dataset.padrao; previa(ta); }

    async function conectar() {
        const b = $('btnConectar'); b.disabled = true; b.textContent = 'Gerando…';
        const extra = $('zapiInst') ? { zapi_instancia: $('zapiInst').value, zapi_token: $('zapiTok').value } : {};
        const r = await api('conectar', extra);
        b.disabled = false; b.textContent = 'Gerar novo QR code';
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        mostrarQr(r);
        clearInterval(timer);
        let voltas = 0;
        timer = setInterval(async () => {
            voltas++;
            const e = await api('estado');
            if (e.ok && e.conectado) { clearInterval(timer); $('qrInfo').textContent = '✅ Conectado!'; toast('WhatsApp conectado!', 'sucesso'); setTimeout(() => location.reload(), 1200); return; }
            if (voltas % 10 === 0) { const q = await api('qr'); if (q.ok) mostrarQr(q); } // o QR expira: renova a cada ~30s
            if (voltas > 100) { clearInterval(timer); $('qrInfo').textContent = 'O QR code expirou. Clique em gerar de novo.'; }
        }, 3000);
    }
    function mostrarQr(r) {
        $('qrArea').hidden = false;
        if (r.qr) $('qrImg').src = r.qr.startsWith('data:') ? r.qr : 'data:image/png;base64,' + r.qr;
        if (r.conectado) { $('qrInfo').textContent = '✅ Conectado!'; setTimeout(() => location.reload(), 1000); }
        if (r.codigo) { $('qrCodigo').hidden = false; $('qrCodigo').textContent = 'Ou digite este código no celular (Conectar com número de telefone): ' + r.codigo; }
    }
    async function desconectar() {
        if (!confirm('Desconectar o seu WhatsApp da plataforma?')) return;
        const r = await api('desconectar');
        if (r.ok) location.reload(); else toast(r.erro, 'erro');
    }
    async function testar() {
        const num = prompt('Enviar a mensagem de teste para qual WhatsApp? (com DDD)', <?= json_encode($empresa['whatsapp'] ?? '') ?>);
        if (num === null) return;
        toast('Enviando…');
        const r = await api('testar', { numero: num });
        toast(r.ok ? r.msg : r.erro, r.ok ? 'sucesso' : 'erro');
    }
    async function reenviar(id, btn) {
        btn.disabled = true;
        const r = await api('reenviar', { id });
        toast(r.ok ? r.msg : r.erro, r.ok ? 'sucesso' : 'erro');
        if (r.ok) setTimeout(() => location.reload(), 800); else btn.disabled = false;
    }
    return { conectar, desconectar, testar, reenviar, padrao };
})();
</script>
</body>
</html>
