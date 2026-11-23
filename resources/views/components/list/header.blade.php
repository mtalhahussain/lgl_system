@props(['title', 'icon' => null, 'eyebrow' => null, 'subtitle' => null, 'actionsClass' => ''])
<div class="page-heading">
    <div>
        @if($eyebrow)<div class="eyebrow">{{ $eyebrow }}</div>@endif
        <h1>@if($icon)<i class="{{ str_contains($icon, ' ') ? $icon : 'fas '.$icon }} me-2 text-primary" aria-hidden="true"></i>@endif{{ $title }}</h1>
        @if($subtitle)<p class="page-subtitle">{{ $subtitle }}</p>@endif
    </div>
    @if(trim($slot) !== '')<div @class(['page-actions d-print-none', $actionsClass])>{{ $slot }}</div>@endif
</div>
