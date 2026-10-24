<?php
// ============================================================
// FRAGMENTOS FUNCIONAIS DO SITE PÚBLICO
// Usados tanto pelo template padrão quanto pelo modo "Site Total IA":
// a IA desenha o HTML/CSS/JS inteiro e deixa marcadores tipo
// {{SERVICOS}} no lugar de cada parte que precisa ser real (grava no
// banco de verdade). Estas funções geram o HTML real de cada marcador.
//
// Contrato de classes CSS que a IA é instruída a estilizar:
// .gds-servico-item, .gds-equipe-item, .gds-galeria-item,
// .gds-avaliacao-item, .gds-cupom-item, .gds-form, .gds-calendario...
// ============================================================

if (!function_exists('e')) {
    function e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
}

function frag_sobre($sobre) {
    return '<div class="gds-sobre"><p>' . nl2br(e($sobre ?: 'Em breve mais novidades por aqui.')) . '</p></div>';
}

function frag_preco($valor) {
    return (float)$valor > 0 ? 'R$ ' . number_format((float)$valor, 2, ',', '.') : 'Sob consulta';
}

function frag_servicos($servicos) {
    if (empty($servicos)) return '<p class="gds-vazio">Nenhum serviço cadastrado ainda.</p>';
    $html = '<div class="gds-servicos">';
    foreach ($servicos as $s) {
        $html .= '<div class="gds-servico-item">'
               . '<h3>' . e($s['nome']) . '</h3>'
               . ($s['descricao'] ? '<p>' . e($s['descricao']) . '</p>' : '')
               . '<span class="gds-preco">' . frag_preco($s['preco']) . '</span>'
               . ((int)$s['duracao_minutos'] > 0 ? ' <span class="gds-duracao">' . (int)$s['duracao_minutos'] . ' min</span>' : '')
               . '</div>';
    }
    return $html . '</div>';
}

function frag_equipe($profissionais) {
    if (empty($profissionais)) return '';
    $html = '<div class="gds-equipe">';
    foreach ($profissionais as $p) {
        $foto = $p['foto']
            ? '<img src="' . e($p['foto']) . '" alt="' . e($p['nome']) . '" loading="lazy">'
            : '<div class="gds-avatar-fallback" aria-hidden="true">' . e(mb_strtoupper(mb_substr($p['nome'], 0, 1))) . '</div>';
        $html .= '<div class="gds-equipe-item">' . $foto . '<h4>' . e($p['nome']) . '</h4><p>' . e($p['especialidade']) . '</p></div>';
    }
    return $html . '</div>';
}

function frag_galeria($imagens, $nomeEmpresa = '') {
    if (empty($imagens)) return '';
    $html = '<div class="gds-galeria">';
    foreach ($imagens as $i => $img) {
        $html .= '<img class="gds-galeria-item" src="' . e($img) . '" alt="Foto ' . ($i + 1) . ($nomeEmpresa ? ' de ' . e($nomeEmpresa) : '') . '" loading="lazy">';
    }
    return $html . '</div>';
}

function frag_estrelas($nota) {
    $nota = max(0, min(5, (int)round((float)$nota)));
    return '<span class="gds-stars" aria-label="' . $nota . ' de 5 estrelas">' . str_repeat('★', $nota) . str_repeat('☆', 5 - $nota) . '</span>';
}

