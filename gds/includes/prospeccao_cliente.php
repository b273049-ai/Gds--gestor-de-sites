<?php
// ============================================================
// FECHOU! LEAD → CLIENTE
// Um clique transforma o prospect em empresa da plataforma, com tudo que o Google
// já trouxe: nome, ramo, WhatsApp, telefone, endereço, horário, logo/cor da marca,
// fotos e avaliações reais. Cria o acesso do dono, os serviços do ramo e o site
// publicado com a mesma cara da prévia que ele aprovou.
// ============================================================
require_once __DIR__ . '/prospeccao.php';
require_once __DIR__ . '/ia_helper.php';
require_once __DIR__ . '/imagens_ia.php';

// "segunda-feira: 08:00–18:00", "sábado: 08:00–12:00, 13:00–17:00", "domingo: Fechado"
function prosp_horario_google($lista) {
    $nomes = ['domingo' => 0, 'segunda' => 1, 'terça' => 2, 'terca' => 2, 'quarta' => 3, 'quinta' => 4, 'sexta' => 5, 'sábado' => 6, 'sabado' => 6];
    $dias = []; $abre = null; $fecha = null; $pausa = null;
    foreach ((array)$lista as $linha) {
        $partes = explode(':', (string)$linha, 2);
        if (count($partes) < 2) continue;
        $nome = mb_strtolower(trim($partes[0]));
        $dia = null;
        foreach ($nomes as $k => $v) if (str_starts_with($nome, $k)) { $dia = $v; break; }
        if ($dia === null) continue;
        $h = $partes[1];
        if (preg_match('/24\s*horas|aberto 24/iu', $h)) { $dias[] = $dia; $abre = '00:00'; $fecha = '23:59'; continue; }
        if (!preg_match_all('/(\d{1,2}):(\d{2})\s*[–—-]\s*(\d{1,2}):(\d{2})/u', $h, $m, PREG_SET_ORDER)) continue;
        $dias[] = $dia;
        foreach ($m as $i => $r) {
            $ini = sprintf('%02d:%s', $r[1], $r[2]);
            $fim = sprintf('%02d:%s', $r[3], $r[4]);
            if ($fim === '00:00') $fim = '23:59';
            if ($abre === null || $ini < $abre) $abre = $ini;
            if ($fecha === null || $fim > $fecha) $fecha = $fim;
            if ($i === 1 && $pausa === null) $pausa = [sprintf('%02d:%s', $m[0][3], $m[0][4]), $ini];
        }
    }
    if (!$dias) return null;
    $dias = array_values(array_unique($dias)); sort($dias);
    return ['dias' => implode(',', $dias), 'abertura' => $abre, 'fechamento' => $fecha, 'pausa' => $pausa];
}

// Endereço do site (roda também fora do painel: na ponte do WhatsApp e no cron, sem o auth.php)
function prosp_slug_unico($nome) {
    global $pdo;
    $t = trim((string)$nome);
    if (function_exists('iconv')) { $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t); if ($c !== false) $t = $c; }
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($t)), '-') ?: 'empresa';
    $base = mb_substr($base, 0, 60);
    if (in_array($base, ['admin', 'empresa', 'site', 'assets', 'includes', 'config', 'sql', 'login', 'logout', 'install', 'index', 'api', 'uploads', 'previa', 'indica', 'privacidade', 'cron'], true)) $base .= '-site';
    $slug = $base; $n = 2;
    $st = $pdo->prepare("SELECT 1 FROM empresas WHERE slug=?");
    while (true) { $st->execute([$slug]); if (!$st->fetchColumn()) return $slug; $slug = $base . '-' . $n++; }
}

// Duração típica por tipo de serviço (o dono ajusta no painel)
function prosp_duracao_servico($nome) {
    $n = mb_strtolower($nome);
    foreach (['tosa completa' => 90, 'banho e tosa' => 90, 'tosa' => 60, 'banho' => 60, 'hidrata' => 30, 'consulta' => 30, 'vacina' => 20, 'corte' => 40, 'barba' => 30,
              'manicure' => 45, 'pedicure' => 45, 'escova' => 45, 'coloração' => 120, 'limpeza de pele' => 60, 'massagem' => 60, 'avaliação' => 30, 'pacote' => 60, 'aula' => 60] as $k => $min) {
        if (str_contains($n, $k)) return $min;
    }
    return 45;
}

