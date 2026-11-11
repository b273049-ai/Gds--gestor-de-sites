<?php
// ============================================================
// Regras de disponibilidade da agenda (usadas pelo site público,
// pelo endpoint de horários e pelo painel da empresa).
// Um horário está ocupado se ele SE SOBREPÕE a outro agendamento
// (levando em conta a duração do serviço), à pausa de almoço ou a
// uma folga cadastrada — e não só se começa na mesma hora exata.
// ============================================================

function agenda_hm_para_min($hm) {
    [$h, $m] = array_map('intval', explode(':', substr((string)$hm, 0, 5) . ':0'));
    return $h * 60 + $m;
}

function agenda_config($empresa) {
    $pausaIni = !empty($empresa['pausa_inicio']) ? substr($empresa['pausa_inicio'], 0, 5) : '';
    $pausaFim = !empty($empresa['pausa_fim']) ? substr($empresa['pausa_fim'], 0, 5) : '';
    if ($pausaIni === '' || $pausaFim === '' || $pausaFim <= $pausaIni) { $pausaIni = $pausaFim = ''; }
    return [
        'abertura'   => substr($empresa['horario_abertura'] ?? '08:00', 0, 5) ?: '08:00',
        'fechamento' => substr($empresa['horario_fechamento'] ?? '19:00', 0, 5) ?: '19:00',
        'intervalo'  => max(10, (int)($empresa['intervalo_agenda'] ?? 30) ?: 30),
        'dias'       => ($empresa['dias_funcionamento'] ?? '') !== '' && $empresa['dias_funcionamento'] !== null ? $empresa['dias_funcionamento'] : '1,2,3,4,5,6',
        'antecedencia_dias' => (int)($empresa['antecedencia_max_dias'] ?? 60),
        'pausa_inicio' => $pausaIni,
        'pausa_fim'    => $pausaFim,
    ];
}

// Folgas/feriados: [ 'Y-m-d' => [null (empresa toda) | profissional_id, ...] ]
function agenda_bloqueios($empresaId, $de, $ate) {
    global $pdo;
    static $memoria = [];                       // a mesma página pede o mesmo período várias vezes
    $chave = (int)$empresaId . '|' . $de . '|' . $ate;
    if (isset($memoria[$chave])) return $memoria[$chave];
    try {
        $st = $pdo->prepare("SELECT data, profissional_id FROM agenda_bloqueios WHERE empresa_id=? AND data BETWEEN ?::date AND ?::date");
        $st->execute([$empresaId, $de, $ate]);
    } catch (PDOException $e) {
        return []; // banco ainda sem a tabela (adicional_12)
    }
    $r = [];
    foreach ($st->fetchAll() as $b) $r[$b['data']][] = $b['profissional_id'] ? (int)$b['profissional_id'] : null;
    return $memoria[$chave] = $r;
}

// Dias do período em que a empresa inteira está fechada (folga/feriado)
function agenda_dias_fechados($empresaId, $de, $ate) {
    $fechados = [];
    foreach (agenda_bloqueios($empresaId, $de, $ate) as $dia => $quem) {
        if (in_array(null, $quem, true)) $fechados[] = $dia;
    }
    return $fechados;
}

