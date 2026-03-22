export type User = {
    uuid: string;
    name: string;
    surname: string | null;
    email: string;
    avatar?: string;
    is_admin: boolean;
    is_impersonable: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    settings: {
        uuid: string;
        active_year: number | null;
        base_currency: string;
    } | null;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
