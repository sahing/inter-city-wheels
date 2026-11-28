<?php

namespace App\Services;

use App\Models\ServiceLocation;

class RegionalLocationService
{
    /**
     * Common City Aliases dictionary for regional & national cities.
     */
    protected static array $aliases = [
        'berhampore' => ['Berhampore', 'Baharampur', 'Berhampur'],
        'baharampur' => ['Baharampur', 'Berhampore', 'Berhampur'],
        'berhampur' => ['Berhampore', 'Baharampur', 'Berhampur'],
        'digha' => ['Digha', 'New Digha', 'Old Digha', 'Digha Beach'],
        'digha beach' => ['Digha', 'New Digha', 'Old Digha'],
        'kolkata' => ['Kolkata', 'Calcutta'],
        'calcutta' => ['Kolkata', 'Calcutta'],
        'burdwan' => ['Bardhaman', 'Burdwan'],
        'bardhaman' => ['Bardhaman', 'Burdwan'],
        'bolpur' => ['Bolpur', 'Santiniketan', 'Bolpur (Santiniketan)'],
        'santiniketan' => ['Bolpur', 'Santiniketan', 'Bolpur (Santiniketan)'],
        'mandarmani' => ['Mandarmani', 'Mandarmani Beach'],
        'tajpur' => ['Tajpur', 'Tajpur Beach'],
        'shankarpur' => ['Shankarpur', 'Shankarpur Beach'],
        'contai' => ['Contai', 'Kanthi'],
        'kanthi' => ['Contai', 'Kanthi'],
        'midnapore' => ['Midnapore', 'Medinipur'],
        'medinipur' => ['Midnapore', 'Medinipur'],
        'malda' => ['Malda', 'English Bazar'],
        'english bazar' => ['Malda', 'English Bazar'],
        'bangalore' => ['Bengaluru', 'Bangalore'],
        'bengaluru' => ['Bengaluru', 'Bangalore'],
        'bombay' => ['Mumbai', 'Bombay'],
        'mumbai' => ['Mumbai', 'Bombay'],
        'madras' => ['Chennai', 'Madras'],
        'chennai' => ['Chennai', 'Madras'],
        'panaji' => ['Goa', 'Panjim', 'Panaji'],
        'panjim' => ['Goa', 'Panjim', 'Panaji'],
        'goa' => ['Goa', 'Panjim', 'Panaji'],
    ];

