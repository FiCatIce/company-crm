<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useInitials } from '@/composables/useInitials';

const page = usePage();
const user = computed(() => page.props.auth.user);

const { getInitials } = useInitials();
const hasAvatar = computed(
    () => !!user.value.avatar && user.value.avatar !== '',
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            data-test="user-menu-button"
            class="flex items-center gap-2 rounded-full py-1 pr-2 pl-1 text-sm transition-colors outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring/50"
        >
            <Avatar class="size-8 rounded-full">
                <AvatarImage
                    v-if="hasAvatar"
                    :src="user.avatar!"
                    :alt="user.name"
                />
                <AvatarFallback
                    class="rounded-full bg-primary/10 text-xs font-semibold text-primary"
                >
                    {{ getInitials(user.name) }}
                </AvatarFallback>
            </Avatar>
            <span
                class="hidden max-w-[10rem] truncate font-medium text-foreground sm:block"
            >
                {{ user.name }}
            </span>
            <ChevronDown class="size-4 text-muted-foreground" />
        </DropdownMenuTrigger>
        <DropdownMenuContent
            class="w-56 rounded-lg"
            align="end"
            :side-offset="8"
        >
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
