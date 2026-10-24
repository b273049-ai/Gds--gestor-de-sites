<?php
// ============================================================
// Regras de disponibilidade da agenda (usadas pelo site público,
// pelo endpoint de horários e pelo painel da empresa).
// Um horário está ocupado se ele SE SOBREPÕE a outro agendamento
// (levando em conta a duração do serviço), e não só se começa na
// mesma hora exata — que era o que o sistema verificava antes.
// ============================================================

function agenda_config($empresa) {
    return [
        'abertura'   => substr($empresa['horario_abertura'] ?? '08:00', 0, 5) ?: '08:00',
        'fechamento' => substr($empresa['horario_fechamento'] ?? '19:00', 0, 5) ?: '19:00',
        'intervalo'  => max(10, (int)($empresa['intervalo_agenda'] ?? 30) ?: 30),
        'dias'       => ($empresa['dias_funcionamento'] ?? '') !== '' && $empresa['dias_funcionamento'] !== null ? $empresa['dias_funcionamento'] : '1,2,3,4,5,6',
        'antecedencia_dias' => (int)($empresa['antecedencia_max_dias'] ?? 60),
    ];
}

// Agendamentos ativos de um dia: [inicio_min, fim_min, profissional_id]
function agenda_ocupacoes_do_dia($empresaId, $dataYmd) {
    global $pdo;
    $st = $pdo->prepare("
        SELECT a.data_hora, a.profissional_id, COALESCE(NULLIF(s.duracao_minutos, 0), 30) AS dur
        FROM agendamentos a LEFT JOIN servicos s ON s.id = a.servico_id
        WHERE a.empresa_id = ? AND a.status <> 'cancelado' AND a.data_hora::date = ?::date");
    $st->execute([$empresaId, $dataYmd]);
    $lista = [];
    foreach ($st->fetchAll() as $r) {
        $t = strtotime($r['data_hora']);
        $ini = (int)date('G', $t) * 60 + (int)date('i', $t);
        $lista[] = [$ini, $ini + (int)$r['dur'], $r['profissional_id'] ? (int)$r['profissional_id'] : null];
    }
    return $lista;
}

function agenda_qtd_profissionais($empresaId) {
    global $pdo;
    $st = $pdo->prepare("SELECT COUNT(*) c FROM profissionais WHERE empresa_id=? AND ativo=true");
    $st->execute([$empresaId]);
    return max(1, (int)$st->fetch()['c']);
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

function agenda_horarios_ocupados($empresa, $dataYmd, $duracao, $profissionalId = null) {
    $cfg = agenda_config($empresa);
    $oc = agenda_ocupacoes_do_dia($empresa['id'], $dataYmd);
    $qtd = agenda_qtd_profissionais($empresa['id']);
    [$ah, $am] = array_map('intval', explode(':', $cfg['abertura']));
    [$fh, $fm] = array_map('intval', explode(':', $cfg['fechamento']));
    $ocupados = [];
    for ($m = $ah * 60 + $am; $m + $duracao <= $fh * 60 + $fm; $m += $cfg['intervalo']) {
        if (!agenda_intervalo_livre($oc, $m, $duracao, $profissionalId, $qtd)) {
            $ocupados[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }
    }
    return $ocupados;
}

// Validação completa de um pedido de agendamento. Retorna '' se ok, ou a mensagem de erro.
function agenda_validar($empresa, $dataHora, $duracao, $profissionalId = null, $ignorarAgendamentoId = null) {
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', (string)$dataHora) ?: DateTime::createFromFormat('Y-m-d H:i', (string)$dataHora);
    if (!$dt) return 'Data/hora inválida. Escolha o dia e o horário no calendário.';
    if ($dt->getTimestamp() < time()) return 'Esse horário já passou. Escolha outro.';
    $cfg = agenda_config($empresa);
    if ($cfg['antecedencia_dias'] > 0 && $dt->getTimestamp() > strtotime('+' . $cfg['antecedencia_dias'] . ' days')) {
        return 'Só aceitamos agendamentos com até ' . $cfg['antecedencia_dias'] . ' dias de antecedência.';
    }
    $diasAbertos = array_map('intval', explode(',', $cfg['dias']));
    if (!in_array((int)$dt->format('w'), $diasAbertos, true)) return 'Não atendemos nesse dia da semana.';
    $ini = (int)$dt->format('G') * 60 + (int)$dt->format('i');
    [$ah, $am] = array_map('intval', explode(':', $cfg['abertura']));
    [$fh, $fm] = array_map('intval', explode(':', $cfg['fechamento']));
    if ($ini < $ah * 60 + $am || $ini + $duracao > $fh * 60 + $fm) {
        return 'Fora do horário de atendimento (' . $cfg['abertura'] . ' às ' . $cfg['fechamento'] . ').';
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
