<?php
// Página do cliente final: ver os detalhes do agendamento e cancelar, pelo link recebido na confirmação
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/site_render.php';
require_once __DIR__ . '/../includes/espera.php';
if (!function_exists('e')) { function e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); } }
header('X-Robots-Tag: noindex');

$token = preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? $_POST['t'] ?? ''));
$ag = false;
if (strlen($token) === 32) {
    try {
        $st = $pdo->prepare("SELECT a.*, c.nome AS cliente_nome, c.telefone AS cliente_telefone, s.nome AS servico_nome, s.duracao_minutos, p.nome AS profissional_nome,
                e.nome AS empresa_nome, e.slug, e.whatsapp, e.endereco, e.email AS empresa_email, e.notificar_email, si.cor_primaria, si.ia_tema, si.html_gerado, si.ia_modo_total
            FROM agendamentos a JOIN empresas e ON e.id = a.empresa_id JOIN sites si ON si.empresa_id = e.id
            LEFT JOIN clientes c ON c.id = a.cliente_id LEFT JOIN servicos s ON s.id = a.servico_id LEFT JOIN profissionais p ON p.id = a.profissional_id
            WHERE a.token = ? LIMIT 1");
        $st->execute([$token]);
        $ag = $st->fetch();
    } catch (PDOException $e) { $ag = false; }
}

$msg = '';
// O cliente cancelou, confirmou ou remarcou: o site e os horários em cache da empresa são refeitos
if ($ag && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') gds_cache_invalidar((int)$ag['empresa_id']);
if ($ag && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['cancelar'])) {
    if (!hash_equals($_SESSION['gds_cancel_csrf'] ?? '', (string)($_POST['k'] ?? ''))) {
        $msg = 'Página expirada. Tente de novo.';
    } elseif (in_array($ag['status'], ['cancelado', 'concluido'], true) || strtotime($ag['data_hora']) < time()) {
        $msg = 'Este agendamento não pode mais ser cancelado.';
    } else {
        $motivo = mb_substr(trim(strip_tags((string)($_POST['motivo'] ?? ''))), 0, 255);
        $pdo->prepare("UPDATE agendamentos SET status='cancelado', cancelado_motivo=? WHERE id=?")->execute([$motivo ?: 'Cancelado pelo cliente no site', $ag['id']]);
        site_notificar_empresa(['nome' => $ag['empresa_nome'], 'email' => $ag['empresa_email'], 'notificar_email' => $ag['notificar_email']],
            'Agendamento cancelado: ' . $ag['cliente_nome'] . ' — ' . date('d/m H:i', strtotime($ag['data_hora'])),
            [$ag['cliente_nome'] . ' cancelou o agendamento de ' . date('d/m/Y \à\s H:i', strtotime($ag['data_hora'])) . ($ag['servico_nome'] ? ' (' . $ag['servico_nome'] . ')' : '') . '.', $motivo ? 'Motivo: ' . $motivo : '']);
        if ($empresaWa = wa_carregar_empresa($ag['empresa_id'])) {
            wa_adiar();
            $varsCanc = wa_vars_agendamento($ag + ['telefone' => $ag['cliente_telefone'] ?? ''], $empresaWa);
            wa_notificar_empresa($empresaWa, 'cancelamento', ['nome' => $ag['cliente_nome']] + $varsCanc, 'agendamento', $ag['id']);
            if (!empty($ag['cliente_telefone'])) wa_notificar_cliente($empresaWa, 'agendamento_cancelado', $ag['cliente_telefone'], $varsCanc, 'agendamento', $ag['id']);
            espera_avisar($empresaWa, $ag['data_hora'], $ag['servico_id'] ?? null); // vaga aberta: avisa a lista de espera
            if (!empty($ag['assinatura_id'])) { require_once __DIR__ . '/../includes/clube.php'; clube_usar((int)$ag['assinatura_id'], -1); } // devolve o uso do plano
        }
        $ag['status'] = 'cancelado';
        $msg = 'ok';
    }
}
// Cliente confirma presença pelo link do lembrete (reduz falta, como no Trinks/Booksy)
if ($ag && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['confirmar'])) {
    if (!hash_equals($_SESSION['gds_cancel_csrf'] ?? '', (string)($_POST['k'] ?? ''))) $msg = 'Página expirada. Tente de novo.';
    elseif ($ag['status'] === 'agendado' && strtotime($ag['data_hora']) > time()) {
        $pdo->prepare("UPDATE agendamentos SET status='confirmado' WHERE id=?")->execute([$ag['id']]);
        $ag['status'] = 'confirmado'; $msg = 'confirmado';
        if ($empresaWa = wa_carregar_empresa($ag['empresa_id'])) {
            wa_adiar();
            wa_notificar_empresa($empresaWa, 'contato', ['nome' => $ag['cliente_nome'], 'telefone' => $ag['cliente_telefone'] ?? '', 'mensagem' => '✅ Confirmou presença: ' . date('d/m \à\s H:i', strtotime($ag['data_hora'])) . ($ag['servico_nome'] ? ' — ' . $ag['servico_nome'] : '')], 'agendamento', $ag['id']);
        }
    }
}
// Remarcar: o cliente escolhe outro dia/horário livre (mesmo serviço e profissional)
if ($ag && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['remarcar'])) {
    require_once __DIR__ . '/../includes/agenda_helper.php';
    $nova = (string)($_POST['nova_data'] ?? '') . ' ' . (string)($_POST['nova_hora'] ?? '');
    if (!hash_equals($_SESSION['gds_cancel_csrf'] ?? '', (string)($_POST['k'] ?? ''))) $msg = 'Página expirada. Tente de novo.';
    elseif (in_array($ag['status'], ['cancelado', 'concluido'], true) || strtotime($ag['data_hora']) < time() + 3600) $msg = 'Este agendamento não pode mais ser remarcado por aqui. Fale com a empresa.';
    else {
        $emp = $pdo->prepare("SELECT * FROM empresas WHERE id=?"); $emp->execute([$ag['empresa_id']]); $emp = $emp->fetch();
        $sv = null;
        if ($ag['servico_id']) { $q = $pdo->prepare("SELECT id, vagas, duracao_minutos FROM servicos WHERE id=?"); $q->execute([$ag['servico_id']]); $sv = $q->fetch() ?: null; }
        $erro = agenda_validar($emp, trim($nova), (int)($ag['duracao_minutos'] ?: 30), $ag['profissional_id'] ?: null, $ag['id'], $sv);
        if ($erro) $msg = $erro;
        else {
            $antes = $ag['data_hora'];
            $pdo->prepare("UPDATE agendamentos SET data_hora=?, status='agendado', lembrete_enviado_em=NULL WHERE id=?")->execute([trim($nova), $ag['id']]);
            $ag['data_hora'] = trim($nova) . ':00'; $ag['status'] = 'agendado'; $msg = 'remarcado';
            site_notificar_empresa(['nome' => $ag['empresa_nome'], 'email' => $ag['empresa_email'], 'notificar_email' => $ag['notificar_email']],
                'Agendamento remarcado: ' . $ag['cliente_nome'], [$ag['cliente_nome'] . ' remarcou de ' . date('d/m H:i', strtotime($antes)) . ' para ' . date('d/m/Y \à\s H:i', strtotime($ag['data_hora'])) . '.']);
            if ($empresaWa = wa_carregar_empresa($ag['empresa_id'])) {
                wa_adiar();
                wa_notificar_empresa($empresaWa, 'contato', ['nome' => $ag['cliente_nome'], 'telefone' => $ag['cliente_telefone'] ?? '', 'mensagem' => '🔁 Remarcou: de ' . date('d/m H:i', strtotime($antes)) . ' para ' . date('d/m \à\s H:i', strtotime($ag['data_hora'])) . ($ag['servico_nome'] ? ' — ' . $ag['servico_nome'] : '')], 'agendamento', $ag['id']);
                espera_avisar($empresaWa, $antes, $ag['servico_id'] ?? null); // o horário antigo ficou livre
            }
        }
    }
}
// Avaliação do atendimento pela página (como no delivery) — vai para Avaliações no painel, para aprovar
$jaAvaliou = false;
if ($ag) { try { $q = $pdo->prepare("SELECT 1 FROM avaliacoes WHERE agendamento_id=? LIMIT 1"); $q->execute([$ag['id']]); $jaAvaliou = (bool)$q->fetchColumn(); } catch (PDOException $e) {} }
if ($ag && !$jaAvaliou && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['avaliar'])) {
    if (!hash_equals($_SESSION['gds_cancel_csrf'] ?? '', (string)($_POST['k'] ?? ''))) $msg = 'Página expirada. Tente de novo.';
    elseif ($ag['status'] !== 'concluido') $msg = 'Você pode avaliar depois do atendimento.';
    else {
        try {
            $pdo->prepare("INSERT INTO avaliacoes (empresa_id, cliente_id, nota, comentario, nome_autor, aprovado, origem, agendamento_id) VALUES (?,?,?,?,?,false,'agendamento',?)")
                ->execute([$ag['empresa_id'], $ag['cliente_id'], max(1, min(5, (int)($_POST['nota'] ?? 5))), mb_substr(trim(strip_tags((string)($_POST['comentario'] ?? ''))), 0, 1000) ?: null, mb_substr((string)$ag['cliente_nome'], 0, 100) ?: 'Cliente', $ag['id']]);
            $jaAvaliou = true; $msg = 'avaliado';
        } catch (PDOException $e) { $msg = 'Não deu para enviar agora. Tente de novo.'; }
    }
}
$_SESSION['gds_cancel_csrf'] = bin2hex(random_bytes(16));

