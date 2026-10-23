import { failureReason, postJson } from '@/lib/http';
import { ref } from 'vue';
import type { SectionProps } from './types';

const TRY_AGAIN = 'Something went wrong — please try again, or give us a call.';

/**
 * Sending one of the site's forms (contact, quote, booking) as a lead. Tracks
 * sending/sent, and puts the server's reason (e.g. they already have messages
 * waiting on a reply) or a generic retry line in `error`.
 */
export function useLeadForm(props: SectionProps) {
    const sending = ref(false);
    const sent = ref(false);
    const error = ref('');

    async function send(body: Record<string, unknown>): Promise<void> {
        if (sending.value) return;
        sending.value = true;
        error.value = '';
        try {
            const res = await postJson(props.live.contactAction || `/public/${props.brand.slug}/leads`, body);
            if (res.ok) {
                sent.value = true;
            } else {
                error.value = (await failureReason(res)) ?? TRY_AGAIN;
            }
        } catch {
            error.value = TRY_AGAIN;
        } finally {
            sending.value = false;
        }
    }

    return { sending, sent, error, send };
}
