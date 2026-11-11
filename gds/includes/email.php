<?php
// ============================================================
// E-MAIL: envio por SMTP (ex.: e-mail da Hostinger) com reserva no mail() do PHP.
// Configure em Admin → Configurações → E-mail (SMTP).
// Hostinger: servidor smtp.hostinger.com, porta 465 (SSL), usuário = o e-mail completo.
// ============================================================

function email_smtp_configurado() {
    return get_config('smtp_host', '') !== '' && get_config('smtp_usuario', '') !== '' && get_config('smtp_senha', '') !== '';
}

// Envia um e-mail. Retorna ['ok' => bool, 'erro' => string, 'via' => 'smtp'|'mail']
function gds_enviar_email($para, $assunto, $html, $texto = '') {
    $para = trim((string)$para);
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'erro' => 'E-mail do destinatário inválido.', 'via' => ''];
    $texto = $texto !== '' ? $texto : trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>|</p>#i', "\n", $html)), ENT_QUOTES, 'UTF-8'));
    $nome = get_config('smtp_nome', '') ?: get_config('mkt_marca', '') ?: 'GDS';
    $r = email_smtp_configurado()
        ? email_smtp($para, $assunto, $html, $texto, get_config('smtp_usuario', ''), $nome)
        : email_mail_php($para, $assunto, $html, $texto, $nome);
    set_config('email_ultimo', date('d/m/Y H:i') . ' · ' . ($r['ok'] ? 'enviado' : 'falhou: ' . mb_substr($r['erro'], 0, 200)) . ' · ' . $r['via']);
    if (!$r['ok']) error_log('GDS e-mail (' . $r['via'] . '): ' . $r['erro']);
    return $r;
}

function email_mime($de, $nome, $para, $assunto, $html, $texto) {
    $fronteira = 'gds-' . bin2hex(random_bytes(8));
    $cab = 'From: =?UTF-8?B?' . base64_encode($nome) . "?= <$de>\r\n"
         . "To: <$para>\r\n"
         . 'Subject: =?UTF-8?B?' . base64_encode($assunto) . "?=\r\n"
         . 'Date: ' . date('r') . "\r\n"
         . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $de)[1] ?? 'gds.local') . ">\r\n"
         . "MIME-Version: 1.0\r\n"
         . "Content-Type: multipart/alternative; boundary=\"$fronteira\"\r\n";
    $corpo = "--$fronteira\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($texto))
           . "--$fronteira\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
           . "--$fronteira--\r\n";
    return [$cab, $corpo];
}

