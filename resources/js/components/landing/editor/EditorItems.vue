<script setup lang="ts">
// A list the company adds to (services, reviews, questions): each item in a card
// with its number and a remove button, its fields in the slot, then "Add ...".
import { Button } from '@/components/ui/button';
import { Plus, Trash2 } from 'lucide-vue-next';

const items = defineModel<Record<string, unknown>[]>({ required: true });
const props = defineProps<{ noun: string; blank: Record<string, unknown> }>();

const add = () => (items.value = [...items.value, { ...props.blank }]);
const remove = (i: number) => (items.value = items.value.filter((_, j) => j !== i));
</script>

<template>
    <div v-for="(item, i) in items" :key="i" class="border-border bg-background space-y-2 rounded-lg border p-3">
        <div class="flex items-center justify-between">
            <span class="text-muted-foreground text-xs font-medium">{{ noun }} {{ i + 1 }}</span>
            <button type="button" :aria-label="`Remove ${noun.toLowerCase()} ${i + 1}`" @click="remove(i)">
                <Trash2 class="text-muted-foreground size-4 hover:text-red-600" />
            </button>
        </div>
        <slot :item="item" />
    </div>
    <Button type="button" variant="outline" size="sm" @click="add"><Plus class="mr-1 size-3.5" /> Add {{ noun.toLowerCase() }}</Button>
</template>
