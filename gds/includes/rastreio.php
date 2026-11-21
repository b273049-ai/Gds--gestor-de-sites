<?php
// ============================================================
// RASTREAMENTO DE MOTOBOYS — regras compartilhadas
// (API /api/rastreio.php, painel /empresa/rastreio.php, app /m/TOKEN,
//  link do cliente /r/TOKEN e mapa da TV /tv/TOKEN)
//
// Ciclo: a loja (ou o motoboy) cria a entrega → link do cliente → "Saí" (rota) →
// "Entreguei" → "Voltei" (ou volta automática ao chegar perto da loja) → o link EXPIRA.
// O cliente só vê a posição enquanto a entrega dele está em rota; depois de entregue
// vê só "Entregue"; quando o motoboy volta, o link responde "expirado".
// ============================================================

const RASTREIO_SEGMENTO = 'rastreio-motoboy';
const RASTREIO_SINAL_SEG = 120;      // sem posição por mais que isso = "sem sinal"
const RASTREIO_VELOCIDADE_MEDIA = 6.1; // m/s (~22 km/h na cidade) para a previsão de chegada
const RASTREIO_CORES = ['#f5c518', '#38bdf8', '#f472b6', '#a78bfa', '#34d399', '#fb923c', '#f87171', '#22d3ee'];

function rastreio_token() { return bin2hex(random_bytes(16)); }
function rastreio_token_valido($t) { return is_string($t) && preg_match('/^[a-f0-9]{32}$/', $t); }

function rastreio_url_base() {
    $b = trim((string)get_config('plataforma_url', ''));
    if ($b !== '') return rtrim($b, '/');
    $host = preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https://' : 'http://') . $host;
}
function rastreio_link_cliente($token) { return rastreio_url_base() . '/r/' . $token; }
function rastreio_link_app($token) { return rastreio_url_base() . '/m/' . $token; }
function rastreio_link_tv($token) { return rastreio_url_base() . '/tv/' . $token; }

// A empresa usa o rastreamento? (segmento próprio ou módulo ligado em qualquer ramo)
function rastreio_eh_segmento($empresa) { return ($empresa['segmento_slug'] ?? '') === RASTREIO_SEGMENTO; }

function rastreio_cor($en) {
    $c = (string)($en['cor'] ?? '');
    return preg_match('/^#[0-9a-f]{6}$/i', $c) ? $c : RASTREIO_CORES[((int)$en['id']) % count(RASTREIO_CORES)];
}
function rastreio_iniciais($nome) {
    $p = preg_split('/\s+/', trim((string)$nome));
    return mb_strtoupper(mb_substr($p[0] ?? '?', 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : ''));
}
function rastreio_primeiro_nome($nome) { return explode(' ', trim((string)$nome))[0] ?: 'Motoboy'; }

// Distância em metros (Haversine)
function rastreio_distancia($lat1, $lng1, $lat2, $lng2) {
    if ($lat1 === null || $lat2 === null) return null;
    $r = 6371000; $p1 = deg2rad($lat1); $p2 = deg2rad($lat2);
    $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos($p1) * cos($p2) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;
    return 2 * $r * asin(min(1, sqrt($a)));
}

// Endereço → coordenadas (OpenStreetMap/Nominatim), perto da loja quando ela tem posição. Guardado em cache.
function rastreio_geocodificar($endereco, $empresa) {
    $endereco = trim((string)$endereco);
    if (mb_strlen($endereco) < 6) return null;
    $lat = $empresa['loja_lat'] ?? null; $lng = $empresa['loja_lng'] ?? null;
    $chave = 'rast_geo_' . md5(mb_strtolower($endereco) . '|' . round((float)$lat, 2) . '|' . round((float)$lng, 2));
    if (($c = get_config($chave, '')) !== '') { $j = json_decode($c, true); return $j ?: null; }
    $q = ['q' => $endereco . ($lat === null ? ', Brasil' : ''), 'format' => 'json', 'limit' => 1, 'countrycodes' => 'br'];
    if ($lat !== null) $q += ['viewbox' => ($lng - 0.35) . ',' . ($lat + 0.35) . ',' . ($lng + 0.35) . ',' . ($lat - 0.35), 'bounded' => 1];
    $base = rtrim(getenv('GDS_NOMINATIM_URL') ?: 'https://nominatim.openstreetmap.org', '/');
    $ch = curl_init($base . '/search?' . http_build_query($q));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER => ['Accept-Language: pt-BR'], CURLOPT_USERAGENT => 'GDS-Gestor-de-Sites/1.0 (rastreamento de entregas)']);
    $resp = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $g = $code === 200 ? (json_decode((string)$resp, true)[0] ?? null) : null;
    $out = $g ? ['lat' => (float)$g['lat'], 'lng' => (float)$g['lon']] : null;
    if ($code === 200) set_config($chave, json_encode($out));   // "não achou" também fica guardado
    return $out;
}

