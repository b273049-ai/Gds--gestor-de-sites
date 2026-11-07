<?php
// ============================================================
// WHATSAPP "PONTE" — o Conector GDS roda num celular Android (Termux) ou num
// computador e BUSCA o que fazer no site a cada poucos segundos:
//   conector → POST /api/whatsapp_ponte.php  {estados: [...], resultados: [...]}
//   site     → {instancias: [...], mensagens: [...]}
// Assim funciona em hospedagem compartilhada (só PHP): o site nunca precisa
// chamar o conector, então não precisa de servidor, domínio nem túnel.
// ============================================================

const WA_PONTE_ONLINE_SEG = 75;     // sem notícia do conector por mais que isso = desligado (45 s piscava à toa)
const WA_PONTE_SUMIU_SEG = 90;      // lacuna maior que isso entra no histórico como "o conector sumiu"
const WA_PONTE_PEDIDO_MIN = 4;      // um pedido de QR vale por 4 minutos
const WA_CONECTOR_VERSAO = '1.3.1'; // versão atual do whatsapp-conector (o painel avisa quem está atrás)

// ---------- Histórico da conexão (Admin → WhatsApp → Saúde da conexão) ----------
// Guardado em configuracoes (sem tabela nova): últimos 7 dias, no máximo 150 eventos.
function wa_ponte_eventos_ler() {
    $l = json_decode((string)get_config('wa_ponte_eventos', '[]'), true);
    return is_array($l) ? array_values(array_filter($l, 'is_array')) : [];
}

function wa_ponte_registrar(array $novos) {
    if (!$novos) return;
    $limite = time() - 7 * 86400;
    $l = array_values(array_filter(array_merge(wa_ponte_eventos_ler(), $novos), fn($e) => (int)($e['em'] ?? 0) >= $limite));
    usort($l, fn($a, $b) => (int)$a['em'] <=> (int)$b['em']);
    set_config('wa_ponte_eventos', json_encode(array_slice($l, -150), JSON_UNESCAPED_UNICODE));
}

// Evento que o conector conta (só os campos e tipos conhecidos)
function wa_ponte_evento_limpo($e) {
    $tipos = ['caiu', 'conectou', 'deslogado', 'substituido', 'bloqueado', 'morto', 'travado', 'reiniciou', 'site_falhou'];
    if (!is_array($e) || !in_array($e['tipo'] ?? '', $tipos, true)) return null;
    $em = (int)($e['em'] ?? 0);
    if ($em < time() - 7 * 86400 || $em > time() + 120) $em = time();
    $inst = (string)($e['inst'] ?? '');
    return ['em' => $em, 'tipo' => $e['tipo'], 'inst' => preg_match('/^[a-zA-Z0-9_-]{1,60}$/', $inst) ? $inst : '',
            'codigo' => (int)($e['codigo'] ?? 0) ?: null, 'seg' => max(0, min(30 * 86400, (int)($e['seg'] ?? 0))) ?: null,
            'n' => max(0, min(100000, (int)($e['n'] ?? 0))) ?: null, 'forcado' => !empty($e['forcado']) ?: null,
            'det' => mb_substr(preg_replace('/\s+/', ' ', (string)($e['det'] ?? '')), 0, 160) ?: null];
}

function wa_ponte_duracao($seg) {
    $seg = (int)$seg;
    if ($seg < 90) return $seg . ' s';
    if ($seg < 5400) return round($seg / 60) . ' min';
    if ($seg < 172800) return round($seg / 3600, 1) . ' h';
    return round($seg / 86400) . ' dias';
}

