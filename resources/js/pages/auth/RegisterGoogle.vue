<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TextLink from '@/components/TextLink.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    email: string;
    first_name: string;
    last_name: string;
}>();

const form = useForm({
    company: '',
    first_name: props.first_name,
    last_name: props.last_name,
});

const submit = () => form.post(route('auth.google.register.store'));
</script>

<template>
    <AuthBase title="Finish creating your account" description="One more step — name your company to get started.">
        <Head title="Complete sign-up" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label>Signing in with Google as</Label>
                    <div class="flex items-center gap-2 rounded-md border border-input bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                        <svg class="size-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                            <path
                                fill="#4285F4"
                                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                            />
                            <path
                                fill="#34A853"
                                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                            />
                            <path
                                fill="#FBBC05"
                                d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                            />
                            <path
                                fill="#EA4335"
                                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                            />
                        </svg>
                        <span class="truncate">{{ props.email }}</span>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="company">Company name</Label>
                    <Input
                        id="company"
                        v-model="form.company"
                        type="text"
                        required
                        autofocus
                        autocomplete="organization"
                        placeholder="Acme Pool Service"
                    />
                    <InputError :message="form.errors.company" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="first_name">First name</Label>
                        <Input id="first_name" v-model="form.first_name" type="text" required autocomplete="given-name" placeholder="Jane" />
                        <InputError :message="form.errors.first_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="last_name">Last name</Label>
                        <Input id="last_name" v-model="form.last_name" type="text" autocomplete="family-name" placeholder="Doe" />
                        <InputError :message="form.errors.last_name" />
                    </div>
                </div>

                <SubmitButton :processing="form.processing" class="mt-2 w-full"> Create company </SubmitButton>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Wrong account?
                <TextLink :href="route('register')">Start over</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