// ---------- leituras ----------
function rastreio_motoboy_por_token($token) {
    global $pdo;
    if (!rastreio_token_valido($token)) return null;
    $st = $pdo->prepare("SELECT en.*, e.nome AS empresa_nome, e.slug AS empresa_slug, e.logo AS empresa_logo, e.loja_lat, e.loja_lng,
            COALESCE(e.rastreio_raio, 80) AS rastreio_raio, COALESCE(e.rastreio_validade, 4) AS rastreio_validade
        FROM entregadores en JOIN empresas e ON e.id = en.empresa_id
        WHERE en.app_token = ? AND en.ativo AND COALESCE(e.status, 'ativo') NOT IN ('suspenso', 'cancelado')");
    $st->execute([$token]);
    return $st->fetch() ?: null;
}

function rastreio_entrega_por_token($token) {
    global $pdo;
    if (!rastreio_token_valido($token)) return null;
    $st = $pdo->prepare("SELECT r.*, (r.expira_em IS NOT NULL AND r.expira_em < NOW()) AS vencida,
            en.nome AS motoboy_nome, en.veiculo, en.lat, en.lng, en.rumo, en.velocidade, en.situacao,
            EXTRACT(EPOCH FROM NOW() - en.posicao_em)::int AS posicao_idade,
            e.nome AS empresa_nome, e.logo AS empresa_logo, e.whatsapp AS empresa_whatsapp, e.telefone AS empresa_telefone, e.loja_lat, e.loja_lng
        FROM rastreio_entregas r JOIN empresas e ON e.id = r.empresa_id LEFT JOIN entregadores en ON en.id = r.entregador_id
        WHERE r.token = ? AND COALESCE(e.status, 'ativo') NOT IN ('suspenso', 'cancelado')");
    $st->execute([$token]);
    return $st->fetch() ?: null;
}

// O link do cliente ainda vale? (morre quando o motoboy volta, se for cancelado ou depois da validade máxima)
function rastreio_link_vivo($r) {
    return $r && !in_array($r['status'], ['encerrada', 'cancelada'], true) && !pg_bool($r['vencida'] ?? false);
}

// O que o cliente vê (nada além do necessário: primeiro nome, posição só em rota e com sinal recente)
function rastreio_dados_cliente($r) {
    $emRota = $r['status'] === 'rota';
    $comSinal = $emRota && $r['lat'] !== null && $r['posicao_idade'] !== null && (int)$r['posicao_idade'] <= RASTREIO_SINAL_SEG;
    $dist = $comSinal && $r['destino_lat'] !== null ? rastreio_distancia((float)$r['lat'], (float)$r['lng'], (float)$r['destino_lat'], (float)$r['destino_lng']) : null;
    $eta = $dist !== null ? max(1, (int)ceil($dist / RASTREIO_VELOCIDADE_MEDIA / 60)) : null;
    $textos = ['aguardando' => 'Seu pedido está sendo preparado. Assim que o motoboy sair, você acompanha aqui.',
               'rota' => $dist !== null && $dist < 150 ? 'O motoboy está chegando! Fique de olho na porta.' : 'O motoboy está a caminho do seu endereço.',
               'entregue' => 'Pedido entregue. Bom apetite!'];
    return [
        'ok' => true, 'status' => $r['status'], 'texto' => $textos[$r['status']] ?? '',
        'empresa' => $r['empresa_nome'], 'motoboy' => $r['motoboy_nome'] ? rastreio_primeiro_nome($r['motoboy_nome']) : null,
        'veiculo' => $r['veiculo'] ?: null,
        'posicao' => $comSinal ? ['lat' => round((float)$r['lat'], 6), 'lng' => round((float)$r['lng'], 6), 'rumo' => $r['rumo'] !== null ? (float)$r['rumo'] : null, 'idade' => (int)$r['posicao_idade']] : null,
        'sem_sinal' => $emRota && !$comSinal,
        'loja' => $r['loja_lat'] !== null ? ['lat' => (float)$r['loja_lat'], 'lng' => (float)$r['loja_lng']] : null,
        'destino' => $r['destino_lat'] !== null ? ['lat' => (float)$r['destino_lat'], 'lng' => (float)$r['destino_lng']] : null,
        'distancia_m' => $dist !== null ? (int)round($dist) : null, 'eta_min' => $eta,
        'saiu_em' => $r['saiu_em'] ? date('H:i', strtotime($r['saiu_em'])) : null,
        'entregue_em' => $r['entregue_em'] ? date('H:i', strtotime($r['entregue_em'])) : null,
    ];
}

// Tudo o que o mapa da empresa / TV precisa numa chamada só. $tv = sem dados de clientes (tela pública da loja).
function rastreio_mapa($empresaId, $tv = false) {
    global $pdo;
    $st = $pdo->prepare("SELECT en.id, en.nome, en.telefone, en.veiculo, en.cor, en.situacao, en.lat, en.lng, en.rumo, en.velocidade, en.bateria, en.precisao,
            EXTRACT(EPOCH FROM NOW() - en.posicao_em)::int AS idade, EXTRACT(EPOCH FROM NOW() - en.saiu_em)::int AS fora_seg,
            (SELECT COUNT(*) FROM rastreio_entregas r WHERE r.entregador_id = en.id AND r.status IN ('aguardando','rota')) AS pendentes
        FROM entregadores en WHERE en.empresa_id = ? AND en.ativo ORDER BY en.nome");
    $st->execute([$empresaId]);
    $motoboys = [];
    foreach ($st->fetchAll() as $m) {
        $sinal = $m['lat'] !== null && $m['idade'] !== null && (int)$m['idade'] <= RASTREIO_SINAL_SEG;
        $motoboys[] = ['id' => (int)$m['id'], 'nome' => $tv ? rastreio_primeiro_nome($m['nome']) : $m['nome'], 'iniciais' => rastreio_iniciais($m['nome']),
            'cor' => rastreio_cor($m), 'situacao' => $m['situacao'] ?: 'loja', 'veiculo' => $m['veiculo'],
            'lat' => $m['lat'] !== null ? (float)$m['lat'] : null, 'lng' => $m['lng'] !== null ? (float)$m['lng'] : null,
            'rumo' => $m['rumo'] !== null ? (float)$m['rumo'] : null, 'kmh' => $m['velocidade'] !== null ? (int)round($m['velocidade'] * 3.6) : null,
            'bateria' => $m['bateria'] !== null ? (int)$m['bateria'] : null, 'idade' => $m['idade'] !== null ? (int)$m['idade'] : null, 'sinal' => $sinal,
            'fora_min' => $m['fora_seg'] !== null && ($m['situacao'] ?? '') !== 'loja' ? (int)floor($m['fora_seg'] / 60) : null,
            'pendentes' => (int)$m['pendentes'], 'rastro' => []];
    }
    // Rastro dos que estão na rua (últimos 40 min, até ~120 pontos por motoboy)
    $naRua = array_values(array_filter($motoboys, fn($m) => $m['situacao'] !== 'loja'));
    if ($naRua) {
        $ids = implode(',', array_map(fn($m) => (int)$m['id'], $naRua));
        $pts = $pdo->query("SELECT entregador_id, lat, lng FROM rastreio_pontos WHERE entregador_id IN ($ids) AND em > NOW() - INTERVAL '40 minutes' ORDER BY id")->fetchAll();
        $porId = [];
        foreach ($pts as $p) $porId[(int)$p['entregador_id']][] = [round((float)$p['lat'], 6), round((float)$p['lng'], 6)];
        foreach ($motoboys as &$m) if (isset($porId[$m['id']])) { $l = $porId[$m['id']]; $passo = max(1, (int)ceil(count($l) / 120)); $m['rastro'] = array_values(array_filter($l, fn($k) => $k % $passo === 0, ARRAY_FILTER_USE_KEY)); }
        unset($m);
    }
    $e = $pdo->prepare("SELECT loja_lat, loja_lng, nome FROM empresas WHERE id=?"); $e->execute([$empresaId]); $emp = $e->fetch() ?: [];
    $hoje = $pdo->prepare("SELECT COUNT(*) FILTER (WHERE entregue_em::date = CURRENT_DATE) AS entregues,
            ROUND(AVG(EXTRACT(EPOCH FROM entregue_em - saiu_em) / 60) FILTER (WHERE entregue_em::date = CURRENT_DATE AND saiu_em IS NOT NULL)) AS media_min,
            COUNT(*) FILTER (WHERE status IN ('aguardando','rota')) AS ativas
        FROM rastreio_entregas WHERE empresa_id = ?");
    $hoje->execute([$empresaId]); $h = $hoje->fetch();
    $out = ['ok' => true, 'agora' => date('H:i:s'), 'empresa' => $emp['nome'] ?? '',
        'loja' => ($emp['loja_lat'] ?? null) !== null ? ['lat' => (float)$emp['loja_lat'], 'lng' => (float)$emp['loja_lng']] : null,
        'motoboys' => $motoboys, 'resumo' => ['rota' => count(array_filter($motoboys, fn($m) => $m['situacao'] === 'rota')),
            'voltando' => count(array_filter($motoboys, fn($m) => $m['situacao'] === 'voltando')),
            'loja' => count(array_filter($motoboys, fn($m) => $m['situacao'] === 'loja')),
            'ativas' => (int)$h['ativas'], 'entregues_hoje' => (int)$h['entregues'], 'media_min' => $h['media_min'] !== null ? (int)$h['media_min'] : null]];
    if (!$tv) {
        $st = $pdo->prepare("SELECT r.id, r.token, r.codigo, r.cliente_nome, r.cliente_telefone, r.endereco, r.destino_lat, r.destino_lng, r.status, r.entregador_id,
                to_char(r.criado_em, 'HH24:MI') AS criado, to_char(r.saiu_em, 'HH24:MI') AS saiu, to_char(r.entregue_em, 'HH24:MI') AS entregue,
                ROUND(EXTRACT(EPOCH FROM COALESCE(r.entregue_em, NOW()) - r.saiu_em) / 60) AS minutos
            FROM rastreio_entregas r WHERE r.empresa_id = ? AND (r.status IN ('aguardando','rota','entregue') AND (r.expira_em IS NULL OR r.expira_em > NOW()))
            ORDER BY CASE r.status WHEN 'rota' THEN 0 WHEN 'aguardando' THEN 1 ELSE 2 END, r.id DESC LIMIT 60");
        $st->execute([$empresaId]);
        $out['entregas'] = array_map(fn($r) => ['id' => (int)$r['id'], 'link' => rastreio_link_cliente($r['token']), 'codigo' => $r['codigo'], 'cliente' => $r['cliente_nome'],
            'telefone' => $r['cliente_telefone'], 'endereco' => $r['endereco'], 'status' => $r['status'], 'motoboy_id' => $r['entregador_id'] !== null ? (int)$r['entregador_id'] : null,
            'destino' => $r['destino_lat'] !== null ? ['lat' => (float)$r['destino_lat'], 'lng' => (float)$r['destino_lng']] : null,
            'criado' => $r['criado'], 'saiu' => $r['saiu'], 'entregue' => $r['entregue'], 'minutos' => $r['minutos'] !== null ? (int)$r['minutos'] : null], $st->fetchAll());
    }
    return $out;
}

// Entregas do motoboy para o app
function rastreio_estado_motoboy($m) {
    global $pdo;
    $st = $pdo->prepare("SELECT id, token, codigo, cliente_nome, cliente_telefone, endereco, destino_lat, destino_lng, observacao, status,
            to_char(criado_em, 'HH24:MI') AS criado, to_char(saiu_em, 'HH24:MI') AS saiu, to_char(entregue_em, 'HH24:MI') AS entregue
        FROM rastreio_entregas WHERE entregador_id = ? AND status IN ('aguardando','rota','entregue') AND (expira_em IS NULL OR expira_em > NOW())
        ORDER BY CASE status WHEN 'rota' THEN 0 WHEN 'aguardando' THEN 1 ELSE 2 END, id");
    $st->execute([$m['id']]);
    $hoje = $pdo->prepare("SELECT COUNT(*) FROM rastreio_entregas WHERE entregador_id=? AND entregue_em::date = CURRENT_DATE");
    $hoje->execute([$m['id']]);
    return ['ok' => true, 'nome' => $m['nome'], 'empresa' => $m['empresa_nome'], 'situacao' => $m['situacao'] ?: 'loja',
        'loja' => $m['loja_lat'] !== null ? ['lat' => (float)$m['loja_lat'], 'lng' => (float)$m['loja_lng'], 'raio' => (int)$m['rastreio_raio']] : null,
        'entregues_hoje' => (int)$hoje->fetchColumn(),
        'entregas' => array_map(fn($r) => ['id' => (int)$r['id'], 'link' => rastreio_link_cliente($r['token']), 'codigo' => $r['codigo'], 'cliente' => $r['cliente_nome'],
            'telefone' => $r['cliente_telefone'], 'endereco' => $r['endereco'], 'observacao' => $r['observacao'], 'status' => $r['status'],
            'destino' => $r['destino_lat'] !== null ? ['lat' => (float)$r['destino_lat'], 'lng' => (float)$r['destino_lng']] : null,
            'criado' => $r['criado'], 'saiu' => $r['saiu'], 'entregue' => $r['entregue']], $st->fetchAll())];
}

// ---------- ações ----------
function rastreio_criar_entrega($empresaId, $dados, $entregadorId = null) {
    global $pdo;
    $e = $pdo->prepare("SELECT id, loja_lat, loja_lng, COALESCE(rastreio_validade, 4) AS validade FROM empresas WHERE id=?");
    $e->execute([$empresaId]);
    $emp = $e->fetch();
    if (!$emp) return null;
    $endereco = mb_substr(trim(strip_tags((string)($dados['endereco'] ?? ''))), 0, 255);
    $geo = $endereco !== '' ? rastreio_geocodificar($endereco, $emp) : null;
    $tel = preg_replace('/\D/', '', (string)($dados['telefone'] ?? ''));
    if ($entregadorId) {
        $ok = $pdo->prepare("SELECT 1 FROM entregadores WHERE id=? AND empresa_id=? AND ativo");
        $ok->execute([(int)$entregadorId, $empresaId]);
        if (!$ok->fetchColumn()) $entregadorId = null;
    }
    $token = rastreio_token();
    $horas = max(1, min(24, (int)$emp['validade']));
    $st = $pdo->prepare("INSERT INTO rastreio_entregas (empresa_id, entregador_id, token, codigo, cliente_nome, cliente_telefone, endereco, destino_lat, destino_lng, observacao, expira_em)
        VALUES (?,?,?,?,?,?,?,?,?,?, NOW() + make_interval(hours => ?)) RETURNING id");
    $st->execute([$empresaId, $entregadorId ?: null, $token, mb_substr(trim((string)($dados['codigo'] ?? '')), 0, 30) ?: null,
        mb_substr(trim(strip_tags((string)($dados['cliente'] ?? ''))), 0, 100) ?: null, $tel !== '' ? mb_substr($tel, 0, 20) : null,
        $endereco ?: null, $geo['lat'] ?? null, $geo['lng'] ?? null, mb_substr(trim(strip_tags((string)($dados['observacao'] ?? ''))), 0, 255) ?: null, $horas]);
    $id = (int)$st->fetchColumn();
    // Motoboy já está na rua: a entrega nova já nasce em rota
    if ($entregadorId) {
        $pdo->prepare("UPDATE rastreio_entregas r SET status='rota', saiu_em=NOW() FROM entregadores en
            WHERE r.id=? AND en.id=r.entregador_id AND en.situacao IN ('rota','voltando')")->execute([$id]);
        $pdo->prepare("UPDATE entregadores SET situacao='rota' WHERE id=? AND situacao='voltando'")->execute([$entregadorId]);
    }
    return ['id' => $id, 'token' => $token, 'link' => rastreio_link_cliente($token), 'geocodificado' => (bool)$geo];
}

function rastreio_sair($m) {
    global $pdo;
    $pdo->prepare("UPDATE entregadores SET situacao='rota', saiu_em=COALESCE(CASE WHEN situacao='loja' THEN NULL ELSE saiu_em END, NOW()), afastou = CASE WHEN situacao='loja' THEN FALSE ELSE afastou END WHERE id=?")->execute([$m['id']]);
    // A validade do link conta a partir da saída (pedido feito cedo não expira no meio da entrega)
    $pdo->prepare("UPDATE rastreio_entregas SET status='rota', saiu_em=NOW(), expira_em=GREATEST(COALESCE(expira_em, NOW()), NOW() + make_interval(hours => ?))
        WHERE entregador_id=? AND status='aguardando'")->execute([max(1, min(24, (int)($m['rastreio_validade'] ?? 4))), $m['id']]);
}

function rastreio_entreguei($m, $entregaId) {
    global $pdo;
    $st = $pdo->prepare("UPDATE rastreio_entregas SET status='entregue', entregue_em=NOW(), saiu_em=COALESCE(saiu_em, NOW())
        WHERE id=? AND entregador_id=? AND status IN ('aguardando','rota')");
    $st->execute([(int)$entregaId, $m['id']]);
    $resta = $pdo->prepare("SELECT COUNT(*) FROM rastreio_entregas WHERE entregador_id=? AND status='rota'");
    $resta->execute([$m['id']]);
    if (!(int)$resta->fetchColumn()) $pdo->prepare("UPDATE entregadores SET situacao='voltando' WHERE id=? AND situacao='rota'")->execute([$m['id']]);
    return $st->rowCount() > 0;
}

// Voltou para a loja: os links das entregas desta saída expiram
function rastreio_voltei($entregadorId) {
    global $pdo;
    $pdo->prepare("UPDATE entregadores SET situacao='loja', saiu_em=NULL, afastou=FALSE WHERE id=?")->execute([(int)$entregadorId]);
    $pdo->prepare("UPDATE rastreio_entregas SET status='encerrada', encerrada_em=NOW() WHERE entregador_id=? AND status IN ('rota','entregue')")->execute([(int)$entregadorId]);
}

// Posição vinda do GPS do celular. Devolve 'voltou_auto' quando chegou na loja depois de ter saído.
function rastreio_posicao($m, $d) {
    global $pdo;
    $lat = filter_var($d['lat'] ?? null, FILTER_VALIDATE_FLOAT); $lng = filter_var($d['lng'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($lat === false || $lng === false || $lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180 || ($lat == 0 && $lng == 0)) return ['ok' => false, 'erro' => 'Posição inválida.'];
    $num = fn($k, $min, $max) => is_numeric($d[$k] ?? null) && $d[$k] >= $min && $d[$k] <= $max ? (float)$d[$k] : null;
    $prec = $num('precisao', 0, 100000); $vel = $num('velocidade', 0, 90); $rumo = $num('rumo', 0, 360); $bat = $num('bateria', 0, 100);
    $pdo->prepare("UPDATE entregadores SET lat=?, lng=?, precisao=?, velocidade=?, rumo=COALESCE(?, rumo), bateria=COALESCE(?, bateria), posicao_em=NOW() WHERE id=?")
        ->execute([$lat, $lng, $prec, $vel, $rumo, $bat !== null ? (int)$bat : null, $m['id']]);
    $situacao = $m['situacao'] ?: 'loja';
    $voltou = false;
    if ($situacao !== 'loja' && ($prec === null || $prec <= 150)) {
        $pdo->prepare("INSERT INTO rastreio_pontos (entregador_id, lat, lng) VALUES (?,?,?)")->execute([$m['id'], $lat, $lng]);
        // Volta automática: saiu, se afastou da loja e chegou de novo perto dela
        if ($m['loja_lat'] !== null) {
            $dist = rastreio_distancia($lat, $lng, (float)$m['loja_lat'], (float)$m['loja_lng']);
            if ($dist > 250 && !pg_bool($m['afastou'])) $pdo->prepare("UPDATE entregadores SET afastou=TRUE WHERE id=?")->execute([$m['id']]);
            elseif (pg_bool($m['afastou']) && $dist <= max(30, (int)$m['rastreio_raio'])) { rastreio_voltei($m['id']); $voltou = true; }
        }
    }
    if (random_int(1, 300) === 1) $pdo->exec("DELETE FROM rastreio_pontos WHERE em < NOW() - INTERVAL '3 days'");
    return ['ok' => true, 'voltou_auto' => $voltou, 'situacao' => $voltou ? 'loja' : $situacao];
}

// <head> das páginas do rastreio (app, cliente, TV): fontes, estilo e, se pedir, o Leaflet
function rastreio_head($titulo, $descricao = '', $comMapa = false, $extra = '') {
    $h = '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
       . '<title>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</title>'
       . ($descricao !== '' ? '<meta name="description" content="' . htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8') . '">' : '')
       . '<meta name="theme-color" content="#15171b"><meta name="robots" content="noindex, nofollow"><meta name="referrer" content="strict-origin-when-cross-origin">'
       . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
       . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Semi+Condensed:wght@600;700&display=swap">';
    if ($comMapa) $h .= '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" crossorigin="">'
       . '<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>'
       . '<script src="/assets/js/rastreio-mapa.js?v=1"></script>';
    return $h . '<link rel="stylesheet" href="/assets/css/rastreio.css?v=1">' . $extra;
}

// Tabelas da migração 35 já existem? (antes da primeira visita depois de publicar)
function rastreio_pronto() {
    global $pdo;
    static $ok = null;
    if ($ok === null) { try { $ok = (bool)$pdo->query("SELECT to_regclass('public.rastreio_entregas') IS NOT NULL")->fetchColumn(); } catch (PDOException $e) { $ok = false; } }
    return $ok;
}
