<?php
// Treinos dos alunos: ficha por dia da semana, link pessoal e check-ins (includes/treino_vale.php)
require_once __DIR__ . '/../includes/auth.php';
exigir_login('empresa');
require_once __DIR__ . '/../includes/treino_vale.php';
$empresaId = (int)empresa_id_atual();
$st = $pdo->prepare("SELECT * FROM empresas WHERE id=?"); $st->execute([$empresaId]); $empresa = $st->fetch();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['salvar_treino'])) {
            [$ok, $msg, $id] = treino_salvar($empresaId, $_POST);
            flash($ok ? 'sucesso' : 'erro', $msg);
            header('Location: treinos.php' . ($ok ? '?ver=' . $id : (isset($_POST['id']) && $_POST['id'] ? '?editar=' . (int)$_POST['id'] : '?novo=1'))); exit;
        }
        if (isset($_POST['arquivar'])) { $pdo->prepare("UPDATE treinos SET ativo=false WHERE id=? AND empresa_id=?")->execute([(int)$_POST['arquivar'], $empresaId]); flash('sucesso', 'Treino arquivado (o link deixa de abrir).'); }
        if (isset($_POST['duplicar'])) {
            $d = $pdo->prepare("SELECT * FROM treinos WHERE id=? AND empresa_id=?"); $d->execute([(int)$_POST['duplicar'], $empresaId]);
            if ($t = $d->fetch()) { $pdo->prepare("INSERT INTO treinos (empresa_id, aluno_nome, titulo, plano, token) VALUES (?,?,?,?,?)")->execute([$empresaId, 'Novo aluno', $t['titulo'], $t['plano'], bin2hex(random_bytes(12))]); header('Location: treinos.php?editar=' . (int)$pdo->lastInsertId()); exit; }
        }
    } catch (PDOException $e) { flash('erro', 'O banco ainda está sendo atualizado. Recarregue a página.'); }
    header('Location: treinos.php'); exit;
}
$editar = null;
if (isset($_GET['editar'])) { $q = $pdo->prepare("SELECT * FROM treinos WHERE id=? AND empresa_id=?"); $q->execute([(int)$_GET['editar'], $empresaId]); $editar = $q->fetch() ?: null; }
$lista = [];
try { $q = $pdo->prepare("SELECT t.*, (SELECT COUNT(*) FROM treino_checkins c WHERE c.treino_id=t.id AND c.data >= date_trunc('week', CURRENT_DATE)) AS semana, (SELECT MAX(data) FROM treino_checkins c WHERE c.treino_id=t.id) AS ultimo FROM treinos t WHERE t.empresa_id=? AND t.ativo=true ORDER BY t.atualizado_em DESC"); $q->execute([$empresaId]); $lista = $q->fetchAll(); } catch (PDOException $e) {}
$mostrarForm = $editar || isset($_GET['novo']) || !$lista;
$plano = $editar ? treino_normalizar($editar['plano']) : [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Treinos dos alunos - Meu Negócio') ?>
<style>.tr-dia{border:1px solid var(--borda,#e3e6ea);border-radius:12px;padding:12px;margin-bottom:10px}.tr-dia h4{margin:0 0 8px;display:flex;justify-content:space-between;align-items:center;gap:8px}
.tr-ex{display:grid;grid-template-columns:2fr 1fr 1.4fr 1.4fr auto;gap:6px;margin-bottom:6px}.tr-ex input{margin:0}
@media(max-width:760px){.tr-ex{grid-template-columns:1fr 1fr}.tr-ex input:first-child{grid-column:1/-1}}</style></head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Treinos dos alunos</h1><p class="subtitulo">Cada aluno recebe um link: vê o treino de hoje no celular, marca os exercícios e faz o check-in.</p></div>
        <?php if (!$mostrarForm): ?><div class="acoes"><a class="btn" href="treinos.php?novo=1">+ Novo treino</a></div><?php endif; ?></div>
    <?= flash_render() ?>
    <?php if ($mostrarForm): ?>
    <form method="post" class="form-box largo" id="trForm"><?= csrf_campo() ?><input type="hidden" name="salvar_treino" value="1"><input type="hidden" name="id" value="<?= (int)($editar['id'] ?? 0) ?>"><input type="hidden" name="plano" id="trPlano">
        <h3><?= $editar ? 'Editar treino' : 'Novo treino' ?></h3>
        <div class="form-row"><div><label for="trNome">Aluno</label><input id="trNome" name="aluno_nome" required value="<?= e($editar['aluno_nome'] ?? '') ?>"></div>
            <div><label for="trTel">WhatsApp do aluno</label><input id="trTel" name="aluno_tel" type="tel" inputmode="tel" value="<?= e($editar['aluno_tel'] ?? '') ?>"></div>
            <div><label for="trTit">Nome do treino</label><input id="trTit" name="titulo" maxlength="80" placeholder="Ex.: Hipertrofia — fase 1" value="<?= e($editar['titulo'] ?? '') ?>"></div></div>
        <?php foreach (TREINO_DIAS as $d => $nome): ?>
        <div class="tr-dia" data-dia="<?= $d ?>"><h4><?= e($nome) ?> <span><button type="button" class="btn btn-ghost btn-sm" data-copiar>Copiar do dia anterior</button> <button type="button" class="btn btn-ghost btn-sm" data-mais>+ Exercício</button></span></h4>
            <div class="tr-lista">
            <?php foreach (($plano[(string)$d] ?? []) as $x): ?>
                <div class="tr-ex"><input aria-label="Exercício" placeholder="Exercício" value="<?= e($x['nome']) ?>"><input aria-label="Séries e repetições" placeholder="3x12" value="<?= e($x['series']) ?>"><input aria-label="Observação" placeholder="Carga, descanso…" value="<?= e($x['obs']) ?>"><input aria-label="Link do vídeo" placeholder="Link do vídeo (opcional)" value="<?= e($x['video']) ?>"><button type="button" class="btn btn-ghost btn-sm" data-tirar aria-label="Tirar exercício">×</button></div>
            <?php endforeach; ?>
            </div><p class="dica tr-vazio"<?= !empty($plano[(string)$d]) ? ' hidden' : '' ?>>Descanso. Toque em "+ Exercício" para montar este dia.</p></div>
        <?php endforeach; ?>
        <button type="submit">Salvar treino</button> <a class="btn btn-ghost" href="treinos.php">Voltar</a>
    </form>
    <script>
    (function () {
      var f = document.getElementById('trForm');
      var linha = function (v) { v = v || {}; var d = document.createElement('div'); d.className = 'tr-ex';
        [['Exercício', 'Exercício', 'nome'], ['Séries e repetições', '3x12', 'series'], ['Observação', 'Carga, descanso…', 'obs'], ['Link do vídeo', 'Link do vídeo (opcional)', 'video']].forEach(function (c) { var i = document.createElement('input'); i.setAttribute('aria-label', c[0]); i.placeholder = c[1]; i.value = v[c[2]] || ''; d.appendChild(i); });
        var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-ghost btn-sm'; b.setAttribute('data-tirar', ''); b.setAttribute('aria-label', 'Tirar exercício'); b.textContent = '×'; d.appendChild(b); return d; };
      var ler = function (box) { return [].map.call(box.querySelectorAll('.tr-ex'), function (r) { var i = r.querySelectorAll('input'); return { nome: i[0].value, series: i[1].value, obs: i[2].value, video: i[3].value }; }).filter(function (x) { return x.nome.trim(); }); };
      var vazio = function (box) { box.querySelector('.tr-vazio').hidden = !!box.querySelector('.tr-ex'); };
      f.addEventListener('click', function (e) {
        var box = e.target.closest('.tr-dia'); if (!box) return;
        if (e.target.closest('[data-mais]')) { var l = linha(); box.querySelector('.tr-lista').appendChild(l); l.querySelector('input').focus(); }
        if (e.target.closest('[data-tirar]')) e.target.closest('.tr-ex').remove();
        if (e.target.closest('[data-copiar]')) { var ant = box.previousElementSibling; if (ant && ant.classList.contains('tr-dia')) { var lista = box.querySelector('.tr-lista'); lista.innerHTML = ''; ler(ant).forEach(function (x) { lista.appendChild(linha(x)); }); } }
        vazio(box);
      });
      f.addEventListener('submit', function () { var p = {}; f.querySelectorAll('.tr-dia').forEach(function (b) { var x = ler(b); if (x.length) p[b.getAttribute('data-dia')] = x; }); document.getElementById('trPlano').value = JSON.stringify(p); });
    })();
    </script>
    <?php endif; ?>
    <?php if ($lista && !$mostrarForm || (isset($_GET['ver']) && $lista)): ?>
    <div class="tabela-wrap"><table>
        <thead><tr><th>Aluno</th><th>Treino</th><th>Nesta semana</th><th></th></tr></thead><tbody>
        <?php foreach ($lista as $t): $link = treino_link($empresa, $t); ?>
        <tr<?= (int)($_GET['ver'] ?? 0) === (int)$t['id'] ? ' style="outline:2px solid var(--primaria,#4f7cff)"' : '' ?>><td><strong><?= e($t['aluno_nome']) ?></strong><?= $t['aluno_tel'] ? '<span class="mini">' . e($t['aluno_tel']) . '</span>' : '' ?></td>
            <td><?= e($t['titulo'] ?: '—') ?><span class="mini"><?= count(treino_normalizar($t['plano'])) ?> dia(s) de treino</span></td>
            <td><?= (int)$t['semana'] ?> check-in(s)<span class="mini"><?= $t['ultimo'] ? 'último: ' . date('d/m', strtotime($t['ultimo'])) : 'ainda não treinou' ?></span></td>
            <td class="acoes">
                <?php if ($t['aluno_tel']): ?><a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e(link_whatsapp($t['aluno_tel'], 'Oi, ' . explode(' ', $t['aluno_nome'])[0] . '! Seu treino da semana está aqui: ' . $link . ' — abra no celular, marque os exercícios e faça o check-in. Bom treino! 💪')) ?>">Enviar no WhatsApp</a><?php endif; ?>
                <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener" href="<?= e($link) ?>">Abrir</a>
                <a class="btn btn-ghost btn-sm" href="treinos.php?editar=<?= (int)$t['id'] ?>">Editar</a>
                <form method="post" style="display:inline"><?= csrf_campo() ?><button class="btn btn-ghost btn-sm" name="duplicar" value="<?= (int)$t['id'] ?>" title="Usar como modelo para outro aluno">Duplicar</button></form>
                <form method="post" style="display:inline"><?= csrf_campo() ?><button class="btn btn-ghost btn-sm" name="arquivar" value="<?= (int)$t['id'] ?>">Arquivar</button></form></td></tr>
        <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
</main>
</div>
</body>
</html>
