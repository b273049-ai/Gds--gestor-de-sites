-- ============================================================
-- ADICIONAL 14: muito mais segmentos (de barbearia a delivery, fast food,
-- serviços para casa, eventos, lojas, educação...) organizados em grupos.
-- Também é aplicado sozinho pelo sistema. Não sobrescreve o que o admin
-- já editou.
-- ============================================================

ALTER TABLE segmentos ADD COLUMN IF NOT EXISTS grupo VARCHAR(60);

UPDATE segmentos SET grupo='Beleza e Estética' WHERE slug='barbearia' AND grupo IS NULL;
UPDATE segmentos SET grupo='Beleza e Estética' WHERE slug='salao' AND grupo IS NULL;
UPDATE segmentos SET grupo='Beleza e Estética' WHERE slug='estetica' AND grupo IS NULL;
UPDATE segmentos SET grupo='Saúde' WHERE slug='clinica' AND grupo IS NULL;
UPDATE segmentos SET grupo='Saúde' WHERE slug='odontologia' AND grupo IS NULL;
UPDATE segmentos SET grupo='Saúde' WHERE slug='psicologia' AND grupo IS NULL;
UPDATE segmentos SET grupo='Saúde' WHERE slug='nutricao' AND grupo IS NULL;
UPDATE segmentos SET grupo='Esporte e Bem-estar' WHERE slug='academia' AND grupo IS NULL;
UPDATE segmentos SET grupo='Pets' WHERE slug='petshop' AND grupo IS NULL;
UPDATE segmentos SET grupo='Automotivo' WHERE slug='mecanica' AND grupo IS NULL;
UPDATE segmentos SET grupo='Serviços Profissionais' WHERE slug='advogado' AND grupo IS NULL;
UPDATE segmentos SET grupo='Eventos e Festas' WHERE slug='fotografia' AND grupo IS NULL;
UPDATE segmentos SET grupo='Eventos e Festas' WHERE slug='dj' AND grupo IS NULL;
UPDATE segmentos SET grupo='Eventos e Festas' WHERE slug='eventos' AND grupo IS NULL;
UPDATE segmentos SET grupo='Casa e Construção' WHERE slug='construcao' AND grupo IS NULL;
UPDATE segmentos SET grupo='Tecnologia e Marketing' WHERE slug='agencia' AND grupo IS NULL;
UPDATE segmentos SET grupo='Tecnologia e Marketing' WHERE slug='tecnologia' AND grupo IS NULL;
UPDATE segmentos SET grupo='Serviços Profissionais' WHERE slug='consultoria' AND grupo IS NULL;
UPDATE segmentos SET grupo='Imóveis e Condomínios' WHERE slug='imobiliaria' AND grupo IS NULL;
UPDATE segmentos SET grupo='Alimentação e Bebidas' WHERE slug='restaurante' AND grupo IS NULL;
UPDATE segmentos SET grupo='Comércio e Lojas' WHERE slug='loja' AND grupo IS NULL;
UPDATE segmentos SET grupo='Turismo e Hospedagem' WHERE slug='hotel' AND grupo IS NULL;
UPDATE segmentos SET grupo='Educação' WHERE slug='educacao' AND grupo IS NULL;
UPDATE segmentos SET grupo='Outro' WHERE slug='outro' AND grupo IS NULL;
UPDATE segmentos SET nome='Restaurante' WHERE slug='restaurante' AND nome='Restaurante e Delivery';

