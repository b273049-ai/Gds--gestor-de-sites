<?php
// ============================================================
// PROSPECÇÃO NO PILOTO AUTOMÁTICO
// A plataforma manda a cadência sozinha pelo WhatsApp dela (o número da
// prospecção), recebe as respostas, a IA lê cada uma e marca o funil — e,
// se você quiser, responde sozinha. Tudo com freios para proteger o número:
//   • aquecimento: começa com 10 por dia e sobe aos poucos (semana a semana)
//   • só em horário comercial, com intervalo aleatório entre uma e outra
//   • uma mensagem por vez; nunca 2 no mesmo dia para a mesma pessoa
//   • só números conferidos no WhatsApp (mandar para número inexistente queima o chip)
//   • quem pede para parar sai na hora e recebe um "combinado"
//   • pausa sozinho se muita gente pedir para parar ou se o envio começar a falhar
// O WhatsApp não tem um limite "seguro" oficial para contato frio: os freios
// diminuem o risco, não zeram. Use um número só para prospecção.
// ============================================================

require_once __DIR__ . '/prospeccao.php';
require_once __DIR__ . '/ia_helper.php';
require_once __DIR__ . '/whatsapp.php';
require_once __DIR__ . '/prospeccao_vendedor.php';

const PROSP_INTENCOES = ['fechar' => 'Quer fechar 💰', 'interessado' => 'Interessado', 'preco' => 'Perguntou o preço', 'link' => 'Pediu o link', 'duvida' => 'Tem dúvida', 'quem' => 'Quer saber quem é',
    'sem_tempo' => 'Sem tempo agora', 'ja_tenho' => 'Já tem site', 'nao_preciso' => 'Acha que não precisa', 'nao_quer' => 'Não quer', 'parar' => 'Pediu para parar', 'outro' => 'Outro assunto'];

// Perfis de risco: cada um liga horário, dias, ritmo, máximo por dia, aquecimento e freios de uma vez.
// O WhatsApp não publica um limite "seguro" para contato frio por número conectado por QR: quem derruba o
// número são os sinais de spam (bloqueios e denúncias, texto igual para muitos, número novo disparando).
// Os perfis são uma leitura das boas práticas; o usuário pode escolher correr mais risco.
function prosp_piloto_perfis() {
    $uteis = [1, 2, 3, 4, 5];
    return [
        'auto' => ['nome' => '🧭 Automático (recomendado)', 'desc' => 'Começa no Seguro e passa sozinho para o Equilibrado quando o número está saudável (gente respondendo e quase ninguém pedindo para parar). Se piorar, volta para o Seguro.'],
        'seguro' => ['nome' => '🛡 Seguro', 'horario' => 'comercial', 'dias' => $uteis, 'ritmo' => 'devagar', 'max' => 40, 'rampa' => [1 => 10, 2 => 18, 3 => 28, 4 => 40], 'freio_recusa' => true,
            'desc' => 'Dias úteis em horário comercial, 8 a 15 min entre mensagens, aquecimento de 4 semanas e até 40 por dia. É o que mais se aproxima das boas práticas.'],
        'equilibrado' => ['nome' => '⚖ Equilibrado', 'horario' => 'estendido', 'dias' => [1, 2, 3, 4, 5, 6], 'ritmo' => 'normal', 'max' => 70, 'rampa' => [1 => 15, 2 => 30, 3 => 50], 'freio_recusa' => true,
            'desc' => 'Segunda a sábado das 8h às 19h, 4 a 10 min entre mensagens e até 70 por dia depois do aquecimento.'],
        'agressivo' => ['nome' => '🔥 Agressivo — 24 horas (risco por minha conta)', 'horario' => '24h', 'dias' => [1, 2, 3, 4, 5, 6, 7], 'ritmo' => 'maximo', 'max' => 300, 'rampa' => [1 => 40, 2 => 100], 'freio_recusa' => false,
            'desc' => 'Todos os dias, 24 horas, 1 a 2 min entre mensagens e até 300 por dia (40 na 1ª semana, 100 na 2ª). Não pausa por recusas; se os envios falharem seguidos, espera 30 min e tenta de novo. Mensagem de madrugada gera mais bloqueios e denúncias — é o perfil com mais chance de perder o número.'],
        'personalizado' => ['nome' => '✋ Personalizado', 'desc' => 'Você escolhe cada botão abaixo (horário, dias, ritmo, máximo e freios).'],
    ];
}

