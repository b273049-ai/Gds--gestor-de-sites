-- ============================================================
-- ADICIONAL 13: segmentos inteligentes e "forma de atendimento".
-- Cada segmento diz à IA como esse tipo de negócio atende o cliente
-- (agendamento, orçamento, contato ou catálogo), quem é o público e o
-- que o site precisa ter. Também é aplicado sozinho pelo sistema.
-- ============================================================

ALTER TABLE segmentos ADD COLUMN IF NOT EXISTS modo_atendimento VARCHAR(20);   -- agendamento | orcamento | contato | catalogo
ALTER TABLE segmentos ADD COLUMN IF NOT EXISTS descricao_ia TEXT;              -- orientação para a IA criar o site
ALTER TABLE segmentos ADD COLUMN IF NOT EXISTS cta VARCHAR(80);                -- texto do botão principal
ALTER TABLE segmentos ADD COLUMN IF NOT EXISTS exemplos_servicos TEXT;         -- serviços típicos (um por linha)

ALTER TABLE empresas ADD COLUMN IF NOT EXISTS modo_atendimento VARCHAR(20);    -- vazio = usa o do segmento
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS segmento_descricao VARCHAR(255); -- quando nenhum segmento serve ("Outro")

-- Pedidos de orçamento que chegam pelo site
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS origem VARCHAR(20) DEFAULT 'painel';
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS servico_id INT REFERENCES servicos(id) ON DELETE SET NULL;
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS data_evento DATE;
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS local_evento VARCHAR(150);

