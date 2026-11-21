<?php
// ============================================================
// VISITAS E CLIQUES DO SITE (painel "Desempenho do site") — gravação em lote
//
// Antes, cada visita abria uma conexão no banco só para anotar a estatística. Num pico de acessos isso
// disputava conexão com pedidos e agendamentos. Agora o registro vai para um arquivo local (instantâneo,
// sem banco) e é passado para o banco em lote: cerca de 1 vez por minuto, quando a empresa abre o
// "Desempenho" e a cada passada do cron. As regras continuam as mesmas (visita conta 1 vez a cada 30 min
// por pessoa; clique, 1 vez por minuto do mesmo tipo).
// Precisa de includes/cache_pagina.php (pasta do cache). Sem a pasta, o evento.php grava direto como antes.
// ============================================================

// Anota um evento no arquivo. false = não deu (quem chamou grava direto no banco).
function site_eventos_guardar($empresaId, $tipo, $visitante, $origem, $dispositivo) {
    $pasta = gds_cache_pasta();
    if (!$pasta) return false;
    $arq = $pasta . '/eventos.log';
    if ((int)@filesize($arq) > 20 * 1024 * 1024) return true;      // fila enorme (banco parado há muito tempo): descarta em vez de encher o disco
    $linha = json_encode(['e' => (int)$empresaId, 't' => $tipo, 'v' => $visitante, 'o' => $origem, 'd' => $dispositivo, 'em' => date('Y-m-d H:i:s')]);
    return @file_put_contents($arq, $linha . "\n", FILE_APPEND | LOCK_EX) !== false;
}

// Já passou 1 minuto desde a última passagem para o banco? (e ninguém está fazendo isso agora)
function site_eventos_hora_de_descarregar() {
    $pasta = gds_cache_pasta();
    if (!$pasta || !is_file($pasta . '/eventos.log')) return false;
    $marca = $pasta . '/eventos.ok';
    if (is_file($marca) && time() - (int)filemtime($marca) < 60) return false;
    return @touch($marca);
}

// Passa os eventos do arquivo para o banco (precisa de $pdo). Devolve quantos foram gravados.
function site_eventos_descarregar() {
    global $pdo;
    $pasta = gds_cache_pasta();
    if (!$pasta) return 0;
    @touch($pasta . '/eventos.ok');
    // Pega a fila atual (e alguma que tenha ficado de uma passada que falhou)
    $meu = $pasta . '/eventos.' . getmypid() . '.' . mt_rand(1000, 9999) . '.proc';
    @rename($pasta . '/eventos.log', $meu);
    $gravados = 0;
    foreach ((array)glob($pasta . '/eventos.*.proc') as $f) {
        if ($f !== $meu && time() - (int)@filemtime($f) < 120) continue;        // outro pedido ainda está nela
        $linhas = @file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($linhas === false) continue;
        // Repetidos dentro do próprio lote (mesma pessoa, mesmo tipo, dentro da janela) saem aqui
        $ultimo = []; $lote = [];
        foreach ($linhas as $l) {
            $ev = json_decode($l, true);
            if (!is_array($ev) || empty($ev['e']) || empty($ev['t']) || empty($ev['em'])) continue;
            $k = $ev['e'] . '|' . $ev['v'] . '|' . $ev['t'];
            $t = strtotime($ev['em']);
            if (isset($ultimo[$k]) && $t - $ultimo[$k] < ($ev['t'] === 'visita' ? 1800 : 60)) continue;
            $ultimo[$k] = $t;
            $lote[] = $ev;
        }
        try {
            foreach (array_chunk($lote, 200) as $parte) {
                $valores = []; $par = [];
                foreach ($parte as $ev) {
                    $valores[] = '(?::int, ?::text, ?::text, ?::text, ?::text, ?::timestamp)';
                    array_push($par, (int)$ev['e'], (string)$ev['t'], (string)$ev['v'], mb_substr((string)$ev['o'], 0, 60), (string)$ev['d'], (string)$ev['em']);
                }
                $st = $pdo->prepare("INSERT INTO site_eventos (empresa_id, tipo, visitante, origem, dispositivo, criado_em)
                    SELECT v.e, v.t, v.vis, v.o, v.d, v.em FROM (VALUES " . implode(',', $valores) . ") AS v(e, t, vis, o, d, em)
                    JOIN empresas emp ON emp.id = v.e
                    WHERE NOT EXISTS (SELECT 1 FROM site_eventos s WHERE s.empresa_id = v.e AND s.visitante = v.vis AND s.tipo = v.t
                        AND s.criado_em > v.em - (CASE WHEN v.t = 'visita' THEN INTERVAL '30 minutes' ELSE INTERVAL '1 minute' END))");
                $st->execute($par);
                $gravados += $st->rowCount();
            }
            @unlink($f);
        } catch (Throwable $e) {
            error_log('GDS eventos do site: ' . $e->getMessage());
            @touch($f);                                                         // tenta de novo na próxima passada
        }
    }
    return $gravados;
}
