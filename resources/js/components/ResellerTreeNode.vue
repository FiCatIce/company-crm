<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import ResellerController from '@/actions/App/Http/Controllers/ResellerController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type ResellerNode = {
    id: number;
    name: string;
    parent_id: number | null;
    customers_count: number;
    children: ResellerNode[];
};

defineProps<{
    node: ResellerNode;
    can: { update: boolean; delete: boolean };
    depth?: number;
}>();
</script>

<template>
    <li>
        <div
            class="flex items-center justify-between gap-3 rounded-md py-2.5 pr-2 transition-colors hover:bg-accent/50"
            :style="{ paddingLeft: `${(depth ?? 0) * 20 + 12}px` }"
        >
            <div class="flex min-w-0 items-center gap-2">
                <span
                    class="text-muted-foreground"
                    :class="{ 'opacity-0': node.children.length === 0 }"
                    aria-hidden="true"
                >
                    ▸
                </span>
                <span class="truncate font-medium text-foreground">{{
                    node.name
                }}</span>
                <span
                    class="shrink-0 rounded-full border border-border bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                >
                    {{ node.customers_count }} customer
                </span>
            </div>

            <div class="flex shrink-0 items-center gap-1">
                <Button v-if="can.update" as-child variant="ghost" size="sm">
                    <Link :href="ResellerController.edit(node.id)">Edit</Link>
                </Button>

                <Dialog v-if="can.delete">
                    <DialogTrigger as-child>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                        >
                            Hapus
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <Form
                            v-bind="ResellerController.destroy.form(node.id)"
                            :options="{ preserveScroll: true }"
                            class="space-y-6"
                            v-slot="{ processing }"
                        >
                            <DialogHeader class="space-y-3">
                                <DialogTitle>Hapus reseller?</DialogTitle>
                                <DialogDescription>
                                    Data <strong>{{ node.name }}</strong> akan
                                    dihapus dan anak reseller-nya menjadi
                                    reseller tingkat atas. Reseller yang masih
                                    memiliki customer atau transaksi tidak dapat
                                    dihapus.
                                </DialogDescription>
                            </DialogHeader>

                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button variant="secondary" type="button">
                                        Batal
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    :disabled="processing"
                                >
                                    Hapus
                                </Button>
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>

        <ul v-if="node.children.length">
            <ResellerTreeNode
                v-for="child in node.children"
                :key="child.id"
                :node="child"
                :can="can"
                :depth="(depth ?? 0) + 1"
            />
        </ul>
    </li>
</template>
