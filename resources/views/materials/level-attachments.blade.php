@if(module_visible('materials') && ($course->materials_count ?? 0) > 0)
    <a href="{{ route('materials.index', ['course_id' => $course->id]) }}"><i class="fas fa-paperclip me-1" aria-hidden="true"></i>{{ $course->materials_count }} {{ Str::plural('attachment', $course->materials_count) }}</a>
@else
    <span class="text-muted">—</span>
@endif
