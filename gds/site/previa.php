<?php
// ============================================================
// PRÉVIA DE SITE PARA UM PROSPECT: /previa/CODIGO
// Monta, na hora, o site do negócio encontrado na prospecção (nome, nicho,
// fotos e avaliações reais do Google), com uma faixa "Quero este site".
// Avisa o dono da plataforma quando o prospect abre ou pede o site.
// ============================================================
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/site_render.php';
require_once __DIR__ . '/../includes/migracoes.php';
require_once __DIR__ . '/../includes/prospeccao.php';
gds_garantir_schema();
if (!function_exists('e')) { function e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); } }

$token = preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? ''));
$p = null;
if ($token !== '') { $st = $pdo->prepare("SELECT * FROM prospects WHERE token=?"); $st->execute([$token]); $p = $st->fetch(); }
if (!$p || $p['status'] === 'descartado') {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Prévia indisponível</title></head>'
       . '<body style="font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#f5f6fa;color:#333;text-align:center;padding:20px"><div><h1 style="font-size:22px">Esta prévia não está mais disponível.</h1></div></body></html>';
    exit;
}
$marca = get_config('mkt_marca', '') ?: 'GDS Sites';
$numeroVendas = preg_replace('/\D/', '', get_config('prosp_whatsapp_alerta', '') ?: get_config('mkt_whatsapp', ''));
$ehAdmin = ($_SESSION['role'] ?? '') === 'admin';
$robo = preg_match('/bot|crawl|spider|preview|facebookexternalhit|whatsapp|telegram|slack|discord|headless|lighthouse/i', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

// "Quero este site": registra, avisa e abre o WhatsApp de quem vende
if (isset($_GET['quero'])) {
    if (!$ehAdmin) {
        $pdo->prepare("UPDATE prospects SET quer_site_em=COALESCE(quer_site_em, NOW()), status=CASE WHEN status IN ('novo','contatado','respondeu') THEN 'negociando' ELSE status END, atualizado_em=NOW() WHERE id=?")->execute([$p['id']]);
        prosp_alertar("🔥 {$p['nome']} clicou em \"Quero este site\" na prévia!\nTelefone: " . ($p['telefone'] ?: '—') . "\nAbra a Prospecção para responder.");
    }
    $msg = "Olá! Vi a prévia do site da {$p['nome']} e quero saber como ter o nosso.";
    header('Location: ' . ($numeroVendas ? 'https://wa.me/' . (strlen($numeroVendas) <= 11 ? '55' : '') . $numeroVendas . '?text=' . rawurlencode($msg) : '/'));
    exit;
}

// Registra a visita (sem contar o próprio admin nem as pré-visualizações de link do WhatsApp)
if (!$ehAdmin && !$robo) {
    $primeira = empty($p['previa_aberta_em']);
    $pdo->prepare("UPDATE prospects SET previa_visitas=previa_visitas+1, previa_aberta_em=COALESCE(previa_aberta_em, NOW()), atualizado_em=NOW() WHERE id=?")->execute([$p['id']]);
    if ($primeira) prosp_alertar("👀 {$p['nome']} acabou de abrir a prévia do site!\nÉ a hora de chamar: " . ($p['whatsapp'] ? 'https://wa.me/' . $p['whatsapp'] : ($p['telefone'] ?: '')));
}

// ---------- monta o "site" do prospect ----------
$seg = null;
try { $st = $pdo->prepare("SELECT * FROM segmentos WHERE slug=?"); $st->execute([$p['segmento_slug'] ?: '']); $seg = $st->fetch() ?: null; } catch (PDOException $e) {}
$fotos = prosp_fotos_previa($p);
$corMarca = prosp_cor_marca($p);     // logo do WhatsApp/Instagram > cor no nome > fotos reais
$logoMarca = prosp_logo_local($p);
$reviews = (array)json_decode((string)($p['avaliacoes_texto'] ?? ''), true);
$horario = (array)json_decode((string)($p['horario'] ?? ''), true);
$bairro = $p['bairro'] ?: $p['cidade'];
$sobre = "A {$p['nome']} atende em " . ($bairro ?: 'sua região') . ($p['nota'] && $p['avaliacoes'] ? ", com nota " . number_format((float)$p['nota'], 1, ',', '') . " no Google e {$p['avaliacoes']} avaliações de clientes" : '') . ".\n\n"
       . "Este é um exemplo de como o site de vocês pode ficar: com " . prosp_recurso(($p['categoria'] ?? '') . ' ' . $p['nome']) . ", confirmação automática no WhatsApp e tudo que o cliente precisa para escolher vocês.";
$empresa = [
    'id' => 0, 'nome' => $p['nome'], 'slug' => 'previa', 'logo' => $logoMarca, 'email' => null, 'instagram' => null, 'status' => 'ativo', 'publicado' => true,
    'segmento_slug' => $p['segmento_slug'], 'segmento_nome' => $seg['nome'] ?? $p['categoria'], 'segmento_grupo' => $seg['grupo'] ?? null, 'segmento_descricao' => $p['categoria'],
    'modo_segmento' => $seg['modo_atendimento'] ?? null, 'segmento_cta' => $seg['cta'] ?? null, 'modo_atendimento' => null, 'modulos' => null,
    'endereco' => $p['endereco'], 'whatsapp' => $p['whatsapp'] ? substr($p['whatsapp'], 2) : '', 'telefone' => $p['telefone'], 'whatsapp_mensagem' => '', 'whatsapp_flutuante' => true,
    'titulo' => null, 'subtitulo' => null, 'sobre' => $sobre, 'seo_titulo' => $p['nome'], 'seo_descricao' => '', 'cor_primaria' => $corMarca, 'cor_secundaria' => null,
    'estilo' => null, 'animacoes' => 'cinema', 'secoes' => null, 'layout' => null, 'banner_imagem' => $fotos[0] ?? null, 'galeria_imagens' => implode(',', array_slice($fotos, 1)),
    'fotos_banco' => null, 'html_gerado' => null, 'ia_modo_total' => false, 'ia_tema' => null, 'wa_modo' => 'plataforma', 'pedido_online' => true,
    'aceita_entrega' => true, 'aceita_retirada' => true, 'taxa_entrega' => 0, 'pedido_minimo' => 0, 'formas_pagamento' => 'Pix, Cartão, Dinheiro', 'tempo_entrega' => null,
    'horario_abertura' => '09:00', 'horario_fechamento' => '19:00', 'dias_funcionamento' => '1,2,3,4,5,6', 'intervalo_agenda' => 30, 'permite_pix' => false,
];
$dados = [
    'servicos' => prosp_servicos_exemplo($p['segmento_slug'], $p['categoria']), 'profissionais' => [], 'cupons' => [], 'blocos' => [],
    'avaliacoes' => array_map(fn($r) => ['nota' => (int)$r['nota'], 'comentario' => $r['texto'], 'criado_em' => date('Y-m-d'), 'autor' => $r['autor']], $reviews),
    'avaliacoes_resumo' => ['total' => (int)$p['avaliacoes'], 'media' => (float)$p['nota'], 'dist' => []],
    'galeria' => array_slice($fotos, 1), 'maps_key' => '', 'analytics_id' => '',
];
$dados['agenda'] = agenda_config($empresa) + ['tem_cupons' => false];
$dados['status'] = agenda_status_agora($empresa);
// A cor da marca precisa aparecer bem no fundo da identidade do nicho
if ($corMarca) $empresa['cor_primaria'] = prosp_cor_para_fundo($corMarca, site_identidade($empresa)[1]['bg']);
$dados['tema'] = ['accent' => $empresa['cor_primaria'] ?: '#2563eb'];
$dados['modo'] = atendimento_modo_da_linha($empresa);
$dados['modulos'] = modulos_da_empresa($empresa)['ativos'];

// Prévia com o visual Limpo (o mesmo que o site do cliente terá) e o horário real do Google
require_once __DIR__ . '/../includes/site_limpo.php';
if ($horario) $dados['horario_lista'] = $horario;
$empresa['sobre'] = trim(preg_replace('/\n\nEste é um exemplo.*$/su', '', $empresa['sobre']));
$html = site_render_limpo($empresa, $dados, site_estado_vazio(), true);

// Faixa da prévia (no lugar do aviso padrão) + botão "Quero este site"
$faixa = '<style>.gds-previa{position:fixed;left:50%;transform:translateX(-50%);bottom:calc(18px + env(safe-area-inset-bottom,0px));z-index:10050;display:flex;gap:10px;align-items:center;'
       . 'max-width:calc(100% - 24px);background:rgba(11,13,23,.92);backdrop-filter:blur(10px);color:#fff;font:600 13px/1.35 system-ui,sans-serif;padding:8px 8px 8px 16px;border-radius:999px;box-shadow:0 18px 50px -12px rgba(0,0,0,.6)}'
       . '.gds-previa a{flex:none;background:#25d366;color:#06240f;text-decoration:none;padding:9px 16px;border-radius:999px;font-weight:800}'
       . '.gds-previa .curto{display:none}@media(max-width:760px){.gds-previa{left:12px;right:12px;transform:none;max-width:none;justify-content:space-between;border-radius:16px;bottom:calc(88px + env(safe-area-inset-bottom,0px))}'
       . '.gds-previa .longo{display:none}.gds-previa .curto{display:inline}}.gds-wa{display:none!important}</style>'
       . '<aside class="gds-previa" aria-label="Prévia do site"><span class="longo">✨ Prévia do site da <strong>' . e($p['nome']) . '</strong> · por ' . e($marca) . '</span><span class="curto">✨ Prévia do seu site</span><a href="?quero=1">Quero este site</a></aside>';
$html = preg_replace('#<div class="pt-preview-aviso">.*?</div>#s', '', $html, 1);
$html = str_replace('</body>', $faixa . '</body>', $html);
$html = str_replace('<head>', '<head><meta name="robots" content="noindex, nofollow">', $html);
// Horário real do Google, quando houver
if ($horario) {
    $lista = '<ul style="list-style:none;padding:0;margin:0;display:grid;gap:6px">' . implode('', array_map(fn($h) => '<li>' . e($h) . '</li>', $horario)) . '</ul>';
    $html = preg_replace('#(<h3 class="pt-h3">Horário de atendimento</h3>).*?(</div></div></div></section>)#s', '$1' . str_replace('$', '\\$', $lista) . '$2', $html, 1);
}
$html = str_replace('</body>', '<aside aria-label="Aviso" style="text-align:center;font:13px system-ui,sans-serif;color:var(--suave,#5f5f5f);background:var(--bg,#fff);padding:18px 16px 110px;position:relative;z-index:1">Prévia de demonstração criada por ' . e($marca) . ' para ' . e($p['nome']) . '. Não é o site oficial da empresa.</aside></body>', $html);
header('X-Robots-Tag: noindex, nofollow');
echo $html;