$tema = $ag ? site_tema($ag) : ['accent' => '#2563eb'];
$statusTxt = ['agendado' => 'Aguardando confirmação', 'confirmado' => 'Confirmado', 'concluido' => 'Concluído', 'cancelado' => 'Cancelado'];
$ts = $ag ? strtotime($ag['data_hora']) : 0;
$podeCancelar = $ag && !in_array($ag['status'], ['cancelado', 'concluido'], true) && $ts > time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= $ag ? 'Seu agendamento — ' . e($ag['empresa_nome']) : 'Agendamento não encontrado' ?></title>
<?= frag_css_apoio($tema) ?>
<style>body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;background:#f4f5f8;color:#1c1f2b;display:flex;justify-content:center;padding:32px 16px}main{width:100%;max-width:520px}</style>
</head>
<body>
<main class="gds-c">
<?php if (!$ag): ?>
    <div class="gds-card" style="background:#fff"><h3>Agendamento não encontrado</h3><p class="gds-mute" style="margin-top:8px">Confira se o link está completo.</p></div>
<?php else: ?>
    <div class="gds-card" style="background:#fff">
        <p class="gds-mute" style="font-size:.85rem"><?= e($ag['empresa_nome']) ?></p>
        <h3 style="font-size:1.4rem;margin:4px 0 14px">Seu agendamento</h3>
        <?php if ($msg === 'ok'): ?><div class="gds-alerta gds-alerta-ok">Agendamento cancelado. Avisamos a empresa.</div>
        <?php elseif ($msg === 'avaliado'): ?><div class="gds-alerta gds-alerta-ok">Obrigado pela avaliação! 💛</div>
        <?php elseif ($msg === 'confirmado'): ?><div class="gds-alerta gds-alerta-ok">✅ Presença confirmada. Te esperamos!</div>
        <?php elseif ($msg === 'remarcado'): ?><div class="gds-alerta gds-alerta-ok">🔁 Remarcado! Avisamos a empresa.</div>
        <?php elseif ($msg): ?><div class="gds-alerta gds-alerta-erro"><?= e($msg) ?></div><?php endif; ?>
        <div class="gds-resumo">
            <div><span>Situação</span><span><?= e($statusTxt[$ag['status']] ?? $ag['status']) ?></span></div>
            <?php if ($ag['servico_nome']): ?><div><span>Serviço</span><span><?= e($ag['servico_nome']) ?></span></div><?php endif; ?>
            <?php if ($ag['profissional_nome']): ?><div><span>Profissional</span><span><?= e($ag['profissional_nome']) ?></span></div><?php endif; ?>
            <div><span>Quando</span><span><?= date('d/m/Y \à\s H:i', $ts) ?></span></div>
            <?php if ($ag['endereco']): ?><div><span>Onde</span><span><?= e($ag['endereco']) ?></span></div><?php endif; ?>
            <?php if ($ag['valor'] !== null && (float)$ag['valor'] > 0): ?><div class="gds-total"><span>Valor</span><span>R$ <?= number_format((float)$ag['valor'], 2, ',', '.') ?></span></div><?php endif; ?>
        </div>
        <div class="gds-acoes">
            <?php if ($ag['status'] !== 'cancelado' && $ts > time()): ?>
            <a class="gds-btn-2" target="_blank" rel="noopener" href="<?= e(gerar_link_google_calendar(($ag['servico_nome'] ?: 'Agendamento') . ' - ' . $ag['empresa_nome'], $ag['data_hora'], $ag['duracao_minutos'] ?: 30, '', $ag['endereco'] ?? '')) ?>"><?= frag_icone('calend') ?> Google Agenda</a>
            <?php endif; ?>
            <?php if ($ag['whatsapp']): ?><a class="gds-btn gds-btn-wa" target="_blank" rel="noopener" href="<?= e(frag_link_whatsapp($ag['whatsapp'], 'Olá! Sobre o meu agendamento de ' . date('d/m \à\s H:i', $ts) . ' (' . $ag['cliente_nome'] . '):')) ?>"><?= frag_icone('whats') ?> Falar com a empresa</a><?php endif; ?>
            <?php if ($ag['endereco']): ?><a class="gds-btn-2" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode($ag['endereco']) ?>"><?= frag_icone('rota') ?> Como chegar</a><?php endif; ?>
        </div>
        <?php if ($podeCancelar && $ag['status'] === 'agendado'): ?>
        <form method="post" style="margin-top:16px"><input type="hidden" name="t" value="<?= e($token) ?>"><input type="hidden" name="k" value="<?= e($_SESSION['gds_cancel_csrf']) ?>">
            <button type="submit" name="confirmar" value="1" class="gds-btn gds-btn-bloco">✅ Confirmar minha presença</button></form>
        <?php endif; ?>
        <?php if ($podeCancelar && $ts > time() + 3600): ?>
        <details style="margin-top:16px" data-remarcar><summary class="gds-btn-2">🔁 Remarcar para outro dia/horário</summary>
            <form method="post" style="margin-top:14px">
                <input type="hidden" name="t" value="<?= e($token) ?>"><input type="hidden" name="k" value="<?= e($_SESSION['gds_cancel_csrf']) ?>">
                <div class="gds-campo"><label for="nova_data">Novo dia</label><input type="date" id="nova_data" name="nova_data" min="<?= date('Y-m-d') ?>" required></div>
                <div class="gds-campo"><label for="nova_hora">Horário livre</label><select id="nova_hora" name="nova_hora" required><option value="">Escolha o dia primeiro</option></select></div>
                <button type="submit" name="remarcar" value="1" class="gds-btn">Remarcar</button>
            </form>
        </details>
        <script>
        (function () {
            var d = document.getElementById('nova_data'), h = document.getElementById('nova_hora');
            d.addEventListener('change', function () {
                h.innerHTML = '<option value="">Carregando…</option>';
                fetch('/site/horarios.php?e=<?= (int)$ag['empresa_id'] ?>&data=' + d.value + '&duracao=<?= (int)($ag['duracao_minutos'] ?: 30) ?>&profissional=<?= (int)$ag['profissional_id'] ?>&servico=<?= (int)$ag['servico_id'] ?>')
                    .then(function (r) { return r.json(); }).then(function (r) {
                        var l = (r.livres || []); h.innerHTML = l.length ? '' : '<option value="">Sem horários livres nesse dia</option>';
                        l.forEach(function (x) { var o = document.createElement('option'); o.value = o.textContent = (typeof x === 'string' ? x : x.hora); h.appendChild(o); });
                    });
            });
        })();
        </script>
        <?php endif; ?>
        <?php if ($podeCancelar): ?>
        <details style="margin-top:22px"><summary class="gds-btn-2" style="color:var(--gds-erro)">Preciso cancelar</summary>
            <form method="post" style="margin-top:14px">
                <input type="hidden" name="t" value="<?= e($token) ?>"><input type="hidden" name="k" value="<?= e($_SESSION['gds_cancel_csrf']) ?>">
                <div class="gds-campo"><label for="motivo">Motivo (opcional)</label><input type="text" id="motivo" name="motivo" maxlength="255"></div>
                <button type="submit" name="cancelar" value="1" class="gds-btn" style="background:var(--gds-erro);color:#fff" onclick="return confirm('Cancelar este agendamento?')">Confirmar cancelamento</button>
            </form>
        </details>
        <?php endif; ?>
        <?php if ($ag['status'] === 'concluido' && !$jaAvaliou): ?>
        <form method="post" class="gds-avaliar-pedido"><input type="hidden" name="t" value="<?= e($token) ?>"><input type="hidden" name="k" value="<?= e($_SESSION['gds_cancel_csrf']) ?>">
            <h4>Como foi seu atendimento?</h4>
            <div class="gds-estrelas" role="radiogroup" aria-label="Nota"><?php for ($n = 5; $n >= 1; $n--): ?><input type="radio" id="nota<?= $n ?>" name="nota" value="<?= $n ?>" required><label for="nota<?= $n ?>" title="<?= $n ?> estrela(s)" aria-label="<?= $n ?> estrela(s)">★</label><?php endfor; ?></div>
            <label for="comentario" class="gds-mute" style="font-size:.85rem">Comentário (opcional)</label>
            <textarea id="comentario" name="comentario" rows="2" maxlength="1000" placeholder="Conte o que achou…"></textarea>
            <button type="submit" name="avaliar" value="1" class="gds-btn">Enviar avaliação</button>
        </form>
        <?php endif; ?>
        <?php if (in_array($ag['status'], ['concluido', 'cancelado', 'faltou'], true) || $ts < time()): ?>
        <a class="gds-btn gds-btn-bloco" style="margin-top:18px" href="/<?= e($ag['slug']) ?>?servico=<?= (int)$ag['servico_id'] ?><?= $ag['profissional_id'] ? '&profissional=' . (int)$ag['profissional_id'] : '' ?>#gds-agendamento">🔁 Agendar de novo<?= $ag['servico_nome'] ? ' (' . e($ag['servico_nome']) . ')' : '' ?></a>
        <?php endif; ?>
        <p style="margin-top:22px;font-size:.88rem"><a href="/<?= e($ag['slug']) ?>#gds-agendamento">Fazer um novo agendamento</a></p>
    </div>
<?php endif; ?>
</main>
</body>
</html>
