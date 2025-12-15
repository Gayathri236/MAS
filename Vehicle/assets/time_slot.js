// Add animation to cards
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.slot-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.4s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Add hover effect to cards
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // Add confirmation to transport buttons
    const startButtons = document.querySelectorAll('button[name="start_transport"]');
    const endButtons = document.querySelectorAll('button[name="end_transport"]');

    startButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to start the transport for this order?')) {
                e.preventDefault();
            }
        });
    });

    endButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to mark this order as delivered?')) {
                e.preventDefault();
            }
        });
    });
});