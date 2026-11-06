<?php
// ============================================================
// MÓDULOS DO PAINEL DA EMPRESA — cada negócio vê só o que usa.
// Barbearia não vê delivery; hamburgueria não vê agenda; DJ vê
// orçamentos. O padrão vem da forma de atendimento e a empresa
// pode ligar/desligar módulos em Configurações → Módulos.
// ============================================================

function modulos_catalogo() {
    return [
        'agenda'     => ['rotulo' => 'Agenda e horários', 'desc' => 'Agendamento online, calendário, horários livres, lembretes.', 'icone' => 'calendario'],
        'equipe'     => ['rotulo' => 'Equipe / profissionais', 'desc' => 'Profissionais no site e na agenda ("agendar com fulano").', 'icone' => 'cracha'],
        'orcamentos' => ['rotulo' => 'Orçamentos', 'desc' => 'Pedido de orçamento pelo site, resposta com valor e aprovação do cliente.', 'icone' => 'prancheta'],
        'delivery'   => ['rotulo' => 'Pedidos / delivery', 'desc' => 'Carrinho no site, entrega ou retirada, acompanhamento do pedido.', 'icone' => 'pacote'],
        'cupons'     => ['rotulo' => 'Cupons de desconto', 'desc' => 'Cupons no site e no agendamento.', 'icone' => 'cupom'],
        'avaliacoes' => ['rotulo' => 'Avaliações', 'desc' => 'Clientes avaliam pelo site; você aprova o que aparece.', 'icone' => 'estrela'],
        'pix'        => ['rotulo' => 'Pagamento PIX', 'desc' => 'Cobrança PIX automática no agendamento (Mercado Pago).', 'icone' => 'cartao'],
        'imoveis'    => ['rotulo' => 'Imóveis', 'desc' => 'Vitrine de imóveis no site com filtros, fotos, ficha completa e agendamento de visita.', 'icone' => 'casa'],
    ];
}

// O que faz sentido para cada forma de atendimento
function modulos_padrao($modo) {
    return [
        'agendamento' => ['agenda', 'equipe', 'cupons', 'avaliacoes', 'pix'],
        'orcamento'   => ['orcamentos', 'avaliacoes'],
        'catalogo'    => ['delivery', 'cupons', 'avaliacoes'],
        'contato'     => ['avaliacoes'],
    ][$modo] ?? ['agenda', 'avaliacoes'];
}

// Módulos ligados para a empresa (padrão do tipo de negócio + escolhas dela)
function modulos_da_empresa($empresa) {
    $modo = atendimento_modo_da_linha($empresa); // modo da empresa > do segmento > agendamento
    $ativos = array_fill_keys(modulos_padrao($modo), true);
    // Ramos de imóveis já começam com a vitrine de imóveis
    if (in_array($empresa['segmento_slug'] ?? '', ['imobiliaria', 'construtora', 'loteamento', 'chales'], true)) $ativos['imoveis'] = true;
    $escolhas = json_decode((string)($empresa['modulos'] ?? ''), true) ?: [];
    foreach ($escolhas as $k => $v) if (isset(modulos_catalogo()[$k])) $ativos[$k] = (bool)$v;
    return ['modo' => $modo, 'ativos' => array_keys(array_filter($ativos))];
}

// Cache por requisição para a empresa logada
function modulos_atuais() {
    static $cache = null;
    if ($cache !== null) return $cache;
    global $pdo;
    $id = (int)empresa_id_atual();
    if (!$id) return $cache = ['modo' => 'agendamento', 'ativos' => array_keys(modulos_catalogo())];
    try {
        $st = $pdo->prepare("SELECT e.*, s.modo_atendimento AS modo_segmento, s.grupo AS segmento_grupo, s.slug AS segmento_slug FROM empresas e LEFT JOIN segmentos s ON s.id = e.segmento_id WHERE e.id=?");
        $st->execute([$id]);
        $e = $st->fetch() ?: [];
    } catch (PDOException $ex) {
        $e = [];
    }
    $r = modulos_da_empresa($e + ['id' => $id]);
    $r['empresa'] = $e;
    return $cache = $r;
}

