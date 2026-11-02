@props(['paginator', 'label' => 'rows'])
<div class="list-footer">
    <span>Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} {{ $label }}</span>
    {{ $paginator->onEachSide(1)->links('pagination::bootstrap-5') }}
</div>
