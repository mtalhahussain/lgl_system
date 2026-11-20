<section class="panel tw:mb-6 {{ $endingSoon->isNotEmpty() ? 'border-left-gold' : '' }}" aria-labelledby="ending-soon-heading">
    <div class="panel-heading">
        <h2 id="ending-soon-heading"><i class="fas fa-hourglass-end me-2 text-gold-ink" aria-hidden="true"></i>Teacher slots freeing up</h2>
        <span class="page-subtitle">Batches ending in the next 7 days · assign the next batch any time</span>
    </div>
    @if($endingSoon->isEmpty())
        <p class="empty-state">No batch ends in the next 7 days.</p>
    @else
    <div class="tw:grid tw:grid-cols-1 tw:gap-4 tw:p-4 tw:sm:grid-cols-2 tw:xl:grid-cols-3">
        @foreach($endingSoon as $row)
            @php $batch = $row['batch']; $days = $row['daysLeft']; @endphp
            <article class="tw:rounded-lg tw:border tw:border-slate-200 tw:p-4">
                <div class="tw:flex tw:items-center tw:justify-between tw:gap-2 tw:mb-1">
                    <strong>{{ $row['teacher']?->name ?? 'No teacher' }}</strong>
                    <span class="status-pill pill-plain">Ends {{ $days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in {$days} days") }}</span>
                </div>
                <div class="small text-muted">
                    <a href="{{ route('batches.show', $batch) }}">{{ $batch->name }}</a>
                    · {{ $batch->course?->name }}@if($batch->course?->level) ({{ $batch->course->level }})@endif
                    · {{ $batch->end_date->format('D d M Y') }}
                </div>
                <div class="small tw:mt-2">
                    <span class="metric-label">SLOTS</span>
                    {{ $row['slots'] ? implode(', ', $row['slots']) : 'No weekly timetable set' }}
                </div>
                <div class="tw:mt-3 tw:flex tw:flex-wrap tw:items-center tw:gap-2">
                    @if($row['nextBatch'])
                        <span class="status-pill pill-plain pill-live">Next: {{ $row['nextBatch']->name }} · starts {{ $row['nextBatch']->start_date->format('d M') }}</span>
                    @else
                        <span class="status-pill pill-plain">No next batch assigned</span>
                        @if($row['teacher'])
                            @if(can_route('batches.create'))<a class="btn btn-sm btn-outline-primary" href="{{ route('batches.create', ['teacher_id' => $batch->teacher_id, 'course_id' => $batch->course_id]) }}"><i class="fas fa-plus me-1" aria-hidden="true"></i>Assign next batch</a>@endif
                        @endif
                    @endif
                </div>
            </article>
        @endforeach
    </div>
    @endif
</section>
