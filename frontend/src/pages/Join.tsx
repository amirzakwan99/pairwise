import { useState, type FormEvent } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { api } from '../api';
import { ErrorNotice, PageHeading, Status } from '../components';
import type { Data, Invitation } from '../contracts';
import { useAction, useLoad, useSession } from '../state';

export function JoinPage() {
  const { token } = useParams();
  const session = useSession();
  const navigate = useNavigate();
  const action = useAction();
  const [memberId, setMemberId] = useState('');
  const result = useLoad(signal => api<Data<Invitation>>(`/invitations/${token}`, 'GET', undefined, signal), [token]);
  const submit = (event: FormEvent) => {
    event.preventDefault();
    void action.run(async () => {
      const response = await api<Data<{ group_id: string }>>(`/invitations/${token}/join`, 'POST', { member_id: memberId });
      session.refresh();
      navigate(`/groups/${response.data.group_id}`, { replace: true, state: { notice: 'You joined the group. Your existing expenses and balances are preserved.' } });
    });
  };
  return <div className="form-page"><PageHeading eyebrow="You’re invited" title={result.data ? `Join ${result.data.data.group.name}` : 'Join a group'}/><Status {...result}/><ErrorNotice error={action.error}/>{result.data && <section className="card">
    <p className="muted mb-6">Select your name in this group. It will be linked to your account and its email updated to {session.user!.email}. You can view the group and add or edit expenses.</p>
    {result.data.data.members.length ? <form onSubmit={submit}><label>Your name in this group<select required value={memberId} onChange={e => setMemberId(e.target.value)}><option value="" disabled>Select your name</option>{result.data.data.members.map(member => <option value={member.id} key={member.id}>{member.name}</option>)}</select></label><button className="primary" disabled={action.busy}>{action.busy ? 'Joining…' : 'Join group'}</button></form> : <p>No unclaimed names are available. Ask the creator to add your name or check whether you already joined.</p>}
    <p className="muted text-sm mt-6">If your name is missing, ask the group creator to add it. An expired or revoked invitation requires a new link.</p>
  </section>}</div>;
}
