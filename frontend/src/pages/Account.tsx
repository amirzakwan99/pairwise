import { useState, type FormEvent } from 'react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { api } from '../api';
import { Avatar, Empty, ErrorNotice, PageHeading, Status } from '../components';
import type { Data, Group, User } from '../contracts';
import { useAction, useLoad, useSession } from '../state';

export function AuthPage({ register = false }: { register?: boolean }) {
  const session = useSession();
  const location = useLocation();
  const action = useAction();
  if (session.loading) return <main className="auth-wrap"><p role="status">Opening Pairwise…</p></main>;
  const from = (location.state as { from?: string } | null)?.from;
  if (session.user) return <Navigate to={from?.startsWith('/groups/') || from === '/profile' ? from : '/groups'} replace/>;
  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const fields = Object.fromEntries(new FormData(event.currentTarget));
    void action.run(async () => {
      const result = await api<Data<User>>(`/auth/${register ? 'register' : 'login'}`, 'POST', fields);
      session.setUser(result.data); session.refresh();
    });
  };
  return <main className="auth-wrap"><div className="auth-story"><Link className="brand" to="/login"><span className="brand-mark">↔</span>pairwise.</Link><p className="eyebrow mt-16">Shared moments. Clear balances.</p><h1>Keep the memories.<br/>Split the expenses.</h1><p>Dinner, a weekend away, the everyday things.<br/>See exactly who owes whom, without the guesswork.</p><div className="story-card"><span>Dinner with friends</span><strong>RM120.00</strong><div className="story-line">Ali <span>→</span> Amir <b>RM40.00</b></div><div className="story-line">Abu <span>→</span> Amir <b>RM40.00</b></div><small>Every pair, clearly explained.</small></div></div>
    <section className="auth-form card"><p className="eyebrow">Welcome {register ? 'aboard' : 'back'}</p><h2>{register ? 'Create your account' : 'Sign in to your space'}</h2><p className="muted mb-6">A simpler way to share expenses with friends.</p><ErrorNotice error={action.error ?? session.error}/><form onSubmit={submit} key={register ? 'register' : 'login'}>{register && <label>Your name<input name="name" autoComplete="name" required maxLength={255}/></label>}<label>Email address<input name="email" type="email" autoComplete="email" required maxLength={255}/></label><label>Password<input name="password" type="password" autoComplete={register ? 'new-password' : 'current-password'} required minLength={register ? 8 : undefined}/></label>{register && <label>Confirm password<input name="password_confirmation" type="password" autoComplete="new-password" required minLength={8}/></label>}<button className="primary w-full" disabled={action.busy}>{action.busy ? 'Please wait…' : register ? 'Create account' : 'Sign in'} <span aria-hidden="true">→</span></button></form><p className="muted mt-6 text-center">{register ? 'Already have an account?' : 'New to Pairwise?'} <Link to={register ? '/login' : '/register'}>{register ? 'Sign in' : 'Create an account'}</Link></p></section>
  </main>;
}

export function GroupsPage() {
  const session = useSession();
  const result = useLoad(signal => api<Data<Group[]>>('/groups', 'GET', undefined, signal), [session.revision]);
  return <><PageHeading eyebrow="All together" title="Your groups"><Link className="primary" to="/groups/new">+ New group</Link></PageHeading><section className="welcome-banner"><div><p className="eyebrow">Make room for the good stuff</p><h2>More sharing.<br/>Less keeping track.</h2><p>Create a group, add your friends, and let every expense find its place.</p></div><div className="banner-art" aria-hidden="true"><span>↔</span><small>Every pair counts.</small></div></section><div className="section-heading"><h2>Shared spaces</h2><span className="muted">{result.data?.data.length ?? 0} groups · MYR</span></div><Status {...result}/>{result.data && (result.data.data.length ? <div className="group-grid">{result.data.data.map((g, i) => <Link key={g.id} className="group-card card" to={`/groups/${g.id}`}><span className={`group-icon tone-${i % 3}`} aria-hidden="true">{['◫', '↗', '⌂'][i % 3]}</span><span className="badge">MYR</span><h2>{g.name}</h2><p className="muted">Shared expenses & pairwise balances</p><div className="group-card-footer">Open group <span aria-hidden="true">→</span></div></Link>)}</div> : <Empty title="Your first shared space"><p>Trips, dinners, housemates. Start with a group.</p><Link className="primary mt-5" to="/groups/new">Create a group</Link></Empty>)}</>;
}

