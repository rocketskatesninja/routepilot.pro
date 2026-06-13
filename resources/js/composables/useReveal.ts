import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Scroll-reveal: add `.is-visible` to `.reveal` / `.stagger-children` elements
 * as they scroll into view (mirrors the legacy landing's global observer).
 * SSR-safe — only touches the DOM after mount.
 */
export function useReveal(): void {
    let observer: IntersectionObserver | null = null;

    onMounted(() => {
        if (typeof window === 'undefined' || !('IntersectionObserver' in window)) {
            document.querySelectorAll('.reveal, .stagger-children').forEach((el) => el.classList.add('is-visible'));
            return;
        }
        observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((e) => {
                    if (e.isIntersecting) {
                        e.target.classList.add('is-visible');
                        observer?.unobserve(e.target);
                    }
                });
            },
            { threshold: 0.08, rootMargin: '0px 0px -36px 0px' },
        );
        document.querySelectorAll('.reveal, .stagger-children').forEach((el) => observer?.observe(el));
    });

    onBeforeUnmount(() => observer?.disconnect());
}
