@php
    $attendanceTab = $tab ?? 'students';
    $attendanceDates = array_filter(['from' => request('from'), 'to' => request('to')]);
@endphp
<nav class="nav nav-pills gap-2 mb-4" aria-label="Attendance groups">
    <a class="nav-link {{ $attendanceTab === 'students' ? 'active' : '' }}" @if($attendanceTab === 'students') aria-current="page" @endif href="{{ route('attendance.index', $attendanceDates) }}"><i class="fas fa-user-graduate me-1" aria-hidden="true"></i>Students</a>
    @if(auth()->user()->isAdmin())
        @foreach(['teachers' => ['Teachers', 'fa-chalkboard-teacher'], 'staff' => ['Staff', 'fa-user-tie']] as $key => [$label, $icon])
            <a class="nav-link {{ $attendanceTab === $key ? 'active' : '' }}" @if($attendanceTab === $key) aria-current="page" @endif href="{{ route('attendance.index', $attendanceDates + ['tab' => $key]) }}"><i class="fas {{ $icon }} me-1" aria-hidden="true"></i>{{ $label }}</a>
        @endforeach
    @endif
</nav>
