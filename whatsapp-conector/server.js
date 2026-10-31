// ============================================================
// CONECTOR WHATSAPP DO GDS — grátis, sem Docker e sem banco de dados
//
// Liga números de WhatsApp por QR code (ou código de pareamento) e envia as
// mensagens automáticas da plataforma. Dois jeitos de usar:
//
//  1) PONTE (celular Android ou computador, qualquer hospedagem): defina GDS_SITE e GDS_CHAVE
//     (ou preencha o arquivo ponte.txt). O conector BUSCA as mensagens no site a cada
//     poucos segundos — não precisa de servidor, domínio nem túnel.
//  2) SERVIDOR (app Node.js ou VPS): sem GDS_SITE, ele abre uma porta HTTP com as mesmas
//     rotas da Evolution API; no GDS escolha "Conector GDS em servidor" e cole a URL e a chave.
//
// Variáveis de ambiente (todas opcionais):
//   GDS_SITE   endereço do site do GDS (modo ponte), ex.: https://meusite.com.br
//   GDS_CHAVE  chave do conector mostrada em Admin → WhatsApp (modo ponte)
//   PORT       porta HTTP (padrão 8080; Hostinger/Render/Railway definem sozinhos)
//   API_KEY    chave secreta; se não existir, é gerada e salva em dados/chave.txt
//   DATA_DIR   onde ficam as sessões (padrão ./dados) — NÃO apague: é o "login" dos números
//   INTERVALO  milissegundos mínimos entre mensagens do mesmo número (padrão 1500)
// ============================================================
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import makeWASocket, { useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, Browsers } from '@whiskeysockets/baileys';
import QRCode from 'qrcode';
import pino from 'pino';

const RAIZ = path.dirname(fileURLToPath(import.meta.url));
const DADOS = path.resolve(process.env.DATA_DIR || path.join(RAIZ, 'dados'));
const SESSOES = path.join(DADOS, 'sessoes');
const PORTA = Number(process.env.PORT || 8080);
const INTERVALO = Math.max(500, Number(process.env.INTERVALO || 1500));
fs.mkdirSync(SESSOES, { recursive: true });

function carregarChave() {
    if (process.env.API_KEY && process.env.API_KEY.length >= 12) return process.env.API_KEY;
    const arq = path.join(DADOS, 'chave.txt');
    if (fs.existsSync(arq)) return fs.readFileSync(arq, 'utf8').trim();
    const nova = crypto.randomBytes(24).toString('hex');
    fs.writeFileSync(arq, nova + '\n', { mode: 0o600 });
    return nova;
}
const CHAVE = carregarChave();
const silencioso = pino({ level: 'silent' });
let versaoWa = null;

const log = (...a) => console.log(new Date().toISOString().slice(0, 19), ...a);
const nomeValido = (n) => typeof n === 'string' && /^[a-zA-Z0-9_-]{1,60}$/.test(n);
const pastaDe = (nome) => path.join(SESSOES, nome);
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

// ---------- Instâncias (um número de WhatsApp cada) ----------
const instancias = new Map();

