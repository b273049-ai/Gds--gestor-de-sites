<?php
// ============================================================
// PRÉVIA QUE VENDE (site/previa.php)
//  • Imagem do link: quando a prévia é mandada no WhatsApp, o cartão do link mostra o site
//    do lead dentro de um celular (desenhada no servidor com GD, guardada uma vez por lead).
//  • "Ver no celular": no computador, abre a prévia dentro de um celular.
//  • Tour guiado: 4 balões curtos na primeira visita ("aqui seu cliente agenda"…).
//  • "Ver meu painel": como o dono vai ver a agenda / os pedidos chegando (dados de EXEMPLO).
//  • "Receber um exemplo no meu WhatsApp": manda um aviso de teste SÓ para o número do
//    próprio lead (o que veio do Google), no máximo 1 vez por dia.
// Tudo de graça: sem serviço pago, só PHP, CSS e JS próprios.
// ============================================================

// Texto do botão principal e do que o site faz, pela forma de atendimento
function previa_textos_modo($modo) {
    return [
        'agendamento' => ['cta' => 'Agendar horário', 'faz' => 'Agendamento online · confirmação no WhatsApp', 'onde' => '#agendar'],
        'catalogo'    => ['cta' => 'Fazer pedido', 'faz' => 'Cardápio online · pedido direto no WhatsApp', 'onde' => '#servicos'],
        'orcamento'   => ['cta' => 'Pedir orçamento', 'faz' => 'Orçamento online · resposta no WhatsApp', 'onde' => '#orcamento'],
        'contato'     => ['cta' => 'Falar no WhatsApp', 'faz' => 'Site profissional · contato direto no WhatsApp', 'onde' => '#contato'],
    ][$modo] ?? ['cta' => 'Falar no WhatsApp', 'faz' => 'Site profissional · contato no WhatsApp', 'onde' => '#contato'];
}

// ---------- imagem do link (1200×630) ----------
function previa_og_fonte($peso) {
    $f = __DIR__ . '/../assets/fontes/Inter-' . ($peso === 'bold' ? 'Bold' : 'Regular') . '.otf';
    return is_file($f) ? $f : null;
}

function previa_hex_rgb($hex, $padrao = [37, 99, 235]) {
    $c = sscanf(ltrim((string)$hex, '#'), '%02x%02x%02x');
    return (is_array($c) && count(array_filter($c, 'is_int')) === 3) ? $c : $padrao;
}

// Quebra o texto em até $max linhas que caibam na largura
function previa_og_linhas($texto, $fonte, $tam, $largura, $max = 2) {
    $palavras = preg_split('/\s+/u', trim($texto)); $linhas = []; $atual = '';
    foreach ($palavras as $p) {
        $teste = $atual === '' ? $p : "$atual $p";
        $b = imagettfbbox($tam, 0, $fonte, $teste);
        if ($b[2] - $b[0] > $largura && $atual !== '') { $linhas[] = $atual; $atual = $p; } else $atual = $teste;
    }
    if ($atual !== '') $linhas[] = $atual;
    if (count($linhas) > $max) { $linhas = array_slice($linhas, 0, $max); $linhas[$max - 1] = rtrim(mb_substr($linhas[$max - 1], 0, max(1, mb_strlen($linhas[$max - 1]) - 1))) . '…'; }
    return $linhas;
}

function previa_og_retangulo($im, $x1, $y1, $x2, $y2, $r, $cor) {
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $cor);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $cor);
    foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $cor);
}

