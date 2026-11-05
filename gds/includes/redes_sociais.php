<?php
// ============================================================
// REDES SOCIAIS — Instagram e TikTok conectados para POSTAR SOZINHO
// e para transformar comentário em cliente.
//
// Instagram: "Instagram API com login do Instagram" (oficial da Meta). Não precisa de
//   Página do Facebook: basta a conta profissional (Empresa ou Criador). A sua própria
//   conta, cadastrada como testadora do seu app, publica sem a análise da Meta.
//   • Publica Post, Carrossel (até 10), Reels (MP4) e Stories (só imagem: os adesivos
//     de enquete/pergunta não existem na API — esses stories continuam no lembrete).
//   • Comentário com a palavra-chave → resposta privada no direct (1 por comentário,
//     até 7 dias) com a isca e o link do WhatsApp. Quem responde no direct pode ser
//     atendido pela IA por 24 h (regra da Meta).
//   • Insights por post (alcance, envios, salvos, comentários) → a IA aprende o que funciona.
// TikTok: "Content Posting API" (oficial). Sem a auditoria do TikTok, o post direto sai
//   PRIVADO; por isso o padrão é mandar como RASCUNHO para o app (você escolhe a música
//   em alta e publica com 1 toque). Depois de auditado, dá para postar direto.
// ============================================================
require_once __DIR__ . '/../config/db.php';

const SOC_IG_VERSAO = 'v24.0';
const SOC_IG_ESCOPOS = 'instagram_business_basic,instagram_business_content_publish,instagram_business_manage_comments,instagram_business_manage_messages';
// Rascunho no app só precisa de video.upload; video.publish só existe com o "Direct Post" ligado no app do TikTok
function soc_tt_escopos() { return 'user.info.basic,video.upload' . (soc_tt_modo() === 'direto' ? ',video.publish' : ''); }

// Apps novos da Meta (2026) só têm o "login unificado" (Login do Facebook para Empresas com um ID de configuração):
// aí tudo passa pelo graph.facebook.com. Apps antigos continuam no login do Instagram (graph.instagram.com).
function soc_ig_unificado() { return get_config('ig_config_id', '') !== ''; }
function soc_fb_graph() { return rtrim(getenv('GDS_FB_GRAPH') ?: 'https://graph.facebook.com', '/'); }
function soc_ig_graph() { return soc_ig_unificado() ? soc_fb_graph() : rtrim(getenv('GDS_IG_GRAPH') ?: 'https://graph.instagram.com', '/'); }
function soc_ig_api() { return rtrim(getenv('GDS_IG_API') ?: 'https://api.instagram.com', '/'); }
function soc_tt_api() { return rtrim(getenv('GDS_TT_API') ?: 'https://open.tiktokapis.com', '/'); }

function soc_url_base() {
    $cfg = rtrim(get_config('plataforma_url', ''), '/');
    if ($cfg !== '') return $cfg;
    $host = preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['HTTP_HOST'] ?? '');
    $https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https://' : 'http://') . $host;
}
function soc_redirect_uri() { return soc_url_base() . '/admin/redes_conectar.php'; }
function soc_webhook_url() { return soc_url_base() . '/api/instagram_webhook.php'; }
function soc_webhook_token() {
    $t = get_config('ig_webhook_token', '');
    if ($t === '') { $t = bin2hex(random_bytes(16)); set_config('ig_webhook_token', $t); }
    return $t;
}

// Requisição HTTP. $dados: array (formulário, ou JSON com 'json'=>true) ou string crua.
function soc_http($metodo, $url, $dados = null, $opts = []) {
    $ch = curl_init($url);
    $h = ['Accept: application/json', 'User-Agent: GDS'];
    if (!empty($opts['token'])) $h[] = 'Authorization: Bearer ' . $opts['token'];
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $opts['timeout'] ?? 25, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_CUSTOMREQUEST => $metodo];
    if (!empty($opts['arquivo'])) {
        $fh = fopen($opts['arquivo'], 'rb');
        $o[CURLOPT_UPLOAD] = true; $o[CURLOPT_INFILE] = $fh; $o[CURLOPT_INFILESIZE] = filesize($opts['arquivo']);
    } elseif ($dados !== null) {
        if (!empty($opts['json'])) { $o[CURLOPT_POSTFIELDS] = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); $h[] = 'Content-Type: application/json; charset=UTF-8'; }
        else $o[CURLOPT_POSTFIELDS] = is_array($dados) ? http_build_query($dados) : $dados;
    }
    foreach ((array)($opts['headers'] ?? []) as $x) $h[] = $x;
    $o[CURLOPT_HTTPHEADER] = $h;
    curl_setopt_array($ch, $o);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if (isset($fh) && is_resource($fh)) fclose($fh);
    $j = is_string($resp) ? json_decode($resp, true) : null;
    return ['code' => $code, 'json' => is_array($j) ? $j : [], 'corpo' => is_string($resp) ? $resp : '', 'erro_rede' => $resp === false ? ($err ?: 'sem resposta') : ''];
}

// ======================= INSTAGRAM =======================
function soc_ig_app_ok() { return get_config('ig_app_id', '') !== '' && get_config('ig_app_secret', '') !== ''; }
function soc_ig_conectado() { return get_config('ig_token', '') !== '' && get_config('ig_user_id', '') !== ''; }

function soc_ig_link_autorizar($state) {
    if (soc_ig_unificado()) {
        return 'https://www.facebook.com/' . SOC_IG_VERSAO . '/dialog/oauth?' . http_build_query(['client_id' => get_config('ig_app_id', ''),
            'config_id' => get_config('ig_config_id', ''), 'response_type' => 'code', 'redirect_uri' => soc_redirect_uri(), 'state' => $state]);
    }
    return 'https://www.instagram.com/oauth/authorize?' . http_build_query(['enable_fb_login' => '0', 'force_authentication' => '1',
        'client_id' => get_config('ig_app_id', ''), 'redirect_uri' => soc_redirect_uri(), 'response_type' => 'code', 'scope' => SOC_IG_ESCOPOS, 'state' => $state]);
}

function soc_ig_erro($r, $padrao = 'O Instagram recusou.') {
    if ($r['erro_rede']) return 'Sem conexão com o Instagram agora (' . $r['erro_rede'] . ').';
    $e = $r['json']['error'] ?? [];
    $msg = is_array($e) ? (string)($e['error_user_msg'] ?? $e['message'] ?? '') : (string)($r['json']['error_message'] ?? $e);
    $cod = (int)(is_array($e) ? ($e['code'] ?? 0) : 0);
    $sub = (int)(is_array($e) ? ($e['error_subcode'] ?? 0) : 0);
    if ($cod === 190) return 'A conexão com o Instagram expirou ou foi removida. Clique em "Conectar Instagram" de novo.';
    if ($cod === 10 || $cod === 200) return 'Falta permissão no app do Instagram (' . ($msg ?: 'permissão') . '). Conecte de novo e aceite todas as permissões.';
    if ($sub === 2207042 || $cod === 9) return 'Limite de posts por dia do Instagram atingido. Tenta de novo amanhã.';
    if (in_array($sub, [2207026, 2207004, 2207005, 2207009], true)) return 'O Instagram não aceitou o formato do arquivo (' . $msg . ').';
    return $msg !== '' ? 'Instagram: ' . $msg : $padrao . ' (código ' . $r['code'] . ')';
}

function soc_ig_req($metodo, $caminho, $dados = null, $timeout = 30, $json = false) {
    return soc_http($metodo, soc_ig_graph() . '/' . SOC_IG_VERSAO . '/' . ltrim($caminho, '/'), $dados, ['token' => get_config('ig_token', ''), 'timeout' => $timeout, 'json' => $json]);
}