function prosp_piloto_cfg() {
    $perfil = get_config('prosp_auto_perfil', 'auto');
    if (!isset(prosp_piloto_perfis()[$perfil])) $perfil = 'auto';
    $efetivo = $perfil === 'auto' ? (get_config('prosp_auto_nivel', 'seguro') === 'equilibrado' ? 'equilibrado' : 'seguro') : $perfil;
    $c = [
        'perfil' => $perfil,
        'efetivo' => $efetivo,
        'ligado' => get_config('prosp_auto', '0') === '1',
        'max_dia' => max(5, min(300, (int)get_config('prosp_auto_max', '40'))),
        'aquecido' => get_config('prosp_auto_aquecido', '0') === '1',
        'inicio' => (int)get_config('prosp_auto_inicio', '0'),
        'dias' => array_map('intval', array_filter(explode(',', get_config('prosp_auto_dias', '1,2,3,4,5')), 'strlen')),
        'horario' => get_config('prosp_auto_horario', 'comercial'),
        'ritmo' => get_config('prosp_auto_ritmo', 'normal'),
        'rampa' => [1 => 10, 2 => 18, 3 => 28, 4 => 40],
        'freio_recusa' => get_config('prosp_auto_freio_recusa', '1') === '1',
        'ia_escreve' => get_config('prosp_auto_ia', '1') === '1',
        'responde' => get_config('prosp_ia_responde', 'sugerir'),     // nao | sugerir | auto
        'ouvir' => get_config('prosp_ouvir', '1') === '1',
        'pausa' => get_config('prosp_auto_pausa', ''),
    ];
    if ($efetivo === 'personalizado') {
        // Aquecimento escolhido semana a semana (ex.: "10,18,28,40"); depois da 4ª semana vale o máximo
        $r = array_values(array_filter(array_map('intval', explode(',', get_config('prosp_auto_rampa', ''))), fn($n) => $n > 0));
        if (count($r) === 4) $c['rampa'] = [1 => $r[0], 2 => $r[1], 3 => $r[2], 4 => $r[3]];
    } else {
        $p = prosp_piloto_perfis()[$efetivo];
        $c = array_merge($c, ['horario' => $p['horario'], 'dias' => $p['dias'], 'ritmo' => $p['ritmo'], 'max_dia' => $p['max'], 'rampa' => $p['rampa'], 'freio_recusa' => $p['freio_recusa']]);
    }
    return $c;
}

function prosp_piloto_horarios() {
    return ['comercial' => ['nome' => 'Comercial (9h–12h e 14h–18h)', 'faixas' => [[9, 0, 12, 0], [14, 0, 18, 0]]],
            'estendido' => ['nome' => 'Estendido (8h–12h e 13h–19h)', 'faixas' => [[8, 0, 12, 0], [13, 0, 19, 0]]],
            'manha' => ['nome' => 'Só de manhã (9h–12h)', 'faixas' => [[9, 0, 12, 0]]],
            'tarde' => ['nome' => 'Só à tarde (14h–18h)', 'faixas' => [[14, 0, 18, 0]]],
            '24h' => ['nome' => '24 horas (inclusive de madrugada)', 'faixas' => [[0, 0, 24, 0]]]];
}

function prosp_piloto_ritmos() {
    return ['devagar' => ['nome' => 'Bem devagar (8 a 15 min)', 'min' => 8, 'max' => 15],
            'normal' => ['nome' => 'Normal (4 a 10 min)', 'min' => 4, 'max' => 10],
            'rapido' => ['nome' => 'Rápido (2 a 5 min)', 'min' => 2, 'max' => 5],
            'maximo' => ['nome' => 'Máximo (1 a 2 min — mais risco)', 'min' => 1, 'max' => 2]];
}

// Horas por dia em que o piloto pode mandar (para o limite por hora acompanhar o horário escolhido)
function prosp_piloto_horas_janela($c) {
    if ($c['horario'] === 'sempre') return 24;
    $min = 0;
    foreach (prosp_piloto_horarios()[$c['horario']]['faixas'] ?? [] as [$h1, $m1, $h2, $m2]) $min += ($h2 * 60 + $m2) - ($h1 * 60 + $m1);
    return max(1, $min / 60);
}

// Limite de hoje com o aquecimento do perfil (ex.: Seguro: 10/dia na 1ª semana, 18, 28, 40; depois o máximo)
function prosp_piloto_limite_hoje($c = null) {
    $c = $c ?: prosp_piloto_cfg();
    // Personalizado: "só hoje, N mensagens" vale até a meia-noite
    $hoje = explode(':', get_config('prosp_auto_hoje', ''));
    if ($c['perfil'] === 'personalizado' && count($hoje) === 2 && $hoje[0] === date('Y-m-d')) return max(0, min(300, (int)$hoje[1]));
    if ($c['aquecido'] || !$c['inicio']) return $c['max_dia'];
    $semana = intdiv(max(0, time() - $c['inicio']), 7 * 86400) + 1;
    return min($c['max_dia'], $c['rampa'][$semana] ?? $c['max_dia']);
}

// Modo automático: sobe ou desce de nível pela saúde do número (no máximo uma mudança por dia)
function prosp_piloto_ajustar_auto() {
    if (get_config('prosp_auto_perfil', 'auto') !== 'auto') return;
    if (time() - (int)get_config('prosp_auto_nivel_em', '0') < 86400) return;
    $s = prosp_piloto_saude();
    $atual = get_config('prosp_auto_nivel', 'seguro');
    $novo = $atual;
    if ($s['nivel'] === 'bom' && (int)$s['enviados'] >= 30) $novo = 'equilibrado';
    elseif (in_array($s['nivel'], ['atencao', 'ruim'], true)) $novo = 'seguro';
    set_config('prosp_auto_nivel_em', (string)time());
    if ($novo !== $atual) {
        set_config('prosp_auto_nivel', $novo);
        prosp_alertar($novo === 'equilibrado' ? '🧭 Prospecção: número saudável — o piloto passou para o ritmo Equilibrado.' : '🧭 Prospecção: sinais de risco no número — o piloto voltou para o ritmo Seguro.');
    }
}

function prosp_piloto_semana($c = null) {
    $c = $c ?: prosp_piloto_cfg();
    return $c['inicio'] ? intdiv(max(0, time() - $c['inicio']), 7 * 86400) + 1 : 1;
}

