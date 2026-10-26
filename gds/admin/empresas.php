<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');

$erro = '';
$form = [];

// Criar nova empresa (cria a empresa + o usuário de login dela + o site em rascunho + a 1ª cobrança)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_empresa'])) {
    $form = $_POST;
    $nome = trim($_POST['nome'] ?? '');
    $email = mb_strtolower(trim($_POST['email'] ?? ''));
    $senha = $_POST['senha'] ?? '';
    $segmento_id = (int)($_POST['segmento_id'] ?? 0) ?: null;
    $plano_id = (int)($_POST['plano_id'] ?? 0) ?: null;
    $whatsapp = so_digitos($_POST['whatsapp'] ?? '');
    $modoAt = atendimento_modo_valido($_POST['modo_atendimento'] ?? '') ? $_POST['modo_atendimento'] : null;
    $segDesc = mb_substr(trim($_POST['segmento_descricao'] ?? ''), 0, 150) ?: null;

    $jaExiste = $pdo->prepare("SELECT 1 FROM usuarios WHERE LOWER(email)=?");
    $jaExiste->execute([$email]);

    if ($nome === '') $erro = 'Informe o nome da empresa.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erro = 'E-mail inválido.';
    elseif ($jaExiste->fetch()) $erro = 'Já existe um usuário com esse e-mail.';
    elseif (strlen($senha) < 8) $erro = 'A senha precisa ter pelo menos 8 caracteres.';
    else {
        try {
            $pdo->beginTransaction();
            $slugBase = trim($_POST['slug'] ?? '') !== '' ? slugify($_POST['slug']) : slugify($nome);
            $slug = slug_unico('empresas', $slugBase);

            $stmt = $pdo->prepare("INSERT INTO empresas (nome, slug, segmento_id, plano_id, email, whatsapp, status, vencimento) VALUES (?, ?, ?, ?, ?, ?, 'pendente', CURRENT_DATE + INTERVAL '30 days') RETURNING id");
            $stmt->execute([$nome, $slug, $segmento_id, $plano_id, $email, $whatsapp ?: null]);
            $empresaId = $stmt->fetch()['id'];
            $pdo->prepare("UPDATE empresas SET modo_atendimento=?, segmento_descricao=? WHERE id=?")->execute([$modoAt, $segDesc, $empresaId]);

            $pdo->prepare("INSERT INTO usuarios (empresa_id, nome, email, senha_hash, role) VALUES (?, ?, ?, ?, 'empresa')")
                ->execute([$empresaId, $nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);

            $pdo->prepare("INSERT INTO sites (empresa_id, titulo, publicado) VALUES (?, ?, false)")->execute([$empresaId, $nome]);

            if ($plano_id) {
                $plano = $pdo->prepare("SELECT valor FROM planos WHERE id=?");
                $plano->execute([$plano_id]); $plano = $plano->fetch();
                if ($plano) {
                    $pdo->prepare("INSERT INTO pagamentos_assinaturas (empresa_id, plano_id, valor, vencimento, status) VALUES (?,?,?, CURRENT_DATE + INTERVAL '30 days', 'pendente')")
                        ->execute([$empresaId, $plano_id, $plano['valor']]);
                }
            }
            $pdo->commit();
            flash('sucesso', "Empresa criada! Login: $email · Site: /$slug");
            header('Location: empresas.php'); exit;
        } catch (Exception $ex) {
            $pdo->rollBack();
            error_log('GDS criar empresa: ' . $ex->getMessage());
            $erro = 'Erro ao criar empresa. ' . (APP_DEBUG ? $ex->getMessage() : 'Verifique os dados e tente novamente.');
        }
    }
}

$busca = trim($_GET['q'] ?? '');
$filtroStatus = $_GET['status'] ?? '';
$where = []; $params = [];
if ($busca !== '') { $where[] = "(e.nome ILIKE ? OR e.email ILIKE ? OR e.slug ILIKE ?)"; array_push($params, "%$busca%", "%$busca%", "%$busca%"); }
if (in_array($filtroStatus, ['ativo', 'pendente', 'suspenso', 'cancelado'], true)) { $where[] = "e.status = ?"; $params[] = $filtroStatus; }

