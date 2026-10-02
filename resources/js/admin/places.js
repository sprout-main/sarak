import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';
import 'leaflet.markercluster';
import { api, notify, fmtDateTime, haversineM } from './api.js';
import { dropPinIcon } from './pin.js';

const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function emptyHours() {
    return DAYS.map((_, day) => ({ day, opens: '', closes: '', is_closed: false, note: '' }));
}

export function adminPlaces() {
    return {
        items: [],
        meta: null,
        page: 1,
        q: '',
        category: '',
        status: '',
        categories: [],
        barangays: [],
        loading: true,
        error: '',
        searchTimer: null,
        editing: null,
        form: {
            id: null,
            name: '',
            description: '',
            address: '',
            phone: '',
            website: '',
            category_id: '',
            hours: emptyHours(),
        },
        saving: false,
        deleting: null,
        deleteBusy: false,
        photos: [],
        photosLoading: false,
        photoBusy: null,
        uploadingPhoto: false,
        dragging: null,
        dragLat: 0,
        dragLng: 0,
        dragBarangay: '',
        originLat: 0,
        originLng: 0,
        distanceM: 0,
        undoStack: [],
        snapInfo: '',
        map: null,
        marker: null,
        mapReady: false,

        fmt: fmtDateTime,
        catIcon(key) {
            return typeof window.catIcon === 'function' ? window.catIcon(key) : 'ph-map-pin';
        },

        async init() {
            window.addEventListener('place-created', () => this.reload());
            const [categories, barangays] = await Promise.all([
                api.get('/api/categories').catch(() => ({ data: [] })),
                api.get('/api/barangays').catch(() => ({ data: [] })),
            ]);
            this.categories = categories.data || [];
            this.barangays = barangays.data || [];
            await this.reload();
        },

        async reload() {
            await this.setPage(this.page);
        },

        async setPage(page) {
            this.page = page;
            this.loading = true;
            this.error = '';
            const qs = new URLSearchParams();
            if (this.q) qs.set('q', this.q);
            if (this.category) qs.set('category_id', this.category);
            if (this.status) qs.set('status', this.status);
            qs.set('page', page);
            try {
                const r = await this.fetchList(qs, page);
                this.items = r.data || [];
                this.meta = r.meta || {};
            } catch (e) {
                this.items = [];
                this.meta = null;
                this.error = e.response?.data?.message || 'Failed to load places';
            } finally {
                this.loading = false;
            }
        },

        async fetchList(qs, page) {
            try {
                return await api.get('/api/admin/places?' + qs.toString());
            } catch (e) {
                if (e.response && e.response.status === 404) {
                    const pq = new URLSearchParams();
                    if (this.q) pq.set('q', this.q);
                    if (this.category) {
                        const c = this.categories.find((x) => x.id == this.category);
                        if (c) pq.set('categories', c.slug);
                    }
                    pq.set('page', page);
                    const r = await api.get('/api/places?' + pq.toString());
                    const items = (r.data || []).map((x) => ({
                        id: x.id,
                        name: x.name,
                        slug: x.slug,
                        category: x.category ? { id: x.category.id, label: x.category.label, slug: x.category.slug, color: x.category.color } : null,
                        barangay: x.barangay ? { id: x.barangay.id, name: x.barangay.name } : null,
                        lat: x.lat,
                        lng: x.lng,
                        status: 'approved',
                        source: x.source,
                        verified_tier: x.verified_tier,
                        updated_at: null,
                    }));
                    return { data: items, meta: r.meta };
                }
                throw e;
            }
        },

        onSearch() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.setPage(1), 300);
        },

        badgeClass(kind) {
            const map = {
                approved: 'badge-green',
                pending: 'badge-amber',
                rejected: 'badge-red',
                archived: 'badge-zinc',
            };
            return map[kind] || 'badge-zinc';
        },

        sourceBadge(source) {
            const map = {
                manual: 'badge-zinc',
                overpass: 'badge-sky',
                nominatim: 'badge-violet',
                official_list: 'badge-amber',
                google_ref: 'badge-sky',
                community: 'badge-green',
            };
            return map[source] || 'badge-zinc';
        },

        dayLabel(day) {
            const d = Number(day);
            return DAYS[d] != null ? DAYS[d] : String(day);
        },

        openEdit(place) {
            this.editing = place;
            this.form = {
                id: place.id,
                name: place.name || '',
                description: place.description || '',
                address: place.address || '',
                phone: place.phone || '',
                website: place.website || '',
                category_id: place.category ? place.category.id : '',
                hours: emptyHours(),
            };
            this.loadDetail(place.id);
            this.loadPhotos(place.id);
        },

        closeEdit() {
            this.editing = null;
            this.photos = [];
            this.photoBusy = null;
        },

        photoUrl(photo) {
            const path = photo && photo.path ? photo.path : '';
            if (/^(https?:)?\/\//i.test(path)) return path;
            return '/storage/' + String(path).replace(/^\/+/, '');
        },

        async loadPhotos(id) {
            this.photosLoading = true;
            try {
                const r = await api.get(`/api/admin/places/${id}/photos`);
                this.photos = Array.isArray(r.data?.data) ? r.data.data : [];
            } catch (e) {
                this.photos = [];
                notify(e.response?.data?.message || 'Could not load photos', 'error');
            } finally {
                this.photosLoading = false;
            }
        },

        async uploadPhoto(event) {
            const input = event.target;
            const file = input && input.files && input.files[0];
            if (!file || !this.form.id) return;

            this.uploadingPhoto = true;
            try {
                const fd = new FormData();
                fd.append('photo', file);
                const r = await api.post(`/api/admin/places/${this.form.id}/photos`, fd);
                notify(r.data?.data?.message || 'Photo uploaded');
                await this.loadPhotos(this.form.id);
                await this.reload();
            } catch (e) {
                notify(e.response?.data?.message || 'Photo upload failed', 'error');
            } finally {
                this.uploadingPhoto = false;
                input.value = '';
            }
        },

        async setCoverPhoto(photo) {
            this.photoBusy = photo.id;
            try {
                const r = await api.patch(`/api/admin/places/${this.form.id}/photos/${photo.id}/cover`);
                notify(r.data?.data?.message || 'Cover photo updated');
                await this.loadPhotos(this.form.id);
                await this.reload();
            } catch (e) {
                notify(e.response?.data?.message || 'Could not set cover photo', 'error');
            } finally {
                this.photoBusy = null;
            }
        },

        async deletePhoto(photo) {
            if (!window.confirm('Delete this photo? The file is removed from disk and cannot be recovered.')) return;

            this.photoBusy = photo.id;
            try {
                const r = await api.delete(`/api/admin/places/${this.form.id}/photos/${photo.id}`);
                notify(r.data?.data?.message || 'Photo deleted');
                await this.loadPhotos(this.form.id);
                await this.reload();
            } catch (e) {
                notify(e.response?.data?.message || 'Could not delete photo', 'error');
            } finally {
                this.photoBusy = null;
            }
        },

        async loadDetail(id) {
            try {
                const r = await api.get('/api/places/' + id);
                const d = r.data || {};
                if (d.description) this.form.description = d.description;
                if (Array.isArray(d.hours) && d.hours.length === 7) {
                    this.form.hours = d.hours.map((h) => ({
                        day: h.day,
                        opens: h.opens || '',
                        closes: h.closes || '',
                        is_closed: !!h.is_closed,
                        note: h.note || '',
                    }));
                }
            } catch (e) {
            }
        },

        async saveEdit() {
            this.saving = true;
            const f = this.form;
            const payload = {
                name: f.name.trim(),
                description: f.description || null,
                address: f.address || null,
                phone: f.phone || null,
                website: f.website || null,
                category_id: f.category_id ? Number(f.category_id) : null,
                hours: f.hours.map((h) => ({
                    day: h.day,
                    opens: h.opens || null,
                    closes: h.closes || null,
                    is_closed: !!h.is_closed,
                    note: h.note ? h.note : null,
                })),
            };
            try {
                const r = await api.patch('/api/admin/places/' + f.id, payload);
                notify(r.data?.message || 'Place updated');
                this.closeEdit();
                await this.reload();
            } catch (e) {
                notify(e.response?.data?.message || 'Update failed', 'error');
            } finally {
                this.saving = false;
            }
        },

        confirmDelete(place) {
            this.deleting = place;
        },

        cancelDelete() {
            this.deleting = null;
            this.deleteBusy = false;
        },

        async performDelete() {
            if (!this.deleting) return;
            this.deleteBusy = true;
            const targetId = this.deleting.id;
            const targetName = this.deleting.name;
            try {
                const res = await api.delete('/api/admin/places/' + targetId);
                notify(res?.data?.message || `Place '${targetName}' deleted successfully`);
                if (this.editing && this.editing.id === targetId) {
                    this.closeEdit();
                }
                this.deleting = null;
                await this.reload();
            } catch (e) {
                notify(e.response?.data?.message || 'Failed to delete place', 'error');
            } finally {
                this.deleteBusy = false;
            }
        },

        openDrag(place) {
            this.dragging = place;
            this.dragLat = place.lat;
            this.dragLng = place.lng;
            this.originLat = place.lat;
            this.originLng = place.lng;
            this.dragBarangay = place.barangay ? String(place.barangay.id) : '';
            this.distanceM = 0;
            this.undoStack = [];
            this.snapInfo = '';
            this.mapReady = false;
            this.$nextTick(() => this.initMap());
        },

        closeDrag() {
            if (this.map) {
                this.map.remove();
                this.map = null;
            }
            this.marker = null;
            this.dragging = null;
            this.mapReady = false;
            this.undoStack = [];
            this.distanceM = 0;
            this.snapInfo = '';
        },

        initMap() {
            if (this.mapReady) return;
            const el = this.$refs.dragMap;
            if (!el) return;
            this.mapReady = true;
            this.map = L.map(el, {
                center: [this.dragLat, this.dragLng],
                zoom: 16,
                scrollWheelZoom: false,
            });
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
                maxZoom: 19,
            }).addTo(this.map);
            this.marker = L.marker([this.dragLat, this.dragLng], {
                draggable: true,
                icon: dropPinIcon(),
            }).addTo(this.map);
            this.marker.on('dragstart', () => this.pushUndo());
            this.marker.on('drag', () => this.syncFromMarker());
        },

        pushUndo() {
            const last = this.undoStack[this.undoStack.length - 1];
            const current = { lat: this.dragLat, lng: this.dragLng };
            if (!last || last.lat !== current.lat || last.lng !== current.lng) {
                this.undoStack.push(current);
                if (this.undoStack.length > 50) this.undoStack.shift();
            }
        },

        applyCoords(coords) {
            if (this.marker) this.marker.setLatLng([coords.lat, coords.lng]);
            if (this.map) this.map.panTo([coords.lat, coords.lng]);
            this.distanceM = haversineM(this.originLat, this.originLng, coords.lat, coords.lng);
        },

        syncFromMarker() {
            const ll = this.marker.getLatLng();
            this.dragLat = Number(ll.lat.toFixed(7));
            this.dragLng = Number(ll.lng.toFixed(7));
            this.distanceM = haversineM(this.originLat, this.originLng, this.dragLat, this.dragLng);
        },

        onLatInput(event) {
            const raw = event.target.value;
            if (raw === '') return;
            const value = Number(raw);
            if (Number.isNaN(value)) return;
            this.pushUndo();
            this.dragLat = value;
            this.applyCoords({ lat: this.dragLat, lng: this.dragLng });
        },

        onLngInput(event) {
            const raw = event.target.value;
            if (raw === '') return;
            const value = Number(raw);
            if (Number.isNaN(value)) return;
            this.pushUndo();
            this.dragLng = value;
            this.applyCoords({ lat: this.dragLat, lng: this.dragLng });
        },

        undo() {
            const previous = this.undoStack.pop();
            if (!previous) return;
            this.dragLat = previous.lat;
            this.dragLng = previous.lng;
            this.applyCoords(previous);
            this.snapInfo = '';
        },

        distanceLabel() {
            if (this.distanceM >= 1000) return (this.distanceM / 1000).toFixed(2) + ' km';
            return Math.round(this.distanceM) + ' m';
        },

        async snap() {
            this.snapInfo = '';
            try {
                const url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + this.dragLat + '&lon=' + this.dragLng + '&zoom=18&addressdetails=1';
                const r = await fetch(url);
                if (!r.ok) {
                    this.snapInfo = 'Snap unavailable';
                    return;
                }
                const j = await r.json();
                const road = (j && j.address && (j.address.road || j.address.pedestrian || j.address.highway)) || '';
                if (j && j.lat && j.lon) {
                    this.pushUndo();
                    this.dragLat = Number(Number(j.lat).toFixed(7));
                    this.dragLng = Number(Number(j.lon).toFixed(7));
                    this.applyCoords({ lat: this.dragLat, lng: this.dragLng });
                    this.snapInfo = road ? 'Snapped near ' + road : 'Position snapped';
                } else {
                    this.snapInfo = 'No road found at this spot';
                }
            } catch (e) {
                this.snapInfo = 'Snap unavailable offline';
            }
        },

        async saveLocation() {
            if (this.dragging === null) return;
            const payload = { lat: this.dragLat, lng: this.dragLng };
            if (this.dragBarangay) payload.barangay_id = Number(this.dragBarangay);
            try {
                const r = await api.patch('/api/admin/places/' + this.dragging.id + '/location', payload);
                notify(r.data?.message || 'Location saved');
                await this.reload();
                this.closeDrag();
            } catch (e) {
                notify(e.response?.data?.message || 'Save failed', 'error');
            }
        },
    };
}