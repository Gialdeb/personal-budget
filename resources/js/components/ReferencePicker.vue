<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { ChevronDown, Plus } from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useId,
    watch,
} from 'vue';
import { index as searchBrands } from '@/actions/App/Http/Controllers/BrandCatalogController';
import { preview } from '@/actions/App/Http/Controllers/TrackedItemLogoController';
import InputError from '@/components/InputError.vue';
import SearchableSelect from '@/components/transactions/SearchableSelect.vue';
import SearchableSelectOptionContent from '@/components/transactions/SearchableSelectOptionContent.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogOverlay,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getInitials } from '@/composables/useInitials';
import { useUiCopy } from '@/i18n/ui-copy';
import { fetchWithNotifications } from '@/lib/notifications';
import {
    matchesReferenceSearch,
    normalizeReferenceSearch,
    prioritizeReferenceOptions,
} from '@/lib/tracked-item-options';

type Option = {
    value: string;
    label: string;
    reference_name?: string;
    logo_url?: string | null;
    search_terms?: string[];
    domain?: string | null;
    is_selectable?: boolean;
};
export type ReferenceDraft = {
    name: string;
    website_url: string | null;
    logo_identity_uuid?: string | null;
    logo_source?: 'website';
    logo_brand_uuid?: string;
    logo_upload_token?: string;
};
const props = defineProps<{
    modelValue: string;
    options: Option[];
    recent?: Option[];
    mobileSuggestions?: Option[];
    mobileRecent?: Option[];
    mobileCategory?: string;
    disabled?: boolean;
    busy?: boolean;
    error?: string;
    draftErrors?: Record<string, string>;
    prepare: (draft: ReferenceDraft) => Promise<boolean>;
    contextKey: string;
    description?: string;
    desktopTeleport?: boolean;
    desktopTriggerClass?: string;
}>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const ui = useUiCopy();
const isMobile = useMediaQuery('(max-width: 767px)');
const mobileSuggestions = computed(() =>
    (props.mobileSuggestions ?? [])
        .filter((option) => option.is_selectable !== false)
        .slice(0, 4),
);
const mobileRecent = computed(() =>
    (props.mobileRecent ?? [])
        .filter(
            (option) =>
                option.is_selectable !== false &&
                !mobileSuggestions.value.some(
                    (suggestion) => suggestion.value === option.value,
                ),
        )
        .slice(0, 4),
);
const mobileResults = computed(() => {
    const options = query.value.trim()
        ? prioritizeReferenceOptions(
              filtered.value,
              (option) =>
                  option.is_selectable !== false &&
                  (props.mobileSuggestions ?? []).some(
                      (suggestion) => suggestion.value === option.value,
                  ),
              () => true,
          )
        : [
              ...mobileSuggestions.value,
              ...mobileRecent.value,
              ...filtered.value,
          ];

    return options
        .filter(
            (option, index) =>
                options.findIndex(
                    (candidate) => candidate.value === option.value,
                ) === index,
        )
        .slice(0, 5);
});
const id = useId();
const open = ref(false),
    creating = ref(false),
    query = ref(''),
    name = ref(''),
    website = ref('');
const logoMode = ref<'none' | 'website' | 'brand'>('none');
const logoUrl = ref('');
const logoError = ref(''),
    loading = ref(false),
    automaticLogoLoading = ref(false);
let sequence = 0;
const selected = computed(() =>
    props.options.find((option) => option.value === props.modelValue),
);
const filtered = computed(() =>
    props.options.filter((option) =>
        matchesReferenceSearch(option, query.value, { domain: true }),
    ),
);
type Brand = {
    uuid: string;
    name: string;
    domain: string;
    alternative_domains?: string[];
    category: string | null;
    logo_url: string | null;
    aliases: string[];
};
const viewportHeight = ref(0),
    viewportTop = ref(0);
function updateViewport(): void {
    viewportHeight.value = window.visualViewport?.height ?? window.innerHeight;
    viewportTop.value = window.visualViewport?.offsetTop ?? 0;
}
onMounted(() => {
    document.addEventListener('input', desktopDropdownEvent, true);
    document.addEventListener('focusin', desktopDropdownEvent, true);
    document.addEventListener('click', desktopDropdownEvent, true);
    updateViewport();
    window.visualViewport?.addEventListener('resize', updateViewport);
    window.visualViewport?.addEventListener('scroll', updateViewport);
});
const brands = ref<Brand[]>([]),
    suggestedLogo = ref<Brand | null>(null);
