<script setup lang="ts">
import GoogleAuthButton from '@/components/GoogleAuthButton.vue';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TextLink from '@/components/TextLink.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    googleEnabled?: boolean;
}>();

// OAuth failures redirect back to /login with a flash error.
const page = usePage();
const flashError = computed(() => (page.props.flash as { error?: string } | undefined)?.error);

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase title="Log in to your account" description="Enter your email and password below to log in">
        <Head title="Log in" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>
        <div v-if="flashError" class="mb-4 rounded-md border border-red-500/30 bg-red-500/10 px-3 py-2 text-center text-sm font-medium text-red-600">
            {{ flashError }}
        </div>

        <div v-if="googleEnabled" class="mb-6">
            <GoogleAuthButton />
            <div class="my-6 flex items-center gap-3 text-xs text-muted-foreground">
                <span class="h-px flex-1 bg-border"></span>
                or continue with email
                <span class="h-px flex-1 bg-border"></span>
            </div>
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        autofocus
                        tabindex="1"
                        autocomplete="email"
                        v-model="form.email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Password</Label>
                        <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm" tabindex="5"> Forgot password? </TextLink>
                    </div>
                    <div class="relative">
                        <Input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            tabindex="2"
                            autocomplete="current-password"
                            v-model="form.password"
                            placeholder="Password"
                            class="pr-10"
                        />
                        <button
                            type="button"
                            :aria-label="showPassword ? 'Hide password' : 'Show password'"
                            :aria-pressed="showPassword"
                            class="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground hover:text-foreground"
                            @click="showPassword = !showPassword"
                        >
                            <EyeOff v-if="showPassword" class="size-4" />
                            <Eye v-else class="size-4" />
                        </button>
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between" tabindex="3">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" v-model:checked="form.remember" tabindex="4" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <SubmitButton :processing="form.processing" class="mt-4 w-full" tabindex="4"> Log in </SubmitButton>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Don't have an account?
                <TextLink :href="route('register')" :tabindex="5">Sign up</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
