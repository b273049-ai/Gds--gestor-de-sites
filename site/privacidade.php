<?php
// Aviso de privacidade (LGPD) de cada site: /site/privacidade.php?slug=X (ou no domínio próprio, sem o slug).
// O texto é montado com os dados da empresa e com o que o site dela realmente coleta (módulos e recursos ligados).
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/site_render.php';
require_once __DIR__ . '/../includes/recursos_site.php';
if (!function_exists('e')) { function e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); } }

$host = preg_replace('/^www\./i', '', strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]));
$slug = preg_replace('/[^a-zA-Z0-9\-]/', '', (string)($_GET['slug'] ?? ''));
$empresa = false;
try { if ($host !== '') $empresa = site_buscar_empresa('e.dominio_personalizado = ?', $host); } catch (PDOException $ex) { $empresa = false; }
if (!$empresa && $slug !== '') $empresa = site_buscar_empresa('e.slug = ?', $slug);
if (!$empresa || !pg_bool($empresa['publicado']) || in_array($empresa['status'], ['suspenso', 'cancelado'], true)) {
    http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'Página não encontrada.'; exit;
}
header('Cache-Control: public, max-age=3600');

$nome = $empresa['nome'];
$ativos = modulos_da_empresa($empresa)['ativos'];
$tem = fn($m) => in_array($m, $ativos, true);
$modo = atendimento_modo_da_linha($empresa);
$rec = rec_ler($empresa);
$recOn = fn($k) => !empty($rec[$k]['ativo']);
$whats = preg_replace('/\D/', '', (string)($empresa['whatsapp'] ?? ''));
$whatsLink = $whats ? 'https://wa.me/' . (strlen($whats) <= 11 ? '55' : '') . $whats : '';
$cor = preg_match('/^#[0-9a-f]{6}$/i', (string)$empresa['cor_primaria']) ? $empresa['cor_primaria'] : '#1f6feb';
$cor = frag_cor_legivel($cor, ['#ffffff'], 4.6);
$voltar = (!empty($empresa['dominio_personalizado']) && $empresa['dominio_personalizado'] === $host) ? '/' : '/' . $empresa['slug'];

// O que o site coleta, conforme o que está ligado
$coleta = [['Nome e WhatsApp', 'quando você envia um formulário, para responder e confirmar o seu atendimento']];
if ($modo === 'agendamento' || $tem('agenda')) $coleta[] = ['Data, horário e serviço escolhidos', 'para reservar o seu horário e mandar lembretes'];
if ($modo === 'catalogo' || $tem('delivery')) $coleta[] = ['Endereço de entrega, itens do pedido e forma de pagamento', 'para preparar e entregar o pedido; o CPF só se você pedir na nota'];
if ($modo === 'orcamento' || $tem('orcamentos')) $coleta[] = ['Descrição do que você precisa e fotos que você enviar', 'para preparar o orçamento'];
if ($tem('imoveis')) $coleta[] = ['Imóvel de interesse e dia preferido para visita', 'para combinar a visita com você'];
if ($tem('avaliacoes')) $coleta[] = ['Nome e comentário da avaliação', 'a avaliação aparece no site depois de aprovada; o WhatsApp nunca aparece'];
if ($recOn('aniversario')) $coleta[] = ['Dia e mês do aniversário (sem o ano)', 'para mandar o seu cupom de aniversário'];
if ($recOn('fidelidade')) $coleta[] = ['Histórico de atendimentos ou pedidos concluídos', 'para contar os selos do cartão fidelidade'];
if ($recOn('reserva') || $recOn('fila')) $coleta[] = ['Número de pessoas e horário da reserva ou da fila', 'para organizar as mesas e avisar quando a sua estiver pronta'];
if ($tem('pix') || $recOn('vale')) $coleta[] = ['Dados do pagamento por Pix', 'o pagamento é feito pelo Mercado Pago; o site não guarda dados de cartão'];

