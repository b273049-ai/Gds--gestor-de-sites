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
//   WEBHOOK_URL (modo servidor) endereço que recebe as respostas que chegam, no formato da
//              Evolution API (no GDS: Admin → WhatsApp mostra o endereço pronto)
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
let versaoEm = 0;
// Versão do WhatsApp Web: busca de novo a cada 12 h (e quando o WhatsApp recusa a antiga)
async function versaoAtual(forcar = false) {
    if (!forcar && versaoWa && Date.now() - versaoEm < 12 * 3600e3) return versaoWa;
    try { versaoWa = (await fetchLatestBaileysVersion()).version; versaoEm = Date.now(); } catch { /* fica com a que tinha */ }
    return versaoWa || undefined;
}

const VERSAO = '1.3.1';
const INICIO = Date.now();
const NO_ANDROID = String(process.env.PREFIX || '').includes('com.termux') || fs.existsSync('/data/data/com.termux');

// O processo com esse número é mesmo um conector? (no Android o número de um processo morto é reaproveitado
// por outro app; sem essa conferência o conector novo achava que "já tinha um rodando" e não ligava mais)
function ehConector(pid) {
    try { process.kill(pid, 0); } catch { return false; }
    try { return /server\.js|gds-whatsapp|whatsapp-conector/.test(fs.readFileSync(`/proc/${pid}/cmdline`, 'utf8')); }
    catch { return process.platform === 'win32'; } // sem /proc para ler: só confia no Windows (lá não há esse reaproveitamento rápido)
}

// Um conector só por pasta: dois rodando com o mesmo login ficam se derrubando (erro 440)
// Também descobre como o anterior terminou: trava esquecida = foi fechado à força (Android, falta de energia…)
let fimAnterior = null;   // null = primeira vez | { forcado, parado_seg }
function travarProcesso() {
    const arq = path.join(DADOS, 'conector.pid');
    const vivo = path.join(DADOS, 'vivo.txt');
    let ultimoSinal = 0;
    try { ultimoSinal = Number(fs.readFileSync(vivo, 'utf8')) || 0; } catch { /* primeira vez */ }
    let pid = 0;
    try { pid = Number(fs.readFileSync(arq, 'utf8')); } catch { /* sem trava */ }
    if (pid && pid !== process.pid) {
        if (ehConector(pid)) { console.log(`\n⚠ Já tem um conector rodando (processo ${pid}). Feche o outro Termux/janela: dois ao mesmo tempo ficam derrubando a conexão.\n`); process.exit(1); }
        fimAnterior = { forcado: true };
    } else if (ultimoSinal) fimAnterior = { forcado: false };
    if (fimAnterior && ultimoSinal) fimAnterior.parado_seg = Math.max(0, Math.round((Date.now() - ultimoSinal) / 1000));
    fs.writeFileSync(arq, String(process.pid));
    process.on('exit', () => { try { if (Number(fs.readFileSync(arq, 'utf8')) === process.pid) fs.unlinkSync(arq); } catch { /* ignore */ } });
    // "Ainda estou vivo" a cada minuto: no próximo início dá para saber quanto tempo ficou parado
    const marcar = () => { try { fs.writeFileSync(vivo, String(Date.now())); } catch { /* ignore */ } };
    marcar(); setInterval(marcar, 60000).unref();
}
travarProcesso();

// ---------- Histórico para o site (Admin → WhatsApp → Saúde da conexão) ----------
const eventos = [];
function registrar({ em, ...e }) {
    eventos.push({ ...e, em: Math.floor((em || Date.now()) / 1000) });   // em: milissegundos → segundos
    if (eventos.length > 200) eventos.splice(0, eventos.length - 200);
}
if (fimAnterior) registrar({ tipo: 'reiniciou', forcado: fimAnterior.forcado, seg: fimAnterior.parado_seg || 0 });
// Um erro solto não pode derrubar o conector inteiro
process.on('unhandledRejection', (e) => console.log(new Date().toISOString().slice(0, 19), 'erro ignorado:', e?.message || e));
process.on('uncaughtException', (e) => console.log(new Date().toISOString().slice(0, 19), 'erro ignorado:', e?.message || e));

