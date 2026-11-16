<?php
// ============================================================
// LIGAÇÕES DE PROSPECÇÃO (Admin → Prospecção → 📞 Ligações)
// • Fila de quem ligar agora: retornos combinados → quem pediu o site → quem abriu a prévia →
//   quem respondeu → os mais quentes que ainda não receberam ligação → nova tentativa de quem não atendeu.
// • Roteiro pronto para cada lead (abertura, gancho com dado real, perguntas, apresentação,
//   fechamento) que muda conforme o ramo (agenda, pedido, orçamento) e a situação (já recebeu
//   mensagem, abriu a prévia, tem só Instagram, já tem site). Com IA, um roteiro só daquele lead.
// • Respostas para as objeções mais comuns e um copiloto ao vivo ("ele disse…" → o que falar).
// • Resultado em 1 toque (não atendeu, retornar, interessado, fechou…), retorno com dia e hora
//   e aviso no seu WhatsApp na hora de ligar, mensagem pós-ligação com a prévia.
// Regra de ouro (igual à IA vendedora): nunca afirmar que o negócio não tem site, nunca inventar
// números ou resultados, e quem não quer não recebe mais ligação.
// ============================================================
require_once __DIR__ . '/prospeccao.php';
require_once __DIR__ . '/prospeccao_vendedor.php';   // prosp_oferta_txt()

// resultado => [rótulo, emoji, atendeu?, situação nova do lead (null = mantém)]
const LIG_RESULTADOS = [
    'nao_atendeu'   => ['Não atendeu', '📵', false, null],
    'ocupado'       => ['Ocupado / caixa postal', '⏳', false, null],
    'numero_errado' => ['Número errado', '❌', false, null],
    'sem_interesse' => ['Atendeu: sem interesse', '🙅', true, 'descartado'],
    'retornar'      => ['Pediu para ligar depois', '🕑', true, 'contatado'],
    'interessado'   => ['Interessado: mandar a prévia', '👍', true, 'respondeu'],
    'negociando'    => ['Negociando', '🤝', true, 'negociando'],
    'fechou'        => ['Fechou!', '🎉', true, 'fechado'],
];
const LIG_MAX_TENTATIVAS = 4;   // sem atender nenhuma: sai da fila de ligação (fica para mensagem)

// Números do lead para ligar: [exibição, só dígitos com 55, link tel:, link do WhatsApp]
function lig_numeros($p) {
    [$exib, $wa] = prosp_telefone($p['telefone'] ?: ($p['whatsapp'] ?? ''));
    $wa = preg_replace('/\D/', '', (string)($p['whatsapp'] ?? '')) ?: $wa;
    $dig = preg_replace('/\D/', '', (string)($p['telefone'] ?: ($p['whatsapp'] ?? '')));
    if ($dig !== '' && !str_starts_with($dig, '55')) $dig = '55' . ltrim($dig, '0');
    return ['exibir' => $exib ?: ($p['telefone'] ?? ''), 'whatsapp' => $wa, 'tel' => $dig ? 'tel:+' . $dig : '', 'wa_link' => $wa ? 'https://wa.me/' . $wa : ''];
}

// Melhor momento para ligar, pelo ramo (fora do pico de atendimento do próprio negócio)
function lig_horario($p, $agora = null) {
    $agora = $agora ?? time();
    $modo = prosp_modo_negocio(($p['categoria'] ?? '') . ' ' . ($p['nome'] ?? '') . ' ' . ($p['busca'] ?? ''));
    $janelas = ['pedido' => [[9, 11], [14, 17]], 'agenda' => [[8, 10], [13, 16]], 'orcamento' => [[8, 11], [13, 17]]][$modo];
    $txt = ['pedido' => 'Melhor: de manhã (9h–11h) ou à tarde (14h–17h), longe do almoço e da janta.',
            'agenda' => 'Melhor: logo cedo (8h–10h) ou no começo da tarde (13h–16h), antes de a agenda encher.',
            'orcamento' => 'Melhor: de manhã (8h–11h) ou à tarde (13h–17h), em horário comercial.'][$modo];
    $h = (int)date('G', $agora) + (int)date('i', $agora) / 60; $dia = (int)date('w', $agora);
    $bom = $dia !== 0 && array_filter($janelas, fn($j) => $h >= $j[0] && $h < $j[1]);
    if ($dia === 0 || $h < 8 || $h >= 20) $agoraTxt = 'Fora do horário comercial: prefira mandar mensagem ou agendar a ligação.';
    elseif ($bom) $agoraTxt = 'Agora é um bom momento para ligar.';
    else $agoraTxt = 'Dá para ligar, mas pode ser horário de movimento: se ele estiver ocupado, combine outro horário.';
    // Horário de funcionamento do Google para hoje (quando a prospecção trouxe)
    $hoje = '';
    $semana = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
    foreach ((array)json_decode((string)($p['horario'] ?? ''), true) as $linha) {
        if (is_string($linha) && str_starts_with(mb_strtolower($linha), $semana[$dia])) { $hoje = $linha; break; }
    }
    return ['bom' => (bool)$bom, 'agora' => $agoraTxt, 'dica' => $txt, 'hoje' => $hoje, 'fechado_hoje' => $hoje !== '' && preg_match('/fechado/iu', $hoje)];
}

// ---------- Fila ----------
// Por que este lead está na fila (o primeiro motivo que valer)
function lig_motivo($p) {
    if (!empty($p['ligar_em']) && strtotime($p['ligar_em']) <= time() + 900) return ['retorno', '🕑 Retorno combinado ' . lig_quando($p['ligar_em'])];
    if (!empty($p['quer_site_em'])) return ['quer', '🙋 Clicou em "Quero este site"'];
    if (!empty($p['previa_aberta_em'])) return ['abriu', '👀 Abriu a prévia' . ((int)$p['previa_visitas'] > 1 ? ' ' . (int)$p['previa_visitas'] . ' vezes' : '')];
    if (in_array($p['status'], ['respondeu', 'negociando'], true)) return ['respondeu', '💬 Respondeu no WhatsApp'];
    if (in_array($p['ultimo_resultado'], ['nao_atendeu', 'ocupado'], true)) return ['tentar', '📵 Não atendeu (' . (int)$p['ligacoes'] . 'ª tentativa feita)'];
    return ['quente', '🔥 Lead quente, ainda sem ligação'];
}

function lig_quando($ts) {
    $t = strtotime((string)$ts);
    $d = date('Y-m-d', $t);
    $dia = $d === date('Y-m-d') ? 'hoje' : ($d === date('Y-m-d', strtotime('+1 day')) ? 'amanhã' : ($d === date('Y-m-d', strtotime('-1 day')) ? 'ontem' : date('d/m', $t)));
    return $dia . ' às ' . date('H:i', $t);
}

// Quem é hora de ligar agora (os retornos marcados para mais tarde ficam em lig_agendados)
function lig_fila($limite = 40) {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM prospects
        WHERE optout_em IS NULL AND status NOT IN ('fechado','descartado')
          AND length(regexp_replace(COALESCE(NULLIF(telefone,''), whatsapp, ''), '\D', '', 'g')) >= 10
          AND COALESCE(ultimo_resultado,'') NOT IN ('sem_interesse','numero_errado')
          AND (ligar_em IS NULL OR ligar_em <= NOW() + INTERVAL '15 minutes')
          AND (
                ligar_em IS NOT NULL
             OR (ultima_ligacao_em IS NULL AND (quer_site_em IS NOT NULL OR previa_aberta_em IS NOT NULL OR status IN ('respondeu','negociando') OR pontuacao >= 45))
             OR (ultima_ligacao_em IS NOT NULL AND (quer_site_em > ultima_ligacao_em OR previa_aberta_em > ultima_ligacao_em OR respondeu_em > ultima_ligacao_em))
             OR (ultimo_resultado IN ('nao_atendeu','ocupado') AND COALESCE(ligacoes,0) < ? AND ultima_ligacao_em < NOW() - INTERVAL '20 hours')
          )
        ORDER BY
          CASE WHEN ligar_em IS NOT NULL THEN 0
               WHEN quer_site_em IS NOT NULL THEN 1
               WHEN previa_aberta_em IS NOT NULL THEN 2
               WHEN status IN ('respondeu','negociando') THEN 3
               WHEN ultima_ligacao_em IS NULL THEN 4 ELSE 5 END,
          ligar_em NULLS LAST, previa_aberta_em DESC NULLS LAST, pontuacao DESC, id
        LIMIT ?");
    $st->execute([LIG_MAX_TENTATIVAS, max(1, min(200, (int)$limite))]);
    return $st->fetchAll();
}

