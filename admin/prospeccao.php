<?php
// ============================================================
// CENTRAL DE PROSPECÇÃO (Admin)
// Caçada em massa (vários segmentos × regiões, em grade) · Fila do dia com
// cadência de 3 toques · prévia de site por lead com alerta de abertura ·
// concorrência · copiloto de objeções · mapa · funil por segmento.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/prospeccao.php';
exigir_login('admin');
gds_garantir_schema();

$statusNomes = ['novo' => 'Novo', 'contatado' => 'Contatado', 'respondeu' => 'Respondeu', 'negociando' => 'Negociando', 'fechado' => 'Fechado ✅', 'descartado' => 'Descartado'];
$limiteDia = max(5, (int)get_config('prosp_limite_dia', '25'));
$lead = function ($id) use ($pdo) { $st = $pdo->prepare("SELECT * FROM prospects WHERE id=?"); $st->execute([(int)$id]); return $st->fetch() ?: null; };
// Resumo da verificação de site para a tela
$verifInfo = function ($p) {
    $v = (array)json_decode((string)($p['verificacao'] ?? ''), true);
    $fontes = ['whatsapp' => 'perfil do WhatsApp', 'instagram' => 'Instagram', 'busca' => 'busca', 'dominio' => 'domínio com o nome', 'cliente' => 'o próprio cliente', 'google' => 'Google Maps'];
    $temSite = pg_bool($p['tem_site'] ?? false);
    $txt = !$p['verificado_em'] && !$temSite ? '' : ($temSite ? '⚠ Já tem site' . ($p['site'] ? ': ' . preg_replace('#^https?://(www\.)?#', '', rtrim($p['site'], '/')) : '') . ($p['site_fonte'] ? ' (achado em ' . ($fontes[$p['site_fonte']] ?? $p['site_fonte']) . ')' : '')
        : ($p['wa_existe'] !== null && !pg_bool($p['wa_existe']) ? '⚠ Este número não tem WhatsApp' : '✔ Conferido: sem site' . (isset($v['fontes']['whatsapp']) ? ' no perfil do WhatsApp (' . $v['fontes']['whatsapp'] . ')' : '') . ' e sem domínio com o nome'));
    return ['verificado' => (bool)$p['verificado_em'] || $temSite, 'tem_site' => $temSite, 'sem_whats' => $p['wa_existe'] !== null && !pg_bool($p['wa_existe']),
            'texto' => $txt, 'instagram' => $p['instagram'] ?? null];
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    @set_time_limit(150);
    $id = (int)($_POST['id'] ?? 0);
    switch ($_POST['acao']) {
        // ----- caçada -----
        case 'cacada_criar':
            session_write_close();
            $segs = [];
            $escolhidos = array_filter(array_map('trim', explode(',', (string)($_POST['segmentos'] ?? ''))));
            if ($escolhidos) {
                $in = implode(',', array_fill(0, count($escolhidos), '?'));
                $st = $pdo->prepare("SELECT slug, nome FROM segmentos WHERE slug IN ($in) AND ativo=true ORDER BY nome"); $st->execute(array_values($escolhidos));
                $segs = $st->fetchAll();
            }
            foreach (array_filter(array_map('trim', explode(',', (string)($_POST['extras'] ?? '')))) as $x) $segs[] = ['slug' => null, 'nome' => mb_substr($x, 0, 80)];
            $areas = preg_split('/\r?\n/', (string)($_POST['areas'] ?? ''));
            $fonte = in_array($_POST['fonte'] ?? '', ['auto', 'google', 'osm'], true) ? $_POST['fonte'] : 'auto';
            responder_json(prosp_criar_cacada($segs, $areas, (int)($_POST['densidade'] ?? 1), $fonte, trim($_POST['nome'] ?? '')));
        case 'cacada_passo':
            session_write_close();
            $cid = (int)($_POST['cacada_id'] ?? 0);
            $r = prosp_processar_proxima($cid);
            $r['cacada'] = prosp_cacada($cid);
            responder_json($r);
        case 'cacada_status':
            $st = in_array($_POST['status'] ?? '', ['rodando', 'pausada'], true) ? $_POST['status'] : 'pausada';
            $pdo->prepare("UPDATE prospect_cacadas SET status=? WHERE id=? AND status<>'concluida'")->execute([$st, (int)($_POST['cacada_id'] ?? 0)]);
            $pdo->prepare("UPDATE prospect_tarefas SET status='pendente' WHERE cacada_id=? AND status='rodando'")->execute([(int)($_POST['cacada_id'] ?? 0)]);
            responder_json(['ok' => true, 'cacada' => prosp_cacada((int)($_POST['cacada_id'] ?? 0))]);
        case 'cacada_excluir':
            $pdo->prepare("DELETE FROM prospect_cacadas WHERE id=?")->execute([(int)($_POST['cacada_id'] ?? 0)]);
            responder_json(['ok' => true]);

        // ----- leads -----
        case 'enviado':
            prosp_registrar_envio($id);
            responder_json(['ok' => true, 'lead' => $lead($id)]);
        case 'status':
            $st = array_key_exists($_POST['status'] ?? '', $statusNomes) ? $_POST['status'] : 'novo';
            $pdo->prepare("UPDATE prospects SET status=?, proximo_contato = CASE WHEN ? IN ('fechado','descartado','negociando') THEN NULL ELSE proximo_contato END, atualizado_em=NOW() WHERE id=?")->execute([$st, $st, $id]);
            responder_json(['ok' => true]);
        case 'adiar':
            $pdo->prepare("UPDATE prospects SET proximo_contato=CURRENT_DATE + ?, atualizado_em=NOW() WHERE id=?")->execute([max(1, min(30, (int)($_POST['dias'] ?? 1))), $id]);
            responder_json(['ok' => true]);
        case 'obs':
            $pdo->prepare("UPDATE prospects SET observacoes=?, atualizado_em=NOW() WHERE id=?")->execute([mb_substr(trim($_POST['observacoes'] ?? ''), 0, 2000), $id]);
            responder_json(['ok' => true]);
        case 'verificar':
            session_write_close();
            $p = $lead($id); if (!$p) responder_json(['ok' => false, 'erro' => 'Não encontrado.']);
            if ($p['verificado_em'] && empty($_POST['de_novo'])) responder_json(['ok' => true, 'pendente' => false, 'verif' => $verifInfo($p)]); // já conferido em segundo plano
            $r = prosp_verificar_lead($p);
            responder_json(['ok' => true, 'pendente' => $r['pendente'], 'verif' => $verifInfo($lead($id))]);
        case 'verif_estado':
            $p = $lead($id); if (!$p) responder_json(['ok' => false, 'erro' => 'Não encontrado.']);
            responder_json(['ok' => true, 'verif' => $verifInfo($p)]);
        case 'resposta':
            $p = $lead($id); $chave = (string)($_POST['chave'] ?? '');
            if (!$p || !isset(prosp_respostas_padrao()[$chave])) responder_json(['ok' => false, 'erro' => 'Resposta inválida.']);
            $texto = prosp_aplicar(prosp_resposta_modelo($chave), $p);
            prosp_registrar_resposta($p['id'], $chave);
            responder_json(['ok' => true, 'texto' => $texto, 'whatsapp' => $p['whatsapp']]);
        case 'mensagem':
            $p = $lead($id); if (!$p) responder_json(['ok' => false, 'erro' => 'Não encontrado.']);
            $passo = max(1, min(3, (int)($_POST['passo'] ?? 0) ?: (int)$p['passo'] + 1));
            responder_json(['ok' => true, 'mensagem' => prosp_mensagem($p, $passo), 'passo' => $passo, 'previa' => prosp_link_previa($p), 'concorrencia' => prosp_concorrencia($p),
                'variante' => $passo === 1 ? strtoupper(prosp_variante($p)) : '', 'verif' => $verifInfo($p)]);
        case 'mensagem_ia':
            session_write_close();
            if (!ia_configurada()) responder_json(['ok' => false, 'erro' => 'Configure a IA em Configurações.']);
            $p = $lead($id); if (!$p) responder_json(['ok' => false, 'erro' => 'Não encontrado.']);
            $conc = prosp_concorrencia($p)['texto'];
            $passoIa = (int)$p['passo'] + 1;
            $vend = trim(get_config('prosp_vendedor', ''));
            $prompt = "Escreva UMA mensagem de WhatsApp para o dono da empresa abaixo, que não conhece quem está mandando. Objetivo: que ele RESPONDA (não vender agora).\n"
                    . "Regras que fazem a pessoa responder em vez de dizer \"não, obrigado\":\n"
                    . "- No máximo 4 linhas curtas, tom de gente falando, sem cara de propaganda, sem exageros nem elogios genéricos.\n"
                    . "- Comece confirmando com quem fala (ex.: \"Oi, tudo bem? É da {nome}?\")" . ($vend ? " e se apresente: $vend, da " . (get_config('mkt_marca', '') ?: 'GDS Sites') . "." : '.') . "\n"
                    . "- NUNCA diga que a empresa não tem site (ela pode ter). Fale do que você fez: uma página de demonstração com o nome e as fotos deles.\n"
                    . ($passoIa === 1 ? "- NÃO coloque link nesta primeira mensagem. Termine pedindo permissão para mandar o link, ou com uma pergunta fácil de responder.\n"
                                     : "- Inclua o link da demonstração exatamente assim: " . prosp_link_previa($p) . "\n")
                    . "- Use no máximo um detalhe real (bairro, nota, número de avaliações" . (($el = prosp_elogio($p)) ? " ou o que os clientes mais elogiam nas avaliações: $el" : '') . ") para mostrar que não é mensagem em massa.\n"
                    . "- Português do Brasil, no máximo 1 emoji, sem inventar números ou promessas. Responda só com a mensagem.\n\n"
                    . "Empresa: {$p['nome']}\nRamo: {$p['categoria']}\nBairro/cidade: " . ($p['bairro'] ?: '-') . " / {$p['cidade']}\nNota: " . ($p['nota'] ?: 'sem nota') . " · {$p['avaliacoes']} avaliações\n"
                    . 'Site atual: ' . ($p['site'] ?: 'nenhum') . "\nQuem envia: " . get_config('mkt_marca', 'GDS Sites');
            $r = chamar_ia($prompt, 600, 40, 0.8, null, ['esforco' => 'low']);
            responder_json($r['ok'] ? ['ok' => true, 'mensagem' => trim($r['texto'], " \n\"")] : ['ok' => false, 'erro' => $r['erro']]);
        case 'copiloto':
            session_write_close();
            if (!ia_configurada()) responder_json(['ok' => false, 'erro' => 'Configure a IA em Configurações.']);
            $p = $lead($id); if (!$p) responder_json(['ok' => false, 'erro' => 'Não encontrado.']);
            $resposta = mb_substr(trim($_POST['resposta'] ?? ''), 0, 1500);
            if ($resposta === '') responder_json(['ok' => false, 'erro' => 'Cole o que o cliente respondeu.']);
            $preco = get_config('mkt_preco', '');
            $prompt = "Você é um vendedor consultivo experiente de sites para pequenos negócios. O dono da empresa abaixo respondeu a nossa abordagem pelo WhatsApp. "
                    . "Nunca afirme que ele não tem site; se ele disser que tem, elogie e ofereça comparar, sem insistir. Se ele não quiser, agradeça e encerre. "
                    . "Identifique a intenção/objeção e sugira 2 respostas curtas (até 4 linhas cada), humanas, sem pressão, que avancem para uma conversa ou demonstração. "
                    . "Use dados reais do negócio quando ajudar. Nunca invente números, resultados ou depoimentos." . ($preco ? " Preço/condição: $preco." : '') . "\n\n"
                    . "Empresa: {$p['nome']} ({$p['categoria']}, " . ($p['bairro'] ?: $p['cidade']) . "), nota " . ($p['nota'] ?: '-') . " com {$p['avaliacoes']} avaliações. "
                    . "Prévia do site: " . prosp_link_previa($p) . ($p['previa_aberta_em'] ? ' (ele já abriu a prévia)' : '') . "\n\nO CLIENTE RESPONDEU: \"$resposta\"\n\n"
                    . 'Responda APENAS JSON: {"intencao":"interessado|preco|sem_tempo|ja_tenho|nao_preciso|duvida|nao_quer","leitura":"1 frase sobre o que ele quis dizer","respostas":["...","..."],"proximo_passo":"o que fazer agora"}';
            $r = chamar_ia_json($prompt, 900, 45, 0.6, ['esforco' => 'low']);
            if (!$r['ok']) responder_json(['ok' => false, 'erro' => $r['erro']]);
            $d = $r['dados'];
            if (($d['intencao'] ?? '') === 'nao_quer') $pdo->prepare("UPDATE prospects SET proximo_contato=NULL WHERE id=?")->execute([$id]);
            elseif (in_array($d['intencao'] ?? '', ['interessado', 'preco', 'duvida'], true)) $pdo->prepare("UPDATE prospects SET status=CASE WHEN status IN ('novo','contatado') THEN 'respondeu' ELSE status END WHERE id=?")->execute([$id]);
            responder_json(['ok' => true] + $d);
        case 'excluir':
            $pdo->prepare("DELETE FROM prospects WHERE id=?")->execute([$id]);
            responder_json(['ok' => true]);
        case 'config':
            foreach ([1, '1b', 2, 3] as $n) {
                $t = prosp_normalizar_texto(mb_substr(trim($_POST['msg' . $n] ?? ''), 0, 1500));
                set_config('prosp_mensagem_' . $n, $t === prosp_normalizar_texto(prosp_mensagens_padrao()[$n]) ? '' : $t); // igual ao padrão: segue as melhorias futuras
            }
            foreach (prosp_respostas_padrao() as $k => $r) {
                $t = prosp_normalizar_texto(mb_substr(trim($_POST['resp_' . $k] ?? ''), 0, 1500));
                set_config('prosp_resposta_' . $k, $t === prosp_normalizar_texto($r['texto']) ? '' : $t);
            }
            set_config('prosp_vendedor', mb_substr(trim(strip_tags($_POST['vendedor'] ?? '')), 0, 60));
            set_config('prosp_ab', ($_POST['ab'] ?? '') === '1' ? '1' : '0');
            set_config('prosp_verificar', ($_POST['verificar'] ?? '') === '1' ? '1' : '0');
            set_config('prosp_whatsapp_alerta', preg_replace('/\D/', '', $_POST['whatsapp_alerta'] ?? ''));
            set_config('prosp_limite_dia', (string)max(5, min(200, (int)($_POST['limite_dia'] ?? 25))));
            set_config('prosp_dados_ricos', ($_POST['dados_ricos'] ?? '') === '1' ? '1' : '0');
            responder_json(['ok' => true]);
        case 'testar_alerta':
            responder_json(prosp_alertar('✅ Teste: os alertas da Prospecção estão chegando aqui.') ? ['ok' => true] : ['ok' => false, 'erro' => 'Não enviou. Confira o número e se o WhatsApp da plataforma está conectado (Admin → WhatsApp).']);
    }
    responder_json(['ok' => false, 'erro' => 'Ação inválida.'], 400);
}

