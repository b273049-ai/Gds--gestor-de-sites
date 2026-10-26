<?php
// ============================================================
// COMPONENTES FUNCIONAIS DO SITE PÚBLICO
// São as partes "de verdade" do site: serviços, agendamento, contato,
// WhatsApp, equipe, avaliações, galeria, cupons, mapa e horários.
// A IA desenha o resto do site e deixa marcadores como {{SERVICOS}};
// na hora de exibir, cada marcador vira o HTML gerado aqui.
//
// Visual: assets/css/gds-componentes.css (adapta-se às cores do site)
// Comportamento: assets/js/gds-site.js (calendário, lightbox, etc.)
// Todas as classes começam com "gds-" e o bloco raiz tem "gds-c".
// ============================================================

if (!function_exists('e')) {
    function e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
}

function so_digitos_frag($s) { return preg_replace('/\D/', '', (string)$s); }

function frag_link_whatsapp($numero, $mensagem = '') {
    $d = so_digitos_frag($numero);
    if ($d === '') return '';
    if (strlen($d) <= 11) $d = '55' . $d; // só DDD + número: acrescenta o código do Brasil
    return 'https://wa.me/' . $d . ($mensagem !== '' ? '?text=' . rawurlencode($mensagem) : '');
}

function frag_icone($nome) {
    $p = [
        'relogio'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'calend'   => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'tel'      => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
        'email'    => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
        'pin'      => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'insta'    => '<rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>',
        'check'    => '<path d="M20 6 9 17l-5-5"/>',
        'seta'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'volta'    => '<path d="M19 12H5M11 18l-6-6 6-6"/>',
        'rota'     => '<path d="m3 11 19-9-9 19-2-8-8-2z"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
        'estrela'  => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
        'sacola'   => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>',
        'x'        => '<path d="M18 6 6 18M6 6l12 12"/>',
    ];
    if ($nome === 'whats') {
        return '<svg class="gds-ico" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.28-1.38a9.9 9.9 0 0 0 4.76 1.21h.01c5.46 0 9.9-4.45 9.9-9.91C21.96 6.45 17.5 2 12.04 2zm5.8 14.16c-.24.68-1.4 1.3-1.93 1.38-.5.08-1.12.11-1.81-.11-.42-.13-.95-.31-1.64-.6-2.88-1.24-4.76-4.15-4.9-4.34-.14-.19-1.17-1.55-1.17-2.97 0-1.41.74-2.1 1-2.39.26-.28.57-.35.76-.35.19 0 .38 0 .55.01.18.01.42-.07.65.5.24.58.81 2 .88 2.14.07.15.11.32.02.51-.09.19-.14.31-.28.48-.14.17-.29.37-.42.5-.14.14-.28.28-.12.56.16.28.71 1.17 1.53 1.9 1.05.94 1.94 1.23 2.22 1.37.28.14.44.12.6-.07.16-.19.68-.79.87-1.06.19-.28.37-.23.62-.14.26.09 1.63.77 1.91.91.28.14.47.21.54.33.07.12.07.68-.17 1.36z"/></svg>';
    }
    return '<svg class="gds-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$nome] ?? '') . '</svg>';
}

