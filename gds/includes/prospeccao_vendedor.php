<?php
// ============================================================
// IA VENDEDORA + INDIQUE E GANHE
// • A IA conversa no WhatsApp até fechar: garante que o dono viu a prévia, pergunta o
//   que achou, mostra 1 benefício do ramo dele, apresenta a OFERTA DE ENTRADA e fecha.
//   Quando ele aceita, cria a empresa e o site sozinha (o mesmo "Fechou! Virar cliente")
//   e manda o link, o login e como pagar.
// • Tom testado: cada lead recebe um tom (amigável, consultivo ou direto); depois de
//   alguns leads, a IA passa a usar mais o que mais fecha (e segue testando 20% das vezes).
// • Indique e ganhe: cada cliente tem um link; quem ele indicar e fechar dá 1 mês grátis.
// ============================================================
require_once __DIR__ . '/prospeccao.php';

const PROSP_TONS = [
    'amigavel' => ['Vizinho que ajuda', 'Tom de vizinho que ajuda: informal, caloroso, frases bem curtas, no máximo 1 emoji. Fala como gente da região.'],
    'consultivo' => ['Consultor', 'Tom de consultor: faz 1 pergunta sobre o negócio dele, mostra o problema (cliente que desiste quando ninguém responde rápido) e como o site resolve. Sem gírias.'],
    'direto' => ['Direto ao ponto', 'Tom direto: benefício concreto, oferta clara e a pergunta de fechamento. Sem rodeios e sem enrolação.'],
];

