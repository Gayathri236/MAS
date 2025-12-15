        // Form submission with loading indicator
        document.addEventListener('DOMContentLoaded', function() {
            const registrationForm = document.getElementById('registrationForm');
            const registerBtn = registrationForm.querySelector('.register-btn');
            const loadingIcon = registerBtn.querySelector('.loading');
            const btnText = registerBtn.querySelector('span');
            const passwordInput = document.getElementById('password');
            const passwordStrength = document.getElementById('passwordStrength');
            const imageUpload = document.getElementById('imageUpload');
            const imagePreview = document.getElementById('imagePreview');

            // Password strength checker
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                // Length check
                if (password.length >= 8) strength += 25;
                
                // Contains numbers
                if (/\d/.test(password)) strength += 25;
                
                // Contains lowercase
                if (/[a-z]/.test(password)) strength += 25;
                
                // Contains uppercase
                if (/[A-Z]/.test(password)) strength += 25;
                
                // Update strength meter
                passwordStrength.style.width = strength + '%';
                
                // Update color based on strength
                if (strength < 50) {
                    passwordStrength.className = 'password-strength-meter strength-weak';
                } else if (strength < 75) {
                    passwordStrength.className = 'password-strength-meter strength-medium';
                } else {
                    passwordStrength.className = 'password-strength-meter strength-strong';
                }
            });

            // Image preview
            imageUpload.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.innerHTML = `<img src="${e.target.result}" alt="Image Preview">`;
                    }
                    reader.readAsDataURL(file);
                } else {
                    imagePreview.innerHTML = '';
                }
            });

            registrationForm.addEventListener('submit', function(e) {
                // Basic form validation
                const password = passwordInput.value;
                if (password.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long!');
                    return;
                }

                // Show loading indicator
                loadingIcon.classList.add('active');
                btnText.textContent = 'Creating Account...';
                registerBtn.disabled = true;
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
            });

            // Add page load animation
            const registrationBox = document.querySelector('.registration-box');
            registrationBox.style.opacity = '0';
            registrationBox.style.transform = 'scale(0.95)';
            
            setTimeout(() => {
                registrationBox.style.transition = 'all 0.3s ease';
                registrationBox.style.opacity = '1';
                registrationBox.style.transform = 'scale(1)';
            }, 100);
        });

        // Handle back button/refresh to prevent resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }