export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    is_active: boolean;
    last_login_at: string | null;
    is_current: boolean;
};

export type RoleOption = { value: string; label: string };