async function iniciar(nome, numeroPareamento = '') {
    let inst = instancias.get(nome);
    if (inst && inst.sock && inst.estado !== 'close') return inst;
    if (!inst) {
        inst = { nome, estado: 'close', qr: null, qrBase64: null, pareamento: null, numeroPareamento: null, dono: null, fila: Promise.resolve(), ultimoEnvio: 0, tentativas: 0, parado: false };
        instancias.set(nome, inst);
    }
    inst.parado = false;
    inst.estado = 'connecting';
    inst.pareamento = null;
    inst.numeroPareamento = numeroPareamento || null;
    const { state, saveCreds } = await useMultiFileAuthState(pastaDe(nome));
    if (!versaoWa) {
        try { versaoWa = (await fetchLatestBaileysVersion()).version; } catch { versaoWa = undefined; }
    }
    const sock = makeWASocket({
        auth: state, version: versaoWa, logger: silencioso, browser: Browsers.ubuntu('GDS'),
        printQRInTerminal: false, markOnlineOnConnect: false, syncFullHistory: false,
    });
    inst.sock = sock;
    sock.ev.on('creds.update', saveCreds);
    sock.ev.on('connection.update', async (u) => {
        if (inst.sock !== sock) return; // evento de um socket antigo
        if (u.qr) {
            inst.qr = u.qr;
            inst.qrBase64 = await QRCode.toDataURL(u.qr, { margin: 1, width: 360 });
            // Pareamento por código (sem câmera): pede quando o socket está pronto para receber o QR
            if (inst.numeroPareamento && !inst.pareamento) {
                try { inst.pareamento = await sock.requestPairingCode(inst.numeroPareamento); } catch (e) { log(nome, 'pareamento falhou:', e.message); }
            }
        }
        if (u.connection === 'open') {
            inst.estado = 'open'; inst.qr = inst.qrBase64 = inst.pareamento = inst.numeroPareamento = null; inst.tentativas = 0;
            inst.dono = sock.user?.id || null;
            log(nome, 'conectado', (inst.dono || '').split(':')[0]);
        }
        if (u.connection === 'close') {
            const codigo = u.lastDisconnect?.error?.output?.statusCode;
            inst.estado = 'close'; inst.sock = null;
            if (inst.parado) return;
            if (codigo === DisconnectReason.loggedOut) {
                // Saiu pelo celular: apaga o login para gerar um QR novo da próxima vez
                log(nome, 'desconectado pelo celular');
                fs.rmSync(pastaDe(nome), { recursive: true, force: true });
                inst.dono = null;
                return;
            }
            const atraso = codigo === DisconnectReason.restartRequired ? 0 : Math.min(60000, 2000 * 2 ** inst.tentativas++);
            log(nome, `conexão caiu (código ${codigo ?? '?'}), tentando de novo em ${atraso / 1000}s`);
            setTimeout(() => { if (!inst.parado) iniciar(nome).catch((e) => log(nome, 'erro ao reconectar:', e.message)); }, atraso);
        }
    });
    return inst;
}

// Espera o QR (ou a conexão) aparecer, para responder já com a imagem
async function aguardarQr(inst, ms = 10000) {
    const fim = Date.now() + ms;
    while (Date.now() < fim) {
        if (inst.estado === 'open' || (inst.qrBase64 && (!inst.numeroPareamento || inst.pareamento))) return;
        await esperar(250);
    }
}

async function encerrar(nome, apagar) {
    const inst = instancias.get(nome);
    if (inst) {
        inst.parado = true;
        if (inst.sock) {
            try { if (apagar) await inst.sock.logout(); } catch { /* já estava fora */ }
            try { inst.sock.end(undefined); } catch { /* ignore */ }
        }
        inst.sock = null; inst.estado = 'close'; inst.dono = null; inst.qr = inst.qrBase64 = null;
    }
    if (apagar) { fs.rmSync(pastaDe(nome), { recursive: true, force: true }); instancias.delete(nome); }
}

// Envio com intervalo mínimo entre mensagens do mesmo número (parece gente, evita bloqueio)
function enviarTexto(inst, numero, texto) {
    const tarefa = inst.fila.then(async () => {
        const espera = inst.ultimoEnvio + INTERVALO + Math.floor(Math.random() * 700) - Date.now();
        if (espera > 0) await esperar(espera);
        if (inst.estado !== 'open' || !inst.sock) throw Object.assign(new Error('O WhatsApp deste número não está conectado.'), { status: 409 });
        // Confere o número no WhatsApp (resolve o 9º dígito dos celulares brasileiros)
        const [achado] = (await inst.sock.onWhatsApp(numero)) || [];
        if (!achado?.exists) throw Object.assign(new Error(`O número ${numero} não tem WhatsApp.`), { status: 400 });
        const r = await inst.sock.sendMessage(achado.jid, { text: texto });
        inst.ultimoEnvio = Date.now();
        return r;
    });
    inst.fila = tarefa.catch(() => {});
    return tarefa;
}

