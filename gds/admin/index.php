<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('admin');
require_once __DIR__ . '/../includes/inteligencia.php';

$um = fn($sql) => $pdo->query($sql)->fetch();
$totalEmpresas = $um("SELECT COUNT(*) c FROM empresas")['c'];
$empresasAtivas = $um("SELECT COUNT(*) c FROM empresas WHERE status='ativo'")['c'];
$totalSites = $um("SELECT COUNT(*) c FROM sites")['c'];
$sitesPublicados = $um("SELECT COUNT(*) c FROM sites WHERE publicado=true")['c'];
$sitesIA = $um("SELECT COUNT(*) c FROM sites WHERE ia_modo_total=true")['c'];
$receitaMes = $um("SELECT COALESCE(SUM(valor),0) v FROM pagamentos_assinaturas WHERE status='pago' AND date_trunc('month', pago_em) = date_trunc('month', CURRENT_DATE)")['v'];
$pend = $um("SELECT COUNT(*) c, COALESCE(SUM(valor),0) v FROM pagamentos_assinaturas WHERE status IN ('pendente','atrasado')");
$vencidas = $um("SELECT COUNT(*) c FROM pagamentos_assinaturas WHERE status IN ('pendente','atrasado') AND vencimento < CURRENT_DATE")['c'];
$mrr = $um("SELECT COALESCE(SUM(p.valor / CASE WHEN p.ciclo='anual' THEN 12 ELSE 1 END),0) v FROM empresas e JOIN planos p ON p.id=e.plano_id WHERE e.status='ativo'")['v'];

