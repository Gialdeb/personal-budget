import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { persistPrivacyMode, readPrivacyMode } from '@/lib/privacy-mode.js';

const isPrivacyModeEnabled = ref(readPrivacyMode());

export function usePrivacyMode() {
    const { t } = useI18n();
    const privacyModeLabel = computed(() =>
        isPrivacyModeEnabled.value
            ? t('app.privacyMode.showAmounts')
            : t('app.privacyMode.hideAmounts'),
    );

    function setPrivacyMode(isEnabled: boolean): void {
        isPrivacyModeEnabled.value = isEnabled;
        persistPrivacyMode(isEnabled);
    }

    function togglePrivacyMode(): void {
        setPrivacyMode(!isPrivacyModeEnabled.value);
    }

    return {
        isPrivacyModeEnabled,
        privacyModeLabel,
        setPrivacyMode,
        togglePrivacyMode,
    };
}
