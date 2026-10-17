import { normalizeReferenceSearch } from './tracked-item-options';

export type MovementOption = {
    value: string;
    label: string;
    full_path: string;
    catalog: string;
};
export type MovementGroup = {
    key: string;
    label: string;
    count: number;
    items: Array<{
        key: string;
        title: string;
        date: string | null;
        archived: boolean;
    }>;
};
export type MovementPreview = {
    count: number;
    preview_token: string;
    groups: MovementGroup[];
    block_reason: string | null;
};
export function movementDestinations(
    options: MovementOption[],
    source: string,
): MovementOption[] {
    const selected = options.find((option) => option.value === source);

    return selected
        ? options.filter(
              (option) =>
                  option.catalog === selected.catalog &&
                  option.value !== source,
          )
        : [];
}
export function canMoveMovements(
    preview: MovementPreview | null,
    busy: boolean,
): boolean {
    return (
        !busy &&
        preview !== null &&
        preview.count > 0 &&
        !preview.block_reason &&
        preview.preview_token.length === 64
    );
}

export function searchMovementDestinations(
    options: MovementOption[],
    source: string,
    query: string,
): MovementOption[] {
    const terms = normalizeReferenceSearch(query).split(' ').filter(Boolean);

    return movementDestinations(options, source).filter((option) => {
        const name = normalizeReferenceSearch(option.label);

        return terms.every((term) => name.includes(term));
    });
}

export function isCurrentMovementRequest(
    requestRevision: number,
    currentRevision: number,
    open: boolean,
): boolean {
    return open && requestRevision === currentRevision;
}

export type MovementHistoryEntry = {
    uuid: string;
    source_name: string;
    destination_name: string;
    actor_name: string;
    created_at: string;
    expires_at: string;
    count: number;
    groups: Array<{ label: string; count: number }>;
    restored_at: string | null;
    restored_by_name: string | null;
    can_restore: boolean;
    block_reason: string | null;
};
export function canRestoreMovement(
    entry: MovementHistoryEntry,
    busy: boolean,
    now = Date.now(),
): boolean {
    return (
        !busy &&
        entry.can_restore &&
        !entry.restored_at &&
        new Date(entry.expires_at).getTime() > now
    );
}
