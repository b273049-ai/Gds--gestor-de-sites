<?php
// ============================================================
// LIGAÇÕES PARA QUEM JÁ É CLIENTE (Admin → Ligações → "Já clientes")
// Fila por motivo, do mais urgente para o menos:
//   retorno combinado → cobrança em atraso → mensalidade vencendo (5 dias) →
//   cliente novo que ainda não configurou → sumido do painel (21+ dias) → relacionamento (60+ dias sem ligação)
// O roteiro de cada motivo usa os números reais do cliente (visitas do site, agendamentos/pedidos/contatos
// dos últimos 30 dias, cobrança em aberto) e termina com uma pergunta. Mesmas regras: honesto, sem inventar
// números e sem prometer desconto que não existe.
// ============================================================
require_once __DIR__ . '/prospeccao_ligacoes.php';   // lig_horario, lig_quando, lig_duracao, LIG_MAX_TENTATIVAS

// resultado => [rótulo, emoji, atendeu?]
const CLI_RESULTADOS = [
    'nao_atendeu'   => ['Não atendeu', '📵', false],
    'ocupado'       => ['Ocupado / caixa postal', '⏳', false],
    'numero_errado' => ['Número errado', '❌', false],
    'retornar'      => ['Pediu para ligar depois', '🕑', true],
    'tudo_certo'    => ['Tudo certo', '✅', true],
    'vai_pagar'     => ['Combinou o pagamento', '💳', true],
    'ajuda'         => ['Precisa de ajuda (anotar)', '🛠', true],
    'indicou'       => ['Indicou alguém', '🎁', true],
    'cancelar'      => ['Quer cancelar', '⚠️', true],
];
const CLI_MOTIVOS = [
    'retorno' => '🕑 Retorno combinado', 'cobranca' => '💳 Mensalidade em atraso', 'renovacao' => '📅 Mensalidade vencendo',
    'ativacao' => '🚀 Cliente novo: ajudar a configurar', 'sumido' => '😴 Sumido do painel', 'relacionamento' => '🤝 Relacionamento',
];

// Fila de clientes. Cada linha traz o motivo e os dados para o roteiro.
function cli_fila($limite = 60) {
    global $pdo;
    $st = $pdo->prepare("WITH c AS (
        SELECT e.*, COALESCE(s.publicado, FALSE) AS publicado,
            (SELECT MAX(u.ultimo_login) FROM usuarios u WHERE u.empresa_id = e.id) AS login,
            (SELECT MIN(pa.vencimento) FROM pagamentos_assinaturas pa WHERE pa.empresa_id = e.id
                AND (pa.status = 'atrasado' OR (pa.status = 'pendente' AND pa.vencimento < CURRENT_DATE))) AS atraso_desde,
            (SELECT MIN(pa.vencimento) FROM pagamentos_assinaturas pa WHERE pa.empresa_id = e.id
                AND pa.status = 'pendente' AND pa.vencimento BETWEEN CURRENT_DATE AND CURRENT_DATE + 5) AS vence_em
        FROM empresas e LEFT JOIN sites s ON s.empresa_id = e.id
        WHERE COALESCE(e.status, 'ativo') <> 'cancelado'
          AND length(regexp_replace(COALESCE(NULLIF(e.whatsapp, ''), e.telefone, ''), '\\D', '', 'g')) >= 10
    ), p AS (
        SELECT c.*, CASE
            WHEN ligar_em IS NOT NULL THEN 'retorno'
            WHEN atraso_desde IS NOT NULL THEN 'cobranca'
            WHEN vence_em IS NOT NULL THEN 'renovacao'
            WHEN criado_em > NOW() - INTERVAL '30 days' AND (NOT publicado OR login IS NULL OR login < NOW() - INTERVAL '7 days') THEN 'ativacao'
            WHEN login IS NULL OR login < NOW() - INTERVAL '21 days' THEN 'sumido'
            WHEN ultima_ligacao_em IS NULL OR ultima_ligacao_em < NOW() - INTERVAL '60 days' THEN 'relacionamento'
            END AS motivo
        FROM c
        WHERE (ligar_em IS NULL OR ligar_em <= NOW() + INTERVAL '15 minutes')
          AND COALESCE(ultimo_resultado_lig, '') <> 'numero_errado'
          -- já ligou hoje (e não marcou retorno): fica para amanhã
          AND (ligar_em IS NOT NULL OR ultima_ligacao_em IS NULL OR ultima_ligacao_em < NOW() - INTERVAL '20 hours')
    )
    SELECT * FROM p WHERE motivo IS NOT NULL
    ORDER BY CASE motivo WHEN 'retorno' THEN 0 WHEN 'cobranca' THEN 1 WHEN 'renovacao' THEN 2 WHEN 'ativacao' THEN 3 WHEN 'sumido' THEN 4 ELSE 5 END,
        ligar_em NULLS LAST, atraso_desde NULLS LAST, vence_em NULLS LAST, id
    LIMIT ?");
    $st->execute([max(1, min(200, (int)$limite))]);
    return $st->fetchAll();
}

