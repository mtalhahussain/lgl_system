{{-- items: [['label' => ..., 'value' => ..., 'icon' => 'fa-users', 'note' => optional]] --}}
@props(['items' => []])
@if(count($items))
<div class="kpi-strip">
    @foreach($items as $item)
        <div class="panel metric">
            <div>
                <div class="metric-label">{{ $item['label'] }}</div>
                <div class="metric-value">{{ $item['value'] }}</div>
                @isset($item['note'])<div class="metric-note">{{ $item['note'] }}</div>@endisset
            </div>
            @isset($item['icon'])<span class="metric-icon" aria-hidden="true"><i class="fas {{ $item['icon'] }}"></i></span>@endisset
        </div>
    @endforeach
</div>
@endif
