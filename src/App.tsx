import React, { useState, useEffect } from 'react';
import { supabase } from './supabaseClient';
import VendedoraIA, { type RetornoPagamento } from './VendedoraIA';

// --- IA GENERATOR (Módulo de Inteligência Artificial para Copywriting) ---
function gerarConteudoIA(segmento: string, nomeEmpresa: string) {
  const base = nomeEmpresa || 'Nosso Negócio';
  switch (segmento) {
    case 'Barbearia':
      return {
        titulo_hero: `Estilo e Tradição na ${base}`,
        subtitulo_hero: 'Cortes modernos, barba alinhada e ambiente exclusivo com cerveja gelada.',
        sobre: `Na ${base}, oferecemos mais do que um corte: proporcionamos uma experiência de cuidado masculino com profissionais especialistas e atendimento pontual.`,
        servicos: [
          { nome: 'Corte Social / Degradê', preco: 'R$ 45,00', desc: 'Acabamento perfeito na tesoura ou máquina.' },
          { nome: 'Barba Terapia', preco: 'R$ 35,00', desc: 'Toalha quente, óleos essenciais e navalha.' },
          { nome: 'Combo Completo (Cabelo + Barba)', preco: 'R$ 70,00', desc: 'O pacote ideal para renovar seu visual.' }
        ],
        cta_texto: 'Agende seu Horário no WhatsApp'
      };
    case 'Clínica':
      return {
        titulo_hero: `Cuidado e Saúde para Você na ${base}`,
        subtitulo_hero: 'Atendimento humanizado, estrutura moderna e profissionais renomados.',
        sobre: `A ${base} é dedicada ao seu bem-estar. Oferecemos consultas ágeis, equipamentos modernos e acompanhamento personalizado.`,
        servicos: [
          { nome: 'Consulta de Avaliação', preco: 'R$ 150,00', desc: 'Diagnóstico completo e plano de tratamento.' },
          { nome: 'Exames Preventivos', preco: 'R$ 120,00', desc: 'Resultados rápidos e precisos.' },
          { nome: 'Acompanhamento Especializado', preco: 'R$ 200,00', desc: 'Atendimento contínuo e personalizado.' }
        ],
        cta_texto: 'Falar com Atendimento Médico'
      };
    case 'Restaurante':
      return {
        titulo_hero: `Sabor Incomparável na ${base}`,
        subtitulo_hero: 'Ingredientes selecionados, pratos executivos e entregas rápidas.',
        sobre: `No ${base}, cada prato é preparado com paixão. Venha almoçar conosco ou peça no conforto da sua casa.`,
        servicos: [
          { nome: 'Prato Executivo do Dia', preco: 'R$ 32,90', desc: 'Acompanha salada, proteína e sobremesa.' },
          { nome: 'Marmitex Especial', preco: 'R$ 24,00', desc: 'Sabor caseiro entregue quentinho.' },
          { nome: 'Bebidas e Sucos Naturais', preco: 'A partir de R$ 7,00', desc: 'Sucos da fruta e refrigerantes.' }
        ],
        cta_texto: 'Fazer Pedido Agora'
      };
    case 'Mecânica':
      return {
        titulo_hero: `Manutenção de Confiança na ${base}`,
        subtitulo_hero: 'Diagnóstico computadorizado, revisão e mecânica geral com garantia.',
        sobre: `A ${base} cuida do seu veículo com transparência total. Orçamentos sem compromisso e peças originais.`,
        servicos: [
          { nome: 'Revisão Geral Preventiva', preco: 'R$ 250,00', desc: 'Inspecção de 30 itens de segurança.' },
          { nome: 'Troca de Óleo e Filtros', preco: 'R$ 130,00', desc: 'Óleos sintéticos de alta performance.' },
          { nome: 'Alinhamento e Balanceamento', preco: 'R$ 90,00', desc: 'Precisão e estabilidade para seu carro.' }
        ],
        cta_texto: 'Solicitar Orçamento Grátis'
      };
    default:
      return {
        titulo_hero: `Seja Bem-vindo à ${base}`,
        subtitulo_hero: 'Excelência em serviços e atendimento diferenciado para você.',
        sobre: `A ${base} busca sempre superação e qualidade em todos os serviços prestados aos nossos clientes.`,
        servicos: [
          { nome: 'Atendimento Personalizado', preco: 'Consulte', desc: 'Soluções sob medida para sua necessidade.' }
        ],
        cta_texto: 'Entrar em Contato'
      };
  }
}

