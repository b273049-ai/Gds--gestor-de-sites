<?php
// ============================================================
// WHATSAPP "PONTE" — o Conector GDS roda num celular Android (Termux) ou num
// computador e BUSCA o que fazer no site a cada poucos segundos:
//   conector → POST /api/whatsapp_ponte.php  {estados: [...], resultados: [...]}
//   site     → {instancias: [...], mensagens: [...]}
// Assim funciona em hospedagem compartilhada (só PHP): o site nunca precisa
// chamar o conector, então não precisa de servidor, domínio nem túnel.
// ============================================================

const WA_PONTE_ONLINE_SEG = 45;     // sem notícia do conector por mais que isso = desligado
const WA_PONTE_PEDIDO_MIN = 4;      // um pedido de QR vale por 4 minutos

function wa_ponte_online() {
    return time() - (int)get_config('wa_ponte_visto', '0') < WA_PONTE_ONLINE_SEG;
}

function wa_ponte_ler($instancia) {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM wa_ponte WHERE instancia=?");
    $st->execute([$instancia]);
    return $st->fetch() ?: null;
}

// Pede ao conector para ligar (mostrar QR) a instância; com número, pede código de pareamento
function wa_ponte_pedir($instancia, $numero = '') {
    global $pdo;
    $numero = $numero !== '' ? wa_numero($numero) : null;
    $pdo->prepare("INSERT INTO wa_ponte (instancia, pedido, numero_pareamento, pedido_em) VALUES (?, 'conectar', ?, NOW())
        ON CONFLICT (instancia) DO UPDATE SET pedido = CASE WHEN wa_ponte.estado = 'open' THEN NULL ELSE 'conectar' END,
            numero_pareamento = EXCLUDED.numero_pareamento, pedido_em = NOW(),
            pareamento = CASE WHEN wa_ponte.numero_pareamento IS DISTINCT FROM EXCLUDED.numero_pareamento THEN NULL ELSE wa_ponte.pareamento END")
        ->execute([$instancia, $numero]);
}

// Resposta no formato que as telas de conexão já entendem
function wa_ponte_situacao($instancia) {
    $r = wa_ponte_ler($instancia);
    if (!wa_ponte_online()) {
        return ['ok' => false, 'conectado' => false, 'erro' => 'O conector está desligado. Ligue o celular ou o computador onde ele roda (veja Admin → WhatsApp).'];
    }
    $conectado = $r && $r['estado'] === 'open';
    // QR só vale se for recente (o WhatsApp troca o QR a cada ~20s)
    $qr = $r && !$conectado && $r['qr'] && strtotime($r['atualizado_em']) > time() - 60 ? $r['qr'] : null;
    return ['ok' => true, 'conectado' => $conectado, 'qr' => $qr, 'codigo' => $r['pareamento'] ?? null, 'numero' => $r['numero'] ?? '',
            'aguardando' => !$conectado && !$qr];
}

function wa_ponte_desconectar($instancia) {
    global $pdo;
    $pdo->prepare("UPDATE wa_ponte SET pedido='desconectar', pedido_em=NOW() WHERE instancia=?")->execute([$instancia]);
}

// Mensagem de teste da plataforma: entra na fila, o conector envia em segundos
function wa_ponte_enfileirar_teste($numero, $texto) {
    global $pdo;
    $st = $pdo->prepare("INSERT INTO whatsapp_mensagens (empresa_id, telefone, texto, evento, para, status, canal) VALUES (NULL, ?, ?, 'teste', 'empresa', 'pendente', 'plataforma') RETURNING id");
    $st->execute([wa_numero($numero), $texto]);
    return (int)$st->fetchColumn();
}

// Uma "volta" do conector: recebe o estado dele e devolve o que fazer
function wa_ponte_sincronizar($dados) {
    global $pdo;
    $a = wa_api();
    if (time() - (int)get_config('wa_ponte_visto', '0') >= 10) set_config('wa_ponte_visto', (string)time());
    // Conector novo (v18) repassa as respostas que chegam: guarda quando foi visto para a tela avisar quem precisa atualizar
    if (array_key_exists('recebidas', $dados) && time() - (int)get_config('wa_ponte_ouve', '0') >= 60) set_config('wa_ponte_ouve', (string)time());

    // 1) Estado de cada número no conector
    $forcarSaida = [];
    $logados = [];   // números com login salvo no conector: se caírem, religam sozinhos (não manda "parar")
    foreach (array_slice((array)($dados['estados'] ?? []), 0, 200) as $e) {
        $inst = (string)($e['instancia'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_-]{1,60}$/', $inst)) continue;
        $estado = in_array($e['estado'] ?? '', ['open', 'connecting', 'close'], true) ? $e['estado'] : 'close';
        $numero = preg_replace('/\D/', '', (string)($e['numero'] ?? ''));
        $logado = !empty($e['logado']);
        if ($logado) $logados[$inst] = true;
        if ($logado && $estado === 'close') $estado = 'connecting'; // caiu e está religando
        $linha = wa_ponte_ler($inst);
        if (!$linha) { if ($estado !== 'close') $forcarSaida[] = $inst; continue; }  // número que ninguém pediu
        if ($linha['pedido'] === 'desconectar' && $estado === 'close') {
            $pdo->prepare("DELETE FROM wa_ponte WHERE instancia=?")->execute([$inst]);
            continue;
        }
        $qr = is_string($e['qr'] ?? null) && str_starts_with($e['qr'], 'data:image/png;base64,') && strlen($e['qr']) < 200000 ? $e['qr'] : null;
        $pdo->prepare("UPDATE wa_ponte SET estado=?, qr=?, pareamento=?, numero=COALESCE(NULLIF(?, ''), numero), atualizado_em=NOW(),
                pedido = CASE WHEN ? = 'open' AND pedido = 'conectar' THEN NULL ELSE pedido END WHERE instancia=?")
            ->execute([$estado, $estado === 'open' ? null : $qr, mb_substr((string)($e['pareamento'] ?? ''), 0, 20) ?: null, $numero, $estado, $inst]);
        // Reflete no cadastro da empresa (painel "Meu próprio WhatsApp")
        // (queda rápida de quem tem login não muda o status da empresa: ele religa sozinho)
        if ($estado === 'open' || ($linha['estado'] === 'open' && !$logado)) {
            $pdo->prepare("UPDATE empresas SET wa_status=?, wa_numero_conectado=COALESCE(NULLIF(?, ''), wa_numero_conectado) WHERE wa_instancia=?")
                ->execute([$estado === 'open' ? 'conectado' : 'aguardando', $numero, $inst]);
        }
    }

    // 2) Resultado dos envios
    foreach (array_slice((array)($dados['resultados'] ?? []), 0, 200) as $r) {
        $id = (int)($r['id'] ?? 0);
        if ($id <= 0) continue;
        if (!empty($r['ok'])) {
            $pdo->prepare("UPDATE whatsapp_mensagens SET status='enviado', enviado_em=NOW(), tentativas=tentativas+1, erro=NULL WHERE id=? AND status='enviando'")->execute([$id]);
        } elseif (!empty($r['religando'])) {
            // O celular estava religando: volta para a fila sem gastar tentativa (sai assim que conectar)
            $pdo->prepare("UPDATE whatsapp_mensagens SET erro=?, ponte_em=NULL, status='pendente' WHERE id=? AND status='enviando'")
                ->execute(['Aguardando o WhatsApp religar', $id]);
            continue;
        } else {
            $pdo->prepare("UPDATE whatsapp_mensagens SET tentativas=tentativas+1, erro=?, ponte_em=NULL, status=CASE WHEN tentativas+1 >= 3 THEN 'erro' ELSE 'pendente' END WHERE id=? AND status='enviando'")
                ->execute([mb_substr((string)($r['erro'] ?? 'Falha no conector'), 0, 250), $id]);
        }
        // Mensagem da prospecção: registra na conversa e avança a cadência
        if (is_file(__DIR__ . '/prospeccao_piloto.php')) {
            require_once __DIR__ . '/prospeccao_piloto.php';
            try { prosp_envio_resultado($id, !empty($r['ok']), (string)($r['erro'] ?? '')); } catch (Throwable $e) { error_log('GDS prospecção envio: ' . $e->getMessage()); }
        }
    }

    // 3) O que o conector deve fazer com cada número
    $pdo->exec("DELETE FROM wa_ponte WHERE pedido='desconectar' AND pedido_em < NOW() - INTERVAL '2 minutes'");
    $pdo->exec("UPDATE wa_ponte SET pedido=NULL WHERE pedido='conectar' AND pedido_em < NOW() - INTERVAL '" . WA_PONTE_PEDIDO_MIN . " minutes' AND estado <> 'open'");
    $instancias = [];
    $abertas = [];
    foreach ($pdo->query("SELECT * FROM wa_ponte")->fetchAll() as $l) {
        $acao = $l['pedido'] === 'desconectar' ? 'desconectar' : ($l['pedido'] === 'conectar' ? 'conectar' : ($l['estado'] === 'open' || !empty($logados[$l['instancia']]) ? 'manter' : 'parar'));
        $instancias[] = ['nome' => $l['instancia'], 'acao' => $acao, 'numero_pareamento' => $l['numero_pareamento']];
        if ($l['estado'] === 'open' && $acao !== 'desconectar') $abertas[$l['instancia']] = true;
    }
    foreach ($forcarSaida as $inst) $instancias[] = ['nome' => $inst, 'acao' => 'desconectar', 'numero_pareamento' => null];

    // 4) Mensagens para enviar (só pelos números conectados)
    $pdo->exec("UPDATE whatsapp_mensagens SET status='pendente', ponte_em=NULL WHERE status='enviando' AND ponte_em < NOW() - INTERVAL '3 minutes'");
    $mensagens = [];
    if ($abertas) {
        $st = $pdo->query("SELECT m.id, m.empresa_id, m.telefone, m.texto, m.canal FROM whatsapp_mensagens m
            WHERE m.status='pendente' AND m.tentativas < 3 AND m.criado_em > NOW() - INTERVAL '1 day' AND (m.enviar_apos IS NULL OR m.enviar_apos <= NOW()) ORDER BY m.id LIMIT 40");
        $empresas = [];
        foreach ($st->fetchAll() as $m) {
            if ($m['empresa_id']) {
                $empresas[$m['empresa_id']] ??= wa_carregar_empresa($m['empresa_id']);
                $rem = $empresas[$m['empresa_id']] ? wa_remetente($empresas[$m['empresa_id']]) : null;
                $inst = $rem && $rem['tipo'] === 'ponte' ? $rem['instancia'] : null;
            } else {
                $inst = $a['plat_instancia']; // teste do admin
            }
            if (!$inst || empty($abertas[$inst])) continue;
            $mensagens[] = ['id' => (int)$m['id'], 'instancia' => $inst, 'numero' => $m['telefone'], 'texto' => $m['texto']];
            if (count($mensagens) >= 20) break;
        }
        if ($mensagens) {
            $ids = implode(',', array_map(fn($m) => (int)$m['id'], $mensagens));
            $pdo->exec("UPDATE whatsapp_mensagens SET status='enviando', ponte_em=NOW() WHERE id IN ($ids)");
        }
    }

    // De carona: lembretes e rotinas (no máximo a cada 5 min) — dispensa o Cron Job
    try { wa_rotina(); } catch (Throwable $e) { error_log('GDS ponte rotina: ' . $e->getMessage()); }

    // Prospecção: respostas que chegaram no número da plataforma + próxima mensagem do piloto automático
    $ouvir = [];
    $leadsResponderam = [];
    if (is_file(__DIR__ . '/prospeccao_piloto.php')) {
        require_once __DIR__ . '/prospeccao_piloto.php';
        if (get_config('prosp_ouvir', '1') === '1' && $a['plat_instancia'] !== '') $ouvir[] = $a['plat_instancia'];
        $porInst = [];
        foreach (array_slice((array)($dados['recebidas'] ?? []), 0, 100) as $m) if (is_array($m)) $porInst[(string)($m['instancia'] ?? '')][] = $m;
        foreach ($porInst as $inst => $lista) {
            try { $leadsResponderam = array_merge($leadsResponderam, prosp_processar_recebidas($lista, $inst)); } catch (Throwable $e) { error_log('GDS respostas: ' . $e->getMessage()); }
        }
        try { prosp_piloto_rotina(); } catch (Throwable $e) { error_log('GDS piloto: ' . $e->getMessage()); }
    }

    // 5) Prospecção: leads para o conector verificar (WhatsApp comercial, busca, Instagram)
    $verificar = [];
    $resultadosVerif = array_slice(array_filter((array)($dados['verificacoes'] ?? []), fn($v) => is_array($v) && (int)($v['id'] ?? 0) > 0), 0, 10);
    if (is_file(__DIR__ . '/prospeccao.php') && get_config('prosp_verificar', '1') === '1') {
        require_once __DIR__ . '/prospeccao.php';
        // Só com o WhatsApp da plataforma conectado (é por ele que o perfil é consultado)
        if (!empty($abertas[$a['plat_instancia']])) {
            $verificar = prosp_verificacoes_para_ponte(2);
            foreach ($verificar as &$v) $v['instancia'] = $a['plat_instancia'];
            unset($v);
        }
    }
    // Marketing de carona: com post pendente, gera um (no máximo a cada 90 s) depois de responder ao conector
    $mktCarona = false;
    if (is_file(__DIR__ . '/marketing_ia.php') && time() - (int)get_config('mkt_carona', '0') >= 90) {
        try { $mktCarona = (bool)$pdo->query("SELECT 1 FROM marketing_itens WHERE status='pendente' LIMIT 1")->fetchColumn(); } catch (PDOException $e) { $mktCarona = false; }
        if (!$mktCarona) {
            try { require_once __DIR__ . '/marketing_ia.php'; $mktCarona = soc_tem_devido(); } catch (Throwable $e) { $mktCarona = false; }
        }
        if ($mktCarona) set_config('mkt_carona', (string)time());
    }
    // A conferência dos sites candidatos e a leitura das respostas pela IA são lentas: rodam depois de responder ao conector
    if (($resultadosVerif && is_file(__DIR__ . '/prospeccao.php')) || $leadsResponderam || $mktCarona) {
        register_shutdown_function(function () use ($resultadosVerif, $leadsResponderam, $mktCarona) {
            ignore_user_abort(true);
            @set_time_limit(180);
            if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
            elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
            require_once __DIR__ . '/prospeccao.php';
            foreach ($resultadosVerif as $v) {
                try { prosp_concluir_verificacao((int)$v['id'], $v); } catch (Throwable $e) { error_log('GDS verificação: ' . $e->getMessage()); }
            }
            foreach ($leadsResponderam as $lid) {
                try { prosp_analisar_resposta((int)$lid); } catch (Throwable $e) { error_log('GDS leitura da resposta: ' . $e->getMessage()); }
            }
            if ($mktCarona) {
                try { require_once __DIR__ . '/marketing_ia.php'; mkt_plano_processar(); soc_rotina(); } catch (Throwable $e) { error_log('GDS marketing carona: ' . $e->getMessage()); }
            }
        });
    }

    return ['ok' => true, 'instancias' => $instancias, 'mensagens' => $mensagens, 'verificar' => $verificar, 'ouvir' => $ouvir, 'intervalo' => $mensagens || $verificar ? 2 : 4];
}
