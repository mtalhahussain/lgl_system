@props(['colspan' => 1, 'message' => 'No results match these filters.'])
<tr><td colspan="{{ $colspan }}" class="empty-state">
    <i class="fas fa-inbox fa-2x mb-2 d-block" aria-hidden="true"></i>{{ $message }}
    @if(count(request()->except(['sort', 'dir', 'per_page', 'page'])))
        <div class="mt-2"><a class="btn btn-outline-primary btn-sm" href="{{ url()->current() }}">Clear filters</a></div>
    @endif
</td></tr>
