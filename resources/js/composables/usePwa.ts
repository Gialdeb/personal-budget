import { onBeforeUnmount, onMounted, readonly, ref } from 'vue';

const PWA_ENABLED_SELECTOR = 'meta[name="soamco-pwa-enabled"]';
const PWA_DEBUG_SELECTOR = 'meta[name="soamco-pwa-debug"]';
const RELOAD_ON_ACTIVATE_STORAGE_KEY = 'soamco-budget:pwa-reload-on-activate';
const PWA_DEBUG_STORAGE_KEY = 'soamco-budget:debug-pwa';
const UPDATE_CHECK_INTERVAL_MS = 30 * 60 * 1000;
const CACHE_PREFIX = 'soamco-budget-';

const isEnabled = ref(false);
const isOffline = ref(false);
const isUpdateReady = ref(false);
const isApplyingUpdate = ref(false);
const installPromptEvent = ref<BeforeInstallPromptEvent | null>(null);
const installState = ref<
    'available' | 'installed' | 'ios' | 'dismissed' | 'unsupported'
>('unsupported');
const installDiagnostic = ref('PWA install prompt not initialized yet.');
const isLaunchingInstallPrompt = ref(false);
const registration = ref<ServiceWorkerRegistration | null>(null);
const waitingWorker = ref<ServiceWorker | null>(null);

let isInitialized = false;
let mountedConsumers = 0;
let updateCheckInterval: ReturnType<typeof window.setInterval> | null = null;
let onlineHandler: (() => void) | null = null;
let offlineHandler: (() => void) | null = null;
let visibilityHandler: (() => void) | null = null;
let controllerChangeHandler: (() => void) | null = null;
let beforeInstallPromptHandler:
    | ((event: BeforeInstallPromptEvent) => void)
    | null = null;
let appInstalledHandler: (() => void) | null = null;
let installPromptTrackingBootstrapped = false;

function isDevEnvironment(): boolean {
    if (import.meta.env.DEV) {
        return true;
    }

    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return false;
    }

    if (
        document
            .querySelector<HTMLMetaElement>(PWA_DEBUG_SELECTOR)
            ?.content === 'true'
    ) {
        return true;
    }

    return window.localStorage.getItem(PWA_DEBUG_STORAGE_KEY) === 'true';
}

function debugInstallFlow(message: string): void {
    if (!isDevEnvironment()) {
        return;
    }

    console.debug(`[PWA install] ${message}`);
}

function isIosDevice(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }

    const userAgent = navigator.userAgent.toLowerCase();
    const platform = navigator.platform.toLowerCase();

    return (
        /iphone|ipad|ipod/.test(userAgent) ||
        (platform === 'macintel' && navigator.maxTouchPoints > 1)
    );
}

function isStandaloneMode(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        navigator.standalone === true
    );
}

function refreshInstallState(): void {
    if (isStandaloneMode()) {
        installState.value = 'installed';
        installPromptEvent.value = null;
        installDiagnostic.value =
            'App already installed or running in standalone mode.';
        debugInstallFlow(installDiagnostic.value);

        return;
    }

    if (installPromptEvent.value !== null) {
        installState.value = 'available';
        installDiagnostic.value = 'Browser install prompt is available.';
        debugInstallFlow(installDiagnostic.value);

        return;
    }

    if (isIosDevice()) {
        installState.value = 'ios';
        installDiagnostic.value =
            'iOS detected: manual Add to Home Screen flow only.';
        debugInstallFlow(installDiagnostic.value);

        return;
    }

    if (installState.value === 'dismissed') {
        installDiagnostic.value =
            'Install prompt was dismissed and is no longer cached.';
        debugInstallFlow(installDiagnostic.value);

        return;
    }

    installState.value = 'unsupported';
    installDiagnostic.value =
        'No browser install prompt is currently available.';
    debugInstallFlow(installDiagnostic.value);
}

function supportsServiceWorker(): boolean {
    return (
        typeof window !== 'undefined' &&
        typeof navigator !== 'undefined' &&
        'serviceWorker' in navigator
    );
}

function readPwaEnabledMeta(): boolean {
    if (typeof document === 'undefined') {
        return false;
    }

    return (
        document.querySelector<HTMLMetaElement>(PWA_ENABLED_SELECTOR)
            ?.content === 'true'
    );
}

function setReloadOnActivateFlag(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.setItem(RELOAD_ON_ACTIVATE_STORAGE_KEY, 'true');
}

