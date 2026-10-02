import { api, debounce } from './api';
import { getPosition } from './location';

const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

export function makeHoursRows() {
    return Array.from({ length: 7 }, (_, day) => ({
        day,
        label: DAY_LABELS[day],
        is_closed: true,
        opens: '09:00',
        closes: '18:00',
        note: '',
    }));
}

export function serializeHours(rows) {
    if (!Array.isArray(rows)) return [];
    return rows.map((row) => {
        const out = {
            day: row.day,
            opens: row.is_closed || !row.opens ? null : row.opens,
            closes: row.is_closed || !row.closes ? null : row.closes,
            is_closed: Boolean(row.is_closed),
        };
        if (row.note) out.note = row.note;
        return out;
    });
}

export function collectErrors(err) {
    if (err && err.errors && typeof err.errors === 'object') return err.errors;
    if (err && err.detail && typeof err.detail === 'object') return err.detail;
    return {};
}

export async function submitPlaceForm(composer) {
    composer.formBusy = true;
    composer.formErrors = {};

    const lat = Number(composer.formLat);
    const lng = Number(composer.formLng);

    const localErrors = {};
    if (!String(composer.formCategoryId)) localErrors.category_id = ['Choose a category.'];
    if (!String(composer.formName).trim()) localErrors.name = ['Name is required.'];
    if (!composer.formLat || !Number.isFinite(lat) || lat < -90 || lat > 90) localErrors.lat = ['Enter a valid latitude.'];
    if (!composer.formLng || !Number.isFinite(lng) || lng < -180 || lng > 180) localErrors.lng = ['Enter a valid longitude.'];

    const hasLocalErrors = Object.keys(localErrors).length > 0;
    composer.formErrors = hasLocalErrors
        ? localErrors
        : {};

    if (hasLocalErrors) {
        composer.formBusy = false;
        return;
    }

    const body = {
        category_id: Number(composer.formCategoryId),
        name: String(composer.formName).trim(),
        description: String(composer.formDesc).trim() || undefined,
        address: String(composer.formAddress).trim() || undefined,
        barangay_id: composer.formBarangayId ? Number(composer.formBarangayId) : undefined,
        lat,
        lng,
        phone: String(composer.formPhone).trim() || undefined,
        website: String(composer.formWebsite).trim() || undefined,
    };

    const hours = serializeHours(composer.hoursRows);
    if (hours.some((h) => !h.is_closed)) body.hours = hours;

    try {
        const res = await api.postPlace(body);
        const placeId = res?.data?.id || res?.data?.place_id || res?.data?.data?.id || res?.data?.data?.place_id;
        if (composer.photoFile && placeId) {
            try {
                const formData = new FormData();
                formData.append('photo', composer.photoFile);
                await api.postPhoto(placeId, formData);
            } catch (pErr) {
                console.warn('Photo upload failed', pErr);
            }
        }
        composer.showToast(res?.data?.message || res?.data?.data?.message || 'Place submitted successfully!');
        composer.submitOpen = false;
        composer.resetForm();
        if (window.SarakIsAdmin) {
            composer.loadPlaces();
        }
    } catch (err) {
        if (err.status === 422) {
            composer.formErrors = collectErrors(err);
        } else {
            composer.showToast(err.message || 'Could not submit. Try again.');
        }
    } finally {
        composer.formBusy = false;
    }
}

export async function useMyLocationForForm(composer) {
    composer.formLocating = true;
    try {
        const pos = await getPosition();
        composer.formLat = pos.lat.toFixed(6);
        composer.formLng = pos.lng.toFixed(6);
        if (typeof composer.autoResolveBarangay === 'function') {
            composer.autoResolveBarangay(pos.lat, pos.lng);
        }
        const errors = { ...(composer.formErrors || {}) };
        delete errors.lat;
        delete errors.lng;
        composer.formErrors = errors;
    } catch (err) {
        const errors = { ...(composer.formErrors || {}), lat: [err.message || 'Location unavailable.'] };
        errors.lng = undefined;
        if (errors.lng === undefined) delete errors.lng;
        composer.formErrors = errors;
    } finally {
        composer.formLocating = false;
    }
}

export { debounce };