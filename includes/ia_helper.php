<?php
// ============================================================
// FUNÇÃO CENTRAL DE CHAMADA À IA
// Usada por todos os pontos do sistema que precisam gerar texto.
// Decide sozinha qual provedor usar com base no que foi salvo em
// Admin > Configurações (tabela "configuracoes").
//
// $opcoes aceita:
//   'sistema' => instrução de sistema (papel/regras fixas da IA)
//   'esforco' => 'low' | 'medium' | 'high' (só Anthropic — quanto a IA "pensa")
// ============================================================

const GDS_MODELO_GEMINI_PADRAO = 'gemini-3.5-flash';
const GDS_MODELO_ANTHROPIC_PADRAO = 'claude-opus-5-5';

// Serviços gratuitos (ou com plano grátis) que usam o formato "compatível com OpenAI".
// Os limites grátis mudam com frequência — confira no site de cada um.
function ia_presets_openai() {
    return [
        'groq'       => ['nome' => 'Groq (grátis, muito rápido)', 'url' => 'https://api.groq.com/openai/v1', 'modelo' => 'openai/gpt-oss-120b', 'chave' => 'console.groq.com → API Keys'],
        'openrouter' => ['nome' => 'OpenRouter (modelos ":free")', 'url' => 'https://openrouter.ai/api/v1', 'modelo' => '', 'chave' => 'openrouter.ai → Keys (use um modelo terminado em ":free")'],
        'mistral'    => ['nome' => 'Mistral (plano grátis)', 'url' => 'https://api.mistral.ai/v1', 'modelo' => 'mistral-small-latest', 'chave' => 'console.mistral.ai → API Keys'],
        'outro'      => ['nome' => 'Outro (compatível com OpenAI)', 'url' => '', 'modelo' => '', 'chave' => 'documentação do serviço'],
    ];
}

// Endereços das APIs (dá pra trocar por variável de ambiente, ex.: para usar um proxy/gateway)
function ia_url_base($provedor) {
    if ($provedor === 'openai') return rtrim(get_config('ia_openai_url', 'https://api.groq.com/openai/v1'), '/');
    $env = getenv($provedor === 'anthropic' ? 'GDS_ANTHROPIC_BASE_URL' : 'GDS_GEMINI_BASE_URL');
    if ($env) return rtrim($env, '/');
    return $provedor === 'anthropic' ? 'https://api.anthropic.com' : 'https://generativelanguage.googleapis.com';
}

// Cada provedor guarda a própria chave (antes era uma só, e trocar de provedor exigia colar de novo)
function ia_chave($provedor) {
    $k = trim(get_config('ia_api_key_' . $provedor, ''));
    if ($k === '' && $provedor === get_config('ia_provider', 'gemini')) $k = trim(get_config('ia_api_key', ''));
    return $k;
}

function ia_nome_provedor($provedor) {
    return ['gemini' => 'Google Gemini', 'anthropic' => 'Anthropic Claude', 'openai' => 'IA compatível com OpenAI'][$provedor] ?? $provedor;
}

function ia_configurada() {
    return ia_chave(get_config('ia_provider', 'gemini')) !== '';
}

function chamar_ia($prompt, $maxTokens = 800, $timeoutSegundos = 30, $temperatura = null, $modelo = null, $opcoes = []) {
    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'erro' => 'A extensão cURL do PHP não está habilitada nesta hospedagem. No painel da hospedagem, vá em Configuração PHP e ative a extensão "curl".',
        ];
    }

    $principal = get_config('ia_provider', 'gemini');
    $r = chamar_ia_provedor($principal, $prompt, $maxTokens, $timeoutSegundos, $temperatura, $modelo, $opcoes);

    // Deu limite/indisponibilidade? Tenta a IA reserva (se configurada) antes de mostrar erro
    $reserva = get_config('ia_provider_reserva', '');
    if (!$r['ok'] && !empty($r['temporario']) && $reserva !== '' && $reserva !== $principal && ia_chave($reserva) !== '') {
        $r2 = chamar_ia_provedor($reserva, $prompt, $maxTokens, $timeoutSegundos, $temperatura, null, $opcoes);
        if ($r2['ok']) return $r2 + ['usou_reserva' => true];
        $r['erro'] .= ' A IA reserva (' . ia_nome_provedor($reserva) . ') também falhou: ' . $r2['erro'];
    }
    return $r;
}

