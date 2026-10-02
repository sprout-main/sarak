export function placeQuery(state, bboxString) {
    const query = {
        bbox: bboxString,
        categories: state.activeCategories.length ? state.activeCategories : undefined,
        q: state.q || undefined,
        hours: state.hours !== 'all' ? state.hours : undefined,
    };
    if (state.coords) {
        query.lat = state.coords.lat.toFixed(6);
        query.lng = state.coords.lng.toFixed(6);
        query.radius = state.coords.searchRadius || 5000;
    }
    return query;
}

export const HOURS_OPTIONS = [
    { value: 'all', label: 'All hours' },
    { value: 'open_now', label: 'Open now' },
    { value: 'open_24h', label: 'Open 24 hours' },
    { value: 'unknown', label: 'Hours unknown' },
];