// Texto "Sobre" de verdade (a prévia diz que é exemplo; o site do cliente não pode dizer isso)
function prosp_textos_site($p, $segNome) {
    $bairro = trim((string)($p['bairro'] ?: $p['cidade']));
    $local = $bairro . ($p['cidade'] && $p['cidade'] !== $bairro ? ', ' . $p['cidade'] : '');
    $prova = $p['nota'] && $p['avaliacoes'] ? ' Nossos clientes nos dão nota ' . number_format((float)$p['nota'], 1, ',', '') . ' no Google, em ' . (int)$p['avaliacoes'] . ' avaliações.' : '';
    $base = [
        'subtitulo' => 'Agende online em menos de 1 minuto e receba a confirmação no WhatsApp.',
        'sobre' => "A {$p['nome']} é " . ($segNome ? 'referência em ' . mb_strtolower($segNome) : 'um negócio local') . ($local ? " em $local" : '') . ", com atendimento feito com cuidado e atenção a cada detalhe.$prova\n\n"
                 . "Aqui você escolhe o serviço, o dia e o horário direto pelo site, sem esperar resposta, e recebe a confirmação e o lembrete no seu WhatsApp.",
    ];
    if (!function_exists('ia_configurada') || !ia_configurada()) return $base;
    $reviews = array_slice((array)json_decode((string)($p['avaliacoes_texto'] ?? ''), true), 0, 5);
    $prompt = "Escreva os textos do site de um negócio local brasileiro, em português do Brasil, tom acolhedor e direto, sem exageros e SEM inventar números, prêmios ou anos de mercado.\n"
        . "Negócio: {$p['nome']}\nRamo: " . ($segNome ?: $p['categoria']) . " ({$p['categoria']})\nLocal: $local\n"
        . ($p['nota'] ? "Nota no Google: {$p['nota']} ({$p['avaliacoes']} avaliações)\n" : '')
        . ($reviews ? "O que os clientes dizem: " . implode(' | ', array_map(fn($r) => mb_strimwidth((string)($r['texto'] ?? ''), 0, 160, '…'), $reviews)) . "\n" : '')
        . 'Responda APENAS JSON: {"subtitulo":"frase de até 16 palavras para o topo do site, com o benefício principal","sobre":"2 parágrafos curtos (até 70 palavras no total) sobre o negócio, usando o que os clientes elogiam"}';
    $r = chamar_ia_json($prompt, 500, 30, 0.7, ['esforco' => 'low']);
    if (!$r['ok']) return $base;
    $sub = trim((string)($r['dados']['subtitulo'] ?? ''));
    $sobre = trim((string)($r['dados']['sobre'] ?? ''));
    return ['subtitulo' => $sub !== '' ? mb_substr($sub, 0, 160) : $base['subtitulo'], 'sobre' => $sobre !== '' ? mb_substr($sobre, 0, 1200) : $base['sobre']];
}

// Copia uma imagem da prévia para a pasta da empresa (o site não depende do lead)
function prosp_copiar_imagem($url, $empresaId, $nome) {
    if (!$url || !str_starts_with($url, '/uploads/')) return $url;
    $orig = realpath(__DIR__ . '/..' . parse_url($url, PHP_URL_PATH));
    if (!$orig || !is_file($orig)) return null;
    $pasta = function_exists('img_ia_pasta') ? img_ia_pasta('empresas/' . (int)$empresaId) : null;
    if (!$pasta) return $url;
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION)) ?: 'jpg';
    $dest = "$pasta/$nome.$ext";
    return @copy($orig, $dest) ? '/uploads/empresas/' . (int)$empresaId . "/$nome.$ext" : $url;
}

// Transforma o lead em empresa + site. $opts: email, plano_id, publicar (bool)
// Garante as colunas novas mesmo se a atualização automática do banco ainda não rodou
function prosp_cliente_garantir_colunas() {
    global $pdo;
    if (function_exists('gds_garantir_schema')) { try { gds_garantir_schema(true); } catch (Throwable $e) {} }
    foreach (["ALTER TABLE prospects ADD COLUMN IF NOT EXISTS empresa_id INT", "ALTER TABLE empresas ADD COLUMN IF NOT EXISTS google_nota NUMERIC(2,1)", "ALTER TABLE empresas ADD COLUMN IF NOT EXISTS google_avaliacoes INT"] as $sql) {
        try { $pdo->exec($sql); } catch (Throwable $e) { error_log('GDS virar cliente (coluna): ' . $e->getMessage()); }
    }
}
function prosp_tem_coluna($tabela, $coluna) {
    global $pdo;
    $st = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name=? AND column_name=?");
    $st->execute([$tabela, $coluna]);
    return (bool)$st->fetchColumn();
}

