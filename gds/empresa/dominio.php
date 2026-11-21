<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = (int)empresa_id_atual();
$carregar = function () use ($pdo, $empresaId) {
    $st = $pdo->prepare("SELECT * FROM empresas WHERE id=?"); $st->execute([$empresaId]); return $st->fetch();
};
$empresa = $carregar();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verificar'])) {
    @set_time_limit(60);
    $r = dominio_verificar($empresa);
    responder_json(['ok' => true] + $r);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_dominio'])) {
    $antes = $empresa['dominio_personalizado'] ?? '';
    $erro = dominio_salvar($empresaId, $_POST['dominio'] ?? '');
    if ($erro) flash('erro', $erro);
    else {
        $novo = dominio_normalizar($_POST['dominio'] ?? '');
        if ($novo !== '' && $novo !== $antes) {
            // Avisa o administrador: ele precisa ativar o domínio na hospedagem
            $adm = $pdo->query("SELECT email FROM usuarios WHERE role='admin' AND ativo=true ORDER BY id LIMIT 1")->fetch();
            if ($adm && !empty($adm['email'])) {
                $host = preg_replace('/:\d+$/', '', preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
                @mail($adm['email'], '=?UTF-8?B?' . base64_encode("Novo domínio para ativar: $novo") . '?=',
                    "A empresa {$empresa['nome']} cadastrou o domínio $novo.\n\nAtive na hospedagem (Hostinger: Sites → Painel → Domínios estacionados → adicionar $novo)\ne confira em Admin → Domínios.\n\n— GDS",
                    "From: GDS <nao-responda@$host>\r\nContent-Type: text/plain; charset=UTF-8");
            }
            flash('sucesso', 'Domínio salvo! Agora siga os passos abaixo.');
        } else flash('sucesso', $novo === '' ? 'Domínio removido. Seu site continua no endereço da plataforma.' : 'Domínio salvo.');
    }
    header('Location: dominio.php'); exit;
}

