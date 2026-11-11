<?php
// Desempenho: visitas e cliques reais do site (site_eventos), o que virou cliente
// (agendamentos, pedidos, orçamentos) e o dinheiro do período (faturamento, ticket médio,
// mais vendidos e comissão por profissional).
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
// Visitas que ainda estão na fila (includes/site_eventos.php) entram no banco antes de contar
require_once __DIR__ . '/../includes/site_eventos.php';
try { site_eventos_descarregar(); } catch (Throwable $e) {}
$empresaId = (int)empresa_id_atual();
$mods = modulos_atuais();

$dias = (int)($_GET['dias'] ?? 30);
if (!in_array($dias, [7, 30, 90], true)) $dias = 30;
$um = function ($sql, $params = []) use ($pdo, $empresaId) {
    try { $s = $pdo->prepare($sql); $s->execute(array_merge([$empresaId], $params)); return $s->fetch() ?: []; } catch (PDOException $e) { return []; }
};
$todos = function ($sql, $params = []) use ($pdo, $empresaId) {
    try { $s = $pdo->prepare($sql); $s->execute(array_merge([$empresaId], $params)); return $s->fetchAll(); } catch (PDOException $e) { return []; }
};
$periodo = "criado_em >= CURRENT_DATE - INTERVAL '" . ($dias - 1) . " days'";

