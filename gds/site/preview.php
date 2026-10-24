<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
require_once __DIR__ . '/../includes/auth.php';

// Autenticação: admin vê qualquer empresa, a empresa só vê a própria
if (empty($_SESSION['usuario_id'])) { http_response_code(403); echo 'Acesso negado.'; exit; }
$empresaIdSolicitada = (int)($_GET['empresa_id'] ?? 0);
if ($_SESSION['role'] !== 'admin' && (int)$_SESSION['empresa_id'] !== $empresaIdSolicitada) {
    http_response_code(403); echo 'Acesso negado.'; exit;
}

$modo = ($_GET['modo'] ?? 'template') === 'ia' ? 'ia' : 'template';

$stmt = $pdo->prepare("
    SELECT e.*, s.titulo, s.subtitulo, s.sobre, s.cor_primaria, s.cor_secundaria,
           s.galeria_imagens, s.seo_titulo, s.seo_descricao, s.layout, s.secoes, s.banner_imagem,
           s.html_gerado, seg.nome as segmento_nome, pl.permite_pix
    FROM empresas e
    JOIN sites s ON s.empresa_id = e.id
    LEFT JOIN segmentos seg ON seg.id = e.segmento_id
    LEFT JOIN planos pl ON pl.id = e.plano_id
    WHERE e.id = ? LIMIT 1
");
$stmt->execute([$empresaIdSolicitada]);
$empresa = $stmt->fetch();

if (!$empresa) { echo 'Empresa não encontrada.'; exit; }

if (!function_exists('e')) {
    function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
}

function psite_icon($nome) {
    $icones = [
        'calendario' => '<path d="M8 2v4M16 2v4M3 9h18M5 5h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z"/>',
        'usuarios'   => '<path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'chat'       => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
        'cartao'     => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'pin'        => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($icones[$nome] ?? '') . '</svg>';
}

$layout = in_array($empresa['layout'], ['classico','bold','editorial','grid']) ? $empresa['layout'] : 'classico';

$servicos = $pdo->prepare("SELECT * FROM servicos WHERE empresa_id=? AND ativo=true ORDER BY nome");
$servicos->execute([$empresa['id']]); $servicos = $servicos->fetchAll();

$profissionais = $pdo->prepare("SELECT * FROM profissionais WHERE empresa_id=? AND ativo=true ORDER BY nome");
$profissionais->execute([$empresa['id']]); $profissionais = $profissionais->fetchAll();

$cupons = $pdo->prepare("SELECT * FROM cupons WHERE empresa_id=? AND ativo=true AND (validade IS NULL OR validade >= CURRENT_DATE)");
$cupons->execute([$empresa['id']]); $cupons = $cupons->fetchAll();

$blocos = $pdo->prepare("SELECT * FROM site_blocos WHERE empresa_id=? AND ativo=true ORDER BY ordem, id");
$blocos->execute([$empresa['id']]); $blocos = $blocos->fetchAll();

$galeria = array_filter(array_map('trim', explode(',', $empresa['galeria_imagens'] ?? '')));

$corPrimaria = $empresa['cor_primaria'] ?: '#1a1a2e';
$corSecundaria = $empresa['cor_secundaria'] ?: '#16213e';

$avaliacoes = $pdo->prepare("
    SELECT av.*, c.nome as cliente_nome FROM avaliacoes av
    LEFT JOIN clientes c ON c.id = av.cliente_id
    WHERE av.empresa_id=? ORDER BY av.criado_em DESC LIMIT 5
");
$avaliacoes->execute([$empresa['id']]); $avaliacoes = $avaliacoes->fetchAll();

$tituloPagina = $empresa['seo_titulo'] ?: ($empresa['titulo'] ?: $empresa['nome']);
$descricaoPagina = $empresa['seo_descricao'] ?: ('Conheça ' . $empresa['nome']);
$googleMapsKey = trim(get_config('google_maps_key', ''));

// Pré-visualização nunca processa formulários de verdade (sem POST real)
$leadOk = false; $agendamentoOk = false; $agendamentoErro = '';

$bannerPreview = '<div style="position:sticky;top:0;z-index:999;background:#4f7cff;color:#fff;text-align:center;padding:6px;font-family:sans-serif;font-size:13px;">🔍 Pré-visualização — ' . ($modo === 'ia' ? 'versão gerada por IA' : 'template ' . e($layout)) . ' (formulários não enviam de verdade aqui)</div>';

// ============================================================
// MODO IA
// ============================================================
if ($modo === 'ia') {
    if (empty($empresa['html_gerado'])) {
        echo '<p style="font-family:sans-serif;padding:40px;text-align:center;color:#666;">Nenhum site gerado por IA ainda. Escreva o briefing e clique em "Gerar meu site com IA".</p>';
        exit;
    }
    require_once __DIR__ . '/../includes/site_fragmentos.php';
    $htmlFinal = str_replace(
        ['{{SOBRE}}', '{{SERVICOS}}', '{{EQUIPE}}', '{{GALERIA}}', '{{AGENDAMENTO}}', '{{AVALIACOES}}', '{{CONTATO}}', '{{MAPA}}', '{{CUPONS}}', '{{WHATSAPP_FLOAT}}'],
        [
            frag_sobre($empresa['sobre']), frag_servicos($servicos), frag_equipe($profissionais),
            frag_galeria($galeria), frag_agendamento($servicos, $profissionais, false, ''),
            frag_avaliacoes($avaliacoes), frag_contato(false), frag_mapa($empresa['endereco'], $googleMapsKey),
            frag_cupons($cupons), frag_whatsapp_float($empresa['whatsapp']),
        ],
        $empresa['html_gerado']
    );
    $htmlFinal = preg_replace('/<\/head>/i', frag_css_apoio() . '</head>', $htmlFinal, 1);
    $htmlFinal = preg_replace('/<body([^>]*)>/i', '<body$1>' . $bannerPreview, $htmlFinal, 1);
    echo $htmlFinal;
    exit;
}

// ============================================================
// MODO TEMPLATE (mesma lógica de renderização do site/index.php)
// ============================================================
$secaoHtml = [];
$secaoNav = [];

if (trim($empresa['sobre'] ?? '') !== '') {
    ob_start(); ?>
    <section id="sobre" class="psite-section"><div class="container"><h2>Sobre</h2><p class="lead"><?= nl2br(e($empresa['sobre'])) ?></p></div></section>
    <?php $secaoHtml['sobre'] = ob_get_clean(); $secaoNav['sobre'] = 'Sobre';
}
if (!empty($servicos)) {
    ob_start(); ?>
    <section id="servicos" class="psite-section"><div class="container"><h2>Serviços</h2><div class="psite-grid">
        <?php foreach ($servicos as $s): ?>
        <div class="psite-item"><h3><?= e($s['nome']) ?></h3><p><?= e($s['descricao']) ?></p><span class="psite-price">R$ <?= number_format($s['preco'],2,',','.') ?></span></div>
        <?php endforeach; ?>
    </div></div></section>
    <?php $secaoHtml['servicos'] = ob_get_clean(); $secaoNav['servicos'] = 'Serviços';
}
if (!empty($profissionais)) {
    ob_start(); ?>
    <section id="equipe" class="psite-section"><div class="container"><h2>Equipe</h2><div class="psite-team">
        <?php foreach ($profissionais as $p): ?>
        <div class="psite-person">
            <?php if ($p['foto']): ?><img src="<?= e($p['foto']) ?>" alt="<?= e($p['nome']) ?>"><?php else: ?><div class="avatar-fallback"><?= e(mb_substr($p['nome'],0,1)) ?></div><?php endif; ?>
            <h3><?= e($p['nome']) ?></h3><p><?= e($p['especialidade']) ?></p>
        </div>
        <?php endforeach; ?>
    </div></div></section>
    <?php $secaoHtml['equipe'] = ob_get_clean(); $secaoNav['equipe'] = 'Equipe';
}
if (!empty($galeria)) {
    ob_start(); ?>
    <section id="galeria" class="psite-section"><div class="container"><h2>Galeria</h2><div class="psite-gallery">
        <?php foreach ($galeria as $img): ?><img src="<?= e($img) ?>" alt="Foto" loading="lazy"><?php endforeach; ?>
    </div></div></section>
    <?php $secaoHtml['galeria'] = ob_get_clean(); $secaoNav['galeria'] = 'Galeria';
}
if (!empty($blocos)) {
    ob_start(); ?>
    <section id="destaques" class="psite-section"><div class="container">
        <?php foreach ($blocos as $b): ?><div class="psite-bloco"><h3><?= e($b['titulo']) ?></h3><p><?= e($b['conteudo']) ?></p></div><?php endforeach; ?>
    </div></section>
    <?php $secaoHtml['blocos'] = ob_get_clean(); $secaoNav['blocos'] = 'Destaques';
}
if (!empty($cupons)) {
    ob_start(); ?>
    <section id="cupons" class="psite-section"><div class="container"><h2>Cupons ativos</h2><div class="psite-grid" style="background:transparent;border:none;gap:12px;">
        <?php foreach ($cupons as $c): ?><div class="psite-cupom"><strong><?= e($c['codigo']) ?></strong> — <?= number_format($c['desconto_percentual'],0) ?>%<p style="color:var(--text-dim);font-size:13px;"><?= e($c['descricao']) ?></p></div><?php endforeach; ?>
    </div></div></section>
    <?php $secaoHtml['cupons'] = ob_get_clean(); $secaoNav['cupons'] = 'Cupons';
}
ob_start(); ?>
<section id="agendar" class="psite-section"><div class="container"><h2>Agendar horário</h2>
    <div class="psite-form-wrap"><div><p class="lead">Escolha o serviço, o profissional e o melhor horário.</p></div>
    <div><form method="post" class="psite-form" onsubmit="return false;">
        <label>Seu nome</label><input type="text" name="ag_nome">
        <label>Data e hora desejada</label><input type="datetime-local" name="ag_data_hora">
        <button type="submit" class="psite-btn">Solicitar agendamento</button>
    </form></div></div>
</div></section>
<?php $secaoHtml['agendar'] = ob_get_clean(); $secaoNav['agendar'] = 'Agendar';

if (!empty($avaliacoes)) {
    ob_start(); ?>
    <section id="avaliacoes" class="psite-section"><div class="container"><h2>Avaliações</h2>
        <?php foreach ($avaliacoes as $av): ?>
        <div class="psite-review"><strong><?= e($av['cliente_nome'] ?? 'Cliente') ?></strong><div class="psite-stars"><?= str_repeat('★', (int)$av['nota']) ?></div><p><?= e($av['comentario']) ?></p></div>
        <?php endforeach; ?>
    </div></section>
    <?php $secaoHtml['avaliacoes'] = ob_get_clean(); $secaoNav['avaliacoes'] = 'Avaliações';
}
ob_start(); ?>
<section id="contato" class="psite-section"><div class="container"><h2>Contato</h2>
    <form method="post" class="psite-form" style="max-width:420px;" onsubmit="return false;">
        <label>Nome</label><input type="text" name="nome">
        <label>Mensagem</label><textarea name="mensagem" rows="3"></textarea>
        <button type="submit" class="psite-btn psite-btn-outline">Enviar mensagem</button>
    </form>
</div></section>
<?php $secaoHtml['contato'] = ob_get_clean(); $secaoNav['contato'] = 'Contato';

$ordemPadrao = ['sobre','servicos','equipe','galeria','blocos','cupons','agendar','avaliacoes','contato'];
$ordemEscolhida = !empty($empresa['secoes']) ? array_filter(explode(',', $empresa['secoes'])) : $ordemPadrao;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($tituloPagina) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="/assets/css/site-publico.css">
<style>:root{ --accent: <?= e($corPrimaria) ?>; --accent-2: <?= e($corSecundaria) ?>; }</style>
</head>
<body class="layout-<?= e($layout) ?>">
<?= $bannerPreview ?>
<nav class="psite-nav">
    <div class="psite-brand"><?php if ($empresa['logo']): ?><img src="<?= e($empresa['logo']) ?>" alt="Logo"><?php endif; ?><?= e($empresa['nome']) ?></div>
    <div class="psite-nav-links"><?php foreach ($ordemEscolhida as $chave): if (isset($secaoNav[$chave])): ?><a href="#<?= $chave ?>" data-page-link="<?= $chave ?>" onclick="mostrarPagina('<?= $chave ?>');return false;"><?= e($secaoNav[$chave]) ?></a><?php endif; endforeach; ?></div>
</nav>
<?php if ($layout === 'grid'): ?>
<header class="psite-hero"><div class="psite-hero-grid">
    <div class="psite-hero-text">
        <div class="psite-tag"><span class="dot"></span><?= e($empresa['segmento_nome'] ?: 'Negócio local') ?></div>
        <h1><?= e($empresa['titulo'] ?: $empresa['nome']) ?></h1>
        <p><?= e($empresa['subtitulo'] ?: 'Conheça nosso trabalho e agende com a gente.') ?></p>
        <a href="#agendar" onclick="mostrarPagina(&#39;agendar&#39;);return false;" class="psite-btn">Agendar horário</a>
    </div>
    <?php if ($empresa['banner_imagem']): ?><div class="psite-hero-banner-wrap"><img src="<?= e($empresa['banner_imagem']) ?>" alt=""></div>
    <?php else: ?><div class="psite-hero-banner-wrap" style="background:linear-gradient(135deg,var(--accent),var(--accent-2));"></div><?php endif; ?>
</div></header>
<?php else: ?>
<header class="psite-hero"><div class="container psite-hero-inner">
    <div class="psite-tag"><span class="dot"></span><?= e($empresa['segmento_nome'] ?: 'Negócio local') ?></div>
    <h1><?= e($empresa['titulo'] ?: $empresa['nome']) ?></h1>
    <p><?= e($empresa['subtitulo'] ?: 'Conheça nosso trabalho e agende com a gente.') ?></p>
    <a href="#agendar" onclick="mostrarPagina(&#39;agendar&#39;);return false;" class="psite-btn">Agendar horário</a>
</div></header>
<?php endif; ?>
<div class="psite-features container">
    <div class="psite-feature"><?= psite_icon('calendario') ?> Agende online</div>
    <?php if (!empty($profissionais)): ?><div class="psite-feature"><?= psite_icon('usuarios') ?> Equipe especializada</div><?php endif; ?>
    <?php if ($empresa['whatsapp']): ?><div class="psite-feature"><?= psite_icon('chat') ?> Atendimento via WhatsApp</div><?php endif; ?>
    <?php if (pg_bool($empresa['permite_pix'] ?? false)): ?><div class="psite-feature"><?= psite_icon('cartao') ?> Pagamento via PIX</div><?php endif; ?>
    <?php if ($empresa['endereco']): ?><div class="psite-feature"><?= psite_icon('pin') ?> <?= e($empresa['endereco']) ?></div><?php endif; ?>
</div>
<div id="psite-paginas"><?php foreach ($ordemEscolhida as $chave): if (isset($secaoHtml[$chave])): ?><div class="psite-page" data-page="<?= $chave ?>"><?= $secaoHtml[$chave] ?></div><?php endif; endforeach; ?></div>
<footer class="psite-footer container"><span><?= e($empresa['nome']) ?></span><span>Site por GDS</span></footer>
<script>
function mostrarPagina(chave) {
    document.querySelectorAll('.psite-page').forEach(function(el){ el.classList.remove('psite-page-ativa'); });
    var alvo = document.querySelector('.psite-page[data-page="' + chave + '"]');
    if (alvo) { alvo.classList.add('psite-page-ativa'); }
    document.querySelectorAll('.psite-nav-links a').forEach(function(a){ a.classList.remove('psite-nav-ativo'); });
    var link = document.querySelector('.psite-nav-links a[data-page-link="' + chave + '"]');
    if (link) { link.classList.add('psite-nav-ativo'); }
    if (history.replaceState) { history.replaceState(null, '', '#' + chave); }
}
document.addEventListener('DOMContentLoaded', function() {
    var paginas = document.querySelectorAll('.psite-page');
    if (paginas.length === 0) return;
    mostrarPagina(paginas[0].dataset.page);
});
</script>
</body>
</html>
