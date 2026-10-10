<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import { computed } from 'vue';
import SectionShell from '../primitives/SectionShell.vue';
import type { SectionProps } from '../types';

const props = defineProps<SectionProps>();
const c = computed(() => props.content as { heading?: string });
const team = computed(() => props.live.team ?? []);
</script>

<template>
    <SectionShell
        v-if="team.length || editing"
        id="team"
        tinted
        :heading="c.heading || 'Meet the team'"
        subheading="Certified technicians who care about your pool"
    >
        <div v-if="team.length" class="stagger-children reveal flex flex-wrap justify-center gap-8">
            <div v-for="m in team" :key="m.user_id" class="team-card w-56 text-center">
                <div class="mb-3 flex justify-center">
                    <EntityAvatar :src="m.avatar" type="person" :name="m.name" size="lg" shape="circle" />
                </div>
                <p class="text-foreground font-semibold">{{ m.name }}</p>
                <p v-if="m.title" class="text-primary text-sm">{{ m.title }}</p>
                <p v-if="m.bio" class="text-muted-foreground mt-2 text-sm leading-relaxed">{{ m.bio }}</p>
            </div>
        </div>
        <p v-else class="border-border text-muted-foreground rounded-xl border border-dashed p-6 text-center text-sm">
            Add team members in the editor.
        </p>
    </SectionShell>
</template>