function chamar_ia_provedor($provedor, $prompt, $maxTokens, $timeoutSegundos, $temperatura, $modelo, $opcoes) {
    $apiKey = ia_chave($provedor);
    if ($apiKey === '') {
        return ['ok' => false, 'erro' => 'Nenhuma chave de IA configurada para ' . ia_nome_provedor($provedor) . '. Vá em Configurações e cole uma chave.'];
    }
    if ($provedor === 'gemini') {
        $modelo = $modelo ?: trim(get_config('ia_modelo_gemini', GDS_MODELO_GEMINI_PADRAO));
        $r = chamar_gemini($prompt, $apiKey, $maxTokens, $timeoutSegundos, $temperatura, $modelo, $opcoes['sistema'] ?? null);
        // Modelo principal sem cota/indisponível: tenta o modelo reserva do Gemini (ex.: um "Flash-Lite", que tem mais cota grátis)
        $reservaModelo = trim(get_config('ia_modelo_gemini_reserva', ''));
        if (!$r['ok'] && !empty($r['temporario']) && $reservaModelo !== '' && $reservaModelo !== $modelo) {
            $r2 = chamar_gemini($prompt, $apiKey, $maxTokens, $timeoutSegundos, $temperatura, $reservaModelo, $opcoes['sistema'] ?? null);
            if ($r2['ok']) return $r2;
        }
        return $r;
    }
    if ($provedor === 'anthropic') {
        return chamar_anthropic($prompt, $apiKey, $maxTokens, $timeoutSegundos, trim(get_config('ia_modelo_anthropic', GDS_MODELO_ANTHROPIC_PADRAO)), $opcoes);
    }
    if ($provedor === 'openai') {
        return chamar_openai_compativel($prompt, $apiKey, $maxTokens, $timeoutSegundos, $temperatura, trim(get_config('ia_modelo_openai', '')), $opcoes['sistema'] ?? null);
    }
    return ['ok' => false, 'erro' => "Provedor de IA desconhecido: $provedor"];
}

// Transforma o erro técnico da API numa explicação em português com o que fazer.
// 'temporario' => true marca erros em que vale tentar outra IA/modelo (cota, sobrecarga).
function ia_erro_amigavel($provedor, $http, $msgApi, $modelo = '') {
    $nome = ia_nome_provedor($provedor);
    $m = mb_strtolower((string)$msgApi);
    $detalhe = $msgApi ? " (detalhe técnico: " . mb_substr($msgApi, 0, 200) . ")" : '';
    if ($http === 429 || str_contains($m, 'quota') || str_contains($m, 'rate limit') || str_contains($m, 'resource_exhausted')) {
        return ['ok' => false, 'temporario' => true, 'erro' => "$nome: limite de uso do plano grátis atingido (muitas chamadas por minuto ou por dia). Espere alguns minutos, use um modelo com mais cota (ex.: um \"Flash-Lite\" no Gemini) ou configure uma IA reserva em Configurações.$detalhe"];
    }
    if ($http === 401 || $http === 403 || str_contains($m, 'api key not valid') || str_contains($m, 'invalid api key') || str_contains($m, 'permission')) {
        return ['ok' => false, 'erro' => "$nome: a chave de API foi recusada. Confira se copiou a chave inteira, sem espaços, e se ela é deste serviço.$detalhe"];
    }
    if ($http === 404 || str_contains($m, 'not found') || str_contains($m, 'does not exist') || str_contains($m, 'decommissioned')) {
        return ['ok' => false, 'temporario' => true, 'erro' => "$nome: o modelo \"$modelo\" não existe ou não está liberado para a sua chave. Em Configurações, clique em \"Ver modelos disponíveis\" e escolha um da lista.$detalhe"];
    }
    if ($http === 503 || $http === 500 || $http === 502 || $http === 529 || str_contains($m, 'overloaded') || str_contains($m, 'unavailable')) {
        return ['ok' => false, 'temporario' => true, 'erro' => "$nome está sobrecarregado agora. Tente de novo em instantes.$detalhe"];
    }
    if ($http === 400 && (str_contains($m, 'location') || str_contains($m, 'region'))) {
        return ['ok' => false, 'erro' => "$nome não está disponível na região do servidor da sua hospedagem.$detalhe"];
    }
    return ['ok' => false, 'erro' => "$nome recusou a chamada (HTTP $http)." . ($msgApi ? ' ' . mb_substr($msgApi, 0, 300) : '')];
}

