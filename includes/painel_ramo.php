<?php
// ============================================================
// DASHBOARD DA EMPRESA POR RAMO — os números que cada tipo de negócio olha
// todo dia (como nos sistemas famosos de cada ramo):
//   delivery  → hoje x ontem, pedidos por hora, mais vendidos, tempo, recorrência
//   agenda    → ocupação do dia, previsto, faltas, caixa do dia
//   orçamento → funil do mês e taxa de aprovação
// ============================================================

function ramo_delivery($empresaId) {
    global $pdo;
    $q = function ($sql) use ($pdo, $empresaId) { $s = $pdo->prepare($sql); $s->execute([$empresaId]); return $s->fetch(); };
    $hoje = $q("SELECT COUNT(*) FILTER (WHERE status <> 'cancelado') n, COALESCE(SUM(valor) FILTER (WHERE status <> 'cancelado'), 0) v FROM pedidos WHERE empresa_id=? AND tipo='delivery' AND criado_em::date = CURRENT_DATE");
    $ontem = $q("SELECT COUNT(*) FILTER (WHERE status <> 'cancelado') n, COALESCE(SUM(valor) FILTER (WHERE status <> 'cancelado'), 0) v FROM pedidos WHERE empresa_id=? AND tipo='delivery' AND criado_em::date = CURRENT_DATE - 1");
    $porHora = array_fill(0, 24, 0);
    $s = $pdo->prepare("SELECT EXTRACT(HOUR FROM criado_em)::int h, COUNT(*) n FROM pedidos WHERE empresa_id=? AND tipo='delivery' AND status <> 'cancelado' AND criado_em > NOW() - INTERVAL '7 days' GROUP BY 1");
    $s->execute([$empresaId]);
    foreach ($s->fetchAll() as $l) $porHora[(int)$l['h']] = (int)$l['n'];
    // Mais vendidos da semana (os itens ficam no JSON do pedido)
    $s = $pdo->prepare("SELECT itens FROM pedidos WHERE empresa_id=? AND tipo='delivery' AND status <> 'cancelado' AND criado_em > NOW() - INTERVAL '7 days' ORDER BY id DESC LIMIT 800");
    $s->execute([$empresaId]);
    $top = [];
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $js) {
        foreach ((array)json_decode((string)$js, true) as $i) {
            $nome = preg_replace('/\s*\(.*$/u', '', (string)($i['nome'] ?? '')); // "Pizza (meio a meio...)" conta como "Pizza"
            if ($nome === '') continue;
            $top[$nome]['qtd'] = ($top[$nome]['qtd'] ?? 0) + (int)($i['qtd'] ?? 1);
            $top[$nome]['valor'] = ($top[$nome]['valor'] ?? 0) + (float)($i['preco'] ?? 0) * (int)($i['qtd'] ?? 1);
        }
    }
    uasort($top, fn($a, $b) => $b['qtd'] <=> $a['qtd']);
    $rec = $q("SELECT COUNT(DISTINCT cliente_id) total, COUNT(DISTINCT cliente_id) FILTER (WHERE n > 1) voltaram FROM
        (SELECT cliente_id, COUNT(*) n FROM pedidos WHERE empresa_id=? AND tipo='delivery' AND status <> 'cancelado' AND criado_em > NOW() - INTERVAL '30 days' AND cliente_id IS NOT NULL GROUP BY cliente_id) x");
    $tempo = null;
    try {
        $t = $q("SELECT AVG(EXTRACT(EPOCH FROM (concluido_em - criado_em)) / 60) m FROM pedidos WHERE empresa_id=? AND tipo='delivery' AND concluido_em IS NOT NULL AND criado_em > NOW() - INTERVAL '7 days'");
        $tempo = $t['m'] !== null ? (int)round($t['m']) : null;
    } catch (PDOException $e) {}
    return ['hoje' => $hoje, 'ontem' => $ontem, 'por_hora' => $porHora, 'top' => array_slice($top, 0, 5, true), 'tempo' => $tempo,
            'recorrencia' => $rec['total'] ? (int)round(100 * $rec['voltaram'] / $rec['total']) : null, 'clientes_mes' => (int)$rec['total']];
}

