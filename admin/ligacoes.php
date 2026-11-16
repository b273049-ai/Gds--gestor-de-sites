<?php
// ============================================================
// LIGAÇÕES DE PROSPECÇÃO (Admin → Ligações)
// Fila de quem ligar · roteiro pronto (ou feito pela IA) · respostas para objeções ·
// copiloto ao vivo · cronômetro · resultado em 1 toque · retorno com dia e hora (aviso no
// seu WhatsApp) · mensagem pós-ligação com a prévia. Regras em includes/prospeccao_ligacoes.php.
// Duas filas: "Clientes novos" (leads da prospecção) e "Já clientes" (empresas que já pagam:
// cobrança, renovação, ajuda para configurar, sumidos e relacionamento — includes/clientes_ligacoes.php).
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/prospeccao_ligacoes.php';
require_once __DIR__ . '/../includes/prospeccao_piloto.php';
require_once __DIR__ . '/../includes/clientes_ligacoes.php';
exigir_login('admin');
$para = (($_GET['para'] ?? ($_POST['para'] ?? '')) === 'clientes') ? 'clientes' : 'novos';
$ehCli = $para === 'clientes';
$Q = $ehCli ? 'para=clientes&' : '';      // mantém a fila escolhida nos links
gds_garantir_schema();
$pronto = (bool)$pdo->query("SELECT to_regclass('public.prosp_ligacoes') IS NOT NULL")->fetchColumn()
    && (!$ehCli || (bool)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name='empresas' AND column_name='ligar_em'")->fetchColumn());
$lead = function ($id) use ($pdo) { $st = $pdo->prepare("SELECT * FROM prospects WHERE id=?"); $st->execute([(int)$id]); return $st->fetch() ?: null; };

if ($pronto && $ehCli && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['acao']) && $_POST['acao'] !== 'meta') {
    $c = cli_buscar($_POST['id'] ?? 0);
    try {
        if (!$c) responder_json(['ok' => false, 'erro' => 'Cliente não encontrado.'], 404);
        if ($_POST['acao'] === 'registrar') responder_json(cli_registrar($c, (string)($_POST['resultado'] ?? ''), ['duracao' => $_POST['duracao'] ?? 0, 'canal' => $_POST['canal'] ?? '',
            'anotacao' => $_POST['anotacao'] ?? '', 'retorno' => $_POST['retorno'] ?? '']));
        if ($_POST['acao'] === 'copiloto') {
            session_write_close(); @set_time_limit(40);
            $disse = trim((string)($_POST['disse'] ?? ''));
            if ($disse === '') responder_json(['ok' => false, 'erro' => 'Escreva o que ele disse.']);
            responder_json(cli_copiloto($c, $disse));
        }
        responder_json(['ok' => false, 'erro' => 'Ação desconhecida.'], 400);
    } catch (Throwable $e) {
        error_log('GDS ligações (clientes): ' . $e->getMessage());
        responder_json(['ok' => false, 'erro' => 'Falha no servidor. Tente de novo.'], 500);
    }
}
if ($pronto && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['acao'])) {
    $p = $lead($_POST['id'] ?? 0);
    try {
        switch ($_POST['acao']) {
            case 'registrar':
                if (!$p) responder_json(['ok' => false, 'erro' => 'Lead não encontrado.'], 404);
                responder_json(lig_registrar($p, (string)($_POST['resultado'] ?? ''), ['duracao' => $_POST['duracao'] ?? 0, 'canal' => $_POST['canal'] ?? '',
                    'anotacao' => $_POST['anotacao'] ?? '', 'retorno' => $_POST['retorno'] ?? '']));
            case 'roteiro_ia':
                session_write_close(); @set_time_limit(90);
                if (!$p) responder_json(['ok' => false, 'erro' => 'Lead não encontrado.'], 404);
                responder_json(lig_roteiro_ia($p));
            case 'roteiro_padrao':
                if (!$p) responder_json(['ok' => false, 'erro' => 'Lead não encontrado.'], 404);
                $pdo->prepare("UPDATE prospects SET ligacao_roteiro=NULL WHERE id=?")->execute([$p['id']]);
                responder_json(['ok' => true]);
            case 'copiloto':
                session_write_close(); @set_time_limit(40);
                if (!$p) responder_json(['ok' => false, 'erro' => 'Lead não encontrado.'], 404);
                $disse = trim((string)($_POST['disse'] ?? ''));
                if ($disse === '') responder_json(['ok' => false, 'erro' => 'Escreva o que ele disse.']);
                responder_json(lig_copiloto($p, $disse));
            case 'enviar':
                session_write_close();
                if (!$p) responder_json(['ok' => false, 'erro' => 'Lead não encontrado.'], 404);
                if ($p['optout_em']) responder_json(['ok' => false, 'erro' => 'Este contato pediu para não receber mensagens.']);
                $txt = mb_substr(trim((string)($_POST['texto'] ?? '')), 0, 1500);
                if ($txt === '') responder_json(['ok' => false, 'erro' => 'Escreva a mensagem.']);
                $r = prosp_enviar_plataforma($p, $txt, 'prosp_humano');
                responder_json($r['ok'] ? ['ok' => true, 'msg' => !empty($r['fila']) ? 'Na fila: o conector envia em instantes.' : 'Mensagem enviada!'] : ['ok' => false, 'erro' => $r['erro']]);
            case 'meta':
                set_config('prosp_lig_meta', (string)max(5, min(200, (int)($_POST['meta'] ?? 20))));
                responder_json(['ok' => true]);
            case 'tirar_retorno':
                if ($p) $pdo->prepare("UPDATE prospects SET ligar_em=NULL WHERE id=?")->execute([$p['id']]);
                responder_json(['ok' => true]);
        }
        responder_json(['ok' => false, 'erro' => 'Ação desconhecida.'], 400);
    } catch (Throwable $e) {
        error_log('GDS ligações: ' . $e->getMessage());
        responder_json(['ok' => false, 'erro' => 'Falha no servidor. Tente de novo.'], 500);
    }
}

