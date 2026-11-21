<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = (int)empresa_id_atual();
if (empresa_pode_usar_estudio($empresaId)) { header('Location: /empresa/ia_estudio.php'); exit; }
$empresa = $pdo->prepare("SELECT slug FROM empresas WHERE id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Meu Site') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Meu site</h1><p class="subtitulo">Quem cria e edita o design do site é o administrador da plataforma.</p></div></div>
    <div class="form-box">
        <p>Você continua cuidando de <strong>serviços, profissionais, agenda, cupons e clientes</strong> — tudo isso aparece no site automaticamente.
        A cor de destaque e os horários de atendimento ficam em Configurações.</p>
        <p class="dica" style="margin-top:12px">Quer criar e ajustar o site sozinho conversando com a IA? Peça ao administrador para liberar o <strong>Estúdio IA</strong> no seu plano.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px">
            <a href="/<?= e($empresa['slug'] ?? '') ?>" target="_blank" class="btn"><?= icone('olho') ?> Ver meu site</a>
            <a href="/empresa/configuracoes.php" class="btn btn-ghost"><?= icone('engrenagem') ?> Configurações</a>
        </div>
    </div>
</main>
</div>
</body>
</html>
