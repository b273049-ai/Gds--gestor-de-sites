<button class="sidebar-toggle" onclick="document.body.classList.toggle('sidebar-aberta')" aria-label="Abrir menu">☰</button>
<div class="sidebar-overlay" onclick="document.body.classList.remove('sidebar-aberta')"></div>
<div class="sidebar">
    <h2>Meu Negócio</h2>
    <a href="/empresa/index.php" class="<?= basename($_SERVER['PHP_SELF'])=='index.php'?'active':'' ?>">Dashboard</a>
    <a href="/empresa/agenda.php" class="<?= basename($_SERVER['PHP_SELF'])=='agenda.php'?'active':'' ?>">Agenda</a>
    <a href="/empresa/clientes.php" class="<?= basename($_SERVER['PHP_SELF'])=='clientes.php'?'active':'' ?>">Clientes</a>
    <a href="/empresa/servicos.php" class="<?= basename($_SERVER['PHP_SELF'])=='servicos.php'?'active':'' ?>">Serviços</a>
    <a href="/empresa/profissionais.php" class="<?= basename($_SERVER['PHP_SELF'])=='profissionais.php'?'active':'' ?>">Profissionais</a>
    <a href="/empresa/pedidos.php" class="<?= basename($_SERVER['PHP_SELF'])=='pedidos.php'?'active':'' ?>">Pedidos/Orçamentos</a>
    <a href="/empresa/leads.php" class="<?= basename($_SERVER['PHP_SELF'])=='leads.php'?'active':'' ?>">Leads</a>
    <a href="/empresa/avaliacoes.php" class="<?= basename($_SERVER['PHP_SELF'])=='avaliacoes.php'?'active':'' ?>">Avaliações</a>
    <a href="/empresa/cupons.php" class="<?= basename($_SERVER['PHP_SELF'])=='cupons.php'?'active':'' ?>">Cupons</a>
    <a href="/empresa/assinatura.php" class="<?= basename($_SERVER['PHP_SELF'])=='assinatura.php'?'active':'' ?>">Minha Assinatura</a>
    <a href="/empresa/configuracoes.php" class="<?= basename($_SERVER['PHP_SELF'])=='configuracoes.php'?'active':'' ?>">Configurações</a>
    <a href="/logout.php" class="logout">Sair</a>
</div>
