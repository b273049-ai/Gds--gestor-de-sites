<?php
// ============================================================
// MARKETING SEM CHAVE DE IA + POSTS COM DADOS REAIS
// - Banco de posts prontos (mesmo formato que a IA devolve): o marketing funciona
//   mesmo sem chave configurada, variando modelo, estilo, texto e roteiro.
// - "Dados reais": números verdadeiros da base de prospecção (ex.: quantas barbearias
//   de uma cidade não têm site). Nada inventado.
// ============================================================

// Números reais da prospecção para um público (segmento) — ou null se ainda não há base
function mkt_dados_reais($publico = '') {
    global $pdo;
    $pub = trim((string)$publico);
    try {
        $sql = "SELECT cidade, COUNT(*) total, COUNT(*) FILTER (WHERE NOT tem_site) sem_site, ROUND(AVG(nota) FILTER (WHERE nota > 0)::numeric, 1) nota
                FROM prospects WHERE cidade <> ''" . ($pub !== '' ? " AND (categoria ILIKE ? OR busca ILIKE ? OR segmento_slug ILIKE ?)" : '') . "
                GROUP BY cidade HAVING COUNT(*) >= 8 ORDER BY COUNT(*) DESC LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute($pub !== '' ? ["%$pub%", "%$pub%", "%$pub%"] : []);
        $l = $st->fetch();
    } catch (PDOException $e) { return null; }
    if (!$l || (int)$l['sem_site'] === 0) return null;
    $nicho = $pub !== '' ? mb_strtolower($pub) : '';
    $pct = (int)round(100 * $l['sem_site'] / $l['total']);
    $cidade = trim(preg_replace('/\s*-\s*[A-Z]{2}$/', '', $l['cidade']));
    return ['cidade' => $cidade, 'total' => (int)$l['total'], 'sem_site' => (int)$l['sem_site'], 'pct' => $pct, 'nota' => $l['nota'], 'nicho' => $nicho,
        'frase' => "Das {$l['total']} empresas " . ($nicho ? "de $nicho " : '') . "que mapeamos em $cidade, {$l['sem_site']} ($pct%) não têm site próprio."];
}

function mkt_roteiro($cenas) {
    $tempos = ['0-3s', '3-7s', '7-12s', '12-18s', '18-24s', '24-30s'];
    return array_map(fn($c, $i) => ['tempo' => $tempos[$i] ?? '', 'texto_tela' => $c[0], 'fala' => $c[1]], $cenas, array_keys($cenas));
}