    /**
     * Curated catalog of regional destinations, tourist hubs & key transit cities.
     */
    protected static array $regionalCatalog = [
        // Purba & Paschim Medinipur Coastal & Industrial Corridor
        'digha' => ['name' => 'Digha', 'state' => 'West Bengal (Purba Medinipur)', 'lat' => 21.6266, 'lng' => 87.5074, 'category' => 'Beach Destination'],
        'new digha' => ['name' => 'New Digha', 'state' => 'West Bengal (Purba Medinipur)', 'lat' => 21.6200, 'lng' => 87.5000, 'category' => 'Beach Destination'],
        'old digha' => ['name' => 'Old Digha', 'state' => 'West Bengal (Purba Medinipur)', 'lat' => 21.6300, 'lng' => 87.5150, 'category' => 'Beach Destination'],
        'mandarmani' => ['name' => 'Mandarmani', 'state' => 'West Bengal (Purba Medinipur)', 'lat' => 21.6667, 'lng' => 87.7000, 'category' => 'Beach Resort'],
        'tajpur' => ['name' => 'Tajpur', 'state' => 'West Bengal (Purba Medinipur)', 'lat' => 21.6500, 'lng' => 87.6500, 'category' => 'Beach Destination'],
        'shankarpur' => ['name' => 'Shankarpur', 'state' => 'West Bengal (Purba Medinipur)', 'lat' => 21.6360, 'lng' => 87.5700, 'category' => 'Coastal Town'],
        'contai' => ['name' => 'Contai (Kanthi)', 'state' => 'West Bengal (Purba Medinipur)', 'lat' => 21.7780, 'lng' => 87.7510, 'category' => 'Subdivision Hub'],
        'haldia' => ['name' => 'Haldia', 'state' => 'West Bengal (Purba Medinipur)', 'lat' => 22.0667, 'lng' => 88.0667, 'category' => 'Industrial Port City'],
        'kharagpur' => ['name' => 'Kharagpur', 'state' => 'West Bengal (Paschim Medinipur)', 'lat' => 22.3460, 'lng' => 87.2320, 'category' => 'Railway & Tech Hub'],
        'midnapore' => ['name' => 'Midnapore (Medinipur)', 'state' => 'West Bengal (Paschim Medinipur)', 'lat' => 22.4257, 'lng' => 87.3199, 'category' => 'District HQ'],
        'jhargram' => ['name' => 'Jhargram', 'state' => 'West Bengal (Junglemahal)', 'lat' => 22.4500, 'lng' => 86.9833, 'category' => 'Eco Tourism'],

        // Murshidabad District Hubs & Heritage
        'berhampore' => ['name' => 'Berhampore', 'state' => 'West Bengal (Murshidabad)', 'lat' => 24.0988, 'lng' => 88.2514, 'category' => 'District HQ'],
        'baharampur' => ['name' => 'Baharampur', 'state' => 'West Bengal (Murshidabad)', 'lat' => 24.0988, 'lng' => 88.2514, 'category' => 'District HQ'],
        'murshidabad' => ['name' => 'Murshidabad', 'state' => 'West Bengal (Heritage City)', 'lat' => 24.1750, 'lng' => 88.2800, 'category' => 'Nawabi Heritage'],
        'lalbagh' => ['name' => 'Lalbagh', 'state' => 'West Bengal (Murshidabad)', 'lat' => 24.1800, 'lng' => 88.2700, 'category' => 'Heritage Zone'],
        'jangipur' => ['name' => 'Jangipur', 'state' => 'West Bengal (Murshidabad)', 'lat' => 24.4686, 'lng' => 88.0700, 'category' => 'Commercial Hub'],
        'farakka' => ['name' => 'Farakka', 'state' => 'West Bengal (Murshidabad)', 'lat' => 24.8167, 'lng' => 87.9000, 'category' => 'Barrage & Power Hub'],
        'kandi' => ['name' => 'Kandi', 'state' => 'West Bengal (Murshidabad)', 'lat' => 23.9556, 'lng' => 88.0378, 'category' => 'Subdivision Town'],
        'jiaganj' => ['name' => 'Jiaganj', 'state' => 'West Bengal (Murshidabad)', 'lat' => 24.2370, 'lng' => 88.2660, 'category' => 'Trading Center'],
        'beldanga' => ['name' => 'Beldanga', 'state' => 'West Bengal (Murshidabad)', 'lat' => 23.9300, 'lng' => 88.2500, 'category' => 'Highway Town'],

        // Nadia & Hooghly
        'mayapur' => ['name' => 'Mayapur', 'state' => 'West Bengal (Nadia)', 'lat' => 23.4232, 'lng' => 88.3900, 'category' => 'Spiritual Hub / ISKCON'],
        'krishnanagar' => ['name' => 'Krishnanagar', 'state' => 'West Bengal (Nadia)', 'lat' => 23.4000, 'lng' => 88.5000, 'category' => 'District HQ'],
        'nabadwip' => ['name' => 'Nabadwip', 'state' => 'West Bengal (Nadia)', 'lat' => 23.4200, 'lng' => 88.3700, 'category' => 'Pilgrimage Town'],
        'ranaghat' => ['name' => 'Ranaghat', 'state' => 'West Bengal (Nadia)', 'lat' => 23.1800, 'lng' => 88.5800, 'category' => 'Junction Town'],
        'kalyani' => ['name' => 'Kalyani', 'state' => 'West Bengal (Nadia)', 'lat' => 22.9751, 'lng' => 88.4345, 'category' => 'Educational City'],
        'chandannagar' => ['name' => 'Chandannagar', 'state' => 'West Bengal (Hooghly)', 'lat' => 22.8671, 'lng' => 88.3674, 'category' => 'Heritage French Town'],
        'bandel' => ['name' => 'Bandel', 'state' => 'West Bengal (Hooghly)', 'lat' => 22.9200, 'lng' => 88.3800, 'category' => 'Historic Church & Hub'],
        'serampore' => ['name' => 'Serampore', 'state' => 'West Bengal (Hooghly)', 'lat' => 22.7500, 'lng' => 88.3400, 'category' => 'Danish Heritage City'],
        'tarakeswar' => ['name' => 'Tarakeswar', 'state' => 'West Bengal (Hooghly)', 'lat' => 22.8900, 'lng' => 88.0200, 'category' => 'Pilgrimage Temple'],

        // Kolkata Metro & 24 Parganas
        'kolkata' => ['name' => 'Kolkata', 'state' => 'West Bengal', 'lat' => 22.5726, 'lng' => 88.3639, 'category' => 'State Capital / Metro'],
        'howrah' => ['name' => 'Howrah', 'state' => 'West Bengal', 'lat' => 22.5958, 'lng' => 88.2636, 'category' => 'Twin City / Station'],
        'barasat' => ['name' => 'Barasat', 'state' => 'West Bengal (North 24 Parganas)', 'lat' => 22.7200, 'lng' => 88.4800, 'category' => 'District HQ'],
        'diamond harbour' => ['name' => 'Diamond Harbour', 'state' => 'West Bengal (South 24 Parganas)', 'lat' => 22.1900, 'lng' => 88.1900, 'category' => 'Riverfront Town'],
        'kakdwip' => ['name' => 'Kakdwip', 'state' => 'West Bengal (South 24 Parganas)', 'lat' => 21.8700, 'lng' => 88.1800, 'category' => 'Sagar Island Transit'],
        'bakkhali' => ['name' => 'Bakkhali', 'state' => 'West Bengal (South 24 Parganas)', 'lat' => 21.5630, 'lng' => 88.2580, 'category' => 'Seaside Resort'],
        'gangasagar' => ['name' => 'Gangasagar', 'state' => 'West Bengal (South 24 Parganas)', 'lat' => 21.6500, 'lng' => 88.0500, 'category' => 'Holy Confluence'],

        // Bardhaman, Bankura, Purulia & Birbhum
        'bardhaman' => ['name' => 'Bardhaman (Burdwan)', 'state' => 'West Bengal (Purba Bardhaman)', 'lat' => 23.2324, 'lng' => 87.8615, 'category' => 'Historic Royal City'],
        'durgapur' => ['name' => 'Durgapur', 'state' => 'West Bengal (Paschim Bardhaman)', 'lat' => 23.5204, 'lng' => 87.3119, 'category' => 'Steel City'],
        'asansol' => ['name' => 'Asansol', 'state' => 'West Bengal (Paschim Bardhaman)', 'lat' => 23.6889, 'lng' => 86.9661, 'category' => 'Industrial Center'],
        'bolpur' => ['name' => 'Bolpur (Santiniketan)', 'state' => 'West Bengal (Birbhum)', 'lat' => 23.6700, 'lng' => 87.7200, 'category' => 'Tagore Heritage'],
        'tarapith' => ['name' => 'Tarapith', 'state' => 'West Bengal (Birbhum)', 'lat' => 24.1167, 'lng' => 87.8000, 'category' => 'Maa Tara Temple Town'],
        'rampurhat' => ['name' => 'Rampurhat', 'state' => 'West Bengal (Birbhum)', 'lat' => 24.1667, 'lng' => 87.7833, 'category' => 'Junction Town'],
        'suri' => ['name' => 'Suri', 'state' => 'West Bengal (Birbhum)', 'lat' => 23.9100, 'lng' => 87.5300, 'category' => 'District HQ'],
        'bankura' => ['name' => 'Bankura', 'state' => 'West Bengal', 'lat' => 23.2324, 'lng' => 87.0715, 'category' => 'Terracotta Land'],
        'bishnupur' => ['name' => 'Bishnupur', 'state' => 'West Bengal (Bankura)', 'lat' => 23.0673, 'lng' => 87.3164, 'category' => 'Heritage Temples'],
        'purulia' => ['name' => 'Purulia', 'state' => 'West Bengal', 'lat' => 23.3321, 'lng' => 86.3652, 'category' => 'District HQ / Chhau Land'],
        'ayodhya hills' => ['name' => 'Ayodhya Hills', 'state' => 'West Bengal (Purulia)', 'lat' => 23.2000, 'lng' => 86.1000, 'category' => 'Hill Resort'],

        // North Bengal & Hills
        'malda' => ['name' => 'Malda (English Bazar)', 'state' => 'West Bengal', 'lat' => 25.0108, 'lng' => 88.1411, 'category' => 'Gateway to North Bengal'],
        'raiganj' => ['name' => 'Raiganj', 'state' => 'West Bengal (Uttar Dinajpur)', 'lat' => 25.6200, 'lng' => 88.1200, 'category' => 'Bird Sanctuary Town'],
        'balurghat' => ['name' => 'Balurghat', 'state' => 'West Bengal (Dakshin Dinajpur)', 'lat' => 25.2200, 'lng' => 88.7600, 'category' => 'District HQ'],
        'siliguri' => ['name' => 'Siliguri', 'state' => 'West Bengal', 'lat' => 26.7271, 'lng' => 88.3953, 'category' => 'North Bengal Transit Hub'],
        'jalpaiguri' => ['name' => 'Jalpaiguri', 'state' => 'West Bengal', 'lat' => 26.5400, 'lng' => 88.7300, 'category' => 'Dooars Gateway'],
        'cooch behar' => ['name' => 'Cooch Behar', 'state' => 'West Bengal', 'lat' => 26.3239, 'lng' => 89.4510, 'category' => 'Royal Palace City'],
        'alipurduar' => ['name' => 'Alipurduar', 'state' => 'West Bengal (Dooars)', 'lat' => 26.4919, 'lng' => 89.5271, 'category' => 'National Park Hub'],
        'darjeeling' => ['name' => 'Darjeeling', 'state' => 'West Bengal (Queen of Hills)', 'lat' => 27.0410, 'lng' => 88.2663, 'category' => 'Himalayan Hill Station'],
        'kurseong' => ['name' => 'Kurseong', 'state' => 'West Bengal', 'lat' => 26.8800, 'lng' => 88.2800, 'category' => 'Land of White Orchids'],
        'kalimpong' => ['name' => 'Kalimpong', 'state' => 'West Bengal', 'lat' => 27.0667, 'lng' => 88.4667, 'category' => 'Hill Station'],
        'mirik' => ['name' => 'Mirik', 'state' => 'West Bengal', 'lat' => 26.8900, 'lng' => 88.1800, 'category' => 'Lake Destination'],

        // Neighboring Regional Corridors (Odisha, Jharkhand, Bihar, Sikkim)
        'puri' => ['name' => 'Puri', 'state' => 'Odisha', 'lat' => 19.8135, 'lng' => 85.8312, 'category' => 'Pilgrimage & Sea Beach'],
        'bhubaneswar' => ['name' => 'Bhubaneswar', 'state' => 'Odisha', 'lat' => 20.2961, 'lng' => 85.8245, 'category' => 'State Capital / Temple City'],
        'cuttack' => ['name' => 'Cuttack', 'state' => 'Odisha', 'lat' => 20.4625, 'lng' => 85.8830, 'category' => 'Millennium City'],
        'balasore' => ['name' => 'Balasore (Baleswar)', 'state' => 'Odisha', 'lat' => 21.4934, 'lng' => 86.9135, 'category' => 'Coastal Hub'],
        'jamshedpur' => ['name' => 'Jamshedpur (Tatanagar)', 'state' => 'Jharkhand', 'lat' => 22.8046, 'lng' => 86.2029, 'category' => 'Steel City'],
        'ranchi' => ['name' => 'Ranchi', 'state' => 'Jharkhand', 'lat' => 23.3441, 'lng' => 85.3096, 'category' => 'State Capital / Waterfalls'],
        'dhanbad' => ['name' => 'Dhanbad', 'state' => 'Jharkhand', 'lat' => 23.7957, 'lng' => 86.4304, 'category' => 'Coal Capital'],
        'deoghar' => ['name' => 'Deoghar (Baidyanath Dham)', 'state' => 'Jharkhand', 'lat' => 24.4826, 'lng' => 86.6976, 'category' => 'Pilgrimage Center'],
        'patna' => ['name' => 'Patna', 'state' => 'Bihar', 'lat' => 25.5941, 'lng' => 85.1376, 'category' => 'State Capital'],
        'gaya' => ['name' => 'Gaya (Bodh Gaya)', 'state' => 'Bihar', 'lat' => 24.7914, 'lng' => 85.0002, 'category' => 'World Heritage Hub'],
        'gangtok' => ['name' => 'Gangtok', 'state' => 'Sikkim', 'lat' => 27.3389, 'lng' => 88.6065, 'category' => 'State Capital / Himalayas'],

        // Major National Hubs
        'delhi' => ['name' => 'New Delhi', 'state' => 'Delhi NCR', 'lat' => 28.6139, 'lng' => 77.2090, 'category' => 'National Capital'],
        'mumbai' => ['name' => 'Mumbai', 'state' => 'Maharashtra', 'lat' => 19.0760, 'lng' => 72.8777, 'category' => 'Financial Capital'],
        'pune' => ['name' => 'Pune', 'state' => 'Maharashtra', 'lat' => 18.5204, 'lng' => 73.8567, 'category' => 'IT & Cultural Hub'],
        'goa' => ['name' => 'Goa (Panjim)', 'state' => 'Goa', 'lat' => 15.4909, 'lng' => 73.8278, 'category' => 'Holiday Destination'],
        'bengaluru' => ['name' => 'Bengaluru', 'state' => 'Karnataka', 'lat' => 12.9716, 'lng' => 77.5946, 'category' => 'Silicon Valley of India'],
        'hyderabad' => ['name' => 'Hyderabad', 'state' => 'Telangana', 'lat' => 17.3850, 'lng' => 78.4867, 'category' => 'Tech & Pearl City'],
        'chennai' => ['name' => 'Chennai', 'state' => 'Tamil Nadu', 'lat' => 13.0827, 'lng' => 80.2707, 'category' => 'Automotive & Cultural Hub'],
        'jaipur' => ['name' => 'Jaipur', 'state' => 'Rajasthan', 'lat' => 26.9124, 'lng' => 75.7873, 'category' => 'Pink City'],
    ];

