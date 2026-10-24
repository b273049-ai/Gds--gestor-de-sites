<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = empresa_id_atual();

$erroAgenda = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_agendamento'])) {
    $profissionalId = $_POST['profissional_id'] ?: null;
    $dataHora = $_POST['data_hora'];
    $conflito = false;
    if ($profissionalId) {
        $chk = $pdo->prepare("SELECT id FROM agendamentos WHERE empresa_id=? AND profissional_id=? AND data_hora=? AND status != 'cancelado' LIMIT 1");
        $chk->execute([$empresaId, $profissionalId, $dataHora]);
        $conflito = (bool)$chk->fetch();
    }
    if ($conflito) {
        $erroAgenda = 'Esse profissional já tem um agendamento nesse horário.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO agendamentos (empresa_id, cliente_id, servico_id, profissional_id, data_hora, observacoes) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $empresaId,
            $_POST['cliente_id'] ?: null,
            $_POST['servico_id'] ?: null,
            $profissionalId,
            $dataHora,
            $_POST['observacoes'],
        ]);
        header('Location: agenda.php'); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_status'])) {
    $pdo->prepare("UPDATE agendamentos SET status=? WHERE id=? AND empresa_id=?")
        ->execute([$_POST['status'], (int)$_POST['agendamento_id'], $empresaId]);
    header('Location: agenda.php'); exit;
}

$clientes = $pdo->prepare("SELECT * FROM clientes WHERE empresa_id=? ORDER BY nome");
$clientes->execute([$empresaId]); $clientes = $clientes->fetchAll();

$servicos = $pdo->prepare("SELECT * FROM servicos WHERE empresa_id=? AND ativo=true ORDER BY nome");
$servicos->execute([$empresaId]); $servicos = $servicos->fetchAll();

$profissionais = $pdo->prepare("SELECT * FROM profissionais WHERE empresa_id=? AND ativo=true ORDER BY nome");
$profissionais->execute([$empresaId]); $profissionais = $profissionais->fetchAll();

$agendamentos = $pdo->prepare("
    SELECT a.*, c.nome as cliente_nome, s.nome as servico_nome
    FROM agendamentos a
    LEFT JOIN clientes c ON c.id = a.cliente_id
    LEFT JOIN servicos s ON s.id = a.servico_id
    WHERE a.empresa_id = ?
    ORDER BY a.data_hora DESC LIMIT 50
");
$agendamentos->execute([$empresaId]); $agendamentos = $agendamentos->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Agenda - Meu Negócio</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<div class="content">
    <h1>Agenda</h1>
    <?php if ($erroAgenda): ?><div class="alert-erro"><?= e($erroAgenda) ?></div><?php endif; ?>

    <div class="form-box" style="margin-bottom:24px;">
        <h3>Novo agendamento</h3>
        <form method="post">
            <label>Cliente</label>
            <select name="cliente_id"><option value="">-- selecione --</option>
                <?php foreach ($clientes as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['nome']) ?></option><?php endforeach; ?>
            </select>
            <label>Serviço</label>
            <select name="servico_id"><option value="">-- selecione --</option>
                <?php foreach ($servicos as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['nome']) ?></option><?php endforeach; ?>
            </select>
            <label>Profissional</label>
            <select name="profissional_id"><option value="">-- selecione --</option>
                <?php foreach ($profissionais as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['nome']) ?></option><?php endforeach; ?>
            </select>
            <label>Data e hora</label>
            <input type="datetime-local" name="data_hora" required>
            <label>Observações</label>
            <textarea name="observacoes" rows="2"></textarea>
            <button type="submit" name="criar_agendamento">Agendar</button>
        </form>
    </div>

    <table>
        <tr><th>Data/Hora</th><th>Cliente</th><th>Serviço</th><th>Status</th></tr>
        <?php $statusAg = ['agendado'=>'Agendado','confirmado'=>'Confirmado','concluido'=>'Concluído','cancelado'=>'Cancelado']; ?>
        <?php foreach ($agendamentos as $a): ?>
        <tr>
            <td><?= date('d/m/Y H:i', strtotime($a['data_hora'])) ?></td>
            <td><?= e($a['cliente_nome'] ?? '-') ?></td>
            <td><?= e($a['servico_nome'] ?? '-') ?></td>
            <td>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="agendamento_id" value="<?= $a['id'] ?>">
                    <input type="hidden" name="atualizar_status" value="1">
                    <select name="status" onchange="this.form.submit()">
                        <?php foreach ($statusAg as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $a['status']==$val?'selected':'' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</div>
</body>
</html>
