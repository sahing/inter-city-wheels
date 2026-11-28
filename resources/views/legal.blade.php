@extends('layouts.app')

@section('content')
@php
    $blocks = array_values(array_filter(
        array_map('trim', preg_split('/\r\n\r\n|\r\r|\n\n/', (string) $body) ?: []),
        static fn ($b) => $b !== ''
    ));
    $helpline = $contactHelpline ?? '700 3434 088';
    $email = $contactEmail ?? 'support@intercitywheels.com';
@endphp
<div class="main-content-wrapper" style="padding: 40px 16px; max-width: 860px; margin: 0 auto;">
    <div class="card" style="padding: 32px 28px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 24px;">
            <div>
                <span class="admin-badge badge-gold" style="font-size: 10px; text-transform:uppercase;">IntercityWheels Legal Document</span>
                <h1 style="font-family: var(--font-display); font-size: 24px; font-weight: 800; color: #0f172a; margin: 6px 0 2px;">{{ $contentTitle }}</h1>
                <div style="font-size: 12px; color: #64748b;">Last Updated: {{ $updatedAt }}</div>
            </div>
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                @if(Auth::check() && Auth::user()->role === 'admin')
                <a href="{{ url('/?tab=admin&section=system#admin-legal-pages-card') }}" class="btn btn-sm btn-outline-gold">
                    <span class="material-symbols-outlined" style="font-size: 16px;">edit_document</span>
                    <span>Edit Content</span>
                </a>
                @endif
                <a href="{{ route('home') }}" class="btn btn-sm btn-outline">
                    <span class="material-symbols-outlined" style="font-size: 16px;">arrow_back</span>
                    <span>Back to Home</span>
                </a>
            </div>
        </div>

        <div style="font-size: 14.5px; color: #334155; line-height: 1.7; margin-bottom: 24px;">
            @foreach($blocks as $block)
                @php
                    $lines = preg_split('/\r\n|\r|\n/', $block) ?: [];
                    $firstLine = trim($lines[0] ?? '');
                    $hasHeading = count($lines) > 1 && preg_match('/^(\d+\.|#{1,3}\s)/', $firstLine) === 1;
                @endphp
                @if($hasHeading)
                    <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 20px; margin-bottom: 6px;">{{ ltrim($firstLine, '# ') }}</h3>
                    <p style="margin: 0 0 12px;">{!! nl2br(e(implode("\n", array_slice($lines, 1)))) !!}</p>
                @else
                    <p style="margin: 0 0 14px;">{!! nl2br(e($block)) !!}</p>
                @endif
            @endforeach
        </div>

        <div style="border-top: 1px solid #e2e8f0; padding-top: 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; font-size: 12px; color: #64748b;">
            <div>IntercityWheels Mobility Services Pvt. Ltd. &bull; Helpline: {{ $helpline }}</div>
            <a href="mailto:{{ $email }}" style="color:var(--primary); font-weight:600; text-decoration:none;">{{ $email }}</a>
        </div>
    </div>
</div>
@endsection
