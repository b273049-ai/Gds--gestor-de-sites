<?php
// ============================================================
// TREINO DA SEMANA E VALE-PRESENTE (migração 44) — ligados em Recursos do site
//  • Treino da semana (academia, personal, pilates, crossfit…): o professor monta a ficha de cada aluno
//    por dia da semana; o aluno abre um link no celular, vê o treino de hoje, marca os exercícios e faz
//    o check-in. O professor vê quantos dias o aluno treinou.
//  • Vale-presente: o cliente compra pelo site (Pix da própria empresa, que confirma sozinho; sem Pix
//    ligado, a empresa confirma no painel) e quem ganha recebe o cartão-presente no WhatsApp. No balcão,
//    a empresa abate o valor usado e o saldo fica guardado.
// ============================================================

const TREINO_DIAS = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 0 => 'Domingo'];

// ---------- treino ----------
// Plano: { "1": [ {nome, series, obs, video}, … ], "3": [...] }  (chave = dia da semana, 0 = domingo)
function treino_normalizar($json) {
    $d = is_array($json) ? $json : json_decode((string)$json, true);
    $out = [];
    foreach (TREINO_DIAS as $dia => $_) {
        $itens = [];
        foreach (array_slice((array)($d[$dia] ?? $d[(string)$dia] ?? []), 0, 20) as $x) {
            if (!is_array($x)) continue;
            $nome = mb_substr(trim(strip_tags((string)($x['nome'] ?? ''))), 0, 80);
            if ($nome === '') continue;
            $video = trim((string)($x['video'] ?? ''));
            $itens[] = ['nome' => $nome, 'series' => mb_substr(trim(strip_tags((string)($x['series'] ?? ''))), 0, 40), 'obs' => mb_substr(trim(strip_tags((string)($x['obs'] ?? ''))), 0, 120),
                        'video' => preg_match('#^https://#', $video) ? mb_substr($video, 0, 300) : ''];
        }
        if ($itens) $out[(string)$dia] = $itens;
    }
    return $out;
}