// Executa um POST JSON e devolve [httpCode, corpoDecodificado, erroCurl, corpoBruto, cabecalhos]
function ia_post_json($url, $headers, $corpo, $timeout) {
    $cab = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS => json_encode($corpo),
        CURLOPT_HEADERFUNCTION => function ($ch, $linha) use (&$cab) {
            $p = explode(':', $linha, 2);
            if (count($p) === 2) $cab[strtolower(trim($p[0]))] = trim($p[1]);
            return strlen($linha);
        },
    ]);
    $resposta = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);
    return [$http, json_decode((string)$resposta, true), $erro, (string)$resposta, $cab];
}

// Lista os modelos que a chave realmente pode usar (evita adivinhar nome de modelo)
function ia_listar_modelos($provedor) {
    $chave = ia_chave($provedor);
    if ($chave === '') return ['ok' => false, 'erro' => 'Salve a chave de ' . ia_nome_provedor($provedor) . ' primeiro.'];
    if ($provedor === 'gemini') {
        $url = ia_url_base('gemini') . '/v1beta/models?pageSize=200';
        $headers = ['x-goog-api-key: ' . $chave];
    } elseif ($provedor === 'openai') {
        $url = ia_url_base('openai') . '/models';
        $headers = ['Authorization: Bearer ' . $chave];
    } else {
        $url = ia_url_base('anthropic') . '/v1/models?limit=100';
        $headers = ['x-api-key: ' . $chave, 'anthropic-version: 2023-06-01'];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => $headers]);
    $resp = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); $erroCurl = curl_error($ch); curl_close($ch);
    if ($erroCurl) return ['ok' => false, 'erro' => 'Erro de conexão: ' . $erroCurl];
    $d = json_decode((string)$resp, true);
    if ($http !== 200) return ia_erro_amigavel($provedor, $http, $d['error']['message'] ?? null);
    $lista = [];
    if ($provedor === 'gemini') {
        foreach ($d['models'] ?? [] as $m) {
            if (in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true) && !preg_match('/embedding|tts|image|audio|live/i', $m['name'])) {
                $lista[] = preg_replace('#^models/#', '', $m['name']);
            }
        }
    } else {
        foreach ($d['data'] ?? [] as $m) if (!empty($m['id'])) $lista[] = $m['id'];
    }
    sort($lista);
    return ['ok' => true, 'modelos' => $lista];
}

// Pede JSON e já devolve decodificado (tolerante a ```json ... ``` e texto em volta)
function chamar_ia_json($prompt, $maxTokens = 1500, $timeoutSegundos = 45, $temperatura = null, $opcoes = []) {
    $r = chamar_ia($prompt, $maxTokens, $timeoutSegundos, $temperatura, null, $opcoes);
    if (!$r['ok']) return $r;
    $json = ia_extrair_json($r['texto']);
    if ($json === null) return ['ok' => false, 'erro' => 'A IA respondeu num formato inesperado. Tente novamente.', 'texto' => $r['texto']];
    return ['ok' => true, 'dados' => $json, 'texto' => $r['texto']];
}