    /**
     * Normalize query or city name into all possible matching variants/aliases.
     */
    public static function normalizeAliases(string $cityName): array
    {
        $clean = trim(explode(',', $cityName)[0]);
        $lower = strtolower($clean);

        $list = [$clean];
        if (isset(self::$aliases[$lower])) {
            foreach (self::$aliases[$lower] as $alt) {
                $list[] = $alt;
            }
        }

        // Fuzzy matches (e.g. if string contains berhampore or baharampur)
        foreach (self::$aliases as $key => $alts) {
            if (str_contains($lower, $key)) {
                $list = array_merge($list, $alts);
            }
        }

        return array_values(array_unique($list));
    }

    /**
     * Search both registered database locations and comprehensive regional locations.
     */
    public static function search(string $query): array
    {
        $q = trim($query);
        $cleanQ = trim(explode(',', $q)[0]);
        $lowerQ = strtolower($cleanQ);

        $results = [];
        $seenKeys = [];

        // 1. Fetch active registered ServiceLocations from Database
        $registeredQuery = ServiceLocation::where('is_active', true);
        if ($q !== '') {
            $aliases = self::normalizeAliases($cleanQ);
            $registeredQuery->where(function ($sq) use ($cleanQ, $aliases) {
                $sq->where('name', 'like', "%{$cleanQ}%")
                    ->orWhere('state', 'like', "%{$cleanQ}%");
                foreach ($aliases as $alt) {
                    $sq->orWhere('name', 'like', "%{$alt}%");
                }
            });
        }
        $registeredLocs = $registeredQuery->orderBy('name')->get();

        foreach ($registeredLocs as $loc) {
            $key = strtolower(trim($loc->name));
            $seenKeys[$key] = true;
            $seenKeys[preg_replace('/[^a-z0-9]/', '', $key)] = true;

            $results[] = [
                'name' => $loc->name,
                'state' => $loc->state,
                'latitude' => (float) $loc->latitude,
                'longitude' => (float) $loc->longitude,
                'airport_code' => $loc->airport_code,
                'is_registered' => true,
                'badge' => 'Registered Hub',
                'type' => 'registered',
            ];
        }

        // 2. Search Curated Regional Catalog
        foreach (self::$regionalCatalog as $catKey => $item) {
            $itemNameLower = strtolower($item['name']);
            $itemStateLower = strtolower($item['state']);

            // Deduplicate if already in registered results
            $lookupKey = preg_replace('/[^a-z0-9]/', '', $itemNameLower);
            if (isset($seenKeys[$lookupKey])) {
                continue;
            }

            // Check if matches query
            $matches = false;
            if ($q === '') {
                // If query is empty, show top popular destinations
                $matches = in_array($catKey, ['digha', 'mandarmani', 'berhampore', 'siliguri', 'darjeeling', 'bolpur', 'durgapur', 'puri', 'asansol']);
            } else {
                if (str_contains($itemNameLower, $lowerQ) || str_contains($itemStateLower, $lowerQ) || str_contains($catKey, $lowerQ)) {
                    $matches = true;
                } else {
                    $aliases = self::normalizeAliases($cleanQ);
                    foreach ($aliases as $alt) {
                        if (str_contains($itemNameLower, strtolower($alt))) {
                            $matches = true;
                            break;
                        }
                    }
                }
            }

            if ($matches) {
                $seenKeys[$lookupKey] = true;
                $results[] = [
                    'name' => $item['name'],
                    'state' => $item['state'],
                    'latitude' => (float) $item['lat'],
                    'longitude' => (float) $item['lng'],
                    'airport_code' => null,
                    'is_registered' => false,
                    'badge' => 'Regional Route',
                    'type' => 'regional',
                    'category' => $item['category'] ?? null,
                ];
            }
        }

        // 3. Durable GeoPlaceCache (previously discovered OpenStreetMap cities)
        if (strlen($cleanQ) >= 2) {
            $cachedPlaces = app(GeoCacheService::class)->searchPlaces($cleanQ, 5);
            foreach ($cachedPlaces as $cp) {
                $lookupKey = preg_replace('/[^a-z0-9]/', '', strtolower($cp['name']));
                if (! isset($seenKeys[$lookupKey])) {
                    $seenKeys[$lookupKey] = true;
                    $results[] = [
                        'name' => $cp['name'],
                        'state' => $cp['state'] ?? 'India',
                        'latitude' => (float) $cp['lat'],
                        'longitude' => (float) $cp['lng'],
                        'airport_code' => null,
                        'is_registered' => false,
                        'badge' => 'OpenStreetMap',
                        'type' => 'geocoded',
                        'cached' => true,
                        'beyond_region' => (bool) ($cp['is_beyond_region'] ?? ! self::isWithinServiceRegion((float) $cp['lat'], (float) $cp['lng'], $cp['name'])),
                        'display_name' => $cp['display_name'] ?? null,
                    ];
                }
            }

            // Check if there is already an exact/prefix match before calling live Nominatim
            $hasExact = false;
            foreach ($results as $r) {
                if (strtolower($r['name']) === $lowerQ || str_starts_with(strtolower($r['name']), $lowerQ)) {
                    $hasExact = true;
                    break;
                }
            }

            // Also attempt fast Nominatim lookup if not yet in registered, regional, or city list cache
            if (! $hasExact && count($results) < 3) {
                $geoResults = self::geocodeNominatim($cleanQ);
                foreach ($geoResults as $geo) {
                    $lookupKey = preg_replace('/[^a-z0-9]/', '', strtolower($geo['name']));
                    if (! isset($seenKeys[$lookupKey])) {
                        $seenKeys[$lookupKey] = true;
                        $results[] = $geo;
                        if (strtolower($geo['name']) === $lowerQ || str_starts_with(strtolower($geo['name']), $lowerQ)) {
                            $hasExact = true;
                        }
                    }
                }
            }

            // Always offer the typed text as a location, but only with a real
            // position. Pinning an unknown name to the regional centroid used to
            // invent a distance — and therefore a fare — so an unresolvable name
            // is returned as unresolved and left to the caller.
            if (! $hasExact) {
                $resolvedCoords = self::resolveCoordinatesStrict($cleanQ);

                $custom = [
                    'name' => ucwords($cleanQ),
                    'airport_code' => null,
                    'is_registered' => false,
                    'is_custom' => true,
                    'badge' => 'Desired Location',
                    'type' => 'custom',
                ];

                if ($resolvedCoords !== null) {
                    $custom['state'] = $resolvedCoords['state'] ?? 'West Bengal / Desired Regional Area';
                    $custom['latitude'] = (float) $resolvedCoords['lat'];
                    $custom['longitude'] = (float) $resolvedCoords['lng'];
                } else {
                    $custom['state'] = 'Unresolved — type a nearby town';
                    $custom['latitude'] = null;
                    $custom['longitude'] = null;
                    $custom['needs_geocode'] = true;
                }

                $results[] = $custom;
            }
        }

        return $results;
    }

