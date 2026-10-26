<?php
// ============================================================
// WHATSAPP AUTOMÁTICO — a plataforma manda as mensagens sozinha
// (agendamento recebido/confirmado, lembrete, orçamento com valor,
// pedido do delivery, saiu para entrega...).
//
// De onde a mensagem sai (nesta ordem):
//   1. WhatsApp PRÓPRIO da empresa, conectado por QR code no painel
//   2. WhatsApp DA PLATAFORMA (um número só para todas as empresas)
//   3. Nenhum → a mensagem fica como "manual" no painel, com um botão
//      que abre o WhatsApp com o texto pronto.
//
// Integrações suportadas (o admin escolhe em Configurações):
//   evolution → Evolution API (gratuita, você hospeda; conecta por QR code)
//   zapi      → Z-API (paga, brasileira)
//   webhook   → manda um POST JSON para uma URL (n8n, Make, outro gateway)
// ============================================================

// ---------- Eventos e textos padrão ----------
// {nome} {empresa} {servico} {profissional} {data} {hora} {valor} {itens} {total}
// {entrega} {endereco} {pagamento} {mensagem} {codigo} {link} {telefone}
function wa_eventos() {
    return [
        'agendamento_recebido' => ['grupo' => 'Agendamentos', 'rotulo' => 'Agendamento recebido', 'quando' => 'Assim que o cliente agenda pelo site',
            'texto' => "Olá, {nome}! 👋\nRecebemos seu agendamento na *{empresa}*:\n\n📅 {data} às {hora}\n✂️ {servico}{profissional}\n{valor}\nEm breve confirmamos. Para ver ou cancelar: {link}"],
        'agendamento_confirmado' => ['grupo' => 'Agendamentos', 'rotulo' => 'Agendamento confirmado', 'quando' => 'Quando você marca o agendamento como "Confirmado"',
            'texto' => "✅ {nome}, seu horário está *confirmado*!\n\n📅 {data} às {hora}\n✂️ {servico}\n📍 {endereco}\n\nTe esperamos na *{empresa}*!"],
        'agendamento_lembrete' => ['grupo' => 'Agendamentos', 'rotulo' => 'Lembrete antes do horário', 'quando' => 'Algumas horas antes do agendamento',
            'texto' => "⏰ Lembrete: {nome}, seu horário na *{empresa}* é {data} às {hora} ({servico}).\nSe não puder vir, avise por aqui ou cancele: {link}"],
        'agendamento_cancelado' => ['grupo' => 'Agendamentos', 'rotulo' => 'Agendamento cancelado', 'quando' => 'Quando o agendamento é cancelado',
            'texto' => "{nome}, seu agendamento de {data} às {hora} na *{empresa}* foi *cancelado*.\nQuer marcar outro horário? É só responder esta mensagem."],
        'agendamento_concluido' => ['grupo' => 'Agendamentos', 'rotulo' => 'Pedido de avaliação', 'quando' => 'Quando você marca o atendimento como "Concluído"',
            'texto' => "Obrigado pela visita, {nome}! 💙\nComo foi seu atendimento na *{empresa}*? Sua avaliação ajuda muito: {link}"],

        'orcamento_recebido' => ['grupo' => 'Orçamentos', 'rotulo' => 'Pedido de orçamento recebido', 'quando' => 'Assim que o cliente pede orçamento pelo site',
            'texto' => "Olá, {nome}! 👋\nRecebemos seu pedido de orçamento na *{empresa}*:\n{servico}\n\nJá estamos preparando e te enviamos o valor por aqui. Pedido nº {codigo}."],
        'orcamento_enviado' => ['grupo' => 'Orçamentos', 'rotulo' => 'Orçamento com o valor', 'quando' => 'Quando você responde o orçamento no painel',
            'texto' => "{nome}, seu orçamento da *{empresa}* está pronto! 📝\n\n{servico}\n💰 Valor: *{valor}*\n{mensagem}\n\nPara aprovar, é só responder *SIM* por aqui. Detalhes: {link}"],
        'orcamento_aprovado' => ['grupo' => 'Orçamentos', 'rotulo' => 'Orçamento aprovado', 'quando' => 'Quando você marca o orçamento como "Aprovado"',
            'texto' => "🎉 Combinado, {nome}! Seu orçamento ({valor}) foi aprovado na *{empresa}*. Qualquer dúvida, fale com a gente por aqui."],

        'pedido_recebido' => ['grupo' => 'Pedidos / Delivery', 'rotulo' => 'Pedido recebido', 'quando' => 'Assim que o cliente faz o pedido pelo site',
            'texto' => "Olá, {nome}! 🛍️ Recebemos seu pedido nº {codigo} na *{empresa}*:\n\n{itens}\n\n{entrega}\n💰 Total: *{total}* ({pagamento})\n\nAcompanhe: {link}"],
        'pedido_confirmado' => ['grupo' => 'Pedidos / Delivery', 'rotulo' => 'Pedido confirmado / em preparo', 'quando' => 'Quando você marca o pedido como "Em preparo"',
            'texto' => "👨‍🍳 {nome}, seu pedido nº {codigo} foi *confirmado* e já está sendo preparado!"],
        'pedido_pronto' => ['grupo' => 'Pedidos / Delivery', 'rotulo' => 'Pronto para retirada', 'quando' => 'Quando você marca o pedido como "Pronto"',
            'texto' => "✅ {nome}, seu pedido nº {codigo} está *pronto*! Pode vir buscar na *{empresa}*: {endereco}"],
        'pedido_saiu' => ['grupo' => 'Pedidos / Delivery', 'rotulo' => 'Saiu para entrega', 'quando' => 'Quando você marca o pedido como "Saiu para entrega"',
            'texto' => "🛵 {nome}, seu pedido nº {codigo} *saiu para entrega*! Chega já já."],
        'pedido_concluido' => ['grupo' => 'Pedidos / Delivery', 'rotulo' => 'Pedido entregue / concluído', 'quando' => 'Quando você marca o pedido como "Concluído"',
            'texto' => "Obrigado pela preferência, {nome}! 💙 Esperamos que tenha gostado. Volte sempre à *{empresa}*!"],
        'pedido_cancelado' => ['grupo' => 'Pedidos / Delivery', 'rotulo' => 'Pedido cancelado', 'quando' => 'Quando você cancela um pedido ou orçamento',
            'texto' => "{nome}, seu pedido nº {codigo} na *{empresa}* foi *cancelado*. {mensagem}\nQualquer dúvida, responda esta mensagem."],

        'contato_recebido' => ['grupo' => 'Contato', 'rotulo' => 'Mensagem do site recebida', 'quando' => 'Quando alguém manda mensagem pelo formulário de contato', 'padrao_ativo' => false,
            'texto' => "Olá, {nome}! Recebemos sua mensagem na *{empresa}* e já vamos responder. 😊"],
    ];
}

