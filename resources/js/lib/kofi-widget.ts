type KofiWidgetApi = {
    init: (label: string, buttonColor: string, pageId: string) => void;
    draw: () => void;
};

type KofiWindow = Window & {
    kofiwidget2?: KofiWidgetApi;
    __soamcoKofiWidgetLoader?: Promise<void>;
};

const KOFI_SCRIPT_ID = 'soamco-kofi-widget-script';

export async function ensureKofiWidgetScript(
    scriptUrl: string,
): Promise<KofiWidgetApi> {
    const globalWindow = window as KofiWindow;

    if (globalWindow.kofiwidget2) {
        return globalWindow.kofiwidget2;
    }

    if (!globalWindow.__soamcoKofiWidgetLoader) {
        globalWindow.__soamcoKofiWidgetLoader = new Promise<void>(
            (resolve, reject) => {
                const existingScript = document.getElementById(
                    KOFI_SCRIPT_ID,
                ) as HTMLScriptElement | null;

                if (existingScript) {
                    existingScript.addEventListener('load', () => resolve(), {
                        once: true,
                    });
                    existingScript.addEventListener(
                        'error',
                        () =>
                            reject(
                                new Error(
                                    'Unable to load Ko-fi widget script.',
                                ),
                            ),
                        { once: true },
                    );

                    return;
                }

                const script = document.createElement('script');
                script.id = KOFI_SCRIPT_ID;
                script.async = true;
                script.src = scriptUrl;
                script.onload = () => resolve();
                script.onerror = () =>
                    reject(new Error('Unable to load Ko-fi widget script.'));
                document.head.appendChild(script);
            },
        );
    }

    await globalWindow.__soamcoKofiWidgetLoader;

    if (!globalWindow.kofiwidget2) {
        throw new Error('Ko-fi widget API is unavailable after script load.');
    }

    return globalWindow.kofiwidget2;
}

export function drawKofiWidget(
    widget: KofiWidgetApi,
    host: HTMLElement,
    buttonLabel: string,
    buttonColor: string,
    pageId: string,
): void {
    const originalWrite = document.write.bind(document);
    const originalWriteln = document.writeln.bind(document);

    host.innerHTML = '';

    const writeToHost = (...chunks: string[]) => {
        host.insertAdjacentHTML('beforeend', chunks.join(''));
    };

    document.write = writeToHost;
    document.writeln = writeToHost;

    try {
        widget.init(buttonLabel, buttonColor, pageId);
        widget.draw();
    } finally {
        document.write = originalWrite;
        document.writeln = originalWriteln;
    }
}
