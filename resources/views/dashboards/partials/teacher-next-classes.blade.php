<section class="panel tw:mb-6" aria-labelledby="teacher-next-heading">
    <div class="panel-heading">
        <h2 id="teacher-next-heading"><i class="fas fa-chalkboard-teacher me-2 text-navy" aria-hidden="true"></i>Teachers' next classes</h2>
        <span class="page-subtitle">{{ now()->format('D d M Y, g:i A') }} · from batch timetables</span>
    </div>
    @if($teacherNextClasses->isEmpty())
        <p class="empty-state">No teacher has an ongoing batch.</p>
    @else
    <div class="tw:grid tw:grid-cols-1 tw:gap-4 tw:p-4 tw:sm:grid-cols-2 tw:xl:grid-cols-4">
        @foreach($teacherNextClasses as $row)
            @php $next = $row['next']; @endphp
            <article class="tw:rounded-lg tw:border tw:border-slate-200 tw:p-4 {{ $next && $next['live'] ? 'border-left-gold' : '' }}">
                <div class="tw:flex tw:items-center tw:justify-between tw:gap-2 tw:mb-2">
                    <strong>{{ $row['teacher']->name }}</strong>
                    @if($row['onLeaveUntil'])<span class="status-pill pill-plain">On leave · until {{ $row['onLeaveUntil']->format('D j M') }}</span>@endif
                </div>
                @if($next)
                    <div class="metric-label">NEXT · {{ \App\Services\TeacherScheduleService::dayLabel($next) }}</div>
                    <div class="tw:text-lg tw:font-semibold">{{ $next['start']->format('g:i A') }} – {{ $next['end']->format('g:i A') }}</div>
                    <div class="small text-muted">
                        {{ $next['batch']->course?->name }}@if($next['batch']->course?->level) ({{ $next['batch']->course->level }})@endif ·
                        <a href="{{ route('batches.show', $next['batch']) }}">{{ $next['batch']->name }}</a>
                    </div>
                    <span class="status-pill pill-plain tw:mt-2 {{ $next['live'] ? 'pill-live' : '' }}">{{ \App\Services\TeacherScheduleService::relativeLabel($next) }}</span>
                    @if($row['laterToday'])
                        <div class="small text-muted tw:mt-3">Later today:
                            @foreach($row['laterToday'] as $slot){{ $slot['start']->format('g:i A') }} {{ $slot['batch']->name }}@if(! $loop->last), @endif @endforeach
                        </div>
                    @endif
                @else
                    <p class="small text-muted mb-0">No class in next 7 days</p>
                @endif
            </article>
        @endforeach
    </div>
    @endif
</section>