// $estado: ['ok' => bool, 'erro' => string]
function frag_avaliacoes($avaliacoes, $estado = [], $permitirEnvio = true) {
    $html = '<div class="gds-avaliacoes">';
    if (empty($avaliacoes)) {
        $html .= '<p class="gds-vazio">Ainda sem avaliações. Seja o primeiro a avaliar!</p>';
    }
    foreach ($avaliacoes as $av) {
        $html .= '<div class="gds-avaliacao-item"><strong>' . e($av['autor'] ?? 'Cliente') . '</strong>'
               . '<div>' . frag_estrelas($av['nota']) . '</div><p>' . e($av['comentario']) . '</p></div>';
    }
    $html .= '</div>';
    if (!$permitirEnvio) return $html;

    if (!empty($estado['ok'])) {
        return $html . '<div class="gds-confirmacao gds-confirmacao-avaliacao"><div class="gds-confirmacao-icone">✓</div><h3>Obrigado pela avaliação!</h3><p>Ela aparece aqui assim que for aprovada.</p></div>';
    }
    $html .= '<details class="gds-avaliar"' . (!empty($estado['erro']) ? ' open' : '') . '><summary>Deixar minha avaliação</summary>';
    if (!empty($estado['erro'])) $html .= '<div class="gds-msg-erro">' . e($estado['erro']) . '</div>';
    $html .= '<form method="post" class="gds-form">' . frag_honeypot()
           . '<label for="av_nome">Seu nome</label><input type="text" id="av_nome" name="av_nome" maxlength="80" required>'
           . '<label for="av_nota">Nota</label><select id="av_nota" name="av_nota" required>'
           . '<option value="5">★★★★★ Excelente</option><option value="4">★★★★ Muito bom</option><option value="3">★★★ Bom</option><option value="2">★★ Regular</option><option value="1">★ Ruim</option></select>'
           . '<label for="av_comentario">Comentário</label><textarea id="av_comentario" name="av_comentario" rows="3" maxlength="600"></textarea>'
           . '<button type="submit" name="enviar_avaliacao" value="1">Enviar avaliação</button></form></details>';
    return $html;
}

function frag_cupons($cupons) {
    if (empty($cupons)) return '';
    $html = '<div class="gds-cupons">';
    foreach ($cupons as $c) {
        $html .= '<div class="gds-cupom-item"><strong>' . e($c['codigo']) . '</strong> — ' . number_format((float)$c['desconto_percentual'], 0) . '% de desconto'
               . ($c['descricao'] ? '<p>' . e($c['descricao']) . '</p>' : '')
               . ($c['validade'] ? '<small>Válido até ' . date('d/m/Y', strtotime($c['validade'])) . '</small>' : '')
               . '</div>';
    }
    return $html . '</div>';
}

function frag_mapa($endereco, $mapsKey) {
    if (!$endereco) return '';
    $src = $mapsKey
        ? 'https://www.google.com/maps/embed/v1/place?key=' . rawurlencode($mapsKey) . '&q=' . urlencode($endereco)
        : 'https://maps.google.com/maps?q=' . urlencode($endereco) . '&output=embed';
    return '<iframe class="gds-mapa" title="Mapa: ' . e($endereco) . '" width="100%" height="260" style="border:0;" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="' . e($src) . '"></iframe>';
}

function frag_whatsapp_float($whatsapp) {
    $numero = so_digitos_frag($whatsapp);
    if (!$numero) return '';
    return '<a class="gds-whatsapp-float" href="https://wa.me/' . $numero . '" target="_blank" rel="noopener" aria-label="Falar no WhatsApp">'
         . '<svg viewBox="0 0 24 24" width="26" height="26" fill="#fff" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.28-1.38a9.9 9.9 0 0 0 4.76 1.21h.01c5.46 0 9.9-4.45 9.9-9.91C21.96 6.45 17.5 2 12.04 2zm5.8 14.16c-.24.68-1.4 1.3-1.93 1.38-.5.08-1.12.11-1.81-.11-.42-.13-.95-.31-1.64-.6-2.88-1.24-4.76-4.15-4.9-4.34-.14-.19-1.17-1.55-1.17-2.97 0-1.41.74-2.1 1-2.39.26-.28.57-.35.76-.35.19 0 .38 0 .55.01.18.01.42-.07.65.5.24.58.81 2 .88 2.14.07.15.11.32.02.51-.09.19-.14.31-.28.48-.14.17-.29.37-.42.5-.14.14-.28.28-.12.56.16.28.71 1.17 1.53 1.9 1.05.94 1.94 1.23 2.22 1.37.28.14.44.12.6-.07.16-.19.68-.79.87-1.06.19-.28.37-.23.62-.14.26.09 1.63.77 1.91.91.28.14.47.21.54.33.07.12.07.68-.17 1.36z"/></svg>'
         . '</a>';
}

function so_digitos_frag($s) { return preg_replace('/\D/', '', (string)$s); }

// Campo invisível: pessoas não preenchem, robôs de spam sim
function frag_honeypot() {
    return '<div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">'
         . '<label>Não preencha<input type="text" name="site_url_confirm" tabindex="-1" autocomplete="off"></label>'
         . '<input type="hidden" name="gds_t" value="' . time() . '"></div>';
}

