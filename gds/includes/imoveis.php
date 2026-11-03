<?php
// ============================================================
// IMÓVEIS (imobiliária, construtora, loteamento) — no padrão dos portais
// (ZAP, VivaReal, OLX): ficha completa, fotos, filtros e "quero visitar".
// ============================================================

const IMOVEL_TIPOS = ['casa' => 'Casa', 'apartamento' => 'Apartamento', 'sobrado' => 'Sobrado', 'cobertura' => 'Cobertura', 'kitnet' => 'Kitnet/Studio',
    'terreno' => 'Terreno/Lote', 'comercial' => 'Ponto comercial', 'sala' => 'Sala comercial', 'galpao' => 'Galpão', 'chacara' => 'Chácara/Sítio', 'rural' => 'Fazenda/Rural'];
const IMOVEL_FINALIDADES = ['venda' => 'Venda', 'aluguel' => 'Aluguel', 'temporada' => 'Temporada'];
const IMOVEL_SITUACOES = ['disponivel' => 'Disponível', 'reservado' => 'Reservado', 'vendido' => 'Vendido', 'alugado' => 'Alugado'];
const IMOVEL_MAX_FOTOS = 20;

function imovel_fotos($im) {
    $f = json_decode((string)($im['fotos'] ?? ''), true);
    return is_array($f) ? array_values(array_filter($f, 'is_string')) : [];
}

function imovel_preco_txt($im) {
    $p = (float)($im['preco'] ?? 0);
    if ($p <= 0) return 'Consulte';
    $t = 'R$ ' . number_format($p, $p == floor($p) ? 0 : 2, ',', '.');
    return $t . (($im['finalidade'] ?? '') === 'aluguel' ? '/mês' : (($im['finalidade'] ?? '') === 'temporada' ? '/diária' : ''));
}

// Resumo curto: "3 quartos · 1 suíte · 2 vagas · 120 m²"
function imovel_resumo($im) {
    $l = [];
    if ((int)$im['quartos']) $l[] = (int)$im['quartos'] . ((int)$im['quartos'] > 1 ? ' quartos' : ' quarto');
    if ((int)$im['suites']) $l[] = (int)$im['suites'] . ((int)$im['suites'] > 1 ? ' suítes' : ' suíte');
    if ((int)$im['banheiros']) $l[] = (int)$im['banheiros'] . ((int)$im['banheiros'] > 1 ? ' banheiros' : ' banheiro');
    if ((int)$im['vagas']) $l[] = (int)$im['vagas'] . ((int)$im['vagas'] > 1 ? ' vagas' : ' vaga');
    if ((float)$im['area']) $l[] = number_format((float)$im['area'], 0, ',', '.') . ' m²';
    elseif ((float)$im['area_terreno']) $l[] = number_format((float)$im['area_terreno'], 0, ',', '.') . ' m² de terreno';
    return implode(' · ', $l);
}

// Imóveis publicados (o que o site mostra)
function imoveis_publicos($empresaId) {
    global $pdo;
    try {
        $s = $pdo->prepare("SELECT * FROM imoveis WHERE empresa_id=? AND ativo=true ORDER BY situacao='disponivel' DESC, destaque DESC, atualizado_em DESC LIMIT 300");
        $s->execute([(int)$empresaId]);
        return $s->fetchAll();
    } catch (PDOException $e) { return []; }
}

// Grava as fotos enviadas (várias de uma vez) e devolve a lista final
function imovel_salvar_fotos($empresaId, $atuais, $campo = 'fotos_novas') {
    require_once __DIR__ . '/orcamento.php';
    $f = $_FILES[$campo] ?? null;
    if (!$f || !is_array($f['name'] ?? null)) return $atuais;
    foreach ($f['name'] as $i => $n) {
        if (count($atuais) >= IMOVEL_MAX_FOTOS) break;
        if (($f['error'][$i] ?? 1) !== UPLOAD_ERR_OK) continue;
        $url = orc_gravar_imagem($f['tmp_name'][$i], (int)$f['size'][$i], 'imoveis/' . (int)$empresaId, 'im-' . bin2hex(random_bytes(6)));
        if ($url) $atuais[] = $url;
    }
    return $atuais;
}

