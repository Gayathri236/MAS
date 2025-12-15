document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-success';
        alert.innerHTML = '<i class="fas fa-check-circle"></i> Profile updated successfully!';
        document.querySelector('.main h1').insertAdjacentElement('afterend', alert);
        
        // Remove after 5 seconds
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    }

    // Preview image before upload
    const profileImageInput = document.getElementById('image');
    const vehicleImageInput = document.getElementById('vehicle_image');
    const currentAvatar = document.querySelector('.current-avatar');
    
    if (profileImageInput) {
        profileImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    currentAvatar.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    }
    
    if (vehicleImageInput) {
        vehicleImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const currentVehicleImage = document.querySelector('.current-vehicle-image');
                    if (currentVehicleImage) {
                        currentVehicleImage.src = e.target.result;
                    } else {
                        const noImageDiv = document.querySelector('.no-image');
                        if (noImageDiv) {
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.alt = 'Vehicle Preview';
                            img.className = 'current-vehicle-image';
                            noImageDiv.innerHTML = '';
                            noImageDiv.appendChild(img);
                            noImageDiv.classList.remove('no-image');
                        }
                    }
                }
                reader.readAsDataURL(file);
            }
        });
    }

    // Add animation to form sections
    const formSections = document.querySelectorAll('.form-section');
    formSections.forEach((section, index) => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        setTimeout(() => {
            section.style.transition = 'all 0.4s ease';
            section.style.opacity = '1';
            section.style.transform = 'translateY(0)';
        }, index * 200);
    });
});