<?php
// ============================================================
// FUNÇÃO CENTRAL DE CHAMADA À IA
// Usada por todos os pontos do sistema que precisam gerar texto.
// Decide sozinha qual provedor usar com base no que foi salvo em
// Admin > Configurações (tabela "configuracoes").
// ============================================================

function chamar_ia($prompt, $maxTokens = 800, $timeoutSegundos = 30, $temperatura = null, $modelo = null) {
    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'erro' => 'A extensão cURL do PHP não está habilitada nesta hospedagem. No hPanel da Hostinger, vá em Configuração PHP e ative a extensão "curl".',
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
        return chamar_gemini($prompt, $apiKey, $maxTokens, $timeoutSegundos, $temperatura, $modelo);
    }

    if ($provider === 'anthropic') {
        return chamar_anthropic($prompt, $apiKey, $maxTokens, $timeoutSegundos);
    }

    return ['ok' => false, 'erro' => "Provedor de IA desconhecido: $provider"];
}

function chamar_gemini($prompt, $apiKey, $maxTokens, $timeoutSegundos = 30, $temperatura = null, $modelo = null) {
    $modelo = $modelo ?: 'gemini-3.5-flash';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelo . ':generateContent';
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
        CURLOPT_POSTFIELDS => json_encode(array_merge_recursive([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => array_filter([
                'maxOutputTokens' => $maxTokens,
                'thinkingConfig' => ['thinkingBudget' => 0], // desliga o "raciocínio interno" que consumia os tokens e cortava a resposta
                'temperature' => $temperatura, // null = usa o padrão do modelo; mais alto = mais variedade/criatividade
            ], function($v) { return $v !== null; }),
        ], [])),
    ]);
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErro = curl_error($ch);
    curl_close($ch);

    if ($curlErro) {
        $dica = strpos($curlErro, 'timed out') !== false
            ? " Isso pode ser só o pedido ser grande (site completo demora mais) — tente de novo, ou peça um briefing mais curto."
            : " Se sua hospedagem bloquear conexões de saída, peça ao suporte para liberar acesso a generativelanguage.googleapis.com na porta 443.";
        return ['ok' => false, 'erro' => 'Erro de conexão ao chamar o Gemini: ' . $curlErro . '.' . $dica];
    }

    $data = json_decode($resposta, true);

    if ($httpCode !== 200) {
        $msgApi = $data['error']['message'] ?? null;
        if ($msgApi) {
            return ['ok' => false, 'erro' => "Gemini recusou a chamada (HTTP $httpCode): $msgApi"];
        }
        return ['ok' => false, 'erro' => "Falha ao chamar o Gemini (HTTP $httpCode). Resposta bruta: " . substr($resposta, 0, 300)];
    }

    if (isset($data['promptFeedback']['blockReason'])) {
        return ['ok' => false, 'erro' => 'O Gemini bloqueou essa solicitação por segurança (motivo: ' . $data['promptFeedback']['blockReason'] . '). Tente reformular o texto.'];
    }

    $texto = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if ($texto === '') {
        $finishReason = $data['candidates'][0]['finishReason'] ?? 'desconhecido';
        $dica = $finishReason === 'MAX_TOKENS' ? ' O limite de tamanho foi atingido antes de terminar — tente um briefing mais direto.' : '';
        return ['ok' => false, 'erro' => "O Gemini não retornou texto (motivo: $finishReason).$dica Tente novamente."];
    }
    return ['ok' => true, 'texto' => trim($texto)];
}

function chamar_anthropic($prompt, $apiKey, $maxTokens, $timeoutSegundos = 30) {
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => $timeoutSegundos,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => $maxTokens,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]),
    ]);
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErro = curl_error($ch);
    curl_close($ch);

    if ($curlErro) {
        return ['ok' => false, 'erro' => 'Erro de conexão ao chamar a Anthropic: ' . $curlErro];
    }

    $data = json_decode($resposta, true);

    if ($httpCode !== 200) {
        $msgApi = $data['error']['message'] ?? null;
        if ($msgApi) {
            return ['ok' => false, 'erro' => "Anthropic recusou a chamada (HTTP $httpCode): $msgApi"];
        }
        return ['ok' => false, 'erro' => "Falha ao chamar a Anthropic (HTTP $httpCode)."];
    }

    $texto = $data['content'][0]['text'] ?? '';
    if ($texto === '') {
        return ['ok' => false, 'erro' => 'A Anthropic respondeu vazio.'];
    }
    return ['ok' => true, 'texto' => trim($texto)];
}