// O que cada evento quer dizer, em português, e o que fazer
// [título, explicação, o que fazer, gravidade: info|aviso|grave]
function wa_ponte_motivo($e) {
    $t = $e['tipo'] ?? ''; $c = (int)($e['codigo'] ?? 0);
    $termux = 'No celular: Configurações → Apps → Termux → Bateria → "Sem restrições". Android 14 ou mais novo: Opções do desenvolvedor → ative "Desativar restrições de processos filhos" e reinicie. Android 12L/13: veja o LEIA-ME do conector (comando adb).';
    switch ($t) {
        case 'sumiu': return ['O conector ficou sem falar com o site', 'O celular (ou computador) do conector ficou ' . wa_ponte_duracao($e['seg'] ?? 0) . ' sem avisar o site: sem internet, desligado, ou o Android fechou o Termux.', 'Deixe o aparelho no carregador e no Wi-Fi. ' . $termux, 'aviso'];
        case 'reiniciou': return !empty($e['forcado'])
            ? ['O conector foi fechado à força', 'O programa parou sem se despedir' . (!empty($e['seg']) ? ' e ficou ' . wa_ponte_duracao($e['seg']) . ' parado' : '') . ': quase sempre é o Android encerrando o Termux para economizar bateria (ou o aparelho desligou).', $termux, 'grave']
            : ['O conector foi reiniciado', 'O programa foi ligado de novo (atualização ou reinício do aparelho).', '', 'info'];
        case 'deslogado': return ['O WhatsApp desconectou o aparelho', 'O WhatsApp confirmou duas vezes que este aparelho saiu (removido em "Aparelhos conectados", celular do chip sem internet por muitos dias, ou o WhatsApp desconfiou do uso).', 'Gere o QR code de novo e leia com o celular do chip. Se acontecer sempre, diminua o volume de mensagens da prospecção.', 'grave'];
        case 'substituido': return ['Outro conector abriu o mesmo número', 'Dois conectores (ou dois Termux) com o mesmo login ficam se derrubando.', 'Deixe só um conector ligado: feche o outro celular/computador ou a outra janela do Termux.', 'grave'];
        case 'bloqueado': return ['O WhatsApp recusou o número', 'O WhatsApp respondeu "proibido" (403): o número pode estar restrito ou banido.', 'Abra o WhatsApp no celular do chip e veja se há aviso de restrição. Pause a prospecção por alguns dias.', 'grave'];
        case 'morto': return ['A conexão morreu sem avisar', 'Comum quando o celular "dorme" ou troca de Wi-Fi para dados. O conector percebeu e religou sozinho.', 'Se repetir muito: ' . $termux, 'info'];
        case 'travado': return ['A conexão travou ao religar', 'O conector ficou mais de 2 minutos tentando e recomeçou do zero.', 'Confira a internet do aparelho do conector.', 'aviso'];
        case 'site_falhou': return ['O conector não conseguiu falar com o site', 'Durante ' . wa_ponte_duracao($e['seg'] ?? 0) . ($e['n'] ? ' (' . (int)$e['n'] . ' tentativas)' : '') . ': ' . ($e['det'] ?: 'sem resposta') . '.', ($e['det'] ?? '') === 'sem internet' ? 'Confira a internet do aparelho do conector.' : 'Se repetir, avise o suporte: o site demorou para responder.', 'aviso'];
        case 'conectou': return ['O WhatsApp voltou', 'Religou sozinho' . (!empty($e['seg']) ? ' depois de ' . wa_ponte_duracao($e['seg']) . ' fora' : '') . '.', '', 'info'];
        case 'caiu':
            $porCodigo = [
                408 => ['A internet do aparelho oscilou', 'O WhatsApp não respondeu a tempo (código 408). O conector religa sozinho.', 'Prefira Wi-Fi estável no aparelho do conector.', 'info'],
                428 => ['O WhatsApp fechou a conexão', 'Queda curta do próprio WhatsApp (código 428). O conector religa sozinho.', 'Normal de vez em quando. Se for toda hora, confira a internet do aparelho.', 'info'],
                405 => ['Versão do WhatsApp Web desatualizada', 'O conector buscou a versão nova e religou.', '', 'info'],
                500 => ['A sessão do WhatsApp deu erro', 'O login salvo no conector está com problema (código 500).', 'Se continuar caindo, clique em Desconectar e leia o QR code de novo.', 'aviso'],
                503 => ['O WhatsApp está fora do ar', 'O serviço do WhatsApp não respondeu (código 503).', 'Aguarde: o conector religa sozinho.', 'info'],
                401 => ['O WhatsApp pediu para confirmar o login', 'Primeiro aviso de saída: o conector está conferindo antes de apagar o login.', '', 'aviso'],
            ];
            return $porCodigo[$c] ?? ['A conexão com o WhatsApp caiu', 'Queda' . ($c ? ' (código ' . $c . ')' : '') . '. O conector religa sozinho.', '', 'info'];
    }
    return ['Evento', '', '', 'info'];
}

