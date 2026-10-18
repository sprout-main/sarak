import axios from 'axios';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;
const csrfMeta = document.querySelector('meta[name="csrf-token"]');
if (csrfMeta) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfMeta.getAttribute('content');
}

axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 401) {
            const url = (error.config && error.config.url) || '';
            if (!url.includes('/auth/')) {
                window.location.assign('/login');
            }
        }
        return Promise.reject(error);
    }
);

export const api = {
    async get(url) {
        const response = await axios.get(url);
        return response.data;
    },
    async post(url, data) {
        const response = await axios.post(url, data || {});
        return response.data;
    },
    async patch(url, data) {
        const response = await axios.patch(url, data || {});
        return response.data;
    },
    async delete(url) {
        const response = await axios.delete(url);
        return response.data;
    },
};

export function notify(message, type = 'success') {
    window.dispatchEvent(new CustomEvent('admin-toast', { detail: { message, type } }));
}

export function totalOf(meta) {
    if (!meta) return 0;
    if (typeof meta.total === 'number') return meta.total;
    if (typeof meta.total_approved === 'number') return meta.total_approved;
    if (meta.pagination && typeof meta.pagination.total === 'number') return meta.pagination.total;
    return 0;
}

export function fmtDateTime(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    const pad = (n) => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
}

export function fmtVal(value) {
    if (value === null || value === undefined) return 'null';
    if (typeof value === 'object') {
        const s = JSON.stringify(value);
        return s.length > 140 ? s.slice(0, 137) + '...' : s;
    }
    let s = String(value);
    if (s.length > 140) s = s.slice(0, 137) + '...';
    return s;
}

export function diffRows(before, after) {
    const b = before && typeof before === 'object' ? before : {};
    const a = after && typeof after === 'object' ? after : {};
    const keys = [...new Set([...Object.keys(b), ...Object.keys(a)])];
    return keys.map((key) => ({
        key,
        before: fmtVal(b[key]),
        after: fmtVal(a[key]),
        changed: JSON.stringify(b[key] ?? null) !== JSON.stringify(a[key] ?? null),
    }));
}

export function summarizePayload(payload, max = 6) {
    if (!payload || typeof payload !== 'object') return [];
    const rows = [];
    for (const entry of Object.entries(payload)) {
        if (rows.length >= max) break;
        const [key, value] = entry;
        if (Array.isArray(value)) {
            rows.push({ key, value: value.length + ' entries' });
            continue;
        }
        if (value && typeof value === 'object') {
            rows.push({ key, value: 'object' });
            continue;
        }
        let s = String(value);
        if (s.length > 40) s = s.slice(0, 40) + '...';
        rows.push({ key, value: s });
    }
    return rows;
}

export function haversineM(lat1, lng1, lat2, lng2) {
    const R = 6371008.8;
    const toRad = (d) => (d * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1);
    const dLng = toRad(lng2 - lng1);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(a));
}