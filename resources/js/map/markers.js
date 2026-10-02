import L from 'leaflet';
import { escapeHtml } from './search';
import { getCategoryIcon } from './icons';

function categoryColor(category) {
    const color = typeof category?.color === 'string' ? category.color.trim() : '';
    if (!color) return '';
    if (typeof CSS !== 'undefined' && typeof CSS.supports === 'function' && CSS.supports('color', color)) {
        return color;
    }
    return /^#[0-9a-f]{3,8}$/i.test(color) ? color : '';
}

function categoryColorStyle(category) {
    const color = categoryColor(category);
    return color ? ` style="--cat-hue:${escapeHtml(color)}"` : '';
}

export function ghostMarkerIcon(category) {
    const iconKey = category?.icon_key || category?.slug || 'grocery';
    const phIcon = getCategoryIcon(iconKey);
    const html = `<div class="ghost-marker-pin"${categoryColorStyle(category)}><i class="ph-fill ${phIcon}"></i></div>`;

    return L.divIcon({
        className: 'ghost-marker-container',
        html,
        iconSize: [28, 28],
        iconAnchor: [14, 14],
    });
}

export function markerIcon(category, { isNew = false, fresh = false } = {}) {
    const iconKey = category.icon_key || category.slug || 'grocery';
    const phIcon = getCategoryIcon(iconKey);
    const classes = ['map-place-logo', 'ph-fill', phIcon];
    if (isNew) classes.push('is-new');
    if (fresh) classes.push('is-fresh');

    const html = `<i class="${classes.join(' ')}" data-cat="${escapeHtml(iconKey)}"${categoryColorStyle(category)} aria-hidden="true"></i>`;

    return L.divIcon({
        className: 'map-place-logo-container',
        html,
        iconSize: [32, 32],
        iconAnchor: [16, 16],
    });
}

export function createMarker(place, { markerById = null, isNew = false, fresh = false, onOpen = null, draggable = false } = {}) {
    const marker = L.marker([Number(place.lat), Number(place.lng)], {
        icon: markerIcon(place.category, { isNew, fresh }),
        draggable: Boolean(draggable),
    });

    marker.on('click', (e) => {
        if (e && typeof L?.DomEvent?.stopPropagation === 'function') {
            L.DomEvent.stopPropagation(e);
        }
        if (onOpen) onOpen(place.id);
    });

    if (markerById) markerById.set(place.id, marker);
    return marker;
}

export function userAvatarPulseIcon(avatarUrl, userName = '') {
    const avatarHtml = avatarUrl
        ? `<div class="w-full h-full rounded-full bg-cover bg-center" style="background-image:url('${escapeHtml(avatarUrl)}');"></div>`
        : `<div class="w-full h-full rounded-full bg-emerald-500 flex items-center justify-center text-zinc-950 font-bold text-xs"><i class="ph-bold ph-user"></i></div>`;

    const html = `
        <div class="user-pulse-marker relative flex items-center justify-center w-9 h-9">
            <span class="user-pulse-ring absolute -inset-2 rounded-full bg-emerald-400/35 animate-ping pointer-events-none"></span>
            <span class="user-pulse-ring-outer absolute -inset-1 rounded-full bg-emerald-500/25 pointer-events-none"></span>
            <div class="relative w-8 h-8 rounded-full border-2 border-emerald-400 bg-zinc-900 shadow-lg shadow-emerald-500/50 overflow-hidden flex items-center justify-center">
                ${avatarHtml}
            </div>
        </div>
    `;

    return L.divIcon({
        className: 'user-location-marker-container',
        html,
        iconSize: [36, 36],
        iconAnchor: [18, 18],
    });
}