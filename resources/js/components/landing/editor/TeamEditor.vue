<script setup lang="ts">
import { TEXTAREA } from '@/components/landing/editor/choice';
import { Input } from '@/components/ui/input';

interface Member {
    user_id: number;
    title: string | null;
    bio: string | null;
}

/** The landing editor's team panel: a tech is on the site once ticked, with an optional title and bio. */
const props = defineProps<{ section: { members: Member[] }; agents: { id: number; name: string }[] }>();
const members = props.section.members;

const memberOf = (id: number) => members.find((m) => m.user_id === id);
function toggleMember(id: number) {
    const i = members.findIndex((m) => m.user_id === id);
    if (i >= 0) members.splice(i, 1);
    else members.push({ user_id: id, title: null, bio: null });
}
</script>

<template>
    <p v-if="!agents.length" class="text-muted-foreground text-xs">No team members to show yet.</p>
    <div v-for="a in agents" :key="a.id" class="border-border bg-background space-y-2 rounded-lg border p-3">
        <div class="flex items-center gap-3">
            <label class="flex flex-1 items-center gap-2 font-medium">
                <input type="checkbox" :checked="!!memberOf(a.id)" @change="toggleMember(a.id)" /> {{ a.name }}
            </label>
            <Input v-if="memberOf(a.id)" v-model="memberOf(a.id)!.title" placeholder="Title (e.g. Lead Technician)" class="flex-1" />
        </div>
        <textarea v-if="memberOf(a.id)" v-model="memberOf(a.id)!.bio" rows="2" :class="TEXTAREA" placeholder="Short bio" />
    </div>
</template>
