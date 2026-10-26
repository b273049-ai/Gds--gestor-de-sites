<?php
// ============================================================
// FORMA DE ATENDIMENTO — como o negócio converte o visitante.
// Decide o componente principal do site, o texto dos botões dos
// serviços e o que a IA deve priorizar.
//   agendamento → marca horário (barbearia, clínica, salão...)
//   orcamento   → pede orçamento com data/detalhes (DJ, fotógrafo, obras...)
//   contato     → conversa pelo WhatsApp/formulário (imobiliária, SaaS...)
//   catalogo    → escolhe produto/prato e pede pelo WhatsApp (restaurante, loja)
// Ordem de prioridade: escolha manual da empresa > segmento > padrão.
// ============================================================

function atendimento_modos() {
    return [
        'agendamento' => ['nome' => 'Agendamento online', 'desc' => 'O cliente escolhe o serviço, o profissional, o dia e o horário.', 'botao' => 'Agendar', 'cta' => 'Agendar horário', 'componente' => '{{AGENDAMENTO}}', 'ancora' => 'agendar'],
        'orcamento'   => ['nome' => 'Pedido de orçamento', 'desc' => 'O cliente escolhe o serviço, informa data/local/detalhes e pede orçamento.', 'botao' => 'Pedir orçamento', 'cta' => 'Pedir orçamento', 'componente' => '{{ORCAMENTO}}', 'ancora' => 'orcamento'],
        'contato'     => ['nome' => 'Contato / WhatsApp', 'desc' => 'O cliente fala com a empresa pelo WhatsApp ou formulário.', 'botao' => 'Quero saber mais', 'cta' => 'Fale com a gente', 'componente' => '{{CONTATO}}', 'ancora' => 'contato'],
        'catalogo'    => ['nome' => 'Catálogo com pedido pelo WhatsApp', 'desc' => 'O cliente vê produtos/cardápio com preço e pede pelo WhatsApp.', 'botao' => 'Pedir', 'cta' => 'Ver cardápio / produtos', 'componente' => '{{SERVICOS}}', 'ancora' => 'servicos'],
    ];
}

function atendimento_modo_valido($m) {
    return is_string($m) && isset(atendimento_modos()[$m]);
}

// Busca segmento + forma de atendimento de uma empresa (tolerante a banco sem as colunas novas)
function atendimento_info_empresa($empresaId) {
    global $pdo;
    try {
        $st = $pdo->prepare("SELECT e.modo_atendimento AS modo_empresa, e.segmento_descricao, s.nome AS segmento_nome, s.slug AS segmento_slug,
                s.modo_atendimento AS modo_segmento, s.descricao_ia, s.cta, s.exemplos_servicos
            FROM empresas e LEFT JOIN segmentos s ON s.id = e.segmento_id WHERE e.id=?");
        $st->execute([$empresaId]);
        $r = $st->fetch() ?: [];
    } catch (PDOException $e) {
        $st = $pdo->prepare("SELECT s.nome AS segmento_nome, s.slug AS segmento_slug FROM empresas e LEFT JOIN segmentos s ON s.id = e.segmento_id WHERE e.id=?");
        $st->execute([$empresaId]);
        $r = $st->fetch() ?: [];
    }
    $r['modo'] = atendimento_modo_valido($r['modo_empresa'] ?? null) ? $r['modo_empresa']
               : (atendimento_modo_valido($r['modo_segmento'] ?? null) ? $r['modo_segmento'] : 'agendamento');
    $r['modo_origem'] = atendimento_modo_valido($r['modo_empresa'] ?? null) ? 'empresa' : (atendimento_modo_valido($r['modo_segmento'] ?? null) ? 'segmento' : 'padrao');
    $r['segmento_texto'] = trim(($r['segmento_slug'] ?? '') === 'outro' || empty($r['segmento_nome']) ? ($r['segmento_descricao'] ?? '') : $r['segmento_nome']) ?: 'não informado';
    return $r;
}

// Forma de atendimento já resolvida a partir de uma linha "empresa" (site público)
function atendimento_modo_da_linha($empresa) {
    if (atendimento_modo_valido($empresa['modo_atendimento'] ?? null)) return $empresa['modo_atendimento'];
    if (atendimento_modo_valido($empresa['modo_segmento'] ?? null)) return $empresa['modo_segmento'];
    return 'agendamento';
}

// Campos "Segmento + Outro (descreva) + Como os clientes contratam" usados nos
// formulários de empresa (cadastro e edição no admin)
function atendimento_campos_form($segmentos, $segId, $segDescricao, $modo) {
    $modos = atendimento_modos();
    $opts = (string)$segId === '' ? '<option value="">— escolha o segmento —</option>' : '';
    $modoSeg = [];
    foreach ($segmentos as $s) {
        $modoSeg[$s['id']] = $s['modo_atendimento'] ?? '';
        $opts .= '<option value="' . (int)$s['id'] . '" data-slug="' . e($s['slug'] ?? '') . '"' . ((string)$segId === (string)$s['id'] ? ' selected' : '') . '>' . e($s['nome']) . '</option>';
    }
    $optsModo = '<option value="">Automático (pelo segmento)</option>';
    foreach ($modos as $k => $m) $optsModo .= '<option value="' . e($k) . '"' . ($modo === $k ? ' selected' : '') . '>' . e($m['nome']) . '</option>';
    $nomesModo = array_map(fn($m) => $m['nome'], $modos);
    return '<div class="form-row">'
        . '<div><label>Segmento</label><select name="segmento_id" id="campoSegmento" required>' . $opts . '</select></div>'
        . '<div><label>Como os clientes contratam</label><select name="modo_atendimento" id="campoModo">' . $optsModo . '</select>'
        . '<p class="dica" id="dicaModo"></p></div></div>'
        . '<div id="campoSegDesc"><label>Que tipo de negócio é? (quando escolher "Outro")</label><input type="text" name="segmento_descricao" maxlength="150" value="' . e($segDescricao) . '" placeholder="Ex: venda de sites e marketing para pequenas empresas"></div>'
        . '<script>(() => { const s = document.getElementById("campoSegmento"), m = document.getElementById("campoModo"), d = document.getElementById("campoSegDesc"), dica = document.getElementById("dicaModo");'
        . 'const modoSeg = ' . json_encode($modoSeg) . ', nomes = ' . json_encode($nomesModo, JSON_UNESCAPED_UNICODE) . ';'
        . 'const at = () => { const o = s.selectedOptions[0]; d.hidden = !o || o.dataset.slug !== "outro"; const ms = modoSeg[s.value] || "agendamento";'
        . 'dica.textContent = m.value ? "" : "Pelo segmento: " + (nomes[ms] || ms) + ". A IA também ajusta isso ao criar o site."; };'
        . 's.addEventListener("change", at); m.addEventListener("change", at); at(); })();</script>';
}
