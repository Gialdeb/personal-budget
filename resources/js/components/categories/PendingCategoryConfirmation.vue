<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { categoryConfirmation } from '@/lib/pending-category-confirmation';
function close(): void {
    categoryConfirmation.value?.resolve(false);
}
</script>
<template>
    <Dialog
        :open="categoryConfirmation !== null"
        @update:open="
            (open) => {
                if (!open) close();
            }
        "
    >
        <DialogContent @escape-key-down="close">
            <DialogHeader>
                <DialogTitle>Conferma lo spostamento dei budget</DialogTitle>
                <DialogDescription
                    >La nuova sottocategoria riceverà questi budget. Il
                    movimento, le categorie e i budget saranno salvati insieme.
                    Annullando non viene salvato nulla.</DialogDescription
                >
            </DialogHeader>
            <ul class="max-h-80 space-y-3 overflow-y-auto text-sm">
                <li
                    v-for="(
                        transfer, index
                    ) in categoryConfirmation?.transfers ?? []"
                    :key="index"
                    class="rounded-xl border p-3"
                >
                    <p class="font-medium">
                        {{ transfer.source }} → {{ transfer.destination }}
                    </p>
                    <p>
                        {{ transfer.month }}/{{ transfer.year }} ·
                        {{ transfer.amount }} {{ transfer.currency }}
                    </p>
                </li>
            </ul>
            <div class="flex flex-wrap justify-end gap-2">
                <Button variant="outline" @click="close">Annulla</Button>
                <Button @click="categoryConfirmation?.resolve(true)"
                    >Conferma e salva tutto</Button
                >
            </div>
        </DialogContent>
    </Dialog>
</template>