// Mensagem que a EMPRESA recebe no próprio WhatsApp a cada novidade
function wa_texto_aviso_empresa($tipo, $v) {
    $t = [
        'agendamento' => "🔔 *Novo agendamento pelo site*\n👤 {nome} · {telefone}\n✂️ {servico}{profissional}\n📅 {data} às {hora}\n{valor}",
        'cancelamento' => "❌ *Agendamento cancelado pelo cliente*\n👤 {nome} · {telefone}\n📅 {data} às {hora} — {servico}",
        'orcamento' => "🔔 *Novo pedido de orçamento* (nº {codigo})\n👤 {nome} · {telefone}\n{servico}\n{mensagem}",
        'pedido' => "🛍️ *Novo pedido nº {codigo}*\n👤 {nome} · {telefone}\n\n{itens}\n\n{entrega}\n💰 Total: {total} ({pagamento})",
        'contato' => "💬 *Nova mensagem pelo site*\n👤 {nome} · {telefone}\n{mensagem}",
    ][$tipo] ?? '{mensagem}';
    return wa_aplicar_variaveis($t, $v);
}

function wa_aplicar_variaveis($texto, $v) {
    $mapa = [];
    foreach ($v as $k => $val) $mapa['{' . $k . '}'] = (string)$val;
    // Linha cujas variáveis ficaram todas vazias some inteira (ex.: "📍 {endereco}" sem endereço)
    $linhas = [];
    foreach (explode("\n", $texto) as $linha) {
        if (preg_match_all('/\{([a-z_]+)\}/', $linha, $m) && !array_filter($m[1], fn($k) => trim((string)($v[$k] ?? '')) !== '')) {
            if (!preg_match('/[\p{L}\p{N}]/u', preg_replace('/\{[a-z_]+\}/', '', $linha))) continue;
        }
        $linhas[] = $linha;
    }
    $texto = strtr(implode("\n", $linhas), $mapa);
    $texto = preg_replace('/\{[a-z_]+\}/', '', $texto);         // variável sem valor some
    $texto = preg_replace("/[ \t]+\n/", "\n", $texto);
    return trim(preg_replace("/\n{3,}/", "\n\n", $texto));
}

