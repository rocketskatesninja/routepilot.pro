<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { LoaderCircle } from 'lucide-vue-next';

defineOptions({ inheritAttrs: false });

defineProps<{ processing?: boolean }>();
</script>

<template>
    <Button type="submit" :disabled="processing" v-bind="$attrs">
        <LoaderCircle v-if="processing" class="mr-1 h-4 w-4 animate-spin" />
        <slot />
    </Button>
</template>
