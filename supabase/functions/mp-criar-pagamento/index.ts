// Cria um pedido + preferência do Mercado Pago (Checkout Pro) para um serviço
// escolhido na conversa com a Vendedora IA de um site público.
//
// Quem recebe:
//   - empresa conectada via OAuth (mp-oauth) → o dinheiro cai direto na conta dela;
//     a plataforma retém MP_COMISSAO_PERCENT (marketplace_fee).
//   - empresa não conectada → conta da plataforma (MP_ACCESS_TOKEN), a menos que
//     MP_EXIGIR_CONTA_EMPRESA=true.
//
// Segredos (supabase secrets set ...):
//   MP_ACCESS_TOKEN          (opcional) token da conta da plataforma, usado como fallback
//   MP_COMISSAO_PERCENT      (opcional) comissão da plataforma sobre vendas das empresas, ex.: 10
//   MP_EXIGIR_CONTA_EMPRESA  (opcional) "true" para só vender se a empresa estiver conectada
//   SITE_PUBLIC_URL          (opcional) URL pública do app — usada nas back_urls. Sem ela, usa o Origin.
import { createClient } from 'jsr:@supabase/supabase-js@2';
import { corsHeaders, json } from '../_shared/cors.ts';
import { parsePreco } from '../_shared/preco.ts';
import { MP_API, tokenParaEmpresa } from '../_shared/mercadopago.ts';

function baseUrlPublica(req: Request): string | null {
  const candidata = Deno.env.get('SITE_PUBLIC_URL') || req.headers.get('origin');
  if (!candidata) return null;
  try {
    const url = new URL(candidata);
    if (url.protocol !== 'https:' && url.protocol !== 'http:') return null;
    return `${url.origin}${url.pathname.replace(/\/$/, '')}`;
  } catch {
    return null;
  }
}

function textoCurto(valor: unknown, max: number): string | null {
  if (typeof valor !== 'string') return null;
  const t = valor.trim().slice(0, max);
  return t || null;
}

Deno.serve(async (req) => {
  if (req.method === 'OPTIONS') return new Response('ok', { headers: corsHeaders });
  if (req.method !== 'POST') return json({ erro: 'Método não permitido' }, 405);

  let body: Record<string, unknown>;
  try {
    body = await req.json();
  } catch {
    return json({ erro: 'JSON inválido' }, 400);
  }

  const siteId = Number(body.site_id);
  const servicoIndex = Number(body.servico_index);
  if (!Number.isInteger(siteId) || !Number.isInteger(servicoIndex) || servicoIndex < 0) {
    return json({ erro: 'Parâmetros inválidos' }, 400);
  }
  const nomeCliente = textoCurto(body.nome_cliente, 120);
  const emailBruto = textoCurto(body.email_cliente, 200);
  const emailCliente = emailBruto && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailBruto) ? emailBruto : null;

  const supabase = createClient(
    Deno.env.get('SUPABASE_URL')!,
    Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!,
    { auth: { persistSession: false } },
  );

  const { data: site, error: erroSite } = await supabase
    .from('sites')
    .select('id, empresa_id, status_publicacao, funcionalidades, empresas(nome_negocio)')
    .eq('id', siteId)
    .single();

  if (erroSite || !site) return json({ erro: 'Site não encontrado' }, 404);
  if (site.status_publicacao !== 'Publicado') return json({ erro: 'Site não publicado' }, 403);

  const configs = (site.funcionalidades ?? {}) as Record<string, any>;
  if (configs.vendedora_ia?.ativa === false) return json({ erro: 'Vendas desativadas neste site' }, 403);

  // Preço sempre lido do banco — nunca confiar em valor enviado pelo navegador.
  const servico = Array.isArray(configs.servicos) ? configs.servicos[servicoIndex] : null;
  const valor = servico ? parsePreco(servico.preco) : null;
  if (!servico || !valor) return json({ erro: 'Serviço indisponível para pagamento online' }, 400);

  const base = baseUrlPublica(req);
  if (!base) return json({ erro: 'Origem inválida' }, 400);

  const credencial = await tokenParaEmpresa(supabase, site.empresa_id);
  if (!credencial) return json({ erro: 'Esta empresa ainda não conectou o Mercado Pago.' }, 503);
  const { token: accessToken, recebedor } = credencial;

  const percentual = Math.min(Math.max(Number(Deno.env.get('MP_COMISSAO_PERCENT') || 0), 0), 50);
  const comissao = recebedor === 'empresa' ? Math.round(valor * percentual) / 100 : 0;

  const empresa = Array.isArray(site.empresas) ? site.empresas[0] : site.empresas;
  const nomeNegocio: string = empresa?.nome_negocio || 'GDS';
  const servicoNome = String(servico.nome || 'Serviço').slice(0, 200);

  const { data: pedido, error: erroPedido } = await supabase
    .from('pedidos')
    .insert({
      site_id: site.id,
      empresa_id: site.empresa_id,
      servico_nome: servicoNome,
      valor,
      nome_cliente: nomeCliente,
      email_cliente: emailCliente,
      recebedor,
      comissao,
    })
    .select('id')
    .single();

  if (erroPedido || !pedido) {
    console.error('Erro ao criar pedido', erroPedido);
    return json({ erro: 'Não foi possível registrar o pedido' }, 500);
  }

  const retorno = (status: string) => `${base}/?site=${site.id}&pagamento=${status}&pedido=${pedido.id}`;
  const preferencia: Record<string, unknown> = {
    items: [
      {
        id: `site-${site.id}-servico-${servicoIndex}`,
        title: servicoNome,
        description: String(servico.desc || '').slice(0, 250) || undefined,
        category_id: 'services',
        quantity: 1,
        currency_id: 'BRL',
        unit_price: valor,
      },
    ],
    payer: {
      ...(nomeCliente ? { name: nomeCliente } : {}),
      ...(emailCliente ? { email: emailCliente } : {}),
    },
    external_reference: pedido.id,
    statement_descriptor: nomeNegocio.replace(/[^A-Za-z0-9 ]/g, '').slice(0, 22) || undefined,
    // ?pedido= diz ao webhook com qual token (empresa ou plataforma) consultar o pagamento.
    notification_url: `${Deno.env.get('SUPABASE_URL')}/functions/v1/mp-webhook?pedido=${pedido.id}`,
    ...(comissao > 0 ? { marketplace_fee: comissao } : {}),
    back_urls: {
      success: retorno('sucesso'),
      pending: retorno('pendente'),
      failure: retorno('falha'),
    },
    metadata: { pedido_id: pedido.id, site_id: site.id, origem: 'vendedora_ia', recebedor },
  };
  // auto_return só é aceito pelo Mercado Pago com back_urls https.
  if (base.startsWith('https://')) preferencia.auto_return = 'approved';

  const resp = await fetch(`${MP_API}/checkout/preferences`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${accessToken}`,
      'Content-Type': 'application/json',
      'X-Idempotency-Key': pedido.id,
    },
    body: JSON.stringify(preferencia),
  });
  const mp = await resp.json().catch(() => ({}));

  if (!resp.ok || !mp.init_point) {
    console.error('Erro Mercado Pago', resp.status, mp);
    await supabase.from('pedidos').update({ status: 'cancelado', atualizado_em: new Date().toISOString() }).eq('id', pedido.id);
    return json({ erro: 'Não foi possível iniciar o pagamento' }, 502);
  }

  await supabase
    .from('pedidos')
    .update({ mp_preference_id: mp.id, atualizado_em: new Date().toISOString() })
    .eq('id', pedido.id);

  return json({ pedido_id: pedido.id, init_point: mp.init_point });
});
