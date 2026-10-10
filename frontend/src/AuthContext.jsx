import { createContext, useContext, useEffect, useState, useCallback } from 'react';
import { api, getToken, setToken } from './api.js';

const AuthCtx = createContext(null);
export const useAuth = () => useContext(AuthCtx);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [kampus, setKampus] = useState('Universitas Islam Negeri Imam Bonjol Padang');
  const [loading, setLoading] = useState(!!getToken());

  useEffect(() => {
    if (!getToken()) return;
    api.get('/auth/me')
      .then((r) => { setUser(r.user); if (r.kampus) setKampus(r.kampus); })
      .catch(() => setToken(null))
      .finally(() => setLoading(false));
  }, []);

  const login = useCallback(async (email, password, role = 'mahasiswa') => {
    const endpoint = role === 'admin' ? '/auth/admin/login' : '/auth/login';
    const r = await api.post(endpoint, { email, password });
    setToken(r.token);
    setUser(r.user);
    return r.user;
  }, []);

  const logout = useCallback(() => { setToken(null); setUser(null); }, []);

  return <AuthCtx.Provider value={{ user, kampus, loading, login, logout, setUser }}>{children}</AuthCtx.Provider>;
}
