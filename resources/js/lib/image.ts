/**
 * Get a picked image into a shape the server takes before it's uploaded. The
 * server accepts JPEG/PNG/WebP/GIF up to 8 MB and keeps at most 1200px anyway, so
 * anything else — an SVG logo, an AVIF, a huge PNG — is redrawn here at 1200px:
 * as a PNG when a transparent background matters (a logo), else a JPEG.
 * Returns the original file when it's already fine or the browser can't read it
 * (the server then says why it was refused).
 */
const MAX_SIDE = 1200;
const SMALL_ENOUGH = 2 * 1024 * 1024;
const TAKEN = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

export async function fitImage(file: File, transparent = false): Promise<File> {
    if (TAKEN.includes(file.type) && file.size <= SMALL_ENOUGH) return file;
    const svg = file.type === 'image/svg+xml';
    const url = URL.createObjectURL(svg ? await sizedSvg(file) : file);
    try {
        const img = new Image();
        img.src = url;
        await img.decode();
        // An SVG has no real size, so it's drawn at full size; a photo only ever shrinks.
        const scale = svg
            ? MAX_SIDE / Math.max(img.naturalWidth, img.naturalHeight)
            : Math.min(1, MAX_SIDE / Math.max(img.naturalWidth, img.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.naturalWidth * scale);
        canvas.height = Math.round(img.naturalHeight * scale);
        const ctx = canvas.getContext('2d');
        if (!ctx || !canvas.width || !canvas.height) return file;
        if (!transparent) {
            // A JPEG has no transparency; without this a see-through area turns black.
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        }
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        const type = transparent ? 'image/png' : 'image/jpeg';
        const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, type, 0.85));
        if (!blob) return file;
        const name = file.name.replace(/\.\w+$/, '') + (transparent ? '.png' : '.jpg');
        return new File([blob], name, { type, lastModified: file.lastModified });
    } catch {
        return file;
    } finally {
        URL.revokeObjectURL(url);
    }
}

/** The SVG with a pixel size from its viewBox — Firefox won't draw one without it. */
async function sizedSvg(file: File): Promise<Blob> {
    const doc = new DOMParser().parseFromString(await file.text(), 'image/svg+xml');
    const root = doc.documentElement;
    const box = (root.getAttribute('viewBox') ?? '').split(/[\s,]+/).map(Number);
    const [w, h] =
        box.length === 4 ? [box[2], box[3]] : [parseFloat(root.getAttribute('width') ?? ''), parseFloat(root.getAttribute('height') ?? '')];
    if (w > 0 && h > 0) {
        if (box.length !== 4) root.setAttribute('viewBox', `0 0 ${w} ${h}`);
        const scale = MAX_SIDE / Math.max(w, h);
        root.setAttribute('width', String(Math.round(w * scale)));
        root.setAttribute('height', String(Math.round(h * scale)));
    }
    return new Blob([new XMLSerializer().serializeToString(doc)], { type: 'image/svg+xml' });
}
