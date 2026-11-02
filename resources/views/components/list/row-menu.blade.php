{{-- Items: <li><a class="dropdown-item" ...></li>; fixed strategy keeps the menu visible inside scrolling tables. --}}
@props(['label'])
<div class="dropdown row-menu d-print-none">
    <button class="btn btn-icon" type="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Actions for {{ $label }}">
        <i class="fas fa-ellipsis-h" aria-hidden="true"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">{{ $slot }}</ul>
</div>
