class CreativeWorkerCharts {
    constructor(data) {
        this.data = data;
        this.init();
    }
    
    init() {
        this.renderProjectCategoriesChart();
        this.renderPortfolioGrowthChart();
        this.renderEarningsChart();
        this.renderSkillsChart();
    }
    
    renderProjectCategoriesChart() {
        const canvas = ChartUtils.initChartContainer('projectCategoriesChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: this.data.projectCategories?.map(item => this.formatCategory(item.category)) || [],
                datasets: [{
                    label: 'Proyek Tersedia',
                    data: this.data.projectCategories?.map(item => item.count) || [],
                    backgroundColor: ChartConfig.colors.creative,
                    borderWidth: 0
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
    
    renderPortfolioGrowthChart() {
        const canvas = ChartUtils.initChartContainer('portfolioGrowthChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: this.data.portfolioGrowth?.labels || ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'],
                datasets: [{
                    label: 'Total Portfolio',
                    data: this.data.portfolioGrowth?.data || [1, 2, 3, 5, 7, 10],
                    borderColor: ChartConfig.colors.info,
                    backgroundColor: ChartConfig.colors.info + '20',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: ChartConfig.commonOptions
        });
    }
    
    renderEarningsChart() {
        const canvas = ChartUtils.initChartContainer('earningsChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: this.data.earnings?.labels || ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'],
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: this.data.earnings?.data || ChartUtils.generateDemoData(6, 50, 500),
                    backgroundColor: ChartConfig.colors.success,
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
                }
            }
        });
    }
    
    renderSkillsChart() {
        const canvas = ChartUtils.initChartContainer('skillsChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'radar',
            data: {
                labels: this.data.skills?.labels || ['Web Dev', 'Design', 'UI/UX', 'Marketing', 'Content', 'Video'],
                datasets: [{
                    label: 'Tingkat Kemampuan',
                    data: this.data.skills?.data || [85, 70, 90, 60, 75, 65],
                    backgroundColor: ChartConfig.colors.primary + '40',
                    borderColor: ChartConfig.colors.primary,
                    pointBackgroundColor: ChartConfig.colors.primary,
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: ChartConfig.colors.primary
                }]
            },
            options: {
                ...ChartConfig.commonOptions,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            stepSize: 20
                        }
                    }
                }
            }
        });
    }
    
    formatCategory(category) {
        const categories = {
            'website': 'Website',
            'logo': 'Logo Design',
            'social_media': 'Social Media',
            'video': 'Video Production',
            'content': 'Content Writing',
            'marketing': 'Digital Marketing',
            'other': 'Lainnya'
        };
        return categories[category] || category;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof cwChartData !== 'undefined') {
        new CreativeWorkerCharts(cwChartData);
    } else {
        console.warn('Creative Worker chart data not found. Using demo data.');
        new CreativeWorkerCharts({});
    }
});