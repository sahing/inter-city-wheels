@php
    $avatarVal = trim((string) ($avatar ?? ''));
    $fallbackVal = $fallback ?? '👤';
    $isAvatarUrl = $avatarVal !== ''
        && (str_starts_with($avatarVal, 'https://') || str_starts_with($avatarVal, 'http://'))
        && filter_var($avatarVal, FILTER_VALIDATE_URL) !== false;
@endphp
@if($isAvatarUrl)
<img src="{{ $avatarVal }}" alt="{{ $alt ?? 'Profile avatar' }}" class="avatar-img" referrerpolicy="no-referrer" data-fallback="{{ $fallbackVal }}" onerror="this.replaceWith(document.createTextNode(this.dataset.fallback || '👤'))">
@else
{{ $avatarVal !== '' ? $avatarVal : $fallbackVal }}
@endif
