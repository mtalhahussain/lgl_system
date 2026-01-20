<nav class="nav flex-column">
    <a href="{{ route('student.dashboard') }}" class="nav-link {{ request()->routeIs('student.dashboard') ? 'active' : '' }}">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
    </a>
    
    <a href="{{ route('student.attendance') }}" class="nav-link {{ request()->routeIs('student.attendance') ? 'active' : '' }}">
        <i class="fas fa-clipboard-check"></i>
        <span>My Attendance</span>
    </a>
    
    <a href="{{ route('fees.student', auth()->user()) }}" class="nav-link {{ request()->routeIs('fees.student') ? 'active' : '' }}">
        <i class="fas fa-money-bill-wave"></i>
        <span>My Fees</span>
    </a>
    
    <a href="{{ route('students.show', auth()->user()) }}" class="nav-link {{ request()->routeIs('students.show') ? 'active' : '' }}">
        <i class="fas fa-user"></i>
        <span>My Profile</span>
    </a>
    
    <div class="nav-divider my-3"></div>
    
    <div class="nav-header px-3 mb-2">
        <small class="text-white-50 text-uppercase fw-bold">Quick Actions</small>
    </div>
    
    <a href="#" class="nav-link" onclick="window.print(); return false;">
        <i class="fas fa-print"></i>
        <span>Print Schedule</span>
    </a>
    
    <a href="#" class="nav-link" onclick="showHelpModal(); return false;">
        <i class="fas fa-question-circle"></i>
        <span>Help & Support</span>
    </a>
    
    <div class="nav-divider my-3"></div>
    
    <form action="{{ route('logout') }}" method="POST" class="px-3">
        @csrf
        <button type="submit" class="nav-link w-100 text-start border-0 bg-transparent text-danger">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </button>
    </form>
</nav>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="helpModalLabel">
                    <i class="fas fa-question-circle me-2"></i>Help & Support
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-book me-2 text-primary"></i>Quick Guide</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-chevron-right me-2 text-muted"></i>Check your attendance regularly</li>
                            <li><i class="fas fa-chevron-right me-2 text-muted"></i>Pay fees before due date</li>
                            <li><i class="fas fa-chevron-right me-2 text-muted"></i>Update profile information</li>
                            <li><i class="fas fa-chevron-right me-2 text-muted"></i>Join online classes on time</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-phone me-2 text-success"></i>Contact Support</h6>
                        <p class="small text-muted">
                            <strong>Office:</strong> +92-XXX-XXXXXXX<br>
                            <strong>Email:</strong> support@lglsystem.com<br>
                            <strong>Hours:</strong> 9:00 AM - 5:00 PM
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.nav-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.1);
    margin: 15px 20px;
}

.nav-header {
    font-size: 0.75rem;
    letter-spacing: 0.5px;
}

.sidebar .nav-link {
    position: relative;
    overflow: hidden;
}

.sidebar .nav-link::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.sidebar .nav-link:hover::before {
    left: 100%;
}

.sidebar .nav-link.active {
    background-color: rgba(255, 255, 255, 0.25);
    border-left: 3px solid #fff;
}

.sidebar .nav-link.active::after {
    content: '';
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 6px;
    height: 6px;
    background-color: #fff;
    border-radius: 50%;
}
</style>

<script>
function showHelpModal() {
    const modal = new bootstrap.Modal(document.getElementById('helpModal'));
    modal.show();
}
</script>