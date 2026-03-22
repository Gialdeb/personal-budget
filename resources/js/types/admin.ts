export type AdminUserRole = 'admin' | 'staff' | 'user';

export type AdminUserItem = {
    id: number;
    name: string;
    surname: string | null;
    full_name: string;
    email: string;
    roles: AdminUserRole[];
    primary_role: AdminUserRole | null;
    status: string;
    status_label: string;
    plan_code: string | null;
    subscription_status: string;
    subscription_status_label: string;
    is_impersonable: boolean;
    email_verified_at: string | null;
    created_at: string | null;
    can_impersonate: boolean;
    can_ban: boolean;
    can_suspend: boolean;
    can_reactivate: boolean;
    can_manage_roles: boolean;
    can_delete: boolean;
};

export type AdminUserFilterValue = {
    value: string;
    label: string;
};

export type AdminUsersFilters = {
    search: string;
    role: string;
    status: string;
    plan: string;
};

export type AdminUsersOptions = {
    roles: AdminUserFilterValue[];
    statuses: AdminUserFilterValue[];
    plans: AdminUserFilterValue[];
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatedAdminUsers = {
    data: AdminUserItem[];
    links: PaginationLink[];
    total: number;
    from: number | null;
    to: number | null;
    current_page: number;
    last_page: number;
    per_page: number;
};

export type AdminUsersPageProps = {
    users: PaginatedAdminUsers;
    filters: AdminUsersFilters;
    options: AdminUsersOptions;
};
