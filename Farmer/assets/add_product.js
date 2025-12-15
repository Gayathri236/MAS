function previewImage(input){
        const preview = document.getElementById('imagePreview');
        if(input.files && input.files[0]){
            const reader = new FileReader();
            reader.onload = function(e){ 
                preview.innerHTML = `
                    <div style="text-align:center;">
                        <img src="${e.target.result}" alt="Preview" style="max-width:200px;border-radius:10px;margin:10px 0;">
                        <div style="color:#2a9d8f;font-weight:500;">Image preview</div>
                    </div>
                `; 
            };
            reader.readAsDataURL(input.files[0]);
        } else preview.innerHTML='';
    }
    
    document.getElementById('productForm').addEventListener('submit',function(e){
        const qty = parseFloat(document.getElementById('quantity').value);
        const price = parseFloat(document.getElementById('price').value);
        
        if(qty <= 0){ 
            alert('Quantity must be greater than 0'); 
            e.preventDefault(); 
        }
        if(price <= 0){ 
            alert('Price must be greater than 0'); 
            e.preventDefault(); 
        }
    });
    
    // Set max date to today for harvest date
    document.getElementById('harvest_date').max = new Date().toISOString().split('T')[0];
    
    // Add animation to form
    document.addEventListener('DOMContentLoaded', function() {
        const formCard = document.querySelector('.form-card');
        formCard.style.transform = 'translateY(20px)';
        formCard.style.opacity = '0';
        
        setTimeout(() => {
            formCard.style.transition = 'all 0.5s ease';
            formCard.style.transform = 'translateY(0)';
            formCard.style.opacity = '1';
        }, 100);
    });