// Troca o código do login pelo token de 60 dias e guarda a conta
function soc_ig_conectar($code) {
    if (soc_ig_unificado()) return soc_ig_conectar_unificado($code);
    $r = soc_http('POST', soc_ig_api() . '/oauth/access_token', ['client_id' => get_config('ig_app_id', ''), 'client_secret' => get_config('ig_app_secret', ''),
        'grant_type' => 'authorization_code', 'redirect_uri' => soc_redirect_uri(), 'code' => $code]);
    $j = $r['json']['data'][0] ?? $r['json'];
    $curto = (string)($j['access_token'] ?? '');
    if ($curto === '') return ['ok' => false, 'erro' => soc_ig_erro($r, 'O Instagram não liberou o acesso.')];
    $l = soc_http('GET', soc_ig_graph() . '/access_token?' . http_build_query(['grant_type' => 'ig_exchange_token', 'client_secret' => get_config('ig_app_secret', ''), 'access_token' => $curto]));
    $token = (string)($l['json']['access_token'] ?? '') ?: $curto;
    $expira = time() + (int)($l['json']['expires_in'] ?? 3600);
    $me = soc_http('GET', soc_ig_graph() . '/' . SOC_IG_VERSAO . '/me?fields=user_id,username,name,followers_count,media_count,account_type', null, ['token' => $token]);
    $uid = (string)($me['json']['user_id'] ?? $j['user_id'] ?? '');
    if ($uid === '') return ['ok' => false, 'erro' => soc_ig_erro($me, 'Não consegui ler a sua conta do Instagram.')];
    set_config('ig_token', $token);
    set_config('ig_user_id', $uid);
    set_config('ig_usuario', (string)($me['json']['username'] ?? ''));
    set_config('ig_seguidores', (string)(int)($me['json']['followers_count'] ?? 0));
    set_config('ig_token_expira', (string)$expira);
    set_config('ig_conectado_em', (string)time());
    set_config('ig_erro', '');
    // Comentários e mensagens chegam no webhook (comentário vira cliente)
    $s = soc_ig_req('POST', 'me/subscribed_apps', ['subscribed_fields' => 'comments,messages']);
    set_config('ig_webhook_assinado', !empty($s['json']['success']) ? '1' : '0');
    return ['ok' => true, 'usuario' => get_config('ig_usuario', '')];
}

// Login unificado: código → token (longo) → descobre a conta do Instagram liberada no login
function soc_ig_conectar_unificado($code) {
    $fb = soc_fb_graph() . '/' . SOC_IG_VERSAO;
    $app = ['client_id' => get_config('ig_app_id', ''), 'client_secret' => get_config('ig_app_secret', '')];
    $r = soc_http('GET', $fb . '/oauth/access_token?' . http_build_query($app + ['redirect_uri' => soc_redirect_uri(), 'code' => $code]));
    $token = (string)($r['json']['access_token'] ?? '');
    if ($token === '') return ['ok' => false, 'erro' => soc_ig_erro($r, 'A Meta não liberou o acesso.')];
    $expira = !empty($r['json']['expires_in']) ? time() + (int)$r['json']['expires_in'] : 0;
    $l = soc_http('GET', $fb . '/oauth/access_token?' . http_build_query($app + ['grant_type' => 'fb_exchange_token', 'fb_exchange_token' => $token]));
    if (!empty($l['json']['access_token'])) { $token = (string)$l['json']['access_token']; $expira = !empty($l['json']['expires_in']) ? time() + (int)$l['json']['expires_in'] : 0; }
    // Qual conta do Instagram a pessoa liberou: 1) permissões do token  2) Página ligada ao Instagram
    $igId = ''; $pagina = ''; $tokPagina = '';
    $d = soc_http('GET', $fb . '/debug_token?' . http_build_query(['input_token' => $token, 'access_token' => $app['client_id'] . '|' . $app['client_secret']]));
    foreach ((array)($d['json']['data']['granular_scopes'] ?? []) as $g) {
        if (str_starts_with((string)($g['scope'] ?? ''), 'instagram') && !empty($g['target_ids'][0])) { $igId = (string)$g['target_ids'][0]; break; }
    }
    $pgs = soc_http('GET', $fb . '/me/accounts?fields=id,name,access_token,instagram_business_account{id,username}&limit=50', null, ['token' => $token]);
    foreach ((array)($pgs['json']['data'] ?? []) as $pg) {
        $ib = (string)($pg['instagram_business_account']['id'] ?? '');
        if ($ib !== '' && ($igId === '' || $ib === $igId)) { $igId = $ib; $pagina = (string)$pg['id']; $tokPagina = (string)($pg['access_token'] ?? ''); break; }
    }
    if ($igId === '') return ['ok' => false, 'erro' => 'Entrou, mas nenhuma conta do Instagram foi liberada. Conecte de novo e marque a sua conta profissional na tela da Meta (e confira se ela está na configuração do login unificado).'];
    $me = soc_http('GET', $fb . '/' . $igId . '?fields=id,username,followers_count,media_count', null, ['token' => $token]);
    set_config('ig_token', $token);
    set_config('ig_user_id', $igId);
    set_config('ig_usuario', (string)($me['json']['username'] ?? ''));
    set_config('ig_seguidores', (string)(int)($me['json']['followers_count'] ?? 0));
    set_config('ig_token_expira', (string)$expira);
    set_config('ig_conectado_em', (string)time());
    set_config('ig_page_id', $pagina);
    set_config('ig_page_token', $tokPagina);
    set_config('ig_erro', '');
    // Webhook: pela Página (quando tem) ou pela própria conta do Instagram
    $s = $pagina !== '' && $tokPagina !== ''
        ? soc_http('POST', $fb . '/' . $pagina . '/subscribed_apps', ['subscribed_fields' => 'feed'], ['token' => $tokPagina])
        : soc_ig_req('POST', $igId . '/subscribed_apps', ['subscribed_fields' => 'comments,messages']);
    set_config('ig_webhook_assinado', !empty($s['json']['success']) ? '1' : '0');
    return ['ok' => true, 'usuario' => get_config('ig_usuario', '') ?: $igId];
}

function soc_ig_desconectar() {
    foreach (['ig_token', 'ig_user_id', 'ig_usuario', 'ig_seguidores', 'ig_token_expira', 'ig_conectado_em', 'ig_webhook_assinado', 'ig_erro', 'ig_page_id', 'ig_page_token'] as $k) set_config($k, '');
}

// O token dura 60 dias; renova quando faltam menos de 15 (precisa ter mais de 24 h)
function soc_ig_renovar() {
    if (!soc_ig_conectado()) return false;
    $exp = (int)get_config('ig_token_expira', '0');
    if (soc_ig_unificado()) {
        if ($exp === 0 || $exp - time() > 15 * 86400 || time() - (int)get_config('ig_renovado_tentativa', '0') < 6 * 3600) return false;
        set_config('ig_renovado_tentativa', (string)time());
        $r = soc_http('GET', soc_fb_graph() . '/' . SOC_IG_VERSAO . '/oauth/access_token?' . http_build_query(['grant_type' => 'fb_exchange_token',
            'client_id' => get_config('ig_app_id', ''), 'client_secret' => get_config('ig_app_secret', ''), 'fb_exchange_token' => get_config('ig_token', '')]));
        if (empty($r['json']['access_token'])) { set_config('ig_erro', soc_ig_erro($r)); return false; }
        set_config('ig_token', $r['json']['access_token']);
        set_config('ig_token_expira', (string)(!empty($r['json']['expires_in']) ? time() + (int)$r['json']['expires_in'] : 0));
        return true;
    }
    if ($exp - time() > 15 * 86400 || time() - (int)get_config('ig_conectado_em', '0') < 86400) return false;
    if (time() - (int)get_config('ig_renovado_tentativa', '0') < 6 * 3600) return false;
    set_config('ig_renovado_tentativa', (string)time());
    $r = soc_http('GET', soc_ig_graph() . '/refresh_access_token?' . http_build_query(['grant_type' => 'ig_refresh_token', 'access_token' => get_config('ig_token', '')]));
    if (empty($r['json']['access_token'])) { set_config('ig_erro', soc_ig_erro($r)); return false; }
    set_config('ig_token', $r['json']['access_token']);
    set_config('ig_token_expira', (string)(time() + (int)($r['json']['expires_in'] ?? 5184000)));
    set_config('ig_conectado_em', (string)time());
    return true;
}

