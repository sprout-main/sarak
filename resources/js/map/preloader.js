/**
 * Predictive Preloader & Memory Cache Core
 * Asynchronously prefetches adjacent photos (N-1, N+1) into memory cache
 * to eliminate white flashes and network stalls during rapid navigation.
 */

const preloadedUrls = new Set();
const activeFetches = new Map();

export function preloadPhotoUrl(url) {
    if (!url || typeof url !== 'string') return Promise.resolve(false);
    if (preloadedUrls.has(url)) return Promise.resolve(true);
    if (activeFetches.has(url)) return activeFetches.get(url);

    const promise = new Promise((resolve) => {
        const img = new Image();
        img.onload = () => {
            preloadedUrls.add(url);
            activeFetches.delete(url);
            resolve(true);
        };
        img.onerror = () => {
            activeFetches.delete(url);
            resolve(false);
        };
        img.src = url;
    });

    activeFetches.set(url, promise);
    return promise;
}

export function prefetchAdjacentPhotos(photos, currentIndex, resolver) {
    if (!Array.isArray(photos) || photos.length <= 1) return;
    const total = photos.length;
    const indicesToPrefetch = [
        (currentIndex + 1) % total,
        (currentIndex - 1 + total) % total,
    ];

    if (total > 3) {
        indicesToPrefetch.push((currentIndex + 2) % total);
    }

    indicesToPrefetch.forEach((idx) => {
        const photo = photos[idx];
        const url = typeof resolver === 'function' ? resolver(photo) : (photo && photo.path ? `/storage/${String(photo.path).replace(/^\/+/, '')}` : null);
        if (url) {
            preloadPhotoUrl(url);
        }
    });
}

export function isPhotoPreloaded(url) {
    return preloadedUrls.has(url);
}

export function clearPreloadCache() {
    preloadedUrls.clear();
    activeFetches.clear();
}