// Retornos combinados para mais tarde (hoje e próximos dias)
function lig_agendados($limite = 30) {
    global $pdo;
    $st = $pdo->prepare("SELECT id, nome, categoria, ligar_em FROM prospects WHERE ligar_em > NOW() + INTERVAL '15 minutes' AND optout_em IS NULL
        AND status NOT IN ('fechado','descartado') ORDER BY ligar_em LIMIT ?");
    $st->execute([$limite]);
    return $st->fetchAll();
}

// ---------- Roteiro ----------
// Roteiro pronto (sem IA), com os dados reais do lead. Estrutura igual à do roteiro da IA.
function lig_roteiro_padrao($p) {
    $v = prosp_variaveis($p);
    $txtNicho = ($p['categoria'] ?? '') . ' ' . ($p['nome'] ?? '') . ' ' . ($p['busca'] ?? '');
    $modo = prosp_modo_negocio($txtNicho);
    $vend = trim(get_config('prosp_vendedor', ''));
    $eu = $vend !== '' ? "Aqui é $vend, da {$v['{marca}']}" : "Aqui é da {$v['{marca}']}";
    $oferta = function_exists('prosp_oferta_txt') ? prosp_oferta_txt() : trim(get_config('mkt_preco', ''));
    $preco = $oferta !== '' ? " Fica $oferta, sem fidelidade." : ' É por mês, sem fidelidade.';
    $nome = $p['nome'];
    $jaFalou = (int)($p['passo'] ?? 0) > 0 || !empty($p['contatado_em']);
    $abriu = !empty($p['previa_aberta_em']);
    $soInsta = !empty($p['site']) && prosp_tipo_link($p['site']) !== 'site' && prosp_tipo_link($p['site']) !== 'nenhum';
    $temSite = pg_bool($p['tem_site'] ?? false) || prosp_tipo_link($p['site'] ?? '') === 'site';
    $nicho = $v['{nicho}'];

    // 1. Abertura
    $abertura = ["Oi, tudo bem? É da $nome? Com quem eu falo?", "$eu. Prometo ser breve: é coisa de 1 minuto, pode ser agora?"];
    if ($jaFalou) $abertura[1] = "$eu — te mandei uma mensagem no WhatsApp esses dias, com uma demonstração do site da $nome. Tem 1 minutinho?";
    if (!empty($p['ultimo_resultado']) && $p['ultimo_resultado'] === 'retornar') $abertura[1] = "$eu — a gente combinou de eu te ligar agora. Ainda é um bom momento?";

    // 2. Gancho com dado real (sem inventar)
    $gancho = [];
    if ((int)$p['avaliacoes'] >= 5 && (float)$p['nota'] >= 4) $gancho[] = "Eu vi vocês no Google: {$v['{nota}']} estrelas com {$p['avaliacoes']} avaliações {$v['{bairro_em}']} — parabéns, isso é difícil de conseguir.";
    if ($v['{elogio}'] !== '') $gancho[] = "E o que os clientes mais elogiam nas avaliações é {$v['{elogio}']}.";
    if (!$gancho) $gancho[] = "Eu trabalho com $nicho {$v['{bairro_em}']} e vi vocês no Google Maps.";
    if ($abriu) $gancho[] = 'Conseguiu dar uma olhada na demonstração que eu mandei? O que achou?';

    // 3. Perguntas (o cliente fala, você escuta — e anota)
    $perguntas = [
        'agenda' => ['Hoje o cliente marca horário com vocês como? Pelo WhatsApp mesmo?', 'E quando alguém manda mensagem à noite ou no domingo, quem responde?', 'Acontece de cliente marcar e não aparecer?'],
        'pedido' => ['Hoje os pedidos chegam mais pelo WhatsApp, por aplicativo ou por telefone?', 'No horário de pico, dá conta de responder todo mundo rápido?', 'Vocês pagam taxa por pedido em algum aplicativo?'],
        'orcamento' => ['Como chegam os pedidos de orçamento hoje?', 'Quanto tempo leva, em média, para vocês responderem um orçamento?', "Quando alguém procura $nicho no Google {$v['{bairro_em}']}, vocês aparecem?"],
    ][$modo];

    // 4. Apresentação
    $apresentacao = ["Pensando nisso, eu montei uma demonstração do site da $nome, com o nome, as fotos e as avaliações de vocês.",
        "Nele o cliente " . lig_acao_cliente($p) . ", 24 horas, vê {$v['{o_que}']} e chega a confirmação no WhatsApp. Não precisa instalar nada e tudo se mexe por botões."];
    if ($soInsta) $apresentacao[] = 'O Instagram continua: o site é o que aparece no Google e onde o cliente resolve sozinho, sem esperar resposta.';
    if ($temSite) $apresentacao = ['Vi que vocês já têm site — ótimo. A demonstração que eu fiz é para comparar: ela tem ' . $v['{recurso}'] . ' e confirmação automática no WhatsApp. Se não fizer diferença, sem problema nenhum.'];

    // 5. Fechamento (sempre terminar com uma pergunta de escolha fácil)
    $fechamento = $abriu
        ? ["Se você gostou, eu deixo o seu no ar ainda hoje, com o seu nome.$preco", 'Posso preparar? Qual o melhor e-mail para eu mandar o acesso?']
        : ['Posso te mandar o link agora no WhatsApp, para você ver no celular?', "Se gostar, eu deixo o seu no ar ainda hoje.$preco Te ligo amanhã de manhã ou à tarde para você me dizer o que achou?"];

    // 6. Se não puder falar agora
    $semTempo = ['Sem problema! Qual é melhor para eu te ligar de volta: hoje à tarde ou amanhã de manhã?', 'Enquanto isso, posso te mandar a demonstração no WhatsApp para você ver quando der?'];

    $etapas = [
        ['titulo' => 'Abertura', 'tempo' => '10 s', 'dica' => 'Fale sorrindo (dá para ouvir) e peça permissão. Se não for o dono, pergunte o melhor horário para falar com ele.', 'falas' => $abertura],
        ['titulo' => 'Gancho', 'tempo' => '15 s', 'dica' => 'Um detalhe verdadeiro mostra que não é ligação em massa.', 'falas' => $gancho],
        ['titulo' => 'Perguntas', 'tempo' => '1 min', 'dica' => 'Faça uma pergunta por vez e deixe ele falar. Anote a dor que ele contar: é ela que você usa no fechamento.', 'falas' => $perguntas],
        ['titulo' => 'Apresentação', 'tempo' => '30 s', 'dica' => 'Ligue o que ele contou ao que o site resolve. Nada de prometer números.', 'falas' => $apresentacao],
        ['titulo' => 'Fechamento', 'tempo' => '20 s', 'dica' => 'Termine sempre com uma pergunta de escolha fácil (isto ou aquilo).', 'falas' => $fechamento],
        ['titulo' => 'Se ele não puder agora', 'tempo' => '', 'dica' => 'Combine dia e hora e registre em "Pediu para ligar depois".', 'falas' => $semTempo],
    ];
    return ['etapas' => $etapas, 'objecoes' => lig_objecoes($p), 'origem' => 'padrao'];
}

// Respostas para as objeções mais comuns (curtas, honestas, terminando em pergunta)
// "o cliente faz o pedido sozinho" (frase conjugada, pelo ramo)
function lig_acao_cliente($p) {
    return ['pedido' => 'faz o pedido sozinho', 'orcamento' => 'pede o orçamento sozinho', 'agenda' => 'marca o horário sozinho'][prosp_modo_negocio(($p['categoria'] ?? '') . ' ' . ($p['nome'] ?? '') . ' ' . ($p['busca'] ?? ''))];
}

function lig_objecoes($p) {
    $v = prosp_variaveis($p);
    $faz = lig_acao_cliente($p);
    $oferta = function_exists('prosp_oferta_txt') ? prosp_oferta_txt() : trim(get_config('mkt_preco', ''));
    $vend = trim(get_config('prosp_vendedor', ''));
    $nicho = $v['{nicho}'];
    return [
        ['chave' => 'caro', 'titulo' => '"Quanto custa?" / "Tá caro"', 'resposta' => ($oferta !== '' ? "Fica $oferta, sem fidelidade. " : 'É um valor mensal, sem fidelidade. ') . 'Pensa assim: se o site trouxer um cliente a mais no mês, ele já se pagou. E se não gostar, cancela.', 'pergunta' => 'Faz sentido testar um mês?'],
        ['chave' => 'ja_tenho', 'titulo' => '"Já tenho site"', 'resposta' => "Que ótimo, então você já sabe a importância. O seu tem {$v['{recurso}']} e confirmação automática no WhatsApp? Se tiver, você já está bem servido e eu não insisto.", 'pergunta' => 'Posso te mandar a demonstração só para comparar?'],
        ['chave' => 'instagram', 'titulo' => '"Tenho Instagram, já basta"', 'resposta' => "O Instagram é ótimo para mostrar o trabalho. O site é o que aparece quando alguém procura $nicho no Google {$v['{bairro_em}']}, e lá o cliente $faz, sem você parar para responder. Um completa o outro.", 'pergunta' => 'Quer ver como ficaria com as suas fotos?'],
        ['chave' => 'sem_tempo', 'titulo' => '"Agora não posso"', 'resposta' => 'Sem problema, eu sei como é a correria.', 'pergunta' => 'É melhor eu te ligar hoje à tarde ou amanhã de manhã?'],
        ['chave' => 'manda_whats', 'titulo' => '"Me manda pelo WhatsApp"', 'resposta' => 'Mando agora mesmo! É a demonstração com o nome e as fotos de vocês.', 'pergunta' => 'Te ligo em 15 minutos para você me dizer o que achou?'],
        ['chave' => 'vou_pensar', 'titulo' => '"Vou pensar"', 'resposta' => 'Claro, é uma decisão sua. Só para eu entender e não te ligar à toa:', 'pergunta' => 'O que falta para você decidir: o valor, saber se funciona mesmo ou falar com alguém?'],
        ['chave' => 'socio', 'titulo' => '"Preciso falar com meu sócio / esposa"', 'resposta' => 'Faz todo sentido. Eu mando a demonstração para vocês verem juntos.', 'pergunta' => 'Qual o melhor horário para eu ligar e conversar com os dois?'],
        ['chave' => 'muito_cliente', 'titulo' => '"Já tenho cliente demais"', 'resposta' => "Que bom, é sinal de trabalho bem feito! O site ajuda a organizar: o cliente $faz e você para de responder WhatsApp o dia inteiro.", 'pergunta' => 'Hoje quanto tempo do seu dia vai em responder mensagem?'],
        ['chave' => 'quem', 'titulo' => '"Quem é você? Como achou meu número?"', 'resposta' => ($vend !== '' ? "Sou $vend, da {$v['{marca}']}" : "Sou da {$v['{marca}']}") . ". Achei vocês no Google Maps procurando $nicho {$v['{bairro_em}']}; o número está no perfil público de vocês. Se preferir que eu não ligue mais, eu respeito.", 'pergunta' => 'Posso te contar em 30 segundos o que eu fiz para vocês?'],
        ['chave' => 'ja_tentei', 'titulo' => '"Já tive site e não deu certo"', 'resposta' => 'Entendo, muita gente teve site que ninguém atualizava. A diferença aqui é que ele trabalha sozinho (' . $v['{recurso}'] . ' e confirmação no WhatsApp) e você mexe em tudo por botões.', 'pergunta' => 'O que não funcionou da outra vez?'],
        ['chave' => 'nao_quero', 'titulo' => '"Não tenho interesse"', 'resposta' => 'Tudo bem, agradeço muito o seu tempo! Se um dia mudar de ideia, é só me chamar. Bom trabalho!', 'pergunta' => '(Encerre com educação e marque "Atendeu: sem interesse" — ele não recebe mais ligação.)'],
    ];
}

// Roteiro salvo pela IA (se houver) ou o padrão
function lig_roteiro($p) {
    $ia = json_decode((string)($p['ligacao_roteiro'] ?? ''), true);
    if (is_array($ia) && !empty($ia['etapas'])) {
        $ia['objecoes'] = !empty($ia['objecoes']) ? $ia['objecoes'] : lig_objecoes($p);
        $ia['origem'] = 'ia';
        return $ia;
    }
    return lig_roteiro_padrao($p);
}

// Limpa o que veio da IA (texto curto, no formato esperado)
function lig_limpar_roteiro($d) {
    $txt = fn($s, $n = 400) => mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$s)), 0, $n);
    $etapas = [];
    foreach (array_slice((array)($d['etapas'] ?? []), 0, 8) as $e) {
        if (!is_array($e)) continue;
        $falas = array_values(array_filter(array_map(fn($f) => $txt($f, 300), array_slice((array)($e['falas'] ?? []), 0, 5))));
        if (!$falas || trim((string)($e['titulo'] ?? '')) === '') continue;
        $etapas[] = ['titulo' => $txt($e['titulo'], 60), 'tempo' => $txt($e['tempo'] ?? '', 12), 'dica' => $txt($e['dica'] ?? '', 200), 'falas' => $falas];
    }
    $obj = [];
    foreach (array_slice((array)($d['objecoes'] ?? []), 0, 12) as $i => $o) {
        if (!is_array($o) || trim((string)($o['titulo'] ?? '')) === '' || trim((string)($o['resposta'] ?? '')) === '') continue;
        $obj[] = ['chave' => 'ia' . $i, 'titulo' => $txt($o['titulo'], 80), 'resposta' => $txt($o['resposta'], 400), 'pergunta' => $txt($o['pergunta'] ?? '', 200)];
    }
    return count($etapas) >= 3 ? ['etapas' => $etapas, 'objecoes' => $obj] : null;
}

