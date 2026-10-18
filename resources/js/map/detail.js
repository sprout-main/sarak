import { api } from './api';
import { DAY_NAMES, hoursHeadline, manilaTodayIndex } from './opennow';
import { prefetchAdjacentPhotos } from './preloader';

export async function fetchDetail(id) {
    const res = await api.getPlace(id);
    return res.data;
}

export function todayIndex() {
    return manilaTodayIndex();
}

export function daysTable(hours, today) {
    if (!Array.isArray(hours)) return [];
    return hours.map((h) => ({
        ...h,
        label: DAY_NAMES[h.day] || `Day ${h.day}`,
        isToday: h.day === today,
    }));
}

export function photoUrl(photo) {
    if (!photo || !photo.path) return null;
    const clean = String(photo.path).replace(/^\/+/, '');
    if (clean.startsWith('public/storage/')) return `/${clean}`;
    if (clean.startsWith('storage/')) return `/public/${clean}`;
    return `/public/storage/${clean}`;
}

export function telHref(phone) {
    const digits = String(phone || '').replace(/[^\d+]/g, '');
    return `tel:${digits}`;
}

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

export function trapFocus(container, on, returnTo = null) {
    if (!container) return;
    const stored = container._atlasTrapHandler;
    if (stored) {
        container.removeEventListener('keydown', stored);
        container._atlasTrapHandler = null;
    }
    if (on) {
        const focusables = [...container.querySelectorAll(FOCUSABLE)];
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        if (first) first.focus();
        const handler = (e) => {
            if (e.key !== 'Tab') return;
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                (last || first).focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        };
        container.addEventListener('keydown', handler);
        container._atlasTrapHandler = handler;
    } else if (returnTo && typeof returnTo.focus === 'function') {
        returnTo.focus();
    }
}

export function openDetailModal(composer, id, triggerRef) {
    composer.detailOpen = true;
    composer.detailLoading = true;
    composer.hoursExpanded = false;
    composer.detailPlace = null;
    composer.activePhotoIdx = 0;
    composer.photoLightboxOpen = false;
    if (typeof composer.resetLightboxTransform === 'function') {
        composer.resetLightboxTransform();
    }
    composer.detailHeadline = hoursHeadline(null);
    fetchDetail(id)
        .then((place) => {
            composer.detailPlace = place;
            composer.activePhotoIdx = 0;
            composer.detailHeadline = hoursHeadline(place, manilaTodayIndex());
            composer.detailLoading = false;
            if (place && Array.isArray(place.photos) && place.photos.length > 0) {
                prefetchAdjacentPhotos(place.photos, 0, photoUrl);
            }
            composer.$nextTick(() => {
                const el = composer.$refs.detailModal;
                if (el) {
                    trapFocus(el, true, triggerRef);
                }
            });
        })
        .catch((err) => {
            composer.detailOpen = false;
            composer.detailLoading = false;
            composer.showToast(err.message || 'Could not load this place.');
        });
    return true;
}

export function closeDetailModal(composer, triggerRef) {
    const el = composer.$refs.detailModal;
    if (el) trapFocus(el, false, triggerRef);
    composer.detailOpen = false;
    composer.detailPlace = null;
    composer.detailLoading = false;
    composer.hoursExpanded = false;
    composer.activePhotoIdx = 0;
    composer.photoLightboxOpen = false;
    if (typeof document !== 'undefined' && document.body) {
        document.body.classList.remove('has-lightbox-open');
    }
    if (typeof composer.resetLightboxTransform === 'function') {
        composer.resetLightboxTransform();
    }
    composer.detailHeadline = hoursHeadline(null);
}
