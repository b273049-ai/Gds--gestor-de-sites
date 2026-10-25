<?php
// ============================================================
// API do Estúdio IA (JSON). Incluída por admin/ia_estudio_api.php e
// empresa/ia_estudio_api.php, que já validaram login + CSRF e
// definiram $empresaId (o admin escolhe; a empresa só a própria).
// ============================================================
require_once __DIR__ . '/ia_site_engine.php';

@set_time_limit(300); // planejar + gerar (e às vezes emendar) pode passar dos 2 minutos
@ini_set('memory_limit', '256M');

// Nunca devolve um "erro 500" mudo: qualquer falha vira uma mensagem que aparece no chat
function estudio_explicar_erro($msg) {
    $m = (string)$msg;
    if (stripos($m, 'does not exist') !== false || stripos($m, 'não existe') !== false || stripos($m, 'undefined column') !== false) {
        return 'O banco de dados está desatualizado. Rode o arquivo sql/adicional_10_estudio_ia.sql no Supabase (SQL Editor → New query → Run) e tente de novo. Detalhe: ' . mb_substr($m, 0, 200);
    }
    if (stripos($m, 'Maximum execution time') !== false) {
        return 'A hospedagem encerrou o processo por tempo (a IA demorou demais). Tente de novo, ou use uma IA mais rápida (Groq) em Configurações.';
    }
    if (stripos($m, 'Allowed memory size') !== false) {
        return 'O servidor ficou sem memória ao processar o site. Tente um pedido menor.';
    }
    return 'Erro interno no servidor: ' . mb_substr($m, 0, 300);
}
set_exception_handler(function (Throwable $e) {
    error_log('GDS Estúdio IA: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) { http_response_code(200); header('Content-Type: application/json; charset=utf-8'); }
    echo json_encode(['ok' => false, 'erro' => estudio_explicar_erro($e->getMessage())], JSON_UNESCAPED_UNICODE);
});
register_shutdown_function(function () {
    $e = error_get_last();
    if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
    error_log('GDS Estúdio IA (fatal): ' . $e['message'] . ' em ' . $e['file'] . ':' . $e['line']);
    if (!headers_sent()) { http_response_code(200); header('Content-Type: application/json; charset=utf-8'); }
    echo json_encode(['ok' => false, 'erro' => estudio_explicar_erro($e['message'])], JSON_UNESCAPED_UNICODE);
});
gds_garantir_schema();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    responder_json(['ok' => false, 'erro' => 'Método inválido.'], 405);
}

$acao = $_POST['acao'] ?? '';
$usuarioId = usuario_logado();
$ehAdmin = is_admin();

$site = $pdo->prepare("SELECT * FROM sites WHERE empresa_id=?");
$site->execute([$empresaId]);
$site = $site->fetch();
if (!$site) responder_json(['ok' => false, 'erro' => 'Empresa sem site cadastrado.'], 404);

// Limite diário de gerações para as empresas (controle de custo da IA). Admin não tem limite.
function estudio_checar_limite($empresaId, $ehAdmin) {
    global $pdo;
    if ($ehAdmin) return;
    $limite = (int)get_config('ia_limite_diario_empresa', '20');
    if ($limite <= 0) return;
    $st = $pdo->prepare("SELECT COUNT(*) c FROM ia_logs WHERE empresa_id=? AND criado_em >= CURRENT_DATE AND prompt LIKE 'Estúdio IA%'");
    $st->execute([$empresaId]);
    if ((int)$st->fetch()['c'] >= $limite) {
        responder_json(['ok' => false, 'erro' => "Você atingiu o limite de $limite gerações com IA por hoje. Amanhã libera de novo."], 429);
    }
}

function estudio_log($empresaId, $usuarioId, $resumo, $resposta) {
    global $pdo;
    $pdo->prepare("INSERT INTO ia_logs (empresa_id, usuario_id, prompt, resposta) VALUES (?,?,?,?)")
        ->execute([$empresaId, $usuarioId, 'Estúdio IA — ' . mb_substr($resumo, 0, 1000), $resposta]);
}

