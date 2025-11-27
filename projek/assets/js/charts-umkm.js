class UMKMCharts {
    constructor(data) {
        this.data = data;
        this.init();
    }
    
    init() {
        this.renderMyProjectsChart();
        this.renderProjectBudgetChart();
        this.renderContractTimelineChart();
        this.renderSpendingByCategoryChart();
    }
    
    renderMyProjectsChart() {
        const canvas = ChartUtils.initChartContainer('myProjectsChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: this.data.projectStatus?.map(item => this.formatStatusLabel(item.status)) || [],
                datasets: [{
                    data: this.data.projectStatus?.map(item => item.count) || [],
                    backgroundColor: this.data.projectStatus?.map(item => 
                        ChartUtils.getStatusColor(item.status)
                    ) || [ChartConfig.colors.primary],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                ...ChartConfig.commonOptions,
                cutout: '60%'
            }
        });
    }
    
    renderProjectBudgetChart() {
        const canvas = ChartUtils.initChartContainer('projectBudgetChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: this.data.projectBudgets?.labels || ['Proyek A', 'Proyek B', 'Proyek C', 'Proyek D'],
                datasets: [{
                    label: 'Budget (Rp)',
                    data: this.data.projectBudgets?.data || ChartUtils.generateDemoData(4, 50, 500),
                    backgroundColor: ChartConfig.colors.warning,
                    borderWidth: 0
                }]
            },
            options: {
                ...ChartConfig.commonOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + (value / 100000).toFixed(0) + ' rb';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
    
    renderContractTimelineChart() {
        const canvas = ChartUtils.initChartContainer('contractTimelineChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: this.data.contractTimeline?.labels || ['M1', 'M2', 'M3', 'M4', 'M5', 'M6'],
                datasets: [{
                    label: 'Kontrak Aktif',
                    data: this.data.contractTimeline?.data || ChartUtils.generateDemoData(6, 1, 10),
                    borderColor: ChartConfig.colors.success,
                    backgroundColor: ChartConfig.colors.success + '20',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: ChartConfig.commonOptions
        });
    }
    
    renderSpendingByCategoryChart() {
        const canvas = ChartUtils.initChartContainer('spendingByCategoryChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: this.data.spendingByCategory?.labels || ['Design', 'Development', 'Marketing', 'Content'],
                datasets: [{
                    data: this.data.spendingByCategory?.data || ChartUtils.generateDemoData(4, 100, 1000),
                    backgroundColor: [
                        ChartConfig.colors.primary,
                        ChartConfig.colors.info,
                        ChartConfig.colors.warning,
                        ChartConfig.colors.success
                    ]
                }]
            },
            options: {
                ...ChartConfig.commonOptions,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw;
                                return ChartConfig.formatCurrency(value * 1000);
                            }
                        }
                    }
                }
            }
        });
    }
    
    formatStatusLabel(status) {
        const labels = {
            'draft': 'Draft',
            'open': 'Open',
            'in_progress': 'In Progress',
            'completed': 'Selesai',
            'cancelled': 'Dibatalkan'
        };
        return labels[status] || status;
    }
}

// Initialize UMKM charts
document.addEventListener('DOMContentLoaded', function() {
    if (typeof umkmChartData !== 'undefined') {
        new UMKMCharts(umkmChartData);
    } else {
        console.warn('UMKM chart data not found. Using demo data.');
        new UMKMCharts({});
    }
});