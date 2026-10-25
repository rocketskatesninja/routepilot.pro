<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';
import { computed } from 'vue';

interface Props {
    user: User;
    showEmail?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const { getInitials } = useInitials();

const showAvatar = computed(() => !!props.user.avatar);
</script>

<template>
    <!-- In the collapsed rail the avatar is an icon-sized square like the links (--rail-icon, app.css). -->
    <Avatar
        class="h-8 w-8 overflow-hidden rounded-lg group-data-[collapsible=icon]:size-[var(--rail-icon,2rem)] group-data-[collapsible=icon]:text-[calc(var(--rail-icon,2rem)*0.4)]"
    >
        <AvatarImage v-if="showAvatar" :src="user.avatar" :alt="user.name" />
        <AvatarFallback class="rounded-lg text-black dark:text-white">
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" class="text-muted-foreground truncate text-xs">{{ user.email }}</span>
    </div>
</template>
