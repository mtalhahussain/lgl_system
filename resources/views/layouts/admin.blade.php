<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - {{ institute_name() }}</title>
    <script>try { if (localStorage.getItem('lgl:sidebar') === 'collapsed') document.documentElement.classList.add('sidebar-collapsed'); } catch (e) {}</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="app-body">
    <a class="skip-link" href="#mainContent">Skip to content</a>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-md navbar-light app-topbar">
        <div class="container-fluid">
            <button class="sidebar-collapse-btn d-none d-md-inline-flex" type="button" id="sidebarCollapse" aria-controls="appSidebar" aria-expanded="true" aria-label="Collapse sidebar" title="Collapse sidebar">
                <i class="fas fa-bars" aria-hidden="true"></i>
            </button>
            <a class="navbar-brand d-md-none" href="{{ route('dashboard') }}" title="{{ institute_name() }}">
                <span class="brand-mark"><i class="fas fa-graduation-cap" aria-hidden="true"></i></span>
                {{ institute_name() }}
            </a>
            <button class="navbar-toggler d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-expanded="false" aria-label="Toggle navigation menu">
                <i class="fas fa-bars" aria-hidden="true"></i>
            </button>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle account menu">
                <i class="fas fa-user-circle" aria-hidden="true"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <span class="user-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><span class="account-label">{{ auth()->user()->name }}<small>{{ ucfirst(auth()->user()->role) }} workspace</small></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="fas fa-user me-2"></i>Profile</a></li>
                            @if(auth()->user()->isAdmin())<li><a class="dropdown-item" href="{{ route('settings.index') }}"><i class="fas fa-cog me-2"></i>Settings</a></li>@endif
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="appSidebar" class="col-md-3 col-lg-2 d-md-block sidebar collapse content-wrapper" aria-label="Main navigation">
                <a class="sidebar-brand d-none d-md-flex" href="{{ route('dashboard') }}" title="{{ institute_name() }}">
                    <span class="brand-mark"><i class="fas fa-graduation-cap" aria-hidden="true"></i></span>
                    <span>{{ institute_name() }}</span>
                </a>
                <div class="position-sticky pt-3"><div class="sidebar-heading">WORKSPACE</div>
                    <ul class="nav flex-column">
                        <!-- Dashboard Links -->
                        @if(auth()->user()->isAdmin())
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" 
                                   href="{{ route('admin.dashboard') }}">
                                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                </a>
                            </li>
                        @elseif(auth()->user()->isTeacher())
                            <li class="nav-item">
                                <a class="nav-link {{ request()->is('teacher/dashboard') ? 'active' : '' }}" 
                                   href="{{ url('/teacher/dashboard') }}">
                                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                </a>
                            </li>
                        @elseif(auth()->user()->isStudent())
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('student.dashboard') ? 'active' : '' }}" 
                                   href="{{ route('student.dashboard') }}">
                                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('student.timetable') ? 'active' : '' }}"
                                   href="{{ route('student.timetable') }}">
                                    <i class="fas fa-calendar-week me-2"></i>My Timetable
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('student.attendance') ? 'active' : '' }}"
                                   href="{{ route('student.attendance') }}">
                                    <i class="fas fa-clipboard-check me-2"></i>My Attendance
                                </a>
                            </li>
                            <li class="nav-item"><a class="nav-link {{ request()->is('student/results*') ? 'active' : '' }}" href="{{ route('student.results') }}"><i class="fas fa-file-circle-check me-2" aria-hidden="true"></i>My Results</a></li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->is('students/' . auth()->id()) ? 'active' : '' }}" 
                                   href="{{ route('students.show', auth()->user()) }}">
                                    <i class="fas fa-user me-2"></i>My Profile
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->is('fees/student/' . auth()->id()) ? 'active' : '' }}" 
                                   href="{{ route('fees.student', auth()->user()) }}">
                                    <i class="fas fa-money-bill-wave me-2"></i>My Fees
                                </a>
                            </li>
                        @endif
                        
                        @if(auth()->user()->isAdmin() || auth()->user()->isTeacher())
                        <!-- Student Management -->
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('students*') ? 'active' : '' }}" 
                               href="{{ url('/students') }}">
                                <i class="fas fa-users me-2"></i>Students
                            </a>
                        </li>
                        @endif
                        
                        @if(auth()->user()->isAdmin())
                        <!-- Teacher Management -->
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('teachers*') ? 'active' : '' }}" 
                               href="{{ url('/teachers') }}">
                                <i class="fas fa-chalkboard-teacher me-2"></i>Teachers
                            </a>
                        </li>
                        @endif
                        
                        @if(auth()->user()->isAdmin() || auth()->user()->isTeacher())
                        <li class="nav-item"><a class="nav-link {{ request()->is('class-sessions*') ? 'active' : '' }}" href="{{ route('class-sessions.index') }}"><i class="fas fa-calendar-alt me-2" aria-hidden="true"></i>Class Sessions</a></li>
                        <!-- Course Management -->
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('courses*') ? 'active' : '' }}" 
                               href="{{ url('/courses') }}">
                                <i class="fas fa-book me-2"></i>{{ auth()->user()->isTeacher() ? 'View Courses' : 'Courses' }}
                            </a>
                        </li>
                        
                        <!-- Batch Management -->
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('batches*') ? 'active' : '' }}" 
                               href="{{ url('/batches') }}">
                                <i class="fas fa-layer-group me-2"></i>{{ auth()->user()->isTeacher() ? 'My Batches' : 'Batches' }}
                            </a>
                        </li>
                        @endif
                        
                        @if(auth()->user()->isAdmin())
                        <!-- Fee Management -->
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('fees*') && !request()->is('fees/reports') ? 'active' : '' }}" 
                               href="{{ url('/fees') }}">
                                <i class="fas fa-money-bill me-2"></i>Fee Management
                            </a>
                        </li>

                        @endif

                        @if(auth()->user()->isAdmin() || auth()->user()->isTeacher())
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('payroll*') ? 'active' : '' }}" href="{{ route('payroll.index') }}">
                                <i class="fas fa-hand-holding-usd me-2"></i>{{ auth()->user()->isTeacher() ? 'My Earnings' : 'Payroll' }}
                            </a>
                        </li>
                        @endif
                        
                        @if(auth()->user()->isAdmin() || auth()->user()->isTeacher())
                        <!-- Attendance Management -->
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('attendance*') ? 'active' : '' }}" 
                               href="{{ url('/attendance') }}">
                                <i class="fas fa-clipboard-check me-2"></i>Attendance
                            </a>
                        </li>
                        @endif
                        @if(auth()->user()->isAdmin())
                        <li class="nav-item"><a class="nav-link {{ request()->is('biometric/teachers') ? 'active' : '' }}" href="{{ route('biometric.teachers') }}"><i class="fas fa-user-clock me-2" aria-hidden="true"></i>Staff Attendance</a></li>
                        @endif

                        @if(auth()->user()->isAdmin())
                        <li class="nav-item"><a class="nav-link {{ request()->is('certificates*') ? 'active' : '' }}" href="{{ route('certificates.index') }}"><i class="fas fa-award me-2" aria-hidden="true"></i>Certificates</a></li>
                        @endif

                        @if(auth()->user()->isAdmin() || auth()->user()->isTeacher())
                        <li class="nav-item"><a class="nav-link {{ request()->is('exams*') ? 'active' : '' }}" href="{{ route('exams.index') }}"><i class="fas fa-file-pen me-2" aria-hidden="true"></i>Exams</a></li>
                        @endif

                        @if(auth()->user()->isAdmin())
                        <!-- Reports & Analytics -->
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('reports*') ? 'active' : '' }}" 
                               href="{{ url('/reports') }}">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </a>
                        </li>
                        @endif
                        
                        @if(auth()->user()->isAdmin())
                        <li class="sidebar-heading">MANAGEMENT</li>
                        <li class="nav-item"><a class="nav-link {{ request()->is('accounts*') ? 'active' : '' }}" href="{{ route('accounts.index') }}"><i class="fas fa-users-cog me-2" aria-hidden="true"></i>Users</a></li>
                        <!-- Settings -->
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('settings*', 'whatsapp*', 'biometric') ? 'active' : '' }}" 
                               href="{{ route('settings.index') }}">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a>
                        </li>
                        @endif
                        
                    </ul><div class="sidebar-footer"><span class="workspace-dot"></span> {{ ucfirst(auth()->user()->role) }} portal<small>{{ now()->format('l, d M Y') }}</small></div>
                </div>
            </nav>

            <!-- Main content -->
            <main id="mainContent" tabindex="-1" class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content-wrapper">
                <!-- Flash Messages -->
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if($back = \App\Support\BackLink::for(request()))
                    <a class="back-link d-print-none" href="{{ $back['url'] }}"><i class="fas fa-arrow-left" aria-hidden="true"></i>Back to {{ $back['label'] }}</a>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <dialog id="confirmDialog" class="confirm-dialog" aria-labelledby="confirmDialogTitle">
        <form method="dialog">
            <h2 id="confirmDialogTitle" class="h5">Please confirm</h2>
            <p data-confirm-message></p>
            <div class="confirm-actions">
                <button class="btn btn-outline-primary" value="cancel">Cancel</button>
                <button class="btn btn-primary" value="confirm">Confirm</button>
            </div>
        </form>
    </dialog>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @stack('scripts')
</body>
</html>
