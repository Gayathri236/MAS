 // Form submission with loading indicator
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loginBtn = loginForm.querySelector('.login-btn');
            const loadingIcon = loginBtn.querySelector('.loading');
            const btnText = loginBtn.querySelector('span');

            loginForm.addEventListener('submit', function(e) {
                // Show loading indicator
                loadingIcon.classList.add('active');
                btnText.textContent = 'Logging in...';
                loginBtn.disabled = true;
                
                // Simulate network delay for better UX
                setTimeout(() => {
                    // Form will submit normally
                }, 500);
            });

            // Add animation to form inputs
            const inputs = document.querySelectorAll('.input-group input, .input-group select');
            inputs.forEach(input => {
                // Add focus animation
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'translateY(-2px)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'translateY(0)';
                });
                
                // Add value check for label animation (if we had floating labels)
                if (input.value) {
                    input.parentElement.classList.add('has-value');
                }
                
                input.addEventListener('input', function() {
                    if (this.value) {
                        this.parentElement.classList.add('has-value');
                    } else {
                        this.parentElement.classList.remove('has-value');
                    }
                });
            });

            // Add page load animation
            const loginBox = document.querySelector('.login-box');
            loginBox.style.opacity = '0';
            loginBox.style.transform = 'scale(0.95)';
            
            setTimeout(() => {
                loginBox.style.transition = 'all 0.3s ease';
                loginBox.style.opacity = '1';
                loginBox.style.transform = 'scale(1)';
            }, 100);
        });

        // Handle back button/refresh to prevent resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }