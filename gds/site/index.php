<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/site_fragmentos.php';

$slug = $_GET['slug'] ?? '';
$hostAtual = preg_replace('/^www\./i', '', strtolower($_SERVER['HTTP_HOST'] ?? ''));

$baseSql = "
    SELECT e.*, s.titulo, s.subtitulo, s.sobre, s.publicado, s.cor_primaria, s.cor_secundaria,
           s.galeria_imagens, s.seo_titulo, s.seo_descricao, s.layout, s.secoes, s.banner_imagem,
           s.html_gerado, s.ia_modo_total,
           seg.nome as segmento_nome, pl.permite_pix
    FROM empresas e
    JOIN sites s ON s.empresa_id = e.id
    LEFT JOIN segmentos seg ON seg.id = e.segmento_id
    LEFT JOIN planos pl ON pl.id = e.plano_id
";

// Se o domínio que a pessoa digitou for um domínio próprio de cliente, usa ele.
// Senão (acessando pelo domínio da própria plataforma), usa o slug normal.
$empresa = false;
if ($hostAtual && strpos($hostAtual, 'sitegestor.com') === false && strpos($hostAtual, 'localhost') === false) {
    $stmt = $pdo->prepare($baseSql . " WHERE e.dominio_personalizado = ? LIMIT 1");
    $stmt->execute([$hostAtual]);
    $empresa = $stmt->fetch();
}
if (!$empresa) {
    $stmt = $pdo->prepare($baseSql . " WHERE e.slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $empresa = $stmt->fetch();
}

if (!$empresa || !pg_bool($empresa['publicado'])) {
    http_response_code(404);
    echo "<h1 style='font-family:sans-serif;text-align:center;margin-top:80px;color:#666'>Site não encontrado ou ainda não publicado.</h1>";
    exit;
}

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

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
$descricaoPagina = $empresa['seo_descricao'] ?: ('Conheça ' . $empresa['nome'] . ($empresa['segmento_nome'] ? ' — ' . $empresa['segmento_nome'] : ''));

$googleMapsKey = trim(get_config('google_maps_key', ''));
$googleAnalyticsId = trim(get_config('google_analytics_id', ''));

// Formulário de contato (gera lead)
$leadOk = false;
$nomeLead = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_lead'])) {
    $stmt = $pdo->prepare("INSERT INTO leads (empresa_id, nome, email, telefone, mensagem) VALUES (?,?,?,?,?)");
    $stmt->execute([$empresa['id'], $_POST['nome'], $_POST['email'], $_POST['telefone'], $_POST['mensagem']]);
    $leadOk = true;
    $nomeLead = $_POST['nome'];
}

