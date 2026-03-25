export type AccountSharingMember = {
    uuid: string;
    role: string | null;
    role_label: string | null;
    status: string | null;
    status_label: string | null;
    source: string | null;
    source_label: string | null;
    joined_at: string | null;
    left_at: string | null;
    left_reason: string | null;
    revoked_at: string | null;
    restored_at: string | null;
    user: {
        uuid: string | null;
        name: string | null;
        email: string | null;
    };
};

export type AccountSharingInvitation = {
    uuid: string;
    email: string;
    role: string | null;
    role_label: string | null;
    status: string | null;
    status_label: string | null;
    expires_at: string | null;
    accepted_at: string | null;
    created_at: string | null;
};
