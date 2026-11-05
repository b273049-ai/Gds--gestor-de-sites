// Retorno do OAuth do Mercado Pago: troca o "code" pelo token da empresa e salva.
// Cadastre esta URL como "URL de redirecionamento" na sua aplicação do Mercado Pago:
//   https://<projeto>.supabase.co/functions/v1/mp-oauth-callback
// Secrets: MP_CLIENT_ID, MP_CLIENT_SECRET, SITE_PUBLIC_URL, MP_OAUTH_TEST_TOKEN (opcional "true")
import { createClient } from 'jsr:@supabase/supabase-js@2';
import { comparacaoSegura, deBase64url, hmacSha256Hex, linhaCredencial, redirectUriOAuth, solicitarToken } from '../_shared/mercadopago.ts';

function voltar(resultado: 'ok' | 'erro', motivo?: string) {
  const base = Deno.env.get('SITE_PUBLIC_URL');
  if (!base) {
    return new Response(resultado === 'ok' ? 'Conta Mercado Pago conectada! Pode fechar esta página.' : `Não foi possível conectar: ${motivo ?? ''}`, {
      status: resultado === 'ok' ? 200 : 400,
      headers: { 'Content-Type': 'text/plain; charset=utf-8' },
    });
  }
  const url = new URL(base);
  url.searchParams.set('mp_conectado', resultado);
  if (motivo) url.searchParams.set('motivo', motivo);
  return Response.redirect(url.toString(), 302);
}

Deno.serve(async (req) => {
  const url = new URL(req.url);
  const code = url.searchParams.get('code');
  const state = url.searchParams.get('state') ?? '';
  if (url.searchParams.get('error') || !code) return voltar('erro', 'autorizacao_negada');

  const clientSecret = Deno.env.get('MP_CLIENT_SECRET');
  if (!clientSecret) return voltar('erro', 'servidor_nao_configurado');

  const [payload, assinatura] = state.split('.');
  if (!payload || !assinatura || !comparacaoSegura(await hmacSha256Hex(clientSecret, payload), assinatura)) {
    return voltar('erro', 'link_invalido');
  }
  let empresaId: number;
  try {
    const dados = JSON.parse(deBase64url(payload));
    if (typeof dados.x !== 'number' || dados.x < Date.now()) return voltar('erro', 'link_expirado');
    empresaId = Number(dados.e);
    if (!Number.isInteger(empresaId)) return voltar('erro', 'link_invalido');
  } catch {
    return voltar('erro', 'link_invalido');
  }

  try {
    const token = await solicitarToken({
      grant_type: 'authorization_code',
      code,
      redirect_uri: redirectUriOAuth(),
      ...(Deno.env.get('MP_OAUTH_TEST_TOKEN') === 'true' ? { test_token: 'true' } : {}),
    });
    const supabase = createClient(Deno.env.get('SUPABASE_URL')!, Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!, { auth: { persistSession: false } });
    const { error } = await supabase
      .from('empresas_mercadopago')
      .upsert({ ...linhaCredencial(empresaId, token), conectado_em: new Date().toISOString() });
    if (error) throw error;
  } catch (e) {
    console.error('Erro no OAuth Mercado Pago', e instanceof Error ? e.message : e);
    return voltar('erro', 'falha_ao_obter_token');
  }
  return voltar('ok');
});
