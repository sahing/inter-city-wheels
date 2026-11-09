@extends('layouts.app')

@section('content')
<div class="main-content-wrapper" style="padding: 40px 16px; max-width: 860px; margin: 0 auto;">
    <div class="card" style="padding: 32px 28px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 24px;">
            <div>
                <span class="admin-badge badge-gold" style="font-size: 10px; text-transform:uppercase;">IntercityWheels Legal Document</span>
                <h1 style="font-family: var(--font-display); font-size: 24px; font-weight: 800; color: #0f172a; margin: 6px 0 2px;">{{ $contentTitle }}</h1>
                <div style="font-size: 12px; color: #64748b;">Last Updated: {{ $updatedAt }}</div>
            </div>
            <a href="{{ route('home') }}" class="btn btn-sm btn-outline">
                <span class="material-symbols-outlined" style="font-size: 16px;">arrow_back</span>
                <span>Back to Home</span>
            </a>
        </div>

        <div style="font-size: 14.5px; color: #334155; line-height: 1.7; margin-bottom: 24px;">
            <p>{{ $body }}</p>
            <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 20px;">1. Operational Scope & Service Fulfillment</h3>
            <p>IntercityWheels provides verified chauffeur-driven outstation cabs and scheduled sleeper berths connecting major intercity routes and regional transit hubs. Users agree to share accurate pickup locations and verify the 4-digit ride OTP before boarding.</p>
            <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 20px;">2. Transparent Pricing & Toll Escrow</h3>
            <p>All displayed fares include base km distance, state highway tolls, and applicable GST. Driver bids are binding upon confirmation.</p>
        </div>

        <div style="border-top: 1px solid #e2e8f0; padding-top: 16px; display:flex; justify-content:space-between; align-items:center; font-size: 12px; color: #64748b;">
            <div>IntercityWheels Mobility Services Pvt. Ltd. &bull; Helpline: 1800-209-WHEELS</div>
            <a href="mailto:support@intercitywheels.com" style="color:var(--primary); font-weight:600; text-decoration:none;">support@intercitywheels.com</a>
        </div>
    </div>
</div>
@endsection

