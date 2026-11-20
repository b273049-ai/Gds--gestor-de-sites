<?php
// ============================================================
// RECURSOS PARA QUEM VENDE SERVIÇO (ligados em Recursos do site):
//  • Grade de aulas: as turmas (serviços com vagas) da semana, com as vagas que sobram de verdade.
//  • Acompanhe o atendimento: a equipe marca as etapas (chegou → em atendimento → finalizando →
//    pronto) e manda foto; o cliente acompanha no link do agendamento e recebe "pronto" no WhatsApp.
//    Ex.: pet shop (banho e tosa), estética automotiva, oficina, salão.
//  • Quiz "qual serviço é para você": 3 perguntas montadas sozinhas com os serviços (categoria, tempo
//    e preço) e o resultado já leva para o agendamento.
//  • Calculadora de orçamento: o cliente marca os serviços e a quantidade e vê uma estimativa.
//  • Tour 360°: fotos panorâmicas do espaço (tiradas com o celular), abertas num visualizador.
// Tudo de graça: PHP, CSS e JS próprios (o tour usa o Pannellum, livre, carregado só ao abrir).
// ============================================================

require_once __DIR__ . '/site_nichos.php';

const TOUR_MAX_BYTES = 15 * 1024 * 1024;

// ---------- acompanhe o atendimento ----------
function acomp_etapas($nicho) {
    return match ($nicho) {
        'pet' => ['Chegou', 'No banho', 'Na tosa e secagem', 'Pronto para buscar'],
        'auto' => ['Recebido', 'Em serviço', 'Finalizando', 'Pronto para retirar'],
        'beleza', 'barbearia' => ['Chegou', 'Em atendimento', 'Finalizando', 'Pronto'],
        default => ['Recebido', 'Em andamento', 'Finalizando', 'Pronto'],
    };
}

function acomp_avancar($empresa, $agId, $etapa, $foto = null) {
    global $pdo;
    $etapas = acomp_etapas(site_nicho_da_empresa($empresa));
    $etapa = max(0, min(count($etapas), (int)$etapa));
    $st = $pdo->prepare("SELECT a.id, a.token, a.etapa, a.status, c.nome, c.telefone, s.nome AS servico FROM agendamentos a LEFT JOIN clientes c ON c.id=a.cliente_id LEFT JOIN servicos s ON s.id=a.servico_id WHERE a.id=? AND a.empresa_id=?");
    $st->execute([(int)$agId, (int)$empresa['id']]);
    $a = $st->fetch();
    if (!$a) return [false, 'Agendamento não encontrado.'];
    $pdo->prepare("UPDATE agendamentos SET etapa=?, etapa_em=NOW()" . ($foto ? ", etapa_foto=?" : '') . " WHERE id=?")->execute($foto ? [$etapa, $foto, $a['id']] : [$etapa, $a['id']]);
    $msg = 'Etapa atualizada: ' . ($etapas[$etapa - 1] ?? 'início') . '.';
    if ($a['telefone'] && function_exists('wa_enfileirar')) {
        if (function_exists('wa_adiar')) wa_adiar();
        $link = function_exists('wa_url_publica') ? wa_url_publica($empresa, '/site/agendamento.php?t=' . $a['token']) : '';
        $nome = explode(' ', trim((string)$a['nome']))[0] ?: 'Olá';
        if ($etapa === 1 && (int)$a['etapa'] === 0) { wa_enfileirar($empresa, $a['telefone'], "{$nome}, recebemos" . ($a['servico'] ? " para {$a['servico']}" : '') . " aqui na *{$empresa['nome']}*! Acompanhe cada etapa por este link: {$link}", 'acomp_inicio'); $msg .= ' O cliente recebeu o link para acompanhar.'; }
        elseif ($etapa === count($etapas)) { wa_enfileirar($empresa, $a['telefone'], "✅ {$nome}, {$etapas[$etapa - 1]}! " . ($foto ? 'Tem foto nova no link: ' : 'Detalhes: ') . $link, 'acomp_pronto'); $msg .= ' O cliente foi avisado no WhatsApp.'; }
        elseif ($foto) wa_enfileirar($empresa, $a['telefone'], "📸 Foto nova do atendimento na *{$empresa['nome']}*: {$link}", 'acomp_foto');
    }
    return [true, $msg];
}