// Post pronto sem IA: escolhe uma variação do objetivo e preenche com o público e a marca
function mkt_post_sem_ia($objetivo, $publico, $marca, $preco = '') {
    $pub = trim((string)$publico) ?: 'seu negócio';
    $pubBaixo = mb_strtolower($pub);
    $tags = array_merge(['empreendedorismo', 'pequenosnegocios', 'marketingdigital', 'sitesprofissionais', 'agendamentoonline', 'whatsappbusiness', 'negociolocal'],
        $publico ? [preg_replace('/[^a-z0-9]/', '', strtr(mb_strtolower($publico), ['á' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ú' => 'u', 'ç' => 'c']))] : []);
    $estilos = ['neon', 'editorial', 'bold', 'pop', 'minimal', 'luxo'];
    $banco = [
        'dor' => [
            ['modelo' => 'pergunta', 'cena' => 'tired small business owner answering many WhatsApp messages on a phone late at night in a shop',
             'c' => ['selo' => 'Isso é com você?', 'titulo' => 'Quantos clientes você *perdeu* hoje no WhatsApp?', 'subtitulo' => 'Enquanto você atendia, alguém desistiu de esperar resposta.', 'cta' => 'Chama no direct'],
             'legenda' => "Você responde, atende, cobra, organiza… e ainda tem que responder \"tem horário?\" o dia inteiro.\nCom um site que agenda sozinho, o cliente marca e a confirmação chega no WhatsApp dele.\nVocê só aparece pra atender. 👇 Chama no direct.",
             'roteiro' => [['QUANTOS CLIENTES VOCÊ PERDEU?', 'Quantos clientes você perdeu hoje esperando resposta no WhatsApp?'], ['RESPONDENDO O DIA TODO', 'Você atende, cobra, organiza e ainda responde "tem horário?" o dia inteiro.'], ['O SITE AGENDA SOZINHO', 'Com um site que agenda sozinho, o cliente escolhe o horário e pronto.'], ['CONFIRMAÇÃO NO WHATSAPP', 'A confirmação chega no WhatsApp dele, sem você digitar nada.'], ['CHAMA NO DIRECT', 'Quer ver como fica o seu? Chama no direct.']]],
            ['modelo' => 'foto_titulo', 'cena' => 'busy local shop owner multitasking with phone ringing, warm natural light',
             'c' => ['selo' => 'Pra ' . $pubBaixo, 'titulo' => 'Seu cliente *não espera* resposta', 'subtitulo' => 'Ele marca com quem deixa marcar sozinho, a qualquer hora.', 'cta' => 'Quero o meu'],
             'legenda' => "De noite, no domingo, no meio do seu atendimento: é quando o cliente procura.\nSe ele não consegue marcar na hora, marca no concorrente.\nUm site com agenda online trabalha enquanto você descansa. Quer ver o seu? 👇",
             'roteiro' => [['ELE NÃO ESPERA', 'Seu cliente não espera resposta. Ele marca com quem deixa marcar.'], ['22H DE DOMINGO', 'Às dez da noite de domingo ele quer marcar. Você está descansando.'], ['AGENDA ABERTA 24H', 'Com a agenda online, ele escolhe o horário sozinho.'], ['VOCÊ SÓ ATENDE', 'Você acorda com a agenda cheia.'], ['LINK NA BIO', 'Quer o seu? Link na bio.']]],
        ],
        'antes_depois' => [
            ['modelo' => 'antes_depois', 'cena' => 'split scene of a messy paper appointment book next to a clean smartphone booking screen',
             'c' => ['selo' => 'Antes × depois', 'titulo' => 'A mesma *' . $pubBaixo . '*. Outro jogo.', 'antes' => ['Agenda no caderno', 'Cliente esperando resposta', 'Falta sem avisar'], 'depois' => ['Cliente marca sozinho', 'Confirmação no WhatsApp', 'Lembrete automático'], 'cta' => 'Quero o depois'],
             'legenda' => "Antes: caderno, mensagem perdida e cliente que some.\nDepois: o cliente marca sozinho, recebe a confirmação e o lembrete no WhatsApp.\nMesma $pubBaixo, outra rotina. Quer o depois? 👇",
             'roteiro' => [['ANTES', 'Antes: agenda no caderno e cliente esperando resposta.'], ['FALTAS', 'E aquele cliente que marca e não aparece.'], ['DEPOIS', 'Depois: o cliente marca sozinho pelo site.'], ['LEMBRETE AUTOMÁTICO', 'Ele recebe a confirmação e o lembrete no WhatsApp.'], ['QUER O DEPOIS?', 'Quer o depois? Chama no direct.']]],
        ],
        'recursos' => [
            ['modelo' => 'lista', 'cena' => 'smiling owner showing a modern website on a phone to a customer at the counter',
             'c' => ['selo' => 'O que vem no site', 'titulo' => 'Tudo que o seu site *faz sozinho*', 'itens' => ['Agenda online 24h', 'Confirmação no WhatsApp', 'Lembrete antes do horário', 'Pix para sinal', 'Seu domínio .com.br', 'Aparece no Google'], 'cta' => 'Ver demonstração'],
             'legenda' => "Não é só um site bonito. Ele trabalha:\n✔ agenda 24h\n✔ confirmação e lembrete no WhatsApp\n✔ Pix para sinal\n✔ domínio próprio\nQuer ver o seu pronto? Chama no direct 👇",
             'roteiro' => [['ELE TRABALHA SOZINHO', 'O seu site não precisa só ser bonito. Ele precisa trabalhar.'], ['AGENDA 24H', 'Agenda aberta vinte e quatro horas.'], ['WHATSAPP AUTOMÁTICO', 'Confirmação e lembrete no WhatsApp, sozinho.'], ['PIX NO SINAL', 'E sinal no Pix pra acabar com a falta.'], ['VER DEMONSTRAÇÃO', 'Quer ver o seu? Chama no direct.']]],
        ],
        'prova' => [
            ['modelo' => 'depoimento', 'cena' => 'happy local business owner holding a phone with a booking notification, candid',
             'c' => ['selo' => 'Exemplo de depoimento', 'titulo' => 'O que o cliente *sente*', 'citacao' => 'Antes eu passava o dia respondendo. Agora o cliente marca sozinho e eu só confirmo a agenda.', 'autor' => 'Troque por um depoimento real de cliente', 'cta' => 'Quero isso'],
             'legenda' => "Troque este texto por um depoimento real (com permissão do cliente). Prova de verdade vende mais que qualquer promessa. 👇",
             'roteiro' => [['ANTES', 'Leia aqui o depoimento real do seu cliente: como era antes.'], ['DEPOIS', 'Como ficou depois do site.'], ['MOSTRE O SITE', 'Mostre o site dele na tela do celular.'], ['CHAMADA', 'Quer o mesmo? Chama no direct.']]],
        ],
        'oferta' => [
            ['modelo' => 'oferta', 'cena' => 'confident small business owner pointing at a phone screen showing a website, bright modern shop',
             'c' => ['selo' => 'Condição especial', 'titulo' => 'Seu site *pronto* esta semana', 'subtitulo' => 'Com agenda, WhatsApp automático e domínio.', 'preco' => $preco ?: 'Fale com a gente', 'cta' => 'Garantir o meu'],
             'legenda' => "Site profissional com agenda online, WhatsApp automático e domínio próprio" . ($preco ? " por $preco" : '') . ".\nVocê manda as fotos, a gente coloca no ar.\nChama no direct e garante o seu 👇",
             'roteiro' => [['SEU SITE PRONTO', 'Seu site pronto ainda esta semana.'], ['COM AGENDA', 'Com agenda online e WhatsApp automático.'], ['SEU DOMÍNIO', 'E com o seu domínio ponto com ponto br.'], [$preco ? mb_strtoupper($preco) : 'CONDIÇÃO ESPECIAL', $preco ? "Tudo isso por $preco." : 'Com condição especial para quem chamar hoje.'], ['CHAMA NO DIRECT', 'Chama no direct.']]],
        ],
        'educativo' => [
            ['modelo' => 'numero', 'cena' => 'person searching on a smartphone for a local business on a map app, street background',
             'c' => ['selo' => 'Dica rápida', 'titulo' => 'O Instagram *não aparece* quando procuram no Google', 'subtitulo' => 'Quem pesquisa "' . $pubBaixo . ' perto de mim" encontra site e Google Maps.', 'preco' => 'Google', 'cta' => 'Salva esse post'],
             'legenda' => "Instagram é vitrine, mas quem pesquisa no Google quer resolver agora.\nSite + perfil no Google Maps = você aparece na hora da decisão.\nSalva pra lembrar 💾",
             'roteiro' => [['INSTAGRAM NÃO BASTA', 'Seu Instagram não aparece quando procuram no Google.'], ['"PERTO DE MIM"', 'Quem pesquisa "perto de mim" quer resolver agora.'], ['SITE + GOOGLE MAPS', 'Site e Google Maps colocam você nessa hora.'], ['SALVA', 'Salva esse post pra não esquecer.']]],
        ],
        'bastidores' => [
            ['modelo' => 'foto_split', 'cena' => 'over-the-shoulder view of a laptop building a colorful small business website, cozy desk',
             'c' => ['selo' => 'Bastidores', 'titulo' => 'Um site de *' . $pubBaixo . '* nascendo', 'subtitulo' => 'Nome, fotos e avaliações reais do Google, agenda e WhatsApp.', 'cta' => 'Quero ver o meu'],
             'legenda' => "Bastidor de hoje: um site saindo do zero com as fotos e as avaliações reais do negócio.\nQuer ver como ficaria o seu? Manda o nome no direct que eu monto uma prévia 👇",
             'roteiro' => [['DO ZERO', 'Olha um site nascendo do zero.'], ['FOTOS REAIS', 'Com as fotos e as avaliações reais do negócio.'], ['AGENDA ONLINE', 'Agenda online já funcionando.'], ['MANDA O NOME', 'Manda o nome do seu negócio no direct que eu monto uma prévia.']]],
        ],
        'objecao' => [
            ['modelo' => 'pergunta', 'cena' => 'skeptical small business owner with arms crossed in front of a shop, friendly mood',
             'c' => ['selo' => '"Eu não preciso de site"', 'titulo' => '"Meu cliente marca pelo *WhatsApp*." Ótimo.', 'subtitulo' => 'O site só tira de você o trabalho de responder um por um.', 'cta' => 'Faz sentido?'],
             'legenda' => "\"Meu cliente já marca pelo WhatsApp.\"\nPerfeito — o site não substitui o WhatsApp, ele responde por você: o cliente vê os horários, marca, e a confirmação chega no WhatsApp.\nMenos tempo no celular, mais tempo atendendo. Faz sentido? 👇",
             'roteiro' => [['"EU NÃO PRECISO DE SITE"', 'Meu cliente marca pelo WhatsApp, não preciso de site.'], ['ÓTIMO', 'Ótimo. O site não tira o WhatsApp de você.'], ['ELE RESPONDE POR VOCÊ', 'Ele responde por você: mostra os horários e marca.'], ['CONFIRMAÇÃO NO WHATSAPP', 'E a confirmação chega no WhatsApp do mesmo jeito.'], ['FAZ SENTIDO?', 'Faz sentido? Comenta aqui.']]],
        ],
    ];
    $lista = $banco[$objetivo] ?? $banco['dor'];
    $t = $lista[random_int(0, count($lista) - 1)];
    $c = $t['c'] + ['selo' => '', 'titulo' => '', 'subtitulo' => '', 'itens' => [], 'antes' => [], 'depois' => [], 'citacao' => '', 'autor' => '', 'preco' => '', 'cta' => 'Chama no direct'];
    return ['modelo' => $t['modelo'], 'estilo' => $estilos[random_int(0, count($estilos) - 1)], 'cena_foto' => $t['cena'], 'conteudo' => $c,
        'legenda' => $t['legenda'], 'hashtags' => array_values(array_unique(array_filter($tags))), 'roteiro' => mkt_roteiro($t['roteiro']),
        'dica_gravacao' => 'Grave na vertical, com luz de janela de frente e o texto da tela nos primeiros 2 segundos. Corte a cada frase.',
        'origem' => 'banco'];
}

// Post com números verdadeiros da base (sem IA)
function mkt_post_dados($dados, $marca) {
    $c = ['selo' => 'Dado real · ' . $dados['cidade'], 'titulo' => "*{$dados['pct']}%* das empresas" . ($dados['nicho'] ? " de {$dados['nicho']}" : '') . " de {$dados['cidade']} não têm site",
        'subtitulo' => $dados['frase'] . ' Quem tem, aparece primeiro.', 'preco' => $dados['pct'] . '%', 'cta' => 'Seja dos primeiros',
        'itens' => [], 'antes' => [], 'depois' => [], 'citacao' => '', 'autor' => ''];
    return ['modelo' => 'numero', 'estilo' => 'bold', 'cena_foto' => 'street view of a Brazilian neighborhood with small local shops, daylight', 'conteudo' => $c,
        'legenda' => $dados['frase'] . "\nQuando alguém pesquisa no Google, quem tem site sai na frente.\nQuer ser um dos primeiros da sua cidade? Chama no direct 👇",
        'hashtags' => ['empreendedorismo', 'negociolocal', mb_strtolower(preg_replace('/\W+/u', '', $dados['cidade'])), 'sitesprofissionais', 'marketingdigital'],
        'roteiro' => mkt_roteiro([[$dados['pct'] . '% SEM SITE', "{$dados['pct']} por cento das empresas" . ($dados['nicho'] ? " de {$dados['nicho']}" : '') . " de {$dados['cidade']} não têm site."],
            ['NÓS CONTAMOS', "A gente mapeou {$dados['total']} delas."], ['QUEM TEM, APARECE', 'Quem tem site aparece primeiro no Google.'], ['SEJA O PRIMEIRO', 'Quer ser um dos primeiros? Chama no direct.']]),
        'dica_gravacao' => 'Mostre o número grande na tela logo no primeiro segundo e fale olhando para a câmera.', 'origem' => 'dados'];
}

// Semana de conteúdo sem IA (mistura os objetivos)
function mkt_semana_sem_ia($publico) {
    $pub = trim((string)$publico) ?: 'negócios locais';
    $plano = [['Segunda', 'Reels', 'dor', 'Quantos clientes você perdeu hoje esperando resposta?'], ['Terça', 'Carrossel', 'educativo', 'Instagram não aparece quando procuram no Google'],
        ['Quarta', 'Story', 'bastidores', 'Bastidor: um site de ' . mb_strtolower($pub) . ' nascendo'], ['Quinta', 'Reels', 'objecao', '"Meu cliente marca pelo WhatsApp." Ótimo.'],
        ['Sexta', 'Feed', 'antes_depois', 'Antes: caderno. Depois: cliente marca sozinho.'], ['Sábado', 'Story', 'prova', 'O que o cliente diz depois do site (use um depoimento real)'],
        ['Domingo', 'Feed', 'oferta', 'Seu site pronto esta semana']];
    return array_map(fn($d) => ['dia' => $d[0], 'formato' => $d[1], 'objetivo' => $d[2], 'tema' => $d[3], 'gancho' => $d[3]], $plano);
}
