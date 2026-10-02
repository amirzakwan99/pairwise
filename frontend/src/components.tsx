import type { ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { ApiError, rm } from './api';
import type { Settlement, User } from './contracts';

export function ErrorNotice({ error }: { error: Error | null }) {
  if (!error) return null;
  return <div role="alert" className="error"><strong>{error.message}</strong>{error instanceof ApiError && Object.keys(error.errors).length > 0 && <ul>{Object.entries(error.errors).flatMap(([field, messages]) => messages.map(message => <li key={`${field}:${message}`}><span className="capitalize">{field.replaceAll('_', ' ')}</span>: {message}</li>))}</ul>}</div>;
}
export function Status({ loading, error, retry }: { loading: boolean; error: Error | null; retry: () => void }) {
  if (loading) return <div className="card" role="status">Loading your group…</div>;
  if (error) return <div className="card"><ErrorNotice error={error}/><button className="secondary mt-4" onClick={retry}>Try again</button></div>;
  return null;
}
export function Empty({ title, children }: { title: string; children: ReactNode }) {
  return <div className="empty card"><span className="empty-symbol" aria-hidden="true">↔</span><h2>{title}</h2><div className="muted">{children}</div></div>;
}
export function Avatar({ name }: { name: string }) { return <span className="avatar" aria-hidden="true">{name.slice(0, 1).toUpperCase()}</span>; }
export function PageHeading({ eyebrow, title, children }: { eyebrow: string; title: string; children?: ReactNode }) {
  return <header className="page-heading"><div><p className="eyebrow">{eyebrow}</p><h1>{title}</h1></div>{children}</header>;
}
export function Debts({ debts, user, compact = false }: { debts: Settlement[]; user: User; compact?: boolean }) {
  if (!debts.length) return <Empty title="All square">There are no pairwise debts in this group.</Empty>;
  const personal = debts.filter(d => d.from.id === user.id || d.to.id === user.id);
  return <div className="space-y-4">{!compact && personal.length > 0 && <section className="personal card"><h2>Your payments & receivables</h2>{personal.map(d => <p key={`${d.from.id}:${d.to.id}`} className="personal-row">{d.from.id === user.id ? `You owe ${d.to.name}` : `You receive from ${d.from.name}`}<strong>{rm(d.amount)}</strong></p>)}</section>}
    <div className="card divide-y divide-stone-100">{debts.map(d => <div className="debt-row" key={`${d.from.id}:${d.to.id}`}><Avatar name={d.from.name}/><div className="min-w-0"><strong>{d.from.name} <span className="muted">→</span> {d.to.name}</strong><p className="muted text-sm">{d.from.name} pays {d.to.name}</p></div><strong className="money ml-auto">{rm(d.amount)}</strong></div>)}</div></div>;
}
export function BackToGroup({ id }: { id: string }) { return <Link className="back" to={`/groups/${id}`}>← Back to group</Link>; }