$segmentos = $pdo->query("SELECT * FROM segmentos WHERE ativo=true ORDER BY (slug='outro'), nome")->fetchAll();
$planos = $pdo->query("SELECT * FROM planos WHERE ativo=true ORDER BY valor")->fetchAll();
$stmt = $pdo->prepare("
    SELECT e.*, sg.nome AS segmento_nome, p.nome AS plano_nome, s.publicado, s.ia_modo_total
    FROM empresas e
    LEFT JOIN segmentos sg ON sg.id = e.segmento_id
    LEFT JOIN planos p ON p.id = e.plano_id
    LEFT JOIN sites s ON s.empresa_id = e.id
    " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
    ORDER BY e.criado_em DESC");
$stmt->execute($params);
$empresas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Empresas - GDS Admin') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header">
        <div><h1>Empresas</h1><p class="subtitulo"><?= count($empresas) ?> empresa(s)<?= $busca || $filtroStatus ? ' encontradas' : ' cadastradas' ?></p></div>
    </div>
    <?= flash_render() ?>
    <?php if ($erro): ?><?= alerta('erro', $erro) ?><?php endif; ?>

    <details class="form-box" id="nova" <?= $erro || !$empresas ? 'open' : '' ?>>
        <summary><?= icone('mais') ?> Cadastrar nova empresa</summary>
        <form method="post">
            <?= csrf_campo() ?>
            <div class="form-row">
                <div><label>Nome da empresa</label><input type="text" name="nome" required value="<?= e($form['nome'] ?? '') ?>" oninput="document.getElementById('slugPrev').textContent='/'+this.value.normalize('NFD').replace(/[̀-ͯ]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')"></div>
                <div><label>Endereço do site (opcional)</label><input type="text" name="slug" placeholder="gerado a partir do nome" value="<?= e($form['slug'] ?? '') ?>"><p class="dica">Ficará: <span id="slugPrev">/<?= e(slugify($form['nome'] ?? 'nome-da-empresa')) ?></span></p></div>
            </div>
            <?= atendimento_campos_form($segmentos, $form['segmento_id'] ?? '', $form['segmento_descricao'] ?? '', $form['modo_atendimento'] ?? '') ?>
            <div class="form-row">
                <div><label>Plano</label>
                    <select name="plano_id" required>
                        <?php foreach ($planos as $p): ?><option value="<?= $p['id'] ?>" <?= ($form['plano_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?> — R$ <?= number_format($p['valor'], 2, ',', '.') ?><?= pg_bool($p['permite_ia']) ? ' · IA' : '' ?></option><?php endforeach; ?>
                    </select></div>
            </div>
            <div class="form-row">
                <div><label>E-mail de login da empresa</label><input type="email" name="email" required value="<?= e($form['email'] ?? '') ?>"></div>
                <div><label>Senha de acesso (mín. 8)</label><input type="password" name="senha" required minlength="8" autocomplete="new-password"></div>
            </div>
            <label>WhatsApp (opcional, com DDD)</label><input type="text" name="whatsapp" placeholder="5511999999999" value="<?= e($form['whatsapp'] ?? '') ?>">
            <button type="submit" name="criar_empresa" value="1"><?= icone('mais') ?> Criar empresa</button>
        </form>
    </details>

    <form class="toolbar" method="get">
        <input type="search" name="q" placeholder="Buscar por nome, e-mail ou endereço…" value="<?= e($busca) ?>">
        <select name="status" onchange="this.form.submit()">
            <option value="">Todos os status</option>
            <?php foreach (['ativo' => 'Ativo', 'pendente' => 'Pendente', 'suspenso' => 'Suspenso', 'cancelado' => 'Cancelado'] as $v => $l): ?><option value="<?= $v ?>" <?= $filtroStatus === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-ghost">Filtrar</button>
        <?php if ($busca || $filtroStatus): ?><a class="link" href="empresas.php">limpar</a><?php endif; ?>
    </form>

    <div class="tabela-wrap"><table>
        <thead><tr><th>Empresa</th><th>Segmento / Plano</th><th>Status</th><th>Vencimento</th><th>Site</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($empresas as $emp): ?>
        <tr>
            <td><strong><?= e($emp['nome']) ?></strong><span class="mini"><?= e($emp['email']) ?></span></td>
            <td><?= e($emp['segmento_nome'] ?? '—') ?><span class="mini"><?= e($emp['plano_nome'] ?? 'sem plano') ?></span></td>
            <td><span class="badge badge-<?= e($emp['status']) ?>"><?= e($emp['status']) ?></span></td>
            <td><?php if ($emp['vencimento']): $venc = strtotime($emp['vencimento']); ?><span style="color:<?= $venc < time() ? 'var(--danger)' : 'inherit' ?>"><?= date('d/m/Y', $venc) ?></span><?php else: ?>—<?php endif; ?></td>
            <td>
                <a class="link" href="/<?= e($emp['slug']) ?>" target="_blank">/<?= e($emp['slug']) ?></a>
                <span class="mini"><?= pg_bool($emp['publicado']) ? 'publicado' : 'rascunho' ?><?= pg_bool($emp['ia_modo_total']) ? ' · design IA' : '' ?></span>
            </td>
            <td class="acoes">
                <a href="ia_estudio.php?empresa_id=<?= $emp['id'] ?>" class="btn btn-sm btn-ia" title="Criar/editar o site com IA"><?= icone('brilho', 14) ?> Estúdio</a>
                <a href="site_hub.php?empresa_id=<?= $emp['id'] ?>" class="btn btn-ghost btn-sm">Site</a>
                <a href="empresa_editar.php?id=<?= $emp['id'] ?>" class="btn btn-ghost btn-sm" title="Editar empresa"><?= icone('lapis', 14) ?></a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$empresas): ?><tr class="vazio"><td colspan="6">Nenhuma empresa encontrada.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</main>
</div>
</body>
</html>