// Agendamento real feito pelo cliente final
$agendamentoOk = false;
$agendamentoErro = '';
$pixParaMostrar = null;
$pixErro = '';
$detalhesAgendamento = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fazer_agendamento'])) {
    $nomeCliente = trim($_POST['ag_nome'] ?? '');
    $emailCliente = trim($_POST['ag_email'] ?? '');
    $telefoneCliente = trim($_POST['ag_telefone'] ?? '');
    $servicoId = $_POST['ag_servico_id'] ?: null;
    $profissionalId = $_POST['ag_profissional_id'] ?: null;
    $dataHora = $_POST['ag_data_hora'] ?? '';

    if ($nomeCliente === '' || $dataHora === '') {
        $agendamentoErro = 'Preencha ao menos nome e data/hora.';
    } elseif ($profissionalId) {
        $conflito = $pdo->prepare("SELECT id FROM agendamentos WHERE empresa_id=? AND profissional_id=? AND data_hora=? AND status != 'cancelado' LIMIT 1");
        $conflito->execute([$empresa['id'], $profissionalId, $dataHora]);
        if ($conflito->fetch()) {
            $agendamentoErro = 'Esse horário acabou de ser reservado com esse profissional. Escolha outro horário ou outro profissional.';
        }
    }

    if ($agendamentoErro === '' && $nomeCliente !== '' && $dataHora !== '') {
        $clienteId = null;
        if ($emailCliente !== '') {
            $busca = $pdo->prepare("SELECT id FROM clientes WHERE empresa_id=? AND email=? LIMIT 1");
            $busca->execute([$empresa['id'], $emailCliente]);
            $existente = $busca->fetch();
            if ($existente) { $clienteId = $existente['id']; }
        }
        if (!$clienteId) {
            $novo = $pdo->prepare("INSERT INTO clientes (empresa_id, nome, email, telefone) VALUES (?,?,?,?) RETURNING id");
            $novo->execute([$empresa['id'], $nomeCliente, $emailCliente, $telefoneCliente]);
            $clienteId = $novo->fetch()['id'];
        }

        $insAg = $pdo->prepare("INSERT INTO agendamentos (empresa_id, cliente_id, servico_id, profissional_id, data_hora, status) VALUES (?,?,?,?,?,'agendado') RETURNING id");
        $insAg->execute([$empresa['id'], $clienteId, $servicoId, $profissionalId, $dataHora]);
        $novoAgendamentoId = $insAg->fetch()['id'];
        $agendamentoOk = true;

        // Monta os detalhes pro cartão de conclusão (resumo + link do Google Calendar)
        $servicoNomeConfirmado = ''; $duracaoConfirmada = 60;
        if ($servicoId) {
            $srvInfo = $pdo->prepare("SELECT nome, duracao_minutos FROM servicos WHERE id=?");
            $srvInfo->execute([$servicoId]); $srvInfo = $srvInfo->fetch();
            if ($srvInfo) { $servicoNomeConfirmado = $srvInfo['nome']; $duracaoConfirmada = $srvInfo['duracao_minutos'] ?: 60; }
        }
        $dataFormatada = date('d/m/Y \à\s H:i', strtotime($dataHora));
        $detalhesAgendamento = [
            'servico_nome' => $servicoNomeConfirmado,
            'data_formatada' => $dataFormatada,
            'empresa_whatsapp' => $empresa['whatsapp'],
            'link_calendario' => gerar_link_google_calendar(
                ($servicoNomeConfirmado ?: 'Agendamento') . ' - ' . $empresa['nome'],
                $dataHora, $duracaoConfirmada,
                'Agendamento feito pelo site de ' . $empresa['nome'],
                $empresa['endereco'] ?? ''
            ),
        ];

        // Se a empresa ativou pagamento online e o serviço escolhido tem preço, gera a cobrança PIX de verdade
        if ($servicoId && pg_bool($empresa['pix_ativo'] ?? false) && !empty($empresa['mercadopago_access_token'])) {
            $srvPago = $pdo->prepare("SELECT nome, preco FROM servicos WHERE id=?");
            $srvPago->execute([$servicoId]); $srvPago = $srvPago->fetch();
            if ($srvPago && $srvPago['preco'] > 0) {
                require_once __DIR__ . '/../includes/mercadopago_helper.php';
                $cobranca = gerar_cobranca_pix($empresa['mercadopago_access_token'], $srvPago['preco'], $srvPago['nome'] . ' - ' . $empresa['nome'], $emailCliente);
                if ($cobranca['ok']) {
                    $pdo->prepare("INSERT INTO pagamentos_clientes (empresa_id, agendamento_id, cliente_id, valor, referencia_gateway) VALUES (?,?,?,?,?)")
                        ->execute([$empresa['id'], $novoAgendamentoId, $clienteId, $srvPago['preco'], $cobranca['mp_id']]);
                    $pixParaMostrar = $cobranca;
                } else {
                    $pixErro = $cobranca['erro'];
                }
            }
        }
    }
}

