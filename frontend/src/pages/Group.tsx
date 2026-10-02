import { useState, type FormEvent } from 'react';
import { Link, useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { api, rm } from '../api';
import { Avatar, Debts, Empty, ErrorNotice, PageHeading, Status } from '../components';
import type { Data, Expense, Group, Member, Settlements, Summary } from '../contracts';
import { useAction, useLoad, useSession } from '../state';

export function GroupPage() {
  const { groupId } = useParams();
  const session = useSession();
  const location = useLocation();
  const notice = (location.state as { notice?: string } | null)?.notice;
  const [params, setParams] = useSearchParams();
  const tab = params.get('tab') ?? 'overview';
  const [sort, setSort] = useState('date');
  const [direction, setDirection] = useState('desc');
  const path = `/groups/${groupId}`;
  const result = useLoad(async signal => {
    const [group, members, expenses, summary, debts] = await Promise.all([
      api<Data<Group>>(path, 'GET', undefined, signal), api<Data<Member[]>>(`${path}/members`, 'GET', undefined, signal),
      api<Data<Expense[]>>(`${path}/expenses?sort=${sort}&direction=${direction}`, 'GET', undefined, signal),
      api<Data<Summary>>(`${path}/summary`, 'GET', undefined, signal), api<Settlements>(`${path}/settlements`, 'GET', undefined, signal),
    ]);
    return { group: group.data, members: members.data, expenses: expenses.data, summary: summary.data, debts: debts.settlements };
  }, [groupId, sort, direction, session.revision]);
  const data = result.data;
  const mine = data?.summary.members.find(m => m.id === session.user!.id);
  const owner = data?.members.some(m => m.id === session.user!.id && m.role === 'owner' && m.active) ?? false;
  return <><Link className="back" to="/groups">← Your groups</Link><PageHeading eyebrow="A shared space" title={data?.group.name ?? 'Your group'}><Link className="primary" to={`${path}/expenses/new`}>+ Add expense</Link></PageHeading><Status {...result}/>{data && <>
    {notice && <p className="success mb-5" role="status">{notice}</p>}<div className="tabs" role="navigation" aria-label="Group sections">{['overview', 'history', 'settlements', 'members'].map(t => <button key={t} className={tab === t ? 'selected' : ''} onClick={() => setParams({ tab: t })}>{t === 'history' ? 'Expenses' : t === 'settlements' ? 'Who owes whom' : t[0].toUpperCase() + t.slice(1)}</button>)}</div>
    {tab === 'overview' && <><div className="stats-grid"><div className="stat-card total"><p>Total shared expenses</p><strong>{rm(data.summary.total_expenses)}</strong><span>{data.expenses.length} expenses · {data.members.filter(m => m.active).length} active members</span></div><div className="stat-card"><p>You owe</p><strong>{rm(mine?.payable_total ?? '0.00')}</strong><span>Payments to your friends</span></div><div className="stat-card"><p>You receive</p><strong className="text-emerald-800">{rm(mine?.receivable_total ?? '0.00')}</strong><span>Payments from your friends</span></div></div><div className="section-heading"><h2>Who owes whom?</h2><button className="text-button" onClick={() => setParams({ tab: 'settlements' })}>View all →</button></div><Debts debts={data.debts} user={session.user!} compact/><p className="calculation-note">↔ Debts are netted within each pair. Payments are never rerouted through another person.</p><div className="section-heading"><h2>Everyone’s position</h2><span className="muted">Including former members</span></div><div className="member-summary-grid">{data.summary.members.map(m => <article className="card" key={m.id}><div className="flex items-center gap-3 mb-4"><Avatar name={m.name}/><h3>{m.name}{m.id === session.user!.id ? ' (you)' : ''}</h3></div><p className="position">{m.net_balance === '0.00' ? 'Net balance RM0.00' : m.net_balance.startsWith('-') ? `Owes ${rm(m.net_balance.slice(1))} net` : `Owed ${rm(m.net_balance)} net`}</p><dl className="totals"><div><dt>Paid</dt><dd>{rm(m.paid_total)}</dd></div><div><dt>Share</dt><dd>{rm(m.share_total)}</dd></div><div><dt>To pay</dt><dd>{rm(m.payable_total)}</dd></div><div><dt>To receive</dt><dd>{rm(m.receivable_total)}</dd></div></dl></article>)}</div></>}
    {tab === 'history' && <><div className="section-heading flex-wrap"><h2>Expense history</h2><div className="sort-controls"><label className="sr-only" htmlFor="sort">Sort expenses</label><select id="sort" value={sort} onChange={e => setSort(e.target.value)}><option value="date">Date</option><option value="amount">Amount</option><option value="description">Description</option></select><label className="sr-only" htmlFor="direction">Sort direction</label><select id="direction" value={direction} onChange={e => setDirection(e.target.value)}><option value="desc">Descending</option><option value="asc">Ascending</option></select></div></div>{data.expenses.length ? <div className="card expense-list">{data.expenses.map(e => <Link className="expense-row" key={e.id} to={`${path}/expenses/${e.id}`}><span className="expense-icon" aria-hidden="true">◫</span><div className="min-w-0"><h3>{e.description}</h3><p className="muted text-sm">{data.members.find(m => m.id === e.paid_by)?.name} paid · {e.splits.length} participants</p><p className="muted text-xs mt-1">{e.expense_date} · {e.split_type}</p></div><strong className="money ml-auto">{rm(e.amount)}</strong><span aria-hidden="true">›</span></Link>)}</div> : <Empty title="No expenses yet"><p>Add the first thing you shared.</p><Link className="primary mt-4" to={`${path}/expenses/new`}>Add expense</Link></Empty>}</>}
    {tab === 'settlements' && <><div className="section-heading"><h2>Who owes whom?</h2><span className="badge">MYR</span></div><p className="muted mb-6">Each payment is calculated from saved expense shares and opposing debts within that pair.</p><Debts debts={data.debts} user={session.user!}/><p className="calculation-note">These are calculated debts. Recording payments is outside this version.</p></>}
    {tab === 'members' && <Members group={data.group} members={data.members} owner={owner}/>}
  </>}</>;
}

function Members({ group, members, owner }: { group: Group; members: Member[]; owner: boolean }) {
  const session = useSession();
  const action = useAction();
  const navigate = useNavigate();
  const path = `/groups/${group.id}`;
  const submitMember = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = event.currentTarget;
    const payload = Object.fromEntries(new FormData(form));
    void action.run(async () => { await api(`${path}/members`, 'POST', payload); form.reset(); session.refresh(); }, 'Member added.');
  };
  const rename = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(event.currentTarget));
    void action.run(async () => { await api(path, 'PATCH', payload); session.refresh(); }, 'Group renamed.');
  };
  return <div className="space-y-6"><ErrorNotice error={action.error}/>{action.success && <p className="success" role="status">{action.success}</p>}<div className="card"><h2 className="mb-4">The people you share with</h2>{members.map(m => <div key={m.id} className="member-row"><Avatar name={m.name}/><div className="min-w-0"><h3>{m.name}{m.id === session.user!.id ? ' (you)' : ''}</h3>{m.email && <p className="muted text-sm break-all">{m.email}</p>}<span className="badge">{m.active ? m.role : 'Former member'}</span>{m.role === 'member' && <span className="badge ml-2">No login needed</span>}</div>{owner && m.active && m.role !== 'owner' && <button className="text-button danger-text ml-auto" disabled={action.busy} onClick={() => {
    if (window.confirm(`Remove ${m.name}? Their historical expenses and debts remain. They cannot be selected for new expenses.`)) void action.run(async () => { await api(`${path}/members/${m.id}`, 'DELETE'); session.refresh(); });
  }}>Remove</button>}</div>)}</div>{owner && <><section className="card"><h2>Add a friend</h2><p className="muted my-3">Enter a name to add a participant. Email is optional contact information. They don’t need an account; you manage the members and expenses.</p><form onSubmit={submitMember}><label>Name<input name="name" required maxLength={255} autoComplete="off" placeholder="e.g. Ali"/></label><label>Email address (optional)<input name="email" type="email" maxLength={255} autoComplete="off"/></label><button className="primary" disabled={action.busy}>Add member</button></form></section><section className="card"><h2 className="mb-4">Group settings</h2><form onSubmit={rename}><label>Group name<input name="name" required defaultValue={group.name} maxLength={255}/></label><button className="secondary" disabled={action.busy}>Rename group</button></form><hr className="my-6 border-stone-200"/><p className="muted mb-4">Deleting the group permanently removes its expenses, shares, and membership history.</p><button className="danger" disabled={action.busy} onClick={() => {
    if (window.confirm(`Permanently delete ${group.name} and all its expenses?`)) void action.run(async () => { await api(path, 'DELETE'); session.refresh(); navigate('/groups'); });
  }}>Delete group</button></section></>}</div>;
}
