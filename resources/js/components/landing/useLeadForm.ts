import { failureReason, postJson } from '@/lib/http';
import { reactive } from 'vue';
import type { SectionProps } from './types';

const TRY_AGAIN = 'Something went wrong — please try again, or give us a call.';

export interface LeadFormState {
    sending: boolean;
    sent: boolean;
    error: string;
    send: (body: Record<string, unknown>) => Promise<void>;
}

/**
 * Sending one of the site's forms (contact, quote, booking) as a lead. Tracks
 * sending/sent, and puts the server's reason (e.g. they already have messages
 * waiting on a reply) or a generic retry line in `error`. Rendered by LeadForm.
 */
export function useLeadForm(props: SectionProps): LeadFormState {
    const state: LeadFormState = reactive({
        sending: false,
        sent: false,
        error: '',
        async send(body: Record<string, unknown>) {
            if (state.sending) return;
            state.sending = true;
            state.error = '';
            try {
                const res = await postJson(props.live.contactAction || `/public/${props.brand.slug}/leads`, body);
                if (res.ok) {
                    state.sent = true;
                } else {
                    state.error = (await failureReason(res)) ?? TRY_AGAIN;
                }
            } catch {
                state.error = TRY_AGAIN;
            } finally {
                state.sending = false;
            }
        },
    });

    return state;
}