function email_mail_php($para, $assunto, $html, $texto, $nome) {
    $host = preg_replace('/:\d+$/', '', preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
    $de = 'nao-responda@' . preg_replace('/^www\./', '', $host);
    [$cab, $corpo] = email_mime($de, $nome, $para, $assunto, $html, $texto);
    // mail() recebe o "To" e o "Subject" separados
    $cab = preg_replace("/^(To|Subject): .*\r\n/m", '', $cab);
    $ok = @mail($para, '=?UTF-8?B?' . base64_encode($assunto) . '?=', $corpo, $cab, '-f' . $de);
    return ['ok' => (bool)$ok, 'erro' => $ok ? '' : 'A função mail() da hospedagem recusou o envio. Configure o SMTP em Admin → Configurações.', 'via' => 'mail'];
}

// Cliente SMTP mínimo: SSL direto (465) ou STARTTLS (587), AUTH LOGIN
function email_smtp($para, $assunto, $html, $texto, $de, $nome) {
    $host = get_config('smtp_host', '');
    $porta = (int)get_config('smtp_porta', '465') ?: 465;
    $usuario = get_config('smtp_usuario', '');
    $senha = get_config('smtp_senha', '');
    $erro = function ($m) { return ['ok' => false, 'erro' => $m, 'via' => 'smtp']; };
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $alvo = ($porta === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $porta;
    $s = @stream_socket_client($alvo, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$s) return $erro("Não conectou em $host:$porta ($errstr). Confira servidor e porta.");
    stream_set_timeout($s, 20);
    $ler = function () use ($s) { $r = ''; while (($l = fgets($s, 1024)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return $r; };
    $cmd = function ($c, $esperado) use ($s, $ler) { if ($c !== null) fwrite($s, $c . "\r\n"); $r = $ler(); return [(int)substr($r, 0, 3) === $esperado, trim($r)]; };
    [$ok, $r] = $cmd(null, 220); if (!$ok) return $erro('Servidor respondeu: ' . $r);
    $eu = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost') ?: 'localhost';
    [$ok, $r] = $cmd("EHLO $eu", 250); if (!$ok) return $erro('EHLO: ' . $r);
    // 587 exige STARTTLS; 25/2525 só usam se o servidor oferecer
    if ($porta !== 465 && ($porta === 587 || stripos($r, 'STARTTLS') !== false)) {
        [$ok, $r] = $cmd('STARTTLS', 220); if (!$ok) return $erro('STARTTLS: ' . $r);
        if (!@stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) return $erro('Não foi possível ativar a criptografia (TLS).');
        [$ok, $r] = $cmd("EHLO $eu", 250); if (!$ok) return $erro('EHLO: ' . $r);
    }
    [$ok, $r] = $cmd('AUTH LOGIN', 334); if (!$ok) return $erro('AUTH: ' . $r);
    [$ok, $r] = $cmd(base64_encode($usuario), 334); if (!$ok) return $erro('Usuário recusado: ' . $r);
    [$ok, $r] = $cmd(base64_encode($senha), 235); if (!$ok) return $erro('Usuário ou senha do e-mail incorretos (' . $r . ').');
    [$ok, $r] = $cmd("MAIL FROM:<$de>", 250); if (!$ok) return $erro('Remetente recusado: ' . $r);
    [$ok, $r] = $cmd("RCPT TO:<$para>", 250); if (!$ok) { [$ok2] = [(int)substr($r, 0, 3) === 251]; if (!$ok2) return $erro('Destinatário recusado: ' . $r); }
    [$ok, $r] = $cmd('DATA', 354); if (!$ok) return $erro('DATA: ' . $r);
    [$cab, $corpo] = email_mime($de, $nome, $para, $assunto, $html, $texto);
    $msg = $cab . "\r\n" . $corpo;
    $msg = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $msg));
    [$ok, $r] = $cmd($msg . "\r\n.", 250); if (!$ok) return $erro('Mensagem recusada: ' . $r);
    $cmd('QUIT', 221);
    fclose($s);
    return ['ok' => true, 'erro' => '', 'via' => 'smtp'];
}

// Modelo simples e bonito para os e-mails da plataforma
function email_modelo($titulo, $paragrafos, $botaoTexto = '', $botaoLink = '') {
    $p = implode('', array_map(fn($t) => '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#33364d">' . $t . '</p>', (array)$paragrafos));
    $b = $botaoLink ? '<p style="margin:24px 0"><a href="' . htmlspecialchars($botaoLink) . '" style="display:inline-block;background:#5b7cff;color:#fff;text-decoration:none;font-weight:700;padding:14px 26px;border-radius:10px">' . htmlspecialchars($botaoTexto) . '</a></p>'
                     . '<p style="font-size:12px;color:#8a8ea8;word-break:break-all">Se o botão não abrir, copie este endereço: ' . htmlspecialchars($botaoLink) . '</p>' : '';
    return '<!DOCTYPE html><html><body style="margin:0;background:#f3f4fb;padding:24px;font-family:Arial,Helvetica,sans-serif">'
         . '<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;border:1px solid #e4e6f2">'
         . '<h1 style="margin:0 0 18px;font-size:22px;color:#14162b">' . htmlspecialchars($titulo) . '</h1>' . $p . $b . '</div></body></html>';
}
