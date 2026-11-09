<script setup lang="ts">
import { computed, ref, watch } from 'vue';

/**
 * A typeahead select: a text input that filters a list of options as you type,
 * with keyboard navigation. Emits the chosen option's id (or '' when cleared).
 *
 * `touch` is for picking on a phone: bigger targets, each tap starts a fresh search
 * (emptying the box doesn't lose the choice; its row has its own remove button), the
 * box moves up clear of the keyboard, and picking closes the keyboard.
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
        label?: string;
        touch?: boolean;
    }>(),
    { placeholder: 'Search…', disabled: false },
);

const emit = defineEmits<{ 'update:modelValue': [number | string] }>();

const selectedName = computed(() => props.options.find((o) => o.id === props.modelValue)?.name ?? '');
const query = ref(selectedName.value);
const open = ref(false);
const highlight = ref(0);
const root = ref<HTMLElement | null>(null);
const input = ref<HTMLInputElement | null>(null);

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
    if (props.touch) {
        input.value?.blur();
    }
}
// Not v-model: it holds back text mid-composition, which on Android keyboards is every
// letter of a word, so the list wouldn't filter until the word was finished.
function onInput(event: Event) {
    query.value = (event.target as HTMLInputElement).value;
    open.value = true;
    highlight.value = 0;
    if (query.value === '' && !props.touch) {
        emit('update:modelValue', '');
    }
}
function onFocus() {
    if (!props.disabled) {
        open.value = true;
        highlight.value = 0;
        if (props.touch) {
            query.value = '';
            // Once the keyboard is up: to the top of the screen, so it doesn't cover the results.
            if (window.matchMedia('(pointer: coarse)').matches) {
                window.setTimeout(() => root.value?.scrollIntoView({ block: 'start', behavior: 'smooth' }), 300);
            }
        }
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
    <div ref="root" class="relative">
        <input
            :id="id"
            ref="input"
            :value="query"
            type="text"
            autocomplete="off"
            autocapitalize="none"
            autocorrect="off"
            spellcheck="false"
            role="combobox"
            :aria-label="label"
            :aria-expanded="open"
            :placeholder="touch && open && selectedName ? selectedName : placeholder"
            :disabled="disabled"
            class="border-input bg-background placeholder:text-muted-foreground w-full border px-3 disabled:opacity-60"
            :class="touch ? 'h-11 rounded-xl text-base' : 'h-9 rounded-md text-sm'"
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
            class="border-border bg-popover text-popover-foreground absolute z-30 mt-1 max-h-56 w-full overflow-auto overscroll-contain rounded-md border py-1 shadow-md"
            @mousedown.prevent
        >
            <li
                v-for="(o, i) in filtered"
                :key="o.id"
                class="cursor-pointer px-3"
                :class="[touch ? 'py-2.5 text-base' : 'py-1.5 text-sm', i === highlight ? 'bg-muted text-foreground' : 'hover:bg-muted']"
                @mousedown.prevent="choose(o)"
                @mouseenter="highlight = i"
            >
                {{ o.name }}
            </li>
        </ul>
        <p
            v-else-if="open && query.trim() !== ''"
            class="border-border bg-popover text-muted-foreground absolute z-30 mt-1 w-full rounded-md border px-3 py-2 text-sm shadow-md"
        >
            No matches.
        </p>
    </div>
</template>
