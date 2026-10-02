import Alpine from 'alpinejs';

import L from 'leaflet';
import './leaflet-global';
import 'leaflet.markercluster';

import { api } from './api';
import { getTheme, applyTheme } from '../theme';
import { currentBbox } from './bbox';
import { readFilters, readNewIds, readPlaceId, writeParams } from './urlstate';
import { placeQuery } from './filters';
import { createMarker, ghostMarkerIcon, userAvatarPulseIcon } from './markers';
import { searchPlaces, nearestOpenNow } from './search';
import { getPosition } from './location';
import { makeHoursRows, submitPlaceForm, useMyLocationForForm } from './submission';
import { openDetailModal, closeDetailModal, todayIndex, daysTable, photoUrl, telHref } from './detail';
import { prefetchAdjacentPhotos } from './preloader';
import { formatDistance, to12h, hoursHeadline, manilaTodayIndex } from './opennow';
import { getCategoryIcon } from './icons';

const BANGUED_BOUNDS = [
    [17.553700, 120.537529],
    [17.627090, 120.702324],
];
const BANGUED_NEAR_BOUNDS = [
    [17.589386, 120.614133],
    [17.593973, 120.624433],
];
const BANGUED = [
    (BANGUED_BOUNDS[0][0] + BANGUED_BOUNDS[1][0]) / 2,
    (BANGUED_BOUNDS[0][1] + BANGUED_BOUNDS[1][1]) / 2,
];
const INITIAL_ZOOM = 15;
const INITIAL_MIN_ZOOM = 1;
const INITIAL_MAX_ZOOM = 22;
const ATTRIBUTION =
    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>';
const DOCK_LAYOUT_BREAKPOINT = 768;
const FOCUS_ZOOM = 16;
const FOCUS_DURATION = 0.7;
const FOCUS_FALLBACK_MS = 1200;
const FOCUS_EDGE_MARGIN_PX = 56;

export const MAP_LAYERS = {
    leaflet: {
        id: 'leaflet',
        label: 'Standard OpenStreetMap',
        hint: 'OpenStreetMap Standard',
        icon: 'ph-map-trifold',
        url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
        maxZoom: 19,
        subdomains: '',
    },
    sarak: {
        id: 'sarak',
        label: 'SARAK',
        hint: 'Vector-styled raster',
        icon: 'ph-compass',
        getUrl: (theme) => `/tiles/${theme === 'dark' ? 'dark' : 'light'}/{z}/{x}/{y}.png`,
        attribution: ATTRIBUTION,
        maxZoom: 20,
        subdomains: 'abcd',
    },
    satellite: {
        id: 'satellite',
        label: 'Satellite',
        hint: 'High-Res Aerial',
        icon: 'ph-globe-hemisphere-west',
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        attribution: 'Tiles &copy; Esri &mdash; Source: Esri, Maxar, Earthstar Geographics, GIS Community',
        subdomains: '',
    },
};

function clusterIcon(cluster) {
    const count = cluster.getChildCount();
    return L.divIcon({
        className: 'sarak-cluster-marker',
        html: `<div class="sarak-cluster"><div class="sarak-cluster-glow"></div><span class="sarak-cluster-count">${count}</span></div>`,
        iconSize: [44, 44],
        iconAnchor: [22, 22],
    });
}

function dropPinIcon() {
    return L.divIcon({
        className: 'sarak-drop-pin-icon',
        html: `
            <div class="relative flex items-center justify-center -translate-x-1/2 -translate-y-full cursor-grab active:cursor-grabbing pointer-events-auto">
                <div class="absolute -bottom-1 w-7 h-7 rounded-full bg-emerald-400/30 animate-ping"></div>
                <div class="relative flex items-center justify-center w-10 h-10 rounded-full bg-zinc-950 border-2 border-emerald-400 shadow-[0_4px_20px_rgba(52,211,153,0.6)] text-emerald-400">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 256 256" fill="currentColor">
                        <path d="M128,16a88.1,88.1,0,0,0-88,88c0,75.3,80,132.17,83.41,134.55a8,8,0,0,0,9.18,0C136,236.17,216,179.3,216,104A88.1,88.1,0,0,0,128,16Zm0,120a32,32,0,1,1,32-32A32,32,0,0,1,128,136Z"></path>
                    </svg>
                </div>
            </div>
        `,
        iconSize: [0, 0],
        iconAnchor: [0, 0],
    });
}

function pendingMarkerIcon() {
    return L.divIcon({
        className: 'sarak-pending-pin-icon',
        html: `
            <div class="relative flex items-center justify-center -translate-x-1/2 -translate-y-full cursor-pointer pointer-events-auto">
                <div class="absolute -bottom-1 w-6 h-6 rounded-full bg-amber-400/40 animate-ping"></div>
                <div class="relative flex items-center justify-center w-9 h-9 rounded-full bg-zinc-950 border-2 border-amber-400 shadow-[0_4px_16px_rgba(251,191,36,0.6)] text-amber-400">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256" fill="currentColor">
                        <path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm16-136a8,8,0,0,0-16,0v56a8,8,0,0,0,8,8h40a8,8,0,0,0,0-16H144Z"></path>
                    </svg>
                </div>
            </div>
        `,
        iconSize: [0, 0],
        iconAnchor: [0, 0],
    });
}

function adminMovePinIcon() {
    return L.divIcon({
        className: 'sarak-admin-move-icon',
        html: `
            <div class="relative flex items-center justify-center -translate-x-1/2 -translate-y-full cursor-grab active:cursor-grabbing pointer-events-auto">
                <div class="absolute -bottom-1 w-8 h-8 rounded-full bg-sky-400/40 animate-ping"></div>
                <div class="relative flex items-center justify-center w-11 h-11 rounded-full bg-zinc-950 border-2 border-sky-400 shadow-[0_4px_20px_rgba(56,189,248,0.7)] text-sky-400">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 256 256" fill="currentColor">
                        <path d="M229.66,122.34l-32-32a8,8,0,0,0-11.32,11.32L204.69,120H136V51.31l18.34,18.35a8,8,0,0,0,11.32-11.32l-32-32a8,8,0,0,0-11.32,0l-32,32A8,8,0,0,0,101.66,69.66L120,51.31V120H51.31l18.35-18.34A8,8,0,0,0,58.34,90.34l-32,32a8,8,0,0,0,0,11.32l32,32a8,8,0,0,0,11.32-11.32L51.31,136H120v68.69l-18.34-18.35a8,8,0,0,0-11.32,11.32l32,32a8,8,0,0,0,11.32,0l32-32a8,8,0,0,0-11.32-11.32L136,204.69V136h68.69l-18.35,18.34a8,8,0,0,0,11.32,11.32l32-32A8,8,0,0,0,229.66,122.34Z"></path>
                    </svg>
                </div>
            </div>
        `,
        iconSize: [0, 0],
        iconAnchor: [0, 0],
    });
}

