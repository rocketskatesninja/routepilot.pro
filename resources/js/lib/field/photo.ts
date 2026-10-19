/**
 * Shrink a camera photo on the phone before it's kept or sent. A full-size phone
 * photo is several MB — over the server's upload limit, slow on a cell signal and
 * heavy in the offline queue — while the server keeps visit photos at 1200px anyway.
 * Returns the original file if it's already small or the browser can't decode it.
 */
const MAX_SIDE = 1600;
const QUALITY = 0.85;
const SMALL_ENOUGH = 500 * 1024;

export async function shrinkPhoto(file: File): Promise<File> {
    if (file.size <= SMALL_ENOUGH) return file;
    const url = URL.createObjectURL(file);
    try {
        // An <img> applies the photo's EXIF rotation, so a portrait shot stays upright.
        const img = new Image();
        img.src = url;
        await img.decode();
        const scale = Math.min(1, MAX_SIDE / Math.max(img.naturalWidth, img.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.naturalWidth * scale);
        canvas.height = Math.round(img.naturalHeight * scale);
        canvas.getContext('2d')?.drawImage(img, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', QUALITY));
        if (!blob || blob.size >= file.size) return file;
        return new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: file.lastModified });
    } catch {
        return file;
    } finally {
        URL.revokeObjectURL(url);
    }
}
