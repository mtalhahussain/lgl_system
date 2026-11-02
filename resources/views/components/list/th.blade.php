{{-- Sortable header: <x-list.th sort="name">Name</x-list.th>; the first default-sorted key may be passed as "default". --}}
@props(['sort' => null, 'default' => null])
@php
    $active = $sort && (request('sort') === $sort || (! request('sort') && $default && ltrim($default, '-') === $sort));
    $direction = request('sort') === $sort ? (request('dir') === 'desc' ? 'desc' : 'asc') : ($default && str_starts_with($default, '-') ? 'desc' : 'asc');
@endphp
<th {{ $attributes }} @if($active) aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif scope="col">
    @if($sort)
        <a class="sort-link {{ $active ? 'is-active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => $sort, 'dir' => $active && $direction === 'asc' ? 'desc' : 'asc', 'page' => null, 'export' => null]) }}">
            {{ $slot }}<i class="fas {{ $active ? ($direction === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort' }}" aria-hidden="true"></i>
        </a>
    @else
        {{ $slot }}
    @endif
</th>
