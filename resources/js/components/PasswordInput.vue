<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { Eye, EyeOff } from 'lucide-vue-next';
import { type HTMLAttributes, ref } from 'vue';

/**
 * Password field with a show/hide toggle. Drop-in for the ui <Input> on any
 * password entry — forwards id / autocomplete / placeholder / autofocus / etc.
 * via $attrs, and exposes focus() so callers can still focus it on error.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps<{
    modelValue?: string;
    class?: HTMLAttributes['class'];
}>();
const emit = defineEmits<{ 'update:modelValue': [string] }>();

const show = ref(false);
const input = ref<InstanceType<typeof Input> | null>(null);

function focus() {
    (input.value?.$el as HTMLInputElement | undefined)?.focus?.();
}
defineExpose({ focus });
</script>

<template>
    <div class="relative">
        <Input
            ref="input"
            v-bind="$attrs"
            :type="show ? 'text' : 'password'"
            :model-value="modelValue"
            :class="cn('pr-10', props.class)"
            @update:model-value="(v) => emit('update:modelValue', String(v))"
        />
        <button
            type="button"
            :aria-label="show ? 'Hide password' : 'Show password'"
            :aria-pressed="show"
            tabindex="-1"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground hover:text-foreground"
            @click="show = !show"
        >
            <EyeOff v-if="show" class="size-4" />
            <Eye v-else class="size-4" />
        </button>
    </div>
</template>
