@props(['spacing' => 'mt-3'])
@php
    $messages = array_values(array_unique($errors->all()));
    $shown = $messages;
@endphp
@foreach(['success' => ['success', 'fa-check-circle'], 'error' => ['danger', 'fa-exclamation-circle'], 'warning' => ['warning', 'fa-exclamation-triangle']] as $key => [$variant, $icon])
    @if(session($key) && ! in_array(session($key), $shown, true))
        @php($shown[] = session($key))
        <div class="alert alert-{{ $variant }} alert-dismissible fade show d-flex align-items-baseline {{ $spacing }}" role="alert">
            <i class="fas {{ $icon }} me-2 flex-shrink-0" aria-hidden="true"></i>
            <div class="flex-grow-1" style="min-width:0;overflow-wrap:anywhere">{{ session($key) }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
@endforeach
@if($messages)
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-baseline {{ $spacing }}" role="alert">
        <i class="fas fa-exclamation-triangle me-2 flex-shrink-0" aria-hidden="true"></i>
        <ul class="list-unstyled mb-0 flex-grow-1" style="min-width:0;overflow-wrap:anywhere">
            @foreach($messages as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
