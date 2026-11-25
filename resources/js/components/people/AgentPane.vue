<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import Chip from '@/components/pane/Chip.vue';
import PaneFacts from '@/components/pane/PaneFacts.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import PaneSection from '@/components/pane/PaneSection.vue';
import { Button } from '@/components/ui/button';
import { composeLink, telLink } from '@/lib/links';
import { type AgentDetail } from '@/types/people';
import { router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';

/** An agent's page in the People pane. `gone` = they were removed. */
const props = defineProps<{ agent: AgentDetail; canManage: boolean }>();
const emit = defineEmits<{ edit: []; gone: [] }>();

const summary = (a: AgentDetail) =>
    `Agent. ${a.stats.completed_visits} visit${a.stats.completed_visits === 1 ? '' : 's'} done, ${a.stats.this_week} this week.`;
function remove() {
    if (!confirm('Remove this agent? Their visit history is preserved.')) return;
    router.delete(`/agents/${props.agent.id}`, { preserveScroll: true, onSuccess: () => emit('gone') });
}
</script>

<template>
    <div class="space-y-6">
        <PaneHeader :title="agent.name" :summary="summary(agent)">
            <template #media>
                <EntityAvatar :src="agent.photo_url" type="person" :name="agent.name" size="lg" shape="circle" />
            </template>
            <template #badge>
                <Chip :tone="agent.is_active ? 'good' : 'muted'">{{ agent.is_active ? 'Active' : 'Inactive' }}</Chip>
            </template>
            <template v-if="canManage" #actions>
                <Button size="sm" variant="outline" @click="emit('edit')"><Pencil class="mr-1 size-3.5" /> Edit</Button>
                <Button size="sm" variant="outline" class="text-red-600 hover:text-red-600 dark:text-red-400" @click="remove"
                    ><Trash2 class="mr-1 size-3.5" /> Remove</Button
                >
            </template>
        </PaneHeader>

        <PaneSection title="Contact">
            <PaneFacts
                :facts="[
                    { label: 'Email', value: agent.email, href: composeLink(agent.id, 'agent') },
                    { label: 'Phone', value: agent.phone, href: agent.phone ? telLink(agent.phone) : null },
                ]"
            />
        </PaneSection>

        <PaneSection title="Access">
            <p class="text-muted-foreground text-sm">
                {{
                    agent.agent_plus
                        ? 'Agent+ is on: they can reorder and skip stops on their own route, and see every report.'
                        : 'They work the route as it’s planned and see only their own reports. Turn on Agent+ to let them reorder and skip stops and see every report.'
                }}
            </p>
        </PaneSection>
    </div>
</template>