// ---------- dados da tela ----------
$fila = !$pronto ? [] : ($ehCli ? cli_fila(60) : lig_fila(60));
$agendados = !$pronto ? [] : ($ehCli ? cli_agendados(20) : lig_agendados(20));
$met = !$pronto ? null : ($ehCli ? cli_metricas() : lig_metricas());
$motivoTxt = fn($f) => $ehCli ? cli_motivo_txt($f) : lig_motivo($f)[1];
$atual = null;
if (!empty($_GET['id'])) $atual = $ehCli ? cli_buscar($_GET['id']) : $lead($_GET['id']);
if (!$atual && $fila) $atual = $fila[0];
$pos = 0;
foreach ($fila as $i => $f) if ($atual && (int)$f['id'] === (int)$atual['id']) $pos = $i + 1;
// Próximo: o seguinte na fila; se este lead não está na fila, o primeiro dela
$outros = array_values(array_filter($fila, fn($f) => !$atual || (int)$f['id'] !== (int)$atual['id']));
$proximo = $pos ? ($fila[$pos] ?? ($outros[0] ?? null)) : ($outros[0] ?? null);
$dadosCli = $atual && $ehCli ? cli_dados($atual) : null;
$rot = !$atual ? null : ($ehCli ? cli_roteiro($atual, $dadosCli) : lig_roteiro($atual));
$num = !$atual ? null : ($ehCli ? cli_numeros($atual) : lig_numeros($atual));
$hor = $atual ? lig_horario($atual) : null;
$motivo = !$atual ? null : ($ehCli ? [$atual['motivo'], cli_motivo_txt($atual)] : lig_motivo($atual));
$hist = !$atual ? [] : ($ehCli ? cli_historico($atual['id'], 10) : lig_historico($atual['id'], 10));
$resultados = $ehCli ? CLI_RESULTADOS : LIG_RESULTADOS;
$canalPlat = (bool)wa_remetente(['wa_modo' => 'plataforma']);
$pct = fn($a, $b) => $b > 0 ? (int)round($a * 100 / $b) : 0;
$retornos = [['Daqui a 1 hora', '+1 hour'], ['Hoje 14h', 'today 14:00'], ['Hoje 17h', 'today 17:00'], ['Amanhã 9h', 'tomorrow 09:00'], ['Amanhã 14h', 'tomorrow 14:00'], ['Em 3 dias, 10h', '+3 days 10:00'], ['Semana que vem, 10h', 'next monday 10:00']];
$retornos = array_values(array_filter(array_map(fn($r) => [$r[0], date('Y-m-d\TH:i', strtotime($r[1]))], $retornos), fn($r) => strtotime($r[1]) > time() + 600));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Ligações · Prospecção') ?>
<style>
.lg-para { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
.lg-para a { display: grid; gap: 1px; padding: 10px 16px; border-radius: 12px; border: 1px solid var(--border); background: var(--surface); color: var(--text); text-decoration: none; font-weight: 700; font-size: 14px; }
.lg-para a small { font-weight: 500; font-size: 12px; color: var(--text-dim); }
.lg-para a[aria-current] { border-color: var(--primary); background: var(--info-bg); box-shadow: 0 0 0 1px var(--primary) inset; }
.lg-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 18px; }
.lg-kpis > div { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; display: grid; gap: 2px; }
.lg-kpis strong { font-size: 24px; font-variant-numeric: tabular-nums; } .lg-kpis span { font-size: 12px; color: var(--text-dim); }
.lg-meta { grid-column: span 2; } .lg-meta .barra { height: 8px; border-radius: 6px; background: var(--bg); overflow: hidden; margin-top: 6px; } .lg-meta .barra i { display: block; height: 100%; background: var(--gradiente-ia); }
.lg-grade { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 16px; align-items: start; }
@media (max-width: 1100px) { .lg-grade { grid-template-columns: minmax(0, 1fr); } .lg-meta { grid-column: auto; } }
.lg-card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 20px; display: grid; gap: 14px; }
.lg-topo { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: flex-start; }
.lg-topo h2 { margin: 2px 0 4px; font-size: 24px; overflow-wrap: anywhere; }
.lg-topo > div { min-width: 0; }
.lg-info { display: flex; flex-wrap: wrap; gap: 6px 14px; color: var(--text-dim); font-size: 13px; }
.lg-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12.5px; font-weight: 600; background: var(--surface-2); border: 1px solid var(--border); }
.lg-chip.quente { background: rgba(245, 158, 11, .12); border-color: rgba(245, 158, 11, .4); color: #fcd34d; }
.lg-chip.bom { background: var(--ok-bg); border-color: rgba(62, 207, 142, .4); color: #8ff0c2; }
.lg-chip.ruim { background: var(--warn-bg); border-color: rgba(245, 185, 76, .4); color: #ffd98f; }
.lg-ligar { display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; align-items: stretch; }
.lg-ligar .btn { justify-content: center; min-height: 52px; font-size: 15px; }
.lg-cron { display: grid; place-items: center; min-width: 120px; padding: 6px 14px; border-radius: 12px; background: var(--bg); border: 1px solid var(--border); }
.lg-cron strong { font: 700 26px/1 ui-monospace, SFMono-Regular, Menlo, monospace; font-variant-numeric: tabular-nums; }
.lg-cron button { background: none; border: 0; color: var(--primary); font: inherit; font-size: 12px; cursor: pointer; padding: 2px; }
.lg-cron.rodando strong { color: #8ff0c2; }
@media (max-width: 640px) { .lg-ligar { grid-template-columns: 1fr; } }
.lg-roteiro { counter-reset: et; display: grid; gap: 10px; }
.lg-etapa { position: relative; padding: 14px 14px 14px 52px; border-radius: 12px; background: var(--surface-2); border: 1px solid var(--border); cursor: pointer; }
.lg-etapa::before { counter-increment: et; content: counter(et); position: absolute; left: 14px; top: 14px; width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; font-weight: 700; font-size: 13px; background: var(--surface-3); }
.lg-etapa.feita { opacity: .55; } .lg-etapa.feita::before { content: "✓"; background: var(--ok); color: #052e1f; }
.lg-etapa.agora { border-color: var(--primary); box-shadow: 0 0 0 1px var(--primary) inset; }
.lg-etapa h3 { margin: 0 0 2px; font-size: 15px; display: flex; gap: 8px; align-items: baseline; } .lg-etapa h3 small { color: var(--text-mute); font-weight: 500; }
.lg-etapa .dica { margin: 0 0 6px; }
.lg-etapa ul { margin: 0; padding-left: 18px; display: grid; gap: 4px; } .lg-etapa li { font-size: 15px; line-height: 1.45; overflow-wrap: anywhere; }
.lg-card { min-width: 0; } .lg-info span, .lg-obj p { overflow-wrap: anywhere; }
.lg-obj { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 8px; }
.lg-obj details { background: var(--surface-2); border: 1px solid var(--border); border-radius: 10px; padding: 10px 12px; }
.lg-obj summary { cursor: pointer; font-weight: 600; font-size: 13.5px; }
.lg-obj p { margin: 8px 0 0; font-size: 14px; line-height: 1.45; } .lg-obj p b { color: #b9c7ff; font-weight: 600; }
.lg-lado { position: sticky; top: 12px; display: grid; gap: 14px; }
@media (max-width: 1100px) { .lg-lado { position: static; } }
.lg-res { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.lg-res { min-width: 0; } .form-box .lg-res button { white-space: normal; min-width: 0; line-height: 1.25; }
.lg-res button { display: flex; gap: 6px; align-items: center; justify-content: flex-start; text-align: left; padding: 10px; border-radius: 10px; border: 1px solid var(--border); background: var(--surface-2); color: var(--text); font: inherit; font-size: 13px; cursor: pointer; min-height: 44px; }
.form-box .lg-res button:not([aria-pressed="true"]) { background: var(--surface-2); color: var(--text); border-color: var(--border); padding: 10px; font-weight: 500; }
.lg-res button[aria-pressed="true"] { background: var(--primary-btn); border-color: var(--primary-btn); color: #fff; }
.lg-res button:focus-visible, .lg-etapa:focus-visible { outline: 3px solid var(--primary); outline-offset: 2px; }
.lg-presets { display: flex; flex-wrap: wrap; gap: 6px; }
.lg-hist { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; } .lg-hist li { font-size: 13px; border-left: 3px solid var(--border); padding-left: 10px; } .lg-hist small { color: var(--text-dim); display: block; }
.lg-fila { list-style: none; margin: 0; padding: 0; display: grid; gap: 4px; } .lg-fila a { display: flex; justify-content: space-between; gap: 8px; padding: 8px 10px; border-radius: 8px; text-decoration: none; color: var(--text); font-size: 13px; }
.lg-fila a:hover { background: var(--surface-2); } .lg-fila a[aria-current] { background: var(--info-bg); } .lg-fila small { color: var(--text-dim); white-space: nowrap; }
.lg-pos { display: grid; gap: 8px; } .lg-pos textarea { width: 100%; min-height: 110px; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 10px; padding: 10px; font: inherit; font-size: 14px; }
.lg-copiloto { display: flex; gap: 8px; } .lg-copiloto input { flex: 1; min-width: 0; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 9px 12px; font: inherit; }
.lg-copiloto input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(91,124,255,.2); }
.lg-resposta { background: var(--info-bg); border: 1px solid rgba(91,124,255,.35); border-radius: 10px; padding: 10px 12px; font-size: 14.5px; line-height: 1.45; }
.lg-resposta b { display: block; font-size: 12px; color: var(--text-dim); font-weight: 600; margin-bottom: 2px; }
.lg-horas { display: flex; align-items: flex-end; gap: 4px; height: 70px; } .lg-horas div { flex: 1; display: grid; gap: 2px; align-items: end; text-align: center; font-size: 10.5px; color: var(--text-dim); }
.lg-horas i { display: block; background: var(--primary); border-radius: 4px 4px 0 0; min-height: 3px; }
.form-box.lg { max-width: none; margin: 0; padding: 18px; } .form-box.lg h3 { font-size: 16px; margin-bottom: 8px; }
</style>
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>📞 Ligações</h1>
        <p class="subtitulo">Ligue para os leads com o roteiro na tela: o que falar, como responder às objeções e o que fazer depois.</p></div>
        <div class="acoes"><a class="btn btn-ghost" href="/admin/prospeccao.php">← Prospecção</a></div></div>
    <?= flash_render() ?>
    <nav class="lg-para" aria-label="Para quem ligar">
        <a href="ligacoes.php"<?= $ehCli ? '' : ' aria-current="page"' ?>>🎯 Clientes novos <small>leads da prospecção</small></a>
        <a href="ligacoes.php?para=clientes"<?= $ehCli ? ' aria-current="page"' : '' ?>>🤝 Já clientes <small>cobrança, ajuda e relacionamento</small></a>
    </nav>
<?php if (!$pronto): ?>
    <div class="vazio-box"><h2>Preparando as ligações…</h2><p>O banco está sendo atualizado. Recarregue a página em alguns segundos.</p></div>
<?php else: $hj = $met['hoje']; ?>
    <?php if ($ehCli): ?>
    <section class="lg-kpis" aria-label="Números de hoje">
        <div><strong><?= $hj['total'] ?></strong><span>ligações para clientes hoje</span></div>
        <div><strong><?= $hj['total'] ? $pct($hj['atendidas'], $hj['total']) . '%' : '–' ?></strong><span>atenderam (<?= $hj['atendidas'] ?>)</span></div>
        <div><strong><?= $hj['pagar'] ?></strong><span>combinaram o pagamento</span></div>
        <div><strong><?= $hj['cancelar'] ?></strong><span>querem cancelar</span></div>
        <div><strong><?= $hj['segundos'] >= 60 ? floor($hj['segundos'] / 60) . ' min' : $hj['segundos'] . ' s' ?></strong><span>ao telefone</span></div>
        <div><strong><?= $met['retornos_hoje'] ?></strong><span>retornos marcados hoje</span></div>
    </section>
    <?php else: ?>
    <section class="lg-kpis" aria-label="Números de hoje">
        <div class="lg-meta"><span>Meta de hoje</span><strong><?= $hj['total'] ?> <small style="font-size:14px;color:var(--text-dim)">de <?= $met['meta'] ?> ligações</small></strong>
            <div class="barra" aria-hidden="true"><i style="width:<?= min(100, $pct($hj['total'], $met['meta'])) ?>%"></i></div>
            <div class="lg-presets" style="margin-top:8px" aria-label="Mudar a meta"><?php foreach ([10, 20, 30, 50] as $m): ?><button type="button" class="btn btn-sm <?= $m === $met['meta'] ? 'btn-ligado' : 'btn-ghost' ?>" data-meta="<?= $m ?>"><?= $m ?></button><?php endforeach; ?></div></div>
        <div><strong><?= $hj['total'] ? $pct($hj['atendidas'], $hj['total']) . '%' : '–' ?></strong><span>atenderam (<?= $hj['atendidas'] ?>)</span></div>
        <div><strong><?= $hj['interessados'] ?></strong><span>interessados hoje</span></div>
        <div><strong><?= $hj['fechou'] ?></strong><span>fechados hoje</span></div>
        <div><strong><?= $hj['segundos'] >= 60 ? floor($hj['segundos'] / 60) . ' min' : $hj['segundos'] . ' s' ?></strong><span>ao telefone</span></div>
        <div><strong><?= $met['retornos_hoje'] ?></strong><span>retornos marcados hoje</span></div>
    </section>
    <?php endif; ?>

    <?php if (!$atual && $ehCli): ?>
    <div class="vazio-box"><?= icone('celular', 28) ?><h2>Nenhum cliente para ligar agora</h2>
        <p>Aparecem aqui os clientes com mensalidade em atraso ou vencendo, os novos que ainda não configuraram, quem sumiu do painel e quem está há 60 dias sem uma ligação.</p></div>
    <?php elseif (!$atual): ?>
    <div class="vazio-box"><?= icone('celular', 28) ?><h2>Ninguém para ligar agora</h2>
        <p>A fila mostra quem combinou retorno, quem clicou em "Quero este site", quem abriu a prévia ou respondeu e os leads quentes com telefone.
        Rode uma <a class="link" href="/admin/prospeccao.php#cacada">caçada</a> para encher a base<?= $agendados ? ', ou veja os retornos marcados abaixo' : '' ?>.</p></div>
    <?php else: ?>
    <div class="lg-grade" id="lg" data-id="<?= (int)$atual['id'] ?>">
        <div style="display:grid;gap:16px;min-width:0">
            <section class="lg-card" aria-labelledby="lgNome">
                <div class="lg-topo">
                    <div>
                        <span class="lg-chip <?= in_array($motivo[0], ['retorno', 'quer', 'abriu', 'cobranca'], true) ? 'quente' : '' ?>"><?= e($motivo[1]) ?></span>
                        <?php if ($pos): ?><span class="muted" style="font-size:13px;margin-left:6px"><?= $pos ?> de <?= count($fila) ?> na fila</span><?php endif; ?>
                        <h2 id="lgNome"><?= e($atual['nome']) ?></h2>
                        <div class="lg-info">
                            <?php if ($ehCli): $res30 = cli_resultado_txt($dadosCli); ?>
                            <span><?= e($dadosCli['plano']['nome'] ?? 'Sem plano') ?><?= !empty($dadosCli['plano']['valor']) ? ' · ' . e(cli_brl($dadosCli['plano']['valor'])) . '/mês' : '' ?></span>
                            <span>📈 Últimos 30 dias: <?= e($res30 ?: 'sem movimento registrado') ?></span>
                            <span>🔑 Último acesso ao painel: <?= $atual['login'] ? e(date('d/m/Y', strtotime($atual['login']))) : 'nunca' ?></span>
                            <?php if ($dadosCli['cobranca']): ?><span>💳 Em aberto: <?= e(cli_brl($dadosCli['cobranca']['valor'])) ?> (venc. <?= e(date('d/m', strtotime($dadosCli['cobranca']['vencimento']))) ?>)</span><?php endif; ?>
                            <?php else: ?>
                            <span><?= e($atual['categoria'] ?: 'Negócio') ?></span>
                            <span>📍 <?= e(trim(($atual['bairro'] ? $atual['bairro'] . ', ' : '') . $atual['cidade'], ', ')) ?></span>
                            <?php if ($atual['nota']): ?><span>⭐ <?= number_format((float)$atual['nota'], 1, ',', '') ?> (<?= (int)$atual['avaliacoes'] ?> avaliações)</span><?php endif; ?>
                            <?php if ((int)$atual['ligacoes']): ?><span>📞 <?= (int)$atual['ligacoes'] ?> ligação(ões) · última: <?= e(LIG_RESULTADOS[$atual['ultimo_resultado']][0] ?? '-') ?></span><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display:grid;gap:6px;justify-items:end">
                        <span class="lg-chip <?= $hor['bom'] ? 'bom' : 'ruim' ?>"><?= $hor['bom'] ? '🟢' : '🟡' ?> <?= e($hor['agora']) ?></span>
                        <?php if ($hor['hoje']): ?><span class="muted" style="font-size:12.5px"><?= $hor['fechado_hoje'] ? '⚠ ' : '' ?>Hoje: <?= e(preg_replace('/^[^:]+:\s*/u', '', $hor['hoje'])) ?></span><?php endif; ?>
                    </div>
                </div>
                <p class="dica" style="margin:0"><?= e($hor['dica']) ?></p>
                <div class="lg-ligar">
                    <?php if ($num['wa_link']): ?><a class="btn btn-wa" id="lgWa" href="<?= e($num['wa_link']) ?>" target="_blank" rel="noopener" data-canal="whatsapp"><?= icone('chat', 18) ?> Ligar pelo WhatsApp</a><?php endif; ?>
                    <?php if ($num['tel']): ?><a class="btn <?= $num['wa_link'] ? 'btn-ghost' : '' ?>" id="lgTel" href="<?= e($num['tel']) ?>" data-canal="telefone"><?= icone('celular', 18) ?> Ligar: <?= e($num['exibir']) ?></a><?php endif; ?>
                    <div class="lg-cron" id="lgCron" aria-live="off"><strong id="lgTempo">00:00</strong><button type="button" id="lgCronBt">Iniciar</button></div>
                </div>
                <p class="dica" style="margin:0"><?php if ($num['wa_link']): ?>"Ligar pelo WhatsApp" abre a conversa com ele: toque no 📞 lá em cima para chamar. <?php endif; ?>O cronômetro começa sozinho quando você toca em ligar.
                    <?php if ($ehCli): ?><a class="link" href="<?= e($dadosCli['site']) ?>" target="_blank" rel="noopener">🌐 Abrir o site dele</a> · <a class="link" href="/admin/empresa_editar.php?id=<?= (int)$atual['id'] ?>">Ver a empresa</a>
                    <?php else: ?><a class="link" href="<?= e(prosp_link_previa($atual)) ?>" target="_blank" rel="noopener">👁 Abrir a prévia dele</a><?php endif; ?></p>
            </section>

            <section class="lg-card" aria-labelledby="tRoteiro">
                <div class="lg-topo"><h3 id="tRoteiro" style="margin:0">Roteiro <?= ($rot['origem'] ?? '') === 'ia' ? '<span class="lg-chip" title="Feito pela IA para este lead">✨ personalizado pela IA' . (!empty($rot['gerado_em']) ? ' · ' . e(date('d/m H:i', strtotime($rot['gerado_em']))) : '') . '</span>' : '' ?></h3>
                    <?php if (!$ehCli): ?><div class="lg-presets">
                        <button type="button" class="btn btn-sm btn-ghost" id="lgIa"><?= icone('brilho', 14) ?> <?= ($rot['origem'] ?? '') === 'ia' ? 'Refazer com IA' : 'Personalizar com IA' ?></button>
                        <?php if (($rot['origem'] ?? '') === 'ia'): ?><button type="button" class="btn btn-sm btn-ghost" id="lgPadrao">Voltar ao roteiro pronto</button><?php endif; ?>
                    </div><?php else: ?><span class="lg-chip"><?= e(preg_replace('/^\S+\s/u', '', CLI_MOTIVOS[$atual['motivo']] ?? '')) ?></span><?php endif; ?></div>
                <p class="dica" style="margin:0">Toque numa etapa quando terminar: a próxima fica em destaque. Não precisa ler palavra por palavra — fale do seu jeito.</p>
                <div class="lg-roteiro" id="lgRoteiro">
                    <?php foreach ($rot['etapas'] as $i => $et): ?>
                    <div class="lg-etapa<?= $i === 0 ? ' agora' : '' ?>" tabindex="0" role="button" aria-pressed="false" aria-label="Etapa <?= $i + 1 ?>: <?= e($et['titulo']) ?> (toque quando terminar)">
                        <h3><?= e($et['titulo']) ?><?php if (!empty($et['tempo'])): ?> <small><?= e($et['tempo']) ?></small><?php endif; ?></h3>
                        <?php if (!empty($et['dica'])): ?><p class="dica"><?= e($et['dica']) ?></p><?php endif; ?>
                        <ul><?php foreach ($et['falas'] as $fala): ?><li><?= e($fala) ?></li><?php endforeach; ?></ul>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="lg-card" aria-labelledby="tObj">
                <h3 id="tObj" style="margin:0">Se ele disser…</h3>
                <div class="lg-obj">
                    <?php foreach ($rot['objecoes'] as $o): ?>
                    <details><summary><?= e($o['titulo']) ?></summary><p><?= e($o['resposta']) ?><?php if (!empty($o['pergunta'])): ?> <b><?= e($o['pergunta']) ?></b><?php endif; ?></p></details>
                    <?php endforeach; ?>
                </div>
                <div>
                    <label for="lgDisse" style="font-size:13px;color:var(--text-dim);display:block;margin-bottom:6px">🤖 Copiloto ao vivo: escreva o que ele acabou de dizer</label>
                    <form class="lg-copiloto" id="lgCopForm"><input type="text" id="lgDisse" maxlength="600" placeholder="Ex.: já pago o iFood e não quero mais nada" autocomplete="off"><button type="submit" class="btn btn-sm" id="lgCopBt">O que eu falo?</button></form>
                    <div class="lg-resposta" id="lgCopRes" hidden></div>
                </div>
            </section>
        </div>

        <aside class="lg-lado" aria-label="Resultado e próximos">
            <section class="form-box lg">
                <h3>Como foi a ligação?</h3>
                <div class="lg-res" role="group" aria-label="Resultado">
                    <?php foreach ($resultados as $k => [$rotulo, $emoji]): ?><button type="button" data-res="<?= $k ?>" aria-pressed="false"><span aria-hidden="true"><?= $emoji ?></span><?= e($rotulo) ?></button><?php endforeach; ?>
                </div>
                <div id="lgRetorno" hidden style="margin-top:10px">
                    <label style="font-size:13px;color:var(--text-dim);display:block;margin:0 0 6px">Quando ligar de volta?</label>
                    <div class="lg-presets"><?php foreach ($retornos as [$r, $v]): ?><button type="button" class="btn btn-sm btn-ghost" data-retorno="<?= e($v) ?>"><?= e($r) ?></button><?php endforeach; ?></div>
                    <input type="datetime-local" id="lgRetornoEm" aria-label="Dia e hora do retorno" style="margin-top:8px;width:100%" min="<?= date('Y-m-d\TH:i') ?>">
                    <p class="dica">Na hora marcada, chega um aviso no seu WhatsApp e ele volta para o topo da fila.</p>
                </div>
                <label for="lgNota" style="font-size:13px;color:var(--text-dim);display:block;margin:12px 0 6px">Anotação (o que ele contou, com quem falar…)</label>
                <textarea id="lgNota" rows="3" maxlength="1500" style="width:100%" placeholder="Ex.: falar com a Ana, a dona. Usa iFood e acha a taxa alta."></textarea>
                <div id="lgErro" class="alert alert-erro" role="alert" hidden style="margin-top:10px"></div>
                <button type="button" class="btn" id="lgSalvar" style="width:100%;margin-top:12px" disabled>Salvar resultado</button>
            </section>

            <section class="form-box lg lg-pos" id="lgPos" hidden aria-live="polite">
                <h3 id="lgPosTit">Resultado salvo</h3>
                <div id="lgPosMsg" hidden>
                    <label for="lgMsg" style="font-size:13px;color:var(--text-dim)">Mande agora pelo WhatsApp:</label>
                    <textarea id="lgMsg"></textarea>
                    <div class="lg-presets">
                        <?php if ($canalPlat && $num['whatsapp'] && !$ehCli): ?><button type="button" class="btn btn-sm btn-wa" id="lgEnviar"><?= icone('chat', 14) ?> Enviar pela plataforma</button><?php endif; ?>
                        <?php if ($num['whatsapp']): ?><a class="btn btn-sm <?= $canalPlat && !$ehCli ? 'btn-ghost' : 'btn-wa' ?>" id="lgAbrirWa" target="_blank" rel="noopener" href="#">Abrir no meu WhatsApp</a><?php endif; ?>
                    </div>
                </div>
                <?php if (!$ehCli): ?><a class="btn btn-sm btn-cliente" id="lgFechou" hidden href="/admin/prospeccao.php?q=<?= rawurlencode($atual['nome']) ?>#leads">🎉 Criar o site dele (Virar cliente)</a><?php endif; ?>
                <?php if ($proximo): ?><a class="btn" href="?<?= $Q ?>id=<?= (int)$proximo['id'] ?>" id="lgProximo" style="width:100%">Próxima ligação: <?= e(mb_strimwidth($proximo['nome'], 0, 28, '…')) ?> →</a>
                <?php else: ?><a class="btn btn-ghost" href="ligacoes.php<?= $ehCli ? '?para=clientes' : '' ?>">Atualizar a fila</a><?php endif; ?>
            </section>

            <?php if ($hist): ?>
            <section class="form-box lg"><h3>Ligações anteriores</h3>
                <ul class="lg-hist"><?php foreach ($hist as $h): ?><li><?= e($h['rotulo']) ?><small><?= e($h['quando']) ?><?= $h['duracao'] ? ' · ' . e($h['duracao']) : '' ?><?= $h['retorno'] ? ' · retorno ' . e($h['retorno']) : '' ?></small><?php if ($h['anotacao']): ?><div><?= e($h['anotacao']) ?></div><?php endif; ?></li><?php endforeach; ?></ul>
            </section>
            <?php endif; ?>

            <section class="form-box lg"><h3>Fila (<?= count($fila) ?>)</h3>
                <ul class="lg-fila"><?php foreach (array_slice($fila, 0, 12) as $f): $mo = [null, $motivoTxt($f)]; ?>
                    <li><a href="?<?= $Q ?>id=<?= (int)$f['id'] ?>"<?= (int)$f['id'] === (int)$atual['id'] ? ' aria-current="true"' : '' ?>><span><?= e(mb_strimwidth($f['nome'], 0, 30, '…')) ?></span><small><?= e(mb_strimwidth($mo[1], 0, 26, '…')) ?></small></a></li>
                <?php endforeach; ?></ul>
                <?php if (count($fila) > 12): ?><p class="dica">+ <?= count($fila) - 12 ?> na sequência.</p><?php endif; ?>
            </section>
        </aside>
    </div>
    <?php endif; ?>

    <div class="grid-2" style="margin-top:18px">
        <section class="form-box largo"><h3>🕑 Retornos marcados</h3>
            <?php if (!$agendados): ?><p class="dica">Nenhum retorno marcado para mais tarde.</p><?php else: ?>
            <ul class="lg-fila"><?php foreach ($agendados as $a): ?><li><a href="?<?= $Q ?>id=<?= (int)$a['id'] ?>"><span><?= e(mb_strimwidth($a['nome'], 0, 34, '…')) ?></span><small><?= e(lig_quando($a['ligar_em'])) ?></small></a></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </section>
        <?php if (!$ehCli): ?><section class="form-box largo"><h3>📈 Últimos 30 dias</h3>
            <?php $ms = $met['mes']; ?>
            <p style="margin:0 0 10px"><?= $ms['total'] ?> ligações · <?= $ms['total'] ? $pct($ms['atendidas'], $ms['total']) : 0 ?>% atenderam · <?= $ms['interessados'] ?> interessados · <?= $ms['fechou'] ?> fechados<?= $ms['atendidas'] ? ' (' . $pct($ms['fechou'], $ms['atendidas']) . '% de quem atendeu)' : '' ?></p>
            <?php if ($met['horas']): ?>
            <p class="dica" style="margin:0 0 6px">Quantos atendem em cada horário<?= $met['melhor_hora'] ? ' — melhor: <strong>' . e($met['melhor_hora']) . '</strong>' : '' ?></p>
            <div class="lg-horas" role="img" aria-label="Porcentagem de atendimento por horário"><?php foreach ($met['horas'] as $h): ?><div title="<?= $h['n'] ?> ligações"><i style="height:<?= max(3, (int)round($h['pct'] * .55)) ?>px"></i><?= $h['pct'] ?>%<br><?= $h['h'] ?>h</div><?php endforeach; ?></div>
            <?php else: ?><p class="dica">Com 5 ligações ou mais num mesmo horário, aparece aqui o horário em que mais gente atende.</p><?php endif; ?>
        </section><?php endif; ?>
    </div>
<?php endif; ?>
</main>
</div>
<?php if ($atual): ?>
<script>
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var ID = <?= (int)$atual['id'] ?>, CSRF = document.querySelector('meta[name="csrf-token"]').content;
  var WA = <?= json_encode($num['whatsapp']) ?>;
  function api(dados) {
    var fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('id', ID); fd.append('para', <?= json_encode($para) ?>);
    Object.keys(dados).forEach(function (k) { fd.append(k, dados[k]); });
    return fetch('ligacoes.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); }).catch(function () { return { ok: false, erro: 'Sem conexão. Tente de novo.' }; });
  }

  // ---- cronômetro ----
  var ini = 0, acum = 0, timer = null, canal = '';
  function mostrar() { var s = Math.floor((acum + (ini ? Date.now() - ini : 0)) / 1000); $('lgTempo').textContent = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'); }
  function ligar() { if (ini) return; ini = Date.now(); timer = setInterval(mostrar, 500); $('lgCron').classList.add('rodando'); $('lgCronBt').textContent = 'Pausar'; }
  function pausar() { if (!ini) return; acum += Date.now() - ini; ini = 0; clearInterval(timer); mostrar(); $('lgCron').classList.remove('rodando'); $('lgCronBt').textContent = 'Continuar'; }
  $('lgCronBt').addEventListener('click', function () { ini ? pausar() : ligar(); });
  ['lgWa', 'lgTel'].forEach(function (id) { var a = $(id); if (a) a.addEventListener('click', function () { canal = a.dataset.canal; ligar(); }); });
  function segundos() { return Math.floor((acum + (ini ? Date.now() - ini : 0)) / 1000); }

  // ---- roteiro: etapa feita → próxima em destaque ----
  var etapas = [].slice.call(document.querySelectorAll('.lg-etapa'));
  function marcar(el) {
    var feita = !el.classList.contains('feita');
    el.classList.toggle('feita', feita); el.setAttribute('aria-pressed', feita);
    etapas.forEach(function (e) { e.classList.remove('agora'); });
    var prox = etapas.filter(function (e) { return !e.classList.contains('feita'); })[0];
    if (prox) { prox.classList.add('agora'); if (feita) prox.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
  }
  etapas.forEach(function (el) {
    el.addEventListener('click', function () { marcar(el); });
    el.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); marcar(el); } });
  });
  var bIa = $('lgIa');
  if (bIa) bIa.addEventListener('click', function () {
    bIa.disabled = true; bIa.textContent = 'Escrevendo o roteiro…';
    api({ acao: 'roteiro_ia' }).then(function (r) { if (r.ok) location.reload(); else { alert(r.erro); bIa.disabled = false; bIa.textContent = 'Personalizar com IA'; } });
  });
  var bPad = $('lgPadrao');
  if (bPad) bPad.addEventListener('click', function () { api({ acao: 'roteiro_padrao' }).then(function () { location.reload(); }); });

  // ---- copiloto ----
  $('lgCopForm').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var d = $('lgDisse').value.trim(), out = $('lgCopRes'), bt = $('lgCopBt');
    if (!d) { $('lgDisse').focus(); return; }
    bt.disabled = true; out.hidden = false; out.textContent = 'Pensando…';
    api({ acao: 'copiloto', disse: d }).then(function (r) {
      bt.disabled = false;
      if (!r.ok) { out.textContent = r.erro; return; }
      out.innerHTML = '';
      [['Ele quis dizer', r.leitura], ['Fale', r.falar], ['E pergunte', r.pergunta]].forEach(function (x) {
        if (!x[1]) return; var p = document.createElement('p'); p.style.margin = '0 0 6px'; var b = document.createElement('b'); b.textContent = x[0]; p.appendChild(b); p.appendChild(document.createTextNode(x[1])); out.appendChild(p);
      });
      if (r.resultado) { var bt2 = document.querySelector('[data-res="' + r.resultado + '"]'); if (bt2) escolher(bt2); }
    });
  });

  // ---- resultado ----
  var resultado = '';
  function escolher(b) {
    document.querySelectorAll('[data-res]').forEach(function (x) { x.setAttribute('aria-pressed', x === b); });
    resultado = b.dataset.res; $('lgRetorno').hidden = resultado !== 'retornar'; $('lgSalvar').disabled = false;
    if (resultado === 'retornar' && !$('lgRetornoEm').value) $('lgRetornoEm').focus();
  }
  document.querySelectorAll('[data-res]').forEach(function (b) { b.addEventListener('click', function () { escolher(b); }); });
  document.querySelectorAll('[data-retorno]').forEach(function (b) { b.addEventListener('click', function () {
    $('lgRetornoEm').value = b.dataset.retorno;
    document.querySelectorAll('[data-retorno]').forEach(function (x) { x.classList.toggle('btn-ligado', x === b); x.classList.toggle('btn-ghost', x !== b); });
  }); });
  $('lgSalvar').addEventListener('click', function () {
    var bt = this, err = $('lgErro');
    pausar(); err.hidden = true; bt.disabled = true; bt.textContent = 'Salvando…';
    api({ acao: 'registrar', resultado: resultado, duracao: segundos(), canal: canal, anotacao: $('lgNota').value, retorno: resultado === 'retornar' ? $('lgRetornoEm').value : '' }).then(function (r) {
      bt.textContent = 'Salvar resultado';
      if (!r.ok) { err.textContent = r.erro; err.hidden = false; bt.disabled = false; return; }
      bt.textContent = '✓ Salvo';
      $('lgPos').hidden = false;
      $('lgPosTit').textContent = r.retorno ? 'Salvo! Retorno ' + r.retorno + '.' : 'Resultado salvo.';
      if (r.mensagem && WA) {
        $('lgPosMsg').hidden = false; $('lgMsg').value = r.mensagem;
        var a = $('lgAbrirWa'); if (a) a.href = 'https://wa.me/' + WA + '?text=' + encodeURIComponent(r.mensagem);
      }
      if (resultado === 'fechou') $('lgFechou').hidden = false;
      var nx = $('lgProximo'); (nx || $('lgPos')).focus && (nx || $('lgPos')).scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });
  });
  var msg = $('lgMsg');
  if (msg) msg.addEventListener('input', function () { var a = $('lgAbrirWa'); if (a) a.href = 'https://wa.me/' + WA + '?text=' + encodeURIComponent(msg.value); });
  var env = $('lgEnviar');
  if (env) env.addEventListener('click', function () {
    env.disabled = true;
    api({ acao: 'enviar', texto: $('lgMsg').value }).then(function (r) { env.textContent = r.ok ? '✓ ' + r.msg : 'Enviar pela plataforma'; env.disabled = !!r.ok; if (!r.ok) alert(r.erro); });
  });
  // ---- meta ----
  document.querySelectorAll('[data-meta]').forEach(function (b) { b.addEventListener('click', function () { api({ acao: 'meta', meta: b.dataset.meta }).then(function () { location.reload(); }); }); });
})();
</script>
<?php else: ?>
<script>
document.querySelectorAll('[data-meta]').forEach(function (b) { b.addEventListener('click', function () {
  var fd = new FormData(); fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content); fd.append('acao', 'meta'); fd.append('meta', b.dataset.meta);
  fetch('ligacoes.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function () { location.reload(); });
}); });
</script>
<?php endif; ?>
</body>
</html>