// ============================================================
// MODO "SITE TOTAL IA": se ativado e existir HTML gerado, usa ele
// no lugar do template padrão — substituindo os marcadores pelos
// blocos funcionais reais (agendamento, serviços, avaliações...).
// ============================================================
if (pg_bool($empresa['ia_modo_total'] ?? false) && !empty($empresa['html_gerado'])) {
    $htmlFinal = str_replace(
        ['{{SOBRE}}', '{{SERVICOS}}', '{{EQUIPE}}', '{{GALERIA}}', '{{AGENDAMENTO}}', '{{AVALIACOES}}', '{{CONTATO}}', '{{MAPA}}', '{{CUPONS}}', '{{WHATSAPP_FLOAT}}'],
        [
            frag_sobre($empresa['sobre']),
            frag_servicos($servicos),
            frag_equipe($profissionais),
            frag_galeria($galeria),
            frag_agendamento($servicos, $profissionais, $agendamentoOk, $agendamentoErro, $pixParaMostrar, $pixErro, $detalhesAgendamento),
            frag_avaliacoes($avaliacoes),
            frag_contato($leadOk, $nomeLead),
            frag_mapa($empresa['endereco'], $googleMapsKey),
            frag_cupons($cupons),
            frag_whatsapp_float($empresa['whatsapp']),
        ],
        $empresa['html_gerado']
    );

    // Injeta CSS de apoio + Analytics logo antes do </head>, sem depender da IA ter feito isso certo
    $extras = frag_css_apoio();
    if ($googleAnalyticsId) {
        $extras .= "<script async src=\"https://www.googletagmanager.com/gtag/js?id=" . e($googleAnalyticsId) . "\"></script>"
                 . "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . e($googleAnalyticsId) . "');</script>";
    }
    $htmlFinal = preg_replace('/<\/head>/i', $extras . '</head>', $htmlFinal, 1);

    // Força a exibição da página certa (agendar/contato) depois de confirmar algo, mesmo
    // sem conhecer a implementação exata de navegação que a IA escreveu — usa a convenção
    // data-page que foi exigida no prompt, sobrescrevendo via estilo inline (alta prioridade).
    if ($agendamentoOk || $leadOk) {
        $chaveForcada = $agendamentoOk ? 'agendar' : 'contato';
        $scriptForcarPagina = "<script>document.addEventListener('DOMContentLoaded', function(){"
            . "var alvo = document.querySelector('[data-page=\"" . $chaveForcada . "\"]');"
            . "if (!alvo) return;"
            . "document.querySelectorAll('[data-page]').forEach(function(el){ el.style.setProperty('display', el === alvo ? 'block' : 'none', 'important'); });"
            . "alvo.scrollIntoView({behavior:'smooth', block:'start'});"
            . "});</script>";
        $htmlFinal = preg_replace('/<\/body>/i', $scriptForcarPagina . '</body>', $htmlFinal, 1);
    }

    echo $htmlFinal;
    exit;
}

// ============================================================
// Renderiza cada seção em uma variável (permite reordenar/ocultar
// livremente sem duplicar código). Só entra no array se tiver
// conteúdo pra mostrar.
// ============================================================
$secaoHtml = [];
$secaoNav = [];

if (trim($empresa['sobre'] ?? '') !== '') {
    ob_start(); ?>
    <section id="sobre" class="psite-section">
        <div class="container">
            <h2>Sobre</h2>
            <p class="lead"><?= nl2br(e($empresa['sobre'])) ?></p>
        </div>
    </section>
    <?php $secaoHtml['sobre'] = ob_get_clean(); $secaoNav['sobre'] = 'Sobre';
}

if (!empty($servicos)) {
    ob_start(); ?>
    <section id="servicos" class="psite-section">
        <div class="container">
            <h2>Serviços</h2>
            <div class="psite-grid">
                <?php foreach ($servicos as $s): ?>
                <div class="psite-item">
                    <h3><?= e($s['nome']) ?></h3>
                    <p><?= e($s['descricao']) ?></p>
                    <span class="psite-price">R$ <?= number_format($s['preco'],2,',','.') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php $secaoHtml['servicos'] = ob_get_clean(); $secaoNav['servicos'] = 'Serviços';
}

