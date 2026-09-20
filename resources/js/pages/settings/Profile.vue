<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import ImageUpload from '@/components/ImageUpload.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    pendingEmail?: string | null;
    className?: string;
    canDeleteAccount?: boolean;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

const page = usePage<SharedData>();
const user = page.props.auth.user as User;
const isCustomer = computed(() => page.props.auth.role === 'customer');

const form = useForm({
    first_name: user.first_name,
    last_name: user.last_name ?? '',
    email: user.email,
    photo: null as File | null,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Profile information" description="Update your name and email address" />

                <div
                    v-if="status && status !== 'verification-link-sent' && status !== 'email-change-sent'"
                    class="rounded-md bg-green-50 px-3 py-2 text-sm font-medium text-green-700 dark:bg-green-500/10 dark:text-green-400"
                >
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label>Profile photo</Label>
                        <ImageUpload
                            :model-value="form.photo"
                            :current="user.avatar ?? null"
                            shape="circle"
                            @update:model-value="(f) => (form.photo = f)"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="first_name">First name</Label>
                        <Input
                            id="first_name"
                            class="mt-1 block w-full"
                            v-model="form.first_name"
                            required
                            autocomplete="given-name"
                            placeholder="First name"
                        />
                        <InputError class="mt-2" :message="form.errors.first_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="last_name">Last name</Label>
                        <Input id="last_name" class="mt-1 block w-full" v-model="form.last_name" autocomplete="family-name" placeholder="Last name" />
                        <InputError class="mt-2" :message="form.errors.last_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                        <p v-if="isCustomer" class="mt-1 text-xs text-muted-foreground">
                            This is also your sign-in email. Changing it sends a confirmation link to the new address — the change takes effect
                            only after you confirm it there.
                        </p>
                        <div
                            v-if="isCustomer && status === 'email-change-sent'"
                            class="mt-2 rounded-md bg-sky-50 px-3 py-2 text-sm font-medium text-sky-700 dark:bg-sky-500/10 dark:text-sky-300"
                        >
                            Confirmation sent{{ pendingEmail ? ` to ${pendingEmail}` : '' }}. Your email will change once you click the link in
                            that inbox.
                        </div>
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="mt-2 text-sm text-neutral-800">
                            Your email address is unverified.
                            <Link
                                :href="route('verification.send')"
                                method="post"
                                as="button"
                                class="focus:outline-hidden rounded-md text-sm text-neutral-600 underline hover:text-neutral-900 focus:ring-2 focus:ring-offset-2"
                            >
                                Click here to re-send the verification email.
                            </Link>
                        </p>

                        <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600">
                            A new verification link has been sent to your email address.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">Save</Button>

                        <TransitionRoot
                            :show="form.recentlySuccessful"
                            enter="transition ease-in-out"
                            enter-from="opacity-0"
                            leave="transition ease-in-out"
                            leave-to="opacity-0"
                        >
                            <p class="text-sm text-neutral-600">Saved.</p>
                        </TransitionRoot>
                    </div>
                </form>
            </div>

            <DeleteUser v-if="canDeleteAccount" />
        </SettingsLayout>
    </AppLayout>
</template>
