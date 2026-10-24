<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$empresaId = (int)($_GET['empresa_id'] ?? 0);
$empresa = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();
if (!$empresa) { header('Location: empresas.php'); exit; }

$site = $pdo->prepare("SELECT * FROM sites WHERE empresa_id=?");
$site->execute([$empresaId]); $site = $site->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['desativar_ia'])) {
    $pdo->prepare("UPDATE sites SET ia_modo_total=false WHERE empresa_id=?")->execute([$empresaId]);
    header("Location: ia_criador_site.php?empresa_id=$empresaId&ok=1"); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['html_gerado_b64'])) {
    // Chega codificado em base64 pra não ser barrado pelo firewall da hospedagem
    // (ele costuma bloquear POST com HTML/JS cru, achando que é ataque).
    $decodificado = base64_decode($_POST['html_gerado_b64']);
    $pdo->prepare("UPDATE sites SET html_gerado=? WHERE empresa_id=?")->execute([$decodificado, $empresaId]);
    header("Location: ia_criador_site.php?empresa_id=$empresaId&ok=1"); exit;
}

$iaConfigurada = trim(get_config('ia_api_key', '')) !== '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Criador de Site com IA - GDS Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>✨ Criador de Site com IA (modo total) — <?= e($empresa['nome']) ?></h1>
    <p style="margin-bottom:14px;"><a href="site_hub.php?empresa_id=<?= $empresaId ?>" style="color:#4f7cff;font-size:13px;">← voltar pro painel de site desta empresa</a></p>
    <?php if (isset($_GET['ok'])): ?><div class="alert-erro" style="background:#1f4d2e;color:#5ee06c;">Configuração salva!</div><?php endif; ?>

    <p style="color:#9aa0c0;max-width:640px;margin-bottom:16px;">
        A IA desenha o site inteiro do zero — layout, cores, tipografia, animações — em vez de usar um dos 4 estilos prontos.
        Conte tudo sobre o negócio como se estivesse explicando pra um designer: pode colar o texto do perfil do Google,
        a história, o que diferencia, o clima que quer passar.
    </p>

    <?php if (!$iaConfigurada): ?>
    <div class="alert-erro" style="max-width:640px;">
        A IA ainda não foi configurada. Vá em Configurações e cole uma chave (a do Google Gemini é gratuita).
    </div>
    <?php endif; ?>

    <div class="form-box" style="max-width:640px;">
        <label>Conte sobre o negócio</label>
        <textarea id="briefing" rows="8" placeholder="Ex: Barbearia Vintage, em Jundiaí-SP. Atende há 8 anos, ambiente descontraído com música e cerveja artesanal grátis pros clientes. Diferencial: corte à navalha e barba com toalha quente. Público jovem, 20-40 anos. Quer um site com vibe old-school moderna, bar americano dos anos 50 misturado com tech."><?= e($site['ia_briefing']) ?></textarea>
        <button class="btn" style="margin-top:12px;" onclick="gerarSite()">🚀 Gerar site com IA</button>
        <p style="color:#5c6199;font-size:12px;margin-top:8px;">Cada geração sorteia um estilo visual diferente (brutalista, editorial, glass, luxo escuro, etc). Não gostou? Clique de novo — vem outro completamente diferente.</p>
        <span id="status" style="font-size:13px;color:#9aa0c0;margin-left:8px;"></span>
    </div>

    <div id="previewBox" style="margin-top:24px;<?= empty($site['html_gerado']) ? 'display:none;' : '' ?>">
        <h3 style="font-size:16px;margin-bottom:10px;">Pré-visualização — <?= pg_bool($site['ia_modo_total'] ?? false) ? 'já está ativo no site público' : 'modo IA desativado' ?></h3>
        <iframe id="previewFrame" src="/site/preview.php?empresa_id=<?= $empresaId ?>&modo=ia" style="width:100%;height:600px;border:1px solid #2a2f5c;border-radius:12px;background:#fff;"></iframe>
        <p style="color:#5c6199;font-size:12px;margin-top:10px;">
            Assim que gerado, o site com IA já fica ativo automaticamente (não precisa de passo extra). Ele só fica
            visível pra clientes se também estiver "Publicado" no Editor de Conteúdo.
        </p>
        <?php if (pg_bool($site['ia_modo_total'] ?? false)): ?>
        <form method="post" style="margin-top:8px;display:inline-block;">
            <button type="submit" name="desativar_ia" class="btn btn-sm" onclick="return confirm('Voltar para o template manual em vez do site gerado por IA?')">Voltar pro template manual</button>
        </form>
        <?php endif; ?>
        <button type="button" class="btn btn-sm" style="margin-top:8px;" onclick="document.getElementById('editorBox').style.display='block';this.style.display='none';">✏️ Editar código direto</button>

        <div id="editorBox" style="display:none;margin-top:16px;">
            <form method="post" onsubmit="document.getElementById('htmlB64').value = btoa(unescape(encodeURIComponent(document.getElementById('htmlEditor').value)));">
                <textarea id="htmlEditor" rows="18" style="width:100%;font-family:monospace;font-size:12px;background:#0f1226;color:#e8e8f0;border:1px solid #2a2f5c;border-radius:8px;padding:10px;"><?= e($site['html_gerado']) ?></textarea>
                <input type="hidden" id="htmlB64" name="html_gerado_b64">
                <button type="submit" style="margin-top:10px;">Salvar edição</button>
            </form>
            <p style="color:#5c6199;font-size:12px;margin-top:8px;">Edite o HTML/CSS livremente. Não remova os marcadores tipo <code>{{SERVICOS}}</code> — eles viram dados reais na hora de exibir.</p>
        </div>
    </div>
</div>
</div>

<script>
async function gerarSite() {
    const briefing = document.getElementById('briefing').value.trim();
    if (!briefing) { alert('Conte pelo menos um pouco sobre o negócio.'); return; }
    const status = document.getElementById('status');
    status.innerText = 'Estudando o negócio e desenhando o conceito... depois gera o código (até 2-3 min no total). Não feche essa tela.';
    try {
        const resp = await fetch('ia_gerar_site_completo.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'briefing=' + encodeURIComponent(briefing) + '&empresa_id=<?= $empresaId ?>'
        });
        const data = await resp.json();
        if (data.ok) {
            status.innerText = 'Site gerado (estilo: ' + data.direcao_visual.split(':')[0] + ') e já ativado no site público!';
            document.getElementById('previewBox').style.display = 'block';
            document.getElementById('previewFrame').src = '/site/preview.php?empresa_id=<?= $empresaId ?>&modo=ia&t=' + Date.now();
        } else {
            status.innerText = data.erro || 'Erro ao gerar o site.';
        }
    } catch (e) {
        status.innerText = 'Erro ao chamar a IA.';
    }
}
</script>
</body>
</html>
