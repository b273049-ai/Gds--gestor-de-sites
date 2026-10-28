<?php
// ============================================================
// RENDERIZAÇÃO DO SITE PÚBLICO (uma só implementação, usada pelo
// site de verdade em site/index.php e pela pré-visualização em
// site/preview.php).
// ============================================================
require_once __DIR__ . '/site_fragmentos.php';
require_once __DIR__ . '/agenda_helper.php';
require_once __DIR__ . '/atendimento.php';
require_once __DIR__ . '/whatsapp.php';
require_once __DIR__ . '/modulos.php';
require_once __DIR__ . '/fotos.php';
require_once __DIR__ . '/site_template.php';

const SITE_SQL_BASE = "
    SELECT e.*, s.titulo, s.subtitulo, s.sobre, s.publicado, s.cor_primaria, s.cor_secundaria,
           s.galeria_imagens, s.seo_titulo, s.seo_descricao, s.layout, s.secoes, s.banner_imagem,
           s.html_gerado, s.ia_modo_total, s.ia_tema, s.estilo, s.fotos_banco,
           seg.nome AS segmento_nome, e.segmento_descricao AS segmento_descricao_emp, seg.slug AS segmento_slug, seg.modo_atendimento AS modo_segmento, seg.cta AS segmento_cta, seg.grupo AS segmento_grupo,
           pl.permite_pix
    FROM empresas e
    JOIN sites s ON s.empresa_id = e.id
    LEFT JOIN segmentos seg ON seg.id = e.segmento_id
    LEFT JOIN planos pl ON pl.id = e.plano_id
";

// Busca a empresa do site; se o banco ainda não tiver a coluna nova, cai na consulta antiga
function site_buscar_empresa($where, $param) {
    global $pdo;
    try {
        $st = $pdo->prepare(SITE_SQL_BASE . " WHERE $where LIMIT 1");
        $st->execute([$param]);
        return $st->fetch();
    } catch (PDOException $e) {
        $legado = str_replace([', s.ia_tema, s.estilo, s.fotos_banco', ', e.segmento_descricao AS segmento_descricao_emp', 'seg.modo_atendimento AS modo_segmento, seg.cta AS segmento_cta, seg.grupo AS segmento_grupo'], [', NULL AS ia_tema, NULL AS estilo, NULL AS fotos_banco', '', 'NULL AS modo_segmento, NULL AS segmento_cta, NULL AS segmento_grupo'], SITE_SQL_BASE);
        $st = $pdo->prepare($legado . " WHERE $where LIMIT 1");
        $st->execute([$param]);
        return $st->fetch();
    }
}

function site_carregar_dados($empresa) {
    global $pdo;
    $id = $empresa['id'];
    $q = function ($sql, $alternativa = null) use ($pdo, $id) {
        try { $s = $pdo->prepare($sql); $s->execute([$id]); return $s->fetchAll(); }
        catch (PDOException $e) { if (!$alternativa) throw $e; $s = $pdo->prepare($alternativa); $s->execute([$id]); return $s->fetchAll(); }
    };
    $d = [];
    $d['servicos'] = $q("SELECT * FROM servicos WHERE empresa_id=? AND ativo=true ORDER BY destaque DESC, ordem, nome",
                        "SELECT * FROM servicos WHERE empresa_id=? AND ativo=true ORDER BY nome");
    $d['profissionais'] = $q("SELECT * FROM profissionais WHERE empresa_id=? AND ativo=true ORDER BY nome");
    $d['cupons'] = $q("SELECT * FROM cupons WHERE empresa_id=? AND ativo=true AND (validade IS NULL OR validade >= CURRENT_DATE)");
    $d['blocos'] = $q("SELECT * FROM site_blocos WHERE empresa_id=? AND ativo=true ORDER BY ordem, id");
    $d['avaliacoes'] = $q("SELECT av.nota, av.comentario, av.criado_em, COALESCE(NULLIF(av.nome_autor,''), c.nome, 'Cliente') AS autor
            FROM avaliacoes av LEFT JOIN clientes c ON c.id = av.cliente_id
            WHERE av.empresa_id=? AND COALESCE(av.aprovado, true) ORDER BY av.criado_em DESC LIMIT 9",
        "SELECT av.nota, av.comentario, av.criado_em, COALESCE(c.nome, 'Cliente') AS autor FROM avaliacoes av
            LEFT JOIN clientes c ON c.id = av.cliente_id WHERE av.empresa_id=? ORDER BY av.criado_em DESC LIMIT 9");
    // Resumo com TODAS as avaliações aprovadas (média e distribuição), não só as 9 exibidas
    $res = $q("SELECT nota, COUNT(*) c FROM avaliacoes WHERE empresa_id=? AND COALESCE(aprovado, true) GROUP BY nota",
              "SELECT nota, COUNT(*) c FROM avaliacoes WHERE empresa_id=? GROUP BY nota");
    $dist = []; $total = 0; $soma = 0;
    foreach ($res as $r) { $dist[(int)$r['nota']] = (int)$r['c']; $total += (int)$r['c']; $soma += (int)$r['nota'] * (int)$r['c']; }
    $d['avaliacoes_resumo'] = ['total' => $total, 'media' => $total ? $soma / $total : 0, 'dist' => $dist];
    $d['galeria'] = array_values(array_filter(array_map('trim', explode(',', $empresa['galeria_imagens'] ?? ''))));
    $d['maps_key'] = trim(get_config('google_maps_key', ''));
    $d['analytics_id'] = trim(get_config('google_analytics_id', ''));
    $d['agenda'] = agenda_config($empresa) + ['tem_cupons' => !empty($d['cupons'])];
    $d['status'] = agenda_status_agora($empresa);
    $d['tema'] = site_tema($empresa);
    $d['modo'] = atendimento_modo_da_linha($empresa);
    // Módulos desligados pela empresa somem do site também
    $d['modulos'] = modulos_da_empresa($empresa)['ativos'];
    if (!in_array('cupons', $d['modulos'], true)) { $d['cupons'] = []; $d['agenda']['tem_cupons'] = false; }
    return $d;
}

// Cor de destaque dos componentes: a do design da IA, ou a escolhida no painel
function site_tema($empresa) {
    $tema = json_decode((string)($empresa['ia_tema'] ?? ''), true) ?: [];
    $valida = fn($c) => is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', $c);
    $usaIa = pg_bool($empresa['ia_modo_total'] ?? false) && !empty($empresa['html_gerado']);
    if ($usaIa && !$valida($tema['accent'] ?? null)) {
        // Site gerado antes de guardarmos o tema: tenta achar a cor de destaque no próprio CSS
        if (preg_match('/--(?:accent|acento|destaque|primary|primaria|ac|cor-destaque|brand)\s*:\s*(#[0-9a-fA-F]{6})\b/i', $empresa['html_gerado'], $m)) $tema['accent'] = $m[1];
    }
    if (!$valida($tema['accent'] ?? null)) $tema['accent'] = $valida($empresa['cor_primaria'] ?? null) && strtolower($empresa['cor_primaria']) !== '#1a1a2e' ? $empresa['cor_primaria'] : '#2563eb';
    return $tema;
}

function site_estado_vazio() {
    return ['lead' => [], 'agendamento' => [], 'avaliacao' => [], 'orcamento' => [], 'pedido' => [], 'pagina_forcada' => null];
}

// ------------------------------------------------------------
// Processa os formulários do site (contato, agendamento, avaliação)
// ------------------------------------------------------------
function site_eh_spam() {
    if (trim($_POST['site_url_confirm'] ?? '') !== '') return true;            // campo invisível preenchido = robô
    $t = (int)($_POST['gds_t'] ?? 0);
    if ($t > 0 && time() - $t < 2) return true;                                 // enviado rápido demais pra ser humano
    // no máximo 8 envios a cada 10 minutos por visitante
    $_SESSION['gds_envios'] = array_values(array_filter($_SESSION['gds_envios'] ?? [], fn($x) => $x > time() - 600));
    if (count($_SESSION['gds_envios']) >= 8) return true;
    $_SESSION['gds_envios'][] = time();
    return false;
}

function site_limpar($campo, $max) {
    return mb_substr(trim(strip_tags((string)($_POST[$campo] ?? ''))), 0, $max);
}

function site_url_base() {
    $host = preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    return ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https://' : 'http://') . $host;
}

// Aviso por e-mail para a empresa (agendamento novo, contato novo)
function site_notificar_empresa($empresa, $assunto, $linhas) {
    if (empty($empresa['email']) || (isset($empresa['notificar_email']) && !pg_bool($empresa['notificar_email']))) return;
    $host = preg_replace('/:\d+$/', '', preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
    $corpo = "Olá, {$empresa['nome']}!\n\n" . implode("\n", $linhas) . "\n\nVeja no painel: " . site_url_base() . "/empresa/index.php\n\n— GDS · Gestor de Sites";
    $cab = "From: GDS <nao-responda@$host>\r\nContent-Type: text/plain; charset=UTF-8";
    @mail($empresa['email'], '=?UTF-8?B?' . base64_encode($assunto) . '?=', $corpo, $cab);
}

function site_cupom_valido($empresaId, $codigo) {
    global $pdo;
    $codigo = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)$codigo));
    if ($codigo === '') return null;
    $st = $pdo->prepare("SELECT codigo, desconto_percentual FROM cupons WHERE empresa_id=? AND UPPER(codigo)=? AND ativo=true AND (validade IS NULL OR validade >= CURRENT_DATE) LIMIT 1");
    $st->execute([$empresaId, $codigo]);
    return $st->fetch() ?: false;
}

