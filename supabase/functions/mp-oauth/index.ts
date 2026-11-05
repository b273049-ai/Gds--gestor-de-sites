// Ações de administrador sobre a conexão Mercado Pago de uma empresa.
//   { acao: 'iniciar', empresa_id }     → devolve o link de autorização (enviar ao dono da empresa)
//   { acao: 'desconectar', empresa_id } → apaga as credenciais OAuth da empresa
// Requer header x-gds-admin-key igual ao secret GDS_ADMIN_KEY (a senha digitada no login do painel).
//
// Secrets: MP_CLIENT_ID, MP_CLIENT_SECRET, GDS_ADMIN_KEY
//          MP_AUTH_URL (opcional, padrão https://auth.mercadopago.com.br)
import { createClient } from 'jsr:@supabase/supabase-js@2';
import { corsHeaders, json } from '../_shared/cors.ts';
import { base64url, comparacaoSegura, hmacSha256Hex, redirectUriOAuth } from '../_shared/mercadopago.ts';

const VALIDADE_LINK_MS = 7 * 24 * 3600 * 1000; // o link pode ser enviado ao cliente por WhatsApp

Deno.serve(async (req) => {
  if (req.method === 'OPTIONS') return new Response('ok', { headers: corsHeaders });
  if (req.method !== 'POST') return json({ erro: 'Método não permitido' }, 405);

  const adminKey = Deno.env.get('GDS_ADMIN_KEY');
  const clientId = Deno.env.get('MP_CLIENT_ID');
  const clientSecret = Deno.env.get('MP_CLIENT_SECRET');
  if (!adminKey || !clientId || !clientSecret) return json({ erro: 'Integração Mercado Pago não configurada no servidor' }, 500);
  if (!comparacaoSegura(req.headers.get('x-gds-admin-key') ?? '', adminKey)) return json({ erro: 'Senha de administrador inválida' }, 401);

  const body = await req.json().catch(() => ({}));
  const empresaId = Number(body.empresa_id);
  if (!Number.isInteger(empresaId)) return json({ erro: 'empresa_id inválido' }, 400);

  const supabase = createClient(Deno.env.get('SUPABASE_URL')!, Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!, { auth: { persistSession: false } });
  const { data: empresa } = await supabase.from('empresas').select('id').eq('id', empresaId).maybeSingle();
  if (!empresa) return json({ erro: 'Empresa não encontrada' }, 404);

  if (body.acao === 'desconectar') {
    const { error } = await supabase.from('empresas_mercadopago').delete().eq('empresa_id', empresaId);
    return error ? json({ erro: error.message }, 500) : json({ ok: true });
  }

  if (body.acao === 'iniciar') {
    // "state" assinado: impede trocar a empresa no link ou forjar o retorno.
    const payload = base64url(JSON.stringify({ e: empresaId, x: Date.now() + VALIDADE_LINK_MS, n: crypto.randomUUID() }));
    const state = `${payload}.${await hmacSha256Hex(clientSecret, payload)}`;
    const url = new URL('/authorization', Deno.env.get('MP_AUTH_URL') || 'https://auth.mercadopago.com.br');
    url.searchParams.set('client_id', clientId);
    url.searchParams.set('response_type', 'code');
    url.searchParams.set('platform_id', 'mp');
    url.searchParams.set('state', state);
    url.searchParams.set('redirect_uri', redirectUriOAuth());
    return json({ url: url.toString() });
  }

  return json({ erro: 'Ação inválida' }, 400);
});
