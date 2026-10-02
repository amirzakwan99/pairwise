import { BrowserRouter, Link, NavLink, Navigate, Outlet, Route, Routes, useLocation, useParams } from 'react-router-dom';
import { api } from './api';
import { ErrorNotice } from './components';
import { SessionProvider, useLoad, useSession } from './state';
import type { Data, Group } from './contracts';
import { AuthPage, GroupsPage, NewGroupPage, ProfilePage } from './pages/Account';
import { GroupPage } from './pages/Group';
import { JoinPage } from './pages/Join';
import { ExpenseDetailPage, ExpenseFormPage } from './pages/Expense';

function Layout() {
  const session = useSession();
  const location = useLocation();
  const { data: groups } = useLoad(signal => session.user ? api<Data<Group[]>>('/groups', 'GET', undefined, signal) : Promise.resolve({ data: [] }), [session.revision, session.user?.id]);
  const groupId = location.pathname.match(/^\/groups\/([^/]+)/)?.[1];
  const validGroup = groupId && groupId !== 'new' ? groupId : groups?.data[0]?.id;
  if (session.loading) return <main className="auth-wrap"><p role="status">Opening Pairwise…</p></main>;
  if (session.error) return <main className="auth-wrap"><ErrorNotice error={session.error} /><button onClick={session.retry}>Try again</button></main>;
  if (!session.user) return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  return <div className="app-shell"><aside className="sidebar"><Link className="brand" to="/groups"><span className="brand-mark">↔</span>pairwise<span className="brand-dot">.</span></Link><p className="eyebrow mt-12 mb-4">Your space</p><NavLink to="/groups" end className="side-link">◫ &nbsp; All groups</NavLink><NavLink to="/profile" className="side-link">◎ &nbsp; My account</NavLink><div className="sidebar-groups"><p className="eyebrow">Your groups</p>{groups?.data.map(g => <NavLink key={g.id} to={`/groups/${g.id}`} className="side-link group-side">{g.name}</NavLink>)}<Link className="side-link" to="/groups/new">+ Create a group</Link></div><div className="sidebar-note">Good friends.<br />Clear balances.<span>Shared expenses, one pair at a time.</span></div></aside>
    <div className="workspace"><header className="topbar"><Link to="/groups" className="mobile-brand">↔ pairwise.</Link><span className="desktop-greeting">A little clarity for the things you share.</span><Link to="/profile" className="user-pill">{session.user.name}<span className="avatar small">{session.user.name[0]}</span></Link></header><main className="main-content"><Outlet /></main><footer className="desktop-footer">MYR · Calculated independently between each pair</footer></div>
    <nav className="bottom-nav" aria-label="Mobile navigation"><NavLink to="/groups" end>◫<span>Groups</span></NavLink><Link to={validGroup ? `/groups/${validGroup}/expenses/new` : '/groups/new'}>＋<span>Add</span></Link><Link to={validGroup ? `/groups/${validGroup}?tab=settlements` : '/groups'}>↔<span>Debts</span></Link><NavLink to="/profile">◎<span>Me</span></NavLink></nav>
  </div>;
}

function RedirectToGroup() { const { groupId } = useParams(); return <Navigate to={`/groups/${groupId}?tab=settlements`} replace />; }

export default function App() {
  return <BrowserRouter><SessionProvider><Routes><Route path="/login" element={<AuthPage />} /><Route path="/register" element={<AuthPage register />} /><Route element={<Layout />}><Route path="/groups" element={<GroupsPage />} /><Route path="/groups/new" element={<NewGroupPage />} /><Route path="/join/:token" element={<JoinPage />} /><Route path="/groups/:groupId" element={<GroupPage />} /><Route path="/groups/:groupId/expenses/new" element={<ExpenseFormPage />} /><Route path="/groups/:groupId/expenses/:expenseId" element={<ExpenseDetailPage />} /><Route path="/groups/:groupId/expenses/:expenseId/edit" element={<ExpenseFormPage />} /><Route path="/groups/:groupId/settlements" element={<RedirectToGroup />} /><Route path="/profile" element={<ProfilePage />} /><Route path="/" element={<Navigate to="/groups" replace />} /><Route path="*" element={<div className="card"><h1>Page not found</h1><Link to="/groups">Back to groups</Link></div>} /></Route></Routes></SessionProvider></BrowserRouter>;
}
