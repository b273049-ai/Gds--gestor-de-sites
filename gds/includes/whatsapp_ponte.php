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

    // 1) Estado de cada número no conector
    $forcarSaida = [];
    foreach (array_slice((array)($dados['estados'] ?? []), 0, 200) as $e) {
        $inst = (string)($e['instancia'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_-]{1,60}$/', $inst)) continue;
        $estado = in_array($e['estado'] ?? '', ['open', 'connecting', 'close'], true) ? $e['estado'] : 'close';
        $numero = preg_replace('/\D/', '', (string)($e['numero'] ?? ''));
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
        if ($estado === 'open' || $linha['estado'] === 'open') {
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
        } else {
            $pdo->prepare("UPDATE whatsapp_mensagens SET tentativas=tentativas+1, erro=?, ponte_em=NULL, status=CASE WHEN tentativas+1 >= 3 THEN 'erro' ELSE 'pendente' END WHERE id=? AND status='enviando'")
                ->execute([mb_substr((string)($r['erro'] ?? 'Falha no conector'), 0, 250), $id]);
        }
    }

    // 3) O que o conector deve fazer com cada número
    $pdo->exec("DELETE FROM wa_ponte WHERE pedido='desconectar' AND pedido_em < NOW() - INTERVAL '2 minutes'");
    $pdo->exec("UPDATE wa_ponte SET pedido=NULL WHERE pedido='conectar' AND pedido_em < NOW() - INTERVAL '" . WA_PONTE_PEDIDO_MIN . " minutes' AND estado <> 'open'");
    $instancias = [];
    $abertas = [];
    foreach ($pdo->query("SELECT * FROM wa_ponte")->fetchAll() as $l) {
        $acao = $l['pedido'] === 'desconectar' ? 'desconectar' : ($l['pedido'] === 'conectar' ? 'conectar' : ($l['estado'] === 'open' ? 'manter' : 'parar'));
        $instancias[] = ['nome' => $l['instancia'], 'acao' => $acao, 'numero_pareamento' => $l['numero_pareamento']];
        if ($l['estado'] === 'open' && $acao !== 'desconectar') $abertas[$l['instancia']] = true;
    }
    foreach ($forcarSaida as $inst) $instancias[] = ['nome' => $inst, 'acao' => 'desconectar', 'numero_pareamento' => null];

    // 4) Mensagens para enviar (só pelos números conectados)
    $pdo->exec("UPDATE whatsapp_mensagens SET status='pendente', ponte_em=NULL WHERE status='enviando' AND ponte_em < NOW() - INTERVAL '3 minutes'");
    $mensagens = [];
    if ($abertas) {
        $st = $pdo->query("SELECT m.id, m.empresa_id, m.telefone, m.texto, m.canal FROM whatsapp_mensagens m
            WHERE m.status='pendente' AND m.tentativas < 3 AND m.criado_em > NOW() - INTERVAL '1 day' ORDER BY m.id LIMIT 40");
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

    return ['ok' => true, 'instancias' => $instancias, 'mensagens' => $mensagens, 'intervalo' => $mensagens ? 2 : 4];
}
