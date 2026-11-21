import { onReady } from '../page-helpers';

export function init(config) {
    // Simple chart for demonstration
    onReady( function() {
        const ctx = document.getElementById('revenueChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Revenue (' + config.dataset.currency + ')',
                        data: [35000, 42000, 38000, 45000, 41000, 47000],
                        borderColor: 'rgb(102, 126, 234)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return config.dataset.currency + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
    });
}
