<?php $estudioLiberado = empresa_pode_usar_estudio((int)empresa_id_atual()); ?>
<button class="sidebar-toggle" onclick="document.body.classList.toggle('sidebar-aberta')" aria-label="Abrir menu"><?= icone('menu', 20) ?></button>
<div class="sidebar-overlay" onclick="document.body.classList.remove('sidebar-aberta')"></div>
<aside class="sidebar">
    <div class="sidebar-brand"><span class="brand-logo">G</span><div><strong>Meu Negócio</strong><small>GDS</small></div></div>
    <nav>
        <?= menu_item('/empresa/index.php', 'index.php', 'dashboard', 'Dashboard') ?>
        <?php if ($estudioLiberado): ?><?= menu_item('/empresa/ia_estudio.php', 'ia_estudio.php', 'brilho', 'Estúdio IA', 'destaque') ?><?php else: ?><?= menu_item('/empresa/meu_site.php', 'meu_site.php', 'globo', 'Meu site') ?><?php endif; ?>
        <div class="sidebar-grupo">Atendimento</div>
        <?= menu_item('/empresa/agenda.php', 'agenda.php', 'calendario', 'Agenda') ?>
        <?= menu_item('/empresa/clientes.php', 'clientes.php', 'usuarios', 'Clientes') ?>
        <?= menu_item('/empresa/pedidos.php', 'pedidos.php', 'prancheta', 'Pedidos/Orçamentos') ?>
        <?= menu_item('/empresa/leads.php', 'leads.php', 'caixa', 'Contatos do site') ?>
        <div class="sidebar-grupo">Meu catálogo</div>
        <?= menu_item('/empresa/servicos.php', 'servicos.php', 'tesoura', 'Serviços') ?>
        <?= menu_item('/empresa/profissionais.php', 'profissionais.php', 'cracha', 'Profissionais') ?>
        <?= menu_item('/empresa/cupons.php', 'cupons.php', 'cupom', 'Cupons') ?>
        <?= menu_item('/empresa/avaliacoes.php', 'avaliacoes.php', 'estrela', 'Avaliações') ?>
        <div class="sidebar-grupo">Conta</div>
        <?= menu_item('/empresa/assinatura.php', 'assinatura.php', 'cartao', 'Minha Assinatura') ?>
        <?= menu_item('/empresa/configuracoes.php', 'configuracoes.php', 'engrenagem', 'Configurações') ?>
    </nav>
    <div class="sidebar-rodape">
        <div class="sidebar-usuario"><span class="avatar"><?= e(mb_strtoupper(mb_substr($_SESSION['nome'] ?? 'E', 0, 1))) ?></span><span><?= e($_SESSION['nome'] ?? '') ?></span></div>
        <a href="/logout.php" class="logout"><?= icone('sair') ?><span>Sair</span></a>
    </div>
</aside>
