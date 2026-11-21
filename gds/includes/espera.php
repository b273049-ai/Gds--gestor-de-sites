<?php
// ============================================================
// LISTA DE ESPERA e LEMBRETE DE RETORNO
// - Dia lotado: o cliente entra na lista. Quando alguém cancela, o primeiro
//   da lista daquele dia é avisado no WhatsApp com o link para agendar.
// - Retorno: X dias depois do atendimento (definido em cada serviço), o
//   cliente recebe "que tal agendar o próximo?" — só se ainda não voltou.
// ============================================================

function espera_link_agendar($empresa) {
    return (function_exists('wa_url_publica') ? wa_url_publica($empresa) : '') . '#agendar';
}

// Avisa o próximo da lista de espera de um dia (chamado quando um agendamento é cancelado)
function espera_avisar($empresa, $data, $servicoId = null) {
    global $pdo;
    if (isset($empresa['lista_espera']) && $empresa['lista_espera'] !== null && !pg_bool($empresa['lista_espera'])) return null;
    $data = substr((string)$data, 0, 10);
    if ($data < date('Y-m-d')) return null;
    try {
        $st = $pdo->prepare("SELECT * FROM lista_espera WHERE empresa_id=? AND data=? AND status='aguardando'
            ORDER BY (servico_id IS NOT DISTINCT FROM ?) DESC, id LIMIT 1");
        $st->execute([$empresa['id'], $data, $servicoId]);
        $e = $st->fetch();
    } catch (PDOException $ex) { return null; }
    if (!$e) return null;
    $pdo->prepare("UPDATE lista_espera SET status='avisado', avisado_em=NOW() WHERE id=?")->execute([$e['id']]);
    $dias = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
    $ts = strtotime($data);
    wa_notificar_cliente($empresa, 'lista_espera_vaga', $e['telefone'],
        ['nome' => explode(' ', trim($e['nome']))[0], 'data' => $dias[(int)date('w', $ts)] . ', ' . date('d/m', $ts), 'link' => espera_link_agendar($empresa)], 'espera', $e['id']);
    return $e;
}

// Rotina (cron): lembrete de retorno
function retorno_rotina($limite = 40) {
    global $pdo;
    try {
        $lista = $pdo->query("SELECT a.id, a.empresa_id, a.data_hora, c.nome AS cliente_nome, c.telefone, s.nome AS servico_nome, s.retorno_dias
            FROM agendamentos a JOIN servicos s ON s.id = a.servico_id JOIN clientes c ON c.id = a.cliente_id
            WHERE a.status='concluido' AND s.retorno_dias > 0 AND a.retorno_enviado_em IS NULL
              AND a.data_hora::date + s.retorno_dias <= CURRENT_DATE AND a.data_hora > NOW() - INTERVAL '180 days'
              AND COALESCE(c.telefone,'') <> ''
              AND NOT EXISTS (SELECT 1 FROM agendamentos b WHERE b.cliente_id=a.cliente_id AND b.empresa_id=a.empresa_id AND b.data_hora > a.data_hora AND b.status <> 'cancelado')
            ORDER BY a.data_hora LIMIT " . (int)$limite)->fetchAll();
    } catch (PDOException $e) { return ['retorno' => 'sem tabela']; }
    $n = 0;
    foreach ($lista as $a) {
        $pdo->prepare("UPDATE agendamentos SET retorno_enviado_em=NOW() WHERE id=?")->execute([$a['id']]);
        $emp = wa_carregar_empresa($a['empresa_id']);
        if (!$emp) continue;
        $dias = (int)floor((time() - strtotime($a['data_hora'])) / 86400);
        if (wa_notificar_cliente($emp, 'retorno_lembrete', $a['telefone'], ['nome' => explode(' ', trim($a['cliente_nome']))[0], 'servico' => mb_strtolower($a['servico_nome']),
            'dias' => (string)$dias, 'link' => espera_link_agendar($emp)], 'agendamento', $a['id']) !== null) $n++;
    }
    return ['retorno' => $lista ? count($lista) . " lembrete(s), $n enviado(s)" : 'nada a fazer'];
}