const log = (...a) => console.log(new Date().toISOString().slice(0, 19), ...a);
const nomeValido = (n) => typeof n === 'string' && /^[a-zA-Z0-9_-]{1,60}$/.test(n);
const pastaDe = (nome) => path.join(SESSOES, nome);
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

// ---------- Instâncias (um número de WhatsApp cada) ----------
const instancias = new Map();

async function iniciar(nome, numeroPareamento = '') {
    let inst = instancias.get(nome);
    // Já ligado, ligando ou com religação marcada: não abre uma 2ª conexão (uma derrubaria a outra)
    if (inst && (inst.iniciando || (inst.sock && inst.estado !== 'close') || (inst.religar && !numeroPareamento))) return inst;
    if (!inst) {
        inst = { nome, estado: 'close', qr: null, qrBase64: null, pareamento: null, numeroPareamento: null, dono: null, fila: Promise.resolve(), ultimoEnvio: 0, tentativas: 0, parado: false, ouvir: !!process.env.WEBHOOK_URL, iniciando: false, religar: null, ultimoEvento: Date.now() };
        instancias.set(nome, inst);
    }
    if (inst.religar) { clearTimeout(inst.religar); inst.religar = null; }
    inst.iniciando = true;
    inst.parado = false;
    inst.estado = 'connecting';
    inst.pareamento = null;
    inst.numeroPareamento = numeroPareamento || null;
    let sock;
    try {
        const { state, saveCreds } = await useMultiFileAuthState(pastaDe(nome));
        sock = makeWASocket({
            auth: state, version: await versaoAtual(), logger: silencioso, browser: Browsers.ubuntu('GDS'),
            printQRInTerminal: false, markOnlineOnConnect: false, syncFullHistory: false,
            connectTimeoutMs: 60000, keepAliveIntervalMs: 25000, defaultQueryTimeoutMs: 60000, retryRequestDelayMs: 500,
            getMessage: async () => undefined,
        });
        sock.ev.on('creds.update', saveCreds);
    } catch (e) {
        inst.iniciando = false; inst.estado = 'close';
        agendarReligar(inst, null);
        throw e;
    }
    inst.sock = sock;
    inst.iniciando = false;
    inst.ultimoEvento = Date.now();
    // Respostas que chegam (só nos números que o site pediu para ouvir — o da prospecção)
    sock.ev.on('messages.upsert', ({ messages, type }) => {
        if (inst.sock !== sock || !inst.ouvir || type !== 'notify') return;
        for (const m of messages || []) {
            const r = lerRecebida(m);
            if (r) guardarRecebida(nome, r);
        }
    });
    sock.ev.on('connection.update', async (u) => {
        if (inst.sock !== sock) return; // evento de um socket antigo
        inst.ultimoEvento = Date.now();
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
            inst.confirmarSaida = 0;
            log(nome, 'conectado', (inst.dono || '').split(':')[0]);
            if (inst.caiuEm) {
                registrar({ tipo: 'conectou', inst: nome, seg: Math.round((Date.now() - inst.caiuEm) / 1000), codigo: inst.ultimoCodigo || null });
                inst.caiuEm = null; inst.ultimoRegistro = null;
            }
        }
        if (u.connection === 'close') {
            const codigo = u.lastDisconnect?.error?.output?.statusCode;
            const motivo = String(u.lastDisconnect?.error?.message || '').slice(0, 120);
            const estavaAberta = inst.estado === 'open';
            inst.estado = 'close'; inst.sock = null;
            if (inst.parado) return;
            if (codigo === DisconnectReason.loggedOut) {
                // O aviso de "saiu" às vezes chega numa queda comum: confere uma vez antes de apagar o login
                // (sem login salvo só lendo o QR de novo). Se o WhatsApp repetir, aí sim era saída de verdade.
                if (!inst.confirmarSaida && jaLogado(nome)) {
                    inst.confirmarSaida = 1;
                    if (!inst.caiuEm) inst.caiuEm = Date.now();
                    inst.ultimoCodigo = codigo;
                    registrar({ tipo: 'caiu', inst: nome, codigo, det: motivo });
                    log(nome, 'o WhatsApp avisou saída; conferindo de novo antes de apagar o login…');
                    if (!inst.religar) inst.religar = setTimeout(() => { inst.religar = null; if (!inst.parado) iniciar(nome).catch((e) => log(nome, 'erro ao religar:', e.message)); }, 5000);
                    return;
                }
                // Saiu pelo celular: apaga o login para gerar um QR novo da próxima vez
                log(nome, 'desconectado pelo celular (o WhatsApp confirmou a saída)');
                registrar({ tipo: 'deslogado', inst: nome, codigo, det: motivo });
                fs.rmSync(pastaDe(nome), { recursive: true, force: true });
                inst.dono = null; inst.confirmarSaida = 0; inst.caiuEm = null;
                return;
            }
            if (codigo !== DisconnectReason.restartRequired) {   // 515 = reinício normal logo depois de ler o QR
                const tipo = codigo === DisconnectReason.connectionReplaced ? 'substituido' : codigo === 403 ? 'bloqueado' : 'caiu';
                // Uma queda vira um registro (as tentativas seguintes não enchem o histórico)
                if (estavaAberta || !inst.caiuEm || (tipo !== 'caiu' && inst.ultimoRegistro !== tipo)) registrar({ tipo, inst: nome, codigo, det: motivo });
                inst.ultimoRegistro = tipo;
                if (!inst.caiuEm) inst.caiuEm = Date.now();
                inst.ultimoCodigo = codigo;
            }
            if (codigo === 405) await versaoAtual(true); // versão do WhatsApp Web velha: pega a nova
            if (codigo === DisconnectReason.connectionReplaced) log(nome, '⚠ Outro aparelho/conector abriu este mesmo número. Feche o outro (só um conector por número).');
            if (codigo === 403) log(nome, '⚠ O WhatsApp recusou este número (403). Veja no celular do chip se há aviso de restrição.');
            agendarReligar(inst, codigo);
        }
    });
    return inst;
}

