import { useState, type FormEvent } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { api, rm, today } from '../api';
import { Avatar, BackToGroup, ErrorNotice, PageHeading, Status } from '../components';
import type { Data, Expense, ExpenseInput, Group, Member } from '../contracts';
import { useAction, useLoad, useSession } from '../state';

export function ExpenseFormPage() {
  const { groupId, expenseId } = useParams();
  const { revision } = useSession();
  const result = useLoad(async signal => {
    const [group, members, expense] = await Promise.all([
      api<Data<Group>>(`/groups/${groupId}`, 'GET', undefined, signal),
      api<Data<Member[]>>(`/groups/${groupId}/members`, 'GET', undefined, signal),
      expenseId ? api<Data<Expense>>(`/groups/${groupId}/expenses/${expenseId}`, 'GET', undefined, signal) : Promise.resolve(undefined),
    ]);
    return { group: group.data, members: members.data, expense: expense?.data };
  }, [groupId, expenseId, revision]);
  return <div className="form-page"><BackToGroup id={groupId!}/><PageHeading eyebrow="The things you share" title={expenseId ? 'Edit expense' : 'Add an expense'}/><Status {...result}/>{result.data && <ExpenseForm key={expenseId ?? groupId} {...result.data}/>}</div>;
}

function ExpenseForm({ group, members, expense }: { group: Group; members: Member[]; expense?: Expense }) {
  const session = useSession();
  const active = members.filter(m => m.active);
  const [description, setDescription] = useState(expense?.description ?? '');
  const [amount, setAmount] = useState(expense?.amount ?? '');
  const [payer, setPayer] = useState(expense ? (active.some(m => m.id === expense.paid_by) ? expense.paid_by : '') : session.user!.id);
  const [date, setDate] = useState(expense?.expense_date ?? today());
  const [notes, setNotes] = useState(expense?.notes ?? '');
  const [type, setType] = useState<'equal' | 'custom'>(expense?.split_type ?? 'equal');
  const [participants, setParticipants] = useState(expense ? expense.splits.filter(s => active.some(m => m.id === s.user_id)).map(s => s.user_id) : active.map(m => m.id));
  const [shares, setShares] = useState<Record<string, string>>(Object.fromEntries(expense?.splits.map(s => [s.user_id, s.amount]) ?? []));
  const action = useAction();
  const navigate = useNavigate();
  const canEdit = group.created_by === session.user!.id && members.some(m => m.id === session.user!.id && m.role === 'owner' && m.active);
  if (!canEdit) return <div className="card"><h2>You cannot edit this expense.</h2><p className="muted">Only the group creator can change expenses.</p></div>;
  const submit = (event: FormEvent) => {
    event.preventDefault();
    const payload: ExpenseInput = { description, amount, paid_by: payer, expense_date: date, notes: notes || null, split_type: type, participant_ids: participants,
      ...(type === 'custom' ? { splits: participants.map(user_id => ({ user_id, amount: shares[user_id] ?? '' })) } : {}) };
    void action.run(async () => {
      await api(`/groups/${group.id}/expenses${expense ? `/${expense.id}` : ''}`, expense ? 'PATCH' : 'POST', payload);
      session.refresh(); navigate(`/groups/${group.id}?tab=history`, { state: { notice: expense ? 'Expense updated successfully.' : 'Expense added successfully.' } });
    });
  };
  return <section className="card"><ErrorNotice error={action.error}/>{expense?.splits.some(s => !active.some(m => m.id === s.user_id)) && <p className="info mb-5">This expense includes former members. Saving replaces the participants with your selected active members. Review the shares before saving.</p>}<form onSubmit={submit}><label>What was it?<input autoFocus value={description} onChange={e => setDescription(e.target.value)} required maxLength={255} placeholder="e.g. Dinner"/></label><div className="form-columns"><label>Amount (RM)<input inputMode="decimal" pattern="[0-9]{1,10}(\.[0-9]{1,2})?" title="A positive amount with up to two decimal places" value={amount} onChange={e => setAmount(e.target.value)} required placeholder="0.00"/></label><label>Date<input type="date" value={date} onChange={e => setDate(e.target.value)} required/></label></div><label>Who paid?<select value={payer} onChange={e => setPayer(e.target.value)} required><option value="" disabled>Choose an active payer</option>{active.map(m => <option key={m.id} value={m.id}>{m.name}{m.id === session.user!.id ? ' (you)' : ''}</option>)}</select></label><fieldset><legend>Split between</legend><p className="muted text-sm mb-3">Select the people who shared this expense. The payer can be excluded.</p><div className="participant-grid">{active.map(m => <label className={`participant-option ${participants.includes(m.id) ? 'checked' : ''}`} key={m.id}><input type="checkbox" checked={participants.includes(m.id)} onChange={e => setParticipants(ids => e.target.checked ? [...ids, m.id] : ids.filter(id => id !== m.id))}/><Avatar name={m.name}/><span>{m.name}</span></label>)}</div></fieldset><fieldset className="mt-6"><legend>Split method</legend><div className="split-options"><label><input type="radio" name="split_type" value="equal" checked={type === 'equal'} onChange={() => setType('equal')}/>Equal</label><label><input type="radio" name="split_type" value="custom" checked={type === 'custom'} onChange={() => setType('custom')}/>Custom amounts</label></div></fieldset>{type === 'equal' ? <p className="info">Shares are calculated on save. Extra cents go to participants in sorted ID order, so the total always matches.</p> : <div className="custom-shares"><p className="muted mb-4">Enter each selected person’s exact share. Zero is allowed; the total must equal the expense.</p>{participants.map(id => <label className="custom-share" key={id}><span>{active.find(m => m.id === id)?.name} (RM)</span><input aria-label={`${active.find(m => m.id === id)?.name} share (RM)`} inputMode="decimal" required pattern="[0-9]{1,10}(\.[0-9]{1,2})?" value={shares[id] ?? ''} onChange={e => setShares(v => ({ ...v, [id]: e.target.value }))} placeholder="0.00"/></label>)}</div>}<label className="mt-6">Notes <span className="muted font-normal">(optional)</span><textarea rows={3} value={notes} maxLength={10000} onChange={e => setNotes(e.target.value)} placeholder="Anything useful to remember"/></label><div className="form-actions"><Link className="secondary" to={`/groups/${group.id}`}>Cancel</Link><button className="primary" disabled={action.busy || participants.length === 0}>{action.busy ? 'Saving…' : expense ? 'Save changes' : 'Save expense'}</button></div>{participants.length === 0 && <p className="error mt-4" role="alert">Select at least one participant.</p>}</form></section>;
}

