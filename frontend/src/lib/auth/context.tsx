'use client';

import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { authApi } from '@/lib/api/endpoints';
import { clearToken, getToken, setToken } from '@/lib/api/client';
import type { User } from '@/types/api';

type AuthState = {
  user: User | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refresh: () => Promise<void>;
  hasRole: (role: string | string[]) => boolean;
  can: (permission: string) => boolean;
};

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  async function refresh() {
    try {
      if (!getToken()) { setUser(null); return; }
      const me = await authApi.me();
      setUser(me);
    } catch {
      setUser(null);
      clearToken();
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { refresh(); }, []);

  async function login(email: string, password: string) {
    const { token, user } = await authApi.login(email, password);
    setToken(token);
    setUser(user);
  }

  async function logout() {
    try { await authApi.logout(); } catch { /* no-op */ }
    clearToken();
    setUser(null);
    window.location.href = '/login';
  }

  function hasRole(role: string | string[]): boolean {
    if (!user?.roles) return false;
    const names = user.roles.map((r) => r.name);
    return Array.isArray(role) ? role.some((r) => names.includes(r)) : names.includes(role);
  }

  function can(permission: string): boolean {
    if (!user) return false;
    if (hasRole('super_admin')) return true;
    return user.permissions?.some((p) => p.name === permission) ?? false;
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, logout, refresh, hasRole, can }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
