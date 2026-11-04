# Conector WhatsApp do GDS (grátis)

Liga o WhatsApp da plataforma (e o de cada empresa) por **QR code**, igual ao WhatsApp Web,
e envia as mensagens automáticas: agendamento, lembrete, pedido, orçamento...

- **Não tem mensalidade.** Não precisa de Docker, banco de dados nem conta na Meta.
- É um programa pequeno (Node.js) que precisa ficar **ligado o tempo todo** em algum lugar.
- Funciona em qualquer hospedagem: no celular Android ou no computador ele **busca** as mensagens no site.

> ⚠️ Ele não é da Meta (funciona como o WhatsApp Web). Use **um chip só para isso** e envie só
> mensagens que o cliente pediu. Disparo em massa ou propaganda para quem não pediu faz o
> WhatsApp bloquear o número. Para zero risco de bloqueio existe a opção oficial
> "WhatsApp Cloud API (Meta)", que cobra alguns centavos por aviso.

## O jeito mais fácil: no celular Android ou no computador (funciona em QUALQUER hospedagem)
Nesse modo o conector **busca** as mensagens no site a cada poucos segundos. O site não precisa
chamar o conector, então não precisa de outra hospedagem, domínio, porta nem túnel.

1. GDS → **Admin → WhatsApp** → Serviço: **Conector GDS no celular ou computador** → Salvar.
   Aparecem o **endereço do site** e a **chave do conector**.

### Celular Android (um aparelho antigo serve)
Deixe-o no carregador e no Wi-Fi. Pode ser outro celular, não precisa ser o do chip.
1. Instale o **Termux** pelo F-Droid (https://f-droid.org). A versão da Play Store é antiga.
2. No Termux: `pkg update -y && pkg install -y nodejs-lts unzip`
3. Coloque a pasta `whatsapp-conector` no celular. Por exemplo, baixe o zip e rode
   `termux-setup-storage` e depois `cp -r ~/storage/downloads/whatsapp-conector ~/`.
4. `cd ~/whatsapp-conector`, edite o `ponte.txt` com `nano ponte.txt` (SITE e CHAVE) e rode
   `bash ligar-android.sh`.
5. Nas configurações do Android, tire o Termux da **economia de bateria** ("Sem restrições"),
   senão o sistema desliga o conector.

### Computador (Windows)
1. Instale o Node.js (https://nodejs.org, versão LTS).
2. Na pasta `whatsapp-conector`, abra o `ponte.txt` no Bloco de Notas e cole SITE e CHAVE.
3. Dê dois cliques em **`ligar-windows.bat`**. Deixe a janela aberta (pode minimizar).
   Para ligar sozinho com o Windows, coloque um atalho do .bat em `shell:startup`.

### Depois de ligado (celular ou computador)
No Admin aparece **Conector ligado**. Clique em **Gerar QR code**, leia com o WhatsApp do chip
(Aparelhos conectados → Conectar um aparelho) e use **Enviar teste**. As empresas conectam o
número delas pelo painel delas, do mesmo jeito. De carona, o conector também dispara os
lembretes: não precisa do Cron Job.

## Modo servidor: deixar ligado num servidor (escolha UM)
No GDS escolha **Conector GDS em servidor** e cole a URL e a chave. Sem `ponte.txt` preenchido,
o conector abre uma porta HTTP.


### A) Hostinger — app Node.js (planos Business ou Cloud)
1. hPanel → **Sites → Adicionar site → Node.js** (ou "Node.js Apps").
2. Envie esta pasta `whatsapp-conector` (sem a pasta `node_modules`).
3. Arquivo de entrada: `server.js`. Comando de início: `npm start`. Node 20 ou mais novo.
4. Em variáveis de ambiente, crie `API_KEY` com uma senha longa (ex.: 30 letras e números).
5. Abra o endereço do app: deve aparecer **"Conector WhatsApp GDS no ar"**.

### B) Um VPS (Hostinger VPS, Oracle Cloud "Always Free", Contabo...)
```bash
# Ubuntu: instala o Node 22
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo bash - && sudo apt install -y nodejs
cd whatsapp-conector && npm install
sudo npm install -g pm2
API_KEY=troque-por-uma-senha-longa pm2 start server.js --name whatsapp && pm2 save && pm2 startup
```
Ou com Docker: `docker build -t gds-whats . && docker run -d --restart=always -p 8080:8080 -e API_KEY=suasenha -v gdswhats:/app/dados gds-whats`

Coloque um subdomínio com HTTPS na frente (ex.: `whats.seudominio.com.br`) ou use `http://IP:8080`.

### C) Um computador do escritório que fica sempre ligado (Windows, Mac ou Linux)
1. Instale o Node.js (versão LTS) de https://nodejs.org
2. Abra o terminal nesta pasta e rode `npm install` e depois `npm start`.
3. Para o site (na internet) conseguir falar com esse computador, use um túnel grátis da
   Cloudflare: `cloudflared tunnel --url http://localhost:8080`. Ele mostra um endereço
   `https://....trycloudflare.com`. Esse endereço muda quando o túnel reinicia: para um fixo,
   crie um túnel com nome no painel da Cloudflare (também grátis, precisa de um domínio lá).

## Depois de ligado (modo servidor)
1. Copie a **chave**: aparece no log quando o conector liga ("Chave (cole no GDS)") ou no
   arquivo `dados/chave.txt` (se você não definiu `API_KEY`).
2. GDS → **Admin → WhatsApp**: Serviço **Conector GDS em servidor**, endereço, chave, instância
   `gds-plataforma`. Marque "Usar o número da plataforma" e "Deixar cada empresa conectar o
   próprio WhatsApp". Salve.
3. **Gerar QR code** → no celular: WhatsApp → Aparelhos conectados → Conectar um aparelho.
4. **Enviar teste** para o seu número.

## Respostas da prospecção (v18)
O conector agora também **repassa as respostas** que chegam no número da plataforma, para a prospecção do GDS ler
(a IA marca o funil e sugere a resposta). Só o número que o site pedir é ouvido — os números das empresas não.
- **Celular/computador (ponte):** nada a configurar; basta usar esta versão (copie a pasta nova, `npm install`, ligue).
  O login do número continua (a pasta `dados` não muda).
- **Servidor:** defina a variável `WEBHOOK_URL` com o endereço mostrado em Admin → Prospecção → Piloto automático.

## Bom saber
- A pasta `dados/` guarda o login dos números. **Não apague** (senão precisa ler o QR de novo).
  Se o conector reiniciar, os números reconectam sozinhos.
- O celular precisa entrar na internet de vez em quando (pelo menos a cada 14 dias).
- Entre uma mensagem e outra do mesmo número ele espera ~1,5 s (`INTERVALO`), para parecer gente.
- Se sair do WhatsApp Web pelo celular, é só gerar um QR novo no painel.
- Rotas (compatíveis com a Evolution API), sempre com o cabeçalho `apikey`:
  `POST /instance/create`, `GET /instance/connect/:nome[?number=55...]` (código de pareamento),
  `GET /instance/connectionState/:nome`, `GET /instance/fetchInstances`,
  `POST /message/sendText/:nome` `{number, text}`, `DELETE /instance/logout/:nome`.