function treino_salvar($empresaId, $post) {
    global $pdo;
    $nome = mb_substr(trim(strip_tags((string)($post['aluno_nome'] ?? ''))), 0, 120);
    $tel = preg_replace('/\D/', '', (string)($post['aluno_tel'] ?? ''));
    $titulo = mb_substr(trim(strip_tags((string)($post['titulo'] ?? ''))), 0, 80);
    $plano = treino_normalizar($post['plano'] ?? '');
    if ($nome === '') return [false, 'Informe o nome do aluno.', null];
    if (!$plano) return [false, 'Coloque pelo menos um exercício em algum dia.', null];
    $id = (int)($post['id'] ?? 0);
    $j = json_encode($plano, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($id) {
        $st = $pdo->prepare("UPDATE treinos SET aluno_nome=?, aluno_tel=?, titulo=?, plano=?, atualizado_em=NOW() WHERE id=? AND empresa_id=?");
        $st->execute([$nome, $tel ?: null, $titulo ?: null, $j, $id, (int)$empresaId]);
        return [$st->rowCount() > 0, 'Treino atualizado. O aluno já vê a versão nova no mesmo link.', $id];
    }
    $cli = null;
    if (strlen($tel) >= 10) { $c = $pdo->prepare("SELECT id FROM clientes WHERE empresa_id=? AND RIGHT(regexp_replace(COALESCE(telefone,''), '\\D', '', 'g'), 11)=? LIMIT 1"); $c->execute([(int)$empresaId, substr($tel, -11)]); $cli = $c->fetchColumn() ?: null; }
    $st = $pdo->prepare("INSERT INTO treinos (empresa_id, cliente_id, aluno_nome, aluno_tel, titulo, plano, token) VALUES (?,?,?,?,?,?,?) RETURNING id");
    $st->execute([(int)$empresaId, $cli, $nome, $tel ?: null, $titulo ?: null, $j, bin2hex(random_bytes(12))]);
    return [true, 'Treino criado! Mande o link para o aluno.', (int)$st->fetchColumn()];
}

function treino_por_token($token) {
    global $pdo;
    $st = $pdo->prepare("SELECT t.*, e.nome AS empresa_nome, e.slug, e.whatsapp AS empresa_whatsapp, s.cor_primaria FROM treinos t JOIN empresas e ON e.id=t.empresa_id LEFT JOIN sites s ON s.empresa_id=e.id WHERE t.token=? AND t.ativo=true");
    $st->execute([(string)$token]);
    return $st->fetch() ?: null;
}

function treino_checkins($treinoId, $dias = 35) {
    global $pdo;
    $st = $pdo->prepare("SELECT data FROM treino_checkins WHERE treino_id=? AND data > CURRENT_DATE - CAST(? AS int) ORDER BY data");
    $st->execute([(int)$treinoId, (int)$dias]);
    return array_column($st->fetchAll(), 'data');
}

function treino_checkin($treinoId) {
    global $pdo;
    try { $pdo->prepare("INSERT INTO treino_checkins (treino_id, data) VALUES (?, CURRENT_DATE)")->execute([(int)$treinoId]); return true; }
    catch (PDOException $e) { return false; }   // já fez hoje
}

function treino_link($empresa, $t) {
    return function_exists('wa_url_publica') ? wa_url_publica($empresa, '/site/treino.php?t=' . $t['token']) : '/site/treino.php?t=' . $t['token'];
}

// ---------- vale-presente ----------
function vale_valores() { return [50, 80, 100, 150, 200, 300]; }

function vale_codigo() {
    $c = strtoupper(substr(str_replace(['0', 'O', '1', 'I'], ['7', 'X', '8', 'Y'], bin2hex(random_bytes(4))), 0, 8));
    return substr($c, 0, 4) . '-' . substr($c, 4, 4);
}

function vale_criar($empresa, $post, $r) {
    global $pdo;
    $valor = (float)($post['valor'] ?? 0);
    $comp = mb_substr(trim(strip_tags((string)($post['comprador'] ?? ''))), 0, 120);
    $compTel = preg_replace('/\D/', '', (string)($post['comprador_tel'] ?? ''));
    $para = mb_substr(trim(strip_tags((string)($post['para'] ?? ''))), 0, 120);
    $paraTel = preg_replace('/\D/', '', (string)($post['para_tel'] ?? ''));
    $msg = mb_substr(trim(strip_tags((string)($post['mensagem'] ?? ''))), 0, 300);
    if (!in_array((int)$valor, vale_valores(), true)) return [false, 'Escolha um dos valores.', null];
    if ($comp === '' || strlen($compTel) < 10) return [false, 'Informe o seu nome e o seu WhatsApp com DDD.', null];
    if ($para === '') return [false, 'Para quem é o presente?', null];
    if ($paraTel !== '' && strlen($paraTel) < 10) return [false, 'Confira o WhatsApp de quem vai ganhar (com DDD) ou deixe em branco.', null];
    $token = bin2hex(random_bytes(12));
    $codigo = vale_codigo();
    $pagToken = null;
    if (function_exists('pag_disponivel') && pag_disponivel($empresa)) {
        $pg = pag_criar($empresa, $valor, 'Vale-presente ' . $codigo . ' — ' . $empresa['nome'], 'vale', []);
        if (!empty($pg['ok'])) $pagToken = $pg['token'];
    }
    $pdo->prepare("INSERT INTO vales_presente (empresa_id, codigo, valor, saldo, comprador_nome, comprador_tel, para_nome, para_tel, mensagem, pag_token, token) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$empresa['id'], $codigo, $valor, $valor, $comp, $compTel, $para, $paraTel ?: null, $msg ?: null, $pagToken, $token]);
    if (!$pagToken && function_exists('wa_enfileirar') && !empty($empresa['whatsapp'])) {
        if (function_exists('wa_adiar')) wa_adiar();
        wa_enfileirar($empresa, $empresa['whatsapp'], "🎁 *Pedido de vale-presente pelo site*\n{$comp} ({$compTel}) quer dar R$ " . number_format($valor, 2, ',', '.') . " para {$para}.\nCódigo: {$codigo}\nCombine o pagamento e confirme no painel: Vales-presente.", 'aviso_vale', 'empresa');
    }
    return [true, 'Vale criado!', '/site/vale.php?t=' . $token];
}

function vale_por_token($token) {
    global $pdo;
    $st = $pdo->prepare("SELECT v.*, e.nome AS empresa_nome, e.slug, e.whatsapp AS empresa_whatsapp, e.dominio_personalizado, e.dominio_status, s.cor_primaria FROM vales_presente v JOIN empresas e ON e.id=v.empresa_id LEFT JOIN sites s ON s.empresa_id=e.id WHERE v.token=?");
    $st->execute([(string)$token]);
    return $st->fetch() ?: null;
}

// Pagamento confirmado (Pix) ou confirmação manual no painel: ativa e manda o presente
function vale_ativar($valeId, $validadeDias = 180) {
    global $pdo;
    $st = $pdo->prepare("UPDATE vales_presente SET status='ativo', ativado_em=NOW(), validade=CURRENT_DATE + CAST(? AS int) WHERE id=? AND status='pendente' RETURNING *");
    $st->execute([max(30, (int)$validadeDias), (int)$valeId]);
    $v = $st->fetch();
    if (!$v) return false;
    $emp = function_exists('wa_carregar_empresa') ? wa_carregar_empresa($v['empresa_id']) : null;
    if ($emp && function_exists('wa_enfileirar')) {
        if (function_exists('wa_adiar')) wa_adiar();
        $link = wa_url_publica($emp, '/site/vale.php?t=' . $v['token']);
        $valor = 'R$ ' . number_format((float)$v['valor'], 2, ',', '.');
        if ($v['para_tel']) wa_enfileirar($emp, $v['para_tel'], "🎁 {$v['para_nome']}, você ganhou um *vale-presente de {$valor}* na *{$emp['nome']}*, de {$v['comprador_nome']}!" . ($v['mensagem'] ? "\n\n“{$v['mensagem']}”" : '') . "\n\nAbra o seu cartão: {$link}", 'vale_presente');
        wa_enfileirar($emp, $v['comprador_tel'], "✅ Pagamento confirmado! O vale-presente de {$valor} para {$v['para_nome']} está ativo." . ($v['para_tel'] ? ' Já mandamos no WhatsApp da pessoa.' : ' Mande este link para quem vai ganhar:') . "\n{$link}", 'vale_comprador');
    }
    return true;
}

function vale_ativar_por_pag($pagToken) {
    global $pdo;
    $st = $pdo->prepare("SELECT v.id, s.recursos FROM vales_presente v LEFT JOIN sites s ON s.empresa_id=v.empresa_id WHERE v.pag_token=?");
    $st->execute([(string)$pagToken]);
    if ($x = $st->fetch()) vale_ativar($x['id'], (int)(json_decode((string)$x['recursos'], true)['vale']['validade'] ?? 180));
}

function vale_usar($empresaId, $codigo, $valor) {
    global $pdo;
    $codigo = strtoupper(trim((string)$codigo));
    $valor = round((float)str_replace(',', '.', (string)$valor), 2);
    $st = $pdo->prepare("SELECT * FROM vales_presente WHERE empresa_id=? AND codigo=?");
    $st->execute([(int)$empresaId, $codigo]);
    $v = $st->fetch();
    if (!$v) return [false, 'Código não encontrado.'];
    if ($v['status'] !== 'ativo') return [false, 'Este vale está ' . ($v['status'] === 'pendente' ? 'aguardando pagamento' : $v['status']) . '.'];
    if ($v['validade'] && $v['validade'] < date('Y-m-d')) return [false, 'Este vale venceu em ' . date('d/m/Y', strtotime($v['validade'])) . '.'];
    if ($valor <= 0 || $valor > (float)$v['saldo'] + 0.001) return [false, 'Valor maior que o saldo (R$ ' . number_format((float)$v['saldo'], 2, ',', '.') . ').'];
    $saldo = round((float)$v['saldo'] - $valor, 2);
    $pdo->prepare("UPDATE vales_presente SET saldo=?, status=CASE WHEN ? <= 0 THEN 'usado' ELSE status END WHERE id=?")->execute([$saldo, $saldo, $v['id']]);
    return [true, 'Usado R$ ' . number_format($valor, 2, ',', '.') . '. ' . ($saldo > 0 ? 'Saldo: R$ ' . number_format($saldo, 2, ',', '.') . '.' : 'Vale totalmente usado.')];
}

// Formulário de compra no site
function vale_html_form($empresa) {
    $op = '';
    foreach (vale_valores() as $i => $v) $op .= '<label class="lp-vale-valor"><input type="radio" name="valor" value="' . $v . '"' . ($v === 100 ? ' checked' : '') . '><span>R$ ' . $v . '</span></label>';
    return '<form class="lp-rest-form lp-vale-form" data-vale data-e="' . (int)$empresa['id'] . '" novalidate><fieldset class="lp-vale-valores"><legend>Valor do presente</legend>' . $op . '</fieldset><div class="lp-rest-grade">'
        . '<div><label for="vlPara">Para quem é?</label><input id="vlPara" name="para" required></div>'
        . '<div><label for="vlParaTel">WhatsApp de quem vai ganhar (opcional)</label><input id="vlParaTel" name="para_tel" type="tel" inputmode="tel"></div>'
        . '<div class="lp-rest-largo"><label for="vlMsg">Mensagem (opcional)</label><input id="vlMsg" name="mensagem" maxlength="300" placeholder="Feliz aniversário! Aproveita esse momento só seu."></div>'
        . '<div><label for="vlComp">Seu nome</label><input id="vlComp" name="comprador" autocomplete="name" required></div>'
        . '<div><label for="vlCompTel">Seu WhatsApp</label><input id="vlCompTel" name="comprador_tel" type="tel" inputmode="tel" autocomplete="tel" required></div>'
        . '</div><button type="submit" class="lp-btn">Continuar para o pagamento</button><p class="lp-rest-res" role="status" aria-live="polite"></p></form>';
}