function consumeReloadOnActivateFlag(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    const shouldReload =
        window.sessionStorage.getItem(RELOAD_ON_ACTIVATE_STORAGE_KEY) ===
        'true';

    if (shouldReload) {
        window.sessionStorage.removeItem(RELOAD_ON_ACTIVATE_STORAGE_KEY);
    }

    return shouldReload;
}

async function clearPwaCaches(): Promise<void> {
    if (typeof window === 'undefined' || !('caches' in window)) {
        return;
    }

    const cacheNames = await caches.keys();

    await Promise.all(
        cacheNames
            .filter((cacheName) => cacheName.startsWith(CACHE_PREFIX))
            .map((cacheName) => caches.delete(cacheName)),
    );
}

async function disablePwa(): Promise<void> {
    if (!supportsServiceWorker()) {
        return;
    }

    const activeRegistration = await navigator.serviceWorker
        .getRegistration('/')
        .catch(() => null);

    if (activeRegistration) {
        await activeRegistration.unregister();
    }

    await clearPwaCaches();
}

function setWaitingWorker(worker: ServiceWorker | null): void {
    waitingWorker.value = worker;
    isUpdateReady.value = worker !== null;

    if (worker === null) {
        isApplyingUpdate.value = false;
    }
}

function trackInstallingWorker(worker: ServiceWorker | null): void {
    if (worker === null) {
        return;
    }

    worker.addEventListener('statechange', () => {
        if (
            worker.state === 'installed' &&
            navigator.serviceWorker.controller !== null
        ) {
            setWaitingWorker(worker);
        }
    });
}

async function checkForUpdates(): Promise<void> {
    if (!isEnabled.value || registration.value === null || !navigator.onLine) {
        return;
    }

    try {
        await registration.value.update();
    } catch {
        return;
    }

    if (registration.value.waiting) {
        setWaitingWorker(registration.value.waiting);
    }
}

function attachGlobalListeners(): void {
    if (
        typeof window === 'undefined' ||
        typeof document === 'undefined' ||
        !supportsServiceWorker()
    ) {
        return;
    }

    if (onlineHandler === null) {
        onlineHandler = () => {
            isOffline.value = false;
            void checkForUpdates();
        };

        window.addEventListener('online', onlineHandler);
    }

    if (offlineHandler === null) {
        offlineHandler = () => {
            isOffline.value = true;
        };

        window.addEventListener('offline', offlineHandler);
    }

    if (visibilityHandler === null) {
        visibilityHandler = () => {
            if (document.visibilityState === 'visible') {
                void checkForUpdates();
            }
        };

        document.addEventListener('visibilitychange', visibilityHandler);
    }

    if (controllerChangeHandler === null) {
        controllerChangeHandler = () => {
            if (consumeReloadOnActivateFlag()) {
                window.location.reload();

                return;
            }

            waitingWorker.value = null;
            isApplyingUpdate.value = false;
            isUpdateReady.value = true;
        };

        navigator.serviceWorker.addEventListener(
            'controllerchange',
            controllerChangeHandler,
        );
    }

    if (updateCheckInterval === null) {
        updateCheckInterval = window.setInterval(() => {
            void checkForUpdates();
        }, UPDATE_CHECK_INTERVAL_MS);
    }
}

function detachGlobalListeners(): void {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    if (onlineHandler) {
        window.removeEventListener('online', onlineHandler);
        onlineHandler = null;
    }

    if (offlineHandler) {
        window.removeEventListener('offline', offlineHandler);
        offlineHandler = null;
    }

    if (visibilityHandler) {
        document.removeEventListener('visibilitychange', visibilityHandler);
        visibilityHandler = null;
    }

    if (controllerChangeHandler && supportsServiceWorker()) {
        navigator.serviceWorker.removeEventListener(
            'controllerchange',
            controllerChangeHandler,
        );
        controllerChangeHandler = null;
    }

    if (updateCheckInterval !== null) {
        window.clearInterval(updateCheckInterval);
        updateCheckInterval = null;
    }
}