function gerar_link_google_calendar($titulo, $dataHoraInicio, $duracaoMinutos, $descricao, $local) {
    try {
        $inicio = new DateTime($dataHoraInicio);
    } catch (Exception $e) {
        return '';
    }
    $fim = clone $inicio;
    $fim->modify('+' . max(15, (int)$duracaoMinutos) . ' minutes');
    $fmt = 'Ymd\THis';
    $params = [
        'action' => 'TEMPLATE',
        'text' => $titulo,
        'dates' => $inicio->format($fmt) . '/' . $fim->format($fmt),
        'details' => $descricao,
        'location' => $local,
        'ctz' => 'America/Sao_Paulo', // sem isso, o Google interpreta pelo fuso da conta de quem abre o link, não da empresa
    ];
    return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
}

// $estado: ['ok','erro','pix','pix_erro','detalhes']   $agenda: horários de funcionamento
function frag_agendamento($servicos, $profissionais, $estado = [], $agenda = [], $empresaId = 0) {
    if (!empty($estado['ok'])) {
        $d = $estado['detalhes'] ?? null;
        $html = '<div class="gds-confirmacao">';
        $html .= '<div class="gds-confirmacao-icone">✓</div>';
        $html .= '<h3>Agendamento solicitado!</h3>';
        if ($d) {
            $html .= '<div class="gds-confirmacao-resumo">';
            if (!empty($d['servico_nome'])) $html .= '<p><strong>Serviço:</strong> ' . e($d['servico_nome']) . '</p>';
            if (!empty($d['profissional_nome'])) $html .= '<p><strong>Com:</strong> ' . e($d['profissional_nome']) . '</p>';
            if (!empty($d['data_formatada'])) $html .= '<p><strong>Quando:</strong> ' . e($d['data_formatada']) . '</p>';
            $html .= '</div><div class="gds-confirmacao-acoes">';
            if (!empty($d['link_calendario'])) {
                $html .= '<a href="' . e($d['link_calendario']) . '" target="_blank" rel="noopener" class="gds-btn-secundario">📅 Adicionar à minha agenda</a>';
            }
            if (!empty($d['empresa_whatsapp'])) {
                $numero = so_digitos_frag($d['empresa_whatsapp']);
                $msg = urlencode('Olá! Acabei de agendar' . (!empty($d['servico_nome']) ? ' ' . $d['servico_nome'] : '') . (!empty($d['data_formatada']) ? ' para ' . $d['data_formatada'] : '') . '. Pode confirmar?');
                $html .= '<a href="https://wa.me/' . $numero . '?text=' . $msg . '" target="_blank" rel="noopener" class="gds-btn-secundario">💬 Confirmar pelo WhatsApp</a>';
            }
            $html .= '</div>';
        } else {
            $html .= '<p>Em breve confirmaremos com você.</p>';
        }
        $html .= '</div>';
        if (!empty($estado['pix'])) {
            $pix = $estado['pix'];
            $html .= '<div class="gds-form gds-pix">';
            $html .= '<p><strong>Pague agora pra garantir seu horário</strong></p>';
            if (!empty($pix['qr_base64'])) {
                $html .= '<img src="data:image/png;base64,' . e($pix['qr_base64']) . '" alt="QR Code PIX" style="width:100%;max-width:240px;border-radius:8px;margin:8px auto;display:block;">';
            }
            $html .= '<label>Código PIX copia-e-cola</label><textarea readonly rows="3" onclick="this.select()">' . e($pix['qr_code']) . '</textarea>'
                   . '<button type="button" onclick="navigator.clipboard&&navigator.clipboard.writeText(this.previousElementSibling.value);this.textContent=\'Copiado!\'">Copiar código</button></div>';
        } elseif (!empty($estado['pix_erro'])) {
            $html .= '<div class="gds-msg-erro">Não foi possível gerar a cobrança agora. O agendamento foi feito normalmente.</div>';
        }
        return $html;
    }

    $abertura = substr($agenda['abertura'] ?? '08:00', 0, 5);
    $fechamento = substr($agenda['fechamento'] ?? '19:00', 0, 5);
    $intervalo = max(10, (int)($agenda['intervalo'] ?? 30));
    $dias = $agenda['dias'] ?? '1,2,3,4,5,6';
    $antecedencia = max(0, (int)($agenda['antecedencia_dias'] ?? 60));
    $post = $estado['post'] ?? [];

    $html = '';
    if (!empty($estado['erro'])) $html .= '<div class="gds-msg-erro" role="alert">' . e($estado['erro']) . '</div>';
    $html .= '<form method="post" class="gds-form gds-form-agendamento">' . frag_honeypot();
    $html .= '<label for="ag_nome">Seu nome</label><input type="text" id="ag_nome" name="ag_nome" maxlength="120" required autocomplete="name" value="' . e($post['ag_nome'] ?? '') . '">';
    $html .= '<label for="ag_telefone">WhatsApp / telefone</label><input type="tel" id="ag_telefone" name="ag_telefone" maxlength="20" required autocomplete="tel" inputmode="tel" placeholder="(11) 99999-9999" value="' . e($post['ag_telefone'] ?? '') . '">';
    $html .= '<label for="ag_email">E-mail (opcional)</label><input type="email" id="ag_email" name="ag_email" maxlength="150" autocomplete="email" value="' . e($post['ag_email'] ?? '') . '">';
    if (!empty($servicos)) {
        $html .= '<label for="gds-ag-servico">Serviço</label><select name="ag_servico_id" id="gds-ag-servico" required><option value="">Selecione…</option>';
        foreach ($servicos as $s) {
            $sel = (string)($post['ag_servico_id'] ?? '') === (string)$s['id'] ? ' selected' : '';
            $html .= '<option value="' . (int)$s['id'] . '" data-duracao="' . (int)$s['duracao_minutos'] . '"' . $sel . '>' . e($s['nome']) . ' — ' . frag_preco($s['preco']) . '</option>';
        }
        $html .= '</select>';
    }
    if (!empty($profissionais)) {
        $html .= '<label for="gds-ag-profissional">Profissional</label><select name="ag_profissional_id" id="gds-ag-profissional"><option value="">Sem preferência</option>';
        foreach ($profissionais as $p) {
            $sel = (string)($post['ag_profissional_id'] ?? '') === (string)$p['id'] ? ' selected' : '';
            $html .= '<option value="' . (int)$p['id'] . '"' . $sel . '>' . e($p['nome']) . ($p['especialidade'] ? ' — ' . e($p['especialidade']) : '') . '</option>';
        }
        $html .= '</select>';
    }
    $html .= '<label>Escolha o dia</label>';
    $html .= '<div class="gds-calendario" id="gds-calendario" data-empresa="' . (int)$empresaId . '" data-dias="' . e($dias) . '" data-abertura="' . e($abertura) . '" data-fechamento="' . e($fechamento) . '" data-intervalo="' . $intervalo . '" data-max-dias="' . $antecedencia . '">'
           . '<div class="gds-cal-header"><button type="button" id="gds-cal-prev" aria-label="Mês anterior">‹</button><strong id="gds-cal-mes-label" aria-live="polite"></strong><button type="button" id="gds-cal-next" aria-label="Próximo mês">›</button></div>'
           . '<div class="gds-cal-dias-semana"><span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span></div>'
           . '<div class="gds-cal-grid" id="gds-cal-grid"></div></div>';
    $html .= '<div id="gds-cal-horario-wrap" hidden><label>Horário</label><div class="gds-horarios" id="gds-horarios" role="listbox" aria-label="Horários disponíveis"></div></div>';
    $html .= '<input type="hidden" name="ag_data_hora" id="gds-cal-valor">';
    $html .= '<label for="ag_obs">Observação (opcional)</label><textarea id="ag_obs" name="ag_observacoes" rows="2" maxlength="500">' . e($post['ag_observacoes'] ?? '') . '</textarea>';
    $html .= '<button type="submit" name="fazer_agendamento" value="1" id="gds-cal-submit" disabled>Escolha dia e horário</button>';
    $html .= '</form>';
    $html .= '<script>' . frag_calendario_js() . '</script>';
    return $html;
}

