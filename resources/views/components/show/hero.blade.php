{{-- Detail page header: <x-show.hero icon="fa-layer-group" eyebrow="Batch" :title="$batch->name" :status="$batch->status">
     <x-slot:meta><span><i class="fas fa-calendar"></i>…</span></x-slot:meta> …action buttons… </x-show.hero> --}}
@props(['title', 'icon' => 'fa-circle-info', 'eyebrow' => null, 'status' => null, 'statusLabel' => null, 'back' => null])
<div class="panel profile-hero">
    <span class="profile-avatar" aria-hidden="true"><i class="fas {{ $icon }}"></i></span>
    <div class="profile-identity">
        @if($eyebrow)<div class="eyebrow">{{ $eyebrow }}</div>@endif
        <h1>{{ $title }}</h1>
        @if($status || isset($meta))
            <div class="profile-meta">
                @if($status)<x-list.status :value="$status" :label="$statusLabel" />@endif
                {{ $meta ?? '' }}
            </div>
        @endif
    </div>
    @if(trim($slot) !== '' || $back)
        <div class="profile-actions d-print-none">
            {{ $slot }}
            @if($back)<a class="btn btn-outline-secondary" href="{{ $back }}"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Back</a>@endif
        </div>
    @endif
</div>
