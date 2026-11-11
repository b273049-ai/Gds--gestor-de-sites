<?php
// ============================================================
// OPÇÕES DOS ITENS (adicionais, ponto da carne, meio a meio, tamanho, cor...)
// Guardadas em servicos.opcoes como JSON:
//   [{"nome":"Adicionais","min":0,"max":3,"regra":"soma","itens":[{"nome":"Bacon","preco":4}]}]
// regra "soma"  → o preço das opções escolhidas soma ao preço do item
// regra "maior" → o item passa a valer o maior preço escolhido (pizza meio a meio)
// No painel, a empresa escreve em texto simples (uma linha por grupo):
//   Ponto da carne* (1): Mal passado, Ao ponto, Bem passado
//   Adicionais (até 3): Bacon +4, Cheddar +3,50, Ovo +2
//   Sabores* (2, maior preço): Calabresa 45, Marguerita 42, Portuguesa 48
//   Adicionais (até 5, qtd): Bacon +4, Cheddar +3   → o cliente escolhe a quantidade de cada (2x bacon), como no iFood
//   Molhos* (mín 2, até 3): Barbecue, Maionese verde, Cheddar   → "mín" exige uma quantidade mínima
// ============================================================

function opc_ler($servico) {
    $g = json_decode((string)($servico['opcoes'] ?? ''), true);
    return is_array($g) ? array_values(array_filter($g, fn($x) => !empty($x['itens']))) : [];
}

function opc_num($t) {
    $t = trim(str_replace(['R$', ' '], '', (string)$t));
    if ($t === '') return 0.0;
    if (str_contains($t, ',')) $t = str_replace(',', '.', str_replace('.', '', $t));
    return round((float)$t, 2);
}

function opc_de_texto($texto) {
    $grupos = [];
    foreach (preg_split('/\r?\n/', (string)$texto) as $linha) {
        $linha = trim($linha);
        if ($linha === '' || !str_contains($linha, ':')) continue;
        [$cab, $lista] = array_map('trim', explode(':', $linha, 2));
        $obrig = str_contains($cab, '*');
        $spec = preg_match('/\(([^)]*)\)/u', $cab, $m) ? mb_strtolower($m[1]) : '';
        $nome = trim(preg_replace('/\([^)]*\)|\*/u', '', $cab));
        $max = preg_match('/(\d+)/', $spec, $n) ? max(1, (int)$n[1]) : 1;
        $regra = str_contains($spec, 'maior') ? 'maior' : 'soma';
        $qtd = $regra === 'soma' && preg_match('/\bqtd|quant/u', $spec);
        $min = preg_match('/m[ií]n\.?\s*(\d+)/u', $spec, $mn) ? max(1, (int)$mn[1]) : 0;
        if ($min && preg_match_all('/(\d+)/', $spec, $todos) && count($todos[1]) > 1) $max = max(array_map('intval', $todos[1]));
        $itens = [];
        foreach (preg_split('/\s*[,;]\s*(?=[^\d])/u', $lista) as $it) {
            $it = trim($it);
            if ($it === '') continue;
            if (preg_match('/^(.*?)\s*\+?\s*(?:R\$\s*)?(\d+(?:[.,]\d{1,2})?)$/u', $it, $p) && trim($p[1]) !== '') $itens[] = ['nome' => mb_substr(trim($p[1]), 0, 60), 'preco' => opc_num($p[2])];
            else $itens[] = ['nome' => mb_substr($it, 0, 60), 'preco' => 0];
            if (count($itens) >= 40) break;
        }
        if ($nome === '' || !$itens) continue;
        $maxFinal = $qtd ? $max : min($max, count($itens));
        $g = ['nome' => mb_substr($nome, 0, 60), 'min' => $min ? min($min, $maxFinal) : ($obrig ? 1 : 0), 'max' => max(1, $maxFinal), 'regra' => $regra, 'itens' => $itens];
        if ($qtd) $g['qtd'] = true;
        $grupos[] = $g;
        if (count($grupos) >= 8) break;
    }
    return $grupos;
}

function opc_para_texto($grupos) {
    $l = [];
    foreach ((array)$grupos as $g) {
        $spec = ($g['max'] ?? 1) > 1 ? 'até ' . $g['max'] : '1';
        if (($g['min'] ?? 0) > 1) $spec = 'mín ' . $g['min'] . ', ' . $spec;
        if (!empty($g['qtd'])) $spec .= ', qtd';
        if (($g['regra'] ?? '') === 'maior') $spec = ($g['max'] ?? 2) . ', maior preço';
        $itens = array_map(fn($i) => $i['nome'] . ((float)$i['preco'] > 0 ? ' ' . (($g['regra'] ?? '') === 'maior' ? '' : '+') . number_format((float)$i['preco'], 2, ',', '') : ''), $g['itens']);
        $l[] = $g['nome'] . (($g['min'] ?? 0) > 0 && ($g['min'] ?? 0) <= 1 ? '*' : '') . " ($spec): " . implode(', ', $itens);
    }
    return implode("\n", $l);
}

