{{--
    filters: [['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active']], ['name' => 'from', 'label' => 'From', 'type' => 'date']]
    search: placeholder text, or null to hide the search box
--}}
@props(['filters' => [], 'search' => null, 'csv' => false, 'perPage' => true, 'perPageDefault' => 15])
@php
    $filters = collect($filters)->map(fn ($filter) => $filter + ['type' => isset($filter['options']) ? 'select' : 'text', 'options' => []]);
    $chips = [];
    if ($search && filled(request('search'))) {
        $chips[] = ['label' => 'Search', 'value' => request('search'), 'key' => 'search'];
    }
    foreach ($filters as $filter) {
        $value = request($filter['name']);
        if (is_string($value) && $value !== '') {
            $options = collect($filter['options'])->all();
            $chips[] = ['label' => $filter['label'], 'value' => $options[$value] ?? $value, 'key' => $filter['name']];
        }
    }
    $keep = array_filter(request()->only(['sort', 'dir', 'per_page']), 'filled');
@endphp
<div class="list-toolbar d-print-none">
    @if($perPage || $search)
    <div class="list-display-options">
        @if($perPage)
        @php
            $pageSizeId = 'filter-page-size';
            $queryFields = [];
            $flattenQuery = function ($values, $prefix = '') use (&$flattenQuery, &$queryFields) {
                foreach ($values as $key => $value) {
                    $name = $prefix === '' ? $key : $prefix.'['.$key.']';
                    if (is_array($value)) {
                        $flattenQuery($value, $name);
                    } elseif (is_scalar($value)) {
                        $queryFields[$name] = $value;
                    }
                }
            };
            $flattenQuery(request()->except(['page', 'per_page', 'export']));
        @endphp
        <form method="get" action="{{ url()->current() }}" class="list-page-size d-print-none" data-page-size>
            @foreach($queryFields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <label for="{{ $pageSizeId }}">Rows per page</label>
            <select id="{{ $pageSizeId }}" name="per_page" class="form-select" data-native>
                @foreach(\App\Support\ListQuery::PER_PAGE as $size)
                    <option value="{{ $size }}" @selected((int) request('per_page', $perPageDefault) === $size)>{{ $size }}</option>
                @endforeach
            </select>
            <button class="btn btn-outline-primary page-size-apply">Apply</button>
        </form>
        @endif
        @if($search)
            <div class="list-search">
                <label class="visually-hidden" for="list_search">{{ $search }}</label>
                <i class="fas fa-search" aria-hidden="true"></i>
                <input id="list_search" form="list-filters" type="search" name="search" value="{{ request('search') }}" placeholder="{{ $search }}" maxlength="100" autocomplete="off">
            </div>
        @endif
    </div>
    @endif
    <form method="get" action="{{ url()->current() }}" id="list-filters" class="list-filter" data-list-filter role="search">
        @foreach(['sort', 'dir'] as $key)
            @if(filled(request($key)))<input type="hidden" name="{{ $key }}" value="{{ request($key) }}">@endif
        @endforeach

        @foreach($filters as $filter)
            <div class="list-field">
                <label for="filter_{{ $filter['name'] }}">{{ $filter['label'] }}</label>
                @if($filter['type'] === 'select')
                    <select id="filter_{{ $filter['name'] }}" name="{{ $filter['name'] }}" class="form-select">
                        <option value="">All</option>
                        @foreach($filter['options'] as $value => $label)
                            <option value="{{ $value }}" @selected((string) request($filter['name']) === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                @else
                    <input id="filter_{{ $filter['name'] }}" type="{{ $filter['type'] }}" name="{{ $filter['name'] }}" value="{{ request($filter['name']) }}" class="form-control">
                @endif
            </div>
        @endforeach
        @if($perPage)
            <input type="hidden" name="per_page" value="{{ request('per_page', $perPageDefault) }}">
        @endif
        <div class="list-buttons">
            <button class="btn btn-primary list-apply">Apply</button>
            @if($csv && auth()->user()->isAdmin())
                <a class="btn btn-outline-primary" href="{{ request()->fullUrlWithQuery(['export' => 'csv', 'page' => null]) }}"><i class="fas fa-file-csv me-1" aria-hidden="true"></i>CSV</a>
            @endif
        </div>
    </form>
    @if(count($chips))
        <div class="list-chips" aria-label="Active filters">
            @foreach($chips as $chip)
                <a class="filter-chip" href="{{ request()->fullUrlWithoutQuery([$chip['key'], 'page', 'export']) }}" aria-label="Remove filter {{ $chip['label'] }}: {{ $chip['value'] }}">
                    {{ $chip['label'] }}: {{ $chip['value'] }} <span aria-hidden="true">&times;</span>
                </a>
            @endforeach
            <a class="filter-clear" href="{{ url()->current().($keep ? '?'.http_build_query($keep) : '') }}">Clear all</a>
        </div>
    @endif
</div>
