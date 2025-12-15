// Add animation to cards
document.addEventListener('DOMContentLoaded', function() {
    // Animate stats box
    const statBox = document.querySelector('.stat-box');
    if (statBox) {
        statBox.style.opacity = '0';
        statBox.style.transform = 'translateY(20px)';
        setTimeout(() => {
            statBox.style.transition = 'all 0.4s ease';
            statBox.style.opacity = '1';
            statBox.style.transform = 'translateY(0)';
        }, 100);
    }

    // Animate order cards
    const orderCards = document.querySelectorAll('.order-card');
    orderCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px) scale(0.95)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0) scale(1)';
        }, index * 100 + 200);
    });

    // Add hover effect to order cards
    orderCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
});