// Religa sozinho, cada vez esperando um pouco mais (2 s, 4 s, 8 s, 16 s… até 30 s); nunca desiste
function agendarReligar(inst, codigo) {
    if (inst.parado || inst.religar) return;
    let atraso = codigo === DisconnectReason.restartRequired ? 500 : Math.min(30000, 2000 * 2 ** Math.min(inst.tentativas++, 4));
    if (codigo === DisconnectReason.connectionReplaced) atraso = Math.max(atraso, 30000);
    if (codigo === 403) atraso = Math.max(atraso, 300000);   // número recusado: insistir rápido só piora
    log(inst.nome, `conexão caiu (código ${codigo ?? '?'}), religando em ${Math.round(atraso / 1000)}s`);
    inst.religar = setTimeout(() => {
        inst.religar = null;
        if (!inst.parado) iniciar(inst.nome).catch((e) => log(inst.nome, 'erro ao religar:', e.message));
    }, atraso);
}

// Vigia: conexão "aberta" mas com o socket morto (comum depois do celular dormir) → religa
setInterval(() => {
    for (const inst of instancias.values()) {
        if (inst.parado || inst.iniciando) continue;
        const ws = inst.sock?.ws;
        const morto = inst.estado === 'open' && ws && (ws.isClosed || ws.isClosing || (typeof ws.readyState === 'number' && ws.readyState > 1));
        const travado = inst.estado === 'connecting' && inst.sock && Date.now() - inst.ultimoEvento > 120000 && jaLogado(inst.nome);
        if (morto || travado) {
            log(inst.nome, morto ? 'a conexão morreu sem avisar; religando' : 'ficou travado conectando; religando');
            registrar({ tipo: morto ? 'morto' : 'travado', inst: inst.nome });
            if (!inst.caiuEm) inst.caiuEm = Date.now();
            const s = inst.sock; inst.sock = null; inst.estado = 'close';
            try { s.end(undefined); } catch { /* ignore */ }
            agendarReligar(inst, null);
        }
        // Celular voltou da soneca: se ficou fechado e tem login, religa já
        if (inst.estado === 'close' && !inst.sock && !inst.religar && jaLogado(inst.nome)) agendarReligar(inst, null);
    }
}, 30000).unref();

