<?php
// ============================================================
// APP DO MOTOBOY
//   /m/TOKEN               → o app (GPS ao vivo, Saí / Entreguei / Voltei, nova entrega)
//   /m/TOKEN?manifest=1    → manifesto para "instalar" na tela inicial
//   POST ?entrar=1         → login com WhatsApp + PIN (feito no site da empresa)
// O site público das empresas do segmento "Rastreamento de motoboys" mostra a entrada
// do app (rastreio_pagina_entrada), chamada por site/index.php.
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/rastreio.php';
if (!function_exists('e')) { function e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); } }

// Tela de entrada (site da empresa): WhatsApp + PIN
function rastreio_pagina_entrada($empresa, $erro = '') {
    $slug = $empresa['slug'];
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    ?><!DOCTYPE html>
<html lang="pt-BR"><head><?= rastreio_head('App do motoboy · ' . $empresa['nome'], 'Entrada do app de entregas de ' . $empresa['nome'] . '.') ?></head>
<body class="rz">
<main class="rz-entrar">
  <div class="rz-entrar-caixa">
    <div class="rz-marca"><?php if (!empty($empresa['logo'])): ?><img src="<?= e($empresa['logo']) ?>" alt=""><?php endif; ?><div><strong><?= e($empresa['nome']) ?></strong><div class="rz-mute">Entregas acompanhadas ao vivo</div></div></div>
    <div class="rz-faixa-deco" aria-hidden="true"></div>
    <h1>App do motoboy</h1>
    <p class="rz-mute" style="margin:0">Entre com o seu WhatsApp e o PIN que a loja cadastrou. No próximo acesso o celular já abre direto.</p>
    <?php if ($erro): ?><div class="rz-erro" role="alert"><?= e($erro) ?></div><?php endif; ?>
    <form method="post" action="/site/motoboy.php?entrar=1" class="rz-card" autocomplete="on">
      <input type="hidden" name="empresa" value="<?= e($slug) ?>">
      <div class="rz-campo"><label for="tel">Seu WhatsApp</label><input id="tel" name="telefone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="(11) 99999-9999"></div>
      <div class="rz-campo"><label for="pin">PIN</label><input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{4,8}" autocomplete="current-password" required placeholder="4 a 8 números"></div>
      <button class="rz-btn rz-btn-faixa rz-btn-grande" type="submit">Entrar</button>
    </form>
    <p class="rz-mute" style="font-size:14px;margin:0">É cliente e quer acompanhar seu pedido? Use o link que a loja mandou no seu WhatsApp.</p>
    <p style="font-size:14px;margin:0"><a href="/login.php" style="color:var(--rz-txt)">Sou da loja: entrar no painel</a></p>
  </div>
</main>
<script>
// Já entrou neste celular antes: abre o app direto
try { var t = localStorage.getItem('gds_motoboy_<?= e($slug) ?>'); if (t && /^[a-f0-9]{32}$/.test(t) && !location.search.includes('sair')) location.replace('/m/' + t); } catch (e) {}
</script>
</body></html>
<?php
}

// Chamado por site/index.php: não continua daqui
if (defined('RASTREIO_SO_FUNCOES')) return;

session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);

// ---------- login ----------
if (isset($_GET['entrar']) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    session_start();
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($_POST['empresa'] ?? '')));
    $st = $pdo->prepare("SELECT e.*, seg.slug AS segmento_slug FROM empresas e LEFT JOIN segmentos seg ON seg.id = e.segmento_id WHERE e.slug=?");
    $st->execute([$slug]);
    $empresa = $st->fetch();
    if (!$empresa || !rastreio_pronto()) { http_response_code(404); exit('Empresa não encontrada.'); }
    // Freio contra tentativa de adivinhar o PIN: 6 erros em 10 minutos
    $t = array_filter((array)($_SESSION['rast_tentativas'] ?? []), fn($x) => $x > time() - 600);
    if (count($t) >= 6) { rastreio_pagina_entrada($empresa, 'Muitas tentativas. Espere 10 minutos ou peça o link do app à loja.'); exit; }
    $tel = preg_replace('/\D/', '', (string)($_POST['telefone'] ?? ''));
    $pin = (string)($_POST['pin'] ?? '');
    $achado = null; $doTelefone = [];
    if (strlen($tel) >= 8 && preg_match('/^\d{4,8}$/', $pin)) {
        $l = $pdo->prepare("SELECT * FROM entregadores WHERE empresa_id=? AND ativo AND pin_hash IS NOT NULL");
        $l->execute([$empresa['id']]);
        foreach ($l->fetchAll() as $en) {
            $t2 = preg_replace('/\D/', '', (string)$en['telefone']);
            if ($t2 === '' || substr($t2, -8) !== substr($tel, -8)) continue;
            $doTelefone[] = $en;
            if (password_verify($pin, $en['pin_hash'])) { $achado = $en; break; }
        }
    }
    // Freio por motoboy (vale em qualquer aparelho): 8 PINs errados em 15 min bloqueiam aquele WhatsApp por 15 min
    foreach ($doTelefone as $en) {
        $chave = 'rast_falhas_' . (int)$en['id'];
        $falhas = array_values(array_filter((array)json_decode((string)get_config($chave, '[]'), true), fn($x) => $x > time() - 900));
        if (count($falhas) >= 8) { rastreio_pagina_entrada($empresa, 'Muitas tentativas com este WhatsApp. Espere 15 minutos ou peça o link do app à loja.'); exit; }
        if (!$achado) { $falhas[] = time(); set_config($chave, json_encode($falhas)); }
        elseif ($falhas) set_config($chave, '[]');
    }
    if (!$achado) {
        $t[] = time(); $_SESSION['rast_tentativas'] = array_values($t);
        usleep(600000);
        rastreio_pagina_entrada($empresa, 'WhatsApp ou PIN não conferem. Confira com a loja.');
        exit;
    }
    $token = $achado['app_token'] ?: rastreio_token();
    if (!$achado['app_token']) $pdo->prepare("UPDATE entregadores SET app_token=? WHERE id=?")->execute([$token, $achado['id']]);
    header('Location: /m/' . $token, true, 303);
    exit;
}

