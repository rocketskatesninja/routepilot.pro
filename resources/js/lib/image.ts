/**
 * Picked images are redrawn in the browser before they're kept or sent, so the
 * server (JPEG/PNG/WebP/GIF, 8 MB, keeps at most 1200px) gets something it takes.
 * Both functions return the original file when it's already fine or the browser
 * can't read it (the server then says why it was refused).
 */
const TAKEN = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

/**
 * An uploaded picture (logo, avatar, pool photo). Anything the server won't take —
 * an SVG logo, an AVIF, a huge PNG — is redrawn at 1200px: as a PNG when a
 * transparent background matters (a logo), else a JPEG.
 */
export async function fitImage(file: File, transparent = false): Promise<File> {
    if (TAKEN.includes(file.type) && file.size <= 2 * 1024 * 1024) return file;
    const svg = file.type === 'image/svg+xml';
    // An SVG has no real size, so it's drawn at full size; a photo only ever shrinks.
    return redraw(file, svg ? await sizedSvg(file, 1200) : file, { maxSide: 1200, grow: svg, type: transparent ? 'image/png' : 'image/jpeg' });
}

/**
 * A camera photo from the field app. A full-size phone photo is several MB: over
 * the upload limit, slow on a cell signal and heavy in the offline queue.
 */
export async function shrinkPhoto(file: File): Promise<File> {
    if (file.size <= 500 * 1024) return file;
    const out = await redraw(file, file, { maxSide: 1600, grow: false, type: 'image/jpeg' });
    return out.size >= file.size ? file : out;
}

async function redraw(file: File, source: Blob, to: { maxSide: number; grow: boolean; type: 'image/png' | 'image/jpeg' }): Promise<File> {
    const url = URL.createObjectURL(source);
    try {
        // An <img> applies the photo's EXIF rotation, so a portrait shot stays upright.
        const img = new Image();
        img.src = url;
        await img.decode();
        const fit = to.maxSide / Math.max(img.naturalWidth, img.naturalHeight);
        const scale = to.grow ? fit : Math.min(1, fit);
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.naturalWidth * scale);
        canvas.height = Math.round(img.naturalHeight * scale);
        const ctx = canvas.getContext('2d');
        if (!ctx || !canvas.width || !canvas.height) return file;
        if (to.type === 'image/jpeg') {
            // A JPEG has no transparency; without this a see-through area turns black.
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        }
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, to.type, 0.85));
        if (!blob) return file;
        const name = file.name.replace(/\.\w+$/, '') + (to.type === 'image/png' ? '.png' : '.jpg');
        return new File([blob], name, { type: to.type, lastModified: file.lastModified });
    } catch {
        return file;
    } finally {
        URL.revokeObjectURL(url);
    }
}

/** The SVG with a pixel size from its viewBox — Firefox won't draw one without it. */
async function sizedSvg(file: File, maxSide: number): Promise<Blob> {
    const doc = new DOMParser().parseFromString(await file.text(), 'image/svg+xml');
    const root = doc.documentElement;
    const box = (root.getAttribute('viewBox') ?? '').split(/[\s,]+/).map(Number);
    const [w, h] =
        box.length === 4 ? [box[2], box[3]] : [parseFloat(root.getAttribute('width') ?? ''), parseFloat(root.getAttribute('height') ?? '')];
    if (w > 0 && h > 0) {
        if (box.length !== 4) root.setAttribute('viewBox', `0 0 ${w} ${h}`);
        const scale = maxSide / Math.max(w, h);
        root.setAttribute('width', String(Math.round(w * scale)));
        root.setAttribute('height', String(Math.round(h * scale)));
    }
    return new Blob([new XMLSerializer().serializeToString(doc)], { type: 'image/svg+xml' });
}
