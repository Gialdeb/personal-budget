const TOUCH_TARGET_SELECTOR =
    '.app-touch-interactive, [data-app-touch-target]';

function isGuardedTouchTarget(event: Event): boolean {
    if (!(event.target instanceof Element)) {
        return false;
    }

    return event.target.closest(TOUCH_TARGET_SELECTOR) !== null;
}

function preventBrowserTouchChrome(event: Event): void {
    if (isGuardedTouchTarget(event)) {
        event.preventDefault();
    }
}

export function initializeAppTouchGuards(): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (window.__soamcoBudgetAppTouchGuardsInitialized === true) {
        return;
    }

    window.__soamcoBudgetAppTouchGuardsInitialized = true;

    document.addEventListener('contextmenu', preventBrowserTouchChrome, {
        capture: true,
    });
    document.addEventListener('dragstart', preventBrowserTouchChrome, {
        capture: true,
    });
}
