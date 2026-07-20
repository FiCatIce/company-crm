<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { UserCheck, UserCog, Users } from '@lucide/vue';
import { computed } from 'vue';
import SupportAssignmentController from '@/actions/App/Http/Controllers/SupportAssignmentController';
import TeamController from '@/actions/App/Http/Controllers/TeamController';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { TeamAgentRow, TeamRepRow } from '@/types/crm';

const props = defineProps<{
    kind: 'manager' | 'sales' | 'support';
    team: { id: number; name: string } | null;
    reps: TeamRepRow[];
    agents: TeamAgentRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tim Saya', href: TeamController.index() },
];

const initial = (name: string) => name.trim().charAt(0).toUpperCase();

const typeStyles: Record<string, string> = {
    sales: 'border-blue-200 bg-blue-50 text-blue-700',
    cs: 'border-violet-200 bg-violet-50 text-violet-700',
    maintenance: 'border-amber-200 bg-amber-50 text-amber-700',
};
const typeBadge = (value?: string) =>
    (value && typeStyles[value]) || 'border-border bg-muted text-foreground';

const subtitle = computed(() => {
    if (props.kind === 'manager') {
        return 'Sales di tim Anda, ukuran buku mereka, dan siapa yang membantu menangani customer masing-masing.';
    }

    if (props.kind === 'sales') {
        return 'CS & Maintenance yang membantu menangani customer Anda.';
    }

    return 'Sales yang menugaskan Anda — customer merekalah yang bisa Anda akses.';
});

const agentsHeading = computed(() => {
    if (props.kind === 'manager') {
        return 'CS & Maintenance di tim';
    }

    if (props.kind === 'sales') {
        return 'Support Saya';
    }

    return 'Sales yang Anda layani';
});
</script>

<template>
    <Head title="Tim Saya" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 sm:p-6 lg:p-8"
        >
            <div class="flex items-start gap-4">
                <div
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <Users class="size-5" />
                </div>
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1
                            class="text-2xl font-semibold tracking-tight text-foreground"
                        >
                            Tim Saya
                        </h1>
                        <span
                            v-if="props.team"
                            class="inline-flex items-center rounded-full border border-border bg-muted px-2.5 py-0.5 text-xs font-medium text-foreground"
                            >{{ props.team.name }}</span
                        >
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ subtitle }}
                    </p>
                </div>
            </div>

            <div
                v-if="!props.team"
                class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
            >
                Anda belum tergabung dalam tim. Hubungi manager Anda untuk
                didaftarkan.
            </div>

            <!-- Manager: reps + who supports each of them -->
            <section
                v-if="props.kind === 'manager'"
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
            >
                <div class="border-b border-border px-6 py-4">
                    <h2 class="text-sm font-semibold text-foreground">
                        Sales di tim
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        {{ props.reps.length }} orang — beserta support yang
                        mereka tugaskan.
                    </p>
                </div>

                <ul v-if="props.reps.length" class="divide-y divide-border">
                    <li
                        v-for="rep in props.reps"
                        :key="rep.id"
                        class="px-6 py-4 transition-colors hover:bg-accent/50"
                    >
                        <div class="flex flex-wrap items-center gap-3">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                            >
                                {{ initial(rep.name) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-foreground">
                                    {{ rep.name }}
                                </div>
                                <div
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ rep.email }}
                                </div>
                            </div>
                            <span
                                v-if="rep.type"
                                class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                :class="typeBadge(rep.type.value)"
                                >{{ rep.type.label }}</span
                            >
                            <span
                                class="text-sm text-muted-foreground tabular-nums"
                            >
                                {{ rep.customers_count }} customer
                            </span>
                        </div>

                        <div
                            class="mt-2 flex flex-wrap items-center gap-1.5 pl-12"
                        >
                            <span class="text-xs text-muted-foreground"
                                >Didukung:</span
                            >
                            <template v-if="rep.assignees.length">
                                <span
                                    v-for="a in rep.assignees"
                                    :key="a.id"
                                    class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                    :class="typeBadge(a.type?.value)"
                                >
                                    {{ a.name }}
                                    <span v-if="a.type" class="ml-1 opacity-70"
                                        >· {{ a.type.label }}</span
                                    >
                                </span>
                            </template>
                            <span v-else class="text-xs text-muted-foreground"
                                >belum ada</span
                            >
                        </div>
                    </li>
                </ul>

                <div
                    v-else
                    class="px-6 py-10 text-center text-sm text-muted-foreground"
                >
                    Belum ada sales di tim Anda.
                </div>
            </section>

            <!-- Agents band: team support (manager) / my support (sales) / reps served (support) -->
            <section
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-6 py-4"
                >
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            {{ agentsHeading }}
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            {{ props.agents.length }} orang
                        </p>
                    </div>
                    <Button
                        v-if="props.kind === 'sales'"
                        as-child
                        variant="secondary"
                        size="sm"
                    >
                        <Link :href="SupportAssignmentController.index()"
                            >Kelola</Link
                        >
                    </Button>
                </div>

                <ul v-if="props.agents.length" class="divide-y divide-border">
                    <li
                        v-for="a in props.agents"
                        :key="a.id"
                        class="flex flex-wrap items-center gap-3 px-6 py-4 transition-colors hover:bg-accent/50"
                    >
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-semibold text-muted-foreground"
                        >
                            {{ initial(a.name) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-foreground">
                                {{ a.name }}
                            </div>
                            <div class="truncate text-xs text-muted-foreground">
                                {{ a.email }}
                            </div>
                        </div>
                        <span
                            v-if="a.type"
                            class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                            :class="typeBadge(a.type.value)"
                            >{{ a.type.label }}</span
                        >
                    </li>
                </ul>

                <div v-else class="px-6 py-10 text-center">
                    <div
                        class="mx-auto flex max-w-sm flex-col items-center gap-2"
                    >
                        <div
                            class="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground"
                        >
                            <component
                                :is="
                                    props.kind === 'support'
                                        ? UserCheck
                                        : UserCog
                                "
                                class="size-5"
                            />
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{
                                props.kind === 'support'
                                    ? 'Belum ada sales yang menugaskan Anda.'
                                    : props.kind === 'sales'
                                      ? 'Belum ada support yang Anda tugaskan.'
                                      : 'Belum ada CS/Maintenance di tim Anda.'
                            }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