// Configuração de mensagens da empresa (ativo + texto de cada evento)
function wa_config_empresa($empresa) {
    $salvo = json_decode((string)($empresa['wa_mensagens'] ?? ''), true) ?: [];
    $cfg = [];
    foreach (wa_eventos() as $chave => $ev) {
        $cfg[$chave] = [
            'ativo' => isset($salvo[$chave]['ativo']) ? (bool)$salvo[$chave]['ativo'] : ($ev['padrao_ativo'] ?? true),
            'texto' => trim((string)($salvo[$chave]['texto'] ?? '')) !== '' ? $salvo[$chave]['texto'] : $ev['texto'],
        ];
    }
    return $cfg;
}

// ---------- Números ----------
// "(11) 98888-7777" → "5511988887777"
function wa_numero($tel) {
    $d = preg_replace('/\D/', '', (string)$tel);
    if ($d === '') return '';
    $d = ltrim($d, '0');
    if (strlen($d) <= 11) $d = '55' . $d;
    return strlen($d) >= 12 && strlen($d) <= 15 ? $d : '';
}

function wa_link_manual($tel, $texto) {
    $n = wa_numero($tel);
    return $n ? 'https://wa.me/' . $n . '?text=' . rawurlencode($texto) : '';
}

// ---------- Configuração da integração (admin) ----------
function wa_api() {
    return [
        'tipo' => get_config('wa_api_tipo', ''),
        'url' => rtrim(get_config('wa_api_url', ''), '/'),
        'chave' => get_config('wa_api_chave', ''),
        'plat_instancia' => get_config('wa_plataforma_instancia', ''),
        'plat_token' => get_config('wa_plataforma_token', ''),
        'plat_ativo' => get_config('wa_plataforma_ativo', '0') === '1',
        'empresas_conectar' => get_config('wa_empresas_conectar', '1') === '1',
    ];
}

function wa_api_configurada() {
    $a = wa_api();
    if ($a['tipo'] === 'evolution') return $a['url'] !== '' && $a['chave'] !== '';
    if ($a['tipo'] === 'zapi') return true;
    if ($a['tipo'] === 'webhook') return $a['url'] !== '';
    return false;
}

// A empresa pode conectar o próprio número por QR code?
function wa_pode_conectar_proprio() {
    $a = wa_api();
    return $a['empresas_conectar'] && in_array($a['tipo'], ['evolution', 'zapi'], true) && wa_api_configurada();
}

function wa_plataforma_disponivel() {
    $a = wa_api();
    if (!$a['plat_ativo'] || !wa_api_configurada()) return false;
    return $a['tipo'] === 'webhook' || $a['plat_instancia'] !== '';
}

// De onde sai a mensagem desta empresa: ['canal' => 'proprio'|'plataforma', ...] ou null
function wa_remetente($empresa) {
    $modo = $empresa['wa_modo'] ?? 'plataforma';
    if ($modo === 'desligado') return null;
    $a = wa_api();
    if ($modo === 'proprio' && !empty($empresa['wa_instancia']) && ($empresa['wa_status'] ?? '') === 'conectado' && wa_api_configurada()) {
        return ['canal' => 'proprio', 'tipo' => $a['tipo'], 'instancia' => $empresa['wa_instancia'], 'token' => $empresa['wa_instancia_token'] ?? ''];
    }
    if (wa_plataforma_disponivel()) {
        return ['canal' => 'plataforma', 'tipo' => $a['tipo'], 'instancia' => $a['plat_instancia'], 'token' => $a['plat_token']];
    }
    return null;
}

// ---------- HTTP ----------
function wa_http($metodo, $url, $corpo = null, $cabecalhos = [], $timeout = 12) {
    $ch = curl_init($url);
    $h = array_merge(['Accept: application/json'], $cabecalhos);
    if ($corpo !== null) $h[] = 'Content-Type: application/json';
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_HTTPHEADER => $h,
    ]);
    if ($corpo !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo, JSON_UNESCAPED_UNICODE));
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) return ['ok' => false, 'status' => 0, 'erro' => 'Sem conexão com o servidor do WhatsApp (' . $err . ')', 'dados' => null];
    $dados = json_decode($resp, true);
    $ok = $code >= 200 && $code < 300;
    $erro = '';
    if (!$ok) {
        $msg = is_array($dados) ? ($dados['response']['message'] ?? $dados['message'] ?? $dados['error'] ?? '') : '';
        if (is_array($msg)) $msg = implode(' ', array_map(fn($x) => is_string($x) ? $x : json_encode($x), $msg));
        $erro = "Erro $code" . ($msg ? ": $msg" : '');
    }
    return ['ok' => $ok, 'status' => $code, 'erro' => $erro, 'dados' => $dados, 'bruto' => $resp];
}

function wa_zapi_base($instancia, $token) {
    return 'https://api.z-api.io/instances/' . rawurlencode($instancia) . '/token/' . rawurlencode($token);
}

