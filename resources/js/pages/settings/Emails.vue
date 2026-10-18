<script setup lang="ts">
import EmailChoices from '@/components/EmailChoices.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

/** A customer choosing which optional emails they get. Each switch saves right away. */
const props = defineProps<{ preferences: Record<string, boolean>; types: Record<string, [string, string]> }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Email settings', href: '/settings/emails' }];
const busy = ref(false);
const change = (type: string, on: boolean) =>
    router.patch(
        '/settings/emails',
        { [type]: on },
        { preserveScroll: true, onStart: () => (busy.value = true), onFinish: () => (busy.value = false) },
    );
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Email settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Emails" description="Choose which emails you get. Each switch saves right away." />
                <EmailChoices :types="props.types" :preferences="props.preferences" :busy="busy" @change="change" />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
