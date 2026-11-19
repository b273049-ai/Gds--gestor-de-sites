<?php
// ============================================================
// RECURSOS DO SITE (migração 41) — cada empresa liga os que quiser, por botões:
//  • Cartão fidelidade: a cada N atendimentos/pedidos concluídos, um prêmio. Os selos são contados
//    sozinhos (agendamento "concluído" ou pedido "concluído" desde o dia em que ligou) + selos dados à
//    mão no painel. O cliente confere no site com o WhatsApp dele. Sem app.
//  • App no celular: o site vira ícone na tela do celular (manifest + service worker /gds-sw.js).
//  • Stories: bolinhas no topo com novidades (fotos que o dono sobe), como no Instagram.
//  • Antes e depois: fotos com barra de arrastar para comparar.
//  • Cupom de aniversário: o cliente cadastra o dia no site; no dia, recebe um cupom no WhatsApp.
//  • Vídeo no topo: um vídeo curto do espaço no lugar da foto principal.
// Tudo de graça: só PHP, CSS e JS próprios.
// ============================================================

const REC_PADRAO = [
    'fidelidade' => ['ativo' => false, 'meta' => 10, 'premio' => '', 'desde' => null],
    'app' => ['ativo' => false],
    'stories' => ['ativo' => false, 'itens' => []],
    'antes_depois' => ['ativo' => false, 'itens' => []],
    'aniversario' => ['ativo' => false, 'desconto' => 10],
    'video' => ['ativo' => false, 'url' => ''],
    // restaurante e bar (includes/restaurante.php)
    'reserva' => ['ativo' => false],
    'fila' => ['ativo' => false],
    'garcom' => ['ativo' => false],
    // serviços (includes/recursos_servicos.php)
    'grade' => ['ativo' => false],
    'acompanhar' => ['ativo' => false],
    'quiz' => ['ativo' => false],
    'calculadora' => ['ativo' => false],
    'tour360' => ['ativo' => false, 'itens' => []],
    // treino da semana e vale-presente (includes/treino_vale.php)
    'treino' => ['ativo' => false],
    'vale' => ['ativo' => false, 'validade' => 180],
    'monte' => ['ativo' => false],
];
const REC_MAX_VIDEO = 25 * 1024 * 1024;

function rec_ler($empresaOuSite) {
    $j = json_decode((string)($empresaOuSite['recursos'] ?? ''), true);
    $r = REC_PADRAO;
    if (is_array($j)) foreach ($r as $k => $v) if (isset($j[$k]) && is_array($j[$k])) $r[$k] = array_merge($v, $j[$k]);
    $r['fidelidade']['meta'] = max(3, min(30, (int)$r['fidelidade']['meta']));
    $r['aniversario']['desconto'] = max(5, min(50, (int)$r['aniversario']['desconto']));
    foreach (['stories', 'antes_depois', 'tour360'] as $k) $r[$k]['itens'] = array_values(array_filter((array)$r[$k]['itens'], 'is_array'));
    return $r;
}

function rec_gravar($empresaId, $r) {
    global $pdo;
    $pdo->prepare("UPDATE sites SET recursos=?, atualizado_em=NOW() WHERE empresa_id=?")->execute([json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int)$empresaId]);
    if (function_exists('gds_cache_invalidar')) gds_cache_invalidar((int)$empresaId);
}

function rec_ativo($r, $k) {
    if (empty($r[$k]['ativo'])) return false;
    return match ($k) {
        'stories', 'antes_depois', 'tour360' => count($r[$k]['itens']) > 0,
        'video' => ($r['video']['url'] ?? '') !== '',
        default => true,
    };
}

// Telefone só com os 11 últimos dígitos (DDD + número), para comparar cadastros diferentes
function rec_tel($t) {
    $d = preg_replace('/\D/', '', (string)$t);
    if (strlen($d) >= 12 && str_starts_with($d, '55')) $d = substr($d, 2);
    return substr($d, -11);
}

// Palavra do que conta selo, pela forma de atendimento
function fid_unidade($modo, $plural = true) {
    return match ($modo) { 'catalogo' => $plural ? 'pedidos' : 'pedido', 'agendamento' => $plural ? 'atendimentos' : 'atendimento', default => $plural ? 'compras' : 'compra' };
}

// ---------- cartão fidelidade ----------
function fid_selos($empresaId, $tel, $cfg) {
    global $pdo;
    $tel = rec_tel($tel);
    if (strlen($tel) < 10) return 0;
    $desde = $cfg['desde'] ?: date('Y-m-d');
    $sql = "SELECT
        (SELECT COUNT(*) FROM agendamentos a JOIN clientes c ON c.id=a.cliente_id WHERE a.empresa_id=:e AND a.status='concluido' AND a.data_hora >= CAST(:d AS date) AND RIGHT(regexp_replace(COALESCE(c.telefone,''), '\\D', '', 'g'), 11) = :t)
      + (SELECT COUNT(*) FROM pedidos p JOIN clientes c ON c.id=p.cliente_id WHERE p.empresa_id=:e AND p.status='concluido' AND p.criado_em >= CAST(:d AS date) AND RIGHT(regexp_replace(COALESCE(c.telefone,''), '\\D', '', 'g'), 11) = :t)
      + (SELECT COALESCE(SUM(selos), 0) FROM fidelidade_mov WHERE empresa_id=:e AND telefone=:t)";
    try { $st = $pdo->prepare($sql); $st->execute(['e' => (int)$empresaId, 'd' => $desde, 't' => $tel]); return max(0, (int)$st->fetchColumn()); }
    catch (PDOException $e) { return 0; }
}

function fid_dar($empresaId, $tel, $selos, $motivo = 'manual') {
    global $pdo;
    $tel = rec_tel($tel);
    if (strlen($tel) < 10 || !$selos) return false;
    $pdo->prepare("INSERT INTO fidelidade_mov (empresa_id, telefone, selos, motivo) VALUES (?,?,?,?)")->execute([(int)$empresaId, $tel, (int)$selos, mb_substr($motivo, 0, 30)]);
    if (function_exists('gds_cache_invalidar')) gds_cache_invalidar((int)$empresaId);
    return true;
}

