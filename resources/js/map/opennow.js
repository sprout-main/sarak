export const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
export const DAY_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const MANILA_DAY_INDEX = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };

export function to12h(hhmm) {
    if (!hhmm) return '--';
    const [h, m] = String(hhmm).split(':').map(Number);
    if (!Number.isFinite(h) || !Number.isFinite(m)) return '--';
    const suffix = h >= 12 ? 'PM' : 'AM';
    let hr = h % 12;
    if (hr === 0) hr = 12;
    return `${hr}:${String(m).padStart(2, '0')} ${suffix}`;
}

export function manilaTodayIndex() {
    try {
        const label = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Manila', weekday: 'short' }).format(new Date());
        const index = MANILA_DAY_INDEX[label];
        if (Number.isInteger(index)) return index;
    } catch (e) {}
    return new Date().getDay();
}

export function nextOpenRow(rows, today) {
    if (!Array.isArray(rows) || !rows.length) return null;
    for (let offset = 1; offset <= 6; offset++) {
        const day = (today + offset) % 7;
        const row = rows.find((item) => item && item.day === day);
        if (row && !row.is_closed && row.opens) {
            return {
                row,
                day,
                label: offset === 1 ? 'tomorrow' : DAY_NAMES[day],
            };
        }
    }
    return null;
}

export function hoursHeadline(place, today = manilaTodayIndex()) {
    if (!place) {
        return { state: 'Unknown', detail: 'Hours not provided', kind: 'maybe' };
    }

    const telemetryStatus = place.telemetry ? place.telemetry.status : null;

    if (place.open_24h === true || telemetryStatus === 'open_24h') {
        return { state: 'Open', detail: '24 hours', kind: 'open' };
    }

    const rows = Array.isArray(place.hours) && place.hours.length ? place.hours : null;
    const todayRow = rows
        ? rows.find((row) => row && row.day === today) || null
        : (place.hours_today || null);

    const openNow = place.open_now === true || telemetryStatus === 'open' || telemetryStatus === 'closing_soon';

    if (openNow) {
        const closes = todayRow && !todayRow.is_closed ? todayRow.closes : null;
        return {
            state: 'Open',
            detail: closes ? `Closes at ${to12h(closes)}` : 'Open now',
            kind: 'open',
        };
    }

    const closedNow = place.open_now === false || telemetryStatus === 'closed';

    if (closedNow) {
        const opens = todayRow && !todayRow.is_closed ? todayRow.opens : null;
        if (opens) {
            return { state: 'Closed', detail: `Opens at ${to12h(opens)}`, kind: 'closed' };
        }
        const next = nextOpenRow(rows, today);
        if (next) {
            return {
                state: 'Closed',
                detail: `Opens ${next.label} at ${to12h(next.row.opens)}`,
                kind: 'closed',
            };
        }
        if (todayRow && todayRow.is_closed) {
            return { state: 'Closed', detail: 'Closed today', kind: 'closed' };
        }
        return { state: 'Closed', detail: 'Closed now', kind: 'closed' };
    }

    return { state: 'Unknown', detail: 'Hours not provided', kind: 'maybe' };
}

export function formatDistance(m) {
    if (m === null || m === undefined || !Number.isFinite(m)) return null;
    if (m >= 1000) return `${(m / 1000).toFixed(1)} km`;
    return `${Math.round(m)} m`;
}
