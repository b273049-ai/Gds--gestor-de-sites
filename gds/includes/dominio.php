<?php
// ============================================================
// DOMÍNIO PRÓPRIO DO CLIENTE (ex.: www.barbeariadoze.com.br)
//
// Para um domínio abrir o site da empresa, 3 coisas precisam estar certas:
//   1. DNS: o domínio aponta para o IP do servidor da plataforma
//      (registro A no Registro.br / GoDaddy / Hostinger...)
//   2. Hospedagem: o domínio foi adicionado no servidor da plataforma
//      (na Hostinger: "Domínios estacionados"/Parked Domains do site)
//   3. HTTPS: o certificado SSL foi emitido para o domínio
// Este arquivo testa cada etapa e explica, em português, o que falta.
// ============================================================

// "https://www.Barbearia.com.br/contato" → "barbearia.com.br"
function dominio_normalizar($d) {
    $d = strtolower(trim((string)$d));
    $d = preg_replace('#^[a-z]+://#', '', $d);
    $d = preg_replace('#[/?\#].*$#', '', $d);
    $d = preg_replace('/:\d+$/', '', $d);
    $d = preg_replace('/^www\./', '', rtrim($d, '.'));
    if ($d !== '' && preg_match('/[^\x20-\x7e]/', $d) && function_exists('idn_to_ascii')) {
        $conv = idn_to_ascii($d, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($conv) $d = $conv;
    }
    return $d;
}

function dominio_valido($d) {
    return (bool)preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{1,62}$/', $d);
}

// Host da própria plataforma (não pode ser usado como domínio de cliente)
function dominio_plataforma() {
    $url = get_config('plataforma_url', '');
    $host = $url ? parse_url($url, PHP_URL_HOST) : ($_SERVER['HTTP_HOST'] ?? '');
    return dominio_normalizar($host);
}

// IP para o registro A (configurável pelo admin; senão, o IP deste servidor)
function dominio_ip_servidor() {
    $ip = trim(get_config('servidor_ip', ''));
    if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    $local = $_SERVER['SERVER_ADDR'] ?? '';
    if (filter_var($local, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return $local;
    $plat = dominio_plataforma();
    if ($plat !== '') {
        $r = @gethostbyname($plat);
        if ($r !== $plat && filter_var($r, FILTER_VALIDATE_IP)) return $r;
    }
    return '';
}

// Registros que o cliente precisa criar no provedor do domínio
function dominio_registros_dns($dominio) {
    $ip = dominio_ip_servidor();
    return [
        ['tipo' => 'A', 'nome' => '@ (vazio / ' . $dominio . ')', 'valor' => $ip ?: '(peça o IP ao suporte da plataforma)'],
        ['tipo' => 'A', 'nome' => 'www', 'valor' => $ip ?: '(mesmo IP acima)'],
    ];
}

// IPs para os quais o domínio aponta hoje
function dominio_ips($host) {
    $ips = [];
    $teste = getenv('GDS_DOMINIO_TESTE_IP'); // só para testes locais
    if ($teste) return [$teste];
    if (function_exists('dns_get_record')) {
        $r = @dns_get_record($host, DNS_A);
        foreach ((array)$r as $x) if (!empty($x['ip'])) $ips[] = $x['ip'];
    }
    if (!$ips) {
        $l = @gethostbynamel($host);
        if ($l) $ips = $l;
    }
    return array_values(array_unique($ips));
}

// Busca /?gds_verificar=1 no domínio e confere se quem respondeu foi ESTA plataforma
function dominio_testar_http($host, $https) {
    $url = ($https ? 'https://' : 'http://') . $host . '/?gds_verificar=1';
    $ch = curl_init($url);
    $op = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
           CURLOPT_USERAGENT => 'GDS-Verificador/1.0', CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2];
    if ($teste = getenv('GDS_DOMINIO_TESTE_IP')) {
        $porta = getenv('GDS_DOMINIO_TESTE_PORTA') ?: ($https ? 443 : 80);
        $op[CURLOPT_URL] = ($https ? 'https://' : 'http://') . $host . ':' . $porta . '/?gds_verificar=1';
        $op[CURLOPT_RESOLVE] = ["$host:$porta:$teste"];
    }
    curl_setopt_array($ch, $op);
    $corpo = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    $erro = curl_error($ch);
    curl_close($ch);
    $json = is_string($corpo) ? json_decode($corpo, true) : null;
    return [
        'respondeu' => $corpo !== false && $code > 0,
        'nosso' => is_array($json) && ($json['gds'] ?? '') === 'ok',
        'empresa' => is_array($json) ? ($json['empresa'] ?? null) : null,
        'code' => $code, 'erro' => $erro, 'ssl_erro' => in_array($errno, [35, 51, 58, 60], true),
    ];
}

// Verifica tudo, grava o resultado na empresa e devolve as etapas para a tela
function dominio_verificar($empresa) {
    global $pdo;
    $dom = $empresa['dominio_personalizado'] ?? '';
    if ($dom === '') return ['status' => null, 'etapas' => []];
    $ipServ = dominio_ip_servidor();
    $etapas = [];

    $ips = dominio_ips($dom);
    $ipsWww = getenv('GDS_DOMINIO_TESTE_IP') ? $ips : dominio_ips('www.' . $dom);
    $dnsOk = $ips && (!$ipServ || in_array($ipServ, $ips, true));
    $http = dominio_testar_http($dom, false);
    // Atrás de um proxy/CDN o IP pode ser outro: se a plataforma respondeu, o DNS está certo
    if (!$dnsOk && $http['nosso']) $dnsOk = true;
    $etapas['dns'] = ['ok' => $dnsOk, 'titulo' => 'Domínio apontando para a plataforma (DNS)',
        'detalhe' => $dnsOk ? 'O domínio aponta para ' . implode(', ', $ips) . '.'
            : ($ips ? 'O domínio aponta para ' . implode(', ', $ips) . ', mas deveria apontar para ' . ($ipServ ?: 'o IP da plataforma') . '. Corrija o registro A no site onde o domínio foi registrado.'
                    : 'O domínio ainda não aponta para lugar nenhum (ou acabou de ser alterado). Crie os registros abaixo. Depois de criar, pode levar de alguns minutos até 24h para valer.')];
    if (!$ipsWww) $etapas['dns']['detalhe'] .= ' Falta também o registro do "www".';

    $hospOk = $http['nosso'] && (int)$http['empresa'] === (int)$empresa['id'];
    $etapas['hospedagem'] = ['ok' => $hospOk, 'titulo' => 'Domínio ativado no servidor da plataforma',
        'detalhe' => $hospOk ? 'O servidor já responde com o site desta empresa.'
            : ($http['nosso'] ? 'O servidor respondeu, mas o domínio está ligado a outra empresa. Confira o domínio cadastrado.'
                : ($dnsOk ? 'O DNS está certo, mas o domínio ainda não foi adicionado na hospedagem da plataforma. O administrador precisa ativar (na Hostinger: Domínios estacionados). Já avisamos.'
                          : 'Depende da etapa anterior.'))];

    $https = $hospOk ? dominio_testar_http($dom, true) : ['nosso' => false, 'ssl_erro' => false];
    $sslOk = $hospOk && $https['nosso'];
    $etapas['ssl'] = ['ok' => $sslOk, 'titulo' => 'Cadeado de segurança (HTTPS)',
        'detalhe' => $sslOk ? 'https://' . $dom . ' está funcionando com certificado válido.'
            : ($hospOk ? ($https['ssl_erro'] ? 'O certificado SSL ainda não foi emitido para este domínio. Na Hostinger ele sai sozinho em 1 a 2 horas depois de ativar o domínio; se passar disso, reinstale o SSL no painel da hospedagem.'
                                             : 'Ainda não respondeu em HTTPS. Aguarde a emissão do certificado (até 2 horas).')
                       : 'Depende das etapas anteriores.')];

    $status = $sslOk ? 'ativo' : ($hospOk ? 'sem_ssl' : ($dnsOk ? 'hospedagem' : 'dns'));
    $resumo = ['ativo' => 'Funcionando', 'sem_ssl' => 'Funcionando sem HTTPS', 'hospedagem' => 'Aguardando ativação no servidor', 'dns' => 'Aguardando DNS'][$status];
    try {
        $pdo->prepare("UPDATE empresas SET dominio_status=?, dominio_detalhe=?, dominio_verificado_em=NOW() WHERE id=?")->execute([$status, $resumo, $empresa['id']]);
    } catch (PDOException $e) { /* banco sem as colunas novas */ }
    return ['status' => $status, 'resumo' => $resumo, 'etapas' => $etapas, 'ips' => $ips, 'ip_servidor' => $ipServ];
}

function dominio_status_rotulo($status) {
    return ['ativo' => ['Funcionando', 'pill-ok'], 'sem_ssl' => ['Sem HTTPS ainda', 'pill-aviso'], 'hospedagem' => ['Aguardando ativação', 'pill-aviso'],
            'dns' => ['Aguardando DNS', 'pill-aviso'], 'pendente' => ['Não verificado', 'pill-neutro']][$status ?? 'pendente'] ?? ['Não verificado', 'pill-neutro'];
}

// Salva (ou remove) o domínio de uma empresa, com as validações. Retorna '' ou a mensagem de erro.
function dominio_salvar($empresaId, $entrada) {
    global $pdo;
    $dom = dominio_normalizar($entrada);
    if ($dom !== '' && !dominio_valido($dom)) return 'Domínio inválido. Exemplo: minhaempresa.com.br';
    if ($dom !== '' && $dom === dominio_plataforma()) return 'Esse é o endereço da própria plataforma.';
    if ($dom !== '' && preg_match('/\.(hostingersite|vercel|netlify|github)\./', $dom . '.')) return 'Use um domínio registrado em seu nome (ex.: .com.br).';
    if ($dom !== '') {
        $q = $pdo->prepare("SELECT 1 FROM empresas WHERE dominio_personalizado=? AND id<>?");
        $q->execute([$dom, $empresaId]);
        if ($q->fetch()) return 'Esse domínio já está ligado a outra empresa.';
    }
    $pdo->prepare("UPDATE empresas SET dominio_personalizado=?, dominio_status=?, dominio_detalhe=NULL, dominio_verificado_em=NULL WHERE id=?")
        ->execute([$dom ?: null, $dom ? 'pendente' : null, $empresaId]);
    return '';
}
