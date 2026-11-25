<script setup lang="ts">
import EmailAttachments from '@/components/EmailAttachments.vue';
import FormField from '@/components/FormField.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { type EmailComposer } from '@/composables/useEmailComposer';
import { Link } from '@inertiajs/vue3';
import { Paperclip, Send, Users } from '@lucide/vue';

/** The "Email people" form, as the People pages dock it in their detail pane. */
const props = defineProps<{
    composer: EmailComposer;
    summary: string;
    /** Said after the recipient count ("Tenant picks reach that company's admins."). */
    note?: string;
    /** The company signature (cleaned HTML) the sender can add. */
    signature?: string | null;
    recent: { id: number; subject: string; recipients: number; sent: number; failed: number; sent_on: string | null; files: string[] }[];
    /** Offer "Choose people": the form is covering the list, so it has to step aside. */
    covering?: boolean;
}>();
defineEmits<{ choose: []; cancel: [] }>();
// The page's own composer state (shared on purpose, so a draft outlives this form): edited in place.
const email = props.composer;
</script>

<template>
    <form class="space-y-4 text-sm" @submit.prevent="email.send()">
        <PaneHeader title="Email people" :summary="summary" />
        <div class="grid gap-1.5">
            <Label for="aud">Audience</Label>
            <Select id="aud" v-model="email.form.audience">
                <option v-for="a in email.options" :key="a.key" :value="a.key">{{ a.label }} ({{ a.count }})</option>
            </Select>
            <p class="text-muted-foreground text-xs">{{ email.count }} recipient(s).{{ note ? ` ${note}` : '' }}</p>
            <Button v-if="covering" type="button" variant="outline" size="sm" class="justify-self-start" @click="$emit('choose')">
                <Users class="mr-1 size-4" /> Choose people
            </Button>
        </div>
        <FormField label="Subject" for="esub" :error="email.form.errors.subject">
            <Input id="esub" v-model="email.form.subject" />
        </FormField>
        <FormField label="Message" :error="email.form.errors.body">
            <RichTextEditor v-model="email.form.body" first-name />
        </FormField>
        <EmailAttachments v-model="email.form.attachments" :recipients="email.count" :error="email.attachmentError" />
        <!-- The company signature: on by default, edited on Company settings. -->
        <div v-if="signature" class="grid gap-1.5">
            <div class="flex items-center justify-between gap-2">
                <Label for="esig" class="flex items-center gap-2 font-normal">
                    <Checkbox id="esig" v-model:checked="email.form.signature" /> Include signature
                </Label>
                <Link href="/company" class="text-primary text-xs font-medium hover:underline">Edit signature</Link>
            </div>
            <!-- Cleaned on the server (Tenant::emailSignature), so it's safe to show as HTML. -->
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div
                v-if="email.form.signature"
                class="rich-text border-border bg-muted/30 rounded-md border px-3 py-2 text-sm"
                style="min-height: 0"
                v-html="signature"
            />
        </div>
        <div v-if="recent.length" class="border-border border-t pt-2">
            <p class="text-muted-foreground mb-1 text-xs font-medium">Recent</p>
            <ul class="text-muted-foreground space-y-0.5 text-xs">
                <li v-for="c in recent.slice(0, 5)" :key="c.id" class="flex justify-between gap-2">
                    <span class="flex min-w-0 items-center gap-1"
                        ><span class="truncate" :title="c.subject">{{ c.subject }}</span
                        ><Paperclip
                            v-if="c.files?.length"
                            class="size-3 shrink-0"
                            :title="c.files.join(', ')"
                            :aria-label="`${c.files.length} file(s)`" /></span
                    ><span class="shrink-0"
                        >{{ c.sent }}/{{ c.recipients }} sent<span v-if="c.failed" class="text-red-500"> · {{ c.failed }} failed</span> ·
                        {{ c.sent_on }}</span
                    >
                </li>
            </ul>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" @click="$emit('cancel')">Cancel</Button>
            <Button type="submit" :disabled="email.form.processing || email.count === 0"
                ><Send class="mr-1 size-4" /> Send to {{ email.count }}</Button
            >
        </div>
    </form>
</template>
