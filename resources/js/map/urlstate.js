export function readParams() {
    return new URLSearchParams(window.location.search);
}

export function readFilters() {
    const p = readParams();
    return {
        categories: p.get('categories') ? p.get('categories').split(',').filter(Boolean) : [],
        tier: p.get('tier') || 'all',
        hours: p.get('hours') || 'all',
        q: p.get('q') || '',
    };
}

export function readNewIds() {
    const raw = readParams().get('new');
    if (!raw) return [];
    return raw.split(',').map((v) => Number(v)).filter((v) => Number.isFinite(v) && v > 0);
}

export function readPlaceId() {
    const raw = readParams().get('place');
    if (!raw) return null;
    const id = Number(raw);
    return Number.isInteger(id) && id > 0 ? id : null;
}

export function writeParams(patch, replace = true) {
    const params = readParams();
    for (const key of Object.keys(patch)) {
        const val = patch[key];
        if (val === null || val === undefined || val === '' || (Array.isArray(val) && val.length === 0)) {
            params.delete(key);
        } else if (Array.isArray(val)) {
            params.set(key, val.join(','));
        } else {
            params.set(key, String(val));
        }
    }
    const qs = params.toString();
    const url = window.location.pathname + (qs ? '?' + qs : '');
    if (replace) {
        window.history.replaceState(null, '', url);
    } else {
        window.history.pushState(null, '', url);
    }
}