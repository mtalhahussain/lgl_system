{{-- Attendance section tabs: Students (class sessions) + Teachers & Staff (biometric). --}}
<ul class="nav nav-pills gap-2 mb-4" role="tablist">
    <li class="nav-item">
        <a class="nav-link {{ request()->is('attendance*') ? 'active' : '' }}"
           href="{{ route('attendance.index') }}">
            <i class="fas fa-user-graduate me-1" aria-hidden="true"></i>Students
        </a>
    </li>
    @if(auth()->user()->isAdmin())
    <li class="nav-item">
        <a class="nav-link {{ request()->is('biometric/teachers') ? 'active' : '' }}"
           href="{{ route('biometric.teachers') }}">
            <i class="fas fa-user-tie me-1" aria-hidden="true"></i>Teachers &amp; Staff
        </a>
    </li>
    @endif
</ul>