function frag_preco($valor, $aPartir = false) {
    if ((float)$valor <= 0) return 'Sob consulta';
    return ($aPartir ? 'a partir de ' : '') . 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

function frag_duracao($min) {
    $min = (int)$min;
    if ($min <= 0) return '';
    if ($min < 60) return $min . ' min';
    return intdiv($min, 60) . 'h' . ($min % 60 ? sprintf('%02d', $min % 60) : '');
}

function frag_estrelas($nota) {
    $nota = max(0, min(5, (int)round((float)$nota)));
    return '<span class="gds-estrelas" role="img" aria-label="' . $nota . ' de 5 estrelas">' . str_repeat('★', $nota) . '<span style="opacity:.3">' . str_repeat('★', 5 - $nota) . '</span></span>';
}

function frag_iniciais($nome) {
    $partes = preg_split('/\s+/', trim((string)$nome));
    $ini = mb_substr($partes[0] ?? '?', 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '');
    return mb_strtoupper($ini);
}

function frag_tempo_atras($data) {
    $d = max(0, time() - strtotime((string)$data));
    if ($d < 86400) return 'hoje';
    if ($d < 172800) return 'ontem';
    if ($d < 30 * 86400) return 'há ' . intdiv($d, 86400) . ' dias';
    if ($d < 365 * 86400) { $m = intdiv($d, 30 * 86400); return 'há ' . $m . ($m === 1 ? ' mês' : ' meses'); }
    $a = intdiv($d, 365 * 86400); return 'há ' . $a . ($a === 1 ? ' ano' : ' anos');
}

// Campo invisível: pessoas não preenchem, robôs de spam sim
function frag_honeypot() {
    return '<div class="gds-honey" aria-hidden="true"><label>Não preencha<input type="text" name="site_url_confirm" tabindex="-1" autocomplete="off"></label>'
         . '<input type="hidden" name="gds_t" value="' . time() . '"></div>';
}

function frag_alerta_erro($msg) {
    return $msg ? '<div class="gds-alerta gds-alerta-erro" role="alert">' . e($msg) . '</div>' : '';
}

// ------------------------------------------------------------
// SOBRE
// ------------------------------------------------------------
function frag_sobre($sobre) {
    $texto = trim((string)$sobre);
    if ($texto === '') return '';
    $html = '';
    foreach (preg_split('/\n\s*\n/', $texto) as $par) $html .= '<p>' . nl2br(e(trim($par))) . '</p>';
    return '<div class="gds-c gds-sobre" style="display:grid;gap:12px">' . $html . '</div>';
}

// ------------------------------------------------------------
// SERVIÇOS — cartões com duração, preço e botão de agendar
// ------------------------------------------------------------
function frag_servicos($servicos, $opts = []) {
    if (empty($servicos)) return '<div class="gds-c"><p class="gds-vazio">Em breve, ' . (($opts['modo'] ?? '') === 'catalogo' ? 'nosso cardápio/produtos' : 'nossos serviços') . ' aparecerão aqui.</p></div>';
    $categorias = array_values(array_unique(array_filter(array_map(fn($s) => trim((string)($s['categoria'] ?? '')), $servicos))));

    $html = '<div class="gds-c gds-servicos" id="gds-servicos" data-gds-filtravel>';
    if (count($categorias) > 1) {
        $html .= '<div class="gds-filtros" role="group" aria-label="Filtrar serviços"><button type="button" class="gds-filtro" aria-pressed="true" data-gds-filtro="">Todos</button>';
        foreach ($categorias as $c) $html .= '<button type="button" class="gds-filtro" aria-pressed="false" data-gds-filtro="' . e($c) . '">' . e($c) . '</button>';
        $html .= '</div>';
    }
    $html .= '<div class="gds-grade">';
    foreach ($servicos as $s) {
        $destaque = !empty($s['destaque']) && $s['destaque'] !== 'f' && $s['destaque'] !== false;
        $aPartir = !empty($s['preco_a_partir']) && $s['preco_a_partir'] !== 'f' && $s['preco_a_partir'] !== false;
        $html .= '<article class="gds-svc' . ($destaque ? ' gds-svc-destaque' : '') . '" data-gds-cat="' . e(trim((string)($s['categoria'] ?? ''))) . '">';
        if (!empty($s['imagem'])) $html .= '<img class="gds-svc-img" src="' . e($s['imagem']) . '" alt="' . e($s['nome']) . '" loading="lazy">';
        $html .= '<div class="gds-svc-corpo"><div class="gds-svc-topo"><h3>' . e($s['nome']) . '</h3>'
               . ($destaque ? '<span class="gds-chip gds-chip-accent">' . frag_icone('estrela') . ' Destaque</span>' : '') . '</div>';
        if (!empty($s['descricao'])) $html .= '<p class="gds-svc-desc">' . e($s['descricao']) . '</p>';
        $meta = '';
        if (($opts['modo'] ?? 'agendamento') === 'agendamento' && ($d = frag_duracao($s['duracao_minutos'] ?? 0))) $meta .= '<span class="gds-chip">' . frag_icone('relogio') . ' ' . $d . '</span>';
        if (!empty($s['categoria']) && count($categorias) <= 1) $meta .= '<span class="gds-chip">' . e($s['categoria']) . '</span>';
        if ($meta) $html .= '<div class="gds-svc-meta">' . $meta . '</div>';
        $html .= '</div><div class="gds-svc-rodape"><div class="gds-preco">' . ($aPartir && (float)$s['preco'] > 0 ? '<small>a partir de</small>R$ ' . number_format((float)$s['preco'], 2, ',', '.') : frag_preco($s['preco'])) . '</div>';
        $html .= frag_botao_servico($s, $opts) . '</div></article>';
    }
    return $html . '</div></div>';
}

// Botão de cada serviço, conforme a forma de atendimento do negócio:
// agendamento → abre o agendamento já com o serviço | orçamento → abre o pedido de orçamento com o serviço
// contato → WhatsApp falando do serviço | catálogo → "Pedir" pelo WhatsApp com item e preço
function frag_botao_servico($s, $opts) {
    $modo = $opts['modo'] ?? 'agendamento';
    $whats = $opts['whatsapp'] ?? '';
    $id = (int)$s['id'];
    if ($modo === 'agendamento' && !empty($opts['tem_agendamento'])) {
        return '<a class="gds-btn" href="#gds-agendamento" data-gds-agendar-servico="' . $id . '">Agendar ' . frag_icone('seta') . '</a>';
    }
    if (($modo === 'orcamento' || $modo === 'agendamento') && !empty($opts['tem_orcamento'])) {
        return '<a class="gds-btn" href="#gds-orcamento" data-gds-orcar-servico="' . $id . '">Pedir orçamento ' . frag_icone('seta') . '</a>';
    }
    if ($modo === 'catalogo' && !empty($opts['carrinho']) && (float)$s['preco'] > 0) {
        return '<button type="button" class="gds-btn" data-gds-add="' . $id . '" data-nome="' . e($s['nome']) . '" data-preco="' . (float)$s['preco'] . '">' . frag_icone('sacola') . ' Adicionar</button>';
    }
    if ($whats) {
        if ($modo === 'catalogo') {
            $msg = 'Olá! Quero pedir: ' . $s['nome'] . ((float)$s['preco'] > 0 ? ' (R$ ' . number_format((float)$s['preco'], 2, ',', '.') . ')' : '') . '.';
            return '<a class="gds-btn gds-btn-wa" target="_blank" rel="noopener" href="' . e(frag_link_whatsapp($whats, $msg)) . '">' . frag_icone('whats') . ' Pedir</a>';
        }
        $rotulo = $modo === 'orcamento' ? 'Pedir orçamento' : ($modo === 'agendamento' ? 'Agendar pelo WhatsApp' : 'Quero saber mais');
        $msg = $modo === 'orcamento' ? 'Olá! Vi no site "' . $s['nome'] . '" e gostaria de um orçamento.' : 'Olá! Vi no site "' . $s['nome'] . '" e gostaria de mais informações.';
        return '<a class="gds-btn gds-btn-wa" target="_blank" rel="noopener" href="' . e(frag_link_whatsapp($whats, $msg)) . '">' . frag_icone('whats') . ' ' . $rotulo . '</a>';
    }
    return !empty($opts['tem_contato']) ? '<a class="gds-btn-2" href="#gds-contato">Fale com a gente</a>' : '';
}

// ------------------------------------------------------------
// EQUIPE — com botão "Agendar com fulano"
// ------------------------------------------------------------
function frag_equipe($profissionais, $temAgenda = false) {
    if (empty($profissionais)) return '';
    $html = '<div class="gds-c"><div class="gds-equipe">';
    foreach ($profissionais as $p) {
        $foto = $p['foto']
            ? '<img class="gds-avatar" src="' . e($p['foto']) . '" alt="' . e($p['nome']) . '" loading="lazy">'
            : '<div class="gds-avatar" aria-hidden="true">' . e(frag_iniciais($p['nome'])) . '</div>';
        $html .= '<div class="gds-pro">' . $foto . '<h4>' . e($p['nome']) . '</h4>'
               . ($p['especialidade'] ? '<p class="gds-mute">' . e($p['especialidade']) . '</p>' : '')
               . ($temAgenda ? '<a class="gds-btn-2" href="#gds-agendamento" data-gds-agendar-profissional="' . (int)$p['id'] . '">Agendar com ' . e(explode(' ', trim($p['nome']))[0]) . '</a>' : '')
               . '</div>';
    }
    return $html . '</div></div>';
}

// ------------------------------------------------------------
// GALERIA — grade com ampliação (lightbox)
// ------------------------------------------------------------
function frag_galeria($imagens, $nomeEmpresa = '') {
    if (empty($imagens)) return '';
    $html = '<div class="gds-c"><div class="gds-galeria" data-gds-galeria>';
    foreach (array_values($imagens) as $i => $img) {
        $alt = 'Foto ' . ($i + 1) . ($nomeEmpresa ? ' de ' . $nomeEmpresa : '');
        $html .= '<button type="button" class="gds-foto" data-gds-foto="' . e($img) . '" aria-label="Ampliar ' . e($alt) . '"><img src="' . e($img) . '" alt="' . e($alt) . '" loading="lazy"></button>';
    }
    return $html . '</div></div>';
}

// ------------------------------------------------------------
// AVALIAÇÕES — resumo com média, lista e formulário com estrelas
// ------------------------------------------------------------
function frag_avaliacoes($avaliacoes, $estado = [], $permitirEnvio = true, $resumo = null) {
    $html = '<div class="gds-c gds-avaliacoes">';
    $total = $resumo['total'] ?? count($avaliacoes);
    if ($total > 0) {
        $media = $resumo['media'] ?? (array_sum(array_column($avaliacoes, 'nota')) / max(1, count($avaliacoes)));
        $dist = $resumo['dist'] ?? array_count_values(array_map('intval', array_column($avaliacoes, 'nota')));
        $html .= '<div class="gds-resumo-aval"><div><div class="gds-nota-grande">' . number_format($media, 1, ',', '') . '</div>' . frag_estrelas($media)
               . '<p class="gds-mute" style="font-size:.85rem">' . $total . ' avaliação' . ($total === 1 ? '' : 'ões') . '</p></div><div class="gds-barras">';
        for ($n = 5; $n >= 1; $n--) {
            $q = (int)($dist[$n] ?? 0);
            $html .= '<div class="gds-barra"><span>' . $n . '★</span><span><i style="width:' . round($q / $total * 100) . '%"></i></span><span>' . $q . '</span></div>';
        }
        $html .= '</div></div><div class="gds-avals">';
        foreach ($avaliacoes as $av) {
            $autor = $av['autor'] ?? 'Cliente';
            $html .= '<article class="gds-aval"><div class="gds-aval-topo"><span class="gds-ini" aria-hidden="true">' . e(frag_iniciais($autor)) . '</span><div><strong>' . e($autor) . '</strong>'
                   . (!empty($av['criado_em']) ? '<small>' . e(frag_tempo_atras($av['criado_em'])) . '</small>' : '') . '</div></div>'
                   . frag_estrelas($av['nota']) . ($av['comentario'] ? '<p>' . e($av['comentario']) . '</p>' : '') . '</article>';
        }
        $html .= '</div>';
    } else {
        $html .= '<p class="gds-vazio">Ainda sem avaliações. Seja o primeiro a contar como foi!</p>';
    }
    if (!$permitirEnvio) return $html . '</div>';

    if (!empty($estado['ok'])) {
        return $html . '<div class="gds-alerta gds-alerta-ok" style="margin-top:18px">Obrigado pela avaliação! Ela aparece aqui assim que for aprovada.</div></div>';
    }
    $html .= '<details class="gds-avaliar"' . (!empty($estado['erro']) ? ' open' : '') . '><summary class="gds-btn-2">' . frag_icone('estrela') . ' Deixar minha avaliação</summary>'
           . '<form method="post" class="gds-card" style="max-width:520px">' . frag_honeypot() . frag_alerta_erro($estado['erro'] ?? '')
           . '<div class="gds-campo"><span class="gds-rotulo" id="gds-nota-rot">Sua nota</span><div class="gds-star-input" role="radiogroup" aria-labelledby="gds-nota-rot">';
    for ($n = 5; $n >= 1; $n--) {
        $html .= '<input type="radio" id="gds-nota-' . $n . '" name="av_nota" value="' . $n . '"' . ($n === 5 ? ' checked' : '') . ' required><label for="gds-nota-' . $n . '" title="' . $n . ' estrela' . ($n > 1 ? 's' : '') . '">★</label>';
    }
    $html .= '</div></div>'
           . '<div class="gds-campo"><label for="av_nome">Seu nome</label><input type="text" id="av_nome" name="av_nome" maxlength="80" required autocomplete="name"></div>'
           . '<div class="gds-campo"><label for="av_comentario">Como foi sua experiência?</label><textarea id="av_comentario" name="av_comentario" maxlength="600" rows="3"></textarea></div>'
           . '<button type="submit" name="enviar_avaliacao" value="1" class="gds-btn">Enviar avaliação</button></form></details>';
    return $html . '</div>';
}

// ------------------------------------------------------------
// CUPONS — estilo "ticket" com botão copiar
// ------------------------------------------------------------
function frag_cupons($cupons) {
    if (empty($cupons)) return '';
    $html = '<div class="gds-c"><div class="gds-cupons">';
    foreach ($cupons as $c) {
        $html .= '<div class="gds-cupom"><div class="gds-cupom-valor">' . number_format((float)$c['desconto_percentual'], 0) . '%<small>OFF</small></div><div class="gds-cupom-info">'
               . '<span class="gds-codigo">' . e($c['codigo']) . '</span>'
               . ($c['descricao'] ? '<p class="gds-mute" style="font-size:.85rem">' . e($c['descricao']) . '</p>' : '')
               . ($c['validade'] ? '<small class="gds-mute">Válido até ' . date('d/m/Y', strtotime($c['validade'])) . '</small>' : '')
               . '<button type="button" class="gds-copiar" data-gds-copiar="' . e($c['codigo']) . '">Copiar código</button></div></div>';
    }
    return $html . '</div><p class="gds-mute" style="font-size:.85rem;margin-top:10px">Use o código na hora de agendar.</p></div>';
}

// ------------------------------------------------------------
// MAPA — com botões "Como chegar" (Google Maps e Waze)
// ------------------------------------------------------------
function frag_mapa($endereco, $mapsKey) {
    if (!$endereco) return '';
    $src = $mapsKey
        ? 'https://www.google.com/maps/embed/v1/place?key=' . rawurlencode($mapsKey) . '&q=' . urlencode($endereco)
        : 'https://maps.google.com/maps?q=' . urlencode($endereco) . '&output=embed';
    return '<div class="gds-c"><div class="gds-mapa-box"><iframe title="Mapa: ' . e($endereco) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="' . e($src) . '"></iframe>'
         . '<div class="gds-mapa-info"><span style="display:flex;gap:8px;align-items:center">' . frag_icone('pin') . e($endereco) . '</span><div style="display:flex;gap:8px;flex-wrap:wrap">'
         . '<a class="gds-btn" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=' . urlencode($endereco) . '">' . frag_icone('rota') . ' Como chegar</a>'
         . '<a class="gds-btn-2" target="_blank" rel="noopener" href="https://waze.com/ul?q=' . urlencode($endereco) . '&navigate=yes">Waze</a></div></div></div></div>';
}

// ------------------------------------------------------------
// HORÁRIOS — tabela da semana + "aberto agora"
// ------------------------------------------------------------
function frag_horarios($agenda, $status = null) {
    $nomes = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    $abertos = array_map('intval', explode(',', (string)$agenda['dias']));
    $html = '<div class="gds-c gds-horarios">';
    if ($status) $html .= '<p class="gds-status' . ($status['aberto'] ? ' aberto' : '') . '" style="margin-bottom:12px">' . e($status['texto']) . '</p>';
    $html .= '<table class="gds-horarios-tab"><tbody>';
    foreach ([1, 2, 3, 4, 5, 6, 0] as $d) {
        $faixa = in_array($d, $abertos, true)
            ? $agenda['abertura'] . ' – ' . ($agenda['pausa_inicio'] ? $agenda['pausa_inicio'] . ' · ' . $agenda['pausa_fim'] . ' – ' : '') . $agenda['fechamento']
            : 'Fechado';
        $html .= '<tr' . ((int)date('w') === $d ? ' class="hoje"' : '') . '><td>' . $nomes[$d] . '</td><td>' . e($faixa) . '</td></tr>';
    }
    return $html . '</tbody></table></div>';
}

// ------------------------------------------------------------
// BOTÃO FLUTUANTE DO WHATSAPP — com balão de convite
// ------------------------------------------------------------
function frag_whatsapp_float($whatsapp, $mensagem = '', $nomeEmpresa = '') {
    if (!so_digitos_frag($whatsapp)) return '';
    $mensagem = trim((string)$mensagem) ?: 'Olá! Vim pelo site e gostaria de mais informações.';
    return '<div class="gds-wa" data-gds-wa>'
         . '<div class="gds-wa-balao" hidden role="status"><button type="button" aria-label="Fechar">×</button><strong>' . e($nomeEmpresa ?: 'Atendimento') . '</strong>Olá! 👋 Posso te ajudar? Fale com a gente pelo WhatsApp.</div>'
         . '<a class="gds-wa-btn" href="' . e(frag_link_whatsapp($whatsapp, $mensagem)) . '" target="_blank" rel="noopener" aria-label="Conversar no WhatsApp">'
         . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.28-1.38a9.9 9.9 0 0 0 4.76 1.21h.01c5.46 0 9.9-4.45 9.9-9.91C21.96 6.45 17.5 2 12.04 2zm5.8 14.16c-.24.68-1.4 1.3-1.93 1.38-.5.08-1.12.11-1.81-.11-.42-.13-.95-.31-1.64-.6-2.88-1.24-4.76-4.15-4.9-4.34-.14-.19-1.17-1.55-1.17-2.97 0-1.41.74-2.1 1-2.39.26-.28.57-.35.76-.35.19 0 .38 0 .55.01.18.01.42-.07.65.5.24.58.81 2 .88 2.14.07.15.11.32.02.51-.09.19-.14.31-.28.48-.14.17-.29.37-.42.5-.14.14-.28.28-.12.56.16.28.71 1.17 1.53 1.9 1.05.94 1.94 1.23 2.22 1.37.28.14.44.12.6-.07.16-.19.68-.79.87-1.06.19-.28.37-.23.62-.14.26.09 1.63.77 1.91.91.28.14.47.21.54.33.07.12.07.68-.17 1.36z"/></svg>'
         . '<span>Fale conosco</span></a></div>';
}

// ------------------------------------------------------------
// CONTATO — canais (WhatsApp, telefone, e-mail, endereço) + formulário
// ------------------------------------------------------------
function frag_contato($estado = [], $empresa = [], $status = null) {
    $whats = $empresa['whatsapp'] ?? '';
    $infos = '';
    if (so_digitos_frag($whats)) {
        $infos .= '<a class="gds-info" target="_blank" rel="noopener" href="' . e(frag_link_whatsapp($whats, trim((string)($empresa['whatsapp_mensagem'] ?? '')) ?: 'Olá! Vim pelo site e gostaria de mais informações.')) . '"><span class="gds-info-ico wa">' . frag_icone('whats') . '</span><span><small>WhatsApp · resposta rápida</small><strong>' . e(frag_formatar_telefone($whats)) . '</strong></span></a>';
    }
    if (!empty($empresa['telefone']) && so_digitos_frag($empresa['telefone']) !== so_digitos_frag($whats)) {
        $infos .= '<a class="gds-info" href="tel:+' . (strlen(so_digitos_frag($empresa['telefone'])) <= 11 ? '55' : '') . so_digitos_frag($empresa['telefone']) . '"><span class="gds-info-ico">' . frag_icone('tel') . '</span><span><small>Telefone</small><strong>' . e($empresa['telefone']) . '</strong></span></a>';
    }
    if (!empty($empresa['email'])) {
        $infos .= '<a class="gds-info" href="mailto:' . e($empresa['email']) . '"><span class="gds-info-ico">' . frag_icone('email') . '</span><span><small>E-mail</small><strong>' . e($empresa['email']) . '</strong></span></a>';
    }
    if (!empty($empresa['endereco'])) {
        $infos .= '<a class="gds-info" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=' . urlencode($empresa['endereco']) . '"><span class="gds-info-ico">' . frag_icone('pin') . '</span><span><small>Endereço · toque para traçar rota</small><strong>' . e($empresa['endereco']) . '</strong></span></a>';
    }
    if (!empty($empresa['instagram'])) {
        $ig = ltrim(preg_replace('#^https?://(www\.)?instagram\.com/#i', '', trim($empresa['instagram'])), '@/');
        $infos .= '<a class="gds-info" target="_blank" rel="noopener" href="https://instagram.com/' . e(rtrim($ig, '/')) . '"><span class="gds-info-ico">' . frag_icone('insta') . '</span><span><small>Instagram</small><strong>@' . e(rtrim($ig, '/')) . '</strong></span></a>';
    }
    if ($status) {
        $infos .= '<div class="gds-info"><span class="gds-info-ico">' . frag_icone('relogio') . '</span><span><small>Horário</small><span class="gds-status' . ($status['aberto'] ? ' aberto' : '') . '">' . e($status['texto']) . '</span></span></div>';
    }

    if (!empty($estado['ok'])) {
        $form = '<div class="gds-sucesso"><div class="gds-sucesso-ico">' . frag_icone('check') . '</div><h3>' . (!empty($estado['nome']) ? e(explode(' ', $estado['nome'])[0]) . ', recebemos sua mensagem!' : 'Mensagem enviada!') . '</h3><p class="gds-mute">Vamos responder o quanto antes.</p>'
              . (so_digitos_frag($whats) ? '<div class="gds-acoes"><a class="gds-btn gds-btn-wa" target="_blank" rel="noopener" href="' . e(frag_link_whatsapp($whats, 'Olá! Acabei de enviar uma mensagem pelo site.')) . '">' . frag_icone('whats') . ' Prefere falar agora pelo WhatsApp?</a></div>' : '')
              . '</div>';
    } else {
        $p = $estado['post'] ?? [];
        $assuntos = ['Quero um orçamento', 'Tenho uma dúvida', 'Quero agendar', 'Elogio ou sugestão', 'Outro assunto'];
        $opts = '';
        foreach ($assuntos as $a) $opts .= '<option' . (($p['assunto'] ?? '') === $a ? ' selected' : '') . '>' . e($a) . '</option>';
        $form = '<form method="post" class="gds-card" novalidate data-gds-form>' . frag_honeypot() . frag_alerta_erro($estado['erro'] ?? '')
              . '<div class="gds-campo"><label for="ct_nome">Seu nome</label><input type="text" id="ct_nome" name="nome" maxlength="120" required autocomplete="name" value="' . e($p['nome'] ?? '') . '"></div>'
              . '<div class="gds-linha"><div class="gds-campo"><label for="ct_tel">WhatsApp</label><input type="tel" id="ct_tel" name="telefone" maxlength="20" autocomplete="tel" inputmode="tel" placeholder="(11) 99999-9999" data-gds-tel value="' . e($p['telefone'] ?? '') . '"></div>'
              . '<div class="gds-campo"><label for="ct_email">E-mail</label><input type="email" id="ct_email" name="email" maxlength="150" autocomplete="email" value="' . e($p['email'] ?? '') . '"></div></div>'
              . '<div class="gds-campo"><label for="ct_assunto">Assunto</label><select id="ct_assunto" name="assunto">' . $opts . '</select></div>'
              . '<div class="gds-campo"><label for="ct_msg">Mensagem</label><textarea id="ct_msg" name="mensagem" rows="4" maxlength="2000" required>' . e($p['mensagem'] ?? '') . '</textarea></div>'
              . '<button type="submit" name="enviar_lead" value="1" class="gds-btn gds-btn-bloco">Enviar mensagem ' . frag_icone('seta') . '</button>'
              . '<p class="gds-nota-lgpd">Seus dados são usados só para responder o seu contato.</p></form>';
    }
    if ($infos === '') return '<div class="gds-c" id="gds-contato" style="max-width:560px">' . $form . '</div>';
    return '<div class="gds-c gds-contato" id="gds-contato"><div class="gds-infos">' . $infos . '</div>' . $form . '</div>';
}

function frag_formatar_telefone($t) {
    $d = so_digitos_frag($t);
    if (strlen($d) >= 12 && str_starts_with($d, '55')) $d = substr($d, 2);
    if (strlen($d) === 11) return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7);
    if (strlen($d) === 10) return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6);
    return (string)$t;
}

