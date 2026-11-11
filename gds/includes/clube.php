<?php
// ============================================================
// CLUBE DE ASSINATURA e PACOTES DE SESSÕES (barbearia, salão, estética,
// academia, pilates, escolas...)
// - Assinatura: preço por período (ex.: R$ 120/mês) com N usos no período (0 = ilimitado)
// - Pacote: N sessões por um preço, válidas por X dias
// O cliente assina pelo site e paga por Pix (ativa sozinho quando o Pix cai) ou a
// empresa ativa no painel. Ao agendar um serviço incluído, o uso sai do plano.
// ============================================================

function clube_preco($v) { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }

function clube_planos($empresaId, $soAtivos = true) {
    global $pdo;
    try {
        $st = $pdo->prepare("SELECT * FROM planos_cliente WHERE empresa_id=?" . ($soAtivos ? ' AND ativo=true' : '') . " ORDER BY destaque DESC, tipo, preco");
        $st->execute([$empresaId]);
        return $st->fetchAll();
    } catch (PDOException $e) { return []; }
}

function clube_servicos_ids($plano) {
    return array_values(array_filter(array_map('intval', (array)json_decode((string)($plano['servicos'] ?? ''), true))));
}

function clube_url($empresa, $token) {
    return (function_exists('wa_url_publica') ? wa_url_publica($empresa, '/site/clube.php?t=' . $token) : '/site/clube.php?t=' . $token);
}