INSERT INTO segmentos (nome, slug, grupo, modo_atendimento, cta, descricao_ia, exemplos_servicos) VALUES
('Hamburgueria', 'hamburgueria', 'Alimentação e Bebidas', 'catalogo', 'Pedir agora', 'Hambúrguer artesanal: fotos suculentas, combos com preço, delivery/retirada, tempo de entrega e horário. O visitante quer ver o cardápio e pedir rápido pelo WhatsApp.', E'Hambúrguer clássico\nSmash burger\nCombo com batata e refri\nBatata frita\nMilkshake'),
('Pizzaria', 'pizzaria', 'Alimentação e Bebidas', 'catalogo', 'Pedir pizza', 'Pizzaria: sabores com preço por tamanho, promoções do dia, delivery e horário. Pedido rápido pelo WhatsApp.', E'Pizza grande\nPizza média\nPizza doce\nBorda recheada\nRefrigerante 2L'),
('Lanchonete e Fast Food', 'fast-food', 'Alimentação e Bebidas', 'catalogo', 'Pedir agora', 'Fast food: cardápio enxuto com preço, combos, rapidez e delivery. Visual chamativo e direto ao ponto.', E'Combo lanche\nCachorro-quente\nPastel\nPorção\nRefrigerante'),
('Delivery de Comida', 'delivery', 'Alimentação e Bebidas', 'catalogo', 'Pedir pelo WhatsApp', 'Delivery: cardápio com preço, área e taxa de entrega, tempo médio e formas de pagamento. O visitante quer pedir sem sair de casa.', E'Prato do dia\nCombo família\nBebidas\nSobremesas'),
('Marmitaria e Comida Caseira', 'marmitaria', 'Alimentação e Bebidas', 'catalogo', 'Pedir marmita', 'Comida caseira: cardápio da semana, tamanhos de marmita com preço, entrega no almoço e planos semanais/mensais.', E'Marmita pequena\nMarmita grande\nMarmita fitness\nPlano semanal'),
('Japonês e Sushi', 'japones', 'Alimentação e Bebidas', 'catalogo', 'Pedir agora', 'Culinária japonesa: combinados com fotos e preço, temakis, delivery e rodízio. Visual limpo e elegante.', E'Combinado 20 peças\nTemaki\nHot roll\nYakisoba\nRodízio'),
('Churrascaria e Espetinho', 'churrascaria', 'Alimentação e Bebidas', 'catalogo', 'Ver cardápio', 'Carnes: cortes, rodízio ou espetinhos com preço, ambiente para família e eventos, delivery de kits.', E'Rodízio\nEspetinho\nKit churrasco\nPorção de carne'),
('Açaí e Sorveteria', 'acai', 'Alimentação e Bebidas', 'catalogo', 'Montar meu açaí', 'Açaí/sorvete: tamanhos com preço, complementos, promoções e delivery. Visual colorido e refrescante.', E'Açaí 300ml\nAçaí 500ml\nAçaí 700ml\nSorvete\nMilkshake'),
('Padaria e Confeitaria', 'padaria', 'Alimentação e Bebidas', 'catalogo', 'Fazer encomenda', 'Padaria/confeitaria: produtos do dia, encomendas de bolos e salgados para festas, café da manhã e horário.', E'Pão francês\nBolo de aniversário\nSalgados para festa\nCafé da manhã\nTortas'),
('Doceria e Bolos Artesanais', 'doceria', 'Alimentação e Bebidas', 'orcamento', 'Encomendar', 'Doces e bolos sob encomenda: portfólio de fotos, sabores, tamanhos e antecedência. O cliente informa a data da festa e pede orçamento.', E'Bolo personalizado\nDocinhos (cento)\nBolo no pote\nKit festa\nBrownie'),
('Cafeteria', 'cafeteria', 'Alimentação e Bebidas', 'catalogo', 'Ver cardápio', 'Cafeteria: ambiente aconchegante, cafés especiais, doces e lanches com preço, horário e localização.', E'Espresso\nCappuccino\nCafé especial\nPão de queijo\nBolo do dia'),
('Bar e Pub', 'bar', 'Alimentação e Bebidas', 'catalogo', 'Reservar mesa', 'Bar: clima, drinks, petiscos com preço, música ao vivo/agenda e reservas para grupos.', E'Chopp\nDrinks autorais\nPorções\nHappy hour\nReserva para aniversário'),
('Food Truck', 'food-truck', 'Alimentação e Bebidas', 'catalogo', 'Ver onde estamos', 'Food truck: cardápio, agenda de onde o truck estará e atendimento para eventos (orçamento).', E'Lanche da casa\nCombo\nEvento particular'),
('Buffet e Comida para Eventos', 'buffet', 'Alimentação e Bebidas', 'orcamento', 'Pedir orçamento', 'Buffet: cardápios/pacotes por pessoa, fotos de eventos, estrutura. O cliente informa data, tipo de evento e convidados.', E'Buffet de festa\nFingerfood\nCoffee break\nChurrasco para eventos'),
('Bebidas e Adega', 'adega', 'Alimentação e Bebidas', 'catalogo', 'Pedir pelo WhatsApp', 'Adega/distribuidora: bebidas com preço, gelo e carvão, entrega rápida e horário até tarde.', E'Cerveja (fardo)\nDestilados\nVinhos\nGelo\nCarvão'),
('Hortifruti e Mercearia', 'mercearia', 'Alimentação e Bebidas', 'catalogo', 'Fazer pedido', 'Mercearia/hortifruti/empório: produtos frescos, cestas, entrega no bairro e formas de pagamento.', E'Cesta de frutas\nVerduras\nCesta básica\nProdutos naturais'),
('Manicure e Nail Designer', 'manicure', 'Beleza e Estética', 'agendamento', 'Agendar horário', 'Unhas: fotos de trabalhos, técnicas (gel, fibra, esmaltação), preços e agendamento fácil.', E'Manicure\nPedicure\nAlongamento em gel\nBlindagem\nManutenção'),
('Design de Sobrancelhas e Cílios', 'sobrancelhas', 'Beleza e Estética', 'agendamento', 'Agendar horário', 'Olhar: antes/depois, técnicas (design, henna, lash lifting, extensão de cílios) e agendamento.', E'Design de sobrancelha\nHenna\nLash lifting\nExtensão de cílios\nMicropigmentação'),
('Maquiagem Profissional', 'maquiagem', 'Beleza e Estética', 'orcamento', 'Pedir orçamento', 'Maquiadora: portfólio de noivas e eventos, atendimento a domicílio. A cliente informa data e local do evento.', E'Maquiagem social\nNoiva\nPenteado\nAtendimento a domicílio'),
('Depilação', 'depilacao', 'Beleza e Estética', 'agendamento', 'Agendar horário', 'Depilação: métodos (cera, laser), higiene, pacotes e agendamento.', E'Depilação com cera\nDepilação a laser\nPacote corpo inteiro'),
('Spa e Massagem', 'spa', 'Beleza e Estética', 'agendamento', 'Agendar sessão', 'Bem-estar: relaxamento, tipos de massagem, ambiente, pacotes e vale-presente.', E'Massagem relaxante\nMassagem modeladora\nDay spa\nPedras quentes\nVale-presente'),
('Estúdio de Tatuagem e Piercing', 'tatuagem', 'Beleza e Estética', 'orcamento', 'Pedir orçamento', 'Tatuagem: portfólio grande por estilo, tatuadores, higiene. O cliente envia a ideia, local do corpo e tamanho para orçamento.', E'Tatuagem fineline\nTatuagem colorida\nCobertura\nPiercing'),
('Bronzeamento', 'bronzeamento', 'Beleza e Estética', 'agendamento', 'Agendar horário', 'Bronzeamento natural/artificial: resultados, cuidados, pacotes e agendamento.', E'Bronzeamento natural\nBronze a jato\nPacote verão'),
('Trancista e Cabelos Afro', 'trancas', 'Beleza e Estética', 'agendamento', 'Agendar horário', 'Tranças e cabelos crespos/cacheados: portfólio, estilos, tempo de cada trança e agendamento.', E'Box braids\nNagô\nTwist\nCorte e finalização afro'),
('Fisioterapia', 'fisioterapia', 'Saúde', 'agendamento', 'Agendar avaliação', 'Fisioterapia: especialidades (ortopédica, RPG, pilates clínico), atendimento domiciliar, convênios.', E'Avaliação\nFisioterapia ortopédica\nRPG\nPilates clínico\nAtendimento domiciliar'),
('Fonoaudiologia', 'fonoaudiologia', 'Saúde', 'agendamento', 'Agendar avaliação', 'Fono: público (infantil/adulto), áreas atendidas, acolhimento e agendamento.', E'Avaliação\nTerapia de fala\nAudiometria\nVoz'),
('Oftalmologia e Ótica', 'otica', 'Saúde', 'contato', 'Falar no WhatsApp', 'Ótica/oftalmo: armações e lentes, exame de vista, marcas e formas de pagamento.', E'Exame de vista\nÓculos de grau\nÓculos de sol\nLentes de contato'),
('Laboratório e Exames', 'laboratorio', 'Saúde', 'agendamento', 'Agendar exame', 'Laboratório: exames, preparo, convênios, coleta domiciliar e resultados online.', E'Exame de sangue\nCheck-up\nColeta domiciliar\nTeste de gravidez'),
('Farmácia', 'farmacia', 'Saúde', 'catalogo', 'Pedir pelo WhatsApp', 'Farmácia: entrega rápida, medicamentos e perfumaria, plantão e formas de pagamento.', E'Medicamentos\nPerfumaria\nAferição de pressão\nEntrega'),
('Medicina Veterinária', 'veterinario', 'Saúde', 'agendamento', 'Agendar consulta', 'Clínica veterinária: especialidades, emergência, vacinas, exames e cuidado com o pet.', E'Consulta\nVacinação\nCastração\nExames\nEmergência'),
('Home Care e Cuidador de Idosos', 'cuidador', 'Saúde', 'orcamento', 'Pedir orçamento', 'Cuidados em casa: profissionais qualificados, plantões, confiança. A família descreve a necessidade e pede orçamento.', E'Cuidador diurno\nCuidador noturno\nEnfermagem domiciliar\nAcompanhante hospitalar'),
('Terapias Holísticas', 'holistico', 'Saúde', 'agendamento', 'Agendar sessão', 'Terapias integrativas: acolhimento, explicação de cada terapia sem promessas de cura, online/presencial.', E'Reiki\nAcupuntura\nAromaterapia\nConstelação familiar\nBarra de Access'),
('Pilates', 'pilates', 'Esporte e Bem-estar', 'agendamento', 'Agendar aula experimental', 'Pilates: benefícios, estúdio, instrutores, planos e aula experimental.', E'Aula experimental\nPilates solo\nPilates aparelho\nPlano mensal'),
('Yoga e Meditação', 'yoga', 'Esporte e Bem-estar', 'agendamento', 'Agendar aula experimental', 'Yoga: estilos, professores, horários de turmas, online/presencial.', E'Aula experimental\nHatha yoga\nMeditação\nPlano mensal'),
('Crossfit e Funcional', 'crossfit', 'Esporte e Bem-estar', 'agendamento', 'Agendar aula experimental', 'Box/funcional: comunidade, energia, horários das turmas, planos e aula experimental.', E'Aula experimental\nCrossfit\nFuncional\nPlano trimestral'),
('Artes Marciais e Lutas', 'lutas', 'Esporte e Bem-estar', 'agendamento', 'Agendar aula experimental', 'Lutas: modalidades, faixas/níveis, turmas infantis e adultas, professores.', E'Jiu-jitsu\nMuay thai\nBoxe\nKarate\nTurma kids'),
('Escola de Dança', 'danca', 'Esporte e Bem-estar', 'agendamento', 'Agendar aula experimental', 'Dança: estilos, turmas por nível, eventos/apresentações e aula experimental.', E'Ballet\nDança de salão\nZumba\nHip hop\nAula particular'),
('Natação e Escola de Esportes', 'natacao', 'Esporte e Bem-estar', 'agendamento', 'Agendar aula experimental', 'Escola de esportes: modalidades, idades, horários, estrutura e professores.', E'Natação infantil\nNatação adulto\nHidroginástica\nFutebol society'),
('Quadra e Espaço Esportivo', 'quadra', 'Esporte e Bem-estar', 'agendamento', 'Reservar horário', 'Aluguel de quadra: horários livres, valores por hora, estrutura (vestiário, churrasqueira).', E'Quadra society (1h)\nQuadra de areia\nBeach tennis\nMensalista'),
('Banho e Tosa', 'banho-tosa', 'Pets', 'agendamento', 'Agendar banho', 'Estética animal: carinho, segurança, leva e traz, pacotes mensais.', E'Banho\nTosa higiênica\nTosa completa\nHidratação\nPacote mensal'),
('Adestramento', 'adestramento', 'Pets', 'orcamento', 'Pedir avaliação', 'Adestramento: método positivo, resultados, aulas a domicílio. O tutor descreve o comportamento do pet.', E'Avaliação\nAdestramento básico\nCorreção de comportamento\nPacote 10 aulas'),
('Hotel e Creche para Pets', 'hotel-pet', 'Pets', 'contato', 'Consultar vagas', 'Hospedagem pet: estrutura, rotina, segurança, câmeras e fotos dos pets. O tutor consulta datas.', E'Diária hotel\nCreche (day care)\nPacote mensal'),
('Pet Sitter e Dog Walker', 'dog-walker', 'Pets', 'agendamento', 'Agendar passeio', 'Passeios e visitas: confiança, rotina, fotos durante o passeio e pacotes.', E'Passeio 30 min\nPasseio 1h\nVisita pet sitter\nPacote semanal'),
('Decoração de Festas', 'decoracao-festas', 'Eventos e Festas', 'orcamento', 'Pedir orçamento', 'Decoração: portfólio de festas por tema, pegue e monte, kits. O cliente informa data, tema e local.', E'Decoração infantil\nPegue e monte\nCasamento\nChá revelação'),
('Salão de Festas e Espaço para Eventos', 'salao-festas', 'Eventos e Festas', 'orcamento', 'Consultar data', 'Espaço para eventos: fotos do espaço, capacidade, estrutura e pacotes. O cliente consulta data e convidados.', E'Aniversário\nCasamento\nEvento corporativo\nPacote completo'),
('Cerimonial e Assessoria de Casamento', 'cerimonial', 'Eventos e Festas', 'orcamento', 'Pedir orçamento', 'Assessoria: tranquilidade para os noivos, portfólio de casamentos, pacotes. Informar data e local.', E'Assessoria completa\nAssessoria do dia\nCerimonial'),
('Filmagem e Drone', 'filmagem', 'Eventos e Festas', 'orcamento', 'Pedir orçamento', 'Vídeo: showreel, filmes de casamento/eventos, drone, institucionais. Informar data e tipo de evento.', E'Filmagem de casamento\nVídeo institucional\nDrone\nAftermovie'),
('Banda e Música ao Vivo', 'banda', 'Eventos e Festas', 'orcamento', 'Pedir orçamento', 'Música ao vivo: vídeos, repertório, formações e eventos atendidos. Informar data, local e tipo de evento.', E'Voz e violão\nBanda completa\nCerimônia de casamento\nAtração para bar'),
('Animação e Recreação Infantil', 'recreacao', 'Eventos e Festas', 'orcamento', 'Pedir orçamento', 'Recreação: monitores, brincadeiras, personagens, segurança e alegria. Informar data, idade e número de crianças.', E'Recreação 3h\nPersonagem vivo\nPintura facial\nBrinquedos'),
('Aluguel de Brinquedos e Itens para Festa', 'aluguel-festa', 'Eventos e Festas', 'orcamento', 'Pedir orçamento', 'Locação: catálogo de itens com fotos, entrega e montagem. Informar data e endereço.', E'Pula-pula\nPiscina de bolinhas\nMesas e cadeiras\nTenda'),
('Convites e Papelaria Personalizada', 'papelaria', 'Eventos e Festas', 'orcamento', 'Pedir orçamento', 'Papelaria personalizada: portfólio, prazos, temas. O cliente informa a data e a ideia.', E'Convites\nTopo de bolo\nLembrancinhas\nKit festa'),
('Fotografia de Produto e Ensaios', 'estudio-foto', 'Eventos e Festas', 'agendamento', 'Agendar ensaio', 'Estúdio fotográfico: ensaios com horário marcado, pacotes, portfólio e fotos de produtos.', E'Ensaio gestante\nEnsaio corporativo\nFoto de produto\nEnsaio infantil'),
('Eletricista', 'eletricista', 'Casa e Construção', 'orcamento', 'Pedir orçamento', 'Eletricista: segurança, atendimento rápido, serviços residenciais e comerciais. O cliente descreve o problema.', E'Instalação elétrica\nTroca de disjuntor\nChuveiro\nPadrão de energia\nEmergência'),
('Encanador e Desentupidora', 'encanador', 'Casa e Construção', 'orcamento', 'Chamar agora', 'Hidráulica: atendimento 24h, rapidez, orçamento sem compromisso.', E'Desentupimento\nVazamento\nCaça-vazamento\nInstalação hidráulica'),
('Pintor', 'pintor', 'Casa e Construção', 'orcamento', 'Pedir orçamento', 'Pintura: antes/depois, acabamento, limpeza da obra. O cliente informa ambiente e metragem.', E'Pintura residencial\nPintura comercial\nTextura\nGrafiato'),
('Marcenaria e Móveis Planejados', 'marcenaria', 'Casa e Construção', 'orcamento', 'Pedir orçamento', 'Móveis sob medida: portfólio de ambientes, projeto 3D, material e prazo.', E'Cozinha planejada\nGuarda-roupa\nHome theater\nMóveis comerciais'),
('Serralheria e Vidraçaria', 'serralheria', 'Casa e Construção', 'orcamento', 'Pedir orçamento', 'Serralheria/vidros: portões, grades, box, esquadrias. Portfólio e orçamento com medidas.', E'Portão\nGrade\nBox de banheiro\nEsquadrias\nGuarda-corpo'),
('Arquitetura e Design de Interiores', 'arquitetura', 'Casa e Construção', 'orcamento', 'Solicitar proposta', 'Arquitetura: portfólio de projetos, processo, estilo. O cliente descreve o projeto e pede proposta.', E'Projeto residencial\nInteriores\nConsultoria\nProjeto comercial'),
('Engenharia e Projetos', 'engenharia', 'Casa e Construção', 'orcamento', 'Solicitar proposta', 'Engenharia: projetos, laudos, regularização, ART. Credibilidade e prazos.', E'Projeto estrutural\nLaudo técnico\nRegularização\nAcompanhamento de obra'),
('Jardinagem e Paisagismo', 'jardinagem', 'Casa e Construção', 'orcamento', 'Pedir orçamento', 'Jardins: antes/depois, manutenção mensal, projetos de paisagismo.', E'Manutenção de jardim\nPaisagismo\nPoda\nGrama'),
('Limpeza Residencial e Diarista', 'limpeza', 'Casa e Construção', 'agendamento', 'Agendar limpeza', 'Limpeza: confiança, profissionais verificados, pacotes e agendamento.', E'Faxina completa\nLimpeza pós-obra\nPassadoria\nPacote semanal'),
('Limpeza de Estofados e Higienização', 'estofados', 'Casa e Construção', 'orcamento', 'Pedir orçamento', 'Higienização: sofás, colchões, tapetes, antes/depois. O cliente informa o item e tamanho.', E'Sofá\nColchão\nTapete\nCadeiras\nImpermeabilização'),
('Ar-condicionado e Refrigeração', 'ar-condicionado', 'Casa e Construção', 'orcamento', 'Pedir orçamento', 'Climatização: instalação, manutenção e limpeza, marcas atendidas, PMOC para empresas.', E'Instalação\nLimpeza\nManutenção\nCarga de gás'),
('Dedetização e Controle de Pragas', 'dedetizacao', 'Casa e Construção', 'orcamento', 'Pedir orçamento', 'Controle de pragas: segurança para família e pets, certificado, garantia.', E'Dedetização\nDescupinização\nDesratização\nLimpeza de caixa d\'água'),
('Energia Solar', 'energia-solar', 'Casa e Construção', 'orcamento', 'Simular economia', 'Energia solar: economia na conta de luz, financiamento, projetos instalados. O cliente informa o valor da conta de luz.', E'Sistema residencial\nSistema comercial\nManutenção\nLimpeza de placas'),
('Chaveiro', 'chaveiro', 'Casa e Construção', 'contato', 'Chamar agora', 'Chaveiro: atendimento 24h, rapidez, cópias, fechaduras e automotivo.', E'Abertura de porta\nCópia de chave\nTroca de fechadura\nChave automotiva'),
('Mudanças e Fretes', 'mudancas', 'Casa e Construção', 'orcamento', 'Pedir orçamento', 'Mudanças: cuidado com os móveis, montagem, embalagem. Informar origem, destino e data.', E'Mudança residencial\nFrete\nMontagem de móveis\nGuarda-móveis'),
('Piscinas', 'piscinas', 'Casa e Construção', 'orcamento', 'Pedir orçamento', 'Piscinas: construção, manutenção e limpeza, produtos.', E'Limpeza mensal\nConstrução\nReforma\nAquecimento'),
('Segurança Eletrônica', 'seguranca', 'Casa e Construção', 'orcamento', 'Pedir orçamento', 'Segurança: câmeras, alarmes, cerca elétrica, monitoramento e portaria remota.', E'Câmeras\nAlarme\nCerca elétrica\nInterfone\nMonitoramento'),
('Materiais de Construção', 'material-construcao', 'Casa e Construção', 'catalogo', 'Fazer pedido', 'Loja de materiais: produtos, entrega, orçamento de lista de materiais.', E'Cimento\nTintas\nHidráulica\nElétrica\nOrçamento de lista'),
('Funilaria e Pintura', 'funilaria', 'Automotivo', 'orcamento', 'Pedir orçamento', 'Funilaria: antes/depois, seguro, prazos. O cliente envia fotos do dano para orçamento.', E'Funilaria\nPintura\nMartelinho de ouro\nPolimento'),
('Estética Automotiva e Lava-Rápido', 'lava-jato', 'Automotivo', 'agendamento', 'Agendar lavagem', 'Lava-rápido/estética: pacotes, antes/depois, agendamento e leva e traz.', E'Lavagem simples\nLavagem completa\nPolimento\nVitrificação\nHigienização interna'),
('Auto Elétrica', 'auto-eletrica', 'Automotivo', 'orcamento', 'Pedir orçamento', 'Elétrica automotiva: diagnóstico, baterias, som e acessórios.', E'Diagnóstico\nBateria\nAlternador\nSom automotivo'),
('Borracharia e Pneus', 'pneus', 'Automotivo', 'contato', 'Chamar no WhatsApp', 'Pneus: marcas, alinhamento e balanceamento, socorro.', E'Pneus novos\nAlinhamento\nBalanceamento\nSocorro'),
('Concessionária e Revenda de Veículos', 'veiculos', 'Automotivo', 'contato', 'Falar com vendedor', 'Veículos: estoque com fotos e preço, financiamento, troca. O visitante escolhe o carro e chama no WhatsApp.', E'Seminovos\n0 km\nFinanciamento\nAvaliação do seu usado'),
('Autoescola', 'autoescola', 'Automotivo', 'contato', 'Quero me matricular', 'Autoescola: categorias, aulas, aprovação, pacotes e formas de pagamento.', E'Primeira habilitação\nCategoria A\nCategoria B\nAulas avulsas'),
('Despachante', 'despachante', 'Automotivo', 'contato', 'Falar no WhatsApp', 'Despachante: documentação veicular rápida, transferências, licenciamento.', E'Transferência\nLicenciamento\nSegunda via\nRegularização'),
('Motoboy e Entregas', 'motoboy', 'Automotivo', 'orcamento', 'Pedir orçamento', 'Entregas: rapidez, área atendida, preços por distância, contratos mensais.', E'Entrega expressa\nColeta\nMensalista\nDocumentos'),
('Guincho', 'guincho', 'Automotivo', 'contato', 'Chamar agora', 'Guincho 24h: rapidez, área atendida, socorro mecânico.', E'Reboque\nSocorro mecânico\nTransporte de veículos'),
('Contabilidade', 'contabilidade', 'Serviços Profissionais', 'orcamento', 'Solicitar proposta', 'Contabilidade: abertura de empresa, MEI, impostos, planos mensais. Linguagem simples.', E'Abertura de empresa\nMEI\nContabilidade mensal\nImposto de renda'),
('Corretora de Seguros', 'seguros', 'Serviços Profissionais', 'orcamento', 'Fazer cotação', 'Seguros: tipos de seguro, seguradoras parceiras, atendimento no sinistro. O cliente pede cotação.', E'Seguro auto\nSeguro residencial\nSeguro de vida\nPlano de saúde'),
('Despachante e Documentação', 'documentacao', 'Serviços Profissionais', 'contato', 'Falar no WhatsApp', 'Documentos: certidões, regularizações, agilidade.', E'Certidões\nRegularização\nPassaporte\nApostilamento'),
('Tradução e Revisão', 'traducao', 'Serviços Profissionais', 'orcamento', 'Pedir orçamento', 'Tradução: idiomas, juramentada, prazos. O cliente envia o documento para orçamento.', E'Tradução juramentada\nTradução técnica\nRevisão de texto'),
('Recursos Humanos e Recrutamento', 'rh', 'Serviços Profissionais', 'orcamento', 'Solicitar proposta', 'RH: recrutamento e seleção, treinamentos, terceirização.', E'Recrutamento\nTreinamento\nTerceirização'),
('Coaching e Mentoria', 'coaching', 'Serviços Profissionais', 'agendamento', 'Agendar sessão', 'Coaching/mentoria: método, resultados, primeira sessão.', E'Sessão individual\nMentoria em grupo\nProcesso completo'),
('Assessoria de Imprensa e Comunicação', 'comunicacao', 'Serviços Profissionais', 'orcamento', 'Solicitar proposta', 'Comunicação: clientes atendidos, cases, planos.', E'Assessoria de imprensa\nRedes sociais\nProdução de conteúdo'),
('Cartório e Serviços Notariais', 'cartorio', 'Serviços Profissionais', 'contato', 'Falar conosco', 'Cartório: serviços, documentos necessários, horário e localização.', E'Reconhecimento de firma\nEscritura\nProcuração'),
('Gráfica e Comunicação Visual', 'grafica', 'Serviços Profissionais', 'orcamento', 'Pedir orçamento', 'Gráfica: produtos, prazos, arte inclusa. O cliente informa quantidade e material.', E'Cartão de visita\nBanner\nPanfletos\nAdesivos\nFachada'),
('Costura e Ateliê', 'costura', 'Serviços Profissionais', 'orcamento', 'Pedir orçamento', 'Ateliê: ajustes, roupas sob medida, uniformes. Portfólio e prazos.', E'Ajuste de roupa\nRoupa sob medida\nUniformes\nVestido de festa'),
('Assistência Técnica de Celular e Informática', 'assistencia-tecnica', 'Serviços Profissionais', 'orcamento', 'Pedir orçamento', 'Assistência técnica: conserto rápido, garantia, marcas atendidas. O cliente informa aparelho e defeito.', E'Troca de tela\nBateria\nFormatação\nRecuperação de dados'),
('Conserto de Eletrodomésticos', 'eletrodomesticos', 'Serviços Profissionais', 'orcamento', 'Pedir orçamento', 'Conserto: máquinas de lavar, geladeiras, micro-ondas. Atendimento a domicílio.', E'Máquina de lavar\nGeladeira\nMicro-ondas\nFogão'),
('Lavanderia', 'lavanderia', 'Serviços Profissionais', 'catalogo', 'Pedir coleta', 'Lavanderia: preços por peça/kg, coleta e entrega, prazos.', E'Lavar e passar (kg)\nEdredom\nTerno\nTênis'),
('Desenvolvimento de Sites e Apps', 'sites-apps', 'Tecnologia e Marketing', 'orcamento', 'Pedir orçamento', 'Criação de sites/apps: portfólio, processo, tecnologia, prazos e planos. O cliente descreve o projeto.', E'Site institucional\nLoja virtual\nAplicativo\nLanding page\nManutenção'),
('Social Media', 'social-media', 'Tecnologia e Marketing', 'orcamento', 'Solicitar proposta', 'Gestão de redes: resultados de clientes, planos mensais, o que está incluso.', E'Gestão de Instagram\nCriação de posts\nReels\nTráfego pago'),
('Design Gráfico', 'design', 'Tecnologia e Marketing', 'orcamento', 'Pedir orçamento', 'Design: portfólio forte, processo, identidade visual.', E'Logotipo\nIdentidade visual\nArtes para redes\nEmbalagens'),
('Provedor de Internet', 'provedor', 'Tecnologia e Marketing', 'contato', 'Ver planos', 'Internet: planos com velocidade e preço, cobertura, suporte. O visitante consulta disponibilidade no endereço.', E'Plano 300 mega\nPlano 600 mega\nPlano 1 giga\nEmpresarial'),
('Suporte de TI para Empresas', 'suporte-ti', 'Tecnologia e Marketing', 'orcamento', 'Solicitar proposta', 'TI: suporte, redes, backup, segurança, contratos mensais.', E'Suporte mensal\nRedes\nBackup\nServidores'),
('Escola e Colégio', 'escola', 'Educação', 'contato', 'Agendar visita', 'Escola: proposta pedagógica, estrutura, segmentos, matrículas abertas e visita.', E'Educação infantil\nEnsino fundamental\nEnsino médio\nPeríodo integral'),
('Escola de Idiomas', 'idiomas', 'Educação', 'contato', 'Aula experimental grátis', 'Idiomas: método, níveis, online/presencial, turmas e aula experimental.', E'Inglês\nEspanhol\nAula particular\nCurso online'),
('Reforço Escolar e Professor Particular', 'reforco', 'Educação', 'agendamento', 'Agendar aula', 'Reforço: matérias, níveis, aulas em casa ou online, resultados.', E'Reforço escolar\nMatemática\nRedação\nPreparatório ENEM'),
('Escola de Música', 'escola-musica', 'Educação', 'agendamento', 'Agendar aula experimental', 'Música: instrumentos, professores, idades, aula experimental.', E'Violão\nTeclado\nCanto\nBateria'),
('Curso Profissionalizante', 'curso-profissionalizante', 'Educação', 'contato', 'Quero me inscrever', 'Cursos: o que aprende, certificado, mercado de trabalho, turmas e formas de pagamento.', E'Informática\nAdministração\nCabeleireiro\nEletricista'),
('Infoproduto e Curso Online', 'curso-online', 'Educação', 'contato', 'Quero participar', 'Curso online/infoproduto: transformação prometida (sem exageros), conteúdo, bônus, garantia, depoimentos.', E'Curso completo\nMentoria\nComunidade\nE-book'),
('Berçário e Creche', 'creche', 'Educação', 'contato', 'Agendar visita', 'Creche: segurança, carinho, rotina, alimentação, estrutura e visita.', E'Berçário\nMaternal\nPeríodo integral\nMeio período'),
('Agência de Viagens', 'agencia-viagens', 'Turismo e Hospedagem', 'orcamento', 'Montar minha viagem', 'Viagens: destinos, pacotes, facilidades de pagamento. O cliente informa destino, datas e pessoas.', E'Pacote nacional\nPacote internacional\nCruzeiro\nLua de mel'),
('Passeios e Turismo Receptivo', 'passeios', 'Turismo e Hospedagem', 'agendamento', 'Reservar passeio', 'Passeios: roteiros com fotos, duração, o que inclui, preço por pessoa.', E'Passeio de barco\nCity tour\nTrilha\nTransfer'),
('Camping e Chalés', 'chales', 'Turismo e Hospedagem', 'contato', 'Consultar disponibilidade', 'Chalés/camping: natureza, estrutura, fotos e datas.', E'Chalé casal\nChalé família\nÁrea de camping'),
('Transfer e Transporte Executivo', 'transfer', 'Turismo e Hospedagem', 'orcamento', 'Pedir orçamento', 'Transporte: frota, conforto, pontualidade, aeroporto e eventos.', E'Transfer aeroporto\nViagens\nEventos\nVan para grupos'),
('Loja de Roupas e Moda', 'moda', 'Comércio e Lojas', 'catalogo', 'Comprar pelo WhatsApp', 'Moda: coleção com fotos e preços, tamanhos, entrega e trocas. Visual com cara de vitrine.', E'Vestidos\nCalças\nBlusas\nModa praia\nAcessórios'),
('Moda Fitness', 'moda-fitness', 'Comércio e Lojas', 'catalogo', 'Comprar pelo WhatsApp', 'Moda fitness: conjuntos, tamanhos, tecidos, entrega.', E'Conjunto fitness\nLegging\nTop\nShort'),
('Calçados', 'calcados', 'Comércio e Lojas', 'catalogo', 'Comprar pelo WhatsApp', 'Calçados: modelos, numeração, preço e entrega.', E'Tênis\nSandálias\nBotas\nSapatos sociais'),
('Joias, Semijoias e Relojoaria', 'joias', 'Comércio e Lojas', 'catalogo', 'Comprar pelo WhatsApp', 'Joias/semijoias: fotos detalhadas, garantia, presentes.', E'Anéis\nColares\nBrincos\nAlianças\nRelógios'),
('Cosméticos e Perfumaria', 'cosmeticos', 'Comércio e Lojas', 'catalogo', 'Comprar pelo WhatsApp', 'Beleza: produtos com preço, marcas, kits para presente.', E'Perfumes\nMaquiagem\nSkincare\nKits presente'),
('Floricultura', 'floricultura', 'Comércio e Lojas', 'catalogo', 'Encomendar flores', 'Flores: buquês e arranjos com preço, entrega no mesmo dia, datas especiais.', E'Buquê de rosas\nArranjo\nCesta de café da manhã\nCoroa de flores'),
('Pet Shop (Produtos)', 'petshop-produtos', 'Comércio e Lojas', 'catalogo', 'Pedir pelo WhatsApp', 'Produtos pet: rações, acessórios, entrega rápida.', E'Ração\nPetiscos\nBrinquedos\nCaminhas'),
('Móveis e Decoração', 'moveis', 'Comércio e Lojas', 'catalogo', 'Comprar pelo WhatsApp', 'Móveis/decoração: ambientes com fotos, preços, entrega e montagem.', E'Sofás\nMesas\nDecoração\nColchões'),
('Eletrônicos e Celulares', 'eletronicos', 'Comércio e Lojas', 'catalogo', 'Comprar pelo WhatsApp', 'Eletrônicos: produtos com preço, garantia, parcelamento.', E'Celulares\nAcessórios\nFones\nCapinhas'),
('Papelaria e Presentes', 'presentes', 'Comércio e Lojas', 'catalogo', 'Comprar pelo WhatsApp', 'Presentes/papelaria: produtos, personalizados, datas especiais.', E'Material escolar\nPresentes\nPersonalizados'),
('Produtos Naturais e Suplementos', 'suplementos', 'Comércio e Lojas', 'catalogo', 'Comprar pelo WhatsApp', 'Naturais/suplementos: produtos, benefícios sem promessas, entrega.', E'Whey protein\nVitaminas\nGranel\nChás'),
('Artesanato', 'artesanato', 'Comércio e Lojas', 'catalogo', 'Encomendar', 'Artesanato: peças únicas com fotos, encomendas personalizadas, envio.', E'Peças decorativas\nCrochê\nPersonalizados\nEncomendas'),
('Brechó', 'brecho', 'Comércio e Lojas', 'catalogo', 'Comprar pelo WhatsApp', 'Brechó: peças com fotos, tamanho e preço, novidades da semana.', E'Roupas\nBolsas\nCalçados'),
('Gás e Água', 'gas-agua', 'Comércio e Lojas', 'catalogo', 'Pedir agora', 'Entrega de gás e água: rapidez, preços, horário.', E'Botijão de gás\nGalão de água 20L'),
('Atacado e Distribuidora', 'atacado', 'Comércio e Lojas', 'contato', 'Falar com vendedor', 'Atacado: linhas de produtos, pedido mínimo, entrega para comércios.', E'Catálogo atacado\nRevenda\nEntrega'),
('Administração de Condomínios', 'condominios', 'Imóveis e Condomínios', 'orcamento', 'Solicitar proposta', 'Administração condominial: transparência, prestação de contas, síndico profissional.', E'Administração\nSíndico profissional\nCobrança'),
('Construtora e Incorporadora', 'construtora', 'Imóveis e Condomínios', 'contato', 'Falar com corretor', 'Empreendimentos: plantas, localização, condições de pagamento, obra em andamento.', E'Apartamentos\nCasas\nLotes'),
('Loteamento', 'loteamento', 'Imóveis e Condomínios', 'contato', 'Falar com corretor', 'Lotes: localização, infraestrutura, condições de pagamento e mapa.', E'Lotes residenciais\nLotes comerciais'),
('Igreja e Comunidade Religiosa', 'igreja', 'Religião, Causas e Comunidade', 'contato', 'Fale conosco', 'Igreja: acolhimento, horários de cultos/missas, ministérios, endereço e transmissões.', E'Cultos\nCélulas\nMinistério infantil\nEventos'),
('ONG e Projeto Social', 'ong', 'Religião, Causas e Comunidade', 'contato', 'Quero ajudar', 'ONG: causa, impacto, transparência, como doar e ser voluntário.', E'Doação\nVoluntariado\nProjetos'),
('Associação e Clube', 'associacao', 'Religião, Causas e Comunidade', 'contato', 'Quero me associar', 'Associação/clube: benefícios, estrutura, eventos e mensalidade.', E'Associação\nEventos\nEspaço do clube'),
('Político e Candidato', 'politico', 'Religião, Causas e Comunidade', 'contato', 'Fale comigo', 'Mandato/candidatura: propostas, trajetória, ações, contato com o eleitor.', E'Propostas\nAgenda\nPrestação de contas'),
('Agronegócio e Produtor Rural', 'agro', 'Agro e Indústria', 'contato', 'Falar no WhatsApp', 'Agro: produção, qualidade, venda para comércio, logística.', E'Venda de produção\nInsumos\nConsultoria agrícola'),
('Indústria e Fábrica', 'industria', 'Agro e Indústria', 'orcamento', 'Solicitar orçamento', 'Indústria: produtos, capacidade produtiva, certificações, clientes atendidos.', E'Produção sob encomenda\nLinha de produtos\nRevenda'),
('Transportadora e Logística', 'transportadora', 'Agro e Indústria', 'orcamento', 'Fazer cotação', 'Logística: rotas atendidas, frota, rastreio, cotação de frete.', E'Frete fracionado\nCarga fechada\nArmazenagem'),
('Portfólio Pessoal', 'portfolio', 'Pessoal e Criativo', 'contato', 'Vamos conversar', 'Portfólio: trabalhos em destaque, sobre a pessoa, habilidades e contato.', E'Projetos\nSobre mim\nContato'),
('Artista e Influenciador', 'artista', 'Pessoal e Criativo', 'contato', 'Contratar / parcerias', 'Artista/influenciador: mídia kit, números verdadeiros, parcerias, agenda de shows.', E'Shows\nPublis\nParcerias'),
('Escritor e Autor', 'escritor', 'Pessoal e Criativo', 'catalogo', 'Comprar o livro', 'Autor: livros com sinopse e onde comprar, biografia, eventos.', E'Livro\nE-book\nPalestra'),
('Palestrante', 'palestrante', 'Pessoal e Criativo', 'orcamento', 'Contratar palestra', 'Palestras: temas, vídeos, empresas atendidas. A empresa informa data e público.', E'Palestra presencial\nPalestra online\nWorkshop')
ON CONFLICT (slug) DO UPDATE SET
    grupo = COALESCE(segmentos.grupo, EXCLUDED.grupo),
    modo_atendimento = COALESCE(segmentos.modo_atendimento, EXCLUDED.modo_atendimento),
    cta = COALESCE(segmentos.cta, EXCLUDED.cta),
    descricao_ia = COALESCE(segmentos.descricao_ia, EXCLUDED.descricao_ia),
    exemplos_servicos = COALESCE(segmentos.exemplos_servicos, EXCLUDED.exemplos_servicos);
