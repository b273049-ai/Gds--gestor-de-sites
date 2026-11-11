<?php
// ============================================================
// IDENTIDADE POR NICHO — o site precisa "parecer" o negócio na
// primeira olhada: barbearia tem cara de barbearia, hamburgueria
// dá fome, clínica passa calma e confiança, DJ tem cara de festa.
// Cada nicho define cores, fontes, tipo de topo, frase de impacto,
// textura/motivo visual, ícone e como os serviços aparecem.
// ============================================================

function site_nichos() {
    $icone = [
        'tesoura' => '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4 8.1 15.9M14.5 14.5 20 20M8.1 8.1 12 12"/>',
        'flor'    => '<path d="M12 7.5a4.5 4.5 0 1 1 4.5 4.5M12 7.5A4.5 4.5 0 1 0 7.5 12M12 7.5V9m-4.5 3a4.5 4.5 0 1 0 4.5 4.5M7.5 12H9m7.5 0a4.5 4.5 0 1 1-4.5 4.5m4.5-4.5H15m-3 4.5V15"/><circle cx="12" cy="12" r="3"/>',
        'fogo'    => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
        'cruz'    => '<path d="M11 2a2 2 0 0 0-2 2v5H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h5v5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2v-5h5a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2h-5V4a2 2 0 0 0-2-2z"/>',
        'musica'  => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
        'raio'    => '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>',
        'pata'    => '<circle cx="11" cy="4" r="2"/><circle cx="18" cy="8" r="2"/><circle cx="20" cy="16" r="2"/><path d="M9 10a5 5 0 0 1 5 5v3.5a3.5 3.5 0 0 1-6.84 1.045Q6.52 17.48 4.46 16.84A3.5 3.5 0 0 1 5.5 10Z"/>',
        'chave'   => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'casa'    => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        'balanca' => '<path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1ZM2 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10M12 3v18M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/>',
        'estrela' => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
        'sacola'  => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>',
        'livro'   => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>',
    ];
    return [
        'barbearia' => ['nome' => 'Barbearia', 'hero' => 'foto', 'motivo' => 'listras', 'icone' => $icone['tesoura'], 'servicos' => 'menu',
            'cores' => ['bg' => '#0e0c0a', 'bg2' => '#16120e', 'superficie' => 'rgba(255,244,228,.04)', 'texto' => '#f4ece0', 'suave' => '#b7aa94', 'linha' => 'rgba(244,236,224,.1)', 'accent' => '#c9a227', 'escuro' => true],
            'fontes' => 'Bebas+Neue&family=Inter:wght@400;500;600;700', 'titulo' => "'Bebas Neue'", 'corpo' => "'Inter'", 'titulo_caixa' => true,
            'headline' => 'Corte na régua. Barba no capricho.', 'sub' => 'Escolha o barbeiro, o serviço e o horário livre — leva menos de um minuto e a confirmação chega no seu WhatsApp.',
            'servicos_titulo' => 'Serviços & preços', 'servicos_sobre' => 'Tabela', 'galeria_titulo' => 'Cortes e ambiente'],
        'tatuagem' => ['nome' => 'Tatuagem', 'hero' => 'foto', 'motivo' => 'grao', 'icone' => $icone['raio'], 'servicos' => 'menu',
            'cores' => ['bg' => '#0b0b0c', 'bg2' => '#131315', 'superficie' => 'rgba(255,255,255,.04)', 'texto' => '#f1f1f1', 'suave' => '#a9a9ad', 'linha' => 'rgba(255,255,255,.09)', 'accent' => '#e2483d', 'escuro' => true],
            'fontes' => 'Bebas+Neue&family=Inter:wght@400;500;600;700', 'titulo' => "'Bebas Neue'", 'corpo' => "'Inter'", 'titulo_caixa' => true,
            'headline' => 'Arte na pele, feita com quem entende.', 'sub' => 'Mande sua ideia, o tamanho e o local do corpo — o orçamento chega no seu WhatsApp.',
            'servicos_titulo' => 'Estilos e valores', 'galeria_titulo' => 'Portfólio'],
        'beleza' => ['nome' => 'Beleza', 'hero' => 'split', 'motivo' => 'curvas', 'icone' => $icone['flor'], 'servicos' => 'menu',
            'cores' => ['bg' => '#fbf6f3', 'bg2' => '#f4eae5', 'superficie' => '#ffffff', 'texto' => '#2b201e', 'suave' => '#7b6b67', 'linha' => 'rgba(43,32,30,.1)', 'accent' => '#b25d6d', 'escuro' => false],
            'fontes' => 'Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Manrope:wght@400;500;600;700', 'titulo' => "'Cormorant Garamond'", 'corpo' => "'Manrope'",
            'headline' => 'Sua melhor versão, no seu tempo.', 'sub' => 'Agende online, escolha a profissional e receba a confirmação no WhatsApp.',
            'servicos_titulo' => 'Tratamentos & valores', 'galeria_titulo' => 'Resultados'],
        'gastronomia' => ['nome' => 'Gastronomia', 'hero' => 'foto', 'motivo' => 'pontos', 'icone' => $icone['fogo'], 'servicos' => 'cards',
            'cores' => ['bg' => '#fff8ef', 'bg2' => '#ffeeda', 'superficie' => '#ffffff', 'texto' => '#23150b', 'suave' => '#6f5a49', 'linha' => 'rgba(35,21,11,.1)', 'accent' => '#e23b2a', 'escuro' => false],
            'fontes' => 'Bricolage+Grotesque:opsz,wght@12..96,600;12..96,800&family=Inter:wght@400;500;600;700', 'titulo' => "'Bricolage Grotesque'", 'corpo' => "'Inter'",
            'headline' => 'Bateu a fome? A gente resolve.', 'sub' => 'Monte seu pedido aqui mesmo — entrega ou retirada, com cada etapa avisada no seu WhatsApp.',
            'servicos_titulo' => 'Cardápio', 'galeria_titulo' => 'Do forno para você'],
        'cafe' => ['nome' => 'Cafeteria e doces', 'hero' => 'split', 'motivo' => 'curvas', 'icone' => $icone['estrela'], 'servicos' => 'cards',
            'cores' => ['bg' => '#f8f1e9', 'bg2' => '#efe3d4', 'superficie' => '#fffaf4', 'texto' => '#2b1d14', 'suave' => '#7a6656', 'linha' => 'rgba(43,29,20,.1)', 'accent' => '#9a5b2e', 'escuro' => false],
            'fontes' => 'Fraunces:opsz,wght@9..144,600;9..144,800&family=Inter:wght@400;500;600', 'titulo' => "'Fraunces'", 'corpo' => "'Inter'",
            'headline' => 'Feito com calma, servido com carinho.', 'sub' => 'Peça pelo site ou encomende para a sua festa — a gente avisa tudo pelo WhatsApp.',
            'servicos_titulo' => 'Cardápio', 'galeria_titulo' => 'Feito aqui'],
        'saude' => ['nome' => 'Saúde', 'hero' => 'split', 'motivo' => 'ondas', 'icone' => $icone['cruz'], 'servicos' => 'cards',
            'cores' => ['bg' => '#ffffff', 'bg2' => '#f1f7f6', 'superficie' => '#ffffff', 'texto' => '#0f2024', 'suave' => '#56676b', 'linha' => 'rgba(15,32,36,.09)', 'accent' => '#0d8a7f', 'escuro' => false],
            'fontes' => 'Plus+Jakarta+Sans:wght@400;500;600;700;800', 'titulo' => "'Plus Jakarta Sans'", 'corpo' => "'Plus Jakarta Sans'",
            'headline' => 'Cuidado de verdade, do agendamento ao retorno.', 'sub' => 'Marque sua consulta online, receba a confirmação e um lembrete antes do horário.',
            'servicos_titulo' => 'Especialidades e atendimentos', 'galeria_titulo' => 'Nosso espaço'],
        'eventos' => ['nome' => 'Eventos', 'hero' => 'foto', 'motivo' => 'neon', 'icone' => $icone['musica'], 'servicos' => 'cards',
            'cores' => ['bg' => '#08070f', 'bg2' => '#100e1c', 'superficie' => 'rgba(255,255,255,.04)', 'texto' => '#f2efff', 'suave' => '#aaa3c9', 'linha' => 'rgba(242,239,255,.1)', 'accent' => '#c04bff', 'escuro' => true],
            'fontes' => 'Syne:wght@600;700;800&family=Inter:wght@400;500;600', 'titulo' => "'Syne'", 'corpo' => "'Inter'",
            'headline' => 'Seu evento inesquecível começa aqui.', 'sub' => 'Conte a data e o local — o orçamento chega no seu WhatsApp, sem compromisso.',
            'servicos_titulo' => 'Pacotes e serviços', 'galeria_titulo' => 'Eventos que fizemos'],
        'fitness' => ['nome' => 'Esporte', 'hero' => 'foto', 'motivo' => 'diagonal', 'icone' => $icone['raio'], 'servicos' => 'cards',
            'cores' => ['bg' => '#07090c', 'bg2' => '#0e1217', 'superficie' => 'rgba(255,255,255,.04)', 'texto' => '#eef3f6', 'suave' => '#9eabb5', 'linha' => 'rgba(238,243,246,.1)', 'accent' => '#c4f135', 'escuro' => true],
            'fontes' => 'Anton&family=Inter:wght@400;500;600;700', 'titulo' => "'Anton'", 'corpo' => "'Inter'", 'titulo_caixa' => true,
            'headline' => 'Seu melhor treino começa hoje.', 'sub' => 'Agende sua aula experimental e venha conhecer a nossa equipe.',
            'servicos_titulo' => 'Modalidades e planos', 'galeria_titulo' => 'Nosso espaço'],
        'pet' => ['nome' => 'Pets', 'hero' => 'split', 'motivo' => 'pontos', 'icone' => $icone['pata'], 'servicos' => 'cards',
            'cores' => ['bg' => '#fffaf2', 'bg2' => '#fff0dc', 'superficie' => '#ffffff', 'texto' => '#26190d', 'suave' => '#75604b', 'linha' => 'rgba(38,25,13,.1)', 'accent' => '#e9741c', 'escuro' => false],
            'fontes' => 'Fredoka:wght@500;600;700&family=Nunito:wght@400;600;700', 'titulo' => "'Fredoka'", 'corpo' => "'Nunito'",
            'headline' => 'Seu pet merece esse carinho.', 'sub' => 'Agende banho, tosa ou consulta em segundos e receba a confirmação no WhatsApp.',
            'servicos_titulo' => 'Serviços e preços', 'galeria_titulo' => 'Clientes de quatro patas'],
        'auto' => ['nome' => 'Automotivo', 'hero' => 'foto', 'motivo' => 'diagonal', 'icone' => $icone['chave'], 'servicos' => 'cards',
            'cores' => ['bg' => '#0b0d10', 'bg2' => '#12161b', 'superficie' => 'rgba(255,255,255,.04)', 'texto' => '#eef1f4', 'suave' => '#9aa4ae', 'linha' => 'rgba(238,241,244,.1)', 'accent' => '#ff6a13', 'escuro' => true],
            'fontes' => 'Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600', 'titulo' => "'Barlow Condensed'", 'corpo' => "'Barlow'", 'titulo_caixa' => true,
            'headline' => 'Seu carro em boas mãos.', 'sub' => 'Descreva o problema e receba o orçamento no WhatsApp — com transparência do começo ao fim.',
            'servicos_titulo' => 'Serviços', 'galeria_titulo' => 'Oficina e trabalhos'],
        'casa' => ['nome' => 'Casa e obras', 'hero' => 'foto', 'motivo' => 'grade', 'icone' => $icone['casa'], 'servicos' => 'cards',
            'cores' => ['bg' => '#f6f5f1', 'bg2' => '#ecebe4', 'superficie' => '#ffffff', 'texto' => '#1b1a17', 'suave' => '#66645c', 'linha' => 'rgba(27,26,23,.1)', 'accent' => '#d99a00', 'escuro' => false],
            'fontes' => 'Archivo:wght@500;700;800&family=Inter:wght@400;500;600', 'titulo' => "'Archivo'", 'corpo' => "'Inter'",
            'headline' => 'Serviço bem feito, do orçamento à entrega.', 'sub' => 'Conte o que precisa e receba o orçamento no WhatsApp — sem enrolação.',
            'servicos_titulo' => 'O que fazemos', 'galeria_titulo' => 'Obras entregues'],
        'profissional' => ['nome' => 'Serviços profissionais', 'hero' => 'split', 'motivo' => 'linhas', 'icone' => $icone['balanca'], 'servicos' => 'cards',
            'cores' => ['bg' => '#f6f7fa', 'bg2' => '#eceff5', 'superficie' => '#ffffff', 'texto' => '#121a2d', 'suave' => '#5a6378', 'linha' => 'rgba(18,26,45,.1)', 'accent' => '#1e3a8a', 'escuro' => false],
            'fontes' => 'Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600', 'titulo' => "'Fraunces'", 'corpo' => "'Inter'",
            'headline' => 'Orientação clara para decisões importantes.', 'sub' => 'Conte o seu caso — retornamos rápido pelo WhatsApp, com atendimento humano.',
            'servicos_titulo' => 'Áreas de atuação', 'galeria_titulo' => 'Nosso escritório'],
        'tecnologia' => ['nome' => 'Tecnologia', 'hero' => 'split', 'motivo' => 'grade', 'icone' => $icone['raio'], 'servicos' => 'cards',
            'cores' => ['bg' => '#070a18', 'bg2' => '#0d1126', 'superficie' => 'rgba(255,255,255,.04)', 'texto' => '#eef1ff', 'suave' => '#a3a9c8', 'linha' => 'rgba(255,255,255,.09)', 'accent' => '#6d7cff', 'escuro' => true],
            'fontes' => 'Sora:wght@500;600;700;800&family=Inter:wght@400;500;600', 'titulo' => "'Sora'", 'corpo' => "'Inter'",
            'headline' => 'Tecnologia que trabalha por você.', 'sub' => 'Fale com um especialista e receba uma proposta sob medida.',
            'servicos_titulo' => 'Soluções', 'galeria_titulo' => 'Projetos'],
        'educacao' => ['nome' => 'Educação', 'hero' => 'split', 'motivo' => 'pontos', 'icone' => $icone['livro'], 'servicos' => 'cards',
            'cores' => ['bg' => '#ffffff', 'bg2' => '#f2f5fd', 'superficie' => '#ffffff', 'texto' => '#111a33', 'suave' => '#58617a', 'linha' => 'rgba(17,26,51,.09)', 'accent' => '#2f62ea', 'escuro' => false],
            'fontes' => 'Plus+Jakarta+Sans:wght@400;500;600;700;800', 'titulo' => "'Plus Jakarta Sans'", 'corpo' => "'Plus Jakarta Sans'",
            'headline' => 'Aprender pode ser leve — e dar resultado.', 'sub' => 'Conheça as turmas e agende uma aula experimental.',
            'servicos_titulo' => 'Cursos e aulas', 'galeria_titulo' => 'Nosso espaço'],
        'varejo' => ['nome' => 'Lojas', 'hero' => 'split', 'motivo' => 'linhas', 'icone' => $icone['sacola'], 'servicos' => 'cards',
            'cores' => ['bg' => '#ffffff', 'bg2' => '#f5f3f0', 'superficie' => '#ffffff', 'texto' => '#121212', 'suave' => '#626262', 'linha' => 'rgba(18,18,18,.1)', 'accent' => '#e2552f', 'escuro' => false],
            'fontes' => 'Syne:wght@600;700;800&family=Inter:wght@400;500;600', 'titulo' => "'Syne'", 'corpo' => "'Inter'",
            'headline' => 'Escolha, peça e receba sem complicação.', 'sub' => 'Veja os produtos, monte seu pedido e acompanhe tudo pelo WhatsApp.',
            'servicos_titulo' => 'Produtos', 'galeria_titulo' => 'Vitrine'],
        'turismo' => ['nome' => 'Turismo e hospedagem', 'hero' => 'foto', 'motivo' => 'ondas', 'icone' => $icone['casa'], 'servicos' => 'cards',
            'cores' => ['bg' => '#f7f5ef', 'bg2' => '#ece8dc', 'superficie' => '#ffffff', 'texto' => '#1b231f', 'suave' => '#5f6a63', 'linha' => 'rgba(27,35,31,.1)', 'accent' => '#2e6b57', 'escuro' => false],
            'fontes' => 'Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700', 'titulo' => "'Cormorant Garamond'", 'corpo' => "'Manrope'",
            'headline' => 'Seu descanso começa antes da viagem.', 'sub' => 'Consulte datas e valores direto pelo WhatsApp.',
            'servicos_titulo' => 'Acomodações e pacotes', 'galeria_titulo' => 'Conheça o lugar'],
        'geral' => ['nome' => 'Negócio local', 'hero' => 'split', 'motivo' => 'grade', 'icone' => $icone['estrela'], 'servicos' => 'cards',
            'cores' => ['bg' => '#f7f7fb', 'bg2' => '#eeeef6', 'superficie' => '#ffffff', 'texto' => '#141428', 'suave' => '#5f6078', 'linha' => 'rgba(20,20,40,.09)', 'accent' => '#5146e5', 'escuro' => false],
            'fontes' => 'Sora:wght@500;600;700;800&family=Inter:wght@400;500;600', 'titulo' => "'Sora'", 'corpo' => "'Inter'",
            'headline' => '', 'sub' => '', 'servicos_titulo' => 'Serviços', 'galeria_titulo' => 'Galeria'],
    ];
}

