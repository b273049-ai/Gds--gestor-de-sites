<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = empresa_id_atual();

$avaliacoes = $pdo->prepare("
    SELECT av.*, c.nome as cliente_nome FROM avaliacoes av
    LEFT JOIN clientes c ON c.id = av.cliente_id
    WHERE av.empresa_id=? ORDER BY av.criado_em DESC
");
$avaliacoes->execute([$empresaId]); $avaliacoes = $avaliacoes->fetchAll();

$media = 0;
if (count($avaliacoes) > 0) {
    $media = array_sum(array_column($avaliacoes, 'nota')) / count($avaliacoes);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Avaliações - Meu Negócio</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Avaliações</h1>
    <div class="cards">
        <div class="card"><div class="label">Nota Média</div><div class="value"><?= number_format($media,1) ?> ★</div></div>
        <div class="card"><div class="label">Total de Avaliações</div><div class="value"><?= count($avaliacoes) ?></div></div>
    </div>
    <table>
        <tr><th>Cliente</th><th>Nota</th><th>Comentário</th><th>Data</th></tr>
        <?php foreach ($avaliacoes as $av): ?>
        <tr>
            <td><?= e($av['cliente_nome'] ?? 'Cliente') ?></td>
            <td><?= str_repeat('★', (int)$av['nota']) ?></td>
            <td><?= e($av['comentario']) ?></td>
            <td><?= date('d/m/Y', strtotime($av['criado_em'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($avaliacoes)): ?><tr><td colspan="4">Nenhuma avaliação recebida ainda.</td></tr><?php endif; ?>
    </table>
</div>
</div>
</body>
</html>