function ramo_agenda($empresa) {
    global $pdo;
    $id = (int)$empresa['id'];
    $s = $pdo->prepare("SELECT COUNT(*) FILTER (WHERE a.status NOT IN ('cancelado')) marcados, COUNT(*) FILTER (WHERE a.status='concluido') feitos,
            COUNT(*) FILTER (WHERE a.status='faltou') faltas, COALESCE(SUM(COALESCE(a.valor, s.preco)) FILTER (WHERE a.status NOT IN ('cancelado','faltou')), 0) previsto,
            COALESCE(SUM(COALESCE(s.duracao_minutos, 30)) FILTER (WHERE a.status NOT IN ('cancelado','faltou')), 0) minutos
        FROM agendamentos a LEFT JOIN servicos s ON s.id = a.servico_id WHERE a.empresa_id=? AND a.data_hora::date = CURRENT_DATE");
    $s->execute([$id]); $hoje = $s->fetch();
    // Capacidade: minutos abertos no dia × profissionais ativos (ou 1)
    $profs = (int)$pdo->query("SELECT COUNT(*) FROM profissionais WHERE empresa_id=$id AND ativo=true")->fetchColumn() ?: 1;
    $ab = strtotime($empresa['horario_abertura'] ?: '09:00'); $fe = strtotime($empresa['horario_fechamento'] ?: '18:00');
    $capacidade = max(60, ($fe - $ab) / 60) * $profs;
    $s = $pdo->prepare("SELECT COUNT(*) FILTER (WHERE status IN ('concluido','faltou')) base, COUNT(*) FILTER (WHERE status='faltou') faltas
        FROM agendamentos WHERE empresa_id=? AND data_hora > NOW() - INTERVAL '30 days' AND data_hora < NOW()");
    $s->execute([$id]); $f = $s->fetch();
    $caixa = null;
    try {
        $s = $pdo->prepare("SELECT COUNT(*) n, COALESCE(SUM(total), 0) v FROM comandas WHERE empresa_id=? AND criado_em::date = CURRENT_DATE");
        $s->execute([$id]); $caixa = $s->fetch();
    } catch (PDOException $e) {}
    $s = $pdo->prepare("SELECT c.nome, c.telefone FROM clientes c WHERE c.empresa_id=? AND c.nascimento IS NOT NULL
        AND EXTRACT(MONTH FROM c.nascimento) = EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(DAY FROM c.nascimento) = EXTRACT(DAY FROM CURRENT_DATE) LIMIT 10");
    try { $s->execute([$id]); $aniver = $s->fetchAll(); } catch (PDOException $e) { $aniver = []; }
    return ['hoje' => $hoje, 'ocupacao' => (int)min(100, round(100 * $hoje['minutos'] / $capacidade)), 'faltas_pct' => $f['base'] ? (int)round(100 * $f['faltas'] / $f['base']) : null,
            'caixa' => $caixa, 'aniversariantes' => $aniver];
}

function ramo_orcamento($empresaId) {
    global $pdo;
    $s = $pdo->prepare("SELECT COUNT(*) recebidos, COUNT(*) FILTER (WHERE status IN ('orcado','aprovado','em_andamento','concluido')) respondidos,
            COUNT(*) FILTER (WHERE status IN ('aprovado','em_andamento','concluido')) aprovados,
            COALESCE(SUM(valor) FILTER (WHERE status IN ('aprovado','em_andamento','concluido')), 0) valor_aprovado,
            COALESCE(SUM(valor) FILTER (WHERE status = 'orcado'), 0) em_negociacao, COUNT(*) FILTER (WHERE status='novo') sem_resposta
        FROM pedidos WHERE empresa_id=? AND COALESCE(tipo,'') <> 'delivery' AND criado_em > NOW() - INTERVAL '30 days'");
    $s->execute([$empresaId]); $r = $s->fetch();
    $r['taxa'] = $r['respondidos'] ? (int)round(100 * $r['aprovados'] / $r['respondidos']) : null;
    return $r;
}
