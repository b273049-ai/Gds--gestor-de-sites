import { useEffect, useRef, useState } from 'react';
import { supabase } from './supabaseClient';
import { parsePreco } from './preco';

// ==========================================
// VENDEDORA IA — chat de vendas das páginas públicas, com pagamento via Mercado Pago
// ==========================================

type Servico = { nome: string; preco: string; desc?: string };
type Mensagem = { de: 'ia' | 'cliente'; texto: string };

export type RetornoPagamento = 'sucesso' | 'pendente' | 'falha';

type Props = {
  siteId: string;
  nomeNegocio: string;
  servicos: Servico[];
  horario?: string;
  linkWhatsApp: string;
  cor: string;
  nomeVendedora?: string;
  retornoPagamento?: RetornoPagamento | null;
};

const formatarBRL = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

function normalizar(t: string) {
  return t.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
}

export default function VendedoraIA({ siteId, nomeNegocio, servicos, horario, linkWhatsApp, cor, nomeVendedora = 'Lia', retornoPagamento }: Props) {
  const [aberta, setAberta] = useState(!!retornoPagamento);
  const [mensagens, setMensagens] = useState<Mensagem[]>(() => [{ de: 'ia', texto: mensagemInicial() }]);
  const [entrada, setEntrada] = useState('');
  const [servicoEscolhido, setServicoEscolhido] = useState<number | null>(null);
  const [nomeCliente, setNomeCliente] = useState('');
  const [emailCliente, setEmailCliente] = useState('');
  const [processando, setProcessando] = useState(false);
  const fimRef = useRef<HTMLDivElement>(null);

  function mensagemInicial() {
    if (retornoPagamento === 'sucesso') return `🎉 Pagamento aprovado! Obrigada por escolher a ${nomeNegocio}. Em breve entraremos em contato para confirmar os detalhes.`;
    if (retornoPagamento === 'pendente') return `⏳ Seu pagamento está em processamento pelo Mercado Pago (Pix/boleto podem levar alguns minutos). Assim que for confirmado, seu pedido é liberado!`;
    if (retornoPagamento === 'falha') return `😕 O pagamento não foi concluído. Quer tentar novamente? É só escolher o serviço abaixo.`;
    return `Oi! 👋 Sou a ${nomeVendedora}, vendedora virtual da ${nomeNegocio}. Posso te ajudar a escolher um serviço e já garantir com pagamento seguro pelo Mercado Pago (Pix, cartão ou boleto). O que você procura?`;
  }

  useEffect(() => {
    fimRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [mensagens, servicoEscolhido, aberta]);

  const falar = (texto: string) => setMensagens(m => [...m, { de: 'ia', texto }]);

  function escolherServico(idx: number) {
    const s = servicos[idx];
    setMensagens(m => [...m, { de: 'cliente', texto: s.nome }]);
    const valor = parsePreco(s.preco);
    if (!valor) {
      setServicoEscolhido(null);
      falar(`O ${s.nome} tem valor sob consulta. Fale com nossa equipe pelo WhatsApp que montamos um orçamento pra você! 😉`);
      return;
    }
    setServicoEscolhido(idx);
    falar(`Ótima escolha! ✨ ${s.nome} sai por ${formatarBRL(valor)}.${s.desc ? ` ${s.desc}` : ''} Me diz seu nome e e-mail para eu gerar seu pagamento seguro no Mercado Pago.`);
  }

  function responder(texto: string) {
    const t = normalizar(texto);
    const idx = servicos.findIndex(s => {
      const nome = normalizar(s.nome);
      return t.includes(nome) || nome.split(/[\s/()+-]+/).some(p => p.length > 3 && t.includes(p));
    });
    if (idx >= 0) return escolherServico(idx);

    if (/(preco|valor|quanto|custa|servico|tabela|opco)/.test(t)) {
      falar('Estes são nossos serviços: ' + servicos.map(s => `${s.nome} (${s.preco})`).join(' • ') + '. Toque em um deles para contratar!');
    } else if (/(horario|hora|abre|fecha|funciona|atend)/.test(t)) {
      falar(`Nosso horário: ${horario || 'Segunda a Sábado - 08h às 18h'}.`);
    } else if (/(pix|cartao|boleto|parcel|pagamento|pagar|seguro)/.test(t)) {
      falar('Aceitamos Pix, cartão de crédito (com parcelamento), débito e boleto, tudo pelo Mercado Pago com segurança. Escolha um serviço para pagar!');
    } else if (/(whats|humano|atendente|pessoa|falar com)/.test(t)) {
      falar('Claro! Toque no botão "Falar no WhatsApp" aqui embaixo que nossa equipe te atende. 💬');
    } else if (/(oi|ola|bom dia|boa tarde|boa noite)/.test(t)) {
      falar('Oi! Quer ver nossos serviços e preços? É só tocar em um deles abaixo. 😊');
    } else {
      falar('Hmm, não entendi muito bem. Posso te mostrar nossos serviços e preços, informar o horário ou te passar para um atendente no WhatsApp.');
    }
  }

  function enviar(e: React.FormEvent) {
    e.preventDefault();
    const texto = entrada.trim();
    if (!texto) return;
    setMensagens(m => [...m, { de: 'cliente', texto }]);
    setEntrada('');
    setTimeout(() => responder(texto), 350);
  }

  async function pagar(e: React.FormEvent) {
    e.preventDefault();
    if (servicoEscolhido === null || processando) return;
    setProcessando(true);
    const { data, error } = await supabase.functions.invoke('mp-criar-pagamento', {
      body: { site_id: siteId, servico_index: servicoEscolhido, nome_cliente: nomeCliente, email_cliente: emailCliente },
    });
    if (error || !data?.init_point) {
      setProcessando(false);
      falar('Ops, não consegui abrir o pagamento agora. 😕 Tente novamente em instantes ou fale com a gente no WhatsApp.');
      return;
    }
    falar('Perfeito! Estou te levando para o checkout seguro do Mercado Pago... 🔒');
    window.location.href = data.init_point;
  }

  const estiloInput: React.CSSProperties = { padding: '9px', background: '#111827', border: '1px solid #374151', color: '#fff', borderRadius: '6px', fontSize: '13px' };

  if (!aberta) {
    return (
      <button
        onClick={() => setAberta(true)}
        style={{ position: 'fixed', right: '16px', bottom: '16px', zIndex: 50, padding: '12px 18px', background: cor, color: '#000', border: 'none', borderRadius: '30px', fontWeight: 'bold', fontSize: '14px', cursor: 'pointer', boxShadow: '0 6px 20px rgba(0,0,0,0.5)' }}
      >
        🤖 Fale com a {nomeVendedora}
      </button>
    );
  }

  const servicoAtual = servicoEscolhido !== null ? servicos[servicoEscolhido] : null;
  const valorAtual = servicoAtual ? parsePreco(servicoAtual.preco) : null;

  return (
    <div style={{ position: 'fixed', right: '16px', bottom: '16px', left: '16px', marginLeft: 'auto', zIndex: 50, maxWidth: '370px', height: 'min(560px, calc(100vh - 32px))', display: 'flex', flexDirection: 'column', background: '#1f2937', border: `1px solid ${cor}`, borderRadius: '14px', boxShadow: '0 10px 30px rgba(0,0,0,0.6)', overflow: 'hidden', fontFamily: 'sans-serif', textAlign: 'left' }}>
      <div style={{ padding: '12px 14px', background: '#111827', borderBottom: '1px solid #374151', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div>
          <strong style={{ color: cor, fontSize: '14px' }}>🤖 {nomeVendedora} · Vendedora IA</strong>
          <div style={{ fontSize: '11px', color: '#9ca3af' }}>{nomeNegocio} · Pagamento via Mercado Pago</div>
        </div>
        <button onClick={() => setAberta(false)} aria-label="Fechar" style={{ background: 'transparent', border: 'none', color: '#9ca3af', fontSize: '18px', cursor: 'pointer' }}>✕</button>
      </div>

      <div style={{ flex: 1, overflowY: 'auto', padding: '12px', display: 'flex', flexDirection: 'column', gap: '8px' }}>
        {mensagens.map((m, i) => (
          <div key={i} style={{ alignSelf: m.de === 'ia' ? 'flex-start' : 'flex-end', maxWidth: '85%', padding: '8px 11px', borderRadius: '12px', fontSize: '13px', lineHeight: 1.45, background: m.de === 'ia' ? '#374151' : cor, color: m.de === 'ia' ? '#f9fafb' : '#000' }}>
            {m.texto}
          </div>
        ))}

        {servicoAtual && valorAtual && (
          <form onSubmit={pagar} style={{ display: 'flex', flexDirection: 'column', gap: '7px', background: '#111827', padding: '10px', borderRadius: '10px', border: '1px solid #374151' }}>
            <div style={{ fontSize: '12px', color: '#d1d5db' }}>
              <strong>{servicoAtual.nome}</strong> — {formatarBRL(valorAtual)}
            </div>
            <input placeholder="Seu nome" value={nomeCliente} onChange={e => setNomeCliente(e.target.value)} required maxLength={120} style={estiloInput} />
            <input placeholder="Seu e-mail" type="email" value={emailCliente} onChange={e => setEmailCliente(e.target.value)} required maxLength={200} style={estiloInput} />
            <button type="submit" disabled={processando} style={{ padding: '11px', background: '#009ee3', color: '#fff', border: 'none', borderRadius: '6px', fontWeight: 'bold', fontSize: '13px', cursor: processando ? 'wait' : 'pointer', opacity: processando ? 0.7 : 1 }}>
              {processando ? 'Gerando pagamento...' : '💳 Pagar com Mercado Pago'}
            </button>
            <button type="button" onClick={() => setServicoEscolhido(null)} style={{ padding: '6px', background: 'transparent', color: '#9ca3af', border: 'none', fontSize: '11px', cursor: 'pointer' }}>
              Escolher outro serviço
            </button>
          </form>
        )}
        <div ref={fimRef} />
      </div>

      {servicoEscolhido === null && servicos.length > 0 && (
        <div style={{ padding: '8px 10px 0', display: 'flex', gap: '6px', overflowX: 'auto' }}>
          {servicos.map((s, idx) => (
            <button key={idx} onClick={() => escolherServico(idx)} style={{ flexShrink: 0, padding: '6px 10px', background: 'transparent', color: cor, border: `1px solid ${cor}`, borderRadius: '14px', fontSize: '11px', cursor: 'pointer' }}>
              {s.nome} · {s.preco}
            </button>
          ))}
        </div>
      )}

      <form onSubmit={enviar} style={{ display: 'flex', gap: '6px', padding: '10px' }}>
        <input placeholder="Digite sua mensagem..." value={entrada} onChange={e => setEntrada(e.target.value)} style={{ ...estiloInput, flex: 1 }} />
        <button type="submit" style={{ padding: '0 14px', background: cor, color: '#000', border: 'none', borderRadius: '6px', fontWeight: 'bold', cursor: 'pointer' }}>➤</button>
      </form>
      <a href={linkWhatsApp} target="_blank" rel="noreferrer" style={{ textAlign: 'center', fontSize: '11px', color: '#9ca3af', padding: '0 0 10px', textDecoration: 'none' }}>
        💬 Falar no WhatsApp
      </a>
    </div>
  );
}
