@extends('layouts.app')

@section('content')
<div class="main-content-wrapper" style="padding: 28px 16px 48px; max-width: 760px; margin: 0 auto; width: 100%;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
        <div style="display:flex; align-items:center; gap:8px;">
            <span class="material-symbols-outlined" style="color:var(--primary); font-size:22px;">gavel</span>
            <div>
                <div style="font-family:var(--font-display); font-size:17px; font-weight:800; color:var(--text-main);">
                    Driver Bidding &amp; Trip Auction
                </div>
                <div style="font-size:11.5px; color:var(--text-muted);">
                    IntercityWheels Reverse Auction Portal
                </div>
            </div>
        </div>
        <a href="{{ route('home') }}" class="btn btn-sm btn-outline">
            <span class="material-symbols-outlined" style="font-size:16px;">arrow_back</span>
            <span>Back to Console</span>
        </a>
    </div>

    @if($missing)
        <div class="driver-panel-card">
            <div class="driver-panel-card-header">
                <div>
                    <span class="admin-badge badge-danger">Link Not Valid</span>
                    <h1 class="driver-panel-card-title" style="margin-top:8px;">This bidding link is not valid</h1>
                </div>
            </div>
            <div class="driver-panel-card-body">
                <p style="font-size:13.5px; color:var(--text-muted); margin-bottom:16px;">
                    Ask the IntercityWheels operations team to send you a fresh bidding link or return to the Driver Console to view active auctions.
                </p>
                <a href="{{ route('home') }}" class="btn btn-sm btn-primary">
                    <span class="material-symbols-outlined" style="font-size:16px;">local_offer</span>
                    <span>Open Driver Console</span>
                </a>
            </div>
        </div>
    @else
        @php
            $statusBadge = [
                'open' => ['badge-success', 'Bidding Open'],
                'closed' => ['badge-secondary', 'Bidding Closed'],
                'awarded' => ['badge-gold', 'Allotted'],
                'cancelled' => ['badge-danger', 'Cancelled'],
            ][$payload['status']] ?? ['badge-secondary', ucfirst($payload['status'])];

            $bidMinFloor = $payload['min_driver_price'] ?? 1;
            $bidMaxCeiling = $payload['current_lowest'] !== null
                ? max($bidMinFloor, (float) $payload['current_lowest'] - 1)
                : (float) $payload['start_amount'];
            $termsUrl = \App\Models\PlatformSetting::get('terms_conditions_url', '/terms-conditions') ?: '/terms-conditions';
        @endphp

        <!-- Main Auction Summary Card -->
        <div class="driver-panel-card">
            <div class="driver-panel-card-header">
                <div>
                    <div class="auction-meta-badges">
                        <span class="admin-badge badge-primary" style="font-family:monospace;">{{ $payload['booking_reference'] }}</span>
                        <span class="admin-badge {{ $statusBadge[0] }}">{{ $statusBadge[1] }}</span>
                        @if($payload['you_are_lowest'] && $payload['is_open'])
                            <span class="admin-badge badge-success">You Hold Lowest Bid</span>
                        @endif
                    </div>
                    <h1 class="driver-panel-card-title" style="margin-top:8px;">
                        {{ $payload['pickup_city'] }} &rarr; {{ $payload['drop_city'] }}
                    </h1>
                    <p class="driver-panel-card-subtitle">
                        {{ strtoupper($payload['service_type']) }} &bull; {{ ucfirst($payload['trip_type']) }}
                        @if($payload['distance_km'] !== null) &bull; {{ $payload['distance_km'] }} km @endif
                        @if($payload['departure_at'] ?? null) &bull; Pickup {{ \Illuminate\Support\Carbon::parse($payload['departure_at'])->format('d M Y, h:i A') }} @endif
                    </p>
                </div>
                <span class="auction-deadline-pill">
                    <span class="material-symbols-outlined" style="font-size:15px;">schedule</span>
                    @if($payload['closes_at'])
                        <span>Closes {{ \Illuminate\Support\Carbon::parse($payload['closes_at'])->format('d M Y, h:i A') }}</span>
                    @else
                        <span>Open until driver allotted</span>
                    @endif
                </span>
            </div>

            <div class="driver-panel-card-body" style="display:flex; flex-direction:column; gap:14px;">
                <div class="auction-route-strip">
                    <div>
                        <div class="auction-route-city-label pickup">Pickup City</div>
                        <div class="auction-route-city-name">{{ $payload['pickup_city'] }}</div>
                    </div>
                    <div class="auction-route-mid">
                        <div class="auction-distance-pill">
                            <span class="material-symbols-outlined" style="font-size:14px;">route</span>
                            <span>{{ $payload['distance_km'] !== null ? $payload['distance_km'] . ' KM' : 'On request' }}</span>
                        </div>
                        @if($payload['departure_at'] ?? null)
                            <div class="auction-departure-meta">
                                <span class="material-symbols-outlined" style="font-size:13px;">calendar_today</span>
                                <span>{{ \Illuminate\Support\Carbon::parse($payload['departure_at'])->format('d M Y, h:i A') }}</span>
                            </div>
                        @endif
                    </div>
                    <div>
                        <div class="auction-route-city-label drop">Drop City</div>
                        <div class="auction-route-city-name">{{ $payload['drop_city'] }}</div>
                    </div>
                </div>

                <div class="auction-metrics-grid">
                    <div class="auction-metric-cell">
                        <div class="auction-metric-label">Max Allowed Bid</div>
                        <div class="auction-metric-value gold">₹{{ number_format($payload['start_amount'], 0) }}</div>
                    </div>
                    <div class="auction-metric-cell">
                        <div class="auction-metric-label">Current Lowest</div>
                        <div class="auction-metric-value success">
                            @if($payload['current_lowest'] !== null)
                                ₹{{ number_format($payload['current_lowest'], 0) }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div class="auction-metric-cell">
                        <div class="auction-metric-label">Instant-Win Floor</div>
                        <div class="auction-metric-value">₹{{ number_format($bidMinFloor, 0) }}</div>
                    </div>
                </div>

                <div style="font-size:12px; color:var(--text-muted); background:var(--surface-base); border:1px solid var(--outline); border-radius:8px; padding:10px 12px; display:flex; align-items:flex-start; gap:8px;">
                    <span class="material-symbols-outlined" style="font-size:17px; color:var(--primary); flex-shrink:0;">info</span>
                    <span>
                        Valid bid range: <strong>₹{{ number_format($bidMinFloor, 0) }} – ₹{{ number_format($bidMaxCeiling, 0) }}</strong>.
                        Quoting the floor price (<strong>₹{{ number_format($bidMinFloor, 0) }}</strong>) immediately wins and allots this trip to you.
                        Customer contact and exact addresses stay hidden until allotment.
                    </span>
                </div>
            </div>
        </div>

        <!-- Allotted Trip Reveal or Status Alert -->
        @if($awardedToYou && $revealedBooking)
            <div class="driver-panel-card" style="border-color: rgba(22, 163, 74, 0.45);">
                <div class="driver-panel-card-header" style="background:#f0fdf4;">
                    <div>
                        <span class="admin-badge badge-success">You won this trip</span>
                        <h2 class="driver-panel-card-title" style="margin-top:6px;">Full trip details released</h2>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:11px; color:var(--text-muted); font-weight:700;">YOUR LOCKED PAYOUT</div>
                        <div style="font-family:var(--font-display); font-size:20px; font-weight:800; color:var(--success);">
                            ₹{{ number_format((float) $revealedBooking->driver_payout_amount, 0) }}
                        </div>
                    </div>
                </div>
                <div class="driver-panel-card-body">
                    <div class="driver-details-grid">
                        <div class="driver-details-row">
                            <span class="driver-details-label">Passenger</span>
                            <span class="driver-details-value">{{ $revealedBooking->customer_name }}</span>
                        </div>
                        <div class="driver-details-row">
                            <span class="driver-details-label">Mobile</span>
                            <span class="driver-details-value">
                                <a href="tel:{{ $revealedBooking->customer_phone }}" style="color:#0284c7; text-decoration:none;">{{ $revealedBooking->customer_phone }}</a>
                            </span>
                        </div>
                        <div class="driver-details-row">
                            <span class="driver-details-label">Pickup Address</span>
                            <span class="driver-details-value">{{ $revealedBooking->pickup_location }}</span>
                        </div>
                        <div class="driver-details-row">
                            <span class="driver-details-label">Drop Address</span>
                            <span class="driver-details-value">{{ $revealedBooking->drop_location }}</span>
                        </div>
                        @if($revealedBooking->departure_time)
                        <div class="driver-details-row">
                            <span class="driver-details-label">Departure</span>
                            <span class="driver-details-value">{{ $revealedBooking->departure_time->format('d M Y, h:i A') }}</span>
                        </div>
                        @endif
                    </div>
                    <p style="font-size:12px; color:var(--text-muted); margin:12px 0 14px; line-height:1.5;">
                        Ask the passenger for their 4-digit ride start OTP when commencing the trip.
                    </p>
                    <a class="btn btn-primary btn-block" href="{{ route('home') }}">
                        <span class="material-symbols-outlined" style="font-size:17px;">local_taxi</span>
                        <span>Open Driver Console</span>
                    </a>
                </div>
            </div>
        @elseif($payload['status'] === 'awarded')
            <div class="driver-panel-card">
                <div class="driver-panel-card-body">
                    <div style="display:flex; align-items:center; gap:10px; font-size:13px; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:12px 14px;">
                        <span class="material-symbols-outlined" style="font-size:20px;">verified</span>
                        <span>This trip has been allotted to another driver partner. Check the Driver Console for other open auctions.</span>
                    </div>
                </div>
            </div>
        @elseif($payload['status'] === 'cancelled')
            <div class="driver-panel-card">
                <div class="driver-panel-card-body">
                    <div style="display:flex; align-items:center; gap:10px; font-size:13px; color:#b91c1c; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:12px 14px;">
                        <span class="material-symbols-outlined" style="font-size:20px;">cancel</span>
                        <span>Bidding for this booking was cancelled by the operations team.</span>
                    </div>
                </div>
            </div>
        @endif

        <!-- Bid Submission Card -->
        @if($payload['is_open'])
            @guest
                <div class="driver-panel-card">
                    <div class="driver-panel-card-header">
                        <div>
                            <span class="admin-badge badge-primary">Driver Partners Only</span>
                            <h2 class="driver-panel-card-title" style="margin-top:6px;">Sign in to place your bid</h2>
                            <p class="driver-panel-card-subtitle">Bids are accepted only from registered, verified driver partners.</p>
                        </div>
                    </div>
                    <div class="driver-panel-card-body">
                        <form method="POST" action="{{ route('login') }}">
                            @csrf
                            <input type="hidden" name="next" value="/bid/{{ $token }}">
                            <div class="form-group" style="margin-bottom:12px;">
                                <label class="form-label" for="bid-email">Email Address</label>
                                <input class="form-control" type="email" id="bid-email" name="email" required autocomplete="email" placeholder="driver@example.com">
                            </div>
                            <div class="form-group" style="margin-bottom:16px;">
                                <label class="form-label" for="bid-password">Password</label>
                                <input class="form-control" type="password" id="bid-password" name="password" required autocomplete="current-password" placeholder="Enter your password">
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">
                                <span class="material-symbols-outlined" style="font-size:17px;">login</span>
                                <span>Sign In &amp; Bid</span>
                            </button>
                        </form>
                        <a class="btn btn-outline btn-block" style="margin-top:10px;" href="{{ route('home') }}">New driver? Register here</a>
                    </div>
                </div>
            @else
                @if($driver === null)
                    <div class="driver-panel-card">
                        <div class="driver-panel-card-body">
                            <div style="font-size:13px; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:12px 14px;">
                                This account has no driver profile. Register as a driver partner to bid on trips.
                            </div>
                        </div>
                    </div>
                @elseif(! $isVerified)
                    <div class="driver-panel-card">
                        <div class="driver-panel-card-body">
                            <div style="font-size:13px; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:12px 14px;">
                                Your driver profile is pending verification. You can bid once the operations team approves it.
                            </div>
                        </div>
                    </div>
                @else
                    <div class="driver-panel-card">
                        <div class="driver-panel-card-header">
                            <div>
                                <h2 class="driver-panel-card-title">
                                    <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">gavel</span>
                                    <span>Place your bid</span>
                                </h2>
                                <p class="driver-panel-card-subtitle">
                                    Bidding as <strong>{{ $driver->name }}</strong> &bull; {{ $driver->vehicle_model }} ({{ $driver->vehicle_plate }})
                                </p>
                            </div>
                            <span class="admin-badge badge-info">{{ $remainingBids }} bid{{ $remainingBids === 1 ? '' : 's' }} left</span>
                        </div>
                        <div class="driver-panel-card-body">
                            <div class="form-group" style="margin-bottom:10px;">
                                <label class="form-label" for="bid-amount">
                                    I will accept this booking at price (₹{{ number_format($bidMinFloor, 0) }} – ₹{{ number_format($bidMaxCeiling, 0) }})
                                </label>
                                <input class="form-control" type="number" id="bid-amount" inputmode="numeric"
                                       min="{{ (int) $bidMinFloor }}" max="{{ (int) $bidMaxCeiling }}" step="1"
                                       style="font-size:18px; font-weight:800; height:44px;"
                                       placeholder="e.g. {{ (int) max($bidMinFloor, floor(($payload['current_lowest'] ?? $payload['start_amount']) - 100)) }}">
                            </div>

                            <div id="bid-statement-preview" style="font-size:12px; color:var(--text-main); font-weight:600; background:var(--surface-base); border:1px dashed var(--outline); border-radius:8px; padding:8px 10px; margin-bottom:12px;">
                                Enter your amount above: <em>"I will accept this booking ({{ $payload['pickup_city'] }} &rarr; {{ $payload['drop_city'] }}) at price ₹___."</em>
                            </div>

                            <label class="auction-bid-terms" style="margin-bottom:14px;">
                                <input type="checkbox" id="bid-agree-terms" style="margin-top:2px; width:16px; height:16px; accent-color:var(--primary);">
                                <span>
                                    I agree to the <a href="{{ $termsUrl }}" target="_blank" rel="noopener" style="color:var(--primary); font-weight:600; text-decoration:underline;">Terms &amp; Conditions</a> and confirm I will accept and complete this booking at my quoted price if allotted.
                                </span>
                            </label>

                            <button type="button" class="btn btn-primary btn-block" id="bid-submit" style="height:44px; font-size:14px;">
                                <span class="material-symbols-outlined" style="font-size:17px;">gavel</span>
                                <span>Submit Bid</span>
                            </button>
                            <div class="auction-bid-feedback" id="bid-msg" role="status" aria-live="polite" style="margin-top:10px;"></div>
                        </div>
                    </div>
                @endif
            @endguest
        @endif

        <!-- Driver's Own Bids -->
        @if($myBids->isNotEmpty())
            <div class="driver-panel-card">
                <div class="driver-panel-card-header">
                    <h2 class="driver-panel-card-title">
                        <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">history</span>
                        <span>Your Submitted Bids</span>
                    </h2>
                    <span class="admin-badge badge-secondary">{{ $myBids->count() }} Total</span>
                </div>
                <div class="driver-panel-card-body">
                    <div class="driver-details-grid">
                        @foreach($myBids->sortByDesc('id') as $bid)
                            <div class="driver-details-row" style="align-items:center;">
                                <span class="driver-details-label">{{ $bid->created_at?->format('d M Y, h:i A') }}</span>
                                <span class="driver-details-value" style="display:flex; align-items:center; gap:8px;">
                                    <span>₹{{ number_format((float) $bid->bid_amount, 0) }}</span>
                                    @if($bid->is_winning)
                                        <span class="admin-badge badge-success" style="font-size:10px;">Lowest</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>

@if(! $missing && $payload['is_open'] && $driver !== null && $isVerified)
<script>
(function () {
    const token = @json($token);
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const submit = document.getElementById('bid-submit');
    const amountInput = document.getElementById('bid-amount');
    const agreeTerms = document.getElementById('bid-agree-terms');
    const preview = document.getElementById('bid-statement-preview');
    const msg = document.getElementById('bid-msg');
    const pickupCity = @json($payload['pickup_city']);
    const dropCity = @json($payload['drop_city']);

    function updatePreview() {
        if (!preview) return;
        const val = Number(amountInput.value);
        if (val > 0) {
            preview.textContent = 'I will accept this booking (' + pickupCity + ' → ' + dropCity + ') at price ₹' + val.toLocaleString('en-IN') + '.';
        } else {
            preview.textContent = 'Enter your amount above: "I will accept this booking (' + pickupCity + ' → ' + dropCity + ') at price ₹___."';
        }
    }

    amountInput.addEventListener('input', updatePreview);

    function show(text, ok) {
        msg.textContent = text;
        msg.className = 'auction-bid-feedback ' + (ok ? 'ok' : 'error');
    }

    async function placeBid() {
        const amount = Number(amountInput.value);

        if (!amount || amount < 1) {
            show('Enter your price in rupees.', false);
            amountInput.focus();
            return;
        }

        if (agreeTerms && !agreeTerms.checked) {
            show('Please agree to the Terms & Conditions before placing your bid.', false);
            agreeTerms.focus();
            return;
        }

        submit.disabled = true;
        submit.textContent = 'Submitting…';

        try {
            const res = await fetch('/api/bidding/' + encodeURIComponent(token) + '/bids', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ amount: amount, agreed_terms: true })
            });

            const data = await res.json().catch(() => ({}));

            if (res.ok && data.success) {
                show(data.message || 'Bid placed. Reloading…', true);
                window.setTimeout(function () { window.location.reload(); }, 600);
                return;
            }

            if (res.status === 419) {
                show('Your session expired. Reload the page and try again.', false);
            } else {
                show(data.message || 'That bid was not accepted.', false);
            }
        } catch (err) {
            show('Network error. Please try again.', false);
        }

        submit.disabled = false;
        submit.innerHTML = '<span class="material-symbols-outlined" style="font-size:17px;">gavel</span><span>Submit Bid</span>';
    }

    submit.addEventListener('click', placeBid);
    amountInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') { event.preventDefault(); placeBid(); }
    });
})();
</script>
@endif
@endsection