function frag_calendario_js() {
    return <<<'JS'
(function(){
  var cal = document.getElementById('gds-calendario');
  if (!cal) return;
  var hoje = new Date(); hoje.setHours(0,0,0,0);
  var view = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
  var diaEscolhido = null, horaEscolhida = null, reqId = 0;
  var meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
  var dias = (cal.dataset.dias || '1,2,3,4,5,6').split(',').map(Number);
  var abre = (cal.dataset.abertura || '08:00').split(':').map(Number);
  var fecha = (cal.dataset.fechamento || '19:00').split(':').map(Number);
  var passo = parseInt(cal.dataset.intervalo || '30', 10);
  var maxDias = parseInt(cal.dataset.maxDias || '60', 10);
  var limite = new Date(hoje); limite.setDate(limite.getDate() + (maxDias > 0 ? maxDias : 3650));
  var grid = document.getElementById('gds-cal-grid');
  var label = document.getElementById('gds-cal-mes-label');
  var wrap = document.getElementById('gds-cal-horario-wrap');
  var lista = document.getElementById('gds-horarios');
  var valor = document.getElementById('gds-cal-valor');
  var submit = document.getElementById('gds-cal-submit');
  var selServico = document.getElementById('gds-ag-servico');
  var selProf = document.getElementById('gds-ag-profissional');
  function pad(n){ return String(n).padStart(2,'0'); }
  function iso(d){ return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()); }
  function duracao(){ var o = selServico && selServico.selectedOptions[0]; var d = o ? parseInt(o.dataset.duracao || '0', 10) : 0; return d > 0 ? d : passo; }
  function atualizarBotao(){
    var ok = diaEscolhido && horaEscolhida;
    valor.value = ok ? iso(diaEscolhido) + 'T' + horaEscolhida : '';
    submit.disabled = !ok;
    submit.textContent = ok ? 'Agendar para ' + pad(diaEscolhido.getDate()) + '/' + pad(diaEscolhido.getMonth()+1) + ' às ' + horaEscolhida : 'Escolha dia e horário';
  }
  function horarios(ocupados){
    lista.innerHTML = '';
    var agora = new Date(), ehHoje = diaEscolhido.getTime() === hoje.getTime(), dur = duracao(), qtd = 0;
    var ini = abre[0]*60 + (abre[1]||0), fim = fecha[0]*60 + (fecha[1]||0);
    for (var m = ini; m + dur <= fim; m += passo) {
      var h = pad(Math.floor(m/60)) + ':' + pad(m%60);
      if (ehHoje && m <= agora.getHours()*60 + agora.getMinutes() + 30) continue; // sem horário em cima da hora
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'gds-horario'; b.textContent = h; b.setAttribute('role','option');
      if (ocupados.indexOf(h) !== -1) { b.disabled = true; b.classList.add('gds-horario-ocupado'); b.title = 'Ocupado'; }
      else { qtd++; }
      if (h === horaEscolhida && !b.disabled) b.classList.add('gds-horario-selecionado');
      (function(hora, btn){ btn.addEventListener('click', function(){
        horaEscolhida = hora;
        lista.querySelectorAll('.gds-horario').forEach(function(x){ x.classList.remove('gds-horario-selecionado'); x.setAttribute('aria-selected','false'); });
        btn.classList.add('gds-horario-selecionado'); btn.setAttribute('aria-selected','true');
        atualizarBotao();
      }); })(h, b);
      lista.appendChild(b);
    }
    if (!qtd) { lista.innerHTML = '<p class="gds-vazio">Sem horários livres neste dia. Escolha outro dia.</p>'; horaEscolhida = null; }
    atualizarBotao();
  }
  function carregarHorarios(){
    if (!diaEscolhido) return;
    wrap.hidden = false;
    lista.innerHTML = '<p class="gds-vazio">Carregando horários…</p>';
    var id = ++reqId;
    var url = '/site/horarios.php?e=' + encodeURIComponent(cal.dataset.empresa) + '&data=' + iso(diaEscolhido)
            + '&duracao=' + duracao() + (selProf && selProf.value ? '&profissional=' + encodeURIComponent(selProf.value) : '');
    fetch(url).then(function(r){ return r.json(); }).catch(function(){ return { ocupados: [] }; }).then(function(d){
      if (id === reqId) horarios((d && d.ocupados) || []);
    });
  }
  function render(){
    label.textContent = meses[view.getMonth()] + ' ' + view.getFullYear();
    grid.innerHTML = '';
    var primeiro = new Date(view.getFullYear(), view.getMonth(), 1).getDay();
    var total = new Date(view.getFullYear(), view.getMonth()+1, 0).getDate();
    for (var i = 0; i < primeiro; i++) grid.appendChild(document.createElement('span'));
    for (var dia = 1; dia <= total; dia++) {
      var data = new Date(view.getFullYear(), view.getMonth(), dia);
      var btn = document.createElement('button');
      btn.type = 'button'; btn.textContent = dia; btn.className = 'gds-cal-dia';
      var fechado = dias.indexOf(data.getDay()) === -1;
      if (data < hoje || data > limite || fechado) { btn.disabled = true; btn.classList.add('gds-cal-dia-passado'); if (fechado) btn.title = 'Fechado'; }
      if (diaEscolhido && data.getTime() === diaEscolhido.getTime()) btn.classList.add('gds-cal-dia-selecionado');
      (function(d){ btn.addEventListener('click', function(){ diaEscolhido = d; horaEscolhida = null; render(); carregarHorarios(); }); })(data);
      grid.appendChild(btn);
    }
    document.getElementById('gds-cal-prev').disabled = view <= new Date(hoje.getFullYear(), hoje.getMonth(), 1);
  }
  document.getElementById('gds-cal-prev').addEventListener('click', function(){ view.setMonth(view.getMonth()-1); render(); });
  document.getElementById('gds-cal-next').addEventListener('click', function(){ view.setMonth(view.getMonth()+1); render(); });
  if (selServico) selServico.addEventListener('change', carregarHorarios);
  if (selProf) selProf.addEventListener('change', carregarHorarios);
  render();
})();
JS;
}

