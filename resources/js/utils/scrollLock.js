/**
 * Global Scroll Lock Utility for EME's Apartelle
 *
 * Automatically detects when ANY modal, dialog, lightbox, or overlay is open
 * anywhere in the application (Guest package modal, Room preview, Extend Stay,
 * Admin modals, SweetAlert2, image previews, etc.) and locks background
 * scrolling and the window scrollbar without layout shifts.
 */

const MODAL_SELECTORS = [
    '.modal-overlay',
    '[class*="modal-overlay"]',
    '.modal-backdrop',
    '[class*="modal-backdrop"]',
    '.modal.show',
    '.modal.d-block',
    '.custom-modal-backdrop',
    '.print-modal-overlay',
    '.image-preview-overlay',
    '[class*="preview-overlay"]',
    '.swal2-container.swal2-shown',
    '.swal2-container',
    '[role="dialog"]'
];

let isLocked = false;
let scrollbarWidth = 0;

/**
 * Measure the browser window's vertical scrollbar width
 */
export function getScrollbarWidth() {
    if (typeof window === 'undefined' || typeof document === 'undefined') return 0;
    return window.innerWidth - document.documentElement.clientWidth;
}

/**
 * Lock background scrolling and the background scrollbar
 */
export function lockBackgroundScroll() {
    if (isLocked) return;

    scrollbarWidth = getScrollbarWidth();
    isLocked = true;

    // Apply modal-open state to both html and body for bulletproof lock
    document.documentElement.classList.add('modal-open');
    document.body.classList.add('modal-open');

    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';

    // Prevent content jump when scrollbar vanishes on Windows/desktop
    if (scrollbarWidth > 0) {
        document.body.style.paddingRight = `${scrollbarWidth}px`;
        const fixedHeaders = document.querySelectorAll('.fixed-top');
        fixedHeaders.forEach((el) => {
            el.style.paddingRight = `${scrollbarWidth}px`;
        });
    }
}

/**
 * Unlock background scrolling and restore scrollbar
 */
export function unlockBackgroundScroll() {
    if (!isLocked) return;

    isLocked = false;

    document.documentElement.classList.remove('modal-open');
    document.body.classList.remove('modal-open');

    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';

    const fixedHeaders = document.querySelectorAll('.fixed-top');
    fixedHeaders.forEach((el) => {
        el.style.paddingRight = '';
    });
}

/**
 * Check if any modal or overlay is currently active in the DOM
 */
export function checkAndSyncModals() {
    if (typeof document === 'undefined') return;

    let foundActiveModal = false;

    for (const selector of MODAL_SELECTORS) {
        const elements = document.querySelectorAll(selector);
        for (const el of elements) {
            // Check if element is attached and rendered visibly
            const style = window.getComputedStyle(el);
            const isVisible =
                style.display !== 'none' &&
                style.visibility !== 'hidden' &&
                parseFloat(style.opacity || '1') > 0;

            if (isVisible) {
                foundActiveModal = true;
                break;
            }
        }
        if (foundActiveModal) break;
    }

    if (foundActiveModal) {
        lockBackgroundScroll();
    } else {
        unlockBackgroundScroll();
    }
}

/**
 * Initialize global scroll lock observer for the entire application
 */
export function initGlobalScrollLock(router) {
    if (typeof window === 'undefined') return;

    // Check immediately on load
    checkAndSyncModals();

    // Use MutationObserver on document.body to instantly detect added/removed modals
    const observer = new MutationObserver(() => {
        checkAndSyncModals();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'style', 'hidden']
    });

    // Reset lock on route changes to ensure no orphaned locks remain
    if (router && router.afterEach) {
        router.afterEach(() => {
            setTimeout(checkAndSyncModals, 60);
        });
    }

    // Window safety cleanup
    window.addEventListener('beforeunload', () => {
        unlockBackgroundScroll();
    });

    return observer;
}