// ------------------------------------------------------------
// PEDIDO DE ORÇAMENTO — para quem não marca horário (DJ, fotógrafo,
// obras, eventos, agência...): serviço de interesse + data/local + detalhes.
// Vira um "Pedido/Orçamento" no painel e pode seguir também pelo WhatsApp.
// $estado: ['ok','erro','post','resumo','msg_whatsapp']
// ------------------------------------------------------------
function frag_orcamento($servicos, $estado = [], $empresa = []) {
    $whats = $empresa['whatsapp'] ?? '';
    if (!empty($estado['ok'])) {
        $html = '<div class="gds-c gds-orcamento" id="gds-orcamento"><div class="gds-sucesso" data-gds-sucesso><div class="gds-sucesso-ico">' . frag_icone('check') . '</div>'
              . '<h3>Pedido de orçamento enviado!</h3><p class="gds-mute">' . (!empty($estado['nome']) ? e(explode(' ', $estado['nome'])[0]) . ', r' : 'R') . 'ecebemos tudo e vamos te responder com o orçamento o quanto antes.</p>';
        if (!empty($estado['wa_automatico'])) $html .= '<p class="gds-aviso-wa">' . frag_icone('whats') . ' Enviamos a confirmação para o seu WhatsApp — o valor também chega por lá.</p>';
        if (!empty($estado['resumo'])) {
            $html .= '<div class="gds-resumo">';
            foreach ($estado['resumo'] as [$rot, $val]) $html .= '<div><span>' . e($rot) . '</span><span>' . e($val) . '</span></div>';
            $html .= '</div>';
        }
        if (!empty($estado['link_acompanhar'])) $html .= '<p style="margin-top:14px;font-size:.88rem"><a href="' . e($estado['link_acompanhar']) . '">Acompanhe seu pedido nº ' . (int)($estado['codigo'] ?? 0) . ' aqui</a></p>';
        if (so_digitos_frag($whats) && empty($estado['wa_automatico'])) {
            $html .= '<div class="gds-acoes"><a class="gds-btn gds-btn-wa" target="_blank" rel="noopener" href="' . e(frag_link_whatsapp($whats, $estado['msg_whatsapp'] ?? 'Olá! Acabei de pedir um orçamento pelo site.')) . '">' . frag_icone('whats') . ' Enviar também pelo WhatsApp (resposta mais rápida)</a></div>';
        }
        return $html . '</div></div>';
    }
    $p = $estado['post'] ?? [];
    $html = '<div class="gds-c gds-orcamento" id="gds-orcamento" style="max-width:720px">' . frag_alerta_erro($estado['erro'] ?? '')
          . '<form method="post" class="gds-card" novalidate data-gds-form-orcamento>' . frag_honeypot() . '<input type="hidden" name="pedir_orcamento" value="1">';
    if ($servicos) {
        $html .= '<div class="gds-campo"><span class="gds-rotulo">O que você precisa?</span><div class="gds-opcoes" role="radiogroup">';
        foreach ($servicos as $s) {
            $sel = (string)($p['orc_servico_id'] ?? '') === (string)$s['id'] ? ' checked' : '';
            $aPartir = !empty($s['preco_a_partir']) && $s['preco_a_partir'] !== 'f';
            $preco = (float)$s['preco'] > 0 ? ($aPartir ? 'a partir de ' : '') . 'R$ ' . number_format((float)$s['preco'], 2, ',', '.') : '';
            $html .= '<label class="gds-op"><input type="radio" name="orc_servico_id" value="' . (int)$s['id'] . '"' . $sel . '><span class="gds-op-txt"><strong>' . e($s['nome']) . '</strong>'
                   . ($s['descricao'] ? '<small>' . e(mb_strimwidth($s['descricao'], 0, 60, '…')) . '</small>' : '') . '</span>' . ($preco ? '<span class="gds-op-preco">' . e($preco) . '</span>' : '') . '</label>';
        }
        $outro = (string)($p['orc_servico_id'] ?? '') === 'outro' ? ' checked' : '';
        $html .= '<label class="gds-op"><input type="radio" name="orc_servico_id" value="outro"' . $outro . '><span class="gds-op-txt"><strong>Outro / ainda não sei</strong><small>Conte nos detalhes abaixo</small></span></label></div></div>';
    }
    $html .= '<div class="gds-linha"><div class="gds-campo"><label for="orc_data">Data (se houver)</label><input type="text" id="orc_data" name="orc_data" placeholder="dd/mm/aaaa" inputmode="numeric" maxlength="10" data-gds-data value="' . e($p['orc_data'] ?? '') . '"></div>'
           . '<div class="gds-campo"><label for="orc_local">Cidade / local</label><input type="text" id="orc_local" name="orc_local" maxlength="150" value="' . e($p['orc_local'] ?? '') . '"></div></div>'
           . '<div class="gds-campo"><label for="orc_detalhes">Conte os detalhes</label><textarea id="orc_detalhes" name="orc_detalhes" rows="4" maxlength="2000" placeholder="Ex.: tipo de evento, quantidade de pessoas, horário, o que você imagina…">' . e($p['orc_detalhes'] ?? '') . '</textarea></div>'
           . '<div class="gds-campo"><label for="orc_nome">Seu nome</label><input type="text" id="orc_nome" name="orc_nome" maxlength="120" required autocomplete="name" value="' . e($p['orc_nome'] ?? '') . '"></div>'
           . '<div class="gds-linha"><div class="gds-campo"><label for="orc_tel">WhatsApp</label><input type="tel" id="orc_tel" name="orc_telefone" maxlength="20" required autocomplete="tel" inputmode="tel" placeholder="(11) 99999-9999" data-gds-tel value="' . e($p['orc_telefone'] ?? '') . '"></div>'
           . '<div class="gds-campo"><label for="orc_email">E-mail (opcional)</label><input type="email" id="orc_email" name="orc_email" maxlength="150" autocomplete="email" value="' . e($p['orc_email'] ?? '') . '"></div></div>'
           . '<button type="submit" class="gds-btn gds-btn-bloco">' . frag_icone('seta') . ' Pedir orçamento</button>'
           . '<p class="gds-nota-lgpd">Sem compromisso. Usamos seus dados só para enviar o orçamento.</p></form>';
    if (so_digitos_frag($whats)) {
        $html .= '<p style="margin-top:12px;font-size:.9rem" class="gds-mute">Prefere conversar? <a href="' . e(frag_link_whatsapp($whats, 'Olá! Gostaria de um orçamento.')) . '" target="_blank" rel="noopener"><strong>Chame no WhatsApp</strong></a></p>';
    }
    return $html . '</div>';
}

