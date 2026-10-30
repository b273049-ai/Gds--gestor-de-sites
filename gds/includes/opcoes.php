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
        $itens = [];
        foreach (preg_split('/\s*[,;]\s*(?=[^\d])/u', $lista) as $it) {
            $it = trim($it);
            if ($it === '') continue;
            if (preg_match('/^(.*?)\s*\+?\s*(?:R\$\s*)?(\d+(?:[.,]\d{1,2})?)$/u', $it, $p) && trim($p[1]) !== '') $itens[] = ['nome' => mb_substr(trim($p[1]), 0, 60), 'preco' => opc_num($p[2])];
            else $itens[] = ['nome' => mb_substr($it, 0, 60), 'preco' => 0];
            if (count($itens) >= 40) break;
        }
        if ($nome === '' || !$itens) continue;
        $grupos[] = ['nome' => mb_substr($nome, 0, 60), 'min' => $obrig ? min($max, $regra === 'maior' ? 1 : 1) : 0, 'max' => min($max, count($itens)), 'regra' => $regra, 'itens' => $itens];
        if (count($grupos) >= 8) break;
    }
    return $grupos;
}

function opc_para_texto($grupos) {
    $l = [];
    foreach ((array)$grupos as $g) {
        $spec = ($g['max'] ?? 1) > 1 ? 'até ' . $g['max'] : '1';
        if (($g['regra'] ?? '') === 'maior') $spec = ($g['max'] ?? 2) . ', maior preço';
        $itens = array_map(fn($i) => $i['nome'] . ((float)$i['preco'] > 0 ? ' ' . (($g['regra'] ?? '') === 'maior' ? '' : '+') . number_format((float)$i['preco'], 2, ',', '') : ''), $g['itens']);
        $l[] = $g['nome'] . (($g['min'] ?? 0) > 0 ? '*' : '') . " ($spec): " . implode(', ', $itens);
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
        $porGrupo[$g][$i] = true;
    }
    $preco = $base; $extras = 0.0; $partes = [];
    foreach ($grupos as $gi => $g) {
        $sel = array_keys($porGrupo[$gi] ?? []);
        if (count($sel) < (int)($g['min'] ?? 0)) return ['ok' => false, 'erro' => 'Escolha: ' . $g['nome'] . ' (' . $servico['nome'] . ').'];
        if (count($sel) > (int)($g['max'] ?? 1)) $sel = array_slice($sel, 0, (int)$g['max']);
        if (!$sel) continue;
        $nomes = array_map(fn($i) => $g['itens'][$i]['nome'], $sel);
        $precos = array_map(fn($i) => (float)$g['itens'][$i]['preco'], $sel);
        if (($g['regra'] ?? 'soma') === 'maior') {
            $preco = max($preco, max($precos));
            $partes[] = (count($sel) > 1 ? implode(' / ', $nomes) : $nomes[0]);
        } else {
            $extras += array_sum($precos);
            $partes[] = implode(', ', $nomes);
        }
    }
    return ['ok' => true, 'preco' => round($preco + $extras, 2), 'nome' => $servico['nome'] . ($partes ? ' (' . implode('; ', $partes) . ')' : '')];
}
