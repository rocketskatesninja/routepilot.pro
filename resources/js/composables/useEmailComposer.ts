import { useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

export interface Audience {
    key: string;
    label: string;
    count: number;
}

/**
 * "Email people" on a People page: who's ticked in the list, and the message being
 * written. It lives on the page (not in the form component) so a draft survives
 * closing the form. `audiences` are the page's groups; `pageKeys` the rows on show.
 */
export function useEmailComposer(audiences: () => Audience[], pageKeys: () => string[]) {
    const firstGroup = () => audiences()[0]?.key ?? '';
    const open = ref(false);
    const picked = ref<string[]>([]);
    const form = useForm<{ audience: string; subject: string; body: string; signature: boolean; attachments: File[]; recipients: string[] }>({
        audience: firstGroup(),
        subject: '',
        // A new message opens with a greeting the writer can change; {first name} is each person's own.
        body: '<p>Hi {first name},</p><p></p>',
        signature: true,
        attachments: [],
        recipients: [],
    });

    // Ticking someone means "send to these people": the audience follows. Untick everyone and
    // it goes back to the first group (there's no "Selected people" left to pick).
    watch(
        () => picked.value.length,
        (now, before) => {
            if (now > before) form.audience = 'selected';
            else if (now === 0 && form.audience === 'selected') form.audience = firstGroup();
        },
    );
    const options = computed<Audience[]>(() => [
        ...(picked.value.length ? [{ key: 'selected', label: 'Selected people', count: picked.value.length }] : []),
        ...audiences(),
    ]);
    const count = computed(() => options.value.find((a) => a.key === form.audience)?.count ?? 0);
    // The server's word on the files (the list as a whole, or one of them).
    const attachmentError = computed(() => Object.entries(form.errors as Record<string, string>).find(([k]) => k.startsWith('attachments'))?.[1]);
    const allOnPage = computed(() => pageKeys().length > 0 && pageKeys().every((k) => picked.value.includes(k)));

    return reactive({
        open,
        picked,
        form,
        options,
        count,
        attachmentError,
        allOnPage,
        isPicked: (key: string) => picked.value.includes(key),
        toggle(key: string) {
            const i = picked.value.indexOf(key);
            if (i === -1) picked.value.push(key);
            else picked.value.splice(i, 1);
        },
        toggleAll() {
            if (allOnPage.value) picked.value = picked.value.filter((k) => !pageKeys().includes(k));
            else picked.value = [...new Set([...picked.value, ...pageKeys()])];
        },
        /** Open the form, aimed at whoever is ticked (or `keys`, from a deep link). */
        start(keys?: string[]) {
            if (keys) picked.value = keys;
            form.audience = picked.value.length ? 'selected' : firstGroup();
            form.clearErrors();
            open.value = true;
        },
        /** Put the form away; the checkboxes, and anyone ticked, go with it (a typed draft stays). */
        close() {
            open.value = false;
            picked.value = [];
            form.clearErrors();
        },
        send() {
            form.recipients = form.audience === 'selected' ? [...picked.value] : [];
            form.post('/people/email', {
                preserveScroll: true,
                onSuccess: () => {
                    open.value = false;
                    form.reset();
                    picked.value = [];
                },
            });
        },
    });
}

export type EmailComposer = ReturnType<typeof useEmailComposer>;
