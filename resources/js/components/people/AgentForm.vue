<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import ImageUpload from '@/components/ImageUpload.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { saveRecord } from '@/composables/useCrudForm';
import { type AgentDetail } from '@/types/people';
import { useForm } from '@inertiajs/vue3';

/** Add an agent, or edit the one given. */
const props = defineProps<{ agent: AgentDetail | null }>();
const emit = defineEmits<{ close: [] }>();

// The agent as they were when the form opened: picking another row doesn't retarget it.
const editing = props.agent;
const f = editing?.fields;
const form = useForm({
    first_name: f?.first_name ?? '',
    last_name: f?.last_name ?? '',
    email: f?.email ?? '',
    phone: f?.phone ?? '',
    password: '',
    map_color: f?.map_color ?? '#0ea5e9',
    is_active: f?.is_active ?? true,
    agent_plus: f?.agent_plus ?? false,
    photo: null as File | null,
});
const submit = () => saveRecord(form, '/agents', editing?.id ?? null, () => emit('close'));
</script>

<template>
    <form class="space-y-4 text-sm" @submit.prevent="submit">
        <PaneHeader
            :title="editing ? 'Edit agent' : 'New agent'"
            :summary="editing ? 'Update their profile and access.' : 'They sign in with this email and password.'"
        />
        <ImageUpload
            :model-value="form.photo"
            :current="editing?.photo_url ?? null"
            :error="form.errors.photo"
            shape="circle"
            @update:model-value="(file) => (form.photo = file)"
        />
        <div class="grid grid-cols-2 gap-3">
            <FormField label="First name" for="a_first" :error="form.errors.first_name"><Input id="a_first" v-model="form.first_name" /></FormField>
            <FormField label="Last name" for="a_last"><Input id="a_last" v-model="form.last_name" /></FormField>
        </div>
        <FormField label="Email" for="a_email" :error="form.errors.email"><Input id="a_email" v-model="form.email" type="email" /></FormField>
        <div class="grid grid-cols-2 gap-3">
            <FormField label="Phone" for="a_phone"><Input id="a_phone" v-model="form.phone" /></FormField>
            <FormField label="Map color" for="a_color">
                <input id="a_color" v-model="form.map_color" type="color" class="border-input h-9 w-full rounded-md border" />
            </FormField>
        </div>
        <FormField :label="editing ? 'New password (optional)' : 'Initial password'" for="a_pw" :error="form.errors.password">
            <PasswordInput id="a_pw" v-model="form.password" autocomplete="new-password" />
        </FormField>
        <label class="flex items-center gap-2"
            ><input v-model="form.agent_plus" type="checkbox" /> Agent+ (reorder and skip stops on their route, and see every report)</label
        >
        <label v-if="editing" class="flex items-center gap-2"><input v-model="form.is_active" type="checkbox" /> Active (can log in)</label>
        <div class="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" @click="emit('close')">Cancel</Button>
            <Button type="submit" :disabled="form.processing">{{ editing ? 'Save' : 'Add agent' }}</Button>
        </div>
    </form>
</template>