// Gera (uma vez) e devolve a URL pública da imagem. null se o servidor não tiver GD/fonte.
function previa_og_imagem($p, $foto, $cor, $modo) {
    $bold = previa_og_fonte('bold'); $reg = previa_og_fonte('regular');
    if (!$bold || !$reg || !function_exists('imagettftext') || empty($p['id'])) return null;
    $pasta = __DIR__ . '/../uploads/prospects/' . (int)$p['id'];
    $chave = substr(md5($p['nome'] . '|' . $foto . '|' . $cor . '|' . $modo . '|v2'), 0, 10);
    $arq = "$pasta/og-$chave.jpg";
    $url = '/uploads/prospects/' . (int)$p['id'] . "/og-$chave.jpg";
    if (is_file($arq)) return $url;
    if (!is_dir($pasta) && !@mkdir($pasta, 0755, true)) return null;
    $W = 1200; $H = 630;
    $im = imagecreatetruecolor($W, $H);
    imagealphablending($im, true);
    [$r, $g, $b] = previa_hex_rgb($cor);
    $fundo = imagecolorallocate($im, 16, 18, 24);
    imagefill($im, 0, 0, $fundo);
    // luz da marca (círculos translúcidos atrás do celular)
    for ($i = 60; $i >= 1; $i--) {
        $c = imagecolorallocatealpha($im, $r, $g, $b, 125);
        imagefilledellipse($im, 960, 330, 14 * $i, 14 * $i, $c);
    }
    $branco = imagecolorallocate($im, 246, 247, 250);
    $cinza = imagecolorallocate($im, 168, 174, 186);
    $marca = imagecolorallocate($im, min(255, $r + 40), min(255, $g + 40), min(255, $b + 40));
    $t = previa_textos_modo($modo);
    // textos à esquerda
    imagettftext($im, 26, 0, 72, 150, $marca, $bold, 'Prévia do site');
    $y = 235;
    $nomeL = previa_og_linhas($p['nome'], $bold, 58, 620);
    foreach ($nomeL as $linha) { imagettftext($im, 58, 0, 70, $y, $branco, $bold, $linha); $y += 76; }
    foreach (previa_og_linhas(count($nomeL) > 1 ? explode(' · ', $t['faz'])[0] : $t['faz'], $reg, 27, 620, 2) as $linha) { imagettftext($im, 27, 0, 72, $y + 12, $cinza, $reg, $linha); $y += 42; }
    if (!empty($p['nota']) && (int)($p['avaliacoes'] ?? 0) >= 3 && $y + 60 <= 530) {
        imagettftext($im, 26, 0, 72, $y + 60, $branco, $bold, number_format((float)$p['nota'], 1, ',', '') . ' ★  ·  ' . (int)$p['avaliacoes'] . ' avaliações no Google');
    }
    imagettftext($im, 24, 0, 72, 588, $cinza, $reg, 'Toque para abrir a prévia');
    // celular à direita
    $cx = 810; $cy = 70; $cw = 300; $ch = 600;
    previa_og_retangulo($im, $cx, $cy, $cx + $cw, $cy + $ch, 46, imagecolorallocate($im, 6, 7, 10));
    previa_og_retangulo($im, $cx + 3, $cy + 3, $cx + $cw - 3, $cy + $ch - 3, 44, imagecolorallocate($im, 58, 62, 72));
    $sx = $cx + 14; $sy = $cy + 14; $sw = $cw - 28; $sh = $ch - 28;
    $tela = imagecolorallocate($im, 250, 249, 246);
    previa_og_retangulo($im, $sx, $sy, $sx + $sw, $sy + $sh, 34, $tela);
    // foto do topo (recorte central) ou bloco na cor da marca
    $alturaFoto = 250;
    $src = null;
    if ($foto && str_starts_with($foto, '/uploads/') && !str_contains($foto, '..')) {
        $orig = __DIR__ . '/..' . parse_url($foto, PHP_URL_PATH);
        if (is_file($orig) && filesize($orig) < 12 * 1024 * 1024) $src = @imagecreatefromstring((string)file_get_contents($orig));
    }
    if ($src) {
        $w0 = imagesx($src); $h0 = imagesy($src); $alvo = $sw / $alturaFoto;
        if ($w0 / $h0 > $alvo) { $cw0 = (int)($h0 * $alvo); $ch0 = $h0; $x0 = (int)(($w0 - $cw0) / 2); $y0 = 0; }
        else { $cw0 = $w0; $ch0 = (int)($w0 / $alvo); $x0 = 0; $y0 = (int)(($h0 - $ch0) / 3); }
        imagecopyresampled($im, $src, $sx, $sy, $x0, $y0, $sw, $alturaFoto, $cw0, $ch0);
        imagedestroy($src);
        // arredonda os cantos de cima da tela por cima da foto
        $corpo = imagecolorallocate($im, 58, 62, 72);
        foreach ([[$sx, $sy], [$sx + $sw - 34, $sy]] as $k => [$qx, $qy]) {
            for ($dx = 0; $dx < 34; $dx++) for ($dy = 0; $dy < 34; $dy++) {
                $ox = $k === 0 ? 34 - $dx : $dx; $oy = 34 - $dy;
                if ($ox * $ox + $oy * $oy > 34 * 34) imagesetpixel($im, $qx + $dx, $qy + $dy, $corpo);
            }
        }
    } else {
        previa_og_retangulo($im, $sx, $sy, $sx + $sw, $sy + $alturaFoto, 34, imagecolorallocate($im, $r, $g, $b));
        imagefilledrectangle($im, $sx, $sy + $alturaFoto - 40, $sx + $sw, $sy + $alturaFoto, imagecolorallocate($im, $r, $g, $b));
    }
    // ilha do celular
    previa_og_retangulo($im, $cx + $cw / 2 - 45, $cy + 24, $cx + $cw / 2 + 45, $cy + 48, 12, imagecolorallocate($im, 6, 7, 10));
    // nome, linha e botão dentro da tela
    $escuro = imagecolorallocate($im, 30, 28, 26); $suave = imagecolorallocate($im, 120, 116, 110);
    $yy = $sy + $alturaFoto + 46;
    foreach (previa_og_linhas($p['nome'], $bold, 21, $sw - 36, 2) as $linha) { imagettftext($im, 21, 0, $sx + 18, $yy, $escuro, $bold, $linha); $yy += 30; }
    imagettftext($im, 14, 0, $sx + 18, $yy + 4, $suave, $reg, $p['bairro'] ?: ($p['cidade'] ?: 'Atendimento online'));
    $bcor = imagecolorallocate($im, $r, $g, $b);
    $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b);
    $btxt = $lum > 140 ? imagecolorallocate($im, 20, 20, 20) : imagecolorallocate($im, 255, 255, 255);
    previa_og_retangulo($im, $sx + 18, $yy + 30, $sx + $sw - 18, $yy + 82, 26, $bcor);
    $bb = imagettfbbox(18, 0, $bold, $t['cta']);
    imagettftext($im, 18, 0, (int)($sx + $sw / 2 - ($bb[2] - $bb[0]) / 2), $yy + 63, $btxt, $bold, $t['cta']);
    // linhas que lembram o conteúdo do site
    $linhaCor = imagecolorallocate($im, 226, 223, 217);
    for ($i = 0; $i < 3; $i++) previa_og_retangulo($im, $sx + 18, $yy + 110 + $i * 34, $sx + $sw - 18 - $i * 40, $yy + 126 + $i * 34, 8, $linhaCor);
    $ok = imagejpeg($im, $arq, 86);
    imagedestroy($im);
    // apaga versões antigas desta prévia
    foreach ((array)glob("$pasta/og-*.jpg") as $velho) if ($velho !== $arq) @unlink($velho);
    return $ok ? $url : null;
}