// Está dentro do dia e do horário escolhidos?
function prosp_piloto_na_janela($c = null, $agora = null) {
    $c = $c ?: prosp_piloto_cfg();
    $agora = $agora ?: time();
    if ($c['horario'] === 'sempre') return true; // só para testes (não aparece na tela)
    if (!in_array((int)date('N', $agora), $c['dias'], true)) return false;
    if ($c['horario'] === '24h') return true;
    $min = (int)date('G', $agora) * 60 + (int)date('i', $agora);
    foreach (prosp_piloto_horarios()[$c['horario']]['faixas'] ?? prosp_piloto_horarios()['comercial']['faixas'] as [$h1, $m1, $h2, $m2]) {
        if ($min >= $h1 * 60 + $m1 && $min < $h2 * 60 + $m2) return true;
    }
    return false;
}

// O WhatsApp da plataforma está pronto para mandar agora?
function prosp_piloto_canal() {
    if (!function_exists('wa_remetente')) return null;
    $rem = wa_remetente(['wa_modo' => 'plataforma']);
    if (!$rem) return null;
    if ($rem['tipo'] === 'ponte') {
        if (!wa_ponte_online()) return null;
        $l = wa_ponte_ler($rem['instancia']);
        if (!$l || $l['estado'] !== 'open') return null;
    }
    return $rem;
}

function prosp_piloto_contagem() {
    global $pdo;
    $q = fn($sql) => (int)$pdo->query($sql)->fetchColumn();
    return [
        'hoje' => $q("SELECT COUNT(*) FROM whatsapp_mensagens WHERE ref_tipo='prospect' AND evento='prosp_cadencia' AND status IN ('pendente','enviando','enviado') AND criado_em::date = CURRENT_DATE"),
        'hora' => $q("SELECT COUNT(*) FROM whatsapp_mensagens WHERE ref_tipo='prospect' AND evento='prosp_cadencia' AND status IN ('pendente','enviando','enviado') AND criado_em > NOW() - INTERVAL '1 hour'"),
        // (só as de menos de 1 dia: as mais velhas não saem mais e não podem travar o piloto — ver wa_expirar_velhas)
        'na_fila' => $q("SELECT COUNT(*) FROM whatsapp_mensagens WHERE ref_tipo='prospect' AND status IN ('pendente','enviando') AND criado_em > NOW() - INTERVAL '1 day'"),
    ];
}

