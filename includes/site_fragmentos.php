<?php
// ============================================================
// FRAGMENTOS FUNCIONAIS PARA O MODO "SITE TOTAL IA"
// A IA desenha o HTML/CSS/JS inteiro do site, com liberdade total
// de visual e animações. Mas ela deixa marcadores tipo {{SERVICOS}}
// no lugar de cada parte que precisa ser real (grava no banco de
// verdade). Essas funções geram o HTML real que substitui cada
// marcador na hora de servir a página.
//
// Contrato de classes CSS que a IA é instruída a estilizar:
// .gds-servico-item, .gds-equipe-item, .gds-galeria-item,
// .gds-avaliacao-item, .gds-cupom-item, .gds-form (inputs/botões)
// ============================================================

if (!function_exists('e')) {
    function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
}

function frag_sobre($sobre) {
    return '<p>' . nl2br(e($sobre ?: 'Em breve mais novidades por aqui.')) . '</p>';
}

function frag_servicos($servicos) {
    if (empty($servicos)) return '<p>Nenhum serviço cadastrado ainda.</p>';
    $html = '';
    foreach ($servicos as $s) {
        $html .= '<div class="gds-servico-item">'
               . '<h3>' . e($s['nome']) . '</h3>'
               . '<p>' . e($s['descricao']) . '</p>'
               . '<span class="gds-preco">R$ ' . number_format($s['preco'],2,',','.') . '</span>'
               . '</div>';
    }
    return $html;
}

function frag_equipe($profissionais) {
    if (empty($profissionais)) return '';
    $html = '';
    foreach ($profissionais as $p) {
        $foto = $p['foto'] ? '<img src="' . e($p['foto']) . '" alt="' . e($p['nome']) . '">' : '<div class="gds-avatar-fallback">' . e(mb_substr($p['nome'],0,1)) . '</div>';
        $html .= '<div class="gds-equipe-item">' . $foto . '<h4>' . e($p['nome']) . '</h4><p>' . e($p['especialidade']) . '</p></div>';
    }
    return $html;
}

function frag_galeria($imagens) {
    if (empty($imagens)) return '';
    $html = '';
    foreach ($imagens as $img) {
        $html .= '<img class="gds-galeria-item" src="' . e($img) . '" alt="Foto" loading="lazy">';
    }
    return $html;
}

function frag_avaliacoes($avaliacoes) {
    if (empty($avaliacoes)) return '<p>Ainda sem avaliações.</p>';
    $html = '';
    foreach ($avaliacoes as $av) {
        $estrelas = str_repeat('★', (int)$av['nota']) . str_repeat('☆', 5-(int)$av['nota']);
        $html .= '<div class="gds-avaliacao-item"><strong>' . e($av['cliente_nome'] ?? 'Cliente') . '</strong>'
               . '<div class="gds-stars">' . $estrelas . '</div><p>' . e($av['comentario']) . '</p></div>';
    }
    return $html;
}

function frag_cupons($cupons) {
    if (empty($cupons)) return '';
    $html = '';
    foreach ($cupons as $c) {
        $html .= '<div class="gds-cupom-item"><strong>' . e($c['codigo']) . '</strong> — ' . number_format($c['desconto_percentual'],0) . '% de desconto'
               . '<p>' . e($c['descricao']) . '</p></div>';
    }
    return $html;
}

function frag_mapa($endereco, $mapsKey) {
    if (!$endereco) return '';
    if ($mapsKey) {
        $src = 'https://www.google.com/maps/embed/v1/place?key=' . e($mapsKey) . '&q=' . urlencode($endereco);
    } else {
        $src = 'https://maps.google.com/maps?q=' . urlencode($endereco) . '&output=embed';
    }
    return '<iframe class="gds-mapa" width="100%" height="220" style="border:0;" loading="lazy" src="' . $src . '"></iframe>';
}