// Resumo para a tela: situação, versão, últimas 24 h e o que fazer
function wa_ponte_saude() {
    $info = json_decode((string)get_config('wa_ponte_info', ''), true) ?: [];
    $ev = wa_ponte_eventos_ler();
    $desde = time() - 86400;
    $dia = array_values(array_filter($ev, fn($e) => (int)$e['em'] >= $desde));
    $problemas = array_values(array_filter($dia, fn($e) => !in_array($e['tipo'], ['conectou'], true) && !($e['tipo'] === 'reiniciou' && empty($e['forcado']))));
    $fora = 0;
    foreach ($dia as $e) if (in_array($e['tipo'], ['conectou', 'sumiu'], true)) $fora += (int)($e['seg'] ?? 0);
    // A causa que mais pesou: a mais grave; entre iguais, a mais frequente
    $peso = ['grave' => 3, 'aviso' => 2, 'info' => 1];
    $grupos = [];
    foreach ($problemas as $e) {
        $m = wa_ponte_motivo($e);
        $k = $m[0];
        $grupos[$k] ??= ['motivo' => $m, 'n' => 0, 'ultimo' => 0];
        $grupos[$k]['n']++; $grupos[$k]['ultimo'] = max($grupos[$k]['ultimo'], (int)$e['em']);
    }
    uasort($grupos, fn($a, $b) => [$peso[$b['motivo'][3]], $b['n']] <=> [$peso[$a['motivo'][3]], $a['n']]);
    $versao = (string)($info['versao'] ?? '');
    return ['info' => $info, 'versao' => $versao, 'desatualizado' => $versao === '' || version_compare($versao, WA_CONECTOR_VERSAO, '<'),
            'quedas_24h' => count($problemas), 'fora_24h' => $fora, 'grupos' => array_values($grupos),
            'recentes' => array_reverse(array_slice($ev, -15))];
}

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

// Encerra a resposta ao conector agora; o trabalho de carona continua no servidor depois.
// (FPM/LiteSpeed têm função própria; nos demais, Content-Length + flush já liberam o conector.)
function wa_ponte_liberar_resposta() {
    static $feito = false;
    if ($feito) return;
    $feito = true;
    if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); return; }
    if (function_exists('litespeed_finish_request')) { litespeed_finish_request(); return; }
    while (ob_get_level() > 0) @ob_end_flush();
    @flush();
}

