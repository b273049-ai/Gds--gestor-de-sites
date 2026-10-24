<button class="sidebar-toggle" onclick="document.body.classList.toggle('sidebar-aberta')" aria-label="Abrir menu">☰</button>
<div class="sidebar-overlay" onclick="document.body.classList.remove('sidebar-aberta')"></div>
<div class="sidebar">
    <h2>GDS Admin</h2>
    <a href="/admin/index.php" class="<?= basename($_SERVER['PHP_SELF'])=='index.php'?'active':'' ?>">Dashboard</a>
    <a href="/admin/empresas.php" class="<?= basename($_SERVER['PHP_SELF'])=='empresas.php'?'active':'' ?>">Empresas</a>
    <a href="/admin/templates.php" class="<?= basename($_SERVER['PHP_SELF'])=='templates.php'?'active':'' ?>">Templates</a>
    <a href="/admin/segmentos.php" class="<?= basename($_SERVER['PHP_SELF'])=='segmentos.php'?'active':'' ?>">Segmentos</a>
    <a href="/admin/planos.php" class="<?= basename($_SERVER['PHP_SELF'])=='planos.php'?'active':'' ?>">Planos</a>
    <a href="/admin/financeiro.php" class="<?= basename($_SERVER['PHP_SELF'])=='financeiro.php'?'active':'' ?>">Financeiro</a>
    <a href="/admin/relatorios.php" class="<?= basename($_SERVER['PHP_SELF'])=='relatorios.php'?'active':'' ?>">Relatórios</a>
    <a href="/admin/configuracoes.php" class="<?= basename($_SERVER['PHP_SELF'])=='configuracoes.php'?'active':'' ?>">Configurações</a>
    <a href="/logout.php" class="logout">Sair</a>
</div>
