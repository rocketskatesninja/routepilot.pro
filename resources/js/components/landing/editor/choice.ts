/** A selectable button in the landing editor, picked or not: one look for every picker. */
export const choice = (picked: boolean): string =>
    picked ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-background text-foreground hover:bg-muted';