function site_processar_post($empresa, $dados) {
    global $pdo;
    $estado = site_estado_vazio();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return $estado;
    wa_adiar();

    // ---------- CONTATO (gera lead) ----------
    if (isset($_POST['enviar_lead'])) {
        $nome = site_limpar('nome', 120); $email = site_limpar('email', 150);
        $tel = site_limpar('telefone', 20); $msg = site_limpar('mensagem', 2000); $assunto = site_limpar('assunto', 80);
        $estado['pagina_forcada'] = 'contato';
        if (site_eh_spam()) { $estado['lead'] = ['ok' => true, 'nome' => $nome]; return $estado; } // finge sucesso pro robô
        if ($nome === '' || $msg === '') {
            $estado['lead'] = ['erro' => 'Preencha seu nome e a mensagem.', 'post' => $_POST];
        } elseif ($email === '' && strlen(preg_replace('/\D/', '', $tel)) < 10) {
            $estado['lead'] = ['erro' => 'Informe seu WhatsApp (com DDD) ou e-mail para podermos responder.', 'post' => $_POST];
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $estado['lead'] = ['erro' => 'E-mail inválido.', 'post' => $_POST];
        } else {
            try {
                $pdo->prepare("INSERT INTO leads (empresa_id, nome, email, telefone, mensagem, assunto) VALUES (?,?,?,?,?,?)")
                    ->execute([$empresa['id'], $nome, $email ?: null, $tel ?: null, $msg, $assunto ?: null]);
            } catch (PDOException $e) {
                $pdo->prepare("INSERT INTO leads (empresa_id, nome, email, telefone, mensagem) VALUES (?,?,?,?,?)")
                    ->execute([$empresa['id'], $nome, $email ?: null, $tel ?: null, ($assunto ? "[$assunto] " : '') . $msg]);
            }
            wa_notificar_empresa($empresa, 'contato', ['nome' => $nome, 'telefone' => $tel ?: $email, 'mensagem' => ($assunto ? "[$assunto] " : '') . $msg], 'lead');
            if ($tel !== '') wa_notificar_cliente($empresa, 'contato_recebido', $tel, ['nome' => explode(' ', $nome)[0]], 'lead');
            site_notificar_empresa($empresa, "Nova mensagem pelo site: $nome", ["$nome mandou uma mensagem pelo site" . ($assunto ? " ($assunto)" : '') . ':', '', $msg, '', 'WhatsApp/telefone: ' . ($tel ?: '-'), 'E-mail: ' . ($email ?: '-')]);
            $estado['lead'] = ['ok' => true, 'nome' => $nome];
        }
        return $estado;
    }

    // ---------- AVALIAÇÃO (entra pendente de aprovação) ----------
    if (isset($_POST['enviar_avaliacao'])) {
        $estado['pagina_forcada'] = 'avaliacoes';
        $nome = site_limpar('av_nome', 80); $nota = (int)($_POST['av_nota'] ?? 0); $coment = site_limpar('av_comentario', 600);
        if (site_eh_spam()) { $estado['avaliacao'] = ['ok' => true]; return $estado; }
        if ($nome === '' || $nota < 1 || $nota > 5) {
            $estado['avaliacao'] = ['erro' => 'Informe seu nome e uma nota de 1 a 5.'];
        } else {
            try {
                $pdo->prepare("INSERT INTO avaliacoes (empresa_id, nota, comentario, nome_autor, aprovado, origem) VALUES (?,?,?,?,false,'site')")
                    ->execute([$empresa['id'], $nota, $coment, $nome]);
                $estado['avaliacao'] = ['ok' => true];
            } catch (PDOException $e) {
                error_log('GDS avaliação: ' . $e->getMessage());
                $estado['avaliacao'] = ['erro' => 'Não foi possível enviar sua avaliação agora.'];
            }
        }
        return $estado;
    }

    // ---------- PEDIDO DE ORÇAMENTO (vira um Pedido/Orçamento no painel) ----------
    if (isset($_POST['pedir_orcamento'])) {
        $estado['pagina_forcada'] = 'orcamento';
        $nome = site_limpar('orc_nome', 120); $tel = site_limpar('orc_telefone', 20); $email = site_limpar('orc_email', 150);
        $detalhes = site_limpar('orc_detalhes', 2000); $local = site_limpar('orc_local', 150); $dataTxt = site_limpar('orc_data', 10);
        $servico = null;
        foreach ($dados['servicos'] as $sv) if ((string)$sv['id'] === (string)($_POST['orc_servico_id'] ?? '')) $servico = $sv;
        $dataEvento = null;
        if ($dataTxt !== '') {
            $dt = DateTime::createFromFormat('d/m/Y', $dataTxt);
            if ($dt && $dt->format('d/m/Y') === $dataTxt) $dataEvento = $dt->format('Y-m-d');
        }
        $erro = '';
        if (site_eh_spam()) { $estado['orcamento'] = ['ok' => true, 'nome' => $nome]; return $estado; }
        if ($nome === '' || strlen(preg_replace('/\D/', '', $tel)) < 10) $erro = 'Informe seu nome e WhatsApp (com DDD).';
        elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $erro = 'E-mail inválido.';
        elseif ($dataTxt !== '' && !$dataEvento) $erro = 'Data inválida — use dd/mm/aaaa.';
        elseif ($dataEvento && $dataEvento < date('Y-m-d')) $erro = 'Essa data já passou.';
        elseif (!$servico && $detalhes === '') $erro = 'Escolha o serviço ou conte os detalhes do que você precisa.';
        if ($erro !== '') { $estado['orcamento'] = ['erro' => $erro, 'post' => $_POST]; return $estado; }

        $resumo = [];
        if ($servico) $resumo[] = ['Serviço', $servico['nome']];
        if ($dataEvento) $resumo[] = ['Data', date('d/m/Y', strtotime($dataEvento))];
        if ($local !== '') $resumo[] = ['Local', $local];
        $descricao = trim(($servico ? $servico['nome'] : 'Pedido de orçamento') . ($dataEvento ? ' — ' . date('d/m/Y', strtotime($dataEvento)) : '') . ($local ? ' — ' . $local : '') . ($detalhes ? "\n" . $detalhes : ''));
        try {
            $telDig = preg_replace('/\D/', '', $tel);
            $busca = $pdo->prepare("SELECT id FROM clientes WHERE empresa_id=? AND ((? <> '' AND LOWER(email)=LOWER(?)) OR right(regexp_replace(COALESCE(telefone,''), '\D', '', 'g'), 11) = right(?, 11)) LIMIT 1");
            $busca->execute([$empresa['id'], $email, $email, $telDig]);
            $clienteId = ($b = $busca->fetch()) ? $b['id'] : null;
            if (!$clienteId) {
                $n = $pdo->prepare("INSERT INTO clientes (empresa_id, nome, email, telefone) VALUES (?,?,?,?) RETURNING id");
                $n->execute([$empresa['id'], $nome, $email ?: null, $tel]);
                $clienteId = $n->fetch()['id'];
            }
            $tokenPedido = bin2hex(random_bytes(16));
            $pdo->beginTransaction();
            $pdo->exec('SAVEPOINT antes_pedido');
            try {
                $ins = $pdo->prepare("INSERT INTO pedidos (empresa_id, cliente_id, descricao, valor, status, origem, servico_id, data_evento, local_evento, tipo, token) VALUES (?,?,?,?, 'novo', 'site', ?,?,?, 'orcamento', ?) RETURNING id");
                $ins->execute([$empresa['id'], $clienteId, $descricao, $servico ? (float)$servico['preco'] : 0, $servico['id'] ?? null, $dataEvento, $local ?: null, $tokenPedido]);
            } catch (PDOException $e) {
                $pdo->exec('ROLLBACK TO SAVEPOINT antes_pedido');
                $tokenPedido = null;
                $ins = $pdo->prepare("INSERT INTO pedidos (empresa_id, cliente_id, descricao, valor, status) VALUES (?,?,?,?, 'novo') RETURNING id");
                $ins->execute([$empresa['id'], $clienteId, '[site] ' . $descricao, $servico ? (float)$servico['preco'] : 0]);
            }
            $pedidoId = (int)$ins->fetch()['id'];
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('GDS orçamento: ' . $e->getMessage());
            $estado['orcamento'] = ['erro' => 'Não foi possível enviar agora. Tente de novo ou chame no WhatsApp.', 'post' => $_POST];
            return $estado;
        }
        $varsOrc = ['nome' => explode(' ', $nome)[0], 'telefone' => $tel, 'codigo' => (string)$pedidoId,
            'servico' => implode("\n", array_map(fn($r) => $r[0] . ': ' . $r[1], $resumo)) ?: 'Orçamento personalizado',
            'mensagem' => $detalhes ? 'Detalhes: ' . mb_strimwidth($detalhes, 0, 600, '…') : '',
            'link' => $tokenPedido ? wa_url_publica($empresa, '/site/pedido.php?t=' . $tokenPedido) : ''];
        wa_notificar_cliente($empresa, 'orcamento_recebido', $tel, $varsOrc, 'pedido', $pedidoId);
        wa_notificar_empresa($empresa, 'orcamento', ['nome' => $nome] + $varsOrc, 'pedido', $pedidoId);
        $msgWa = "Olá! Acabei de pedir um orçamento pelo site.\n" . implode("\n", array_map(fn($r) => $r[0] . ': ' . $r[1], $resumo)) . ($detalhes ? "\nDetalhes: " . mb_strimwidth($detalhes, 0, 400, '…') : '') . "\nNome: $nome";
        site_notificar_empresa($empresa, "Novo pedido de orçamento: $nome", array_merge(['Novo pedido de orçamento pelo site!', '', 'Cliente: ' . $nome, 'WhatsApp: ' . $tel . ($email ? ' · ' . $email : '')],
            array_map(fn($r) => $r[0] . ': ' . $r[1], $resumo), $detalhes ? ['', 'Detalhes:', $detalhes] : []));
        $estado['orcamento'] = ['ok' => true, 'nome' => $nome, 'resumo' => $resumo, 'msg_whatsapp' => $msgWa, 'codigo' => $pedidoId,
            'link_acompanhar' => $tokenPedido ? '/site/pedido.php?t=' . $tokenPedido : '', 'wa_automatico' => wa_remetente($empresa) !== null];
        return $estado;
    }

    // ---------- PEDIDO PELO SITE (delivery / retirada) ----------
    if (isset($_POST['fazer_pedido'])) {
        $estado['pedido'] = site_processar_pedido($empresa, $dados);
        return $estado;
    }

    // ---------- AGENDAMENTO ----------
    if (isset($_POST['fazer_agendamento'])) {
        $estado['pagina_forcada'] = 'agendar';
        $nome = site_limpar('ag_nome', 120); $email = site_limpar('ag_email', 150);
        $tel = site_limpar('ag_telefone', 20); $obs = site_limpar('ag_observacoes', 500);
        $dataHora = (string)($_POST['ag_data_hora'] ?? '');
        $erro = '';

        // Serviço e profissional precisam ser DESTA empresa
        $servico = null; $profissional = null;
        $servicoId = (int)($_POST['ag_servico_id'] ?? 0);
        $profId = (int)($_POST['ag_profissional_id'] ?? 0);
        foreach ($dados['servicos'] as $s) if ((int)$s['id'] === $servicoId) $servico = $s;
        foreach ($dados['profissionais'] as $p) if ((int)$p['id'] === $profId) $profissional = $p;
        $cupom = null;
        if (trim($_POST['ag_cupom'] ?? '') !== '') $cupom = site_cupom_valido($empresa['id'], $_POST['ag_cupom']);

        if (site_eh_spam()) { $erro = 'Não foi possível enviar agora. Tente novamente em alguns minutos.'; }
        elseif ($nome === '' || $tel === '') { $erro = 'Preencha seu nome e WhatsApp.'; }
        elseif (strlen(preg_replace('/\D/', '', $tel)) < 10) { $erro = 'WhatsApp inválido — use DDD + número.'; }
        elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $erro = 'E-mail inválido.'; }
        elseif (!empty($dados['servicos']) && !$servico) { $erro = 'Escolha um serviço.'; }
        elseif ($profId && !$profissional) { $erro = 'Profissional inválido.'; }
        elseif ($cupom === false) { $erro = 'Esse cupom não existe ou já venceu. Apague o campo para continuar sem cupom.'; }
        else {
            $duracao = $servico && (int)$servico['duracao_minutos'] > 0 ? (int)$servico['duracao_minutos'] : $dados['agenda']['intervalo'];
            $erro = agenda_validar($empresa, $dataHora, $duracao, $profissional ? (int)$profissional['id'] : null);
        }
        if ($erro !== '') { $estado['agendamento'] = ['erro' => $erro, 'post' => $_POST]; return $estado; }

        $dataSql = str_replace('T', ' ', $dataHora) . ':00';
        $preco = $servico ? (float)$servico['preco'] : 0;
        $valor = $cupom ? round($preco * (1 - (float)$cupom['desconto_percentual'] / 100), 2) : $preco;
        $token = bin2hex(random_bytes(16));
        try {
            $pdo->beginTransaction();
            // Trava por empresa: dois clientes clicando no mesmo horário ao mesmo tempo não geram agendamento duplo
            $pdo->prepare("SELECT pg_advisory_xact_lock(?)")->execute([(int)$empresa['id']]);
            $erro = agenda_validar($empresa, $dataHora, $duracao, $profissional ? (int)$profissional['id'] : null);
            if ($erro !== '') { $pdo->rollBack(); $estado['agendamento'] = ['erro' => $erro, 'post' => $_POST]; return $estado; }

            $clienteId = null;
            $telDig = preg_replace('/\D/', '', $tel);
            $busca = $pdo->prepare("SELECT id FROM clientes WHERE empresa_id=? AND ((? <> '' AND LOWER(email)=LOWER(?)) OR right(regexp_replace(COALESCE(telefone,''), '\D', '', 'g'), 11) = right(?, 11)) LIMIT 1");
            $busca->execute([$empresa['id'], $email, $email, $telDig]);
            if ($ex = $busca->fetch()) {
                $clienteId = $ex['id'];
            } else {
                $novo = $pdo->prepare("INSERT INTO clientes (empresa_id, nome, email, telefone) VALUES (?,?,?,?) RETURNING id");
                $novo->execute([$empresa['id'], $nome, $email ?: null, $tel]);
                $clienteId = $novo->fetch()['id'];
            }
            $pdo->exec('SAVEPOINT antes_insert');
            try {
                $ins = $pdo->prepare("INSERT INTO agendamentos (empresa_id, cliente_id, servico_id, profissional_id, data_hora, status, observacoes, token, valor, cupom_codigo, origem) VALUES (?,?,?,?,?,'agendado',?,?,?,?,'site') RETURNING id");
                $ins->execute([$empresa['id'], $clienteId, $servico['id'] ?? null, $profissional['id'] ?? null, $dataSql, $obs ?: null, $token, $servico ? $valor : null, $cupom['codigo'] ?? null]);
            } catch (PDOException $e) {
                // banco sem as colunas novas (adicional_12): grava o básico
                $pdo->exec('ROLLBACK TO SAVEPOINT antes_insert');
                $token = null;
                $ins = $pdo->prepare("INSERT INTO agendamentos (empresa_id, cliente_id, servico_id, profissional_id, data_hora, status, observacoes) VALUES (?,?,?,?,?,'agendado',?) RETURNING id");
                $ins->execute([$empresa['id'], $clienteId, $servico['id'] ?? null, $profissional['id'] ?? null, $dataSql, trim(($obs ?: '') . ($cupom ? ' [cupom ' . $cupom['codigo'] . ']' : '')) ?: null]);
            }
            $agId = $ins->fetch()['id'];
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('GDS agendamento: ' . $e->getMessage());
            $estado['agendamento'] = ['erro' => 'Não foi possível concluir o agendamento agora. Tente novamente.', 'post' => $_POST];
            return $estado;
        }

        $dias = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
        $ts = strtotime($dataSql);
        $dataFormatada = $dias[(int)date('w', $ts)] . ', ' . date('d/m/Y \à\s H:i', $ts);
        $tituloEvento = ($servico['nome'] ?? 'Agendamento') . ' - ' . $empresa['nome'];
        $descEvento = 'Agendamento feito pelo site de ' . $empresa['nome'] . ($profissional ? ' com ' . $profissional['nome'] : '');
        $ag = ['ok' => true, 'detalhes' => [
            'cliente_nome' => $nome,
            'servico_nome' => $servico['nome'] ?? '',
            'profissional_nome' => $profissional['nome'] ?? '',
            'data_formatada' => $dataFormatada,
            'valor' => $servico && $preco > 0 ? 'R$ ' . number_format($valor, 2, ',', '.') : '',
            'cupom' => $cupom['codigo'] ?? '',
            'desconto' => $cupom ? number_format((float)$cupom['desconto_percentual'], 0) : '',
            'empresa_whatsapp' => $empresa['whatsapp'],
            'link_calendario' => gerar_link_google_calendar($tituloEvento, $dataSql, $duracao, $descEvento, $empresa['endereco'] ?? ''),
            'ics' => gerar_ics_data_uri($tituloEvento, $dataSql, $duracao, $descEvento, $empresa['endereco'] ?? ''),
            'link_gerenciar' => $token ? site_url_base() . '/site/agendamento.php?t=' . $token : '',
        ]];

        // PIX: se a empresa ativou pagamento online e o serviço tem preço, gera a cobrança de verdade
        if ($servico && $valor > 0 && pg_bool($empresa['pix_ativo'] ?? false) && !empty($empresa['mercadopago_access_token'])) {
            require_once __DIR__ . '/mercadopago_helper.php';
            $cob = gerar_cobranca_pix($empresa['mercadopago_access_token'], $valor, $servico['nome'] . ' - ' . $empresa['nome'], $email);
            if ($cob['ok']) {
                $pdo->prepare("INSERT INTO pagamentos_clientes (empresa_id, agendamento_id, cliente_id, valor, referencia_gateway) VALUES (?,?,?,?,?)")
                    ->execute([$empresa['id'], $agId, $clienteId, $valor, $cob['mp_id']]);
                $ag['pix'] = ['qr_base64' => $cob['qr_base64'], 'qr_code' => $cob['qr_code']];
            } else {
                $ag['pix_erro'] = $cob['erro'];
            }
        }
        $varsAg = wa_vars_agendamento(['data_hora' => $dataSql, 'cliente_nome' => $nome, 'telefone' => $tel, 'servico_nome' => $servico['nome'] ?? 'Atendimento',
            'profissional_nome' => $profissional['nome'] ?? '', 'valor' => $servico ? $valor : 0, 'token' => $token], $empresa);
        wa_notificar_cliente($empresa, 'agendamento_recebido', $tel, $varsAg, 'agendamento', $agId);
        wa_notificar_empresa($empresa, 'agendamento', ['nome' => $nome] + $varsAg, 'agendamento', $agId);
        $ag['wa_automatico'] = wa_remetente($empresa) !== null;
        site_notificar_empresa($empresa, "Novo agendamento: $nome — " . date('d/m H:i', $ts), [
            'Novo agendamento pelo site!', '',
            'Cliente: ' . $nome, 'WhatsApp: ' . $tel . ($email ? ' · ' . $email : ''),
            'Serviço: ' . ($servico['nome'] ?? '-') . ($ag['detalhes']['valor'] ? ' (' . $ag['detalhes']['valor'] . ($cupom ? ', cupom ' . $cupom['codigo'] : '') . ')' : ''),
            'Profissional: ' . ($profissional['nome'] ?? 'sem preferência'), 'Quando: ' . $dataFormatada,
            $obs ? 'Observação: ' . $obs : '',
        ]);
        $estado['agendamento'] = $ag;
    }
    return $estado;
}