// Envia de fato (sem fila). Retorna ['ok'=>bool, 'erro'=>string]
function wa_enviar_agora($remetente, $numero, $texto, $extra = []) {
    $a = wa_api();
    $numero = wa_numero($numero);
    if ($numero === '') return ['ok' => false, 'erro' => 'Número de WhatsApp inválido.'];
    switch ($remetente['tipo']) {
        case 'evolution':
            $r = wa_http('POST', $a['url'] . '/message/sendText/' . rawurlencode($remetente['instancia']), ['number' => $numero, 'text' => $texto],
                ['apikey: ' . ($a['chave'] ?: $remetente['token'])]); // a chave global vale para todas as instâncias
            break;
        case 'zapi':
            $r = wa_http('POST', wa_zapi_base($remetente['instancia'], $remetente['token']) . '/send-text', ['phone' => $numero, 'message' => $texto],
                $a['chave'] !== '' ? ['Client-Token: ' . $a['chave']] : []);
            break;
        case 'webhook':
            $r = wa_http('POST', $a['url'], ['numero' => $numero, 'texto' => $texto, 'canal' => $remetente['canal'], 'instancia' => $remetente['instancia']] + $extra,
                $a['chave'] !== '' ? ['X-GDS-Token: ' . $a['chave']] : []);
            break;
        default:
            return ['ok' => false, 'erro' => 'Integração de WhatsApp não configurada.'];
    }
    return ['ok' => $r['ok'], 'erro' => $r['ok'] ? '' : mb_substr($r['erro'], 0, 250)];
}

// ---------- Fila ----------
// Registra e tenta enviar na hora. Sem remetente, fica "manual" (botão no painel).
function wa_enfileirar($empresa, $telefone, $texto, $evento, $para = 'cliente', $refTipo = null, $refId = null) {
    global $pdo;
    $num = wa_numero($telefone);
    if ($num === '' || trim($texto) === '') return null;
    $rem = wa_remetente($empresa);
    try {
        $st = $pdo->prepare("INSERT INTO whatsapp_mensagens (empresa_id, telefone, texto, evento, para, ref_tipo, ref_id, status, canal) VALUES (?,?,?,?,?,?,?,?,?) RETURNING id");
        $st->execute([$empresa['id'], $num, $texto, $evento, $para, $refTipo, $refId, $rem ? 'pendente' : 'manual', $rem['canal'] ?? null]);
        $id = (int)$st->fetch()['id'];
    } catch (PDOException $e) {
        error_log('GDS WhatsApp (fila): ' . $e->getMessage());
        return null;
    }
    if ($rem) {
        $extra = ['empresa_id' => (int)$empresa['id'], 'evento' => $evento];
        if (!empty($GLOBALS['wa_adiar'])) $GLOBALS['wa_adiados'][] = [$id, $rem, $num, $texto, $extra];
        else wa_tentar_envio($id, $rem, $num, $texto, $extra);
    }
    return $id;
}

// Envia as mensagens DEPOIS de responder ao navegador (o visitante não espera o WhatsApp)
function wa_adiar() {
    if (!empty($GLOBALS['wa_adiar'])) return;
    $GLOBALS['wa_adiar'] = true;
    $GLOBALS['wa_adiados'] = [];
    register_shutdown_function(function () {
        $lista = $GLOBALS['wa_adiados'] ?? [];
        if (!$lista) return;
        ignore_user_abort(true);
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
        foreach ($lista as [$id, $rem, $num, $texto, $extra]) {
            try { wa_tentar_envio($id, $rem, $num, $texto, $extra); } catch (Throwable $e) { error_log('GDS WhatsApp: ' . $e->getMessage()); }
        }
    });
}

function wa_tentar_envio($id, $rem, $num, $texto, $extra = []) {
    global $pdo;
    $r = wa_enviar_agora($rem, $num, $texto, $extra);
    if ($r['ok']) {
        $pdo->prepare("UPDATE whatsapp_mensagens SET status='enviado', enviado_em=NOW(), tentativas=tentativas+1, erro=NULL WHERE id=?")->execute([$id]);
    } else {
        // 3 tentativas: na hora e mais duas pela rotina automática
        $pdo->prepare("UPDATE whatsapp_mensagens SET tentativas=tentativas+1, erro=?, status=CASE WHEN tentativas+1 >= 3 THEN 'erro' ELSE 'pendente' END WHERE id=?")->execute([$r['erro'], $id]);
    }
    return $r;
}