function cli_buscar($id) {
    foreach (cli_fila(200) as $c) if ((int)$c['id'] === (int)$id) return $c;
    // Fora da fila (ex.: aberto pelo link do aviso): carrega com os mesmos campos
    global $pdo;
    $st = $pdo->prepare("SELECT e.*, COALESCE(s.publicado, FALSE) AS publicado, (SELECT MAX(u.ultimo_login) FROM usuarios u WHERE u.empresa_id = e.id) AS login,
            (SELECT MIN(pa.vencimento) FROM pagamentos_assinaturas pa WHERE pa.empresa_id = e.id AND (pa.status = 'atrasado' OR (pa.status = 'pendente' AND pa.vencimento < CURRENT_DATE))) AS atraso_desde,
            (SELECT MIN(pa.vencimento) FROM pagamentos_assinaturas pa WHERE pa.empresa_id = e.id AND pa.status = 'pendente' AND pa.vencimento BETWEEN CURRENT_DATE AND CURRENT_DATE + 5) AS vence_em,
            'relacionamento' AS motivo
        FROM empresas e LEFT JOIN sites s ON s.empresa_id = e.id WHERE e.id = ?");
    $st->execute([(int)$id]);
    $c = $st->fetch();
    if (!$c) return null;
    if ($c['atraso_desde']) $c['motivo'] = 'cobranca'; elseif ($c['vence_em']) $c['motivo'] = 'renovacao';
    return $c;
}

function cli_motivo_txt($c) {
    $m = $c['motivo'] ?? 'relacionamento';
    $t = CLI_MOTIVOS[$m] ?? CLI_MOTIVOS['relacionamento'];
    if ($m === 'retorno' && !empty($c['ligar_em'])) $t .= ' ' . lig_quando($c['ligar_em']);
    if ($m === 'cobranca' && !empty($c['atraso_desde'])) $t .= ' desde ' . date('d/m', strtotime($c['atraso_desde']));
    if ($m === 'renovacao' && !empty($c['vence_em'])) $t .= ' (' . date('d/m', strtotime($c['vence_em'])) . ')';
    if ($m === 'sumido') $t .= $c['login'] ? ' há ' . max(1, (int)floor((time() - strtotime($c['login'])) / 86400)) . ' dias' : ' (nunca entrou)';
    return $t;
}

function cli_numeros($c) {
    $wa = function_exists('wa_numero') ? wa_numero($c['whatsapp'] ?: $c['telefone']) : '';
    [$exib, $cel] = prosp_telefone($c['whatsapp'] ?: $c['telefone']);
    $dig = preg_replace('/\D/', '', (string)($c['whatsapp'] ?: $c['telefone']));
    if ($dig !== '' && !str_starts_with($dig, '55')) $dig = '55' . ltrim($dig, '0');
    $wa = $wa ?: $cel;
    return ['exibir' => $exib ?: (string)$c['telefone'], 'whatsapp' => $wa, 'tel' => $dig ? 'tel:+' . $dig : '', 'wa_link' => $wa ? 'https://wa.me/' . $wa : ''];
}

// Números reais do cliente nos últimos 30 dias (para mostrar valor e para a conversa)
function cli_dados($c) {
    global $pdo;
    $id = (int)$c['id'];
    $um = function ($sql) use ($pdo, $id) { try { $s = $pdo->prepare($sql); $s->execute([$id]); return (int)$s->fetchColumn(); } catch (PDOException $e) { return 0; } };
    $d = [
        'visitas' => $um("SELECT COUNT(*) FROM site_eventos WHERE empresa_id=? AND tipo='visita' AND criado_em > NOW() - INTERVAL '30 days'"),
        'agendamentos' => $um("SELECT COUNT(*) FROM agendamentos WHERE empresa_id=? AND criado_em > NOW() - INTERVAL '30 days' AND status <> 'cancelado'"),
        'pedidos' => $um("SELECT COUNT(*) FROM pedidos WHERE empresa_id=? AND criado_em > NOW() - INTERVAL '30 days' AND status <> 'cancelado'"),
        'contatos' => $um("SELECT COUNT(*) FROM leads WHERE empresa_id=? AND criado_em > NOW() - INTERVAL '30 days'"),
    ];
    $cob = null;
    try {
        $s = $pdo->prepare("SELECT valor, vencimento, link_pagamento FROM pagamentos_assinaturas WHERE empresa_id=? AND status IN ('pendente','atrasado') ORDER BY vencimento LIMIT 1");
        $s->execute([$id]); $cob = $s->fetch() ?: null;
    } catch (PDOException $e) {}
    $plano = null;
    try { $s = $pdo->prepare("SELECT nome, valor FROM planos WHERE id=?"); $s->execute([(int)$c['plano_id']]); $plano = $s->fetch() ?: null; } catch (PDOException $e) {}
    $d['cobranca'] = $cob; $d['plano'] = $plano;
    $d['site'] = prosp_url_base() . '/' . $c['slug'];
    $d['painel'] = prosp_url_base() . '/login.php';
    $d['indica'] = function_exists('prosp_indica_link') ? prosp_indica_link($id) : '';
    return $d;
}

function cli_brl($v) { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }

// Frase com o que o site entregou (só o que é verdade e maior que zero)
function cli_resultado_txt($d) {
    $p = [];
    if ($d['visitas']) $p[] = $d['visitas'] . ($d['visitas'] === 1 ? ' visita' : ' visitas');
    if ($d['agendamentos']) $p[] = $d['agendamentos'] . ($d['agendamentos'] === 1 ? ' agendamento' : ' agendamentos');
    if ($d['pedidos']) $p[] = $d['pedidos'] . ($d['pedidos'] === 1 ? ' pedido' : ' pedidos');
    if ($d['contatos']) $p[] = $d['contatos'] . ($d['contatos'] === 1 ? ' contato' : ' contatos');
    if (!$p) return '';
    $ult = array_pop($p);
    return ($p ? implode(', ', $p) . ' e ' : '') . $ult;
}

function cli_roteiro($c, $d) {
    $vend = trim(get_config('prosp_vendedor', ''));
    $marca = get_config('mkt_marca', '') ?: 'GDS Sites';
    $eu = $vend !== '' ? "Aqui é $vend, da $marca" : "Aqui é da $marca";
    $nome = $c['nome'];
    $res = cli_resultado_txt($d);
    $abertura = ["Oi, tudo bem? Falo com o responsável pela $nome?", "$eu, a empresa do site de vocês. É rapidinho, pode falar agora?"];
    $gancho = $res !== '' ? ["Antes de tudo: nos últimos 30 dias o site de vocês teve $res. Está chegando tudo certinho aí?"]
                          : ['Liguei para saber como está sendo usar o site e se está chegando cliente por ele.'];
    $etapas = [];
    switch ($c['motivo']) {
        case 'cobranca':
            $cob = $d['cobranca'];
            $etapas = [
                ['Abertura', '10 s', 'Tom tranquilo: é um lembrete, não uma cobrança dura.', $abertura],
                ['Valor entregue', '15 s', 'Lembrar o que o site está fazendo por ele ajuda a priorizar o pagamento.', $gancho],
                ['O lembrete', '20 s', 'Fale o valor e a data uma vez, sem rodeio, e pergunte se aconteceu algo.',
                    [$cob ? 'Vi aqui que a mensalidade de ' . date('d/m', strtotime($cob['vencimento'])) . ', de ' . cli_brl($cob['valor']) . ', ficou em aberto.' : 'Vi aqui que a mensalidade ficou em aberto.',
                     'Aconteceu alguma coisa? Às vezes é só o link que se perdeu no WhatsApp.']],
                ['Resolver agora', '20 s', 'Ofereça o caminho mais fácil e combine uma data se ele não puder hoje.',
                    ['Posso te mandar o link do pagamento agora mesmo no WhatsApp?', 'Se hoje não der, qual dia fica melhor para você? Eu anoto aqui.']],
                ['Encerrar', '', 'Agradeça e confirme o que foi combinado.', ['Combinado, então. Obrigado pela parceria! Qualquer coisa no site, me chama.']],
            ];
            break;
        case 'renovacao':
            $cob = $d['cobranca'];
            $etapas = [
                ['Abertura', '10 s', 'Ligação de cuidado: aproveite para saber se está tudo bem.', $abertura],
                ['Como está indo', '30 s', 'Escute: é a melhor hora para descobrir se ele está satisfeito.', array_merge($gancho, ['Tem alguma coisa que você gostaria que funcionasse diferente?'])],
                ['O lembrete', '15 s', '', [$cob ? 'Só para lembrar: a mensalidade vence dia ' . date('d/m', strtotime($cob['vencimento'])) . ' (' . cli_brl($cob['valor']) . ').' : 'Só para lembrar que a mensalidade vence nos próximos dias.',
                    'Quer que eu te mande o link por WhatsApp para já deixar resolvido?']],
                ['Encerrar', '', '', ['Obrigado! Qualquer coisa no site, é só me chamar.']],
            ];
            break;
        case 'ativacao':
            $etapas = [
                ['Abertura', '10 s', 'Ligação de boas-vindas: o objetivo é ele usar o site de verdade.', ["Oi, tudo bem? É da $nome? $eu. Liguei para dar as boas-vindas e te ajudar a deixar o site redondo."]],
                ['Onde ele está', '30 s', 'Descubra o que falta. Cliente que não configura cancela.', ['Você já conseguiu entrar no painel?', !pg_bool($c['publicado']) ? 'Vi que o site ainda não está publicado: quer que a gente faça isso juntos agora?' : 'O site já está no ar. Já colocou o link na bio do Instagram e no WhatsApp?']],
                ['Configurar junto', '1–3 min', 'Faça junto com ele, pelo celular: serviços e preços, horário, foto, WhatsApp conectado.', ['Me fala os seus serviços e preços que eu te mostro onde colocar.', 'Conectar o WhatsApp faz o cliente receber a confirmação sozinho. Quer fazer agora?']],
                ['Divulgar', '20 s', 'O site só traz cliente se as pessoas souberem dele.', ['O link do seu site é ' . $d['site'] . '. Coloca na bio do Instagram e no status do WhatsApp hoje?']],
                ['Encerrar', '', 'Combine um retorno em poucos dias para ver se chegou cliente.', ['Te ligo na semana que vem para ver se já chegou cliente pelo site, combinado?']],
            ];
            break;
        case 'sumido':
            $etapas = [
                ['Abertura', '10 s', 'Sem cobrança: é uma ligação para ajudar.', $abertura],
                ['Por que sumiu', '30 s', 'Descubra o motivo: falta de tempo, não sabe usar, não viu resultado.', array_merge($gancho, ['Faz um tempo que você não entra no painel. Ficou alguma dúvida ou algo travou?'])],
                ['Ajudar', '1 min', 'Resolva uma coisa durante a ligação: senha, horário, um serviço novo.', ['Quer que eu te ajude agora? Se for a senha, eu te mando o link para criar uma nova.', 'Tem algum serviço, preço ou horário que mudou e eu posso atualizar com você?']],
                ['Encerrar', '', '', ['Fico à disposição. Te mando por WhatsApp o link do painel para facilitar.']],
            ];
            break;
        default: // relacionamento e retorno
            $etapas = [
                ['Abertura', '10 s', '', $c['motivo'] === 'retorno' ? ["Oi! $eu. A gente combinou de eu te ligar agora. Ainda é um bom momento?"] : $abertura],
                ['Como está indo', '30 s', 'Escute e anote: elogio vira depoimento, reclamação vira melhoria.', array_merge($gancho, ['Tem alguma coisa que você gostaria de melhorar no site?'])],
                ['Indicação', '20 s', 'Cliente satisfeito é o melhor vendedor. Explique a regra de verdade, sem exagero.', [($d['indica'] ? 'Você tem um link só seu: ' . $d['indica'] . '. ' : '') . 'Cada pessoa que você indicar e fechar com a gente te dá 1 mês grátis.', 'Lembra de algum colega que também precisaria de um site?']],
                ['Encerrar', '', '', ['Obrigado pela parceria! Qualquer coisa, é só me chamar.']],
            ];
    }
    $etapas = array_map(fn($e) => ['titulo' => $e[0], 'tempo' => $e[1], 'dica' => $e[2], 'falas' => array_values(array_filter($e[3]))], $etapas);
    return ['etapas' => $etapas, 'objecoes' => cli_objecoes($c, $d), 'origem' => 'padrao'];
}

function cli_objecoes($c, $d) {
    $res = cli_resultado_txt($d);
    return [
        ['chave' => 'sem_dinheiro', 'titulo' => '"Agora estou sem dinheiro"', 'resposta' => 'Entendo, acontece. Vamos combinar uma data que fique boa para você.', 'pergunta' => 'Qual dia você consegue? Eu anoto e te mando o link nesse dia.'],
        ['chave' => 'nao_uso', 'titulo' => '"Não estou usando"', 'resposta' => 'Obrigado por falar. ' . ($res !== '' ? "Mesmo assim, o site teve $res nos últimos 30 dias. " : '') . 'Geralmente é alguma coisa de configuração que dá para resolver rápido.', 'pergunta' => 'O que te impediu de usar? Posso te ajudar agora pelo telefone.'],
        ['chave' => 'sem_resultado', 'titulo' => '"Não chegou cliente pelo site"', 'resposta' => 'Faz sentido querer resultado. O site ajuda muito quando está na bio do Instagram, no status do WhatsApp e no Google com o horário certo.', 'pergunta' => 'O link já está no seu Instagram e no WhatsApp? Posso te ajudar a colocar agora.'],
        ['chave' => 'cancelar', 'titulo' => '"Quero cancelar"', 'resposta' => 'Tudo bem, eu respeito. Antes, me ajuda a melhorar: o que não funcionou para você?', 'pergunta' => 'Se eu resolver isso, você gostaria de continuar? (Se não, registre "Quer cancelar" e trate o cancelamento no Financeiro.)'],
        ['chave' => 'caro', 'titulo' => '"Está caro"', 'resposta' => 'Entendo. Pensa assim: se o site trouxer um cliente a mais por mês, ele já se pagou.' . ($res !== '' ? " E nos últimos 30 dias ele teve $res." : ''), 'pergunta' => 'O que faria valer mais a pena para você?'],
        ['chave' => 'senha', 'titulo' => '"Esqueci a senha / não consigo entrar"', 'resposta' => 'Sem problema: na tela de entrar tem "Esqueci minha senha", e eu te mando o link agora pelo WhatsApp.', 'pergunta' => 'Quer fazer junto comigo agora?'],
        ['chave' => 'ja_paguei', 'titulo' => '"Eu já paguei"', 'resposta' => 'Obrigado por avisar, pode ser que não tenha baixado aqui ainda. Desculpe o incômodo.', 'pergunta' => 'Consegue me mandar o comprovante no WhatsApp para eu dar baixa?'],
        ['chave' => 'sem_tempo', 'titulo' => '"Agora não posso"', 'resposta' => 'Sem problema!', 'pergunta' => 'É melhor eu te ligar hoje à tarde ou amanhã de manhã?'],
    ];
}

// ---------- Registro ----------
function cli_registrar($c, $resultado, $dados = []) {
    global $pdo;
    if (!isset(CLI_RESULTADOS[$resultado])) return ['ok' => false, 'erro' => 'Escolha o resultado da ligação.'];
    $retorno = null;
    if (!empty($dados['retorno'])) {
        $t = strtotime((string)$dados['retorno']);
        if (!$t || $t < time() - 300 || $t > time() + 90 * 86400) return ['ok' => false, 'erro' => 'Escolha um dia e hora de retorno a partir de agora.'];
        $retorno = date('Y-m-d H:i:00', $t);
    }
    if ($resultado === 'retornar' && !$retorno) return ['ok' => false, 'erro' => 'Escolha quando ligar de volta.'];
    $dur = max(0, min(7200, (int)($dados['duracao'] ?? 0))) ?: null;
    $canal = in_array($dados['canal'] ?? '', ['whatsapp', 'telefone'], true) ? $dados['canal'] : null;
    $nota = mb_substr(trim(strip_tags((string)($dados['anotacao'] ?? ''))), 0, 1500);
    $pdo->prepare("INSERT INTO prosp_ligacoes (empresa_id, motivo, resultado, duracao_seg, canal, anotacao, retorno_em) VALUES (?,?,?,?,?,?,?)")
        ->execute([$c['id'], $c['motivo'] ?? null, $resultado, $dur, $canal, $nota ?: null, $retorno]);
    $pdo->prepare("UPDATE empresas SET ultima_ligacao_em = NOW(), ultimo_resultado_lig = ?, ligar_em = ?, ligar_avisado = FALSE WHERE id = ?")
        ->execute([$resultado, $retorno, $c['id']]);
    // Quer cancelar: avisa você no WhatsApp para tratar com calma (o cancelamento é feito no Financeiro)
    if ($resultado === 'cancelar' && function_exists('prosp_alertar')) prosp_alertar("⚠️ {$c['nome']} disse que quer cancelar." . ($nota !== '' ? "\nMotivo: " . mb_strimwidth($nota, 0, 200, '…') : ''));
    $d = cli_dados($c);
    return ['ok' => true, 'retorno' => $retorno ? lig_quando($retorno) : null, 'mensagem' => cli_mensagem_pos($c, $resultado, $d)];
}

function cli_mensagem_pos($c, $resultado, $d) {
    $vend = trim(get_config('prosp_vendedor', ''));
    $marca = get_config('mkt_marca', '') ?: 'GDS Sites';
    $eu = $vend !== '' ? "$vend, da $marca" : "da $marca";
    $link = $d['cobranca']['link_pagamento'] ?? '';
    switch ($resultado) {
        case 'vai_pagar':
            return "Oi! Aqui é $eu. Obrigado pela conversa 🙂\n" . ($link ? "Segue o link para o pagamento da mensalidade:\n$link" : 'Já te mando o link para o pagamento da mensalidade por aqui.');
        case 'nao_atendeu': case 'ocupado':
            if (in_array($c['motivo'], ['cobranca', 'renovacao'], true)) return "Oi, tudo bem? Aqui é $eu. Tentei te ligar: é só um lembrete da mensalidade do site." . ($link ? "\nLink para pagamento: $link" : '') . "\nQualquer dúvida, me chama!";
            return "Oi, tudo bem? Aqui é $eu. Tentei te ligar para saber como está o site da {$c['nome']}. Quando puder, me chama por aqui!";
        case 'ajuda': case 'retornar':
            return "Oi! Aqui é $eu. Como combinamos, aqui estão os links:\nSeu site: {$d['site']}\nPainel: {$d['painel']}\nQualquer dúvida, me chama!";
        case 'tudo_certo': case 'indicou':
            return "Obrigado pela conversa! 🙂" . ($d['indica'] ? "\nSeu link para indicar: {$d['indica']}\nCada indicação que fechar com a gente vale 1 mês grátis para você." : '');
        default:
            return '';
    }
}

function cli_historico($id, $limite = 10) {
    global $pdo;
    $st = $pdo->prepare("SELECT resultado, motivo, duracao_seg, anotacao, retorno_em, criado_em FROM prosp_ligacoes WHERE empresa_id=? ORDER BY id DESC LIMIT ?");
    $st->execute([(int)$id, $limite]);
    return array_map(fn($l) => ['rotulo' => (CLI_RESULTADOS[$l['resultado']][1] ?? '') . ' ' . (CLI_RESULTADOS[$l['resultado']][0] ?? $l['resultado']) . ($l['motivo'] ? ' · ' . preg_replace('/^\S+\s/u', '', CLI_MOTIVOS[$l['motivo']] ?? '') : ''),
        'quando' => date('d/m H:i', strtotime($l['criado_em'])), 'duracao' => $l['duracao_seg'] ? lig_duracao((int)$l['duracao_seg']) : '',
        'anotacao' => $l['anotacao'], 'retorno' => $l['retorno_em'] ? lig_quando($l['retorno_em']) : ''], $st->fetchAll());
}

function cli_agendados($limite = 20) {
    global $pdo;
    $st = $pdo->prepare("SELECT id, nome, ligar_em FROM empresas WHERE ligar_em > NOW() + INTERVAL '15 minutes' AND COALESCE(status,'ativo') <> 'cancelado' ORDER BY ligar_em LIMIT ?");
    $st->execute([$limite]);
    return $st->fetchAll();
}

// Cron: aviso no WhatsApp na hora do retorno combinado com um cliente
function cli_avisar_retornos() {
    global $pdo;
    $st = $pdo->query("SELECT * FROM empresas WHERE ligar_em BETWEEN NOW() - INTERVAL '30 minutes' AND NOW() + INTERVAL '10 minutes'
        AND NOT COALESCE(ligar_avisado, FALSE) AND COALESCE(status,'ativo') <> 'cancelado' ORDER BY ligar_em LIMIT 10");
    $n = 0;
    foreach ($st->fetchAll() as $c) {
        $pdo->prepare("UPDATE empresas SET ligar_avisado = TRUE WHERE id = ?")->execute([$c['id']]);
        prosp_alertar("📞 Hora de ligar para o cliente {$c['nome']} (" . date('H:i', strtotime($c['ligar_em'])) . ")\n" . cli_numeros($c)['exibir']
            . "\nAbra: " . prosp_url_base() . '/admin/ligacoes.php?para=clientes&id=' . (int)$c['id']);
        $n++;
    }
    return $n;
}

// Copiloto ao vivo para cliente
function cli_copiloto($c, $disse) {
    if (!ia_configurada()) return ['ok' => false, 'erro' => 'Configure a IA em Admin → Configurações. Enquanto isso, use as respostas prontas.'];
    $d = cli_dados($c);
    $prompt = "Você está ajudando AO VIVO alguém da empresa que faz o site de \"{$c['nome']}\" numa ligação com esse CLIENTE (ele já paga o site). Motivo da ligação: "
        . (CLI_MOTIVOS[$c['motivo']] ?? 'relacionamento') . ". Números reais dos últimos 30 dias: " . (cli_resultado_txt($d) ?: 'sem registros') . ". "
        . "O cliente acabou de dizer: \"" . mb_substr($disse, 0, 600) . "\".\n"
        . "Diga o que falar agora: uma resposta curta e natural (até 2 frases faladas) e uma pergunta de volta. Seja honesto, não invente números nem prometa desconto. "
        . "Se ele quiser cancelar, entenda o motivo com respeito.\n"
        . 'Responda APENAS JSON: {"leitura":"o que ele quis dizer","falar":"...","pergunta":"...","resultado_sugerido":"nao_atendeu|ocupado|numero_errado|retornar|tudo_certo|vai_pagar|ajuda|indicou|cancelar|"}';
    $r = chamar_ia_json($prompt, 500, 25, 0.5, ['esforco' => 'low']);
    if (!$r['ok']) return ['ok' => false, 'erro' => $r['erro']];
    $x = $r['dados']; $res = (string)($x['resultado_sugerido'] ?? '');
    return ['ok' => true, 'leitura' => mb_substr((string)($x['leitura'] ?? ''), 0, 200), 'falar' => mb_substr((string)($x['falar'] ?? ''), 0, 500),
            'pergunta' => mb_substr((string)($x['pergunta'] ?? ''), 0, 300), 'resultado' => isset(CLI_RESULTADOS[$res]) ? $res : ''];
}

function cli_metricas() {
    global $pdo;
    $h = $pdo->query("SELECT COUNT(*) total, COUNT(*) FILTER (WHERE resultado NOT IN ('nao_atendeu','ocupado','numero_errado')) atendidas,
            COUNT(*) FILTER (WHERE resultado = 'vai_pagar') pagar, COUNT(*) FILTER (WHERE resultado = 'cancelar') cancelar, COALESCE(SUM(duracao_seg),0) segundos
        FROM prosp_ligacoes WHERE empresa_id IS NOT NULL AND criado_em::date = CURRENT_DATE")->fetch();
    $retornos = (int)$pdo->query("SELECT COUNT(*) FROM empresas WHERE ligar_em::date = CURRENT_DATE")->fetchColumn();
    return ['hoje' => array_map('intval', $h), 'retornos_hoje' => $retornos];
}
