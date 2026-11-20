<?php
// /robots.txt e /sitemap.xml (rotas no .htaccess) — respondem conforme o domínio acessado:
//  • domínio próprio de um cliente: só o site dele (página inicial + privacidade);
//  • domínio da plataforma: todos os sites publicados que não usam domínio próprio.
// O painel, as APIs, as prévias e os links pessoais (rastreio, treino…) ficam fora da busca.
require_once __DIR__ . '/../config/db.php';

$host = preg_replace('/^www\./i', '', strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]));
$https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
$base = ($https ? 'https://' : 'http://') . preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');

if (isset($_GET['robots'])) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    echo "User-agent: *\n"
       . "Disallow: /admin/\nDisallow: /empresa/\nDisallow: /api/\nDisallow: /previa/\nDisallow: /indica/\n"
       . "Disallow: /r/\nDisallow: /m/\nDisallow: /tv/\nDisallow: /cron.php\nDisallow: /install.php\n"
       . "Disallow: /site/\nAllow: /site/privacidade.php\n\n"
       . "Sitemap: $base/sitemap.xml\n";
    exit;
}

$urls = [];
$publicado = "s.publicado = true AND e.status NOT IN ('suspenso','cancelado')";
try {
    $st = $pdo->prepare("SELECT e.slug, s.atualizado_em FROM empresas e JOIN sites s ON s.empresa_id = e.id WHERE e.dominio_personalizado = ? AND $publicado LIMIT 1");
    $st->execute([$host]);
    $proprio = $st->fetch();
} catch (PDOException $ex) { $proprio = false; }

$data = fn($t) => $t ? date('Y-m-d', strtotime((string)$t)) : date('Y-m-d');
if ($proprio) {
    $urls[] = [$base . '/', $data($proprio['atualizado_em']), '1.0'];
    $urls[] = [$base . '/site/privacidade.php', $data($proprio['atualizado_em']), '0.2'];
} else {
    try {
        $rs = $pdo->query("SELECT e.slug, s.atualizado_em FROM empresas e JOIN sites s ON s.empresa_id = e.id
            WHERE $publicado AND (e.dominio_personalizado IS NULL OR e.dominio_personalizado = '' OR COALESCE(e.dominio_status, '') <> 'ativo')
            ORDER BY s.atualizado_em DESC NULLS LAST LIMIT 20000")->fetchAll();
    } catch (PDOException $ex) {
        $rs = $pdo->query("SELECT e.slug, s.atualizado_em FROM empresas e JOIN sites s ON s.empresa_id = e.id WHERE $publicado LIMIT 20000")->fetchAll();
    }
    foreach ($rs as $r) {
        $urls[] = [$base . '/' . rawurlencode($r['slug']), $data($r['atualizado_em']), '0.8'];
        $urls[] = [$base . '/site/privacidade.php?slug=' . rawurlencode($r['slug']), $data($r['atualizado_em']), '0.1'];
    }
}

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $mod, $pri]) {
    echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc><lastmod>' . $mod . '</lastmod><priority>' . $pri . "</priority></url>\n";
}
echo "</urlset>\n";