// Roteiro feito pela IA só para este lead (fica salvo; dá para refazer)
function lig_roteiro_ia($p) {
    global $pdo;
    if (!ia_configurada()) return ['ok' => false, 'erro' => 'Configure a IA em Admin → Configurações para gerar o roteiro personalizado. O roteiro pronto continua funcionando.'];
    $vend = trim(get_config('prosp_vendedor', ''));
    $marca = get_config('mkt_marca', '') ?: 'GDS Sites';
    $oferta = function_exists('prosp_oferta_txt') ? prosp_oferta_txt() : trim(get_config('mkt_preco', ''));
    $hist = [];
    try {
        $st = $pdo->prepare("SELECT resultado, anotacao, criado_em FROM prosp_ligacoes WHERE prospect_id=? ORDER BY id DESC LIMIT 5");
        $st->execute([$p['id']]);
        foreach ($st->fetchAll() as $l) $hist[] = date('d/m', strtotime($l['criado_em'])) . ': ' . (LIG_RESULTADOS[$l['resultado']][0] ?? $l['resultado']) . ($l['anotacao'] ? ' — ' . mb_substr($l['anotacao'], 0, 200) : '');
    } catch (PDOException $e) {}
    $revs = array_slice(array_map(fn($r) => mb_substr((string)($r['texto'] ?? ''), 0, 160), (array)json_decode((string)($p['avaliacoes_texto'] ?? ''), true)), 0, 3);
    $prompt = "Você é um treinador de vendas por telefone. Escreva o ROTEIRO de uma ligação de " . ($vend ?: 'um vendedor') . ", da \"$marca\" (sites para pequenos negócios: agendamento/pedido/orçamento online 24 h, confirmação no WhatsApp, aparecer no Google), "
        . "para o dono da empresa abaixo. Objetivo: conseguir permissão para mandar a demonstração (prévia) do site e marcar o fechamento, de forma honesta e sem pressão.\n"
        . "Regras: falas curtas, faladas (não escritas), tom de gente da região, português do Brasil. Nunca diga que a empresa não tem site. Nunca invente números, resultados, clientes ou depoimentos. "
        . "Use no máximo 2 detalhes reais abaixo para mostrar que não é ligação em massa. Cada etapa termina numa pergunta. Inclua objeções prováveis para ESTE ramo com resposta curta e uma pergunta de volta.\n"
        . ($oferta ? "Oferta: $oferta, sem fidelidade.\n" : "Preço: diga que é mensal e sem fidelidade (não invente valor).\n")
        . "Empresa: {$p['nome']} | Ramo: {$p['categoria']} | Local: " . ($p['bairro'] ?: '-') . " / {$p['cidade']} | Google: " . ($p['nota'] ?: 'sem nota') . " com {$p['avaliacoes']} avaliações"
        . " | Site/link atual: " . ($p['site'] ?: 'nenhum encontrado (não afirme isso)') . " | Já recebeu mensagem: " . ((int)$p['passo'] > 0 ? 'sim' : 'não') . " | Abriu a prévia: " . ($p['previa_aberta_em'] ? 'sim' : 'não')
        . ($revs ? "\nTrechos de avaliações: " . implode(' / ', $revs) : '') . ($hist ? "\nLigações anteriores: " . implode(' | ', $hist) : '') . "\n\n"
        . 'Responda APENAS JSON: {"etapas":[{"titulo":"Abertura","tempo":"10 s","dica":"...","falas":["...","..."]},{"titulo":"Gancho",...},{"titulo":"Perguntas",...},{"titulo":"Apresentação",...},{"titulo":"Fechamento",...},{"titulo":"Se ele não puder agora",...}],'
        . '"objecoes":[{"titulo":"\"Tá caro\"","resposta":"...","pergunta":"..."}]}';
    $r = chamar_ia_json($prompt, 2200, 60, 0.6, ['esforco' => 'low']);
    if (!$r['ok']) return ['ok' => false, 'erro' => $r['erro']];
    $rot = lig_limpar_roteiro($r['dados']);
    if (!$rot) return ['ok' => false, 'erro' => 'A IA não devolveu um roteiro completo. Tente de novo.'];
    $rot['gerado_em'] = date('Y-m-d H:i');
    $pdo->prepare("UPDATE prospects SET ligacao_roteiro=? WHERE id=?")->execute([json_encode($rot, JSON_UNESCAPED_UNICODE), $p['id']]);
    return ['ok' => true, 'roteiro' => lig_roteiro(['ligacao_roteiro' => json_encode($rot)] + $p)];
}

