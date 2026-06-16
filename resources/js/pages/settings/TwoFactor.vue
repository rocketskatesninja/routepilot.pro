<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';

interface Props {
    enabled: boolean;
    pending: boolean;
    qrSvg: string | null;
    setupKey: string | null;
    recoveryCodes: string[] | null;
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'Two-factor authentication', href: '/settings/two-factor' }];

const confirmForm = useForm({ code: '' });

const enable = () => router.post(route('two-factor.enable'), {}, { preserveScroll: true });
const confirm = () => confirmForm.post(route('two-factor.confirm'), { preserveScroll: true, onSuccess: () => confirmForm.reset('code') });
const disable = () => router.delete(route('two-factor.disable'), { preserveScroll: true });
const regenerate = () => router.post(route('two-factor.recovery-codes'), {}, { preserveScroll: true });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Two-factor authentication" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Two-factor authentication"
                    description="Add an extra layer of security by requiring a code from your authenticator app when you sign in."
                />

                <!-- Disabled -->
                <div v-if="!enabled && !pending" class="space-y-4">
                    <p class="text-sm text-muted-foreground">Two-factor authentication is currently <strong>off</strong>.</p>
                    <Button @click="enable">Enable two-factor</Button>
                </div>

                <!-- Pending: scan + confirm -->
                <div v-else-if="pending" class="space-y-6">
                    <p class="text-sm text-muted-foreground">
                        Scan this QR code with your authenticator app (Google Authenticator, 1Password, Authy…), then enter the 6-digit code to
                        finish.
                    </p>

                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                        <div class="inline-block rounded-lg border bg-white p-3" v-html="qrSvg" />
                        <div class="space-y-2">
                            <Label>Or enter this key manually</Label>
                            <code class="block break-all rounded bg-muted px-3 py-2 font-mono text-sm">{{ setupKey }}</code>
                        </div>
                    </div>

                    <div v-if="recoveryCodes" class="space-y-2">
                        <Label>Recovery codes</Label>
                        <p class="text-xs text-muted-foreground">Store these somewhere safe. Each can be used once if you lose your device.</p>
                        <div class="grid grid-cols-2 gap-1 rounded bg-muted p-3 font-mono text-sm">
                            <span v-for="c in recoveryCodes" :key="c">{{ c }}</span>
                        </div>
                    </div>

                    <form @submit.prevent="confirm" class="space-y-2">
                        <Label for="code">Confirmation code</Label>
                        <div class="flex items-center gap-3">
                            <Input
                                id="code"
                                v-model="confirmForm.code"
                                class="w-40 text-center tracking-[0.3em]"
                                placeholder="123456"
                                autocomplete="one-time-code"
                                autofocus
                            />
                            <Button :disabled="confirmForm.processing">Confirm</Button>
                            <Button type="button" variant="ghost" @click="disable">Cancel</Button>
                        </div>
                        <InputError :message="confirmForm.errors.code" />
                    </form>
                </div>

                <!-- Enabled -->
                <div v-else class="space-y-4">
                    <p class="text-sm text-muted-foreground">Two-factor authentication is <strong>on</strong>.</p>

                    <div v-if="recoveryCodes" class="space-y-2">
                        <Label>New recovery codes</Label>
                        <p class="text-xs text-muted-foreground">Your old codes no longer work. Store these somewhere safe.</p>
                        <div class="grid grid-cols-2 gap-1 rounded bg-muted p-3 font-mono text-sm">
                            <span v-for="c in recoveryCodes" :key="c">{{ c }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <Button variant="outline" @click="regenerate">Regenerate recovery codes</Button>
                        <Button variant="destructive" @click="disable">Turn off</Button>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