if (!empty($profissionais)) {
    ob_start(); ?>
    <section id="equipe" class="psite-section">
        <div class="container">
            <h2>Equipe</h2>
            <div class="psite-team">
                <?php foreach ($profissionais as $p): ?>
                <div class="psite-person">
                    <?php if ($p['foto']): ?>
                    <img src="<?= e($p['foto']) ?>" alt="<?= e($p['nome']) ?>">
                    <?php else: ?>
                    <div class="avatar-fallback"><?= e(mb_substr($p['nome'],0,1)) ?></div>
                    <?php endif; ?>
                    <h3><?= e($p['nome']) ?></h3>
                    <p><?= e($p['especialidade']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php $secaoHtml['equipe'] = ob_get_clean(); $secaoNav['equipe'] = 'Equipe';
}

if (!empty($galeria)) {
    ob_start(); ?>
    <section id="galeria" class="psite-section">
        <div class="container">
            <h2>Galeria</h2>
            <div class="psite-gallery">
                <?php foreach ($galeria as $img): ?>
                <img src="<?= e($img) ?>" alt="Foto de <?= e($empresa['nome']) ?>" loading="lazy">
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php $secaoHtml['galeria'] = ob_get_clean(); $secaoNav['galeria'] = 'Galeria';
}

if (!empty($blocos)) {
    ob_start(); ?>
    <section id="destaques" class="psite-section">
        <div class="container">
            <?php foreach ($blocos as $b): ?>
            <div class="psite-bloco">
                <h3><?= e($b['titulo']) ?></h3>
                <p><?= e($b['conteudo']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php $secaoHtml['blocos'] = ob_get_clean(); $secaoNav['blocos'] = 'Destaques';
}

if (!empty($cupons)) {
    ob_start(); ?>
    <section id="cupons" class="psite-section">
        <div class="container">
            <h2>Cupons ativos</h2>
            <div class="psite-grid" style="background:transparent;border:none;gap:12px;">
                <?php foreach ($cupons as $c): ?>
                <div class="psite-cupom">
                    <strong><?= e($c['codigo']) ?></strong> — <?= number_format($c['desconto_percentual'],0) ?>% de desconto
                    <p style="color:var(--text-dim);font-size:13px;margin-top:6px;"><?= e($c['descricao']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php $secaoHtml['cupons'] = ob_get_clean(); $secaoNav['cupons'] = 'Cupons';
}

// Agendar sempre aparece (é a ação principal do site)
ob_start(); ?>
<section id="agendar" class="psite-section">
    <div class="container">
        <h2>Agendar horário</h2>
        <div class="psite-form-wrap">
            <div>
                <p class="lead">Escolha o serviço, o profissional (se quiser) e o melhor horário. Confirmamos com você em seguida.</p>
                <?php if ($empresa['endereco']): ?>
                <div style="margin-top:24px;border:1px solid var(--surface-2);border-radius:12px;overflow:hidden;">
                    <?php if ($googleMapsKey): ?>
                    <iframe width="100%" height="220" style="border:0;display:block;" loading="lazy"
                        src="https://www.google.com/maps/embed/v1/place?key=<?= e($googleMapsKey) ?>&q=<?= urlencode($empresa['endereco']) ?>"></iframe>
                    <?php else: ?>
                    <iframe width="100%" height="220" style="border:0;display:block;filter:grayscale(0.3) invert(0.9) contrast(0.85);" loading="lazy"
                        src="https://maps.google.com/maps?q=<?= urlencode($empresa['endereco']) ?>&output=embed"></iframe>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div>
            <?= frag_agendamento($servicos, $profissionais, $agendamentoOk, $agendamentoErro, $pixParaMostrar, $pixErro, $detalhesAgendamento) ?>
            </div>
        </div>
    </div>
</section>
<?php $secaoHtml['agendar'] = ob_get_clean(); $secaoNav['agendar'] = 'Agendar';

if (!empty($avaliacoes)) {
    ob_start(); ?>
    <section id="avaliacoes" class="psite-section">
        <div class="container">
            <h2>Avaliações</h2>
            <?php foreach ($avaliacoes as $av): ?>
            <div class="psite-review">
                <strong><?= e($av['cliente_nome'] ?? 'Cliente') ?></strong>
                <div class="psite-stars"><?= str_repeat('★', (int)$av['nota']) . str_repeat('☆', 5-(int)$av['nota']) ?></div>
                <p><?= e($av['comentario']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php $secaoHtml['avaliacoes'] = ob_get_clean(); $secaoNav['avaliacoes'] = 'Avaliações';
}

// Contato sempre aparece
ob_start(); ?>
<section id="contato" class="psite-section">
    <div class="container">
        <h2>Contato</h2>
        <?= frag_contato($leadOk, $nomeLead) ?>
    </div>
</section>
<?php $secaoHtml['contato'] = ob_get_clean(); $secaoNav['contato'] = 'Contato';

// Ordem final: a que a empresa escolheu, ou a padrão se nunca configurou
$ordemPadrao = ['sobre','servicos','equipe','galeria','blocos','cupons','agendar','avaliacoes','contato'];
$ordemEscolhida = !empty($empresa['secoes']) ? array_filter(explode(',', $empresa['secoes'])) : $ordemPadrao;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($tituloPagina) ?></title>
<meta name="description" content="<?= e($descricaoPagina) ?>">
<meta property="og:title" content="<?= e($tituloPagina) ?>">
<meta property="og:description" content="<?= e($descricaoPagina) ?>">
<meta property="og:type" content="website">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="/assets/css/site-publico.css">
<style>:root{ --accent: <?= e($corPrimaria) ?>; --accent-2: <?= e($corSecundaria) ?>; }</style>
<?php if ($googleAnalyticsId): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($googleAnalyticsId) ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?= e($googleAnalyticsId) ?>');
</script>
<?php endif; ?>
</head>
<body class="layout-<?= e($layout) ?>">

<nav class="psite-nav">
    <div class="psite-brand">
        <?php if ($empresa['logo']): ?><img src="<?= e($empresa['logo']) ?>" alt="Logo"><?php endif; ?>
        <?= e($empresa['nome']) ?>
    </div>
    <div class="psite-nav-links">
        <?php foreach ($ordemEscolhida as $chave): if (isset($secaoNav[$chave])): ?>
        <a href="#<?= $chave ?>" data-page-link="<?= $chave ?>" onclick="mostrarPagina('<?= $chave ?>');return false;"><?= e($secaoNav[$chave]) ?></a>
        <?php endif; endforeach; ?>
    </div>
</nav>

<?php if ($layout === 'grid'): ?>
<header class="psite-hero">
    <div class="psite-hero-grid">
        <div class="psite-hero-text">
            <div class="psite-tag"><span class="dot"></span><?= e($empresa['segmento_nome'] ?: 'Negócio local') ?></div>
            <h1><?= e($empresa['titulo'] ?: $empresa['nome']) ?></h1>
            <p><?= e($empresa['subtitulo'] ?: 'Conheça nosso trabalho e agende com a gente.') ?></p>
            <a href="#agendar" onclick="mostrarPagina('agendar');return false;" class="psite-btn">Agendar horário</a>
        </div>
        <?php if ($empresa['banner_imagem']): ?>
        <div class="psite-hero-banner-wrap"><img src="<?= e($empresa['banner_imagem']) ?>" alt="<?= e($empresa['nome']) ?>"></div>
        <?php else: ?>
        <div class="psite-hero-banner-wrap" style="background:linear-gradient(135deg,var(--accent),var(--accent-2));"></div>
        <?php endif; ?>
    </div>
</header>
<?php else: ?>
<header class="psite-hero">
    <div class="container psite-hero-inner">
        <div class="psite-tag"><span class="dot"></span><?= e($empresa['segmento_nome'] ?: 'Negócio local') ?></div>
        <h1><?= e($empresa['titulo'] ?: $empresa['nome']) ?></h1>
        <p><?= e($empresa['subtitulo'] ?: 'Conheça nosso trabalho e agende com a gente.') ?></p>
        <a href="#agendar" onclick="mostrarPagina('agendar');return false;" class="psite-btn">Agendar horário</a>
    </div>
</header>
<?php endif; ?>

<div class="psite-features container">
    <div class="psite-feature"><?= psite_icon('calendario') ?> Agende online</div>
    <?php if (!empty($profissionais)): ?><div class="psite-feature"><?= psite_icon('usuarios') ?> Equipe especializada</div><?php endif; ?>
    <?php if ($empresa['whatsapp']): ?><div class="psite-feature"><?= psite_icon('chat') ?> Atendimento via WhatsApp</div><?php endif; ?>
    <?php if (pg_bool($empresa['permite_pix'] ?? false)): ?><div class="psite-feature"><?= psite_icon('cartao') ?> Pagamento via PIX</div><?php endif; ?>
    <?php if ($empresa['endereco']): ?><div class="psite-feature"><?= psite_icon('pin') ?> <?= e($empresa['endereco']) ?></div><?php endif; ?>
</div>

<div id="psite-paginas">
<?php foreach ($ordemEscolhida as $chave): if (isset($secaoHtml[$chave])): ?>
<div class="psite-page" data-page="<?= $chave ?>"><?= $secaoHtml[$chave] ?></div>
<?php endif; endforeach; ?>
</div>

<footer class="psite-footer container">
    <span><?= e($empresa['nome']) ?><?= $empresa['endereco'] ? ' — ' . e($empresa['endereco']) : '' ?></span>
    <span>Site por GDS — Gestor de Sites</span>
</footer>

<?php if ($empresa['whatsapp']): ?>
<a class="psite-whatsapp-float" href="https://wa.me/<?= preg_replace('/\D/','',$empresa['whatsapp']) ?>" target="_blank" aria-label="Falar no WhatsApp">
    <svg viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.28-1.38a9.9 9.9 0 0 0 4.76 1.21h.01c5.46 0 9.9-4.45 9.9-9.91C21.96 6.45 17.5 2 12.04 2zm5.8 14.16c-.24.68-1.4 1.3-1.93 1.38-.5.08-1.12.11-1.81-.11-.42-.13-.95-.31-1.64-.6-2.88-1.24-4.76-4.15-4.9-4.34-.14-.19-1.17-1.55-1.17-2.97 0-1.41.74-2.1 1-2.39.26-.28.57-.35.76-.35.19 0 .38 0 .55.01.18.01.42-.07.65.5.24.58.81 2 .88 2.14.07.15.11.32.02.51-.09.19-.14.31-.28.48-.14.17-.29.37-.42.5-.14.14-.28.28-.12.56.16.28.71 1.17 1.53 1.9 1.05.94 1.94 1.23 2.22 1.37.28.14.44.12.6-.07.16-.19.68-.79.87-1.06.19-.28.37-.23.62-.14.26.09 1.63.77 1.91.91.28.14.47.21.54.33.07.12.07.68-.17 1.36z"/></svg>
</a>
<?php endif; ?>

<script>
function mostrarPagina(chave) {
    document.querySelectorAll('.psite-page').forEach(function(el){ el.classList.remove('psite-page-ativa'); });
    var alvo = document.querySelector('.psite-page[data-page="' + chave + '"]');
    if (alvo) { alvo.classList.add('psite-page-ativa'); }
    document.querySelectorAll('.psite-nav-links a').forEach(function(a){ a.classList.remove('psite-nav-ativo'); });
    var link = document.querySelector('.psite-nav-links a[data-page-link="' + chave + '"]');
    if (link) { link.classList.add('psite-nav-ativo'); }
    window.scrollTo({top: 0, behavior: 'smooth'});
    if (history.replaceState) { history.replaceState(null, '', '#' + chave); }
}
document.addEventListener('DOMContentLoaded', function() {
    var paginas = document.querySelectorAll('.psite-page');
    if (paginas.length === 0) return;
    var paginaForcada = <?= $agendamentoOk ? "'agendar'" : ($leadOk ? "'contato'" : 'null') ?>;
    if (paginaForcada && document.querySelector('.psite-page[data-page="' + paginaForcada + '"]')) {
        mostrarPagina(paginaForcada);
        return;
    }
    var hash = window.location.hash.replace('#', '');
    var existe = hash && document.querySelector('.psite-page[data-page="' + hash + '"]');
    mostrarPagina(existe ? hash : paginas[0].dataset.page);
});
</script>

</body>
</html>