function previa_url_base() {
    $host = preg_replace('/[^a-z0-9.:-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') return '';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || !preg_match('/^(127\.|localhost)/', $host);
    return ($https ? 'https://' : 'http://') . $host;
}

// Troca as tags do cartão do link (título, descrição e imagem) para a versão "prévia"
function previa_aplicar_og($html, $p, $og, $modo) {
    $t = previa_textos_modo($modo);
    $titulo = 'Prévia do site da ' . $p['nome'];
    $desc = $t['faz'] . '. Veja como fica o site de vocês, pronto para usar.';
    $html = preg_replace('#<meta property="og:(title|description|image)"[^>]*>\s*#', '', $html);
    $tags = '<meta property="og:title" content="' . e($titulo) . '"><meta property="og:description" content="' . e($desc) . '">';
    if ($og) $tags .= '<meta property="og:image" content="' . e(previa_url_base() . $og) . '"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630"><meta name="twitter:card" content="summary_large_image">';
    return preg_replace('#</title>#', '</title>' . $tags, $html, 1);
}

// ---------- painel de exemplo ----------
function previa_painel_exemplo($modo, $servicos) {
    $nomes = ['Ana', 'Bruno', 'Carla', 'Diego', 'Elisa', 'Fábio'];
    $sv = array_values(array_filter(array_map(fn($s) => ['nome' => $s['nome'] ?? '', 'preco' => (float)($s['preco'] ?? 0)], (array)$servicos), fn($s) => $s['nome'] !== ''));
    if (!$sv) $sv = [['nome' => 'Atendimento', 'preco' => 0]];
    $preco = fn($v) => $v > 0 ? 'R$ ' . number_format($v, 2, ',', '.') : '';
    $linhas = ''; $kpis = [];
    if ($modo === 'catalogo') {
        $st = [['Novo', 'nv'], ['Em preparo', 'pr'], ['Saiu para entrega', 'en'], ['Entregue', 'ok']];
        $total = 0;
        foreach (array_slice($nomes, 0, 4) as $i => $n) {
            $s = $sv[$i % count($sv)]; $q = 1 + $i % 2; $v = $s['preco'] * $q; $total += $v;
            $linhas .= '<li><span class="pv-hora">#' . (1042 + $i) . '</span><span class="pv-q"><b>' . e($n) . '</b> · ' . $q . '× ' . e($s['nome']) . '</span><span class="pv-st pv-' . $st[$i][1] . '">' . $st[$i][0] . '</span><span class="pv-v">' . $preco($v) . '</span></li>';
        }
        $kpis = [['Pedidos hoje', '23'], ['Faturamento hoje', $total > 0 ? $preco($total * 5) : 'R$ 1.280,00'], ['Tempo médio', '38 min']];
        $titulo = 'Pedidos chegando agora';
    } elseif ($modo === 'orcamento') {
        $st = [['Novo', 'nv'], ['Respondido', 'pr'], ['Aprovado', 'ok'], ['Novo', 'nv']];
        foreach (array_slice($nomes, 0, 4) as $i => $n) $linhas .= '<li><span class="pv-hora">' . ['hoje', 'hoje', 'ontem', 'ontem'][$i] . '</span><span class="pv-q"><b>' . e($n) . '</b> · ' . e($sv[$i % count($sv)]['nome']) . '</span><span class="pv-st pv-' . $st[$i][1] . '">' . $st[$i][0] . '</span><span class="pv-v"></span></li>';
        $kpis = [['Orçamentos no mês', '18'], ['Aprovados', '7'], ['Valor aprovado', 'R$ 9.400,00']];
        $titulo = 'Pedidos de orçamento';
    } elseif ($modo === 'agendamento') {
        $horas = ['09:00', '10:30', '13:00', '14:30', '16:00', '17:30'];
        $st = [['Confirmado', 'ok'], ['Confirmado', 'ok'], ['Pix pago', 'en'], ['Lembrete enviado', 'pr'], ['Confirmado', 'ok'], ['Novo', 'nv']];
        $total = 0;
        foreach ($horas as $i => $h) { $s = $sv[$i % count($sv)]; $total += $s['preco']; $linhas .= '<li><span class="pv-hora">' . $h . '</span><span class="pv-q"><b>' . e($nomes[$i]) . '</b> · ' . e($s['nome']) . '</span><span class="pv-st pv-' . $st[$i][1] . '">' . $st[$i][0] . '</span><span class="pv-v">' . $preco($s['preco']) . '</span></li>'; }
        $kpis = [['Agendamentos hoje', '6'], ['Previsto hoje', $total > 0 ? $preco($total) : 'R$ 540,00'], ['Faltas no mês', '0']];
        $titulo = 'Agenda de hoje';
    } else {
        foreach (array_slice($nomes, 0, 4) as $i => $n) $linhas .= '<li><span class="pv-hora">' . ['09:12', '10:40', '13:05', '15:22'][$i] . '</span><span class="pv-q"><b>' . e($n) . '</b> · chamou no WhatsApp pelo site</span><span class="pv-st pv-nv">Novo contato</span><span class="pv-v"></span></li>';
        $kpis = [['Visitas no site hoje', '57'], ['Contatos pelo site', '9'], ['Cliques no WhatsApp', '14']];
        $titulo = 'Contatos que chegaram pelo site';
    }
    $k = implode('', array_map(fn($x) => '<div><span>' . e($x[0]) . '</span><strong>' . e($x[1]) . '</strong></div>', $kpis));
    return '<div class="pv-kpis">' . $k . '</div><h3>' . e($titulo) . '</h3><ul class="pv-lista">' . $linhas . '</ul>';
}