// ------------------------------------------------------------
// VITRINE NO SITE: filtros, cartões e ficha completa (janela)
// ------------------------------------------------------------
function frag_imoveis($imoveis, $empresa, $estadoLead = []) {
    if (!$imoveis) return '';
    $bairros = array_values(array_unique(array_filter(array_map(fn($i) => trim((string)$i['bairro']), $imoveis))));
    sort($bairros);
    $tipos = array_values(array_unique(array_map(fn($i) => $i['tipo'], $imoveis)));
    $fins = array_values(array_unique(array_map(fn($i) => $i['finalidade'], $imoveis)));
    $h = '<div class="gds-c gds-imoveis" id="gds-imoveis" data-gds-imoveis data-empresa="' . (int)$empresa['id'] . '">';
    $h .= '<form class="gds-im-filtros" data-gds-im-filtros onsubmit="return false">';
    if (count($fins) > 1) { $h .= '<select data-f="finalidade" aria-label="Finalidade"><option value="">Comprar ou alugar</option>'; foreach ($fins as $f) $h .= '<option value="' . e($f) . '">' . e(IMOVEL_FINALIDADES[$f] ?? $f) . '</option>'; $h .= '</select>'; }
    if (count($tipos) > 1) { $h .= '<select data-f="tipo" aria-label="Tipo"><option value="">Todos os tipos</option>'; foreach ($tipos as $t) $h .= '<option value="' . e($t) . '">' . e(IMOVEL_TIPOS[$t] ?? $t) . '</option>'; $h .= '</select>'; }
    if (count($bairros) > 1) { $h .= '<select data-f="bairro" aria-label="Bairro"><option value="">Todos os bairros</option>'; foreach ($bairros as $b) $h .= '<option value="' . e($b) . '">' . e($b) . '</option>'; $h .= '</select>'; }
    $h .= '<select data-f="quartos" aria-label="Quartos"><option value="">Quartos</option><option value="1">1+</option><option value="2">2+</option><option value="3">3+</option><option value="4">4+</option></select>'
        . '<input type="text" inputmode="numeric" data-f="preco" placeholder="Preço até (R$)" aria-label="Preço máximo">'
        . '<input type="search" data-f="busca" placeholder="Buscar (código, rua, piscina…)" aria-label="Buscar"></form>';
    $h .= '<p class="gds-mute gds-im-contagem" data-gds-im-contagem aria-live="polite"></p><div class="gds-im-grade">';
    foreach ($imoveis as $im) {
        $fotos = imovel_fotos($im);
        $dados = ['id' => (int)$im['id'], 'cod' => $im['codigo'] ?: (string)$im['id'], 'titulo' => $im['titulo'], 'fin' => $im['finalidade'], 'fin_txt' => IMOVEL_FINALIDADES[$im['finalidade']] ?? '',
            'tipo' => $im['tipo'], 'tipo_txt' => IMOVEL_TIPOS[$im['tipo']] ?? '', 'preco' => (float)$im['preco'], 'preco_txt' => imovel_preco_txt($im),
            'cond' => (float)$im['condominio'], 'iptu' => (float)$im['iptu'], 'q' => (int)$im['quartos'], 's' => (int)$im['suites'], 'b' => (int)$im['banheiros'], 'v' => (int)$im['vagas'],
            'area' => (float)$im['area'], 'terreno' => (float)$im['area_terreno'], 'bairro' => $im['bairro'], 'cidade' => $im['cidade'], 'desc' => $im['descricao'],
            'carac' => array_values(array_filter(array_map('trim', explode(',', (string)$im['caracteristicas'])))), 'fotos' => $fotos, 'video' => $im['video'],
            'fin_ok' => pg_bool($im['financiamento']), 'permuta' => pg_bool($im['permuta']), 'mob' => pg_bool($im['mobiliado']), 'pet' => pg_bool($im['aceita_pet']),
            'sit' => $im['situacao'], 'sit_txt' => IMOVEL_SITUACOES[$im['situacao']] ?? ''];
        $busca = mb_strtolower(implode(' ', [$dados['cod'], $im['titulo'], $im['bairro'], $im['cidade'], $im['descricao'], $im['caracteristicas'], $dados['tipo_txt']]));
        $indisp = $im['situacao'] !== 'disponivel';
        $h .= '<div class="gds-im' . ($indisp ? ' gds-im-indisp' : '') . '" data-gds-im=\'' . htmlspecialchars(json_encode($dados, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '\' data-busca="' . e($busca) . '" tabindex="0" role="button" aria-label="Ver ' . e($im['titulo']) . '">'
            . '<div class="gds-im-foto"' . ($fotos ? ' style="background-image:url(\'' . e($fotos[0]) . '\')"' : '') . '>' . ($fotos ? '' : '🏠')
            . '<span class="gds-im-selo">' . e($dados['fin_txt']) . '</span>' . ($indisp ? '<span class="gds-im-selo gds-im-selo-sit">' . e($dados['sit_txt']) . '</span>' : (pg_bool($im['destaque']) ? '<span class="gds-im-selo gds-im-selo-dest">★ Destaque</span>' : ''))
            . (count($fotos) > 1 ? '<span class="gds-im-nfotos">📷 ' . count($fotos) . '</span>' : '') . '</div>'
            . '<div class="gds-im-corpo"><strong class="gds-im-preco">' . e($dados['preco_txt']) . '</strong>'
            . ($dados['cond'] ? '<small class="gds-mute">Cond. R$ ' . number_format($dados['cond'], 0, ',', '.') . '</small>' : '')
            . '<h3>' . e($im['titulo']) . '</h3><p class="gds-mute">' . e(trim(($dados['tipo_txt'] ? $dados['tipo_txt'] . ' · ' : '') . $im['bairro'] . ($im['cidade'] ? ', ' . $im['cidade'] : ''), ' ·,')) . '</p>'
            . '<p class="gds-im-resumo">' . e(imovel_resumo($im)) . '</p><span class="gds-im-cod">Cód. ' . e($dados['cod']) . '</span></div></div>';
    }
    $h .= '</div><p class="gds-mute" data-gds-im-vazio hidden>Nenhum imóvel com esses filtros. <a href="#gds-contato">Conte o que procura</a> que a gente encontra para você.</p>';
    // Janela com a ficha completa + "quero visitar"
    $whats = preg_replace('/\D/', '', (string)($empresa['whatsapp'] ?? ''));
    $h .= '<div class="gds-im-modal" data-gds-im-modal hidden role="dialog" aria-modal="true"><div class="gds-im-janela"><button type="button" class="gds-im-fechar" data-gds-im-fechar aria-label="Fechar">✕</button><div data-gds-im-conteudo></div>'
        . '<form method="post" class="gds-im-visita" data-gds-im-form novalidate>' . frag_honeypot() . '<input type="hidden" name="enviar_lead" value="1"><input type="hidden" name="lead_imovel_id" data-gds-im-id>'
        . '<h4>Quero visitar / saber mais</h4><div class="gds-linha2"><div class="gds-campo"><label>Seu nome</label><input type="text" name="nome" required maxlength="120" autocomplete="name"></div>'
        . '<div class="gds-campo"><label>WhatsApp</label><input type="tel" name="telefone" required inputmode="tel" autocomplete="tel" placeholder="(00) 00000-0000"></div></div>'
        . '<div class="gds-linha2"><div class="gds-campo"><label>Melhor dia para visitar</label><input type="date" name="lead_visita_data" min="' . date('Y-m-d') . '"></div><div class="gds-campo"><label>Período</label><select name="lead_visita_periodo"><option>Manhã</option><option>Tarde</option><option>Noite</option><option>Qualquer horário</option></select></div></div>'
        . '<input type="hidden" name="mensagem" data-gds-im-msg><input type="hidden" name="assunto" data-gds-im-assunto><button type="submit" class="gds-btn gds-btn-bloco">Agendar visita</button>'
        . ($whats ? '<a class="gds-btn gds-btn-wa gds-btn-bloco" target="_blank" rel="noopener" data-gds-im-wa data-base="https://wa.me/' . (strlen($whats) <= 11 ? '55' : '') . $whats . '">' . frag_icone('whats') . ' Falar agora no WhatsApp</a>' : '')
        . '</form></div></div>';
    return $h . '</div>';
}