// Preço e nome do item com as opções escolhidas. $escolhas = [[grupo, item], ...]
// Retorna ['ok', 'preco', 'nome', 'erro']
function opc_precificar($servico, $escolhas) {
    $grupos = opc_ler($servico);
    $base = (float)$servico['preco'];
    if (!$grupos) return ['ok' => true, 'preco' => $base, 'nome' => $servico['nome']];
    $porGrupo = [];
    foreach ((array)$escolhas as $e) {
        $g = (int)($e[0] ?? -1); $i = (int)($e[1] ?? -1);
        if (!isset($grupos[$g]['itens'][$i])) continue;
        if (!empty($grupos[$g]['itens'][$i]['off'])) return ['ok' => false, 'erro' => $grupos[$g]['itens'][$i]['nome'] . ' acabou. Escolha outra opção em ' . $grupos[$g]['nome'] . '.'];
        $porGrupo[$g][$i] = !empty($grupos[$g]['qtd']) ? ($porGrupo[$g][$i] ?? 0) + 1 : 1; // grupo com quantidade: [g,i] repetido = 2x
    }
    $preco = $base; $extras = 0.0; $partes = [];
    foreach ($grupos as $gi => $g) {
        if (!empty($g['qtd'])) {
            $cont = $porGrupo[$gi] ?? []; $total = array_sum($cont);
            if ($total < (int)($g['min'] ?? 0)) return ['ok' => false, 'erro' => 'Escolha: ' . $g['nome'] . ' (' . $servico['nome'] . ').'];
            if ($total > (int)$g['max']) return ['ok' => false, 'erro' => 'No máximo ' . (int)$g['max'] . ' em ' . $g['nome'] . '.'];
            if (!$cont) continue;
            $nomes = [];
            foreach ($cont as $i => $n) { $extras += (float)$g['itens'][$i]['preco'] * $n; $nomes[] = ($n > 1 ? $n . 'x ' : '') . $g['itens'][$i]['nome']; }
            $partes[] = $g['nome'] . ': ' . implode(', ', $nomes);
            continue;
        }
        $sel = array_keys($porGrupo[$gi] ?? []);
        if (count($sel) < (int)($g['min'] ?? 0)) return ['ok' => false, 'erro' => 'Escolha: ' . $g['nome'] . ' (' . $servico['nome'] . ').'];
        if (count($sel) > (int)($g['max'] ?? 1)) $sel = array_slice($sel, 0, (int)$g['max']);
        if (!$sel) continue;
        $nomes = array_map(fn($i) => $g['itens'][$i]['nome'], $sel);
        $precos = array_map(fn($i) => (float)$g['itens'][$i]['preco'], $sel);
        if (($g['regra'] ?? 'soma') === 'maior') {
            $preco = max($preco, max($precos));
            $partes[] = (count($sel) > 1 ? implode(' / ', $nomes) : $nomes[0]); // sabores: "Calabresa / Portuguesa"
        } else {
            $extras += array_sum($precos);
            $partes[] = $g['nome'] . ': ' . implode(', ', $nomes); // "Retirar: Cebola" — a cozinha entende sem adivinhar
        }
    }
    return ['ok' => true, 'preco' => round($preco + $extras, 2), 'nome' => $servico['nome'] . ($partes ? ' (' . implode('; ', $partes) . ')' : '')];
}

// Opções montadas no editor visual do painel (botões) → mesma estrutura, validada
function opc_normalizar($grupos) {
    $out = [];
    foreach (array_slice((array)$grupos, 0, 12) as $g) {
        if (!is_array($g)) continue;
        $nome = mb_substr(trim(strip_tags((string)($g['nome'] ?? ''))), 0, 60);
        $itens = [];
        foreach (array_slice((array)($g['itens'] ?? []), 0, 40) as $i) {
            $n = mb_substr(trim(strip_tags((string)($i['nome'] ?? ''))), 0, 60);
            if ($n === '') continue;
            $novoIt = ['nome' => $n, 'preco' => max(0, round(is_numeric($i['preco'] ?? null) ? (float)$i['preco'] : opc_num($i['preco'] ?? 0), 2))];
            if (!empty($i['off'])) $novoIt['off'] = true; // "acabou o bacon": opção esgotada
            $itens[] = $novoIt;
        }
        if ($nome === '' || !$itens) continue;
        $regra = ($g['regra'] ?? '') === 'maior' ? 'maior' : 'soma';
        $qtd = $regra === 'soma' && !empty($g['qtd']);
        $max = max(1, min($qtd ? 50 : count($itens), (int)($g['max'] ?? 1)));
        $min = max(0, min($max, (int)($g['min'] ?? 0)));
        $novo = ['nome' => $nome, 'min' => $min, 'max' => $max, 'regra' => $regra, 'itens' => $itens];
        if ($qtd) $novo['qtd'] = true;
        $out[] = $novo;
    }
    return $out;
}
