import '../../css/admin.css';
import '../map/icons.js';
import '@fontsource/geist-sans/400.css';
import '@fontsource/geist-sans/500.css';
import '@fontsource/geist-sans/600.css';
import '@fontsource/geist-sans/700.css';
import '@fontsource/geist-mono/400.css';
import '@fontsource/geist-mono/500.css';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import Alpine from 'alpinejs';
import { themeToggle } from '../theme.js';
import './api.js';
import { api, totalOf, notify } from './api.js';
import { adminQueue } from './queue.js';
import { adminPlaces } from './places.js';
import { adminUsers } from './users.js';
import { adminImport } from './import.js';
import { adminAnalytics } from './analytics.js';
import { adminBoundaries } from './boundaries.js';
import { adminReports } from './reports.js';
import { adminAuditLogs } from './audit-logs.js';

import { dropPinIcon } from './pin.js';

Alpine.data('adminQueue', adminQueue);
Alpine.data('adminPlaces', adminPlaces);
Alpine.data('adminUsers', adminUsers);
Alpine.data('adminImport', adminImport);
Alpine.data('adminAnalytics', adminAnalytics);
Alpine.data('adminBoundaries', adminBoundaries);
Alpine.data('adminReports', adminReports);
Alpine.data('adminAuditLogs', adminAuditLogs);

Alpine.data('adminSubmitPlace', () => ({
    open: false,
    submitting: false,
    categories: [],
    barangays: [],
    errors: {},
    photoFile: null,
    photoName: '',
    photoPreview: null,
    form: {
        name: '',
        category_id: '',
        barangay_id: '',
        address: '',
        lat: '17.596500',
        lng: '120.619000',
        phone: '',
        website: '',
        description: '',
    },
    miniMap: null,
    marker: null,
    locating: false,
    init() {
        window.addEventListener('open-admin-submit-place', () => this.openModal());
    },
    async openModal() {
        this.open = true;
        this.errors = {};
        if (!this.categories.length || !this.barangays.length) {
            try {
                const [cats, brgs] = await Promise.all([
                    api.get('/api/categories'),
                    api.get('/api/barangays'),
                ]);
                this.categories = cats.data || [];
                this.barangays = brgs.data || [];
            } catch (e) {
                console.error('Failed to load categories/barangays', e);
            }
        }
        setTimeout(() => {
            this.initMiniMap();
        }, 120);
    },
    initMiniMap() {
        const el = this.$refs.miniMap;
        if (!el) return;

        const lat = parseFloat(this.form.lat) || 17.5965;
        const lng = parseFloat(this.form.lng) || 120.6190;

        if (this.miniMap) {
            this.miniMap.invalidateSize();
            if (this.marker) {
                this.marker.setLatLng([lat, lng]);
            }
            this.miniMap.panTo([lat, lng]);
            return;
        }

        this.miniMap = L.map(el, {
            center: [lat, lng],
            zoom: 16,
            scrollWheelZoom: true,
        });

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19,
        }).addTo(this.miniMap);

        this.marker = L.marker([lat, lng], {
            draggable: true,
            icon: dropPinIcon(),
        }).addTo(this.miniMap);

        this.marker.on('drag', () => {
            const ll = this.marker.getLatLng();
            this.form.lat = ll.lat.toFixed(6);
            this.form.lng = ll.lng.toFixed(6);
        });

        this.miniMap.on('click', (e) => {
            if (this.marker) {
                this.marker.setLatLng(e.latlng);
            }
            this.form.lat = e.latlng.lat.toFixed(6);
            this.form.lng = e.latlng.lng.toFixed(6);
        });

        setTimeout(() => {
            if (this.miniMap) {
                this.miniMap.invalidateSize();
            }
        }, 200);
    },
    updateMapFromInputs() {
        const lat = parseFloat(this.form.lat);
        const lng = parseFloat(this.form.lng);
        if (!isNaN(lat) && !isNaN(lng) && this.marker && this.miniMap) {
            this.marker.setLatLng([lat, lng]);
            this.miniMap.panTo([lat, lng]);
        }
    },
    recenterBangued() {
        this.form.lat = '17.596500';
        this.form.lng = '120.619000';
        this.updateMapFromInputs();
    },
    useMyLocation() {
        if (!navigator.geolocation) return;
        this.locating = true;
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                this.locating = false;
                this.form.lat = pos.coords.latitude.toFixed(6);
                this.form.lng = pos.coords.longitude.toFixed(6);
                this.updateMapFromInputs();
            },
            (err) => {
                this.locating = false;
                console.warn('Geolocation failed', err);
            },
            { timeout: 8000, enableHighAccuracy: true }
        );
    },
    onPhotoSelected(e) {
        const file = e.target.files && e.target.files[0];
        if (!file) return;
        this.photoFile = file;
        this.photoName = file.name;
        const reader = new FileReader();
        reader.onload = (ev) => {
            this.photoPreview = ev.target.result;
        };
        reader.readAsDataURL(file);
    },
    clearPhoto() {
        this.photoFile = null;
        this.photoName = '';
        this.photoPreview = null;
    },
    closeModal() {
        this.open = false;
        this.errors = {};
        this.clearPhoto();
        if (this.miniMap) {
            this.miniMap.remove();
            this.miniMap = null;
            this.marker = null;
        }
        this.form = {
            name: '',
            category_id: '',
            barangay_id: '',
            address: '',
            lat: '17.596500',
            lng: '120.619000',
            phone: '',
            website: '',
            description: '',
        };
    },
    async submit() {
        this.submitting = true;
        this.errors = {};
        try {
            const payload = {
                name: this.form.name,
                category_id: parseInt(this.form.category_id, 10),
                barangay_id: this.form.barangay_id ? parseInt(this.form.barangay_id, 10) : null,
                address: this.form.address || null,
                lat: parseFloat(this.form.lat),
                lng: parseFloat(this.form.lng),
                phone: this.form.phone || null,
                website: this.form.website || null,
                description: this.form.description || null,
            };

            const res = await api.post('/api/admin/places', payload);
            const createdPlace = res.data?.data || res.data;
            const placeId = createdPlace?.id || createdPlace?.place_id;

            if (this.photoFile && placeId) {
                try {
                    const fd = new FormData();
                    fd.append('photo', this.photoFile);
                    await api.post(`/api/places/${placeId}/photos`, fd);
                } catch (photoErr) {
                    // Swallowing this made a failed upload look like a clean
                    // create, so the admin only found out later that the place
                    // had no photo.
                    const detail = photoErr.response?.data?.errors?.photo
                        ? photoErr.response.data.errors.photo[0]
                        : photoErr.response?.data?.message;
                    notify(
                        `Place created, but the photo was not uploaded. ${detail || ''}`.trim(),
                        'error'
                    );
                }
            }

            const msg = res.data?.message || res.data?.data?.message || 'Place created and published!';
            notify(msg, 'success');
            window.dispatchEvent(new CustomEvent('place-created', { detail: res.data }));
            this.closeModal();
        } catch (e) {
            if (e.response && e.response.data && e.response.data.errors) {
                this.errors = e.response.data.errors;
            } else {
                const err = e.response?.data?.message || 'Failed to create place';
                notify(err, 'error');
            }
        } finally {
            this.submitting = false;
        }
    },
}));