// ---------- exportar ----------
if (isset($_GET['exportar'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="prospects.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Nome', 'Ramo', 'Cidade', 'Bairro', 'Endereço', 'Telefone', 'WhatsApp', 'Site', 'Nota', 'Avaliações', 'Pontuação', 'Status', 'Mensagens enviadas', 'Abriu a prévia', 'Prévia', 'Observações', 'Google Maps'], ';');
    foreach ($pdo->query("SELECT * FROM prospects WHERE NOT tem_site ORDER BY pontuacao DESC, avaliacoes DESC") as $p)
        fputcsv($out, [$p['nome'], $p['categoria'], $p['cidade'], $p['bairro'], $p['endereco'], $p['telefone'], $p['whatsapp'], $p['site'], $p['nota'], $p['avaliacoes'], $p['pontuacao'],
            $statusNomes[$p['status']] ?? $p['status'], $p['passo'], $p['previa_aberta_em'] ? date('d/m/Y H:i', strtotime($p['previa_aberta_em'])) : '', $p['token'] ? prosp_url_base() . '/previa/' . $p['token'] : '', $p['observacoes'], $p['maps_url']], ';');
    exit;
}

// ---------- dados da tela ----------
$um = fn($sql, $par = []) => (function () use ($pdo, $sql, $par) { $s = $pdo->prepare($sql); $s->execute($par); return $s->fetch(); })();
$f = $um("SELECT COUNT(*) FILTER (WHERE NOT tem_site) leads, COUNT(*) FILTER (WHERE tem_site) com_site, COUNT(*) total,
    COUNT(*) FILTER (WHERE passo > 0) contatados, COUNT(*) FILTER (WHERE previa_aberta_em IS NOT NULL) abriram, COUNT(*) FILTER (WHERE quer_site_em IS NOT NULL) querem,
    COUNT(*) FILTER (WHERE status='respondeu') responderam, COUNT(*) FILTER (WHERE status='negociando') negociando, COUNT(*) FILTER (WHERE status='fechado') fechados,
    COUNT(*) FILTER (WHERE NOT tem_site AND pontuacao >= 70 AND passo = 0 AND status='novo') quentes
    FROM prospects");
$enviadosHoje = (int)$um("SELECT COUNT(*) c FROM prospects WHERE ultimo_envio_em::date = CURRENT_DATE")['c'];
// Fila do dia: quem abriu a prévia primeiro, depois quem tem retorno marcado para hoje, depois os novos mais quentes
$fila = $pdo->query("SELECT * FROM prospects WHERE NOT tem_site AND status NOT IN ('fechado','descartado','negociando') AND COALESCE(whatsapp,'') <> ''
    AND ((passo = 0 AND status='novo') OR (passo BETWEEN 1 AND 2 AND proximo_contato <= CURRENT_DATE))
    ORDER BY (previa_aberta_em IS NOT NULL AND passo < 3) DESC, (proximo_contato <= CURRENT_DATE) DESC, (verificado_em IS NOT NULL) DESC, pontuacao DESC, avaliacoes DESC LIMIT 60")->fetchAll();
$cacadas = $pdo->query("SELECT * FROM prospect_cacadas ORDER BY id DESC LIMIT 12")->fetchAll();
$segmentos = $pdo->query("SELECT slug, nome, COALESCE(NULLIF(grupo,''),'Outros') grupo FROM segmentos WHERE ativo=true AND slug<>'outro' " . SEGMENTOS_ORDEM)->fetchAll();
$porGrupo = []; foreach ($segmentos as $s) $porGrupo[$s['grupo']][] = $s;
$funilSeg = $pdo->query("SELECT COALESCE(s.nome, p.categoria) seg, COUNT(*) FILTER (WHERE NOT p.tem_site) leads, COUNT(*) FILTER (WHERE p.tem_site) com_site,
    COUNT(*) FILTER (WHERE p.passo > 0) contatados, COUNT(*) FILTER (WHERE p.previa_aberta_em IS NOT NULL) abriram, COUNT(*) FILTER (WHERE p.status='fechado') fechados
    FROM prospects p LEFT JOIN segmentos s ON s.slug=p.segmento_slug GROUP BY 1 HAVING COUNT(*) FILTER (WHERE NOT p.tem_site) > 0 ORDER BY fechados DESC, abriram DESC, leads DESC LIMIT 25")->fetchAll();

// Leads (aba Leads)
$where = ['NOT tem_site']; $par = [];
$fStatus = array_key_exists($_GET['status'] ?? '', $statusNomes) ? $_GET['status'] : '';
if ($fStatus) { $where[] = 'status=?'; $par[] = $fStatus; } else $where[] = "status <> 'descartado'";
$fTemp = $_GET['temp'] ?? '';
if ($fTemp === 'quente') $where[] = 'pontuacao >= 70'; elseif ($fTemp === 'morno') $where[] = 'pontuacao BETWEEN 45 AND 69'; elseif ($fTemp === 'frio') $where[] = 'pontuacao < 45';
if (isset($_GET['abriu'])) $where[] = 'previa_aberta_em IS NOT NULL';
if (isset($_GET['whats'])) $where[] = "COALESCE(whatsapp,'') <> ''";
if (($fSeg = $_GET['seg'] ?? '') !== '') { $where[] = '(segmento_slug=? OR categoria=?)'; $par[] = $fSeg; $par[] = $fSeg; }
if (($fCid = trim($_GET['cidade'] ?? '')) !== '') { $where[] = 'cidade ILIKE ?'; $par[] = "%$fCid%"; }
if (($q = trim($_GET['q'] ?? '')) !== '') { $where[] = '(nome ILIKE ? OR categoria ILIKE ? OR bairro ILIKE ? OR busca ILIKE ?)'; array_push($par, "%$q%", "%$q%", "%$q%", "%$q%"); }
$st = $pdo->prepare("SELECT * FROM prospects WHERE " . implode(' AND ', $where) . " ORDER BY (previa_aberta_em IS NOT NULL) DESC, pontuacao DESC, avaliacoes DESC LIMIT 200");
$st->execute($par); $leads = $st->fetchAll();
$cidades = $pdo->query("SELECT DISTINCT cidade FROM prospects WHERE cidade<>'' ORDER BY 1 LIMIT 100")->fetchAll(PDO::FETCH_COLUMN);
$pontosMapa = $pdo->query("SELECT id, nome, categoria, lat, lng, pontuacao, whatsapp, status, (previa_aberta_em IS NOT NULL) abriu, tem_site FROM prospects WHERE lat IS NOT NULL AND status<>'descartado' ORDER BY pontuacao DESC LIMIT 2000")->fetchAll();
$temGoogle = prosp_chave_google() !== '';
$pct = fn($a, $b) => $b > 0 ? round($a / $b * 100) : 0;
$filaJson = array_map(fn($p) => ['id' => (int)$p['id'], 'nome' => $p['nome'], 'categoria' => $p['categoria'], 'bairro' => $p['bairro'] ?: $p['cidade'], 'nota' => $p['nota'], 'avaliacoes' => (int)$p['avaliacoes'],
    'whatsapp' => $p['whatsapp'], 'pontuacao' => (int)$p['pontuacao'], 'passo' => (int)$p['passo'], 'abriu' => (bool)$p['previa_aberta_em'], 'visitas' => (int)$p['previa_visitas'],
    'motivos' => (array)json_decode((string)$p['motivos'], true), 'maps' => $p['maps_url'], 'cidade' => $p['cidade'], 'verif' => $verifInfo($p)], $fila);
$ab = prosp_resultado_ab();
$aprendeu = prosp_aprendizado();
$respostas = prosp_respostas_padrao();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?= painel_head('Prospecção de clientes - GDS Admin') ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
.abas-p { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; border-bottom: 1px solid var(--border); }
.abas-p a { padding: 10px 14px; text-decoration: none; color: var(--text-dim); border-bottom: 2px solid transparent; font-weight: 600; font-size: 14px; }
.abas-p a.ativa { color: var(--text); border-color: var(--primary); }
.abas-p a b { background: var(--primary); color: #fff; border-radius: 999px; padding: 1px 7px; font-size: 11px; margin-left: 4px; }
.aba-p { display: none; } .aba-p.ativa { display: block; }
.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 18px; }
.kpis div { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; }
.kpis strong { display: block; font-size: 24px; } .kpis span { font-size: 12px; color: var(--text-dim); }
.segs { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 12px; max-height: 420px; overflow: auto; padding: 4px; }
.segs fieldset { border: 1px solid var(--border); border-radius: 12px; padding: 10px 12px; margin: 0; }
.segs legend { font-weight: 700; font-size: 13px; padding: 0 6px; }
.segs legend button { font-size: 11px; margin-left: 6px; background: none; border: 0; color: var(--primary); cursor: pointer; }
.segs label { display: flex; gap: 6px; align-items: center; font-size: 13px; margin: 4px 0; color: var(--text); }
.estimativa { font-size: 14px; padding: 10px 14px; border-radius: 10px; background: var(--info-bg); margin-top: 10px; }
.cacada { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 14px 16px; display: grid; gap: 8px; margin-bottom: 10px; }
.cacada-topo { display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; align-items: center; }
.cacada small { color: var(--text-dim); }
.sprint { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 20px; display: grid; gap: 12px; }
.sprint h2 { margin: 0; font-size: 22px; }
.sprint textarea, .prosp-msg textarea { width: 100%; min-height: 150px; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 10px; padding: 12px; font: inherit; font-size: 14px; }
.sprint-acoes { display: flex; gap: 8px; flex-wrap: wrap; }
.barra-meta { height: 10px; border-radius: 6px; background: var(--bg); overflow: hidden; } .barra-meta div { height: 100%; background: var(--gradiente-ia); }
.prosp { display: grid; gap: 12px; }
.prosp-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 16px; display: grid; grid-template-columns: 1fr auto; gap: 10px 18px; }
.prosp-card.abriu { border-color: #ff7a45; box-shadow: 0 0 0 3px rgba(255,122,69,.15); }
.prosp-card h3 { margin: 0 0 4px; font-size: 16px; }
.prosp-info { font-size: 13px; color: var(--text-dim); display: flex; flex-wrap: wrap; gap: 4px 14px; }
.chips-m { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.chips-m span { font-size: 11.5px; background: var(--surface-2); border: 1px solid var(--border); padding: 3px 8px; border-radius: 999px; color: var(--text-dim); }
.chips-m span.hot { background: rgba(255,122,69,.15); color: #ff9a6b; border-color: rgba(255,122,69,.4); }
.verif-box { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 10px 0 4px; padding: 8px 10px; border-radius: 10px; background: var(--surface-2); border: 1px solid var(--border); font-size: 13px; }
.verif-box > span { flex: 1 1 220px; }
.verif-box.verif-ok { border-color: rgba(52,199,89,.45); }
.verif-box.verif-ruim { border-color: rgba(255,90,90,.55); background: rgba(255,90,90,.08); }
.chips-resp { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.respostas-prontas { margin-top: 12px; }
.respostas-prontas summary { cursor: pointer; font-size: 13px; }
.ab-res { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin: 10px 0; }
.ab-res div { background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; padding: 12px; display: grid; gap: 2px; }
.ab-res span { font-size: 22px; font-weight: 800; }
.ab-res small { color: var(--text-dim); }
.prosp-nota { text-align: right; } .prosp-nota strong { font-size: 26px; display: block; line-height: 1; }
.temp-quente { color: #ff7a45; } .temp-morno { color: #f5b94c; } .temp-frio { color: #6cb6ff; }
.prosp-acoes { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.prosp-acoes select { width: auto; }
.prosp-msg { grid-column: 1 / -1; }
.btn-wa { background: #25d366 !important; color: #06240f !important; border-color: #25d366 !important; font-weight: 700; }
.copiloto { margin-top: 10px; display: grid; gap: 8px; }
.copiloto .sug { background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 10px 12px; white-space: pre-wrap; font-size: 13.5px; }
#mapa { height: 560px; border-radius: 14px; border: 1px solid var(--border); }
.tab-funil td, .tab-funil th { text-align: right; } .tab-funil td:first-child, .tab-funil th:first-child { text-align: left; }
@media (max-width: 760px) { .prosp-card { grid-template-columns: 1fr; } .prosp-nota { text-align: left; } #mapa { height: 420px; } }
</style>
</head>
<body>
<div class="layout">
<?php include '_sidebar.php'; ?>
<main class="content">
    <div class="page-header"><div><h1>Prospecção de clientes</h1>
        <p class="subtitulo">Cace empresas sem site em vários segmentos e regiões de uma vez, mande a prévia do site pronta e saiba na hora quem abriu.</p></div>
        <div class="acoes"><a class="btn btn-ghost" href="?exportar=1"><?= icone('download', 16) ?> Exportar planilha</a></div></div>
    <?= flash_render() ?>

    <div class="kpis">
        <div><strong><?= (int)$f['leads'] ?></strong><span>empresas sem site na base</span></div>
        <div><strong class="temp-quente"><?= (int)$f['quentes'] ?></strong><span>🔥 quentes ainda não contatadas</span></div>
        <div><strong><?= (int)$f['contatados'] ?></strong><span>contatadas</span></div>
        <div><strong class="temp-quente"><?= (int)$f['abriram'] ?></strong><span>👀 abriram a prévia (<?= $pct($f['abriram'], $f['contatados']) ?>%)</span></div>
        <div><strong><?= (int)$f['querem'] ?></strong><span>clicaram "Quero este site"</span></div>
        <div><strong><?= (int)$f['fechados'] ?></strong><span>✅ fechadas (<?= $pct($f['fechados'], $f['contatados']) ?>%)</span></div>
    </div>

    <nav class="abas-p" id="abas">
        <a href="#fila" data-aba="fila">⚡ Fila de hoje <b><?= count($fila) ?></b></a>
        <a href="#cacada" data-aba="cacada">🎯 Caçada em massa</a>
        <a href="#leads" data-aba="leads">📋 Leads</a>
        <a href="#mapa-aba" data-aba="mapa-aba">🗺 Mapa</a>
        <a href="#funil" data-aba="funil">📈 Funil por segmento</a>
        <a href="#mensagens" data-aba="mensagens">✉ Mensagens e alertas</a>
    </nav>

    <!-- ================= FILA DE HOJE ================= -->
    <section class="aba-p" id="aba-fila">
        <div class="form-box largo" style="margin-bottom:14px">
            <div class="barra-linha"><div class="rotulo"><span>Meta do dia: <strong id="feitosHoje"><?= $enviadosHoje ?></strong> de <?= $limiteDia ?> mensagens</span><span class="muted">acima disso o WhatsApp pode bloquear o número</span></div>
            <div class="barra-meta"><div id="barraMeta" style="width:<?= min(100, $pct($enviadosHoje, $limiteDia)) ?>%"></div></div></div>
            <?php if ($aprendeu['dica']): ?><p class="dica">🧠 <strong><?= e($aprendeu['dica']) ?></strong></p><?php endif; ?>
            <p class="dica">A fila mostra primeiro quem <strong>abriu a prévia</strong>, depois os retornos marcados para hoje (2º toque no dia 2, 3º no dia 5) e então os novos mais quentes. Clique em <strong>Enviar e próximo</strong>: o WhatsApp abre com a mensagem pronta e a fila anda.</p>
        </div>
        <div class="sprint" id="sprint" <?= $fila ? '' : 'hidden' ?>>
            <div class="cacada-topo"><div><span class="muted" id="spPos"></span><h2 id="spNome"></h2><div class="prosp-info" id="spInfo"></div></div><div class="prosp-nota"><strong id="spPts"></strong><span id="spTemp"></span></div></div>
            <div class="chips-m" id="spMotivos"></div>
            <div class="verif-box" id="spVerif"><span id="spVerifTxt"></span>
                <a class="btn btn-ghost btn-sm" id="spGoogle" target="_blank" rel="noopener">🔎 Conferir no Google</a>
                <a class="btn btn-ghost btn-sm" id="spInsta" target="_blank" rel="noopener">📸 Instagram</a></div>
            <p class="dica" id="spConc"></p>
            <p class="dica" id="spVariante"></p>
            <textarea id="spMsg" aria-label="Mensagem"></textarea>
            <div class="sprint-acoes">
                <button type="button" class="btn btn-wa" id="spEnviar" onclick="PR.spEnviar()"><?= icone('chat', 16) ?> Enviar e próximo</button>
                <button type="button" class="btn btn-ghost" onclick="PR.spIa()"><?= icone('brilho', 14) ?> Reescrever com IA</button>
                <a class="btn btn-ghost" id="spPrevia" target="_blank" rel="noopener">👁 Ver a prévia</a>
                <button type="button" class="btn btn-ghost" onclick="PR.spPular()">Pular</button>
                <button type="button" class="btn btn-ghost" onclick="PR.spDescartar()">Descartar</button>
            </div>
            <details class="respostas-prontas"><summary class="link">💬 Ele respondeu? Respostas prontas</summary>
                <div class="chips-resp" data-alvo="sprint"><?php foreach ($respostas as $k => $r): ?><button type="button" class="btn btn-ghost btn-sm" data-chave="<?= e($k) ?>" onclick="PR.responder(this)"><?= e($r['rotulo']) ?></button><?php endforeach; ?></div></details>
        </div>
        <div class="vazio-box" id="filaVazia" <?= $fila ? 'hidden' : '' ?>><?= icone('olho', 28) ?><p>Nada na fila agora. Rode uma <a class="link" href="#cacada" onclick="PR.aba('cacada')">caçada</a> para encher a base de empresas sem site.</p></div>
    </section>

    <!-- ================= CAÇADA ================= -->
    <section class="aba-p" id="aba-cacada">
        <?php if (!$temGoogle): ?><?= alerta('info', 'Sem a chave do Google, a caçada usa o OpenStreetMap (grátis, menos empresas e sem avaliações). Para caçar no Google Maps, cadastre a chave da "Places API (New)" em Configurações → Mapas.') ?><?php endif; ?>
        <div class="form-box largo">
            <h3>1. Quais segmentos?</h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin:6px 0 10px">
                <button type="button" class="btn btn-ghost btn-sm" onclick="PR.marcar('quentes')">🔥 Os que mais compram site</button>
                <button type="button" class="btn btn-ghost btn-sm" onclick="PR.marcar('todos')">Todos (<?= count($segmentos) ?>)</button>
                <button type="button" class="btn btn-ghost btn-sm" onclick="PR.marcar('nenhum')">Limpar</button>
            </div>
            <div class="segs" id="segs">
                <?php foreach ($porGrupo as $g => $lista): ?>
                <fieldset><legend><?= e($g) ?> <button type="button" onclick="PR.marcarGrupo(this)">todos</button></legend>
                    <?php foreach ($lista as $s): ?><label><input type="checkbox" value="<?= e($s['slug']) ?>" data-nome="<?= e($s['nome']) ?>" onchange="PR.estimar()"> <?= e($s['nome']) ?></label><?php endforeach; ?>
                </fieldset>
                <?php endforeach; ?>
            </div>
            <label for="extras">Outros termos de busca (separados por vírgula, opcional)</label>
            <input type="text" id="extras" placeholder="Ex: depilação a laser, conserto de celular, açougue" oninput="PR.estimar()">

            <h3 style="margin-top:20px">2. Onde?</h3>
            <label for="areas">Cidades ou bairros (um por linha)</label>
            <textarea id="areas" rows="4" placeholder="Moema, São Paulo - SP&#10;Pinheiros, São Paulo - SP&#10;Jundiaí - SP" oninput="PR.estimar()"></textarea>
            <div class="form-row">
                <div><label for="densidade">Varredura</label>
                    <select id="densidade" onchange="PR.estimar()">
                        <option value="1">Região inteira (até 60 por segmento)</option>
                        <option value="4" selected>Grade 2×2 (até 240 por segmento)</option>
                        <option value="9">Grade 3×3 (até 540 por segmento)</option>
                        <option value="16">Grade 4×4 (cidade grande)</option>
                    </select></div>
                <div><label for="fonte">Fonte</label>
                    <select id="fonte" onchange="PR.estimar()"><?php if ($temGoogle): ?><option value="google">Google Maps</option><?php endif; ?><option value="osm">OpenStreetMap (grátis)</option></select></div>
            </div>
            <label for="nomeCacada">Nome da caçada (opcional)</label>
            <input type="text" id="nomeCacada" placeholder="Ex: Beleza e saúde — Zona Sul">
            <div class="estimativa" id="estimativa">Escolha segmentos e regiões.</div>
            <button type="button" class="btn" style="margin-top:12px" id="btnCacar" onclick="PR.cacar()">🎯 Começar caçada</button>
            <p class="dica">A caçada roda nesta tela com barra de progresso. Se você fechar, ela continua sozinha pela rotina automática (cron), algumas buscas a cada execução. Empresas repetidas entre quadrantes e segmentos são juntadas.</p>
        </div>
        <h3>Caçadas</h3>
        <div id="listaCacadas">
        <?php foreach ($cacadas as $c): ?>
            <div class="cacada" data-id="<?= (int)$c['id'] ?>" data-status="<?= e($c['status']) ?>">
                <div class="cacada-topo"><strong><?= e($c['nome']) ?></strong>
                    <span><span class="pill <?= $c['status'] === 'concluida' ? 'pill-ok' : ($c['status'] === 'rodando' ? 'pill-aviso' : 'pill-neutro') ?> cac-status"><?= e(['rodando' => 'rodando', 'pausada' => 'pausada', 'concluida' => 'concluída'][$c['status']] ?? $c['status']) ?></span>
                    <?php if ($c['status'] !== 'concluida'): ?><button type="button" class="btn btn-ghost btn-sm" onclick="PR.alternar(this)"><?= $c['status'] === 'rodando' ? 'Pausar' : 'Continuar' ?></button><?php endif; ?>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="PR.excluirCacada(this)" title="Excluir a caçada (os leads ficam)"><?= icone('lixeira', 14) ?></button></span></div>
                <div class="progresso"><div class="cac-barra" style="width:<?= $pct($c['feitas'], $c['total']) ?>%"></div></div>
                <small class="cac-info"><?= (int)$c['feitas'] ?>/<?= (int)$c['total'] ?> buscas · <?= (int)$c['encontrados'] ?> empresas vistas · <strong><?= (int)$c['sem_site'] ?> sem site</strong> · <?= (int)$c['novos'] ?> novas · <?= e($c['fonte'] === 'google' ? 'Google Maps' : 'OpenStreetMap') ?> · <?= date('d/m H:i', strtotime($c['criado_em'])) ?></small>
            </div>
        <?php endforeach; ?>
        <?php if (!$cacadas): ?><p class="dica">Nenhuma caçada ainda.</p><?php endif; ?>
        </div>
    </section>

    <!-- ================= LEADS ================= -->
    <section class="aba-p" id="aba-leads">
        <form class="toolbar" method="get">
            <input type="hidden" name="aba" value="leads">
            <input type="search" name="q" placeholder="Nome, ramo ou bairro…" value="<?= e($q) ?>">
            <select name="seg"><option value="">Todos os segmentos</option><?php foreach ($segmentos as $s): ?><option value="<?= e($s['slug']) ?>" <?= $fSeg === $s['slug'] ? 'selected' : '' ?>><?= e($s['nome']) ?></option><?php endforeach; ?></select>
            <select name="cidade"><option value="">Todas as regiões</option><?php foreach ($cidades as $c): ?><option <?= $fCid === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
            <select name="status"><option value="">Todas as situações</option><?php foreach ($statusNomes as $k => $v): ?><option value="<?= $k ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
            <select name="temp"><option value="">Toda temperatura</option><option value="quente" <?= $fTemp === 'quente' ? 'selected' : '' ?>>🔥 Quentes</option><option value="morno" <?= $fTemp === 'morno' ? 'selected' : '' ?>>🌤 Mornos</option><option value="frio" <?= $fTemp === 'frio' ? 'selected' : '' ?>>❄ Frios</option></select>
            <label class="check"><input type="checkbox" name="abriu" <?= isset($_GET['abriu']) ? 'checked' : '' ?>> Abriu a prévia</label>
            <label class="check"><input type="checkbox" name="whats" <?= isset($_GET['whats']) ? 'checked' : '' ?>> Com WhatsApp</label>
            <button class="btn btn-ghost">Filtrar</button>
        </form>
        <p class="dica"><?= count($leads) ?> empresa(s) nesta lista<?= count($leads) >= 200 ? ' (mostrando as 200 melhores)' : '' ?>.</p>
        <div class="prosp">
        <?php foreach ($leads as $p): [$tk, $tr] = prosp_temperatura((int)$p['pontuacao'], (bool)$p['previa_aberta_em']); ?>
            <article class="prosp-card<?= $p['previa_aberta_em'] ? ' abriu' : '' ?>" data-id="<?= (int)$p['id'] ?>" data-wa="<?= e($p['whatsapp']) ?>">
                <div>
                    <h3><?= e($p['nome']) ?></h3>
                    <div class="prosp-info">
                        <span><?= e($p['categoria']) ?></span>
                        <?php if ($p['nota']): ?><span>⭐ <?= number_format((float)$p['nota'], 1, ',', '') ?> (<?= (int)$p['avaliacoes'] ?>)</span><?php endif; ?>
                        <?php if ($p['telefone']): ?><span>📞 <?= e($p['telefone']) ?></span><?php endif; ?>
                        <span>📍 <?= e(mb_strimwidth(($p['bairro'] ? $p['bairro'] . ', ' : '') . $p['cidade'], 0, 60, '…')) ?></span>
                        <?php if ($p['site']): ?><span>🔗 <a class="link" href="<?= e(preg_match('#^https?://#', $p['site']) ? $p['site'] : 'https://' . $p['site']) ?>" target="_blank" rel="noopener"><?= e(mb_strimwidth(preg_replace('#^https?://(www\.)?#', '', $p['site']), 0, 34, '…')) ?></a></span><?php endif; ?>
                    </div>
                    <div class="chips-m">
                        <?php if ($p['previa_aberta_em']): ?><span class="hot">👀 Abriu a prévia <?= (int)$p['previa_visitas'] ?>x · <?= date('d/m H:i', strtotime($p['previa_aberta_em'])) ?></span><?php endif; ?>
                        <?php if ($p['quer_site_em']): ?><span class="hot">🙋 Clicou "Quero este site"</span><?php endif; ?>
                        <?php if ($p['respondeu_em']): ?><span class="hot">💬 Respondeu</span><?php endif; ?>
                        <?php if ($p['passo'] > 0): ?><span>✉ <?= (int)$p['passo'] ?>/3 mensagens<?= $p['proximo_contato'] ? ' · próxima ' . date('d/m', strtotime($p['proximo_contato'])) : '' ?></span><?php endif; ?>
                        <?php foreach ((array)json_decode((string)$p['motivos'], true) as $m): ?><span><?= e($m) ?></span><?php endforeach; ?>
                    </div>
                </div>
                <div class="prosp-nota"><strong class="temp-<?= $tk ?>"><?= (int)$p['pontuacao'] ?></strong><span class="temp-<?= $tk ?>"><?= $tr ?></span></div>
                <details class="prosp-msg" ontoggle="if(this.open)PR.carregarMsg(this)">
                    <summary class="link" style="cursor:pointer;font-size:13px">Mensagem, prévia e copiloto</summary>
                    <p class="dica conc"></p>
                    <textarea class="msg-texto" placeholder="Carregando…"></textarea>
                    <div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap">
                        <select class="passo" onchange="PR.carregarMsg(this.closest('details'), this.value)"><option value="1">1ª mensagem (abertura)</option><option value="2">2ª (dia 2)</option><option value="3">3ª (dia 5)</option></select>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="PR.ia(this)"><?= icone('brilho', 14) ?> Escrever com IA</button>
                        <a class="btn btn-ghost btn-sm previa-link" target="_blank" rel="noopener">👁 Abrir a prévia</a>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="PR.copiarPrevia(this)">Copiar link da prévia</button>
                    </div>
                    <input type="text" class="obs" placeholder="Anotação (ex.: falar com o João à tarde)" value="<?= e($p['observacoes'] ?? '') ?>" style="margin-top:8px;width:100%" onchange="PR.obs(this)">
                    <label style="margin-top:10px">💬 Ele respondeu? Respostas prontas (abre o WhatsApp com o texto e anota a resposta)</label>
                    <div class="chips-resp"><?php foreach ($respostas as $k => $r): ?><button type="button" class="btn btn-ghost btn-sm" data-chave="<?= e($k) ?>" onclick="PR.responder(this)"><?= e($r['rotulo']) ?></button><?php endforeach; ?></div>
                    <div class="copiloto">
                        <label>🤖 Copiloto: cole aqui o que o cliente respondeu</label>
                        <textarea class="resposta-cli" rows="2" style="min-height:60px" placeholder="Ex: quanto custa? / já tenho Instagram / agora não"></textarea>
                        <button type="button" class="btn btn-sm" onclick="PR.copiloto(this)">Sugerir resposta</button>
                        <div class="copiloto-res"></div>
                    </div>
                </details>
                <div class="prosp-acoes">
                    <?php if ($p['whatsapp']): ?><button type="button" class="btn btn-sm btn-wa" onclick="PR.whats(this)"><?= icone('chat', 14) ?> Chamar no WhatsApp</button>
                    <?php elseif ($p['telefone']): ?><a class="btn btn-sm btn-ghost" href="tel:<?= e(preg_replace('/\D/', '', $p['telefone'])) ?>">📞 Ligar (fixo)</a><?php endif; ?>
                    <?php if ($p['maps_url']): ?><a class="btn btn-sm btn-ghost" href="<?= e($p['maps_url']) ?>" target="_blank" rel="noopener">Ver no Maps</a><?php endif; ?>
                    <a class="btn btn-sm btn-ghost" target="_blank" rel="noopener" href="https://www.google.com/search?q=<?= rawurlencode('"' . $p['nome'] . '" ' . ($p['cidade'] ?? '')) ?>" title="Conferir se já tem site">🔎 Google</a>
                    <a class="btn btn-sm btn-ghost" target="_blank" rel="noopener" href="<?= $p['instagram'] ? 'https://www.instagram.com/' . e($p['instagram']) . '/' : 'https://www.google.com/search?q=' . rawurlencode($p['nome'] . ' ' . ($p['cidade'] ?? '') . ' instagram') ?>">📸 Instagram</a>
                    <select onchange="PR.status(this)" aria-label="Situação"><?php foreach ($statusNomes as $k => $v): ?><option value="<?= $k ?>" <?= $p['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
                    <button type="button" class="btn btn-sm btn-ghost" title="Excluir" onclick="PR.excluir(this)"><?= icone('lixeira', 14) ?></button>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$leads): ?><div class="vazio-box"><?= icone('olho', 28) ?><p>Nenhuma empresa com esses filtros.</p></div><?php endif; ?>
        </div>
    </section>

    <!-- ================= MAPA ================= -->
    <section class="aba-p" id="aba-mapa-aba">
        <p class="dica">🔥 laranja = quente ou abriu a prévia · 🟡 morno · 🔵 frio · ⚪ já tem site (concorrente). Clique num ponto para chamar.</p>
        <div id="mapa"></div>
    </section>

    <!-- ================= FUNIL ================= -->
    <section class="aba-p" id="aba-funil">
        <div class="form-box largo">
            <h3>🧠 O que está funcionando</h3>
            <p class="dica"><?= e($aprendeu['dica'] ?: 'Marque quem respondeu (respostas prontas ou situação "Respondeu") para o sistema aprender.') ?></p>
            <?php if ($aprendeu['segmentos']): ?>
            <div class="tabela-wrap"><table><thead><tr><th>Segmento</th><th>Mensagens</th><th>Responderam</th><th>Taxa</th><th>Negociando/fechados</th></tr></thead><tbody>
            <?php foreach ($aprendeu['segmentos'] as $sg): ?><tr><td><?= e($sg['seg']) ?></td><td><?= (int)$sg['enviados'] ?></td><td><?= (int)$sg['responderam'] ?></td><td><strong><?= $pct($sg['responderam'], $sg['enviados']) ?>%</strong></td><td><?= (int)$sg['avancaram'] ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php endif; ?>
            <?php if ($aprendeu['horas']): $maxH = max(1, ...array_map(fn($h) => $h['enviados'] ? $h['responderam'] / $h['enviados'] : 0, $aprendeu['horas'])) ?>
            <h4 style="margin:14px 0 6px">Respostas por horário do envio</h4>
            <?php foreach ($aprendeu['horas'] as $h): $tx = $h['enviados'] ? $h['responderam'] / $h['enviados'] : 0; ?>
            <div class="barra-linha"><div class="rotulo"><span><?= (int)$h['faixa'] ?>h–<?= (int)$h['faixa'] + 2 ?>h</span><span><?= round($tx * 100) ?>% · <?= (int)$h['enviados'] ?> envios</span></div><div class="progresso"><div style="width:<?= $maxH > 0 ? round($tx / $maxH * 100) : 0 ?>%"></div></div></div>
            <?php endforeach; endif; ?>
        </div>
        <div class="form-box largo">
            <h3>Funil geral</h3>
            <?php foreach ([['Empresas vistas', $f['total'], $f['total']], ['Sem site (oportunidades)', $f['leads'], $f['total']], ['Contatadas', $f['contatados'], $f['leads']], ['Abriram a prévia', $f['abriram'], $f['contatados']], ['Responderam', $f['responderam'] + $f['negociando'] + $f['fechados'], $f['contatados']], ['Fechadas', $f['fechados'], $f['contatados']]] as [$rot, $v, $base]): ?>
            <div class="barra-linha"><div class="rotulo"><span><?= $rot ?></span><span><?= (int)$v ?> · <?= $pct($v, $base) ?>%</span></div><div class="progresso"><div style="width:<?= $pct($v, $base) ?>%"></div></div></div>
            <?php endforeach; ?>
        </div>
        <div class="tabela-wrap"><table class="tab-funil">
            <thead><tr><th>Segmento</th><th>Sem site</th><th>% sem site</th><th>Contatadas</th><th>Abriram a prévia</th><th>Fechadas</th></tr></thead>
            <tbody>
            <?php foreach ($funilSeg as $r): ?>
                <tr><td><strong><?= e($r['seg']) ?></strong></td><td><?= (int)$r['leads'] ?></td><td><?= $pct($r['leads'], $r['leads'] + $r['com_site']) ?>%</td><td><?= (int)$r['contatados'] ?></td>
                    <td><?= (int)$r['abriram'] ?> <span class="mini"><?= $pct($r['abriram'], $r['contatados']) ?>%</span></td><td><?= (int)$r['fechados'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$funilSeg): ?><tr class="vazio"><td colspan="6">Aparece depois da primeira caçada.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
        <p class="dica">Use esta tabela para decidir onde caçar mais: segmentos com muita empresa sem site e alta taxa de abertura da prévia são os mais quentes para você.</p>
    </section>

    <!-- ================= MENSAGENS ================= -->
    <section class="aba-p" id="aba-mensagens">
        <form class="form-box largo" onsubmit="PR.salvarConfig(event)">
            <h3>Como a abordagem funciona</h3>
            <p class="dica">O "não, obrigado" vem de mensagem longa, link de número desconhecido logo de cara e cara de vendedor. Por isso a <strong>1ª mensagem não tem link</strong>: ela se apresenta, mostra que você olhou a empresa e pede permissão (ou faz uma pergunta fácil). Quando ele responder, use <strong>"Pode mandar"</strong> nas respostas prontas e o link vai na hora. Quem não responder recebe o link no 2º toque (dia 2) e uma despedida curta no 3º (dia 5).</p>
            <div class="form-row">
                <div><label>Seu nome (aparece como "Aqui é ___, da <?= e(get_config('mkt_marca', '') ?: 'GDS Sites') ?>")</label><input type="text" name="vendedor" maxlength="60" value="<?= e(get_config('prosp_vendedor', '')) ?>" placeholder="Ex.: Lucas"></div>
                <div><label class="check" style="margin-top:28px"><input type="checkbox" name="ab" value="1" <?= get_config('prosp_ab', '1') === '1' ? 'checked' : '' ?>> Testar as duas versões da 1ª mensagem (A/B)</label></div>
            </div>
            <?php if ($ab): ?>
            <div class="ab-res">
                <?php foreach (['a' => 'Versão A (pede permissão)', 'b' => 'Versão B (pergunta)'] as $k => $rot): $x = $ab[$k] ?? ['enviados' => 0, 'responderam' => 0, 'taxa' => 0, 'avancaram' => 0]; ?>
                <div><strong><?= $rot ?></strong><span><?= $x['taxa'] ?>% responderam</span><small><?= $x['responderam'] ?> de <?= $x['enviados'] ?> · <?= $x['avancaram'] ?> negociando/fechados</small></div>
                <?php endforeach; ?>
            </div>
            <p class="dica">Marque quem respondeu (pelas respostas prontas ou mudando a situação para "Respondeu"). Com umas 30 mensagens de cada, dá para ver qual funciona melhor na sua região.</p>
            <?php endif; ?>
            <p class="dica">Variáveis: {nome} {bairro} {cidade} {nota_txt} {vendedor_txt} {acao_cliente} {o_que} {verbo} {recurso} {previa} {nota} {avaliacoes} {nicho} {marca} {vendedor_nome} {preco_txt} {elogio} {elogio_txt}. Apague o texto de um campo para voltar ao padrão.</p>
            <?php foreach ([1 => '1ª mensagem — versão A (sem link, pede permissão)', '1b' => '1ª mensagem — versão B (sem link, pergunta sobre o negócio)', 2 => '2ª mensagem — dia 2, para quem não respondeu (com o link)', 3 => '3ª mensagem — dia 5, despedida'] as $n => $rot): ?>
            <label><?= $rot ?></label><textarea name="msg<?= $n ?>" rows="5" style="width:100%"><?= e(prosp_modelo($n)) ?></textarea>
            <?php endforeach; ?>
            <details style="margin-top:12px"><summary class="link" style="cursor:pointer">Respostas prontas (o que mandar quando ele responder)</summary>
            <?php foreach ($respostas as $k => $r): ?>
            <label><?= e($r['rotulo']) ?></label><textarea name="resp_<?= e($k) ?>" rows="3" style="width:100%"><?= e(prosp_resposta_modelo($k)) ?></textarea>
            <?php endforeach; ?>
            </details>
            <h3 style="margin-top:18px">Verificação de site</h3>
            <label class="check"><input type="checkbox" name="verificar" value="1" <?= get_config('prosp_verificar', '1') === '1' ? 'checked' : '' ?>> Antes de chamar, conferir se a empresa já tem site</label>
            <p class="dica">Confere o <strong>perfil do WhatsApp</strong> da empresa (se o número tem WhatsApp, e o site/descrição que a própria empresa publicou — precisa do WhatsApp da plataforma conectado) e <strong>domínios com o nome</strong> dela (nomedaempresa.com.br / .com). Quem já tem site sai da fila sozinho. Para conferir no Google ou no Instagram, use os botões 🔎 e 📸 de cada empresa.</p>
            <h3 style="margin-top:18px">Alertas e limites</h3>
            <div class="form-row">
                <div><label>Seu WhatsApp para receber os alertas ("abriu a prévia", "quer o site")</label><input type="tel" name="whatsapp_alerta" value="<?= e(get_config('prosp_whatsapp_alerta', '') ?: get_config('mkt_whatsapp', '')) ?>" placeholder="11999999999"></div>
                <div><label>Máximo de mensagens por dia</label><input type="number" name="limite_dia" min="5" max="200" value="<?= $limiteDia ?>"></div>
            </div>
            <label class="check"><input type="checkbox" name="dados_ricos" value="1" <?= get_config('prosp_dados_ricos', '1') === '1' ? 'checked' : '' ?>> Trazer fotos e avaliações reais do Google para a prévia (cada busca custa um pouco mais na API do Google)</label>
            <p class="dica">Os alertas saem pelo WhatsApp da plataforma (Admin → WhatsApp). O botão "Quero este site" da prévia abre uma conversa com este mesmo número.</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap"><button type="submit">Salvar</button><button type="button" class="btn btn-ghost" onclick="PR.testarAlerta()">Testar alerta</button></div>
        </form>
        <p class="dica">⚠ Boas práticas: mande uma por vez, personalize, respeite o limite diário e marque como "Descartado" quem pedir para não receber. Disparo em massa automático viola as regras do WhatsApp e derruba o número.</p>
    </section>
</main>
</div>
<div id="toasts"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
const PR = (() => {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (id) => document.getElementById(id);
    const FILA = <?= json_encode($filaJson, JSON_UNESCAPED_UNICODE) ?>;
    const PONTOS = <?= json_encode(array_map(fn($m) => [(int)$m['id'], $m['nome'], $m['categoria'], (float)$m['lat'], (float)$m['lng'], (int)$m['pontuacao'], $m['whatsapp'], pg_bool($m['abriu']), pg_bool($m['tem_site'])], $pontosMapa), JSON_UNESCAPED_UNICODE) ?>;
    const QUENTES = /barbe|sal[aã]o|est[eé]tic|manicure|sobrancel|tatu|pet|veterin|dent|odonto|cl[ií]nica|fisio|psic|nutri|pilates|academia|hamburg|pizz|a[cç]a[ií]|lanch|marmit|restaur|confeit|padaria|mec[aâ]nic|lava|fot[oó]graf|dj|buffet|decora/i;
    let limite = <?= $limiteDia ?>, feitos = <?= $enviadosHoje ?>, idx = 0, rodando = {};
    async function api(dados) {
        const fd = new FormData(); fd.append('csrf_token', CSRF); Object.entries(dados).forEach(([k, v]) => fd.append(k, v));
        try { return await (await fetch('prospeccao.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })).json(); }
        catch (e) { return { ok: false, erro: 'Falha de conexão.' }; }
    }
    function toast(msg, tipo) { const t = document.createElement('div'); t.className = 'toast toast-' + (tipo || 'info'); t.textContent = msg; $('toasts').appendChild(t); setTimeout(() => t.remove(), 5000); }
    const card = (el) => el.closest('.prosp-card');
    const abrirWa = (num, txt) => window.open('https://wa.me/' + num + '?text=' + encodeURIComponent(txt), '_blank', 'noopener');

    // ---------- abas ----------
    let mapaPronto = false;
    function aba(nome) {
        document.querySelectorAll('.abas-p a').forEach(a => a.classList.toggle('ativa', a.dataset.aba === nome));
        document.querySelectorAll('.aba-p').forEach(s => s.classList.toggle('ativa', s.id === 'aba-' + nome));
        if (nome === 'mapa-aba' && !mapaPronto) { mapaPronto = true; setTimeout(montarMapa, 50); }
    }
    document.querySelectorAll('.abas-p a').forEach(a => a.addEventListener('click', e => { e.preventDefault(); history.replaceState(null, '', '#' + a.dataset.aba); aba(a.dataset.aba); }));
    const pedida = new URLSearchParams(location.search).get('aba');
    aba(pedida || (location.hash.slice(1) || (FILA.length ? 'fila' : 'cacada')));

    // ---------- fila (modo sprint) ----------
    async function mostrarFila() {
        if (idx >= FILA.length) { $('sprint').hidden = true; $('filaVazia').hidden = false; return; }
        const p = FILA[idx];
        $('spPos').textContent = (idx + 1) + ' de ' + FILA.length + ' na fila · ' + (p.passo + 1) + 'ª mensagem';
        $('spNome').textContent = p.nome;
        $('spInfo').textContent = [p.categoria, p.bairro, p.nota ? '⭐ ' + String(p.nota).replace('.', ',') + ' (' + p.avaliacoes + ')' : ''].filter(Boolean).join(' · ');
        $('spPts').textContent = p.pontuacao; $('spPts').className = p.abriu || p.pontuacao >= 70 ? 'temp-quente' : (p.pontuacao >= 45 ? 'temp-morno' : 'temp-frio');
        $('spTemp').textContent = p.abriu ? '👀 Abriu a prévia ' + p.visitas + 'x' : (p.pontuacao >= 70 ? '🔥 Quente' : p.pontuacao >= 45 ? '🌤 Morno' : '❄ Frio');
        $('spMotivos').innerHTML = ''; p.motivos.forEach(m => { const s = document.createElement('span'); s.textContent = m; $('spMotivos').appendChild(s); });
        $('spMsg').value = 'Carregando a mensagem…'; $('spConc').textContent = ''; $('spVariante').textContent = '';
        $('spGoogle').href = 'https://www.google.com/search?q=' + encodeURIComponent('"' + p.nome + '" ' + (p.cidade || p.bairro || ''));
        $('spInsta').href = p.verif.instagram ? 'https://www.instagram.com/' + p.verif.instagram + '/' : 'https://www.google.com/search?q=' + encodeURIComponent(p.nome + ' ' + (p.cidade || '') + ' instagram');
        mostrarVerif(p);
        const r = await api({ acao: 'mensagem', id: p.id, passo: p.passo + 1 });
        if (r.ok && FILA[idx] === p) {
            $('spMsg').value = r.mensagem; $('spPrevia').href = r.previa; $('spConc').textContent = r.concorrencia.texto ? '📊 ' + r.concorrencia.texto : '';
            $('spVariante').textContent = r.variante ? '🧪 Versão ' + r.variante + ' da 1ª mensagem (teste A/B) · sem link: quando ele responder, use "Pode mandar"' : '';
        }
        if (!p.verif.verificado) verificarAtual(p);
    }
    function mostrarVerif(p) {
        const v = p.verif, box = $('spVerif');
        box.className = 'verif-box' + (v.tem_site || v.sem_whats ? ' verif-ruim' : v.verificado ? ' verif-ok' : '');
        $('spVerifTxt').textContent = v.texto || (v.verificado ? '' : '🔎 Conferindo se já tem site…');
    }
    async function verificarAtual(p) {
        let r = await api({ acao: 'verificar', id: p.id });
        for (let i = 0; r.ok && r.pendente && !r.verif.verificado && i < 15 && FILA[idx] === p; i++) {
            await new Promise(ok => setTimeout(ok, 4000));
            r = await api({ acao: 'verif_estado', id: p.id });
        }
        if (!r.ok) return;
        p.verif = r.verif;
        if (FILA[idx] !== p) return;
        if (!p.verif.verificado) { $('spVerifTxt').textContent = 'Ainda conferindo no conector… use 🔎 para olhar no Google.'; return; }
        mostrarVerif(p);
        if (p.verif.tem_site || p.verif.sem_whats) { toast(p.verif.texto + ' — pulei para o próximo.', 'erro'); setTimeout(() => { if (FILA[idx] === p) { idx++; mostrarFila(); } }, 2500); }
    }
    async function responder(btn) {
        const alvo = btn.closest('.chips-resp');
        const id = alvo.dataset.alvo === 'sprint' ? (FILA[idx] || {}).id : card(btn).dataset.id;
        if (!id) return;
        const r = await api({ acao: 'resposta', id, chave: btn.dataset.chave });
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        if (r.whatsapp) abrirWa(r.whatsapp, r.texto); else { try { await navigator.clipboard.writeText(r.texto); } catch (e) {} toast('Texto copiado.', 'sucesso'); }
        toast('Anotado: ele respondeu.', 'sucesso');
    }
    async function spEnviar() {
        const p = FILA[idx]; if (!p) return;
        if (feitos >= limite && !$('spEnviar').dataset.ok) { $('spEnviar').dataset.ok = '1'; toast('Você chegou na meta de ' + limite + ' mensagens hoje. Clique de novo se quiser mesmo continuar.', 'erro'); return; }
        abrirWa(p.whatsapp, $('spMsg').value);
        await api({ acao: 'enviado', id: p.id });
        feitos++; $('feitosHoje').textContent = feitos; $('barraMeta').style.width = Math.min(100, feitos / limite * 100) + '%';
        idx++; mostrarFila();
    }
    function spPular() { idx++; mostrarFila(); }
    async function spDescartar() { const p = FILA[idx]; await api({ acao: 'status', id: p.id, status: 'descartado' }); idx++; mostrarFila(); }
    async function spIa() { const p = FILA[idx]; $('spMsg').value = 'Escrevendo…'; const r = await api({ acao: 'mensagem_ia', id: p.id }); $('spMsg').value = r.ok ? r.mensagem : ''; if (!r.ok) toast(r.erro, 'erro'); }
    if (FILA.length) mostrarFila();

    // ---------- caçada ----------
    function segsMarcados() { return [...document.querySelectorAll('#segs input:checked')]; }
    function marcar(modo) {
        document.querySelectorAll('#segs input').forEach(c => c.checked = modo === 'todos' || (modo === 'quentes' && QUENTES.test(c.dataset.nome + ' ' + c.value)));
        estimar();
    }
    function marcarGrupo(b) { const cs = b.closest('fieldset').querySelectorAll('input'); const todos = [...cs].every(c => c.checked); cs.forEach(c => c.checked = !todos); estimar(); }
    function estimar() {
        const ns = segsMarcados().length + $('extras').value.split(',').filter(x => x.trim()).length;
        const na = $('areas').value.split('\n').filter(x => x.trim()).length;
        const google = $('fonte').value === 'google';
        const d = google ? +$('densidade').value : 1;
        const buscas = ns * na * d;
        $('estimativa').innerHTML = !ns || !na ? 'Escolha segmentos e regiões.' :
            `<strong>${ns} segmento(s) × ${na} região(ões)${google && d > 1 ? ' × ' + d + ' quadrantes' : ''} = ${buscas} busca(s)</strong> · até ${(buscas * (google ? 60 : 150)).toLocaleString('pt-BR')} empresas vistas`
            + (google ? ` · ${buscas}–${buscas * 3} chamadas à API do Google (confira sua cota grátis)` : '') + (buscas > 1500 ? ' · ⚠ acima do máximo de 1.500 por caçada' : '');
    }
    async function cacar() {
        const segs = segsMarcados().map(c => c.value);
        const b = $('btnCacar'); b.disabled = true; b.textContent = 'Preparando a caçada…';
        const r = await api({ acao: 'cacada_criar', segmentos: segs.join(','), extras: $('extras').value, areas: $('areas').value, densidade: $('densidade').value, fonte: $('fonte').value, nome: $('nomeCacada').value });
        b.disabled = false; b.textContent = '🎯 Começar caçada';
        if (!r.ok) { toast(r.erro, 'erro'); return; }
        if (r.faltou && r.faltou.length) toast('Não achei no mapa: ' + r.faltou.join(', '), 'erro');
        const div = document.createElement('div'); div.className = 'cacada'; div.dataset.id = r.id; div.dataset.status = 'rodando';
        div.innerHTML = '<div class="cacada-topo"><strong></strong><span><span class="pill pill-aviso cac-status">rodando</span> <button type="button" class="btn btn-ghost btn-sm">Pausar</button></span></div><div class="progresso"><div class="cac-barra" style="width:0"></div></div><small class="cac-info">0/' + r.total + ' buscas</small>';
        div.querySelector('strong').textContent = $('nomeCacada').value || 'Nova caçada';
        div.querySelector('button').onclick = function () { alternar(this); };
        $('listaCacadas').prepend(div);
        toast('Caçada começou: ' + r.total + ' buscas.', 'sucesso');
        rodar(r.id);
    }
    function atualizarCacada(c) {
        const div = document.querySelector('.cacada[data-id="' + c.id + '"]'); if (!div) return;
        div.dataset.status = c.status;
        div.querySelector('.cac-barra').style.width = (c.total ? Math.round(c.feitas / c.total * 100) : 0) + '%';
        div.querySelector('.cac-info').innerHTML = `${c.feitas}/${c.total} buscas · ${c.encontrados} empresas vistas · <strong>${c.sem_site} sem site</strong> · ${c.novos} novas`;
        const st = div.querySelector('.cac-status'); st.textContent = { rodando: 'rodando', pausada: 'pausada', concluida: 'concluída' }[c.status] || c.status;
        st.className = 'pill cac-status ' + (c.status === 'concluida' ? 'pill-ok' : c.status === 'rodando' ? 'pill-aviso' : 'pill-neutro');
        const b = div.querySelector('.cacada-topo button:not([title])'); if (b) { if (c.status === 'concluida') b.remove(); else b.textContent = c.status === 'rodando' ? 'Pausar' : 'Continuar'; }
    }
    async function rodar(id) {
        if (rodando[id]) return; rodando[id] = true;
        while (rodando[id]) {
            const r = await api({ acao: 'cacada_passo', cacada_id: id });
            if (!r.ok) { toast(r.erro || 'Erro na caçada.', 'erro'); break; }
            if (r.cacada) atualizarCacada(r.cacada);
            if (r.erro_tarefa) toast(r.tarefa + ': ' + r.erro_tarefa, 'erro');
            if (r.fim || !r.cacada || r.cacada.status !== 'rodando') break;
        }
        rodando[id] = false;
        const c = document.querySelector('.cacada[data-id="' + id + '"]');
        if (c && c.dataset.status === 'concluida') toast('Caçada concluída! Veja a Fila de hoje.', 'sucesso');
    }
    async function alternar(b) {
        const div = b.closest('.cacada'), novo = div.dataset.status === 'rodando' ? 'pausada' : 'rodando';
        if (novo === 'pausada') rodando[div.dataset.id] = false;
        const r = await api({ acao: 'cacada_status', cacada_id: div.dataset.id, status: novo });
        if (r.ok && r.cacada) { atualizarCacada(r.cacada); if (novo === 'rodando') rodar(div.dataset.id); }
    }
    async function excluirCacada(b) { const div = b.closest('.cacada'); rodando[div.dataset.id] = false; const r = await api({ acao: 'cacada_excluir', cacada_id: div.dataset.id }); if (r.ok) div.remove(); }
    document.querySelectorAll('.cacada[data-status="rodando"]').forEach(d => rodar(d.dataset.id)); // retoma as que estavam rodando
    estimar();

    // ---------- leads ----------
    async function carregarMsg(det, passo) {
        const c = card(det); const r = await api({ acao: 'mensagem', id: c.dataset.id, passo: passo || '' });
        if (!r.ok) return;
        det.querySelector('.msg-texto').value = r.mensagem; det.querySelector('.passo').value = r.passo;
        det.querySelector('.previa-link').href = r.previa; det.querySelector('.previa-link').dataset.url = r.previa;
        det.querySelector('.conc').textContent = r.concorrencia.texto ? '📊 ' + r.concorrencia.texto : '';
    }
    async function whats(btn) {
        const c = card(btn), det = c.querySelector('details');
        if (!det.querySelector('.msg-texto').value) await carregarMsg(det);
        abrirWa(c.dataset.wa, det.querySelector('.msg-texto').value);
        await api({ acao: 'enviado', id: c.dataset.id });
        toast('Registrado. O próximo toque já foi agendado.', 'sucesso');
    }
    async function ia(btn) { const c = card(btn), t = c.querySelector('.msg-texto'); t.value = 'Escrevendo…'; const r = await api({ acao: 'mensagem_ia', id: c.dataset.id }); t.value = r.ok ? r.mensagem : ''; if (!r.ok) toast(r.erro, 'erro'); }
    async function copiarPrevia(btn) { const a = card(btn).querySelector('.previa-link'); try { await navigator.clipboard.writeText(a.dataset.url || a.href); toast('Link copiado!', 'sucesso'); } catch (e) { prompt('Link da prévia:', a.href); } }
    async function copiloto(btn) {
        const c = card(btn), box = c.querySelector('.copiloto-res'); box.textContent = 'Pensando…';
        const r = await api({ acao: 'copiloto', id: c.dataset.id, resposta: c.querySelector('.resposta-cli').value });
        if (!r.ok) { box.textContent = r.erro; return; }
        box.innerHTML = '';
        const p = document.createElement('p'); p.className = 'dica'; p.textContent = '🧭 ' + (r.leitura || '') + (r.proximo_passo ? ' Próximo passo: ' + r.proximo_passo : ''); box.appendChild(p);
        (r.respostas || []).forEach(t => { const d = document.createElement('div'); d.className = 'sug'; d.textContent = t;
            const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm btn-wa'; b.style.marginTop = '6px'; b.textContent = 'Mandar esta';
            b.onclick = () => abrirWa(c.dataset.wa, t); const w = document.createElement('div'); w.append(d, b); box.appendChild(w); });
    }
    async function status(sel) { const r = await api({ acao: 'status', id: card(sel).dataset.id, status: sel.value }); if (r.ok) toast('Situação salva.', 'sucesso'); }
    async function obs(inp) { const r = await api({ acao: 'obs', id: card(inp).dataset.id, observacoes: inp.value }); if (r.ok) toast('Anotação salva.', 'sucesso'); }
    async function excluir(btn) { const c = card(btn); const r = await api({ acao: 'excluir', id: c.dataset.id }); if (r.ok) c.remove(); }

    // ---------- mapa ----------
    function montarMapa() {
        if (!window.L) { $('mapa').textContent = 'Não foi possível carregar o mapa.'; return; }
        const m = L.map('mapa'); L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(m);
        const cores = (p) => p[8] ? '#9aa0b5' : (p[7] || p[5] >= 70 ? '#ff7a45' : p[5] >= 45 ? '#f5b94c' : '#6cb6ff');
        const pts = [];
        PONTOS.forEach(p => {
            const mk = L.circleMarker([p[3], p[4]], { radius: p[8] ? 5 : 8, color: cores(p), fillColor: cores(p), fillOpacity: .8, weight: 1 }).addTo(m);
            const div = document.createElement('div'); const s = document.createElement('strong'); s.textContent = p[1]; div.append(s, document.createElement('br'), document.createTextNode(p[2] + ' · ' + (p[8] ? 'já tem site' : p[5] + ' pts')));
            if (p[6] && !p[8]) { const a = document.createElement('a'); a.href = 'https://wa.me/' + p[6]; a.target = '_blank'; a.textContent = ' · WhatsApp'; div.appendChild(a); }
            mk.bindPopup(div); pts.push([p[3], p[4]]);
        });
        if (pts.length) m.fitBounds(pts, { padding: [30, 30] }); else m.setView([-14.2, -51.9], 4);
    }

    // ---------- configurações ----------
    async function salvarConfig(ev) {
        ev.preventDefault(); const fd = Object.fromEntries(new FormData(ev.target));
        const r = await api(Object.assign({ acao: 'config' }, fd, { dados_ricos: ev.target.dados_ricos.checked ? '1' : '0' }));
        if (r.ok) { limite = +fd.limite_dia || limite; toast('Salvo!', 'sucesso'); }
    }
    async function testarAlerta() { const r = await api({ acao: 'testar_alerta' }); toast(r.ok ? 'Alerta enviado! Confira o WhatsApp.' : r.erro, r.ok ? 'sucesso' : 'erro'); }
    return { responder, aba, spEnviar, spPular, spDescartar, spIa, marcar, marcarGrupo, estimar, cacar, alternar, excluirCacada, carregarMsg, whats, ia, copiarPrevia, copiloto, status, obs, excluir, salvarConfig, testarAlerta };
})();
</script>
</body>
</html>
