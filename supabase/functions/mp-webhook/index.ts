// Recebe notificações (Webhooks) do Mercado Pago e atualiza o status do pedido.
// Deploy sem verificação de JWT (ver supabase/config.toml).
//
// Segredos necessários:
//   MP_ACCESS_TOKEN     mesmo token usado em mp-criar-pagamento
//   MP_WEBHOOK_SECRET   "Assinatura secreta" em Suas integrações > Webhooks
import { createClient } from 'jsr:@supabase/supabase-js@2';

const MP_API = 'https://api.mercadopago.com';
const encoder = new TextEncoder();

async function hmacSha256Hex(segredo: string, mensagem: string): Promise<string> {
  const chave = await crypto.subtle.importKey('raw', encoder.encode(segredo), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
  const assinatura = await crypto.subtle.sign('HMAC', chave, encoder.encode(mensagem));
  return Array.from(new Uint8Array(assinatura)).map((b) => b.toString(16).padStart(2, '0')).join('');
}

function comparacaoSegura(a: string, b: string): boolean {
  if (a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return diff === 0;
}

// Valida o header x-signature: "ts=<timestamp>,v1=<hmac>"
// manifest = "id:<data.id>;request-id:<x-request-id>;ts:<ts>;"
async function assinaturaValida(req: Request, dataId: string, segredo: string): Promise<boolean> {
  const xSignature = req.headers.get('x-signature');
  if (!xSignature) return false;
  const partes = Object.fromEntries(
    xSignature.split(',').map((p) => {
      const [k, ...v] = p.split('=');
      return [k.trim(), v.join('=').trim()];
    }),
  );
  const ts = partes.ts;
  const v1 = partes.v1;
  if (!ts || !v1) return false;

  const requestId = req.headers.get('x-request-id');
  const id = /^[a-z0-9]+$/i.test(dataId) ? dataId.toLowerCase() : dataId;
  let manifest = `id:${id};`;
  if (requestId) manifest += `request-id:${requestId};`;
  manifest += `ts:${ts};`;

  return comparacaoSegura(await hmacSha256Hex(segredo, manifest), v1.toLowerCase());
}

function statusPedido(mpStatus: string): string {
  switch (mpStatus) {
    case 'approved':
      return 'pago';
    case 'rejected':
      return 'recusado';
    case 'cancelled':
      return 'cancelado';
    case 'refunded':
    case 'charged_back':
      return 'estornado';
    default: // pending, in_process, authorized, in_mediation
      return 'pendente';
  }
}

Deno.serve(async (req) => {
  if (req.method !== 'POST') return new Response('ok');

  const accessToken = Deno.env.get('MP_ACCESS_TOKEN');
  const segredo = Deno.env.get('MP_WEBHOOK_SECRET');
  if (!accessToken || !segredo) {
    console.error('MP_ACCESS_TOKEN ou MP_WEBHOOK_SECRET não configurado');
    return new Response('not configured', { status: 500 });
  }

  const url = new URL(req.url);
  const body = await req.json().catch(() => ({} as Record<string, any>));
  const tipo = body?.type ?? url.searchParams.get('type') ?? url.searchParams.get('topic');
  const dataId = String(url.searchParams.get('data.id') ?? body?.data?.id ?? '');

  // Só nos interessam notificações de pagamento; demais tipos recebem 200 para não serem reenviados.
  if (tipo !== 'payment' || !dataId) return new Response('ignored');

  if (!(await assinaturaValida(req, dataId, segredo))) {
    console.warn('Assinatura inválida no webhook', { dataId });
    return new Response('invalid signature', { status: 401 });
  }

  // Nunca confiar no corpo da notificação: consultar o pagamento na API.
  const resp = await fetch(`${MP_API}/v1/payments/${encodeURIComponent(dataId)}`, {
    headers: { Authorization: `Bearer ${accessToken}` },
  });
  if (!resp.ok) {
    console.error('Erro ao consultar pagamento', resp.status, await resp.text());
    return new Response('retry', { status: 502 }); // MP reenvia a notificação
  }
  const pagamento = await resp.json();
  const pedidoId = pagamento.external_reference;
  if (!pedidoId) return new Response('no reference');

  const supabase = createClient(
    Deno.env.get('SUPABASE_URL')!,
    Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!,
    { auth: { persistSession: false } },
  );

  const { data: pedido } = await supabase.from('pedidos').select('id, valor').eq('id', pedidoId).single();
  if (!pedido) return new Response('unknown order');

  let status = statusPedido(pagamento.status);
  // Proteção extra: valor pago tem que bater com o valor do pedido.
  if (status === 'pago' && Number(pagamento.transaction_amount) + 0.001 < Number(pedido.valor)) {
    status = 'divergente';
  }

  const { error } = await supabase
    .from('pedidos')
    .update({
      status,
      mp_payment_id: String(pagamento.id),
      mp_status: pagamento.status,
      mp_status_detail: pagamento.status_detail,
      atualizado_em: new Date().toISOString(),
    })
    .eq('id', pedidoId);

  if (error) {
    console.error('Erro ao atualizar pedido', error);
    return new Response('retry', { status: 500 });
  }
  return new Response('ok');
});