function modulo_ativo($chave) {
    return in_array($chave, modulos_atuais()['ativos'], true);
}

// Nome do catálogo conforme o negócio
function modulos_rotulo_catalogo() {
    $m = modulos_atuais();
    $e = $m['empresa'] ?? [];
    if ($m['modo'] === 'catalogo') {
        return ($e['segmento_grupo'] ?? '') === 'Alimentação e Bebidas' || in_array($e['segmento_slug'] ?? '', ['restaurante', 'delivery'], true) ? 'Cardápio' : 'Produtos';
    }
    return ['orcamento' => 'Serviços e pacotes', 'contato' => 'Serviços / planos'][$m['modo']] ?? 'Serviços';
}

// Nome da tela de pedidos conforme os módulos
function modulos_rotulo_pedidos() {
    $orc = modulo_ativo('orcamentos'); $del = modulo_ativo('delivery');
    return $orc && $del ? 'Pedidos e orçamentos' : ($del ? 'Pedidos' : 'Orçamentos');
}

// Página de um módulo desligado: explica e oferece ligar
function exigir_modulo(...$chaves) {
    foreach ($chaves as $c) if (modulo_ativo($c)) return;
    $cat = modulos_catalogo();
    $nome = $cat[$chaves[0]]['rotulo'] ?? 'Este recurso';
    http_response_code(200);
    echo '<!DOCTYPE html><html lang="pt-br"><head>' . painel_head($nome) . '</head><body><div class="layout">';
    include __DIR__ . '/../empresa/_sidebar.php';
    echo '<main class="content"><div class="vazio-box" style="max-width:620px;margin:40px auto">' . icone($cat[$chaves[0]]['icone'] ?? 'engrenagem', 32)
       . '<h2>' . e($nome) . ' está desligado</h2><p>' . e($cat[$chaves[0]]['desc'] ?? '') . '</p>'
       . '<p>Seu painel mostra só o que combina com o seu tipo de negócio. Se você também trabalha assim, ligue em Configurações.</p>'
       . '<a class="btn" href="/empresa/configuracoes.php#modulos">Ligar em Configurações → Módulos</a></div></main></div></body></html>';
    exit;
}

// Taxa de entrega por bairro (empresas.bairros_entrega, JSON [{"bairro":"Centro","taxa":5}]).
// Lista vazia = taxa única (empresas.taxa_entrega).
function delivery_bairros($empresa) {
    $lista = [];
    foreach ((array)json_decode((string)($empresa['bairros_entrega'] ?? ''), true) as $b) {
        $nome = trim((string)($b['bairro'] ?? ''));
        if ($nome !== '') $lista[] = ['bairro' => $nome, 'taxa' => max(0, (float)($b['taxa'] ?? 0))];
    }
    return $lista;
}

// "Centro = 5" / "Jardim América; 7,50" (uma linha por bairro) → JSON
function delivery_bairros_de_texto($texto) {
    $lista = [];
    foreach (preg_split('/\r?\n/', (string)$texto) as $linha) {
        if (!preg_match('/^\s*(.+?)\s*[=;:|\t-]\s*(?:R\$\s*)?([\d.,]+)\s*$/u', $linha, $m)) continue;
        $valor = str_contains($m[2], ',') ? (float)str_replace(',', '.', str_replace('.', '', $m[2])) : (float)$m[2];
        $lista[] = ['bairro' => mb_substr(trim($m[1]), 0, 60), 'taxa' => round(max(0, $valor), 2)];
        if (count($lista) >= 80) break;
    }
    return $lista ? json_encode($lista, JSON_UNESCAPED_UNICODE) : null;
}

function delivery_bairros_texto($empresa) {
    return implode("\n", array_map(fn($b) => $b['bairro'] . ' = ' . number_format($b['taxa'], 2, ',', ''), delivery_bairros($empresa)));
}