// Copiloto ao vivo: "ele disse X" → o que falar agora (1 frase + 1 pergunta)
function lig_copiloto($p, $disse) {
    if (!ia_configurada()) return ['ok' => false, 'erro' => 'Configure a IA em Admin → Configurações. Enquanto isso, use as respostas prontas das objeções.'];
    $oferta = function_exists('prosp_oferta_txt') ? prosp_oferta_txt() : trim(get_config('mkt_preco', ''));
    $prompt = "Você está ajudando AO VIVO um vendedor numa ligação para o dono de \"{$p['nome']}\" ({$p['categoria']}, " . ($p['bairro'] ?: $p['cidade']) . "). "
        . "Ele vende sites para pequenos negócios (agendamento/pedido/orçamento online, confirmação no WhatsApp, aparecer no Google)" . ($oferta ? ", oferta: $oferta sem fidelidade" : ', mensal e sem fidelidade') . ". "
        . "O cliente acabou de dizer: \"" . mb_substr($disse, 0, 600) . "\".\n"
        . "Diga o que o vendedor deve FALAR agora: uma resposta curta e natural (até 2 frases faladas) e uma pergunta de volta. Honesto: nunca diga que ele não tem site, nunca invente números. "
        . "Se o cliente não quer, a resposta é agradecer e encerrar.\n"
        . 'Responda APENAS JSON: {"leitura":"o que ele quis dizer, em poucas palavras","falar":"...","pergunta":"...","resultado_sugerido":"nao_atendeu|ocupado|numero_errado|sem_interesse|retornar|interessado|negociando|fechou|"}';
    $r = chamar_ia_json($prompt, 500, 25, 0.5, ['esforco' => 'low']);
    if (!$r['ok']) return ['ok' => false, 'erro' => $r['erro']];
    $d = $r['dados'];
    $res = (string)($d['resultado_sugerido'] ?? '');
    return ['ok' => true, 'leitura' => mb_substr((string)($d['leitura'] ?? ''), 0, 200), 'falar' => mb_substr((string)($d['falar'] ?? ''), 0, 500),
            'pergunta' => mb_substr((string)($d['pergunta'] ?? ''), 0, 300), 'resultado' => isset(LIG_RESULTADOS[$res]) ? $res : ''];
}

