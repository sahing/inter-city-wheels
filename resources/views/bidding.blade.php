<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Driver Bidding · IntercityWheels</title>
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}?v={{ filemtime(public_path('css/styles.css')) }}">
    <style>
        :root {
            --bid-bg: #0b0f19;
            --bid-card: #131a28;
            --bid-line: #243044;
            --bid-gold: #d49b4c;
            --bid-gold-soft: #ebcaa5;
            --bid-text: #e8edf5;
            --bid-muted: #93a1b5;
        }
        * { box-sizing: border-box; }
        body.bid-page {
            margin: 0;
            min-height: 100vh;
            background: radial-gradient(1200px 600px at 50% -10%, #182136 0%, var(--bid-bg) 55%);
            color: var(--bid-text);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            padding: 20px 14px 48px;
        }
        .bid-wrap { max-width: 520px; margin: 0 auto; }
        .bid-brand {
            display: flex; align-items: center; gap: 10px;
            font-weight: 800; letter-spacing: 0.08em; font-size: 13px;
            color: var(--bid-gold-soft); text-transform: uppercase; margin-bottom: 18px;
        }
        .bid-brand span.dot { width: 8px; height: 8px; border-radius: 50%; background: var(--bid-gold); }
        .bid-card {
            background: var(--bid-card);
            border: 1px solid var(--bid-line);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 14px;
            box-shadow: 0 18px 40px rgba(0,0,0,0.35);
        }
        .bid-eyebrow { font-size: 11px; letter-spacing: 0.14em; text-transform: uppercase; color: var(--bid-muted); margin-bottom: 6px; }
        .bid-ref { font-size: 20px; font-weight: 800; letter-spacing: 0.02em; }
        .bid-route { display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 700; margin: 14px 0 6px; flex-wrap: wrap; }
        .bid-route .arrow { color: var(--bid-gold); }
        .bid-meta { color: var(--bid-muted); font-size: 13px; }
        .bid-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px; }
        .bid-cell { border: 1px solid var(--bid-line); border-radius: 12px; padding: 12px; background: #0f1624; }
        .bid-cell .k { font-size: 10.5px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--bid-muted); }
        .bid-cell .v { font-size: 20px; font-weight: 800; margin-top: 4px; }
        .bid-cell .v.gold { color: var(--bid-gold-soft); }
        .bid-pill { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; }
        .pill-open { background: rgba(34,197,94,0.14); color: #4ade80; border: 1px solid rgba(34,197,94,0.35); }
        .pill-closed { background: rgba(148,163,184,0.14); color: #cbd5e1; border: 1px solid rgba(148,163,184,0.35); }
        .pill-won { background: rgba(212,155,76,0.16); color: var(--bid-gold-soft); border: 1px solid rgba(212,155,76,0.45); }
        .bid-note { font-size: 12.5px; color: var(--bid-muted); line-height: 1.6; margin-top: 12px; }
        .bid-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; border: 0; border-radius: 12px; padding: 14px 16px;
            font-size: 15px; font-weight: 800; cursor: pointer;
            background: linear-gradient(135deg, var(--bid-gold-soft), var(--bid-gold)); color: #10151f;
        }
        .bid-btn:disabled { opacity: 0.55; cursor: not-allowed; }
        .bid-btn.ghost { background: transparent; border: 1px solid var(--bid-line); color: var(--bid-text); }
        .bid-input {
            width: 100%; padding: 14px; border-radius: 12px; border: 1px solid var(--bid-line);
            background: #0f1624; color: var(--bid-text); font-size: 18px; font-weight: 700;
        }
        .bid-input:focus { outline: none; border-color: var(--bid-gold); }
        .bid-label { font-size: 12px; color: var(--bid-muted); margin: 14px 0 6px; display: block; }
        .bid-msg { margin-top: 10px; font-size: 13px; border-radius: 10px; padding: 10px 12px; display: none; }
        .bid-msg.error { display: block; background: rgba(220,38,38,0.12); color: #fca5a5; border: 1px solid rgba(220,38,38,0.35); }
        .bid-msg.ok { display: block; background: rgba(34,197,94,0.12); color: #86efac; border: 1px solid rgba(34,197,94,0.35); }
        .bid-list { list-style: none; margin: 10px 0 0; padding: 0; }
        .bid-list li { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed var(--bid-line); font-size: 13px; }
        .bid-list li:last-child { border-bottom: 0; }
        .bid-reveal { border-color: rgba(212,155,76,0.5); }
        .bid-reveal .row { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; font-size: 13.5px; border-bottom: 1px dashed var(--bid-line); }
        .bid-reveal .row:last-child { border-bottom: 0; }
        .bid-reveal .row span:first-child { color: var(--bid-muted); }
        .bid-reveal .row span:last-child { font-weight: 700; text-align: right; }
        .bid-fine { font-size: 11px; color: #6b7a90; text-align: center; margin-top: 20px; line-height: 1.7; }
        .bid-alert { border-left: 3px solid var(--bid-gold); padding-left: 12px; font-size: 13px; color: var(--bid-gold-soft); line-height: 1.6; }
    </style>
</head>
<body class="bid-page">
<div class="bid-wrap">
    <div class="bid-brand"><span class="dot"></span> IntercityWheels · Driver Bidding</div>

    @if($missing)
        <div class="bid-card">
            <div class="bid-eyebrow">Link not valid</div>
            <h1 class="bid-ref">This bidding link is not valid</h1>
            <p class="bid-note">Ask the IntercityWheels operations team to send you a fresh bidding link.</p>
        </div>
    @else
        @php
            $statusPill = [
                'open' => ['pill-open', 'Bidding open'],
                'closed' => ['pill-closed', 'Bidding closed'],
                'awarded' => ['pill-won', 'Allotted'],
                'cancelled' => ['pill-closed', 'Cancelled'],
            ][$payload['status']] ?? ['pill-closed', ucfirst($payload['status'])];
        @endphp

        <div class="bid-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
                <div>
                    <div class="bid-eyebrow">Booking</div>
                    <div class="bid-ref">{{ $payload['booking_reference'] }}</div>
                </div>
                <span class="bid-pill {{ $statusPill[0] }}">{{ $statusPill[1] }}</span>
            </div>

            <div class="bid-route">
                <span>{{ $payload['pickup_city'] }}</span>
                <span class="arrow">&rarr;</span>
                <span>{{ $payload['drop_city'] }}</span>
            </div>
            <div class="bid-meta">
                {{ strtoupper($payload['service_type']) }} · {{ ucfirst($payload['trip_type']) }}
                @if($payload['distance_km'] !== null) · {{ $payload['distance_km'] }} km @endif
                @if($payload['departure_at'] ?? null) · Pickup {{ \Illuminate\Support\Carbon::parse($payload['departure_at'])->format('d M Y, h:i A') }} @endif
            </div>

            <div class="bid-grid">
                <div class="bid-cell">
                    <div class="k">Max allowed bid</div>
                    <div class="v gold">₹{{ number_format($payload['start_amount'], 0) }}</div>
                </div>
                <div class="bid-cell">
                    <div class="k">Current lowest</div>
                    <div class="v">
                        @if($payload['current_lowest'] !== null)
                            ₹{{ number_format($payload['current_lowest'], 0) }}
                        @else
                            —
                        @endif
                    </div>
                </div>
            </div>

            @php
                $bidMinFloor = $payload['min_driver_price'] ?? 1;
                $bidMaxCeiling = $payload['current_lowest'] !== null
                    ? max(1, (float) $payload['current_lowest'] - 1)
                    : (float) $payload['start_amount'];
            @endphp

            <p class="bid-note">
                @if($payload['closes_at'])
                    Bidding closes {{ \Illuminate\Support\Carbon::parse($payload['closes_at'])->format('d M Y, h:i A') }}.
                @else
                    Open until a driver is allotted.
                @endif
                Bid between <strong>₹{{ number_format($bidMinFloor, 0) }}</strong> and
                <strong>₹{{ number_format($bidMaxCeiling, 0) }}</strong>.
                Quoting the floor <strong>₹{{ number_format($bidMinFloor, 0) }}</strong> immediately wins and allots this trip to you.
            </p>

            @if($payload['you_are_lowest'] && $payload['is_open'])
                <p class="bid-note" style="color:#86efac;">You currently hold the lowest bid for this trip.</p>
            @endif
        </div>

        @if($awardedToYou && $revealedBooking)
            <div class="bid-card bid-reveal">
                <div class="bid-eyebrow">You won this trip</div>
                <h2 class="bid-ref" style="font-size:16px;">Full trip details released</h2>
                <div style="margin-top:12px;">
                    <div class="row"><span>Customer</span><span>{{ $revealedBooking->customer_name }}</span></div>
                    <div class="row"><span>Mobile</span><span>{{ $revealedBooking->customer_phone }}</span></div>
                    <div class="row"><span>Pickup</span><span>{{ $revealedBooking->pickup_location }}</span></div>
                    <div class="row"><span>Drop</span><span>{{ $revealedBooking->drop_location }}</span></div>
                    @if($revealedBooking->departure_time)
                        <div class="row"><span>Departure</span><span>{{ $revealedBooking->departure_time->format('d M Y, h:i A') }}</span></div>
                    @endif
                    <div class="row"><span>Your payout</span><span>₹{{ number_format((float) $revealedBooking->driver_payout_amount, 0) }}</span></div>
                </div>
                <p class="bid-note">
                    An operations executive has confirmed this assignment. The customer can now see your name and vehicle.
                    Ask the passenger for the 4-digit ride OTP when you start the trip.
                </p>
                <a class="bid-btn" style="margin-top:12px; text-decoration:none;" href="{{ route('home') }}">Open driver console</a>
            </div>
        @elseif($payload['status'] === 'awarded')
            <div class="bid-card">
                <div class="bid-alert">This booking has been awarded to another driver partner. Thanks for bidding — you will get the next one.</div>
            </div>
        @elseif($payload['status'] === 'cancelled')
            <div class="bid-card">
                <div class="bid-alert">Bidding for this booking was cancelled by the operations team.</div>
            </div>
        @endif

        @if($payload['is_open'])
            @guest
                <div class="bid-card">
                    <div class="bid-eyebrow">Driver partners only</div>
                    <h2 class="bid-ref" style="font-size:16px;">Sign in to place your bid</h2>
                    <p class="bid-note">Bids are accepted only from registered, verified driver partners.</p>

                    <form method="POST" action="{{ route('login') }}" style="margin-top:12px;">
                        @csrf
                        <input type="hidden" name="next" value="/bid/{{ $token }}">
                        <label class="bid-label" for="bid-email">Email</label>
                        <input class="bid-input" style="font-size:15px; font-weight:500;" type="email" id="bid-email" name="email" required autocomplete="email">
                        <label class="bid-label" for="bid-password">Password</label>
                        <input class="bid-input" style="font-size:15px; font-weight:500;" type="password" id="bid-password" name="password" required autocomplete="current-password">
                        <button type="submit" class="bid-btn" style="margin-top:16px;">Sign in &amp; bid</button>
                    </form>

                    <a class="bid-btn ghost" style="margin-top:10px; text-decoration:none;" href="{{ route('home') }}">New driver? Register here</a>
                </div>
            @else
                @if($driver === null)
                    <div class="bid-card">
                        <div class="bid-alert">This account has no driver profile. Register as a driver partner to bid on trips.</div>
                    </div>
                @elseif(! $isVerified)
                    <div class="bid-card">
                        <div class="bid-alert">Your driver profile is pending verification. You can bid once the operations team approves it.</div>
                    </div>
                @else
                    <div class="bid-card">
                        <div class="bid-eyebrow">Place your bid</div>
                        <div class="bid-meta" style="margin-bottom:4px;">
                            Signed in as <strong>{{ $driver->name }}</strong> · {{ $driver->vehicle_model }} ({{ $driver->vehicle_plate }})
                        </div>

                        <label class="bid-label" for="bid-amount">
                            Your price (₹{{ number_format($bidMinFloor, 0) }} – ₹{{ number_format($bidMaxCeiling, 0) }})
                        </label>
                        <input class="bid-input" type="number" id="bid-amount" inputmode="numeric"
                               min="{{ (int) $bidMinFloor }}" max="{{ (int) $bidMaxCeiling }}" step="1"
                               placeholder="e.g. {{ (int) max($bidMinFloor, floor(($payload['current_lowest'] ?? $payload['start_amount']) - 100)) }}">
                        <label style="display:flex; align-items:flex-start; gap:8px; margin-top:12px; font-size:12.5px; color:var(--bid-muted); cursor:pointer; line-height:1.5;">
                            <input type="checkbox" id="bid-agree-terms" style="margin-top:3px; width:16px; height:16px; accent-color:var(--bid-gold);">
                            <span>
                                I agree to the <a href="{{ \App\Models\PlatformSetting::get('terms_conditions_url', '/terms-conditions') }}" target="_blank" rel="noopener" style="color:var(--bid-gold-soft); text-decoration:underline;">Terms &amp; Conditions</a>, cancellation rules, and commit to complete this trip at my quoted price if allotted.
                            </span>
                        </label>
                        <button type="button" class="bid-btn" id="bid-submit" style="margin-top:14px;">Submit bid</button>
                        <div class="bid-msg" id="bid-msg" role="status" aria-live="polite"></div>
                        <p class="bid-note">
                            {{ $remainingBids }} bid{{ $remainingBids === 1 ? '' : 's' }} left in this auction.
                            Your lowest price wins if the operations team approves it.
                            Customer contact and exact addresses are shared only after approval.
                        </p>
                    </div>
                @endif
            @endguest
        @endif

        @if($myBids->isNotEmpty())
            <div class="bid-card">
                <div class="bid-eyebrow">Your bids</div>
                <ul class="bid-list">
                    @foreach($myBids->sortByDesc('id') as $bid)
                        <li>
                            <span>₹{{ number_format((float) $bid->bid_amount, 0) }}</span>
                            <span style="color:var(--bid-muted);">
                                {{ $bid->created_at?->format('d M, h:i A') }}
                                @if($bid->is_winning) · <strong style="color:#86efac;">lowest</strong> @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <p class="bid-fine">
            Customer name, mobile number and exact addresses stay hidden until the winner is approved —
            this is enforced on the server, not just on this page.
        </p>
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
    const msg = document.getElementById('bid-msg');

    function show(text, ok) {
        msg.textContent = text;
        msg.className = 'bid-msg ' + (ok ? 'ok' : 'error');
    }

    async function placeBid() {
        const amount = Number(amountInput.value);

        if (!amount || amount < 1) {
            show('Enter your price in rupees.', false);
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
                show('Bid placed. Reloading…', true);
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
        submit.textContent = 'Submit bid';
    }

    submit.addEventListener('click', placeBid);
    amountInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') { event.preventDefault(); placeBid(); }
    });
})();
</script>
@endif
</body>
</html>