// ---------- Mensagens recebidas ----------
const recebidas = [];   // vão para o site na próxima volta da ponte (ou para o WEBHOOK_URL)
function textoDa(msg) {
    let c = msg || {};
    for (let i = 0; i < 3; i++) c = c.ephemeralMessage?.message || c.viewOnceMessage?.message || c.viewOnceMessageV2?.message || c.documentWithCaptionMessage?.message || c;
    return c.conversation || c.extendedTextMessage?.text || c.imageMessage?.caption || c.videoMessage?.caption
        || c.buttonsResponseMessage?.selectedDisplayText || c.listResponseMessage?.title || c.templateButtonReplyMessage?.selectedDisplayText
        || (c.audioMessage ? '[áudio]' : c.imageMessage ? '[imagem]' : c.videoMessage ? '[vídeo]' : c.stickerMessage ? '[figurinha]' : c.documentMessage ? '[documento]'
            : c.contactMessage ? '[contato]' : c.locationMessage ? '[localização]' : c.reactionMessage ? '' : '');
}
function lerRecebida(m) {
    const k = m?.key || {};
    if (k.fromMe) return null;
    let jid = String(k.remoteJid || '');
    if (!jid || jid.endsWith('@g.us') || jid.endsWith('@broadcast') || jid.endsWith('@newsletter') || jid === 'status@broadcast') return null;
    // Contas com "LID" (endereço novo do WhatsApp): o número de verdade vem em remoteJidAlt/senderPn
    if (jid.endsWith('@lid')) jid = String(k.remoteJidAlt || k.senderPn || m.participantPn || '');
    const numero = jid.split('@')[0].split(':')[0].replace(/\D/g, '');
    const texto = String(textoDa(m.message) || '').slice(0, 2000);
    if (numero.length < 10 || !texto.trim()) return null;
    return { numero, texto, nome: String(m.pushName || '').slice(0, 80), id: String(k.id || ''), em: Number(m.messageTimestamp || 0) || Math.floor(Date.now() / 1000) };
}
function guardarRecebida(instancia, r) {
    if (process.env.WEBHOOK_URL && !ponte) {
        // Formato da Evolution API (o GDS e outros sistemas já entendem)
        fetch(process.env.WEBHOOK_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, signal: AbortSignal.timeout(15000),
            body: JSON.stringify({ event: 'messages.upsert', instance: instancia, data: { key: { remoteJid: r.numero + '@s.whatsapp.net', fromMe: false, id: r.id }, pushName: r.nome, message: { conversation: r.texto }, messageTimestamp: r.em } }) })
            .catch((e) => log(instancia, 'webhook falhou:', e.message));
        return;
    }
    recebidas.push({ instancia, ...r });
    if (recebidas.length > 500) recebidas.splice(0, recebidas.length - 500);
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
        if (inst.religar) { clearTimeout(inst.religar); inst.religar = null; }
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
        // Religando? espera até 45 s antes de desistir (a mensagem não se perde: volta para a fila do site)
        for (let i = 0; i < 45 && (inst.estado !== 'open' || !inst.sock) && !inst.parado; i++) await esperar(1000);
        if (inst.estado !== 'open' || !inst.sock) throw Object.assign(new Error('O WhatsApp deste número não está conectado (religando).'), { status: 409, religando: true });
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

