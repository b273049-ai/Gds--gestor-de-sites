<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verificar'])) {
    @set_time_limit(60);
    $st = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
    $st->execute([(int)$_POST['empresa_id']]);
    $emp = $st->fetch();
    responder_json($emp ? ['ok' => true] + dominio_verificar($emp) : ['ok' => false]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_servidor'])) {
    $ip = trim($_POST['servidor_ip'] ?? '');
    if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP)) flash('erro', 'IP inválido.');
    else {
        set_config('servidor_ip', $ip);
        $url = rtrim(url_valida_ou_vazia($_POST['plataforma_url'] ?? ''), '/');
        set_config('plataforma_url', $url);
        flash('sucesso', 'Dados do servidor salvos.');
    }
    header('Location: dominios.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_dominio'])) {
    $erro = dominio_salvar((int)$_POST['empresa_id'], $_POST['dominio'] ?? '');
    flash($erro ? 'erro' : 'sucesso', $erro ?: 'Domínio salvo.');
    header('Location: dominios.php'); exit;
}

$ip = dominio_ip_servidor();
$ipSalvo = get_config('servidor_ip', '');
$platUrl = get_config('plataforma_url', '');
$empresas = $pdo->query("SELECT id, nome, slug, dominio_personalizado, dominio_status, dominio_verificado_em FROM empresas WHERE dominio_personalizado IS NOT NULL AND dominio_personalizado<>'' ORDER BY (dominio_status='ativo'), nome")->fetchAll();
$todas = $pdo->query("SELECT id, nome FROM empresas WHERE dominio_personalizado IS NULL OR dominio_personalizado='' ORDER BY nome")->fetchAll();
$pendentes = array_filter($empresas, fn($e) => ($e['dominio_status'] ?? '') !== 'ativo');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Domínios - GDS Admin') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Domínios próprios</h1>
        <p class="subtitulo">Sites de clientes no endereço deles (ex.: barbeariadoze.com.br). <?= $pendentes ? '<strong>' . count($pendentes) . ' aguardando</strong>.' : 'Tudo em dia.' ?></p></div></div>
    <?= flash_render() ?>

    <div class="grid-2">
    <form method="post" class="form-box largo">
        <?= csrf_campo() ?>
        <h3>Servidor da plataforma</h3>
        <label>IP do servidor (vai no registro A que o cliente cria)</label>
        <input type="text" name="servidor_ip" value="<?= e($ipSalvo) ?>" placeholder="<?= e($ip ?: 'ex.: 185.123.45.67') ?>">
        <p class="dica"><?= $ip ? 'Detectado automaticamente: <strong>' . e($ip) . '</strong>. ' : '' ?>Na Hostinger o IP certo aparece em <strong>Sites → Painel → Detalhes do plano</strong> (ou Informações da hospedagem). Se a plataforma usa o CDN da Hostinger, use o IP da hospedagem, não o do CDN.</p>
        <label>Endereço principal da plataforma</label>
        <input type="url" name="plataforma_url" value="<?= e($platUrl) ?>" placeholder="https://seudominio.com.br">
        <p class="dica">Usado nos links enviados por WhatsApp (acompanhar pedido, cancelar agendamento) e no lembrete automático.</p>
        <button type="submit" name="salvar_servidor" value="1">Salvar</button>
    </form>

    <section class="form-box largo">
        <h3>Como ativar o domínio de um cliente (Hostinger)</h3>
        <ol class="dica" style="line-height:1.9">
            <li>O cliente cadastra o domínio em <strong>Meu domínio</strong> (ou você, na lista abaixo) e cria os registros <strong>A</strong> (<code>@</code> e <code>www</code>) apontando para <strong><?= e($ip ?: 'o IP do servidor') ?></strong>.</li>
            <li>Na Hostinger: <strong>Sites → Painel do site da plataforma → Domínios → Domínios estacionados</strong> (Parked Domains) → digite o domínio → <strong>Estacionar</strong>.<br>
                <small>Se der erro, é porque o DNS ainda não está apontando — espere e tente de novo.</small></li>
            <li>O SSL (cadeado) é instalado sozinho em 1 a 2 horas. Se não aparecer, vá em <strong>Segurança → SSL</strong> e instale para o domínio.</li>
            <li>Clique em <strong>Verificar</strong> aqui: quando ficar "Funcionando", o site do cliente abre no domínio dele, com https.</li>
        </ol>
        <p class="dica">Domínio estacionado mostra o mesmo conteúdo da plataforma — é o sistema que reconhece o domínio e abre o site certo. Não crie "site novo" para o cliente na Hostinger.</p>
    </section>
    </div>

    <section class="form-box largo">
        <h3>Domínios cadastrados</h3>
        <div class="tabela-wrap"><table>
            <thead><tr><th>Empresa</th><th>Domínio</th><th>Situação</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($empresas as $emp): [$rot, $cls] = dominio_status_rotulo($emp['dominio_status']); ?>
            <tr>
                <td><a class="link" href="empresa_editar.php?id=<?= (int)$emp['id'] ?>"><?= e($emp['nome']) ?></a></td>
                <td><strong><?= e($emp['dominio_personalizado']) ?></strong><?php if ($emp['dominio_status'] === 'ativo'): ?> <a class="link" target="_blank" rel="noopener" href="https://<?= e($emp['dominio_personalizado']) ?>">abrir ↗</a><?php endif; ?></td>
                <td><span class="pill <?= $cls ?>" data-pill="<?= (int)$emp['id'] ?>"><?= e($rot) ?></span><span class="mini" data-det="<?= (int)$emp['id'] ?>"><?= $emp['dominio_verificado_em'] ? 'verificado ' . date('d/m H:i', strtotime($emp['dominio_verificado_em'])) : '' ?></span></td>
                <td class="acoes"><button type="button" class="btn btn-ghost btn-sm" onclick="verificar(<?= (int)$emp['id'] ?>, this)">Verificar</button></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$empresas): ?><tr class="vazio"><td colspan="4">Nenhuma empresa com domínio próprio ainda.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
        <?php if ($todas): ?>
        <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-top:16px">
            <?= csrf_campo() ?>
            <div><label>Adicionar domínio para</label><select name="empresa_id"><?php foreach ($todas as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['nome']) ?></option><?php endforeach; ?></select></div>
            <div style="flex:1;min-width:200px"><label>Domínio</label><input type="text" name="dominio" placeholder="cliente.com.br"></div>
            <button type="submit" name="salvar_dominio" value="1" style="margin-top:0">Adicionar</button>
        </form>
        <?php endif; ?>
    </section>
</main>
</div>
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
async function verificar(id, b) {
    b.disabled = true; b.textContent = 'Verificando…';
    try {
        const fd = new FormData(); fd.append('verificar', '1'); fd.append('empresa_id', id); fd.append('csrf_token', CSRF);
        const r = await (await fetch('dominios.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })).json();
        const pill = document.querySelector('[data-pill="' + id + '"]');
        pill.textContent = r.resumo || '—'; pill.className = 'pill ' + (r.status === 'ativo' ? 'pill-ok' : 'pill-aviso');
        const falha = Object.values(r.etapas || {}).find(et => !et.ok);
        document.querySelector('[data-det="' + id + '"]').textContent = falha ? falha.detalhe : 'Tudo certo.';
    } catch (e) { alert('Falha ao verificar.'); }
    b.disabled = false; b.textContent = 'Verificar';
}
</script>
</body>
</html>