function acomp_html_cliente($ag, $nicho) {
    $etapas = acomp_etapas($nicho);
    $atual = (int)($ag['etapa'] ?? 0);
    if ($atual <= 0) return '';
    $h = '<div class="gds-acomp"><h4 style="margin:18px 0 10px">Acompanhe o atendimento</h4><ol class="gds-acomp-etapas">';
    foreach ($etapas as $i => $nome) {
        $cl = $i + 1 < $atual ? 'feito' : ($i + 1 === $atual ? 'atual' : '');
        $h .= '<li class="' . $cl . '"' . ($i + 1 === $atual ? ' aria-current="step"' : '') . '><span aria-hidden="true">' . ($i + 1 < $atual || ($i + 1 === $atual && $atual === count($etapas)) ? '✓' : $i + 1) . '</span>' . e($nome) . '</li>';
    }
    $h .= '</ol>';
    if (!empty($ag['etapa_em'])) $h .= '<p class="gds-mute" style="font-size:.85rem">Atualizado às ' . date('H:i', strtotime($ag['etapa_em'])) . '</p>';
    if (!empty($ag['etapa_foto'])) $h .= '<img src="' . e($ag['etapa_foto']) . '" alt="Foto do atendimento" loading="lazy" style="width:100%;border-radius:14px;margin-top:8px">';
    $h .= '<style>.gds-acomp-etapas{list-style:none;padding:0;margin:0;display:grid;gap:8px}.gds-acomp-etapas li{display:flex;align-items:center;gap:10px;color:#6b6b6b}'
        . '.gds-acomp-etapas li span{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;border:2px solid #d0d0d0;font-weight:700;font-size:.85rem;flex:none}'
        . '.gds-acomp-etapas li.feito,.gds-acomp-etapas li.atual{color:#1b1b1b;font-weight:600}.gds-acomp-etapas li.feito span{background:#1f9d55;border-color:#1f9d55;color:#fff}'
        . '.gds-acomp-etapas li.atual span{border-color:#1f9d55;color:#1f9d55}</style>';
    return $h . ((int)$atual < count($etapas) ? '<script>setTimeout(function(){location.reload()},30000)</script>' : '') . '</div>';
}

// ---------- grade de aulas ----------
function grade_html($empresa, $servicos) {
    if (!function_exists('agenda_horarios_do_dia')) return '';
    $turmas = array_values(array_filter((array)$servicos, fn($s) => (int)($s['vagas'] ?? 1) > 1));
    if (!$turmas) return '';
    $cfg = agenda_config($empresa);
    $dias = []; $semana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    for ($i = 0; $i < 7; $i++) {
        $ts = strtotime("+$i day"); $data = date('Y-m-d', $ts);
        $slots = [];
        foreach ($turmas as $t) {
            try { $h = agenda_horarios_do_dia($empresa, $data, max(15, (int)($t['duracao_minutos'] ?? 60)), null, $t); } catch (Throwable $e) { continue; }
            if (!empty($h['fechado'])) continue;
            foreach ((array)($h['livres'] ?? []) as $hora) {
                if ($i === 0 && strtotime("$data $hora") < time()) continue;
                $slots[] = ['hora' => $hora, 'id' => (int)$t['id'], 'nome' => $t['nome'], 'vagas' => (int)($h['vagas'][$hora] ?? $t['vagas'])];
            }
        }
        usort($slots, fn($a, $b) => strcmp($a['hora'], $b['hora']));
        $dias[] = ['rot' => $i === 0 ? 'Hoje' : ($i === 1 ? 'Amanhã' : $semana[(int)date('w', $ts)] . ' ' . date('d', $ts)), 'slots' => array_slice($slots, 0, 14)];
    }
    if (!array_filter($dias, fn($d) => $d['slots'])) return '';
    $h = '<div class="lp-grade-aulas" data-grade><div class="lp-grade-dias" role="tablist" aria-label="Dias da semana">';
    foreach ($dias as $i => $d) $h .= '<button type="button" role="tab" id="gdT' . $i . '" aria-controls="gdP' . $i . '" aria-selected="' . ($i === 0 ? 'true' : 'false') . '"' . ($i ? ' tabindex="-1"' : '') . '>' . e($d['rot']) . '</button>';
    $h .= '</div>';
    foreach ($dias as $i => $d) {
        $h .= '<div class="lp-grade-painel" role="tabpanel" id="gdP' . $i . '" aria-labelledby="gdT' . $i . '"' . ($i ? ' hidden' : '') . '>';
        if (!$d['slots']) $h .= '<p class="lp-grade-vazio">Sem aulas com vaga neste dia.</p>';
        else { $h .= '<ul>'; foreach ($d['slots'] as $s) $h .= '<li><span class="lp-grade-hora">' . e($s['hora']) . '</span><span class="lp-grade-nome">' . e($s['nome']) . '<small>' . ($s['vagas'] <= 3 ? '🔥 ' : '') . $s['vagas'] . ($s['vagas'] === 1 ? ' vaga' : ' vagas') . '</small></span><a class="lp-btn lp-btn-2" href="#gds-agendamento" data-gds-agendar-servico="' . $s['id'] . '">Reservar</a></li>'; $h .= '</ul>'; }
        $h .= '</div>';
    }
    return $h . '</div>';
}