// Mensagem de exemplo mandada ao WhatsApp do lead
function previa_msg_teste($p, $modo, $servicos, $marca) {
    $s = (string)(((array)$servicos)[0]['nome'] ?? 'atendimento');
    $quando = date('d/m', strtotime('+1 day')) . ' às 10:00';
    $corpo = [
        'agendamento' => "🔔 *Novo agendamento pelo site* (exemplo)\n👤 Ana Souza\n✂️ $s\n📅 $quando\n✅ Confirmação e lembrete já enviados para a cliente.",
        'catalogo'    => "🛒 *Novo pedido pelo site* (exemplo)\n#1042 · Ana Souza\n1× $s\n🛵 Entrega · Pix pago\nO pedido já aparece no seu painel.",
        'orcamento'   => "📝 *Novo pedido de orçamento* (exemplo)\n👤 Ana Souza\n🔧 $s\nAs respostas e as fotos do cliente já estão no seu painel.",
    ][$modo] ?? "💬 *Novo contato pelo site* (exemplo)\n👤 Ana Souza quer saber mais sobre $s.";
    return "Olá! Aqui é a {$marca}. Assim vai chegar no seu WhatsApp cada cliente que usar o site da *{$p['nome']}*:\n\n{$corpo}\n\nGostou? É só responder esta mensagem ou tocar em \"Quero este site\" na prévia.";
}