// ---------- site ----------
$ev = $um("SELECT COUNT(*) FILTER (WHERE tipo='visita') visitas, COUNT(DISTINCT visitante) FILTER (WHERE tipo='visita') pessoas,
    COUNT(*) FILTER (WHERE tipo='whatsapp') whatsapp, COUNT(*) FILTER (WHERE tipo IN ('ligar','rota')) ligar_rota,
    COUNT(*) FILTER (WHERE tipo IN ('agendar','orcamento','pedido','contato')) conversoes,
    COUNT(*) FILTER (WHERE tipo='visita' AND dispositivo='celular') celular
    FROM site_eventos WHERE empresa_id=? AND $periodo");
$visitas = (int)($ev['visitas'] ?? 0);
$porDia = [];
foreach ($todos("SELECT criado_em::date d, COUNT(*) FILTER (WHERE tipo='visita') v, COUNT(*) FILTER (WHERE tipo<>'visita') c FROM site_eventos WHERE empresa_id=? AND $periodo GROUP BY 1") as $r) $porDia[$r['d']] = $r;
$origens = $todos("SELECT origem, COUNT(*) c FROM site_eventos WHERE empresa_id=? AND tipo='visita' AND $periodo GROUP BY 1 ORDER BY 2 DESC LIMIT 6");
$antes = $um("SELECT COUNT(*) FILTER (WHERE tipo='visita') v FROM site_eventos WHERE empresa_id=? AND criado_em >= CURRENT_DATE - INTERVAL '" . (2 * $dias - 1) . " days' AND criado_em < CURRENT_DATE - INTERVAL '" . ($dias - 1) . " days'");
$variacao = !empty($antes['v']) ? round(($visitas - $antes['v']) / $antes['v'] * 100) : null;

// ---------- negócio ----------
$ag = $um("SELECT COUNT(*) FILTER (WHERE a.status<>'cancelado') total, COUNT(*) FILTER (WHERE a.status='concluido') feitos, COUNT(*) FILTER (WHERE a.status='cancelado') cancelados,
    COUNT(*) FILTER (WHERE a.origem='site') do_site, COALESCE(SUM(COALESCE(a.valor, s.preco)) FILTER (WHERE a.status='concluido'), 0) valor
    FROM agendamentos a LEFT JOIN servicos s ON s.id=a.servico_id WHERE a.empresa_id=? AND a.data_hora >= CURRENT_DATE - INTERVAL '" . ($dias - 1) . " days' AND a.data_hora < CURRENT_DATE + 1");
$pd = $um("SELECT COUNT(*) FILTER (WHERE status<>'cancelado') total, COUNT(*) FILTER (WHERE status='concluido') feitos, COALESCE(SUM(valor) FILTER (WHERE status='concluido'), 0) valor,
    COUNT(*) FILTER (WHERE tipo='delivery' AND status<>'cancelado') delivery, COUNT(*) FILTER (WHERE tipo='orcamento') orcamentos, COUNT(*) FILTER (WHERE tipo='orcamento' AND status IN ('aprovado','em_andamento','pronto','concluido')) orc_aprovados
    FROM pedidos WHERE empresa_id=? AND $periodo");
$faturamento = (float)($ag['valor'] ?? 0) + (float)($pd['valor'] ?? 0);
$vendas = (int)($ag['feitos'] ?? 0) + (int)($pd['feitos'] ?? 0);
$ticket = $vendas ? $faturamento / $vendas : 0;

// Mais vendidos: serviços concluídos na agenda + itens dos pedidos concluídos
$top = [];
foreach ($todos("SELECT s.nome, COUNT(*) q, SUM(COALESCE(a.valor, s.preco)) v FROM agendamentos a JOIN servicos s ON s.id=a.servico_id
    WHERE a.empresa_id=? AND a.status='concluido' AND a.data_hora >= CURRENT_DATE - INTERVAL '" . ($dias - 1) . " days' GROUP BY s.nome") as $r) $top[$r['nome']] = ['q' => (int)$r['q'], 'v' => (float)$r['v']];
foreach ($todos("SELECT itens FROM pedidos WHERE empresa_id=? AND status='concluido' AND tipo='delivery' AND $periodo") as $r) {
    foreach ((array)json_decode((string)$r['itens'], true) as $it) {
        $n = (string)($it['nome'] ?? ''); if ($n === '') continue;
        $top[$n]['q'] = ($top[$n]['q'] ?? 0) + (int)($it['qtd'] ?? 1);
        $top[$n]['v'] = ($top[$n]['v'] ?? 0) + (float)($it['preco'] ?? 0) * (int)($it['qtd'] ?? 1);
    }
}
uasort($top, fn($a, $b) => $b['v'] <=> $a['v']);
$top = array_slice($top, 0, 8, true);
$maxTop = max([1, ...array_map(fn($t) => $t['v'], $top)]);

// Por profissional (com comissão)
$equipe = modulo_ativo('equipe') ? $todos("SELECT p.nome, COALESCE(p.comissao, 0) comissao, COUNT(a.id) q, COALESCE(SUM(COALESCE(a.valor, s.preco)), 0) v
    FROM profissionais p LEFT JOIN agendamentos a ON a.profissional_id=p.id AND a.status='concluido' AND a.data_hora >= CURRENT_DATE - INTERVAL '" . ($dias - 1) . " days'
    LEFT JOIN servicos s ON s.id=a.servico_id WHERE p.empresa_id=? AND p.ativo=true GROUP BY p.id ORDER BY v DESC") : [];

// Gráfico de barras (visitas por dia) em SVG
$serie = [];
for ($i = $dias - 1; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i day")); $serie[$d] = [(int)($porDia[$d]['v'] ?? 0), (int)($porDia[$d]['c'] ?? 0)]; }
$maxV = max([1, ...array_map(fn($x) => $x[0], $serie)]);
$nomesOrigem = ['direto' => 'Direto / link salvo', 'instagram' => 'Instagram / Facebook', 'google' => 'Google', 'whatsapp' => 'WhatsApp', 'tiktok' => 'TikTok', 'interno' => 'Navegação no site'];
$pct = fn($a, $b) => $b > 0 ? round($a / $b * 100) : 0;
$moeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Desempenho - Meu Negócio') ?></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header">
        <div><h1>Desempenho</h1><p class="subtitulo">Quem visitou seu site, o que fizeram e quanto isso virou de dinheiro.</p></div>
        <div class="acoes segmentado">
            <?php foreach ([7 => '7 dias', 30 => '30 dias', 90 => '90 dias'] as $d => $r): ?><a class="btn <?= $d === $dias ? '' : 'btn-ghost' ?> btn-sm" href="?dias=<?= $d ?>"><?= $r ?></a><?php endforeach; ?>
        </div>
    </div>
    <?= flash_render() ?>

    <h2>Seu site</h2>
    <div class="cards">
        <div class="card"><div class="label"><?= icone('olho', 16) ?> Visitas</div><div class="value"><?= $visitas ?></div>
            <div class="sub"><?= (int)($ev['pessoas'] ?? 0) ?> pessoas<?= $variacao !== null ? ' · <span style="color:' . ($variacao >= 0 ? 'var(--ok)' : 'var(--danger)') . '">' . ($variacao >= 0 ? '▲' : '▼') . ' ' . abs($variacao) . '%</span> vs. período anterior' : '' ?></div></div>
        <div class="card"><div class="label"><?= icone('chat', 16) ?> Cliques no WhatsApp</div><div class="value"><?= (int)($ev['whatsapp'] ?? 0) ?></div><div class="sub"><?= $pct((int)($ev['whatsapp'] ?? 0), $visitas) ?>% das visitas</div></div>
        <div class="card"><div class="label"><?= icone('enviar', 16) ?> Pedidos pelo site</div><div class="value"><?= (int)($ev['conversoes'] ?? 0) ?></div><div class="sub">agendamentos, orçamentos, pedidos e contatos · <?= $pct((int)($ev['conversoes'] ?? 0), $visitas) ?>% de conversão</div></div>
        <div class="card"><div class="label"><?= icone('celular', 16) ?> Pelo celular</div><div class="value"><?= $pct((int)($ev['celular'] ?? 0), $visitas) ?>%</div><div class="sub"><?= (int)($ev['ligar_rota'] ?? 0) ?> pediram rota ou ligaram</div></div>
    </div>

    <div class="grade-2">
        <div class="form-box largo">
            <h3>Visitas por dia</h3>
            <?php if ($visitas === 0): ?><p class="dica">Ainda não há visitas registradas neste período. Divulgue o link do seu site no Instagram, no status do WhatsApp e no Google — as visitas aparecem aqui em tempo real.</p><?php endif; ?>
            <svg class="grafico-barras" viewBox="0 0 <?= count($serie) * 10 ?> 100" preserveAspectRatio="none" role="img" aria-label="Visitas por dia">
                <?php $i = 0; foreach ($serie as $d => [$v, $c]): $h = $v / $maxV * 92; ?>
                <rect x="<?= $i * 10 + 1.5 ?>" y="<?= 100 - $h ?>" width="7" height="<?= max(0.6, $h) ?>" rx="1.5" class="<?= $c > 0 ? 'com-acao' : '' ?>"><title><?= date('d/m', strtotime($d)) ?>: <?= $v ?> visita(s), <?= $c ?> clique(s)/pedido(s)</title></rect>
                <?php $i++; endforeach; ?>
            </svg>
            <div class="grafico-eixo"><span><?= date('d/m', strtotime(array_key_first($serie))) ?></span><span>hoje</span></div>
            <p class="dica">Barras mais fortes = dias em que alguém também clicou no WhatsApp, agendou ou pediu.</p>
        </div>
        <div class="form-box largo">
            <h3>De onde vêm as visitas</h3>
            <?php foreach ($origens as $o): $p = $pct((int)$o['c'], $visitas); ?>
            <div class="barra-linha"><div class="rotulo"><span><?= e($nomesOrigem[$o['origem']] ?? $o['origem']) ?></span><span><?= (int)$o['c'] ?> · <?= $p ?>%</span></div><div class="progresso"><div style="width:<?= $p ?>%"></div></div></div>
            <?php endforeach; ?>
            <?php if (!$origens): ?><p class="dica">Aparece quando as visitas começarem.</p><?php endif; ?>
            <p class="dica">Dica: no link da bio use <code>?utm_source=instagram</code> no fim do endereço — assim você sabe exatamente quantas pessoas vieram de cada lugar.</p>
        </div>
    </div>

    <h2>Seu negócio no período</h2>
    <div class="cards">
        <div class="card"><div class="label"><?= icone('dinheiro', 16) ?> Faturamento</div><div class="value"><?= $moeda($faturamento) ?></div><div class="sub"><?= $vendas ?> venda(s)/atendimento(s) concluído(s)</div></div>
        <div class="card"><div class="label"><?= icone('grafico', 16) ?> Ticket médio</div><div class="value"><?= $moeda($ticket) ?></div><div class="sub">por atendimento ou pedido</div></div>
        <?php if (modulo_ativo('agenda')): ?>
        <div class="card"><div class="label"><?= icone('calendario', 16) ?> Agendamentos</div><div class="value"><?= (int)($ag['total'] ?? 0) ?></div><div class="sub"><?= (int)($ag['do_site'] ?? 0) ?> pelo site · <?= (int)($ag['cancelados'] ?? 0) ?> cancelado(s)</div></div>
        <?php endif; ?>
        <?php if (modulo_ativo('delivery')): ?>
        <div class="card"><div class="label"><?= icone('pacote', 16) ?> Pedidos delivery</div><div class="value"><?= (int)($pd['delivery'] ?? 0) ?></div><div class="sub"><?= (int)($pd['feitos'] ?? 0) ?> concluído(s)</div></div>
        <?php endif; ?>
        <?php if (modulo_ativo('orcamentos')): ?>
        <div class="card"><div class="label"><?= icone('prancheta', 16) ?> Orçamentos</div><div class="value"><?= (int)($pd['orcamentos'] ?? 0) ?></div><div class="sub"><?= $pct((int)($pd['orc_aprovados'] ?? 0), (int)($pd['orcamentos'] ?? 0)) ?>% aprovados</div></div>
        <?php endif; ?>
    </div>

    <div class="grade-2">
        <div class="form-box largo">
            <h3>Mais vendidos</h3>
            <?php foreach ($top as $nome => $t): ?>
            <div class="barra-linha"><div class="rotulo"><span><?= e($nome) ?> <span class="muted">· <?= (int)$t['q'] ?>x</span></span><span><?= $moeda($t['v']) ?></span></div><div class="progresso"><div style="width:<?= round($t['v'] / $maxTop * 100) ?>%"></div></div></div>
            <?php endforeach; ?>
            <?php if (!$top): ?><p class="dica">Marque os atendimentos e pedidos como "concluído" para eles entrarem aqui.</p><?php endif; ?>
        </div>
        <?php if ($equipe): ?>
        <div class="form-box largo">
            <h3>Por profissional</h3>
            <div class="tabela-wrap"><table>
                <thead><tr><th>Profissional</th><th>Atend.</th><th>Faturou</th><th>Comissão</th></tr></thead>
                <tbody>
                <?php foreach ($equipe as $p): ?>
                <tr><td><strong><?= e($p['nome']) ?></strong></td><td><?= (int)$p['q'] ?></td><td><?= $moeda($p['v']) ?></td>
                    <td><?= (float)$p['comissao'] > 0 ? $moeda($p['v'] * $p['comissao'] / 100) . ' <span class="mini">' . number_format((float)$p['comissao'], 0) . '%</span>' : '<a class="link" href="profissionais.php">definir %</a>' ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
        <?php endif; ?>
    </div>
    <p class="dica" style="margin-top:14px">As visitas são contadas sem guardar dados pessoais (cada navegador ganha um código aleatório). Robôs e pré-visualizações de links não contam.</p>
</main>
</div>
</body>
</html>
