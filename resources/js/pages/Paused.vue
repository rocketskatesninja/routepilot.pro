<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { PauseCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();
const isAdmin = computed(() => page.props.auth.role === 'tenant_admin');
const company = computed(() => page.props.tenant?.name ?? 'This account');
</script>

<template>
    <Head title="Account paused" />

    <div class="flex min-h-screen flex-col items-center justify-center gap-6 bg-background p-6 text-center">
        <div class="flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
            <PauseCircle class="size-8" />
        </div>

        <div class="max-w-md space-y-2">
            <h1 class="text-2xl font-semibold">{{ company }} is paused</h1>
            <p class="text-muted-foreground">
                <template v-if="isAdmin"> Your free trial has ended. Add a subscription to bring your team back online. </template>
                <template v-else> Your company's RoutePilot account is paused. Please reach out to your account owner to restore access. </template>
            </p>
        </div>

        <div class="flex items-center gap-3">
            <Link v-if="isAdmin" :href="route('billing.show')">
                <Button>Go to billing</Button>
            </Link>
            <Link method="post" :href="route('logout')" as="button">
                <Button :variant="isAdmin ? 'outline' : 'default'">Sign out</Button>
            </Link>
        </div>

        <div class="mt-4 flex items-center gap-2 text-sm text-muted-foreground">
            <AppLogoIcon class="size-5 fill-current" />
            <span>RoutePilot</span>
        </div>
    </div>
</template>
