export const CATEGORY_ICON_MAP = {
    all: 'ph-grid-four',
    coffee_shop: 'ph-coffee',
    coffee_shops: 'ph-coffee',
    coffeeshop: 'ph-coffee',
    coffeeshops: 'ph-coffee',
    coffee: 'ph-coffee',
    cafe: 'ph-coffee',
    cafes: 'ph-coffee',
    kape: 'ph-coffee',
    kapehan: 'ph-coffee',
    fast_food: 'ph-hamburger',
    fast_foods: 'ph-hamburger',
    fastfood: 'ph-hamburger',
    food: 'ph-hamburger',
    burger: 'ph-hamburger',
    grocery: 'ph-shopping-cart',
    groceries: 'ph-shopping-cart',
    market: 'ph-shopping-cart',
    markets: 'ph-shopping-cart',
    supermarket: 'ph-storefront',
    supermarkets: 'ph-storefront',
    convenience: 'ph-shopping-cart',
    convenience_store: 'ph-shopping-cart',
    convenience_stores: 'ph-shopping-cart',
    store: 'ph-shopping-cart',
    stores: 'ph-shopping-cart',
    shop: 'ph-shopping-cart',
    shops: 'ph-shopping-cart',
    tindahan: 'ph-shopping-cart',
    hospital: 'ph-first-aid',
    hospitals: 'ph-first-aid',
    clinic: 'ph-first-aid',
    clinics: 'ph-first-aid',
    medical: 'ph-first-aid',
    health: 'ph-first-aid',
    health_center: 'ph-first-aid',
    doctor: 'ph-first-aid',
    pharmacy: 'ph-pill',
    pharmacies: 'ph-pill',
    drugstore: 'ph-pill',
    drugstores: 'ph-pill',
    medicine: 'ph-pill',
    medicines: 'ph-pill',
    botika: 'ph-pill',
    school: 'ph-graduation-cap',
    schools: 'ph-graduation-cap',
    education: 'ph-graduation-cap',
    college: 'ph-graduation-cap',
    colleges: 'ph-graduation-cap',
    university: 'ph-graduation-cap',
    universities: 'ph-graduation-cap',
    academy: 'ph-graduation-cap',
    eskwela: 'ph-graduation-cap',
    gas_station: 'ph-gas-pump',
    gas_stations: 'ph-gas-pump',
    gas: 'ph-gas-pump',
    fuel: 'ph-gas-pump',
    petrol: 'ph-gas-pump',
    restaurant: 'ph-fork-knife',
    restaurants: 'ph-fork-knife',
    dining: 'ph-fork-knife',
    carinderia: 'ph-fork-knife',
    karinderya: 'ph-fork-knife',
    eatery: 'ph-fork-knife',
    bank: 'ph-bank',
    banks: 'ph-bank',
    atm: 'ph-money',
    atms: 'ph-money',
    money: 'ph-money',
    government: 'ph-buildings',
    civic: 'ph-buildings',
    hall: 'ph-buildings',
    municipal: 'ph-buildings',
    capitol: 'ph-buildings',
    hotel: 'ph-bed',
    hotels: 'ph-bed',
    lodging: 'ph-bed',
    inn: 'ph-bed',
    resort: 'ph-bed',
    motel: 'ph-bed',
    police: 'ph-shield',
    fire_station: 'ph-flame',
    fire: 'ph-flame',
    church: 'ph-church',
    churches: 'ph-church',
    chapel: 'ph-church',
    worship: 'ph-church',
    place_of_worship: 'ph-church',
    park: 'ph-tree',
    parks: 'ph-tree',
    plaza: 'ph-tree',
    bakery: 'ph-cookie',
    bakeries: 'ph-cookie',
    bakeshop: 'ph-cookie',
    laundry: 'ph-t-shirt',
    repair: 'ph-wrench',
    bar: 'ph-beer-bottle',
    gym: 'ph-barbell',
    fitness: 'ph-barbell',
    library: 'ph-books',
    pin: 'ph-map-pin',
    default: 'ph-map-pin',
};

export function getCategoryIcon(key) {
    if (!key) return 'ph-map-pin';
    const raw = String(key).toLowerCase().trim();
    if (raw.startsWith('ph-')) {
        return raw;
    }
    const normalized = raw.replace(/[-_\s]+/g, '_');
    if (CATEGORY_ICON_MAP[normalized]) {
        return CATEGORY_ICON_MAP[normalized];
    }
    if (CATEGORY_ICON_MAP[raw]) {
        return CATEGORY_ICON_MAP[raw];
    }
    const singular = normalized.endsWith('ies')
        ? normalized.slice(0, -3) + 'y'
        : (normalized.endsWith('s') && !normalized.endsWith('ss') ? normalized.slice(0, -1) : normalized);
    if (CATEGORY_ICON_MAP[singular]) {
        return CATEGORY_ICON_MAP[singular];
    }
    return 'ph-map-pin';
}

if (typeof window !== 'undefined') {
    window.catIcon = getCategoryIcon;
}
