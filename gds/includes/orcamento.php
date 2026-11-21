<?php
// ============================================================
// ORÇAMENTO PROFISSIONAL (eventos, obras, oficinas, assistência técnica...)
// - Perguntas por serviço (ex.: "Quantos convidados?") respondidas no pedido
// - Fotos enviadas pelo cliente (re-gravadas como JPEG, sem metadados)
// - Data livre/ocupada para eventos (limite de eventos por dia)
// - Etapas com foto: a empresa atualiza o cliente e ele acompanha no link do pedido
// ============================================================
require_once __DIR__ . '/imagens_ia.php';

const ORC_MAX_FOTOS = 3;
const ORC_MAX_BYTES = 8 * 1024 * 1024;

function orc_perguntas($servico) {
    return array_values(array_filter(array_map('strval', (array)json_decode((string)($servico['perguntas'] ?? ''), true))));
}

// Respostas do formulário para o serviço escolhido: [[pergunta, resposta], ...]
function orc_respostas_post($servico) {
    if (!$servico) return [];
    $env = (array)($_POST['orc_resp'][(int)$servico['id']] ?? []);
    $out = [];
    foreach (orc_perguntas($servico) as $i => $perg) {
        $r = mb_substr(trim(strip_tags((string)($env[$i] ?? ''))), 0, 300);
        if ($r !== '') $out[] = [$perg, $r];
    }
    return $out;
}

// Grava uma imagem enviada (campo de $_FILES já normalizado). Retorna a URL pública ou null.
function orc_gravar_imagem($tmp, $tamanho, $sub, $nome) {
    if (!is_uploaded_file($tmp)) return null;
    if ($tamanho <= 0 || $tamanho > ORC_MAX_BYTES) return null;
    $bytes = @file_get_contents($tmp);
    if (!$bytes || !@getimagesizefromstring($bytes)) return null;
    $pasta = img_ia_pasta($sub);
    if (!$pasta) return null;
    $arq = img_ia_salvar($bytes, "$pasta/$nome", 0);
    return $arq ? '/uploads/' . trim($sub, '/') . '/' . basename($arq) : null;
}

// Fotos do pedido de orçamento (até 3)
function orc_salvar_fotos($empresaId, $pedidoId, $campo = 'orc_fotos') {
    $f = $_FILES[$campo] ?? null;
    if (!$f || !is_array($f['name'] ?? null)) return [];
    $urls = [];
    foreach ($f['name'] as $i => $n) {
        if (count($urls) >= ORC_MAX_FOTOS) break;
        if (($f['error'][$i] ?? 1) !== UPLOAD_ERR_OK) continue;
        $url = orc_gravar_imagem($f['tmp_name'][$i], (int)$f['size'][$i], 'pedidos/' . (int)$empresaId, (int)$pedidoId . '-' . bin2hex(random_bytes(6)));
        if ($url) $urls[] = $url;
    }
    return $urls;
}

// Quantos eventos já estão fechados na data (orçamento aprovado em diante)
function orc_eventos_na_data($empresaId, $data, $ignorarPedido = 0) {
    global $pdo;
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE empresa_id=? AND data_evento=? AND status IN ('aprovado','em_andamento','concluido') AND id <> ?");
        $st->execute([$empresaId, $data, (int)$ignorarPedido]);
        return (int)$st->fetchColumn();
    } catch (PDOException $e) { return 0; }
}

// null = empresa não controla; true/false = data livre/ocupada
function orc_data_livre($empresa, $data) {
    $lim = (int)($empresa['eventos_por_dia'] ?? 0);
    if ($lim <= 0) return null;
    return orc_eventos_na_data($empresa['id'], $data) < $lim;
}

// Etapas do pedido (linha do tempo com foto)
function orc_etapas($pedidoId) {
    global $pdo;
    try {
        $st = $pdo->prepare("SELECT * FROM pedido_etapas WHERE pedido_id=? ORDER BY criado_em, id");
        $st->execute([$pedidoId]);
        return $st->fetchAll();
    } catch (PDOException $e) { return []; }
}

// A empresa registra uma etapa (texto + foto opcional) e o cliente é avisado no WhatsApp
function orc_adicionar_etapa($empresa, $pedido, $texto, $campoFoto = 'etapa_foto') {
    global $pdo;
    $texto = mb_substr(trim(strip_tags($texto)), 0, 500);
    if ($texto === '') return ['ok' => false, 'erro' => 'Escreva o que foi feito.'];
    $foto = null;
    $f = $_FILES[$campoFoto] ?? null;
    if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $foto = orc_gravar_imagem($f['tmp_name'], (int)$f['size'], 'pedidos/' . (int)$empresa['id'], (int)$pedido['id'] . '-etapa-' . bin2hex(random_bytes(6)));
        if (!$foto) return ['ok' => false, 'erro' => 'A foto não foi aceita (envie JPG, PNG ou WEBP de até 8 MB).'];
    }
    $pdo->prepare("INSERT INTO pedido_etapas (pedido_id, texto, foto) VALUES (?,?,?)")->execute([$pedido['id'], $texto, $foto]);
    $pdo->prepare("UPDATE pedidos SET atualizado_em=NOW() WHERE id=?")->execute([$pedido['id']]);
    $avisado = false;
    if (!empty($pedido['cliente_telefone']) && function_exists('wa_notificar_cliente')) {
        $link = !empty($pedido['token']) ? wa_url_publica($empresa, '/site/pedido.php?t=' . $pedido['token']) : '';
        wa_notificar_cliente($empresa, 'pedido_etapa', $pedido['cliente_telefone'], ['nome' => explode(' ', trim((string)$pedido['cliente_nome']))[0],
            'codigo' => (string)$pedido['id'], 'mensagem' => $texto . ($foto ? ' (com foto)' : ''), 'link' => $link], 'pedido', $pedido['id']);
        $avisado = true;
    }
    return ['ok' => true, 'foto' => $foto, 'avisado' => $avisado];
}

// Linha do tempo (página do cliente e painel)
function frag_etapas($etapas) {
    if (!$etapas) return '';
    $h = '<div class="gds-etapas"><h4>Atualizações</h4><ol>';
    foreach (array_reverse($etapas) as $et) {
        $h .= '<li><time>' . date('d/m H:i', strtotime($et['criado_em'])) . '</time><p>' . nl2br(e($et['texto'])) . '</p>'
            . ($et['foto'] ? '<a href="' . e($et['foto']) . '" target="_blank" rel="noopener"><img src="' . e($et['foto']) . '" alt="Foto da etapa" loading="lazy"></a>' : '') . '</li>';
    }
    return $h . '</ol></div>';
}

function frag_anexos($anexos) {
    $anexos = array_values(array_filter((array)(is_array($anexos) ? $anexos : json_decode((string)$anexos, true))));
    if (!$anexos) return '';
    $h = '<div class="gds-anexos">';
    foreach ($anexos as $u) $h .= '<a href="' . e($u) . '" target="_blank" rel="noopener"><img src="' . e($u) . '" alt="Foto enviada" loading="lazy"></a>';
    return $h . '</div>';
}
