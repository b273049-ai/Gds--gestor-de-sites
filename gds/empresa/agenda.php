<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/agenda_helper.php';
exigir_login('empresa');
$empresaId = (int)empresa_id_atual();
$statusAg = ['agendado' => 'Agendado', 'confirmado' => 'Confirmado', 'concluido' => 'Concluído', 'cancelado' => 'Cancelado'];

$empresa = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();

$form = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_agendamento'])) {
    $form = $_POST;
    $dataHora = str_replace('T', ' ', $_POST['data_hora'] ?? '');
    $profissionalId = (int)($_POST['profissional_id'] ?? 0) ?: null;
    $servicoId = (int)($_POST['servico_id'] ?? 0) ?: null;
    $clienteId = (int)($_POST['cliente_id'] ?? 0) ?: null;
    $erro = '';

    // Tudo precisa pertencer a esta empresa
    $pertence = function ($tabela, $id) use ($pdo, $empresaId) {
        if (!$id) return true;
        $s = $pdo->prepare("SELECT * FROM $tabela WHERE id=? AND empresa_id=?"); $s->execute([$id, $empresaId]); return $s->fetch();
    };
    $servico = $pertence('servicos', $servicoId);
    $dt = DateTime::createFromFormat('Y-m-d H:i', $dataHora);
    if (!$dt) $erro = 'Informe data e hora.';
    elseif (!$pertence('profissionais', $profissionalId) || !$servico || !$pertence('clientes', $clienteId)) $erro = 'Dados inválidos.';
    elseif (!$clienteId && trim($_POST['novo_cliente_nome'] ?? '') === '') $erro = 'Escolha um cliente ou informe o nome de um cliente novo.';
    else {
        $dur = is_array($servico) && (int)$servico['duracao_minutos'] > 0 ? (int)$servico['duracao_minutos'] : agenda_config($empresa)['intervalo'];
        $ini = (int)$dt->format('G') * 60 + (int)$dt->format('i');
        $livre = agenda_intervalo_livre(agenda_ocupacoes_do_dia($empresaId, $dt->format('Y-m-d')), $ini, $dur, $profissionalId, agenda_qtd_profissionais($empresaId));
        if (!$livre && !isset($_POST['forcar'])) $erro = $profissionalId ? 'Esse profissional já tem um agendamento que se sobrepõe a esse horário. Marque "agendar mesmo assim" se quiser encaixar.' : 'Todos os profissionais estão ocupados nesse horário. Marque "agendar mesmo assim" se quiser encaixar.';
    }
    if ($erro === '') {
        if (!$clienteId) {
            $n = $pdo->prepare("INSERT INTO clientes (empresa_id, nome, telefone) VALUES (?,?,?) RETURNING id");
            $n->execute([$empresaId, trim($_POST['novo_cliente_nome']), trim($_POST['novo_cliente_telefone'] ?? '') ?: null]);
            $clienteId = $n->fetch()['id'];
        }
        $pdo->prepare("INSERT INTO agendamentos (empresa_id, cliente_id, servico_id, profissional_id, data_hora, status, observacoes) VALUES (?,?,?,?,?,?,?)")
            ->execute([$empresaId, $clienteId, $servicoId, $profissionalId, $dt->format('Y-m-d H:i:00'), 'confirmado', trim($_POST['observacoes'] ?? '') ?: null]);
        flash('sucesso', 'Agendamento criado para ' . $dt->format('d/m/Y \à\s H:i') . '.');
        header('Location: agenda.php?ver=' . urlencode($_GET['ver'] ?? 'proximos')); exit;
    }
    flash('erro', $erro);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_status']) && isset($statusAg[$_POST['status'] ?? ''])) {
    $pdo->prepare("UPDATE agendamentos SET status=? WHERE id=? AND empresa_id=?")->execute([$_POST['status'], (int)$_POST['agendamento_id'], $empresaId]);
    flash('sucesso', 'Status atualizado.');
    header('Location: agenda.php?' . http_build_query(array_intersect_key($_GET, ['ver' => 1, 'data' => 1, 'profissional' => 1]))); exit;
}