    /**
     * Strict resolution used for pricing.
     *
     * Returns null when the name cannot be grounded in the registered
     * locations table, the curated regional catalogue, or a known regional
     * landmark. It deliberately never invents a position: a fabricated
     * coordinate produces a fabricated distance and therefore a fabricated
     * fare.
     */
    public static function resolveCoordinatesStrict(string $cityName): ?array
    {
        $clean = trim(explode(',', $cityName)[0]);
        $lower = strtolower($clean);
        $slug = preg_replace('/[^a-z0-9]/', '', $lower);

        if ($clean === '') {
            return null;
        }

        // 1. Direct DB check with aliases
        $aliases = self::normalizeAliases($clean);
        $loc = ServiceLocation::where(function ($q) use ($clean, $aliases) {
            $q->whereRaw('LOWER(name) = ?', [strtolower($clean)]);
            foreach ($aliases as $alt) {
                $q->orWhereRaw('LOWER(name) = ?', [strtolower($alt)]);
            }
        })->first();

        if ($loc) {
            return [
                'name' => $loc->name,
                'state' => $loc->state,
                'lat' => (float) $loc->latitude,
                'lng' => (float) $loc->longitude,
            ];
        }

        // 2. Check Regional Catalog
        if (isset(self::$regionalCatalog[$lower])) {
            $item = self::$regionalCatalog[$lower];

            return [
                'name' => $item['name'],
                'state' => $item['state'],
                'lat' => (float) $item['lat'],
                'lng' => (float) $item['lng'],
            ];
        }

        foreach (self::$regionalCatalog as $k => $item) {
            if ($k === $slug || str_starts_with(strtolower($item['name']), $lower) || str_starts_with($lower, $k)) {
                return [
                    'name' => $item['name'],
                    'state' => $item['state'],
                    'lat' => (float) $item['lat'],
                    'lng' => (float) $item['lng'],
                ];
            }
        }

        // 3. Known regional landmarks (keyword match only, otherwise null)
        $guessed = self::guessRegionalCoordinates($clean);
        if ($guessed !== null) {
            return $guessed;
        }

        // 4. Durable GeoPlaceCache (previously discovered OpenStreetMap cities)
        $cached = app(GeoCacheService::class)->findByName($clean);
        if ($cached !== null) {
            return [
                'name' => $cached['name'],
                'state' => $cached['state'] ?? 'India',
                'lat' => (float) $cached['lat'],
                'lng' => (float) $cached['lng'],
            ];
        }

        return null;
    }

