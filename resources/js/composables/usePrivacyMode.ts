import { computed, ref } from 'vue';
import { persistPrivacyMode, readPrivacyMode } from '@/lib/privacy-mode.js';

const isPrivacyModeEnabled = ref(readPrivacyMode());

export function usePrivacyMode() {
    const privacyModeLabel = computed(() =>
        isPrivacyModeEnabled.value ? 'Mostra importi' : 'Nascondi importi',
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