$atualizado = date('d/m/Y', strtotime(substr((string)($empresa['atualizado_em'] ?? $empresa['criado_em'] ?? 'now'), 0, 10) ?: 'now'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Privacidade · <?= e($nome) ?></title>
<meta name="description" content="Como <?= e($nome) ?> usa e protege os seus dados pessoais, conforme a LGPD.">
<meta name="theme-color" content="<?= e($cor) ?>">
<style>
:root{--c:<?= e($cor) ?>;--fg:#16181d;--mute:#4b515c;--bg:#f5f6f8;--card:#fff;--linha:#e3e6eb}
@media (prefers-color-scheme:dark){:root{--fg:#eef0f3;--mute:#b4bac4;--bg:#111317;--card:#1a1d22;--linha:#2b3038}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--fg);font:16px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif}
header{border-top:6px solid var(--c);background:var(--card);border-bottom:1px solid var(--linha)}
.in{max-width:760px;margin:0 auto;padding:22px 18px}header a{color:var(--fg);font-weight:600}
h1{font-size:clamp(24px,5vw,32px);line-height:1.2;margin:6px 0 4px;text-wrap:balance}h2{font-size:19px;margin:28px 0 8px}
p,li{color:var(--mute)}strong{color:var(--fg)}main .in{padding-bottom:56px}
table{width:100%;border-collapse:collapse;background:var(--card);border:1px solid var(--linha);border-radius:12px;overflow:hidden}
th,td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--linha);vertical-align:top;font-size:15px}th{color:var(--fg)}tr:last-child td{border-bottom:0}
a{color:inherit}a:focus-visible{outline:3px solid var(--c);outline-offset:2px}
.btn{display:inline-flex;align-items:center;min-height:46px;padding:0 18px;border-radius:999px;background:var(--c);color:#fff;font-weight:700;text-decoration:none;margin-top:6px}
.tab{overflow-x:auto}
</style></head>
<body>
<header><div class="in"><a href="<?= e($voltar) ?>">← Voltar para o site</a>
<h1>Privacidade e seus dados</h1><p>Como <strong><?= e($nome) ?></strong> usa as informações que você envia por este site, de acordo com a Lei Geral de Proteção de Dados (LGPD, Lei 13.709/2018). Atualizado em <?= e($atualizado) ?>.</p></div></header>
<main><div class="in">
<h2>Quais dados pedimos e para quê</h2>
<p>Só pedimos o necessário para atender você. Nada é vendido nem usado para propaganda de outras empresas.</p>
<div class="tab"><table><thead><tr><th scope="col">Dado</th><th scope="col">Para que serve</th></tr></thead><tbody>
<?php foreach ($coleta as [$d, $p]): ?><tr><td><strong><?= e($d) ?></strong></td><td><?= e($p) ?></td></tr><?php endforeach; ?>
</tbody></table></div>

<h2>Mensagens no WhatsApp</h2>
<p>Usamos o seu WhatsApp para confirmar, lembrar e atualizar o que você pediu (e para avisos que você mesmo pediu, como cupom de aniversário). Se não quiser mais receber, é só pedir pelo contato abaixo.</p>

<h2>Com quem os dados são compartilhados</h2>
<ul>
<li>Com a plataforma GDS (sitegestor.com), que hospeda este site e guarda os dados em servidores protegidos, só para fazer o site funcionar.</li>
<li>Com o WhatsApp, para entregar as mensagens.</li>
<?php if ($tem('pix') || $recOn('vale') || $modo === 'catalogo'): ?><li>Com o Mercado Pago, quando você paga por Pix pelo site.</li><?php endif; ?>
<li>Com autoridades, apenas quando a lei obrigar.</li>
</ul>

<h2>Cookies e armazenamento no celular</h2>
<p>O site usa só o necessário para funcionar: guardar o carrinho, lembrar o que você escolheu e confirmar o envio de formulários. Não usamos cookies de propaganda.</p>

<h2>Por quanto tempo guardamos</h2>
<p>Enquanto você for nosso cliente e pelo tempo que a lei exigir (por exemplo, registros de vendas). Depois disso, os dados são apagados.</p>

<h2>Seus direitos</h2>
<p>Você pode, a qualquer momento e sem custo: saber quais dados temos sobre você, corrigir, pedir a cópia, pedir para apagar, e retirar a autorização de mensagens.</p>

<h2>Fale com a gente</h2>
<p>Responsável pelos dados: <strong><?= e($nome) ?></strong><?= !empty($empresa['endereco']) ? ', ' . e($empresa['endereco']) : '' ?>.</p>
<?php if (!empty($empresa['email'])): ?><p>E-mail: <strong><?= e($empresa['email']) ?></strong></p><?php endif; ?>
<?php if ($whatsLink): ?><a class="btn" href="<?= e($whatsLink . '?text=' . rawurlencode('Olá! Quero falar sobre os meus dados pessoais (LGPD).')) ?>" target="_blank" rel="noopener">Pedir pelo WhatsApp</a><?php endif; ?>
</div></main>
</body></html>