// ---------- quiz ----------
function quiz_dados($servicos) {
    $sv = array_values(array_filter((array)$servicos, fn($s) => !empty($s['nome']) && empty($s['esgotado'])));
    if (count($sv) < 3) return null;
    return array_map(fn($s) => ['id' => (int)$s['id'], 'nome' => $s['nome'], 'cat' => trim((string)($s['categoria'] ?? '')), 'min' => (int)($s['duracao_minutos'] ?? 0), 'preco' => (float)($s['preco'] ?? 0),
        'desc' => mb_strimwidth(trim((string)($s['descricao'] ?? '')), 0, 110, '…')], $sv);
}

function quiz_html($servicos, $modo) {
    $d = quiz_dados($servicos);
    if (!$d) return '';
    $acao = $modo === 'orcamento' ? 'orcar' : ($modo === 'agendamento' ? 'agendar' : 'ver');
    return '<div class="lp-quiz" data-quiz data-acao="' . $acao . '"><div class="lp-quiz-passo" aria-live="polite"></div>'
        . '<script type="application/json" class="lp-quiz-dados">' . json_encode($d, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>'
        . '<noscript><p>Veja todos os serviços na lista acima.</p></noscript></div>';
}

// ---------- calculadora ----------
function calc_html($servicos) {
    $sv = array_values(array_filter((array)$servicos, fn($s) => (float)($s['preco'] ?? 0) > 0 && empty($s['esgotado'])));
    if (count($sv) < 2) return '';
    $h = '<form class="lp-calc" data-calc novalidate><ul>';
    foreach (array_slice($sv, 0, 20) as $s) {
        $id = (int)$s['id'];
        $h .= '<li><label class="lp-calc-item"><input type="checkbox" name="s' . $id . '" value="' . number_format((float)$s['preco'], 2, '.', '') . '" data-nome="' . e($s['nome']) . '"><span>' . e($s['nome'])
            . '<small>' . (pg_bool($s['preco_a_partir'] ?? false) ? 'a partir de ' : '') . 'R$ ' . number_format((float)$s['preco'], 2, ',', '.') . '</small></span></label>'
            . '<label class="lp-calc-qtd"><span class="lp-sr">Quantidade de ' . e($s['nome']) . '</span><input type="number" min="1" max="999" value="1" inputmode="numeric" data-qtd="s' . $id . '"></label></li>';
    }
    return $h . '</ul><div class="lp-calc-total" aria-live="polite"><span>Estimativa</span><strong>R$ 0,00</strong></div><p class="lp-calc-nota">Valor aproximado. O orçamento final confirma tudo, sem compromisso.</p>'
        . '<a class="lp-btn" href="#agendar" data-calc-pedir>Pedir orçamento com estes itens</a></form>';
}

// ---------- tour 360 ----------
function tour_html($r) {
    if (empty($r['tour360']['ativo']) || empty($r['tour360']['itens'])) return '';
    $h = '<div class="lp-tour" data-tour><div class="lp-tour-tela" id="lpTour360" role="region" aria-label="Visualizador 360°"></div><div class="lp-tour-lista" role="group" aria-label="Ambientes">';
    foreach (array_slice($r['tour360']['itens'], 0, 8) as $i => $t)
        $h .= '<button type="button" data-tour-img="' . e($t['img']) . '"' . ($i === 0 ? ' aria-pressed="true"' : ' aria-pressed="false"') . '><img src="' . e($t['img']) . '" alt="" loading="lazy" width="120" height="60">' . e($t['titulo'] ?: 'Ambiente ' . ($i + 1)) . '</button>';
    return $h . '</div><p class="lp-tour-dica">Arraste para olhar em volta.</p></div>';
}

function tour_upload($empresaId) {
    $f = $_FILES['tour_img'] ?? null;
    if (!$f || ($f['error'] ?? 4) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) return [null, 'Escolha a foto panorâmica (JPG).'];
    return tour_gravar($f['tmp_name'], (int)$f['size'], $empresaId);
}

// Confere e grava uma foto panorâmica enviada (usada no tour do site e no tour de cada imóvel)
function tour_gravar($tmp, $tamanho, $empresaId) {
    if (!is_uploaded_file($tmp)) return [null, 'Envie a foto de novo.'];
    if ($tamanho > TOUR_MAX_BYTES) return [null, 'A foto passou de 15 MB.'];
    $f = ['tmp_name' => $tmp];
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return [null, 'Use uma foto JPG, PNG ou WebP.'];
    if ($info[0] < $info[1] * 1.7) return [null, 'Essa foto não é panorâmica. Use o modo "Panorama" ou "Foto 360°" da câmera (a foto fica bem larga).'];
    $pasta = function_exists('img_ia_pasta') ? img_ia_pasta('empresas/' . (int)$empresaId) : null;
    if (!$pasta) return [null, 'A pasta de arquivos não tem permissão de escrita.'];
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]];
    $nome = 'tour-' . bin2hex(random_bytes(5)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$pasta/$nome")) return [null, 'Não consegui salvar a foto.'];
    return ['/uploads/empresas/' . (int)$empresaId . '/' . $nome, ''];
}

