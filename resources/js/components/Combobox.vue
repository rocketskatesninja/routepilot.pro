<script setup lang="ts">
import { computed, ref, watch } from 'vue';

/**
 * A typeahead select: a text input that filters a list of options as you type,
 * with keyboard navigation. Emits the chosen option's id (or '' when cleared).
 */
interface Option {
    id: number;
    name: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: number | string;
        options: Option[];
        placeholder?: string;
        disabled?: boolean;
        id?: string;
    }>(),
    { placeholder: 'Search…', disabled: false },
);

const emit = defineEmits<{ 'update:modelValue': [number | string] }>();

const selectedName = computed(() => props.options.find((o) => o.id === props.modelValue)?.name ?? '');
const query = ref(selectedName.value);
const open = ref(false);
const highlight = ref(0);

// Reflect external selection changes (e.g. an edit form populating the field).
watch(selectedName, (name) => {
    if (!open.value) {
        query.value = name;
    }
});

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (q === '' || q === selectedName.value.toLowerCase()) {
        return props.options;
    }
    return props.options.filter((o) => o.name.toLowerCase().includes(q));
});

function choose(o: Option) {
    emit('update:modelValue', o.id);
    query.value = o.name;
    open.value = false;
}
function onInput() {
    open.value = true;
    highlight.value = 0;
    if (query.value === '') {
        emit('update:modelValue', '');
    }
}
function onFocus() {
    if (!props.disabled) {
        open.value = true;
        highlight.value = 0;
    }
}
function onBlur() {
    // Defer so an option mousedown registers; then revert to the committed name.
    window.setTimeout(() => {
        open.value = false;
        query.value = selectedName.value;
    }, 120);
}
function move(delta: number) {
    if (!open.value) {
        open.value = true;
        return;
    }
    const n = filtered.value.length;
    if (n > 0) {
        highlight.value = (highlight.value + delta + n) % n;
    }
}
function enter() {
    const o = filtered.value[highlight.value];
    if (o) {
        choose(o);
    }
}
</script>

<template>
    <div class="relative">
        <input
            :id="id"
            v-model="query"
            type="text"
            autocomplete="off"
            role="combobox"
            :aria-expanded="open"
            :placeholder="placeholder"
            :disabled="disabled"
            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm disabled:opacity-60"
            @input="onInput"
            @focus="onFocus"
            @blur="onBlur"
            @keydown.down.prevent="move(1)"
            @keydown.up.prevent="move(-1)"
            @keydown.enter.prevent="enter"
            @keydown.esc="open = false"
        />
        <ul
            v-if="open && filtered.length"
            class="absolute z-30 mt-1 max-h-56 w-full overflow-auto rounded-md border border-border bg-popover py-1 text-popover-foreground shadow-md"
        >
            <li
                v-for="(o, i) in filtered"
                :key="o.id"
                class="cursor-pointer px-3 py-1.5 text-sm"
                :class="i === highlight ? 'bg-muted text-foreground' : 'hover:bg-muted'"
                @mousedown.prevent="choose(o)"
                @mouseenter="highlight = i"
            >
                {{ o.name }}
            </li>
        </ul>
        <p
            v-else-if="open && query.trim() !== ''"
            class="absolute z-30 mt-1 w-full rounded-md border border-border bg-popover px-3 py-2 text-sm text-muted-foreground shadow-md"
        >
            No matches.
        </p>
    </div>
</template>
