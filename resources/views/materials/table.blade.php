<div class="table-responsive"><table class="table list-table">
    <thead><tr><th scope="col">Title</th><th scope="col">File</th><th scope="col">Size</th><th scope="col">Uploaded</th><th scope="col" class="text-end">Actions</th></tr></thead>
    <tbody>
    @forelse($materials as $material)
        <tr>
            <td><span class="cell-main">{{ $material->title }}</span>@if($material->description)<span class="cell-sub">{{ $material->description }}</span>@endif</td>
            <td class="text-break">{{ $material->original_name }}</td>
            <td class="text-nowrap">{{ $material->readable_size }}</td>
            <td class="text-nowrap">{{ $material->created_at?->format('d M Y') }}</td>
            <td class="text-end text-nowrap">
                @if($material->canPreview())
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('materials.view', $material) }}" target="_blank" rel="noopener"><i class="fas fa-eye me-1" aria-hidden="true"></i>View</a>
                @endif
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('materials.download', $material) }}"><i class="fas fa-download me-1" aria-hidden="true"></i>Download</a>
                @if($manage)
                    @if(can_route('materials.destroy'))<form class="d-inline" method="post" action="{{ route('materials.destroy', $material) }}" data-confirm="Delete {{ $material->title }}? The file will be removed.">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1" aria-hidden="true"></i>Delete</button>
                    </form>@endif
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="5" class="empty-state">No books or materials uploaded for this level yet.</td></tr>
    @endforelse
    </tbody>
</table></div>
