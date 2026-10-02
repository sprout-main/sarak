export function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.getAttribute('content')) {
        return meta.getAttribute('content');
    }
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export function buildQuery(params) {
    const p = new URLSearchParams();
    for (const [key, value] of Object.entries(params || {})) {
        if (value === undefined || value === null || value === '') continue;
        if (Array.isArray(value)) {
            if (value.length) p.set(key, value.join(','));
        } else {
            p.set(key, String(value));
        }
    }
    return p.toString();
}

async function request(path, { method = 'GET', body = null, signal = null } = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    let payload = body;

    const token = getCsrfToken();
    if (method !== 'GET' && method !== 'HEAD' && token) {
        headers['X-CSRF-TOKEN'] = token;
        headers['X-XSRF-TOKEN'] = token;
    }

    if (body instanceof FormData) {
        payload = body;
    } else if (body !== null) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }

    let res;
    try {
        res = await fetch(path, { method, headers, body: payload, signal });
    } catch (err) {
        if (err.name === 'AbortError') throw err;
        const wrapped = new Error('Connection lost. Try again.');
        wrapped.status = 0;
        throw wrapped;
    }

    if (res.redirected && res.type === 'opaqueredirect') {
        window.location.assign(res.url);
    }

    if (res.status === 401) {
        window.location.assign('/login');
        const err = new Error('Please sign in to continue.');
        err.status = 401;
        throw err;
    }

    let data = null;
    const contentType = res.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
        try {
            data = await res.json();
        } catch {
            data = null;
        }
    }

    if (!res.ok) {
        const err = new Error(data?.message || res.statusText || 'Request failed.');
        err.status = res.status;
        err.detail = data?.data;
        err.errors = data?.errors || null;
        throw err;
    }

    return data;
}

export const api = {
    getCategories: () => request('/api/categories'),
    getBarangays: () => request('/api/barangays'),
    getBoundaries: () => request('/api/boundaries'),
    getPlaces: (params, signal) => request(`/api/places?${buildQuery(params)}`, { signal }),
    getPlace: (id) => request(`/api/places/${id}`),
    postPlace: (body) => request('/api/places', { method: 'POST', body }),
    postPhoto: (placeId, formData) => request(`/api/places/${placeId}/photos`, { method: 'POST', body: formData }),
    resolveBarangay: (lat, lng) => request(`/api/geo/barangay?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`),
    getRoute: (params, signal) => request(`/api/route?${buildQuery(params)}`, { signal }),
    semanticSearch: (params, signal) => request(`/api/search/semantic?${buildQuery(params)}`, { signal }),
    getContributors: (limit = 25) => request(`/api/contributors/leaderboard?limit=${limit}`),
    postReport: (placeId, body) => request(`/api/places/${placeId}/reports`, { method: 'POST', body }),
    massRelocatePlaces: (moves) => request('/api/admin/places/mass-relocate', { method: 'POST', body: { moves } }),
    getAdminPendingPlaces: () => request('/api/admin/places?status=pending&per_page=100'),
    getAdminQueue: () => request('/api/admin/queue?status=pending'),
    approveQueue: (queueId) => request(`/api/admin/queue/${queueId}/approve`, { method: 'POST' }),
    rejectQueue: (queueId, note = 'Rejected by administrator') => request(`/api/admin/queue/${queueId}/reject`, { method: 'POST', body: { note } }),
    discardQueue: (queueId) => request(`/api/admin/queue/${queueId}`, { method: 'DELETE' }),
    adminUpdatePlace: (placeId, body) => request(`/api/admin/places/${placeId}`, { method: 'PATCH', body }),
    adminUpdatePlaceLocation: (placeId, lat, lng, barangay_id = null) => request(`/api/admin/places/${placeId}/location`, { method: 'PATCH', body: { lat, lng, barangay_id } }),
    adminDeletePlace: (placeId) => request(`/api/admin/places/${placeId}`, { method: 'DELETE' }),
};

export function debounce(fn, wait = 250) {
    let timer = null;
    const wrapped = (...args) => {
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => {
            timer = null;
            fn(...args);
        }, wait);
    };
    wrapped.cancel = () => {
        if (timer) clearTimeout(timer);
        timer = null;
    };
    return wrapped;
}