function respostaInstancia(inst) {
    return { instanceName: inst.nome, state: inst.estado, status: inst.estado };
}

// ---------- HTTP (rotas iguais às da Evolution API usadas pelo GDS) ----------
function responder(res, status, dados) {
    const corpo = JSON.stringify(dados);
    res.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store' });
    res.end(corpo);
}
const erro = (res, status, msg) => responder(res, status, { status, error: msg, response: { message: [msg] } });

function lerCorpo(req) {
    return new Promise((ok, falha) => {
        let tam = 0; const partes = [];
        req.on('data', (c) => { tam += c.length; if (tam > 64 * 1024) { falha(Object.assign(new Error('Corpo grande demais.'), { status: 413 })); req.destroy(); } else partes.push(c); });
        req.on('end', () => {
            if (!partes.length) return ok({});
            try { ok(JSON.parse(Buffer.concat(partes).toString('utf8'))); } catch { falha(Object.assign(new Error('JSON inválido.'), { status: 400 })); }
        });
        req.on('error', falha);
    });
}

function autorizado(req) {
    const enviada = Buffer.from(String(req.headers.apikey || ''));
    const certa = Buffer.from(CHAVE);
    return enviada.length === certa.length && crypto.timingSafeEqual(enviada, certa);
}

const servidor = http.createServer(async (req, res) => {
    const url = new URL(req.url, 'http://localhost');
    const partes = url.pathname.split('/').filter(Boolean).map(decodeURIComponent);
    try {
        if (req.method === 'GET' && partes.length === 0) {
            const conectados = [...instancias.values()].filter((i) => i.estado === 'open').length;
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            return res.end(`<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Conector WhatsApp GDS</title>
<body style="font-family:system-ui;max-width:520px;margin:40px auto;padding:0 16px"><h1>✅ Conector WhatsApp GDS no ar</h1>
<p>${conectados} número(s) conectado(s).</p><p>No GDS: <b>Admin → WhatsApp → Conector GDS</b>, cole o endereço desta página e a chave (está no arquivo <code>dados/chave.txt</code> ou no log de quando o conector ligou).</p></body>`);
        }
        if (!autorizado(req)) return erro(res, 401, 'Chave (apikey) inválida.');
        const [grupo, acao, nomeUrl] = partes;

        if (grupo === 'instance' && acao === 'create' && req.method === 'POST') {
            const corpo = await lerCorpo(req);
            const nome = corpo.instanceName;
            if (!nomeValido(nome)) return erro(res, 400, 'Nome de instância inválido (use letras, números, - e _).');
            const inst = await iniciar(nome);
            await aguardarQr(inst);
            return responder(res, 201, { instance: respostaInstancia(inst), hash: { apikey: CHAVE }, qrcode: inst.qrBase64 ? { base64: inst.qrBase64, code: inst.qr, pairingCode: inst.pareamento } : null });
        }
        if (grupo === 'instance' && acao === 'fetchInstances' && req.method === 'GET') {
            const filtro = url.searchParams.get('instanceName');
            const lista = [...instancias.values()].filter((i) => !filtro || i.nome === filtro)
                .map((i) => ({ name: i.nome, connectionStatus: i.estado, ownerJid: i.dono ? i.dono.split(':')[0].split('@')[0] + '@s.whatsapp.net' : null }));
            return responder(res, 200, lista);
        }
        if (!nomeValido(nomeUrl || '')) return erro(res, 404, 'Rota não encontrada.');

        if (grupo === 'instance' && acao === 'connect' && req.method === 'GET') {
            const numero = String(url.searchParams.get('number') || '').replace(/\D/g, '');
            let inst = instancias.get(nomeUrl);
            if (numero && inst && inst.estado !== 'open' && inst.numeroPareamento !== numero) { await encerrar(nomeUrl, false); inst = null; }
            if (!inst || inst.estado === 'close') inst = await iniciar(nomeUrl, numero);
            await aguardarQr(inst);
            return responder(res, 200, { base64: inst.qrBase64, code: inst.qr, pairingCode: inst.pareamento, instance: respostaInstancia(inst) });
        }
        if (grupo === 'instance' && acao === 'connectionState' && req.method === 'GET') {
            const inst = instancias.get(nomeUrl);
            return responder(res, 200, { instance: { instanceName: nomeUrl, state: inst ? inst.estado : 'close' } });
        }
        if (grupo === 'instance' && (acao === 'logout' || acao === 'delete') && req.method === 'DELETE') {
            await encerrar(nomeUrl, true);
            return responder(res, 200, { status: 'SUCCESS', error: false, response: { message: 'Instância desconectada.' } });
        }
        if (grupo === 'message' && acao === 'sendText' && req.method === 'POST') {
            const corpo = await lerCorpo(req);
            const numero = String(corpo.number || '').replace(/\D/g, '');
            const texto = String(corpo.text || '');
            if (numero.length < 10 || !texto.trim()) return erro(res, 400, 'Informe number (com DDI) e text.');
            if (texto.length > 4096) return erro(res, 400, 'Mensagem longa demais (máximo 4096 caracteres).');
            const inst = instancias.get(nomeUrl);
            if (!inst || inst.estado !== 'open') return erro(res, 409, 'O WhatsApp deste número não está conectado.');
            const r = await enviarTexto(inst, numero, texto);
            return responder(res, 201, { key: r?.key || null, status: 'PENDING' });
        }
        return erro(res, 404, 'Rota não encontrada.');
    } catch (e) {
        log('erro:', e.message);
        return erro(res, e.status || 500, e.status ? e.message : 'Erro interno no conector: ' + e.message);
    }
});

