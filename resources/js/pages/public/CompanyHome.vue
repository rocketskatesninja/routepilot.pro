<script setup lang="ts">
import CompanyMark from '@/components/portal/CompanyMark.vue';
import PortalHero from '@/components/portal/PortalHero.vue';
import { Button } from '@/components/ui/button';
import { hexToHslChannels } from '@/composables/useBrand';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

/** A company's web address when it has switched its website off: its name, and a way in for its customers. */
const props = defineProps<{ company: { name: string; color: string; logo: string | null } }>();

// The company's colour, set on the page itself so it's right from the first paint.
const brandVars = computed(() => {
    const c = hexToHslChannels(props.company.color);
    return c ? { '--brand': c, '--primary': c, '--ring': c } : {};
});
</script>

<template>
    <Head :title="props.company.name">
        <meta name="robots" content="noindex" />
    </Head>

    <div class="bg-background text-foreground min-h-svh" :style="brandVars">
        <main class="mx-auto flex max-w-xl flex-col gap-8 px-4 py-10">
            <CompanyMark :company="props.company" />

            <PortalHero>
                Welcome to {{ props.company.name }}
                <template #sub>
                    <p class="text-balance">Already a customer? Log in to see your visit reports and request a service.</p>
                </template>
                <template #actions>
                    <Button as-child><a href="/login">Customer log in</a></Button>
                </template>
            </PortalHero>
        </main>
    </div>
</template>