// $estado: ['ok','erro','nome','post']
function frag_contato($estado = []) {
    if (!empty($estado['ok'])) {
        $saudacao = !empty($estado['nome']) ? e($estado['nome']) . ', sua mensagem foi enviada!' : 'Mensagem enviada!';
        return '<div class="gds-confirmacao"><div class="gds-confirmacao-icone">✓</div><h3>' . $saudacao . '</h3><p>Entraremos em contato em breve.</p></div>';
    }
    $post = $estado['post'] ?? [];
    $html = '';
    if (!empty($estado['erro'])) $html .= '<div class="gds-msg-erro" role="alert">' . e($estado['erro']) . '</div>';
    $html .= '<form method="post" class="gds-form gds-form-contato">' . frag_honeypot();
    $html .= '<label for="ct_nome">Nome</label><input type="text" id="ct_nome" name="nome" maxlength="120" required autocomplete="name" value="' . e($post['nome'] ?? '') . '">';
    $html .= '<label for="ct_email">E-mail</label><input type="email" id="ct_email" name="email" maxlength="150" autocomplete="email" value="' . e($post['email'] ?? '') . '">';
    $html .= '<label for="ct_tel">WhatsApp / telefone</label><input type="tel" id="ct_tel" name="telefone" maxlength="20" autocomplete="tel" inputmode="tel" value="' . e($post['telefone'] ?? '') . '">';
    $html .= '<label for="ct_msg">Mensagem</label><textarea id="ct_msg" name="mensagem" rows="4" maxlength="2000" required>' . e($post['mensagem'] ?? '') . '</textarea>';
    $html .= '<button type="submit" name="enviar_lead" value="1">Enviar mensagem</button>';
    $html .= '</form>';
    return $html;
}

