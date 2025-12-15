function updateMap() {
    const locationInput = document.getElementById('location');
    const mapFrame = document.getElementById('mapFrame');
    const mapLink = document.getElementById('mapLink');
    
    if (locationInput.value.trim()) {
        const encodedLocation = encodeURIComponent(locationInput.value.trim());
        mapFrame.src = `https://www.google.com/maps?q=${encodedLocation}&output=embed&zoom=12`;
        mapLink.href = `https://www.google.com/maps/search/?api=1&query=${encodedLocation}`;
    }
}

function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.innerHTML = `
                <div style="text-align: center;">
                    <h4 style="color: #2a9d8f; margin-bottom: 10px;">Image Preview:</h4>
                    <img src="${e.target.result}" 
                         alt="Preview" 
                         style="max-width: 150px; max-height: 150px; border-radius: 10px; border: 2px solid #e0e0e0;">
                </div>
            `;
        };
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.innerHTML = '';
    }
}

// Add animation to form
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.profile-form');
    form.style.opacity = '0';
    form.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        form.style.transition = 'all 0.5s ease';
        form.style.opacity = '1';
        form.style.transform = 'translateY(0)';
    }, 100);
    
    // Check for success message to scroll to it
    const successMessage = document.querySelector('.success-message');
    if (successMessage) {
        successMessage.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});