<?php
// Política de privacidade pública (a Meta e o TikTok pedem este endereço para ativar o app)
require_once __DIR__ . '/config/db.php';
$marca = get_config('mkt_marca', '') ?: 'GDS Sites';
$contato = get_config('mkt_whatsapp', '');
$email = get_config('smtp_de', '') ?: get_config('smtp_usuario', '');
header('Cache-Control: public, max-age=3600');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Política de privacidade · <?= htmlspecialchars($marca) ?></title>
<meta name="robots" content="index, follow">
<style>
:root { --bg: #f7f7fb; --txt: #1d1f2b; --dim: #5b6075; --card: #fff; --borda: #e3e5ef; }
@media (prefers-color-scheme: dark) { :root { --bg: #0d0f1c; --txt: #eef0fa; --dim: #a6abc4; --card: #151830; --borda: #262b4d; } }
body { margin: 0; background: var(--bg); color: var(--txt); font: 16px/1.65 system-ui, -apple-system, "Segoe UI", sans-serif; }
main { max-width: 760px; margin: 0 auto; padding: 32px 16px 60px; }
article { background: var(--card); border: 1px solid var(--borda); border-radius: 16px; padding: 24px; }
h1 { font-size: 28px; margin: 0 0 4px; } h2 { font-size: 19px; margin: 26px 0 6px; } p, li { color: var(--dim); } small { color: var(--dim); }
</style>
</head>
<body>
<main><article>
<h1>Política de privacidade</h1>
<small><?= htmlspecialchars($marca) ?> · atualizada em <?= date('d/m/Y', filemtime(__FILE__)) ?></small>

<h2>Quem somos</h2>
<p><?= htmlspecialchars($marca) ?> cria sites e ferramentas de atendimento (agendamento, pedidos, orçamento e WhatsApp) para pequenos negócios.</p>

<h2>Que dados usamos</h2>
<ul>
<li><b>Clientes da plataforma:</b> nome, e-mail, telefone e os dados do negócio que você cadastra para montar o seu site.</li>
<li><b>Visitantes dos sites:</b> o que a pessoa envia nos formulários (nome, telefone, pedido, horário) para o negócio atender.</li>
<li><b>Instagram e TikTok (quando você conecta a sua conta):</b> o nome de usuário, os números públicos dos seus posts (alcance, curtidas, comentários, salvos e envios), e os comentários e mensagens do direct que chegam aos seus posts, para responder quem pediu informação. Publicamos somente o conteúdo que você aprovou no seu plano.</li>
</ul>

<h2>Para que usamos</h2>
<p>Só para prestar o serviço: publicar seus posts no horário escolhido, responder quem comentou pedindo informação, mostrar os resultados e melhorar os próximos posts. Não vendemos nem alugamos dados.</p>

<h2>Com quem compartilhamos</h2>
<p>Com os serviços necessários para funcionar: hospedagem e banco de dados, a Meta (Instagram) e o TikTok pelas APIs oficiais, o provedor de WhatsApp e o provedor de inteligência artificial que escreve os textos.</p>

<h2>Por quanto tempo guardamos</h2>
<p>Enquanto a conta estiver ativa. Ao desconectar o Instagram ou o TikTok, apagamos as chaves de acesso na hora.</p>

<h2>Como pedir a exclusão dos seus dados</h2>
<p>Você pode desconectar a rede a qualquer momento no painel (Marketing → Instagram e TikTok → Desconectar) ou remover o app em Instagram → Configurações → Apps e sites. Para apagar todos os seus dados, peça<?= $email ? ' pelo e-mail <b>' . htmlspecialchars($email) . '</b>' : '' ?><?= $contato ? ($email ? ' ou' : '') . ' pelo WhatsApp <b>' . htmlspecialchars($contato) . '</b>' : ' pelos nossos canais de contato' ?>: apagamos em até 30 dias e confirmamos.</p>

<h2>Seus direitos (LGPD)</h2>
<p>Você pode pedir acesso, correção, portabilidade ou exclusão dos seus dados, e revogar o consentimento, pelos mesmos contatos acima.</p>
</article></main>
</body>
</html>
