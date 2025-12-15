function filterOrders(status) {
        const orders = document.querySelectorAll('.order-card');
        orders.forEach(order => {
            if (status === 'all' || order.getAttribute('data-status') === status) {
                order.style.display = 'flex';
            } else {
                order.style.display = 'none';
            }
        });
    }
    
document.addEventListener('DOMContentLoaded', function() {
        const orderCards = document.querySelectorAll('.order-card');
        orderCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.4s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
        
const rejectLinks = document.querySelectorAll('.btn-reject');
        rejectLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to reject this order? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
        

const disabledAcceptBtns = document.querySelectorAll('.btn-disabled[title*="negative"]');
        disabledAcceptBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                alert('Cannot accept order: Stock would go negative. Please update your inventory first.');
            });
        });
    });