// Qual nicho visual combina com a empresa
function site_nicho_da_empresa($empresa) {
    $slug = $empresa['segmento_slug'] ?? '';
    $porSlug = [
        'barbearia' => 'barbearia', 'tatuagem' => 'tatuagem',
        'salao' => 'beleza', 'estetica' => 'beleza', 'manicure' => 'beleza', 'sobrancelhas' => 'beleza', 'maquiagem' => 'beleza', 'depilacao' => 'beleza',
        'spa' => 'beleza', 'bronzeamento' => 'beleza', 'trancas' => 'beleza',
        'cafeteria' => 'cafe', 'padaria' => 'cafe', 'doceria' => 'cafe',
        'dj' => 'eventos', 'eventos' => 'eventos', 'fotografia' => 'eventos', 'banda' => 'eventos', 'filmagem' => 'eventos', 'decoracao-festas' => 'eventos',
        'salao-festas' => 'eventos', 'cerimonial' => 'eventos', 'recreacao' => 'eventos', 'buffet' => 'eventos', 'bar' => 'eventos', 'estudio-foto' => 'eventos',
        'advogado' => 'profissional', 'contabilidade' => 'profissional', 'consultoria' => 'profissional', 'imobiliaria' => 'profissional', 'seguros' => 'profissional',
        'agencia' => 'tecnologia', 'tecnologia' => 'tecnologia', 'sites-apps' => 'tecnologia', 'social-media' => 'tecnologia', 'design' => 'tecnologia',
        'petshop' => 'pet', 'veterinario' => 'saude',
    ];
    if (isset($porSlug[$slug])) return $porSlug[$slug];
    return [
        'Alimentação e Bebidas' => 'gastronomia', 'Beleza e Estética' => 'beleza', 'Saúde' => 'saude', 'Esporte e Bem-estar' => 'fitness', 'Pets' => 'pet',
        'Eventos e Festas' => 'eventos', 'Casa e Construção' => 'casa', 'Automotivo' => 'auto', 'Serviços Profissionais' => 'profissional',
        'Tecnologia e Marketing' => 'tecnologia', 'Educação' => 'educacao', 'Comércio e Lojas' => 'varejo', 'Turismo e Hospedagem' => 'turismo',
        'Imóveis e Condomínios' => 'profissional', 'Pessoal e Criativo' => 'eventos',
    ][$empresa['segmento_grupo'] ?? ''] ?? 'geral';
}

