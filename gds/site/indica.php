<?php
// ============================================================
// INDIQUE E GANHE: /indica/CODIGO
// O cliente manda este link para outro dono de negócio. Quem preencher vira lead
// quente na Prospecção (com "indicado por"), e o dono da plataforma é avisado.
// Se o indicado fechar, quem indicou ganha 1 mês grátis (automático).
// ============================================================
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/migracoes.php';
require_once __DIR__ . '/../includes/site_render.php';
require_once __DIR__ . '/../includes/prospeccao_vendedor.php';
gds_garantir_schema();
if (!function_exists('e')) { function e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); } }
header('X-Robots-Tag: noindex');

$codigo = preg_replace('/[^a-f0-9]/', '', (string)($_GET['c'] ?? ''));
$quem = null;
if ($codigo !== '') {
    try { $st = $pdo->prepare("SELECT e.id, e.nome, e.slug, e.logo, s.cor_primaria FROM empresas e LEFT JOIN sites s ON s.empresa_id=e.id WHERE e.indica_codigo=?"); $st->execute([$codigo]); $quem = $st->fetch() ?: null; } catch (PDOException $e) {}
}
if (!$quem) { http_response_code(404); echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Link inválido</title><p style="font-family:system-ui;padding:40px;text-align:center">Este link de indicação não existe mais.</p>'; exit; }
$marca = get_config('mkt_marca', '') ?: 'GDS Sites';
$oferta = prosp_oferta_txt();
$segs = [];
try { $segs = $pdo->query("SELECT slug, nome FROM segmentos WHERE ativo=true AND slug<>'outro' ORDER BY nome")->fetchAll(); } catch (PDOException $e) {}
$erro = ''; $ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $negocio = mb_substr(trim((string)($_POST['negocio'] ?? '')), 0, 120);
    $nome = mb_substr(trim((string)($_POST['nome'] ?? '')), 0, 80);
    $wa = preg_replace('/\D/', '', (string)($_POST['whatsapp'] ?? ''));
    $cidade = mb_substr(trim((string)($_POST['cidade'] ?? '')), 0, 80);
    $seg = (string)($_POST['segmento'] ?? '');
    $segOk = array_values(array_filter($segs, fn($s) => $s['slug'] === $seg))[0] ?? null;
    if (site_eh_spam()) $erro = 'Muitas tentativas. Tente de novo em alguns minutos.';
    elseif ($negocio === '' || strlen($wa) < 10 || strlen($wa) > 13) $erro = 'Preencha o nome do negócio e um WhatsApp com DDD.';
    else {
        if (strlen($wa) <= 11) $wa = '55' . $wa;
        try {
            $pdo->prepare("INSERT INTO prospects (origem_id, fonte, nome, categoria, cidade, telefone, whatsapp, status, pontuacao, segmento_slug, tem_site, observacoes, indicado_por, token)
                VALUES (?, 'indicacao', ?, ?, ?, ?, ?, 'respondeu', 95, ?, false, ?, ?, ?)
                ON CONFLICT (origem_id) DO UPDATE SET atualizado_em=NOW()")
                ->execute(['ind:' . $wa, $negocio, $segOk['nome'] ?? 'Indicação', $cidade ?: null, $wa, $wa, $segOk['slug'] ?? null,
                    '🎁 Indicado por ' . $quem['nome'] . ($nome ? " · falar com $nome" : ''), (int)$quem['id'], bin2hex(random_bytes(10))]);
            $ok = true;
            prosp_alertar("🎁 Indicação nova de {$quem['nome']}: $negocio" . ($cidade ? " ($cidade)" : '') . ($nome ? " · $nome" : '') . "\nWhatsApp: https://wa.me/$wa\nJá está na Prospecção como lead quente. Chama agora!");
        } catch (Throwable $e) { error_log('GDS indicação: ' . $e->getMessage()); $erro = 'Não deu para enviar agora. Tente de novo.'; }
    }
}
$cor = preg_match('/^#[0-9a-f]{6}$/i', (string)($quem['cor_primaria'] ?? '')) ? $quem['cor_primaria'] : '#22c55e';
$numVendas = preg_replace('/\D/', '', get_config('prosp_whatsapp_alerta', '') ?: get_config('mkt_whatsapp', ''));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($quem['nome']) ?> indicou você · <?= e($marca) ?></title>
<style>
:root { --cor: <?= e($cor) ?>; --bg: #f6f7fb; --card: #fff; --txt: #161827; --dim: #5d6277; --borda: #e4e6ef; }
@media (prefers-color-scheme: dark) { :root { --bg: #0c0e1a; --card: #151830; --txt: #eef0fa; --dim: #a7abc3; --borda: #272b4b; } }
* { box-sizing: border-box; }
body { margin: 0; background: var(--bg); color: var(--txt); font: 16px/1.55 system-ui, -apple-system, "Segoe UI", sans-serif; }
main { max-width: 560px; margin: 0 auto; padding: 28px 16px 48px; }
.topo { display: flex; gap: 12px; align-items: center; margin-bottom: 18px; }
.topo img { width: 52px; height: 52px; border-radius: 14px; object-fit: cover; }
.selo { display: inline-block; background: color-mix(in srgb, var(--cor) 16%, transparent); color: var(--txt); border: 1px solid color-mix(in srgb, var(--cor) 45%, transparent); padding: 4px 12px; border-radius: 999px; font-size: 13.5px; font-weight: 700; }
h1 { font-size: clamp(26px, 7vw, 36px); line-height: 1.12; margin: 14px 0 10px; letter-spacing: -.02em; }
.sub { color: var(--dim); margin: 0 0 20px; }
ul.ben { list-style: none; padding: 0; margin: 0 0 22px; display: grid; gap: 8px; }
ul.ben li { background: var(--card); border: 1px solid var(--borda); border-radius: 12px; padding: 10px 14px; }
form { background: var(--card); border: 1px solid var(--borda); border-radius: 18px; padding: 18px; display: grid; gap: 10px; }
label { font-weight: 600; font-size: 14px; }
input, select { width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--borda); background: var(--bg); color: var(--txt); font: inherit; }
button { padding: 14px; border: 0; border-radius: 12px; background: var(--cor); color: #fff; font: 800 16px system-ui, sans-serif; cursor: pointer; text-shadow: 0 1px 2px rgba(0,0,0,.25); }
.ok, .erro { padding: 16px; border-radius: 14px; } .ok { background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.45); } .erro { background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.4); }
.ok a { display: inline-block; margin-top: 10px; background: #25d366; color: #052e16; padding: 10px 16px; border-radius: 999px; font-weight: 800; text-decoration: none; }
.hp { position: absolute; left: -9999px; }
small { color: var(--dim); }
</style>
</head>
<body>
<main>
    <div class="topo"><?php if ($quem['logo']): ?><img src="<?= e($quem['logo']) ?>" alt=""><?php endif; ?><span class="selo">🎁 <?= e($quem['nome']) ?> indicou você</span></div>
    <h1>Um site profissional para o seu negócio, pronto em poucos dias</h1>
    <p class="sub">O mesmo que a <?= e($quem['nome']) ?> usa: seus clientes agendam ou pedem pelo site a qualquer hora e a confirmação chega no WhatsApp.<?= $oferta ? ' <b>' . e($oferta) . '</b>.' : '' ?></p>
    <ul class="ben">
        <li>📅 Agenda online 24 h (ou pedidos e orçamentos pelo site)</li>
        <li>💬 Confirmação e lembrete automáticos no WhatsApp</li>
        <li>🔎 Aparecer no Google com o seu nome e endereço</li>
        <li>🎨 A gente monta uma prévia grátis com o seu nome e as suas fotos</li>
    </ul>
    <?php if ($ok): ?>
        <div class="ok"><b>Recebido! 🎉</b><br>Vamos montar a prévia do site de vocês e te chamar no WhatsApp em breve.
            <?php if ($numVendas): ?><br><a href="https://wa.me/<?= e((strlen($numVendas) <= 11 ? '55' : '') . $numVendas) ?>?text=<?= rawurlencode('Oi! A ' . $quem['nome'] . ' me indicou e quero ver como fica o site do meu negócio.') ?>">Falar agora no WhatsApp</a><?php endif; ?></div>
    <?php else: ?>
        <?php if ($erro): ?><p class="erro"><?= e($erro) ?></p><?php endif; ?>
        <form method="post">
            <label for="negocio">Nome do seu negócio</label><input id="negocio" name="negocio" required maxlength="120" value="<?= e($_POST['negocio'] ?? '') ?>">
            <label for="segmento">Ramo</label>
            <select id="segmento" name="segmento"><option value="">Escolha…</option><?php foreach ($segs as $s): ?><option value="<?= e($s['slug']) ?>" <?= ($_POST['segmento'] ?? '') === $s['slug'] ? 'selected' : '' ?>><?= e($s['nome']) ?></option><?php endforeach; ?></select>
            <label for="nome">Seu nome</label><input id="nome" name="nome" maxlength="80" value="<?= e($_POST['nome'] ?? '') ?>">
            <label for="whatsapp">Seu WhatsApp com DDD</label><input id="whatsapp" name="whatsapp" required inputmode="tel" placeholder="(61) 99999-9999" value="<?= e($_POST['whatsapp'] ?? '') ?>">
            <label for="cidade">Cidade / bairro</label><input id="cidade" name="cidade" maxlength="80" value="<?= e($_POST['cidade'] ?? '') ?>">
            <input class="hp" name="site_url_confirm" tabindex="-1" autocomplete="off" aria-hidden="true">
            <input type="hidden" name="gds_t" value="<?= time() ?>">
            <button type="submit">Quero a minha prévia grátis</button>
            <small>Sem compromisso. Usamos seu contato só para falar sobre o seu site.</small>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
