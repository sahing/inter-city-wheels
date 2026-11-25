# Intercity Wheels - Laravel Application Architecture & Deployment Guide

This project is fully powered by **Laravel 12** running on **PHP 8.2+**, integrating the complete feature set defined in [gemini.md](file:///f:/antigravityprojects/inter-city-wheels/.agents/gemini.md) and the user design from [DESIGN.md](file:///f:/antigravityprojects/inter-city-wheels/.agents/design/DESIGN.md).

---

## 1. Architecture Overview

### Database Models & Schema ([database/migrations/](file:///f:/antigravityprojects/inter-city-wheels/database/migrations/))
- **`RouteFare`**: City pairs, GPS coordinates (`from_lat`, `from_lng`, `to_lat`, `to_lng`), route distance (km), duration, and base highway tolls.
- **`Driver`**: Verified chauffeur profiles, vehicle details, license plate, star ratings, wallet balance, and online status.
- **`SeatLayout`**: Sleeper/seater coach decks (Upper & Lower), berth numbers (`L1`–`L9`, `U1`–`U6`), window/aisle designations, base fares, and ladies quota reservation flags.
- **`Booking`**: Unique references (`ICW-xxxxx`), customer info, trip types, selected berths/fleet, itemized tolls/add-ons, 4-digit ride verification OTP, and dynamic form data.
- **`DriverBid`**: Driver counter-bids, customer counter-proposals, and state tracking.
- **`DynamicFormField`**: Configurable booking fields (e.g. Flight Number, Pet Breed, Company GSTIN) with conditional logic rules.
- **`WorkflowStage`**: 5-stage automated booking lifecycle state machine.
- **`Coupon`**: Promotional discount rules (`FIRSTWHEELS` 15% discount, `INTERCITY100` flat ₹100).

---

## 2. Controllers & API Endpoints

- **[HomeController.php](file:///f:/antigravityprojects/inter-city-wheels/app/Http/Controllers/HomeController.php)**: Renders the master Blade view (`resources/views/intercity.blade.php`) populated with Eloquent database records.
- **[BookingController.php](file:///f:/antigravityprojects/inter-city-wheels/app/Http/Controllers/BookingController.php)**:
  - `POST /api/bookings`: Processes cab and berth reservations, applies coupons, computes taxes/tolls, and generates a secure 4-digit start OTP.
  - `GET /api/bookings/{reference}`: Fetches confirmed ticket details.
- **[DriverBiddingController.php](file:///f:/antigravityprojects/inter-city-wheels/app/Http/Controllers/DriverBiddingController.php)**:
  - `GET /api/bids`: Live auction bids from verified drivers.
  - `POST /api/bids/counter`: Submits passenger counter-offers.
  - `POST /api/bids/accept`: Locks in a driver and issues ride confirmation.
- **[DynamicFormController.php](file:///f:/antigravityprojects/inter-city-wheels/app/Http/Controllers/Admin/DynamicFormController.php)**:
  - `GET /api/admin/dynamic-fields` & `POST /api/admin/dynamic-fields`: Dynamic field CRUD with conditional rules.
- **[WorkflowConfigController.php](file:///f:/antigravityprojects/inter-city-wheels/app/Http/Controllers/Admin/WorkflowConfigController.php)**:
  - `GET /api/admin/workflows` & `PUT /api/admin/workflows/{id}`: Workflow pipeline stage management.
- **[RouteCalculationController.php](file:///f:/antigravityprojects/inter-city-wheels/app/Http/Controllers/Api/RouteCalculationController.php)**:
  - `GET /api/routes/calculate`: Computes distance, drive time, tolls, and fleet fares between any two cities.

---

## 3. Automated Testing Suite

The application includes automated Feature & Unit tests:
```bash
php artisan test
```

### Test Coverage Results:
- `✓ sedan distance fare formula`
- `✓ suv distance fare formula`
- `✓ sleeper berths total`
- `✓ home page loads with laravel blade`
- `✓ health check endpoint`
- `✓ route calculation api`
- `✓ store cab booking with coupon`
- `✓ store bus sleeper berth booking`
- `✓ driver bidding counter offer`
- `✓ admin dynamic form field creation`

**Result**: **10 / 10 Tests Passed (23 assertions, 0 failures)**

---

## 4. Local Development & Seeding

1. **Environment Configuration**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

2. **Database Migration & Seeding**:
   ```bash
   php artisan migrate:fresh --seed
   ```

3. **Start Development Server**:
   ```bash
   php artisan serve --port=3000
   ```
   Access application: `http://localhost:3000`

4. **Maps and distance**: place search and driving distance come from
   OpenStreetMap (Nominatim + OSRM), configured through `OSM_*` variables. No
   API key is required for development; see [docs/OSM.md](docs/OSM.md) before
   pointing production traffic at the public instances.

5. **Firebase sign-in**: Google sign-in uses the `FIREBASE_*` variables and is
   verified server-side. After changing the Firebase project, run
   `php artisan firebase:check` — it reproduces `redirect_uri_mismatch`,
   unauthorized domains and a disabled provider without a browser. See
   [docs/FIREBASE.md](docs/FIREBASE.md).

---

## 5. Production Deployment

### Option A: Laravel Forge / Cloud VPS (Nginx + PHP 8.2-FPM)
1. Point your domain to `/public` directory.
2. In your Nginx vhost:
   ```nginx
   server {
       listen 80;
       server_name intercitywheels.com;
       root /var/www/intercity-wheels/public;

       index index.php index.html;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
           fastcgi_index index.php;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
           include fastcgi_params;
       }
   }
   ```
3. Optimize configuration for production:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

### Option B: Docker Containerization
Using the provided `Dockerfile` and `docker-compose.yml`:
```bash
docker-compose up -d --build
```

### Option C: Health Monitoring
Check service health anytime:
```bash
curl http://localhost:3000/health
```
Response:
```json
{
  "status": "healthy",
  "framework": "Laravel 12 (PHP 8.2.12)",
  "database": "connected",
  "timestamp": "2026-10-13T04:49:02+00:00"
}
```