function prosp_virar_cliente($id, $opts = []) {
    global $pdo;
    prosp_cliente_garantir_colunas();
    $temEmpresaId = prosp_tem_coluna('prospects', 'empresa_id');
    $st = $pdo->prepare("SELECT * FROM prospects WHERE id=?"); $st->execute([(int)$id]);
    $p = $st->fetch();
    if (!$p) return ['ok' => false, 'erro' => 'Lead não encontrado.'];
    if ($temEmpresaId && !empty($p['empresa_id'])) {
        $e = $pdo->prepare("SELECT id, slug FROM empresas WHERE id=?"); $e->execute([$p['empresa_id']]);
        if ($e = $e->fetch()) return ['ok' => true, 'ja_existia' => true, 'empresa_id' => (int)$e['id'], 'slug' => $e['slug'], 'url' => prosp_url_base() . '/' . $e['slug']];
    }
    $seg = null;
    try { $s = $pdo->prepare("SELECT * FROM segmentos WHERE slug=?"); $s->execute([$p['segmento_slug'] ?: '']); $seg = $s->fetch() ?: null; } catch (PDOException $e) {}
    // Acesso do dono: o e-mail que você informar (dá para trocar depois em Empresas → Editar)
    $email = mb_strtolower(trim((string)($opts['email'] ?? '')));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'erro' => 'E-mail inválido.'];
    $host = preg_replace('#^https?://(www\.)?#', '', parse_url(prosp_url_base(), PHP_URL_HOST) ?: 'sitegestor.com');
    $slug = prosp_slug_unico($p['nome']);
    if ($email === '') $email = $slug . '@clientes.' . $host;
    $ja = $pdo->prepare("SELECT 1 FROM usuarios WHERE LOWER(email)=?"); $ja->execute([$email]);
    if ($ja->fetchColumn()) return ['ok' => false, 'erro' => "Já existe um acesso com o e-mail $email."];
    $senha = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(12))), 0, 10);
    $wa = preg_replace('/\D/', '', (string)$p['whatsapp']);
    if (strlen($wa) >= 12 && str_starts_with($wa, '55')) $wa = substr($wa, 2);
    if ($wa === '') { $t = preg_replace('/\D/', '', (string)$p['telefone']); if (strlen($t) === 11 && $t[2] === '9') $wa = $t; }
    $hor = prosp_horario_google(json_decode((string)($p['horario'] ?? ''), true));
    $planoId = (int)($opts['plano_id'] ?? 0) ?: null;
    $publicar = !array_key_exists('publicar', $opts) || !empty($opts['publicar']);
    // O que vem de fora (fotos, logo, IA) roda antes da transação
    $fotos = prosp_fotos_previa($p);
    $logo = function_exists('prosp_logo_local') ? prosp_logo_local($p) : null;
    $cor = function_exists('prosp_cor_marca') ? prosp_cor_marca($p) : null;
    $textos = prosp_textos_site($p, $seg['nome'] ?? '');
    $servicos = prosp_servicos_exemplo($p['segmento_slug'], $p['categoria']);
    $reviews = array_values(array_filter((array)json_decode((string)($p['avaliacoes_texto'] ?? ''), true), fn($r) => is_array($r) && !empty($r['texto'])));

    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare("INSERT INTO empresas (nome, slug, segmento_id, plano_id, email, telefone, whatsapp, endereco, instagram, status, vencimento, segmento_descricao,
            horario_abertura, horario_fechamento, dias_funcionamento, pausa_inicio, pausa_fim, intervalo_agenda, whatsapp_flutuante)
            VALUES (?,?,?,?,?,?,?,?,?, 'pendente', CURRENT_DATE + INTERVAL '30 days', ?, ?,?,?,?,?, 30, true) RETURNING id");
        $ins->execute([$p['nome'], $slug, $seg['id'] ?? null, $planoId, str_contains($email, '@clientes.') ? null : $email, $p['telefone'] ?: null, $wa ?: null,
            $p['endereco'] ?: null, $p['instagram'] ?: null, mb_substr((string)$p['categoria'], 0, 150) ?: null,
            $hor['abertura'] ?? '09:00', $hor['fechamento'] ?? '18:00', $hor['dias'] ?? '1,2,3,4,5,6', $hor['pausa'][0] ?? null, $hor['pausa'][1] ?? null]);
        $empresaId = (int)$ins->fetchColumn();
        $pdo->prepare("INSERT INTO usuarios (empresa_id, nome, email, senha_hash, role) VALUES (?, ?, ?, ?, 'empresa')")
            ->execute([$empresaId, $p['nome'], $email, password_hash($senha, PASSWORD_DEFAULT)]);
        $fotosEmp = array_values(array_filter(array_map(fn($u, $i) => prosp_copiar_imagem($u, $empresaId, 'foto-' . ($i + 1)), $fotos, array_keys($fotos))));
        $logoEmp = $logo ? prosp_copiar_imagem($logo, $empresaId, 'logo') : null;
        if ($logoEmp) $pdo->prepare("UPDATE empresas SET logo=? WHERE id=?")->execute([$logoEmp, $empresaId]);
        $pdo->prepare("INSERT INTO sites (empresa_id, titulo, subtitulo, sobre, banner_imagem, galeria_imagens, cor_primaria, animacoes, publicado, seo_titulo, seo_descricao)
            VALUES (?,?,?,?,?,?,?, 'cinema', ?, ?, ?)")
            ->execute([$empresaId, $p['nome'], $textos['subtitulo'], $textos['sobre'], $fotosEmp[0] ?? null, implode(',', array_slice($fotosEmp, 1)) ?: null, $cor ?: null, $publicar ? 'true' : 'false',
                mb_substr($p['nome'] . ($seg ? ' · ' . $seg['nome'] : '') . ($p['bairro'] || $p['cidade'] ? ' em ' . ($p['bairro'] ?: $p['cidade']) : ''), 0, 70),
                mb_substr($textos['subtitulo'] . ' ' . ($p['endereco'] ?? ''), 0, 160)]);
        $sv = $pdo->prepare("INSERT INTO servicos (empresa_id, nome, descricao, preco, duracao_minutos, ativo, destaque, ordem) VALUES (?,?,?,0,?,true,?,?)");
        foreach ($servicos as $i => $s) $sv->execute([$empresaId, $s['nome'], '', prosp_duracao_servico($s['nome']), $i < 2 ? 'true' : 'false', $i]);
        $av = $pdo->prepare("INSERT INTO avaliacoes (empresa_id, nota, comentario, nome_autor, aprovado, origem) VALUES (?,?,?,?,true,'google')");
        foreach (array_slice($reviews, 0, 10) as $r) $av->execute([$empresaId, max(1, min(5, (int)($r['nota'] ?? 5))), mb_substr((string)$r['texto'], 0, 1000), mb_substr((string)($r['autor'] ?? 'Cliente'), 0, 100)]);
        if ($planoId) {
            $pl = $pdo->prepare("SELECT valor FROM planos WHERE id=?"); $pl->execute([$planoId]);
            if (($valor = $pl->fetchColumn()) !== false) $pdo->prepare("INSERT INTO pagamentos_assinaturas (empresa_id, plano_id, valor, vencimento, status) VALUES (?,?,?, CURRENT_DATE + INTERVAL '30 days', 'pendente')")->execute([$empresaId, $planoId, $valor]);
        }
        $pdo->prepare("UPDATE prospects SET status='fechado', " . ($temEmpresaId ? 'empresa_id=' . (int)$empresaId . ', ' : '') . "proximo_contato=NULL, atualizado_em=NOW(),
            observacoes=TRIM(COALESCE(observacoes,'') || ' · Virou cliente em ' || TO_CHAR(NOW(),'DD/MM/YYYY')) WHERE id=?")->execute([$p['id']]);
        // Cancela qualquer mensagem de prospecção ainda na fila para este número
        $pdo->prepare("DELETE FROM whatsapp_mensagens WHERE ref_tipo='prospect' AND ref_id=? AND status='pendente'")->execute([$p['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('GDS virar cliente: ' . $e->getMessage());
        return ['ok' => false, 'erro' => 'Não consegui criar a empresa: ' . mb_substr($e->getMessage(), 0, 200)];
    }
    // Quem indicou ganha 1 mês grátis
    if (!empty($p['indicado_por'])) { try { require_once __DIR__ . '/prospeccao_vendedor.php'; prosp_indica_premiar($p); } catch (Throwable $e) { error_log('GDS indicação prêmio: ' . $e->getMessage()); } }
    // Nota do Google no site (opcional: nunca impede a criação)
    if ($p['nota'] && $p['avaliacoes'] && prosp_tem_coluna('empresas', 'google_nota')) {
        try { $pdo->prepare("UPDATE empresas SET google_nota=?, google_avaliacoes=? WHERE id=?")->execute([round((float)$p['nota'], 1), (int)$p['avaliacoes'], $empresaId]); } catch (Throwable $e) {}
    }
    return ['ok' => true, 'empresa_id' => $empresaId, 'slug' => $slug, 'url' => prosp_url_base() . '/' . $slug, 'email' => $email, 'senha' => $senha,
            'publicado' => $publicar, 'servicos' => count($servicos), 'avaliacoes' => min(10, count($reviews)), 'fotos' => count($fotosEmp ?? []), 'horario' => $hor];
}