export function NewGroupPage() {
  const [name, setName] = useState('');
  const [memberNames, setMemberNames] = useState<string[]>([]);
  const [memberName, setMemberName] = useState('');
  const [memberError, setMemberError] = useState('');
  const navigate = useNavigate();
  const session = useSession();
  const action = useAction();
  const namesWithDraft = () => {
    const nextName = memberName.trim();
    if (!nextName) return memberNames;
    if ([session.user!.name, ...memberNames].some(value => value.toLowerCase() === nextName.toLowerCase())) {
      setMemberError('This name is already included in the group.');
      return null;
    }
    setMemberError('');
    return [...memberNames, nextName];
  };
  const addMember = () => {
    const names = namesWithDraft();
    if (names) {
      setMemberNames(names);
      setMemberName('');
    }
  };
  const submit = (event: FormEvent) => {
    event.preventDefault();
    const member_names = namesWithDraft();
    if (!member_names) return;
    void action.run(async () => {
      const result = await api<Data<Group>>('/groups', 'POST', { name, currency: 'MYR', member_names });
      session.refresh(); navigate(`/groups/${result.data.id}`);
    });
  };
  return <div className="form-page">
    <Link to="/groups" className="back">← Your groups</Link>
    <PageHeading eyebrow="Start something shared" title="Create a group"/>
    <section className="card">
      <ErrorNotice error={action.error}/>
      <form onSubmit={submit}>
        <label>Group name<input autoFocus required maxLength={255} value={name} onChange={e => setName(e.target.value)} placeholder="e.g. Langkawi Trip"/></label>
        <label>Member name (optional)<input value={memberName} onChange={e => { setMemberName(e.target.value); setMemberError(''); }} maxLength={255} placeholder="e.g. Ali" aria-describedby="member-names-help" aria-invalid={!!memberError} disabled={action.busy} onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); addMember(); } }}/></label>
        <button type="button" className="secondary mb-4" onClick={addMember} disabled={action.busy || !memberName.trim()}>Add member</button>
        {memberError && <p role="alert" className="muted mb-4">{memberError}</p>}
        {memberNames.length > 0 && <ul className="mb-4" aria-label="Members to add">{memberNames.map(member => <li key={member} className="share-row"><span className="grow break-all">{member}</span><button type="button" className="secondary" aria-label={`Remove ${member}`} disabled={action.busy} onClick={() => { setMemberNames(names => names.filter(value => value !== member)); setMemberError(''); }}>Remove</button></li>)}</ul>}
        <p id="member-names-help" className="muted mb-6">Add members one at a time. You are included automatically. Members do not need accounts or emails.</p>
        <label>Currency<input value="MYR · Malaysian Ringgit" readOnly/></label>
        <p className="muted mb-6">You’ll manage this group and its expenses.</p>
        <button className="primary" disabled={action.busy}>{action.busy ? 'Creating…' : 'Create group'}</button>
      </form>
    </section>
  </div>;
}

export function ProfilePage() {
  const { user, setUser } = useSession();
  const action = useAction();
  const logout = useAction();
  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = event.currentTarget;
    const payload = Object.fromEntries(new FormData(form));
    void action.run(async () => { await api('/auth/password', 'PATCH', payload); form.reset(); }, 'Password changed successfully.');
  };
  return <div className="form-page"><PageHeading eyebrow="Your space" title="My account"/><div className="card flex items-center gap-4 mb-6"><Avatar name={user!.name}/><div><h2>{user!.name}</h2><p className="muted break-all">{user!.email}</p></div></div><section className="card"><h2 className="mb-4">Change password</h2><ErrorNotice error={action.error}/>{action.success && <p role="status" className="success">{action.success}</p>}<form onSubmit={submit}><label>Current password<input name="current_password" type="password" autoComplete="current-password" required/></label><label>New password<input name="password" type="password" autoComplete="new-password" required minLength={8}/></label><label>Confirm new password<input name="password_confirmation" type="password" autoComplete="new-password" required minLength={8}/></label><button className="primary" disabled={action.busy}>{action.busy ? 'Saving…' : 'Change password'}</button></form></section><ErrorNotice error={logout.error}/><button className="secondary mt-6" disabled={logout.busy} onClick={() => void logout.run(async () => { await api('/auth/logout', 'POST'); setUser(null); })}>{logout.busy ? 'Signing out…' : 'Sign out'}</button></div>;
}