Alpine.data('themeToggle', themeToggle);

Alpine.data('adminToast', () => ({
    message: '',
    type: 'success',
    show: false,
    timer: null,
    init() {
        const el = document.getElementById('admin-flash');
        if (el && el.dataset.message) {
            this.message = el.dataset.message;
            this.type = el.dataset.type || 'success';
            this.pop();
        }
        window.addEventListener('admin-toast', (e) => {
            this.message = e.detail.message;
            this.type = e.detail.type || 'success';
            this.pop();
        });
    },
    pop() {
        clearTimeout(this.timer);
        this.show = true;
        this.timer = setTimeout(() => {
            this.show = false;
        }, 3500);
    },
}));

Alpine.data('adminDashboard', () => ({
    totalApproved: null,
    pendingTotal: null,
    openNow: null,
    categoryCount: null,
    byAction: null,
    maxAction: 1,
    error: '',
    async init() {
        window.addEventListener('place-created', () => this.init());
        try {
            const [places, placesOpen, pending, categories] = await Promise.all([
                api.get('/api/places?page=1'),
                api.get('/api/places?hours=open_now&page=1'),
                api.get('/api/admin/analytics/pending'),
                api.get('/api/categories'),
            ]);
            this.totalApproved = totalOf(places.meta);
            this.openNow = totalOf(placesOpen.meta);
            this.pendingTotal = pending.total != null ? pending.total : 0;
            this.byAction = pending.by_action || {};
            this.categoryCount = (categories.data || []).length;
            const list = ['create', 'update', 'move', 'photo', 'delete'].map((a) => ({ key: a, count: this.byAction[a] || 0 }));
            this.maxAction = Math.max(1, ...list.map((a) => a.count));
        } catch (e) {
            this.error = e.response?.data?.message || 'Failed to load dashboard data';
        }
    },
    actionsList() {
        if (!this.byAction) return [];
        return ['create', 'update', 'move', 'photo', 'delete'].map((a) => ({ key: a, count: this.byAction[a] || 0 }));
    },
    kpis() {
        return [
            { label: 'Approved places', value: this.totalApproved, icon: 'ph ph-map-pin', bg: 'var(--accent-soft)', color: 'var(--success)' },
            { label: 'Pending queue', value: this.pendingTotal, icon: 'ph ph-tray', bg: 'var(--warn-soft)', color: 'var(--warn)' },
            { label: 'Open now', value: this.openNow, icon: 'ph ph-clock', bg: 'var(--info-soft)', color: 'var(--info)' },
            { label: 'Categories', value: this.categoryCount, icon: 'ph ph-squares-four', bg: 'var(--violet-soft)', color: 'var(--violet)' },
        ];
    },
}));

window.Alpine = Alpine;
Alpine.start();