// A empresa vende pelo carrinho do site? (catálogo + pedido online ligado + entrega ou retirada)
function site_pedido_online($empresa, $modo) {
    if ($modo !== 'catalogo') return false;
    $lig = fn($c) => !isset($empresa[$c]) || $empresa[$c] === null || pg_bool($empresa[$c]);
    return $lig('pedido_online') && ($lig('aceita_entrega') || $lig('aceita_retirada'));
}

function site_processar_pedido($empresa, $dados) {
    global $pdo;
    $p = $_POST;
    $nome = site_limpar('pd_nome', 120); $tel = site_limpar('pd_telefone', 20);
    $tipo = ($_POST['pd_tipo_entrega'] ?? '') === 'retirada' ? 'retirada' : 'entrega';
    $endereco = site_limpar('pd_endereco', 250); $obs = site_limpar('pd_obs', 500);
    $formas = array_values(array_filter(array_map('trim', explode(',', (string)($empresa['formas_pagamento'] ?? '') ?: 'Pix, Cartão na entrega, Dinheiro'))));
    $pagamento = in_array($_POST['pd_pagamento'] ?? '', $formas, true) ? $_POST['pd_pagamento'] : ($formas[0] ?? '');
    $troco = valor_decimal_site($_POST['pd_troco'] ?? '');
    $lig = fn($c) => !isset($empresa[$c]) || $empresa[$c] === null || pg_bool($empresa[$c]);
    if (!site_pedido_online($empresa, $dados['modo'])) return ['erro' => 'Pedidos pelo site estão desligados. Chame no WhatsApp.', 'post' => $p];
    if (site_eh_spam()) return ['erro' => 'Não foi possível enviar agora. Tente novamente em alguns minutos.', 'post' => $p];

    // Itens: só id e quantidade vêm do navegador; nome e preço saem do banco
    $porId = [];
    foreach ($dados['servicos'] as $sv) $porId[(int)$sv['id']] = $sv;
    $itens = []; $subtotal = 0;
    foreach ((array)json_decode((string)($_POST['pd_itens'] ?? ''), true) as $it) {
        $id = (int)($it['id'] ?? 0); $qtd = max(0, min(99, (int)($it['qtd'] ?? 0)));
        if ($qtd < 1 || !isset($porId[$id]) || (float)$porId[$id]['preco'] <= 0) continue;
        $preco = (float)$porId[$id]['preco'];
        $itens[] = ['id' => $id, 'nome' => $porId[$id]['nome'], 'qtd' => $qtd, 'preco' => $preco];
        $subtotal += $preco * $qtd;
        if (count($itens) >= 50) break;
    }
    $taxa = $tipo === 'entrega' ? (float)($empresa['taxa_entrega'] ?? 0) : 0;
    $total = round($subtotal + $taxa, 2);
    $minimo = (float)($empresa['pedido_minimo'] ?? 0);
    $erro = '';
    if (!$itens) $erro = 'Seu pedido está vazio.';
    elseif ($nome === '' || strlen(preg_replace('/\D/', '', $tel)) < 10) $erro = 'Informe seu nome e WhatsApp (com DDD).';
    elseif ($tipo === 'entrega' && !$lig('aceita_entrega')) $erro = 'No momento só aceitamos retirada no local.';
    elseif ($tipo === 'retirada' && !$lig('aceita_retirada')) $erro = 'No momento só fazemos entrega.';
    elseif ($tipo === 'entrega' && mb_strlen($endereco) < 8) $erro = 'Informe o endereço completo para a entrega.';
    elseif ($minimo > 0 && $subtotal < $minimo) $erro = 'O pedido mínimo é R$ ' . number_format($minimo, 2, ',', '.') . '.';
    elseif ($troco !== null && $troco > 0 && $troco < $total) $erro = 'O troco precisa ser maior que o total do pedido.';
    if ($erro !== '') return ['erro' => $erro, 'post' => $p];

    $linhas = array_map(fn($i) => $i['qtd'] . 'x ' . $i['nome'], $itens);
    $descricao = implode(', ', $linhas);
    $token = bin2hex(random_bytes(16));
    try {
        $pdo->beginTransaction();
        $telDig = preg_replace('/\D/', '', $tel);
        $busca = $pdo->prepare("SELECT id FROM clientes WHERE empresa_id=? AND right(regexp_replace(COALESCE(telefone,''), '\D', '', 'g'), 11) = right(?, 11) LIMIT 1");
        $busca->execute([$empresa['id'], $telDig]);
        $clienteId = ($b = $busca->fetch()) ? $b['id'] : null;
        if (!$clienteId) {
            $n = $pdo->prepare("INSERT INTO clientes (empresa_id, nome, telefone) VALUES (?,?,?) RETURNING id");
            $n->execute([$empresa['id'], $nome, $tel]);
            $clienteId = $n->fetch()['id'];
        }
        $ins = $pdo->prepare("INSERT INTO pedidos (empresa_id, cliente_id, descricao, valor, status, origem, tipo, itens, tipo_entrega, endereco_entrega, forma_pagamento, troco_para, taxa_entrega, observacoes, token)
            VALUES (?,?,?,?, 'novo', 'site', 'delivery', ?,?,?,?,?,?,?,?) RETURNING id");
        $ins->execute([$empresa['id'], $clienteId, $descricao, $total, json_encode($itens, JSON_UNESCAPED_UNICODE), $tipo, $tipo === 'entrega' ? $endereco : null,
                       $pagamento, $troco ?: null, $taxa, $obs ?: null, $token]);
        $pedidoId = (int)$ins->fetch()['id'];
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('GDS pedido: ' . $e->getMessage());
        return ['erro' => 'Não foi possível enviar o pedido agora. Tente de novo ou chame no WhatsApp.', 'post' => $p];
    }
    $linhaPedido = ['id' => $pedidoId, 'itens' => json_encode($itens), 'tipo_entrega' => $tipo, 'endereco_entrega' => $endereco, 'taxa_entrega' => $taxa,
                    'forma_pagamento' => $pagamento, 'troco_para' => $troco, 'valor' => $total, 'cliente_nome' => $nome, 'telefone' => $tel, 'token' => $token, 'tipo' => 'delivery'];
    $vars = wa_vars_pedido($linhaPedido, $empresa);
    if ($obs) $vars['mensagem'] = 'Obs.: ' . $obs;
    wa_notificar_cliente($empresa, 'pedido_recebido', $tel, $vars, 'pedido', $pedidoId);
    wa_notificar_empresa($empresa, 'pedido', ['nome' => $nome] + $vars + ['itens' => $vars['itens'] . ($obs ? "\nObs.: $obs" : '')], 'pedido', $pedidoId);
    site_notificar_empresa($empresa, "Novo pedido nº $pedidoId: $nome", array_merge(["Novo pedido pelo site (nº $pedidoId)!", '', 'Cliente: ' . $nome . ' · ' . $tel], $linhas,
        ['', $vars['entrega'], 'Total: ' . wa_dinheiro($total) . ' (' . $vars['pagamento'] . ')', $obs ? 'Obs.: ' . $obs : '']));
    $msgWa = "Olá! Fiz o pedido nº $pedidoId pelo site:\n" . implode("\n", $linhas) . "\n" . $vars['entrega'] . "\nTotal: " . wa_dinheiro($total) . ' (' . $vars['pagamento'] . ")\nNome: $nome";
    return ['ok' => true, 'codigo' => $pedidoId, 'nome' => $nome, 'itens' => $itens, 'taxa' => $taxa, 'total' => $total,
            'link' => '/site/pedido.php?t=' . $token, 'wa_automatico' => wa_remetente($empresa) !== null, 'msg_whatsapp' => $msgWa];
}

function valor_decimal_site($s) {
    $s = trim((string)$s);
    if ($s === '') return null;
    if (str_contains($s, ',')) { $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s); }
    $s = preg_replace('/[^0-9.]/', '', $s);
    return is_numeric($s) ? (float)$s : null;
}

