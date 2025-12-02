document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('#statusFilters .nav-link');
    const searchInput = document.getElementById('searchInput');
    const proposalItems = document.querySelectorAll('.proposal-item');
    const noResultsMsg = document.getElementById('noResults');

    let currentFilter = 'all';
    let currentSearch = '';

    // Fungsi Utama Filter
    function filterProposals() {
        let visibleCount = 0;

        proposalItems.forEach(item => {
            const status = item.getAttribute('data-status');
            const title = item.getAttribute('data-title');
            
            // Logika cek status (tab)
            const matchesFilter = (currentFilter === 'all') || (status === currentFilter);
            
            // Logika cek search (text)
            const matchesSearch = title.includes(currentSearch);

            if (matchesFilter && matchesSearch) {
                item.classList.remove('d-none');
                
                // Reset animasi agar efek muncul kembali main saat ganti filter
                item.style.animation = 'none';
                item.offsetHeight; /* trigger reflow/repaint */
                item.style.animation = 'fadeInUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards';
                
                visibleCount++;
            } else {
                item.classList.add('d-none');
            }
        });

        // Toggle Empty State Message
        if (visibleCount === 0 && proposalItems.length > 0) {
            noResultsMsg.classList.remove('d-none');
        } else {
            noResultsMsg.classList.add('d-none');
        }
    }

    // Event Listener: Filter Tabs
    filterButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Update UI Active State
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            // Update Logic
            currentFilter = this.getAttribute('data-filter');
            filterProposals();
        });
    });

    // Event Listener: Search Input
    searchInput.addEventListener('input', function(e) {
        currentSearch = e.target.value.toLowerCase().trim();
        filterProposals();
    });
});