// Frases de impacto específicas por segmento (ganham da frase do nicho)
function site_headline_segmento($slug) {
    return [
        'dj' => ['A pista cheia do começo ao fim.', 'Casamentos, 15 anos e festas com som, luz e energia certa. Conte a data — o orçamento chega no seu WhatsApp.'],
        'fotografia' => ['Momentos que viram memória.', 'Ensaios e eventos fotografados com sensibilidade. Peça seu orçamento em 1 minuto.'],
        'hamburgueria' => ['Hambúrguer de verdade, do jeito que você gosta.', 'Monte seu pedido aqui mesmo — entrega ou retirada, com cada etapa avisada no WhatsApp.'],
        'pizzaria' => ['Pizza quentinha, borda do jeito que você ama.', 'Peça pelo site e acompanhe tudo pelo WhatsApp: no forno, a caminho, entregue.'],
        'acai' => ['Açaí do seu jeito, montado na hora.', 'Escolha o tamanho, os complementos e peça agora mesmo.'],
        'odontologia' => ['Seu sorriso em boas mãos.', 'Agende sua avaliação online e receba lembrete antes da consulta.'],
        'psicologia' => ['Um espaço seguro para você.', 'Agende sua primeira sessão com discrição — presencial ou online.'],
        'mecanica' => ['Seu carro em boas mãos.', 'Conte o que está acontecendo e receba o orçamento no WhatsApp, com transparência.'],
        'advogado' => ['Seus direitos, com quem entende.', 'Conte o seu caso e receba o retorno de um advogado pelo WhatsApp.'],
        'salao' => ['Cabelo dos sonhos, sem esperar na fila.', 'Agende online, escolha a profissional e receba a confirmação no WhatsApp.'],
        'manicure' => ['Unhas impecáveis, no horário que cabe na sua rotina.', 'Agende online e receba lembrete no WhatsApp.'],
        'academia' => ['Seu melhor treino começa hoje.', 'Agende sua aula experimental e venha conhecer a equipe.'],
    ][$slug] ?? null;
}