function site_fragmentos_marcadores($empresa, $dados, $estado) {
    $html = (string)($empresa['html_gerado'] ?? '');
    $modoTemplate = !pg_bool($empresa['ia_modo_total'] ?? false) || $html === '';
    $modo = $dados['modo'];
    $temAgenda = $modoTemplate ? $modo === 'agendamento' : str_contains($html, '{{AGENDAMENTO}}');
    $temOrcamento = $modoTemplate ? $modo === 'orcamento' : str_contains($html, '{{ORCAMENTO}}');
    $optsServicos = ['modo' => $modo, 'tem_agendamento' => $temAgenda, 'tem_orcamento' => $temOrcamento, 'tem_contato' => true, 'whatsapp' => $empresa['whatsapp'],
                     'carrinho' => site_pedido_online($empresa, $modo)];
    $info = atendimento_modos()[$modo];
    $linkWhats = so_digitos_frag($empresa['whatsapp'] ?? '') ? frag_link_whatsapp($empresa['whatsapp'], trim((string)($empresa['whatsapp_mensagem'] ?? '')) ?: 'Olá! Vim pelo site e gostaria de mais informações.') : '#gds-contato';
    $linkPrincipal = ['agendamento' => $temAgenda ? '#gds-agendamento' : $linkWhats, 'orcamento' => $temOrcamento ? '#gds-orcamento' : $linkWhats,
                      'contato' => $linkWhats, 'catalogo' => '#gds-servicos'][$modo];
    return [
        '{{SOBRE}}' => frag_sobre($empresa['sobre']),
        '{{SERVICOS}}' => frag_servicos($dados['servicos'], $optsServicos),
        '{{EQUIPE}}' => frag_equipe($dados['profissionais'], $temAgenda),
        '{{GALERIA}}' => frag_galeria($dados['galeria'], $empresa['nome']),
        '{{AGENDAMENTO}}' => frag_agendamento($dados['servicos'], $dados['profissionais'], $estado['agendamento'], $dados['agenda'], $empresa),
        '{{ORCAMENTO}}' => frag_orcamento($dados['servicos'], $estado['orcamento'] ?? [], $empresa),
        '{{AVALIACOES}}' => !in_array('avaliacoes', $dados['modulos'] ?? ['avaliacoes'], true) ? '' : frag_avaliacoes($dados['avaliacoes'], $estado['avaliacao'], true, $dados['avaliacoes_resumo']),
        '{{CONTATO}}' => frag_contato($estado['lead'], $empresa, $dados['status']),
        '{{MAPA}}' => frag_mapa($empresa['endereco'], $dados['maps_key']),
        '{{CUPONS}}' => frag_cupons($dados['cupons']),
        '{{HORARIOS}}' => frag_horarios($dados['agenda'], $dados['status']),
        '{{WHATSAPP_FLOAT}}' => site_mostrar_whatsapp_float($empresa) ? frag_whatsapp_float($empresa['whatsapp'], $empresa['whatsapp_mensagem'] ?? '', $empresa['nome']) : '',
        // Links prontos para os botões que a própria IA escreve (href="{{LINK_WHATSAPP}}" etc.)
        '{{LINK_WHATSAPP}}' => e($linkWhats),
        '{{LINK_PRINCIPAL}}' => e($linkPrincipal),
        '{{TEXTO_PRINCIPAL}}' => e(site_texto_cta($empresa, $modo)),
    ];
}