function ia_extrair_json($texto) {
    $texto = trim((string)$texto);
    $texto = preg_replace('/^```(?:json)?\s*/i', '', $texto);
    $texto = preg_replace('/```\s*$/', '', $texto);
    $dados = json_decode(trim($texto), true);
    if (is_array($dados)) return $dados;
    $ini = strpos($texto, '{'); $fim = strrpos($texto, '}');
    if ($ini !== false && $fim > $ini) {
        $dados = json_decode(substr($texto, $ini, $fim - $ini + 1), true);
        if (is_array($dados)) return $dados;
    }
    return null;
}

function chamar_gemini($prompt, $apiKey, $maxTokens, $timeoutSegundos = 30, $temperatura = null, $modelo = null, $sistema = null, $semThinkingConfig = false, $tentativa = 0) {
    $modelo = $modelo ?: GDS_MODELO_GEMINI_PADRAO;
    $url = ia_url_base('gemini') . '/v1beta/models/' . rawurlencode($modelo) . ':generateContent';

    $generationConfig = ['maxOutputTokens' => $maxTokens];
    if (!$semThinkingConfig) {
        // desliga o "raciocínio interno" que consumia os tokens e cortava a resposta
        $generationConfig['thinkingConfig'] = ['thinkingBudget' => 0];
    }
    if ($temperatura !== null) {
        $generationConfig['temperature'] = $temperatura; // mais alto = mais variedade/criatividade
    }
    $corpo = [
        'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
        'generationConfig' => $generationConfig,
    ];
    if ($sistema) {
        $corpo['systemInstruction'] = ['parts' => [['text' => $sistema]]];
    }

    [$httpCode, $data, $curlErro, $resposta] = ia_post_json($url, ['x-goog-api-key: ' . $apiKey], $corpo, $timeoutSegundos);

    // Excesso de chamadas por minuto: se o Google pedir pra esperar pouco, espera e tenta mais uma vez
    if ($httpCode === 429 && $tentativa === 0) {
        $espera = 0;
        foreach ($data['error']['details'] ?? [] as $det) {
            if (isset($det['retryDelay'])) $espera = (int)ceil((float)rtrim($det['retryDelay'], 's'));
        }
        if ($espera > 0 && $espera <= 20) {
            sleep($espera);
            return chamar_gemini($prompt, $apiKey, $maxTokens, $timeoutSegundos, $temperatura, $modelo, $sistema, $semThinkingConfig, 1);
        }
    }

    if ($curlErro) {
        $dica = stripos($curlErro, 'timed out') !== false
            ? ' Isso pode ser só o pedido ser grande (site completo demora mais) — tente de novo.'
            : ' Se sua hospedagem bloquear conexões de saída, peça ao suporte para liberar acesso a generativelanguage.googleapis.com na porta 443.';
        return ['ok' => false, 'temporario' => true, 'erro' => 'Erro de conexão ao chamar o Gemini: ' . $curlErro . '.' . $dica];
    }

    if ($httpCode !== 200) {
        $msgApi = $data['error']['message'] ?? null;
        // Alguns modelos não aceitam thinkingBudget=0 — tenta de novo sem essa opção
        if (!$semThinkingConfig && $httpCode === 400 && $msgApi && stripos($msgApi, 'thinking') !== false) {
            return chamar_gemini($prompt, $apiKey, $maxTokens, $timeoutSegundos, $temperatura, $modelo, $sistema, true, $tentativa);
        }
        return ia_erro_amigavel('gemini', $httpCode, $msgApi ?: substr($resposta, 0, 200), $modelo);
    }

    if (isset($data['promptFeedback']['blockReason'])) {
        return ['ok' => false, 'erro' => 'O Gemini bloqueou essa solicitação por segurança (motivo: ' . $data['promptFeedback']['blockReason'] . '). Tente reformular o texto.'];
    }

    $texto = '';
    foreach ($data['candidates'][0]['content']['parts'] ?? [] as $parte) {
        if (!empty($parte['thought'])) continue; // ignora partes de raciocínio, se vierem
        $texto .= $parte['text'] ?? '';
    }
    $finishReason = $data['candidates'][0]['finishReason'] ?? 'desconhecido';
    if ($texto === '') {
        $dica = match ($finishReason) {
            'MAX_TOKENS' => ' O limite de tamanho foi atingido antes de terminar — tente um briefing mais direto.',
            'SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST' => ' O filtro de segurança do Google barrou a resposta — reformule o pedido.',
            'RECITATION' => ' O Google barrou a resposta por parecer cópia de conteúdo existente — tente de novo.',
            default => '',
        };
        return ['ok' => false, 'temporario' => $finishReason !== 'MAX_TOKENS', 'erro' => "O Gemini não retornou texto (motivo: $finishReason).$dica"];
    }
    return ['ok' => true, 'texto' => trim($texto), 'cortado' => $finishReason === 'MAX_TOKENS'];
}

