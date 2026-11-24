"use client";

import * as React from "react";
import { useRouter, usePathname } from "next/navigation";
import { apiClient } from "@/lib/api-client";
import { mockAuthService } from "@/services/mock-auth-service";

export interface AuthUser {
  id: number | string;
  name: string;
  email: string;
  role: "user" | "workspace_owner" | "workspace_admin" | "super_admin" | string;
  avatar_url?: string;
  phone?: string;
  email_verified: boolean;
  workspaces?: {
    id: number;
    name: string;
    slug: string;
    plan: string;
    role: string;
  }[];
}

interface AuthContextType {
  user: AuthUser | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (data: { email: string; password: string; remember?: boolean }) => Promise<void>;
  register: (data: { name: string; email: string; password: string; organization: string }) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = React.createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = React.useState<AuthUser | null>(null);
  const [isLoading, setIsLoading] = React.useState(true);
  const router = useRouter();
  const pathname = usePathname();

  const refreshUser = React.useCallback(async () => {
    try {
      const res = await apiClient.get<AuthUser>("/user");
      if (res.success && res.data) {
        setUser(res.data);
      } else {
        setUser(null);
      }
    } catch {
      // In development, if backend server is not running on port 8000, fallback to demo session
      const mockUser = await mockAuthService.getCurrentUser();
      if (mockUser) {
        setUser({
          ...mockUser,
          email_verified: true,
          workspaces: [
            {
              id: 1,
              name: mockUser.organization || "My Workspace",
              slug: "workspace",
              plan: "standard",
              role: "owner",
            },
          ],
        });
      } else {
        setUser(null);
      }
    } finally {
      setIsLoading(false);
    }
  }, []);

  React.useEffect(() => {
    refreshUser();
  }, [refreshUser]);

  const login = async (credentials: {
    email: string;
    password: string;
    remember?: boolean;
  }) => {
    setIsLoading(true);
    try {
      const res = await apiClient.post<{ user: AuthUser; token?: string }>("/auth/login", credentials);
      if (res.success && res.data?.user) {
        setUser(res.data.user);
      }
    } catch (err) {
      // If backend connection fails, check mock credentials for seamless dev testing
      try {
        const mockResult = await mockAuthService.login({
          email: credentials.email,
          password: credentials.password,
          rememberMe: credentials.remember,
        });
        setUser({
          ...mockResult.user,
          email_verified: true,
          workspaces: [
            {
              id: 1,
              name: mockResult.user.organization,
              slug: "sterling-partners-llp",
              plan: "enterprise",
              role: "owner",
            },
          ],
        });
      } catch {
        throw err;
      }
    } finally {
      setIsLoading(false);
    }
  };

  const register = async (data: {
    name: string;
    email: string;
    password: string;
    organization: string;
  }) => {
    setIsLoading(true);
    try {
      const res = await apiClient.post<{ user: AuthUser; token?: string }>("/auth/register", data);
      if (res.success && res.data?.user) {
        setUser(res.data.user);
      }
    } catch (err) {
      // Graceful fallback for mock mode
      try {
        const mockResult = await mockAuthService.register({
          name: data.name,
          email: data.email,
          organization: data.organization,
          password: data.password,
          confirmPassword: data.password,
          agreeToTerms: true,
        });
        setUser({
          ...mockResult.user,
          email_verified: false,
          workspaces: [
            {
              id: 1,
              name: data.organization,
              slug: "new-workspace",
              plan: "professional",
              role: "owner",
            },
          ],
        });
      } catch {
        throw err;
      }
    } finally {
      setIsLoading(false);
    }
  };

  const logout = async () => {
    setIsLoading(true);
    try {
      await apiClient.post("/auth/logout");
    } catch {
      await mockAuthService.logout();
    } finally {
      setUser(null);
      setIsLoading(false);
      router.push("/login");
    }
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        isAuthenticated: !!user,
        isLoading,
        login,
        register,
        logout,
        refreshUser,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = React.useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
}
