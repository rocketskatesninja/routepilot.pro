<script setup lang="ts">
import { Building2, FlaskConical, User, Waves } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        src?: string | null;
        type?: 'pool' | 'person' | 'inventory' | 'tenant';
        name?: string | null;
        size?: 'sm' | 'md' | 'lg';
        shape?: 'square' | 'circle';
    }>(),
    { src: null, type: 'person', name: null, size: 'md', shape: 'square' },
);

const icons = { pool: Waves, person: User, inventory: FlaskConical, tenant: Building2 };
const icon = computed(() => icons[props.type]);
const sizeClass = computed(() => ({ sm: 'size-7', md: 'size-10', lg: 'size-20' })[props.size]);
const iconClass = computed(() => ({ sm: 'size-3.5', md: 'size-5', lg: 'size-9' })[props.size]);
const shapeClass = computed(() => (props.shape === 'circle' ? 'rounded-full' : 'rounded-md'));
</script>

<template>
    <div :class="[sizeClass, shapeClass, 'flex shrink-0 items-center justify-center overflow-hidden border border-border bg-muted/40']">
        <img v-if="src" :src="src" :alt="name ?? ''" class="h-full w-full object-cover" />
        <component :is="icon" v-else :class="[iconClass, 'text-muted-foreground']" />
    </div>
</template>