// Notifica o CLIENTE num evento (respeita o liga/desliga e o texto da empresa)
function wa_notificar_cliente($empresa, $evento, $telefone, $vars, $refTipo = null, $refId = null) {
    $cfg = wa_config_empresa($empresa)[$evento] ?? null;
    if (!$cfg || !$cfg['ativo']) return null;
    $vars += ['empresa' => $empresa['nome'], 'endereco' => $empresa['endereco'] ?? ''];
    return wa_enfileirar($empresa, $telefone, wa_aplicar_variaveis($cfg['texto'], $vars), $evento, 'cliente', $refTipo, $refId);
}

// Avisa a EMPRESA no WhatsApp dela (novo agendamento, pedido...)
function wa_notificar_empresa($empresa, $tipo, $vars, $refTipo = null, $refId = null) {
    if (isset($empresa['wa_avisar_empresa']) && $empresa['wa_avisar_empresa'] !== null && !pg_bool($empresa['wa_avisar_empresa'])) return null;
    if (empty($empresa['whatsapp'])) return null;
    $rem = wa_remetente($empresa);
    if (!$rem) return null; // sem integração não faz sentido gerar botão manual para a própria empresa
    // Número próprio conectado = mesmo número da empresa: mensagem "para si mesmo" funciona no WhatsApp
    return wa_enfileirar($empresa, $empresa['whatsapp'], wa_texto_aviso_empresa($tipo, $vars), 'aviso_' . $tipo, 'empresa', $refTipo, $refId);
}

// Depois de mudar um status no painel: diz se o cliente foi avisado sozinho
// ou deixa um botão "Enviar pelo WhatsApp" com o texto pronto (sem integração)
function wa_resultado_para_tela($msgId) {
    global $pdo;
    if (!$msgId) return '';
    $st = $pdo->prepare("SELECT telefone, texto, status FROM whatsapp_mensagens WHERE id=?");
    $st->execute([$msgId]);
    $m = $st->fetch();
    if (!$m) return '';
    if ($m['status'] === 'manual') {
        $_SESSION['wa_manual'] = ['link' => wa_link_manual($m['telefone'], $m['texto']), 'texto' => $m['texto']];
        return '';
    }
    return ' Cliente avisado no WhatsApp.';
}