    /**
     * Coordinates for a known regional landmark, or null when the name is not
     * one of them.
     */
    protected static function guessRegionalCoordinates(string $name): ?array
    {
        $lower = strtolower($name);
        if (str_contains($lower, 'digha')) {
            return ['name' => $name, 'state' => 'West Bengal (Purba Medinipur)', 'lat' => 21.6266, 'lng' => 87.5074];
        }
        if (str_contains($lower, 'berhampore') || str_contains($lower, 'baharampur') || str_contains($lower, 'murshidabad')) {
            return ['name' => $name, 'state' => 'West Bengal (Murshidabad)', 'lat' => 24.0988, 'lng' => 88.2514];
        }
        if (str_contains($lower, 'kolkata') || str_contains($lower, 'calcutta')) {
            return ['name' => $name, 'state' => 'West Bengal', 'lat' => 22.5726, 'lng' => 88.3639];
        }
        if (str_contains($lower, 'puri')) {
            return ['name' => $name, 'state' => 'Odisha', 'lat' => 19.8135, 'lng' => 85.8312];
        }
        if (str_contains($lower, 'darjeeling') || str_contains($lower, 'siliguri')) {
            return ['name' => $name, 'state' => 'West Bengal', 'lat' => 26.7271, 'lng' => 88.3953];
        }

        // Unknown to the dataset, the aliases and the landmark list: no
        // position. Callers must decide what to do rather than inventing one.
        return null;
    }