// Clientes com selos (para o painel): [tel, nome, selos]
function fid_ranking($empresaId, $cfg, $limite = 30) {
    global $pdo;
    $desde = $cfg['desde'] ?: date('Y-m-d');
    $sql = "WITH mov AS (
        SELECT RIGHT(regexp_replace(COALESCE(c.telefone,''), '\\D', '', 'g'), 11) AS tel, MAX(c.nome) AS nome, COUNT(*) AS n
          FROM agendamentos a JOIN clientes c ON c.id=a.cliente_id WHERE a.empresa_id=:e AND a.status='concluido' AND a.data_hora >= CAST(:d AS date) GROUP BY 1
        UNION ALL
        SELECT RIGHT(regexp_replace(COALESCE(c.telefone,''), '\\D', '', 'g'), 11), MAX(c.nome), COUNT(*)
          FROM pedidos p JOIN clientes c ON c.id=p.cliente_id WHERE p.empresa_id=:e AND p.status='concluido' AND p.criado_em >= CAST(:d AS date) GROUP BY 1
        UNION ALL
        SELECT f.telefone, (SELECT nome FROM clientes c WHERE c.empresa_id=:e AND RIGHT(regexp_replace(COALESCE(c.telefone,''), '\\D', '', 'g'), 11)=f.telefone LIMIT 1), SUM(f.selos)
          FROM fidelidade_mov f WHERE f.empresa_id=:e GROUP BY f.telefone
    ) SELECT tel, MAX(nome) AS nome, SUM(n) AS selos FROM mov WHERE LENGTH(tel) >= 10 GROUP BY tel HAVING SUM(n) > 0 ORDER BY SUM(n) DESC LIMIT " . (int)$limite;
    try { $st = $pdo->prepare($sql); $st->execute(['e' => (int)$empresaId, 'd' => $desde]); return $st->fetchAll(); } catch (PDOException $e) { return []; }
}

// ---------- aniversário ----------
// Cadastro feito pelo cliente no site: cria ou completa o cliente da empresa
function aniv_cadastrar($empresaId, $nome, $tel, $dia, $mes) {
    global $pdo;
    $tel = rec_tel($tel); $nome = mb_substr(trim(strip_tags((string)$nome)), 0, 120);
    $dia = (int)$dia; $mes = (int)$mes;
    if (strlen($tel) < 10 || $nome === '' || !checkdate($mes, $dia, 2000)) return [false, 'Confira o nome, o WhatsApp e o dia do aniversário.'];
    $data = sprintf('2000-%02d-%02d', $mes, $dia);   // o ano não importa (só o dia e o mês)
    $st = $pdo->prepare("SELECT id FROM clientes WHERE empresa_id=? AND RIGHT(regexp_replace(COALESCE(telefone,''), '\\D', '', 'g'), 11)=? ORDER BY id LIMIT 1");
    $st->execute([(int)$empresaId, $tel]);
    if ($id = $st->fetchColumn()) $pdo->prepare("UPDATE clientes SET nascimento=COALESCE(nascimento, ?) WHERE id=?")->execute([$data, $id]);
    else $pdo->prepare("INSERT INTO clientes (empresa_id, nome, telefone, nascimento, observacoes) VALUES (?,?,?,?,?)")->execute([(int)$empresaId, $nome, $tel, $data, 'Cadastrou o aniversário pelo site']);
    return [true, 'Prontinho! No seu aniversário você recebe o presente no WhatsApp.'];
}