// ============================================================
// Anthropic (Claude) — usa streaming (SSE) para respostas longas,
// como o HTML de um site inteiro, sem estourar timeout da API.
// ============================================================
function chamar_anthropic($prompt, $apiKey, $maxTokens, $timeoutSegundos = 30, $modelo = GDS_MODELO_ANTHROPIC_PADRAO, $opcoes = []) {
    // O modelo "pensa" antes de responder e esse raciocínio conta no max_tokens —
    // por isso o piso mais alto, pra resposta curta não sair cortada.
    $maxTokens = max((int)$maxTokens, 4000);
    $esforco = $opcoes['esforco'] ?? ($maxTokens > 8000 ? 'medium' : 'low');

    $corpo = [
        'model' => $modelo ?: GDS_MODELO_ANTHROPIC_PADRAO,
        'max_tokens' => $maxTokens,
        'stream' => true,
        'output_config' => ['effort' => $esforco],
        // Se o filtro de segurança recusar, a própria API refaz com o modelo reserva recomendado
        'fallbacks' => 'default',
        'messages' => [['role' => 'user', 'content' => $prompt]],
    ];
    if (!empty($opcoes['sistema'])) {
        $corpo['system'] = $opcoes['sistema'];
    }

    $estado = ['buffer' => '', 'bruto' => '', 'texto' => '', 'stop_reason' => null, 'erro' => null];

    $ch = curl_init(ia_url_base('anthropic') . '/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => $timeoutSegundos,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
            'anthropic-beta: server-side-fallback-2026-07-01',
        ],
        CURLOPT_POSTFIELDS => json_encode($corpo),
        CURLOPT_WRITEFUNCTION => function ($ch, $pedaco) use (&$estado) {
            $estado['bruto'] .= strlen($estado['bruto']) < 4000 ? $pedaco : '';
            $estado['buffer'] .= $pedaco;
            while (($pos = strpos($estado['buffer'], "\n\n")) !== false) {
                $evento = substr($estado['buffer'], 0, $pos);
                $estado['buffer'] = substr($estado['buffer'], $pos + 2);
                anthropic_processar_evento($evento, $estado);
            }
            return strlen($pedaco);
        },
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErro = curl_error($ch);
    curl_close($ch);
    if (trim($estado['buffer']) !== '') anthropic_processar_evento($estado['buffer'], $estado);

    if ($httpCode !== 200) {
        $data = json_decode($estado['bruto'], true);
        if ($curlErro && !$httpCode) return ['ok' => false, 'temporario' => true, 'erro' => 'Erro de conexão ao chamar a Anthropic: ' . $curlErro];
        return ia_erro_amigavel('anthropic', $httpCode, $data['error']['message'] ?? null, $corpo['model']);
    }
    if ($estado['erro']) {
        return ['ok' => false, 'erro' => 'A Anthropic interrompeu a resposta: ' . $estado['erro']];
    }
    if ($curlErro) {
        $dica = stripos($curlErro, 'timed out') !== false ? ' A geração demorou mais que o limite — tente novamente.' : '';
        return ['ok' => false, 'erro' => 'Erro de conexão ao chamar a Anthropic: ' . $curlErro . '.' . $dica];
    }
    if ($estado['stop_reason'] === 'refusal') {
        return ['ok' => false, 'erro' => 'A IA recusou esse pedido por segurança. Tente reformular o briefing.'];
    }
    if (trim($estado['texto']) === '') {
        return ['ok' => false, 'erro' => 'A Anthropic respondeu vazio. Tente novamente.'];
    }
    return ['ok' => true, 'texto' => trim($estado['texto']), 'cortado' => $estado['stop_reason'] === 'max_tokens'];
}

