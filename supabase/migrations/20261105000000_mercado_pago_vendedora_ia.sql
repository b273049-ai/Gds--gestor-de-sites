-- Integração Mercado Pago na Vendedora IA
-- Pedidos criados pela Vendedora IA (chat das páginas públicas) e pagos via Checkout Pro.

create extension if not exists pgcrypto;

create table if not exists public.pedidos (
  id uuid primary key default gen_random_uuid(),
  site_id bigint not null references public.sites(id) on delete cascade,
  empresa_id bigint references public.empresas(id) on delete set null,
  servico_nome text not null,
  valor numeric(12, 2) not null check (valor > 0),
  nome_cliente text,
  email_cliente text,
  -- pendente | pago | recusado | cancelado | estornado | divergente
  status text not null default 'pendente',
  mp_preference_id text,
  mp_payment_id text,
  mp_status text,
  mp_status_detail text,
  criado_em timestamptz not null default now(),
  atualizado_em timestamptz not null default now()
);

create index if not exists pedidos_site_id_idx on public.pedidos (site_id);
create index if not exists pedidos_empresa_id_idx on public.pedidos (empresa_id);

alter table public.pedidos enable row level security;

-- Escrita somente pelas Edge Functions (service role, que ignora RLS).
-- Leitura liberada para o painel, que hoje usa a chave anon (mesmo padrão das tabelas
-- empresas/sites). Restrinja este policy quando o painel tiver autenticação real.
drop policy if exists "pedidos leitura painel" on public.pedidos;
create policy "pedidos leitura painel" on public.pedidos
  for select using (true);