// ------------------------------------------------------------
// PRODUTO EM 3D (migração 45): arquivo .glb do item, visto no site com o <model-viewer> (Google, livre)
// e "no seu espaço" pela câmera do celular (Android: Scene Viewer; iPhone: Quick Look).
// O modelo é feito de graça com apps de escaneamento (KIRI Engine, Scaniverse, Polycam).
// ------------------------------------------------------------
const MODELO3D_MAX_BYTES = 20 * 1024 * 1024;

// Grava o .glb enviado em $_FILES[$campo]; devolve [url|null, erro]
function modelo3d_gravar($campo, $empresaId) {
    $f = $_FILES[$campo] ?? null;
    if (!$f || ($f['error'] ?? 4) === UPLOAD_ERR_NO_FILE) return [null, ''];
    if (($f['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) return [null, 'O envio do modelo 3D falhou (o arquivo pode passar do limite do servidor).'];
    if ((int)$f['size'] > MODELO3D_MAX_BYTES) return [null, 'O modelo 3D passou de 20 MB. No app de escaneamento, exporte com qualidade "média".'];
    $cab = (string)@file_get_contents($f['tmp_name'], false, null, 0, 12);
    if (strlen($cab) < 12 || substr($cab, 0, 4) !== 'glTF' || unpack('V', substr($cab, 4, 4))[1] !== 2) return [null, 'Esse arquivo não é um modelo 3D .glb. No app (KIRI Engine, Scaniverse ou Polycam), exporte no formato GLB.'];
    if (!function_exists('img_ia_pasta')) require_once __DIR__ . '/imagens_ia.php';
    $pasta = img_ia_pasta('empresas/' . (int)$empresaId);
    if (!$pasta) return [null, 'A pasta de arquivos não tem permissão de escrita.'];
    $nome = '3d-' . bin2hex(random_bytes(6)) . '.glb';
    if (!move_uploaded_file($f['tmp_name'], "$pasta/$nome")) return [null, 'Não consegui salvar o modelo 3D.'];
    return ['/uploads/empresas/' . (int)$empresaId . '/' . $nome, ''];
}

function modelo3d_apagar($url, $empresaId) {
    $base = '/uploads/empresas/' . (int)$empresaId . '/3d-';
    if (is_string($url) && str_starts_with($url, $base) && str_ends_with($url, '.glb') && !str_contains($url, '..')) @unlink(__DIR__ . '/..' . $url);
}
