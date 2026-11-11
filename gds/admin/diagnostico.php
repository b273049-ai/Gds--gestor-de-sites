<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_banco'])) {
    $ok = gds_garantir_schema(true);
    flash($ok ? 'sucesso' : 'erro', $ok ? 'Banco de dados atualizado!' : 'Ainda há erros — veja abaixo.');
    header('Location: diagnostico.php'); exit;
}

$itens = [];
$add = function ($grupo, $nome, $ok, $detalhe = '', $aviso = false) use (&$itens) { $itens[$grupo][] = compact('nome', 'ok', 'detalhe', 'aviso'); };

// PHP e extensões
$add('Servidor', 'PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '8.0', '>='), 'Precisa de PHP 8.0 ou mais novo.');
foreach (['pdo_pgsql' => 'conexão com o banco', 'curl' => 'IA, WhatsApp, PIX e verificação de domínio', 'mbstring' => 'textos com acento', 'json' => 'JSON'] as $ext => $pra) {
    $add('Servidor', "Extensão $ext", extension_loaded($ext), "Ative no painel da hospedagem (usada para $pra).");
}
$add('Servidor', 'Extensão intl (opcional)', extension_loaded('intl'), 'Só para domínios com acento.', true);
$add('Servidor', 'Função dns_get_record', function_exists('dns_get_record'), 'A verificação de DNS usa outro método.', true);

// Arquivos
$arquivos = ['includes/whatsapp.php', 'includes/dominio.php', 'includes/atendimento.php', 'includes/modulos.php', 'includes/site_render.php', 'includes/site_fragmentos.php',
             'includes/ia_site_engine.php', 'assets/js/gds-site.js', 'assets/js/gds-motion.js', 'assets/css/gds-componentes.css', 'site/pedido.php', 'cron.php'];
foreach (array_keys(gds_migracoes()) as $v) $arquivos[] = 'sql/' . gds_migracoes()[$v][0];
foreach ($arquivos as $a) $add('Arquivos', $a, is_file(__DIR__ . '/../' . $a), 'Arquivo faltando: envie de novo a pasta inteira do zip (sobrescrevendo).');

// Banco
$versao = (int)get_config('schema_versao', '0');
$add('Banco de dados', "Versão do banco: $versao de " . GDS_SCHEMA_VERSAO, $versao >= GDS_SCHEMA_VERSAO, 'Clique em "Atualizar banco agora".');
foreach (gds_migracoes() as $v => [$arq, $check]) {
    try { $ok = (bool)$pdo->query("SELECT ($check) AS ok")->fetch()['ok']; $det = ''; }
    catch (Throwable $e) { $ok = false; $det = $e->getMessage(); }
    $add('Banco de dados', "Atualização $v ($arq)", $ok, $det ?: 'Ainda não aplicada.');
}
$erroSchema = get_config('schema_erro', '');

// Configurações
$add('Configurações', 'IA configurada', ia_configurada(), 'Configurações → Inteligência Artificial.', true);
$add('Configurações', 'WhatsApp automático', wa_api_configurada(), 'Admin → WhatsApp (sem isso, as mensagens ficam para envio manual).', true);
$add('Configurações', 'IP do servidor (domínios)', dominio_ip_servidor() !== '', 'Admin → Domínios.', true);
$add('Configurações', 'Endereço da plataforma', get_config('plataforma_url', '') !== '', 'Admin → Domínios (usado nos links do WhatsApp).', true);
$ultimaRotina = (int)get_config('wa_rotina_ultima', '0');
$add('Configurações', 'Rotina automática (lembretes)', $ultimaRotina > time() - 86400, $ultimaRotina ? 'Última execução: ' . date('d/m/Y H:i', $ultimaRotina) : 'Nunca rodou.', true);
$add('Configurações', 'Fotos profissionais (Pexels)', get_config('pexels_api_key', '') !== '', 'Opcional: Configurações → Fotos (deixa os sites gerados mais bonitos).', true);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Diagnóstico - GDS Admin') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Diagnóstico</h1><p class="subtitulo">Confere se o servidor, os arquivos e o banco estão em ordem. Se algo deu erro, comece por aqui.</p></div>
        <form method="post"><?= csrf_campo() ?><button type="submit" name="atualizar_banco" value="1"><?= icone('atualizar', 16) ?> Atualizar banco agora</button></form></div>
    <?= flash_render() ?>
    <?php if ($erroSchema): ?><div class="alert alert-erro"><strong>Último erro ao atualizar o banco:</strong><pre style="white-space:pre-wrap;margin:8px 0 0;font-size:12px"><?= e($erroSchema) ?></pre></div><?php endif; ?>
    <div class="grid-2">
    <?php foreach ($itens as $grupo => $lista): ?>
        <section class="form-box largo">
            <h3><?= e($grupo) ?></h3>
            <ul class="passos-dominio">
            <?php foreach ($lista as $i): ?>
                <li><span class="ico"><?= $i['ok'] ? '✅' : ($i['aviso'] ? '⚠️' : '❌') ?></span><span><strong><?= e($i['nome']) ?></strong><?php if (!$i['ok'] && $i['detalhe']): ?><br><small><?= e($i['detalhe']) ?></small><?php endif; ?></span></li>
            <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
    </div>
</main>
</div>
</body>
</html>