function frag_whatsapp_float($whatsapp) {
    if (!$whatsapp) return '';
    $numero = preg_replace('/\D/', '', $whatsapp);
    return '<a class="gds-whatsapp-float" href="https://wa.me/' . $numero . '" target="_blank" aria-label="WhatsApp">'
         . '<svg viewBox="0 0 24 24" width="26" height="26" fill="#fff"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.28-1.38a9.9 9.9 0 0 0 4.76 1.21h.01c5.46 0 9.9-4.45 9.9-9.91C21.96 6.45 17.5 2 12.04 2zm5.8 14.16c-.24.68-1.4 1.3-1.93 1.38-.5.08-1.12.11-1.81-.11-.42-.13-.95-.31-1.64-.6-2.88-1.24-4.76-4.15-4.9-4.34-.14-.19-1.17-1.55-1.17-2.97 0-1.41.74-2.1 1-2.39.26-.28.57-.35.76-.35.19 0 .38 0 .55.01.18.01.42-.07.65.5.24.58.81 2 .88 2.14.07.15.11.32.02.51-.09.19-.14.31-.28.48-.14.17-.29.37-.42.5-.14.14-.28.28-.12.56.16.28.71 1.17 1.53 1.9 1.05.94 1.94 1.23 2.22 1.37.28.14.44.12.6-.07.16-.19.68-.79.87-1.06.19-.28.37-.23.62-.14.26.09 1.63.77 1.91.91.28.14.47.21.54.33.07.12.07.68-.17 1.36z"/></svg>'
         . '</a>';
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

function frag_agendamento($servicos, $profissionais, $agendamentoOk, $agendamentoErro, $pixParaMostrar = null, $pixErro = '', $detalhes = null) {
    if ($agendamentoOk) {
        $html = '<div class="gds-confirmacao">';
        $html .= '<div class="gds-confirmacao-icone">✓</div>';
        $html .= '<h3>Agendamento confirmado!</h3>';
        if ($detalhes) {
            $html .= '<div class="gds-confirmacao-resumo">';
            if (!empty($detalhes['servico_nome'])) $html .= '<p><strong>Serviço:</strong> ' . e($detalhes['servico_nome']) . '</p>';
            if (!empty($detalhes['data_formatada'])) $html .= '<p><strong>Quando:</strong> ' . e($detalhes['data_formatada']) . '</p>';
            $html .= '</div>';
            $html .= '<div class="gds-confirmacao-acoes">';
            if (!empty($detalhes['link_calendario'])) {
                $html .= '<a href="' . e($detalhes['link_calendario']) . '" target="_blank" rel="noopener" class="gds-btn-secundario">📅 Adicionar ao Google Calendar</a>';
            }
            if (!empty($detalhes['empresa_whatsapp'])) {
                $numero = preg_replace('/\D/', '', $detalhes['empresa_whatsapp']);
                $msg = urlencode('Olá! Acabei de agendar' . (!empty($detalhes['servico_nome']) ? ' ' . $detalhes['servico_nome'] : '') . (!empty($detalhes['data_formatada']) ? ' para ' . $detalhes['data_formatada'] : '') . '. Pode confirmar?');
                $html .= '<a href="https://wa.me/' . $numero . '?text=' . $msg . '" target="_blank" rel="noopener" class="gds-btn-secundario">💬 Confirmar via WhatsApp</a>';
            }
            $html .= '</div>';
        } else {
            $html .= '<p>Em breve confirmaremos com você.</p>';
        }
        $html .= '</div>';
        if ($pixParaMostrar) {
            $html .= '<div class="gds-form" style="margin-top:14px;text-align:center;">';
            $html .= '<p style="margin-bottom:10px;font-weight:600;">Pague agora pra garantir seu horário</p>';
            if ($pixParaMostrar['qr_base64']) {
                $html .= '<img src="data:image/png;base64,' . $pixParaMostrar['qr_base64'] . '" style="width:100%;max-width:260px;border-radius:8px;margin-bottom:12px;">';
            }
            $html .= '<label>Código PIX copia-e-cola</label><textarea readonly rows="3" onclick="this.select()">' . e($pixParaMostrar['qr_code']) . '</textarea></div>';
        } elseif ($pixErro) {
            $html .= '<div class="gds-msg-erro" style="margin-top:12px;">Não foi possível gerar a cobrança agora. O agendamento foi feito normalmente.</div>';
        }
        return $html;
    }
    $html = '';
    if ($agendamentoErro) $html .= '<div class="gds-msg-erro">' . e($agendamentoErro) . '</div>';
    $html .= '<form method="post" class="gds-form">';
    $html .= '<label>Seu nome</label><input type="text" name="ag_nome" required>';
    $html .= '<label>E-mail</label><input type="email" name="ag_email">';
    $html .= '<label>Telefone</label><input type="text" name="ag_telefone">';
    if (!empty($servicos)) {
        $html .= '<label>Serviço</label><select name="ag_servico_id"><option value="">-- selecione --</option>';
        foreach ($servicos as $s) { $html .= '<option value="' . $s['id'] . '">' . e($s['nome']) . '</option>'; }
        $html .= '</select>';
    }
    if (!empty($profissionais)) {
        $html .= '<label>Profissional (opcional)</label><select name="ag_profissional_id"><option value="">-- sem preferência --</option>';
        foreach ($profissionais as $p) { $html .= '<option value="' . $p['id'] . '">' . e($p['nome']) . '</option>'; }
        $html .= '</select>';
    }
    $html .= '<label>Escolha o dia</label>';
    $html .= '<div class="gds-calendario" id="gds-calendario">'
           . '<div class="gds-cal-header"><button type="button" id="gds-cal-prev">‹</button><strong id="gds-cal-mes-label"></strong><button type="button" id="gds-cal-next">›</button></div>'
           . '<div class="gds-cal-dias-semana"><span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span></div>'
           . '<div class="gds-cal-grid" id="gds-cal-grid"></div></div>';
    $html .= '<div id="gds-cal-horario-wrap" style="display:none;margin-top:10px;"><label>Horário</label><select id="gds-cal-horario-select"></select></div>';
    $html .= '<input type="hidden" name="ag_data_hora" id="gds-cal-valor" required>';
    $html .= '<button type="submit" name="fazer_agendamento" id="gds-cal-submit" disabled>Solicitar agendamento</button>';
    $html .= '</form>';
    $html .= '<script>' . frag_calendario_js() . '</script>';
    return $html;
}

function frag_calendario_js() {
    return <<<JS
(function(){
  var hoje = new Date(); hoje.setHours(0,0,0,0);
  var view = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
  var diaEscolhido = null;
  var meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
  var grid = document.getElementById('gds-cal-grid');
  if (!grid) return;
  var label = document.getElementById('gds-cal-mes-label');
  var horarioWrap = document.getElementById('gds-cal-horario-wrap');
  var horarioSelect = document.getElementById('gds-cal-horario-select');
  var valorInput = document.getElementById('gds-cal-valor');
  var submitBtn = document.getElementById('gds-cal-submit');

  function gerarHorarios() {
    horarioSelect.innerHTML = '';
    var agora = new Date();
    var ehHoje = diaEscolhido && diaEscolhido.getTime() === hoje.getTime();
    for (var h = 8; h <= 19; h++) {
      for (var m = 0; m < 60; m += 30) {
        if (h === 19 && m > 0) continue;
        if (ehHoje && (h < agora.getHours() || (h === agora.getHours() && m <= agora.getMinutes()))) continue; // não deixa marcar horário que já passou hoje
        var hh = String(h).padStart(2,'0'), mm = String(m).padStart(2,'0');
        var opt = document.createElement('option');
        opt.value = hh + ':' + mm;
        opt.textContent = hh + ':' + mm;
        horarioSelect.appendChild(opt);
      }
    }
    if (horarioSelect.options.length === 0) {
      var vazio = document.createElement('option');
      vazio.value = ''; vazio.textContent = 'Sem horários disponíveis hoje';
      horarioSelect.appendChild(vazio);
      submitBtn.disabled = true;
    }
    horarioSelect.selectedIndex = 0; // garante o primeiro horário disponível selecionado de verdade
  }

  function atualizarValor() {
    if (diaEscolhido && horarioSelect.value) {
      var y = diaEscolhido.getFullYear(), m = String(diaEscolhido.getMonth()+1).padStart(2,'0'), d = String(diaEscolhido.getDate()).padStart(2,'0');
      valorInput.value = y + '-' + m + '-' + d + 'T' + horarioSelect.value;
      submitBtn.disabled = false;
    }
  }

  function render() {
    label.textContent = meses[view.getMonth()] + ' ' + view.getFullYear();
    grid.innerHTML = '';
    var primeiroDiaSemana = new Date(view.getFullYear(), view.getMonth(), 1).getDay();
    var totalDias = new Date(view.getFullYear(), view.getMonth()+1, 0).getDate();
    for (var i = 0; i < primeiroDiaSemana; i++) { grid.appendChild(document.createElement('span')); }
    for (var dia = 1; dia <= totalDias; dia++) {
      var data = new Date(view.getFullYear(), view.getMonth(), dia);
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = dia;
      btn.className = 'gds-cal-dia';
      if (data < hoje) { btn.disabled = true; btn.classList.add('gds-cal-dia-passado'); }
      if (diaEscolhido && data.getTime() === diaEscolhido.getTime()) { btn.classList.add('gds-cal-dia-selecionado'); }
      (function(d){
        btn.addEventListener('click', function(){
          diaEscolhido = d;
          horarioWrap.style.display = 'block';
          gerarHorarios();
          atualizarValor();
          render();
        });
      })(data);
      grid.appendChild(btn);
    }
  }

  document.getElementById('gds-cal-prev').addEventListener('click', function(){ view.setMonth(view.getMonth()-1); render(); });
  document.getElementById('gds-cal-next').addEventListener('click', function(){ view.setMonth(view.getMonth()+1); render(); });
  horarioSelect.addEventListener('change', atualizarValor);
  render();
})();
JS;
}

function frag_contato($leadOk, $nomeCliente = '') {
    if ($leadOk) {
        $saudacao = $nomeCliente ? e($nomeCliente) . ', mensagem' : 'Mensagem';
        return '<div class="gds-confirmacao"><div class="gds-confirmacao-icone">✓</div><h3>' . $saudacao . ' enviada!</h3><p>Entraremos em contato em breve.</p></div>';
    }
    $html = '<form method="post" class="gds-form">';
    $html .= '<label>Nome</label><input type="text" name="nome" required>';
    $html .= '<label>E-mail</label><input type="email" name="email">';
    $html .= '<label>Telefone</label><input type="text" name="telefone">';
    $html .= '<label>Mensagem</label><textarea name="mensagem" rows="3"></textarea>';
    $html .= '<button type="submit" name="enviar_lead">Enviar mensagem</button>';
    $html .= '</form>';
    return $html;
}

// ============================================================
// Estilo mínimo de apoio para os fragmentos injetados — cobre o
// básico (formulário legível, grid de imagens) caso a IA esqueça
// de estilizar alguma classe. Fica ANTES do </head> do HTML gerado.
// ============================================================
function frag_css_apoio() {
    return '<style id="gds-apoio">
.gds-form{display:flex;flex-direction:column;gap:6px;max-width:420px;}
.gds-form label{font-size:13px;opacity:0.8;margin-top:8px;}
.gds-form input,.gds-form select,.gds-form textarea{padding:10px;border-radius:8px;border:1px solid rgba(128,128,128,0.3);font-family:inherit;}
.gds-form button{margin-top:12px;padding:12px;border:none;border-radius:8px;cursor:pointer;font-weight:600;}
.gds-msg-sucesso{background:rgba(94,224,108,0.15);border:1px solid rgba(94,224,108,0.4);color:#2f9e46;padding:12px 16px;border-radius:8px;}
.gds-msg-erro{background:rgba(255,90,90,0.15);border:1px solid rgba(255,90,90,0.4);color:#c0392b;padding:12px 16px;border-radius:8px;margin-bottom:10px;}
.gds-whatsapp-float{position:fixed;bottom:20px;right:20px;z-index:999;width:54px;height:54px;border-radius:50%;background:#25D366;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(0,0,0,0.3);text-decoration:none;}
.gds-calendario{border:1px solid rgba(128,128,128,0.25);border-radius:10px;padding:14px;max-width:320px;}
.gds-cal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
.gds-cal-header button{background:none;border:1px solid rgba(128,128,128,0.3);border-radius:6px;width:28px;height:28px;cursor:pointer;font-size:16px;}
.gds-cal-dias-semana{display:grid;grid-template-columns:repeat(7,1fr);text-align:center;font-size:11px;opacity:0.6;margin-bottom:4px;}
.gds-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;}
.gds-cal-dia{aspect-ratio:1;border:none;border-radius:6px;background:rgba(128,128,128,0.12);cursor:pointer;font-size:13px;}
.gds-cal-dia:hover:not(:disabled){background:rgba(128,128,128,0.3);}
.gds-cal-dia-passado{opacity:0.25;cursor:not-allowed;}
.gds-cal-dia-selecionado{background:#4f7cff!important;color:#fff;font-weight:600;}
#gds-cal-submit:disabled{opacity:0.5;cursor:not-allowed;}
.gds-confirmacao{background:rgba(94,224,108,0.08);border:1px solid rgba(94,224,108,0.35);border-radius:12px;padding:24px 20px;text-align:center;max-width:420px;}
.gds-confirmacao-icone{width:48px;height:48px;border-radius:50%;background:#2f9e46;color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 12px;}
.gds-confirmacao h3{margin-bottom:10px;}
.gds-confirmacao-resumo{text-align:left;background:rgba(0,0,0,0.06);border-radius:8px;padding:12px 14px;margin:12px 0;font-size:14px;}
.gds-confirmacao-resumo p{margin:4px 0;}
.gds-confirmacao-acoes{display:flex;flex-direction:column;gap:8px;margin-top:12px;}
.gds-btn-secundario{display:block;padding:12px;border-radius:8px;border:1px solid rgba(128,128,128,0.35);text-decoration:none;color:inherit;font-size:14px;font-weight:600;}
/* ---------- REFORÇO MOBILE ---------- */
@media (max-width: 480px) {
  .gds-form{max-width:100%;padding:0 2px;}
  .gds-form input,.gds-form select,.gds-form textarea,.gds-form button{font-size:16px;} /* evita zoom automático no iOS */
  .gds-calendario{max-width:100%;padding:10px;}
  .gds-cal-dia{font-size:12px;min-height:36px;}
  .gds-cal-header button{width:36px;height:36px;} /* alvo de toque adequado */
  .gds-whatsapp-float{width:50px;height:50px;bottom:14px;right:14px;}
  .gds-confirmacao{padding:18px 14px;}
  .gds-btn-secundario{padding:14px;}
}</style>';
}
