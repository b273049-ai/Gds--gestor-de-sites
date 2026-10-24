<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Meu Negócio</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Essa função mudou de lugar</h1>
    <div class="form-box" style="max-width:480px;">
        <p style="color:#e8e8f0;line-height:1.6;">
            A criação e edição do site agora é feita pelo administrador da plataforma — ele cuida do conteúdo,
            do layout e até pode usar IA pra desenhar o site inteiro.
        </p>
        <p style="color:#9aa0c0;margin-top:12px;font-size:14px;">
            Você ainda pode ajustar a <strong>cor do site</strong> em Configurações, e continua cuidando de
            serviços, profissionais, agenda e clientes por aqui normalmente.
        </p>
        <a href="/empresa/configuracoes.php" class="btn" style="margin-top:16px;display:inline-block;">Ir para Configurações</a>
    </div>
</div>
</div>
</body>
</html>