// Cria o "contêiner" de mídia (passo 1 da publicação)
function soc_ig_container($dados) {
    $r = soc_ig_req('POST', get_config('ig_user_id', '') . '/media', $dados, 60);
    return !empty($r['json']['id']) ? ['ok' => true, 'id' => (string)$r['json']['id']] : ['ok' => false, 'erro' => soc_ig_erro($r)];
}
function soc_ig_status($container) {
    $r = soc_ig_req('GET', $container . '?fields=status_code,status');
    return ['codigo' => (string)($r['json']['status_code'] ?? ($r['code'] >= 400 ? 'ERROR' : '')), 'detalhe' => (string)($r['json']['status'] ?? ''), 'erro' => $r['code'] >= 400 ? soc_ig_erro($r) : ''];
}
function soc_ig_publicar_container($container) {
    $r = soc_ig_req('POST', get_config('ig_user_id', '') . '/media_publish', ['creation_id' => $container], 60);
    if (empty($r['json']['id'])) return ['ok' => false, 'erro' => soc_ig_erro($r)];
    $id = (string)$r['json']['id'];
    $l = soc_ig_req('GET', $id . '?fields=permalink');
    return ['ok' => true, 'id' => $id, 'link' => (string)($l['json']['permalink'] ?? '')];
}

// ======================= TIKTOK =======================
function soc_tt_app_ok() { return get_config('tt_client_key', '') !== '' && get_config('tt_client_secret', '') !== ''; }
function soc_tt_conectado() { return get_config('tt_refresh', '') !== '' || get_config('tt_token', '') !== ''; }
function soc_tt_modo() { return get_config('tt_modo', 'rascunho') === 'direto' ? 'direto' : 'rascunho'; }

function soc_tt_link_autorizar($state) {
    return 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query(['client_key' => get_config('tt_client_key', ''), 'scope' => soc_tt_escopos(),
        'response_type' => 'code', 'redirect_uri' => soc_redirect_uri(), 'state' => $state]);
}

function soc_tt_erro($r, $padrao = 'O TikTok recusou.') {
    if ($r['erro_rede']) return 'Sem conexão com o TikTok agora (' . $r['erro_rede'] . ').';
    $e = $r['json']['error'] ?? [];
    $cod = is_array($e) ? (string)($e['code'] ?? '') : (string)($r['json']['error'] ?? '');
    $msg = is_array($e) ? (string)($e['message'] ?? '') : (string)($r['json']['error_description'] ?? '');
    $mapa = ['access_token_invalid' => 'A conexão com o TikTok expirou. Clique em "Conectar TikTok" de novo.',
        'scope_not_authorized' => 'Falta permissão no app do TikTok (video.upload / video.publish). Conecte de novo.',
        'unaudited_client_can_only_post_to_private_accounts' => 'O seu app do TikTok ainda não foi auditado: use o modo "Rascunho no app" ou deixe a conta privada.',
        'spam_risk_too_many_posts' => 'O TikTok limitou: muitos posts hoje. Tenta amanhã.',
        'spam_risk_too_many_pending_share' => 'Já tem 5 rascunhos esperando no app do TikTok: publique ou apague alguns.',
        'url_ownership_unverified' => 'O TikTok pede para verificar o seu domínio (Developer Portal → URL properties) para postar fotos.',
        'rate_limit_exceeded' => 'Muitas tentativas seguidas no TikTok. Tenta daqui a pouco.'];
    if (isset($mapa[$cod])) return $mapa[$cod];
    return ($cod !== '' && $cod !== 'ok') || $msg !== '' ? 'TikTok: ' . trim($cod . ' ' . $msg) : $padrao . ' (código ' . $r['code'] . ')';
}
function soc_tt_ok($r) { return $r['code'] < 400 && (($r['json']['error']['code'] ?? 'ok') === 'ok'); }

function soc_tt_conectar($code) {
    $r = soc_http('POST', soc_tt_api() . '/v2/oauth/token/', ['client_key' => get_config('tt_client_key', ''), 'client_secret' => get_config('tt_client_secret', ''),
        'code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => soc_redirect_uri()], ['headers' => ['Content-Type: application/x-www-form-urlencoded']]);
    if (empty($r['json']['access_token'])) return ['ok' => false, 'erro' => soc_tt_erro($r, 'O TikTok não liberou o acesso.')];
    soc_tt_guardar_token($r['json']);
    $u = soc_http('GET', soc_tt_api() . '/v2/user/info/?fields=open_id,display_name,avatar_url', null, ['token' => $r['json']['access_token']]);
    set_config('tt_usuario', (string)($u['json']['data']['user']['display_name'] ?? ''));
    set_config('tt_erro', '');
    return ['ok' => true, 'usuario' => get_config('tt_usuario', '')];
}
function soc_tt_guardar_token($j) {
    set_config('tt_token', (string)$j['access_token']);
    set_config('tt_token_expira', (string)(time() + (int)($j['expires_in'] ?? 86400)));
    if (!empty($j['refresh_token'])) { set_config('tt_refresh', (string)$j['refresh_token']); set_config('tt_refresh_expira', (string)(time() + (int)($j['refresh_expires_in'] ?? 31536000))); }
    if (!empty($j['open_id'])) set_config('tt_open_id', (string)$j['open_id']);
}
function soc_tt_desconectar() {
    foreach (['tt_token', 'tt_token_expira', 'tt_refresh', 'tt_refresh_expira', 'tt_open_id', 'tt_usuario', 'tt_erro'] as $k) set_config($k, '');
}
// Token do TikTok vale 24 h: renova sozinho com o refresh (vale 1 ano)
function soc_tt_token() {
    if ((int)get_config('tt_token_expira', '0') > time() + 300 && get_config('tt_token', '') !== '') return get_config('tt_token', '');
    if (get_config('tt_refresh', '') === '') return '';
    $r = soc_http('POST', soc_tt_api() . '/v2/oauth/token/', ['client_key' => get_config('tt_client_key', ''), 'client_secret' => get_config('tt_client_secret', ''),
        'grant_type' => 'refresh_token', 'refresh_token' => get_config('tt_refresh', '')], ['headers' => ['Content-Type: application/x-www-form-urlencoded']]);
    if (empty($r['json']['access_token'])) { set_config('tt_erro', soc_tt_erro($r)); return ''; }
    soc_tt_guardar_token($r['json']);
    return get_config('tt_token', '');
}
function soc_tt_req($caminho, $dados) {
    $tok = soc_tt_token();
    if ($tok === '') return ['code' => 401, 'json' => ['error' => ['code' => 'access_token_invalid']], 'corpo' => '', 'erro_rede' => ''];
    return soc_http('POST', soc_tt_api() . $caminho, $dados, ['token' => $tok, 'json' => true, 'timeout' => 40]);
}
// Privacidade permitida (sem auditoria só vem SELF_ONLY)
function soc_tt_privacidade() {
    $r = soc_tt_req('/v2/post/publish/creator_info/query/', new stdClass());
    $ops = (array)($r['json']['data']['privacy_level_options'] ?? []);
    foreach (['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY'] as $p) if (in_array($p, $ops, true)) return $p;
    return 'SELF_ONLY';
}

// Vídeo (Reels → TikTok): envia o arquivo direto (não precisa verificar domínio)
function soc_tt_enviar_video($arquivo, $legenda) {
    $tam = is_file($arquivo) ? filesize($arquivo) : 0;
    if ($tam < 1000) return ['ok' => false, 'erro' => 'O vídeo não está no servidor. Clique em "Postar sozinho" de novo para preparar.'];
    if ($tam > 64 * 1024 * 1024) return ['ok' => false, 'erro' => 'Vídeo maior que 64 MB.'];
    $fonte = ['source' => 'FILE_UPLOAD', 'video_size' => $tam, 'chunk_size' => $tam, 'total_chunk_count' => 1];
    if (soc_tt_modo() === 'direto') {
        $r = soc_tt_req('/v2/post/publish/video/init/', ['post_info' => ['title' => mb_substr($legenda, 0, 2200), 'privacy_level' => soc_tt_privacidade(),
            'disable_duet' => false, 'disable_comment' => false, 'disable_stitch' => false, 'video_cover_timestamp_ms' => 1000], 'source_info' => $fonte]);
    } else {
        $r = soc_tt_req('/v2/post/publish/inbox/video/init/', ['source_info' => $fonte]);
    }
    if (!soc_tt_ok($r) || empty($r['json']['data']['upload_url'])) return ['ok' => false, 'erro' => soc_tt_erro($r)];
    $tipo = str_ends_with(strtolower($arquivo), '.webm') ? 'video/webm' : 'video/mp4';
    $up = soc_http('PUT', $r['json']['data']['upload_url'], null, ['arquivo' => $arquivo, 'timeout' => 180,
        'headers' => ['Content-Type: ' . $tipo, 'Content-Range: bytes 0-' . ($tam - 1) . '/' . $tam]]);
    if ($up['code'] >= 400 || $up['erro_rede']) return ['ok' => false, 'erro' => 'O TikTok não recebeu o vídeo (código ' . $up['code'] . ').'];
    return ['ok' => true, 'publish_id' => (string)$r['json']['data']['publish_id']];
}
// Fotos (Carrossel/Post → modo foto do TikTok, que tem alcance alto)
function soc_tt_enviar_fotos($urls, $titulo, $legenda) {
    $direto = soc_tt_modo() === 'direto';
    $info = ['title' => mb_substr($titulo, 0, 90), 'description' => mb_substr($legenda, 0, 4000), 'disable_comment' => false, 'auto_add_music' => true];
    if ($direto) $info['privacy_level'] = soc_tt_privacidade();
    $r = soc_tt_req('/v2/post/publish/content/init/', ['post_info' => $info, 'source_info' => ['source' => 'PULL_FROM_URL', 'photo_cover_index' => 0, 'photo_images' => array_values(array_slice($urls, 0, 35))],
        'post_mode' => $direto ? 'DIRECT_POST' : 'MEDIA_UPLOAD', 'media_type' => 'PHOTO']);
    if (!soc_tt_ok($r) || empty($r['json']['data']['publish_id'])) return ['ok' => false, 'erro' => soc_tt_erro($r)];
    return ['ok' => true, 'publish_id' => (string)$r['json']['data']['publish_id']];
}
function soc_tt_status($publishId) {
    $r = soc_tt_req('/v2/post/publish/status/fetch/', ['publish_id' => $publishId]);
    $d = $r['json']['data'] ?? [];
    return ['status' => (string)($d['status'] ?? ''), 'erro' => soc_tt_ok($r) ? (string)($d['fail_reason'] ?? '') : soc_tt_erro($r), 'post_id' => (string)(($d['publicaly_available_post_id'] ?? [])[0] ?? '')];
}

// ======================= PUBLICAÇÃO DOS ITENS DO PLANO =======================
function soc_redes_ativas() {
    $r = [];
    if (soc_ig_conectado()) $r[] = 'instagram';
    if (soc_tt_app_ok() && soc_tt_conectado()) $r[] = 'tiktok';
    return $r;
}
function soc_midia($it) { return json_decode((string)($it['midia'] ?? ''), true) ?: []; }
function soc_pub($it) { return json_decode((string)($it['pub'] ?? ''), true) ?: []; }
function soc_salvar_pub($id, $pub) {
    global $pdo;
    $pdo->prepare("UPDATE marketing_itens SET pub=?, atualizado_em=NOW() WHERE id=?")->execute([json_encode($pub, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $id]);
}
function soc_abs($url) { return preg_match('#^https?://#', (string)$url) ? $url : soc_url_base() . '/' . ltrim((string)$url, '/'); }
function soc_caminho($url) {
    $p = parse_url((string)$url, PHP_URL_PATH) ?: '';
    if (!str_starts_with($p, '/uploads/mkt/')) return '';
    $real = realpath(__DIR__ . '/..' . $p);
    $base = realpath(__DIR__ . '/../uploads/mkt');
    return $real && $base && str_starts_with($real, $base . DIRECTORY_SEPARATOR) ? $real : '';
}
function soc_legenda($post, $rede = 'instagram') {
    $tags = array_slice((array)($post['hashtags'] ?? []), 0, 5);
    if ($rede === 'tiktok') {
        $t = trim((string)($post['legenda_tiktok'] ?? '')) ?: trim((string)($post['legenda'] ?? ''));
        return trim($t . "\n\n" . implode(' ', array_map(fn($h) => '#' . $h, array_slice($tags, 0, 4))));
    }
    return trim((string)($post['legenda'] ?? '') . "\n\n" . implode(' ', array_map(fn($h) => '#' . $h, $tags)));
}
// Stories que precisam de adesivo (enquete, pergunta…) não dá para postar pela API
function soc_story_precisa_mao($s) { return in_array($s['adesivo'] ?? 'nenhum', ['enquete', 'pergunta', 'quiz', 'contagem', 'link'], true); }

// Publica (ou continua publicando) UM item numa rede. Devolve o estado daquela rede.
function soc_publicar_rede($it, $rede, $agora = false) {
    $post = json_decode((string)$it['post'], true) ?: [];
    $m = soc_midia($it);
    $pub = soc_pub($it);
    $e = $pub[$rede] ?? ['status' => 'aguardando', 'tentativas' => 0];
    if (in_array($e['status'], ['publicado', 'manual', 'rascunho', 'parcial', 'erro'], true)) return $e;
    $falha = function ($msg, $definitivo = false) use (&$e) {
        $e['tentativas'] = (int)($e['tentativas'] ?? 0) + 1;
        $e['erro'] = $msg;
        $e['status'] = $definitivo || $e['tentativas'] >= 3 ? 'erro' : 'aguardando';
        unset($e['container']);
        return $e;
    };
    if ($rede === 'instagram') {
        // Continua um Reels/Carrossel que o Instagram ainda estava processando
        if (!empty($e['container'])) {
            $s = soc_ig_status($e['container']);
            if ($s['codigo'] === 'IN_PROGRESS' || $s['codigo'] === '') {
                if (time() - (int)($e['desde'] ?? time()) > 1800) return $falha('O Instagram demorou demais para processar o vídeo.');
                return $e;
            }
            if ($s['codigo'] !== 'FINISHED') return $falha($s['erro'] ?: 'O Instagram não aceitou a mídia (' . ($s['detalhe'] ?: $s['codigo']) . ').', $s['codigo'] === 'ERROR');
            $p = soc_ig_publicar_container($e['container']);
            if (!$p['ok']) return $falha($p['erro']);
            return ['status' => 'publicado', 'media_id' => $p['id'], 'link' => $p['link'], 'em' => date('c')];
        }
        $leg = soc_legenda($post, 'instagram');
        if ($it['tipo'] === 'story') {
            // Cada story na sua hora; os com adesivo ficam para você (lembrete)
            $horas = function_exists('mkt_horarios_story') ? mkt_horarios_story(count((array)($post['stories'] ?? []))) : ['08:30'];
            $feitos = (array)($e['stories'] ?? []);
            $pend = 0;
            foreach ((array)($post['stories'] ?? []) as $i => $s) {
                if (isset($feitos[$i])) continue;
                if (soc_story_precisa_mao($s)) { $feitos[$i] = ['status' => 'manual']; continue; }
                $url = $m['stories'][$i] ?? '';
                if (!$url) { $feitos[$i] = ['status' => 'manual', 'erro' => 'sem arte no servidor']; continue; }
                if (!$agora && strtotime($it['data'] . ' ' . ($horas[$i] ?? $it['hora'])) > time()) { $pend++; continue; }
                $c = soc_ig_container(['media_type' => 'STORIES', 'image_url' => soc_abs($url)]);
                $p = $c['ok'] ? soc_ig_publicar_container($c['id']) : $c;
                $feitos[$i] = $p['ok'] ? ['status' => 'publicado', 'media_id' => $p['id'], 'em' => date('c')] : ['status' => 'erro', 'erro' => $p['erro']];
            }
            $e['stories'] = $feitos;
            $sts = array_column($feitos, 'status');
            if ($pend > 0) { $e['status'] = 'aguardando'; return $e; }
            $e['status'] = in_array('publicado', $sts, true) ? (in_array('manual', $sts, true) ? 'parcial' : 'publicado') : (in_array('erro', $sts, true) ? 'erro' : 'manual');
            if ($e['status'] === 'erro') $e['erro'] = implode(' · ', array_unique(array_filter(array_column($feitos, 'erro'))));
            $e['em'] = date('c');
            return $e;
        }
        if ($it['tipo'] === 'reels') {
            $video = $m['video'] ?? '';
            if (!$video) return $falha('O vídeo ainda não foi preparado.', true);
            if (($m['video_tipo'] ?? '') !== 'mp4') return ['status' => 'manual', 'erro' => 'Seu navegador gravou o vídeo em WebM e o Instagram só aceita MP4. Poste este pelo celular (o lembrete chega no WhatsApp) ou use o Chrome atualizado.'];
            $d = ['media_type' => 'REELS', 'video_url' => soc_abs($video), 'caption' => $leg, 'share_to_feed' => 'true'];
            if (!empty($m['capa'])) $d['cover_url'] = soc_abs($m['capa']);
            $c = soc_ig_container($d);
        } elseif ($it['tipo'] === 'carrossel') {
            $filhos = [];
            foreach (array_slice((array)($m['imagens'] ?? []), 0, 10) as $u) {
                $f = soc_ig_container(['image_url' => soc_abs($u), 'is_carousel_item' => 'true']);
                if (!$f['ok']) return $falha($f['erro']);
                $filhos[] = $f['id'];
            }
            if (count($filhos) < 2) return $falha('O carrossel precisa de pelo menos 2 imagens preparadas.', true);
            $c = soc_ig_container(['media_type' => 'CAROUSEL', 'children' => implode(',', $filhos), 'caption' => $leg]);
        } else {
            $img = $m['imagens'][0] ?? '';
            if (!$img) return $falha('A arte ainda não foi preparada.', true);
            $c = soc_ig_container(['image_url' => soc_abs($img), 'caption' => $leg]);
        }
        if (!$c['ok']) return $falha($c['erro']);
        $e = ['status' => 'processando', 'container' => $c['id'], 'desde' => time(), 'tentativas' => (int)($e['tentativas'] ?? 0)];
        // Imagens costumam ficar prontas na hora: tenta publicar já
        $s = soc_ig_status($c['id']);
        if ($s['codigo'] === 'FINISHED') {
            $p = soc_ig_publicar_container($c['id']);
            if (!$p['ok']) return $falha($p['erro']);
            return ['status' => 'publicado', 'media_id' => $p['id'], 'link' => $p['link'], 'em' => date('c')];
        }
        if ($s['codigo'] === 'ERROR' || $s['codigo'] === 'EXPIRED') return $falha($s['erro'] ?: 'O Instagram não aceitou a mídia (' . $s['detalhe'] . ').', true);
        return $e;
    }
    // ---- TikTok ----
    if ($it['tipo'] === 'story') return ['status' => 'manual', 'erro' => 'O TikTok não tem stories pela API.'];
    if (!empty($e['publish_id'])) {
        $s = soc_tt_status($e['publish_id']);
        if ($s['status'] === 'PUBLISH_COMPLETE') return ['status' => 'publicado', 'publish_id' => $e['publish_id'], 'link' => $s['post_id'] ? 'https://www.tiktok.com/video/' . $s['post_id'] : '', 'em' => date('c')];
        if ($s['status'] === 'SEND_TO_USER_INBOX') return ['status' => 'rascunho', 'publish_id' => $e['publish_id'], 'em' => date('c')];
        if ($s['status'] === 'FAILED') return $falha('O TikTok recusou: ' . ($s['erro'] ?: 'falhou'), true);
        if (time() - (int)($e['desde'] ?? time()) > 3600) return $falha('O TikTok demorou demais para processar.', true);
        return $e;
    }
    $leg = soc_legenda($post, 'tiktok');
    if ($it['tipo'] === 'reels') {
        $arq = soc_caminho($m['video'] ?? '');
        $r = $arq ? soc_tt_enviar_video($arq, $leg) : ['ok' => false, 'erro' => 'O vídeo ainda não foi preparado.'];
    } else {
        $urls = array_map('soc_abs', (array)($m['imagens'] ?? []));
        $titulo = trim(str_replace('*', '', (string)($post['conteudo']['titulo'] ?? $it['tema'] ?? '')));
        $r = $urls ? soc_tt_enviar_fotos($urls, $titulo, $leg) : ['ok' => false, 'erro' => 'A arte ainda não foi preparada.'];
    }
    if (!$r['ok']) return $falha($r['erro']);
    return ['status' => 'processando', 'publish_id' => $r['publish_id'], 'desde' => time(), 'tentativas' => (int)($e['tentativas'] ?? 0)];
}

// Publica o item em todas as redes conectadas e atualiza o status do item
function soc_publicar_item($it, $agora = false) {
    global $pdo;
    $pub = soc_pub($it);
    $antes = $pub;
    foreach (soc_redes_ativas() as $rede) {
        if (!in_array($rede, soc_redes_do_item($it), true)) continue;
        try { $pub[$rede] = soc_publicar_rede($it, $rede, $agora); }
        catch (Throwable $e) { $pub[$rede] = ['status' => 'erro', 'erro' => mb_substr($e->getMessage(), 0, 200)]; }
        $it['pub'] = json_encode($pub);
    }
    soc_salvar_pub($it['id'], $pub);
    $ig = $pub['instagram'] ?? [];
    if (!empty($ig['media_id']) && empty($it['ig_media_id'])) $pdo->prepare("UPDATE marketing_itens SET ig_media_id=? WHERE id=?")->execute([$ig['media_id'], $it['id']]);
    $sts = array_map(fn($r) => $r['status'] ?? '', array_intersect_key($pub, array_flip(['instagram', 'tiktok'])));
    $fim = $sts && !array_intersect($sts, ['aguardando', 'processando']);
    $algum = (bool)array_intersect($sts, ['publicado', 'parcial', 'rascunho']);
    if ($fim && $algum) {
        $pdo->prepare("UPDATE marketing_itens SET status='postado', postado_em=COALESCE(postado_em, NOW()) WHERE id=? AND status='pronto'")->execute([$it['id']]);
        if (json_encode($antes) !== json_encode($pub)) soc_avisar_publicado($it, $pub);
    } elseif ($fim && $sts) {
        // Nada saiu sozinho (erro ou formato que só dá à mão): para de tentar e avisa para postar você
        $pdo->prepare("UPDATE marketing_itens SET publicar=false WHERE id=?")->execute([$it['id']]);
        soc_avisar_falha($it, $pub);
    }
    return $pub;
}
function soc_redes_do_item($it) {
    $esc = array_filter(explode(',', get_config('mkt_postar_em', 'instagram,tiktok')));
    return $it['tipo'] === 'story' ? array_values(array_intersect($esc, ['instagram'])) : array_values($esc);
}

// Aviso no seu WhatsApp: postei, e a dica de ouro da 1ª hora
function soc_avisar_publicado($it, $pub) {
    if (!function_exists('prosp_alertar') && is_file(__DIR__ . '/prospeccao.php')) require_once __DIR__ . '/prospeccao.php';
    if (!function_exists('prosp_alertar') || get_config('mkt_lembrete', '1') !== '1') return;
    $linhas = [];
    if (in_array($pub['instagram']['status'] ?? '', ['publicado', 'parcial'], true)) $linhas[] = '📸 Instagram: ' . (($pub['instagram']['link'] ?? '') ?: 'publicado');
    if (($pub['tiktok']['status'] ?? '') === 'publicado') $linhas[] = '🎵 TikTok: ' . (($pub['tiktok']['link'] ?? '') ?: 'publicado');
    if (($pub['tiktok']['status'] ?? '') === 'rascunho') $linhas[] = '🎵 TikTok: está nos rascunhos do app. Abra, coloque uma música em alta e publique.';
    if (($pub['instagram']['status'] ?? '') === 'parcial') $linhas[] = '✋ Os stories com adesivo (enquete/pergunta) são com você: o lembrete chega na hora.';
    if (!$linhas) return;
    prosp_alertar("✅ Postei sozinho: " . mb_strimwidth((string)$it['tema'], 0, 80, '…') . "\n" . implode("\n", $linhas)
        . "\n\n💡 Responda os comentários na 1ª hora: o algoritmo entrega mais para posts com conversa.");
}

function soc_avisar_falha($it, $pub) {
    if (!function_exists('prosp_alertar') && is_file(__DIR__ . '/prospeccao.php')) require_once __DIR__ . '/prospeccao.php';
    if (!function_exists('prosp_alertar') || get_config('mkt_lembrete', '1') !== '1') return;
    $motivos = array_filter(array_map(fn($r, $k) => !empty($r['erro']) ? ucfirst($k) . ': ' . $r['erro'] : '', $pub, array_keys($pub)));
    $link = soc_url_base() . '/admin/marketing.php?plano=' . (int)$it['plano_id'] . '#item-' . (int)$it['id'];
    prosp_alertar("✋ Não deu para postar sozinho: " . mb_strimwidth((string)$it['tema'], 0, 80, '…') . "
" . implode("
", $motivos) . "

Poste você (arte, vídeo e legenda prontos): $link");
}

// Trava simples no banco: cron, carona da ponte e "Postar agora" nunca publicam o mesmo post 2 vezes
function soc_travar($segundos = 300) {
    global $pdo;
    $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('soc_trava', '0') ON CONFLICT (chave) DO NOTHING");
    $st = $pdo->prepare("UPDATE configuracoes SET valor=? WHERE chave='soc_trava' AND COALESCE(NULLIF(valor,''),'0')::bigint < ?");
    $st->execute([(string)time(), time() - $segundos]);
    return $st->rowCount() === 1;
}
function soc_destravar() { global $pdo; $pdo->exec("UPDATE configuracoes SET valor='0' WHERE chave='soc_trava'"); }

// Tem post para publicar agora? (consulta leve, para a carona da ponte e a rotina do painel)
function soc_tem_devido() {
    global $pdo;
    if (!soc_redes_ativas()) return false;
    try {
        return (bool)$pdo->query("SELECT 1 FROM marketing_itens WHERE publicar AND midia IS NOT NULL AND status IN ('pronto','postado')
            AND (data + hora::time) <= NOW() + INTERVAL '1 minute' AND data >= CURRENT_DATE - 2
            AND (status='pronto' OR pub LIKE '%\"aguardando\"%' OR pub LIKE '%\"processando\"%') LIMIT 1")->fetchColumn();
    } catch (PDOException $e) { return false; }
}

// Cron / carona: publica o que chegou a hora (e continua os que estão processando)
function soc_rotina() {
    global $pdo;
    $res = ['soc_pub' => 0];
    if (!soc_redes_ativas()) return $res;
    if (!soc_travar()) return $res + ['soc' => 'ocupado'];
    try { return soc_rotina_trabalho($res); } finally { soc_destravar(); }
}
function soc_rotina_trabalho($res) {
    global $pdo;
    try { soc_ig_renovar(); } catch (Throwable $e) { /* tenta depois */ }
    $st = $pdo->query("SELECT * FROM marketing_itens WHERE publicar AND midia IS NOT NULL AND status IN ('pronto','postado')
        AND (data + hora::time) <= NOW() + INTERVAL '1 minute' AND data >= CURRENT_DATE - 2
        AND (pub IS NULL OR pub LIKE '%\"aguardando\"%' OR pub LIKE '%\"processando\"%' OR status='pronto')
        ORDER BY data, hora LIMIT 4");
    $t0 = time();
    foreach ($st->fetchAll() as $it) {
        if (time() - $t0 > 50) break;
        if ($it['status'] === 'postado' && !preg_match('/"(aguardando|processando)"/', (string)$it['pub'])) continue;
        soc_publicar_item($it);
        $res['soc_pub']++;
    }
    try { $res['soc_metricas'] = soc_ig_metricas(); } catch (Throwable $e) { $res['soc_metricas'] = 'erro'; }
    return $res;
}

// ======================= MÉTRICAS (a IA aprende) =======================
// Nota do post = (envios×3 + salvos×2 + comentários×1,5 + curtidas×0,5) por 100 contas alcançadas.
// Envios e tempo assistido são o que o Instagram mais usa para entregar a quem não te segue.
function soc_nota_real($m) {
    $alc = max(1, (int)($m['reach'] ?? 0));
    return round(((int)($m['shares'] ?? 0) * 3 + (int)($m['saved'] ?? 0) * 2 + (int)($m['comments'] ?? 0) * 1.5 + (int)($m['likes'] ?? 0) * 0.5) / $alc * 100, 2);
}
function soc_ig_ler_insights($mediaId) {
    foreach (['reach,likes,comments,shares,saved,total_interactions,views', 'reach,likes,comments,shares,saved'] as $metricas) {
        $r = soc_ig_req('GET', $mediaId . '/insights?metric=' . $metricas);
        if (!empty($r['json']['data'])) {
            $out = [];
            foreach ($r['json']['data'] as $d) $out[$d['name']] = (int)($d['total_value']['value'] ?? ($d['values'][0]['value'] ?? 0));
            return $out;
        }
    }
    return null;
}
function soc_ig_metricas() {
    global $pdo;
    if (!soc_ig_conectado()) return 0;
    $st = $pdo->query("SELECT id, ig_media_id FROM marketing_itens WHERE ig_media_id IS NOT NULL AND postado_em < NOW() - INTERVAL '20 hours'
        AND postado_em > NOW() - INTERVAL '10 days' AND (metricas_em IS NULL OR metricas_em < NOW() - INTERVAL '24 hours') ORDER BY metricas_em NULLS FIRST LIMIT 5");
    $n = 0;
    foreach ($st->fetchAll() as $it) {
        $m = soc_ig_ler_insights($it['ig_media_id']);
        $pdo->prepare("UPDATE marketing_itens SET metricas=COALESCE(?, metricas), nota_real=COALESCE(?, nota_real), metricas_em=NOW() WHERE id=?")
            ->execute([$m ? json_encode($m) : null, $m ? soc_nota_real($m) : null, $it['id']]);
        if ($m) $n++;
    }
    return $n;
}

// ======================= COMENTÁRIO VIRA CLIENTE =======================
function soc_normalizar($t) {
    $t = mb_strtoupper(trim((string)$t));
    $t = strtr($t, ['Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'É' => 'E', 'Ê' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ç' => 'C']);
    return preg_replace('/\s+/', ' ', $t);
}
// Palavras que mostram interesse de compra (além da palavra-chave do post)
function soc_tem_interesse($texto, $palavra = '') {
    $t = ' ' . preg_replace('/[^A-Z0-9 ]/', ' ', soc_normalizar($texto)) . ' ';
    $lista = array_filter(array_merge([soc_normalizar($palavra), soc_normalizar(get_config('mkt_palavra', 'QUERO'))],
        ['QUERO', 'EU QUERO', 'PRECO', 'QUANTO CUSTA', 'QUANTO E', 'VALOR', 'COMO FUNCIONA', 'TENHO INTERESSE', 'INTERESSE', 'ME MANDA', 'MANDA PRA MIM', 'COMO FACO', 'INFO']));
    foreach ($lista as $p) if ($p !== '' && str_contains($t, ' ' . preg_replace('/[^A-Z0-9 ]/', ' ', $p) . ' ')) return true;
    return false;
}
function soc_link_whats($origem) {
    $n = preg_replace('/\D/', '', get_config('mkt_whatsapp', ''));
    if ($n === '') return '';
    if (strlen($n) <= 11) $n = '55' . $n;
    return 'https://wa.me/' . $n . '?text=' . rawurlencode('Oi! Vim pelo Instagram' . ($origem ? " ($origem)" : '') . ' e quero saber mais.');
}
function soc_texto_dm($usuario, $post) {
    $modelo = get_config('mkt_dm_texto', '') ?: "Oi, {nome}! 👋 Vi seu comentário e já te mando: {isca}\n\nMe conta: qual é o seu negócio? Se preferir, me chama no WhatsApp: {whats}";
    $isca = trim((string)($post['dm_texto'] ?? '')) ?: (get_config('mkt_isca', '') ?: 'eu monto uma prévia GRÁTIS do site do seu negócio, com agendamento e WhatsApp automático, pra você ver como fica. Sem compromisso.');
    $txt = strtr($modelo, ['{nome}' => $usuario ? '@' . $usuario : 'tudo bem', '{isca}' => $isca, '{whats}' => soc_link_whats('post')]);
    return mb_substr(trim(preg_replace("/\n{3,}/", "\n\n", $txt)), 0, 950);
}

function soc_ig_comentario($v) {
    global $pdo;
    $cid = (string)($v['id'] ?? '');
    $texto = (string)($v['text'] ?? '');
    $de = $v['from'] ?? [];
    if ($cid === '' || (string)($de['id'] ?? '') === get_config('ig_user_id', '') || !empty($v['parent_id'])) return 'ignorado';
    $mediaId = (string)($v['media']['id'] ?? '');
    $item = null;
    if ($mediaId !== '') { $st = $pdo->prepare("SELECT * FROM marketing_itens WHERE ig_media_id=? LIMIT 1"); $st->execute([$mediaId]); $item = $st->fetch() ?: null; }
    $post = $item ? (json_decode((string)$item['post'], true) ?: []) : [];
    if (!soc_tem_interesse($texto, (string)($post['palavra_chave'] ?? ''))) return 'sem_interesse';
    try {
        $pdo->prepare("INSERT INTO marketing_leads (rede, origem, externo_id, usuario, usuario_id, texto, item_id, media_id) VALUES ('instagram','comentario',?,?,?,?,?,?)")
            ->execute([$cid, mb_substr((string)($de['username'] ?? ''), 0, 80), mb_substr((string)($de['id'] ?? ''), 0, 40), mb_substr($texto, 0, 1000), $item['id'] ?? null, $mediaId ?: null]);
        $leadId = (int)$pdo->lastInsertId();
    } catch (PDOException $e) { return 'repetido'; }
    if (get_config('mkt_dm_auto', '1') !== '1') return 'salvo';
    // 1) Resposta privada no direct (a Meta permite 1 por comentário, em até 7 dias)
    $r = soc_ig_enviar_msg(['recipient' => ['comment_id' => $cid], 'message' => ['text' => soc_texto_dm((string)($de['username'] ?? ''), $post)]]);
    $ok = !empty($r['json']['message_id']) || !empty($r['json']['recipient_id']);
    $pdo->prepare("UPDATE marketing_leads SET respondido=?, erro=? WHERE id=?")->execute([$ok, $ok ? null : mb_substr(soc_ig_erro($r), 0, 300), $leadId]);
    // 2) Resposta pública curta (mostra que você responde e gera mais conversa no post)
    if ($ok && get_config('mkt_resp_publica', '1') === '1') {
        $frases = ['Te chamei no direct! 📩', 'Mandei no seu direct, confere lá 😉', 'Acabei de te mandar no direct 🚀', 'Olha o direct! 📬'];
        soc_ig_req('POST', $cid . '/replies', ['message' => $frases[random_int(0, count($frases) - 1)]]);
    }
    soc_avisar_lead($de['username'] ?? '', $texto, $item, $ok);
    return $ok ? 'respondido' : 'erro';
}

// Mensagem no direct: palavra-chave (ex.: story "manda QUERO no direct") ou resposta de quem já é lead
function soc_ig_mensagem($m) {
    global $pdo;
    $msg = $m['message'] ?? [];
    $de = (string)($m['sender']['id'] ?? '');
    if (!empty($msg['is_echo']) || $de === '' || $de === get_config('ig_user_id', '') || empty($msg['text'])) return 'ignorado';
    $mid = (string)($msg['mid'] ?? '');
    $texto = (string)$msg['text'];
    $st = $pdo->prepare("SELECT * FROM marketing_leads WHERE usuario_id=? AND rede='instagram' ORDER BY id DESC LIMIT 1"); $st->execute([$de]);
    $lead = $st->fetch();
    if (!$lead) {
        if (!soc_tem_interesse($texto)) return 'sem_interesse';
        try {
            $pdo->prepare("INSERT INTO marketing_leads (rede, origem, externo_id, usuario_id, texto) VALUES ('instagram','direct',?,?,?)")->execute([$mid ?: null, $de, mb_substr($texto, 0, 1000)]);
        } catch (PDOException $e) { return 'repetido'; }
        $r = soc_ig_enviar_dm($de, soc_texto_dm('', []));
        $pdo->prepare("UPDATE marketing_leads SET respondido=?, erro=? WHERE id=?")->execute([$r['ok'], $r['ok'] ? null : $r['erro'], (int)$pdo->lastInsertId()]);
        soc_avisar_lead('', $texto, null, $r['ok']);
        return 'respondido';
    }
    // Já é lead: guarda a conversa; se mandou o WhatsApp, vira prospect quente na Prospecção
    $pdo->prepare("UPDATE marketing_leads SET texto=LEFT(COALESCE(texto,'') || E'\n↩ ' || ?, 4000), etapa=CASE WHEN etapa='novo' THEN 'conversando' ELSE etapa END WHERE id=?")->execute([mb_substr($texto, 0, 600), $lead['id']]);
    $fone = soc_achar_telefone($texto);
    if ($fone) {
        soc_lead_para_prospeccao($lead, $fone, $texto);
        soc_ig_enviar_dm($de, 'Perfeito! Já anotei aqui e te chamo no WhatsApp ' . substr($fone, 2) . ' rapidinho. 🙌');
        return 'whatsapp';
    }
    if (get_config('mkt_dm_ia', '1') === '1' && function_exists('ia_configurada') && ia_configurada()) {
        $resp = soc_ia_responder_dm($lead, $texto);
        if ($resp !== '') soc_ig_enviar_dm($de, $resp);
        return 'ia';
    }
    return 'salvo';
}
// Envia pelo direct: pela conta do Instagram; no login unificado com Página, tenta também pela Página
function soc_ig_enviar_msg($dados) {
    $r = soc_ig_req('POST', get_config('ig_user_id', '') . '/messages', $dados, 30, true);
    if (empty($r['json']['message_id']) && soc_ig_unificado() && get_config('ig_page_id', '') !== '' && get_config('ig_page_token', '') !== '') {
        $r = soc_http('POST', soc_fb_graph() . '/' . SOC_IG_VERSAO . '/' . get_config('ig_page_id', '') . '/messages', $dados, ['token' => get_config('ig_page_token', ''), 'json' => true]);
    }
    return $r;
}
function soc_ig_enviar_dm($igsid, $texto) {
    $r = soc_ig_enviar_msg(['recipient' => ['id' => $igsid], 'message' => ['text' => mb_substr($texto, 0, 950)]]);
    $ok = !empty($r['json']['message_id']);
    return ['ok' => $ok, 'erro' => $ok ? null : mb_substr(soc_ig_erro($r), 0, 300)];
}
function soc_achar_telefone($texto) {
    if (!preg_match('/(?:\+?55[\s.-]?)?\(?([1-9]{2})\)?[\s.-]?(9?\d{4})[\s.-]?(\d{4})/', $texto, $mm)) return '';
    $n = $mm[1] . $mm[2] . $mm[3];
    return strlen($n) >= 10 ? '55' . $n : '';
}
function soc_lead_para_prospeccao($lead, $fone, $texto) {
    global $pdo;
    try {
        $pdo->prepare("INSERT INTO prospects (origem_id, fonte, nome, whatsapp, telefone, status, observacoes, pontuacao) VALUES (?, 'instagram', ?, ?, ?, 'respondeu', ?, 90)
            ON CONFLICT (origem_id) DO UPDATE SET whatsapp=EXCLUDED.whatsapp, atualizado_em=NOW()")
            ->execute(['ig:' . $lead['usuario_id'], $lead['usuario'] ? '@' . $lead['usuario'] : 'Lead do Instagram', $fone, $fone,
                'Veio do Instagram: "' . mb_strimwidth((string)$lead['texto'], 0, 200, '…') . '". Mandou: ' . mb_strimwidth($texto, 0, 200, '…')]);
        $pdo->prepare("UPDATE marketing_leads SET etapa='conversando' WHERE id=?")->execute([$lead['id']]);
    } catch (Throwable $e) { error_log('GDS lead → prospecção: ' . $e->getMessage()); }
    if (!function_exists('prosp_alertar') && is_file(__DIR__ . '/prospeccao.php')) require_once __DIR__ . '/prospeccao.php';
    if (function_exists('prosp_alertar')) prosp_alertar('🔥 Lead do Instagram mandou o WhatsApp: ' . ($lead['usuario'] ? '@' . $lead['usuario'] . ' · ' : '') . 'wa.me/' . $fone . "\nJá está na Prospecção como \"respondeu\". Chama agora, enquanto está quente!");
}
function soc_avisar_lead($usuario, $texto, $item, $respondido) {
    if (!function_exists('prosp_alertar') && is_file(__DIR__ . '/prospeccao.php')) require_once __DIR__ . '/prospeccao.php';
    if (!function_exists('prosp_alertar') || get_config('mkt_aviso_lead', '1') !== '1') return;
    prosp_alertar('🎯 Novo lead no Instagram' . ($usuario ? ': @' . $usuario : '') . "\n\"" . mb_strimwidth($texto, 0, 120, '…') . '"' . ($item ? "\nPost: " . mb_strimwidth((string)$item['tema'], 0, 70, '…') : '')
        . ($respondido ? "\n✅ Já mandei o direct com a oferta." : "\n⚠ Não consegui mandar o direct: responda você."));
}
// A IA conversa no direct (dentro das 24 h que a Meta permite) com um objetivo: pegar o WhatsApp
function soc_ia_responder_dm($lead, $texto) {
    $marca = get_config('mkt_marca', 'GDS Sites');
    $preco = get_config('mkt_preco', '');
    $prompt = "Você atende o direct do Instagram da \"$marca\" (sites com IA para negócios locais: agendamento online, WhatsApp automático, orçamento, delivery, domínio próprio)."
        . ($preco ? " Preço/condição: $preco." : '') . " Objetivo: ser útil e conseguir o WhatsApp da pessoa para mandar a prévia grátis do site dela.\n"
        . "Histórico do lead (comentário e mensagens):\n" . mb_substr((string)$lead['texto'], -1500) . "\n\nNova mensagem: \"$texto\"\n"
        . "Responda em português do Brasil, como gente (sem parecer robô), em até 2 frases curtas, no máximo 1 emoji. Se perguntar preço, responda"
        . ($preco ? " com o preço" : " que depende do negócio e que a prévia é grátis") . ". Termine pedindo o WhatsApp com DDD ou o tipo de negócio. Não invente recursos. Só o texto da resposta.";
    $r = chamar_ia($prompt, 220, 25, 0.7);
    return $r['ok'] ? mb_substr(trim((string)$r['texto'], " \"\n"), 0, 600) : '';
}

// Resumo para a tela: o que funcionou e quanto trouxe
function soc_resultados($dias = 30) {
    global $pdo;
    $d = (int)$dias;
    $r = $pdo->query("SELECT COUNT(*) FILTER (WHERE postado_em > NOW() - INTERVAL '$d days') posts,
        COALESCE(SUM((metricas::json->>'reach')::int) FILTER (WHERE postado_em > NOW() - INTERVAL '$d days'), 0) alcance,
        COALESCE(SUM((metricas::json->>'shares')::int) FILTER (WHERE postado_em > NOW() - INTERVAL '$d days'), 0) envios,
        COALESCE(SUM((metricas::json->>'saved')::int) FILTER (WHERE postado_em > NOW() - INTERVAL '$d days'), 0) salvos
        FROM marketing_itens")->fetch();
    $r['leads'] = (int)$pdo->query("SELECT COUNT(*) FROM marketing_leads WHERE criado_em > NOW() - INTERVAL '$d days'")->fetchColumn();
    $r['whats'] = (int)$pdo->query("SELECT COUNT(*) FROM marketing_leads WHERE etapa IN ('conversando','cliente') AND criado_em > NOW() - INTERVAL '$d days'")->fetchColumn();
    $r['top'] = $pdo->query("SELECT i.id, i.plano_id, i.tipo, i.tema, i.gancho, i.nota_real, i.metricas, i.pub, (SELECT COUNT(*) FROM marketing_leads l WHERE l.item_id=i.id) leads
        FROM marketing_itens i WHERE i.nota_real IS NOT NULL OR EXISTS (SELECT 1 FROM marketing_leads l WHERE l.item_id=i.id)
        ORDER BY (SELECT COUNT(*) FROM marketing_leads l WHERE l.item_id=i.id) * 5 + COALESCE(i.nota_real, 0) DESC LIMIT 5")->fetchAll();
    return $r;
}

// O que a IA precisa saber antes de planejar: o que deu certo e o que não deu
function soc_aprendizado() {
    global $pdo;
    $rows = $pdo->query("SELECT i.tipo, i.tema, i.gancho, i.nota_real, (SELECT COUNT(*) FROM marketing_leads l WHERE l.item_id=i.id) leads FROM marketing_itens i
        WHERE i.nota_real IS NOT NULL ORDER BY i.metricas_em DESC LIMIT 40")->fetchAll();
    if (count($rows) < 4) return '';
    usort($rows, fn($a, $b) => ($b['leads'] * 5 + $b['nota_real']) <=> ($a['leads'] * 5 + $a['nota_real']));
    $fmt = fn($x) => '- ' . (MKT_TIPOS[$x['tipo']] ?? $x['tipo']) . ': "' . mb_strimwidth((string)($x['gancho'] ?: $x['tema']), 0, 110, '…') . '" (nota ' . $x['nota_real'] . ($x['leads'] ? ', ' . $x['leads'] . ' leads' : '') . ')';
    $top = array_map($fmt, array_slice($rows, 0, 4));
    $pior = array_map($fmt, array_slice($rows, -2));
    return "\nDADOS REAIS DO PERFIL (use para decidir): os posts que MAIS renderam envios, salvos e leads foram:\n" . implode("\n", $top)
        . "\nOs que MENOS renderam:\n" . implode("\n", $pior) . "\nRepita os padrões de gancho, formato e tema que funcionaram (sem copiar o texto) e evite os que não funcionaram.";
}