// O componente principal da forma de atendimento precisa existir no site.
// Se o design (feito antes, ou pela IA esquecendo) não tiver, ele entra numa seção própria.
// "Serviços" / "Cardápio" / "Produtos" conforme o tipo de negócio
function site_titulo_catalogo($empresa, $modo) {
    if ($modo !== 'catalogo') return 'Serviços';
    return ($empresa['segmento_grupo'] ?? '') === 'Alimentação e Bebidas' || in_array($empresa['segmento_slug'] ?? '', ['restaurante', 'delivery'], true) ? 'Cardápio' : 'Produtos';
}

// Texto do botão principal: o do segmento só vale se a forma de atendimento for a mesma dele
function site_texto_cta($empresa, $modo) {
    $doSegmento = trim((string)($empresa['segmento_cta'] ?? ''));
    $mesmoModo = ($empresa['modo_segmento'] ?? null) === $modo || (empty($empresa['modo_segmento']) && $modo === 'agendamento');
    return $doSegmento !== '' && $mesmoModo ? $doSegmento : atendimento_modos()[$modo]['cta'];
}

function site_garantir_componente_principal($html, $modo) {
    $marcador = ['agendamento' => '{{AGENDAMENTO}}', 'orcamento' => '{{ORCAMENTO}}'][$modo] ?? null;
    if (!$marcador || str_contains($html, $marcador)) return $html;
    // Site feito num modo e a forma de atendimento mudou depois: troca o componente no mesmo lugar
    $errado = $modo === 'agendamento' ? '{{ORCAMENTO}}' : '{{AGENDAMENTO}}';
    if (str_contains($html, $errado)) return preg_replace('/' . preg_quote($errado, '/') . '/', $marcador, $html, 1);
    $titulo = $modo === 'agendamento' ? 'Agende seu horário' : 'Peça seu orçamento';
    $usaPaginas = str_contains($html, 'data-page=');
    $secao = "\n<section class=\"gds-secao-auto\"" . ($usaPaginas ? ' data-page="' . ($modo === 'agendamento' ? 'agendar' : 'orcamento') . '" style="display:none;' : ' style="') . "padding:56px 20px;max-width:820px;margin:0 auto;\">\n<h2 style=\"margin:0 0 20px\">$titulo</h2>\n$marcador\n</section>\n";
    $pos = stripos($html, '<footer');
    if ($pos === false) $pos = strripos($html, '</body>');
    return $pos === false ? $html . $secao : substr($html, 0, $pos) . $secao . substr($html, $pos);
}

