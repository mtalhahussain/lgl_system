<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - {{ institute_name() }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar {
            background: white;
            min-height: calc(100vh - 56px);
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: #333;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 10px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover {
            background-color: #f8f9fa;
            color: #667eea;
            transform: translateX(5px);
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .dashboard-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
            transition: transform 0.3s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stat-card-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .stat-card-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }
        .stat-card-info {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
            color: white;
        }
        .stat-card-danger {
            background: linear-gradient(135deg, #dc3545 0%, #e91e63 100%);
            color: white;
        }
        .content-wrapper {
            min-height: calc(100vh - 56px);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6b4190 100%);
            transform: translateY(-1px);
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        .badge {
            font-size: 0.75em;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .alert {
            border-radius: 10px;
        }
        .pagination .page-link {
            color: #667eea;
        }
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
        }
    </style>
    @stack('styles')
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('admin.dashboard') }}">
                <i class="fas fa-graduation-cap me-2"></i>
                {{ institute_name() }}
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i>{{ auth()->user()->name }}
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
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
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse content-wrapper">
                <div class="position-sticky pt-3">
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
                        @elseif(auth()->user()->isAccountant())
                            <li class="nav-item">
                                <a class="nav-link {{ request()->is('accountant/dashboard') ? 'active' : '' }}" 
                                   href="{{ url('/accountant/dashboard') }}">
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
                                <a class="nav-link {{ request()->routeIs('student.attendance') ? 'active' : '' }}" 
                                   href="{{ route('student.attendance') }}">
                                    <i class="fas fa-clipboard-check me-2"></i>My Attendance
                                </a>
                            </li>
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
                        
                        @if(auth()->user()->isAdmin() || auth()->user()->isAccountant())
                        <!-- Fee Management -->
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('fees*') && !request()->is('fees/reports') ? 'active' : '' }}" 
                               href="{{ url('/fees') }}">
                                <i class="fas fa-money-bill me-2"></i>Fee Management
                            </a>
                        </li>
                        
                        <!-- Fee Installments -->
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('installments*') ? 'active' : '' }}" 
                               href="{{ route('installments.create') }}">
                                <i class="fas fa-calculator me-2"></i>Create Installments
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
                        
                        @if(auth()->user()->isAdmin() || auth()->user()->isAccountant())
                        <!-- Reports & Analytics -->
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('reports*') ? 'active' : '' }}" 
                               href="{{ url('/reports') }}">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </a>
                        </li>
                        @endif
                        
                        <hr class="my-2">
                        
                        @if(auth()->user()->isAdmin())
                        <!-- Settings -->
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('settings*') ? 'active' : '' }}" 
                               href="{{ route('settings.index') }}">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a>
                        </li>
                        @endif
                        
                        <!-- Help & Support -->
                        <li class="nav-item">
                            <a class="nav-link" href="javascript:void(0);" onclick="alert('Help & Support page coming soon!')">
                                <i class="fas fa-question-circle me-2"></i>Help & Support
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content-wrapper">
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

                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @stack('scripts')
</body>
</html>