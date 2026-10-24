<?php
// ============================================================
// RENDERIZAÇÃO DO SITE PÚBLICO (uma só implementação, usada pelo
// site de verdade em site/index.php e pela pré-visualização em
// site/preview.php — antes eram dois códigos diferentes que saíam
// do sincronismo).
// ============================================================
require_once __DIR__ . '/site_fragmentos.php';
require_once __DIR__ . '/agenda_helper.php';

const SITE_SQL_BASE = "
    SELECT e.*, s.titulo, s.subtitulo, s.sobre, s.publicado, s.cor_primaria, s.cor_secundaria,
           s.galeria_imagens, s.seo_titulo, s.seo_descricao, s.layout, s.secoes, s.banner_imagem,
           s.html_gerado, s.ia_modo_total,
           seg.nome AS segmento_nome, pl.permite_pix
    FROM empresas e
    JOIN sites s ON s.empresa_id = e.id
    LEFT JOIN segmentos seg ON seg.id = e.segmento_id
    LEFT JOIN planos pl ON pl.id = e.plano_id
";

function site_carregar_dados($empresa) {
    global $pdo;
    $id = $empresa['id'];
    $q = function ($sql) use ($pdo, $id) { $s = $pdo->prepare($sql); $s->execute([$id]); return $s->fetchAll(); };
    $d = [];
    $d['servicos'] = $q("SELECT * FROM servicos WHERE empresa_id=? AND ativo=true ORDER BY nome");
    $d['profissionais'] = $q("SELECT * FROM profissionais WHERE empresa_id=? AND ativo=true ORDER BY nome");
    $d['cupons'] = $q("SELECT * FROM cupons WHERE empresa_id=? AND ativo=true AND (validade IS NULL OR validade >= CURRENT_DATE)");
    $d['blocos'] = $q("SELECT * FROM site_blocos WHERE empresa_id=? AND ativo=true ORDER BY ordem, id");
    try {
        $d['avaliacoes'] = $q("SELECT av.nota, av.comentario, COALESCE(NULLIF(av.nome_autor,''), c.nome, 'Cliente') AS autor
            FROM avaliacoes av LEFT JOIN clientes c ON c.id = av.cliente_id
            WHERE av.empresa_id=? AND COALESCE(av.aprovado, true) ORDER BY av.criado_em DESC LIMIT 8");
    } catch (PDOException $e) {
        // banco sem o adicional_10 ainda: colunas novas não existem
        $d['avaliacoes'] = $q("SELECT av.nota, av.comentario, COALESCE(c.nome, 'Cliente') AS autor FROM avaliacoes av
            LEFT JOIN clientes c ON c.id = av.cliente_id WHERE av.empresa_id=? ORDER BY av.criado_em DESC LIMIT 8");
    }
    $d['galeria'] = array_values(array_filter(array_map('trim', explode(',', $empresa['galeria_imagens'] ?? ''))));
    $d['maps_key'] = trim(get_config('google_maps_key', ''));
    $d['analytics_id'] = trim(get_config('google_analytics_id', ''));
    $d['agenda'] = agenda_config($empresa);
    return $d;
}

function site_estado_vazio() {
    return ['lead' => [], 'agendamento' => [], 'avaliacao' => [], 'pagina_forcada' => null];
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

function site_processar_post($empresa, $dados) {
    global $pdo;
    $estado = site_estado_vazio();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return $estado;

    // ---------- CONTATO (gera lead) ----------
    if (isset($_POST['enviar_lead'])) {
        $nome = site_limpar('nome', 120); $email = site_limpar('email', 150);
        $tel = site_limpar('telefone', 20); $msg = site_limpar('mensagem', 2000);
        $estado['pagina_forcada'] = 'contato';
        if (site_eh_spam()) { $estado['lead'] = ['ok' => true, 'nome' => $nome]; return $estado; } // finge sucesso pro robô
        if ($nome === '' || $msg === '') {
            $estado['lead'] = ['erro' => 'Preencha seu nome e a mensagem.', 'post' => $_POST];
        } elseif ($email === '' && $tel === '') {
            $estado['lead'] = ['erro' => 'Informe um e-mail ou telefone para podermos responder.', 'post' => $_POST];
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $estado['lead'] = ['erro' => 'E-mail inválido.', 'post' => $_POST];
        } else {
            $pdo->prepare("INSERT INTO leads (empresa_id, nome, email, telefone, mensagem) VALUES (?,?,?,?,?)")
                ->execute([$empresa['id'], $nome, $email ?: null, $tel ?: null, $msg]);
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

    // ---------- AGENDAMENTO ----------
    if (isset($_POST['fazer_agendamento'])) {
        $estado['pagina_forcada'] = 'agendar';
        $nome = site_limpar('ag_nome', 120); $email = site_limpar('ag_email', 150);
        $tel = site_limpar('ag_telefone', 20); $obs = site_limpar('ag_observacoes', 500);
        $dataHora = (string)($_POST['ag_data_hora'] ?? '');
        $erro = '';

        // Serviço e profissional precisam ser DESTA empresa (antes aceitava id de qualquer empresa)
        $servico = null; $profissional = null;
        $servicoId = (int)($_POST['ag_servico_id'] ?? 0);
        $profId = (int)($_POST['ag_profissional_id'] ?? 0);
        foreach ($dados['servicos'] as $s) if ((int)$s['id'] === $servicoId) $servico = $s;
        foreach ($dados['profissionais'] as $p) if ((int)$p['id'] === $profId) $profissional = $p;

        if (site_eh_spam()) { $erro = 'Não foi possível enviar agora. Tente novamente em alguns minutos.'; }
        elseif ($nome === '' || $tel === '') { $erro = 'Preencha seu nome e telefone.'; }
        elseif (strlen(preg_replace('/\D/', '', $tel)) < 10) { $erro = 'Telefone inválido — use DDD + número.'; }
        elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $erro = 'E-mail inválido.'; }
        elseif (!empty($dados['servicos']) && !$servico) { $erro = 'Escolha um serviço.'; }
        elseif ($profId && !$profissional) { $erro = 'Profissional inválido.'; }
        else {
            $duracao = $servico && (int)$servico['duracao_minutos'] > 0 ? (int)$servico['duracao_minutos'] : $dados['agenda']['intervalo'];
            $erro = agenda_validar($empresa, $dataHora, $duracao, $profissional ? (int)$profissional['id'] : null);
        }
        if ($erro !== '') { $estado['agendamento'] = ['erro' => $erro, 'post' => $_POST]; return $estado; }

        $dataSql = str_replace('T', ' ', $dataHora) . ':00';
        try {
            $pdo->beginTransaction();
            // Trava por empresa: dois clientes clicando no mesmo horário ao mesmo tempo não geram agendamento duplo
            $pdo->prepare("SELECT pg_advisory_xact_lock(?)")->execute([(int)$empresa['id']]);
            $erro = agenda_validar($empresa, $dataHora, $duracao, $profissional ? (int)$profissional['id'] : null);
            if ($erro !== '') { $pdo->rollBack(); $estado['agendamento'] = ['erro' => $erro, 'post' => $_POST]; return $estado; }

            $clienteId = null;
            $telDig = preg_replace('/\D/', '', $tel);
            $busca = $pdo->prepare("SELECT id FROM clientes WHERE empresa_id=? AND ((? <> '' AND email=?) OR regexp_replace(COALESCE(telefone,''), '\D', '', 'g') = ?) LIMIT 1");
            $busca->execute([$empresa['id'], $email, $email, $telDig]);
            if ($ex = $busca->fetch()) { $clienteId = $ex['id']; }
            else {
                $novo = $pdo->prepare("INSERT INTO clientes (empresa_id, nome, email, telefone) VALUES (?,?,?,?) RETURNING id");
                $novo->execute([$empresa['id'], $nome, $email ?: null, $tel]);
                $clienteId = $novo->fetch()['id'];
            }
            $ins = $pdo->prepare("INSERT INTO agendamentos (empresa_id, cliente_id, servico_id, profissional_id, data_hora, status, observacoes) VALUES (?,?,?,?,?,'agendado',?) RETURNING id");
            $ins->execute([$empresa['id'], $clienteId, $servico['id'] ?? null, $profissional['id'] ?? null, $dataSql, $obs ?: null]);
            $agId = $ins->fetch()['id'];
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('GDS agendamento: ' . $e->getMessage());
            $estado['agendamento'] = ['erro' => 'Não foi possível concluir o agendamento agora. Tente novamente.', 'post' => $_POST];
            return $estado;
        }

        $dataFormatada = date('d/m/Y \à\s H:i', strtotime($dataSql));
        $ag = ['ok' => true, 'detalhes' => [
            'servico_nome' => $servico['nome'] ?? '',
            'profissional_nome' => $profissional['nome'] ?? '',
            'data_formatada' => $dataFormatada,
            'empresa_whatsapp' => $empresa['whatsapp'],
            'link_calendario' => gerar_link_google_calendar(
                ($servico['nome'] ?? 'Agendamento') . ' - ' . $empresa['nome'], $dataSql, $duracao,
                'Agendamento feito pelo site de ' . $empresa['nome'], $empresa['endereco'] ?? ''),
        ]];

        // PIX: se a empresa ativou pagamento online e o serviço tem preço, gera a cobrança de verdade
        if ($servico && (float)$servico['preco'] > 0 && pg_bool($empresa['pix_ativo'] ?? false) && !empty($empresa['mercadopago_access_token'])) {
            require_once __DIR__ . '/mercadopago_helper.php';
            $cob = gerar_cobranca_pix($empresa['mercadopago_access_token'], $servico['preco'], $servico['nome'] . ' - ' . $empresa['nome'], $email);
            if ($cob['ok']) {
                $pdo->prepare("INSERT INTO pagamentos_clientes (empresa_id, agendamento_id, cliente_id, valor, referencia_gateway) VALUES (?,?,?,?,?)")
                    ->execute([$empresa['id'], $agId, $clienteId, $servico['preco'], $cob['mp_id']]);
                $ag['pix'] = ['qr_base64' => $cob['qr_base64'], 'qr_code' => $cob['qr_code']];
            } else {
                $ag['pix_erro'] = $cob['erro'];
            }
        }
        $estado['agendamento'] = $ag;
    }
    return $estado;
}

function site_fragmentos_marcadores($empresa, $dados, $estado) {
    return [
        '{{SOBRE}}' => frag_sobre($empresa['sobre']),
        '{{SERVICOS}}' => frag_servicos($dados['servicos']),
        '{{EQUIPE}}' => frag_equipe($dados['profissionais']),
        '{{GALERIA}}' => frag_galeria($dados['galeria'], $empresa['nome']),
        '{{AGENDAMENTO}}' => frag_agendamento($dados['servicos'], $dados['profissionais'], $estado['agendamento'], $dados['agenda'], $empresa['id']),
        '{{AVALIACOES}}' => frag_avaliacoes($dados['avaliacoes'], $estado['avaliacao']),
        '{{CONTATO}}' => frag_contato($estado['lead']),
        '{{MAPA}}' => frag_mapa($empresa['endereco'], $dados['maps_key']),
        '{{CUPONS}}' => frag_cupons($dados['cupons']),
        '{{WHATSAPP_FLOAT}}' => frag_whatsapp_float($empresa['whatsapp']),
    ];
}

function site_script_analytics($id) {
    if (!$id || !preg_match('/^[A-Za-z0-9\-_]+$/', $id)) return '';
    return '<script async src="https://www.googletagmanager.com/gtag/js?id=' . e($id) . '"></script>'
         . "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . e($id) . "');</script>";
}

// Depois de enviar um formulário, mostra a página onde está a confirmação/erro —
// funciona com qualquer navegação que a IA tenha escrito (procura o [data-page] pai).
function site_script_foco_resultado() {
    return "<script>document.addEventListener('DOMContentLoaded',function(){"
         . "var alvo=document.querySelector('.gds-confirmacao,.gds-msg-erro');if(!alvo)return;"
         . "var pag=alvo.closest('[data-page]');"
         . "if(pag){var k=pag.getAttribute('data-page');"
         . "if(typeof window.gdsMostrarPagina==='function'){try{window.gdsMostrarPagina(k);}catch(e){}}"
         . "if(getComputedStyle(pag).display==='none'){document.querySelectorAll('[data-page]').forEach(function(el){el.style.setProperty('display',el===pag?'block':'none','important');});}}"
         . "setTimeout(function(){alvo.scrollIntoView({behavior:'smooth',block:'center'});},120);"
         . "});</script>";
}

function site_script_preview() {
    return "<script>document.addEventListener('submit',function(e){e.preventDefault();e.stopPropagation();alert('Pré-visualização: os formulários não são enviados de verdade aqui.');},true);</script>";
}

// ------------------------------------------------------------
// MODO IA: HTML gerado + marcadores trocados por conteúdo real
// ------------------------------------------------------------
function site_render_ia($empresa, $dados, $estado, $preview = false) {
    $html = strtr($empresa['html_gerado'], site_fragmentos_marcadores($empresa, $dados, $estado));
    $extras = frag_css_apoio();
    if (!$preview) $extras .= site_script_analytics($dados['analytics_id']);
    if (stripos($html, '</head>') !== false) {
        $html = preg_replace('/<\/head>/i', str_replace(['\\', '$'], ['\\\\', '\\$'], $extras) . '</head>', $html, 1);
    } else {
        $html = $extras . $html;
    }
    $fim = site_script_foco_resultado() . ($preview ? site_script_preview() : '');
    if (stripos($html, '</body>') !== false) {
        $html = preg_replace('/<\/body>/i', str_replace(['\\', '$'], ['\\\\', '\\$'], $fim) . '</body>', $html, 1);
    } else {
        $html .= $fim;
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
    $layout = in_array($empresa['layout'], ['classico', 'bold', 'editorial', 'grid'], true) ? $empresa['layout'] : 'classico';
    $nome = $empresa['nome'];
    $sec = []; $nav = [];
    $secao = function ($id, $titulo, $conteudo, $rotuloNav = null) use (&$sec, &$nav) {
        $sec[$id] = '<section id="' . $id . '" class="psite-section"><div class="container">' . ($titulo ? '<h2>' . e($titulo) . '</h2>' : '') . $conteudo . '</div></section>';
        $nav[$id] = $rotuloNav ?? $titulo;
    };

    if (trim($empresa['sobre'] ?? '') !== '') $secao('sobre', 'Sobre', '<p class="lead">' . nl2br(e($empresa['sobre'])) . '</p>');
    if ($dados['servicos']) {
        $h = '<div class="psite-grid">';
        foreach ($dados['servicos'] as $s) {
            $h .= '<div class="psite-item"><h3>' . e($s['nome']) . '</h3><p>' . e($s['descricao']) . '</p><span class="psite-price">' . frag_preco($s['preco']) . '</span>'
                . ((int)$s['duracao_minutos'] > 0 ? '<span class="psite-dur">' . (int)$s['duracao_minutos'] . ' min</span>' : '') . '</div>';
        }
        $secao('servicos', 'Serviços', $h . '</div>');
    }
    if ($dados['profissionais']) {
        $h = '<div class="psite-team">';
        foreach ($dados['profissionais'] as $p) {
            $h .= '<div class="psite-person">' . ($p['foto'] ? '<img src="' . e($p['foto']) . '" alt="' . e($p['nome']) . '" loading="lazy">' : '<div class="avatar-fallback">' . e(mb_strtoupper(mb_substr($p['nome'], 0, 1))) . '</div>')
                . '<h3>' . e($p['nome']) . '</h3><p>' . e($p['especialidade']) . '</p></div>';
        }
        $secao('equipe', 'Equipe', $h . '</div>');
    }
    if ($dados['galeria']) {
        $h = '<div class="psite-gallery">';
        foreach ($dados['galeria'] as $i => $img) $h .= '<img src="' . e($img) . '" alt="Foto ' . ($i + 1) . ' de ' . e($nome) . '" loading="lazy">';
        $secao('galeria', 'Galeria', $h . '</div>');
    }
    if ($dados['blocos']) {
        $h = '';
        foreach ($dados['blocos'] as $b) $h .= '<div class="psite-bloco"><h3>' . e($b['titulo']) . '</h3><p>' . nl2br(e($b['conteudo'])) . '</p></div>';
        $secao('blocos', '', $h, 'Destaques');
    }
    if ($dados['cupons']) {
        $h = '<div class="psite-grid psite-grid-cupons">';
        foreach ($dados['cupons'] as $c) $h .= '<div class="psite-cupom"><strong>' . e($c['codigo']) . '</strong> — ' . number_format((float)$c['desconto_percentual'], 0) . '% de desconto<p>' . e($c['descricao']) . '</p></div>';
        $secao('cupons', 'Cupons ativos', $h . '</div>');
    }
    $mapa = $empresa['endereco'] ? '<div class="psite-mapa">' . frag_mapa($empresa['endereco'], $dados['maps_key']) . '</div>' : '';
    $agenda = $dados['agenda'];
    $horario = '<p class="psite-horario">' . psite_icon('relogio') . ' Atendimento ' . e(site_dias_texto($agenda['dias'])) . ', das ' . e($agenda['abertura']) . ' às ' . e($agenda['fechamento']) . '</p>';
    $secao('agendar', 'Agendar horário', '<div class="psite-form-wrap"><div><p class="lead">Escolha o serviço, o profissional (se quiser) e o melhor horário. Confirmamos com você em seguida.</p>' . $horario . $mapa . '</div><div>'
        . frag_agendamento($dados['servicos'], $dados['profissionais'], $estado['agendamento'], $agenda, $empresa['id']) . '</div></div>', 'Agendar');
    $secao('avaliacoes', 'Avaliações', frag_avaliacoes($dados['avaliacoes'], $estado['avaliacao']));
    $secao('contato', 'Contato', frag_contato($estado['lead']));

    $ordemPadrao = ['sobre', 'servicos', 'equipe', 'galeria', 'blocos', 'cupons', 'agendar', 'avaliacoes', 'contato'];
    $ordem = !empty($empresa['secoes']) ? array_values(array_filter(explode(',', $empresa['secoes']))) : $ordemPadrao;
    // contato sempre existe (é o mínimo do site)
    if (!in_array('contato', $ordem, true)) $ordem[] = 'contato';

    $titulo = $empresa['seo_titulo'] ?: ($empresa['titulo'] ?: $nome);
    $descricao = $empresa['seo_descricao'] ?: ('Conheça ' . $nome . ($empresa['segmento_nome'] ? ' — ' . $empresa['segmento_nome'] : '') . ($empresa['endereco'] ? ' em ' . $empresa['endereco'] : ''));
    $corP = cor_hex_valida_site($empresa['cor_primaria'], '#4f7cff');
    $corS = cor_hex_valida_site($empresa['cor_secundaria'], '#16213e');
    $ctaAlvo = isset($sec['agendar']) && in_array('agendar', $ordem, true) ? 'agendar' : 'contato';

    $schema = ['@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $nome, 'description' => $descricao];
    if ($empresa['endereco']) $schema['address'] = $empresa['endereco'];
    if ($empresa['telefone'] || $empresa['whatsapp']) $schema['telephone'] = $empresa['telefone'] ?: $empresa['whatsapp'];
    if ($empresa['logo']) $schema['image'] = $empresa['logo'];

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
<script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
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
        <a href="#<?= $ctaAlvo ?>" onclick="mostrarPagina('<?= $ctaAlvo ?>');return false;" class="psite-btn"><?= $ctaAlvo === 'agendar' ? 'Agendar horário' : 'Fale com a gente' ?></a>
    </div>
    <?php if ($empresa['banner_imagem']): ?><div class="psite-hero-banner-wrap"><img src="<?= e($empresa['banner_imagem']) ?>" alt="<?= e($nome) ?>"></div>
    <?php else: ?><div class="psite-hero-banner-wrap" style="background:linear-gradient(135deg,var(--accent),var(--accent-2));"></div><?php endif; ?>
</div></header>
<?php else: ?>
<header class="psite-hero"><div class="container psite-hero-inner">
    <div class="psite-tag"><span class="dot"></span><?= e($empresa['segmento_nome'] ?: 'Negócio local') ?></div>
    <h1><?= e($empresa['titulo'] ?: $nome) ?></h1>
    <p><?= e($empresa['subtitulo'] ?: 'Conheça nosso trabalho e agende com a gente.') ?></p>
    <a href="#<?= $ctaAlvo ?>" onclick="mostrarPagina('<?= $ctaAlvo ?>');return false;" class="psite-btn"><?= $ctaAlvo === 'agendar' ? 'Agendar horário' : 'Fale com a gente' ?></a>
</div></header>
<?php endif; ?>
<div class="psite-features container">
    <div class="psite-feature"><?= psite_icon('calendario') ?> Agende online</div>
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
<?= frag_whatsapp_float($empresa['whatsapp']) ?>
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
