export function currentBbox(map) {
    const b = map.getBounds();
    const south = b.getSouth().toFixed(6);
    const west = b.getWest().toFixed(6);
    const north = b.getNorth().toFixed(6);
    const east = b.getEast().toFixed(6);
    return `${south},${west},${north},${east}`;
}