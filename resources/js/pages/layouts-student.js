import { onReady } from '../page-helpers';

export function init(config) {
    onReady( function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        // Toggle sidebar
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');

            if (window.innerWidth > 768) {
                mainContent.classList.toggle('sidebar-open');
            } else {
                sidebarOverlay.classList.toggle('show');
            }
        });

        // Close sidebar when clicking overlay (mobile)
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebarOverlay.classList.remove('show');
                if (sidebar.classList.contains('show')) {
                    mainContent.classList.add('sidebar-open');
                }
            } else {
                mainContent.classList.remove('sidebar-open');
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Add loading state to buttons on form submission
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function() {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.classList.add('loading');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                }
            });
        });
    });

    // Utility function for currency formatting
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-PK', {
            style: 'currency',
            currency: 'PKR'
        }).format(amount);
    }

    // Show loading state
    function showLoading(element) {
        element.classList.add('loading');
    }

    // Hide loading state
    function hideLoading(element) {
        element.classList.remove('loading');
    }
}