// ------------------------------------------------------------
// LINKS DE CALENDÁRIO (Google e arquivo .ics para iPhone/Outlook)
// ------------------------------------------------------------
function gerar_link_google_calendar($titulo, $dataHoraInicio, $duracaoMinutos, $descricao, $local) {
    try { $inicio = new DateTime($dataHoraInicio); } catch (Exception $e) { return ''; }
    $fim = (clone $inicio)->modify('+' . max(15, (int)$duracaoMinutos) . ' minutes');
    return 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action' => 'TEMPLATE', 'text' => $titulo,
        'dates' => $inicio->format('Ymd\THis') . '/' . $fim->format('Ymd\THis'),
        'details' => $descricao, 'location' => $local,
        'ctz' => 'America/Sao_Paulo', // sem isso o Google usa o fuso de quem abre o link
    ]);
}

function gerar_ics_data_uri($titulo, $dataHoraInicio, $duracaoMinutos, $descricao, $local) {
    try { $inicio = new DateTime($dataHoraInicio, new DateTimeZone('America/Sao_Paulo')); } catch (Exception $e) { return ''; }
    $fim = (clone $inicio)->modify('+' . max(15, (int)$duracaoMinutos) . ' minutes');
    $utc = new DateTimeZone('UTC');
    $esc = fn($s) => str_replace(["\\", ';', ',', "\n"], ["\\\\", '\;', '\,', '\n'], (string)$s);
    $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//GDS//Agendamento//PT\r\nBEGIN:VEVENT\r\nUID:" . uniqid('gds-') . "@gds\r\n"
         . 'DTSTAMP:' . gmdate('Ymd\THis\Z') . "\r\nDTSTART:" . (clone $inicio)->setTimezone($utc)->format('Ymd\THis\Z') . "\r\nDTEND:" . (clone $fim)->setTimezone($utc)->format('Ymd\THis\Z') . "\r\n"
         . 'SUMMARY:' . $esc($titulo) . "\r\nDESCRIPTION:" . $esc($descricao) . "\r\nLOCATION:" . $esc($local) . "\r\n"
         . "BEGIN:VALARM\r\nTRIGGER:-PT2H\r\nACTION:DISPLAY\r\nDESCRIPTION:Lembrete\r\nEND:VALARM\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    return 'data:text/calendar;charset=utf-8,' . rawurlencode($ics);
}

// ------------------------------------------------------------
// AGENDAMENTO — passo a passo: serviço → profissional → dia e hora → dados
// $estado: ['ok','erro','post','detalhes','pix','pix_erro']
// ------------------------------------------------------------
function frag_agendamento($servicos, $profissionais, $estado = [], $agenda = [], $empresa = []) {
    if (!empty($estado['ok'])) return frag_agendamento_confirmado($estado, $empresa);

    $empresaId = (int)($empresa['id'] ?? 0);
    $post = $estado['post'] ?? [];
    $passos = [];
    if ($servicos) $passos['servico'] = 'Serviço';
    if ($profissionais) $passos['profissional'] = 'Profissional';
    $passos['data'] = 'Dia e horário';
    $passos['dados'] = 'Seus dados';

    $config = [
        'empresa' => $empresaId,
        'dias' => array_map('intval', explode(',', (string)($agenda['dias'] ?? '1,2,3,4,5,6'))),
        'abertura' => $agenda['abertura'] ?? '08:00',
        'fechamento' => $agenda['fechamento'] ?? '19:00',
        'intervalo' => (int)($agenda['intervalo'] ?? 30),
        'maxDias' => (int)($agenda['antecedencia_dias'] ?? 60),
        'temServicos' => (bool)$servicos,
    ];

    $html = '<div class="gds-c gds-agendamento" id="gds-agendamento" data-gds-agenda=\'' . e(json_encode($config)) . '\'>';
    $html .= '<div class="gds-passos" aria-hidden="true">';
    $i = 1;
    foreach ($passos as $k => $rot) $html .= '<div class="gds-passo-ind" data-gds-ind="' . $k . '"><span>' . $i++ . '. ' . $rot . '</span></div>';
    $html .= '</div>' . frag_alerta_erro($estado['erro'] ?? '');
    // campo oculto: o botão é desativado ao enviar (evita clique duplo) e botão desativado não vai no formulário
    $html .= '<form method="post" novalidate data-gds-form-agenda>' . frag_honeypot() . '<input type="hidden" name="fazer_agendamento" value="1">';

    if ($servicos) {
        $html .= '<section class="gds-passo" data-gds-passo="servico"><h3>Qual serviço você quer?</h3><div class="gds-opcoes" role="radiogroup">';
        foreach ($servicos as $s) {
            $aPartir = !empty($s['preco_a_partir']) && $s['preco_a_partir'] !== 'f';
            $sel = (string)($post['ag_servico_id'] ?? '') === (string)$s['id'] ? ' checked' : '';
            $html .= '<label class="gds-op"><input type="radio" name="ag_servico_id" value="' . (int)$s['id'] . '" data-nome="' . e($s['nome']) . '" data-duracao="' . (int)$s['duracao_minutos'] . '" data-preco="' . (float)$s['preco'] . '"' . $sel . ' required>'
                   . '<span class="gds-op-txt"><strong>' . e($s['nome']) . '</strong><small>' . e(frag_duracao($s['duracao_minutos']) ?: '') . '</small></span><span class="gds-op-preco">' . ($aPartir && (float)$s['preco'] > 0 ? '<small>a partir de</small>' . e(frag_preco($s['preco'])) : e(frag_preco($s['preco']))) . '</span></label>';
        }
        $html .= '</div></section>';
    }
    if ($profissionais) {
        $selSem = empty($post['ag_profissional_id']) ? ' checked' : '';
        $html .= '<section class="gds-passo" data-gds-passo="profissional"><h3>Com quem?</h3><div class="gds-opcoes" role="radiogroup">'
               . '<label class="gds-op"><input type="radio" name="ag_profissional_id" value="" data-nome="Sem preferência"' . $selSem . '><span class="gds-mini" aria-hidden="true">✦</span><span class="gds-op-txt"><strong>Sem preferência</strong><small>Primeiro horário livre</small></span></label>';
        foreach ($profissionais as $p) {
            $sel = (string)($post['ag_profissional_id'] ?? '') === (string)$p['id'] ? ' checked' : '';
            $foto = $p['foto'] ? '<img src="' . e($p['foto']) . '" alt="" loading="lazy">' : '<span class="gds-mini" aria-hidden="true">' . e(frag_iniciais($p['nome'])) . '</span>';
            $html .= '<label class="gds-op"><input type="radio" name="ag_profissional_id" value="' . (int)$p['id'] . '" data-nome="' . e($p['nome']) . '"' . $sel . '>' . $foto
                   . '<span class="gds-op-txt"><strong>' . e($p['nome']) . '</strong><small>' . e($p['especialidade']) . '</small></span></label>';
        }
        $html .= '</div></section>';
    }

    $html .= '<section class="gds-passo" data-gds-passo="data"><h3>Escolha o dia e o horário</h3><div class="gds-cal-wrap">'
           . '<div class="gds-cal"><div class="gds-cal-topo"><button type="button" class="gds-cal-nav" data-gds-mes="-1" aria-label="Mês anterior">‹</button><span data-gds-mes-rot aria-live="polite"></span><button type="button" class="gds-cal-nav" data-gds-mes="1" aria-label="Próximo mês">›</button></div>'
           . '<div class="gds-cal-sem"><span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span></div><div class="gds-cal-grade" data-gds-dias></div></div>'
           . '<div data-gds-horas><p class="gds-mute">Escolha um dia no calendário para ver os horários livres.</p></div></div>'
           . '<input type="hidden" name="ag_data_hora" value="' . e($post['ag_data_hora'] ?? '') . '" data-gds-valor>'
           . '<noscript><p class="gds-alerta gds-alerta-erro">Ative o JavaScript para escolher o horário, ou fale com a gente pelo WhatsApp.</p></noscript></section>';

    $html .= '<section class="gds-passo" data-gds-passo="dados"><h3>Seus dados</h3><div class="gds-resumo" data-gds-resumo></div>'
           . '<div class="gds-campo"><label for="ag_nome">Nome completo</label><input type="text" id="ag_nome" name="ag_nome" maxlength="120" required autocomplete="name" value="' . e($post['ag_nome'] ?? '') . '"></div>'
           . '<div class="gds-linha"><div class="gds-campo"><label for="ag_telefone">WhatsApp</label><input type="tel" id="ag_telefone" name="ag_telefone" maxlength="20" required autocomplete="tel" inputmode="tel" placeholder="(11) 99999-9999" data-gds-tel value="' . e($post['ag_telefone'] ?? '') . '"></div>'
           . '<div class="gds-campo"><label for="ag_email">E-mail (opcional)</label><input type="email" id="ag_email" name="ag_email" maxlength="150" autocomplete="email" value="' . e($post['ag_email'] ?? '') . '"></div></div>';
    if (!empty($agenda['tem_cupons']) && $servicos) {
        $html .= '<div class="gds-campo"><label for="ag_cupom">Cupom de desconto (opcional)</label><div class="gds-cupom-campo"><input type="text" id="ag_cupom" name="ag_cupom" maxlength="30" autocomplete="off" value="' . e($post['ag_cupom'] ?? '') . '"><button type="button" class="gds-btn-2" data-gds-aplicar-cupom>Aplicar</button></div><p class="gds-cupom-msg" data-gds-cupom-msg aria-live="polite"></p></div>';
    }
    $html .= '<div class="gds-campo"><label for="ag_obs">Alguma observação? (opcional)</label><textarea id="ag_obs" name="ag_observacoes" rows="2" maxlength="500">' . e($post['ag_observacoes'] ?? '') . '</textarea></div>'
           . '<button type="submit" class="gds-btn gds-btn-bloco" data-gds-confirmar>' . frag_icone('check') . ' Confirmar agendamento</button>'
           . '<p class="gds-nota-lgpd">Usamos seus dados só para confirmar e lembrar do seu horário.</p></section>';

    $html .= '<div class="gds-nav-passos" data-gds-nav hidden><button type="button" class="gds-btn-2" data-gds-voltar>' . frag_icone('volta') . ' Voltar</button><button type="button" class="gds-btn" data-gds-avancar>Continuar ' . frag_icone('seta') . '</button></div>';
    return $html . '</form></div>';
}

function frag_agendamento_confirmado($estado, $empresa) {
    $d = $estado['detalhes'] ?? [];
    $html = '<div class="gds-c gds-agendamento"><div class="gds-sucesso" data-gds-sucesso><div class="gds-sucesso-ico">' . frag_icone('check') . '</div>'
          . '<h3>Agendamento solicitado!</h3><p class="gds-mute">' . (!empty($d['cliente_nome']) ? e(explode(' ', $d['cliente_nome'])[0]) . ', a' : 'A') . 'notamos tudo. Você receberá a confirmação em breve.</p>'
          . (!empty($estado['wa_automatico']) ? '<p class="gds-aviso-wa">' . frag_icone('whats') . ' Mandamos os detalhes para o seu WhatsApp.</p>' : '')
          . '<div class="gds-resumo">';
    foreach ([['Serviço', $d['servico_nome'] ?? ''], ['Profissional', $d['profissional_nome'] ?? ''], ['Quando', $d['data_formatada'] ?? '']] as [$r, $v]) {
        if ($v !== '') $html .= '<div><span>' . $r . '</span><span>' . e($v) . '</span></div>';
    }
    if (!empty($d['cupom'])) $html .= '<div><span>Cupom</span><span>' . e($d['cupom']) . ' (−' . e($d['desconto']) . '%)</span></div>';
    if (!empty($d['valor'])) $html .= '<div class="gds-total"><span>Valor</span><span>' . e($d['valor']) . '</span></div>';
    $html .= '</div><div class="gds-acoes">';
    if (!empty($d['link_calendario'])) $html .= '<a class="gds-btn-2" href="' . e($d['link_calendario']) . '" target="_blank" rel="noopener">' . frag_icone('calend') . ' Google Agenda</a>';
    if (!empty($d['ics'])) $html .= '<a class="gds-btn-2" href="' . e($d['ics']) . '" download="agendamento.ics">' . frag_icone('download') . ' iPhone / Outlook</a>';
    if (!empty($d['empresa_whatsapp'])) {
        $msg = 'Olá! Acabei de agendar' . (!empty($d['servico_nome']) ? ' ' . $d['servico_nome'] : '') . (!empty($d['data_formatada']) ? ' para ' . $d['data_formatada'] : '') . (!empty($d['cliente_nome']) ? ' (nome: ' . $d['cliente_nome'] . ')' : '') . '. Pode confirmar?';
        $html .= '<a class="gds-btn gds-btn-wa" href="' . e(frag_link_whatsapp($d['empresa_whatsapp'], $msg)) . '" target="_blank" rel="noopener">' . frag_icone('whats') . ' Confirmar pelo WhatsApp</a>';
    }
    $html .= '</div>';
    if (!empty($d['link_gerenciar'])) $html .= '<p style="margin-top:16px;font-size:.88rem"><a href="' . e($d['link_gerenciar']) . '">Precisa cancelar? Gerencie seu agendamento aqui</a> — guarde este link.</p>';
    $html .= '</div>';
    if (!empty($estado['pix'])) {
        $pix = $estado['pix'];
        $html .= '<div class="gds-pix"><h3>Pague com PIX e garanta seu horário</h3>';
        if (!empty($pix['qr_base64'])) $html .= '<img src="data:image/png;base64,' . e($pix['qr_base64']) . '" alt="QR Code PIX">';
        $html .= '<div class="gds-campo"><label for="gds-pix-cod">PIX copia e cola</label><textarea id="gds-pix-cod" readonly rows="3">' . e($pix['qr_code']) . '</textarea></div>'
               . '<button type="button" class="gds-btn" data-gds-copiar="' . e($pix['qr_code']) . '">Copiar código PIX</button></div>';
    } elseif (!empty($estado['pix_erro'])) {
        $html .= '<div class="gds-alerta gds-alerta-erro" style="margin-top:14px">Não foi possível gerar o PIX agora. Seu horário está reservado do mesmo jeito.</div>';
    }
    return $html . '</div>';
}

