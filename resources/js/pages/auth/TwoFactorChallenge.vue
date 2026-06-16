<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    code: '',
});

const submit = () => {
    form.post(route('two-factor.challenge.store'), {
        onFinish: () => form.reset('code'),
    });
};
</script>

<template>
    <AuthLayout title="Two-factor authentication" description="Enter the 6-digit code from your authenticator app, or one of your recovery codes.">
        <Head title="Two-factor challenge" />

        <form @submit.prevent="submit">
            <div class="space-y-6">
                <div class="grid gap-2">
                    <Label htmlFor="code">Authentication code</Label>
                    <Input
                        id="code"
                        type="text"
                        inputmode="text"
                        autocomplete="one-time-code"
                        class="mt-1 block w-full text-center tracking-[0.3em]"
                        v-model="form.code"
                        placeholder="123456"
                        required
                        autofocus
                    />
                    <InputError :message="form.errors.code" />
                </div>

                <SubmitButton :processing="form.processing" class="w-full">Verify</SubmitButton>
            </div>
        </form>
    </AuthLayout>
</template>
