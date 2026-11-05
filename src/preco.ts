// Mantenha igual a supabase/functions/_shared/preco.ts.
// Converte "R$ 1.234,56" / "R$ 45,00" / "45" em número. Retorna null para "Consulte" etc.
export function parsePreco(preco: unknown): number | null {
  if (typeof preco === 'number') return Number.isFinite(preco) && preco > 0 ? Math.round(preco * 100) / 100 : null;
  if (typeof preco !== 'string') return null;
  const limpo = preco.replace(/[^\d,.]/g, '');
  if (!limpo) return null;
  const normalizado = limpo.includes(',') ? limpo.replace(/\./g, '').replace(',', '.') : limpo;
  const valor = Number(normalizado);
  return Number.isFinite(valor) && valor > 0 ? Math.round(valor * 100) / 100 : null;
}