// ============================================================
// Estilo mínimo de apoio para os fragmentos injetados — cobre o
// básico (formulário legível, calendário, grid de imagens) caso a IA
// esqueça de estilizar alguma classe. Usa cores herdadas (currentColor)
// pra combinar com qualquer design. Fica ANTES do </head>.
// ============================================================
function frag_css_apoio() {
    return '<style id="gds-apoio">
.gds-servicos{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:14px}
.gds-equipe{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px}
.gds-equipe-item img,.gds-avatar-fallback{width:96px;height:96px;border-radius:50%;object-fit:cover}
.gds-avatar-fallback{display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;background:rgba(128,128,128,.18)}
.gds-galeria{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px}
.gds-galeria-item{width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:10px;display:block}
.gds-duracao{opacity:.7;font-size:.85em}
.gds-vazio{opacity:.7}
.gds-form{display:flex;flex-direction:column;gap:6px;max-width:460px;position:relative}
.gds-form label{font-size:13px;opacity:.85;margin-top:8px}
.gds-form input,.gds-form select,.gds-form textarea{padding:11px 12px;border-radius:8px;border:1px solid rgba(128,128,128,.35);font:inherit;font-size:16px;background:rgba(255,255,255,.04);color:inherit;width:100%;box-sizing:border-box}
.gds-form select option{color:#111}
.gds-form button{margin-top:12px;padding:13px 16px;border:none;border-radius:8px;cursor:pointer;font:inherit;font-weight:600;min-height:44px}
.gds-form button:disabled{opacity:.55;cursor:not-allowed}
.gds-msg-sucesso{background:rgba(94,224,108,.15);border:1px solid rgba(94,224,108,.4);color:#2f9e46;padding:12px 16px;border-radius:8px}
.gds-msg-erro{background:rgba(255,90,90,.12);border:1px solid rgba(255,90,90,.45);color:#d64545;padding:12px 16px;border-radius:8px;margin-bottom:10px}
.gds-whatsapp-float{position:fixed;bottom:20px;right:20px;z-index:999;width:56px;height:56px;border-radius:50%;background:#25D366;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 20px rgba(0,0,0,.3);text-decoration:none}
.gds-calendario{border:1px solid rgba(128,128,128,.3);border-radius:12px;padding:14px;max-width:340px}
.gds-cal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
.gds-cal-header button{background:none;border:1px solid rgba(128,128,128,.35);border-radius:8px;width:36px;height:36px;cursor:pointer;font-size:18px;color:inherit;margin:0;padding:0;min-height:0}
.gds-cal-dias-semana{display:grid;grid-template-columns:repeat(7,1fr);text-align:center;font-size:11px;opacity:.6;margin-bottom:4px}
.gds-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:4px}
.gds-form .gds-cal-dia{aspect-ratio:1;border:none;border-radius:8px;background:rgba(128,128,128,.14);cursor:pointer;font-size:13px;color:inherit;margin:0;padding:0;min-height:36px;font-weight:500}
.gds-cal-dia:hover:not(:disabled){background:rgba(128,128,128,.3)}
.gds-form .gds-cal-dia-passado{opacity:.25;cursor:not-allowed}
.gds-form .gds-cal-dia-selecionado{background:#4f7cff;color:#fff;font-weight:700}
.gds-horarios{display:grid;grid-template-columns:repeat(auto-fill,minmax(74px,1fr));gap:6px}
.gds-form .gds-horario{margin:0;padding:10px 4px;border-radius:8px;border:1px solid rgba(128,128,128,.35);background:transparent;color:inherit;font-weight:500;min-height:42px}
.gds-form .gds-horario-selecionado{background:#4f7cff;border-color:#4f7cff;color:#fff}
.gds-form .gds-horario-ocupado{text-decoration:line-through;opacity:.35}
.gds-confirmacao{background:rgba(94,224,108,.08);border:1px solid rgba(94,224,108,.35);border-radius:12px;padding:24px 20px;text-align:center;max-width:460px}
.gds-confirmacao-icone{width:48px;height:48px;border-radius:50%;background:#2f9e46;color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 12px}
.gds-confirmacao h3{margin-bottom:10px}
.gds-confirmacao-resumo{text-align:left;background:rgba(128,128,128,.1);border-radius:8px;padding:12px 14px;margin:12px 0;font-size:14px}
.gds-confirmacao-resumo p{margin:4px 0}
.gds-confirmacao-acoes{display:flex;flex-direction:column;gap:8px;margin-top:12px}
.gds-btn-secundario{display:block;padding:12px;border-radius:8px;border:1px solid rgba(128,128,128,.4);text-decoration:none;color:inherit;font-size:14px;font-weight:600}
.gds-avaliacoes{display:grid;gap:12px}
.gds-avaliar{margin-top:16px}
.gds-avaliar summary{cursor:pointer;font-weight:600;padding:10px 0}
.gds-cupons{display:grid;gap:10px}
.gds-pix{margin-top:14px;text-align:center}
@media (max-width:480px){.gds-form{max-width:100%}.gds-calendario{max-width:100%;padding:10px}.gds-whatsapp-float{width:52px;height:52px;bottom:14px;right:14px}.gds-confirmacao{padding:18px 14px}}
@media (prefers-reduced-motion:reduce){*{scroll-behavior:auto!important}}
</style>';
}