// Assinatura/pacote ativo do cliente que cobre o serviço e ainda tem uso
function clube_do_cliente($empresaId, $clienteId, $servicoId) {
    global $pdo;
    if (!$clienteId) return null;
    try {
        $st = $pdo->prepare("SELECT a.*, p.nome AS plano_nome, p.tipo, p.quantidade, p.servicos FROM assinaturas a JOIN planos_cliente p ON p.id = a.plano_id
            WHERE a.empresa_id=? AND a.cliente_id=? AND a.status='ativa' AND (a.vence_em IS NULL OR a.vence_em >= CURRENT_DATE) ORDER BY a.vence_em");
        $st->execute([$empresaId, $clienteId]);
        foreach ($st->fetchAll() as $a) {
            $ids = clube_servicos_ids($a);
            if ($ids && !in_array((int)$servicoId, $ids, true)) continue;
            if ((int)$a['quantidade'] > 0 && (int)$a['usos'] >= (int)$a['quantidade']) continue;
            return $a;
        }
    } catch (PDOException $e) {}
    return null;
}

function clube_usar($assinaturaId, $delta = 1) {
    global $pdo;
    $pdo->prepare("UPDATE assinaturas SET usos = GREATEST(0, usos + ?) WHERE id=?")->execute([$delta, $assinaturaId]);
    // Pacote que acabou as sessões
    $pdo->prepare("UPDATE assinaturas a SET status='usada' FROM planos_cliente p WHERE p.id=a.plano_id AND a.id=? AND p.tipo='pacote' AND p.quantidade > 0 AND a.usos >= p.quantidade")->execute([$assinaturaId]);
}

// Ativa (ou renova) depois do pagamento
function clube_ativar($id) {
    global $pdo;
    $st = $pdo->prepare("SELECT a.*, p.validade_dias, p.tipo, p.nome AS plano_nome, c.nome AS cliente_nome, c.telefone FROM assinaturas a
        JOIN planos_cliente p ON p.id = a.plano_id LEFT JOIN clientes c ON c.id = a.cliente_id WHERE a.id=?");
    $st->execute([$id]);
    $a = $st->fetch();
    if (!$a) return false;
    $dias = max(1, (int)$a['validade_dias']);
    // Renovação antes de vencer: soma ao vencimento atual; senão começa hoje
    $base = $a['status'] === 'ativa' && $a['vence_em'] && $a['vence_em'] >= date('Y-m-d') ? $a['vence_em'] : date('Y-m-d');
    $vence = date('Y-m-d', strtotime("$base +$dias days"));
    $pdo->prepare("UPDATE assinaturas SET status='ativa', inicio=COALESCE(inicio, CURRENT_DATE), vence_em=?, usos=CASE WHEN ? = 'assinatura' THEN 0 ELSE usos END, aviso_vencimento_em=NULL WHERE id=?")
        ->execute([$vence, $a['tipo'], $id]);
    if (!empty($a['telefone']) && function_exists('wa_carregar_empresa') && ($emp = wa_carregar_empresa($a['empresa_id']))) {
        wa_notificar_cliente($emp, 'clube_ativo', $a['telefone'], ['nome' => explode(' ', trim((string)$a['cliente_nome']))[0], 'plano' => $a['plano_nome'],
            'vencimento' => date('d/m/Y', strtotime($vence)), 'link' => function_exists('espera_link_agendar') ? espera_link_agendar($emp) : wa_url_publica($emp)], 'assinatura', $id);
    }
    return true;
}

// Cliente assina pelo site: cria a assinatura pendente e o Pix
function clube_assinar($empresa, $planoId, $nome, $telefone, $email = '') {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM planos_cliente WHERE id=? AND empresa_id=? AND ativo=true"); $st->execute([$planoId, $empresa['id']]);
    $plano = $st->fetch();
    if (!$plano) return ['ok' => false, 'erro' => 'Plano não encontrado.'];
    $tel = preg_replace('/\D/', '', $telefone);
    if (mb_strlen(trim($nome)) < 2 || strlen($tel) < 10) return ['ok' => false, 'erro' => 'Informe seu nome e WhatsApp com DDD.'];
    $busca = $pdo->prepare("SELECT id FROM clientes WHERE empresa_id=? AND right(regexp_replace(COALESCE(telefone,''), '\D', '', 'g'), 11) = right(?, 11) LIMIT 1");
    $busca->execute([$empresa['id'], $tel]);
    $clienteId = $busca->fetchColumn();
    if (!$clienteId) {
        $n = $pdo->prepare("INSERT INTO clientes (empresa_id, nome, telefone, email) VALUES (?,?,?,?) RETURNING id");
        $n->execute([$empresa['id'], mb_substr(trim($nome), 0, 150), $telefone, $email ?: null]);
        $clienteId = $n->fetchColumn();
    }
    $token = bin2hex(random_bytes(16));
    $ins = $pdo->prepare("INSERT INTO assinaturas (empresa_id, plano_id, cliente_id, status, token) VALUES (?,?,?, 'pendente', ?) RETURNING id");
    $ins->execute([$empresa['id'], $plano['id'], $clienteId, $token]);
    $id = (int)$ins->fetchColumn();
    $pix = null;
    if (function_exists('pag_disponivel') && pag_disponivel($empresa) && (float)$plano['preco'] > 0) {
        $cob = pag_criar($empresa, (float)$plano['preco'], $plano['nome'] . ' - ' . $empresa['nome'], $plano['tipo'] === 'pacote' ? 'pacote' : 'mensalidade', ['assinatura_id' => $id], $clienteId, $email);
        if ($cob['ok']) $pix = $cob;
    }
    if (function_exists('wa_notificar_empresa')) wa_notificar_empresa($empresa, 'agendamento', ['nome' => $nome, 'servico' => '⭐ Nova adesão ao "' . $plano['nome'] . '" (' . clube_preco($plano['preco']) . '). ' . ($pix ? 'Ativa sozinha quando o Pix cair.' : 'Confirme o pagamento no painel em Clube e pacotes.')], 'assinatura', $id);
    return ['ok' => true, 'token' => $token, 'pix' => $pix, 'id' => $id];
}

// Rotina (cron): avisa vencimento (com Pix de renovação), vence o que passou do prazo
function clube_rotina() {
    global $pdo;
    try {
        $pdo->exec("UPDATE assinaturas SET status='vencida' WHERE status='ativa' AND vence_em < CURRENT_DATE");
        $lista = $pdo->query("SELECT a.*, p.nome AS plano_nome, p.preco, p.tipo, c.nome AS cliente_nome, c.telefone FROM assinaturas a
            JOIN planos_cliente p ON p.id = a.plano_id LEFT JOIN clientes c ON c.id = a.cliente_id
            WHERE a.status='ativa' AND p.tipo='assinatura' AND a.vence_em <= CURRENT_DATE + 3 AND a.aviso_vencimento_em IS NULL LIMIT 30")->fetchAll();
    } catch (PDOException $e) { return ['clube' => 'sem tabela']; }
    $n = 0;
    foreach ($lista as $a) {
        $pdo->prepare("UPDATE assinaturas SET aviso_vencimento_em=NOW() WHERE id=?")->execute([$a['id']]);
        $emp = wa_carregar_empresa($a['empresa_id']);
        if (!$emp || empty($a['telefone'])) continue;
        if (function_exists('pag_disponivel') && pag_disponivel($emp)) pag_criar($emp, (float)$a['preco'], 'Renovação ' . $a['plano_nome'] . ' - ' . $emp['nome'], 'mensalidade', ['assinatura_id' => $a['id']], $a['cliente_id']);
        wa_notificar_cliente($emp, 'clube_renovar', $a['telefone'], ['nome' => explode(' ', trim((string)$a['cliente_nome']))[0], 'plano' => $a['plano_nome'],
            'vencimento' => date('d/m', strtotime($a['vence_em'])), 'valor' => clube_preco($a['preco']), 'link' => clube_url($emp, $a['token'])], 'assinatura', $a['id']);
        $n++;
    }
    return ['clube' => $n ? "$n aviso(s) de renovação" : 'nada a fazer'];
}

// Seção do site com os planos (template e marcador {{CLUBE}})
function frag_clube($empresa, $planos, $servicos = []) {
    if (!$planos) return '';
    $nomes = []; foreach ($servicos as $s) $nomes[(int)$s['id']] = $s['nome'];
    $html = '<div class="gds-c gds-clube" id="gds-clube"><div class="gds-clube-grade">';
    foreach ($planos as $p) {
        $ids = clube_servicos_ids($p);
        $inclui = $ids ? implode(', ', array_filter(array_map(fn($i) => $nomes[$i] ?? null, $ids))) : 'Todos os serviços';
        $qtd = (int)$p['quantidade'];
        $linha = $p['tipo'] === 'pacote' ? "$qtd sessões · válido por {$p['validade_dias']} dias" : ($qtd > 0 ? "$qtd por mês" : 'Ilimitado no mês');
        $html .= '<article class="gds-plano' . (pg_bool($p['destaque']) ? ' gds-plano-destaque' : '') . '">'
               . (pg_bool($p['destaque']) ? '<span class="gds-chip gds-chip-accent">Mais escolhido</span>' : '')
               . '<h3>' . e($p['nome']) . '</h3><div class="gds-plano-preco">' . e(frag_preco($p['preco'])) . '<small>' . ($p['tipo'] === 'pacote' ? '' : ' /' . ((int)$p['validade_dias'] === 30 ? 'mês' : $p['validade_dias'] . ' dias')) . '</small></div>'
               . '<p class="gds-mute">' . e($linha) . '</p><p class="gds-plano-inclui">' . frag_icone('check') . ' ' . e($inclui) . '</p>'
               . ($p['descricao'] ? '<p class="gds-mute">' . e($p['descricao']) . '</p>' : '')
               . '<form method="post" action="/site/clube.php" class="gds-plano-form"><input type="hidden" name="e" value="' . (int)$empresa['id'] . '"><input type="hidden" name="plano" value="' . (int)$p['id'] . '">'
               . '<div class="gds-campo"><label>Seu nome</label><input type="text" name="nome" required maxlength="120" autocomplete="name"></div>'
               . '<div class="gds-campo"><label>WhatsApp</label><input type="tel" name="telefone" required inputmode="tel" data-gds-tel placeholder="(00) 00000-0000"></div>'
               . frag_honeypot() . '<button type="submit" class="gds-btn gds-btn-bloco">' . ($p['tipo'] === 'pacote' ? 'Comprar pacote' : 'Assinar') . '</button></form></article>';
    }
    return $html . '</div></div>';
}
