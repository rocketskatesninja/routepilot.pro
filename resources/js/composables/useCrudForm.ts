interface SaveOptions {
    preserveScroll?: boolean;
    onSuccess?: () => void;
}
interface Saveable {
    post(url: string, options?: SaveOptions): void;
    patch(url: string, options?: SaveOptions): void;
}

/**
 * Save a pane's create/edit form: POST to `base` for a new record, PATCH
 * `base/{id}` for an existing one, then call `done` (which closes the form).
 */
export function saveRecord(form: Saveable, base: string, id: number | null, done: () => void): void {
    if (id === null) form.post(base, { preserveScroll: true, onSuccess: done });
    else form.patch(`${base}/${id}`, { preserveScroll: true, onSuccess: done });
}
