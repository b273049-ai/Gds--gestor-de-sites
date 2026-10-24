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

// Endereços das APIs (dá pra trocar por variável de ambiente, ex.: para usar um proxy/gateway)
function ia_url_base($provedor) {
    $env = getenv($provedor === 'anthropic' ? 'GDS_ANTHROPIC_BASE_URL' : 'GDS_GEMINI_BASE_URL');
    if ($env) return rtrim($env, '/');
    return $provedor === 'anthropic' ? 'https://api.anthropic.com' : 'https://generativelanguage.googleapis.com';
}

function ia_configurada() {
    return trim(get_config('ia_api_key', '')) !== '';
}

function chamar_ia($prompt, $maxTokens = 800, $timeoutSegundos = 30, $temperatura = null, $modelo = null, $opcoes = []) {
    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'erro' => 'A extensão cURL do PHP não está habilitada nesta hospedagem. No painel da hospedagem, vá em Configuração PHP e ative a extensão "curl".',
        ];
    }

    $provider = get_config('ia_provider', 'gemini');
    $apiKey = trim(get_config('ia_api_key', ''));

    if ($apiKey === '') {
        return [
            'ok' => false,
            'erro' => 'Nenhuma chave de IA configurada ainda. Vá em Configurações e cole uma chave (a do Google Gemini é gratuita, veja o passo a passo lá).',
        ];
    }

    if ($provider === 'gemini') {
        $modelo = $modelo ?: trim(get_config('ia_modelo_gemini', GDS_MODELO_GEMINI_PADRAO));
        return chamar_gemini($prompt, $apiKey, $maxTokens, $timeoutSegundos, $temperatura, $modelo, $opcoes['sistema'] ?? null);
    }

    if ($provider === 'anthropic') {
        $modeloAnthropic = trim(get_config('ia_modelo_anthropic', GDS_MODELO_ANTHROPIC_PADRAO));
        return chamar_anthropic($prompt, $apiKey, $maxTokens, $timeoutSegundos, $modeloAnthropic, $opcoes);
    }

    return ['ok' => false, 'erro' => "Provedor de IA desconhecido: $provider"];
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

function chamar_gemini($prompt, $apiKey, $maxTokens, $timeoutSegundos = 30, $temperatura = null, $modelo = null, $sistema = null, $semThinkingConfig = false) {
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

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => $timeoutSegundos,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($corpo),
    ]);
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErro = curl_error($ch);
    curl_close($ch);

    if ($curlErro) {
        $dica = stripos($curlErro, 'timed out') !== false
            ? ' Isso pode ser só o pedido ser grande (site completo demora mais) — tente de novo.'
            : ' Se sua hospedagem bloquear conexões de saída, peça ao suporte para liberar acesso a generativelanguage.googleapis.com na porta 443.';
        return ['ok' => false, 'erro' => 'Erro de conexão ao chamar o Gemini: ' . $curlErro . '.' . $dica];
    }

    $data = json_decode($resposta, true);

    if ($httpCode !== 200) {
        $msgApi = $data['error']['message'] ?? null;
        // Alguns modelos não aceitam thinkingBudget=0 — tenta de novo sem essa opção
        if (!$semThinkingConfig && $httpCode === 400 && $msgApi && stripos($msgApi, 'thinking') !== false) {
            return chamar_gemini($prompt, $apiKey, $maxTokens, $timeoutSegundos, $temperatura, $modelo, $sistema, true);
        }
        if ($msgApi) {
            return ['ok' => false, 'erro' => "Gemini recusou a chamada (HTTP $httpCode): $msgApi"];
        }
        return ['ok' => false, 'erro' => "Falha ao chamar o Gemini (HTTP $httpCode). Resposta bruta: " . substr((string)$resposta, 0, 300)];
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
        $dica = $finishReason === 'MAX_TOKENS' ? ' O limite de tamanho foi atingido antes de terminar — tente um briefing mais direto.' : '';
        return ['ok' => false, 'erro' => "O Gemini não retornou texto (motivo: $finishReason).$dica Tente novamente."];
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
        $msgApi = $data['error']['message'] ?? null;
        if ($msgApi) return ['ok' => false, 'erro' => "Anthropic recusou a chamada (HTTP $httpCode): $msgApi"];
        if ($curlErro) return ['ok' => false, 'erro' => 'Erro de conexão ao chamar a Anthropic: ' . $curlErro];
        return ['ok' => false, 'erro' => "Falha ao chamar a Anthropic (HTTP $httpCode)."];
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