// Reconecta sozinho os números que já estavam logados (só os que chegaram a ler o QR)
function jaLogado(nome) {
    try { return !!JSON.parse(fs.readFileSync(path.join(pastaDe(nome), 'creds.json'), 'utf8')).me; } catch { return false; }
}
for (const nome of fs.readdirSync(SESSOES)) {
    if (nomeValido(nome) && jaLogado(nome)) iniciar(nome).catch((e) => log(nome, 'não reconectou:', e.message));
}

// ---------- Modo PONTE: o conector busca o que fazer no site ----------
function lerPonte() {
    let site = process.env.GDS_SITE || '', chave = process.env.GDS_CHAVE || '';
    const arq = path.join(RAIZ, 'ponte.txt');
    if ((!site || !chave) && fs.existsSync(arq)) {
        for (const linha of fs.readFileSync(arq, 'utf8').split(/\r?\n/)) {
            const m = linha.match(/^\s*(SITE|CHAVE)\s*=\s*(\S+)\s*$/i);
            if (!m) continue;
            if (m[1].toUpperCase() === 'SITE') site ||= m[2]; else chave ||= m[2];
        }
    }
    if (!site || !chave || /COLE|SEU-SITE/i.test(site + chave)) return null;
    site = site.replace(/\/+$/, '');
    if (!/^https?:\/\//i.test(site)) site = 'https://' + site;
    return { url: site + '/api/whatsapp_ponte.php', chave };
}

const resultados = [];          // resultados de envio para contar ao site na próxima volta
const removidas = new Set();    // números desconectados, para avisar o site uma vez
let ultimoAviso = '';
const avisar = (msg) => { if (msg !== ultimoAviso) { log(msg); ultimoAviso = msg; } };

async function voltaPonte(p) {
    const estados = [...instancias.values()].map((i) => ({
        instancia: i.nome, estado: i.estado, qr: i.estado !== 'open' ? i.qrBase64 : null, pareamento: i.pareamento,
        numero: i.dono ? i.dono.split(':')[0].split('@')[0] : '',
    }));
    for (const n of removidas) if (!instancias.has(n)) estados.push({ instancia: n, estado: 'close' });
    removidas.clear();
    const lote = resultados.splice(0);
    let r;
    try {
        const resp = await fetch(p.url, {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-GDS-Chave': p.chave },
            body: JSON.stringify({ chave: p.chave, estados, resultados: lote }), signal: AbortSignal.timeout(25000),
        });
        r = await resp.json().catch(() => null);
        if (!resp.ok || !r || !r.ok) { resultados.unshift(...lote); avisar('⚠ O site recusou: ' + (r?.erro || `resposta ${resp.status}`)); return 15; }
    } catch (e) {
        resultados.unshift(...lote);
        avisar('⚠ Sem conexão com o site (' + e.message + '). Tentando de novo…');
        return 10;
    }
    avisar('✅ Conectado ao site. Pode gerar o QR code no painel do GDS.');

    for (const it of r.instancias || []) {
        if (!nomeValido(it.nome)) continue;
        const inst = instancias.get(it.nome);
        if (it.acao === 'conectar') {
            const num = String(it.numero_pareamento || '').replace(/\D/g, '');
            if (inst && inst.estado !== 'open' && num && inst.numeroPareamento !== num) await encerrar(it.nome, false);
            const atual = instancias.get(it.nome);
            if (!atual || atual.estado === 'close' || atual.parado) iniciar(it.nome, num).catch((e) => log(it.nome, 'erro:', e.message));
        } else if (it.acao === 'manter') {
            if ((!inst || inst.estado === 'close') && jaLogado(it.nome)) iniciar(it.nome).catch((e) => log(it.nome, 'erro:', e.message));
        } else if (it.acao === 'parar') {
            if (inst && inst.estado !== 'open' && !inst.parado) await encerrar(it.nome, false);
        } else if (it.acao === 'desconectar') {
            if (inst || fs.existsSync(pastaDe(it.nome))) { await encerrar(it.nome, true); removidas.add(it.nome); log(it.nome, 'desconectado pelo painel'); }
        }
    }
    for (const m of r.mensagens || []) {
        const inst = instancias.get(m.instancia);
        if (!inst) { resultados.push({ id: m.id, ok: false, erro: 'Este número não está ligado no conector.' }); continue; }
        enviarTexto(inst, String(m.numero || '').replace(/\D/g, ''), String(m.texto || ''))
            .then(() => resultados.push({ id: m.id, ok: true }))
            .catch((e) => resultados.push({ id: m.id, ok: false, erro: e.message }));
    }
    return Math.max(2, Math.min(30, Number(r.intervalo) || 4));
}

async function rodarPonte(p) {
    log('Conector WhatsApp GDS no modo ponte →', p.url);
    for (;;) {
        const seg = await voltaPonte(p).catch((e) => { log('erro:', e.message); return 10; });
        await esperar(seg * 1000);
    }
}

const ponte = lerPonte();
if (ponte) {
    rodarPonte(ponte);
} else {
    if (fs.existsSync(path.join(RAIZ, 'ponte.txt'))) log('Dica: para usar no celular ou computador, preencha SITE e CHAVE no arquivo ponte.txt (GDS → Admin → WhatsApp).');
    servidor.listen(PORTA, () => {
        log(`Conector WhatsApp GDS ouvindo na porta ${PORTA}`);
        log(`Chave (cole no GDS): ${CHAVE}`);
    });
}

for (const sinal of ['SIGINT', 'SIGTERM']) {
    process.on(sinal, () => { for (const i of instancias.values()) { i.parado = true; try { i.sock?.end(undefined); } catch { /* ignore */ } } process.exit(0); });
}