// ---------- app ----------
$token = (string)($_GET['t'] ?? '');
$m = rastreio_pronto() ? rastreio_motoboy_por_token($token) : null;

if (isset($_GET['manifest'])) {
    header('Content-Type: application/manifest+json; charset=utf-8');
    if (!$m) { http_response_code(404); echo '{}'; exit; }
    echo json_encode(['name' => 'Entregas · ' . $m['empresa_nome'], 'short_name' => 'Entregas', 'start_url' => '/m/' . $token, 'scope' => '/m/' . $token,
        'display' => 'standalone', 'orientation' => 'portrait', 'background_color' => '#15171b', 'theme_color' => '#15171b', 'lang' => 'pt-BR',
        'icons' => [['src' => '/assets/img/rastreio-icone.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
if (!$m) {
    http_response_code(404);
    ?><!DOCTYPE html><html lang="pt-BR"><head><?= rastreio_head('Acesso inválido') ?></head><body class="rz"><main class="rz-expirado"><div class="rz-card">
    <h1>Acesso inválido</h1><p class="rz-mute">Este link do app não vale mais (a loja pode ter gerado um novo ou desativado o acesso). Peça o link novo à loja.</p></div></main>
    <script>try { Object.keys(localStorage).forEach(function (k) { if (k.indexOf('gds_motoboy_') === 0 && localStorage.getItem(k) === <?= json_encode($token) ?>) localStorage.removeItem(k); }); } catch (e) {}</script></body></html><?php
    exit;
}
$primeiro = rastreio_primeiro_nome($m['nome']);
?><!DOCTYPE html>
<html lang="pt-BR">
<head><?= rastreio_head('Entregas · ' . $primeiro, '', false, '<link rel="manifest" href="/m/' . e($token) . '?manifest=1"><link rel="icon" href="/assets/img/rastreio-icone.svg"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">') ?></head>
<body class="rz">
<div class="rz-app" id="app" data-t="<?= e($token) ?>" data-empresa="<?= e($m['empresa_slug']) ?>" data-empresa-nome="<?= e($m['empresa_nome']) ?>">
  <header class="rz-app-topo">
    <div><strong><?= e($primeiro) ?></strong><small><?= e($m['empresa_nome']) ?></small></div>
    <span class="rz-vivo parado" id="gpsSelo" role="status">GPS desligado</span>
  </header>
  <main class="rz-app-corpo">
    <div id="aviso" hidden></div>
    <section class="rz-status" id="status" data-s="loja" aria-live="polite">
      <span class="rz-mute" id="statusRot">Situação</span>
      <h1 id="statusTit">Na loja</h1>
      <span class="rz-mute rz-num" id="statusSub"></span>
      <button class="rz-btn rz-btn-faixa rz-btn-grande" id="btnPrincipal" type="button">Saí para entregar</button>
    </section>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
      <h2 style="font-size:22px">Minhas entregas</h2>
      <button class="rz-btn rz-btn-linha" type="button" id="btnNova">+ Nova entrega</button>
    </div>
    <div id="lista" class="rz-lista" style="display:grid;gap:12px"><div class="rz-lista-vazia">Carregando…</div></div>
    <div class="rz-gps rz-num" id="gpsInfo"></div>
    <p class="rz-mute" style="font-size:14px;margin:0">Deixe esta tela aberta durante a entrega: o celular não apaga a tela enquanto você está em rota. Se fechar, a loja e o cliente deixam de ver você no mapa.</p>
    <button class="rz-btn rz-btn-linha" type="button" id="btnInstalar" hidden>Instalar o app na tela inicial</button>
  </main>
</div>
<dialog class="rz-dialogo" id="dlgNova" aria-labelledby="dlgNovaTit">
  <form method="dialog" id="formNova">
    <h2 id="dlgNovaTit" style="font-size:24px;margin-bottom:12px">Nova entrega</h2>
    <div class="rz-campo"><label for="nCliente">Nome do cliente</label><input id="nCliente" name="cliente" autocomplete="off"></div>
    <div class="rz-campo"><label for="nTel">WhatsApp do cliente</label><input id="nTel" name="telefone" type="tel" inputmode="tel" autocomplete="off"></div>
    <div class="rz-campo"><label for="nEnd">Endereço</label><input id="nEnd" name="endereco" autocomplete="off" placeholder="Rua, número, bairro"></div>
    <div class="rz-campo"><label for="nCod">Nº do pedido (opcional)</label><input id="nCod" name="codigo" inputmode="numeric" autocomplete="off"></div>
    <div id="novaErro" class="rz-erro" hidden></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
      <button class="rz-btn rz-btn-linha" value="cancelar" formnovalidate type="submit">Cancelar</button>
      <button class="rz-btn rz-btn-faixa" value="criar" type="submit" id="btnCriar">Criar e enviar link</button>
    </div>
  </form>
</dialog>
<script src="/assets/js/rastreio-app.js?v=1" defer></script>
</body>
</html>
