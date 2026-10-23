<script setup lang="ts">
import { computed } from 'vue';
import SectionShell from '../primitives/SectionShell.vue';
import type { SectionProps } from '../types';

const props = defineProps<SectionProps>();
const team = computed(() => props.live.team ?? []);
const initials = (name: string) =>
    name
        .split(/\s+/)
        .map((w) => w[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
</script>

<template>
    <SectionShell :section="content" tinted>
        <ul v-if="team.length" class="grid grid-cols-2 gap-x-6 gap-y-10 @5xl:grid-cols-3">
            <li v-for="m in team" :key="m.user_id">
                <img v-if="m.avatar" :src="m.avatar" :alt="m.name" loading="lazy" class="aspect-[4/5] w-full rounded-2xl object-cover" />
                <div
                    v-else
                    class="site-font text-primary flex aspect-[4/5] w-full items-center justify-center rounded-2xl text-5xl font-bold"
                    style="background: color-mix(in srgb, var(--tenant-primary) 14%, hsl(var(--background)))"
                    aria-hidden="true"
                >
                    {{ initials(m.name) }}
                </div>
                <p class="text-foreground mt-4 text-lg font-semibold">{{ m.name }}</p>
                <p v-if="m.title" class="text-muted-foreground">{{ m.title }}</p>
                <p v-if="m.bio" class="text-muted-foreground mt-2 text-base leading-relaxed">{{ m.bio }}</p>
            </li>
        </ul>
        <p v-else class="border-border text-muted-foreground rounded-xl border border-dashed p-6 text-sm">Add team members in the editor.</p>
    </SectionShell>
</template>
