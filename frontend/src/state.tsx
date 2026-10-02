import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';
import { api } from './api';
import type { Data, User } from './contracts';

interface Session {
  user: User | null; loading: boolean; error: Error | null; revision: number;
  setUser: (user: User | null) => void; refresh: () => void; retry: () => void;
}
const SessionContext = createContext<Session | null>(null);

export function SessionProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);
  const [revision, setRevision] = useState(0);
  const [attempt, setAttempt] = useState(0);
  const refresh = useCallback(() => setRevision(v => v + 1), []);
  const retry = useCallback(() => setAttempt(v => v + 1), []);
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true); setError(null);
    api<Data<User>>('/auth/me', 'GET', undefined, controller.signal)
      .then(r => setUser(r.data)).catch((e: Error) => {
        if (controller.signal.aborted) return;
        if ('status' in e && e.status === 401) setUser(null); else setError(e);
      }).finally(() => { if (!controller.signal.aborted) setLoading(false); });
    return () => controller.abort();
  }, [attempt]);
  useEffect(() => {
    const expire = () => setUser(null);
    window.addEventListener('session-expired', expire);
    return () => window.removeEventListener('session-expired', expire);
  }, []);
  return <SessionContext.Provider value={{ user, loading, error, revision, setUser, refresh, retry }}>{children}</SessionContext.Provider>;
}

export function useSession(): Session {
  const value = useContext(SessionContext);
  if (!value) throw new Error('SessionProvider is required');
  return value;
}

export function useLoad<T>(load: (signal: AbortSignal) => Promise<T>, keys: unknown[]) {
  const [data, setData] = useState<T>();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);
  const [attempt, setAttempt] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true); setError(null); setData(undefined);
    load(controller.signal).then(value => { if (!controller.signal.aborted) setData(value); })
      .catch((e: Error) => { if (!controller.signal.aborted) setError(e); })
      .finally(() => { if (!controller.signal.aborted) setLoading(false); });
    return () => controller.abort();
    // Caller supplies every value used by load as an explicit dependency.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...keys, attempt]);
  return { data, loading, error, retry: () => setAttempt(v => v + 1) };
}

export function useAction() {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<Error | null>(null);
  const [success, setSuccess] = useState('');
  const run = async (operation: () => Promise<void>, message = '') => {
    if (busy) return;
    setBusy(true); setError(null); setSuccess('');
    try { await operation(); setSuccess(message); }
    catch (e) { setError(e instanceof Error ? e : new Error('Please try again.')); }
    finally { setBusy(false); }
  };
  return { busy, error, success, run };
}