export default function App() {
  // Rota Dinâmica via URL (Ex: ?site=12)
  const [publicSiteId, setPublicSiteId] = useState<string | null>(null);
  const [retornoPagamento, setRetornoPagamento] = useState<RetornoPagamento | null>(null);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const siteParam = params.get('site');
    if (siteParam) {
      setPublicSiteId(siteParam);
    }
    // Retorno do Checkout Pro do Mercado Pago (?pagamento=sucesso|pendente|falha)
    const pagamento = params.get('pagamento');
    if (pagamento === 'sucesso' || pagamento === 'pendente' || pagamento === 'falha') {
      setRetornoPagamento(pagamento);
      window.history.replaceState({}, '', siteParam ? `?site=${siteParam}` : window.location.pathname);
    }
  }, []);

  if (publicSiteId) {
    return <PaginaPublicaSite siteId={publicSiteId} retornoPagamento={retornoPagamento} voltarAoPainel={() => { setPublicSiteId(null); window.history.pushState({}, '', window.location.pathname); }} />;
  }

  return <AdminDashboard verSitePublico={(id: string) => { setPublicSiteId(id); window.history.pushState({}, '', `?site=${id}`); }} />;
}

// ==========================================
// 1. PÁGINA PÚBLICA DO CLIENTE (LANDING PAGE REAL)
// ==========================================
function PaginaPublicaSite({ siteId, voltarAoPainel, retornoPagamento }: { siteId: string; voltarAoPainel: () => void; retornoPagamento?: RetornoPagamento | null }) {
  const [site, setSite] = useState<any>(null);
  const [carregando, setCarregando] = useState(true);

  useEffect(() => {
    async function carregarSite() {
      const { data } = await supabase
        .from('sites')
        .select('*, empresas(*)')
        .eq('id', siteId)
        .single();

      if (data) setSite(data);
      setCarregando(false);
    }
    carregarSite();
  }, [siteId]);

  if (carregando) {
    return <div style={{ background: '#0d1117', color: '#fff', minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>Carregando site...</div>;
  }

  if (!site) {
    return (
      <div style={{ background: '#0d1117', color: '#fff', minHeight: '100vh', padding: '40px', textAlign: 'center' }}>
        <h2>Site não encontrado ou desativado.</h2>
        <button onClick={voltarAoPainel} style={{ padding: '10px 20px', background: '#38edf8', border: 'none', borderRadius: '6px', cursor: 'pointer', marginTop: '15px' }}>Voltar ao Painel</button>
      </div>
    );
  }

  const configs = site.funcionalidades || {};
  const cor = configs.cor_principal || '#38edf8';
  const whatsapp = configs.whatsapp || site.empresas?.whatsapp || '';
  const linkWhatsApp = `https://wa.me/${whatsapp.replace(/\D/g, '')}?text=Olá!%20Vim%20pelo%20site.`;

  return (
    <div style={{ fontFamily: 'sans-serif', backgroundColor: '#111827', color: '#f9fafb', minHeight: '100vh' }}>
      
      {/* Barra de Navegação do Painel (Apenas para teste) */}
      <div style={{ background: '#1f2937', padding: '10px 20px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: '1px solid #374151' }}>
        <span style={{ fontSize: '12px', color: '#9ca3af' }}>🌐 Modo Público de Visualização (URL: ?site={site.id})</span>
        <button onClick={voltarAoPainel} style={{ padding: '6px 12px', background: '#374151', color: '#fff', border: 'none', borderRadius: '4px', fontSize: '12px', cursor: 'pointer' }}>
          ← Voltar ao ADMIN
        </button>
      </div>

      {/* Header do Site */}
      <header style={{ padding: '20px', textAlign: 'center', borderBottom: `2px solid ${cor}` }}>
        <h1 style={{ margin: 0, fontSize: '26px', color: cor }}>{site.empresas?.nome_negocio || 'Seu Negócio'}</h1>
        <p style={{ margin: '5px 0 0', fontSize: '14px', color: '#9ca3af' }}>{site.segmento}</p>
      </header>

      {/* Hero Section */}
      <section style={{ padding: '40px 20px', textAlign: 'center', background: 'radial-gradient(circle, rgba(31,41,55,1) 0%, rgba(17,24,39,1) 100%)' }}>
        <h2 style={{ fontSize: '28px', margin: '0 0 15px', color: '#ffffff' }}>
          {configs.titulo_hero || `Bem-vindo à ${site.empresas?.nome_negocio}`}
        </h2>
        <p style={{ fontSize: '16px', color: '#d1d5db', maxWidth: '600px', margin: '0 auto 25px' }}>
          {configs.subtitulo_hero || 'Oferecemos a melhor qualidade e os melhores serviços da região.'}
        </p>
        <a 
          href={linkWhatsApp} 
          target="_blank" 
          rel="noreferrer"
          style={{ display: 'inline-block', padding: '14px 28px', backgroundColor: cor, color: '#000', fontWeight: 'bold', fontSize: '16px', borderRadius: '30px', textDecoration: 'none', boxShadow: '0 4px 14px rgba(0,0,0,0.4)' }}
        >
          💬 {configs.cta_texto || 'Falar no WhatsApp'}
        </a>
      </section>

      {/* Sobre Nós */}
      {configs.sobre && (
        <section style={{ padding: '30px 20px', maxWidth: '700px', margin: '0 auto', textAlign: 'center', borderTop: '1px solid #1f2937' }}>
          <h3 style={{ fontSize: '20px', color: cor, marginBottom: '10px' }}>Sobre Nós</h3>
          <p style={{ fontSize: '14px', color: '#9ca3af', lineHeight: '1.6' }}>{configs.sobre}</p>
        </section>
      )}

      {/* Serviços / Produtos */}
      {configs.servicos && configs.servicos.length > 0 && (
        <section style={{ padding: '30px 20px', maxWidth: '800px', margin: '0 auto' }}>
          <h3 style={{ fontSize: '20px', color: cor, textAlign: 'center', marginBottom: '20px' }}>Nossos Serviços & Preços</h3>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '15px' }}>
            {configs.servicos.map((s: any, idx: number) => (
              <div key={idx} style={{ background: '#1f2937', padding: '15px', borderRadius: '8px', border: '1px solid #374151' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                  <strong style={{ fontSize: '15px', color: '#fff' }}>{s.nome}</strong>
                  <span style={{ fontSize: '13px', color: cor, fontWeight: 'bold' }}>{s.preco}</span>
                </div>
                <p style={{ margin: 0, fontSize: '12px', color: '#9ca3af' }}>{s.desc}</p>
              </div>
            ))}
          </div>
        </section>
      )}

      {/* Horário & Rodapé */}
      <footer style={{ padding: '30px 20px', textAlign: 'center', borderTop: '1px solid #1f2937', marginTop: '40px', color: '#6b7280', fontSize: '12px' }}>
        <p style={{ margin: '0 0 10px' }}>🕒 Horário de Atendimento: {configs.horario || 'Segunda a Sábado - 08h às 18h'}</p>
        <p style={{ margin: 0 }}>© {new Date().getFullYear()} {site.empresas?.nome_negocio}. Desenvolvido via GDS Platform.</p>
      </footer>

      {/* Vendedora IA com pagamento via Mercado Pago */}
      {configs.vendedora_ia?.ativa !== false && site.status_publicacao === 'Publicado' && (configs.servicos?.length ?? 0) > 0 && (
        <VendedoraIA
          siteId={site.id.toString()}
          nomeNegocio={site.empresas?.nome_negocio || 'Nosso Negócio'}
          servicos={configs.servicos}
          horario={configs.horario}
          linkWhatsApp={linkWhatsApp}
          cor={cor}
          nomeVendedora={configs.vendedora_ia?.nome || 'Lia'}
          retornoPagamento={retornoPagamento}
        />
      )}

    </div>
  );
}

// ==========================================
// 2. PAINEL ADMINISTRATIVO (ADMIN DASHBOARD)
// ==========================================
function AdminDashboard({ verSitePublico }: { verSitePublico: (id: string) => void }) {
  const [autenticado, setAutenticado] = useState(false);
  const [emailAdmin, setEmailAdmin] = useState('');
  const [senhaAdmin, setSenhaAdmin] = useState('');

  const [empresas, setEmpresas] = useState<any[]>([]);
  const [sites, setSites] = useState<any[]>([]);

  const [menuAberto, setMenuAberto] = useState(false);
  const [modalEmpresa, setModalEmpresa] = useState(false);
  const [modalSite, setModalSite] = useState(false);
  const [modalEditarSite, setModalEditarSite] = useState(false);

  // Form Empresa
  const [nome, setNome] = useState('');
  const [whatsapp, setWhatsapp] = useState('');
  const [email, setEmail] = useState('');
  const [senha, setSenha] = useState('');

  // Form Site
  const [empresaId, setEmpresaId] = useState('');
  const [segmento, setSegmento] = useState('Barbearia');

  // Form Edição do Site
  const [siteEmEdicao, setSiteEmEdicao] = useState<any>(null);
  const [editWhatsapp, setEditWhatsapp] = useState('');
  const [editCor, setEditCor] = useState('#38edf8');
  const [editHorario, setEditHorario] = useState('08:00 às 18:00');
  const [editTitulo, setEditTitulo] = useState('');
  const [editSubtitulo, setEditSubtitulo] = useState('');
  const [editVendedoraAtiva, setEditVendedoraAtiva] = useState(true);
  const [editVendedoraNome, setEditVendedoraNome] = useState('Lia');

  // Vendas da Vendedora IA (Mercado Pago)
  const [pedidos, setPedidos] = useState<any[]>([]);

  const [busca, setBusca] = useState('');
  const [filtroStatus, setFiltroStatus] = useState('Todos');

  useEffect(() => {
    if (autenticado) carregarDados();
  }, [autenticado]);

  async function carregarDados() {
    const { data: dEmpresas } = await supabase.from('empresas').select('*').order('id', { ascending: false });
    const { data: dSites } = await supabase.from('sites').select('*, empresas(*)').order('id', { ascending: false });
    if (dEmpresas) setEmpresas(dEmpresas);
    if (dSites) setSites(dSites);
    const { data: dPedidos } = await supabase.from('pedidos').select('*').order('criado_em', { ascending: false }).limit(50);
    if (dPedidos) setPedidos(dPedidos);
  }

  function loginAdmin(e: React.FormEvent) {
    e.preventDefault();
    if (emailAdmin && senhaAdmin) setAutenticado(true);
  }

  // --- IA GENERATOR INTEGRATION ---
  async function criarSiteComIA(e: React.FormEvent) {
    e.preventDefault();
    if (!empresaId) return alert('Selecione uma empresa!');

    const empresaSel = empresas.find(e => e.id.toString() === empresaId.toString());
    const copyIA = gerarConteudoIA(segmento, empresaSel?.nome_negocio || '');

    const funcionalidades = {
      ...copyIA,
      whatsapp: empresaSel?.whatsapp || '',
      cor_principal: segmento === 'Barbearia' ? '#e5c158' : segmento === 'Clínica' ? '#38edf8' : segmento === 'Restaurante' ? '#f97316' : '#22c55e',
      horario: 'Seg a Sex das 08:00 às 18:00',
      vendedora_ia: { ativa: true, nome: 'Lia' }
    };

    const { error } = await supabase.from('sites').insert([{
      empresa_id: empresaId, segmento, status_publicacao: 'Publicado', funcionalidades
    }]);

    if (error) alert('Erro ao criar site: ' + error.message);
    else {
      setEmpresaId('');
      setModalSite(false);
      carregarDados();
    }
  }

  async function aplicarIAnoSiteEdicao() {
    if (!siteEmEdicao) return;
    const empresaNome = siteEmEdicao.empresas?.nome_negocio || '';
    const copyIA = gerarConteudoIA(siteEmEdicao.segmento, empresaNome);

    setEditTitulo(copyIA.titulo_hero);
    setEditSubtitulo(copyIA.subtitulo_hero);
    alert('✨ Conteúdo gerado via IA aplicado aos campos! Clique em "Salvar Alterações".');
  }

  async function cadastrarEmpresa(e: React.FormEvent) {
    e.preventDefault();
    const { error } = await supabase.from('empresas').insert([{
      nome_negocio: nome, whatsapp, email_login: email, senha, status_pagamento: 'Em dia'
    }]);
    if (error) alert('Erro: ' + error.message);
    else {
      setNome(''); setWhatsapp(''); setEmail(''); setSenha('');
      setModalEmpresa(false);
      carregarDados();
    }
  }

  async function alternarStatusPagamento(id: number, statusAtual: string) {
    const proximosStatus: { [key: string]: string } = { 'Em dia': 'Pendente', 'Pendente': 'Atrasado', 'Atrasado': 'Em dia' };
    const novoStatus = proximosStatus[statusAtual] || 'Em dia';
    await supabase.from('empresas').update({ status_pagamento: novoStatus }).eq('id', id);
    carregarDados();
  }

  async function deletarEmpresa(id: number) {
    if (!confirm('Excluir empresa?')) return;
    await supabase.from('empresas').delete().eq('id', id);
    carregarDados();
  }

  async function alternarStatusSite(id: number, statusAtual: string) {
    const novoStatus = statusAtual === 'Publicado' ? 'Rascunho' : 'Publicado';
    await supabase.from('sites').update({ status_publicacao: novoStatus }).eq('id', id);
    carregarDados();
  }

  async function deletarSite(id: number) {
    if (!confirm('Excluir site?')) return;
    await supabase.from('sites').delete().eq('id', id);
    carregarDados();
  }

  function abrirEdicaoSite(site: any) {
    setSiteEmEdicao(site);
    const configs = site.funcionalidades || {};
    setEditWhatsapp(configs.whatsapp || site.empresas?.whatsapp || '');
    setEditCor(configs.cor_principal || '#38edf8');
    setEditHorario(configs.horario || '08:00 às 18:00');
    setEditTitulo(configs.titulo_hero || '');
    setEditSubtitulo(configs.subtitulo_hero || '');
    setEditVendedoraAtiva(configs.vendedora_ia?.ativa !== false);
    setEditVendedoraNome(configs.vendedora_ia?.nome || 'Lia');
    setModalEditarSite(true);
  }

  async function salvarEdicaoSite(e: React.FormEvent) {
    e.preventDefault();
    if (!siteEmEdicao) return;
    const configsAtualizadas = {
      ...(siteEmEdicao.funcionalidades || {}),
      whatsapp: editWhatsapp,
      cor_principal: editCor,
      horario: editHorario,
      titulo_hero: editTitulo,
      subtitulo_hero: editSubtitulo,
      vendedora_ia: { ativa: editVendedoraAtiva, nome: editVendedoraNome.trim() || 'Lia' }
    };
    await supabase.from('sites').update({ funcionalidades: configsAtualizadas }).eq('id', siteEmEdicao.id);
    setModalEditarSite(false);
    carregarDados();
  }

  const empresasEmDia = empresas.filter(e => e.status_pagamento === 'Em dia' || !e.status_pagamento);
  const receitaAtiva = empresasEmDia.length * 99;
  const inadimplentes = empresas.filter(e => e.status_pagamento === 'Pendente' || e.status_pagamento === 'Atrasado').length;
  const vendasPagas = pedidos.filter(p => p.status === 'pago');
  const totalVendasIA = vendasPagas.reduce((acc, p) => acc + Number(p.valor || 0), 0);

  const empresasFiltradas = empresas.filter(e => e.nome_negocio?.toLowerCase().includes(busca.toLowerCase()));
  const sitesFiltrados = sites.filter(s => {
    const coincideBusca = s.segmento?.toLowerCase().includes(busca.toLowerCase()) || s.empresas?.nome_negocio?.toLowerCase().includes(busca.toLowerCase());
    const coincideStatus = filtroStatus === 'Todos' || s.status_publicacao === filtroStatus;
    return coincideBusca && coincideStatus;
  });

  if (!autenticado) {
    return (
      <div style={{ fontFamily: 'sans-serif', backgroundColor: '#0d1117', color: '#fff', minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '20px' }}>
        <div style={{ background: '#161b22', padding: '25px', borderRadius: '12px', border: '1px solid #30363d', width: '100%', maxWidth: '360px' }}>
          <h2 style={{ color: '#38edf8', textAlign: 'center', margin: '0 0 5px', fontSize: '20px' }}>GDS ADMIN</h2>
          <p style={{ color: '#8b949e', textAlign: 'center', fontSize: '12px', margin: '0 0 20px' }}>Painel do Gestor de Sites</p>
          <form onSubmit={loginAdmin} style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
            <input type="email" placeholder="E-mail Admin" value={emailAdmin} onChange={e => setEmailAdmin(e.target.value)} required style={{ padding: '12px', background: '#0d1117', border: '1px solid #30363d', color: '#fff', borderRadius: '6px' }} />
            <input type="password" placeholder="Senha" value={senhaAdmin} onChange={e => setSenhaAdmin(e.target.value)} required style={{ padding: '12px', background: '#0d1117', border: '1px solid #30363d', color: '#fff', borderRadius: '6px' }} />
            <button type="submit" style={{ padding: '12px', background: '#238636', color: '#fff', border: 'none', borderRadius: '6px', fontWeight: 'bold', fontSize: '14px', cursor: 'pointer' }}>Entrar no Painel</button>
          </form>
        </div>
      </div>
    );
  }

  return (
    <div style={{ fontFamily: 'sans-serif', backgroundColor: '#0d1117', color: '#fff', minHeight: '100vh', padding: '15px' }}>
      
      {/* Topo Mobile */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '15px' }}>
        <h2 style={{ fontSize: '18px', margin: 0, color: '#38edf8' }}>GDS — Painel ADMIN</h2>
        <div style={{ display: 'flex', gap: '8px' }}>
          <button onClick={() => setMenuAberto(!menuAberto)} style={{ padding: '8px 12px', background: '#21262d', color: '#fff', border: '1px solid #30363d', borderRadius: '6px' }}>Menu ☰</button>
          <button onClick={() => setAutenticado(false)} style={{ padding: '8px 10px', background: '#da3633', color: '#fff', border: 'none', borderRadius: '6px', fontSize: '12px' }}>Sair</button>
        </div>
      </div>

      {menuAberto && (
        <div style={{ background: '#161b22', padding: '15px', borderRadius: '8px', marginBottom: '15px', border: '1px solid #30363d' }}>
          <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px', fontSize: '13px' }}>
            <li>📊 Dashboard</li><li>🏢 Empresas</li><li>🌐 Sites</li><li>🎨 IA Copywriter</li>
            <li>✏️ Editor Visual</li><li>💰 Financeiro</li>
          </ul>
        </div>
      )}

      {/* Indicadores */}
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '10px', marginBottom: '15px' }}>
        <div style={{ background: '#161b22', padding: '10px', borderRadius: '8px', border: '1px solid #30363d' }}>
          <span style={{ fontSize: '11px', color: '#8b949e' }}>Empresas</span>
          <h3 style={{ margin: '5px 0 0', fontSize: '18px' }}>{empresas.length}</h3>
        </div>
        <div style={{ background: '#161b22', padding: '10px', borderRadius: '8px', border: '1px solid #30363d' }}>
          <span style={{ fontSize: '11px', color: '#8b949e' }}>Receita Ativa</span>
          <h3 style={{ margin: '5px 0 0', fontSize: '14px', color: '#2ea043' }}>R$ {receitaAtiva}</h3>
        </div>
        <div style={{ background: '#161b22', padding: '10px', borderRadius: '8px', border: '1px solid #30363d' }}>
          <span style={{ fontSize: '11px', color: '#8b949e' }}>Pendentes</span>
          <h3 style={{ margin: '5px 0 0', fontSize: '14px', color: inadimplentes > 0 ? '#da3633' : '#8b949e' }}>{inadimplentes}</h3>
        </div>
      </div>

      {/* Busca */}
      <div style={{ display: 'flex', gap: '8px', marginBottom: '15px' }}>
        <input placeholder="🔍 Buscar..." value={busca} onChange={e => setBusca(e.target.value)} style={{ flex: 1, padding: '10px', background: '#161b22', border: '1px solid #30363d', color: '#fff', borderRadius: '6px', fontSize: '12px' }} />
        <select value={filtroStatus} onChange={e => setFiltroStatus(e.target.value)} style={{ padding: '10px', background: '#161b22', border: '1px solid #30363d', color: '#fff', borderRadius: '6px', fontSize: '12px' }}>
          <option value="Todos">Todos</option>
          <option value="Publicado">Publicados</option>
          <option value="Rascunho">Rascunhos</option>
        </select>
      </div>

      {/* Botões de Ação */}
      <div style={{ display: 'flex', gap: '10px', marginBottom: '15px' }}>
        <button onClick={() => setModalEmpresa(true)} style={{ flex: 1, padding: '12px', backgroundColor: '#238636', color: '#fff', border: 'none', borderRadius: '6px', fontWeight: 'bold', fontSize: '12px' }}>
          + Empresa
        </button>
        <button onClick={() => setModalSite(true)} style={{ flex: 1, padding: '12px', backgroundColor: '#8a2be2', color: '#fff', border: 'none', borderRadius: '6px', fontWeight: 'bold', fontSize: '12px' }}>
          🤖 Gerar Site com IA
        </button>
      </div>

      {/* Modal Empresa */}
      {modalEmpresa && (
        <div style={{ background: '#161b22', padding: '15px', borderRadius: '8px', border: '1px solid #238636', marginBottom: '15px' }}>
          <h3 style={{ margin: '0 0 15px', fontSize: '15px' }}>Cadastrar Empresa</h3>
          <form onSubmit={cadastrarEmpresa} style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
            <input placeholder="Nome do Negócio" value={nome} onChange={e => setNome(e.target.value)} required style={{ padding: '10px', background: '#0d1117', border: '1px solid #30363d', color: '#fff', borderRadius: '4px' }} />
            <input placeholder="WhatsApp" value={whatsapp} onChange={e => setWhatsapp(e.target.value)} required style={{ padding: '10px', background: '#0d1117', border: '1px solid #30363d', color: '#fff', borderRadius: '4px' }} />
            <input placeholder="E-mail" type="email" value={email} onChange={e => setEmail(e.target.value)} required style={{ padding: '10px', background: '#0d1117', border: '1px solid #30363d', color: '#fff', borderRadius: '4px' }} />
            <input placeholder="Senha" type="password" value={senha} onChange={e => setSenha(e.target.value)} required style={{ padding: '10px', background: '#0d1117', border: '1px solid #30363d', color: '#fff', borderRadius: '4px' }} />
            <div style={{ display: 'flex', gap: '10px' }}>
              <button type="submit" style={{ flex: 1, padding: '10px', background: '#238636', color: '#fff', border: 'none', borderRadius: '4px', fontWeight: 'bold' }}>Salvar</button>
              <button type="button" onClick={() => setModalEmpresa(false)} style={{ padding: '10px', background: '#21262d', color: '#fff', border: '1px solid #30363d', borderRadius: '4px' }}>Cancelar</button>
            </div>
          </form>
        </div>
      )}

      {/* Modal Criação com IA */}
      {modalSite && (
        <div style={{ background: '#161b22', padding: '15px', borderRadius: '8px', border: '1px solid #8a2be2', marginBottom: '15px' }}>
          <h3 style={{ margin: '0 0 10px', fontSize: '15px', color: '#c084fc' }}>🤖 Gerar Site Automático via IA</h3>
          <p style={{ fontSize: '11px', color: '#8b949e', margin: '0 0 15px' }}>A IA criará títulos, textos, serviços e tabela de preços automaticamente.</p>
          <form onSubmit={criarSiteComIA} style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
            <label style={{ fontSize: '12px', color: '#8b949e' }}>Selecione a Empresa:</label>
            <select value={empresaId} onChange={e => setEmpresaId(e.target.value)} required style={{ padding: '10px', background: '#0d1117', border: '1px solid #30363d', color: '#fff', borderRadius: '4px' }}>
              <option value="">Selecione...</option>
              {empresas.map(emp => <option key={emp.id} value={emp.id}>{emp.nome_negocio}</option>)}
            </select>
            <label style={{ fontSize: '12px', color: '#8b949e' }}>Segmento:</label>
            <select value={segmento} onChange={e => setSegmento(e.target.value)} style={{ padding: '10px', background: '#0d1117', border: '1px solid #30363d', color: '#fff', borderRadius: '4px' }}>
              <option value="Barbearia">Barbearia</option>
              <option value="Clínica">Clínica</option>
              <option value="Restaurante">Restaurante</option>
              <option value="Mecânica">Mecânica</option>
            </select>
            <div style={{ display: 'flex', gap: '10px', marginTop: '10px' }}>
              <button type="submit" style={{ flex: 1, padding: '10px', background: '#8a2be2', color: '#fff', border: 'none', borderRadius: '4px', fontWeight: 'bold' }}>✨ Criar em 1 Clique</button>
              <button type="button" onClick={() => setModalSite(false)} style={{ padding: '10px', background: '#21262d', color: '#fff', border: '1px solid #30363d', borderRadius: '4px' }}>Cancelar</button>
            </div>
          </form>
        </div>
      )}

      {/* Modal Editor Site */}
      {modalEditarSite && (
        <div style={{ background: '#161b22', padding: '15px', borderRadius: '8px', border: '1px solid #38edf8', marginBottom: '15px' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '15px' }}>
            <h3 style={{ margin: 0, fontSize: '15px', color: '#38edf8' }}>✏️ Editor ({siteEmEdicao?.segmento})</h3>
            <button type="button" onClick={aplicarIAnoSiteEdicao} style={{ padding: '6px 10px', background: '#8a2be2', color: '#fff', border: 'none', borderRadius: '4px', fontSize: '11px', cursor: 'pointer' }}>✨ Re-gerar com IA</button>
          </div>
          <form onSubmit={salvarEdicaoSite} style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
            <label style={{ fontSize: '12px', color: '#8b949e' }}>Título Principal (Hero):</label>
            <input value={editTitulo} onChange={e => setEditTitulo(e.target.value)} style={{ padding: '10px', background: '#0d1117', border: '1px solid #30363d', color: '#fff', borderRadius: '4px' }} />
            
            <label style={{ fontSize: '12px', color: '#8b949e' }}>Subtítulo:</label>
            <input value={editSubtitulo} onChange={e => setEditSubtitulo(e.target.value)} style={{ padding: '10px', background: '#0d1117', border: '1px solid #30363d', color: '#fff', borderRadius: '4px' }} />

            <label style={{ fontSize: '12px', color: '#8b949e' }}>WhatsApp Atendimento:</label>
            <input value={editWhatsapp} onChange={e => setEditWhatsapp(e.target.value)} style={{ padding: '10px', background: '#0d1117', border: '1px solid #30363d', color: '#fff', borderRadius: '4px' }} />

            <label style={{ fontSize: '12px', color: '#8b949e' }}>Cor Principal:</label>
            <input type="color" value={editCor} onChange={e => setEditCor(e.target.value)} style={{ width: '100%', height: '40px', background: '#0d1117', border: '1px solid #30363d', borderRadius: '4px' }} />

            <label style={{ fontSize: '12px', color: '#8b949e', display: 'flex', alignItems: 'center', gap: '8px', marginTop: '5px' }}>
              <input type="checkbox" checked={editVendedoraAtiva} onChange={e => setEditVendedoraAtiva(e.target.checked)} />
              🤖 Vendedora IA ativa (vende os serviços com pagamento via Mercado Pago)
            </label>
            {editVendedoraAtiva && (
              <input placeholder="Nome da vendedora" value={editVendedoraNome} onChange={e => setEditVendedoraNome(e.target.value)} maxLength={30} style={{ padding: '10px', background: '#0d1117', border: '1px solid #30363d', color: '#fff', borderRadius: '4px' }} />
            )}

            <div style={{ display: 'flex', gap: '10px', marginTop: '10px' }}>
              <button type="submit" style={{ flex: 1, padding: '10px', background: '#38edf8', color: '#0d1117', border: 'none', borderRadius: '4px', fontWeight: 'bold' }}>Salvar Alterações</button>
              <button type="button" onClick={() => setModalEditarSite(false)} style={{ padding: '10px', background: '#21262d', color: '#fff', border: '1px solid #30363d', borderRadius: '4px' }}>Cancelar</button>
            </div>
          </form>
        </div>
      )}

      {/* Vendas da Vendedora IA (Mercado Pago) */}
      <div style={{ background: '#161b22', padding: '15px', borderRadius: '8px', border: '1px solid #009ee3', marginBottom: '15px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px' }}>
          <h4 style={{ margin: 0, fontSize: '14px' }}>🤖 Vendas da Vendedora IA ({pedidos.length})</h4>
          <span style={{ fontSize: '12px', color: '#2ea043', fontWeight: 'bold' }}>
            {totalVendasIA.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })} pagos
          </span>
        </div>
        {pedidos.length === 0 && <p style={{ fontSize: '12px', color: '#8b949e', margin: 0 }}>Nenhuma venda ainda. Os pedidos pagos via Mercado Pago aparecem aqui.</p>}
        {pedidos.map(p => {
          const site = sites.find(s => s.id === p.site_id);
          const corPedido = p.status === 'pago' ? '#238636' : p.status === 'pendente' ? '#d29922' : '#da3633';
          return (
            <div key={p.id} style={{ borderBottom: '1px solid #30363d', padding: '8px 0', display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '8px' }}>
              <div style={{ minWidth: 0 }}>
                <strong style={{ fontSize: '13px' }}>{p.servico_nome}</strong>
                <div style={{ fontSize: '11px', color: '#8b949e' }}>
                  {site?.empresas?.nome_negocio || `Site #${p.site_id}`} · {p.nome_cliente || 'Cliente'} · {new Date(p.criado_em).toLocaleDateString('pt-BR')}
                </div>
              </div>
              <div style={{ textAlign: 'right', flexShrink: 0 }}>
                <div style={{ fontSize: '12px', fontWeight: 'bold' }}>{Number(p.valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</div>
                <span style={{ fontSize: '10px', background: corPedido, padding: '2px 6px', borderRadius: '10px' }}>{p.status}</span>
              </div>
            </div>
          );
        })}
      </div>

      {/* Lista Empresas */}
      <div style={{ background: '#161b22', padding: '15px', borderRadius: '8px', border: '1px solid #30363d', marginBottom: '15px' }}>
        <h4 style={{ margin: '0 0 10px', fontSize: '14px' }}>Empresas & Pagamentos ({empresasFiltradas.length})</h4>
        {empresasFiltradas.map(emp => {
          const statusPag = emp.status_pagamento || 'Em dia';
          const corStatus = statusPag === 'Em dia' ? '#238636' : statusPag === 'Pendente' ? '#d29922' : '#da3633';
          return (
            <div key={emp.id} style={{ borderBottom: '1px solid #30363d', padding: '8px 0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <div>
                <strong style={{ fontSize: '13px' }}>{emp.nome_negocio}</strong>
                <div style={{ fontSize: '11px', color: '#8b949e' }}>{emp.whatsapp}</div>
              </div>
              <div style={{ display: 'flex', gap: '6px', alignItems: 'center' }}>
                <button onClick={() => alternarStatusPagamento(emp.id, statusPag)} style={{ fontSize: '10px', background: corStatus, color: '#fff', border: 'none', padding: '4px 8px', borderRadius: '10px' }}>💳 {statusPag}</button>
                <button onClick={() => deletarEmpresa(emp.id)} style={{ padding: '4px 8px', background: '#21262d', color: '#fff', border: '1px solid #30363d', borderRadius: '4px', fontSize: '11px' }}>🗑️</button>
              </div>
            </div>
          );
        })}
      </div>

      {/* Lista Sites */}
      <div style={{ background: '#161b22', padding: '15px', borderRadius: '8px', border: '1px solid #30363d' }}>
        <h4 style={{ margin: '0 0 10px', fontSize: '14px' }}>Sites em Gestão ({sitesFiltrados.length})</h4>
        {sitesFiltrados.map(site => (
          <div key={site.id} style={{ borderBottom: '1px solid #30363d', padding: '10px 0', display: 'flex', flexDirection: 'column', gap: '8px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <div>
                <strong style={{ fontSize: '13px' }}>{site.segmento}</strong>
                <div style={{ fontSize: '11px', color: '#38edf8' }}>Empresa: {site.empresas?.nome_negocio || 'N/A'}</div>
              </div>
              <span style={{ fontSize: '10px', background: site.status_publicacao === 'Publicado' ? '#238636' : '#8b949e', padding: '2px 6px', borderRadius: '10px' }}>
                {site.status_publicacao}
              </span>
            </div>

            {/* Ações */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 40px', gap: '6px' }}>
              <button onClick={() => verSitePublico(site.id.toString())} style={{ padding: '6px', background: '#238636', color: '#fff', border: 'none', borderRadius: '4px', fontSize: '11px', fontWeight: 'bold' }}>
                🌐 Ver Site
              </button>
              <button onClick={() => abrirEdicaoSite(site)} style={{ padding: '6px', background: '#1f6feb', color: '#fff', border: 'none', borderRadius: '4px', fontSize: '11px' }}>
                ✏️ Editar
              </button>
              <button onClick={() => alternarStatusSite(site.id, site.status_publicacao)} style={{ padding: '6px', background: '#21262d', color: '#fff', border: '1px solid #30363d', borderRadius: '4px', fontSize: '11px' }}>
                🔄 Status
              </button>
              <button onClick={() => deletarSite(site.id)} style={{ padding: '6px', background: '#da3633', color: '#fff', border: 'none', borderRadius: '4px', fontSize: '11px' }}>
                🗑️
              </button>
            </div>
          </div>
        ))}
      </div>

    </div>
  );
}