    /**
     * OpenStreetMap (Nominatim) place lookup, via OsmGeoService so that the
     * timeout, identifying User-Agent, one-request-per-second throttle and
     * caching all live in one place.
     *
     * Returns no results when OSM is disabled or unreachable: the curated
     * catalogue and the registered locations remain the offline answer.
     */
    protected static function geocodeNominatim(string $query): array
    {
        $service = app(OsmGeoService::class);

        if (! $service->enabled()) {
            return [];
        }

        $items = [];

        foreach ($service->search($query, 3) as $place) {
            $lat = (float) $place['latitude'];
            $lng = (float) $place['longitude'];

            $items[] = [
                'name' => $place['name'],
                'state' => $place['state'] ?? 'India',
                'latitude' => $lat,
                'longitude' => $lng,
                'airport_code' => null,
                'is_registered' => false,
                'badge' => 'OpenStreetMap',
                'type' => 'geocoded',
                'cached' => (bool) ($place['cached'] ?? false),
                'beyond_region' => ! self::isWithinServiceRegion($lat, $lng, (string) $place['name']),
                'display_name' => $place['display_name'] ?? null,
            ];
        }

        return $items;
    }

    /**
     * Compute Haversine distance, road distance multiplier, drive time, and toll.
     */
    public static function computeRouteMetrics(float $lat1, float $lng1, float $lat2, float $lng2): array
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $straightKm = 6371 * $c;

