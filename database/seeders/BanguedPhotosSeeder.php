<?php

namespace Database\Seeders;

use App\Enums\PhotoStatus;
use App\Enums\PlaceStatus;
use App\Enums\Source;
use App\Enums\VerificationTier;
use App\Models\Category;
use App\Models\Place;
use App\Models\PlaceHour;
use App\Models\PlacePhoto;
use App\Models\User;
use App\Services\AtlasCache;
use App\Services\BarangayResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BanguedPhotosSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first() ?? User::factory()->create(['role' => 'admin']);
        $resolver = app(BarangayResolver::class);
        $cache = app(AtlasCache::class);

        (new CategorySeeder)->run();

        $categories = [
            'coffee_shop' => Category::where('slug', 'coffee_shop')->firstOrFail(),
            'fast_food' => Category::where('slug', 'fast_food')->firstOrFail(),
            'grocery' => Category::where('slug', 'grocery')->firstOrFail(),
            'hospital' => Category::where('slug', 'hospital')->firstOrFail(),
            'pharmacy' => Category::where('slug', 'pharmacy')->firstOrFail(),
        ];

        $folderSpecs = [
            'coffee-cafe-merci' => [
                'cat' => 'coffee_shop',
                'name' => 'Cafe Merci',
                'desc' => 'Scenic coffee shop serving iced espresso, specialty brewed coffee, and artisan pastries.',
                'address' => 'Patucannay Road, Zone 4 Poblacion, Bangued, Abra',
                'lat' => 17.598100,
                'lng' => 120.617700,
                'hours' => ['08:00', '21:00'],
            ],
            'coffee-daily-dose' => [
                'cat' => 'coffee_shop',
                'name' => 'Daily Dose Coffee Shop',
                'desc' => 'Popular local specialty coffee shop offering cold brews, matcha lattes, and comfort food.',
                'address' => 'Capitulacion Street, Zone 4 Poblacion, Bangued, Abra',
                'lat' => 17.599000,
                'lng' => 120.617000,
                'hours' => ['08:00', '22:00'],
            ],
            'coffee-depresso' => [
                'cat' => 'coffee_shop',
                'name' => 'Depresso Cafe',
                'desc' => 'Cozy coffee nook and cafe serving espresso, frappes, and dessert specialties.',
                'address' => 'McKinley Street, Zone 4 Poblacion, Bangued, Abra',
                'lat' => 17.598200,
                'lng' => 120.618000,
                'hours' => ['09:00', '21:00'],
            ],
            'coffee-igop' => [
                'cat' => 'coffee_shop',
                'name' => 'Igop Cafe',
                'desc' => 'Relaxing coffee house featuring signature coffee drinks, snacks, and sweet treats.',
                'address' => 'Partelo Street, Zone 2 Poblacion, Bangued, Abra',
                'lat' => 17.598700,
                'lng' => 120.619500,
                'hours' => ['08:00', '21:00'],
            ],
            'coffee-kapepiliin' => [
                'cat' => 'coffee_shop',
                'name' => 'Kapepiliin',
                'desc' => 'Handcrafted local Abra coffee blends, iced drinks, and breakfast favorites.',
                'address' => 'Casamata Hill Road, Zone 4 Poblacion, Bangued, Abra',
                'lat' => 17.597500,
                'lng' => 120.616200,
                'hours' => ['07:00', '20:00'],
            ],
            'coffee-ladrillo' => [
                'cat' => 'coffee_shop',
                'name' => 'Ladrillo Cafe',
                'desc' => 'Rustic cafe ambiance with rich espresso roasts, teas, and light meals.',
                'address' => 'Taft Avenue, Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.596000,
                'lng' => 120.618300,
                'hours' => ['08:00', '20:00'],
            ],
            'fastfood-chowking' => [
                'cat' => 'fast_food',
                'match_name' => 'Chowking Bangued',
            ],
            'fastfood-jollibee-zone5' => [
                'cat' => 'fast_food',
                'match_name' => 'Jollibee Bangued',
            ],
            'fastfood-jollibee-zone7' => [
                'cat' => 'fast_food',
                'name' => 'Jollibee - Zone 7 Branch',
                'desc' => 'Fast food chain branch serving Chickenjoy, Jolly Spaghetti, and Yumburgers in northern Bangued.',
                'address' => 'Capitulacion Street, Zone 7 Poblacion, Bangued, Abra',
                'lat' => 17.604100,
                'lng' => 120.614800,
                'hours' => ['06:00', '22:00'],
            ],
            'fastfood-mang-inasal' => [
                'cat' => 'fast_food',
                'match_name' => 'Mang Inasal Bangued',
            ],
            'fastfood-maxs' => [
                'cat' => 'fast_food',
                'name' => "Max's Restaurant Bangued",
                'desc' => 'Classic Filipino restaurant and fast meals famous for "The House That Fried Chicken Built".',
                'address' => 'Partelo Street, Zone 2 Poblacion, Bangued, Abra',
                'lat' => 17.599200,
                'lng' => 120.619000,
                'hours' => ['09:00', '21:00'],
            ],
            'fastfood-shakeys' => [
                'cat' => 'fast_food',
                'name' => "Shakey's Pizza Parlor Bangued",
                'desc' => 'Family casual dining and fast food chain famous for thin-crust pizzas and Mojo potatoes.',
                'address' => 'McKinley Street, Zone 4 Poblacion, Bangued, Abra',
                'lat' => 17.598000,
                'lng' => 120.618800,
                'hours' => ['09:00', '21:00'],
            ],
            'grocery-aquino' => [
                'cat' => 'grocery',
                'name' => 'Aquino General Merchandise',
                'desc' => 'Wholesale and retail general grocery goods, consumer staples, and household items.',
                'address' => 'Zone 4 Poblacion, Bangued, Abra',
                'lat' => 17.598600,
                'lng' => 120.616800,
                'hours' => ['07:30', '18:30'],
            ],
            'grocery-jtc' => [
                'cat' => 'grocery',
                'match_name' => 'JTC Grocery',
            ],
            'grocery-miguel-chike' => [
                'cat' => 'grocery',
                'name' => 'Miguel Chike Gen. Mdse.',
                'desc' => 'General store and grocery outlet offering dry goods, canned items, and daily essentials.',
                'address' => 'Zone 4 Poblacion, Bangued, Abra',
                'lat' => 17.597900,
                'lng' => 120.618400,
                'hours' => ['07:00', '18:30'],
            ],
            'grocery-nct' => [
                'cat' => 'grocery',
                'name' => 'NCT Department Store & Supermarket',
                'desc' => 'One-stop department store and supermarket with fresh food, groceries, and merchandise.',
                'address' => 'Partelo Street, Zone 2 Poblacion, Bangued, Abra',
                'lat' => 17.600200,
                'lng' => 120.617400,
                'hours' => ['08:00', '19:00'],
            ],
            'hospital-aph' => [
                'cat' => 'hospital',
                'match_name' => 'Abra Provincial Hospital',
            ],
            'hospital-assumpta' => [
                'cat' => 'hospital',
                'match_name' => 'Assumpta Family Hospital',
            ],
            'hospital-christian' => [
                'cat' => 'hospital',
                'match_name' => 'Bangued Christian Hospital',
            ],
            'hospital-hmc' => [
                'cat' => 'hospital',
                'name' => "HMC - Medical Specialist's Clinic",
                'desc' => 'Medical specialist clinic and outpatient facility providing specialized consultations and diagnostic services.',
                'address' => 'Zone 4 Poblacion, Bangued, Abra',
                'lat' => 17.597000,
                'lng' => 120.616900,
                'hours' => ['08:00', '17:00'],
            ],
            'hospital-seares' => [
                'cat' => 'hospital',
                'match_name' => 'Dr. Petronilo V. Seares, Sr. Memorial Hospital',
            ],
            'hospital-st-james-valera' => [
                'cat' => 'hospital',
                'name' => 'St. James Hospital / Valera Medical Hospital',
                'desc' => 'General hospital and medical center offering 24/7 emergency care, inpatient care, and diagnostics.',
                'address' => 'Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.597200,
                'lng' => 120.619200,
                'hours' => ['00:00', '23:59'],
            ],
            'pharmacy-mercury-zone5' => [
                'cat' => 'pharmacy',
                'match_name' => 'Mercury Drug - Taft Branch',
            ],
            'pharmacy-southstar' => [
                'cat' => 'pharmacy',
                'match_name' => 'Southstar Drug - Bangued Capitulacion',
            ],
            'pharmacy-st-joseph-mckinley' => [
                'cat' => 'pharmacy',
                'name' => 'St. Joseph Drug - McKinley Branch',
                'desc' => 'Drugstore chain branch providing prescription medicines, OTC drugs, and healthcare products.',
                'address' => 'McKinley Street, Zone 4 Poblacion, Bangued, Abra',
                'lat' => 17.599400,
                'lng' => 120.616500,
                'hours' => ['07:30', '19:30'],
            ],
            'pharmacy-st-joseph-zone5' => [
                'cat' => 'pharmacy',
                'name' => 'St. Joseph Drug - Zone 5 Branch',
                'desc' => 'Convenient neighborhood branch of St. Joseph Drug offering medicines and wellness supplies.',
                'address' => 'Zone 5 Poblacion, Bangued, Abra',
                'lat' => 17.597600,
                'lng' => 120.617300,
                'hours' => ['07:30', '19:30'],
            ],
            'pharmacy-tgp' => [
                'cat' => 'pharmacy',
                'match_name' => 'The Generics Pharmacy (TGP) - McKinley Branch',
            ],
        ];

        $sourceBase = base_path('bangued');

        // Resolve through the configured public disk. On InfinityFree that disk
        // is rooted at public/storage rather than storage/app/public, so the
        // old hardcoded path silently wrote photos where the app never looks.
        $publicDisk = Storage::disk('public');

        if (! $publicDisk->exists('photos')) {
            $publicDisk->makeDirectory('photos');
        }

        $insertedPlacesCount = 0;
        $matchedPlacesCount = 0;
        $insertedPhotosCount = 0;

        foreach ($folderSpecs as $folder => $spec) {
            $catSlug = $spec['cat'];
            $category = $categories[$catSlug];
            $folderDir = $sourceBase.DIRECTORY_SEPARATOR.$catSlug.DIRECTORY_SEPARATOR.$folder;

            if (! File::isDirectory($folderDir)) {
                $this->command?->warn("Folder not found: {$folderDir}");

                continue;
            }

            // Find or create place
            if (isset($spec['match_name'])) {
                $place = Place::where('name', $spec['match_name'])->first();
                if (! $place) {
                    $this->command?->warn("Could not find matching place for: {$spec['match_name']}");

                    continue;
                }
                $matchedPlacesCount++;
            } else {
                $name = $spec['name'];
                $place = Place::where('name', $name)->first();

                if (! $place) {
                    $lat = (float) $spec['lat'];
                    $lng = (float) $spec['lng'];
                    $barangayId = $resolver->resolveId($lat, $lng);

                    $baseSlug = Str::slug($name);
                    $slug = $baseSlug;
                    while (Place::withTrashed()->where('slug', $slug)->exists()) {
                        $slug = $baseSlug.'-'.Str::random(5);
                    }

                    $place = Place::create([
                        'category_id' => $category->id,
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $spec['desc'],
                        'address' => $spec['address'],
                        'barangay_id' => $barangayId,
                        'geom' => DB::raw("ST_GeomFromText('POINT({$lng} {$lat})', 4326)"),
                        'lat' => $lat,
                        'lng' => $lng,
                        'source' => Source::GoogleRef->value,
                        'source_ref' => 'gmaps:'.Str::slug($name),
                        'source_url' => 'https://www.google.com/maps/search/?api=1&query='.urlencode($name.' Bangued Abra'),
                        'status' => PlaceStatus::Approved->value,
                        'verified_tier' => VerificationTier::Verified->value,
                        'submitted_by' => $admin->id,
                        'approved_by' => $admin->id,
                        'approved_at' => now(),
                        'is_claimed' => false,
                        'phone' => null,
                        'website' => null,
                    ]);

                    $hours = $spec['hours'] ?? ['08:00', '20:00'];
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

                    $insertedPlacesCount++;
                } else {
                    $matchedPlacesCount++;
                }
            }

            // Copy photos and register PlacePhoto records
            $relativeDir = "photos/{$place->id}";

            if (! $publicDisk->exists($relativeDir)) {
                $publicDisk->makeDirectory($relativeDir);
            }

            $images = File::glob($folderDir.DIRECTORY_SEPARATOR.'*.jpg');
            sort($images);

            $existingPhotoCount = PlacePhoto::where('place_id', $place->id)->count();

            foreach ($images as $index => $imgFile) {
                $filename = basename($imgFile);
                $relativePath = $relativeDir.'/'.$filename;

                $publicDisk->put($relativePath, File::get($imgFile));

                $photoExists = PlacePhoto::where('place_id', $place->id)
                    ->where('path', $relativePath)
                    ->exists();

                if (! $photoExists) {
                    $isCover = ($existingPhotoCount === 0 && $index === 0);
                    PlacePhoto::create([
                        'place_id' => $place->id,
                        'path' => $relativePath,
                        'uploader_id' => $admin->id,
                        'is_cover' => $isCover,
                        'status' => PhotoStatus::Approved->value,
                    ]);
                    $insertedPhotosCount++;
                }
            }
        }

        $cache->invalidate();

        $this->command?->info('Bangued Photos Seeder complete!');
        $this->command?->info("Matched existing places: {$matchedPlacesCount}");
        $this->command?->info("New places created: {$insertedPlacesCount}");
        $this->command?->info("Photos attached: {$insertedPhotosCount}");
    }
}