function anthropic_processar_evento($evento, &$estado) {
    foreach (explode("\n", $evento) as $linha) {
        if (strncmp($linha, 'data:', 5) !== 0) continue;
        $d = json_decode(trim(substr($linha, 5)), true);
        if (!is_array($d)) continue;
        switch ($d['type'] ?? '') {
            case 'content_block_start':
                // Troca de modelo por recusa no meio do caminho: descarta o texto parcial anterior
                if (($d['content_block']['type'] ?? '') === 'fallback') $estado['texto'] = '';
                break;
            case 'content_block_delta':
                if (($d['delta']['type'] ?? '') === 'text_delta') $estado['texto'] .= $d['delta']['text'];
                break;
            case 'message_delta':
                if (isset($d['delta']['stop_reason'])) $estado['stop_reason'] = $d['delta']['stop_reason'];
                break;
            case 'error':
                $estado['erro'] = $d['error']['message'] ?? 'erro desconhecido';
                break;
        }
    }
}

// ============================================================
// Serviços "compatíveis com OpenAI" (Groq, OpenRouter, Mistral...)
// — várias têm plano gratuito. Endereço e modelo vêm de Configurações.
// ============================================================
function chamar_openai_compativel($prompt, $apiKey, $maxTokens, $timeoutSegundos, $temperatura, $modelo, $sistema = null) {
    if ($modelo === '') return ['ok' => false, 'erro' => 'Escolha o modelo da IA em Configurações (botão "Ver modelos disponíveis").'];
    $mensagens = [];
    if ($sistema) $mensagens[] = ['role' => 'system', 'content' => $sistema];
    $mensagens[] = ['role' => 'user', 'content' => $prompt];
    $corpo = ['model' => $modelo, 'messages' => $mensagens, 'max_tokens' => (int)$maxTokens];
    if ($temperatura !== null) $corpo['temperature'] = min(1.0, (float)$temperatura);
    $headers = ['Authorization: Bearer ' . $apiKey];
    if (str_contains(ia_url_base('openai'), 'openrouter.ai')) {
        $headers[] = 'X-Title: GDS - Gestor de Sites';
    }
    [$http, $d, $erroCurl] = ia_post_json(ia_url_base('openai') . '/chat/completions', $headers, $corpo, $timeoutSegundos);
    if ($erroCurl && !$http) return ['ok' => false, 'temporario' => true, 'erro' => 'Erro de conexão com a IA: ' . $erroCurl];

    // Alguns serviços limitam o tamanho máximo de resposta: tenta de novo com um limite menor
    $msg = $d['error']['message'] ?? (is_string($d['error'] ?? null) ? $d['error'] : null);
    if ($http === 400 && $msg && preg_match('/max_tokens|max_completion_tokens|maximum/i', $msg) && $maxTokens > 8000) {
        return chamar_openai_compativel($prompt, $apiKey, 8000, $timeoutSegundos, $temperatura, $modelo, $sistema);
    }
    if ($http !== 200) return ia_erro_amigavel('openai', $http, $msg, $modelo);

    $texto = $d['choices'][0]['message']['content'] ?? '';
    $fim = $d['choices'][0]['finish_reason'] ?? '';
    if (trim((string)$texto) === '') return ['ok' => false, 'temporario' => true, 'erro' => 'A IA respondeu vazio. Tente novamente.'];
    return ['ok' => true, 'texto' => trim($texto), 'cortado' => $fim === 'length'];
}