$empresa = $carregar();
$dom = $empresa['dominio_personalizado'] ?? '';
[$rotulo, $classe] = dominio_status_rotulo($empresa['dominio_status'] ?? null);
$registros = $dom ? dominio_registros_dns($dom) : [];
$urlPlataforma = wa_url_publica(array_merge($empresa, ['dominio_status' => null]));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Meu domínio - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Meu domínio</h1>
        <p class="subtitulo">Use um endereço só seu (ex.: <strong>www.seunegocio.com.br</strong>). Seu site continua funcionando também em <a class="link" href="<?= e($urlPlataforma) ?>" target="_blank"><?= e($urlPlataforma) ?></a>.</p></div></div>
    <?= flash_render() ?>

    <form method="post" class="form-box largo">
        <?= csrf_campo() ?>
        <h3>1. Qual é o seu domínio?</h3>
        <p class="dica">Ainda não tem? Registre em <a class="link" href="https://registro.br" target="_blank" rel="noopener">registro.br</a> (.com.br, cerca de R$ 40/ano) ou em outro site de domínios.</p>
        <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            <div style="flex:1;min-width:240px"><label for="dominio">Domínio</label><input type="text" id="dominio" name="dominio" value="<?= e($dom) ?>" placeholder="seunegocio.com.br" autocapitalize="off" spellcheck="false"></div>
            <button type="submit" name="salvar_dominio" value="1" style="margin-top:0"><?= $dom ? 'Alterar' : 'Salvar domínio' ?></button>
        </div>
    </form>

    <?php if ($dom): ?>
    <section class="form-box largo">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
            <h3 style="margin:0">2. Situação de <?= e($dom) ?></h3>
            <span class="pill <?= $classe ?>" id="pillStatus"><?= e($rotulo) ?></span>
        </div>
        <ul class="passos-dominio" id="passos">
            <li><span class="ico">•</span><span>Clique em <strong>Verificar agora</strong> para testar.</span></li>
        </ul>
        <button type="button" class="btn" id="btnVerificar" onclick="verificar()"><?= icone('atualizar', 16) ?> Verificar agora</button>
        <?php if (($empresa['dominio_status'] ?? '') === 'ativo'): ?>
            <a class="btn btn-ghost" href="https://<?= e($dom) ?>" target="_blank" rel="noopener"><?= icone('link', 16) ?> Abrir https://<?= e($dom) ?></a>
        <?php endif; ?>
        <?php if (!empty($empresa['dominio_verificado_em'])): ?><p class="dica">Última verificação: <?= date('d/m/Y H:i', strtotime($empresa['dominio_verificado_em'])) ?></p><?php endif; ?>
    </section>

    <section class="form-box largo">
        <h3>3. Aponte o domínio para a plataforma</h3>
        <p>No site onde você registrou o domínio, abra a parte de <strong>DNS</strong> (ou "Zona DNS") e crie estes registros:</p>
        <div class="tabela-wrap"><table class="dns-tabela">
            <thead><tr><th>Tipo</th><th>Nome / Host</th><th>Valor / Aponta para</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($registros as $r): ?>
            <tr><td><?= e($r['tipo']) ?></td><td><?= e($r['nome']) ?></td><td><strong><?= e($r['valor']) ?></strong></td>
                <td><button type="button" class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText(<?= e(json_encode($r['valor'])) ?>);this.textContent='Copiado!'">Copiar</button></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <p class="dica">Se já existir um registro A ou CNAME com o mesmo nome, <strong>edite</strong> o existente (não deixe dois). Não mexa nos registros MX se você usa e-mail no domínio.</p>
        <details style="margin-top:10px"><summary class="link" style="cursor:pointer">Passo a passo no Registro.br</summary>
            <ol class="dica" style="line-height:1.8"><li>Entre em registro.br → <strong>Meus domínios</strong> → clique no domínio.</li>
                <li>Em <strong>DNS</strong>, clique em <strong>Configurar zona DNS</strong> (se pedir, ative o "Modo avançado" e aguarde alguns minutos).</li>
                <li><strong>Nova entrada</strong> → tipo <strong>A</strong>, nome vazio, valor: o IP acima. Repita com o nome <strong>www</strong>.</li>
                <li>Salve. Pode levar até algumas horas para valer — depois clique em <strong>Verificar agora</strong>.</li></ol></details>
        <details style="margin-top:6px"><summary class="link" style="cursor:pointer">Passo a passo na Hostinger / GoDaddy / outros</summary>
            <ol class="dica" style="line-height:1.8"><li>Procure por <strong>DNS</strong>, <strong>Zona DNS</strong> ou <strong>Gerenciar DNS</strong> do domínio.</li>
                <li>Edite (ou crie) o registro <strong>A</strong> com nome <strong>@</strong> apontando para o IP acima, e outro <strong>A</strong> com nome <strong>www</strong>.</li>
                <li>Apague registros A/AAAA antigos do mesmo nome que apontem para outro lugar (ex.: "parking" do registrador).</li></ol></details>
    </section>
    <?php endif; ?>
</main>
</div>
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
async function verificar() {
    const b = document.getElementById('btnVerificar');
    if (!b) return;
    b.disabled = true; const txt = b.innerHTML; b.textContent = 'Verificando… (até 30s)';
    try {
        const fd = new FormData(); fd.append('verificar', '1'); fd.append('csrf_token', CSRF);
        const r = await (await fetch('dominio.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })).json();
        const lista = document.getElementById('passos');
        lista.innerHTML = '';
        Object.values(r.etapas || {}).forEach(et => {
            const li = document.createElement('li');
            li.innerHTML = '<span class="ico"></span><span><strong></strong><br><small></small></span>';
            li.querySelector('.ico').textContent = et.ok ? '✅' : '⏳';
            li.querySelector('strong').textContent = et.titulo;
            li.querySelector('small').textContent = et.detalhe;
            lista.appendChild(li);
        });
        const pill = document.getElementById('pillStatus');
        pill.textContent = r.resumo || '—';
        pill.className = 'pill ' + (r.status === 'ativo' ? 'pill-ok' : 'pill-aviso');
    } catch (e) { alert('Não foi possível verificar agora.'); }
    b.disabled = false; b.innerHTML = txt;
}
<?php if ($dom): ?>verificar();<?php endif; ?>
</script>
</body>
</html>