$empresasRecentes = $pdo->query("
    SELECT e.id, e.nome, e.slug, e.status, e.vencimento, p.nome AS plano_nome, s.publicado, s.ia_modo_total
    FROM empresas e
    LEFT JOIN planos p ON p.id = e.plano_id
    LEFT JOIN sites s ON s.empresa_id = e.id
    ORDER BY e.criado_em DESC LIMIT 8
")->fetchAll();
// Central de inteligência
$saude = intel_saude_empresas();
$acoes = intel_acoes($saude);
$prev = intel_previsao($saude);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'briefing') responder_json(['ok' => true] + intel_briefing($acoes, $prev, $saude, true));
$briefing = intel_briefing($acoes, $prev, $saude);
$riscos = array_slice(array_values(array_filter($saude, fn($s) => $s['risco'] !== 'baixo')), 0, 5);
$contaRisco = ['alto' => 0, 'medio' => 0, 'baixo' => 0]; foreach ($saude as $s) $contaRisco[$s['risco']]++;
$semSite = $pdo->query("SELECT e.id, e.nome FROM empresas e JOIN sites s ON s.empresa_id=e.id WHERE COALESCE(s.html_gerado,'')='' AND s.publicado=false ORDER BY e.criado_em DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Dashboard Admin - GDS') ?>
<style>
.intel-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; margin-bottom: 16px; }
.intel-grid .form-box { margin: 0; }
.intel-topo { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.intel-briefing ul { margin: 10px 0 6px; padding-left: 0; list-style: none; display: grid; gap: 8px; }
.intel-briefing li { line-height: 1.45; }
.intel-acoes { list-style: none; padding: 0; margin: 8px 0 0; display: grid; gap: 6px; }
.intel-acoes a { display: flex; gap: 10px; align-items: flex-start; padding: 9px 10px; border-radius: 10px; background: var(--surface-2); border: 1px solid var(--border); color: inherit; text-decoration: none; }
.intel-acoes a:hover { border-color: var(--primary); }
.intel-acoes .ic { font-size: 18px; line-height: 1.2; }
.intel-acoes strong { display: block; font-size: 14px; }
.intel-acoes small, .intel-cliente small { color: var(--text-dim); font-size: 12.5px; display: block; }
.intel-prev { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin: 10px 0; }
.intel-prev div { background: var(--surface-2); border: 1px solid var(--border); border-radius: 10px; padding: 10px; }
.intel-prev small { color: var(--text-dim); display: block; font-size: 12px; }
.intel-prev strong { font-size: 18px; }
.intel-semaforo { display: flex; gap: 8px; flex-wrap: wrap; margin: 8px 0 10px; }
.intel-semaforo span { font-size: 12px; padding: 3px 10px; border-radius: 999px; border: 1px solid var(--border); }
.s-alto { color: #ff8a8a; } .s-medio { color: #f5c26b; } .s-baixo { color: #6be3a0; }
.intel-cliente { display: flex; gap: 10px; align-items: center; padding: 8px 0; border-top: 1px solid var(--border); }
.intel-cliente > div { flex: 1; min-width: 0; }
.intel-cliente .nota { flex: none; width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; font-size: 14px; border: 2px solid; }
.r-alto { color: #ff8a8a; border-color: #ff8a8a; } .r-medio { color: #f5c26b; border-color: #f5c26b; } .r-baixo { color: #6be3a0; border-color: #6be3a0; }
</style></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header">
        <div><h1>Olá, <?= e(explode(' ', $_SESSION['nome'] ?? '')[0]) ?></h1><p class="subtitulo">Visão geral da plataforma hoje, <?= date('d/m/Y') ?>.</p></div>
        <div class="acoes"><a href="empresas.php#nova" class="btn"><?= icone('mais') ?> Nova empresa</a></div>
    </div>
    <?= flash_render() ?>
    <?php if (!ia_configurada()): ?><?= alerta('aviso', 'A IA ainda não está configurada — o Estúdio IA e os botões "✨" não vão funcionar. Configure em Configurações (o Google Gemini é gratuito).') ?><?php endif; ?>

    <div class="cards">
        <div class="card"><div class="label"><?= icone('predio', 16) ?> Empresas</div><div class="value"><?= $totalEmpresas ?></div><div class="sub"><?= $empresasAtivas ?> ativas</div></div>
        <div class="card"><div class="label"><?= icone('globo', 16) ?> Sites publicados</div><div class="value"><?= $sitesPublicados ?><span class="muted" style="font-size:15px">/<?= $totalSites ?></span></div><div class="sub"><?= $sitesIA ?> com design IA</div></div>
        <div class="card"><div class="label"><?= icone('dinheiro', 16) ?> Recebido no mês</div><div class="value">R$ <?= number_format($receitaMes, 2, ',', '.') ?></div><div class="sub">Receita recorrente: R$ <?= number_format($mrr, 2, ',', '.') ?>/mês</div></div>
        <div class="card"><div class="label"><?= icone('cartao', 16) ?> A receber</div><div class="value">R$ <?= number_format($pend['v'], 2, ',', '.') ?></div><div class="sub" style="color:<?= $vencidas ? 'var(--danger)' : 'var(--ok)' ?>"><?= $pend['c'] ?> cobranças · <?= $vencidas ?> vencidas</div></div>
    </div>

    <div class="intel-grid">
        <section class="form-box largo intel-briefing">
            <div class="intel-topo"><h3><?= icone('brilho', 16) ?> Briefing do dia</h3>
                <button type="button" class="btn btn-ghost btn-sm" onclick="novoBriefing(this)" title="Gerar de novo"><?= icone('atualizar', 14) ?></button></div>
            <ul id="briefing"><?php foreach ($briefing['itens'] as $b): ?><li><?= e($b) ?></li><?php endforeach; ?></ul>
            <p class="dica" id="briefingFonte"><?= $briefing['fonte'] === 'ia' ? 'Escrito pela IA com os números de hoje.' : 'Gerado pelas regras da plataforma. Com a IA ligada, ele fica mais detalhado.' ?></p>
        </section>
        <section class="form-box largo">
            <h3><?= icone('enviar', 16) ?> Próximas ações</h3>
            <p class="dica">Em ordem do que vale mais dinheiro agora.</p>
            <ol class="intel-acoes">
            <?php foreach (array_slice($acoes, 0, 7) as $a): ?>
                <li><a href="<?= e($a['link']) ?>"><span class="ic"><?= $a['icone'] ?></span><span><strong><?= e($a['titulo']) ?></strong><small><?= e($a['detalhe']) ?></small></span></a></li>
            <?php endforeach; ?>
            <?php if (!$acoes): ?><li class="dica">Tudo em dia. 🎉</li><?php endif; ?>
            </ol>
        </section>
    </div>

    <div class="intel-grid">
        <section class="form-box largo">
            <h3><?= icone('grafico', 16) ?> Previsão de receita</h3>
            <div class="intel-prev">
                <div><small>Hoje (recorrente)</small><strong><?= e(intel_dinheiro($prev['mrr'])) ?></strong></div>
                <div><small>Próximos 30 dias</small><strong><?= e(intel_dinheiro($prev['mes1'])) ?></strong></div>
                <div><small>60 dias</small><strong><?= e(intel_dinheiro($prev['mes2'])) ?></strong></div>
                <div><small>90 dias</small><strong><?= e(intel_dinheiro($prev['mes3'])) ?></strong></div>
            </div>
            <p class="dica">Conta as mensalidades de hoje, desconta o risco de cada cliente cancelar e soma o que deve fechar da prospecção (<?= (int)$prev['pipeline']['negociando'] ?> negociando × <?= round($prev['pipeline']['taxa'] * 100) ?>% de fechamento<?= $prev['pipeline']['taxa'] == 0.3 ? ', estimado até ter histórico' : ' real' ?>).
            <?php if ($prev['em_risco'] > 0): ?><strong style="color:var(--danger)"><?= e(intel_dinheiro($prev['em_risco'])) ?>/mês em risco.</strong><?php endif; ?></p>
        </section>
        <section class="form-box largo">
            <div class="intel-topo"><h3><?= icone('usuarios', 16) ?> Saúde dos clientes</h3><a class="link" href="empresas.php">ver todos</a></div>
            <div class="intel-semaforo"><span class="s-alto"><?= $contaRisco['alto'] ?> risco alto</span><span class="s-medio"><?= $contaRisco['medio'] ?> atenção</span><span class="s-baixo"><?= $contaRisco['baixo'] ?> saudáveis</span></div>
            <?php foreach ($riscos as $r): ?>
            <div class="intel-cliente">
                <span class="nota r-<?= $r['risco'] ?>"><?= $r['pontos'] ?></span>
                <div><strong><?= e($r['nome']) ?></strong><small><?= e(implode(' · ', array_slice($r['ruim'], 0, 2)) ?: 'sem alertas') ?></small></div>
                <?php if ($r['whatsapp']): ?><a class="btn btn-sm btn-wa" target="_blank" rel="noopener" href="https://wa.me/<?= e((strlen($r['whatsapp']) <= 11 ? '55' : '') . $r['whatsapp']) ?>?text=<?= rawurlencode($r['mensagem']) ?>">Chamar</a><?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (!$riscos): ?><p class="dica">Nenhum cliente pedindo atenção agora.</p><?php endif; ?>
        </section>
    </div>

    <div class="grid-2">
        <div>
            <h2 style="margin-top:0">Empresas recentes</h2>
            <div class="tabela-wrap"><table>
                <thead><tr><th>Empresa</th><th>Plano</th><th>Status</th><th>Site</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($empresasRecentes as $emp): ?>
                <tr>
                    <td><strong><?= e($emp['nome']) ?></strong><span class="mini">vence <?= $emp['vencimento'] ? date('d/m/Y', strtotime($emp['vencimento'])) : '—' ?></span></td>
                    <td><?= e($emp['plano_nome'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= e($emp['status']) ?>"><?= e($emp['status']) ?></span></td>
                    <td><?= pg_bool($emp['publicado']) ? '<span class="badge badge-ativo">no ar</span>' : '<span class="pill pill-neutro">rascunho</span>' ?></td>
                    <td class="acoes"><a class="btn btn-ghost btn-sm" href="site_hub.php?empresa_id=<?= $emp['id'] ?>">Site</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$empresasRecentes): ?><tr class="vazio"><td colspan="5">Nenhuma empresa ainda. <a class="link" href="empresas.php">Cadastre a primeira</a>.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
        <div>
            <div class="card card-ia" style="margin-bottom:16px">
                <div class="label"><?= icone('brilho', 16) ?> Estúdio IA</div>
                <p style="color:var(--text-dim);font-size:13px;margin:4px 0 12px">Descreva o negócio, a IA cria o site inteiro e você ajusta conversando — com pré-visualização por dispositivo e histórico de versões.</p>
                <?php if ($semSite): ?>
                <p style="font-size:13px;margin-bottom:8px">Empresas esperando um site:</p>
                <ul class="checklist">
                    <?php foreach ($semSite as $s): ?><li><?= icone('predio', 14) ?> <?= e($s['nome']) ?> — <a class="link" href="ia_estudio.php?empresa_id=<?= $s['id'] ?>">criar com IA</a></li><?php endforeach; ?>
                </ul>
                <?php else: ?><p class="dica">Nenhuma empresa sem site no momento.</p><?php endif; ?>
            </div>
        </div>
    </div>
</main>
</div>
<script>
async function novoBriefing(b) {
    b.disabled = true;
    const fd = new FormData(); fd.append('acao', 'briefing'); fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    try {
        const r = await (await fetch('index.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })).json();
        if (r.ok) {
            const ul = document.getElementById('briefing'); ul.innerHTML = '';
            r.itens.forEach(t => { const li = document.createElement('li'); li.textContent = t; ul.appendChild(li); });
            document.getElementById('briefingFonte').textContent = r.fonte === 'ia' ? 'Escrito pela IA com os números de hoje.' : 'Gerado pelas regras da plataforma. Com a IA ligada, ele fica mais detalhado.';
        }
    } catch (e) {}
    b.disabled = false;
}
</script>
</body>
</html>