// Reenvia pendentes (chamado pela rotina automática)
function wa_processar_fila($limite = 20) {
    global $pdo;
    $st = $pdo->query("SELECT e.*, m.id AS msg_id, m.telefone AS msg_telefone, m.texto AS msg_texto FROM whatsapp_mensagens m JOIN empresas e ON e.id = m.empresa_id
        WHERE m.status='pendente' AND m.tentativas < 3 AND m.criado_em > NOW() - INTERVAL '1 day' ORDER BY m.id LIMIT " . (int)$limite);
    $n = 0;
    foreach ($st->fetchAll() as $m) {
        $rem = wa_remetente($m);
        if (!$rem) { $pdo->prepare("UPDATE whatsapp_mensagens SET status='manual' WHERE id=?")->execute([$m['msg_id']]); continue; }
        wa_tentar_envio($m['msg_id'], $rem, $m['msg_telefone'], $m['msg_texto']);
        $n++;
    }
    return $n;
}

// ---------- Rotina automática (lembretes + reenvios) ----------
// Roda pelo cron (cron.php) e também "de carona" nos acessos ao painel, no máximo a cada 5 minutos.
function wa_rotina($forcar = false) {
    global $pdo;
    if (!$forcar) {
        $ultima = (int)get_config('wa_rotina_ultima', '0');
        if (time() - $ultima < 300) return ['pulou' => true];
    }
    set_config('wa_rotina_ultima', (string)time());
    $res = ['lembretes' => 0, 'reenvios' => 0];
    try {
        $res['reenvios'] = wa_processar_fila();
        // Lembretes: agendamentos que começam dentro de N horas (padrão 24) e ainda não foram lembrados
        $st = $pdo->query("SELECT a.id, a.data_hora, a.token, c.nome AS cliente_nome, c.telefone, s.nome AS servico_nome, a.empresa_id
            FROM agendamentos a JOIN empresas e ON e.id = a.empresa_id
            LEFT JOIN clientes c ON c.id = a.cliente_id LEFT JOIN servicos s ON s.id = a.servico_id
            WHERE a.status IN ('agendado','confirmado') AND a.lembrete_enviado_em IS NULL
              AND a.data_hora > NOW() + INTERVAL '30 minutes'
              AND a.data_hora <= NOW() + make_interval(hours => GREATEST(1, COALESCE(e.wa_lembrete_horas, 24)))
              AND a.criado_em < NOW() - INTERVAL '30 minutes'
            ORDER BY a.data_hora LIMIT 30");
        $empresas = [];
        foreach ($st->fetchAll() as $a) {
            $pdo->prepare("UPDATE agendamentos SET lembrete_enviado_em=NOW() WHERE id=?")->execute([$a['id']]);
            if (empty($a['telefone'])) continue;
            $empresas[$a['empresa_id']] ??= wa_carregar_empresa($a['empresa_id']);
            if (!$empresas[$a['empresa_id']]) continue;
            if (wa_notificar_cliente($empresas[$a['empresa_id']], 'agendamento_lembrete', $a['telefone'], wa_vars_agendamento($a, $empresas[$a['empresa_id']]), 'agendamento', $a['id'])) $res['lembretes']++;
        }
    } catch (PDOException $e) {
        error_log('GDS WhatsApp (rotina): ' . $e->getMessage());
    }
    return $res;
}

// Roda a rotina depois de responder a página (sem atrasar ninguém), no máximo a cada 5 min
function wa_rotina_em_segundo_plano() {
    static $registrada = false;
    if ($registrada) return;
    $registrada = true;
    register_shutdown_function(function () {
        try {
            if (time() - (int)get_config('wa_rotina_ultima', '0') < 300) return;
            if (!wa_api_configurada()) return;
            ignore_user_abort(true);
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
            elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
            wa_rotina();
        } catch (Throwable $e) {
            error_log('GDS rotina: ' . $e->getMessage());
        }
    });
}

function wa_carregar_empresa($id) {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

// Endereço público (domínio próprio ativo, senão a plataforma)
function wa_url_publica($empresa, $caminho = '') {
    if (!empty($empresa['dominio_personalizado']) && ($empresa['dominio_status'] ?? '') === 'ativo') return 'https://' . $empresa['dominio_personalizado'] . $caminho;
    $host = preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['HTTP_HOST'] ?? '');
    $base = get_config('plataforma_url', '') ?: (($host ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https://' : 'http://') . $host : ''));
    return rtrim($base, '/') . ($caminho !== '' ? $caminho : '/' . $empresa['slug']);
}

// Variáveis de um agendamento (linha com data_hora, servico_nome, cliente_nome, token, profissional_nome?)
function wa_vars_agendamento($a, $empresa) {
    $dias = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
    $ts = strtotime($a['data_hora']);
    return [
        'nome' => explode(' ', trim($a['cliente_nome'] ?? 'Cliente'))[0],
        'telefone' => $a['telefone'] ?? '',
        'servico' => $a['servico_nome'] ?? 'Atendimento',
        'profissional' => !empty($a['profissional_nome']) ? ' com ' . $a['profissional_nome'] : '',
        'data' => $dias[(int)date('w', $ts)] . ', ' . date('d/m', $ts),
        'hora' => date('H:i', $ts),
        'valor' => isset($a['valor']) && (float)$a['valor'] > 0 ? '💰 R$ ' . number_format((float)$a['valor'], 2, ',', '.') : '',
        'link' => !empty($a['token']) ? wa_url_publica($empresa, '/site/agendamento.php?t=' . $a['token']) : wa_url_publica($empresa),
    ];
}

function wa_dinheiro($v) {
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

// Variáveis de um pedido/orçamento (linha de pedidos + cliente_nome/telefone + servico_nome)
function wa_vars_pedido($p, $empresa) {
    $itens = json_decode((string)($p['itens'] ?? ''), true) ?: [];
    $linhas = array_map(fn($i) => (int)$i['qtd'] . 'x ' . $i['nome'] . ' — ' . wa_dinheiro($i['preco'] * $i['qtd']), $itens);
    $entrega = ($p['tipo_entrega'] ?? '') === 'retirada' ? '🏪 Retirada no local'
             : (($p['tipo_entrega'] ?? '') === 'entrega' ? '🛵 Entrega em: ' . ($p['endereco_entrega'] ?? '') . ((float)($p['taxa_entrega'] ?? 0) > 0 ? ' (taxa ' . wa_dinheiro($p['taxa_entrega']) . ')' : '') : '');
    $servico = $p['servico_nome'] ?? '';
    if ($servico === '' && !empty($p['descricao'])) $servico = strtok($p['descricao'], "\n");
    return [
        'nome' => explode(' ', trim($p['cliente_nome'] ?? 'Cliente'))[0],
        'telefone' => $p['telefone'] ?? '',
        'codigo' => (string)$p['id'],
        'servico' => $servico,
        'valor' => (float)($p['valor'] ?? 0) > 0 ? wa_dinheiro($p['valor']) : 'a combinar',
        'total' => wa_dinheiro($p['valor'] ?? 0),
        'itens' => implode("\n", $linhas),
        'entrega' => $entrega,
        'pagamento' => ($p['forma_pagamento'] ?? '') . (!empty($p['troco_para']) ? ', troco para ' . wa_dinheiro($p['troco_para']) : ''),
        'mensagem' => trim((string)($p['resposta'] ?? '')),
        'link' => !empty($p['token']) ? wa_url_publica($empresa, '/site/pedido.php?t=' . $p['token']) : wa_url_publica($empresa),
    ];
}

// Busca o pedido completo e dispara a mensagem do novo status
function wa_avisar_status_pedido($empresa, $pedidoId, $status) {
    global $pdo;
    $st = $pdo->prepare("SELECT p.*, c.nome AS cliente_nome, c.telefone, sv.nome AS servico_nome FROM pedidos p
        LEFT JOIN clientes c ON c.id = p.cliente_id LEFT JOIN servicos sv ON sv.id = p.servico_id WHERE p.id=? AND p.empresa_id=?");
    $st->execute([$pedidoId, $empresa['id']]);
    $p = $st->fetch();
    if (!$p || empty($p['telefone'])) return null;
    $evento = [
        'orcado' => 'orcamento_enviado', 'aprovado' => 'orcamento_aprovado',
        'em_andamento' => ($p['tipo'] ?? '') === 'delivery' ? 'pedido_confirmado' : null,
        'pronto' => 'pedido_pronto', 'saiu_entrega' => 'pedido_saiu',
        'concluido' => ($p['tipo'] ?? '') === 'delivery' ? 'pedido_concluido' : null,
        'cancelado' => 'pedido_cancelado',
    ][$status] ?? null;
    if (!$evento) return null;
    return wa_notificar_cliente($empresa, $evento, $p['telefone'], wa_vars_pedido($p, $empresa), 'pedido', $p['id']);
}

function wa_avisar_status_agendamento($empresa, $agId, $status) {
    global $pdo;
    $evento = ['confirmado' => 'agendamento_confirmado', 'cancelado' => 'agendamento_cancelado', 'concluido' => 'agendamento_concluido'][$status] ?? null;
    if (!$evento) return null;
    $st = $pdo->prepare("SELECT a.*, c.nome AS cliente_nome, c.telefone, s.nome AS servico_nome, pr.nome AS profissional_nome FROM agendamentos a
        LEFT JOIN clientes c ON c.id = a.cliente_id LEFT JOIN servicos s ON s.id = a.servico_id LEFT JOIN profissionais pr ON pr.id = a.profissional_id
        WHERE a.id=? AND a.empresa_id=?");
    $st->execute([$agId, $empresa['id']]);
    $a = $st->fetch();
    if (!$a || empty($a['telefone'])) return null;
    $v = wa_vars_agendamento($a, $empresa);
    if ($evento === 'agendamento_concluido') $v['link'] = wa_url_publica($empresa) . '#avaliacoes';
    return wa_notificar_cliente($empresa, $evento, $a['telefone'], $v, 'agendamento', $a['id']);
}

// ============================================================
// CONEXÃO POR QR CODE (Evolution API) / Z-API
// ============================================================
function wa_conexao_criar($empresa) {
    global $pdo;
    $a = wa_api();
    if ($a['tipo'] === 'zapi') {
        // Na Z-API cada número é uma instância contratada: a empresa informa ID e token
        if (empty($empresa['wa_instancia']) || empty($empresa['wa_instancia_token'])) return ['ok' => false, 'erro' => 'Informe o ID e o token da sua instância Z-API.'];
        return ['ok' => true];
    }
    if ($a['tipo'] !== 'evolution') return ['ok' => false, 'erro' => 'A conexão por QR code não está disponível nesta plataforma.'];
    $nome = $empresa['wa_instancia'] ?: 'gds-' . (int)$empresa['id'] . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    if (empty($empresa['wa_instancia'])) {
        $r = wa_http('POST', $a['url'] . '/instance/create', ['instanceName' => $nome, 'qrcode' => true, 'integration' => 'WHATSAPP-BAILEYS'], ['apikey: ' . $a['chave']], 20);
        if (!$r['ok'] && $r['status'] !== 403 && stripos((string)$r['erro'], 'already') === false) return ['ok' => false, 'erro' => 'Não foi possível criar a conexão: ' . $r['erro']];
        $hash = $r['dados']['hash'] ?? '';
        $token = is_array($hash) ? ($hash['apikey'] ?? '') : (string)$hash;
        $pdo->prepare("UPDATE empresas SET wa_instancia=?, wa_instancia_token=?, wa_status='aguardando' WHERE id=?")->execute([$nome, $token ?: null, $empresa['id']]);
        $qr = $r['dados']['qrcode']['base64'] ?? null;
        if ($qr) return ['ok' => true, 'qr' => $qr, 'codigo' => $r['dados']['qrcode']['pairingCode'] ?? null];
    }
    return wa_conexao_qr(array_merge($empresa, ['wa_instancia' => $nome]));
}

function wa_conexao_qr($empresa, $numeroPareamento = '') {
    $a = wa_api();
    if ($a['tipo'] === 'zapi') {
        $r = wa_http('GET', wa_zapi_base($empresa['wa_instancia'], $empresa['wa_instancia_token']) . '/qr-code/image', null, $a['chave'] !== '' ? ['Client-Token: ' . $a['chave']] : []);
        if (!$r['ok']) return ['ok' => false, 'erro' => $r['erro']];
        $v = $r['dados']['value'] ?? '';
        return ['ok' => true, 'qr' => str_starts_with($v, 'data:') ? $v : ($v ? 'data:image/png;base64,' . $v : null)];
    }
    $url = $a['url'] . '/instance/connect/' . rawurlencode($empresa['wa_instancia']) . ($numeroPareamento ? '?number=' . wa_numero($numeroPareamento) : '');
    $r = wa_http('GET', $url, null, ['apikey: ' . $a['chave']]);
    if (!$r['ok']) return ['ok' => false, 'erro' => $r['erro']];
    $d = $r['dados'] ?: [];
    $qr = $d['base64'] ?? ($d['qrcode']['base64'] ?? null);
    return ['ok' => true, 'qr' => $qr, 'codigo' => $d['pairingCode'] ?? null, 'conectado' => ($d['instance']['state'] ?? '') === 'open'];
}

// Pergunta ao servidor se o número já está conectado (e atualiza o banco)
function wa_conexao_estado($empresa) {
    global $pdo;
    $a = wa_api();
    if (empty($empresa['wa_instancia'])) return ['ok' => true, 'conectado' => false];
    if ($a['tipo'] === 'zapi') {
        $r = wa_http('GET', wa_zapi_base($empresa['wa_instancia'], $empresa['wa_instancia_token']) . '/status', null, $a['chave'] !== '' ? ['Client-Token: ' . $a['chave']] : []);
        $conectado = $r['ok'] && !empty($r['dados']['connected']);
        $numero = '';
    } else {
        $r = wa_http('GET', $a['url'] . '/instance/connectionState/' . rawurlencode($empresa['wa_instancia']), null, ['apikey: ' . $a['chave']]);
        $conectado = $r['ok'] && (($r['dados']['instance']['state'] ?? $r['dados']['state'] ?? '') === 'open');
        $numero = '';
        if ($conectado) {
            $info = wa_http('GET', $a['url'] . '/instance/fetchInstances?instanceName=' . rawurlencode($empresa['wa_instancia']), null, ['apikey: ' . $a['chave']]);
            $lista = $info['dados'] ?? [];
            $item = is_array($lista) && isset($lista[0]) ? $lista[0] : $lista;
            $jid = $item['ownerJid'] ?? ($item['instance']['owner'] ?? ($item['owner'] ?? ''));
            $numero = preg_replace('/\D/', '', explode('@', (string)$jid)[0]);
        }
    }
    if (!$r['ok']) return ['ok' => false, 'erro' => $r['erro'], 'conectado' => false];
    $pdo->prepare("UPDATE empresas SET wa_status=?, wa_numero_conectado=COALESCE(NULLIF(?, ''), wa_numero_conectado) WHERE id=?")
        ->execute([$conectado ? 'conectado' : 'aguardando', $numero, $empresa['id']]);
    return ['ok' => true, 'conectado' => $conectado, 'numero' => $numero];
}

function wa_conexao_desconectar($empresa) {
    global $pdo;
    $a = wa_api();
    if (!empty($empresa['wa_instancia'])) {
        if ($a['tipo'] === 'evolution') {
            wa_http('DELETE', $a['url'] . '/instance/logout/' . rawurlencode($empresa['wa_instancia']), null, ['apikey: ' . $a['chave']]);
            wa_http('DELETE', $a['url'] . '/instance/delete/' . rawurlencode($empresa['wa_instancia']), null, ['apikey: ' . $a['chave']]);
            $pdo->prepare("UPDATE empresas SET wa_instancia=NULL, wa_instancia_token=NULL WHERE id=?")->execute([$empresa['id']]);
        } elseif ($a['tipo'] === 'zapi') {
            wa_http('GET', wa_zapi_base($empresa['wa_instancia'], $empresa['wa_instancia_token']) . '/disconnect', null, $a['chave'] !== '' ? ['Client-Token: ' . $a['chave']] : []);
        }
    }
    $pdo->prepare("UPDATE empresas SET wa_status='desconectado', wa_numero_conectado=NULL WHERE id=?")->execute([$empresa['id']]);
    return ['ok' => true];
}