// ---------- Verificação de lead para a prospecção ----------
// Pergunta ao WhatsApp, como qualquer contato faria no app: o número existe? É uma conta
// comercial? Que site e descrição a própria empresa publicou no perfil? Qual a foto (logo)?
async function verificarLead(inst, d) {
    const out = { id: d.id, wa: null, erros: [] };
    const numero = String(d.numero || '').replace(/\D/g, '');
    if (!inst || inst.estado !== 'open' || !inst.sock) { out.erros.push('whatsapp da plataforma não conectado'); return out; }
    if (numero.length < 12) { out.erros.push('número inválido'); return out; }
    try {
        const [a] = (await inst.sock.onWhatsApp(numero)) || [];
        out.wa = { existe: !!a?.exists };
        if (a?.exists) {
            try {
                const bp = await inst.sock.getBusinessProfile(a.jid);
                if (bp) Object.assign(out.wa, { comercial: true, sites: bp.website || [], descricao: String(bp.description || '').slice(0, 600), categoria: bp.category || '', email: bp.email || '' });
            } catch { /* conta pessoal */ }
            try { out.wa.foto = await inst.sock.profilePictureUrl(a.jid, 'image'); } catch { /* sem foto pública */ }
        }
    } catch (e) { out.erros.push('whatsapp: ' + e.message); }
    return out;
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
        if (grupo === 'gds' && acao === 'verificar' && req.method === 'POST' && nomeValido(nomeUrl || '')) {
            const corpo = await lerCorpo(req);
            return responder(res, 200, await verificarLead(instancias.get(nomeUrl), corpo));
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
const verificacoes = [];        // leads verificados (prospecção) para contar ao site
let verificando = Promise.resolve();
const removidas = new Set();    // números desconectados, para avisar o site uma vez
let ultimoAviso = '';
const avisar = (msg) => { if (msg !== ultimoAviso) { log(msg); ultimoAviso = msg; } };
let falhaSite = null;           // desde quando o site não responde (vira um registro quando voltar)
function anotarFalhaSite(det) {
    falhaSite ??= { desde: Date.now(), n: 0 };
    falhaSite.n++; falhaSite.det = det;
}
const infoConector = () => ({ versao: VERSAO, inicio: Math.floor(INICIO / 1000), plataforma: NO_ANDROID ? 'android' : process.platform, node: process.version });

async function voltaPonte(p) {
    const estados = [...instancias.values()].map((i) => ({
        instancia: i.nome, estado: i.estado, logado: jaLogado(i.nome), qr: i.estado !== 'open' ? i.qrBase64 : null, pareamento: i.pareamento,
        numero: i.dono ? i.dono.split(':')[0].split('@')[0] : '',
    }));
    for (const n of removidas) if (!instancias.has(n)) estados.push({ instancia: n, estado: 'close' });
    removidas.clear();
    const lote = resultados.splice(0);
    const loteVerif = verificacoes.splice(0);
    const loteRec = recebidas.splice(0, 100);
    // O site esteve fora/lento por um tempo: conta agora que ele voltou a responder
    if (falhaSite && Date.now() - falhaSite.desde >= 20000 && !falhaSite.contado) {
        registrar({ tipo: 'site_falhou', em: falhaSite.desde, seg: Math.round((Date.now() - falhaSite.desde) / 1000), n: falhaSite.n, det: falhaSite.det });
        falhaSite.contado = true;
    }
    const loteEv = eventos.splice(0, 100);
    const devolver = () => { resultados.unshift(...lote); verificacoes.unshift(...loteVerif); recebidas.unshift(...loteRec); eventos.unshift(...loteEv); };
    let r;
    try {
        const resp = await fetch(p.url, {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-GDS-Chave': p.chave },
            body: JSON.stringify({ chave: p.chave, estados, resultados: lote, verificacoes: loteVerif, recebidas: loteRec, eventos: loteEv, info: infoConector() }),
            signal: AbortSignal.timeout(30000),
        });
        r = await resp.json().catch(() => null);
        if (!resp.ok || !r || !r.ok) { devolver(); anotarFalhaSite('o site recusou (resposta ' + resp.status + ')'); avisar('⚠ O site recusou: ' + (r?.erro || `resposta ${resp.status}`)); return 15; }
    } catch (e) {
        devolver();
        const demorou = e?.name === 'TimeoutError' || e?.name === 'AbortError';
        const causa = String(e?.cause?.code || e?.cause?.message || e?.message);
        const semNet = /ENOTFOUND|EAI_AGAIN|ENETUNREACH|EHOSTUNREACH|getaddrinfo/i.test(causa);
        const siteFora = /ECONNREFUSED|ECONNRESET|EPIPE|socket hang up|other side closed/i.test(causa);
        anotarFalhaSite(demorou ? 'o site demorou mais de 30 s para responder' : semNet ? 'sem internet' : siteFora ? 'o site não atendeu' : 'erro: ' + String(e?.message || e).slice(0, 80));
        avisar('⚠ Sem conexão com o site (' + (demorou ? 'demorou demais' : e.message) + '). Tentando de novo…');
        return 10;
    }
    falhaSite = null;
    avisar('✅ Conectado ao site. Pode gerar o QR code no painel do GDS.');
    // Quais números devem repassar as respostas que chegam (o site decide)
    const ouvir = new Set(Array.isArray(r.ouvir) ? r.ouvir : []);
    for (const i of instancias.values()) i.ouvir = ouvir.has(i.nome);

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
            // Só para quem nunca terminou de ler o QR; número já logado que caiu continua religando
            if (inst && inst.estado !== 'open' && !inst.parado && !jaLogado(it.nome)) await encerrar(it.nome, false);
            else if ((!inst || inst.estado === 'close') && jaLogado(it.nome)) iniciar(it.nome).catch((e) => log(it.nome, 'erro:', e.message));
        } else if (it.acao === 'desconectar') {
            if (inst || fs.existsSync(pastaDe(it.nome))) { await encerrar(it.nome, true); removidas.add(it.nome); log(it.nome, 'desconectado pelo painel'); }
        }
    }
    for (const m of r.mensagens || []) {
        const inst = instancias.get(m.instancia);
        if (!inst) { resultados.push({ id: m.id, ok: false, erro: 'Este número não está ligado no conector.' }); continue; }
        enviarTexto(inst, String(m.numero || '').replace(/\D/g, ''), String(m.texto || ''))
            .then(() => resultados.push({ id: m.id, ok: true }))
            .catch((e) => resultados.push({ id: m.id, ok: false, erro: e.message, religando: !!e.religando }));
    }
    // Prospecção: confere os leads um por vez, com pausa entre um e outro
    for (const v of r.verificar || []) {
        verificando = verificando.then(async () => {
            const res = await verificarLead(v.instancia ? instancias.get(v.instancia) : null, v).catch((e) => ({ id: v.id, erros: [e.message] }));
            verificacoes.push(res);
            await esperar(3000 + Math.random() * 3000);
        });
    }
    return Math.max(2, Math.min(30, Number(r.intervalo) || 4));
}

async function rodarPonte(p) {
    log(`Conector WhatsApp GDS ${VERSAO} no modo ponte →`, p.url);
    if (fimAnterior?.forcado) log('⚠ Da última vez o conector foi fechado à força (o Android costuma fazer isso). Veja "Se o WhatsApp cair muito" no LEIA-ME.');
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
