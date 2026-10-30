<?php
$estudioLiberado = empresa_pode_usar_estudio((int)empresa_id_atual());
$mods = modulos_atuais();
?>
<button class="sidebar-toggle" onclick="document.body.classList.toggle('sidebar-aberta')" aria-label="Abrir menu"><?= icone('menu', 20) ?></button>
<div class="sidebar-overlay" onclick="document.body.classList.remove('sidebar-aberta')"></div>
<aside class="sidebar">
    <div class="sidebar-brand"><span class="brand-logo">G</span><div><strong>Meu Negócio</strong><small><?= e(atendimento_modos()[$mods['modo']]['nome'] ?? 'GDS') ?></small></div></div>
    <nav>
        <?= menu_item('/empresa/index.php', 'index.php', 'dashboard', 'Dashboard') ?>
        <?= menu_item('/empresa/desempenho.php', 'desempenho.php', 'grafico', 'Desempenho') ?>
        <?php if ($estudioLiberado): ?><?= menu_item('/empresa/ia_estudio.php', 'ia_estudio.php', 'brilho', 'Estúdio IA', 'destaque') ?><?php else: ?><?= menu_item('/empresa/meu_site.php', 'meu_site.php', 'globo', 'Meu site') ?><?php endif; ?>
        <div class="sidebar-grupo">Atendimento</div>
        <?php if (modulo_ativo('agenda')): ?><?= menu_item('/empresa/agenda.php', 'agenda.php', 'calendario', 'Agenda') ?><?php endif; ?>
        <?php if (modulo_ativo('delivery') || modulo_ativo('orcamentos')): ?><?= menu_item('/empresa/pedidos.php', 'pedidos.php', modulo_ativo('delivery') ? 'pacote' : 'prancheta', modulos_rotulo_pedidos()) ?><?php endif; ?>
        <?php if (modulo_ativo('delivery')): ?><?= menu_item('/empresa/mesas.php', 'mesas.php', 'layout', 'QR Code das mesas') ?><?php endif; ?>
        <?php if (modulo_ativo('agenda')): ?><?= menu_item('/empresa/clube.php', 'clube.php', 'estrela', 'Clube e pacotes') ?><?php endif; ?>
        <?= menu_item('/empresa/leads.php', 'leads.php', 'caixa', 'Contatos do site') ?>
        <?= menu_item('/empresa/clientes.php', 'clientes.php', 'usuarios', 'Clientes') ?>
        <?= menu_item('/empresa/whatsapp.php', 'whatsapp.php', 'chat', 'WhatsApp automático') ?>
        <div class="sidebar-grupo">Meu catálogo</div>
        <?= menu_item('/empresa/servicos.php', 'servicos.php', $mods['modo'] === 'catalogo' ? 'pacote' : 'tesoura', modulos_rotulo_catalogo()) ?>
        <?php if (modulo_ativo('equipe')): ?><?= menu_item('/empresa/profissionais.php', 'profissionais.php', 'cracha', 'Equipe') ?><?php endif; ?>
        <?php if (modulo_ativo('cupons')): ?><?= menu_item('/empresa/cupons.php', 'cupons.php', 'cupom', 'Cupons') ?><?php endif; ?>
        <?php if (modulo_ativo('avaliacoes')): ?><?= menu_item('/empresa/avaliacoes.php', 'avaliacoes.php', 'estrela', 'Avaliações') ?><?php endif; ?>
        <div class="sidebar-grupo">Conta</div>
        <?= menu_item('/empresa/dominio.php', 'dominio.php', 'globo', 'Meu domínio') ?>
        <?= menu_item('/empresa/assinatura.php', 'assinatura.php', 'cartao', 'Minha Assinatura') ?>
        <?= menu_item('/empresa/configuracoes.php', 'configuracoes.php', 'engrenagem', 'Configurações') ?>
    </nav>
    <div class="sidebar-rodape">
        <div class="sidebar-usuario"><span class="avatar"><?= e(mb_strtoupper(mb_substr($_SESSION['nome'] ?? 'E', 0, 1))) ?></span><span><?= e($_SESSION['nome'] ?? '') ?></span></div>
        <a href="/logout.php" class="logout"><?= icone('sair') ?><span>Sair</span></a>
    </div>
</aside>
