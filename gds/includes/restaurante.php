<?php
// ============================================================
// RESTAURANTE E BAR (migração 42) — ligados em Recursos do site:
//  • Reserva de mesa: o cliente pede dia, hora e quantas pessoas; a casa confirma no painel e o
//    cliente recebe a confirmação no WhatsApp.
//  • Fila de espera virtual: entra na fila pelo site (ou pelo QR na porta), acompanha a posição ao
//    vivo no celular e recebe "sua mesa está pronta" no WhatsApp.
//  • Chamar garçom / pedir a conta: botões no cardápio aberto pelo QR Code da mesa; a equipe vê os
//    chamados ao vivo no painel (com som).
// Tudo de graça: PHP, CSS e JS próprios + a fila de WhatsApp que a plataforma já tem.
// ============================================================

const RES_STATUS = ['pendente' => 'Aguardando confirmação', 'confirmada' => 'Confirmada', 'cancelada' => 'Cancelada', 'nao_veio' => 'Não veio'];

function res_horarios($empresa) {
    $ab = substr((string)($empresa['horario_abertura'] ?? '11:00'), 0, 5) ?: '11:00';
    $fe = substr((string)($empresa['horario_fechamento'] ?? '23:00'), 0, 5) ?: '23:00';
    [$h1, $m1] = array_map('intval', explode(':', $ab)); [$h2, $m2] = array_map('intval', explode(':', $fe));
    $ini = $h1 * 60 + $m1; $fim = $h2 * 60 + $m2; if ($fim <= $ini) $fim = 23 * 60 + 30;
    $out = [];
    for ($m = $ini; $m <= $fim - 60; $m += 30) $out[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    return $out ?: ['19:00', '19:30', '20:00', '20:30', '21:00'];
}

function res_dias_abertos($empresa) {
    $d = (string)($empresa['dias_funcionamento'] ?? '');
    return $d !== '' ? array_map('intval', explode(',', $d)) : [0, 1, 2, 3, 4, 5, 6];
}

// Pedido de reserva feito no site
function res_criar($empresa, $post) {
    global $pdo;
    $nome = mb_substr(trim(strip_tags((string)($post['nome'] ?? ''))), 0, 120);
    $tel = preg_replace('/\D/', '', (string)($post['tel'] ?? ''));
    $data = (string)($post['data'] ?? ''); $hora = (string)($post['hora'] ?? '');
    $pessoas = max(1, min(30, (int)($post['pessoas'] ?? 2)));
    $obs = mb_substr(trim(strip_tags((string)($post['obs'] ?? ''))), 0, 300);
    if ($nome === '' || strlen($tel) < 10) return [false, 'Informe o seu nome e o WhatsApp com DDD.'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || $data < date('Y-m-d') || $data > date('Y-m-d', strtotime('+60 days'))) return [false, 'Escolha uma data a partir de hoje.'];
    if (!in_array((int)date('w', strtotime($data)), res_dias_abertos($empresa), true)) return [false, 'Não abrimos neste dia da semana. Escolha outro dia.'];
    if (!in_array($hora, res_horarios($empresa), true)) return [false, 'Escolha um dos horários da lista.'];
    if ($data === date('Y-m-d') && strtotime("$data $hora") < time() + 30 * 60) return [false, 'Esse horário já passou. Escolha outro.'];
    $dup = $pdo->prepare("SELECT 1 FROM reservas WHERE empresa_id=? AND telefone=? AND data=? AND status IN ('pendente','confirmada')");
    $dup->execute([$empresa['id'], $tel, $data]);
    if ($dup->fetch()) return [false, 'Você já tem uma reserva para este dia. Se precisar mudar, fale com a gente no WhatsApp.'];
    $token = bin2hex(random_bytes(12));
    $pdo->prepare("INSERT INTO reservas (empresa_id, nome, telefone, data, hora, pessoas, observacoes, token) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$empresa['id'], $nome, $tel, $data, $hora, $pessoas, $obs ?: null, $token]);
    $quando = date('d/m', strtotime($data)) . ' às ' . $hora;
    if (function_exists('wa_adiar')) wa_adiar();
    if (function_exists('wa_enfileirar')) {
        wa_enfileirar($empresa, $tel, "Olá, " . explode(' ', $nome)[0] . "! Recebemos o seu pedido de reserva na *{$empresa['nome']}* para {$pessoas} pessoa(s) em {$quando}. Já já confirmamos por aqui. 🍽", 'reserva_recebida');
        if (!empty($empresa['whatsapp'])) wa_enfileirar($empresa, $empresa['whatsapp'], "🍽 *Novo pedido de reserva pelo site*\n👤 {$nome} · {$tel}\n📅 {$quando} · {$pessoas} pessoa(s)" . ($obs ? "\n📝 {$obs}" : '') . "\nConfirme no painel: Reservas e fila.", 'aviso_reserva', 'empresa');
    }
    if (function_exists('gds_cache_invalidar')) gds_cache_invalidar((int)$empresa['id']);
    return [true, "Pedido de reserva enviado para {$quando}. A confirmação chega no seu WhatsApp."];
}

function res_mudar($empresa, $id, $status) {
    global $pdo;
    if (!isset(RES_STATUS[$status])) return false;
    $st = $pdo->prepare("SELECT * FROM reservas WHERE id=? AND empresa_id=?"); $st->execute([(int)$id, (int)$empresa['id']]);
    $r = $st->fetch();
    if (!$r || $r['status'] === $status) return (bool)$r;
    $pdo->prepare("UPDATE reservas SET status=? WHERE id=?")->execute([$status, $r['id']]);
    $quando = date('d/m', strtotime($r['data'])) . ' às ' . $r['hora'];
    $msg = ['confirmada' => "✅ Reserva confirmada na *{$empresa['nome']}*: {$quando}, {$r['pessoas']} pessoa(s). Até lá!",
            'cancelada' => "Olá! Infelizmente não conseguimos confirmar a sua reserva de {$quando} na *{$empresa['nome']}*. Quer tentar outro horário? É só responder aqui."][$status] ?? null;
    if ($msg && function_exists('wa_enfileirar')) { if (function_exists('wa_adiar')) wa_adiar(); wa_enfileirar($empresa, $r['telefone'], $msg, 'reserva_' . $status); }
    return true;
}

// ---------- fila de espera ----------
function fila_entrar($empresa, $post) {
    global $pdo;
    $nome = mb_substr(trim(strip_tags((string)($post['nome'] ?? ''))), 0, 120);
    $tel = preg_replace('/\D/', '', (string)($post['tel'] ?? ''));
    $pessoas = max(1, min(30, (int)($post['pessoas'] ?? 2)));
    if ($nome === '') return [false, 'Informe o seu nome.', null];
    if ($tel !== '' && strlen($tel) < 10) return [false, 'Confira o WhatsApp (com DDD) ou deixe em branco.', null];
    if ($tel !== '') {
        $j = $pdo->prepare("SELECT token FROM fila_espera WHERE empresa_id=? AND telefone=? AND status IN ('aguardando','chamado') AND criado_em > NOW() - INTERVAL '12 hours'");
        $j->execute([$empresa['id'], $tel]);
        if ($t = $j->fetchColumn()) return [true, 'Você já está na fila.', $t];
    }
    $token = bin2hex(random_bytes(12));
    $pdo->prepare("INSERT INTO fila_espera (empresa_id, nome, telefone, pessoas, token) VALUES (?,?,?,?,?)")->execute([$empresa['id'], $nome, $tel ?: null, $pessoas, $token]);
    return [true, 'Você entrou na fila!', $token];
}

function fila_posicao($token) {
    global $pdo;
    $st = $pdo->prepare("SELECT f.*, e.nome AS empresa_nome, e.slug FROM fila_espera f JOIN empresas e ON e.id=f.empresa_id WHERE f.token=?");
    $st->execute([(string)$token]);
    $f = $st->fetch();
    if (!$f) return null;
    $pos = null;
    if ($f['status'] === 'aguardando') {
        $q = $pdo->prepare("SELECT COUNT(*) FROM fila_espera WHERE empresa_id=? AND status='aguardando' AND criado_em <= ? AND criado_em > NOW() - INTERVAL '12 hours'");
        $q->execute([$f['empresa_id'], $f['criado_em']]);
        $pos = (int)$q->fetchColumn();
    }
    return ['status' => $f['status'], 'posicao' => $pos, 'nome' => explode(' ', $f['nome'])[0], 'empresa' => $f['empresa_nome'], 'slug' => $f['slug'], 'pessoas' => (int)$f['pessoas']];
}

function fila_mudar($empresa, $id, $status) {
    global $pdo;
    if (!in_array($status, ['chamado', 'sentou', 'desistiu'], true)) return false;
    $st = $pdo->prepare("SELECT * FROM fila_espera WHERE id=? AND empresa_id=?"); $st->execute([(int)$id, (int)$empresa['id']]);
    $f = $st->fetch();
    if (!$f) return false;
    $pdo->prepare("UPDATE fila_espera SET status=?, chamado_em=CASE WHEN ?='chamado' THEN NOW() ELSE chamado_em END WHERE id=?")->execute([$status, $status, $f['id']]);
    if ($status === 'chamado' && $f['telefone'] && function_exists('wa_enfileirar')) {
        if (function_exists('wa_adiar')) wa_adiar();
        wa_enfileirar($empresa, $f['telefone'], "🔔 " . explode(' ', $f['nome'])[0] . ", a sua mesa na *{$empresa['nome']}* está pronta! Pode vir até a recepção.", 'fila_chamado');
    }
    return true;
}

// ---------- chamar garçom / pedir a conta ----------
function mesa_chamar($empresa, $mesa, $tipo) {
    global $pdo;
    $mesa = mb_substr(preg_replace('/[^0-9A-Za-z]/', '', (string)$mesa), 0, 10);
    if ($mesa === '' || !in_array($tipo, ['garcom', 'conta'], true)) return [false, 'Mesa inválida.'];
    $ja = $pdo->prepare("SELECT 1 FROM mesa_chamados WHERE empresa_id=? AND mesa=? AND tipo=? AND status='aberto' AND criado_em > NOW() - INTERVAL '30 minutes'");
    $ja->execute([$empresa['id'], $mesa, $tipo]);
    if (!$ja->fetch()) $pdo->prepare("INSERT INTO mesa_chamados (empresa_id, mesa, tipo) VALUES (?,?,?)")->execute([$empresa['id'], $mesa, $tipo]);
    return [true, $tipo === 'conta' ? 'Pedimos a sua conta. Já vamos até a mesa ' . $mesa . '.' : 'Chamamos o garçom para a mesa ' . $mesa . '. Já estamos indo!'];
}

// Tudo que a equipe precisa ver agora (painel ao vivo)
function rest_ao_vivo($empresaId) {
    global $pdo;
    $ch = $pdo->prepare("SELECT id, mesa, tipo, criado_em FROM mesa_chamados WHERE empresa_id=? AND status='aberto' AND criado_em > NOW() - INTERVAL '3 hours' ORDER BY criado_em");
    $ch->execute([(int)$empresaId]);
    $fi = $pdo->prepare("SELECT id, nome, telefone, pessoas, status, criado_em, chamado_em FROM fila_espera WHERE empresa_id=? AND status IN ('aguardando','chamado') AND criado_em > NOW() - INTERVAL '12 hours' ORDER BY criado_em");
    $fi->execute([(int)$empresaId]);
    $re = $pdo->prepare("SELECT id, nome, telefone, data, hora, pessoas, observacoes, status FROM reservas WHERE empresa_id=? AND data >= CURRENT_DATE AND status IN ('pendente','confirmada') ORDER BY data, hora LIMIT 100");
    $re->execute([(int)$empresaId]);
    return ['chamados' => $ch->fetchAll(), 'fila' => $fi->fetchAll(), 'reservas' => $re->fetchAll()];
}

// ---------- HTML do site ----------
function rest_html_reserva($empresa) {
    $dias = res_dias_abertos($empresa);
    $hs = res_horarios($empresa);
    $opH = implode('', array_map(fn($h) => '<option>' . $h . '</option>', $hs));
    $opP = implode('', array_map(fn($n) => '<option value="' . $n . '"' . ($n === 2 ? ' selected' : '') . '>' . $n . ($n === 1 ? ' pessoa' : ' pessoas') . '</option>', range(1, 20)));
    return '<form class="lp-rest-form" data-rest="reservar" data-e="' . (int)$empresa['id'] . '" data-dias="' . e(implode(',', $dias)) . '" novalidate><div class="lp-rest-grade">'
        . '<div><label for="rsNome">Seu nome</label><input id="rsNome" name="nome" autocomplete="name" required></div>'
        . '<div><label for="rsTel">WhatsApp com DDD</label><input id="rsTel" name="tel" type="tel" inputmode="tel" autocomplete="tel" required></div>'
        . '<div><label for="rsData">Dia</label><input id="rsData" name="data" type="date" min="' . date('Y-m-d') . '" max="' . date('Y-m-d', strtotime('+60 days')) . '" required></div>'
        . '<div><label for="rsHora">Horário</label><select id="rsHora" name="hora" required><option value="">Escolha</option>' . $opH . '</select></div>'
        . '<div><label for="rsPes">Pessoas</label><select id="rsPes" name="pessoas">' . $opP . '</select></div>'
        . '<div class="lp-rest-largo"><label for="rsObs">Observação (opcional)</label><input id="rsObs" name="obs" maxlength="300" placeholder="Aniversário, cadeirão para criança, área externa…"></div>'
        . '</div><button type="submit" class="lp-btn">Pedir reserva</button><p class="lp-rest-res" role="status" aria-live="polite"></p></form>';
}

function rest_html_fila($empresa) {
    $opP = implode('', array_map(fn($n) => '<option value="' . $n . '"' . ($n === 2 ? ' selected' : '') . '>' . $n . ($n === 1 ? ' pessoa' : ' pessoas') . '</option>', range(1, 20)));
    return '<form class="lp-rest-form" data-rest="fila" data-e="' . (int)$empresa['id'] . '" novalidate><div class="lp-rest-grade">'
        . '<div><label for="flNome">Seu nome</label><input id="flNome" name="nome" autocomplete="given-name" required></div>'
        . '<div><label for="flTel">WhatsApp (avisamos quando a mesa ficar pronta)</label><input id="flTel" name="tel" type="tel" inputmode="tel" autocomplete="tel"></div>'
        . '<div><label for="flPes">Pessoas</label><select id="flPes" name="pessoas">' . $opP . '</select></div>'
        . '</div><button type="submit" class="lp-btn">Entrar na fila</button><p class="lp-rest-res" role="status" aria-live="polite"></p></form>';
}

function rest_html_garcom($empresa, $mesa) {
    $mesa = mb_substr(preg_replace('/[^0-9A-Za-z]/', '', (string)$mesa), 0, 10);
    if ($mesa === '') return '';
    return '<aside class="lp-garcom" data-e="' . (int)$empresa['id'] . '" data-mesa="' . e($mesa) . '" aria-label="Atendimento na mesa ' . e($mesa) . '">'
        . '<button type="button" data-chamar="garcom">🙋 Chamar garçom</button><button type="button" data-chamar="conta">🧾 Pedir a conta</button><p class="lp-garcom-res" role="status" aria-live="polite"></p></aside>';
}

// Página "minha posição na fila" (/site/restaurante.php?fila=TOKEN)
function fila_pagina_html($token, $f, $cor) {
    $titulo = $f['status'] === 'chamado' ? 'Sua mesa está pronta!' : ($f['status'] === 'aguardando' ? 'Você está na fila' : 'Fila encerrada');
    $grande = $f['status'] === 'aguardando' ? ($f['posicao'] . 'º') : ($f['status'] === 'chamado' ? '🔔' : '✓');
    $txt = $f['status'] === 'aguardando' ? ($f['posicao'] === 1 ? 'Você é o próximo! Fique por perto.' : 'Há ' . ($f['posicao'] - 1) . ' grupo(s) na sua frente. Esta tela se atualiza sozinha.')
         : ($f['status'] === 'chamado' ? 'Pode vir até a recepção, ' . e($f['nome']) . '.' : 'Obrigado pela visita, ' . e($f['nome']) . '!');
    return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Fila · ' . e($f['empresa']) . '</title>'
        . '<style>:root{--c:' . e($cor) . '}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f6f5f2;color:#1b1b1b;font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;padding:20px;box-sizing:border-box;text-align:center}'
        . '.c{background:#fff;border-radius:24px;padding:32px 24px;max-width:420px;width:100%;box-shadow:0 20px 60px -30px rgba(0,0,0,.35)}h1{font-size:22px;margin:0 0 4px}.n{font-size:72px;font-weight:800;color:var(--c);margin:12px 0;line-height:1}'
        . 'p{margin:6px 0;color:#444}a,button{display:inline-block;margin-top:16px;color:#1b1b1b;font:600 15px system-ui;background:none;border:1px solid #ccc;border-radius:999px;padding:10px 18px;text-decoration:none;cursor:pointer}'
        . '@media(prefers-reduced-motion:no-preference){.n{animation:p 2s ease-in-out infinite}@keyframes p{50%{transform:scale(1.04)}}}</style></head><body><main class="c">'
        . '<p>' . e($f['empresa']) . ' · ' . (int)$f['pessoas'] . ' pessoa(s)</p><h1>' . $titulo . '</h1><div class="n" aria-hidden="true">' . $grande . '</div><p role="status" aria-live="polite">' . $txt . '</p>'
        . ($f['status'] === 'aguardando' ? '<form method="post"><input type="hidden" name="sair" value="1"><button type="submit">Sair da fila</button></form>' : '<a href="/' . e($f['slug']) . '">Voltar ao site</a>')
        . '</main>' . (in_array($f['status'], ['aguardando', 'chamado'], true) ? '<script>setTimeout(function(){location.reload()},15000)</script>' : '') . '</body></html>';
}
