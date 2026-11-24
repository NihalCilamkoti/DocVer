import { User, LoginFormData, RegisterFormData } from "@/types/auth";

export const mockAuthService = {
  getCurrentUser: async (): Promise<User | null> => {
    return {
      id: "usr_1",
      name: "DocVer User",
      email: "user@docver.com",
      organization: "Default Workspace",
      role: "admin",
      createdAt: new Date().toISOString(),
    };
  },

  login: async (credentials: LoginFormData): Promise<{ user: User; token: string }> => {
    await new Promise((resolve) => setTimeout(resolve, 300));
    const name = credentials.email.split("@")[0].replace(".", " ");
    return {
      user: {
        id: "usr_" + Date.now(),
        name: name.charAt(0).toUpperCase() + name.slice(1),
        email: credentials.email,
        organization: "Default Workspace",
        role: "admin",
        createdAt: new Date().toISOString(),
      },
      token: "sanctum_session_token",
    };
  },

  register: async (data: RegisterFormData): Promise<{ user: User; token: string }> => {
    await new Promise((resolve) => setTimeout(resolve, 300));
    return {
      user: {
        id: "usr_" + Date.now(),
        name: data.name,
        email: data.email,
        organization: data.organization || "Default Workspace",
        role: "admin",
        createdAt: new Date().toISOString(),
      },
      token: "sanctum_session_token",
    };
  },

  logout: async (): Promise<void> => {
    await new Promise((resolve) => setTimeout(resolve, 100));
  },
};
