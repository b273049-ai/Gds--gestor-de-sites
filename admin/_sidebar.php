<button class="sidebar-toggle" onclick="document.body.classList.toggle('sidebar-aberta')" aria-label="Abrir menu"><?= icone('menu', 20) ?></button>
<div class="sidebar-overlay" onclick="document.body.classList.remove('sidebar-aberta')"></div>
<aside class="sidebar">
    <div class="sidebar-brand"><span class="brand-logo">G</span><div><strong>GDS</strong><small>Painel Admin</small></div></div>
    <nav>
        <div class="sidebar-grupo">Visão geral</div>
        <?= menu_item('/admin/index.php', 'index.php', 'dashboard', 'Dashboard') ?>
        <?= menu_item('/admin/relatorios.php', 'relatorios.php', 'grafico', 'Relatórios') ?>
        <div class="sidebar-grupo">Clientes da plataforma</div>
        <?= menu_item('/admin/empresas.php', 'empresas.php', 'predio', 'Empresas') ?>
        <?= menu_item('/admin/financeiro.php', 'financeiro.php', 'dinheiro', 'Financeiro') ?>
        <div class="sidebar-grupo">Catálogo</div>
        <?= menu_item('/admin/planos.php', 'planos.php', 'pacote', 'Planos') ?>
        <?= menu_item('/admin/segmentos.php', 'segmentos.php', 'tag', 'Segmentos') ?>
        <?= menu_item('/admin/templates.php', 'templates.php', 'layout', 'Templates') ?>
        <div class="sidebar-grupo">Sistema</div>
        <?= menu_item('/admin/configuracoes.php', 'configuracoes.php', 'engrenagem', 'Configurações') ?>
    </nav>
    <div class="sidebar-rodape">
        <div class="sidebar-usuario"><span class="avatar"><?= e(mb_strtoupper(mb_substr($_SESSION['nome'] ?? 'A', 0, 1))) ?></span><span><?= e($_SESSION['nome'] ?? '') ?></span></div>
        <a href="/logout.php" class="logout"><?= icone('sair') ?><span>Sair</span></a>
    </div>
</aside>
