<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ShieldPlus } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { destroy } from '@/actions/App/Http/Controllers/Admin/AdminInvitationController';
import { store } from '@/actions/App/Http/Controllers/Admin/AdminInvitationController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { AdminUserItem } from '@/types';

const props = defineProps<{ user: AdminUserItem }>();
const { t } = useI18n();
const open = ref(false);
const cancelling = ref(false);
const form = useForm({ invitation: '' });

function prompt(cancel = false) {
    cancelling.value = cancel;
    form.clearErrors();
    open.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (cancelling.value && props.user.admin_invitation) {
        form.delete(destroy.url(props.user.admin_invitation.uuid), options);
    } else {
        form.post(store.url({ user: props.user.uuid }), options);
    }
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Badge v-if="user.roles.includes('admin')" variant="secondary">{{
            t('adminInvitation.admin')
        }}</Badge>
        <template v-else>
            <Badge
                v-if="user.admin_invitation"
                variant="secondary"
                class="max-w-full leading-5 whitespace-normal"
                >{{
                    t(
                        user.admin_invitation.pending
                            ? 'adminInvitation.pending'
                            : 'adminInvitation.expired',
                    )
                }}</Badge
            >
            <Button
                v-if="user.can_invite_admin"
                size="sm"
                variant="outline"
                class="h-auto min-h-9 max-w-full rounded-xl text-left leading-5 whitespace-normal"
                @click="prompt()"
            >
                <ShieldPlus class="size-4" />
                {{
                    t(
                        user.admin_invitation
                            ? 'adminInvitation.resend'
                            : 'adminInvitation.title',
                    )
                }}
            </Button>
            <Button
                v-if="user.admin_invitation"
                size="sm"
                variant="ghost"
                @click="prompt(true)"
                >{{ t('adminInvitation.cancel') }}</Button
            >
        </template>
        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2"
                        ><ShieldPlus class="size-5" />{{
                            t(
                                cancelling
                                    ? 'adminInvitation.cancel'
                                    : 'adminInvitation.title',
                            )
                        }}</DialogTitle
                    >
                    <DialogDescription>{{
                        t(
                            cancelling
                                ? 'adminInvitation.cancel_description'
                                : 'adminInvitation.description',
                            { email: user.email },
                        )
                    }}</DialogDescription>
                </DialogHeader>
                <p class="text-sm font-medium">{{ user.full_name }}</p>
                <InputError :message="form.errors.invitation" />
                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="form.processing"
                        @click="open = false"
                        >{{ t('adminInvitation.cancel_button') }}</Button
                    >
                    <Button :disabled="form.processing" @click="submit">{{
                        t(
                            cancelling
                                ? 'adminInvitation.cancel'
                                : 'adminInvitation.send',
                        )
                    }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
