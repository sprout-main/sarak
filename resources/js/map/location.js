export function getPosition() {
    return new Promise((resolve, reject) => {
        if (!('geolocation' in navigator)) {
            reject(new Error('Geolocation is not supported here.'));
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({
                lat: pos.coords.latitude,
                lng: pos.coords.longitude,
                accuracy: pos.coords.accuracy,
            }),
            (err) => {
                const message = err && err.code === 1 ? 'Location permission declined.' : 'Location unavailable.';
                const wrapped = new Error(message);
                wrapped.code = err && err.code;
                reject(wrapped);
            },
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 }
        );
    });
}

export function aroundParams(coords, radius = 5000) {
    return {
        lat: coords.lat.toFixed(6),
        lng: coords.lng.toFixed(6),
        radius: radius || 5000,
    };
}