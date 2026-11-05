-- Marketplace Mercado Pago: cada empresa conecta a própria conta (OAuth) e recebe as vendas direto.

-- Credenciais OAuth das empresas. SEM policies: só as Edge Functions (service role) leem/escrevem.
create table if not exists public.empresas_mercadopago (
  empresa_id bigint primary key references public.empresas(id) on delete cascade,
  mp_user_id text not null,
  access_token text not null,
  refresh_token text,
  public_key text,
  live_mode boolean,
  expira_em timestamptz,
  conectado_em timestamptz not null default now(),
  atualizado_em timestamptz not null default now()
);

alter table public.empresas_mercadopago enable row level security;
revoke all on table public.empresas_mercadopago from anon, authenticated;

-- Status público da conexão (sem tokens) para o painel.
create or replace view public.empresas_mp_status as
  select empresa_id, mp_user_id, live_mode, conectado_em
  from public.empresas_mercadopago;
grant select on public.empresas_mp_status to anon, authenticated;

-- Quem recebeu o pagamento e a comissão da plataforma.
alter table public.pedidos
  add column if not exists recebedor text not null default 'plataforma', -- 'empresa' | 'plataforma'
  add column if not exists comissao numeric(12, 2) not null default 0;
