// Recebe notificações (Webhooks) do Mercado Pago e atualiza o status do pedido.
// Deploy sem verificação de JWT (ver supabase/config.toml).
//
// Segredos necessários:
//   MP_WEBHOOK_SECRET   "Assinatura secreta" em Suas integrações > (sua aplicação) > Webhooks
//   MP_ACCESS_TOKEN     token da plataforma (para pedidos recebidos pela plataforma)
// Pedidos de empresas conectadas via OAuth são consultados com o token da própria empresa.
import { createClient } from 'jsr:@supabase/supabase-js@2';
import { comparacaoSegura, hmacSha256Hex, MP_API, tokenParaEmpresa, type Recebedor } from '../_shared/mercadopago.ts';

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

  const segredo = Deno.env.get('MP_WEBHOOK_SECRET');
  if (!segredo) {
    console.error('MP_WEBHOOK_SECRET não configurado');
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

  const supabase = createClient(
    Deno.env.get('SUPABASE_URL')!,
    Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!,
    { auth: { persistSession: false } },
  );

  // O pedido vem na notification_url (?pedido=). Sem ele (notificação configurada no painel),
  // tenta com o token da plataforma.
  const pedidoParam = url.searchParams.get('pedido');
  let pedidoEsperado: { id: string; empresa_id: number | null; recebedor: Recebedor } | null = null;
  if (pedidoParam) {
    const { data } = await supabase.from('pedidos').select('id, empresa_id, recebedor').eq('id', pedidoParam).maybeSingle();
    if (!data) return new Response('unknown order');
    pedidoEsperado = data;
  }

  const credencial = await tokenParaEmpresa(
    supabase,
    pedidoEsperado?.empresa_id ?? null,
    pedidoEsperado?.recebedor ?? 'plataforma',
  );
  if (!credencial) {
    console.error('Sem token para consultar pagamento', { dataId, pedido: pedidoParam });
    return new Response('no credentials');
  }

  // Nunca confiar no corpo da notificação: consultar o pagamento na API.
  const resp = await fetch(`${MP_API}/v1/payments/${encodeURIComponent(dataId)}`, {
    headers: { Authorization: `Bearer ${credencial.token}` },
  });
  if (resp.status === 404) return new Response('payment not found for this account');
  if (!resp.ok) {
    console.error('Erro ao consultar pagamento', resp.status);
    return new Response('retry', { status: 502 }); // MP reenvia a notificação
  }
  const pagamento = await resp.json();
  const pedidoId = pagamento.external_reference;
  if (!pedidoId) return new Response('no reference');
  if (pedidoEsperado && pedidoEsperado.id !== pedidoId) {
    console.warn('external_reference não confere com o pedido da notificação', { pedidoParam, pedidoId });
    return new Response('reference mismatch');
  }

  const { data: pedido } = await supabase.from('pedidos').select('id, valor').eq('id', pedidoId).maybeSingle();
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