function site_mostrar_whatsapp_float($empresa) {
    return !isset($empresa['whatsapp_flutuante']) || $empresa['whatsapp_flutuante'] === null || pg_bool($empresa['whatsapp_flutuante']);
}

// Dados estruturados para o Google (negócio local, serviços, avaliações, horário)
function site_json_ld($empresa, $dados) {
    $nomesDias = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $ag = $dados['agenda'];
    $ld = ['@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $empresa['nome'], 'url' => site_url_base() . '/' . $empresa['slug']];
    if (!empty($empresa['seo_descricao'])) $ld['description'] = $empresa['seo_descricao'];
    if (!empty($empresa['endereco'])) $ld['address'] = $empresa['endereco'];
    if (!empty($empresa['telefone']) || !empty($empresa['whatsapp'])) $ld['telephone'] = $empresa['telefone'] ?: $empresa['whatsapp'];
    if (!empty($empresa['logo'])) $ld['image'] = $empresa['logo'];
    $ld['openingHoursSpecification'] = [[
        '@type' => 'OpeningHoursSpecification',
        'dayOfWeek' => array_map(fn($d) => $nomesDias[$d], array_map('intval', explode(',', $ag['dias']))),
        'opens' => $ag['abertura'], 'closes' => $ag['fechamento'],
    ]];
    if ($dados['avaliacoes_resumo']['total'] > 0) {
        $ld['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round($dados['avaliacoes_resumo']['media'], 1), 'reviewCount' => $dados['avaliacoes_resumo']['total']];
    }
    $ofertas = [];
    foreach (array_slice($dados['servicos'], 0, 30) as $s) {
        $o = ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => $s['nome']]];
        if ((float)$s['preco'] > 0) { $o['price'] = number_format((float)$s['preco'], 2, '.', ''); $o['priceCurrency'] = 'BRL'; }
        $ofertas[] = $o;
    }
    if ($ofertas) $ld['hasOfferCatalog'] = ['@type' => 'OfferCatalog', 'name' => 'Serviços', 'itemListElement' => $ofertas];
    return '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
}

function site_script_analytics($id) {
    if (!$id || !preg_match('/^[A-Za-z0-9\-_]+$/', $id)) return '';
    return '<script async src="https://www.googletagmanager.com/gtag/js?id=' . e($id) . '"></script>'
         . "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . e($id) . "');</script>";
}