// ------------------------------------------------------------
// <head>: CSS dos componentes + cores do tema + script
// ------------------------------------------------------------
function frag_css_apoio($tema = []) {
    $accent = preg_match('/^#[0-9a-fA-F]{6}$/', $tema['accent'] ?? '') ? $tema['accent'] : '#2563eb';
    return '<link rel="stylesheet" href="/assets/css/gds-componentes.css?v=5">'
         . '<style id="gds-tema">:root{--gds-tema-accent:' . $accent . ';--gds-tema-on-accent:' . frag_cor_contraste($accent) . ';}</style>'
         . '<script src="/assets/js/gds-site.js?v=5" defer></script>';
}

// Texto branco ou escuro, o que tiver mais contraste com a cor de fundo
function frag_cor_contraste($hex) {
    return frag_luminancia($hex) > 0.45 ? '#111111' : '#ffffff';
}

function frag_luminancia($hex) {
    $hex = ltrim((string)$hex, '#');
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) return 0.5;
    $lin = function ($c) { $c = hexdec($c) / 255; return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4; };
    return 0.2126 * $lin(substr($hex, 0, 2)) + 0.7152 * $lin(substr($hex, 2, 2)) + 0.0722 * $lin(substr($hex, 4, 2));
}

// ------------------------------------------------------------
// CARRINHO / PEDIDO PELO SITE (delivery e retirada)
// Barra flutuante + painel com itens, entrega/retirada, pagamento e dados.
// O total é recalculado no servidor com os preços do banco.
// ------------------------------------------------------------
function frag_carrinho($empresa, $estado = []) {
    $taxa = (float)($empresa['taxa_entrega'] ?? 0);
    $minimo = (float)($empresa['pedido_minimo'] ?? 0);
    $entrega = !isset($empresa['aceita_entrega']) || $empresa['aceita_entrega'] === null || pg_bool($empresa['aceita_entrega']);
    $retirada = !isset($empresa['aceita_retirada']) || $empresa['aceita_retirada'] === null || pg_bool($empresa['aceita_retirada']);
    $formas = array_values(array_filter(array_map('trim', explode(',', (string)($empresa['formas_pagamento'] ?? '') ?: 'Pix, Cartão na entrega, Dinheiro'))));
    $p = $estado['post'] ?? [];
    $ok = !empty($estado['ok']);
    $html = '<div class="gds-c gds-carrinho-raiz" data-gds-carrinho data-empresa="' . (int)$empresa['id'] . '" data-taxa="' . $taxa . '" data-minimo="' . $minimo . '"'
          . ($ok ? ' data-limpar="1" data-aberto="1"' : (!empty($estado['erro']) ? ' data-aberto="1"' : '')) . '>';
    // Barra flutuante
    $html .= '<button type="button" class="gds-carrinho-barra" data-gds-carrinho-abrir hidden><span class="gds-carrinho-qtd" data-gds-qtd>0</span>'
           . '<span>Ver pedido</span><strong data-gds-subtotal>R$ 0,00</strong></button>';
    // Painel
    $html .= '<div class="gds-carrinho-fundo" data-gds-carrinho-fechar hidden></div>'
           . '<section class="gds-carrinho" role="dialog" aria-modal="true" aria-labelledby="gds-carrinho-titulo" hidden>'
           . '<header><h3 id="gds-carrinho-titulo">' . frag_icone('sacola') . ' Seu pedido</h3><button type="button" class="gds-carrinho-x" data-gds-carrinho-fechar aria-label="Fechar">' . frag_icone('x') . '</button></header>';
    if ($ok) {
        $d = $estado;
        $html .= '<div class="gds-carrinho-corpo"><div class="gds-sucesso" data-gds-sucesso><div class="gds-sucesso-ico">' . frag_icone('check') . '</div>'
               . '<h3>Pedido nº ' . (int)$d['codigo'] . ' recebido!</h3><p class="gds-mute">' . e(explode(' ', $d['nome'] ?? '')[0]) . ', já estamos cuidando dele.</p>'
               . (!empty($d['wa_automatico']) ? '<p class="gds-aviso-wa">' . frag_icone('whats') . ' Você vai receber as atualizações no seu WhatsApp.</p>' : '')
               . '<div class="gds-resumo">';
        foreach ($d['itens'] as $i) $html .= '<div><span>' . (int)$i['qtd'] . '× ' . e($i['nome']) . '</span><span>' . e(frag_preco($i['preco'] * $i['qtd'])) . '</span></div>';
        if ($d['taxa'] > 0) $html .= '<div><span>Entrega</span><span>' . e(frag_preco($d['taxa'])) . '</span></div>';
        $html .= '<div class="gds-total"><span>Total</span><span>' . e(frag_preco($d['total'])) . '</span></div></div>'
               . '<div class="gds-acoes">' . (!empty($d['link']) ? '<a class="gds-btn" href="' . e($d['link']) . '">Acompanhar pedido</a>' : '');
        if (empty($d['wa_automatico']) && so_digitos_frag($empresa['whatsapp'] ?? '')) {
            $html .= '<a class="gds-btn gds-btn-wa" target="_blank" rel="noopener" href="' . e(frag_link_whatsapp($empresa['whatsapp'], $d['msg_whatsapp'] ?? '')) . '">' . frag_icone('whats') . ' Enviar pelo WhatsApp</a>';
        }
        return $html . '</div></div></div></section></div>';
    }
    $radio = function ($valor, $rotulo, $checked) {
        return '<label class="gds-op gds-op-mini"><input type="radio" name="pd_tipo_entrega" value="' . $valor . '"' . ($checked ? ' checked' : '') . ' required><span class="gds-op-txt"><strong>' . $rotulo . '</strong></span></label>';
    };
    $tipoSel = $p['pd_tipo_entrega'] ?? ($entrega ? 'entrega' : 'retirada');
    $html .= '<form method="post" class="gds-carrinho-corpo" data-gds-carrinho-form novalidate>' . frag_alerta_erro($estado['erro'] ?? '')
           . '<input type="hidden" name="fazer_pedido" value="1"><input type="hidden" name="pd_itens" data-gds-itens value="' . e($p['pd_itens'] ?? '') . '">'
           . '<ul class="gds-carrinho-itens" data-gds-lista></ul><p class="gds-mute gds-carrinho-vazio" data-gds-vazio>Seu pedido está vazio. Escolha os itens no cardápio.</p>'
           . '<div class="gds-carrinho-dados" data-gds-dados>'
           . '<div class="gds-opcoes gds-opcoes-2">' . ($entrega ? $radio('entrega', '🛵 Entrega' . ($taxa > 0 ? ' (+' . frag_preco($taxa) . ')' : ''), $tipoSel === 'entrega') : '')
           . ($retirada ? $radio('retirada', '🏪 Retirar no local', $tipoSel === 'retirada' || !$entrega) : '') . '</div>'
           . ($entrega ? '<div class="gds-campo" data-gds-endereco><label for="pd_endereco">Endereço de entrega</label><input type="text" id="pd_endereco" name="pd_endereco" maxlength="250" autocomplete="street-address" placeholder="Rua, número, bairro, complemento" value="' . e($p['pd_endereco'] ?? '') . '"></div>' : '')
           . '<div class="gds-linha2"><div class="gds-campo"><label for="pd_nome">Seu nome</label><input type="text" id="pd_nome" name="pd_nome" required maxlength="120" autocomplete="name" value="' . e($p['pd_nome'] ?? '') . '"></div>'
           . '<div class="gds-campo"><label for="pd_telefone">WhatsApp</label><input type="tel" id="pd_telefone" name="pd_telefone" required inputmode="tel" autocomplete="tel" data-gds-tel placeholder="(00) 00000-0000" value="' . e($p['pd_telefone'] ?? '') . '"></div></div>'
           . '<div class="gds-linha2"><div class="gds-campo"><label for="pd_pagamento">Pagamento</label><select id="pd_pagamento" name="pd_pagamento" data-gds-pagamento>';
    foreach ($formas as $f) $html .= '<option' . (($p['pd_pagamento'] ?? '') === $f ? ' selected' : '') . '>' . e($f) . '</option>';
    $html .= '</select></div><div class="gds-campo" data-gds-troco hidden><label for="pd_troco">Troco para quanto?</label><input type="text" id="pd_troco" name="pd_troco" inputmode="decimal" placeholder="Ex: 100" value="' . e($p['pd_troco'] ?? '') . '"></div></div>'
           . '<div class="gds-campo"><label for="pd_obs">Observações (opcional)</label><textarea id="pd_obs" name="pd_obs" rows="2" maxlength="500" placeholder="Ex: sem cebola, ponto da carne, portão azul">' . e($p['pd_obs'] ?? '') . '</textarea></div>'
           . frag_honeypot()
           . '</div><div class="gds-carrinho-rodape"><div class="gds-carrinho-totais"><div><span>Subtotal</span><span data-gds-subtotal>R$ 0,00</span></div>'
           . '<div data-gds-linha-taxa><span>Entrega</span><span data-gds-taxa>' . e(frag_preco($taxa)) . '</span></div>'
           . '<div class="gds-total"><span>Total</span><strong data-gds-total>R$ 0,00</strong></div></div>'
           . ($minimo > 0 ? '<p class="gds-mute gds-carrinho-minimo" data-gds-minimo-aviso hidden>Pedido mínimo: ' . e(frag_preco($minimo)) . '</p>' : '')
           . (!empty($empresa['tempo_entrega']) ? '<p class="gds-mute" style="margin:4px 0 0;font-size:.85rem">⏱ ' . e($empresa['tempo_entrega']) . '</p>' : '')
           . '<button type="submit" class="gds-btn gds-btn-bloco" data-gds-finalizar>Fazer pedido</button></div></form></section></div>';
    return $html;
}
