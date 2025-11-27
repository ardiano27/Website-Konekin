class ReviewSystem {
    constructor() {
        this.initEventListeners();
    }
    
    initEventListeners() {
        // Category rating changes
        document.querySelectorAll('.stars-rating input').forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.calculateOverallRating();
                this.updateRatingText();
            });
        });
        
        // Character counters
        document.querySelectorAll('textarea[maxlength]').forEach(textarea => {
            textarea.addEventListener('input', this.updateCharCount.bind(this));
        });
    }
    
    calculateOverallRating() {
        const categories = document.querySelectorAll('.rating-category-card');
        let total = 0;
        let count = 0;
        
        categories.forEach(category => {
            const selectedRating = category.querySelector('input:checked');
            if (selectedRating) {
                total += parseInt(selectedRating.value);
                count++;
            }
        });
        
        if (count > 0) {
            const overallRating = Math.round(total / count);
            this.setOverallRating(overallRating);
        }
    }
    
    setOverallRating(rating) {
        const overallInput = document.querySelector(`input[name="overall_rating"][value="${rating}"]`);
        if (overallInput) {
            overallInput.checked = true;
        }
    }
    
    updateRatingText() {
        const rating = document.querySelector('input[name="overall_rating"]:checked');
        const ratingText = document.getElementById('ratingText');
        
        if (rating) {
            const texts = {
                1: 'Buruk',
                2: 'Cukup',
                3: 'Baik', 
                4: 'Sangat Baik',
                5: 'Luar Biasa'
            };
            ratingText.textContent = texts[rating.value] || '';
        }
    }
    
    updateCharCount(e) {
        const textarea = e.target;
        const maxLength = parseInt(textarea.getAttribute('maxlength'));
        const currentLength = textarea.value.length;
        const charCountId = textarea.name === 'public_review' ? 'publicCharCount' : 'privateCharCount';
        const charCountElement = document.getElementById(charCountId);
        
        if (charCountElement) {
            charCountElement.textContent = currentLength;
            
            if (currentLength > maxLength * 0.9) {
                charCountElement.style.color = '#dc3545';
            } else {
                charCountElement.style.color = '#6c757d';
            }
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new ReviewSystem();
});