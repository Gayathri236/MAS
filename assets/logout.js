  // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            const logoutBtn = document.querySelector('.btn-logout');
            const cancelBtn = document.querySelector('.btn-cancel');
            
            // Add click confirmation for logout
            logoutBtn.addEventListener('click', function(e) {
                if (!confirm('Are you absolutely sure you want to logout?')) {
                    e.preventDefault();
                }
            });
            
            // Add animation to buttons on hover
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Add page load animation
            const container = document.querySelector('.logout-container');
            container.style.opacity = '0';
            container.style.transform = 'scale(0.9)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.3s ease';
                container.style.opacity = '1';
                container.style.transform = 'scale(1)';
            }, 100);
        });