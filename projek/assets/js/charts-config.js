// Konfigurasi warna dan style chart yang konsisten
const ChartConfig = {
    colors: {
        primary: '#2596be',
        secondary: '#6c757d',
        success: '#28a745',
        danger: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8',
        light: '#f8f9fa',
        dark: '#343a40',
        creative: '#2596be',
        umkm: '#28a745'
    },
    
    commonOptions: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true
                }
            }
        }
    },

    formatCurrency: (value) => {
        return 'Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    },
    
    formatNumber: (value) => {
        return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }
};

const ChartUtils = {
    generateDemoData: (count, min = 10, max = 100) => {
        return Array.from({length: count}, () => 
            Math.floor(Math.random() * (max - min + 1)) + min
        );
    },
    
    getStatusColor: (status) => {
        const colors = {
            'open': ChartConfig.colors.info,
            'draft': ChartConfig.colors.secondary,
            'in_progress': ChartConfig.colors.warning,
            'completed': ChartConfig.colors.success,
            'cancelled': ChartConfig.colors.danger,
            'active': ChartConfig.colors.success,
            'pending': ChartConfig.colors.warning,
            'paid': ChartConfig.colors.success,
            'failed': ChartConfig.colors.danger
        };
        return colors[status] || ChartConfig.colors.secondary;
    },
    
    // Initialize chart container
    initChartContainer: (canvasId) => {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.warn(`Canvas element with id '${canvasId}' not found`);
            return null;
        }
        return canvas;
    }
};