// Pedido "receber um exemplo no meu WhatsApp" (POST da prévia). Devolve [ok, mensagem]
function previa_teste_whatsapp($p, $modo, $servicos, $marca) {
    global $pdo;
    $num = preg_replace('/\D/', '', (string)($p['whatsapp'] ?? ''));
    if (strlen($num) < 12) return [false, 'Não temos um WhatsApp desta empresa para mandar o exemplo.'];
    if (!empty($p['teste_wa_em']) && strtotime($p['teste_wa_em']) > time() - 86400) return [false, 'O exemplo já foi enviado hoje. Confira o seu WhatsApp.'];
    if (!function_exists('wa_remetente')) require_once __DIR__ . '/whatsapp.php';
    $rem = wa_remetente(['wa_modo' => 'plataforma']);
    if (!$rem) return [false, 'O envio pelo WhatsApp está indisponível agora.'];
    // marca antes de enviar (dois toques seguidos não mandam duas vezes)
    $st = $pdo->prepare("UPDATE prospects SET teste_wa_em=NOW() WHERE id=? AND (teste_wa_em IS NULL OR teste_wa_em < NOW() - INTERVAL '1 day')");
    $st->execute([$p['id']]);
    if (!$st->rowCount()) return [false, 'O exemplo já foi enviado hoje. Confira o seu WhatsApp.'];
    try { $r = wa_enviar_agora($rem, $num, previa_msg_teste($p, $modo, $servicos, $marca)); } catch (Throwable $e) { $r = ['ok' => false]; }
    if (empty($r['ok'])) { $pdo->prepare("UPDATE prospects SET teste_wa_em=NULL WHERE id=?")->execute([$p['id']]); return [false, 'Não consegui enviar agora. Tente de novo em instantes.']; }
    if (function_exists('prosp_alertar')) prosp_alertar("📲 {$p['nome']} pediu o exemplo no WhatsApp pela prévia — está testando agora!");
    return [true, 'Pronto! Confira o WhatsApp ' . previa_mascarar($num) . '.'];
}

function previa_mascarar($num) {
    $d = substr(preg_replace('/\D/', '', $num), -11);
    return strlen($d) === 11 ? '(' . substr($d, 0, 2) . ') •••••-' . substr($d, -4) : '•••• ' . substr($d, -4);
}

