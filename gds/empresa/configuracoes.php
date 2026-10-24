<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
$empresaId = (int)empresa_id_atual();
$voltar = fn($ancora = '') => header('Location: configuracoes.php' . ($ancora ? "#$ancora" : ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_dados'])) {
    $pdo->prepare("UPDATE empresas SET telefone=?, whatsapp=?, endereco=?, logo=? WHERE id=?")
        ->execute([trim($_POST['telefone'] ?? ''), so_digitos($_POST['whatsapp'] ?? ''), trim($_POST['endereco'] ?? ''), url_valida_ou_vazia($_POST['logo'] ?? '') ?: null, $empresaId]);
    flash('sucesso', 'Dados atualizados!'); $voltar('dados'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_horario'])) {
    $ab = preg_match('/^\d{2}:\d{2}$/', $_POST['horario_abertura'] ?? '') ? $_POST['horario_abertura'] : '08:00';
    $fe = preg_match('/^\d{2}:\d{2}$/', $_POST['horario_fechamento'] ?? '') ? $_POST['horario_fechamento'] : '19:00';
    $dias = array_values(array_intersect(array_map('intval', (array)($_POST['dias'] ?? [])), range(0, 6)));
    if ($fe <= $ab) { flash('erro', 'O horário de fechamento precisa ser depois da abertura.'); $voltar('horario'); exit; }
    if (!$dias) { flash('erro', 'Marque pelo menos um dia de atendimento.'); $voltar('horario'); exit; }
    try {
        $pdo->prepare("UPDATE empresas SET horario_abertura=?, horario_fechamento=?, dias_funcionamento=?, intervalo_agenda=?, antecedencia_max_dias=? WHERE id=?")
            ->execute([$ab, $fe, implode(',', $dias), max(10, min(240, (int)($_POST['intervalo_agenda'] ?? 30))), max(0, min(365, (int)($_POST['antecedencia_max_dias'] ?? 60))), $empresaId]);
        flash('sucesso', 'Horário de atendimento salvo — o calendário do site já segue esses horários.');
    } catch (PDOException $e) {
        flash('erro', 'Peça ao administrador para rodar a atualização do banco (adicional_10_estudio_ia.sql).');
    }
    $voltar('horario'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_cor'])) {
    $pdo->prepare("UPDATE sites SET cor_primaria=?, cor_secundaria=? WHERE empresa_id=?")
        ->execute([cor_hex_valida($_POST['cor_primaria'] ?? '', '#4f7cff'), cor_hex_valida($_POST['cor_secundaria'] ?? '', '#16213e'), $empresaId]);
    flash('sucesso', 'Cores salvas!'); $voltar('cor'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_pagamento'])) {
    $token = trim($_POST['mercadopago_access_token'] ?? '');
    if (isset($_POST['limpar_token'])) $pdo->prepare("UPDATE empresas SET mercadopago_access_token=NULL WHERE id=?")->execute([$empresaId]);
    elseif ($token !== '') $pdo->prepare("UPDATE empresas SET mercadopago_access_token=? WHERE id=?")->execute([$token, $empresaId]);
    $pdo->prepare("UPDATE empresas SET pix_ativo=? WHERE id=?")->execute([isset($_POST['pix_ativo']) ? 'true' : 'false', $empresaId]);
    flash('sucesso', 'Pagamento online atualizado.'); $voltar('pix'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trocar_senha'])) {
    $usuario = $pdo->prepare("SELECT * FROM usuarios WHERE id=?");
    $usuario->execute([usuario_logado()]); $usuario = $usuario->fetch();
    $nova = $_POST['senha_nova'] ?? '';
    if (!password_verify($_POST['senha_atual'] ?? '', $usuario['senha_hash'])) flash('erro', 'Senha atual incorreta.');
    elseif (strlen($nova) < 8) flash('erro', 'A nova senha precisa ter no mínimo 8 caracteres.');
    elseif ($nova !== ($_POST['senha_confirmar'] ?? '')) flash('erro', 'A confirmação não confere.');
    else { $pdo->prepare("UPDATE usuarios SET senha_hash=? WHERE id=?")->execute([password_hash($nova, PASSWORD_DEFAULT), usuario_logado()]); flash('sucesso', 'Senha alterada com sucesso!'); }
    $voltar('senha'); exit;
}

$empresa = $pdo->prepare("SELECT e.*, p.permite_pix FROM empresas e LEFT JOIN planos p ON p.id=e.plano_id WHERE e.id=?");
$empresa->execute([$empresaId]); $empresa = $empresa->fetch();
$site = $pdo->prepare("SELECT * FROM sites WHERE empresa_id=?");
$site->execute([$empresaId]); $site = $site->fetch();
$diasMarcados = array_map('intval', explode(',', $empresa['dias_funcionamento'] ?? '1,2,3,4,5,6'));
$nomesDias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
$tokenSalvo = (string)($empresa['mercadopago_access_token'] ?? '');
$pixPermitido = $empresa['permite_pix'] === null || pg_bool($empresa['permite_pix']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Configurações - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Configurações do negócio</h1><p class="subtitulo">Essas informações aparecem no seu site.</p></div></div>
    <?= flash_render() ?>
    <div class="grid-2">
    <form method="post" class="form-box largo" id="dados">
        <?= csrf_campo() ?>
        <h3>Dados do negócio</h3>
        <label>Nome do negócio</label><input type="text" value="<?= e($empresa['nome']) ?>" disabled>
        <p class="dica">Para trocar o nome ou o e-mail de login, fale com o administrador.</p>
        <div class="form-row">
            <div><label>Telefone</label><input type="tel" name="telefone" value="<?= e($empresa['telefone']) ?>"></div>
            <div><label>WhatsApp (com DDI e DDD)</label><input type="tel" name="whatsapp" value="<?= e($empresa['whatsapp']) ?>" placeholder="5511999999999"></div>
        </div>
        <label>Endereço completo</label><input type="text" name="endereco" value="<?= e($empresa['endereco']) ?>" placeholder="Rua, número, bairro, cidade - UF">
        <label>Logo (URL da imagem)</label><input type="url" name="logo" value="<?= e($empresa['logo']) ?>" placeholder="https://...">
        <button type="submit" name="salvar_dados" value="1">Salvar dados</button>
    </form>

    <form method="post" class="form-box largo" id="horario">
        <?= csrf_campo() ?>
        <h3><?= icone('calendario', 16) ?> Horário de atendimento</h3>
        <p class="dica">O calendário de agendamento do site só mostra dias e horários dentro disso, e esconde os horários já ocupados.</p>
        <label>Dias de atendimento</label>
        <div class="dias-semana"><?php foreach ($nomesDias as $i => $d): ?><label><input type="checkbox" name="dias[]" value="<?= $i ?>" <?= in_array($i, $diasMarcados, true) ? 'checked' : '' ?>> <?= $d ?></label><?php endforeach; ?></div>
        <div class="form-row">
            <div><label>Abre às</label><input type="time" name="horario_abertura" value="<?= e(substr($empresa['horario_abertura'] ?? '08:00', 0, 5)) ?>"></div>
            <div><label>Fecha às</label><input type="time" name="horario_fechamento" value="<?= e(substr($empresa['horario_fechamento'] ?? '19:00', 0, 5)) ?>"></div>
        </div>
        <div class="form-row">
            <div><label>Horários de quantos em quantos minutos</label><select name="intervalo_agenda"><?php foreach ([15, 20, 30, 45, 60, 90, 120] as $m): ?><option value="<?= $m ?>" <?= (int)($empresa['intervalo_agenda'] ?? 30) === $m ? 'selected' : '' ?>><?= $m ?> min</option><?php endforeach; ?></select></div>
            <div><label>Aceita agendar até quantos dias à frente</label><input type="number" min="0" max="365" name="antecedencia_max_dias" value="<?= (int)($empresa['antecedencia_max_dias'] ?? 60) ?>"></div>
        </div>
        <button type="submit" name="salvar_horario" value="1">Salvar horário</button>
    </form>

    <form method="post" class="form-box largo" id="cor">
        <?= csrf_campo() ?>
        <h3>Cores do meu site</h3>
        <p class="dica">Usadas no template padrão. Se o site foi desenhado pela IA, as cores vêm do design dela.</p>
        <div class="form-row">
            <div><label>Cor principal</label><input type="color" name="cor_primaria" value="<?= e(cor_hex_valida($site['cor_primaria'] ?? '', '#4f7cff')) ?>"></div>
            <div><label>Cor secundária</label><input type="color" name="cor_secundaria" value="<?= e(cor_hex_valida($site['cor_secundaria'] ?? '', '#16213e')) ?>"></div>
        </div>
        <button type="submit" name="salvar_cor" value="1">Salvar cores</button>
        <a href="/<?= e($empresa['slug']) ?>" target="_blank" class="btn btn-ghost" style="margin-top:18px"><?= icone('olho') ?> Ver meu site</a>
    </form>

    <form method="post" class="form-box largo" id="pix">
        <?= csrf_campo() ?>
        <h3><?= icone('cartao', 16) ?> Pagamento online (PIX)</h3>
        <?php if (!$pixPermitido): ?><?= alerta('aviso', 'Seu plano atual não inclui PIX no site.') ?><?php endif; ?>
        <p class="dica">O cliente paga na hora de agendar e o dinheiro cai na SUA conta do Mercado Pago. Token: mercadopago.com.br → Seu negócio → Configurações → Credenciais → "Access Token de produção".</p>
        <label class="check" style="margin-top:12px"><input type="checkbox" name="pix_ativo" <?= pg_bool($empresa['pix_ativo'] ?? false) ? 'checked' : '' ?> <?= $pixPermitido ? '' : 'disabled' ?>> Aceitar pagamento por PIX direto no site</label>
        <label>Access Token do Mercado Pago</label>
        <input type="password" name="mercadopago_access_token" autocomplete="off" placeholder="<?= $tokenSalvo !== '' ? 'Salvo: ' . e(substr($tokenSalvo, 0, 8)) . '•••••••• — cole outro para trocar' : 'APP_USR-...' ?>">
        <?php if ($tokenSalvo !== ''): ?><label class="check" style="font-size:12px;margin-top:6px"><input type="checkbox" name="limpar_token"> remover token salvo</label><?php endif; ?>
        <button type="submit" name="salvar_pagamento" value="1">Salvar</button>
    </form>

    <form method="post" class="form-box largo" id="senha">
        <?= csrf_campo() ?>
        <h3>Trocar minha senha</h3>
        <label>Senha atual</label><input type="password" name="senha_atual" required autocomplete="current-password">
        <label>Nova senha (mín. 8)</label><input type="password" name="senha_nova" required minlength="8" autocomplete="new-password">
        <label>Confirmar nova senha</label><input type="password" name="senha_confirmar" required minlength="8" autocomplete="new-password">
        <button type="submit" name="trocar_senha" value="1">Trocar senha</button>
    </form>
    </div>
</main>
</div>
</body>
</html>