        // Realistic road curvature multiplier in India (1.20 - 1.25x)
        $roadKm = max(25, (int) round($straightKm * 1.22));

        // Average outstation speed 45 km/h with highway transitions
        $totalMins = max(25, (int) round(($roadKm / 45) * 60));
        $hours = (int) floor($totalMins / 60);
        $mins = $totalMins % 60;
        $timeStr = $hours > 0 ? ($mins > 0 ? "{$hours}h {$mins}m" : "{$hours}h") : "{$mins}m";

        $baseToll = (float) round($roadKm * 0.92);

        return [
            'straight_km' => round($straightKm, 1),
            'distance_km' => $roadKm,
            'estimated_time' => $timeStr,
            'base_toll' => $baseToll,
        ];
    }

    /**
     * Pick the nearest coordinate candidate within $maxKm using Haversine distance.
     *
     * @param  array<int, array{name: string, state: ?string, lat: float, lng: float}>  $candidates
     * @return array{name: string, state: ?string, lat: float, lng: float, distance_km: float}|null
     */
    private static function closestCandidate(float $lat, float $lng, array $candidates, float $maxKm): ?array
    {
        $best = null;
        $bestKm = $maxKm;

        foreach ($candidates as $candidate) {
            $dLat = deg2rad($candidate['lat'] - $lat);
            $dLng = deg2rad($candidate['lng'] - $lng);
            $a = sin($dLat / 2) * sin($dLat / 2)
                + cos(deg2rad($lat)) * cos(deg2rad($candidate['lat']))
                * sin($dLng / 2) * sin($dLng / 2);
            $km = 6371.0 * 2.0 * atan2(sqrt($a), sqrt(1 - $a));

            if ($km <= $bestKm) {
                $bestKm = $km;
                $best = $candidate + ['distance_km' => round($km, 1)];
            }
        }

        return $best;
    }

    /**
     * Find the closest active ServiceLocation or curated regional city within $maxKm.
     *
     * @return array{name: string, state: ?string, lat: float, lng: float, distance_km: float}|null
     */
    public static function nearestTo(float $lat, float $lng, float $maxKm = 250.0): ?array
    {
        $candidates = [];

        foreach (ServiceLocation::where('is_active', true)->get() as $loc) {
            if ($loc->latitude !== null && $loc->longitude !== null) {
                $candidates[] = [
                    'name' => $loc->name,
                    'state' => $loc->state,
                    'lat' => (float) $loc->latitude,
                    'lng' => (float) $loc->longitude,
                ];
            }
        }

        foreach (self::$regionalCatalog as $item) {
            $candidates[] = [
                'name' => $item['name'],
                'state' => $item['state'],
                'lat' => (float) $item['lat'],
                'lng' => (float) $item['lng'],
            ];
        }

        return self::closestCandidate($lat, $lng, $candidates, $maxKm);
    }

    /**
     * Find the closest active ServiceLocation hub within $maxKm.
     *
     * Falls back to the curated regional catalogue only when no active
     * ServiceLocation records exist in the database.
     *
     * @return array{name: string, state: ?string, lat: float, lng: float, distance_km: float}|null
     */
    public static function nearestActiveHub(float $lat, float $lng, float $maxKm = 150.0): ?array
    {
        $activeHubs = ServiceLocation::where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $candidates = [];
        foreach ($activeHubs as $loc) {
            $candidates[] = [
                'name' => $loc->name,
                'state' => $loc->state,
                'lat' => (float) $loc->latitude,
                'lng' => (float) $loc->longitude,
            ];
        }

        if ($candidates === []) {
            foreach (self::$regionalCatalog as $item) {
                $candidates[] = [
                    'name' => $item['name'],
                    'state' => $item['state'],
                    'lat' => (float) $item['lat'],
                    'lng' => (float) $item['lng'],
                ];
            }
        }

        return self::closestCandidate($lat, $lng, $candidates, $maxKm);
    }

    /**
     * Determine whether an endpoint (by city name and/or coordinates) falls within
     * the operational service region around active hubs.
     */
    public static function isWithinServiceRegion(
        ?float $lat,
        ?float $lng,
        ?string $cityName = null,
        float $maxKm = 150.0
    ): bool {
        if ($lat !== null && $lng !== null) {
            return self::nearestActiveHub($lat, $lng, $maxKm) !== null;
        }

        if ($cityName !== null && trim($cityName) !== '') {
            $resolved = self::resolveCoordinatesStrict($cityName);
            if ($resolved !== null) {
                return self::nearestActiveHub((float) $resolved['lat'], (float) $resolved['lng'], $maxKm) !== null;
            }
        }

        return false;
    }
}
