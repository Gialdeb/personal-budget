function resolveBrowserEnvironment() {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return null;
    }

    return { window, document };
}

function nonEmptyString(value) {
    return typeof value === 'string' && value.trim() !== '';
}

function reportLoadState(reporter, state) {
    if (typeof reporter === 'function') {
        reporter(state);
    }
}

export function tawkToScriptSrc(propertyId, widgetId) {
    return `https://embed.tawk.to/${propertyId}/${widgetId}`;
}

export function loadTawkToWidget(
    config,
    environment = resolveBrowserEnvironment(),
    options = {},
) {
    const reporter = options?.reporter;

    if (!environment) {
        reportLoadState(reporter, {
            loaded: false,
            reason: 'missing-browser-environment',
            enabled: Boolean(config?.enabled),
            hasPropertyId: nonEmptyString(config?.propertyId),
            hasWidgetId: nonEmptyString(config?.widgetId),
            scriptSrc: null,
        });

        return false;
    }

    if (!config?.enabled) {
        reportLoadState(reporter, {
            loaded: false,
            reason: 'disabled',
            enabled: Boolean(config?.enabled),
            hasPropertyId: nonEmptyString(config?.propertyId),
            hasWidgetId: nonEmptyString(config?.widgetId),
            scriptSrc: null,
        });

        return false;
    }

    const { window: targetWindow, document: targetDocument } = environment;
    const { propertyId, widgetId } = config;

    if (!nonEmptyString(propertyId) || !nonEmptyString(widgetId)) {
        reportLoadState(reporter, {
            loaded: false,
            reason: 'missing-config',
            enabled: Boolean(config.enabled),
            hasPropertyId: nonEmptyString(propertyId),
            hasWidgetId: nonEmptyString(widgetId),
            scriptSrc: null,
        });

        return false;
    }

    const scriptSrc = tawkToScriptSrc(propertyId.trim(), widgetId.trim());
    const existingScripts = Array.from(
        targetDocument.getElementsByTagName('script') ?? [],
    );

    if (existingScripts.some((script) => script.src === scriptSrc)) {
        reportLoadState(reporter, {
            loaded: false,
            reason: 'script-already-present',
            enabled: Boolean(config.enabled),
            hasPropertyId: true,
            hasWidgetId: true,
            scriptSrc,
        });

        return false;
    }

    targetWindow.Tawk_API = targetWindow.Tawk_API || {};
    targetWindow.Tawk_LoadStart = targetWindow.Tawk_LoadStart || new Date();

    const script = targetDocument.createElement('script');
    script.async = true;
    script.src = scriptSrc;
    script.charset = 'UTF-8';
    script.setAttribute('crossorigin', '*');

    const [firstScript] = existingScripts;

    if (firstScript?.parentNode) {
        firstScript.parentNode.insertBefore(script, firstScript);
        reportLoadState(reporter, {
            loaded: true,
            reason: 'inserted-before-first-script',
            enabled: Boolean(config.enabled),
            hasPropertyId: true,
            hasWidgetId: true,
            scriptSrc,
        });

        return true;
    }

    targetDocument.body?.appendChild(script);
    reportLoadState(reporter, {
        loaded: true,
        reason: 'appended-to-body',
        enabled: Boolean(config.enabled),
        hasPropertyId: true,
        hasWidgetId: true,
        scriptSrc,
    });

    return true;
}