-- Segmentos padrão (não sobrescreve o que o admin já configurou)
INSERT INTO segmentos (nome, slug, modo_atendimento, cta, descricao_ia, exemplos_servicos) VALUES
('Barbearia', 'barbearia', 'agendamento', 'Agendar horário', 'Público principalmente masculino que quer marcar corte/barba rápido pelo celular. Valorize estilo, ambiente, equipe e preços claros. O visitante quer: ver serviços e preços, escolher o barbeiro e marcar.', E'Corte\nBarba\nCorte + Barba\nSobrancelha\nPigmentação'),
('Salão de Beleza', 'salao', 'agendamento', 'Agendar horário', 'Público principalmente feminino; vende transformação e autocuidado. Fotos de antes/depois e equipe pesam muito. O visitante quer ver serviços, profissionais e marcar.', E'Corte feminino\nEscova\nColoração\nManicure e pedicure\nDesign de sobrancelha'),
('Clínica de Estética', 'estetica', 'agendamento', 'Agendar avaliação', 'Procedimentos estéticos; transmita segurança, higiene, resultados e profissionais qualificados. Primeiro passo costuma ser uma avaliação.', E'Avaliação\nLimpeza de pele\nDepilação a laser\nDrenagem linfática\nHarmonização facial'),
('Clínica Médica', 'clinica', 'agendamento', 'Agendar consulta', 'Saúde: confiança, especialidades, corpo clínico, convênios e localização. Linguagem acolhedora e clara, sem promessas de cura.', E'Consulta\nRetorno\nExames\nCheck-up'),
('Odontologia', 'odontologia', 'agendamento', 'Agendar avaliação', 'Dentista: confiança, conforto, tecnologia e tratamentos. Primeiro passo: avaliação.', E'Avaliação\nLimpeza\nClareamento\nOrtodontia\nImplante'),
('Psicologia e Terapia', 'psicologia', 'agendamento', 'Agendar sessão', 'Tom acolhedor, discreto e humano. Explique abordagem, formato (presencial/online) e como funciona a primeira sessão. Sem promessas.', E'Primeira sessão\nPsicoterapia individual\nTerapia de casal\nAtendimento online'),
('Nutrição', 'nutricao', 'agendamento', 'Agendar consulta', 'Saúde e hábitos; mostre método, acompanhamento e resultados reais sem promessas milagrosas.', E'Consulta inicial\nRetorno\nPlano alimentar\nAcompanhamento mensal'),
('Academia e Personal', 'academia', 'agendamento', 'Agendar aula experimental', 'Energia, resultado e comunidade. Primeiro passo: aula experimental. Mostre modalidades, horários e planos.', E'Aula experimental\nMusculação\nFuncional\nPersonal trainer'),
('Pet Shop e Veterinário', 'petshop', 'agendamento', 'Agendar banho e tosa', 'Carinho com os animais, confiança e praticidade (leva e traz). O visitante quer marcar banho/tosa ou consulta.', E'Banho\nTosa\nBanho e tosa\nConsulta veterinária\nVacinação'),
('Mecânica e Oficina', 'mecanica', 'orcamento', 'Pedir orçamento', 'Oficina: honestidade, transparência, garantia e agilidade. O cliente descreve o problema do carro e pede orçamento.', E'Revisão\nTroca de óleo\nFreios\nSuspensão\nDiagnóstico eletrônico'),
('Advocacia', 'advogado', 'orcamento', 'Falar com um advogado', 'Escritório de advocacia: credibilidade, áreas de atuação e atendimento humano. O visitante descreve o caso e pede contato. Respeite as regras da OAB: sem promessas de resultado e sem preços.', E'Direito trabalhista\nDireito de família\nDireito previdenciário\nDireito do consumidor'),
('Fotografia', 'fotografia', 'orcamento', 'Pedir orçamento', 'Fotógrafo: o portfólio é o protagonista (galeria grande). Pacotes por tipo de evento/ensaio. O cliente informa data e tipo de evento e pede orçamento.', E'Casamento\nEnsaio\nAniversário\nEventos corporativos\nNewborn'),
('DJ e Música para Eventos', 'dj', 'orcamento', 'Pedir orçamento', 'DJ / música ao vivo para eventos: energia, estilo musical, eventos já feitos, equipamento (som e luz) e vídeos/fotos de festas. O cliente informa data, tipo de evento, local e número de convidados e pede orçamento. Não é agendamento de horário.', E'Casamento\nFesta de 15 anos\nAniversário\nEvento corporativo\nFormatura\nSom e iluminação'),
('Buffet e Eventos', 'eventos', 'orcamento', 'Pedir orçamento', 'Eventos: mostre estrutura, cardápios/pacotes, fotos de eventos e depoimentos. O cliente informa data, tipo de evento e convidados.', E'Casamento\nAniversário\nCorporativo\nCoffee break'),
('Construção e Reformas', 'construcao', 'orcamento', 'Pedir orçamento', 'Obras e reformas: portfólio de obras, prazos, garantia e confiança. O cliente descreve a obra e pede visita/orçamento.', E'Reforma completa\nPintura\nElétrica\nHidráulica\nGesso e drywall'),
('Agência e Marketing Digital', 'agencia', 'orcamento', 'Pedir proposta', 'Agência / venda de sites, marketing e tecnologia para empresas: mostre o que entrega, processo, planos/pacotes, cases e resultados. O visitante quer entender o serviço e pedir proposta ou falar no WhatsApp.', E'Criação de site\nGestão de redes sociais\nTráfego pago\nIdentidade visual'),
('Tecnologia e Software', 'tecnologia', 'contato', 'Falar com especialista', 'Empresa de tecnologia/SaaS: proposta de valor clara, benefícios, como funciona, planos e prova social. CTA para demonstração ou contato.', E'Plano básico\nPlano profissional\nImplantação\nSuporte'),
('Consultoria', 'consultoria', 'orcamento', 'Solicitar proposta', 'Consultoria: autoridade, método, resultados e cases. O visitante descreve o desafio e pede proposta.', E'Diagnóstico\nConsultoria mensal\nTreinamento\nMentoria'),
('Imobiliária e Corretor', 'imobiliaria', 'contato', 'Falar com um corretor', 'Imóveis: destaque imóveis/regiões atendidas, credibilidade (CRECI) e atendimento rápido pelo WhatsApp.', E'Venda de imóveis\nLocação\nAvaliação de imóvel\nAdministração de aluguéis'),
('Restaurante e Delivery', 'restaurante', 'catalogo', 'Pedir pelo WhatsApp', 'Comida: fotos apetitosas, cardápio com preços, horário, delivery/retirada e localização. O visitante quer ver o cardápio e pedir.', E'Prato executivo\nPizza\nHambúrguer\nSobremesa\nBebidas'),
('Loja e Comércio', 'loja', 'catalogo', 'Comprar pelo WhatsApp', 'Loja: produtos em destaque com preço, formas de pagamento, entrega e trocas. O visitante escolhe o produto e chama no WhatsApp.', E'Produto em destaque\nLançamentos\nPromoções'),
('Hotel e Pousada', 'hotel', 'contato', 'Consultar disponibilidade', 'Hospedagem: fotos dos quartos e da região, comodidades, localização. O visitante consulta datas e valores.', E'Quarto casal\nQuarto família\nSuíte\nPacote fim de semana'),
('Cursos e Aulas', 'educacao', 'contato', 'Quero me inscrever', 'Educação: o que o aluno aprende, para quem é, professor, formato, depoimentos e investimento.', E'Aula particular\nCurso online\nTurma presencial\nAula experimental'),
('Outro', 'outro', NULL, NULL, 'Segmento não listado: a IA deve entender o negócio pela descrição do dono e escolher a forma de atendimento ideal.', NULL)
ON CONFLICT (slug) DO UPDATE SET
    nome = CASE WHEN segmentos.nome IN ('Salão', 'Clínica', 'Advogado', 'Mecânica', 'Hotel/Pousada', 'Pet Shop', 'Academia', 'Restaurante', 'Imobiliária') THEN EXCLUDED.nome ELSE segmentos.nome END,
    modo_atendimento = COALESCE(segmentos.modo_atendimento, EXCLUDED.modo_atendimento),
    cta = COALESCE(segmentos.cta, EXCLUDED.cta),
    descricao_ia = COALESCE(segmentos.descricao_ia, EXCLUDED.descricao_ia),
    exemplos_servicos = COALESCE(segmentos.exemplos_servicos, EXCLUDED.exemplos_servicos);