export function ExpenseDetailPage() {
  const { groupId, expenseId } = useParams();
  const session = useSession();
  const action = useAction();
  const navigate = useNavigate();
  const path = `/groups/${groupId}/expenses/${expenseId}`;
  const result = useLoad(async signal => {
    const [expense, members] = await Promise.all([
      api<Data<Expense>>(path, 'GET', undefined, signal), api<Data<Member[]>>(`/groups/${groupId}/members`, 'GET', undefined, signal),
    ]);
    return { expense: expense.data, members: members.data };
  }, [groupId, expenseId, session.revision]);
  const data = result.data;
  const name = (id: string) => data?.members.find(m => m.id === id)?.name ?? 'Member';
  const canEdit = data && data.members.some(m => m.id === session.user!.id && m.active && m.role === 'owner');
  const remove = () => {
    if (!data || !window.confirm(`Delete ${data.expense.description} — ${rm(data.expense.amount)}? The group settlement will be recalculated.`)) return;
    void action.run(async () => { await api(path, 'DELETE'); session.refresh(); navigate(`/groups/${groupId}?tab=history`, { state: { notice: 'Expense deleted. Balances recalculated.' } }); });
  };
  return <div className="form-page"><BackToGroup id={groupId!}/><PageHeading eyebrow="Every share explained" title={data?.expense.description ?? 'Expense details'}/><Status {...result}/><ErrorNotice error={action.error}/>{data && <><section className="card"><div className="detail-amount">{rm(data.expense.amount)}</div><p className="muted mt-2">Paid by <strong>{name(data.expense.paid_by)}</strong> · {data.expense.expense_date}</p><p className="muted text-sm mt-2">Added by {name(data.expense.created_by)} · {data.expense.split_type} split</p>{data.expense.notes && <p className="info mt-5 whitespace-pre-wrap">{data.expense.notes}</p>}<hr className="my-6 border-stone-200"/><h2 className="mb-4">Participants & saved shares</h2>{data.expense.splits.map(s => <div className="share-row" key={s.user_id}><Avatar name={name(s.user_id)}/><span>{name(s.user_id)}</span><strong className="ml-auto">{rm(s.amount)}</strong></div>)}</section><section className="card mt-6"><h2 className="mb-4">How this expense creates debt</h2>{data.expense.splits.filter(s => s.user_id !== data.expense.paid_by && s.amount !== '0.00').map(s => <p className="explanation-row" key={s.user_id}>{name(s.user_id)} owes {name(data.expense.paid_by)} <strong>{rm(s.amount)}</strong></p>)}{data.expense.splits.every(s => s.user_id === data.expense.paid_by || s.amount === '0.00') && <p className="muted">The payer covered their own share. This expense creates no debt.</p>}<p className="muted text-sm mt-5">These saved shares are combined with other expenses, then opposing debts are netted within each pair.</p></section>{canEdit && <div className="form-actions mt-6"><button className="danger" disabled={action.busy} onClick={remove}>Delete expense</button><Link className="primary" to={`${path.replace(/^\/api/, '')}/edit`}>Edit expense</Link></div>}</>}</div>;
}
