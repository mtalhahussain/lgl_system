@props(['title', 'eyebrow' => null, 'subtitle' => null])
<div class="page-heading">
    <div>
        @if($eyebrow)<div class="eyebrow">{{ $eyebrow }}</div>@endif
        <h1>{{ $title }}</h1>
        @if($subtitle)<p class="page-subtitle">{{ $subtitle }}</p>@endif
    </div>
    @if(trim($slot) !== '')<div class="page-actions d-print-none">{{ $slot }}</div>@endif
</div>
