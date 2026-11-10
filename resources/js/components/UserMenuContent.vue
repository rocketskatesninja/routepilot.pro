<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { useBigView } from '@/composables/useBigView';
import { logOut } from '@/lib/push';
import type { User } from '@/types';
import { Link } from '@inertiajs/vue3';
import { LogOut, Settings, ZoomIn, ZoomOut } from 'lucide-vue-next';

interface Props {
    user: User;
}

defineProps<Props>();

const { on: bigView, toggle: toggleBigView } = useBigView();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="route('profile.edit')" as="button">
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem @select="toggleBigView">
            <component :is="bigView ? ZoomOut : ZoomIn" class="mr-2 h-4 w-4" />
            {{ bigView ? 'Normal view' : 'Big view' }}
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem @select="logOut">
        <LogOut class="mr-2 h-4 w-4" />
        Log out
    </DropdownMenuItem>
</template>
