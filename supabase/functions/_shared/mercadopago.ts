import type { SupabaseClient } from 'jsr:@supabase/supabase-js@2';

export const MP_API = 'https://api.mercadopago.com';
const encoder = new TextEncoder();

export async function hmacSha256Hex(segredo: string, mensagem: string): Promise<string> {
  const chave = await crypto.subtle.importKey('raw', encoder.encode(segredo), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
  const assinatura = await crypto.subtle.sign('HMAC', chave, encoder.encode(mensagem));
  return Array.from(new Uint8Array(assinatura)).map((b) => b.toString(16).padStart(2, '0')).join('');
}

export function comparacaoSegura(a: string, b: string): boolean {
  if (a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return diff === 0;
}

export function base64url(texto: string): string {
  return btoa(String.fromCharCode(...encoder.encode(texto))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

export function deBase64url(b64: string): string {
  const bin = atob(b64.replace(/-/g, '+').replace(/_/g, '/'));
  return new TextDecoder().decode(Uint8Array.from(bin, (c) => c.charCodeAt(0)));
}

export function redirectUriOAuth(): string {
  return `${Deno.env.get('SUPABASE_URL')}/functions/v1/mp-oauth-callback`;
}

type RespostaToken = {
  access_token: string;
  refresh_token?: string;
  user_id: number | string;
  public_key?: string;
  live_mode?: boolean;
  expires_in?: number;
};

// POST /oauth/token — troca o "code" (ou o refresh_token) por um access_token da empresa.
export async function solicitarToken(params: Record<string, string>): Promise<RespostaToken> {
  const clientId = Deno.env.get('MP_CLIENT_ID');
  const clientSecret = Deno.env.get('MP_CLIENT_SECRET');
  if (!clientId || !clientSecret) throw new Error('MP_CLIENT_ID/MP_CLIENT_SECRET não configurados');
  const resp = await fetch(`${MP_API}/oauth/token`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ client_id: clientId, client_secret: clientSecret, ...params }),
  });
  const dados = await resp.json().catch(() => ({}));
  if (!resp.ok || !dados.access_token) throw new Error(`OAuth MP ${resp.status}: ${dados.message ?? dados.error ?? 'erro'}`);
  return dados;
}

export function linhaCredencial(empresaId: number, t: RespostaToken) {
  return {
    empresa_id: empresaId,
    mp_user_id: String(t.user_id),
    access_token: t.access_token,
    refresh_token: t.refresh_token ?? null,
    public_key: t.public_key ?? null,
    live_mode: t.live_mode ?? null,
    expira_em: t.expires_in ? new Date(Date.now() + t.expires_in * 1000).toISOString() : null,
    atualizado_em: new Date().toISOString(),
  };
}

export type Recebedor = 'empresa' | 'plataforma';

// Token para criar/consultar pagamentos:
//  - empresa conectada via OAuth → token da empresa (renovado automaticamente perto de vencer)
//  - senão → token da plataforma (MP_ACCESS_TOKEN), se existir e MP_EXIGIR_CONTA_EMPRESA != "true"
// `preferido` é usado no webhook para consultar com o mesmo token que criou o pagamento.
export async function tokenParaEmpresa(
  supabase: SupabaseClient,
  empresaId: number | null,
  preferido?: Recebedor,
): Promise<{ token: string; recebedor: Recebedor } | null> {
  if (empresaId && preferido !== 'plataforma') {
    const { data: cred } = await supabase.from('empresas_mercadopago').select('*').eq('empresa_id', empresaId).maybeSingle();
    if (cred) {
      const venceEmBreve = cred.expira_em && new Date(cred.expira_em).getTime() - Date.now() < 7 * 24 * 3600 * 1000;
      if (venceEmBreve && cred.refresh_token) {
        try {
          const novo = await solicitarToken({ grant_type: 'refresh_token', refresh_token: cred.refresh_token });
          await supabase.from('empresas_mercadopago').update(linhaCredencial(empresaId, novo)).eq('empresa_id', empresaId);
          return { token: novo.access_token, recebedor: 'empresa' };
        } catch (e) {
          console.error('Falha ao renovar token da empresa', empresaId, e);
        }
      }
      return { token: cred.access_token, recebedor: 'empresa' };
    }
    if (preferido === 'empresa') return null; // pedido era da empresa, mas a conta foi desconectada
  }
  if (Deno.env.get('MP_EXIGIR_CONTA_EMPRESA') === 'true' && preferido !== 'plataforma') return null;
  const plataforma = Deno.env.get('MP_ACCESS_TOKEN');
  return plataforma ? { token: plataforma, recebedor: 'plataforma' } : null;
}
