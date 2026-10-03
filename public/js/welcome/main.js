/**
 * Main Entry Point
 * Initializes all modules when DOM is ready
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Welcome page initialized');
    
    // Initialize all modules in correct order
    if (window.Toast) {
        window.Toast.init();
    }
    
    if (window.TenantManager) {
        window.TenantManager.init();
    }
    
    if (window.RegistrationManager) {
        window.RegistrationManager.init();
    }
    
    if (window.FormHandler) {
        window.FormHandler.init();
    }
    
    if (window.UIController) {
        window.UIController.init();
    }
    
    // Initialize testimonial functionality
    initializeTestimonials();
    
    // Additional initialization for elements that need special handling
    initializePhoneHints();
    initializeRadioStyles();
});

function initializePhoneHints() {
    // Add any additional phone hint initialization here
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            // Remove any non-digit characters except +
            let value = this.value.replace(/[^\d+]/g, '');
            this.value = value;
        });
    });
}

function initializeRadioStyles() {
    // Ensure radio button styles are correct on page load
    const radioOptions = document.querySelectorAll('.modern-radio-option');
    radioOptions.forEach(option => {
        const radio = option.querySelector('input[type="radio"]');
        if (radio && radio.checked) {
            option.classList.add('modern-radio-option--checked');
        }
    });
}

/**
 * Testimonial Functionality
 * Handles testimonial submission and display
 */
function initializeTestimonials() {
    // Load testimonials on page load
    loadTestimonials();
    
    // Setup testimonial modal
    setupTestimonialModal();
    
    // Setup testimonial form submission
    setupTestimonialForm();
    
    // Setup rating stars
    setupRatingStars();
    
    // Setup avatar preview
    setupAvatarPreview();
}

/**
 * Load testimonials from API
 */
async function loadTestimonials() {
    const container = document.getElementById('testimonialsContainer');
    if (!container) return;
    
    try {
        const response = await fetch('/api/testimonials?limit=6');
        const data = await response.json();
        
        if (data.success && data.testimonials.length > 0) {
            container.innerHTML = data.testimonials.map(testimonial => `
                <div class="testimonial-card">
                    <i class="fas fa-quote-left"></i>
                    <div class="testimonial-rating">
                        ${getStarRating(testimonial.rating)}
                    </div>
                    <p class="testimonial-content">"${escapeHtml(testimonial.content.substring(0, 200))}${testimonial.content.length > 200 ? '...' : ''}"</p>
                    <div class="testimonial-author">
                        <img src="${testimonial.avatar_url}" alt="${escapeHtml(testimonial.name)}" class="author-avatar" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(testimonial.name)}&background=2c76c9&color=fff&size=100'">
                        <div class="author-info">
                            <h4>${escapeHtml(testimonial.name)}</h4>
                            <small>${escapeHtml(testimonial.role || 'Customer')}</small>
                            ${testimonial.property_location ? `<small>📍 ${escapeHtml(testimonial.property_location)}</small>` : ''}
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = `
                <div class="benefit-item">
                    <i class="fas fa-comments"></i>
                    <h3>Be the First to Review</h3>
                    <p>No testimonials yet. Share your experience with us!</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Failed to load testimonials:', error);
        container.innerHTML = `
            <div class="benefit-item">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Unable to Load</h3>
                <p>Please refresh the page to see testimonials.</p>
            </div>
        `;
    }
}

/**
 * Get star rating HTML
 */
function getStarRating(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        stars += i <= rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
    }
    return stars;
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Setup testimonial modal open/close functionality
 */
function setupTestimonialModal() {
    const shareBtns = document.querySelectorAll('#shareExperienceBtn, #shareExperienceBtnFooter');
    const testimonialModal = document.getElementById('testimonialModal');
    const closeTestimonialBtn = document.getElementById('closeTestimonialModalBtn');
    const cancelTestimonialBtn = document.getElementById('cancelTestimonialBtn');
    
    function openTestimonialModal(e) {
        if (e) e.preventDefault();
        if (testimonialModal) {
            testimonialModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    
    shareBtns.forEach(btn => {
        if (btn) btn.addEventListener('click', openTestimonialModal);
    });
    
    function closeTestimonialModal() {
        if (testimonialModal) {
            testimonialModal.classList.remove('active');
            document.body.style.overflow = '';
            const form = document.getElementById('testimonialForm');
            if (form) form.reset();
            
            // Reset rating stars
            const ratingInput = document.getElementById('testimonial_rating');
            const ratingStars = document.querySelectorAll('.rating-input i');
            if (ratingInput) ratingInput.value = 5;
            if (ratingStars.length) {
                ratingStars.forEach((s, index) => {
                    if (index < 5) {
                        s.classList.remove('far');
                        s.classList.add('fas');
                        s.classList.add('active');
                    }
                });
            }
            
            // Reset avatar preview
            const avatarPreview = document.getElementById('testimonialAvatarPreview');
            const avatarInput = document.getElementById('testimonial_avatar');
            if (avatarPreview) avatarPreview.classList.add('hidden');
            if (avatarInput) avatarInput.value = '';
        }
    }
    
    if (closeTestimonialBtn) closeTestimonialBtn.addEventListener('click', closeTestimonialModal);
    if (cancelTestimonialBtn) cancelTestimonialBtn.addEventListener('click', closeTestimonialModal);
    
    if (testimonialModal) {
        testimonialModal.addEventListener('click', (e) => {
            if (e.target === testimonialModal) closeTestimonialModal();
        });
    }
    
    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && testimonialModal && testimonialModal.classList.contains('active')) {
            closeTestimonialModal();
        }
    });
}

/**
 * Setup testimonial form submission
 */
function setupTestimonialForm() {
    const testimonialForm = document.getElementById('testimonialForm');
    if (!testimonialForm) return;
    
    testimonialForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const submitBtn = document.getElementById('submitTestimonialBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner"></span> Submitting...';
        
        const formData = new FormData(testimonialForm);
        
        try {
            const response = await fetch('/api/testimonials', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                if (window.Toast) Toast.show(data.message, 'success');
                closeTestimonialModal();
                testimonialForm.reset();
                
                // Reset rating stars
                const ratingInput = document.getElementById('testimonial_rating');
                const ratingStars = document.querySelectorAll('.rating-input i');
                if (ratingInput) ratingInput.value = 5;
                if (ratingStars.length) {
                    ratingStars.forEach((s, index) => {
                        if (index < 5) {
                            s.classList.remove('far');
                            s.classList.add('fas');
                            s.classList.add('active');
                        }
                    });
                }
                
                // Reload testimonials to show new one (if approved immediately)
                setTimeout(() => {
                    loadTestimonials();
                }, 2000);
            } else {
                let errorMessage = 'Please fix the following errors:';
                if (data.errors) {
                    for (let key in data.errors) {
                        errorMessage += `\n- ${data.errors[key][0]}`;
                    }
                } else if (data.message) {
                    errorMessage = data.message;
                }
                if (window.Toast) Toast.show(errorMessage, 'error');
            }
        } catch (error) {
            console.error('Error submitting testimonial:', error);
            if (window.Toast) Toast.show('Failed to submit testimonial. Please try again.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}

/**
 * Setup rating stars interaction
 */
function setupRatingStars() {
    const ratingStars = document.querySelectorAll('.rating-input i');
    const ratingInput = document.getElementById('testimonial_rating');
    
    if (!ratingStars.length || !ratingInput) return;
    
    ratingStars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.dataset.rating);
            ratingInput.value = rating;
            
            ratingStars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                    s.classList.add('active');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                    s.classList.remove('active');
                }
            });
        });
        
        star.addEventListener('mouseenter', function() {
            const rating = parseInt(this.dataset.rating);
            ratingStars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                }
            });
        });
        
        star.addEventListener('mouseleave', function() {
            const currentRating = parseInt(ratingInput.value);
            ratingStars.forEach((s, index) => {
                if (index < currentRating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                }
            });
        });
    });
}