Alpine.data('mapComposer', () => ({
    ready: false,
    loading: true,
    loadError: null,

    theme: getTheme(),
    activeLayer: (() => {
        const stored = typeof window !== 'undefined' ? localStorage.getItem('sarak_active_layer') : null;
        if (stored && ['leaflet', 'sarak', 'satellite'].includes(stored)) {
            return stored;
        }
        return 'leaflet';
    })(),
    layersOpen: false,

    contributorsOpen: false,
    contributorsLoading: false,
    contributorsList: [],

    reportModalOpen: false,
    reportPlaceId: null,
    reportPlaceName: '',
    reportIssueType: 'wrong_info',
    reportDetails: '',
    reportSubmitting: false,

    categories: [],
    barangays: [],
    places: [],
    totalApproved: 0,

    activeCategories: [],
    tier: 'all',
    hours: 'all',
    q: '',
    barangay: '',

    panelOpen: true,
    searchQ: '',
    searchResults: [],
    searchOpen: false,
    searchBusy: false,
    nearChips: [],
    nearBusy: false,

    detailOpen: false,
    detailLoading: false,
    detailPlace: null,
    detailHeadline: { state: 'Unknown', detail: 'Hours not provided', kind: 'maybe' },
    hoursExpanded: false,
    photosReveal: false,
    activePhotoIdx: 0,
    photoLightboxOpen: false,
    lightboxZoom: 1,
    lightboxPanX: 0,
    lightboxPanY: 0,
    lightboxHudVisible: true,
    lightboxIsPanning: false,
    lightboxPanStartX: 0,
    lightboxPanStartY: 0,
    lightboxInitialPanX: 0,
    lightboxInitialPanY: 0,
    lightboxPullDownY: 0,
    lightboxIsPullingDown: false,
    lightboxAnnouncement: '',
    photoTouchStartX: 0,
    photoTouchStartY: 0,
    photoDragOffset: 0,
    photoIsSwiping: false,
    photoSwipeTime: 0,

    submitOpen: false,
    pickingLocation: false,
    formBusy: false,
    formLocating: false,
    formErrors: {},
    formCategoryId: '',
    formName: '',
    formDesc: '',
    formAddress: '',
    formBarangayId: '',
    formLat: '',
    formLng: '',
    formPhone: '',
    formWebsite: '',
    photoName: '',
    photoFile: null,
    readonlyName: false,
    hoursRows: makeHoursRows(),

    get resolvedBarangayName() {
        if (!this.formBarangayId) return 'Auto-resolving...';
        const b = this.barangays.find((item) => String(item.id) === String(this.formBarangayId));
        return b ? b.name : 'Bangued';
    },

    coords: null,
    locating: false,
    closestDrawerOpen: false,
    closestPlaces: [],
    closestLoading: false,
    aboutOpen: false,

    toastMsg: '',
    newIds: [],

    massDragActive: false,
    draggedPinsDrawerOpen: true,
    draggedPins: [],
    savingMassDrag: false,

    showPendingReviews: false,
    pendingPlaces: [],
    _pendingLayer: null,
    adminMovingPlace: null,
    adminMoveLat: null,
    adminMoveLng: null,
    _adminMoveMarker: null,
    adminEditOpen: false,
    adminEditForm: { id: null, name: '', category_id: '', description: '', address: '', phone: '', website: '' },
    adminBusy: false,
    routingBusy: false,
    routeActive: false,
    routeProfile: 'driving',
    routeSummary: null,
    routeDestination: null,
    _routeLayer: null,
    _routeAbort: null,

    _map: null,
    _cluster: null,
    _massDragLayer: null,
    _tetherLayer: null,
    _placesById: new Map(),
    _markerById: new Map(),
    _visualsByPlaceId: new Map(),
    _seen: new Set(),
    _radiusCircle: null,
    _userMarker: null,
    _tiles: null,
    _boundaryData: null,
    _boundaryLayer: null,
    _seq: 0,
    _abort: null,
    _searchAbort: null,
    _moveTimer: null,
    _searchTimer: null,
    _toastTimer: null,
    _nearTimer: null,
    _focusTimer: null,
    _focusOnMoveEnd: null,
    _lastRenderKey: '',

    async init() {
        this._abort = new AbortController();
        this.newIds = readNewIds();

        const initial = readFilters();
        this.activeCategories = initial.categories;
        this.tier = initial.tier;
        this.hours = initial.hours;
        this.q = initial.q;
        this.searchQ = initial.q;

        if (this.newIds.length) {
            writeParams({ new: null });
        }

        await new Promise((resolve) => {
            this.$nextTick(() => {
                const mapEl = this.$refs.map;
                if (!mapEl) {
                    resolve();
                    return;
                }

                this._map = L.map(mapEl, {
                    center: BANGUED,
                    zoom: INITIAL_ZOOM,
                    minZoom: INITIAL_MIN_ZOOM,
                    maxZoom: INITIAL_MAX_ZOOM,
                    maxBounds: BANGUED_BOUNDS,
                    maxBoundsViscosity: 1,
                    zoomControl: false,
                    attributionControl: true,
                });

                this.applyBanguedLimits();
                this._map.fitBounds(BANGUED_BOUNDS, { animate: false, padding: [0, 0] });
                writeParams({ bbox: currentBbox(this._map) });

                this._tiles = this.createTileLayer(this.activeLayer).addTo(this._map);

                L.control.zoom({ position: 'bottomleft' }).addTo(this._map);

                this._cluster = L.markerClusterGroup({
                    disableClusteringAtZoom: 17,
                    showCoverageOnHover: false,
                    chunkedLoading: true,
                    spiderfyOnMaxZoom: true,
                    spiderLegPolylineOptions: {
                        weight: 1.5,
                        color: '#10b981',
                        opacity: 0.5,
                    },
                    iconCreateFunction: clusterIcon,
                    maxClusterRadius: 50,
                });
                this._map.addLayer(this._cluster);
                this.renderBoundaries();

                this._map.on('moveend', () => this.onMoveEnd());
                this._map.on('zoomend', () => this.updateBoundaryVisibility());
                this._map.on('resizeend', () => {
                    this.applyBanguedLimits();
                    this._map.panInside(BANGUED_BOUNDS, { animate: false });
                    this.updateBoundaryVisibility();
                });

                this._map.on('click', (e) => {
                    if (this.pickingLocation) return;
                    if (this.photoLightboxOpen) return;
                    if (this.massDragActive) return;

                    const origEvent = e && e.originalEvent;
                    const target = origEvent ? origEvent.target : null;

                    if (target && typeof target.closest === 'function') {
                        if (
                            target.closest('.map-place-logo-container') ||
                            target.closest('.leaflet-marker-icon') ||
                            target.closest('.sarak-cluster') ||
                            target.closest('.leaflet-control') ||
                            target.closest('.place-dock') ||
                            target.closest('.place-dock-card')
                        ) {
                            return;
                        }
                    }

                    if (this.detailOpen) {
                        this.closeDetail();
                    }
                });

                window.addEventListener('atlas-theme', (e) => {
                    const next = e.detail && e.detail.theme ? e.detail.theme : getTheme();
                    if (next !== this.theme) {
                        this.theme = next;
                        if (this.activeLayer === 'sarak' && this._map && this._tiles) {
                            this._map.removeLayer(this._tiles);
                            this._tiles = this.createTileLayer('sarak').addTo(this._map);
                            this._tiles.bringToBack();
                        }
                        if (this._boundaryLayer) {
                            this._boundaryLayer.setStyle(() => this.boundaryStyle());
                        }
                    }
                });

                window.addEventListener('open-contributors-modal', () => {
                    this.openContributors();
                });

                window.setMapLayer = (key) => this.setLayer(key);
                window.getMapLayer = () => this.activeLayer;
                window.MAP_LAYERS = MAP_LAYERS;

                setTimeout(() => this._map && this._map.invalidateSize(), 60);
                resolve();
            });
        });

        await this.bootstrap();
    },

    applyBanguedLimits() {
        if (!this._map) return;
        const minZoom = this._map.getBoundsZoom(BANGUED_BOUNDS, true);
        const maxZoom = this._map.getBoundsZoom(BANGUED_NEAR_BOUNDS, true);
        this._map.setMinZoom(minZoom);
        this._map.setMaxZoom(Math.max(19, maxZoom));
    },

    boundaryStyle() {
        if (this.activeLayer === 'satellite') {
            return {
                color: '#38bdf8',
                weight: 2,
                opacity: 0.95,
                fill: false,
                dashArray: '6 6',
                lineCap: 'round',
                lineJoin: 'round',
            };
        }
        return {
            color: '#059669',
            weight: 1.3,
            opacity: 0.85,
            fill: false,
            dashArray: '5 7',
            lineCap: 'round',
            lineJoin: 'round',
        };
    },

    renderBoundaries() {
        if (!this._map || !this._boundaryData) return;
        if (this._boundaryLayer) {
            this._map.removeLayer(this._boundaryLayer);
        }
        this._boundaryLayer = L.geoJSON(this._boundaryData, {
            pane: 'overlayPane',
            style: () => this.boundaryStyle(),
            interactive: false,
        });
        this.updateBoundaryVisibility();
    },

    updateBoundaryVisibility() {
        if (!this._map || !this._boundaryLayer) return;
        const shouldShow = this._map.getZoom() <= this._map.getMinZoom();
        const hasLayer = this._map.hasLayer(this._boundaryLayer);
        if (shouldShow && !hasLayer) {
            this._boundaryLayer.addTo(this._map);
        } else if (!shouldShow && hasLayer) {
            this._map.removeLayer(this._boundaryLayer);
        }
    },

    createTileLayer(layerKey) {
        const config = MAP_LAYERS[layerKey] || MAP_LAYERS.leaflet;
        const url = config.getUrl ? config.getUrl(this.theme) : config.url;
        const minZoom = this._map ? this._map.getMinZoom() : INITIAL_MIN_ZOOM;
        const providerMaxZoom = config.maxZoom ?? INITIAL_MAX_ZOOM;
        const maxZoom = this._map
            ? Math.min(this._map.getMaxZoom(), providerMaxZoom)
            : providerMaxZoom;
        const options = {
            attribution: config.attribution,
            subdomains: config.subdomains || '',
            minZoom,
            maxZoom,
            errorTileUrl: '',
        };
        return L.tileLayer(url, options);
    },

    setLayer(layerKey) {
        if (!MAP_LAYERS[layerKey] || (this.activeLayer === layerKey && this._tiles)) return;
        this.activeLayer = layerKey;
        try {
            localStorage.setItem('sarak_active_layer', layerKey);
        } catch (e) {}

        if (this._map) {
            if (this._tiles) {
                this._map.removeLayer(this._tiles);
            }
            this._tiles = this.createTileLayer(layerKey).addTo(this._map);
            this._tiles.bringToBack();
        }

        if (this._boundaryLayer) {
            this._boundaryLayer.setStyle(() => this.boundaryStyle());
        }

        if (typeof document !== 'undefined') {
            document.documentElement.dataset.mapView = layerKey;
        }
    },

    async bootstrap() {
        if (typeof document !== 'undefined') {
            document.documentElement.dataset.mapView = this.activeLayer;
        }
        const fetchTaxonomy = Promise.all([api.getCategories(), api.getBarangays()])
            .then(([cats, brgs]) => {
                this.categories = cats.data || [];
                this.barangays = brgs.data || [];
            })
            .catch((err) => {
                if (err.name !== 'AbortError') {
                    this.loadError = err.message;
                    this.showToast(err.message);
                }
            });

        const fetchBoundaries = api.getBoundaries()
            .then((response) => {
                this._boundaryData = response.data || null;
                this.renderBoundaries();
            })
            .catch(() => {});

        await Promise.all([fetchTaxonomy, fetchBoundaries, this.loadPlaces()]);
        this.ready = true;
        if (this.newIds.length && this.places.length) {
            this.showToast(`New pin${this.newIds.length > 1 ? 's' : ''} added`);
        }
        const deepLinkedPlace = readPlaceId();
        if (deepLinkedPlace) {
            writeParams({ place: null });
            const known = this._placesById.get(deepLinkedPlace);
            if (known) {
                this.focusPlace(known);
            } else {
                this.openDetail(deepLinkedPlace);
            }
        }
        if (new URLSearchParams(window.location.search).has('submit')) {
            this.openSubmit();
        }
        if (new URLSearchParams(window.location.search).has('mass_drag') && window.SarakIsHeadAdmin) {
            this.toggleMassDrag(true);
        }
    },

    targetPerPage() {
        if (!this._map) return 30;
        const parts = currentBbox(this._map).split(',').map(Number);
        if (parts.length !== 4 || parts.some((v) => Number.isNaN(v))) return 30;
        const span = Math.max(parts[2] - parts[0], parts[3] - parts[1]);
        return Math.min(200, Math.max(30, Math.round(span * 9000)));
    },

    maxPlaces() {
        return 400;
    },

    async loadPlaces() {
        if (!this._map) {
            this.loading = false;
            return;
        }
        const seq = ++this._seq;
        this.loading = true;
        if (this._abort) this._abort.abort();
        this._abort = new AbortController();

        try {
            const base = placeQuery(this, currentBbox(this._map));
            const perPage = this.targetPerPage();

            let places = [];
            let meta = null;
            let page = 1;

            for (;;) {
                const res = await api.getPlaces({ ...base, per_page: perPage, page }, this._abort.signal);
                if (seq !== this._seq) return;
                meta = res.meta || null;
                const rows = res.data || [];
                places = places.concat(rows);
                const lastPage = meta ? Math.max(Number(meta.last_page) || page, page) : page;
                if (page >= lastPage || rows.length < perPage || places.length >= this.maxPlaces()) break;
                page += 1;
                if (page > 10) break;
            }

            if (seq !== this._seq) return;
            this.places = places;
            this.totalApproved = meta && meta.total_approved !== undefined ? meta.total_approved : this.totalApproved;
            this.renderAllMarkers();
        } catch (err) {
            if (err.name === 'AbortError' || seq !== this._seq) return;
            this.loadError = err.message;
            this.showToast(err.message);
        } finally {
            if (seq === this._seq) this.loading = false;
        }
    },

    renderAllMarkers() {
        if (!this._map) return;

        if (this.massDragActive) {
            this._renderMassDragMarkers();
            return;
        }

        if (!this._cluster) return;
        const signature = this.places.map((p) => p.id).join(',');
        if (signature === this._lastRenderKey) return;
        this._lastRenderKey = signature;
        this._cluster.clearLayers();
        this._markerById.clear();

        const viewport = this._map.getBounds().pad(0.2);
        const toAdd = [];

        for (const place of this.places) {
            this._placesById.set(place.id, place);
            const lat = Number(place.lat);
            const lng = Number(place.lng);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;

            if (!viewport.contains([lat, lng])) {
                continue;
            }

            const isNew = this.newIds.includes(place.id);
            const fresh = !this._seen.has(place.id);
            if (fresh) this._seen.add(place.id);
            const marker = createMarker(place, {
                markerById: this._markerById,
                isNew,
                fresh,
                onOpen: (id) => this.openDetail(id),
            });
            toAdd.push(marker);
        }

        requestAnimationFrame(() => {
            this._cluster.addLayers(toAdd);
            if (this.detailOpen && this.detailPlace) {
                this._highlightMarker(this.detailPlace.id);
            }
        });
    },

    onMoveEnd() {
        if (!this._map) return;
        writeParams({ bbox: currentBbox(this._map) });
        if (this._moveTimer) clearTimeout(this._moveTimer);
        this._moveTimer = setTimeout(() => {
            requestAnimationFrame(() => {
                this.loadPlaces();
            });
        }, 280);
    },

    focusPlace(place) {
        if (!this._map || !place) return;
        if (this.massDragActive) return;
        this.searchOpen = false;

        const id = place.id;
        if (this.detailOpen && this.detailPlace && (this.detailPlace.id === id || String(this.detailPlace.id) === String(id))) {
            this.closeDetail();
            return;
        }

        if (this._focusTargetId === id && this._focusTimer) {
            return;
        }

        this._cancelFocus();

        const lat = Number(place.lat);
        const lng = Number(place.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

        const minZoom = this._map.getMinZoom();
        const maxZoom = this._map.getMaxZoom();
        const halfwayZoom = Math.round((minZoom + maxZoom) / 2);
        const currentZoom = typeof this._map.getZoom === 'function' ? this._map.getZoom() : halfwayZoom;
        const zoom = Math.min(maxZoom, Math.max(currentZoom, halfwayZoom));

        const size = this._map.getSize();
        const docked = window.innerWidth >= DOCK_LAYOUT_BREAKPOINT;
        const insetX = docked ? Math.min(420, Math.round(window.innerWidth * 0.34)) : 0;
        const insetY = docked ? 0 : Math.round(window.innerHeight * 0.45);
        const free = {
            left: FOCUS_EDGE_MARGIN_PX,
            top: FOCUS_EDGE_MARGIN_PX,
            right: Math.max(FOCUS_EDGE_MARGIN_PX + 20, size.x - insetX - FOCUS_EDGE_MARGIN_PX),
            bottom: Math.max(FOCUS_EDGE_MARGIN_PX + 20, size.y - insetY - FOCUS_EDGE_MARGIN_PX),
        };

        if (currentZoom >= zoom && typeof this._map.latLngToContainerPoint === 'function') {
            const currentPoint = this._map.latLngToContainerPoint([lat, lng]);
            if (
                currentPoint.x >= free.left &&
                currentPoint.x <= free.right &&
                currentPoint.y >= free.top &&
                currentPoint.y <= free.bottom
            ) {
                this.openDetail(id, true);
                return;
            }
        }

        const targetPoint = { x: (free.left + free.right) / 2, y: (free.top + free.bottom) / 2 };
        const centerPoint = { x: size.x / 2, y: size.y / 2 };
        const offsetPx = [targetPoint.x - centerPoint.x, targetPoint.y - centerPoint.y];

        let targetCenter = [lat, lng];
        if (typeof this._map.project === 'function' && typeof this._map.unproject === 'function') {
            const projected = this._map.project([lat, lng], zoom);
            const offsetProjected = L.point(projected.x - offsetPx[0], projected.y - offsetPx[1]);
            targetCenter = this._map.unproject(offsetProjected, zoom);
        }

        this._focusTargetId = id;
        let settled = false;
        const done = () => {
            if (settled) return;
            settled = true;
            this._focusTimer = null;
            this._focusOnMoveEnd = null;
            this._focusTargetId = null;
            if (this._map) this._map.off('moveend', done);
            this.openDetail(id, true);
        };

        this._focusTimer = setTimeout(done, FOCUS_FALLBACK_MS);
        this._focusOnMoveEnd = done;

        this._map.flyTo(targetCenter, zoom, { duration: FOCUS_DURATION });
        this._map.once('moveend', done);
    },

    _cancelFocus() {
        this._focusTargetId = null;
        if (this._focusTimer) {
            clearTimeout(this._focusTimer);
            this._focusTimer = null;
        }
        if (this._focusOnMoveEnd && this._map) {
            this._map.off('moveend', this._focusOnMoveEnd);
        }
        this._focusOnMoveEnd = null;
    },

    _dockInsetPx() {
        const el = this.$refs.detailModal;
        if (!el || !this.detailOpen) return { x: 0, y: 0 };
        const docked = window.innerWidth >= DOCK_LAYOUT_BREAKPOINT;
        return {
            x: docked ? el.offsetWidth : 0,
            y: docked ? 0 : el.offsetHeight,
        };
    },

    _revealPlace(place) {
        if (!this._map || !place) return;
        const lat = Number(place.lat);
        const lng = Number(place.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

        const inset = this._dockInsetPx();
        if (!inset.x && !inset.y) return;

        const size = this._map.getSize();
        const free = {
            left: FOCUS_EDGE_MARGIN_PX,
            top: FOCUS_EDGE_MARGIN_PX,
            right: size.x - inset.x - FOCUS_EDGE_MARGIN_PX,
            bottom: size.y - inset.y - FOCUS_EDGE_MARGIN_PX,
        };
        if (free.right <= free.left || free.bottom <= free.top) return;

        const point = this._map.latLngToContainerPoint([lat, lng]);
        if (
            point.x >= free.left &&
            point.x <= free.right &&
            point.y >= free.top &&
            point.y <= free.bottom
        ) {
            return;
        }

        const target = { x: (free.left + free.right) / 2, y: (free.top + free.bottom) / 2 };
        this._map.panBy([target.x - point.x, target.y - point.y], {
            animate: true,
            duration: 0.45,
        });
    },

    toggleCategory(slug) {
        const index = this.activeCategories.indexOf(slug);
        if (index >= 0) {
            this.activeCategories.splice(index, 1);
        } else {
            this.activeCategories.push(slug);
        }
        this.syncUrl();
        this.loadPlaces();
    },

    setTier(value) {
        this.tier = value;
        this.syncUrl();
        this.loadPlaces();
    },

    setTheme(value) {
        const theme = applyTheme(value);
        this.theme = theme;
        if (this.activeLayer === 'sarak' && this._map && this._tiles) {
            this._map.removeLayer(this._tiles);
            this._tiles = this.createTileLayer('sarak').addTo(this._map);
            this._tiles.bringToBack();
        }
        if (this._boundaryLayer) {
            this._boundaryLayer.setStyle(() => this.boundaryStyle());
        }
    },

    setHours(value) {
        this.hours = value;
        this.syncUrl();
        this.loadPlaces();
    },

    recenterBangued() {
        if (this._map) {
            this._map.flyToBounds(BANGUED_BOUNDS, {
                padding: [0, 0],
                maxZoom: this._map.getMinZoom(),
                duration: 0.6,
            });
            this.showToast('Centered on Bangued');
        }
    },

    clearFilters() {
        this.activeCategories = [];
        this.tier = 'all';
        this.hours = 'all';
        this.q = '';
        this.searchQ = '';
        this.syncUrl();
        this.loadPlaces();
    },

    syncUrl() {
        writeParams({
            categories: this.activeCategories.length ? this.activeCategories : '',
            tier: this.tier === 'all' ? '' : this.tier,
            hours: this.hours === 'all' ? '' : this.hours,
            q: this.q || '',
        });
    },

    onSearchInput() {
        this.q = this.searchQ;
        this.syncUrl();
        if (this._searchTimer) clearTimeout(this._searchTimer);
        this._searchTimer = setTimeout(() => this.execSearch(), 250);
    },

    async execSearch() {
        const q = String(this.searchQ).trim();
        if (!q) {
            this.searchResults = [];
            this.searchOpen = false;
            this.searchBusy = false;
            this.loadPlaces();
            return;
        }
        this.searchBusy = true;
        this.searchOpen = true;
        if (this._searchAbort) this._searchAbort.abort();
        this._searchAbort = new AbortController();
        try {
            const res = await searchPlaces(q, this._searchAbort.signal);
            this.searchResults = res.data.slice(0, 8);
        } catch (err) {
            if (err.name !== 'AbortError') this.searchResults = [];
        } finally {
            if (!this._searchAbort.signal.aborted) this.searchBusy = false;
        }
        this.loadPlaces();
    },

    clearSearch() {
        this.searchQ = '';
        this.q = '';
        this.searchResults = [];
        this.searchOpen = false;
        this.syncUrl();
        this.loadPlaces();
    },

    nearestOpenNow() {
        if (!this.coords || this.nearChips.length) return;
        this.refreshNearby();
    },

    async refreshNearby() {
        if (!this.coords) return;
        this.nearBusy = true;
        try {
            const chips = await nearestOpenNow(this.coords, 5);
            this.nearChips = chips;
        } catch {
            this.nearChips = [];
        } finally {
            this.nearBusy = false;
        }
    },

    async nearMe() {
        this.locating = true;
        try {
            const pos = await getPosition();
            this.coords = {
                lat: pos.lat,
                lng: pos.lng,
                radius: 100,
                searchRadius: 5000,
            };
            this.drawRadius();
            await this.loadPlaces();
            await this.autoAdjustToNearest(pos.lat, pos.lng);
            this.loadClosestPlaces(pos.lat, pos.lng);
        } catch (err) {
            this.showToast(err.message || 'Location unavailable.');
        } finally {
            this.locating = false;
        }
    },

    async autoAdjustToNearest(userLat, userLng) {
        if (!this._map) return;
        const userLatLng = L.latLng(userLat, userLng);

        let candidatePlaces = this.places.filter((p) => {
            const lat = Number(p.lat);
            const lng = Number(p.lng);
            return Number.isFinite(lat) && Number.isFinite(lng);
        });

        if (this.activeCategories.length > 0) {
            const catMatches = candidatePlaces.filter((p) => {
                const slug = p.category?.slug || p.category?.icon_key;
                return this.activeCategories.includes(slug);
            });
            if (catMatches.length > 0) candidatePlaces = catMatches;
        }

        if (this.q) {
            const qLower = this.q.toLowerCase();
            const searchMatches = candidatePlaces.filter((p) => {
                return (p.name && p.name.toLowerCase().includes(qLower)) ||
                       (p.address && p.address.toLowerCase().includes(qLower));
            });
            if (searchMatches.length > 0) candidatePlaces = searchMatches;
        }

        let nearest = null;
        let minDist = Infinity;

        for (const place of candidatePlaces) {
            const dist = userLatLng.distanceTo(L.latLng(Number(place.lat), Number(place.lng)));
            if (dist < minDist) {
                minDist = dist;
                nearest = place;
            }
        }

        if (nearest) {
            const nearestLatLng = L.latLng(Number(nearest.lat), Number(nearest.lng));
            const bounds = L.latLngBounds([userLatLng, nearestLatLng]);
            this._map.fitBounds(bounds, {
                padding: [80, 80],
                maxZoom: 18,
                animate: true,
            });
            this.showToast(`Nearest: ${nearest.name} (${formatDistance(minDist)})`);
        } else {
            this._map.setView([userLat, userLng], 17, { animate: true });
        }
    },

    loadClosestPlaces(userLat, userLng) {
        this.closestLoading = true;
        try {
            const userLatLng = L.latLng(userLat, userLng);
            const list = this.places
                .map((p) => {
                    const lat = Number(p.lat);
                    const lng = Number(p.lng);
                    const dist = (Number.isFinite(lat) && Number.isFinite(lng))
                        ? userLatLng.distanceTo(L.latLng(lat, lng))
                        : Infinity;
                    return {
                        ...p,
                        distance_m: dist,
                    };
                })
                .filter((p) => Number.isFinite(p.distance_m));

            list.sort((a, b) => a.distance_m - b.distance_m);
            this.closestPlaces = list.slice(0, 15);
            this.closestDrawerOpen = true;
        } catch {
            this.closestPlaces = [];
        } finally {
            this.closestLoading = false;
        }
    },

    openClosestDrawer() {
        this.closestDrawerOpen = true;
    },

    closeClosestDrawer() {
        this.closestDrawerOpen = false;
    },

    openAbout() {
        this.aboutOpen = true;
    },

    closeAbout() {
        this.aboutOpen = false;
    },

    clearLocation() {
        this.coords = null;
        this.nearChips = [];
        this.closestPlaces = [];
        this.closestDrawerOpen = false;
        if (this._radiusCircle && this._map) {
            this._map.removeLayer(this._radiusCircle);
            this._radiusCircle = null;
        }
        if (this._userMarker && this._map) {
            this._map.removeLayer(this._userMarker);
            this._userMarker = null;
        }
        if (this.routeActive) {
            this.clearRoute();
        }
        this.loadPlaces();
    },

    async startDirections(place) {
        const target = place || this.detailPlace;
        if (!target || !target.lat || !target.lng) {
            this.showToast('Location coordinates unavailable for this place.');
            return;
        }

        // If user coordinates aren't set yet, prompt and acquire GPS
        if (!this.coords) {
            this.showToast('Acquiring your location for directions...');
            this.routingBusy = true;
            try {
                const pos = await getPosition();
                this.coords = {
                    lat: pos.lat,
                    lng: pos.lng,
                    radius: 100,
                    searchRadius: 5000,
                };
                this.drawRadius();
            } catch (err) {
                this.routingBusy = false;
                this.showToast(err.message || 'Location access needed to calculate route.');
                this.openExternalNavigation(target);
                return;
            }
        }

        await this.calculateRoute(target, this.routeProfile || 'driving');
    },

    async calculateRoute(place, profile = 'driving') {
        const target = place || this.routeDestination || this.detailPlace;
        if (!this.coords || !target || !target.lat || !target.lng) return;

        if (this._routeAbort) {
            this._routeAbort.abort();
        }
        this._routeAbort = new AbortController();

        this.routingBusy = true;
        this.routeProfile = profile;
        this.routeDestination = {
            id: target.id,
            name: target.name,
            lat: target.lat,
            lng: target.lng,
        };

        try {
            const res = await api.getRoute({
                start_lat: this.coords.lat,
                start_lng: this.coords.lng,
                dest_lat: target.lat,
                dest_lng: target.lng,
                profile: profile,
            }, this._routeAbort.signal);

            const data = res?.data;
            if (!data || !data.geometry || !data.geometry.coordinates || !data.geometry.coordinates.length) {
                throw new Error('No route found between these locations.');
            }

            this.routeSummary = data;
            this.routeActive = true;
            this.searchOpen = false;
            this.renderRouteLayer(data.geometry.coordinates, target);
            this.closeDetail();
            this.showToast(`Route to ${target.name}: ${data.distance_text} (${data.duration_text})`);
        } catch (err) {
            if (err.name === 'AbortError') return;
            this.showToast(err.message || 'Failed to calculate directions.');
        } finally {
            this.routingBusy = false;
        }
    },

    setRouteProfile(profile) {
        if (this.routeProfile === profile && this.routeActive) return;
        this.routeProfile = profile;

        // Instant telemetry switch if multi-modal durations are already loaded
        if (this.routeSummary && this.routeSummary.durations && this.routeSummary.durations[profile]) {
            const d = this.routeSummary.durations[profile];
            this.routeSummary.duration_seconds = d.seconds;
            this.routeSummary.duration_text = d.text;
            this.showToast(`Updated mode: ${d.label} (${d.text})`);
            return;
        }

        if (this.routeDestination) {
            this.calculateRoute(this.routeDestination, profile);
        }
    },

    renderRouteLayer(coordinates, place) {
        if (!this._map) return;
        if (!this._routeLayer) {
            this._routeLayer = L.layerGroup().addTo(this._map);
        } else {
            this._routeLayer.clearLayers();
        }

        // Convert [lng, lat] from GeoJSON to Leaflet [lat, lng]
        const latLngs = coordinates.map(([lng, lat]) => [lat, lng]);

        // Outer glow/casing line for contrast against all map layers
        const casing = L.polyline(latLngs, {
            color: '#047857',
            weight: 8,
            opacity: 0.5,
            lineCap: 'round',
            lineJoin: 'round',
        });

        // Inner glowing vibrant route line
        const line = L.polyline(latLngs, {
            color: '#34d399',
            weight: 4.5,
            opacity: 0.95,
            lineCap: 'round',
            lineJoin: 'round',
        });

        // Destination flag/marker icon
        const destIcon = L.divIcon({
            className: 'sarak-route-dest-icon',
            html: `
                <div class="relative flex items-center justify-center -translate-x-1/2 -translate-y-full">
                    <div class="absolute -bottom-1 w-6 h-6 rounded-full bg-emerald-400/30 animate-ping"></div>
                    <div class="relative flex items-center justify-center w-8 h-8 rounded-full bg-zinc-950 border-2 border-emerald-400 shadow-[0_2px_14px_rgba(52,211,153,0.7)] text-emerald-300">
                        <i class="ph-fill ph-flag-pennant text-sm"></i>
                    </div>
                </div>
            `,
            iconSize: [0, 0],
            iconAnchor: [0, 0],
        });

        const destMarker = L.marker([Number(place.lat), Number(place.lng)], {
            icon: destIcon,
            zIndexOffset: 1500,
        });

        this._routeLayer.addLayer(casing);
        this._routeLayer.addLayer(line);
        this._routeLayer.addLayer(destMarker);

        // Fit map bounds to encompass the entire route smoothly
        const bounds = casing.getBounds();
        if (bounds.isValid()) {
            this._map.fitBounds(bounds, {
                paddingTopLeft: [70, 90],
                paddingBottomRight: [70, 90],
                maxZoom: 17,
            });
        }
    },

    clearRoute() {
        if (this._routeAbort) {
            this._routeAbort.abort();
            this._routeAbort = null;
        }
        if (this._routeLayer) {
            this._routeLayer.clearLayers();
        }
        this.routeActive = false;
        this.routeSummary = null;
        this.routeDestination = null;
        this.showToast('Route cleared.');
    },

    openExternalNavigation(place) {
        const target = place || this.routeDestination || this.detailPlace;
        if (!target) return;
        const originParam = this.coords ? `&origin=${this.coords.lat},${this.coords.lng}` : '';
        const url = `https://www.google.com/maps/dir/?api=1${originParam}&destination=${target.lat},${target.lng}`;
        window.open(url, '_blank', 'noopener,noreferrer');
    },

    drawRadius() {
        if (!this._map) return;
        if (this._radiusCircle) {
            this._map.removeLayer(this._radiusCircle);
            this._radiusCircle = null;
        }
        if (this._userMarker) {
            this._map.removeLayer(this._userMarker);
            this._userMarker = null;
        }
        if (!this.coords) return;

        // 100m radius circle around user location
        this._radiusCircle = L.circle([this.coords.lat, this.coords.lng], {
            radius: 100,
            className: 'sarak-user-radius',
            color: '#10b981',
            weight: 2,
            opacity: 0.85,
            dashArray: '4 6',
            fillColor: '#10b981',
            fillOpacity: 0.12,
        }).addTo(this._map);

        // User avatar pulse marker
        const avatarUrl = window.SarakUserAvatar || null;
        const userName = window.SarakUserName || 'You';
        this._userMarker = L.marker([this.coords.lat, this.coords.lng], {
            icon: userAvatarPulseIcon(avatarUrl, userName),
            zIndexOffset: 1200,
        }).addTo(this._map);
        this._userMarker.bindTooltip('Your Location', { direction: 'top', offset: [0, -18] });
    },

    openDetail(id, fromFocus = false) {
        if (this.massDragActive) return;
        if (this.detailOpen && this.detailPlace && (this.detailPlace.id === id || String(this.detailPlace.id) === String(id))) {
            this.closeDetail();
            return;
        }
        if (!fromFocus && this._map) {
            const place = this._placesById.get(id) || this.places.find((p) => p.id === id || String(p.id) === String(id));
            if (place) {
                this.focusPlace(place);
                return;
            }
        }
        this._cancelFocus();
        this._highlightMarker(id);
        openDetailModal(this, id, this.$refs.fabButton);
        this.$nextTick(() => {
            this._revealPlace(this._placesById.get(id) || this.detailPlace);
        });
    },

    closeDetail() {
        this._cancelFocus();
        closeDetailModal(this, this.$refs.fabButton);
        this._highlightMarker(null);
    },

    prevPhoto() {
        if (!this.detailPlace || !Array.isArray(this.detailPlace.photos) || this.detailPlace.photos.length <= 1) return;
        const total = this.detailPlace.photos.length;
        this.activePhotoIdx = (this.activePhotoIdx - 1 + total) % total;
        this.resetLightboxTransform();
        prefetchAdjacentPhotos(this.detailPlace.photos, this.activePhotoIdx, (p) => this.photoUrlFor(p));
        this._announcePhotoChange();
        this._scrollFilmstripToActive();
    },

    nextPhoto() {
        if (!this.detailPlace || !Array.isArray(this.detailPlace.photos) || this.detailPlace.photos.length <= 1) return;
        const total = this.detailPlace.photos.length;
        this.activePhotoIdx = (this.activePhotoIdx + 1) % total;
        this.resetLightboxTransform();
        prefetchAdjacentPhotos(this.detailPlace.photos, this.activePhotoIdx, (p) => this.photoUrlFor(p));
        this._announcePhotoChange();
        this._scrollFilmstripToActive();
    },

    goToPhoto(idx) {
        if (!this.detailPlace || !Array.isArray(this.detailPlace.photos)) return;
        if (idx < 0 || idx >= this.detailPlace.photos.length) return;
        this.activePhotoIdx = idx;
        this.resetLightboxTransform();
        prefetchAdjacentPhotos(this.detailPlace.photos, this.activePhotoIdx, (p) => this.photoUrlFor(p));
        this._announcePhotoChange();
        this._scrollFilmstripToActive();
    },

    _announcePhotoChange() {
        if (!this.detailPlace || !Array.isArray(this.detailPlace.photos)) return;
        const current = this.activePhotoIdx + 1;
        const total = this.detailPlace.photos.length;
        const photo = this.detailPlace.photos[this.activePhotoIdx];
        const caption = photo && photo.caption ? photo.caption : this.detailPlace.name;
        this.lightboxAnnouncement = `Photo ${current} of ${total}: ${caption}`;
    },

    _scrollFilmstripToActive() {
        this.$nextTick(() => {
            const activeThumb = document.querySelector(`[data-filmstrip-idx="${this.activePhotoIdx}"]`);
            if (activeThumb && typeof activeThumb.scrollIntoView === 'function') {
                activeThumb.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }
        });
    },

    openPhotoLightbox() {
        if (!this.detailPlace || !Array.isArray(this.detailPlace.photos) || !this.detailPlace.photos.length) return;
        this.photoLightboxOpen = true;
        if (typeof document !== 'undefined' && document.body) {
            document.body.classList.add('has-lightbox-open');
        }
        this.resetLightboxTransform();
        this.lightboxHudVisible = true;
        prefetchAdjacentPhotos(this.detailPlace.photos, this.activePhotoIdx, (p) => this.photoUrlFor(p));
        this._announcePhotoChange();
        this._scrollFilmstripToActive();
    },

    closePhotoLightbox() {
        this.photoLightboxOpen = false;
        if (typeof document !== 'undefined' && document.body) {
            document.body.classList.remove('has-lightbox-open');
        }
        this.resetLightboxTransform();
    },

    toggleLightboxZoom(e) {
        if (this.lightboxZoom > 1.05) {
            this.resetLightboxTransform();
        } else {
            this.lightboxZoom = 2.5;
            if (e && e.currentTarget) {
                const rect = e.currentTarget.getBoundingClientRect();
                const clientX = e.clientX || (e.touches && e.touches[0] ? e.touches[0].clientX : rect.left + rect.width / 2);
                const clientY = e.clientY || (e.touches && e.touches[0] ? e.touches[0].clientY : rect.top + rect.height / 2);
                const relX = (clientX - rect.left) / rect.width - 0.5;
                const relY = (clientY - rect.top) / rect.height - 0.5;
                this.lightboxPanX = Math.round(-relX * rect.width * 0.7);
                this.lightboxPanY = Math.round(-relY * rect.height * 0.7);
                this._clampLightboxPan();
            } else {
                this.lightboxPanX = 0;
                this.lightboxPanY = 0;
            }
        }
    },

    zoomInLightbox() {
        this.lightboxZoom = Math.min(3.5, Math.round((this.lightboxZoom + 0.5) * 10) / 10);
        this._clampLightboxPan();
    },

    zoomOutLightbox() {
        this.lightboxZoom = Math.max(1.0, Math.round((this.lightboxZoom - 0.5) * 10) / 10);
        if (this.lightboxZoom <= 1.05) {
            this.resetLightboxTransform();
        } else {
            this._clampLightboxPan();
        }
    },

    resetLightboxTransform() {
        this.lightboxZoom = 1;
        this.lightboxPanX = 0;
        this.lightboxPanY = 0;
        this.lightboxPullDownY = 0;
        this.lightboxIsPullingDown = false;
        this.lightboxIsPanning = false;
    },

    toggleLightboxHud() {
        this.lightboxHudVisible = !this.lightboxHudVisible;
    },

    onLightboxWheel(e) {
        if (!this.photoLightboxOpen) return;
        e.preventDefault();
        const delta = e.deltaY < 0 ? 0.25 : -0.25;
        const newZoom = Math.max(1.0, Math.min(3.5, Math.round((this.lightboxZoom + delta) * 100) / 100));
        if (newZoom <= 1.02) {
            this.resetLightboxTransform();
        } else {
            this.lightboxZoom = newZoom;
            this._clampLightboxPan();
        }
    },

    _clampLightboxPan() {
        if (this.lightboxZoom <= 1.0) {
            this.lightboxPanX = 0;
            this.lightboxPanY = 0;
            return;
        }
        const maxPanX = Math.round(window.innerWidth * (this.lightboxZoom - 1) * 0.5);
        const maxPanY = Math.round(window.innerHeight * (this.lightboxZoom - 1) * 0.5);
        this.lightboxPanX = Math.max(-maxPanX, Math.min(maxPanX, this.lightboxPanX));
        this.lightboxPanY = Math.max(-maxPanY, Math.min(maxPanY, this.lightboxPanY));
    },

    startLightboxDrag(e) {
        const clientX = e.clientX || (e.touches && e.touches[0] ? e.touches[0].clientX : 0);
        const clientY = e.clientY || (e.touches && e.touches[0] ? e.touches[0].clientY : 0);

        if (this.lightboxZoom > 1.05) {
            this.lightboxIsPanning = true;
            this.lightboxPanStartX = clientX;
            this.lightboxPanStartY = clientY;
            this.lightboxInitialPanX = this.lightboxPanX;
            this.lightboxInitialPanY = this.lightboxPanY;
        } else {
            this.lightboxIsPullingDown = true;
            this.lightboxPanStartY = clientY;
            this.lightboxPullDownY = 0;
        }
    },

    moveLightboxDrag(e) {
        const clientX = e.clientX || (e.touches && e.touches[0] ? e.touches[0].clientX : 0);
        const clientY = e.clientY || (e.touches && e.touches[0] ? e.touches[0].clientY : 0);

        if (this.lightboxIsPanning) {
            const dx = clientX - this.lightboxPanStartX;
            const dy = clientY - this.lightboxPanStartY;
            this.lightboxPanX = this.lightboxInitialPanX + dx;
            this.lightboxPanY = this.lightboxInitialPanY + dy;
            this._clampLightboxPan();
        } else if (this.lightboxIsPullingDown) {
            const dy = clientY - this.lightboxPanStartY;
            if (dy > 0) {
                this.lightboxPullDownY = dy * 0.75;
            }
        }
    },

    endLightboxDrag() {
        if (this.lightboxIsPanning) {
            this.lightboxIsPanning = false;
        }
        if (this.lightboxIsPullingDown) {
            if (this.lightboxPullDownY > 80) {
                this.closePhotoLightbox();
            }
            this.lightboxPullDownY = 0;
            this.lightboxIsPullingDown = false;
        }
    },

    onHeroPhotoTouchStart(e) {
        if (!e.touches || e.touches.length !== 1) return;
        this.photoTouchStartX = e.touches[0].clientX;
        this.photoTouchStartY = e.touches[0].clientY;
        this.photoDragOffset = 0;
        this.photoIsSwiping = false;
        this.photoSwipeTime = Date.now();
    },

    onHeroPhotoTouchMove(e) {
        if (!e.touches || e.touches.length !== 1) return;
        const currentX = e.touches[0].clientX;
        const currentY = e.touches[0].clientY;
        const dx = currentX - this.photoTouchStartX;
        const dy = currentY - this.photoTouchStartY;

        if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 10) {
            this.photoIsSwiping = true;
            this.photoDragOffset = dx * 0.45;
        }
    },

    onHeroPhotoTouchEnd() {
        if (this.photoIsSwiping) {
            const elapsed = Date.now() - this.photoSwipeTime;
            const velocity = Math.abs(this.photoDragOffset) / (elapsed || 1);
            if (this.photoDragOffset < -35 || (this.photoDragOffset < -15 && velocity > 0.3)) {
                this.nextPhoto();
            } else if (this.photoDragOffset > 35 || (this.photoDragOffset > 15 && velocity > 0.3)) {
                this.prevPhoto();
            }
        }
        this.photoDragOffset = 0;
        this.photoIsSwiping = false;
    },

    onLightboxKeyDown(e) {
        if (!this.photoLightboxOpen) return;
        switch (e.key) {
            case 'Escape':
                e.preventDefault();
                if (this.lightboxZoom > 1.05) {
                    this.resetLightboxTransform();
                } else {
                    this.closePhotoLightbox();
                }
                break;
            case 'ArrowLeft':
            case 'h':
            case 'H':
                e.preventDefault();
                this.prevPhoto();
                break;
            case 'ArrowRight':
            case 'l':
            case 'L':
                e.preventDefault();
                this.nextPhoto();
                break;
            case '+':
            case '=':
            case 'k':
            case 'K':
                e.preventDefault();
                this.zoomInLightbox();
                break;
            case '-':
            case '_':
            case 'j':
            case 'J':
                e.preventDefault();
                this.zoomOutLightbox();
                break;
            case '0':
                e.preventDefault();
                this.resetLightboxTransform();
                break;
            case ' ':
                e.preventDefault();
                this.toggleLightboxZoom();
                break;
            default:
                if (/^[1-9]$/.test(e.key)) {
                    const targetIdx = parseInt(e.key, 10) - 1;
                    if (this.detailPlace && this.detailPlace.photos && targetIdx < this.detailPlace.photos.length) {
                        e.preventDefault();
                        this.goToPhoto(targetIdx);
                    }
                }
                break;
        }
    },

    _highlightMarker(id) {
        const active = id === null || id === undefined ? null : this._markerById.get(id);
        this._markerById.forEach((marker) => {
            const el = marker.getElement ? marker.getElement() : null;
            if (el) el.classList.toggle('is-active', marker === active);
        });
    },

    async openSuggestFix(id) {
        if (!window.SarakUser && !window.AtlasUser) {
            this.showToast('Sign in to suggest a fix.');
            setTimeout(() => window.location.assign('/login'), 700);
            return;
        }
        if (!id) return;
        const targetId = id;
        const targetName = this.detailPlace ? this.detailPlace.name : '';
        this.closeDetail();
        const known = this._placesById.get(targetId);
        const place = known || await fetchPlaceSafe(targetId);
        const name = (place && place.name) ? place.name : targetName;
        this.openReportModal(targetId, name);
    },

    openReportModal(placeId, placeName = '') {
        this.reportPlaceId = placeId;
        this.reportPlaceName = placeName || '';
        this.reportIssueType = 'wrong_info';
        this.reportDetails = '';
        this.reportModalOpen = true;
    },

    closeReportModal() {
        this.reportModalOpen = false;
        this.reportSubmitting = false;
    },

    async submitReport() {
        if (!window.SarakUser && !window.AtlasUser) {
            this.showToast('Sign in to suggest a fix.');
            setTimeout(() => window.location.assign('/login'), 700);
            return;
        }
        if (!this.reportPlaceId) return;
        this.reportSubmitting = true;
        try {
            const res = await api.postReport(this.reportPlaceId, {
                issue_type: this.reportIssueType,
                details: this.reportDetails ? this.reportDetails.trim() : null,
            });
            this.showToast(res?.data?.message || 'Thank you! Your feedback has been submitted to the moderation team.');
            this.closeReportModal();
        } catch (err) {
            this.showToast(err.message || 'Failed to submit report. Please try again.');
        } finally {
            this.reportSubmitting = false;
        }
    },

    openSubmit() {
        if (!window.SarakUser && !window.AtlasUser) {
            this.showToast('Sign in to add a place.');
            setTimeout(() => window.location.assign('/login'), 700);
            return;
        }
        this.resetForm();
        this.startPickingLocation();
    },

    closeSubmit() {
        this.submitOpen = false;
        this.pickingLocation = false;
        if (this._mapClickListener && this._map) {
            this._map.off('click', this._mapClickListener);
            this._mapClickListener = null;
        }
        if (this._dropPinMarker && this._map) {
            this._map.removeLayer(this._dropPinMarker);
            this._dropPinMarker = null;
        }
    },

    resetForm() {
        this.formCategoryId = '';
        this.formName = '';
        this.formDesc = '';
        this.formAddress = '';
        this.formBarangayId = '';
        this.formLat = '';
        this.formLng = '';
        this.formPhone = '';
        this.formWebsite = '';
        this.photoName = '';
        this.photoFile = null;
        this.readonlyName = false;
        this.formErrors = {};
        this.hoursRows = makeHoursRows();
        if (this._dropPinMarker && this._map) {
            this._map.removeLayer(this._dropPinMarker);
            this._dropPinMarker = null;
        }
    },

    startPickingLocation() {
        this.submitOpen = false;
        this.pickingLocation = true;

        let startLat = parseFloat(this.formLat);
        let startLng = parseFloat(this.formLng);
        if (!Number.isFinite(startLat) || !Number.isFinite(startLng)) {
            if (this._map) {
                const c = this._map.getCenter();
                startLat = Number(c.lat.toFixed(6));
                startLng = Number(c.lng.toFixed(6));
            } else {
                startLat = BANGUED[0];
                startLng = BANGUED[1];
            }
        }

        this.formLat = startLat.toFixed(6);
        this.formLng = startLng.toFixed(6);

        if (!this._dropPinMarker && this._map) {
            this._dropPinMarker = L.marker([startLat, startLng], {
                draggable: true,
                icon: dropPinIcon(),
                zIndexOffset: 1000,
            }).addTo(this._map);

            this._dropPinMarker.on('drag', (e) => {
                const pos = e.target.getLatLng();
                this.formLat = pos.lat.toFixed(6);
                this.formLng = pos.lng.toFixed(6);
            });

            this._dropPinMarker.on('dragend', async (e) => {
                const pos = e.target.getLatLng();
                this.formLat = pos.lat.toFixed(6);
                this.formLng = pos.lng.toFixed(6);
                this.autoResolveBarangay(pos.lat, pos.lng);
            });
        } else if (this._dropPinMarker) {
            this._dropPinMarker.setLatLng([startLat, startLng]);
            if (this._map && !this._map.hasLayer(this._dropPinMarker)) {
                this._dropPinMarker.addTo(this._map);
            }
        }

        if (this._map) {
            this._map.panTo([startLat, startLng]);

            if (this._mapClickListener) {
                this._map.off('click', this._mapClickListener);
            }
            this._mapClickListener = (e) => {
                if (!this.pickingLocation) return;
                const lat = Number(e.latlng.lat.toFixed(6));
                const lng = Number(e.latlng.lng.toFixed(6));
                this.formLat = String(lat);
                this.formLng = String(lng);
                if (this._dropPinMarker) {
                    this._dropPinMarker.setLatLng([lat, lng]);
                }
                this.autoResolveBarangay(lat, lng);
            };
            this._map.on('click', this._mapClickListener);
        }
        this.autoResolveBarangay(startLat, startLng);
    },

    async autoResolveBarangay(lat, lng) {
        try {
            const res = await api.resolveBarangay(lat, lng);
            if (res?.data?.id) {
                this.formBarangayId = String(res.data.id);
            }
        } catch {
            // graceful fallback
        }
    },

    confirmPickedLocation() {
        this.pickingLocation = false;
        this.submitOpen = true;
        if (this._mapClickListener && this._map) {
            this._map.off('click', this._mapClickListener);
            this._mapClickListener = null;
        }
        const errors = { ...(this.formErrors || {}) };
        delete errors.lat;
        delete errors.lng;
        this.formErrors = errors;
        this.showToast(`Pinned at ${this.formLat}, ${this.formLng}`);
    },

    cancelPickingLocation() {
        this.pickingLocation = false;
        if (this._mapClickListener && this._map) {
            this._map.off('click', this._mapClickListener);
            this._mapClickListener = null;
        }
        if (this._dropPinMarker && this._map) {
            this._map.removeLayer(this._dropPinMarker);
            this._dropPinMarker = null;
        }
        if (this.formName || this.formCategoryId) {
            this.submitOpen = true;
        }
    },

    clearDroppedPin() {
        this.formLat = '';
        this.formLng = '';
        if (this._dropPinMarker && this._map) {
            this._map.removeLayer(this._dropPinMarker);
            this._dropPinMarker = null;
        }
    },

    submitForm() {
        submitPlaceForm(this);
    },

    useMyLocation() {
        useMyLocationForForm(this);
    },

    onPhotoPick(event) {
        const input = event.target;
        this.photoFile = input && input.files && input.files[0] ? input.files[0] : null;
        this.photoName = this.photoFile ? this.photoFile.name : '';
    },

    showToast(message) {
        this.toastMsg = String(message || '');
        if (this._toastTimer) clearTimeout(this._toastTimer);
        this._toastTimer = setTimeout(() => {
            this.toastMsg = '';
        }, 3400);
    },

    todayIndex() {
        return todayIndex();
    },

    hoursHeadlineFor(place) {
        return hoursHeadline(place, manilaTodayIndex());
    },

    toggleHours() {
        this.hoursExpanded = !this.hoursExpanded;
    },

    hasHours(place) {
        return Array.isArray(place && place.hours) && place.hours.some((row) => row && !row.is_closed);
    },

    daysTableFor(hours) {
        return daysTable(hours, todayIndex());
    },

    photoUrlFor(photo) {
        return photoUrl(photo);
    },

    telHrefFor(phone) {
        return telHref(phone);
    },

    fd(distance) {
        return formatDistance(distance);
    },

    fmtTime(hhmm) {
        return to12h(hhmm);
    },

    fmtOpenRange(row) {
        if (!row || row.is_closed) return 'Closed';
        if (row.opens && row.closes) return `${to12h(row.opens)} - ${to12h(row.closes)}`;
        return 'Hours vary';
    },

    filterActiveCount() {
        let count = this.activeCategories.length;
        if (this.tier !== 'all') count += 1;
        if (this.hours !== 'all') count += 1;
        if (this.q) count += 1;
        return count;
    },

    async openContributors() {
        this.contributorsOpen = true;
        if (!this.contributorsList.length) {
            this.contributorsLoading = true;
            try {
                const res = await api.getContributors(25);
                this.contributorsList = res.data || [];
            } catch (e) {
                this.showToast('Failed to load contributors.');
            } finally {
                this.contributorsLoading = false;
            }
        }
    },

    closeContributors() {
        this.contributorsOpen = false;
    },

    catIcon(key) {
        return getCategoryIcon(key);
    },

    getCategoryIcon(key) {
        return getCategoryIcon(key);
    },

    _renderMassDragMarkers() {
        if (!this._map) return;

        if (this._cluster && this._map.hasLayer(this._cluster)) {
            this._map.removeLayer(this._cluster);
        }

        if (!this._massDragLayer) {
            this._massDragLayer = L.layerGroup();
        }
        if (!this._map.hasLayer(this._massDragLayer)) {
            this._massDragLayer.addTo(this._map);
        }
        this._massDragLayer.clearLayers();
        this._markerById.clear();

        if (!this._tetherLayer) {
            this._tetherLayer = L.layerGroup();
        }
        if (!this._map.hasLayer(this._tetherLayer)) {
            this._tetherLayer.addTo(this._map);
        }

        const viewport = this._map.getBounds().pad(0.3);

        for (const place of this.places) {
            this._placesById.set(place.id, place);
            const staged = this.draggedPins.find((p) => p.placeId === place.id);
            const lat = staged ? staged.newLat : Number(place.lat);
            const lng = staged ? staged.newLng : Number(place.lng);

            if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;
            if (!viewport.contains([lat, lng]) && !staged) continue;

            const placeForMarker = staged ? { ...place, lat, lng } : place;
            const marker = createMarker(placeForMarker, {
                markerById: this._markerById,
                isNew: false,
                fresh: false,
                onOpen: (id) => this.openDetail(id),
                draggable: true,
            });

            marker.on('dragstart', (e) => this._onPinDragStart(place, marker, e));
            marker.on('drag', (e) => this._onPinDrag(place, marker, e));
            marker.on('dragend', (e) => this._onPinDragEnd(place, marker, e));

            this._massDragLayer.addLayer(marker);

            if (marker.dragging && typeof marker.dragging.enable === 'function') {
                marker.dragging.enable();
            }

            if (staged) {
                this._updateStagedVisuals(staged, marker);
            }
        }
    },

    _onPinDragStart(place, marker, e) {
        let staged = this.draggedPins.find((p) => p.placeId === place.id);
        if (!staged) {
            const originLat = Number(place.lat);
            const originLng = Number(place.lng);
            staged = {
                placeId: place.id,
                name: place.name,
                category: place.category,
                originLat,
                originLng,
                newLat: originLat,
                newLng: originLng,
                distanceM: 0,
                barangayId: place.barangay_id || null,
                barangayName: place.barangay?.name || 'Bangued',
            };
            this.draggedPins.push(staged);
        }

        this._updateStagedVisuals(staged, marker);
    },

    _onPinDrag(place, marker, e) {
        const staged = this.draggedPins.find((p) => p.placeId === place.id);
        if (!staged) return;

        const curLatLng = marker.getLatLng();
        const visuals = this._visualsByPlaceId.get(place.id);
        if (visuals && visuals.tether) {
            visuals.tether.setLatLngs([
                [staged.originLat, staged.originLng],
                [curLatLng.lat, curLatLng.lng],
            ]);
        }
    },

    _onPinDragEnd(place, marker, e) {
        const staged = this.draggedPins.find((p) => p.placeId === place.id);
        if (!staged) return;

        const curLatLng = marker.getLatLng();
        const dist = L.latLng(staged.originLat, staged.originLng).distanceTo(curLatLng);

        if (dist < 0.5) {
            this.revertSinglePin(place.id);
            return;
        }

        staged.newLat = Number(curLatLng.lat.toFixed(7));
        staged.newLng = Number(curLatLng.lng.toFixed(7));
        staged.distanceM = Math.round(dist);
        this._updateStagedVisuals(staged, marker);

        this.draggedPinsDrawerOpen = true;

        api.resolveBarangay(staged.newLat, staged.newLng).then((res) => {
            if (res && res.data && res.data.barangay) {
                staged.barangayId = res.data.barangay.id;
                staged.barangayName = res.data.barangay.name;
                this.draggedPins = [...this.draggedPins];
            }
        }).catch(() => {});

        this.draggedPins = [...this.draggedPins];
    },

    _updateStagedVisuals(staged, marker = null) {
        if (!this._tetherLayer) {
            this._tetherLayer = L.layerGroup();
        }
        if (this._map && !this._map.hasLayer(this._tetherLayer)) {
            this._tetherLayer.addTo(this._map);
        }

        let visuals = this._visualsByPlaceId.get(staged.placeId);
        if (!visuals) {
            visuals = { ghost: null, tether: null, marker: null };
            this._visualsByPlaceId.set(staged.placeId, visuals);
        }
        if (marker) {
            visuals.marker = marker;
        }

        if (!visuals.ghost) {
            visuals.ghost = L.marker([staged.originLat, staged.originLng], {
                icon: ghostMarkerIcon(staged.category),
                interactive: false,
                zIndexOffset: -100,
            });
            this._tetherLayer.addLayer(visuals.ghost);
        }

        if (!visuals.tether) {
            visuals.tether = L.polyline([
                [staged.originLat, staged.originLng],
                [staged.newLat, staged.newLng],
            ], {
                dashArray: '6, 6',
                color: '#10b981',
                weight: 2.5,
                opacity: 0.9,
                interactive: false,
            });
            this._tetherLayer.addLayer(visuals.tether);
        } else {
            visuals.tether.setLatLngs([
                [staged.originLat, staged.originLng],
                [staged.newLat, staged.newLng],
            ]);
        }
    },

    revertSinglePin(placeId) {
        const idx = this.draggedPins.findIndex((p) => p.placeId === placeId);
        if (idx !== -1) {
            const staged = this.draggedPins[idx];
            const visuals = this._visualsByPlaceId.get(placeId);
            if (visuals) {
                if (visuals.tether && this._tetherLayer) {
                    this._tetherLayer.removeLayer(visuals.tether);
                }
                if (visuals.ghost && this._tetherLayer) {
                    this._tetherLayer.removeLayer(visuals.ghost);
                }
                const activeMarker = this._markerById.get(placeId) || visuals.marker;
                if (activeMarker) {
                    activeMarker.setLatLng([staged.originLat, staged.originLng]);
                }
                this._visualsByPlaceId.delete(placeId);
            }
            this.draggedPins.splice(idx, 1);
            this.draggedPins = [...this.draggedPins];
            this.showToast(`Reverted "${staged.name}"`);
        }
    },

    revertAllPins() {
        for (const staged of this.draggedPins) {
            const visuals = this._visualsByPlaceId.get(staged.placeId);
            if (visuals) {
                if (visuals.tether && this._tetherLayer) {
                    this._tetherLayer.removeLayer(visuals.tether);
                }
                if (visuals.ghost && this._tetherLayer) {
                    this._tetherLayer.removeLayer(visuals.ghost);
                }
                const activeMarker = this._markerById.get(staged.placeId) || visuals.marker;
                if (activeMarker) {
                    activeMarker.setLatLng([staged.originLat, staged.originLng]);
                }
            }
        }
        this._visualsByPlaceId.clear();
        this.draggedPins = [];
        this.showToast('Discarded all relocated pins');
    },

    focusDraggedPin(staged) {
        if (!this._map || !staged) return;
        this._map.flyTo([staged.newLat, staged.newLng], Math.max(this._map.getZoom(), 17), { duration: 0.5 });
        const visuals = this._visualsByPlaceId.get(staged.placeId);
        const marker = this._markerById.get(staged.placeId) || visuals?.marker;
        if (marker && marker.getElement()) {
            const el = marker.getElement();
            el.classList.add('pin-focused-pulse');
            setTimeout(() => el.classList.remove('pin-focused-pulse'), 1200);
        }
    },

    toggleMassDrag(forceState = null) {
        if (!window.SarakIsHeadAdmin) return;
        const next = forceState !== null ? Boolean(forceState) : !this.massDragActive;
        if (next === this.massDragActive) return;

        if (!next) {
            if (this.draggedPins.length > 0) {
                this.revertAllPins();
            }
            this.massDragActive = false;
            this.draggedPinsDrawerOpen = false;

            if (this._massDragLayer && this._map && this._map.hasLayer(this._massDragLayer)) {
                this._map.removeLayer(this._massDragLayer);
                this._massDragLayer.clearLayers();
            }
            if (this._tetherLayer && this._map && this._map.hasLayer(this._tetherLayer)) {
                this._map.removeLayer(this._tetherLayer);
                this._tetherLayer.clearLayers();
            }
            this._visualsByPlaceId.clear();
            if (this._cluster && this._map && !this._map.hasLayer(this._cluster) && !this.showPendingReviews) {
                this._map.addLayer(this._cluster);
            }
            this._lastRenderKey = '';
            this.renderAllMarkers();
            this.showToast('Exited Mass Drag Mode');
        } else {
            if (this.detailOpen) this.closeDetail();
            this.searchOpen = false;
            this.massDragActive = true;
            this.draggedPinsDrawerOpen = true;
            this._lastRenderKey = '';
            this.renderAllMarkers();
            this.showToast('Mass Drag Mode active: drag pins to relocate');
        }
    },

    async saveAllDraggedPins() {
        if (!window.SarakIsHeadAdmin || this.draggedPins.length === 0 || this.savingMassDrag) return;
        this.savingMassDrag = true;

        const moves = this.draggedPins.map((p) => ({
            id: p.placeId,
            lat: p.newLat,
            lng: p.newLng,
            barangay_id: p.barangayId || null,
        }));

        try {
            const res = await api.massRelocatePlaces(moves);
            const count = res?.data?.updated_count || moves.length;
            this.showToast(`Saved ${count} relocated ${count === 1 ? 'place' : 'places'}`);

            for (const staged of this.draggedPins) {
                const visuals = this._visualsByPlaceId.get(staged.placeId);
                if (visuals) {
                    if (visuals.tether && this._tetherLayer) {
                        this._tetherLayer.removeLayer(visuals.tether);
                    }
                    if (visuals.ghost && this._tetherLayer) {
                        this._tetherLayer.removeLayer(visuals.ghost);
                    }
                }
            }
            this._visualsByPlaceId.clear();
            this.draggedPins = [];
            this.toggleMassDrag(false);
            await this.loadPlaces();
        } catch (err) {
            this.showToast(err.message || 'Failed to save relocated pins');
        } finally {
            this.savingMassDrag = false;
        }
    },

    async togglePendingReviews() {
        this.showPendingReviews = !this.showPendingReviews;
        if (this.showPendingReviews) {
            if (this._cluster && this._map && this._map.hasLayer(this._cluster)) {
                this._map.removeLayer(this._cluster);
            }
            await this.loadPendingReviews();
        } else {
            if (this._pendingLayer && this._map) {
                this._map.removeLayer(this._pendingLayer);
                this._pendingLayer = null;
            }
            if (this._cluster && this._map && !this._map.hasLayer(this._cluster)) {
                this._map.addLayer(this._cluster);
            }
        }
    },

    async loadPendingReviews() {
        if (!this._map) return;
        if (this.showPendingReviews && this._cluster && this._map && this._map.hasLayer(this._cluster)) {
            this._map.removeLayer(this._cluster);
        }
        if (!this._pendingLayer) {
            this._pendingLayer = L.layerGroup().addTo(this._map);
        } else {
            this._pendingLayer.clearLayers();
        }

        try {
            const [placesRes, queueRes] = await Promise.allSettled([
                api.getAdminPendingPlaces(),
                api.getAdminQueue(),
            ]);

            const pendingPlaces = placesRes.status === 'fulfilled' ? (placesRes.value?.data || []) : [];
            const queueItems = queueRes.status === 'fulfilled' ? (queueRes.value?.data || []) : [];

            const combined = [];
            for (const p of pendingPlaces) {
                combined.push({
                    ...p,
                    is_pending: true,
                    status: 'pending',
                });
            }

            for (const q of queueItems) {
                if (q.place) {
                    if (!combined.some((item) => item.id === q.place.id)) {
                        combined.push({
                            ...q.place,
                            queue_id: q.id,
                            is_pending: true,
                            status: 'pending',
                        });
                    }
                } else if (q.payload && q.payload.lat && q.payload.lng) {
                    combined.push({
                        id: `queue-${q.id}`,
                        queue_id: q.id,
                        name: q.payload.name || 'Pending Place Submission',
                        lat: q.payload.lat,
                        lng: q.payload.lng,
                        address: q.payload.address || '',
                        description: q.payload.description || '',
                        phone: q.payload.phone || '',
                        website: q.payload.website || '',
                        category: q.payload.category_id ? this.categories.find((c) => c.id == q.payload.category_id) : null,
                        is_pending: true,
                        status: 'pending',
                    });
                }
            }

            this.pendingPlaces = combined;

            for (const item of combined) {
                const lat = Number(item.lat);
                const lng = Number(item.lng);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;

                const marker = L.marker([lat, lng], {
                    icon: pendingMarkerIcon(),
                    zIndexOffset: 500,
                });
                marker.on('click', () => {
                    this.openPendingDetail(item);
                });
                this._pendingLayer.addLayer(marker);
            }

            this.showToast(`Loaded ${combined.length} pending review ${combined.length === 1 ? 'place' : 'places'}`);
        } catch (err) {
            this.showToast(err.message || 'Failed to load pending reviews');
        }
    },

    openPendingDetail(item) {
        this.detailPlace = { ...item, is_pending: true };
        this.detailHeadline = { state: 'Pending Moderation', detail: 'Awaiting administrator review', kind: 'pending' };
        this.detailOpen = true;
    },

    adminStartMovePlace(place) {
        if (!this._map || !place) return;
        this.closeDetail();

        if (this._adminMoveMarker) {
            this._map.removeLayer(this._adminMoveMarker);
            this._adminMoveMarker = null;
        }

        this.adminMovingPlace = place;
        this.adminMoveLat = Number(place.lat);
        this.adminMoveLng = Number(place.lng);

        this._adminMoveMarker = L.marker([this.adminMoveLat, this.adminMoveLng], {
            draggable: true,
            icon: adminMovePinIcon(),
            zIndexOffset: 1000,
        }).addTo(this._map);

        this._adminMoveMarker.on('drag', (e) => {
            const pos = e.target.getLatLng();
            this.adminMoveLat = pos.lat;
            this.adminMoveLng = pos.lng;
        });

        this._adminMoveMarker.on('dragend', (e) => {
            const pos = e.target.getLatLng();
            this.adminMoveLat = pos.lat;
            this.adminMoveLng = pos.lng;
        });

        this._map.panTo([this.adminMoveLat, this.adminMoveLng], { animate: true });
        this.showToast(`Drag pin to reposition ${place.name}`);
    },

    async adminSaveMovedLocation() {
        if (!this.adminMovingPlace || !this._adminMoveMarker) return;
        this.adminBusy = true;

        try {
            let barangayId = null;
            try {
                const geo = await api.resolveBarangay(this.adminMoveLat, this.adminMoveLng);
                if (geo && geo.data && geo.data.id) {
                    barangayId = geo.data.id;
                }
            } catch {}

            await api.adminUpdatePlaceLocation(this.adminMovingPlace.id, this.adminMoveLat, this.adminMoveLng, barangayId);

            this.showToast(`Location for ${this.adminMovingPlace.name} updated successfully`);
            this.adminCancelMove();
            await this.loadPlaces();
            if (this.showPendingReviews) {
                await this.loadPendingReviews();
            }
        } catch (err) {
            this.showToast(err.message || 'Failed to update location');
        } finally {
            this.adminBusy = false;
        }
    },

    adminCancelMove() {
        if (this._adminMoveMarker && this._map) {
            this._map.removeLayer(this._adminMoveMarker);
            this._adminMoveMarker = null;
        }
        this.adminMovingPlace = null;
        this.adminMoveLat = null;
        this.adminMoveLng = null;
    },

    adminOpenEdit(place) {
        if (!place) return;
        this.adminEditForm = {
            id: place.id,
            name: place.name || '',
            category_id: place.category_id || (place.category ? place.category.id : ''),
            description: place.description || '',
            address: place.address || '',
            phone: place.phone || '',
            website: place.website || '',
        };
        this.adminEditOpen = true;
    },

    async adminSaveEdit() {
        if (!this.adminEditForm.id) return;
        this.adminBusy = true;

        try {
            await api.adminUpdatePlace(this.adminEditForm.id, this.adminEditForm);
            this.adminEditOpen = false;
            this.showToast('Place updated successfully');

            if (this.detailPlace && this.detailPlace.id === this.adminEditForm.id) {
                this.detailPlace.name = this.adminEditForm.name;
                this.detailPlace.description = this.adminEditForm.description;
                this.detailPlace.address = this.adminEditForm.address;
                this.detailPlace.phone = this.adminEditForm.phone;
                this.detailPlace.website = this.adminEditForm.website;
                if (this.adminEditForm.category_id) {
                    const cat = this.categories.find((c) => c.id == this.adminEditForm.category_id);
                    if (cat) this.detailPlace.category = cat;
                }
            }

            await this.loadPlaces();
        } catch (err) {
            this.showToast(err.message || 'Failed to update place');
        } finally {
            this.adminBusy = false;
        }
    },

    async adminDeletePlace(place) {
        if (!place || !place.id) return;
        if (!confirm(`Are you sure you want to permanently remove "${place.name}"? This action is audited and cannot be undone.`)) {
            return;
        }

        this.adminBusy = true;
        try {
            if (place.queue_id && (!place.id || String(place.id).startsWith('queue-'))) {
                await api.discardQueue(place.queue_id);
            } else {
                await api.adminDeletePlace(place.id);
            }
            this.closeDetail();
            this.showToast(`Place "${place.name}" removed successfully`);
            await this.loadPlaces();
            if (this.showPendingReviews) {
                await this.loadPendingReviews();
            }
        } catch (err) {
            this.showToast(err.message || 'Failed to remove place');
        } finally {
            this.adminBusy = false;
        }
    },

    async adminApprovePlace(place) {
        if (!place) return;
        this.adminBusy = true;

        try {
            if (place.queue_id) {
                await api.approveQueue(place.queue_id);
            } else {
                await api.adminUpdatePlace(place.id, { status: 'approved' });
            }
            this.closeDetail();
            this.showToast(`Place "${place.name}" approved and published`);
            await this.loadPlaces();
            if (this.showPendingReviews) {
                await this.loadPendingReviews();
            }
        } catch (err) {
            this.showToast(err.message || 'Failed to approve place');
        } finally {
            this.adminBusy = false;
        }
    },

    async adminRejectPlace(place) {
        if (!place) return;
        const note = prompt(`Reason for rejecting "${place.name}":`, 'Details do not meet directory standards');
        if (note === null) return;

        this.adminBusy = true;
        try {
            if (place.queue_id) {
                await api.rejectQueue(place.queue_id, note);
            } else {
                await api.adminUpdatePlace(place.id, { status: 'rejected' });
            }
            this.closeDetail();
            this.showToast(`Place "${place.name}" submission rejected`);
            await this.loadPlaces();
            if (this.showPendingReviews) {
                await this.loadPendingReviews();
            }
        } catch (err) {
            this.showToast(err.message || 'Failed to reject place');
        } finally {
            this.adminBusy = false;
        }
    },

    get openSummary() {
        const headline = this.detailHeadline;
        return headline ? `${headline.state} | ${headline.detail}` : '';
    },

    get photosRevealTotal() {
        const count = this.detailPlace && Array.isArray(this.detailPlace.photos) ? this.detailPlace.photos.length : 0;
        return Math.max(0, count - 8);
    },
}));

async function fetchPlaceSafe(id) {
    try {
        const res = await api.getPlace(id);
        return res.data;
    } catch {
        return null;
    }
}