// ---------- Registro ----------
// $dados: duracao (segundos), canal (whatsapp|telefone), anotacao, retorno (Y-m-d H:i)
function lig_registrar($p, $resultado, $dados = []) {
    global $pdo;
    if (!isset(LIG_RESULTADOS[$resultado])) return ['ok' => false, 'erro' => 'Escolha o resultado da ligação.'];
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
    $pdo->prepare("INSERT INTO prosp_ligacoes (prospect_id, resultado, duracao_seg, canal, anotacao, retorno_em) VALUES (?,?,?,?,?,?)")
        ->execute([$p['id'], $resultado, $dur, $canal, $nota ?: null, $retorno]);
    [, , $atendeu, $novoStatus] = LIG_RESULTADOS[$resultado];
    // Situação só avança (não volta "negociando" para "contatado" por causa de um retorno marcado)
    $ordem = ['novo' => 0, 'contatado' => 1, 'respondeu' => 2, 'negociando' => 3, 'fechado' => 4, 'descartado' => 5];
    $status = $p['status'];
    if ($novoStatus && (in_array($novoStatus, ['descartado', 'fechado'], true) || ($ordem[$novoStatus] ?? 0) > ($ordem[$status] ?? 0))) $status = $novoStatus;
    $obs = trim((string)$p['observacoes']);
    if ($nota !== '') $obs = trim("📞 " . date('d/m H:i') . ' (' . LIG_RESULTADOS[$resultado][0] . "): $nota" . ($obs !== '' ? "\n" . $obs : ''));
    $pdo->prepare("UPDATE prospects SET ligacoes = COALESCE(ligacoes,0) + 1, ultima_ligacao_em = NOW(), ultimo_resultado = ?, status = ?,
            ligar_em = ?, ligar_avisado = FALSE, observacoes = ?, contatado_em = CASE WHEN ? THEN COALESCE(contatado_em, NOW()) ELSE contatado_em END,
            proximo_contato = CASE WHEN ? IN ('fechado','descartado') THEN NULL ELSE proximo_contato END, atualizado_em = NOW()
        WHERE id = ?")
        ->execute([$resultado, $status, $retorno, mb_substr($obs, 0, 4000), $atendeu ? 'true' : 'false', $status, $p['id']]);
    return ['ok' => true, 'status' => $status, 'retorno' => $retorno ? lig_quando($retorno) : null,
            'mensagem' => lig_mensagem_pos($p, $resultado), 'tentativas' => (int)$p['ligacoes'] + 1];
}

// Mensagem de WhatsApp para mandar logo depois da ligação
function lig_mensagem_pos($p, $resultado) {
    $vend = trim(get_config('prosp_vendedor', ''));
    $marca = get_config('mkt_marca', '') ?: 'GDS Sites';
    $eu = $vend !== '' ? "$vend, da $marca" : "da $marca";
    $previa = prosp_link_previa($p);
    switch ($resultado) {
        case 'interessado': case 'negociando': case 'retornar':
            return "Oi! Aqui é $eu, acabamos de conversar por telefone 🙂\nComo combinei, aqui está a demonstração do site da {$p['nome']}:\n$previa\nDá uma olhada com calma e me diz o que achou!";
        case 'nao_atendeu': case 'ocupado':
            return "Oi, tudo bem? É da {$p['nome']}? Aqui é $eu. Tentei te ligar agora há pouco: montei uma demonstração do site de vocês, com o nome e as fotos daí. Posso te mandar o link por aqui?";
        case 'fechou':
            return "Oi! Aqui é $eu. Que bom fechar com vocês! 🎉 Já estou preparando o site da {$p['nome']} e te mando o acesso por aqui ainda hoje.";
        default:
            return '';
    }
}

function lig_historico($id, $limite = 20) {
    global $pdo;
    $st = $pdo->prepare("SELECT resultado, duracao_seg, canal, anotacao, retorno_em, criado_em FROM prosp_ligacoes WHERE prospect_id=? ORDER BY id DESC LIMIT ?");
    $st->execute([(int)$id, $limite]);
    return array_map(fn($l) => ['resultado' => $l['resultado'], 'rotulo' => (LIG_RESULTADOS[$l['resultado']][1] ?? '') . ' ' . (LIG_RESULTADOS[$l['resultado']][0] ?? $l['resultado']),
        'quando' => date('d/m H:i', strtotime($l['criado_em'])), 'duracao' => $l['duracao_seg'] ? lig_duracao((int)$l['duracao_seg']) : '', 'canal' => $l['canal'],
        'anotacao' => $l['anotacao'], 'retorno' => $l['retorno_em'] ? lig_quando($l['retorno_em']) : ''], $st->fetchAll());
}

function lig_duracao($s) { return $s >= 60 ? floor($s / 60) . ' min ' . str_pad((string)($s % 60), 2, '0', STR_PAD_LEFT) . ' s' : $s . ' s'; }

// ---------- Números ----------
function lig_metricas() {
    global $pdo;
    $semAtender = "('nao_atendeu','ocupado','numero_errado')";
    $h = $pdo->query("SELECT COUNT(*) total,
            COUNT(*) FILTER (WHERE resultado NOT IN $semAtender) atendidas,
            COUNT(*) FILTER (WHERE resultado IN ('interessado','negociando','fechou')) interessados,
            COUNT(*) FILTER (WHERE resultado = 'fechou') fechou,
            COALESCE(SUM(duracao_seg), 0) segundos
        FROM prosp_ligacoes WHERE criado_em::date = CURRENT_DATE")->fetch();
    $m = $pdo->query("SELECT COUNT(*) total, COUNT(*) FILTER (WHERE resultado NOT IN $semAtender) atendidas,
            COUNT(*) FILTER (WHERE resultado IN ('interessado','negociando','fechou')) interessados, COUNT(*) FILTER (WHERE resultado = 'fechou') fechou
        FROM prosp_ligacoes WHERE criado_em > NOW() - INTERVAL '30 days'")->fetch();
    // Melhor horário para atender (últimos 60 dias, só faixas com 5+ ligações)
    $horas = $pdo->query("SELECT EXTRACT(HOUR FROM criado_em)::int h, COUNT(*) n, COUNT(*) FILTER (WHERE resultado NOT IN $semAtender) a
        FROM prosp_ligacoes WHERE criado_em > NOW() - INTERVAL '60 days' GROUP BY 1 HAVING COUNT(*) >= 5 ORDER BY 1")->fetchAll();
    $melhor = null;
    foreach ($horas as $x) { $taxa = $x['a'] / max(1, $x['n']); if (!$melhor || $taxa > $melhor['taxa']) $melhor = ['h' => (int)$x['h'], 'taxa' => $taxa, 'n' => (int)$x['n']]; }
    $retornosHoje = (int)$pdo->query("SELECT COUNT(*) FROM prospects WHERE ligar_em::date = CURRENT_DATE AND status NOT IN ('fechado','descartado')")->fetchColumn();
    $meta = max(5, min(200, (int)get_config('prosp_lig_meta', '20')));
    return ['hoje' => array_map('intval', $h), 'mes' => array_map('intval', $m), 'meta' => $meta, 'retornos_hoje' => $retornosHoje,
        'horas' => array_map(fn($x) => ['h' => (int)$x['h'], 'n' => (int)$x['n'], 'pct' => (int)round($x['a'] * 100 / max(1, $x['n']))], $horas),
        'melhor_hora' => $melhor ? sprintf('%02dh–%02dh (%d%% atendem)', $melhor['h'], $melhor['h'] + 1, round($melhor['taxa'] * 100)) : null];
}

// ---------- Aviso de retorno (cron a cada 5 min) ----------
// Manda no seu WhatsApp: "📞 Hora de ligar para X" uns minutos antes do horário combinado
function lig_avisar_retornos() {
    global $pdo;
    $st = $pdo->query("SELECT * FROM prospects WHERE ligar_em BETWEEN NOW() - INTERVAL '30 minutes' AND NOW() + INTERVAL '10 minutes'
        AND NOT COALESCE(ligar_avisado, FALSE) AND optout_em IS NULL AND status NOT IN ('fechado','descartado') ORDER BY ligar_em LIMIT 10");
    $n = 0;
    foreach ($st->fetchAll() as $p) {
        $pdo->prepare("UPDATE prospects SET ligar_avisado = TRUE WHERE id = ?")->execute([$p['id']]);
        $num = lig_numeros($p);
        $ultima = $pdo->prepare("SELECT anotacao FROM prosp_ligacoes WHERE prospect_id=? AND anotacao IS NOT NULL ORDER BY id DESC LIMIT 1");
        $ultima->execute([$p['id']]);
        $nota = (string)$ultima->fetchColumn();
        prosp_alertar("📞 Hora de ligar para {$p['nome']} (" . date('H:i', strtotime($p['ligar_em'])) . ")\n" . $num['exibir']
            . ($nota !== '' ? "\nCombinado: " . mb_strimwidth($nota, 0, 160, '…') : '') . "\nAbra: " . prosp_url_base() . '/admin/ligacoes.php?id=' . (int)$p['id']);
        $n++;
    }
    return $n;
}
