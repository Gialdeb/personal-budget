/** Ignore hidden responsive variants and elements inside closed panels. */
export function findOnboardingTarget(root, target) {
    return (
        [...root.querySelectorAll(`[data-onboarding="${target}"]`)].find(
            (el) => {
                const box = el.getBoundingClientRect();
                const style = el.ownerDocument.defaultView.getComputedStyle(el);

                return (
                    box.width > 0 &&
                    box.height > 0 &&
                    style.visibility !== 'hidden' &&
                    style.display !== 'none' &&
                    !el.closest(
                        '[hidden], [aria-hidden="true"], [role="dialog"][data-state="closed"], [role="menu"][data-state="closed"], [role="listbox"][data-state="closed"]',
                    )
                );
            },
        ) ?? null
    );
}

export function onboardingPosition(box, card, viewport, padding = 6) {
    const leftEdge = viewport.left + 12;
    const topEdge = viewport.top + 12;
    const rightEdge = viewport.left + viewport.width - 12;
    const bottomEdge = viewport.top + viewport.height - 12;
    const below = box.top + box.height + padding + 12;
    const above = box.top - card.height - padding - 12;

    return {
        top: Math.min(
            Math.max(topEdge, bottomEdge - card.height),
            Math.max(
                topEdge,
                below + card.height <= bottomEdge
                    ? below
                    : above >= topEdge
                      ? above
                      : bottomEdge - card.height,
            ),
        ),
        left: Math.max(
            leftEdge,
            Math.min(box.left - padding, rightEdge - card.width),
        ),
    };
}

export function trapOnboardingFocus(event, dialog) {
    if (event.key !== 'Tab') {
        return;
    }

    const buttons = [
        ...dialog.querySelectorAll(
            'button:not([disabled]), a[href], [tabindex="0"]',
        ),
    ].filter((el) => el.getClientRects().length > 0);
    const first = buttons[0];
    const last = buttons.at(-1);
    const current = dialog.ownerDocument.activeElement;

    if (!first) {
        event.preventDefault();
        dialog.focus();
    } else if (
        event.shiftKey &&
        (current === first || current === dialog || !dialog.contains(current))
    ) {
        event.preventDefault();
        last.focus();
    } else if (
        !event.shiftKey &&
        (current === last || !dialog.contains(current))
    ) {
        event.preventDefault();
        first.focus();
    }
}