function site_script_preview() {
    return "<script>document.addEventListener('submit',function(e){e.preventDefault();e.stopPropagation();alert('Pré-visualização: os formulários não são enviados de verdade aqui.');},true);</script>";
}

// ------------------------------------------------------------
// MODO IA: HTML gerado + marcadores trocados por conteúdo real
// ------------------------------------------------------------
function site_render_ia($empresa, $dados, $estado, $preview = false) {
    $empresa['html_gerado'] = site_garantir_componente_principal($empresa['html_gerado'], $dados['modo']);
    // Links de WhatsApp que a IA escreveu "na mão" (número inventado, errado ou vazio) passam a usar o número real
    $empresa['html_gerado'] = preg_replace('#href\s*=\s*(["\'])\s*(?:https?://)?(?:wa\.me|api\.whatsapp\.com|web\.whatsapp\.com|whatsapp://)[^"\']*\1#i', 'href="{{LINK_WHATSAPP}}"', $empresa['html_gerado']);
    $html = strtr($empresa['html_gerado'], site_fragmentos_marcadores($empresa, $dados, $estado));
    // Site antigo sem o marcador do WhatsApp: o botão entra mesmo assim
    if (!str_contains($empresa['html_gerado'], '{{WHATSAPP_FLOAT}}') && site_mostrar_whatsapp_float($empresa)) {
        $html = preg_replace('/<\/body>/i', str_replace(['\\', '$'], ['\\\\', '\\$'], frag_whatsapp_float($empresa['whatsapp'], $empresa['whatsapp_mensagem'] ?? '', $empresa['nome'])) . '</body>', $html, 1);
    }
    if (site_pedido_online($empresa, $dados['modo'])) {
        $html = preg_replace('/<\/body>/i', str_replace(['\\', '$'], ['\\\\', '\\$'], frag_carrinho($empresa, $estado['pedido'] ?? [])) . '</body>', $html, 1);
    }
    $extras = frag_css_apoio($dados['tema']) . site_json_ld($empresa, $dados);
    if (!$preview) $extras .= site_script_analytics($dados['analytics_id']);
    if (stripos($html, '</head>') !== false) {
        $html = preg_replace('/<\/head>/i', str_replace(['\\', '$'], ['\\\\', '\\$'], $extras) . '</head>', $html, 1);
    } else {
        $html = $extras . $html;
    }
    if ($preview) {
        $html = stripos($html, '</body>') !== false ? preg_replace('/<\/body>/i', str_replace(['\\', '$'], ['\\\\', '\\$'], site_script_preview()) . '</body>', $html, 1) : $html . site_script_preview();
    }
    return $html;
}

function psite_icon($nome) {
    $icones = [
        'calendario' => '<path d="M8 2v4M16 2v4M3 9h18M5 5h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z"/>',
        'usuarios'   => '<path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'chat'       => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
        'cartao'     => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'pin'        => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'relogio'    => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icones[$nome] ?? '') . '</svg>';
}