function attachInstallPromptListeners(): void {
    if (
        installPromptTrackingBootstrapped ||
        typeof window === 'undefined' ||
        typeof document === 'undefined'
    ) {
        return;
    }

    installPromptTrackingBootstrapped = true;
    debugInstallFlow('Bootstrapping global beforeinstallprompt listeners.');

    if (beforeInstallPromptHandler === null) {
        beforeInstallPromptHandler = (event: BeforeInstallPromptEvent) => {
            event.preventDefault();
            installPromptEvent.value = event;
            installState.value = 'available';
            installDiagnostic.value =
                'Captured beforeinstallprompt globally and cached it for reuse.';
            debugInstallFlow(installDiagnostic.value);
        };

        window.addEventListener(
            'beforeinstallprompt',
            beforeInstallPromptHandler,
        );
    }

    if (appInstalledHandler === null) {
        appInstalledHandler = () => {
            installPromptEvent.value = null;
            isLaunchingInstallPrompt.value = false;
            installState.value = 'installed';
            installDiagnostic.value =
                'Browser reported appinstalled; hiding install CTA.';
            debugInstallFlow(installDiagnostic.value);
        };

        window.addEventListener('appinstalled', appInstalledHandler);
    }

    refreshInstallState();
}

async function initializePwa(): Promise<void> {
    attachInstallPromptListeners();
    isEnabled.value = readPwaEnabledMeta();
    isOffline.value =
        typeof navigator !== 'undefined' ? !navigator.onLine : false;
    refreshInstallState();

    if (!supportsServiceWorker()) {
        return;
    }

    if (!isEnabled.value) {
        await disablePwa();

        return;
    }

    const currentRegistration = await navigator.serviceWorker.register(
        '/service-worker.js',
        {
            scope: '/',
            updateViaCache: 'none',
        },
    );

    registration.value = currentRegistration;

    if (currentRegistration.waiting) {
        setWaitingWorker(currentRegistration.waiting);
    }

    trackInstallingWorker(currentRegistration.installing);

    currentRegistration.addEventListener('updatefound', () => {
        trackInstallingWorker(currentRegistration.installing);
    });

    attachGlobalListeners();
    void checkForUpdates();
}

export function usePwa() {
    onMounted(() => {
        mountedConsumers += 1;

        if (!isInitialized) {
            isInitialized = true;
            void initializePwa();
        }
    });

    onBeforeUnmount(() => {
        mountedConsumers = Math.max(0, mountedConsumers - 1);

        if (mountedConsumers === 0) {
            detachGlobalListeners();
        }
    });

    function applyUpdate(): void {
        if (typeof window === 'undefined') {
            return;
        }

        if (waitingWorker.value) {
            isApplyingUpdate.value = true;
            setReloadOnActivateFlag();
            waitingWorker.value.postMessage({ type: 'SKIP_WAITING' });

            return;
        }

        window.location.reload();
    }

    async function launchInstall(): Promise<
        'prompted' | 'installed' | 'ios' | 'dismissed' | 'unsupported'
    > {
        debugInstallFlow(
            `launchInstall() invoked. installPromptEvent present=${installPromptEvent.value !== null}. current state=${installState.value}.`,
        );

        if (isStandaloneMode()) {
            installState.value = 'installed';

            return 'installed';
        }

        if (installPromptEvent.value === null) {
            refreshInstallState();
            debugInstallFlow(
                `Install CTA clicked without prompt; state is ${installState.value}.`,
            );

            return installState.value === 'ios' ? 'ios' : 'unsupported';
        }

        const deferredPrompt = installPromptEvent.value;
        isLaunchingInstallPrompt.value = true;

        try {
            debugInstallFlow(
                'Calling prompt() directly from the install CTA user gesture.',
            );
            await deferredPrompt.prompt();

            const { outcome } = await deferredPrompt.userChoice;
            debugInstallFlow(`Browser install choice resolved: ${outcome}.`);

            if (outcome === 'accepted') {
                installPromptEvent.value = null;
                installState.value = 'installed';
                installDiagnostic.value =
                    'User accepted the browser install prompt.';

                return 'prompted';
            }

            installPromptEvent.value = null;
            installState.value = 'dismissed';
            installDiagnostic.value =
                'User dismissed the browser install prompt.';

            return 'dismissed';
        } catch (error) {
            refreshInstallState();
            debugInstallFlow(
                `prompt() failed before resolving userChoice: ${error instanceof Error ? error.message : String(error)}`,
            );

            return installState.value === 'ios' ? 'ios' : 'unsupported';
        } finally {
            isLaunchingInstallPrompt.value = false;
        }
    }

    return {
        isEnabled: readonly(isEnabled),
        isOffline: readonly(isOffline),
        isUpdateReady: readonly(isUpdateReady),
        isApplyingUpdate: readonly(isApplyingUpdate),
        installState: readonly(installState),
        installDiagnostic: readonly(installDiagnostic),
        isLaunchingInstallPrompt: readonly(isLaunchingInstallPrompt),
        applyUpdate,
        launchInstall,
    };
}

attachInstallPromptListeners();
