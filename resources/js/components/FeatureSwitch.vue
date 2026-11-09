<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useFeature } from '@/composables/useFeature';
import { type TenantFeature } from '@/types';
import { router } from '@inertiajs/vue3';

const props = defineProps<{ feature: TenantFeature; label: string }>();

const on = useFeature(props.feature);

// Saved the moment it's flipped; the shared tenant prop then updates every screen.
const save = (value: boolean) => router.patch('/company/features', { [props.feature]: value }, { preserveScroll: true });
</script>

<template>
    <div class="grid gap-1.5">
        <Label :for="feature" class="flex items-center gap-2 font-normal">
            <Checkbox :id="feature" :checked="on" @update:checked="save" />
            <span>{{ label }}</span>
        </Label>
        <p class="text-muted-foreground pl-7 text-sm"><slot /></p>
    </div>
</template>
