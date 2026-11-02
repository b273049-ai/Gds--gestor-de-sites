<?php
// ============================================================
// CENTRAL DE INTELIGÊNCIA DO ADMIN
// O painel deixa de só mostrar números e passa a dizer O QUE FAZER:
//   - saúde de cada cliente (risco de cancelar), com motivo e ação pronta
//   - próximas ações da plataforma, ordenadas pelo dinheiro em jogo
//   - previsão de receita para 30/60/90 dias
//   - briefing do dia (com IA quando houver chave; senão, por regras)
// Tudo funciona sem chave de IA. A IA só escreve o briefing com mais contexto.
// ============================================================

function intel_dinheiro($v) { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }

// Saúde de cada empresa cliente: 0 a 100 (quanto menor, maior o risco de cancelar)
function intel_saude_empresas($limite = 500) {
    global $pdo;
    try {
        $linhas = $pdo->query("
            SELECT e.id, e.nome, e.slug, e.status, e.vencimento, e.whatsapp, e.criado_em, e.dominio_personalizado, e.dominio_status, e.wa_modo, e.wa_status,
                   e.modulos, e.pix_ativo, e.segmento_id,
                   p.nome AS plano_nome, COALESCE(p.valor / CASE WHEN p.ciclo='anual' THEN 12 ELSE 1 END, 0) AS mensal,
                   s.publicado,
                   (SELECT MAX(u.ultimo_login) FROM usuarios u WHERE u.empresa_id = e.id) AS ultimo_login,
                   (SELECT COUNT(*) FROM site_eventos se WHERE se.empresa_id = e.id AND se.tipo='visita' AND se.criado_em > NOW() - INTERVAL '30 days') AS visitas,
                   (SELECT COUNT(*) FROM agendamentos a WHERE a.empresa_id = e.id AND a.criado_em > NOW() - INTERVAL '30 days') AS agendamentos,
                   (SELECT COUNT(*) FROM pedidos pe WHERE pe.empresa_id = e.id AND pe.criado_em > NOW() - INTERVAL '30 days') AS pedidos,
                   (SELECT COUNT(*) FROM leads l WHERE l.empresa_id = e.id AND l.criado_em > NOW() - INTERVAL '30 days') AS contatos,
                   (SELECT COUNT(*) FROM pagamentos_assinaturas pa WHERE pa.empresa_id = e.id AND pa.status IN ('pendente','atrasado') AND pa.vencimento < CURRENT_DATE) AS atrasadas
            FROM empresas e LEFT JOIN planos p ON p.id = e.plano_id LEFT JOIN sites s ON s.empresa_id = e.id
            WHERE e.status <> 'cancelado' ORDER BY e.id DESC LIMIT " . (int)$limite)->fetchAll();
    } catch (PDOException $e) { return []; }
    $out = [];
    foreach ($linhas as $l) {
        $pts = 0; $bom = []; $ruim = []; $sugestoes = [];
        $dias = $l['ultimo_login'] ? (int)floor((time() - strtotime($l['ultimo_login'])) / 86400) : null;
        $idade = (int)floor((time() - strtotime($l['criado_em'])) / 86400);
        // Uso do painel
        if ($dias !== null && $dias <= 7) { $pts += 25; $bom[] = 'usou o painel nesta semana'; }
        elseif ($dias !== null && $dias <= 30) { $pts += 12; $ruim[] = "não entra no painel há $dias dias"; }
        else { $ruim[] = $dias === null ? 'nunca entrou no painel' : "não entra no painel há $dias dias"; }
        // Site no ar e visitas
        if (pg_bool($l['publicado'])) { $pts += 15; } else { $ruim[] = 'site ainda em rascunho'; }
        $vis = (int)$l['visitas'];
        if ($vis >= 100) { $pts += 15; $bom[] = "$vis visitas no mês"; } elseif ($vis >= 20) { $pts += 8; $bom[] = "$vis visitas no mês"; }
        elseif (pg_bool($l['publicado'])) $ruim[] = $vis ? "só $vis visitas no mês" : 'nenhuma visita no mês';
        // Resultado que o site entrega (o que faz o cliente renovar)
        $res = (int)$l['agendamentos'] + (int)$l['pedidos'] + (int)$l['contatos'];
        if ($res >= 20) { $pts += 20; $bom[] = "$res agendamentos/pedidos/contatos pelo site"; }
        elseif ($res >= 5) { $pts += 12; $bom[] = "$res agendamentos/pedidos/contatos pelo site"; }
        elseif ($res >= 1) { $pts += 5; $ruim[] = "só $res resultado(s) pelo site no mês"; }
        else $ruim[] = 'o site não trouxe nenhum agendamento, pedido ou contato no mês';
        // Pagamento
        if ((int)$l['atrasadas'] === 0) $pts += 15; else $ruim[] = (int)$l['atrasadas'] . ' cobrança(s) atrasada(s)';
        // Recursos que prendem o cliente
        $waAtivo = ($l['wa_modo'] ?? 'plataforma') !== 'desligado';
        if ($waAtivo) $pts += 5; else $sugestoes[] = 'ligar o WhatsApp automático (confirmação e lembrete)';
        $dominio = !empty($l['dominio_personalizado']) && ($l['dominio_status'] ?? '') === 'ativo';
        if ($dominio) $pts += 5; else $sugestoes[] = 'oferecer domínio próprio (.com.br)';
        if (!pg_bool($l['pix_ativo'] ?? false)) $sugestoes[] = 'ativar Pix com sinal (reduz faltas)';
        if ($idade < 14) { $pts = max($pts, 55); $bom[] = 'cliente novo (primeiras 2 semanas)'; }
        $pts = max(0, min(100, $pts));
        $risco = $pts < 40 ? 'alto' : ($pts < 65 ? 'medio' : 'baixo');
        $primeiro = explode(' ', trim($l['nome']))[0];
        $msg = $risco === 'alto'
            ? "Oi! Tudo bem por aí na {$l['nome']}? Passando para ver como está o site e se posso ajudar em alguma coisa — às vezes um ajuste pequeno já traz mais clientes. Quer que eu dê uma olhada com você?"
            : "Oi! Tudo certo na {$l['nome']}? Separei uma ideia rápida para o site de vocês trazer mais clientes: " . ($sugestoes[0] ?? 'divulgar o link na bio do Instagram') . '. Quer que eu ative?';
        // Só cliente ativo conta como receita recorrente (pendente/suspenso ainda não paga)
        $out[] = ['id' => (int)$l['id'], 'nome' => $l['nome'], 'slug' => $l['slug'], 'plano' => $l['plano_nome'], 'mensal' => $l['status'] === 'ativo' ? (float)$l['mensal'] : 0.0, 'status' => $l['status'],
            'pontos' => $pts, 'risco' => $risco, 'bom' => $bom, 'ruim' => $ruim, 'sugestoes' => array_slice($sugestoes, 0, 2),
            'whatsapp' => preg_replace('/\D/', '', (string)$l['whatsapp']), 'mensagem' => $msg, 'resultados' => $res, 'visitas' => $vis, 'dias_sem_login' => $dias];
    }
    usort($out, fn($a, $b) => $a['pontos'] <=> $b['pontos']);
    return $out;
}

// Próximas ações: tudo que pede atenção hoje, do mais valioso para o menos
function intel_acoes($saude = null) {
    global $pdo;
    $saude = $saude ?? intel_saude_empresas();
    $acoes = [];
    $q = function ($sql) use ($pdo) { try { return $pdo->query($sql)->fetch(); } catch (PDOException $e) { return null; } };

    if (($r = $q("SELECT COUNT(*) c, COALESCE(SUM(valor),0) v FROM pagamentos_assinaturas WHERE status IN ('pendente','atrasado') AND vencimento < CURRENT_DATE")) && $r['c'] > 0)
        $acoes[] = ['peso' => (float)$r['v'] * 1.0, 'icone' => '💸', 'titulo' => "Cobrar {$r['c']} mensalidade(s) atrasada(s)", 'detalhe' => intel_dinheiro($r['v']) . ' parados', 'link' => 'financeiro.php'];
    if (($r = $q("SELECT COUNT(*) c FROM prospects WHERE quer_site_em IS NOT NULL AND status NOT IN ('fechado','descartado')")) && $r['c'] > 0)
        $acoes[] = ['peso' => 900 + $r['c'], 'icone' => '🙋', 'titulo' => "{$r['c']} empresa(s) clicaram \"Quero este site\"", 'detalhe' => 'chame agora, é venda quente', 'link' => 'prospeccao.php?aba=leads&status=negociando'];
    if (($r = $q("SELECT COUNT(*) c FROM prospects WHERE previa_aberta_em > NOW() - INTERVAL '3 days' AND status NOT IN ('fechado','descartado','negociando')")) && $r['c'] > 0)
        $acoes[] = ['peso' => 600 + $r['c'], 'icone' => '👀', 'titulo' => "{$r['c']} empresa(s) abriram a prévia nos últimos 3 dias", 'detalhe' => 'é a melhor hora de chamar', 'link' => 'prospeccao.php#fila'];
    if (($r = $q("SELECT COUNT(*) c FROM prospects WHERE respondeu_em IS NOT NULL AND status='respondeu'")) && $r['c'] > 0)
        $acoes[] = ['peso' => 500 + $r['c'], 'icone' => '💬', 'titulo' => "{$r['c']} lead(s) responderam e esperam você", 'detalhe' => 'use as respostas prontas', 'link' => 'prospeccao.php?aba=leads&status=respondeu'];
    $alto = array_values(array_filter($saude, fn($s) => $s['risco'] === 'alto'));
    if ($alto) {
        $emRisco = array_sum(array_column($alto, 'mensal'));
        $acoes[] = ['peso' => $emRisco * 3, 'icone' => '🚨', 'titulo' => count($alto) . ' cliente(s) com risco alto de cancelar', 'detalhe' => intel_dinheiro($emRisco) . '/mês em jogo — ' . $alto[0]['nome'] . ($alto[0]['ruim'] ? ': ' . $alto[0]['ruim'][0] : ''), 'link' => 'empresas.php?saude=alto'];
    }
    if (($r = $q("SELECT COUNT(*) c FROM sites s JOIN empresas e ON e.id=s.empresa_id WHERE s.publicado=false AND e.status='ativo'")) && $r['c'] > 0)
        $acoes[] = ['peso' => 300 + $r['c'], 'icone' => '🧱', 'titulo' => "{$r['c']} site(s) de cliente ainda em rascunho", 'detalhe' => 'cliente pagando sem site no ar cancela', 'link' => 'empresas.php'];
    if (($r = $q("SELECT COUNT(*) c FROM whatsapp_mensagens WHERE status='erro' AND criado_em > NOW() - INTERVAL '7 days'")) && $r['c'] > 0)
        $acoes[] = ['peso' => 200 + $r['c'], 'icone' => '📵', 'titulo' => "{$r['c']} mensagem(ns) de WhatsApp falharam na semana", 'detalhe' => 'confira a conexão', 'link' => 'whatsapp.php'];
    if (function_exists('wa_api') && wa_api()['tipo'] === 'ponte' && function_exists('wa_ponte_online') && !wa_ponte_online())
        $acoes[] = ['peso' => 400, 'icone' => '🔌', 'titulo' => 'O conector de WhatsApp está desligado', 'detalhe' => 'as mensagens ficam na fila até ele voltar', 'link' => 'whatsapp.php'];
    if (($r = $q("SELECT COUNT(*) c FROM prospects WHERE NOT tem_site AND status='novo' AND passo=0 AND COALESCE(whatsapp,'')<>''")) && $r['c'] > 0)
        $acoes[] = ['peso' => 100, 'icone' => '🎯', 'titulo' => "{$r['c']} empresa(s) sem site prontas para chamar", 'detalhe' => 'abra a fila do dia', 'link' => 'prospeccao.php#fila'];
    if (!ia_configurada())
        $acoes[] = ['peso' => 50, 'icone' => '✨', 'titulo' => 'Ligue a IA grátis (Google Gemini)', 'detalhe' => 'libera Estúdio IA, marketing e copiloto com mais qualidade', 'link' => 'configuracoes.php'];
    usort($acoes, fn($a, $b) => $b['peso'] <=> $a['peso']);
    return $acoes;
}

// Previsão de receita: mensalidades dos clientes ativos, pesadas pelo risco de cada um,
// mais o que deve fechar da prospecção (pela taxa de fechamento real da base)
function intel_previsao($saude = null) {
    global $pdo;
    $saude = $saude ?? intel_saude_empresas();
    $mrr = array_sum(array_column($saude, 'mensal'));
    $retencao = ['baixo' => 0.97, 'medio' => 0.85, 'alto' => 0.55];
    $esperado = 0;
    foreach ($saude as $s) $esperado += $s['mensal'] * $retencao[$s['risco']];
    $ticket = count($saude) ? $mrr / max(1, count(array_filter($saude, fn($s) => $s['mensal'] > 0))) : 0;
    $pipe = ['negociando' => 0, 'taxa' => 0.0, 'novos' => 0.0];
    try {
        $r = $pdo->query("SELECT COUNT(*) FILTER (WHERE status='negociando') neg, COUNT(*) FILTER (WHERE status='fechado') fech,
                COUNT(*) FILTER (WHERE status IN ('negociando','fechado','descartado') AND passo > 0) decididos FROM prospects")->fetch();
        $pipe['negociando'] = (int)$r['neg'];
        $pipe['taxa'] = $r['decididos'] >= 5 ? (int)$r['fech'] / (int)$r['decididos'] : 0.3; // até ter histórico, 30%
        $pipe['novos'] = $pipe['negociando'] * $pipe['taxa'];
    } catch (PDOException $e) {}
    $novaReceita = $pipe['novos'] * $ticket;
    return ['mrr' => $mrr, 'mes1' => $esperado + $novaReceita, 'mes2' => $esperado * 0.97 + $novaReceita * 1.6, 'mes3' => $esperado * 0.94 + $novaReceita * 2.1,
            'em_risco' => $mrr - $esperado, 'pipeline' => $pipe, 'ticket' => $ticket];
}

// Briefing do dia: 4 a 5 frases do que importa hoje. Com IA (guardado por dia) ou por regras.
function intel_briefing($acoes, $prev, $saude, $forcar = false) {
    $hoje = date('Y-m-d');
    $cache = json_decode(get_config('intel_briefing', ''), true);
    if (!$forcar && ($cache['dia'] ?? '') === $hoje && !empty($cache['itens'])) return $cache;
    $regras = [];
    foreach (array_slice($acoes, 0, 3) as $a) $regras[] = $a['icone'] . ' ' . $a['titulo'] . ' — ' . $a['detalhe'] . '.';
    $altos = count(array_filter($saude, fn($s) => $s['risco'] === 'alto'));
    $regras[] = '📈 Receita prevista para os próximos 30 dias: ' . intel_dinheiro($prev['mes1']) . ($prev['em_risco'] > 0 ? ' (' . intel_dinheiro($prev['em_risco']) . ' em risco por clientes desanimados).' : '.');
    if (!$altos && $saude) $regras[] = '💚 Nenhum cliente em risco alto agora. Bom momento para oferecer domínio e Pix (venda extra).';
    $res = ['dia' => $hoje, 'itens' => $regras, 'fonte' => 'regras'];
    if (ia_configurada()) {
        $dados = "Ações pendentes:\n" . implode("\n", array_map(fn($a) => "- {$a['titulo']} ({$a['detalhe']})", array_slice($acoes, 0, 8)))
               . "\nReceita recorrente: " . intel_dinheiro($prev['mrr']) . "/mês. Prevista 30 dias: " . intel_dinheiro($prev['mes1']) . ". Em risco: " . intel_dinheiro($prev['em_risco'])
               . "\nClientes em risco alto: $altos de " . count($saude) . ". Leads negociando: {$prev['pipeline']['negociando']}.";
        $r = chamar_ia("Você é o braço direito do dono de uma plataforma de sites para pequenos negócios. Com os dados abaixo, escreva o briefing do dia: "
            . "4 frases curtas, cada uma começando com um emoji, dizendo o que fazer primeiro e por quê (pense em dinheiro e em não perder clientes). "
            . "Use só os números dados, sem inventar. Português do Brasil. Uma frase por linha, sem título.\n\n$dados", 500, 30, 0.5, null, ['esforco' => 'low']);
        if ($r['ok']) {
            $itens = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $r['texto']))));
            if (count($itens) >= 2) $res = ['dia' => $hoje, 'itens' => array_slice($itens, 0, 5), 'fonte' => 'ia'];
        }
    }
    set_config('intel_briefing', json_encode($res, JSON_UNESCAPED_UNICODE));
    return $res;
}
