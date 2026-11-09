<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useCustomerBilling } from '@/composables/useCustomerBilling';
import { router } from '@inertiajs/vue3';

withDefaults(defineProps<{ label?: string }>(), { label: 'Bill customers through RoutePilot' });

const billing = useCustomerBilling();

// Saved the moment it's flipped; the shared tenant prop then updates every screen.
const save = (on: boolean) => router.patch('/company/features', { customer_billing: on }, { preserveScroll: true });
</script>

<template>
    <div class="grid gap-1.5">
        <Label for="customer_billing" class="flex items-center gap-2 font-normal">
            <Checkbox id="customer_billing" :checked="billing" @update:checked="save" />
            <span>{{ label }}</span>
        </Label>
        <p class="text-muted-foreground pl-7 text-sm">
            <slot>
                Charges, balances, invoices and online payments. Turn this off if you bill another way: nothing is billed, reminded or charged, and
                what’s already on record stays there.
            </slot>
        </p>
    </div>
</template>
