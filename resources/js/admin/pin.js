import L from 'leaflet';

export function dropPinIcon() {
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
