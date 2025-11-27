class AdminCharts {
    constructor(data) {
        this.data = data;
        this.init();
    }
    
    init() {
        this.renderProjectStatusChart();
        this.renderUserDistributionChart();
        this.renderMonthlyRevenueChart();
        this.renderTopSkillsChart();
        this.renderProjectCategoriesChart();
        this.renderMessageActivityChart();
        this.renderDisputeStatusChart();
        this.renderRatingVsProjectsChart();
    }
    
    renderProjectStatusChart() {
        const canvas = ChartUtils.initChartContainer('projectStatusChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: this.data.projectStatus?.map(item => this.formatStatusLabel(item.status)) || [],
                datasets: [{
                    label: 'Jumlah Proyek',
                    data: this.data.projectStatus?.map(item => item.count) || [],
                    backgroundColor: this.data.projectStatus?.map(item => 
                        ChartUtils.getStatusColor(item.status)
                    ) || ChartConfig.colors.primary,
                    borderWidth: 0
                }]
            },
            options: {
                ...ChartConfig.commonOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
    
    renderUserDistributionChart() {
        const canvas = ChartUtils.initChartContainer('userDistributionChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: this.data.userDistribution?.map(item => this.formatUserType(item.user_type)) || [],
                datasets: [{
                    data: this.data.userDistribution?.map(item => item.count) || [],
                    backgroundColor: [
                        ChartConfig.colors.creative,
                        ChartConfig.colors.umkm
                    ],
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
    
    renderMonthlyRevenueChart() {
        const canvas = ChartUtils.initChartContainer('monthlyRevenueChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: this.data.monthlyRevenue?.labels || ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'],
                datasets: [{
                    label: 'Pendapatan',
                    data: this.data.monthlyRevenue?.data || ChartUtils.generateDemoData(6, 50, 200),
                    borderColor: ChartConfig.colors.success,
                    backgroundColor: ChartConfig.colors.success + '20',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                ...ChartConfig.commonOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + (value / 1000000).toFixed(1) + ' jt';
                            }
                        }
                    }
                }
            }
        });
    }
    
    renderTopSkillsChart() {
        const canvas = ChartUtils.initChartContainer('topSkillsChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: this.data.topSkills?.labels || ['Web Dev', 'Design', 'Marketing', 'Content', 'Video'],
                datasets: [{
                    label: 'Frekuensi',
                    data: this.data.topSkills?.data || ChartUtils.generateDemoData(5, 30, 100),
                    backgroundColor: ChartConfig.colors.info
                }]
            },
            options: {
                ...ChartConfig.commonOptions,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true
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
    
    renderProjectCategoriesChart() {
        const canvas = ChartUtils.initChartContainer('projectCategoriesChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: this.data.projectCategories?.labels || ['Website', 'Logo', 'Social Media', 'Video'],
                datasets: [{
                    data: this.data.projectCategories?.data || ChartUtils.generateDemoData(4, 10, 50),
                    backgroundColor: [
                        ChartConfig.colors.primary,
                        ChartConfig.colors.info,
                        ChartConfig.colors.warning,
                        ChartConfig.colors.success
                    ]
                }]
            },
            options: ChartConfig.commonOptions
        });
    }
    
    renderMessageActivityChart() {
        const canvas = ChartUtils.initChartContainer('messageActivityChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: this.data.messageActivity?.labels || ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
                datasets: [{
                    label: 'Jumlah Pesan',
                    data: this.data.messageActivity?.data || ChartUtils.generateDemoData(7, 20, 80),
                    borderColor: ChartConfig.colors.warning,
                    backgroundColor: ChartConfig.colors.warning + '20',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: ChartConfig.commonOptions
        });
    }
    
    renderDisputeStatusChart() {
        const canvas = ChartUtils.initChartContainer('disputeStatusChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: this.data.disputeStatus?.labels || ['Open', 'In Review', 'Resolved', 'Cancelled'],
                datasets: [{
                    data: this.data.disputeStatus?.data || ChartUtils.generateDemoData(4, 5, 25),
                    backgroundColor: [
                        ChartConfig.colors.warning,
                        ChartConfig.colors.info,
                        ChartConfig.colors.success,
                        ChartConfig.colors.danger
                    ]
                }]
            },
            options: ChartConfig.commonOptions
        });
    }
    
    renderRatingVsProjectsChart() {
        const canvas = ChartUtils.initChartContainer('ratingVsProjectsChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'Creative Workers',
                    data: this.data.ratingVsProjects?.data || [
                        {x: 4.2, y: 5}, {x: 4.5, y: 8}, {x: 4.8, y: 15}, 
                        {x: 4.3, y: 7}, {x: 4.9, y: 22}, {x: 4.6, y: 12}
                    ],
                    backgroundColor: ChartConfig.colors.primary,
                    pointRadius: 8
                }]
            },
            options: {
                ...ChartConfig.commonOptions,
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Rating'
                        },
                        min: 4,
                        max: 5
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Proyek Selesai'
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    formatStatusLabel(status) {
        const labels = {
            'open': 'Open',
            'draft': 'Draft',
            'in_progress': 'In Progress',
            'completed': 'Completed',
            'cancelled': 'Cancelled'
        };
        return labels[status] || status;
    }
    
    formatUserType(userType) {
        const types = {
            'creative': 'Creative Worker',
            'umkm': 'UMKM'
        };
        return types[userType] || userType;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof adminChartData !== 'undefined') {
        new AdminCharts(adminChartData);
    } else {
        console.warn('Admin chart data not found. Using demo data.');
        new AdminCharts({});
    }
});