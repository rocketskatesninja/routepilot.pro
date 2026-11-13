import { isSignedOutResponse } from '@/lib/http';
import { ref } from 'vue';

/**
 * Whether the server has said this device is signed out (401 / 419): the account
 * was signed in somewhere else, or the session ended. It's not a connection
 * problem, and nothing is lost — visits waiting to sync stay on the device until
 * the person signs in again.
 */
export const signedOut = ref(false);

/** Note a response that says we're signed out; true when it did. */
export function isSignedOut(res: Response): boolean {
    if (!isSignedOutResponse(res)) return false;
    signedOut.value = true;
    return true;
}
