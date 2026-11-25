/** A selectable button in the landing editor, picked or not: one look for every picker. */
export const choice = (picked: boolean): string =>
    picked ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-background text-foreground hover:bg-muted';

/** A multi-line text box in the landing editor. */
export const TEXTAREA = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm';

/** Add a key to a picked list (badges, stats), or take it off. */
export function flip(list: string[], key: string): void {
    const i = list.indexOf(key);
    if (i >= 0) list.splice(i, 1);
    else list.push(key);
}