$clientes = $pdo->prepare("SELECT id, nome FROM clientes WHERE empresa_id=? ORDER BY nome"); $clientes->execute([$empresaId]); $clientes = $clientes->fetchAll();
$servicos = $pdo->prepare("SELECT * FROM servicos WHERE empresa_id=? AND ativo=true ORDER BY nome"); $servicos->execute([$empresaId]); $servicos = $servicos->fetchAll();
$profissionais = $pdo->prepare("SELECT * FROM profissionais WHERE empresa_id=? AND ativo=true ORDER BY nome"); $profissionais->execute([$empresaId]); $profissionais = $profissionais->fetchAll();

// Filtros
$ver = $_GET['ver'] ?? 'proximos';
$data = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data'] ?? '') ? $_GET['data'] : '';
$filtroProf = (int)($_GET['profissional'] ?? 0);
$where = ['a.empresa_id = ?']; $params = [$empresaId];
if ($data) { $where[] = 'a.data_hora::date = ?'; $params[] = $data; $ver = 'data'; }
elseif ($ver === 'hoje') $where[] = 'a.data_hora::date = CURRENT_DATE';
elseif ($ver === 'semana') $where[] = "a.data_hora >= CURRENT_DATE AND a.data_hora < CURRENT_DATE + INTERVAL '7 days'";
elseif ($ver === 'passados') $where[] = 'a.data_hora < NOW()';
elseif ($ver === 'proximos') $where[] = "a.data_hora >= NOW() - INTERVAL '2 hours'";
if ($filtroProf) { $where[] = 'a.profissional_id = ?'; $params[] = $filtroProf; }
$ordem = $ver === 'passados' || $ver === 'todos' ? 'DESC' : 'ASC';
$agendamentos = $pdo->prepare("
    SELECT a.*, c.nome AS cliente_nome, c.telefone, s.nome AS servico_nome, s.duracao_minutos, pr.nome AS profissional_nome
    FROM agendamentos a
    LEFT JOIN clientes c ON c.id = a.cliente_id
    LEFT JOIN servicos s ON s.id = a.servico_id
    LEFT JOIN profissionais pr ON pr.id = a.profissional_id
    WHERE " . implode(' AND ', $where) . " ORDER BY a.data_hora $ordem LIMIT 200");
$agendamentos->execute($params); $agendamentos = $agendamentos->fetchAll();
$diasSemana = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Agenda - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Agenda</h1><p class="subtitulo">Agendamentos do site e os que você marcar aqui.</p></div></div>
    <?= flash_render() ?>

    <details class="form-box" id="novo" <?= $form ? 'open' : '' ?>>
        <summary><?= icone('mais') ?> Novo agendamento</summary>
        <form method="post">
            <?= csrf_campo() ?>
            <div class="form-row">
                <div><label>Cliente</label>
                    <select name="cliente_id" onchange="document.getElementById('novoCliente').hidden = this.value !== ''">
                        <option value="">+ Cliente novo</option>
                        <?php foreach ($clientes as $c): ?><option value="<?= $c['id'] ?>" <?= ($form['cliente_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label>Data e hora</label><input type="datetime-local" name="data_hora" required value="<?= e($form['data_hora'] ?? '') ?>"></div>
            </div>
            <div class="form-row" id="novoCliente" <?= !empty($form['cliente_id']) ? 'hidden' : '' ?>>
                <div><label>Nome do cliente novo</label><input type="text" name="novo_cliente_nome" value="<?= e($form['novo_cliente_nome'] ?? '') ?>"></div>
                <div><label>Telefone</label><input type="tel" name="novo_cliente_telefone" value="<?= e($form['novo_cliente_telefone'] ?? '') ?>"></div>
            </div>
            <div class="form-row">
                <div><label>Serviço</label><select name="servico_id"><option value="">—</option><?php foreach ($servicos as $s): ?><option value="<?= $s['id'] ?>" <?= ($form['servico_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= e($s['nome']) ?> (<?= (int)$s['duracao_minutos'] ?> min)</option><?php endforeach; ?></select></div>
                <div><label>Profissional</label><select name="profissional_id"><option value="">—</option><?php foreach ($profissionais as $p): ?><option value="<?= $p['id'] ?>" <?= ($form['profissional_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?></option><?php endforeach; ?></select></div>
            </div>
            <label>Observações</label><textarea name="observacoes" rows="2"><?= e($form['observacoes'] ?? '') ?></textarea>
            <?php if ($form): ?><label class="check" style="margin-top:10px"><input type="checkbox" name="forcar"> Agendar mesmo assim (encaixe)</label><?php endif; ?>
            <button type="submit" name="criar_agendamento" value="1">Agendar</button>
        </form>
    </details>

    <form class="toolbar" method="get">
        <?php foreach (['proximos' => 'Próximos', 'hoje' => 'Hoje', 'semana' => '7 dias', 'passados' => 'Passados', 'todos' => 'Todos'] as $v => $l): ?>
        <a class="btn btn-sm <?= $ver === $v ? '' : 'btn-ghost' ?>" href="?ver=<?= $v ?><?= $filtroProf ? '&profissional=' . $filtroProf : '' ?>"><?= $l ?></a>
        <?php endforeach; ?>
        <input type="date" name="data" value="<?= e($data) ?>" onchange="this.form.submit()" style="max-width:170px" aria-label="Ver um dia">
        <?php if ($profissionais): ?>
        <select name="profissional" onchange="this.form.submit()"><option value="">Todos os profissionais</option><?php foreach ($profissionais as $p): ?><option value="<?= $p['id'] ?>" <?= $filtroProf == $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?></option><?php endforeach; ?></select>
        <?php endif; ?>
        <input type="hidden" name="ver" value="<?= e($ver === 'data' ? 'todos' : $ver) ?>">
    </form>

    <div class="tabela-wrap"><table>
        <thead><tr><th>Quando</th><th>Cliente</th><th>Serviço</th><th>Profissional</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($agendamentos as $a): $ts = strtotime($a['data_hora']); ?>
        <tr>
            <td><strong><?= date('d/m/Y', $ts) ?></strong> <?= date('H:i', $ts) ?><span class="mini"><?= $diasSemana[(int)date('w', $ts)] ?><?= $a['duracao_minutos'] ? ' · ' . (int)$a['duracao_minutos'] . ' min' : '' ?></span></td>
            <td><?= e($a['cliente_nome'] ?? '—') ?><?php if ($a['observacoes']): ?><span class="mini" title="<?= e($a['observacoes']) ?>">📝 <?= e(mb_strimwidth($a['observacoes'], 0, 40, '…')) ?></span><?php endif; ?></td>
            <td><?= e($a['servico_nome'] ?? '—') ?></td>
            <td><?= e($a['profissional_nome'] ?? '—') ?></td>
            <td>
                <form method="post">
                    <?= csrf_campo() ?><input type="hidden" name="agendamento_id" value="<?= $a['id'] ?>"><input type="hidden" name="atualizar_status" value="1">
                    <select name="status" onchange="this.form.submit()" aria-label="Status">
                        <?php foreach ($statusAg as $val => $label): ?><option value="<?= $val ?>" <?= $a['status'] == $val ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                    </select>
                </form>
            </td>
            <td class="acoes">
                <?php if ($a['telefone'] && $ts > time() && $a['status'] !== 'cancelado'): ?>
                <a class="btn btn-ghost btn-sm" target="_blank" href="<?= e(link_whatsapp($a['telefone'], 'Olá, ' . explode(' ', (string)$a['cliente_nome'])[0] . '! Passando para lembrar do seu horário' . ($a['servico_nome'] ? ' de ' . $a['servico_nome'] : '') . ' em ' . date('d/m \à\s H:i', $ts) . ' na ' . $empresa['nome'] . '. Podemos confirmar?')) ?>" title="Lembrar pelo WhatsApp"><?= icone('chat', 14) ?> Lembrar</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$agendamentos): ?><tr class="vazio"><td colspan="6">Nenhum agendamento neste filtro.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</main>
</div>
</body>
</html>