// Saúde do número nos últimos 7 dias
function prosp_piloto_saude() {
    global $pdo;
    $r = $pdo->query("SELECT
        (SELECT COUNT(DISTINCT ref_id) FROM whatsapp_mensagens WHERE ref_tipo='prospect' AND evento='prosp_cadencia' AND status='enviado' AND enviado_em > NOW() - INTERVAL '7 days') enviados,
        (SELECT COUNT(*) FROM prospects WHERE ultima_resposta_em > NOW() - INTERVAL '7 days') responderam,
        (SELECT COUNT(*) FROM prospects WHERE optout_em > NOW() - INTERVAL '7 days') pararam,
        (SELECT COUNT(*) FROM prospects WHERE ia_intencao='nao_quer' AND ultima_resposta_em > NOW() - INTERVAL '7 days') nao_quer,
        (SELECT COUNT(*) FROM whatsapp_mensagens WHERE ref_tipo='prospect' AND status='erro' AND criado_em > NOW() - INTERVAL '7 days') falhas")->fetch();
    $env = max(0, (int)$r['enviados']);
    $pct = fn($n) => $env ? (int)round(100 * $n / $env) : 0;
    $r['taxa_resposta'] = $pct($r['responderam']);
    $r['taxa_parar'] = $pct($r['pararam']);
    $r['taxa_nao'] = $pct($r['nao_quer']);
    $r['nivel'] = $env < 15 ? 'pouco' : ($r['taxa_parar'] >= 10 || $r['taxa_nao'] >= 20 ? 'ruim' : ($r['taxa_parar'] >= 5 || $r['taxa_resposta'] < 8 ? 'atencao' : 'bom'));
    return $r;
}

function prosp_piloto_pausar($motivo) {
    set_config('prosp_auto_pausa', mb_substr($motivo, 0, 240));
    prosp_alertar('⏸ Prospecção automática pausada: ' . $motivo . ' Veja em Admin → Prospecção → Piloto automático.');
}

// Freios automáticos: muita gente pedindo para parar, recusas ou envios falhando seguidos
function prosp_piloto_freios($c = null) {
    global $pdo;
    $c = $c ?: prosp_piloto_cfg();
    $s = prosp_piloto_saude();
    if ($c['freio_recusa']) {
        if ((int)$s['enviados'] >= 15 && $s['taxa_parar'] >= 15) return "{$s['taxa_parar']}% dos contatos da semana pediram para não receber mais. Revise a mensagem e o público antes de religar.";
        if ((int)$s['enviados'] >= 15 && $s['taxa_nao'] >= 30) return "{$s['taxa_nao']}% responderam que não querem. Ajuste a abordagem antes de continuar.";
    }
    $ult = $pdo->query("SELECT status FROM whatsapp_mensagens WHERE ref_tipo='prospect' AND status IN ('enviado','erro') ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
    if (count($ult) === 3 && !array_diff($ult, ['erro'])) {
        $msg = 'as 3 últimas mensagens falharam (o número pode ter sido restringido). Abra o WhatsApp da plataforma no celular e confira.';
        if (!$c['freio_recusa']) {
            // Perfil sem freio: não desliga — espera 30 min e tenta de novo
            if ((int)get_config('prosp_auto_proximo', '0') < time() + 1500) {
                set_config('prosp_auto_proximo', (string)(time() + 1800));
                prosp_alertar('⚠ Prospecção: ' . $msg . ' Como o perfil não pausa, vou tentar de novo em 30 minutos.');
            }
            return '';
        }
        return $msg;
    }
    return '';
}

// Chave para achar o lead pelo número (DDD + 8 últimos dígitos: resolve o 9º dígito)
function prosp_num_chave($num) {
    $d = preg_replace('/\D/', '', (string)$num);
    if (str_starts_with($d, '55') && strlen($d) >= 12) $d = substr($d, 2);
    return strlen($d) >= 10 ? [substr($d, 0, 2), substr($d, -8)] : null;
}

function prosp_achar_por_numero($num) {
    global $pdo;
    $k = prosp_num_chave($num);
    if (!$k) return null;
    $st = $pdo->prepare("SELECT * FROM prospects WHERE COALESCE(whatsapp,'') <> '' AND RIGHT(regexp_replace(whatsapp, '\\D', '', 'g'), 8) = ?
        AND SUBSTRING(regexp_replace(whatsapp, '\\D', '', 'g') FROM 3 FOR 2) = ? ORDER BY (passo > 0) DESC, ultimo_envio_em DESC NULLS LAST LIMIT 1");
    $st->execute([$k[1], $k[0]]);
    return $st->fetch() ?: null;
}

// Número que já é cliente da plataforma: nunca entra na prospecção automática
function prosp_eh_cliente($num) {
    global $pdo;
    $k = prosp_num_chave($num);
    if (!$k) return false;
    $st = $pdo->prepare("SELECT 1 FROM empresas WHERE RIGHT(regexp_replace(COALESCE(whatsapp,''), '\\D', '', 'g'), 8) = ? LIMIT 1");
    $st->execute([$k[1]]);
    return (bool)$st->fetchColumn();
}

function prosp_conversa($id, $limite = 30) {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM (SELECT * FROM prospect_conversa WHERE prospect_id=? ORDER BY id DESC LIMIT " . (int)$limite . ") x ORDER BY id");
    $st->execute([$id]);
    return $st->fetchAll();
}

function prosp_eh_optout($texto) {
    $t = mb_strtolower(trim($texto));
    return (bool)preg_match('/^(sair|pare|parar|stop|remover|cancelar|descadastrar)\b|\b(n[aã]o (me )?(mand[ae]|envi[ae]) mais|para de (me )?mandar|me (tira|remove|exclui)|n[aã]o quero (receber|mais)|sem interesse|n[aã]o tenho interesse|n[aã]o me interessa|isso [eé] spam|vou (te )?denunciar|denunciar|bloquear)\b/u', $t);
}

// ---------- Enviar pela plataforma ----------
// $tipo: prosp_cadencia (1ª/2ª/3ª mensagem) · prosp_ia (resposta da IA) · prosp_humano (você) · prosp_optout
function prosp_enviar_plataforma($p, $texto, $tipo = 'prosp_humano', $atrasoSeg = 0) {
    global $pdo;
    $texto = trim((string)$texto);
    if ($texto === '' || empty($p['whatsapp'])) return ['ok' => false, 'erro' => 'Sem WhatsApp ou mensagem vazia.'];
    $rem = wa_remetente(['wa_modo' => 'plataforma']);
    if (!$rem) return ['ok' => false, 'erro' => 'O WhatsApp da plataforma não está ligado (Admin → WhatsApp).'];
    $st = $pdo->prepare("INSERT INTO whatsapp_mensagens (empresa_id, telefone, texto, evento, para, ref_tipo, ref_id, status, canal, enviar_apos)
        VALUES (NULL, ?, ?, ?, 'lead', 'prospect', ?, 'pendente', 'plataforma', " . ($atrasoSeg > 0 ? "NOW() + INTERVAL '" . (int)$atrasoSeg . " seconds'" : 'NULL') . ") RETURNING id");
    $st->execute([wa_numero($p['whatsapp']), $texto, $tipo, (int)$p['id']]);
    $msgId = (int)$st->fetchColumn();
    if ($tipo === 'prosp_cadencia') $pdo->prepare("UPDATE prospects SET envio_msg_id=? WHERE id=?")->execute([$msgId, $p['id']]);
    // Na ponte o conector busca sozinho; nos outros, manda já (se não tiver atraso)
    if ($rem['tipo'] !== 'ponte' && $atrasoSeg <= 0) {
        $r = wa_enviar_agora($rem, $p['whatsapp'], $texto);
        if ($r['ok']) $pdo->prepare("UPDATE whatsapp_mensagens SET status='enviado', enviado_em=NOW(), tentativas=tentativas+1, erro=NULL WHERE id=?")->execute([$msgId]);
        else $pdo->prepare("UPDATE whatsapp_mensagens SET status='erro', tentativas=tentativas+1, erro=? WHERE id=?")->execute([mb_substr($r['erro'], 0, 250), $msgId]);
        prosp_envio_resultado($msgId, $r['ok'], $r['erro'] ?? '');
        return ['ok' => $r['ok'], 'erro' => $r['erro'] ?? '', 'msg_id' => $msgId];
    }
    return ['ok' => true, 'fila' => true, 'msg_id' => $msgId];
}

// Chamado quando o envio de uma mensagem de prospecção termina (ponte ou envio direto)
function prosp_envio_resultado($msgId, $ok, $erro = '') {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM whatsapp_mensagens WHERE id=? AND ref_tipo='prospect'");
    $st->execute([$msgId]);
    if (!($m = $st->fetch())) return;
    $pid = (int)$m['ref_id'];
    if ($ok) {
        $autor = ['prosp_cadencia' => 'auto', 'prosp_ia' => 'ia', 'prosp_optout' => 'auto'][$m['evento']] ?? 'humano';
        $pdo->prepare("INSERT INTO prospect_conversa (prospect_id, direcao, autor, texto, msg_id) SELECT ?, 'out', ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM prospect_conversa WHERE msg_id=?)")
            ->execute([$pid, $autor, $m['texto'], $msgId, $msgId]);
        if ($m['evento'] === 'prosp_cadencia') { prosp_registrar_envio($pid); }
        else $pdo->prepare("UPDATE prospects SET ultimo_envio_em=NOW(), atualizado_em=NOW() WHERE id=?")->execute([$pid]);
        $pdo->prepare("UPDATE prospects SET envio_msg_id=NULL WHERE id=? AND envio_msg_id=?")->execute([$pid, $msgId]);
    } elseif ($m['status'] === 'erro' || stripos($erro, 'não tem WhatsApp') !== false) {
        if (stripos($erro, 'não tem WhatsApp') !== false) {
            $pdo->prepare("UPDATE whatsapp_mensagens SET status='erro' WHERE id=?")->execute([$msgId]);
            $pdo->prepare("UPDATE prospects SET wa_existe=false, envio_msg_id=NULL, proximo_contato=NULL WHERE id=?")->execute([$pid]);
        } else $pdo->prepare("UPDATE prospects SET envio_msg_id=NULL WHERE id=? AND envio_msg_id=?")->execute([$pid, $msgId]);
    }
}

// Texto da mensagem escrito pela IA (com o mesmo cuidado da mensagem manual)
function prosp_texto_ia($p, $passo) {
    if (!ia_configurada()) return '';
    $vend = trim(get_config('prosp_vendedor', ''));
    $prompt = "Escreva UMA mensagem de WhatsApp para o dono da empresa abaixo, que não conhece quem está mandando. Objetivo: que ele RESPONDA (não vender agora).\n"
            . "Regras que fazem a pessoa responder em vez de dizer \"não, obrigado\":\n"
            . "- No máximo 4 linhas curtas, tom de gente falando, sem cara de propaganda, sem exageros nem elogios genéricos.\n"
            . ($passo === 1 ? "- Comece confirmando com quem fala (ex.: \"Oi, tudo bem? É da {nome}?\")" . ($vend ? " e se apresente: $vend, da " . (get_config('mkt_marca', '') ?: 'GDS Sites') . "." : '.') . "\n"
                            : "- É a mensagem número $passo (ele ainda não respondeu as anteriores): seja ainda mais breve, sem cobrar resposta e sem repetir a apresentação.\n")
            . "- NUNCA diga que a empresa não tem site (ela pode ter). Fale do que você fez: uma página de demonstração com o nome e as fotos deles.\n"
            . ($passo === 1 ? "- NÃO coloque link nesta primeira mensagem. Termine pedindo permissão para mandar o link, ou com uma pergunta fácil de responder.\n"
                            : "- Inclua o link da demonstração exatamente assim: " . prosp_link_previa($p) . "\n")
            . ($passo >= 3 ? "- É a última mensagem: diga que não vai mais incomodar e deixe a porta aberta.\n" : '')
            . "- Use no máximo um detalhe real (bairro, nota, número de avaliações" . (($el = prosp_elogio($p)) ? " ou o que os clientes mais elogiam nas avaliações: $el" : '') . ") para mostrar que não é mensagem em massa.\n"
            . "- Português do Brasil, no máximo 1 emoji, sem inventar números ou promessas. Responda só com a mensagem.\n\n"
            . "Empresa: {$p['nome']}\nRamo: {$p['categoria']}\nBairro/cidade: " . ($p['bairro'] ?: '-') . " / {$p['cidade']}\nNota: " . ($p['nota'] ?: 'sem nota') . " · {$p['avaliacoes']} avaliações\n"
            . 'Site atual: ' . ($p['site'] ?: 'nenhum') . "\nQuem envia: " . get_config('mkt_marca', 'GDS Sites');
    $r = chamar_ia($prompt, 600, 40, 0.85, null, ['esforco' => 'low']);
    if (!$r['ok']) return '';
    $t = trim($r['texto'], " \n\"");
    // Segurança: a 1ª nunca leva link; as outras precisam do link certo
    if ($passo === 1) $t = trim(preg_replace('#https?://\S+#', '', $t));
    elseif (!str_contains($t, prosp_link_previa($p))) $t .= "\n" . prosp_link_previa($p);
    return mb_strlen($t) > 20 && mb_strlen($t) < 900 ? $t : '';
}

// Próximo lead da cadência: primeiro quem tem retorno marcado, depois os novos mais quentes
function prosp_piloto_proximo() {
    global $pdo;
    $conferir = get_config('prosp_verificar', '1') === '1';
    $base = "NOT tem_site AND COALESCE(whatsapp,'') <> '' AND optout_em IS NULL AND envio_msg_id IS NULL AND status NOT IN ('fechado','descartado','negociando')
             AND (ultimo_envio_em IS NULL OR ultimo_envio_em < NOW() - INTERVAL '20 hours') AND COALESCE(wa_existe, true)";
    $ret = $pdo->query("SELECT * FROM prospects WHERE $base AND passo BETWEEN 1 AND 2 AND respondeu_em IS NULL AND proximo_contato <= CURRENT_DATE
        ORDER BY (previa_aberta_em IS NOT NULL) DESC, proximo_contato, pontuacao DESC LIMIT 5")->fetchAll();
    $novos = $pdo->query("SELECT * FROM prospects WHERE $base AND passo = 0 AND status='novo'" . ($conferir ? " AND verificado_em IS NOT NULL AND wa_existe IS TRUE" : '') . "
        ORDER BY pontuacao DESC, avaliacoes DESC LIMIT 5")->fetchAll();
    foreach (array_merge($ret, $novos) as $p) {
        if (prosp_eh_cliente($p['whatsapp'])) { $pdo->prepare("UPDATE prospects SET status='descartado', observacoes=CONCAT(COALESCE(observacoes,''), ' [já é cliente]') WHERE id=?")->execute([$p['id']]); continue; }
        return $p;
    }
    return null;
}

// Uma "volta" do piloto: manda no máximo UMA mensagem (o intervalo entre elas é aleatório)
function prosp_piloto_rotina() {
    global $pdo;
    prosp_piloto_ajustar_auto();
    $c = prosp_piloto_cfg();
    if (!$c['ligado'] || $c['pausa'] !== '') return ['piloto' => $c['pausa'] ? 'pausado' : 'desligado'];
    if (!prosp_piloto_na_janela($c)) return ['piloto' => 'fora do horário'];
    if (time() < (int)get_config('prosp_auto_proximo', '0')) return ['piloto' => 'aguardando intervalo'];
    $rem = prosp_piloto_canal();
    if (!$rem) return ['piloto' => 'whatsapp da plataforma desconectado'];
    $n = prosp_piloto_contagem();
    if ($n['na_fila'] > 0) return ['piloto' => 'mensagem anterior ainda saindo'];
    $limite = prosp_piloto_limite_hoje($c);
    if ($n['hoje'] >= $limite) return ['piloto' => "limite de hoje ($limite) atingido"];
    if ($n['hora'] >= max(4, (int)ceil($limite / prosp_piloto_horas_janela($c) * 2))) return ['piloto' => 'limite por hora atingido'];
    if (($freio = prosp_piloto_freios($c)) !== '') { prosp_piloto_pausar($freio); return ['piloto' => 'pausado: ' . $freio]; }
    if (time() < (int)get_config('prosp_auto_proximo', '0')) return ['piloto' => 'aguardando para tentar de novo'];
    // trava simples: duas rotinas ao mesmo tempo não mandam em dobro
    if ((int)get_config('prosp_auto_trava', '0') > time() - 60) return ['piloto' => 'ocupado'];
    set_config('prosp_auto_trava', (string)time());
    $p = prosp_piloto_proximo();
    if (!$p) { set_config('prosp_auto_trava', '0'); return ['piloto' => 'sem leads prontos (rode uma caçada)']; }
    $passo = min(3, (int)$p['passo'] + 1);
    $texto = $c['ia_escreve'] ? prosp_texto_ia($p, $passo) : '';
    if ($texto === '') $texto = prosp_mensagem($p, $passo);
    $r = prosp_enviar_plataforma($p, $texto, 'prosp_cadencia');
    $rt = prosp_piloto_ritmos()[$c['ritmo']] ?? prosp_piloto_ritmos()['normal'];
    set_config('prosp_auto_proximo', (string)(time() + random_int($rt['min'] * 60, $rt['max'] * 60)));
    set_config('prosp_auto_trava', '0');
    return ['piloto' => ($r['ok'] ? 'enviando para ' : 'falhou para ') . $p['nome'] . " (mensagem $passo)"];
}

function prosp_piloto_status() {
    $c = prosp_piloto_cfg();
    $n = prosp_piloto_contagem();
    $prox = (int)get_config('prosp_auto_proximo', '0');
    $canal = prosp_piloto_canal();
    $situacao = !$c['ligado'] ? 'Desligado' : ($c['pausa'] ? 'Pausado' : (!$canal ? 'Aguardando o WhatsApp da plataforma conectar'
        : (!prosp_piloto_na_janela($c) ? 'Fora do horário — volta no próximo horário escolhido' : ($n['hoje'] >= prosp_piloto_limite_hoje($c) ? 'Limite de hoje atingido — continua amanhã'
        : ($n['na_fila'] > 0 ? 'Esperando a mensagem anterior sair pelo WhatsApp'
        : ($prox > time() ? 'Próxima mensagem por volta das ' . date('H:i', $prox) : 'Mandando'))))));
    $perfis = prosp_piloto_perfis();
    $perfilTxt = $perfis[$c['perfil']]['nome'] . ($c['perfil'] === 'auto' ? ' — agora no ' . $perfis[$c['efetivo']]['nome'] : '');
    return ['perfil' => $c['perfil'], 'perfil_txt' => $perfilTxt, 'ligado' => $c['ligado'], 'pausa' => $c['pausa'], 'situacao' => $situacao, 'hoje' => $n['hoje'], 'limite' => prosp_piloto_limite_hoje($c),
            'semana' => prosp_piloto_semana($c), 'aquecido' => $c['aquecido'], 'canal' => (bool)$canal, 'saude' => prosp_piloto_saude()];
}

// ---------- Respostas que chegam ----------
// Retorna o id do lead (ou null se o número não é de nenhum lead)
function prosp_receber($numero, $texto, $nome = '', $waId = '') {
    global $pdo;
    $p = prosp_achar_por_numero($numero);
    if (!$p) return null;
    try {
        $pdo->prepare("INSERT INTO prospect_conversa (prospect_id, direcao, autor, texto, wa_id) VALUES (?, 'in', 'lead', ?, ?)")
            ->execute([$p['id'], mb_substr($texto, 0, 2000), $waId !== '' ? mb_substr($waId, 0, 120) : null]);
    } catch (PDOException $e) { return null; } // mesma mensagem chegou duas vezes
    $pdo->prepare("UPDATE prospects SET respondeu_em=COALESCE(respondeu_em, NOW()), ultima_resposta_em=NOW(), nao_lidas=COALESCE(nao_lidas,0)+1, atualizado_em=NOW(),
        status = CASE WHEN status IN ('novo','contatado') THEN 'respondeu' ELSE status END WHERE id=?")->execute([$p['id']]);
    // Cancela os próximos toques da cadência: agora é conversa
    $pdo->prepare("DELETE FROM whatsapp_mensagens WHERE ref_tipo='prospect' AND ref_id=? AND evento='prosp_cadencia' AND status='pendente'")->execute([$p['id']]);
    if (prosp_eh_optout($texto)) {
        $pdo->prepare("UPDATE prospects SET optout_em=NOW(), status='descartado', proximo_contato=NULL, ia_intencao='parar', ia_leitura='Pediu para não receber mais mensagens.' WHERE id=?")->execute([$p['id']]);
        if (get_config('prosp_optout_responder', '1') === '1') prosp_enviar_plataforma($p, 'Combinado, não te mando mais mensagens. Desculpa o incômodo e boa sorte com o negócio! 🙏', 'prosp_optout', random_int(40, 120));
        return (int)$p['id'];
    }
    return (int)$p['id'];
}

// A IA lê a conversa, marca a intenção, sugere respostas e (no modo automático) responde
function prosp_analisar_resposta($id) {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM prospects WHERE id=?"); $st->execute([$id]);
    $p = $st->fetch();
    if (!$p || $p['optout_em']) {
        if ($p) prosp_alertar("🙅 {$p['nome']} pediu para não receber mais mensagens (já saiu da prospecção).");
        return null;
    }
    $conv = prosp_conversa($id, 14);
    $ultima = '';
    foreach (array_reverse($conv) as $m) if ($m['direcao'] === 'in') { $ultima = $m['texto']; break; }
    $historico = implode("\n", array_map(fn($m) => ($m['direcao'] === 'in' ? 'CLIENTE: ' : 'NÓS: ') . $m['texto'], $conv));
    $d = null;
    if (ia_configurada()) {
        $prompt = prosp_prompt_vendedor($p, $historico);
        $r = chamar_ia_json($prompt, 900, 45, 0.5, ['esforco' => 'low']);
        if ($r['ok']) $d = $r['dados'];
    }
    if (!$d) $d = prosp_ler_sem_ia($ultima, $p);
    $int = array_key_exists($d['intencao'] ?? '', PROSP_INTENCOES) ? $d['intencao'] : 'outro';
    $sug = array_values(array_filter(array_map(fn($s) => mb_substr(trim((string)$s), 0, 900), (array)($d['respostas'] ?? []))));
    $pdo->prepare("UPDATE prospects SET ia_intencao=?, ia_leitura=?, ia_sugestoes=? WHERE id=?")
        ->execute([$int, mb_substr((string)($d['leitura'] ?? ''), 0, 300), json_encode(['respostas' => $sug, 'proximo_passo' => mb_substr((string)($d['proximo_passo'] ?? ''), 0, 300)], JSON_UNESCAPED_UNICODE), $id]);
    // Efeitos no funil
    if ($int === 'nao_quer') $pdo->prepare("UPDATE prospects SET proximo_contato=NULL, status='descartado' WHERE id=?")->execute([$id]);
    elseif ($int === 'ja_tenho') $pdo->prepare("UPDATE prospects SET proximo_contato=NULL WHERE id=?")->execute([$id]);
    elseif ($int === 'sem_tempo') $pdo->prepare("UPDATE prospects SET proximo_contato=CURRENT_DATE + 7 WHERE id=?")->execute([$id]);
    elseif (in_array($int, ['interessado', 'preco', 'link', 'fechar'], true)) $pdo->prepare("UPDATE prospects SET status=CASE WHEN status IN ('novo','contatado','respondeu') THEN 'negociando' ELSE status END WHERE id=?")->execute([$id]);
    // Responde sozinho: no automático a IA conversa até fechar (com um teto por lead para nunca virar robô sem fim)
    $respondeu = false; $fechou = false;
    $modo = get_config('prosp_ia_responde', 'sugerir');
    $max = max(2, min(30, (int)get_config('prosp_ia_max', '12')));
    $jaCliente = !empty($p['empresa_id']) || $p['status'] === 'fechado';
    $email = (string)($d['email'] ?? '');
    if ($email === '' && preg_match('/[\w.+-]+@[\w-]+\.[\w.]+/u', $ultima, $mm)) $email = $mm[0];
    if ($modo === 'auto' && !$jaCliente && $int === 'fechar' && get_config('prosp_ia_fecha', '1') === '1') {
        $f = prosp_ia_fechar($p, $email);
        $fechou = $respondeu = !empty($f['ok']);
    } elseif ($modo === 'auto' && !$jaCliente && $sug && (int)$p['auto_respostas'] < $max && !empty($d['seguro_responder_sozinho']) && $int !== 'parar') {
        $r = prosp_enviar_plataforma($p, $sug[0], 'prosp_ia', random_int(45, 150)); // resposta humana não sai em 1 segundo
        if ($r['ok']) { $respondeu = true; $pdo->prepare("UPDATE prospects SET auto_respostas=COALESCE(auto_respostas,0)+1 WHERE id=?")->execute([$id]); }
    }
    if (get_config('prosp_alerta_respostas', '1') === '1' && !$fechou) {
        prosp_alertar("💬 {$p['nome']} respondeu: \"" . mb_strimwidth($ultima, 0, 160, '…') . "\"\nLeitura da IA: " . PROSP_INTENCOES[$int]
            . ($respondeu ? "\n🤖 A IA já respondeu por você." : ($int === 'fechar' ? "\n💰 Quer fechar! Prospecção → 🎉 Fechou! Virar cliente." : "\nResponda em Admin → Prospecção → Conversas.")));
    }
    return $int;
}

// Sem IA: leitura simples por palavras (para o funil não ficar parado)
function prosp_ler_sem_ia($texto, $p) {
    $t = mb_strtolower($texto);
    $link = prosp_link_previa($p);
    $int = match (true) {
        (bool)preg_match('/quanto|pre[cç]o|valor|custa|mensal/u', $t) => 'preco',
        (bool)preg_match('/fechado|pode fazer|pode criar|vamos fazer|bora fazer|quero o site|quero fechar|aceito|manda o pix|como pago/u', $t) => 'fechar',
        (bool)preg_match('/manda|envia|pode mandar|link|quero ver|mostra/u', $t) => 'link',
        (bool)preg_match('/quem [eé]|quem fala|de onde|qual empresa/u', $t) => 'quem',
        (bool)preg_match('/j[aá] tenho|temos site|tenho site/u', $t) => 'ja_tenho',
        (bool)preg_match('/depois|agora n[aã]o|sem tempo|ocupad/u', $t) => 'sem_tempo',
        (bool)preg_match('/n[aã]o (quero|preciso)|obrigad[oa],? n[aã]o/u', $t) => 'nao_quer',
        (bool)preg_match('/sim|quero|pode|interess|legal|top|show/u', $t) => 'interessado',
        default => 'outro',
    };
    $resp = ['link' => ["Claro! Aqui está: $link\nMontei com o nome e as fotos de vocês. Me conta o que achou?"],
             'interessado' => ["Que bom! Aqui está a demonstração: $link\nSe gostar, deixo do jeito de vocês e no ar ainda essa semana."],
             'quem' => ['Sou ' . (get_config('prosp_vendedor', '') ?: 'da equipe') . ' da ' . (get_config('mkt_marca', '') ?: 'GDS Sites') . ". A gente monta sites para negócios da região. Fiz uma demonstração com o nome de vocês: $link"],
             'preco' => [(($pr = prosp_oferta_txt()) ? "Para começar: $pr, sem fidelidade." : 'Depende do plano, e é sem fidelidade.') . " Já vem com agendamento online, confirmação no WhatsApp e o seu domínio. Olha como fica o de vocês: $link\nPosso deixar o seu no ar hoje?"],
             'ja_tenho' => ['Ah, que ótimo! Se um dia quiser comparar, a demonstração fica aqui: ' . $link . ' Sucesso aí!'],
             'sem_tempo' => ['Tranquilo! Te chamo semana que vem, pode ser?'],
             'nao_quer' => ['Sem problema, obrigado pela resposta! Sucesso por aí 🙏']][$int] ?? [];
    return ['intencao' => $int, 'leitura' => 'Leitura automática por palavras (ligue a IA para uma leitura melhor).', 'respostas' => $resp, 'proximo_passo' => '', 'seguro_responder_sozinho' => in_array($int, ['link', 'quem'], true)];
}

// Chegou pela ponte ou pelo webhook: grava já e deixa a IA para depois de responder
function prosp_processar_recebidas(array $lista, $instancia) {
    $a = wa_api();
    if ($instancia !== $a['plat_instancia'] || get_config('prosp_ouvir', '1') !== '1') return [];
    $ids = [];
    foreach (array_slice($lista, 0, 100) as $m) {
        $id = prosp_receber((string)($m['numero'] ?? ''), (string)($m['texto'] ?? ''), (string)($m['nome'] ?? ''), (string)($m['id'] ?? ''));
        if ($id) $ids[$id] = true;
    }
    return array_keys($ids);
}

// Sem a ponte (Evolution, Z-API, Cloud...): manda as respostas que ficaram agendadas
function prosp_fila_direta($limite = 5) {
    global $pdo;
    if (wa_api()['tipo'] === 'ponte') return 0;
    $rem = wa_remetente(['wa_modo' => 'plataforma']);
    if (!$rem) return 0;
    $st = $pdo->query("SELECT * FROM whatsapp_mensagens WHERE ref_tipo='prospect' AND status='pendente' AND tentativas < 3 AND (enviar_apos IS NULL OR enviar_apos <= NOW())
        AND criado_em > NOW() - INTERVAL '1 day' ORDER BY id LIMIT " . (int)$limite);
    $n = 0;
    foreach ($st->fetchAll() as $m) {
        $r = wa_enviar_agora($rem, $m['telefone'], $m['texto']);
        if ($r['ok']) $pdo->prepare("UPDATE whatsapp_mensagens SET status='enviado', enviado_em=NOW(), tentativas=tentativas+1, erro=NULL WHERE id=?")->execute([$m['id']]);
        else $pdo->prepare("UPDATE whatsapp_mensagens SET tentativas=tentativas+1, erro=?, status=CASE WHEN tentativas+1 >= 3 THEN 'erro' ELSE 'pendente' END WHERE id=?")->execute([mb_substr($r['erro'], 0, 250), $m['id']]);
        prosp_envio_resultado((int)$m['id'], $r['ok'], $r['erro'] ?? '');
        $n++;
    }
    return $n;
}

// Rotina completa (cron / de carona): respostas agendadas + próxima mensagem da cadência
function prosp_piloto_tudo() {
    try { $n = prosp_fila_direta(); $r = prosp_piloto_rotina(); return $r + ['fila_direta' => $n]; }
    catch (Throwable $e) { error_log('GDS piloto: ' . $e->getMessage()); return ['piloto' => 'erro: ' . $e->getMessage()]; }
}
