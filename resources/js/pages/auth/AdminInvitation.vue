<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@/composables/useNotifiedForm';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { accept } from '@/routes/admin-invitations';

const props = defineProps<{
    invitation: { uuid: string; pending: boolean; expires_at: string };
}>();
const { t, locale } = useI18n();
const form = useForm({ otp: '' });
function submit() {
    form.post(accept.url(props.invitation.uuid), {
        onFinish: () => form.reset('otp'),
    });
}
</script>

<template>
    <AuthLayout
        :title="t('adminInvitation.confirm')"
        :description="t('adminInvitation.user_intro')"
    >
        <Head :title="t('adminInvitation.confirm')" />
        <div class="space-y-5">
            <ShieldCheck class="mx-auto size-10 text-primary" />
            <form
                v-if="invitation.pending"
                class="space-y-4"
                @submit.prevent="submit"
            >
                <p class="text-center text-sm text-muted-foreground">
                    {{
                        t('adminInvitation.expires', {
                            date: new Date(
                                invitation.expires_at,
                            ).toLocaleString(locale),
                        })
                    }}
                </p>
                <div class="space-y-2">
                    <Label for="admin-otp">{{
                        t('adminInvitation.otp')
                    }}</Label>
                    <Input
                        id="admin-otp"
                        v-model="form.otp"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        required
                        class="text-center text-xl tracking-widest"
                    />
                    <InputError :message="form.errors.otp" />
                </div>
                <Button
                    class="w-full"
                    :disabled="form.processing || form.otp.length !== 6"
                    >{{ t('adminInvitation.confirm') }}</Button
                >
            </form>
            <p v-else class="text-center text-sm text-muted-foreground">
                {{ t('adminInvitation.unavailable') }}
            </p>
        </div>
    </AuthLayout>
</template>