// Agendamentos ativos de um dia: [inicio_min, fim_min, profissional_id]
function agenda_ocupacoes_do_dia($empresaId, $dataYmd) {
    global $pdo;
    $st = $pdo->prepare("
        SELECT DISTINCT ON (CASE WHEN COALESCE(s.vagas, 1) > 1 THEN a.data_hora::text || '-' || a.servico_id ELSE a.id::text END)
            a.data_hora, a.profissional_id, COALESCE(NULLIF(s.duracao_minutos, 0), 30) AS dur
        FROM agendamentos a LEFT JOIN servicos s ON s.id = a.servico_id
        WHERE a.empresa_id = ? AND a.status <> 'cancelado' AND a.data_hora::date = ?::date");
    $st->execute([$empresaId, $dataYmd]);
    $lista = [];
    foreach ($st->fetchAll() as $r) {
        $t = strtotime($r['data_hora']);
        $ini = (int)date('G', $t) * 60 + (int)date('i', $t);
        $lista[] = [$ini, $ini + (int)$r['dur'], $r['profissional_id'] ? (int)$r['profissional_id'] : null];
    }
    // Profissional de folga nesse dia = ocupado o dia inteiro
    foreach (agenda_bloqueios($empresaId, $dataYmd, $dataYmd)[$dataYmd] ?? [] as $prof) {
        if ($prof) $lista[] = [0, 24 * 60, $prof];
    }
    return $lista;
}

function agenda_qtd_profissionais($empresaId) {
    global $pdo;
    static $memoria = [];
    if (isset($memoria[(int)$empresaId])) return $memoria[(int)$empresaId];
    $st = $pdo->prepare("SELECT COUNT(*) c FROM profissionais WHERE empresa_id=? AND ativo=true");
    $st->execute([$empresaId]);
    return $memoria[(int)$empresaId] = max(1, (int)$st->fetch()['c']);
}

// O intervalo [ini, ini+dur) está livre? Com profissional: ninguém dele sobreposto.
// Sem profissional: precisa sobrar pelo menos um profissional livre naquele intervalo.
function agenda_intervalo_livre($ocupacoes, $ini, $dur, $profissionalId, $qtdProfissionais) {
    $fim = $ini + $dur;
    $sobrepostos = 0;
    foreach ($ocupacoes as [$oIni, $oFim, $oProf]) {
        if ($oIni < $fim && $ini < $oFim) {
            if ($profissionalId) {
                if ($oProf === (int)$profissionalId) return false;
            } else {
                $sobrepostos++;
            }
        }
    }
    return $profissionalId ? true : $sobrepostos < $qtdProfissionais;
}

function agenda_bate_na_pausa($cfg, $ini, $dur) {
    if ($cfg['pausa_inicio'] === '') return false;
    $pIni = agenda_hm_para_min($cfg['pausa_inicio']); $pFim = agenda_hm_para_min($cfg['pausa_fim']);
    return $pIni < $ini + $dur && $ini < $pFim;
}

// Turma (serviço com várias vagas no mesmo horário): quantos já reservaram cada horário
function agenda_turma_ocupadas($empresaId, $dataYmd, $servicoId) {
    global $pdo;
    $st = $pdo->prepare("SELECT to_char(data_hora, 'HH24:MI') h, COUNT(*) c FROM agendamentos WHERE empresa_id=? AND servico_id=? AND status <> 'cancelado' AND data_hora::date = ?::date GROUP BY 1");
    $st->execute([$empresaId, $servicoId, $dataYmd]);
    $r = []; foreach ($st->fetchAll() as $l) $r[$l['h']] = (int)$l['c'];
    return $r;
}

// Todos os horários do dia, com a situação de cada um. $servico com vagas > 1 = turma.
function agenda_horarios_do_dia($empresa, $dataYmd, $duracao, $profissionalId = null, $servico = null) {
    $cfg = agenda_config($empresa);
    if (in_array($dataYmd, agenda_dias_fechados($empresa['id'], $dataYmd, $dataYmd), true)) return ['fechado' => true, 'livres' => [], 'ocupados' => []];
    if ($servico && (int)($servico['vagas'] ?? 1) > 1) {
        $vagas = (int)$servico['vagas'];
        $ocup = agenda_turma_ocupadas($empresa['id'], $dataYmd, $servico['id']);
        $livres = []; $ocupados = []; $restam = [];
        for ($m = agenda_hm_para_min($cfg['abertura']); $m + $duracao <= agenda_hm_para_min($cfg['fechamento']); $m += $cfg['intervalo']) {
            $h = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
            if (agenda_bate_na_pausa($cfg, $m, $duracao)) continue;
            $r = $vagas - ($ocup[$h] ?? 0);
            if ($r > 0) { $livres[] = $h; $restam[$h] = $r; } else $ocupados[] = $h;
        }
        return ['fechado' => false, 'livres' => $livres, 'ocupados' => $ocupados, 'vagas' => $restam, 'turma' => $vagas];
    }
    $oc = agenda_ocupacoes_do_dia($empresa['id'], $dataYmd);
    $qtd = agenda_qtd_profissionais($empresa['id']);
    $livres = []; $ocupados = [];
    for ($m = agenda_hm_para_min($cfg['abertura']); $m + $duracao <= agenda_hm_para_min($cfg['fechamento']); $m += $cfg['intervalo']) {
        $h = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        if (agenda_bate_na_pausa($cfg, $m, $duracao)) continue; // horário de almoço nem aparece
        if (agenda_intervalo_livre($oc, $m, $duracao, $profissionalId, $qtd)) $livres[] = $h; else $ocupados[] = $h;
    }
    return ['fechado' => false, 'livres' => $livres, 'ocupados' => $ocupados];
}

// Compatibilidade com quem só precisa da lista de ocupados
function agenda_horarios_ocupados($empresa, $dataYmd, $duracao, $profissionalId = null) {
    return agenda_horarios_do_dia($empresa, $dataYmd, $duracao, $profissionalId)['ocupados'];
}

// Validação completa de um pedido de agendamento. Retorna '' se ok, ou a mensagem de erro.
function agenda_validar($empresa, $dataHora, $duracao, $profissionalId = null, $ignorarAgendamentoId = null, $servico = null) {
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', (string)$dataHora) ?: DateTime::createFromFormat('Y-m-d H:i', (string)$dataHora);
    if (!$dt) return 'Data/hora inválida. Escolha o dia e o horário no calendário.';
    if ($dt->getTimestamp() < time()) return 'Esse horário já passou. Escolha outro.';
    $cfg = agenda_config($empresa);
    if ($cfg['antecedencia_dias'] > 0 && $dt->getTimestamp() > strtotime('+' . $cfg['antecedencia_dias'] . ' days')) {
        return 'Só aceitamos agendamentos com até ' . $cfg['antecedencia_dias'] . ' dias de antecedência.';
    }
    $diasAbertos = array_map('intval', explode(',', $cfg['dias']));
    if (!in_array((int)$dt->format('w'), $diasAbertos, true)) return 'Não atendemos nesse dia da semana.';
    if (agenda_dias_fechados($empresa['id'], $dt->format('Y-m-d'), $dt->format('Y-m-d'))) return 'Não vamos atender nesse dia (folga/feriado). Escolha outra data.';
    $ini = (int)$dt->format('G') * 60 + (int)$dt->format('i');
    if ($ini < agenda_hm_para_min($cfg['abertura']) || $ini + $duracao > agenda_hm_para_min($cfg['fechamento'])) {
        return 'Fora do horário de atendimento (' . $cfg['abertura'] . ' às ' . $cfg['fechamento'] . ').';
    }
    if (agenda_bate_na_pausa($cfg, $ini, $duracao)) return 'Esse horário cai na pausa (' . $cfg['pausa_inicio'] . ' às ' . $cfg['pausa_fim'] . '). Escolha outro.';
    if ($servico && (int)($servico['vagas'] ?? 1) > 1) {
        $ocup = agenda_turma_ocupadas($empresa['id'], $dt->format('Y-m-d'), $servico['id']);
        return ($ocup[$dt->format('H:i')] ?? 0) >= (int)$servico['vagas'] ? 'Esta turma lotou agora há pouco. Escolha outro horário.' : '';
    }
    $oc = agenda_ocupacoes_do_dia($empresa['id'], $dt->format('Y-m-d'));
    if ($ignorarAgendamentoId) {
        // (usado ao remarcar: o próprio agendamento não conta como conflito)
        global $pdo;
        $st = $pdo->prepare("SELECT data_hora, profissional_id FROM agendamentos WHERE id=?");
        $st->execute([$ignorarAgendamentoId]);
        if ($proprio = $st->fetch()) {
            $t = strtotime($proprio['data_hora']); $pIni = (int)date('G', $t) * 60 + (int)date('i', $t);
            foreach ($oc as $k => $o) { if ($o[0] === $pIni && $o[2] === ($proprio['profissional_id'] ? (int)$proprio['profissional_id'] : null)) { unset($oc[$k]); break; } }
        }
    }
    if (!agenda_intervalo_livre($oc, $ini, $duracao, $profissionalId, agenda_qtd_profissionais($empresa['id']))) {
        return $profissionalId
            ? 'Esse horário já está reservado com esse profissional. Escolha outro horário ou outro profissional.'
            : 'Esse horário acabou de ser reservado. Escolha outro.';
    }
    return '';
}

// "Aberto agora" / "Fechado" para mostrar no site
function agenda_status_agora($empresa) {
    $cfg = agenda_config($empresa);
    $agora = (int)date('G') * 60 + (int)date('i');
    $hoje = date('Y-m-d');
    $abertoHoje = in_array((int)date('w'), array_map('intval', explode(',', $cfg['dias'])), true)
        && !agenda_dias_fechados($empresa['id'], $hoje, $hoje);
    $dentro = $agora >= agenda_hm_para_min($cfg['abertura']) && $agora < agenda_hm_para_min($cfg['fechamento']);
    $naPausa = $cfg['pausa_inicio'] !== '' && $agora >= agenda_hm_para_min($cfg['pausa_inicio']) && $agora < agenda_hm_para_min($cfg['pausa_fim']);
    if ($abertoHoje && $dentro && !$naPausa) return ['aberto' => true, 'texto' => 'Aberto agora · fecha às ' . $cfg['fechamento']];
    if ($abertoHoje && $naPausa) return ['aberto' => false, 'texto' => 'Em pausa · volta às ' . $cfg['pausa_fim']];
    if ($abertoHoje && $agora < agenda_hm_para_min($cfg['abertura'])) return ['aberto' => false, 'texto' => 'Fechado · abre hoje às ' . $cfg['abertura']];
    return ['aberto' => false, 'texto' => 'Fechado agora'];
}

// Próximo horário livre (hoje ou nos próximos dias) para mostrar no site: "hoje às 15:30"
function agenda_proximo_livre($empresa, $duracao = null) {
    $cfg = agenda_config($empresa);
    $duracao = $duracao ?: $cfg['intervalo'];
    $dias = array_map('intval', explode(',', $cfg['dias']));
    $semana = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
    for ($i = 0; $i <= 6; $i++) {
        $ts = strtotime("+$i day");
        if (!in_array((int)date('w', $ts), $dias, true)) continue;
        $data = date('Y-m-d', $ts);
        try { $h = agenda_horarios_do_dia($empresa, $data, $duracao); } catch (Throwable $e) { return null; }
        foreach ($h['livres'] as $hora) {
            if ($i === 0 && strtotime("$data $hora") < time() + 30 * 60) continue; // hoje: só daqui a 30 min
            $quando = $i === 0 ? 'hoje' : ($i === 1 ? 'amanhã' : $semana[(int)date('w', $ts)] . ', ' . date('d/m', $ts));
            return ['data' => $data, 'hora' => $hora, 'texto' => "$quando às $hora"];
        }
    }
    return null;
}