// Cron: no dia do aniversário, cria um cupom só da pessoa e manda no WhatsApp (1 vez por ano)
function aniv_rotina() {
    global $pdo;
    $feitos = 0;
    try {
        $sites = $pdo->query("SELECT e.*, s.recursos FROM sites s JOIN empresas e ON e.id=s.empresa_id WHERE s.publicado=true AND s.recursos LIKE '%\"aniversario\"%'")->fetchAll();
    } catch (PDOException $e) { return ['aniversario' => 'sem tabela']; }
    foreach ($sites as $emp) {
        $r = rec_ler($emp);
        if (empty($r['aniversario']['ativo'])) continue;
        $st = $pdo->prepare("SELECT c.id, c.nome, c.telefone FROM clientes c
            WHERE c.empresa_id=? AND c.nascimento IS NOT NULL AND TO_CHAR(c.nascimento, 'MM-DD') = TO_CHAR(CURRENT_DATE, 'MM-DD') AND COALESCE(c.telefone,'') <> ''
              AND NOT EXISTS (SELECT 1 FROM aniversario_envios a WHERE a.empresa_id=c.empresa_id AND a.cliente_id=c.id AND a.ano=EXTRACT(YEAR FROM CURRENT_DATE)) LIMIT 50");
        $st->execute([$emp['id']]);
        foreach ($st->fetchAll() as $c) {
            $codigo = 'NIVER' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $desc = $r['aniversario']['desconto'];
            try {
                $pdo->prepare("INSERT INTO aniversario_envios (empresa_id, cliente_id, ano, cupom) VALUES (?,?,EXTRACT(YEAR FROM CURRENT_DATE),?)")->execute([$emp['id'], $c['id'], $codigo]);
            } catch (PDOException $e) { continue; }   // outra execução já mandou
            $pdo->prepare("INSERT INTO cupons (empresa_id, codigo, descricao, desconto_percentual, validade) VALUES (?,?,?,?,CURRENT_DATE + 15)")
                ->execute([$emp['id'], $codigo, 'Presente de aniversário de ' . mb_substr($c['nome'], 0, 60), $desc]);
            $primeiro = explode(' ', trim($c['nome']))[0];
            $link = function_exists('wa_url_publica') ? wa_url_publica($emp) : '';
            $texto = "🎉 Feliz aniversário, {$primeiro}! A equipe da *{$emp['nome']}* preparou um presente: *{$desc}% de desconto* com o cupom *{$codigo}*, válido por 15 dias." . ($link ? "\nUse no site: {$link}" : '');
            if (function_exists('wa_enfileirar')) wa_enfileirar($emp, $c['telefone'], $texto, 'aniversario');
            $feitos++;
        }
    }
    return ['aniversario' => $feitos . ' cupom(ns)'];
}

// ---------- ícone e manifest do "app" ----------
function rec_icone($empresa, $tam) {
    $tam = in_array((int)$tam, [192, 512], true) ? (int)$tam : 192;
    $pasta = __DIR__ . '/../uploads/empresas/' . (int)$empresa['id'];
    $chave = substr(md5(($empresa['logo'] ?? '') . '|' . ($empresa['nome'] ?? '') . '|' . ($empresa['cor_primaria'] ?? '')), 0, 8);
    $arq = "$pasta/app-$tam-$chave.png";
    $url = '/uploads/empresas/' . (int)$empresa['id'] . "/app-$tam-$chave.png";
    if (is_file($arq)) return $url;
    if (!function_exists('imagecreatetruecolor') || (!is_dir($pasta) && !@mkdir($pasta, 0755, true))) return $empresa['logo'] ?: null;
    $im = imagecreatetruecolor($tam, $tam);
    $c = sscanf(ltrim((string)($empresa['cor_primaria'] ?: '#222222'), '#'), '%02x%02x%02x');
    if (!is_array($c) || count(array_filter($c, 'is_int')) < 3) $c = [34, 34, 34];
    imagefill($im, 0, 0, imagecolorallocate($im, $c[0], $c[1], $c[2]));
    $logo = null;
    if (!empty($empresa['logo']) && str_starts_with($empresa['logo'], '/uploads/') && !str_contains($empresa['logo'], '..')) {
        $f = __DIR__ . '/..' . parse_url($empresa['logo'], PHP_URL_PATH);
        if (is_file($f)) $logo = @imagecreatefromstring((string)file_get_contents($f));
    }
    if ($logo) {
        $w = imagesx($logo); $h = imagesy($logo); $l = min($w, $h);
        imagecopyresampled($im, $logo, 0, 0, (int)(($w - $l) / 2), (int)(($h - $l) / 2), $tam, $tam, $l, $l);
        imagedestroy($logo);
    } else {
        $fonte = __DIR__ . '/../assets/fontes/Inter-Bold.otf';
        $letra = mb_strtoupper(mb_substr(trim((string)$empresa['nome']), 0, 1)) ?: 'G';
        $lum = 0.299 * $c[0] + 0.587 * $c[1] + 0.114 * $c[2];
        $cor = $lum > 150 ? imagecolorallocate($im, 20, 20, 20) : imagecolorallocate($im, 255, 255, 255);
        if (is_file($fonte) && function_exists('imagettftext')) {
            $t = (int)($tam * 0.52); $b = imagettfbbox($t, 0, $fonte, $letra);
            imagettftext($im, $t, 0, (int)(($tam - ($b[2] - $b[0])) / 2 - $b[0]), (int)(($tam - ($b[1] - $b[7])) / 2 - $b[7]), $cor, $fonte, $letra);
        }
    }
    $ok = imagepng($im, $arq);
    imagedestroy($im);
    return $ok ? $url : ($empresa['logo'] ?: null);
}

function rec_manifest($empresa) {
    $inicio = function_exists('wa_url_publica') ? parse_url(wa_url_publica($empresa), PHP_URL_PATH) : '/' . $empresa['slug'];
    $inicio = $inicio ?: '/';
    return [
        'name' => $empresa['nome'], 'short_name' => mb_strimwidth($empresa['nome'], 0, 12, ''),
        'start_url' => $inicio . (str_contains($inicio, '?') ? '&' : '?') . 'app=1', 'scope' => '/', 'display' => 'standalone',
        'background_color' => '#ffffff', 'theme_color' => $empresa['cor_primaria'] ?: '#222222', 'lang' => 'pt-BR',
        'icons' => [['src' => rec_icone($empresa, 192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                    ['src' => rec_icone($empresa, 512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any']],
    ];
}

// ---------- HTML do site ----------
function rec_head($empresa, $r) {
    if (!rec_ativo($r, 'app') || empty($empresa['id'])) return '';
    return '<link rel="manifest" href="/site/recursos.php?acao=manifest&amp;e=' . (int)$empresa['id'] . '"><link rel="apple-touch-icon" href="' . e(rec_icone($empresa, 192)) . '"><meta name="apple-mobile-web-app-capable" content="yes">';
}

function rec_stories($r) {
    if (!rec_ativo($r, 'stories')) return '';
    $h = '<div class="lp-stories" role="group" aria-label="Novidades">';
    foreach (array_slice($r['stories']['itens'], 0, 10) as $i => $s) {
        $h .= '<button type="button" class="lp-story" data-story="' . $i . '" aria-label="Ver novidade: ' . e($s['texto'] ?: 'foto ' . ($i + 1)) . '"><img src="' . e($s['img']) . '" alt="" loading="lazy" width="56" height="56"></button>';
    }
    $dados = array_map(fn($s) => ['img' => $s['img'], 'texto' => (string)($s['texto'] ?? '')], array_slice($r['stories']['itens'], 0, 10));
    return $h . '</div><script type="application/json" id="lpStoriesDados">' . json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
}

function rec_antes_depois($r) {
    if (!rec_ativo($r, 'antes_depois')) return '';
    $h = '<div class="lp-ad-grade">';
    foreach (array_slice($r['antes_depois']['itens'], 0, 6) as $i => $x) {
        $leg = trim((string)($x['legenda'] ?? ''));
        $h .= '<figure class="lp-ad"><div class="lp-ad-box" style="--pos:50%"><img src="' . e($x['depois']) . '" alt="Depois' . ($leg ? ': ' . e($leg) : '') . '" loading="lazy" width="800" height="1000">'
            . '<img class="lp-ad-antes" src="' . e($x['antes']) . '" alt="Antes' . ($leg ? ': ' . e($leg) : '') . '" loading="lazy" width="800" height="1000">'
            . '<span class="lp-ad-linha" aria-hidden="true"></span><span class="lp-ad-rot lp-ad-a" aria-hidden="true">Antes</span><span class="lp-ad-rot lp-ad-d" aria-hidden="true">Depois</span>'
            . '<input type="range" min="0" max="100" value="50" aria-label="Arraste para comparar o antes e o depois' . ($leg ? ' — ' . e($leg) : '') . '"></div>'
            . ($leg ? '<figcaption>' . e($leg) . '</figcaption>' : '') . '</figure>';
    }
    return $h . '</div>';
}

function rec_vantagens($empresa, $r, $modo) {
    $fid = rec_ativo($r, 'fidelidade'); $aniv = rec_ativo($r, 'aniversario');
    if ((!$fid && !$aniv) || empty($empresa['id'])) return '';
    $h = '<div class="lp-vant' . ($fid && $aniv ? ' duas' : '') . '">';
    if ($fid) {
        $meta = $r['fidelidade']['meta']; $premio = trim((string)$r['fidelidade']['premio']) ?: 'um presente da casa';
        $selos = '';
        for ($i = 1; $i <= $meta; $i++) $selos .= '<span class="lp-selo' . ($i === $meta ? ' premio' : '') . '" aria-hidden="true">' . ($i === $meta ? '★' : $i) . '</span>';
        $h .= '<div class="lp-fid" data-fid="' . (int)$empresa['id'] . '" data-meta="' . $meta . '"><h3>Cartão fidelidade</h3>'
            . '<p>A cada <strong>' . $meta . ' ' . e(fid_unidade($modo)) . '</strong>, você ganha <strong>' . e($premio) . '</strong>. Os selos entram sozinhos, sem cartão de papel.</p>'
            . '<div class="lp-selos">' . $selos . '</div>'
            . '<form class="lp-fid-form" novalidate><label for="fidTel">Confira seus selos</label><div class="lp-fid-linha"><input id="fidTel" name="tel" type="tel" inputmode="tel" autocomplete="tel" placeholder="Seu WhatsApp com DDD" required>'
            . '<button type="submit" class="lp-btn">Ver meus selos</button></div><p class="lp-fid-res" role="status" aria-live="polite"></p></form></div>';
    }
    if ($aniv) {
        $desc = $r['aniversario']['desconto'];
        $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        $h .= '<div class="lp-aniv" data-aniv="' . (int)$empresa['id'] . '"><h3>Presente de aniversário</h3><p>Cadastre o seu dia e ganhe <strong>' . $desc . '% de desconto</strong> no seu aniversário. O cupom chega no seu WhatsApp.</p>'
            . '<form class="lp-aniv-form" novalidate><label for="anNome">Seu nome</label><input id="anNome" name="nome" autocomplete="given-name" required>'
            . '<label for="anTel">WhatsApp com DDD</label><input id="anTel" name="tel" type="tel" inputmode="tel" autocomplete="tel" required>'
            . '<fieldset><legend>Dia do aniversário</legend><div class="lp-fid-linha"><label class="lp-sr" for="anDia">Dia</label><select id="anDia" name="dia" required><option value="">Dia</option>'
            . implode('', array_map(fn($d) => '<option>' . $d . '</option>', range(1, 31))) . '</select><label class="lp-sr" for="anMes">Mês</label><select id="anMes" name="mes" required><option value="">Mês</option>'
            . implode('', array_map(fn($i, $m) => '<option value="' . ($i + 1) . '">' . $m . '</option>', array_keys($meses), $meses)) . '</select></div></fieldset>'
            . '<input type="text" name="site_url" class="lp-sr" tabindex="-1" autocomplete="off" aria-hidden="true">'
            . '<button type="submit" class="lp-btn">Quero meu presente</button><p class="lp-aniv-res" role="status" aria-live="polite"></p></form></div>';
    }
    return $h . '</div>';
}

function rec_botao_app($r) {
    if (!rec_ativo($r, 'app')) return '';
    return '<button type="button" class="lp-app-b" id="lpApp" hidden>📲 Instalar o app</button><div class="lp-app-ios" id="lpAppIos" hidden role="dialog" aria-label="Como instalar o app"><p>No iPhone: toque em <strong>Compartilhar</strong> e depois em <strong>Adicionar à Tela de Início</strong>.</p><button type="button" data-fechar-ios>Entendi</button></div>';
}

function rec_video_html($r, $poster, $alt) {
    if (!rec_ativo($r, 'video')) return '';
    return '<video class="lp-video" src="' . e($r['video']['url']) . '"' . ($poster ? ' poster="' . e($poster) . '"' : '') . ' autoplay muted loop playsinline preload="metadata" aria-label="' . e($alt) . '"></video>';
}

// ---------- painel: salvar ----------
function rec_salvar_post($empresaId, $site) {
    $r = rec_ler($site);
    $acao = (string)($_POST['rec_acao'] ?? '');
    $lig = fn($k) => ($_POST[$k] ?? '') === '1';
    switch ($acao) {
        case 'config':
            $antesFid = !empty($r['fidelidade']['ativo']);
            $r['fidelidade']['ativo'] = $lig('fid_ativo');
            $r['fidelidade']['meta'] = max(3, min(30, (int)($_POST['fid_meta'] ?? 10)));
            $r['fidelidade']['premio'] = mb_substr(trim(strip_tags((string)($_POST['fid_premio'] ?? ''))), 0, 80);
            if ($r['fidelidade']['ativo'] && (!$antesFid || empty($r['fidelidade']['desde']))) $r['fidelidade']['desde'] = date('Y-m-d');
            $r['app']['ativo'] = $lig('app_ativo');
            $r['aniversario']['ativo'] = $lig('aniv_ativo');
            $r['aniversario']['desconto'] = max(5, min(50, (int)($_POST['aniv_desconto'] ?? 10)));
            $r['stories']['ativo'] = $lig('stories_ativo');
            $r['antes_depois']['ativo'] = $lig('ad_ativo');
            $r['video']['ativo'] = $lig('video_ativo');
            $r['reserva']['ativo'] = $lig('reserva_ativo');
            $r['fila']['ativo'] = $lig('fila_ativo');
            $r['garcom']['ativo'] = $lig('garcom_ativo');
            foreach (['grade', 'acompanhar', 'quiz', 'calculadora', 'tour360', 'treino', 'vale', 'monte'] as $k) $r[$k]['ativo'] = $lig($k . '_ativo');
            $r['vale']['validade'] = in_array((int)($_POST['vale_validade'] ?? 180), [90, 180, 365], true) ? (int)$_POST['vale_validade'] : 180;
            rec_gravar($empresaId, $r);
            return [true, 'Recursos do site salvos.'];
        case 'add_story':
            if (count($r['stories']['itens']) >= 10) return [false, 'Já são 10 novidades. Apague uma para pôr outra.'];
            $url = rec_upload_imagem('story_img', $empresaId, 'story');
            if (!$url) return [false, 'Escolha uma foto (JPG, PNG ou WebP, até 8 MB).'];
            array_unshift($r['stories']['itens'], ['img' => $url, 'texto' => mb_substr(trim(strip_tags((string)($_POST['story_texto'] ?? ''))), 0, 120), 'em' => date('Y-m-d')]);
            $r['stories']['ativo'] = true;
            rec_gravar($empresaId, $r);
            return [true, 'Novidade publicada no topo do site.'];
        case 'rm_story':
            array_splice($r['stories']['itens'], (int)($_POST['i'] ?? -1), 1);
            rec_gravar($empresaId, $r);
            return [true, 'Novidade apagada.'];
        case 'add_ad':
            if (count($r['antes_depois']['itens']) >= 6) return [false, 'Já são 6 comparações. Apague uma para pôr outra.'];
            $a = rec_upload_imagem('ad_antes', $empresaId, 'antes'); $d = rec_upload_imagem('ad_depois', $empresaId, 'depois');
            if (!$a || !$d) return [false, 'Escolha as duas fotos: a de antes e a de depois.'];
            $r['antes_depois']['itens'][] = ['antes' => $a, 'depois' => $d, 'legenda' => mb_substr(trim(strip_tags((string)($_POST['ad_legenda'] ?? ''))), 0, 80)];
            $r['antes_depois']['ativo'] = true;
            rec_gravar($empresaId, $r);
            return [true, 'Antes e depois adicionado ao site.'];
        case 'rm_ad':
            array_splice($r['antes_depois']['itens'], (int)($_POST['i'] ?? -1), 1);
            rec_gravar($empresaId, $r);
            return [true, 'Comparação apagada.'];
        case 'video':
            $f = $_FILES['video'] ?? null;
            if (!$f || ($f['error'] ?? 4) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) return [false, 'Escolha um vídeo (MP4, até 25 MB).'];
            if ((int)$f['size'] > REC_MAX_VIDEO) return [false, 'O vídeo passou de 25 MB. Use um vídeo mais curto (10 a 20 segundos).'];
            $tipo = function_exists('mime_content_type') ? (string)@mime_content_type($f['tmp_name']) : '';
            $ext = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mp4'][$tipo] ?? null;
            if (!$ext) return [false, 'Formato de vídeo não aceito. Use MP4.'];
            $pasta = function_exists('img_ia_pasta') ? img_ia_pasta('empresas/' . (int)$empresaId) : null;
            if (!$pasta) return [false, 'A pasta de arquivos não tem permissão de escrita.'];
            $nome = 'video-' . bin2hex(random_bytes(5)) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], "$pasta/$nome")) return [false, 'Não consegui salvar o vídeo.'];
            rec_apagar_arquivo($r['video']['url'] ?? '', $empresaId);
            $r['video'] = ['ativo' => true, 'url' => '/uploads/empresas/' . (int)$empresaId . '/' . $nome];
            rec_gravar($empresaId, $r);
            return [true, 'Vídeo no topo do site.'];
        case 'rm_video':
            rec_apagar_arquivo($r['video']['url'] ?? '', $empresaId);
            $r['video'] = ['ativo' => false, 'url' => ''];
            rec_gravar($empresaId, $r);
            return [true, 'Vídeo removido. O topo volta a mostrar a foto.'];
        case 'add_tour':
            if (count($r['tour360']['itens']) >= 8) return [false, 'Já são 8 ambientes. Apague um para pôr outro.'];
            if (!function_exists('tour_upload')) require_once __DIR__ . '/recursos_servicos.php';
            [$url, $erro] = tour_upload($empresaId);
            if (!$url) return [false, $erro];
            $r['tour360']['itens'][] = ['img' => $url, 'titulo' => mb_substr(trim(strip_tags((string)($_POST['tour_titulo'] ?? ''))), 0, 40)];
            $r['tour360']['ativo'] = true;
            rec_gravar($empresaId, $r);
            return [true, 'Ambiente 360° adicionado ao site.'];
        case 'rm_tour':
            $i = (int)($_POST['i'] ?? -1);
            if (isset($r['tour360']['itens'][$i])) { rec_apagar_arquivo($r['tour360']['itens'][$i]['img'], $empresaId); array_splice($r['tour360']['itens'], $i, 1); }
            rec_gravar($empresaId, $r);
            return [true, 'Ambiente apagado.'];
        case 'fid_dar':
            $n = max(-30, min(30, (int)($_POST['selos'] ?? 1)));
            return fid_dar($empresaId, $_POST['tel'] ?? '', $n, 'manual') ? [true, ($n > 0 ? "+$n selo(s)" : "$n selo(s)") . ' para ' . rec_tel($_POST['tel'] ?? '') . '.'] : [false, 'Informe o WhatsApp do cliente com DDD.'];
        case 'fid_resgatar':
            $meta = $r['fidelidade']['meta'];
            if (fid_selos($empresaId, $_POST['tel'] ?? '', $r['fidelidade']) < $meta) return [false, 'Este cliente ainda não completou o cartão.'];
            fid_dar($empresaId, $_POST['tel'] ?? '', -$meta, 'resgate');
            return [true, 'Prêmio entregue! O cartão do cliente recomeçou.'];
    }
    return [false, 'Ação desconhecida.'];
}

function rec_upload_imagem($campo, $empresaId, $prefixo) {
    $f = $_FILES[$campo] ?? null;
    if (!$f || ($f['error'] ?? 4) !== UPLOAD_ERR_OK) return null;
    if (!function_exists('orc_gravar_imagem')) require_once __DIR__ . '/orcamento.php';
    return orc_gravar_imagem($f['tmp_name'], (int)$f['size'], 'empresas/' . (int)$empresaId, $prefixo . '-' . bin2hex(random_bytes(5)));
}

function rec_apagar_arquivo($url, $empresaId) {
    $base = '/uploads/empresas/' . (int)$empresaId . '/';
    if (is_string($url) && str_starts_with($url, $base) && !str_contains($url, '..')) @unlink(__DIR__ . '/..' . $url);
}

// ---------- painel: formulário (empresa → Recursos do site e admin → Site) ----------
function rec_form_html($empresa, $site, $modo) {
    $r = rec_ler($site);
    $chave = function ($campo, $ligado, $rotulo) {
        return '<div class="rec-chave"><span>' . e($rotulo) . '</span><input type="hidden" name="' . $campo . '" value="' . ($ligado ? '1' : '0') . '">'
            . '<div class="ed-presets" data-preset-alvo="' . $campo . '" style="display:flex;gap:6px"><button type="button" class="btn btn-sm ' . ($ligado ? 'btn-ligado' : 'btn-ghost') . '" data-preset="1">Ligado</button>'
            . '<button type="button" class="btn btn-sm ' . (!$ligado ? 'btn-ligado' : 'btn-ghost') . '" data-preset="0">Desligado</button></div></div>';
    };
    $presets = function ($campo, $valores, $atual, $sufixo = '') {
        $h = '<input type="hidden" name="' . $campo . '" value="' . e($atual) . '"><div class="ed-presets" data-preset-alvo="' . $campo . '" style="display:flex;flex-wrap:wrap;gap:6px">';
        foreach ($valores as $v) $h .= '<button type="button" class="btn btn-sm ' . ((string)$v === (string)$atual ? 'btn-ligado' : 'btn-ghost') . '" data-preset="' . e($v) . '">' . e($v . $sufixo) . '</button>';
        return $h . '</div>';
    };
    $csrf = function_exists('csrf_campo') ? csrf_campo() : '';
    $un = fid_unidade($modo);
    $h = '<style>.rec-grade{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,340px),1fr));gap:16px}.rec-card h3{margin:0 0 6px}.rec-card .dica{margin-top:0}'
       . '.rec-chave{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin:8px 0}.rec-miniaturas{display:flex;flex-wrap:wrap;gap:10px;margin:10px 0}'
       . '.rec-miniaturas figure{margin:0;position:relative;width:96px}.rec-miniaturas img{width:96px;height:96px;object-fit:cover;border-radius:10px;display:block}.rec-miniaturas figcaption{font-size:12px;overflow-wrap:anywhere}'
       . '.rec-miniaturas form{position:absolute;top:4px;right:4px}.rec-miniaturas button{width:28px;height:28px;border-radius:50%;padding:0;line-height:1}.rec-rank{list-style:none;padding:0;margin:8px 0;display:grid;gap:6px}'
       . '.rec-rank li{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;border:1px solid var(--borda,#e3e6ea);border-radius:10px;padding:8px 10px}</style>';
    // configuração geral (um formulário com todas as chaves)
    $h .= '<form method="post" class="form-box largo">' . $csrf . '<input type="hidden" name="rec_acao" value="config"><h3>O que aparece no site</h3><p class="dica">Ligue só o que faz sentido para o negócio. Cada recurso aparece no site assim que tiver conteúdo.</p><div class="rec-grade">'
        . '<div class="rec-card"><h3>⭐ Cartão fidelidade</h3><p class="dica">A cada N ' . e($un) . ' concluídos, o cliente ganha um prêmio. Os selos entram sozinhos (a partir do dia em que você ligar).</p>'
        . $chave('fid_ativo', !empty($r['fidelidade']['ativo']), 'Cartão fidelidade')
        . '<label>Quantos ' . e($un) . ' para ganhar</label>' . $presets('fid_meta', [5, 6, 8, 10, 12, 15], $r['fidelidade']['meta'])
        . '<label for="fidPremio">Prêmio</label><input id="fidPremio" name="fid_premio" maxlength="80" value="' . e($r['fidelidade']['premio']) . '" placeholder="Ex.: 1 corte grátis"></div>'
        . '<div class="rec-card"><h3>🎂 Cupom de aniversário</h3><p class="dica">O cliente cadastra o dia no site. No aniversário, recebe um cupom só dele no WhatsApp (válido por 15 dias).</p>'
        . $chave('aniv_ativo', !empty($r['aniversario']['ativo']), 'Cupom de aniversário') . '<label>Desconto</label>' . $presets('aniv_desconto', [5, 10, 15, 20, 25, 30], $r['aniversario']['desconto'], '%') . '</div>'
        . '<div class="rec-card"><h3>📲 App no celular</h3><p class="dica">Seu cliente instala o site como um aplicativo, com o seu ícone na tela do celular. Sem loja de apps.</p>' . $chave('app_ativo', !empty($r['app']['ativo']), 'Botão "Instalar o app"') . '</div>'
        . '<div class="rec-card"><h3>🟣 Novidades (stories)</h3><p class="dica">Bolinhas no topo do site com fotos de novidades e promoções, como no Instagram.</p>' . $chave('stories_ativo', !empty($r['stories']['ativo']), 'Novidades no topo') . '</div>'
        . '<div class="rec-card"><h3>↔️ Antes e depois</h3><p class="dica">Fotos com uma barra de arrastar para comparar o resultado.</p>' . $chave('ad_ativo', !empty($r['antes_depois']['ativo']), 'Antes e depois') . '</div>'
        . '<div class="rec-card"><h3>🎬 Vídeo no topo</h3><p class="dica">Um vídeo curto do espaço no lugar da foto principal (sem som, em repetição).</p>' . $chave('video_ativo', !empty($r['video']['ativo']), 'Vídeo no topo') . '</div>'
        . '</div><h3 style="margin-top:22px">🍽 Restaurante, bar e café</h3><p class="dica">A equipe acompanha tudo ao vivo em <a class="link" href="/empresa/restaurante.php">Reservas, fila e mesas</a> (painel da empresa).</p><div class="rec-grade">'
        . '<div class="rec-card"><h3>📅 Reserva de mesa</h3><p class="dica">O cliente pede dia, hora e quantas pessoas. Você confirma no painel e ele recebe no WhatsApp.</p>' . $chave('reserva_ativo', !empty($r['reserva']['ativo']), 'Reserva de mesa') . '</div>'
        . '<div class="rec-card"><h3>⏳ Fila de espera</h3><p class="dica">Casa cheia? O cliente entra na fila pelo celular, vê a posição ao vivo e é avisado no WhatsApp quando a mesa fica pronta.</p>' . $chave('fila_ativo', !empty($r['fila']['ativo']), 'Fila de espera') . '</div>'
        . '<div class="rec-card"><h3>🍔 Monte o seu</h3><p class="dica">Nos itens com 2 ou mais grupos de opções (pão, carne, molho…), o cliente monta um passo por vez e vê a montagem em camadas.</p>' . $chave('monte_ativo', !empty($r['monte']['ativo']), 'Monte o seu') . '</div>'
        . '<div class="rec-card"><h3>🙋 Chamar garçom</h3><p class="dica">No cardápio aberto pelo QR Code da mesa, botões "Chamar garçom" e "Pedir a conta". O chamado aparece na hora, com som.</p>' . $chave('garcom_ativo', !empty($r['garcom']['ativo']), 'Chamar garçom e pedir a conta') . '</div>'
        . '</div><h3 style="margin-top:22px">🧭 Serviços, aulas e atendimento</h3><div class="rec-grade">'
        . '<div class="rec-card"><h3>🗓 Grade de aulas</h3><p class="dica">Mostra as turmas da semana com as vagas que sobram (serviços com mais de 1 vaga).</p>' . $chave('grade_ativo', !empty($r['grade']['ativo']), 'Grade de aulas') . '</div>'
        . '<div class="rec-card"><h3>📍 Acompanhe o atendimento</h3><p class="dica">A equipe marca as etapas (ex.: chegou, no banho, na tosa, pronto) e manda foto. O cliente acompanha pelo link e é avisado quando fica pronto. Botão "Acompanhar" na Agenda.</p>' . $chave('acompanhar_ativo', !empty($r['acompanhar']['ativo']), 'Acompanhe o atendimento') . '</div>'
        . '<div class="rec-card"><h3>❓ Quiz "qual serviço é para você"</h3><p class="dica">3 perguntas rápidas, montadas sozinhas com os seus serviços, que terminam no agendamento.</p>' . $chave('quiz_ativo', !empty($r['quiz']['ativo']), 'Quiz') . '</div>'
        . '<div class="rec-card"><h3>🧮 Calculadora de orçamento</h3><p class="dica">O cliente marca os serviços e a quantidade e vê uma estimativa na hora, antes de pedir o orçamento.</p>' . $chave('calculadora_ativo', !empty($r['calculadora']['ativo']), 'Calculadora') . '</div>'
        . '<div class="rec-card"><h3>🏋️ Treino da semana</h3><p class="dica">Para academia e personal: monte a ficha de cada aluno por dia; ele abre no celular, marca os exercícios e faz o check-in. Menu "Treinos dos alunos".</p>' . $chave('treino_ativo', !empty($r['treino']['ativo']), 'Treino da semana') . '</div>'
        . '<div class="rec-card"><h3>🎁 Vale-presente</h3><p class="dica">O cliente compra pelo site (Pix) e quem ganha recebe o cartão-presente no WhatsApp. No balcão, você abate o valor. Menu "Vales-presente".</p>' . $chave('vale_ativo', !empty($r['vale']['ativo']), 'Vale-presente') . '<label>Validade</label>' . $presets('vale_validade', [90, 180, 365], $r['vale']['validade'], ' dias') . '</div>'
        . '<div class="rec-card"><h3>🌐 Tour 360°</h3><p class="dica">Fotos panorâmicas do espaço que o cliente gira com o dedo. Tire com o modo "Panorama" do celular.</p>' . $chave('tour360_ativo', !empty($r['tour360']['ativo']), 'Tour 360°') . '</div>'
        . '</div><button type="submit">Salvar</button></form>';
    // tour 360
    $mini = '';
    foreach ($r['tour360']['itens'] as $i => $t) $mini .= '<figure><img src="' . e($t['img']) . '" alt=""><figcaption>' . e($t['titulo'] ?: 'Ambiente ' . ($i + 1)) . '</figcaption><form method="post">' . $csrf . '<input type="hidden" name="rec_acao" value="rm_tour"><input type="hidden" name="i" value="' . $i . '"><button type="submit" class="btn btn-sm" aria-label="Apagar ambiente">×</button></form></figure>';
    $h .= '<form method="post" enctype="multipart/form-data" class="form-box largo" id="rec-tour">' . $csrf . '<input type="hidden" name="rec_acao" value="add_tour"><h3>🌐 Tour 360°</h3>'
        . ($mini ? '<div class="rec-miniaturas">' . $mini . '</div>' : '<p class="dica">Nenhum ambiente ainda. No celular, use o modo "Panorama" e gire devagar; a foto sai bem larga.</p>')
        . '<div class="form-row"><div><label for="tourImg">Foto panorâmica</label><input id="tourImg" type="file" name="tour_img" accept="image/jpeg,image/png,image/webp" required></div>'
        . '<div><label for="tourTit">Nome do ambiente</label><input id="tourTit" name="tour_titulo" maxlength="40" placeholder="Ex.: Recepção"></div></div><button type="submit">Adicionar ambiente</button></form>';
    // novidades
    $mini = '';
    foreach ($r['stories']['itens'] as $i => $s) $mini .= '<figure><img src="' . e($s['img']) . '" alt=""><figcaption>' . e($s['texto']) . '</figcaption><form method="post">' . $csrf . '<input type="hidden" name="rec_acao" value="rm_story"><input type="hidden" name="i" value="' . $i . '"><button type="submit" class="btn btn-sm" aria-label="Apagar novidade">×</button></form></figure>';
    $h .= '<form method="post" enctype="multipart/form-data" class="form-box largo" id="rec-stories">' . $csrf . '<input type="hidden" name="rec_acao" value="add_story"><h3>🟣 Novidades no topo</h3>'
        . ($mini ? '<div class="rec-miniaturas">' . $mini . '</div>' : '<p class="dica">Nenhuma novidade ainda.</p>')
        . '<div class="form-row"><div><label for="storyImg">Foto (de pé fica melhor)</label><input id="storyImg" type="file" name="story_img" accept="image/jpeg,image/png,image/webp" required></div>'
        . '<div><label for="storyTxt">Texto curto (opcional)</label><input id="storyTxt" name="story_texto" maxlength="120" placeholder="Ex.: Promoção de terça: escova + hidratação"></div></div><button type="submit">Publicar novidade</button></form>';
    // antes e depois
    $mini = '';
    foreach ($r['antes_depois']['itens'] as $i => $x) $mini .= '<figure><img src="' . e($x['depois']) . '" alt=""><figcaption>' . e($x['legenda'] ?: 'Comparação ' . ($i + 1)) . '</figcaption><form method="post">' . $csrf . '<input type="hidden" name="rec_acao" value="rm_ad"><input type="hidden" name="i" value="' . $i . '"><button type="submit" class="btn btn-sm" aria-label="Apagar comparação">×</button></form></figure>';
    $h .= '<form method="post" enctype="multipart/form-data" class="form-box largo" id="rec-ad">' . $csrf . '<input type="hidden" name="rec_acao" value="add_ad"><h3>↔️ Antes e depois</h3>'
        . ($mini ? '<div class="rec-miniaturas">' . $mini . '</div>' : '<p class="dica">Nenhuma comparação ainda. Use fotos do mesmo ângulo e da mesma luz.</p>')
        . '<div class="form-row"><div><label for="adA">Foto de antes</label><input id="adA" type="file" name="ad_antes" accept="image/jpeg,image/png,image/webp" required></div><div><label for="adD">Foto de depois</label><input id="adD" type="file" name="ad_depois" accept="image/jpeg,image/png,image/webp" required></div></div>'
        . '<label for="adL">Legenda (opcional)</label><input id="adL" name="ad_legenda" maxlength="80" placeholder="Ex.: Progressiva em cabelo cacheado"><button type="submit">Adicionar</button></form>';
    // vídeo
    $h .= '<div class="form-box largo" id="rec-video"><h3>🎬 Vídeo no topo</h3>'
        . (!empty($r['video']['url']) ? '<video src="' . e($r['video']['url']) . '" muted loop playsinline controls style="max-width:320px;width:100%;border-radius:12px"></video><form method="post">' . $csrf . '<input type="hidden" name="rec_acao" value="rm_video"><button type="submit" class="btn btn-ghost btn-sm">Remover vídeo</button></form>' : '<p class="dica">Grave 10 a 20 segundos do espaço ou do trabalho, com o celular deitado. Até 25 MB.</p>')
        . '<form method="post" enctype="multipart/form-data">' . $csrf . '<input type="hidden" name="rec_acao" value="video"><label for="recVid">Vídeo (MP4)</label><input id="recVid" type="file" name="video" accept="video/mp4,video/webm,video/quicktime" required><button type="submit">Enviar vídeo</button></form></div>';
    // fidelidade: dar selo e prêmios
    if (!empty($r['fidelidade']['ativo']) && !empty($empresa['id'])) {
        $meta = $r['fidelidade']['meta'];
        $rank = fid_ranking($empresa['id'], $r['fidelidade']);
        $lis = '';
        foreach ($rank as $c) {
            $ok = (int)$c['selos'] >= $meta;
            $lis .= '<li><span><strong>' . e($c['nome'] ?: 'Cliente') . '</strong> · ' . e($c['tel']) . ' — ' . (int)$c['selos'] . ' de ' . $meta . ' selos</span>'
                  . ($ok ? '<form method="post" style="margin:0">' . $csrf . '<input type="hidden" name="rec_acao" value="fid_resgatar"><input type="hidden" name="tel" value="' . e($c['tel']) . '"><button type="submit" class="btn btn-sm">🎁 Entregar prêmio</button></form>' : '') . '</li>';
        }
        $h .= '<div class="form-box largo" id="rec-fid"><h3>⭐ Cartão fidelidade dos clientes</h3><p class="dica">Contando desde ' . e(date('d/m/Y', strtotime($r['fidelidade']['desde'] ?: 'today'))) . '. Atendimento feito fora do site? Dê o selo aqui.</p>'
            . '<form method="post" class="form-row">' . $csrf . '<input type="hidden" name="rec_acao" value="fid_dar"><input type="hidden" name="selos" value="1"><div><label for="fidTelP">WhatsApp do cliente</label><input id="fidTelP" name="tel" type="tel" inputmode="tel" required placeholder="(11) 98888-7777"></div><div style="align-self:end"><button type="submit">+1 selo</button></div></form>'
            . ($lis ? '<ul class="rec-rank">' . $lis . '</ul>' : '<p class="dica">Nenhum cliente com selo ainda.</p>') . '</div>';
    }
    return $h;
}