function prosp_tons_placar() {
    global $pdo;
    $out = [];
    foreach (array_keys(PROSP_TONS) as $t) $out[$t] = ['n' => 0, 'avancou' => 0, 'fechou' => 0, 'nota' => 0];
    try {
        foreach ($pdo->query("SELECT ia_tom, COUNT(*) n, COUNT(*) FILTER (WHERE status IN ('negociando','fechado')) avancou, COUNT(*) FILTER (WHERE status='fechado') fechou
            FROM prospects WHERE ia_tom IS NOT NULL GROUP BY 1")->fetchAll() as $r) {
            if (!isset($out[$r['ia_tom']])) continue;
            $out[$r['ia_tom']] = ['n' => (int)$r['n'], 'avancou' => (int)$r['avancou'], 'fechou' => (int)$r['fechou'],
                'nota' => (int)$r['n'] ? round(((int)$r['fechou'] * 5 + (int)$r['avancou']) / (int)$r['n'] * 100, 1) : 0];
        }
    } catch (PDOException $e) {}
    return $out;
}

// Tom do lead: fixo por lead (a conversa não muda de "pessoa" no meio)
function prosp_tom_do_lead($p) {
    global $pdo;
    if (!empty($p['ia_tom']) && isset(PROSP_TONS[$p['ia_tom']])) return $p['ia_tom'];
    $placar = prosp_tons_placar();
    $menor = min(array_column($placar, 'n'));
    if ($menor < 8) {
        $cand = array_keys(array_filter($placar, fn($x) => $x['n'] === $menor));      // ainda aprendendo: equilibra
        $tom = $cand[array_rand($cand)];
    } elseif (random_int(1, 100) <= 20) {
        $tom = array_rand(PROSP_TONS);                                                 // 20%: continua testando
    } else {
        uasort($placar, fn($a, $b) => $b['nota'] <=> $a['nota']);
        $tom = array_key_first($placar);                                               // 80%: o que mais fecha
    }
    try { $pdo->prepare("UPDATE prospects SET ia_tom=? WHERE id=?")->execute([$tom, $p['id']]); } catch (PDOException $e) {}
    return $tom;
}

function prosp_oferta_txt() {
    return trim(get_config('prosp_oferta', '')) ?: trim(get_config('mkt_preco', ''));
}

// Prompt da vendedora (usado na leitura de cada resposta do lead)
function prosp_prompt_vendedor($p, $historico) {
    $vend = trim(get_config('prosp_vendedor', ''));
    $marca = get_config('mkt_marca', '') ?: 'GDS Sites';
    $oferta = prosp_oferta_txt();
    $tom = prosp_tom_do_lead($p);
    $fecha = get_config('prosp_ia_fecha', '1') === '1';
    return "Você é " . ($vend ?: 'uma vendedora') . " da \"$marca\", que faz sites para pequenos negócios (agendamento online 24 h, confirmação e lembrete no WhatsApp, aparecer no Google, pedido de orçamento/delivery). "
        . "Você está no WhatsApp com o dono da empresa abaixo. Seu objetivo é FECHAR a venda, de forma honesta.\n"
        . "Caminho: 1) garantir que ele viu a prévia (mande o link se ainda não viu); 2) perguntar o que achou ou o que mudaria; 3) mostrar UM benefício ligado ao ramo dele; "
        . "4) apresentar a oferta" . ($oferta ? ": $oferta" : ' (diga que é sem fidelidade e que o valor é por mês)') . "; 5) fechar com uma pergunta simples, ex.: \"Posso deixar o seu no ar hoje?\".\n"
        . "Objeções: \"é caro\" → compare com 1 cliente a mais por mês e lembre que é sem fidelidade; \"tenho Instagram\" → o site aparece no Google e agenda sozinho, o Instagram não; "
        . "\"vou pensar\" → pergunte o que falta para decidir e ofereça deixar no ar para ele testar; \"já tenho site\" → elogie, ofereça comparar e não insista.\n"
        . "Se ele ACEITAR (\"pode fazer\", \"fechado\", \"quero\", \"vamos\"), a intenção é \"fechar\"" . ($fecha ? ' (o sistema cria o site dele na hora e manda o acesso)' : '') . ". Se ele mandou um e-mail, coloque em \"email\".\n"
        . "Nunca afirme que ele não tem site. Nunca invente números, resultados, depoimentos ou recursos. Se ele não quiser, agradeça e encerre. Português do Brasil.\n"
        . PROSP_TONS[$tom][1] . " Cada resposta: até 3 linhas curtas e terminando com UMA pergunta.\n\n"
        . "Empresa: {$p['nome']} ({$p['categoria']}, " . ($p['bairro'] ?: $p['cidade']) . "), nota " . ($p['nota'] ?: '-') . " com {$p['avaliacoes']} avaliações. "
        . "Prévia do site: " . prosp_link_previa($p) . ($p['previa_aberta_em'] ? ' (ele JÁ abriu a prévia)' : ' (ainda não abriu)') . "\n\nCONVERSA:\n$historico\n\n"
        . 'Responda APENAS JSON: {"intencao":"interessado|preco|link|duvida|quem|sem_tempo|ja_tenho|nao_preciso|nao_quer|fechar|outro","leitura":"1 frase","respostas":["...","..."],"proximo_passo":"o que fazer agora","email":"","seguro_responder_sozinho":true}';
}

// Fechou na conversa: cria empresa + site e manda o acesso e o pagamento
function prosp_ia_fechar($p, $email = '') {
    global $pdo;
    require_once __DIR__ . '/prospeccao_cliente.php';
    $email = filter_var(trim((string)$email), FILTER_VALIDATE_EMAIL) ? trim($email) : '';
    $r = prosp_virar_cliente((int)$p['id'], ['email' => $email, 'publicar' => true]);
    if (!$r['ok']) {
        prosp_alertar("💰 {$p['nome']} quer fechar! Não consegui criar o site sozinho (" . mb_strimwidth((string)$r['erro'], 0, 120, '…') . "). Feche você: Prospecção → 🎉 Fechou! Virar cliente.");
        return $r;
    }
    try { $pdo->prepare("UPDATE prospects SET ia_fechou_em=NOW() WHERE id=?")->execute([$p['id']]); } catch (PDOException $e) {}
    $pag = trim(get_config('prosp_pagamento', ''));
    $oferta = prosp_oferta_txt();
    $indica = prosp_indica_link((int)$r['empresa_id']);
    $txt = "Fechado! 🎉 Já deixei o site da {$p['nome']} no ar: {$r['url']}\n\n"
        . (!empty($r['senha']) ? "Para entrar no seu painel (agenda, serviços e preços): " . prosp_url_base() . "/login.php\nLogin: {$r['email']}\nSenha: {$r['senha']}\n\n" : '')
        . ($pag !== '' ? "Para ativar" . ($oferta ? " ($oferta)" : '') . ": $pag\n\n" : "Já te mando os dados do pagamento.\n\n")
        . "Qualquer ajuste é só me falar aqui." . ($indica ? "\n\n🎁 Indicou outro dono de negócio que fechou, você ganha 1 mês grátis: $indica" : '');
    $env = prosp_enviar_plataforma($p, $txt, 'prosp_ia', random_int(40, 90));
    prosp_alertar("🤖💰 A IA FECHOU com {$p['nome']}!\nSite: {$r['url']}\n" . ($pag === '' ? "⚠ Mande você os dados do pagamento (ou preencha \"Como o cliente paga\" no Piloto)." : 'Ela já mandou o acesso e como pagar.'));
    return ['ok' => true, 'enviado' => $env['ok'] ?? false] + $r;
}

// ---------- Indique e ganhe ----------
function prosp_indica_codigo($empresaId) {
    global $pdo;
    try {
        $st = $pdo->prepare("SELECT indica_codigo FROM empresas WHERE id=?"); $st->execute([(int)$empresaId]);
        $c = (string)$st->fetchColumn();
        if ($c !== '') return $c;
        for ($i = 0; $i < 5; $i++) {
            $c = substr(bin2hex(random_bytes(5)), 0, 8);
            try { $pdo->prepare("UPDATE empresas SET indica_codigo=? WHERE id=? AND indica_codigo IS NULL")->execute([$c, (int)$empresaId]); return $c; } catch (PDOException $e) { /* repetido: tenta outro */ }
        }
    } catch (PDOException $e) {}
    return '';
}
function prosp_indica_link($empresaId) {
    $c = prosp_indica_codigo($empresaId);
    return $c !== '' ? prosp_url_base() . '/indica/' . $c : '';
}
function prosp_indica_resumo($empresaId) {
    global $pdo;
    try {
        $st = $pdo->prepare("SELECT COUNT(*) total, COUNT(*) FILTER (WHERE status='fechado') fechados FROM prospects WHERE indicado_por=?");
        $st->execute([(int)$empresaId]);
        return $st->fetch() ?: ['total' => 0, 'fechados' => 0];
    } catch (PDOException $e) { return ['total' => 0, 'fechados' => 0]; }
}
// Quem indicou ganha 1 mês quando o indicado vira cliente
function prosp_indica_premiar($indicado) {
    global $pdo;
    $quem = (int)($indicado['indicado_por'] ?? 0);
    if (!$quem) return;
    $pdo->prepare("UPDATE empresas SET vencimento = GREATEST(COALESCE(vencimento, CURRENT_DATE), CURRENT_DATE) + INTERVAL '30 days' WHERE id=?")->execute([$quem]);
    $e = $pdo->prepare("SELECT nome, whatsapp FROM empresas WHERE id=?"); $e->execute([$quem]); $e = $e->fetch();
    if (!$e) return;
    prosp_alertar("🎁 Indicação que virou cliente: {$indicado['nome']} (indicado por {$e['nome']}). Já dei +1 mês grátis para {$e['nome']}.");
    if (!empty($e['whatsapp']) && function_exists('wa_remetente') && ($rem = wa_remetente(['wa_modo' => 'plataforma']))) {
        try { wa_enviar_agora($rem, wa_numero($e['whatsapp']), "🎁 Obrigado pela indicação! A {$indicado['nome']} virou cliente e você ganhou 1 mês grátis no seu site. Indique mais: " . prosp_indica_link($quem)); } catch (Throwable $x) {}
    }
}