switch ($acao) {

    case 'melhorar_briefing':
        $briefing = trim($_POST['briefing'] ?? '');
        session_write_close();
        $ctx = ia_contexto_empresa($empresaId);
        $prompt = "Você ajuda donos de pequenos negócios a descrever o que querem no site. Reescreva o briefing abaixo de forma "
                . "mais rica e útil para um designer (público-alvo, tom de voz, clima visual, diferenciais, o que o visitante deve fazer no site), "
                . "em até 120 palavras, em português, em primeira pessoa como se fosse o dono. Use só informações coerentes com os dados reais; "
                . "não invente prêmios, números ou preços. Responda só com o texto.\n\nDADOS REAIS:\n" . ia_contexto_texto($ctx)
                . "\n\nBRIEFING ATUAL: \"" . ($briefing ?: '(vazio)') . "\"";
        $r = chamar_ia($prompt, 600, 40, 0.8, null, ['esforco' => 'low']);
        if (!$r['ok']) responder_json(['ok' => false, 'erro' => $r['erro']]);
        responder_json(['ok' => true, 'briefing' => $r['texto']]);

    case 'planejar':
        $briefing = trim($_POST['briefing'] ?? '');
        if (mb_strlen($briefing) < 10) responder_json(['ok' => false, 'erro' => 'Conte um pouco mais sobre o negócio (pelo menos uma frase).']);
        estudio_checar_limite($empresaId, $ehAdmin);
        $direcao = $_POST['direcao'] ?? '';
        $preferencias = trim($_POST['preferencias'] ?? '');
        session_write_close(); // não trava as outras telas enquanto a IA pensa
        $ctx = ia_contexto_empresa($empresaId);
        $plano = ia_planejar_site($ctx, $briefing, $direcao, $preferencias);
        // Guarda o plano na sessão pro passo 2 (sem confiar no que o navegador mandar de volta)
        session_start();
        $_SESSION['ia_plano'][$empresaId] = ['plano' => $plano, 'briefing' => $briefing];
        session_write_close();
        responder_json(['ok' => true, 'plano' => [
            'direcao' => $plano['direcao_nome'], 'conceito' => $plano['conceito'] ?? '',
            'fonte_titulo' => $plano['fonte_titulo'], 'fonte_corpo' => $plano['fonte_corpo'],
            'paleta' => array_values((array)($plano['paleta'] ?? [])),
            'paginas' => array_map(fn($p) => $p['titulo'] ?? $p['chave'] ?? '', (array)($plano['paginas'] ?? [])),
            'headline' => $plano['headline'] ?? '',
        ]]);

    case 'gerar':
        $guardado = $_SESSION['ia_plano'][$empresaId] ?? null;
        if (!$guardado) responder_json(['ok' => false, 'erro' => 'Planejamento não encontrado. Clique em gerar de novo.']);
        estudio_checar_limite($empresaId, $ehAdmin);
        session_write_close();
        $ctx = ia_contexto_empresa($empresaId);
        $r = ia_gerar_html_site($ctx, $guardado['briefing'], $guardado['plano']);
        if (!$r['ok']) responder_json(['ok' => false, 'erro' => $r['erro']]);
        $plano = $guardado['plano'];
        $versaoId = ia_salvar_versao($empresaId, $r['html'], 'geracao', 'Site gerado — estilo ' . $plano['direcao_nome'], $usuarioId,
            ['direcao' => $plano['direcao_chave'], 'conceito' => $plano['conceito'] ?? '']);
        ia_aplicar_html_no_site($empresaId, $r['html'], $guardado['briefing']);
        ia_registrar_mensagem($empresaId, 'usuario', $guardado['briefing']);
        ia_registrar_mensagem($empresaId, 'ia', 'Criei um site novo no estilo "' . $plano['direcao_nome'] . '". Conceito: ' . ($plano['conceito'] ?? '') . ' — Peça qualquer ajuste aqui no chat.', $versaoId);
        estudio_log($empresaId, $usuarioId, 'geração — ' . $plano['direcao_nome'], 'HTML com ' . strlen($r['html']) . ' caracteres');
        responder_json(['ok' => true, 'versao_id' => $versaoId, 'direcao' => $plano['direcao_nome']]);

    case 'refinar':
        $instrucao = trim($_POST['instrucao'] ?? '');
        if ($instrucao === '') responder_json(['ok' => false, 'erro' => 'Escreva o que você quer mudar.']);
        if (empty($site['html_gerado'])) responder_json(['ok' => false, 'erro' => 'Gere o site primeiro, depois peça ajustes.']);
        estudio_checar_limite($empresaId, $ehAdmin);
        session_write_close();
        $ctx = ia_contexto_empresa($empresaId);
        $r = ia_refinar_html_site($ctx, $site['html_gerado'], $instrucao);
        if (!$r['ok']) responder_json(['ok' => false, 'erro' => $r['erro']]);
        $versaoId = ia_salvar_versao($empresaId, $r['html'], 'ajuste', $instrucao, $usuarioId);
        ia_aplicar_html_no_site($empresaId, $r['html']);
        ia_registrar_mensagem($empresaId, 'usuario', $instrucao);
        ia_registrar_mensagem($empresaId, 'ia', 'Pronto! Apliquei o ajuste. Se não ficou como você imaginou, é só pedir de novo ou voltar para a versão anterior.', $versaoId);
        estudio_log($empresaId, $usuarioId, 'ajuste — ' . $instrucao, 'HTML com ' . strlen($r['html']) . ' caracteres');
        responder_json(['ok' => true, 'versao_id' => $versaoId]);

    case 'restaurar':
        $versaoId = (int)($_POST['versao_id'] ?? 0);
        $v = $pdo->prepare("SELECT * FROM site_versoes WHERE id=? AND empresa_id=?");
        $v->execute([$versaoId, $empresaId]);
        $v = $v->fetch();
        if (!$v) responder_json(['ok' => false, 'erro' => 'Versão não encontrada.'], 404);
        $nova = ia_salvar_versao($empresaId, $v['html'], 'restauracao', 'Restaurou a versão #' . $versaoId, $usuarioId);
        ia_aplicar_html_no_site($empresaId, $v['html']);
        ia_registrar_mensagem($empresaId, 'ia', 'Voltei o site para a versão #' . $versaoId . '.', $nova);
        responder_json(['ok' => true, 'versao_id' => $nova]);

    case 'salvar_codigo':
        // Chega em base64 pra não ser barrado pelo firewall da hospedagem
        // (ele costuma bloquear POST com HTML/JS cru, achando que é ataque).
        $html = base64_decode($_POST['html_b64'] ?? '', true);
        if ($html === false || stripos($html, '</html>') === false) responder_json(['ok' => false, 'erro' => 'O código precisa ser um documento HTML completo (até </html>).']);
        $html = ia_pos_processar_html($html);
        $versaoId = ia_salvar_versao($empresaId, $html, 'manual', 'Edição manual do código', $usuarioId);
        ia_aplicar_html_no_site($empresaId, $html);
        responder_json(['ok' => true, 'versao_id' => $versaoId]);

    case 'codigo':
        responder_json(['ok' => true, 'html' => $site['html_gerado'] ?? '']);

    case 'historico':
        responder_json(['ok' => true, 'versoes' => ia_listar_versoes($empresaId), 'mensagens' => ia_listar_mensagens($empresaId)]);

    case 'modo':
        $ativar = ($_POST['ativo'] ?? '') === '1';
        if ($ativar && empty($site['html_gerado'])) responder_json(['ok' => false, 'erro' => 'Ainda não existe site gerado por IA.']);
        $pdo->prepare("UPDATE sites SET ia_modo_total=? WHERE empresa_id=?")->execute([$ativar ? 'true' : 'false', $empresaId]);
        responder_json(['ok' => true, 'ativo' => $ativar]);

    case 'publicar':
        $publicar = ($_POST['publicado'] ?? '') === '1';
        $pdo->prepare("UPDATE sites SET publicado=? WHERE empresa_id=?")->execute([$publicar ? 'true' : 'false', $empresaId]);
        responder_json(['ok' => true, 'publicado' => $publicar]);

    default:
        responder_json(['ok' => false, 'erro' => 'Ação inválida.'], 400);
}
