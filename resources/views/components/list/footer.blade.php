@props(['paginator', 'label' => 'rows'])
<div class="list-footer">
    <div class="list-footer-summary">
        <span>Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} {{ $label }}</span>
    </div>
    {{ $paginator->onEachSide(1)->links('pagination::bootstrap-5') }}
</div>
