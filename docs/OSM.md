# OpenStreetMap: place search and driving distance

Two public OSM services are wired in through `App\Services\OsmGeoService`:

| Purpose | Service | Endpoint |
|---|---|---|
| Name → coordinates, coordinates → name | Nominatim | `https://nominatim.openstreetmap.org` |
| Driving distance / duration | OSRM | `https://router.project-osrm.org` |

No API key is needed. Both public instances are free but rate-limited, so every
call is cached and Nominatim traffic is throttled to one request per second.

## Where it is used

- `GET /api/locations/search?q=…` — `RegionalLocationService::search()` keeps the
  registered locations and the curated regional catalogue first, then asks
  Nominatim for anything still unmatched. Results carry real `latitude` /
  `longitude`.
- `GET /api/routes/calculate` and `POST /api/bookings` — both price through
  `FareCalculatorService::resolveTrip()`, so a quote and an invoice use the same
  distance. Resolution order per endpoint:

  1. coordinates supplied by the caller (`from_lat`, `from_lng`, `to_lat`, `to_lng`);
  2. the `route_fares` / `service_locations` tables and the curated catalogue;
  3. Nominatim geocoding of the typed name.

  Distance then comes from OSRM (`distance_source` ends in `+osrm_route`), and
  only if OSRM is unreachable does it fall back to the straight-line estimate
  (`RegionalLocationService::computeRouteMetrics()`).

`distance_source` is returned in the quote so it is always visible where a
number came from: `route_table`, `local_package`, `airport_hub`,
`coordinates(+osrm_route)`, `location_dataset(+osrm_route)` or
`osm_geocode+osrm_route`.

## Two cache layers

A customer query is answered in this order, and only the last step costs a map
API call:

| Layer | Store | Lifetime | Survives |
|---|---|---|---|
| 1. HTTP cache | Laravel cache (`OSM_ROUTE_CACHE_TTL`, `OSM_CACHE_TTL`) | 12 h / 7 d | nothing — cleared by `cache:clear`, a new worker |
| 2. Durable cache | `geo_route_cache`, `geo_place_cache` | `OSM_ROUTE_TTL_DAYS` / `OSM_PLACE_TTL_DAYS` | cache flushes, deploys, other workers |
| 3. Map API | Nominatim / OSRM | — | — |

The first query for a pair measures the route and **writes it to the database
before the quote is returned**. Every later query is one indexed read: the
key is a hash of the two endpoints rounded to ~1 m, so a pair typed slightly
differently still hits, and direction is ignored (`A→B` and `B→A` share a row).
Geocoded names are stored the same way in `geo_place_cache`.

Responses report `route.cached` so the caller can tell whether the number came
from a fresh measurement or from storage. Rows expire (defaults: 30 days for a
route, 90 days for a place) so a changed road or corrected place is re-measured;
expired rows are ignored on read and removed by `geo:prune-cache`.

Because the durable layer is not the API, stored answers still work with the API
switched off: `OSM_ENABLED=false` stops outgoing calls but keeps serving routes
and places already measured.

```bash
php artisan geo:cache-stats   # rows, expired rows and hit counts per store
php artisan geo:prune-cache   # delete expired rows (scheduled daily)
```

## Configuration

Defaults live in `config/services.php` (`osm` block); every key can be
overridden in `.env` — see `.env.example`:

```dotenv
OSM_ENABLED=true
OSM_NOMINATIM_URL=https://nominatim.openstreetmap.org
OSM_OSRM_URL=https://router.project-osrm.org
OSM_USER_AGENT="IntercityWheels/2.0 (+https://intercitywheels.com)"
OSM_COUNTRY_CODES=in
OSM_TIMEOUT=3
OSM_THROTTLE=true
OSM_CACHE_TTL=604800        # place search, 7 days
OSM_ROUTE_CACHE_TTL=43200   # route, 12 hours
OSM_DB_CACHE=true           # persist measured routes/places in the database
OSM_ROUTE_TTL_DAYS=30       # durable route lifetime
OSM_PLACE_TTL_DAYS=90       # durable place lifetime
```

Set a real contact address in `OSM_USER_AGENT`: the public Nominatim policy
requires an identifying agent and forbids heavy use. For production volume,
self-host Nominatim and OSRM (or use a provider) and point `OSM_*_URL` at it.

Both services are optional: with `OSM_ENABLED=false`, or on timeout/HTTP error,
search falls back to the curated data and distances fall back to the
straight-line estimate — nothing throws and no fare is invented. A place that
cannot be grounded at all still fails with `distance_unresolvable` (HTTP 422)
rather than a guessed position.

## Tests

The suite never calls the public instances (`OSM_ENABLED=false` in
`phpunit.xml`). `tests/Feature/OsmGeoIntegrationTest.php` opts back in with
`config(['services.osm.enabled' => true])` and stubs HTTP;
`tests/Feature/GeoCachePersistenceTest.php` proves the durable layer (first
quote writes the row, the next one reads it after `Cache::flush()`, expiry
re-measures, and a missing table degrades instead of failing).

```bash
php artisan test --filter='OsmGeo|GeoCache'
```

For a manual live check:

```php
app(App\Services\OsmGeoService::class)->search('Arambagh');
app(App\Services\OsmGeoService::class)->route(22.5726, 88.3639, 21.6266, 87.5074);
```
