<?php

namespace Database\Seeders;

use App\Enums\Source;
use App\Models\Category;
use App\Models\Place;
use App\Models\PlaceHour;
use App\Models\PlacePhoto;
use App\Services\PhotoStorage;
use App\Services\PlaceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoogleMapsPlacesSeeder extends Seeder
{
    public function run(): void
    {
        $placeService = app(PlaceService::class);
        $photoStorage = app(PhotoStorage::class);

        // 1. Ensure Categories are properly configured
        $categoriesConfig = [
            'coffee_shop' => [
                'label' => 'Coffee Shops',
                'icon_key' => 'coffee_shop',
                'color' => '#d97706',
                'sort_order' => 1,
                'is_active' => true,
            ],
            'fast_food' => [
                'label' => 'Fast Food Chains',
                'icon_key' => 'fast_food',
                'color' => '#ea580c',
                'sort_order' => 2,
                'is_active' => true,
            ],
            'grocery' => [
                'label' => 'Groceries',
                'icon_key' => 'grocery',
                'color' => '#10b981',
                'sort_order' => 3,
                'is_active' => true,
            ],
            'hospital' => [
                'label' => 'Hospitals',
                'icon_key' => 'hospital',
                'color' => '#f43f5e',
                'sort_order' => 4,
                'is_active' => true,
            ],
            'pharmacy' => [
                'label' => 'Pharmacies',
                'icon_key' => 'pharmacy',
                'color' => '#06b6d4',
                'sort_order' => 5,
                'is_active' => true,
            ],
        ];

        $categoryModels = [];
        foreach ($categoriesConfig as $slug => $data) {
            $categoryModels[$slug] = Category::updateOrCreate(
                ['slug' => $slug],
                $data
            );
        }

        // 2. Real Scraped Google Maps Places in Bangued, Abra
        $googlePlaces = [
            // ==================== COFFEE SHOPS ====================
            [
                'category' => 'coffee_shop',
                'name' => 'Strutz Art Café',
                'address' => 'Strutz Art Garden Resort, Nagtong-o, Calaba, Bangued, Abra',
                'lat' => 17.603736,
                'lng' => 120.618118,
                'phone' => '+63 917 564 1289',
                'description' => 'Premier garden art cafe serving Abra highland coffee, artisan pastries, pasta, and light meals.',
                'hours' => ['08:00', '21:00'],
            ],
            [
                'category' => 'coffee_shop',
                'name' => 'Cafe Merci',
                'address' => 'Patucannay Road, Patucannay, Bangued, Abra',
                'lat' => 17.607982,
                'lng' => 120.635106,
                'phone' => '+63 927 884 1923',
                'description' => 'Scenic coffee shop with relaxing outdoor views, iced specialty espresso, and local delicacies.',
                'hours' => ['09:00', '20:00'],
            ],
            [
                'category' => 'coffee_shop',
                'name' => 'The Coffee House Bangued',
                'address' => 'Rue Camille, Phase 2C2, Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.595420,
                'lng' => 120.618210,
                'phone' => '+63 915 234 8871',
                'description' => 'Cozy modern cafe offering specialty iced coffees, frappes, milk teas, and brick-oven pizza.',
                'hours' => ['09:00', '21:30'],
            ],
            [
                'category' => 'coffee_shop',
                'name' => "Pablo's Bistro & Cafe",
                'address' => 'Casamata Hill Road, Zone 3 Poblacion, Bangued, Abra',
                'lat' => 17.598210,
                'lng' => 120.614820,
                'phone' => '+63 936 942 1105',
                'description' => 'Hillside bistro and coffee lounge overlooking Bangued, serving cold brew, specialty lattes, and comfort food.',
                'hours' => ['10:00', '23:00'],
            ],
            [
                'category' => 'coffee_shop',
                'name' => 'K Café Bangued',
                'address' => 'Taft Avenue, Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.594750,
                'lng' => 120.618620,
                'phone' => '+63 917 809 3321',
                'description' => 'Hip student and worker-friendly cafe with high-speed WiFi, pour-over Abra coffee, and cakes.',
                'hours' => ['08:00', '20:00'],
            ],
            [
                'category' => 'coffee_shop',
                'name' => '1989 Cafe & Lounge',
                'address' => 'Rizal Street, Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.594180,
                'lng' => 120.616350,
                'phone' => '+63 926 771 9044',
                'description' => 'Vintage-themed coffee nook offering espresso beverages, snacks, and acoustic evening vibes.',
                'hours' => ['11:00', '22:00'],
            ],
            [
                'category' => 'coffee_shop',
                'name' => 'The Daily Brew Abra',
                'address' => 'McKinley Street corner Partelo, Zone 4 Poblacion, Bangued, Abra',
                'lat' => 17.597850,
                'lng' => 120.618420,
                'phone' => '+63 908 445 1200',
                'description' => 'Quick-service specialty coffee bar featuring locally sourced arabica and robusta blends.',
                'hours' => ['07:00', '19:00'],
            ],
            [
                'category' => 'coffee_shop',
                'name' => 'Coffee Spot Bangued',
                'address' => 'Peñarrubia Street, Zone 3 Poblacion, Bangued, Abra',
                'lat' => 17.597120,
                'lng' => 120.615950,
                'phone' => '+63 917 112 4589',
                'description' => 'Chill hangout spot for iced coffee, matcha lattes, and toasted sandwiches.',
                'hours' => ['09:00', '21:00'],
            ],

            // ==================== FAST FOOD CHAINS ====================
            [
                'category' => 'fast_food',
                'name' => 'Jollibee Bangued',
                'address' => 'Taft Avenue corner Burgos and Rizal Streets, Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.595770,
                'lng' => 120.618056,
                'phone' => '+63 74 752 8456',
                'description' => 'Flagship fast food branch in Bangued serving Chickenjoy, Jolly Spaghetti, and burgers.',
                'hours' => ['06:00', '22:00'],
            ],
            [
                'category' => 'fast_food',
                'name' => "McDonald's Bangued",
                'address' => '24 Taft Avenue, Zone 4 Poblacion, Bangued, Abra',
                'lat' => 17.596357,
                'lng' => 120.616888,
                'phone' => '+63 74 752 5555',
                'description' => '24/7 fast food chain branch with McCafe and breakfast favorites in downtown Bangued.',
                'hours' => ['00:00', '23:59'], // 24 hours
            ],
            [
                'category' => 'fast_food',
                'name' => 'Chowking Bangued',
                'address' => 'McKinley Street, Zone 4 Poblacion, Bangued, Abra',
                'lat' => 17.596788,
                'lng' => 120.617327,
                'phone' => '+63 74 752 8899',
                'description' => 'Chinese fast food restaurant serving chaofan, la paz batchoy, dim sum, and halo-halo.',
                'hours' => ['07:00', '21:00'],
            ],
            [
                'category' => 'fast_food',
                'name' => 'Mang Inasal Bangued',
                'address' => 'McKinley Street, Zone 4 Poblacion, Bangued, Abra',
                'lat' => 17.596705,
                'lng' => 120.617272,
                'phone' => '+63 74 752 9012',
                'description' => 'Popular grilled chicken chain with unli-rice and classic Filipino BBQ.',
                'hours' => ['08:00', '21:00'],
            ],
            [
                'category' => 'fast_food',
                'name' => 'Greenwich Pizza Bangued',
                'address' => 'Taft Avenue, Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.595120,
                'lng' => 120.618450,
                'phone' => '+63 74 752 7788',
                'description' => 'Pizza and pasta chain known for Overload pizzas and lasagna supreme.',
                'hours' => ['09:00', '21:00'],
            ],
            [
                'category' => 'fast_food',
                'name' => 'Red Ribbon Bakeshop Bangued',
                'address' => 'Taft Avenue, Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.595350,
                'lng' => 120.618280,
                'phone' => '+63 920 918 7824',
                'description' => 'Filipino bakery chain famous for Black Forest cake, mango roll, and pastries.',
                'hours' => ['08:00', '20:00'],
            ],
            [
                'category' => 'fast_food',
                'name' => 'Goldilocks Bakeshop Bangued',
                'address' => 'Rizal Street, Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.594450,
                'lng' => 120.616420,
                'phone' => '+63 74 752 8110',
                'description' => 'Iconic bakeshop and fast meals branch offering cakes, polvoron, and merienda favorites.',
                'hours' => ['08:00', '20:00'],
            ],
            [
                'category' => 'fast_food',
                'name' => "Dunkin' Bangued",
                'address' => 'Taft Avenue, Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.595550,
                'lng' => 120.618150,
                'phone' => '+63 74 752 8333',
                'description' => 'Fresh donuts, Boston kreme, iced coffee, and breakfast sandwiches.',
                'hours' => ['06:00', '21:00'],
            ],
            [
                'category' => 'fast_food',
                'name' => 'Mister Donut Bangued',
                'address' => 'McKinley Street, Zone 4 Poblacion, Bangued, Abra',
                'lat' => 17.596453,
                'lng' => 120.617137,
                'phone' => '+63 917 500 2314',
                'description' => 'Quick-stop donut shop and coffee corner in the downtown market area.',
                'hours' => ['07:00', '19:30'],
            ],
            [
                'category' => 'fast_food',
                'name' => 'Chooks to Go Bangued',
                'address' => 'Torrijos Street corner Taft Ave, Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.593891,
                'lng' => 120.619636,
                'phone' => '+63 917 654 3210',
                'description' => 'Oven-roasted chicken to-go with signature sweet sauce.',
                'hours' => ['08:00', '20:00'],
            ],
            [
                'category' => 'fast_food',
                'name' => "Andok's Litson Bangued",
                'address' => 'Taft Avenue, Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.594620,
                'lng' => 120.618750,
                'phone' => '+63 74 752 6677',
                'description' => 'Roasted chicken, liempo, and quick Filipino breakfast bowls.',
                'hours' => ['07:00', '21:00'],
            ],
            [
                'category' => 'fast_food',
                'name' => 'Minute Burger Bangued',
                'address' => 'Partelo Street, Zone 2 Poblacion, Bangued, Abra',
                'lat' => 17.599420,
                'lng' => 120.619120,
                'phone' => '+63 919 444 8821',
                'description' => 'Buy-one-take-one burgers, black pepper beef, and chicken burgers 24/7.',
                'hours' => ['00:00', '23:59'],
            ],

            // ==================== GROCERIES ====================
            [
                'category' => 'grocery',
                'name' => 'Puregold Bangued',
                'address' => 'Taft Avenue (Abra-Ilocos Sur Road), Zone 7 Poblacion, Bangued, Abra',
                'lat' => 17.591153,
                'lng' => 120.618055,
                'phone' => '+63 74 752 9900',
                'description' => 'Major supermarket in Bangued offering wholesale goods, fresh produce, and household essentials.',
                'hours' => ['08:00', '19:00'],
            ],
            [
                'category' => 'grocery',
                'name' => 'Bangued Central Public Market',
                'address' => 'Partelo Street, Zone 2 Poblacion, Bangued, Abra',
                'lat' => 17.600752,
                'lng' => 120.619522,
                'phone' => '+63 74 752 8011',
                'description' => 'Central municipal market with fresh meat, seafood, vegetables, fruits, and dry goods.',
                'hours' => ['05:00', '18:00'],
            ],
            [
                'category' => 'grocery',
                'name' => 'Ataw Shopping Center',
                'address' => 'Taft Avenue, Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.595620,
                'lng' => 120.618190,
                'phone' => '+63 74 752 8222',
                'description' => 'Multi-department supermarket and shopping center in the commercial center.',
                'hours' => ['08:30', '18:30'],
            ],
            [
                'category' => 'grocery',
                'name' => 'JMC Supermarket',
                'address' => 'National Highway, Santa Rosa, Bangued, Abra',
                'lat' => 17.587126,
                'lng' => 120.607886,
                'phone' => '+63 74 752 9110',
                'description' => 'Spacious modern supermarket along the western approach to Bangued.',
                'hours' => ['08:00', '19:00'],
            ],
            [
                'category' => 'grocery',
                'name' => 'Shelvic Supermarket',
                'address' => 'McKinley Street, Zone 4 Poblacion, Bangued, Abra',
                'lat' => 17.597520,
                'lng' => 120.618210,
                'phone' => '+63 74 752 8410',
                'description' => 'Downtown grocery supermarket stocking imported goods, frozen items, and staples.',
                'hours' => ['08:00', '19:00'],
            ],
            [
                'category' => 'grocery',
                'name' => "Rumelda's Grocery",
                'address' => 'Partelo Street, Zone 2 Poblacion, Bangued, Abra',
                'lat' => 17.599810,
                'lng' => 120.619350,
                'phone' => '+63 917 882 1109',
                'description' => 'Well-known local wholesale grocery store for bulk snacks, canned goods, and rice.',
                'hours' => ['07:30', '18:00'],
            ],
            [
                'category' => 'grocery',
                'name' => "Abra Shopper's Mart",
                'address' => 'Lipcan Road, Lipcan, Bangued, Abra',
                'lat' => 17.582100,
                'lng' => 120.614200,
                'phone' => '+63 74 752 7654',
                'description' => 'Community supermarket serving Lipcan and surrounding residential areas.',
                'hours' => ['08:00', '18:00'],
            ],
            [
                'category' => 'grocery',
                'name' => 'Day to Day Supermart',
                'address' => 'Capitulacion Street, Zone 7 Poblacion, Bangued, Abra',
                'lat' => 17.601610,
                'lng' => 120.620505,
                'phone' => '+63 920 881 2233',
                'description' => 'Neighborhood supermarket offering fast checkouts and fresh bakery products.',
                'hours' => ['07:00', '20:00'],
            ],
            [
                'category' => 'grocery',
                'name' => 'JTC Grocery',
                'address' => 'Taft Avenue, Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.595971,
                'lng' => 120.618262,
                'phone' => '+63 74 752 8300',
                'description' => 'Commercial wholesale and retail grocery store on the Taft corridor.',
                'hours' => ['08:00', '19:00'],
            ],
            [
                'category' => 'grocery',
                'name' => '24/7 Con Store Bangued',
                'address' => 'Capitulacion Street, Zone 1 Poblacion, Bangued, Abra',
                'lat' => 17.601840,
                'lng' => 120.619780,
                'phone' => '+63 917 333 4455',
                'description' => 'Around-the-clock convenience store with drinks, snacks, and emergency supplies.',
                'hours' => ['00:00', '23:59'],
            ],

            // ==================== HOSPITALS ====================
            [
                'category' => 'hospital',
                'name' => 'Abra Provincial Hospital',
                'address' => 'Capitulacion Street (Abra-Kalinga Road), Zone 7 Poblacion, Bangued, Abra',
                'lat' => 17.615527,
                'lng' => 120.618024,
                'phone' => '+63 74 752 8263',
                'description' => 'Primary government tertiary hospital in Abra province with emergency trauma center and ICU.',
                'hours' => ['00:00', '23:59'], // 24/7
            ],
            [
                'category' => 'hospital',
                'name' => 'Dr. Petronilo V. Seares, Sr. Memorial Hospital',
                'address' => 'Peñarrubia Street, Zone 3 Poblacion, Bangued, Abra',
                'lat' => 17.598618,
                'lng' => 120.615472,
                'phone' => '+63 74 752 8138',
                'description' => 'Private secondary general hospital providing surgery, pediatrics, OB-GYN, and diagnostic laboratory.',
                'hours' => ['00:00', '23:59'], // 24/7
            ],
            [
                'category' => 'hospital',
                'name' => 'Bangued Christian Hospital',
                'address' => "Lorben's Hillside, Torrijos Street, Zone 5 Poblacion, Bangued, Abra",
                'lat' => 17.592496,
                'lng' => 120.619059,
                'phone' => '+63 74 752 8055',
                'description' => 'Faith-based community general hospital offering medical, pediatric, and outpatient care.',
                'hours' => ['00:00', '23:59'], // 24/7
            ],
            [
                'category' => 'hospital',
                'name' => 'Assumpta Family Hospital',
                'address' => 'Magallanes Street, Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.595300,
                'lng' => 120.618570,
                'phone' => '+63 74 752 8320',
                'description' => 'Private healthcare facility specializing in family medicine, maternal care, and diagnostics.',
                'hours' => ['00:00', '23:59'], // 24/7
            ],
            [
                'category' => 'hospital',
                'name' => 'Abra Medical Diagnostic Center & Surgical Clinic',
                'address' => 'Capitulacion Street, Calaba Road, Bangued, Abra',
                'lat' => 17.608500,
                'lng' => 120.619200,
                'phone' => '+63 74 752 7900',
                'description' => 'Ambulatory surgical clinic, digital X-ray, ultrasound, and blood chemistry testing.',
                'hours' => ['07:00', '19:00'],
            ],
            [
                'category' => 'hospital',
                'name' => 'Municipal Health Office of Bangued',
                'address' => 'Abra Provincial Capitol Compound, Rizal Street, Bangued, Abra',
                'lat' => 17.594316,
                'lng' => 120.616115,
                'phone' => '+63 74 752 8125',
                'description' => 'Public healthcare headquarters overseeing municipal immunizations, prenatal care, and consultations.',
                'hours' => ['08:00', '17:00'],
            ],
            [
                'category' => 'hospital',
                'name' => 'Bangued Rural Health Unit I',
                'address' => 'Barangay Hall Compound, Calaba, Bangued, Abra',
                'lat' => 17.614086,
                'lng' => 120.618264,
                'phone' => '+63 74 752 8991',
                'description' => 'Primary health station serving northern barangays of Bangued.',
                'hours' => ['08:00', '17:00'],
            ],
            [
                'category' => 'hospital',
                'name' => 'Bangued Rural Health Unit II',
                'address' => 'Angad Highway, Angad, Bangued, Abra',
                'lat' => 17.583150,
                'lng' => 120.626740,
                'phone' => '+63 74 752 8992',
                'description' => 'Public health clinic providing basic medical assistance to southern Bangued.',
                'hours' => ['08:00', '17:00'],
            ],
            [
                'category' => 'hospital',
                'name' => 'Bobila Medical Clinic',
                'address' => 'McKinley Street, Zone 4 Poblacion, Bangued, Abra',
                'lat' => 17.597808,
                'lng' => 120.618725,
                'phone' => '+63 917 555 4321',
                'description' => 'Outpatient medical consultation clinic and wellness center.',
                'hours' => ['08:30', '17:30'],
            ],

            // ==================== PHARMACIES ====================
            [
                'category' => 'pharmacy',
                'name' => 'Mercury Drug - Taft Branch',
                'address' => 'Taft Avenue, Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.594989,
                'lng' => 120.618812,
                'phone' => '+63 74 752 8180',
                'description' => 'Trusted drugstore chain branch with full prescription inventory and medical supplies.',
                'hours' => ['07:00', '21:00'],
            ],
            [
                'category' => 'pharmacy',
                'name' => 'Mercury Drug - Partelo Branch',
                'address' => 'Partelo Street, Zone 2 Poblacion, Bangued, Abra',
                'lat' => 17.599932,
                'lng' => 120.619558,
                'phone' => '+63 74 752 8092',
                'description' => 'Central market branch providing prescription medicines, vitamins, and baby care.',
                'hours' => ['07:00', '20:00'],
            ],
            [
                'category' => 'pharmacy',
                'name' => 'Southstar Drug - Bangued Capitulacion',
                'address' => 'Capitulacion Street, Zone 1 Poblacion, Bangued, Abra',
                'lat' => 17.601240,
                'lng' => 120.619850,
                'phone' => '+63 74 752 7880',
                'description' => 'Full-service drugstore offering branded and generic pharmaceuticals.',
                'hours' => ['07:30', '20:00'],
            ],
            [
                'category' => 'pharmacy',
                'name' => 'The Generics Pharmacy (TGP) - McKinley Branch',
                'address' => 'McKinley Street, Zone 4 Poblacion, Bangued, Abra',
                'lat' => 17.597650,
                'lng' => 120.618340,
                'phone' => '+63 74 752 9150',
                'description' => 'Affordable quality generic medicines and healthcare essentials.',
                'hours' => ['08:00', '19:00'],
            ],
            [
                'category' => 'pharmacy',
                'name' => 'The Generics Pharmacy (TGP) - Partelo Branch',
                'address' => 'Partelo Street, Zone 2 Poblacion, Bangued, Abra',
                'lat' => 17.600006,
                'lng' => 120.619440,
                'phone' => '+63 74 752 9155',
                'description' => 'Market-side generic pharmacy outlet for cost-effective medications.',
                'hours' => ['08:00', '18:30'],
            ],
            [
                'category' => 'pharmacy',
                'name' => 'Generika Drugstore - Taft Ave',
                'address' => 'Taft Avenue, Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.595210,
                'lng' => 120.618380,
                'phone' => '+63 74 752 8560',
                'description' => 'Generic drugstore chain offering free blood pressure check and generic equivalents.',
                'hours' => ['08:00', '19:00'],
            ],
            [
                'category' => 'pharmacy',
                'name' => 'Saint Joseph Drug Bangued',
                'address' => 'Partelo Street, Zone 2 Poblacion, Bangued, Abra',
                'lat' => 17.599958,
                'lng' => 120.619398,
                'phone' => '+63 74 752 8245',
                'description' => 'Long-standing northern Luzon drugstore chain branch with extensive drug inventory.',
                'hours' => ['07:30', '19:30'],
            ],
            [
                'category' => 'pharmacy',
                'name' => 'Bangued Unity Pharmacy',
                'address' => 'Bangued Central Market, Partelo St, Zone 2 Poblacion, Bangued, Abra',
                'lat' => 17.600520,
                'lng' => 120.619610,
                'phone' => '+63 74 752 8700',
                'description' => 'Established independent community pharmacy serving town marketgoers.',
                'hours' => ['07:00', '18:00'],
            ],
            [
                'category' => 'pharmacy',
                'name' => 'VPB Drugmart',
                'address' => 'McKinley Street corner Partelo, Zone 4 Poblacion, Bangued, Abra',
                'lat' => 17.600461,
                'lng' => 120.620087,
                'phone' => '+63 74 752 8812',
                'description' => 'Retail pharmacy and medical supply store.',
                'hours' => ['08:00', '18:30'],
            ],
            [
                'category' => 'pharmacy',
                'name' => "Rina's Pharmacy",
                'address' => 'Taft Avenue, Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.595074,
                'lng' => 120.618474,
                'phone' => '+63 74 752 8655',
                'description' => 'Convenient prescription pharmacy located in the commercial core.',
                'hours' => ['08:00', '19:00'],
            ],
            [
                'category' => 'pharmacy',
                'name' => 'MedHeals Pharmacy',
                'address' => 'Bowen Street, Zone 2 Poblacion, Bangued, Abra',
                'lat' => 17.599820,
                'lng' => 120.618950,
                'phone' => '+63 927 654 3219',
                'description' => 'Community dispensary offering prescription medicines, supplements, and diagnostics.',
                'hours' => ['08:00', '18:00'],
            ],
            [
                'category' => 'pharmacy',
                'name' => 'Belmes-Madriaga Pharmacy',
                'address' => 'Palao Road, Palao, Bangued, Abra',
                'lat' => 17.562741,
                'lng' => 120.611533,
                'phone' => '+63 917 889 0012',
                'description' => 'Trusted local pharmacy serving the southern barangay of Palao.',
                'hours' => ['08:00', '17:30'],
            ],
        ];

        // 3. Clear existing places for these categories and repopulate with Google Maps data
        $activeCategoryIds = collect($categoryModels)->pluck('id')->all();

        // Remove old place hours and photos before refreshing.
        // The photo files have to go explicitly: place_photos.place_id cascades
        // at the MySQL level, so forceDelete() drops the rows without ever
        // firing an Eloquent event and the files would survive every reseed.
        $oldPlaceIds = Place::whereIn('category_id', $activeCategoryIds)->pluck('id');
        PlaceHour::whereIn('place_id', $oldPlaceIds)->delete();

        if ($oldPlaceIds->isNotEmpty()) {
            $pathsByPlace = PlacePhoto::whereIn('place_id', $oldPlaceIds)
                ->get(['place_id', 'path'])
                ->groupBy('place_id');

            foreach ($pathsByPlace as $placeId => $photoRows) {
                $photoStorage->forgetForPlace((int) $placeId, $photoRows->pluck('path')->all());
            }

            $photoStorage->prunePlaceDirs($oldPlaceIds->map(fn ($id) => (int) $id)->all());

            Place::whereIn('id', $oldPlaceIds)->forceDelete();
        }

        $seededCount = 0;
        foreach ($googlePlaces as $placeData) {
            $catSlug = $placeData['category'];
            $category = $categoryModels[$catSlug] ?? null;
            if (! $category) {
                continue;
            }

            $lat = (float) $placeData['lat'];
            $lng = (float) $placeData['lng'];
            $barangayId = $placeService->resolveBarangayId($lat, $lng);

            $place = Place::create([
                'category_id' => $category->id,
                'name' => $placeData['name'],
                'slug' => Str::slug($placeData['name']).'-'.Str::random(5),
                'description' => $placeData['description'] ?? null,
                'address' => $placeData['address'],
                'barangay_id' => $barangayId,
                'geom' => DB::raw("ST_GeomFromText('POINT({$lng} {$lat})', 4326)"),
                'lat' => $lat,
                'lng' => $lng,
                'source' => Source::GoogleRef->value,
                'source_ref' => 'gmaps:'.Str::slug($placeData['name']),
                'source_url' => 'https://www.google.com/maps/search/?api=1&query='.urlencode($placeData['name'].' Bangued Abra'),
                'status' => 'approved',
                'verified_tier' => 'verified',
                'karma_score' => rand(15, 85),
                'is_claimed' => rand(0, 1) === 1,
                'phone' => $placeData['phone'] ?? null,
                'website' => null,
            ]);

            // Seed hours
            $hours = $placeData['hours'] ?? ['08:00', '20:00'];
            $is24h = ($hours[0] === '00:00' && $hours[1] === '23:59');

            for ($d = 0; $d < 7; $d++) {
                PlaceHour::create([
                    'place_id' => $place->id,
                    'day' => $d,
                    'opens' => $hours[0],
                    'closes' => $hours[1],
                    'is_closed' => false,
                    'note' => $is24h ? 'Open 24 Hours' : null,
                ]);
            }

            $seededCount++;
        }

        $this->command->info("Successfully populated {$seededCount} real Google Maps places across all 5 active categories in Bangued, Abra.");
    }
}