const completedBrandSearch = ref<string | null>(null);
const canCreateDesktopReference = computed(
    () =>
        !isMobile.value &&
        query.value.trim() !== '' &&
        completedBrandSearch.value ===
            normalizeReferenceSearch(query.value, { domain: true }).slice(
                0,
                150,
            ) &&
        !brandLoading.value &&
        !brandError.value &&
        filtered.value.length === 0 &&
        brands.value.length === 0,
);
const brandLoading = ref(false),
    brandError = ref('');
const logoBrandUuid = ref('');
let brandSequence = 0,
    suggestionSequence = 0;
let brandTimer: ReturnType<typeof setTimeout> | undefined;
let websiteTimer: ReturnType<typeof setTimeout> | undefined;
let brandController: AbortController | undefined,
    suggestionController: AbortController | undefined;
const category = computed(
    () => props.mobileCategory?.split(' > ').at(-1)?.trim().slice(0, 100) ?? '',
);
const categoryBrands = computed(() =>
    brands.value
        .filter(
            (brand) =>
                category.value &&
                normalizeReferenceSearch(brand.category ?? '') ===
                    normalizeReferenceSearch(category.value),
        )
        .slice(0, 4),
);
const triggerButton = ref<InstanceType<typeof Button> | null>(null);
const desktopRoot = ref<HTMLElement | null>(null);
const desktopDropdownClass = `reference-dropdown-${id.replace(/[^a-z0-9_-]/giu, '')}`;
function desktopDropdownEvent(event: Event): void {
    if (
        !isMobile.value &&
        props.desktopTeleport &&
        event.target instanceof Element &&
        event.target.closest(`.${desktopDropdownClass}`)
    ) {
        if (event.type === 'click') {
            desktopInteraction();
        } else {
            desktopSearchInput(event);
        }
    }
}
const desktopSearching = ref(false);
const desktopSelectKey = ref(0);
function initialsLogo(label: string): string {
    const initials = getInitials(label).replace(
        /[&<>"']/gu,
        (character) =>
            ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&apos;',
            })[character] ?? character,
    );

    return `data:image/svg+xml,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40"><rect width="40" height="40" rx="8" fill="#e2e8f0"/><text x="20" y="21" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="14" font-weight="600" fill="#334155">${initials}</text></svg>`)}`;
}
const desktopOptions = computed(() => {
    const personal = prioritizeReferenceOptions(
        filtered.value,
        (option) =>
            (props.mobileSuggestions ?? []).some(
                (suggestion) => suggestion.value === option.value,
            ),
        () => true,
    ).map((option) => ({
        ...option,
        logo_url: option.logo_url || initialsLogo(option.label),
        search_terms: [...(option.search_terms ?? []), query.value],
    }));

    return [
        ...personal,
        ...brands.value.map((brand) => ({
            value: `brand:${brand.uuid}`,
            label: brand.name,
            logo_url: brand.logo_url || initialsLogo(brand.name),
            search_terms: [...(brand.aliases ?? []), query.value],
        })),
    ];
});
function desktopSearchInput(event: Event): void {
    const input = event.target;

    if (input instanceof HTMLInputElement && input.type === 'search') {
        desktopSearching.value = true;
        query.value = input.value;
    }
}
function desktopInteraction(): void {
    setTimeout(() => {
        const input =
            desktopRoot.value?.querySelector<HTMLInputElement>(
                'input[type="search"]',
            ) ??
            (props.desktopTeleport
                ? document.querySelector<HTMLInputElement>(
                      `.${desktopDropdownClass} input[type="search"]`,
                  )
                : null);

        if (input) {
            desktopSearching.value = true;
            query.value = input.value;
        } else {
            desktopSearching.value = false;

            if (!creating.value) {
                query.value = '';
            }
        }
    }, 0);
}
function createDesktopReference(value = query.value): void {
    name.value = value.trim();
    desktopSearching.value = false;
    desktopSelectKey.value++;
    creating.value = true;
    open.value = true;
}
function selectDesktopReference(value: string): void {
    const brand = brands.value.find(
        (candidate) => `brand:${candidate.uuid}` === value,
    );
    desktopSearching.value = false;

    if (brand) {
        desktopSelectKey.value++;
        open.value = true;
        chooseBrand(brand);
    } else {
        choose(value);
        query.value = '';
    }
}
async function loadBrands(): Promise<void> {
    const request = ++brandSequence;
    brandController?.abort();
    brandController = new AbortController();
    brandLoading.value = true;
    brandError.value = '';

    try {
        const search = normalizeReferenceSearch(query.value, {
            domain: true,
        }).slice(0, 150);
        const matches: Brand[] = [];
        let page = 1;
        let hasMore = false;

        do {
            const response = await fetchWithNotifications(
                searchBrands.url({
                    query: { search, category: category.value, page },
                }),
                { notification: 'preview', signal: brandController.signal },
            );

            if (!response.ok) {
                if (request === brandSequence) {
                    brandError.value = ui(
                        'Impossibile caricare i marchi. Riprova.',
                    );
                }

                return;
            }

            const data = await response.json();

            if (request !== brandSequence) {
                return;
            }

            matches.push(
                ...data.data.filter((brand: Brand) =>
                    matchesReferenceSearch(
                        {
                            value: brand.uuid,
                            label: brand.name,
                            domain: brand.domain,
                            search_terms: [
                                ...brand.aliases,
                                ...(brand.alternative_domains ?? []),
                            ],
                        },
                        search,
                        { domain: true },
                    ),
                ),
            );
            hasMore = Boolean(data.links?.next);
            page++;
        } while (matches.length < 5 && hasMore);

        brands.value = matches.slice(0, 5);
        completedBrandSearch.value = search;
    } catch {
        if (request === brandSequence) {
            brandError.value = ui('Impossibile caricare i marchi. Riprova.');
        }
    } finally {
        if (request === brandSequence) {
            brandLoading.value = false;
        }
    }
}
watch(
    [isMobile, category, query, desktopSearching, () => props.disabled],
    () => {
        ++brandSequence;
        brandController?.abort();
        clearTimeout(brandTimer);
        brands.value = [];
        completedBrandSearch.value = null;
        brandLoading.value = false;

        if ((isMobile.value || desktopSearching.value) && !props.disabled) {
            brandTimer = setTimeout(
                () => void loadBrands(),
                query.value ? 200 : 0,
            );
        }
    },
    { immediate: true },
);
async function suggestLogo(searchValue: string): Promise<void> {
    const request = ++suggestionSequence;
    suggestionController?.abort();
    suggestionController = new AbortController();

    try {
        const response = await fetchWithNotifications(
            searchBrands.url({
                query: {
                    search: normalizeReferenceSearch(searchValue, {
                        domain: true,
                    }).slice(0, 150),
                    category: category.value,
                },
            }),
            { notification: 'preview', signal: suggestionController.signal },
        );

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        if (request === suggestionSequence) {
            const available = data.data.filter(
                (brand: Brand) =>
                    brand.logo_url &&
                    matchesReferenceSearch(
                        {
                            value: brand.uuid,
                            label: brand.name,
                            domain: brand.domain,
                            search_terms: [
                                ...brand.aliases,
                                ...(brand.alternative_domains ?? []),
                            ],
                        },
                        searchValue,
                        { domain: true },
                    ),
            ) as Brand[];
            const normalized = normalizeReferenceSearch(name.value);
            suggestedLogo.value =
                available.find(
                    (brand) =>
                        normalizeReferenceSearch(brand.name) === normalized,
                ) ??
                available.find((brand) =>
                    brand.aliases?.some(
                        (alias) =>
                            normalizeReferenceSearch(alias) === normalized,
                    ),
                ) ??
                available[0] ??
                null;
        }
    } catch {
        /* Manual creation stays available when suggestions fail. */
    }
}
async function applyAutomaticBrandLogo(): Promise<void> {
    logoMode.value = suggestedLogo.value ? 'brand' : 'none';
    await nextTick();

    if (suggestedLogo.value) {
        logoBrandUuid.value = suggestedLogo.value.uuid;
        logoUrl.value = suggestedLogo.value.logo_url ?? '';
    }
}
function chooseBrand(brand: Brand): void {
    creating.value = true;
    name.value = brand.name;
    website.value = `https://${brand.domain}`;
}
function mobileOpenFocus(event: Event): void {
    if (isMobile.value && !creating.value) {
        event.preventDefault();
        const content = event.target as HTMLElement;
        void nextTick(() =>
            content
                .querySelector<HTMLButtonElement>('button')
                ?.focus({ preventScroll: true }),
        );
    }
}
function mobileCloseFocus(event: Event): void {
    if (isMobile.value) {
        event.preventDefault();
        triggerButton.value?.$el?.focus({ preventScroll: true });
    } else {
        event.preventDefault();
        desktopRoot.value
            ?.querySelector<HTMLButtonElement>('button')
            ?.focus({ preventScroll: true });
    }
}
function choose(value: string): void {
    emit('update:modelValue', value);
    open.value = false;
}
function reset(): void {
    sequence++;
    ++suggestionSequence;
    suggestionController?.abort();
    clearTimeout(websiteTimer);
    logoBrandUuid.value = '';
    suggestedLogo.value = null;
    creating.value = false;
    query.value = '';
    name.value = '';
    website.value = '';
    logoMode.value = 'none';
    logoUrl.value = '';
    logoError.value = '';
    loading.value = false;
    automaticLogoLoading.value = false;
}
watch(isMobile, () => {
    open.value = false;
    reset();
});
watch(open, (value) => {
    if (!value) {
        reset();
    }
});
watch(
    () => props.contextKey,
    () => {
        open.value = false;
        reset();
    },
);
watch([website, name, creating], () => {
    clearTimeout(websiteTimer);
    ++suggestionSequence;
    suggestionController?.abort();
    sequence++;
    logoMode.value = 'none';
    logoBrandUuid.value = '';
    logoUrl.value = '';
    suggestedLogo.value = null;
    logoError.value = '';
    loading.value = false;
    automaticLogoLoading.value = false;

    if (!creating.value || (!website.value.trim() && !name.value.trim())) {
        return;
    }

    automaticLogoLoading.value = true;
    const requestedWebsite = website.value;
    const requestedName = name.value;
    websiteTimer = setTimeout(async () => {
        await suggestLogo(requestedWebsite.trim() || requestedName);

        if (
            !creating.value ||
            website.value !== requestedWebsite ||
            name.value !== requestedName
        ) {
            return;
        }

        if (suggestedLogo.value) {
            await applyAutomaticBrandLogo();
        } else if (requestedWebsite.trim()) {
            logoMode.value = 'website';
            await fetchLogo();
        }

        if (
            website.value === requestedWebsite &&
            name.value === requestedName
        ) {
            automaticLogoLoading.value = false;
        }
    }, 350);
});
const nameInput = ref<InstanceType<typeof Input> | null>(null);
watch(creating, async (value) => {
    if (value) {
        await nextTick();
        nameInput.value?.$el?.focus();
    }
});
onBeforeUnmount(() => {
    document.removeEventListener('input', desktopDropdownEvent, true);
    document.removeEventListener('focusin', desktopDropdownEvent, true);
    document.removeEventListener('click', desktopDropdownEvent, true);
    window.visualViewport?.removeEventListener('resize', updateViewport);
    window.visualViewport?.removeEventListener('scroll', updateViewport);
    ++brandSequence;
    ++suggestionSequence;
    brandController?.abort();
    suggestionController?.abort();
    clearTimeout(brandTimer);
    clearTimeout(websiteTimer);
    sequence++;
});
async function fetchLogo(): Promise<void> {
    const request = ++sequence;
    loading.value = true;
    logoError.value = '';
    logoUrl.value = '';

    try {
        const response = await fetchWithNotifications(preview.url(), {
            method: 'POST',
            notification: 'preview',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content ?? '',
            },
            body: JSON.stringify({ website_url: website.value }),
        });
        const data = await response.json();

        if (request !== sequence) {
            return;
        }

        if (!response.ok) {
            logoMode.value = 'none';

            return;
        }

        logoUrl.value = data.logo_url ?? '';

        if (!logoUrl.value) {
            logoMode.value = 'none';

            return;
        }
    } catch {
        if (request === sequence) {
            logoMode.value = 'none';
        }
    } finally {
        if (request === sequence) {
            loading.value = false;
        }
    }
}
async function prepareReference(): Promise<void> {
    const context = props.contextKey;
    const success = await props.prepare({
        name: name.value.trim(),
        website_url: website.value.trim() || null,
        ...(logoMode.value === 'brand'
            ? { logo_brand_uuid: logoBrandUuid.value }
            : logoMode.value === 'website'
              ? { logo_source: 'website' as const }
              : { logo_identity_uuid: null }),
    });

    if (success && context === props.contextKey) {
        open.value = false;
    }
}
</script>
<template>
    <div class="min-w-0" data-reference-picker>
        <div
            v-if="
                isMobile &&
                !disabled &&
                (mobileSuggestions.length ||
                    mobileRecent.length ||
                    categoryBrands.length)
            "
            class="mb-2 grid min-w-0 gap-2"
            data-mobile-reference-shortcuts
        >
            <div
                v-if="mobileSuggestions.length || categoryBrands.length"
                class="min-w-0 space-y-1"
            >
                <p class="text-xs font-medium text-muted-foreground">
                    {{ ui('Suggeriti per questa categoria') }}
                </p>
                <div
                    class="flex max-w-full min-w-0 gap-2 overflow-x-auto overscroll-x-contain py-1"
                >
                    <Button
                        v-for="option in mobileSuggestions"
                        :key="option.value"
                        :aria-label="option.reference_name ?? option.label"
                        type="button"
                        variant="outline"
                        class="h-auto min-h-9 shrink-0 touch-manipulation rounded-full px-2.5 py-1 text-xs"
                        :disabled="busy"
                        @click="choose(option.value)"
                        ><SearchableSelectOptionContent
                            :option="{
                                ...option,
                                logo_url:
                                    option.logo_url ||
                                    initialsLogo(option.label),
                            }"
                            compact
                    /></Button>
                    <Button
                        v-for="brand in categoryBrands"
                        :key="brand.uuid"
                        type="button"
                        variant="outline"
                        class="h-auto min-h-9 shrink-0 rounded-full px-2.5 py-1 text-xs"
                        :disabled="busy"
                        @click="
                            open = true;
                            chooseBrand(brand);
                        "
                    >
                        <img
                            :src="brand.logo_url || initialsLogo(brand.name)"
                            alt=""
                            class="mr-2 size-6 object-contain"
                        />{{ brand.name }}
                    </Button>
                </div>
            </div>
            <div v-if="mobileRecent.length" class="min-w-0 space-y-1">
                <p class="text-xs font-medium text-muted-foreground">
                    {{ ui('Recenti') }}
                </p>
                <div
                    class="flex max-w-full min-w-0 gap-2 overflow-x-auto overscroll-x-contain py-1"
                >
                    <Button
                        v-for="option in mobileRecent"
                        :key="option.value"
                        type="button"
                        variant="outline"
                        class="h-auto min-h-9 shrink-0 touch-manipulation rounded-full px-2.5 py-1 text-xs"
                        :disabled="busy"
                        @click="choose(option.value)"
                        ><SearchableSelectOptionContent
                            :option="{
                                ...option,
                                logo_url:
                                    option.logo_url ||
                                    initialsLogo(option.label),
                            }"
                            compact
                    /></Button>
                </div>
            </div>
        </div>
        <div
            v-if="!isMobile"
            ref="desktopRoot"
            class="grid min-w-0 gap-2"
            data-desktop-reference-picker
            @input.capture="desktopSearchInput"
            @click.capture="desktopInteraction"
            @focusin.capture="desktopSearchInput"
        >
            <SearchableSelect
                :key="desktopSelectKey"
                :model-value="modelValue"
                :options="desktopOptions"
                :disabled="disabled || busy"
                :teleport="desktopTeleport ?? false"
                :trigger-class="desktopTriggerClass"
                side="top"
                :content-class="
                    desktopTeleport
                        ? `z-[220] ${desktopDropdownClass}`
                        : 'top-auto bottom-[calc(100%+0.5rem)]'
                "
                clearable
                :creatable="canCreateDesktopReference"
                :creating="busy"
                :placeholder="ui('Scegli riferimento')"
                :search-placeholder="ui('Cerca riferimento')"
                :create-label="ui('Nuovo riferimento')"
                @update:model-value="selectDesktopReference"
                @create-option="createDesktopReference"
            />
            <Button
                v-if="canCreateDesktopReference"
                type="button"
                variant="link"
                class="h-auto justify-start p-0 text-xs"
                :disabled="disabled || busy"
                @click="createDesktopReference()"
                ><Plus class="mr-1 size-3.5" aria-hidden="true" />{{
                    ui('Nuovo riferimento')
                }}</Button
            >
            <p
                v-if="brandLoading && desktopSearching"
                role="status"
                class="text-xs text-muted-foreground"
            >
                {{ ui('Caricamento…') }}
            </p>
            <InputError v-if="!open" :message="brandError || error" />
        </div>
        <Dialog
            :open="open"
            @update:open="
                (value) => {
                    if (!busy) {
                        open = value;
                    }
                }
            "
        >
            <div v-if="isMobile" class="flex min-w-0 items-center gap-2">
                <DialogTrigger as-child
                    ><Button
                        ref="triggerButton"
                        type="button"
                        variant="outline"
                        class="h-11 min-w-0 flex-1 justify-between rounded-2xl"
                        :disabled="disabled || busy"
                        ><span class="min-w-0 truncate"
                            ><SearchableSelectOptionContent
                                v-if="selected"
                                :option="{
                                    ...selected,
                                    logo_url:
                                        selected.logo_url ||
                                        initialsLogo(selected.label),
                                }"
                                compact
                            /><span v-else>{{
                                ui('Scegli riferimento')
                            }}</span></span
                        ><ChevronDown
                            class="ml-2 size-4 shrink-0"
                            aria-hidden="true" /></Button
                ></DialogTrigger>
                <Button
                    type="button"
                    variant="outline"
                    class="h-11 shrink-0 rounded-2xl px-3"
                    :disabled="disabled || busy"
                    @click="
                        open = true;
                        creating = true;
                    "
                    ><Plus class="mr-1 size-4" aria-hidden="true" />{{
                        ui('Nuovo')
                    }}</Button
                >
            </div>
            <Teleport to="body"
                ><DialogOverlay v-if="!isMobile" class="z-[230] bg-black/40"
            /></Teleport>
            <DialogContent
                data-reference-dialog
                @open-auto-focus="mobileOpenFocus"
                @close-auto-focus="mobileCloseFocus"
                class="flex max-h-[85dvh] flex-col overflow-hidden rounded-2xl p-4 sm:max-w-xl sm:p-6"
                :style="
                    isMobile
                        ? {
                              maxHeight: viewportHeight
                                  ? `${Math.max(160, Math.min(viewportHeight - 32, creating ? viewportHeight * 0.9 : 448))}px`
                                  : '80dvh',
                              top: viewportHeight
                                  ? `${viewportTop + viewportHeight / 2}px`
                                  : undefined,
                          }
                        : undefined
                "
                :class="isMobile ? 'gap-3' : 'z-[240] sm:max-w-md'"
            >
                <DialogHeader class="shrink-0 text-left"
                    ><DialogTitle>{{
                        isMobile ? ui('Riferimento') : ui('Nuovo riferimento')
                    }}</DialogTitle
                    ><DialogDescription
                        :class="isMobile ? 'sr-only' : undefined"
                        >{{
                            !isMobile
                                ? ui(
                                      'Il riferimento e il logo verranno salvati insieme alla registrazione.',
                                  )
                                : (description ??
                                  ui(
                                      'Scegli un riferimento o preparane uno nuovo. Verrà salvato insieme alla transazione.',
                                  ))
                        }}</DialogDescription
                    ></DialogHeader
                >
                <div
                    class="min-h-0 space-y-4 overflow-y-auto overscroll-contain p-1"
                    :class="
                        isMobile && !creating ? 'overflow-y-hidden' : undefined
                    "
                >
                    <template v-if="!creating">
                        <div
                            v-if="isMobile"
                            class="flex min-h-0 flex-col gap-2"
                            data-mobile-reference-results
                        >
                            <div class="flex shrink-0 items-center gap-2">
                                <div class="min-w-0 flex-1">
                                    <Input
                                        v-model="query"
                                        type="search"
                                        :aria-label="ui('Cerca riferimento')"
                                        :placeholder="ui('Cerca riferimento')"
                                        class="h-10"
                                        maxlength="150"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="h-10 shrink-0 rounded-xl px-3"
                                    @click="
                                        creating = true;
                                        name = query;
                                    "
                                    ><Plus
                                        class="mr-1 size-4"
                                        aria-hidden="true"
                                    />{{ ui('Nuovo') }}</Button
                                >
                            </div>
                            <div
                                class="grid max-h-60 min-h-0 gap-1 overflow-y-auto overscroll-contain"
                            >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    class="h-9 justify-start text-sm"
                                    @click="choose('')"
                                    >{{ ui('Nessun riferimento') }}</Button
                                >

                                <Button
                                    v-for="option in mobileResults"
                                    :key="option.value"
                                    type="button"
                                    variant="ghost"
                                    class="h-auto min-h-10 min-w-0 justify-start text-left text-sm"
                                    :disabled="option.is_selectable === false"
                                    :aria-label="
                                        option.reference_name ?? option.label
                                    "
                                    @click="choose(option.value)"
                                    ><div class="min-w-0">
                                        <SearchableSelectOptionContent
                                            :option="{
                                                ...option,
                                                logo_url:
                                                    option.logo_url ||
                                                    initialsLogo(option.label),
                                            }"
                                            compact
                                        />
                                    </div>
                                </Button>

                                <Button
                                    v-for="brand in brands"
                                    :key="brand.uuid"
                                    :data-reference-brand="brand.uuid"
                                    type="button"
                                    variant="ghost"
                                    class="h-auto min-h-12 min-w-0 justify-start text-left"
                                    @click="chooseBrand(brand)"
                                >
                                    <img
                                        :src="
                                            brand.logo_url ||
                                            initialsLogo(brand.name)
                                        "
                                        alt=""
                                        class="mr-3 size-8 shrink-0 object-contain"
                                    /><span class="min-w-0"
                                        ><span class="block truncate">{{
                                            brand.name
                                        }}</span>
                                    </span>
                                </Button>
                                <p
                                    v-if="brandLoading"
                                    role="status"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ ui('Caricamento…') }}
                                </p>
                                <p
                                    v-if="brandError"
                                    role="alert"
                                    class="text-xs text-destructive"
                                >
                                    {{ brandError }}
                                </p>
                                <p
                                    v-if="
                                        !mobileResults.length &&
                                        !brands.length &&
                                        !brandLoading
                                    "
                                    class="py-2 text-xs text-muted-foreground"
                                >
                                    {{ ui('Nessun risultato') }}
                                </p>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <div class="grid gap-2">
                            <Label :for="id + '-name'">{{
                                ui('Nome riferimento')
                            }}</Label
                            ><Input
                                :id="id + '-name'"
                                ref="nameInput"
                                v-model="name"
                                maxlength="150"
                            />
                            <InputError :message="draftErrors?.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="id + '-website'">{{
                                ui('Sito web (facoltativo)')
                            }}</Label
                            ><Input
                                :id="id + '-website'"
                                v-model="website"
                                type="url"
                                placeholder="https://example.com"
                            />
                        </div>
                        <InputError :message="draftErrors?.website_url" />
                        <div class="space-y-2">
                            <p class="text-sm font-medium">{{ ui('Logo') }}</p>
                            <div class="flex items-center gap-3">
                                <img
                                    v-if="logoUrl"
                                    :src="logoUrl"
                                    :alt="ui('Logo selezionato')"
                                    class="size-12 rounded-lg object-contain"
                                    @error="
                                        logoMode = 'none';
                                        logoUrl = '';
                                    "
                                />
                                <Avatar
                                    v-if="!logoUrl && name.trim()"
                                    class="size-12 rounded-lg"
                                    data-reference-initials
                                    ><AvatarFallback class="rounded-lg">{{
                                        getInitials(name)
                                    }}</AvatarFallback></Avatar
                                >
                            </div>
                        </div>
                        <p
                            v-if="loading || automaticLogoLoading"
                            role="status"
                            class="text-sm text-muted-foreground"
                        >
                            {{ ui('Caricamento…') }}
                        </p>
                        <InputError
                            :message="
                                logoError ||
                                draftErrors?.logo_identity_uuid ||
                                draftErrors?.logo_brand_uuid ||
                                draftErrors?.logo_upload_token
                            "
                        />
                        <div
                            class="flex flex-wrap gap-2"
                            :class="
                                isMobile
                                    ? 'sticky bottom-0 bg-background py-2'
                                    : undefined
                            "
                        >
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="busy"
                                @click="
                                    isMobile
                                        ? (creating = false)
                                        : (open = false)
                                "
                                >{{ ui('Indietro') }}</Button
                            ><Button
                                type="button"
                                :disabled="
                                    busy ||
                                    loading ||
                                    automaticLogoLoading ||
                                    !name.trim() ||
                                    (logoMode === 'brand' && !logoBrandUuid) ||
                                    (logoMode === 'website' && !website.trim())
                                "
                                @click="prepareReference"
                                >{{ ui('Crea e seleziona') }}</Button
                            >
                        </div>
                    </template>
                    <InputError :message="error" />
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
