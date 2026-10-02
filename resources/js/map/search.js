import { api } from './api';
import { aroundParams } from './location';

export async function searchPlaces(q, signal, coords = null) {
    const isNaturalQuery = q.includes(' ') || /kape|wifi|generator|mangan|agas|klinika|open|food|coffee|study|night|rabii|pagsalaan/i.test(q);
    if (isNaturalQuery) {
        try {
            const params = { q };
            if (coords) {
                params.lat = coords.lat;
                params.lng = coords.lng;
            }
            const sem = await api.semanticSearch(params, signal);
            if (sem && sem.data && sem.data.length > 0) {
                return sem;
            }
        } catch (err) {
            if (err.name === 'AbortError') throw err;
        }
    }
    return api.getPlaces({ q, page: 1 }, signal);
}

export function nearestOpenNow(coords, maximum = 5) {
    return api
        .getPlaces({ ...aroundParams(coords), hours: 'open_now', page: 1 })
        .then((res) => res.data.slice(0, maximum));
}

export function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}