// ------------------------------------------------------------
// MODO TEMPLATE (4 estilos prontos + ordem de seções configurável)
// ------------------------------------------------------------
function site_render_template($empresa, $dados, $estado, $preview = false) {
    // Template novo (5 estilos). O antigo fica abaixo só como reserva (layout "legado").
    if (($empresa['layout'] ?? '') !== 'legado') return site_render_template_premium($empresa, $dados, $estado, $preview);
    $layout = in_array($empresa['layout'], ['classico', 'bold', 'editorial', 'grid'], true) ? $empresa['layout'] : 'classico';
    $nome = $empresa['nome'];
    $sec = []; $nav = [];
    $secao = function ($id, $titulo, $conteudo, $rotuloNav = null) use (&$sec, &$nav) {
        $sec[$id] = '<section id="' . $id . '" class="psite-section"><div class="container">' . ($titulo ? '<h2>' . e($titulo) . '</h2>' : '') . $conteudo . '</div></section>';
        $nav[$id] = $rotuloNav ?? $titulo;
    };

    if (trim($empresa['sobre'] ?? '') !== '') $secao('sobre', 'Sobre', '<p class="lead">' . nl2br(e($empresa['sobre'])) . '</p>');
    $f = site_fragmentos_marcadores($empresa, $dados, $estado);
    if ($dados['servicos']) $secao('servicos', site_titulo_catalogo($empresa, $dados['modo']), $f['{{SERVICOS}}']);
    if ($dados['profissionais']) $secao('equipe', 'Equipe', $f['{{EQUIPE}}']);
    if ($dados['galeria']) $secao('galeria', 'Galeria', $f['{{GALERIA}}']);
    if ($dados['blocos']) {
        $h = '';
        foreach ($dados['blocos'] as $b) $h .= '<div class="psite-bloco"><h3>' . e($b['titulo']) . '</h3><p>' . nl2br(e($b['conteudo'])) . '</p></div>';
        $secao('blocos', '', $h, 'Destaques');
    }
    if ($dados['cupons']) $secao('cupons', 'Cupons ativos', $f['{{CUPONS}}']);
    if ($dados['modo'] === 'agendamento') {
        $secao('agendar', 'Agendar horário', '<p class="lead" style="margin-bottom:22px">Escolha o serviço, o profissional e o melhor horário — leva menos de um minuto.</p>' . $f['{{AGENDAMENTO}}'], 'Agendar');
    } elseif ($dados['modo'] === 'orcamento') {
        $secao('agendar', 'Peça seu orçamento', '<p class="lead" style="margin-bottom:22px">Conte o que você precisa — respondemos com o orçamento rapidinho.</p>' . $f['{{ORCAMENTO}}'], 'Orçamento');
    }
    if (in_array('avaliacoes', $dados['modulos'] ?? ['avaliacoes'], true)) $secao('avaliacoes', 'Avaliações', $f['{{AVALIACOES}}']);
    $secao('contato', 'Contato', $f['{{CONTATO}}']
        . ($empresa['endereco'] ? '<div style="margin-top:28px">' . $f['{{MAPA}}'] . '</div>' : '')
        . '<div style="margin-top:28px;max-width:420px"><h3 style="margin-bottom:12px">Horário de atendimento</h3>' . $f['{{HORARIOS}}'] . '</div>');

    $ordemPadrao = ['sobre', 'servicos', 'equipe', 'galeria', 'blocos', 'cupons', 'agendar', 'avaliacoes', 'contato'];
    $ordem = !empty($empresa['secoes']) ? array_values(array_filter(explode(',', $empresa['secoes']))) : $ordemPadrao;
    // contato sempre existe (é o mínimo do site)
    if (!in_array('contato', $ordem, true)) $ordem[] = 'contato';

    $titulo = $empresa['seo_titulo'] ?: ($empresa['titulo'] ?: $nome);
    $descricao = $empresa['seo_descricao'] ?: ('Conheça ' . $nome . ($empresa['segmento_nome'] ? ' — ' . $empresa['segmento_nome'] : '') . ($empresa['endereco'] ? ' em ' . $empresa['endereco'] : ''));
    $corP = cor_hex_valida_site($empresa['cor_primaria'], '#4f7cff');
    // O template tem fundo escuro: cor de destaque escura demais (como o antigo padrão #1a1a2e) deixaria os botões invisíveis
    if (frag_luminancia($corP) < 0.05) $corP = '#4f7cff';
    $corS = cor_hex_valida_site($empresa['cor_secundaria'], '#16213e');
    $ctaAlvo = isset($sec['agendar']) && in_array('agendar', $ordem, true) ? 'agendar' : ($dados['modo'] === 'catalogo' && isset($sec['servicos']) ? 'servicos' : 'contato');
    $ctaTexto = site_texto_cta($empresa, $dados['modo']);

    ob_start(); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?></title>
<meta name="description" content="<?= e($descricao) ?>">
<meta property="og:title" content="<?= e($titulo) ?>">
<meta property="og:description" content="<?= e($descricao) ?>">
<meta property="og:type" content="website">
<?php if ($empresa['banner_imagem'] || $empresa['logo']): ?><meta property="og:image" content="<?= e($empresa['banner_imagem'] ?: $empresa['logo']) ?>"><?php endif; ?>
<meta name="theme-color" content="<?= e($corP) ?>">
<?php if ($empresa['logo']): ?><link rel="icon" href="<?= e($empresa['logo']) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="/assets/css/site-publico.css?v=2">
<style>:root{ --accent: <?= $corP ?>; --accent-2: <?= $corS ?>; }</style>
<?= frag_css_apoio(['accent' => $corP]) ?>
<?= site_json_ld($empresa, $dados) ?>
<?= $preview ? '' : site_script_analytics($dados['analytics_id']) ?>
</head>
<body class="layout-<?= e($layout) ?>">
<?php if ($preview): ?><div class="psite-preview-aviso">Pré-visualização — template <?= e($layout) ?> (formulários não enviam de verdade aqui)</div><?php endif; ?>
<nav class="psite-nav" aria-label="Navegação principal">
    <a class="psite-brand" href="#" onclick="mostrarPagina(document.querySelector('.psite-page').dataset.page);return false;"><?php if ($empresa['logo']): ?><img src="<?= e($empresa['logo']) ?>" alt=""><?php endif; ?><?= e($nome) ?></a>
    <div class="psite-nav-links">
        <?php foreach ($ordem as $k): if (isset($sec[$k])): ?>
        <a href="#<?= e($k) ?>" data-page-link="<?= e($k) ?>" onclick="mostrarPagina('<?= e($k) ?>');return false;"><?= e($nav[$k]) ?></a>
        <?php endif; endforeach; ?>
    </div>
</nav>
<?php if ($layout === 'grid'): ?>
<header class="psite-hero"><div class="psite-hero-grid">
    <div class="psite-hero-text">
        <div class="psite-tag"><span class="dot"></span><?= e($empresa['segmento_nome'] ?: 'Negócio local') ?></div>
        <h1><?= e($empresa['titulo'] ?: $nome) ?></h1>
        <p><?= e($empresa['subtitulo'] ?: 'Conheça nosso trabalho e agende com a gente.') ?></p>
        <a href="#<?= $ctaAlvo ?>" onclick="mostrarPagina('<?= $ctaAlvo ?>');return false;" class="psite-btn"><?= e($ctaTexto) ?></a>
    </div>
    <?php if ($empresa['banner_imagem']): ?><div class="psite-hero-banner-wrap"><img src="<?= e($empresa['banner_imagem']) ?>" alt="<?= e($nome) ?>"></div>
    <?php else: ?><div class="psite-hero-banner-wrap" style="background:linear-gradient(135deg,var(--accent),var(--accent-2));"></div><?php endif; ?>
</div></header>
<?php else: ?>
<header class="psite-hero"><div class="container psite-hero-inner">
    <div class="psite-tag"><span class="dot"></span><?= e($empresa['segmento_nome'] ?: 'Negócio local') ?></div>
    <h1><?= e($empresa['titulo'] ?: $nome) ?></h1>
    <p><?= e($empresa['subtitulo'] ?: 'Conheça nosso trabalho e agende com a gente.') ?></p>
    <a href="#<?= $ctaAlvo ?>" onclick="mostrarPagina('<?= $ctaAlvo ?>');return false;" class="psite-btn"><?= e($ctaTexto) ?></a>
</div></header>
<?php endif; ?>
<div class="psite-features container">
    <div class="psite-feature"><?= psite_icon('calendario') ?> <?= ['agendamento' => 'Agende online', 'orcamento' => 'Orçamento sem compromisso', 'contato' => 'Atendimento rápido', 'catalogo' => 'Peça pelo WhatsApp'][$dados['modo']] ?></div>
    <?php if ($dados['profissionais']): ?><div class="psite-feature"><?= psite_icon('usuarios') ?> Equipe especializada</div><?php endif; ?>
    <?php if ($empresa['whatsapp']): ?><div class="psite-feature"><?= psite_icon('chat') ?> Atendimento via WhatsApp</div><?php endif; ?>
    <?php if (pg_bool($empresa['pix_ativo'] ?? false)): ?><div class="psite-feature"><?= psite_icon('cartao') ?> Pagamento via PIX</div><?php endif; ?>
    <?php if ($empresa['endereco']): ?><div class="psite-feature"><?= psite_icon('pin') ?> <?= e($empresa['endereco']) ?></div><?php endif; ?>
</div>
<main id="psite-paginas">
<?php foreach ($ordem as $k): if (isset($sec[$k])): ?>
<div class="psite-page" data-page="<?= e($k) ?>"><?= $sec[$k] ?></div>
<?php endif; endforeach; ?>
</main>
<footer class="psite-footer container">
    <span><?= e($nome) ?><?= $empresa['endereco'] ? ' — ' . e($empresa['endereco']) : '' ?></span>
    <span>© <?= date('Y') ?> · Site por GDS</span>
</footer>
<?= site_mostrar_whatsapp_float($empresa) ? frag_whatsapp_float($empresa['whatsapp'], $empresa['whatsapp_mensagem'] ?? '', $nome) : '' ?>
<?= site_pedido_online($empresa, $dados['modo']) ? frag_carrinho($empresa, $estado['pedido'] ?? []) : '' ?>
<script>
function mostrarPagina(chave) {
    var alvo = document.querySelector('.psite-page[data-page="' + chave + '"]');
    if (!alvo) return;
    document.querySelectorAll('.psite-page').forEach(function(el){ el.classList.toggle('psite-page-ativa', el === alvo); });
    document.querySelectorAll('.psite-nav-links a').forEach(function(a){ a.classList.toggle('psite-nav-ativo', a.dataset.pageLink === chave); });
    window.scrollTo({top: 0, behavior: 'smooth'});
    if (history.replaceState) history.replaceState(null, '', '#' + chave);
}
window.gdsMostrarPagina = mostrarPagina;
document.addEventListener('DOMContentLoaded', function() {
    var paginas = document.querySelectorAll('.psite-page');
    if (!paginas.length) return;
    var forcada = <?= json_encode($estado['pagina_forcada']) ?>;
    var hash = location.hash.replace('#', '');
    var escolha = [forcada, hash].find(function(k){ return k && document.querySelector('.psite-page[data-page="' + k + '"]'); });
    mostrarPagina(escolha || paginas[0].dataset.page);
});
</script>
<?= $preview ? site_script_preview() : '' ?>
</body>
</html>
<?php
    return ob_get_clean();
}

function cor_hex_valida_site($cor, $padrao) {
    return preg_match('/^#[0-9a-fA-F]{6}$/', (string)$cor) ? $cor : $padrao;
}

function site_dias_texto($dias) {
    $n = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
    $lista = array_values(array_filter(array_map('intval', explode(',', (string)$dias)), fn($d) => $d >= 0 && $d <= 6));
    sort($lista);
    if ($lista === [1, 2, 3, 4, 5]) return 'de segunda a sexta';
    if ($lista === [1, 2, 3, 4, 5, 6]) return 'de segunda a sábado';
    if ($lista === [0, 1, 2, 3, 4, 5, 6]) return 'todos os dias';
    return implode(', ', array_map(fn($d) => $n[$d], $lista));
}