// Uma "volta" do conector: recebe o estado dele e devolve o que fazer
function wa_ponte_sincronizar($dados) {
    global $pdo;
    $a = wa_api();
    $visto = (int)get_config('wa_ponte_visto', '0');
    if (time() - $visto >= 10) set_config('wa_ponte_visto', (string)time());
    // Histórico: lacuna desde a última volta (funciona até com conector antigo) + o que o conector contou
    $novos = [];
    if ($visto > 0 && time() - $visto > WA_PONTE_SUMIU_SEG) $novos[] = ['em' => $visto, 'tipo' => 'sumiu', 'seg' => time() - $visto];
    foreach (array_slice((array)($dados['eventos'] ?? []), 0, 100) as $e) if ($l = wa_ponte_evento_limpo($e)) $novos[] = $l;
    try { wa_ponte_registrar($novos); } catch (Throwable $e) { error_log('GDS ponte eventos: ' . $e->getMessage()); }
    if (is_array($dados['info'] ?? null) && time() - (int)(json_decode((string)get_config('wa_ponte_info', ''), true)['visto'] ?? 0) >= 60) {
        $i = $dados['info'];
        set_config('wa_ponte_info', json_encode(['versao' => mb_substr((string)($i['versao'] ?? ''), 0, 20), 'inicio' => (int)($i['inicio'] ?? 0),
            'plataforma' => mb_substr((string)($i['plataforma'] ?? ''), 0, 30), 'node' => mb_substr((string)($i['node'] ?? ''), 0, 20), 'visto' => time()], JSON_UNESCAPED_UNICODE));
    }
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

    // Prospecção: respostas que chegaram no número da plataforma
    // (a próxima mensagem do piloto automático — que pode esperar a IA escrever — roda DEPOIS de responder)
    $ouvir = [];
    $leadsResponderam = [];
    $temPiloto = is_file(__DIR__ . '/prospeccao_piloto.php');
    if ($temPiloto) {
        require_once __DIR__ . '/prospeccao_piloto.php';
        if (get_config('prosp_ouvir', '1') === '1' && $a['plat_instancia'] !== '') $ouvir[] = $a['plat_instancia'];
        $porInst = [];
        foreach (array_slice((array)($dados['recebidas'] ?? []), 0, 100) as $m) if (is_array($m)) $porInst[(string)($m['instancia'] ?? '')][] = $m;
        foreach ($porInst as $inst => $lista) {
            try { $leadsResponderam = array_merge($leadsResponderam, prosp_processar_recebidas($lista, $inst)); } catch (Throwable $e) { error_log('GDS respostas: ' . $e->getMessage()); }
        }
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
    // Tudo o que pode demorar (IA, sites de terceiros, lembretes) roda DEPOIS de responder ao conector:
    // se a resposta passar do tempo dele, ele acha que o site caiu e o envio das mensagens atrasa.
    $temProsp = is_file(__DIR__ . '/prospeccao.php');
    register_shutdown_function(function () use ($resultadosVerif, $leadsResponderam, $mktCarona, $temPiloto, $temProsp) {
        ignore_user_abort(true);
        @set_time_limit(180);
        wa_ponte_liberar_resposta();
        // De carona: lembretes e rotinas (no máximo a cada 5 min) — dispensa o Cron Job
        try { wa_rotina(); } catch (Throwable $e) { error_log('GDS ponte rotina: ' . $e->getMessage()); }
        if ($temPiloto) {
            try { prosp_piloto_rotina(); } catch (Throwable $e) { error_log('GDS piloto: ' . $e->getMessage()); }
        }
        if ($temProsp && ($resultadosVerif || $leadsResponderam)) {
            require_once __DIR__ . '/prospeccao.php';
            foreach ($resultadosVerif as $v) {
                try { prosp_concluir_verificacao((int)$v['id'], $v); } catch (Throwable $e) { error_log('GDS verificação: ' . $e->getMessage()); }
            }
            foreach ($leadsResponderam as $lid) {
                try { prosp_analisar_resposta((int)$lid); } catch (Throwable $e) { error_log('GDS leitura da resposta: ' . $e->getMessage()); }
            }
        }
        if ($mktCarona) {
            try { require_once __DIR__ . '/marketing_ia.php'; mkt_plano_processar(); soc_rotina(); } catch (Throwable $e) { error_log('GDS marketing carona: ' . $e->getMessage()); }
        }
    });

    return ['ok' => true, 'instancias' => $instancias, 'mensagens' => $mensagens, 'verificar' => $verificar, 'ouvir' => $ouvir, 'intervalo' => $mensagens || $verificar ? 2 : 4];
}
