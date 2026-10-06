export type AuthUser = {
    id: number;
    name: string;
    email: string;
    role: 'admin';
};

/** Debe coincidir con HandleInertiaRequests::share(). */
export type SharedProps = {
    app: {
        name: string;
        timezone: string;
    };
    auth: {
        user: AuthUser | null;
    };
    notifications: {
        unread: number;
    };
    flash: {
        success: string | null;
        error: string | null;
    };
};