/**
 * Setup avatar preview for testimonial form
 */
function setupAvatarPreview() {
    const avatarInput = document.getElementById('testimonial_avatar');
    const avatarPreview = document.getElementById('testimonialAvatarPreview');
    const previewImg = document.getElementById('avatarPreviewImg');
    const removeAvatarBtn = document.getElementById('removeAvatarBtn');
    
    if (!avatarInput) return;
    
    avatarInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Validate file size (max 2MB)
            if (file.size > 2 * 1024 * 1024) {
                if (window.Toast) Toast.show('Image size must be less than 2MB', 'error');
                this.value = '';
                return;
            }
            
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                if (window.Toast) Toast.show('Please upload JPG, JPEG, or PNG images only', 'error');
                this.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(event) {
                if (previewImg) previewImg.src = event.target.result;
                if (avatarPreview) avatarPreview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    });
    
    if (removeAvatarBtn) {
        removeAvatarBtn.addEventListener('click', function() {
            avatarInput.value = '';
            if (avatarPreview) avatarPreview.classList.add('hidden');
            if (previewImg) previewImg.src = '';
        });
    }
}

/**
 * Close testimonial modal function (global for modal close button)
 */
function closeTestimonialModal() {
    const modal = document.getElementById('testimonialModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        const form = document.getElementById('testimonialForm');
        if (form) form.reset();
        
        // Reset rating stars
        const ratingInput = document.getElementById('testimonial_rating');
        const ratingStars = document.querySelectorAll('.rating-input i');
        if (ratingInput) ratingInput.value = 5;
        if (ratingStars.length) {
            ratingStars.forEach((s, index) => {
                if (index < 5) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                    s.classList.add('active');
                }
            });
        }
        
        // Reset avatar preview
        const avatarPreview = document.getElementById('testimonialAvatarPreview');
        const avatarInput = document.getElementById('testimonial_avatar');
        if (avatarPreview) avatarPreview.classList.add('hidden');
        if (avatarInput) avatarInput.value = '';
    }
}