// ---------- CSS + HTML + JS dos extras ----------
function previa_extras_html($p, $modo, $servicos, $marca, $podeTesteWa) {
    $t = previa_textos_modo($modo);
    $painel = previa_painel_exemplo($modo, $servicos);
    $num = preg_replace('/\D/', '', (string)($p['whatsapp'] ?? ''));
    $tour = [
        ['.lp-vivo', 'Painel ao vivo', 'Mostra para o cliente se vocês estão abertos agora e o que dá para fazer — com dados de verdade.'],
        ['.lp-hero .lp-btn', 'O botão que traz clientes', $modo === 'catalogo' ? 'O cliente escolhe, monta o pedido e ele chega pronto no seu WhatsApp.' : ($modo === 'agendamento' ? 'O cliente agenda sozinho, 24 horas, e recebe a confirmação e o lembrete no WhatsApp.' : 'O cliente fala com vocês em um toque, já com a mensagem pronta.')],
        ['#servicos', $modo === 'catalogo' ? 'Seu cardápio' : 'Seus serviços', 'Com preço, foto e descrição. Você muda tudo pelo painel, com botões.'],
        ['.gds-previa a.pv-quero', 'Gostou?', 'Toque em "Quero este site" e a gente coloca no ar com o seu endereço.'],
    ];
    $css = <<<CSS
.gds-previa{gap:8px!important}.gds-previa .pv-b{flex:none;background:rgba(255,255,255,.12);color:#fff;border:0;font:700 13px/1 system-ui,sans-serif;padding:10px 14px;border-radius:999px;cursor:pointer}
.gds-previa .pv-b:hover{background:rgba(255,255,255,.2)}.gds-previa .pv-b:focus-visible,.gds-previa a:focus-visible{outline:3px solid #fff;outline-offset:2px}
.pv-cel-b{display:none}@media(min-width:900px){.pv-cel-b{display:inline-flex}}
.pv-ov{position:fixed;inset:0;z-index:10060;background:rgba(8,9,13,.72);backdrop-filter:blur(6px);display:none;align-items:center;justify-content:center;padding:16px}
.pv-ov.aberto{display:flex}
.pv-fechar{position:absolute;top:14px;right:14px;width:44px;height:44px;border-radius:50%;border:0;background:#fff;color:#111;font:700 22px/1 system-ui;cursor:pointer}
.pv-celular{width:390px;height:min(840px,calc(100vh - 40px));border-radius:54px;background:#07080b;padding:46px 12px 14px;box-shadow:0 40px 90px -20px rgba(0,0,0,.7),inset 0 0 0 2px #3a3e48;position:relative;display:flex}
.pv-celular::before{content:"";position:absolute;top:14px;left:50%;transform:translateX(-50%);width:104px;height:26px;border-radius:20px;background:#000;box-shadow:inset 0 0 0 1px #1d2027}
.pv-celular iframe{width:100%;height:100%;border:0;border-radius:6px 6px 40px 40px;background:#fff}
.pv-painel{width:min(760px,100%);max-height:calc(100vh - 32px);overflow:auto;background:#f6f7f9;color:#14161b;border-radius:20px;padding:22px;font:15px/1.45 system-ui,-apple-system,"Segoe UI",sans-serif;position:relative}
.pv-painel h2{font-size:20px;margin:0 50px 4px 0}.pv-painel h3{font-size:16px;margin:18px 0 8px}
.pv-aviso{display:inline-block;background:#fff4d6;color:#6b4a00;border-radius:8px;padding:3px 9px;font-size:13px;font-weight:600}
.pv-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:14px}.pv-kpis div{background:#fff;border-radius:14px;padding:12px;border:1px solid #e4e6eb}
.pv-kpis span{display:block;font-size:12.5px;color:#5b6170}.pv-kpis strong{font-size:20px}
.pv-lista{list-style:none;margin:0;padding:0;display:grid;gap:8px}.pv-lista li{display:grid;grid-template-columns:62px minmax(0,1fr) auto auto;gap:10px;align-items:center;background:#fff;border:1px solid #e4e6eb;border-radius:12px;padding:10px 12px}
.pv-hora{font-weight:700;color:#3b4150}.pv-q{overflow-wrap:anywhere}.pv-v{font-weight:700;white-space:nowrap}
.pv-st{font-size:12.5px;font-weight:700;border-radius:999px;padding:3px 9px;white-space:nowrap}.pv-ok{background:#dcf5e7;color:#0b6b3a}.pv-nv{background:#e3ecff;color:#1d4fd7}.pv-pr{background:#fff1d6;color:#7a4b00}.pv-en{background:#efe3ff;color:#5b25b0}
.pv-teste{margin-top:18px;background:#0f1a14;color:#e9fff2;border-radius:16px;padding:16px;display:grid;gap:10px}
.pv-teste button{justify-self:start;background:#25d366;color:#06240f;border:0;font:800 15px/1 system-ui;padding:13px 18px;border-radius:999px;cursor:pointer;min-height:44px}
.pv-teste button[disabled]{opacity:.6;cursor:default}.pv-teste p{margin:0}
@media(max-width:560px){.pv-kpis{grid-template-columns:1fr 1fr}.pv-kpis div:last-child{grid-column:span 2}.pv-lista li{grid-template-columns:52px minmax(0,1fr);}.pv-lista .pv-st,.pv-lista .pv-v{grid-column:2;justify-self:start}}
.pv-tour-alvo{z-index:10055!important;box-shadow:0 0 0 4px #fff,0 0 0 9999px rgba(8,9,13,.55)!important;border-radius:14px}
.pv-balao{position:fixed;top:0;left:0;visibility:hidden;box-sizing:border-box;z-index:10070;width:min(320px,calc(100vw - 24px));background:#fff;color:#14161b;border-radius:16px;padding:16px;box-shadow:0 20px 50px -10px rgba(0,0,0,.45);font:15px/1.45 system-ui,-apple-system,"Segoe UI",sans-serif}
.pv-balao h2{font-size:16px;margin:0 0 4px}.pv-balao p{margin:0 0 12px;color:#3b4150}.pv-balao .pv-nav{display:flex;justify-content:space-between;align-items:center;gap:8px}
.pv-balao button{border:0;border-radius:999px;font:700 14px/1 system-ui;padding:11px 16px;cursor:pointer;min-height:40px}.pv-balao .pv-prox{background:#14161b;color:#fff}.pv-balao .pv-pular{background:transparent;color:#4b5160;text-decoration:underline}
.pv-balao .pv-passo{font-size:12.5px;color:#5b6170}
@media(prefers-reduced-motion:no-preference){.pv-balao{transition:opacity .2s}}
CSS;
    $testeWa = '';
    if ($podeTesteWa && strlen($num) >= 12) {
        $testeWa = '<div class="pv-teste"><p><strong>Teste agora:</strong> receba no seu WhatsApp (' . e(previa_mascarar($num)) . ') o aviso que chega a cada cliente do site.</p>'
                 . '<button type="button" id="pvTesteWa">📲 Receber um exemplo no meu WhatsApp</button><p id="pvTesteMsg" role="status" aria-live="polite"></p></div>';
    }
    $html = '<div class="pv-ov" id="pvCel" role="dialog" aria-modal="true" aria-label="Prévia no celular"><button type="button" class="pv-fechar" data-pv-fechar aria-label="Fechar">×</button><div class="pv-celular"><iframe title="Prévia no celular" data-src="?celular=1"></iframe></div></div>'
          . '<div class="pv-ov" id="pvPainel" role="dialog" aria-modal="true" aria-labelledby="pvPainelT"><div class="pv-painel"><button type="button" class="pv-fechar" data-pv-fechar aria-label="Fechar">×</button>'
          . '<h2 id="pvPainelT">Seu painel no ' . e($marca) . '</h2><span class="pv-aviso">Dados de exemplo — é assim que você acompanha tudo</span>' . $painel . $testeWa . '</div></div>';
    $js = '(function(){if(window.self!==window.top){var f=document.querySelector(".gds-previa");if(f)f.remove();return;}'
        . 'var abrir=function(id){var o=document.getElementById(id);if(!o)return;var fr=o.querySelector("iframe[data-src]");if(fr&&!fr.src)fr.src=location.pathname+(location.search?location.search+"&":"?")+fr.getAttribute("data-src").replace(/^\?/,"");o.classList.add("aberto");o.dataset.volta=document.activeElement?"1":"";var b=o.querySelector("[data-pv-fechar]");if(b)b.focus();o._antes=document.activeElement;};'
        . 'var fechar=function(o){o.classList.remove("aberto");var a=document.querySelector("[data-pv-abrir=\""+o.id+"\"]");if(a)a.focus();};'
        . 'document.addEventListener("click",function(e){var a=e.target.closest("[data-pv-abrir]");if(a){abrir(a.getAttribute("data-pv-abrir"));return;}var x=e.target.closest("[data-pv-fechar]");if(x){fechar(x.closest(".pv-ov"));return;}if(e.target.classList&&e.target.classList.contains("pv-ov"))fechar(e.target);});'
        . 'document.addEventListener("keydown",function(e){if(e.key==="Escape"){document.querySelectorAll(".pv-ov.aberto").forEach(fechar);fimTour();}});'
        . 'var bt=document.getElementById("pvTesteWa");if(bt)bt.addEventListener("click",function(){bt.disabled=true;var m=document.getElementById("pvTesteMsg");m.textContent="Enviando…";var fd=new FormData();fd.append("acao","teste_wa");'
        . 'fetch(location.pathname+location.search,{method:"POST",body:fd,credentials:"same-origin"}).then(function(r){return r.json()}).then(function(j){m.textContent=j.msg||"";if(!j.ok)bt.disabled=false;}).catch(function(){m.textContent="Não consegui enviar agora.";bt.disabled=false;});});'
        // tour guiado
        . 'var passos=' . json_encode($tour, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '.filter(function(p){return document.querySelector(p[0]);});var i=0,balao=null,alvo=null;'
        . 'function solta(a){a.classList.remove("pv-tour-alvo");if(a.dataset.pvPos){a.style.position="";delete a.dataset.pvPos;}}function fimTour(){if(balao){balao.remove();balao=null;}if(alvo){solta(alvo);alvo=null;}try{localStorage.setItem("pv_tour","1")}catch(e){}}'
        . 'function mostrar(){if(alvo)solta(alvo);if(i>=passos.length){fimTour();return;}var p=passos[i];alvo=document.querySelector(p[0]);var fixo=getComputedStyle(alvo).position==="fixed"||alvo.closest(".gds-previa");'
        . 'if(!fixo){alvo.scrollIntoView({block:"center",behavior:matchMedia("(prefers-reduced-motion: reduce)").matches?"auto":"smooth"});}if(getComputedStyle(alvo).position==="static"){alvo.style.position="relative";alvo.dataset.pvPos="1";}alvo.classList.add("pv-tour-alvo");'
        . 'if(!balao){balao=document.createElement("div");balao.className="pv-balao";balao.setAttribute("role","dialog");balao.setAttribute("aria-labelledby","pvBalaoT");document.body.appendChild(balao);}'
        . 'balao.innerHTML="<h2 id=\"pvBalaoT\"></h2><p></p><div class=\"pv-nav\"><span class=\"pv-passo\"></span><span><button type=\"button\" class=\"pv-pular\">Pular</button> <button type=\"button\" class=\"pv-prox\"></button></span></div>";'
        . 'balao.querySelector("h2").textContent=p[1];balao.querySelector("p").textContent=p[2];balao.querySelector(".pv-passo").textContent=(i+1)+" de "+passos.length;balao.querySelector(".pv-prox").textContent=i===passos.length-1?"Entendi":"Próximo";'
        . 'balao.querySelector(".pv-prox").onclick=function(){i++;mostrar();};balao.querySelector(".pv-pular").onclick=fimTour;setTimeout(posicionar,fixo?0:420);balao.querySelector(".pv-prox").focus({preventScroll:true});}'
        . 'function posicionar(){if(!balao||!alvo)return;var r=alvo.getBoundingClientRect(),h=balao.offsetHeight,w=balao.offsetWidth,top=r.bottom+12;if(top+h>innerHeight-8)top=Math.max(8,r.top-h-12);var left=Math.min(Math.max(12,r.left),innerWidth-w-12);balao.style.transform="translate("+Math.round(left)+"px,"+Math.round(top)+"px)";balao.style.visibility="visible";}'
        . 'addEventListener("resize",posicionar);var visto=false;try{visto=localStorage.getItem("pv_tour")==="1"}catch(e){}'
        . 'if(!visto&&passos.length)setTimeout(function(){if(!document.querySelector(".pv-ov.aberto"))mostrar();},1600);'
        . 'var bTour=document.getElementById("pvTour");if(bTour)bTour.addEventListener("click",function(){i=0;mostrar();});})();';
    return ['css' => $css, 'html' => $html, 'js' => $js];
}
