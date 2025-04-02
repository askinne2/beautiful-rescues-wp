jQuery(document).ready(function($) {
    // Add loading overlay to the page if it doesn't exist
    if (!$('.verification-loading-overlay').length) {
        $('body').append('<div class="verification-loading-overlay"><div class="verification-spinner"></div></div>');
    }

    // Check if we're on the checkout page and validate cart
    if ($('.checkout-page').length) {
        // Get selected images from localStorage and ensure HTTPS URLs
        let selectedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]')
            .map(img => ({
                ...img,
                url: img.url.replace('http://', 'https://')
            }));

        // Update localStorage with HTTPS URLs
        localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImages));

        const selectedImagesGrid = $('.selected-images-grid');
        const checkoutColumn = $('.checkout-column.checkout-verification-form');
        
        if (!selectedImages.length) {
            // Show simple message in images grid
            selectedImagesGrid.html('<p class="no-images">No images selected</p>');
            
            // Show empty cart notice in form column
            checkoutColumn.html(`
                <div class="empty-cart-notice">
                    <p>Your cart is empty. Please select some images before proceeding to checkout.</p>
                    <a href="/" class="button">Return to Gallery</a>
                </div>
            `);
            return; // Don't initialize form handlers if cart is empty
        }

        // Function to update selected images preview
        function updateSelectedImagesPreview() {
            if (!selectedImages.length) {
                selectedImagesGrid.html('<p class="no-images">No images selected</p>');
                return;
            }

            // Show images in grid with standardized data
            const imagesHtml = selectedImages.map(image => `
                <div class="selected-image-item" data-id="${image.id}">
                    <img src="${image.url.replace('http://', 'https://')}" 
                         alt="${image.filename || ''}"
                         data-width="${image.width || ''}"
                         data-height="${image.height || ''}">
                    <button class="remove-image" data-id="${image.id}">&times;</button>
                </div>
            `).join('');

            selectedImagesGrid.html(imagesHtml);
        }

        // Function to update cart count
        function updateCartCount() {
            const count = selectedImages.length;
            $('.cart-count').text(count);
            $('.cart-button').toggleClass('hidden', count === 0);

            // Trigger custom event for cart
            $(document).trigger('beautifulRescuesSelectionChanged');
        }

        // Handle remove image
        selectedImagesGrid.on('click', '.remove-image', function(e) {
            e.preventDefault();
            const imageId = $(this).data('id');
            
            selectedImages = selectedImages.filter(img => img.id !== imageId);
            
            // Update localStorage
            localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImages));
            
            // Update UI
            updateSelectedImagesPreview();
            
            // Trigger custom event for cart update
            $(document).trigger('beautifulRescuesSelectionChanged');
        });

        // Initialize selected images preview
        updateSelectedImagesPreview();
    }

    // Handle form submission
    $('.beautiful-rescues-verification-form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        const messages = form.find('#form-messages');
        
        // Clear previous messages
        messages.removeClass('error success').empty();
        
        // Validate required fields
        const requiredFields = {
            '_donor_first_name': 'first name',
            '_donor_last_name': 'last name',
            '_donor_email': 'email address',
            '_donor_phone': 'phone number',
            '_verification_file': 'verification file'
        };

        let hasError = false;
        for (const [fieldName, label] of Object.entries(requiredFields)) {
            const field = form.find(`[name="${fieldName}"]`);
            if (!field.val().trim()) {
                messages.addClass('error').html(`Please enter your ${label}.`);
                field.addClass('error').focus();
                hasError = true;
                return;
            }
        }

        if (hasError) return;

        // Disable submit button and show loading overlay
        submitButton.prop('disabled', true);
        $('.verification-loading-overlay').addClass('active');

        const formData = new FormData(this);
        formData.append('action', 'submit_donation_verification');
        formData.append('verification_nonce', beautifulRescuesVerification.nonce);

        // Add selected images from localStorage if we're on the checkout page
        if ($('.checkout-page').length) {
            const selectedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]');
            // Add selected images to form submission
            BRDebug.info('Adding selected images to form submission:', selectedImages);
            formData.append('selected_images', JSON.stringify(selectedImages));
        }

        $.ajax({
            url: beautifulRescuesVerification.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Clear the selected images from localStorage
                    localStorage.removeItem('beautifulRescuesSelectedImages');
                    // Clear selected images from localStorage
                    BRDebug.info('Cleared selected images from localStorage');

                    // Hide the form and show success message
                    const successHtml = `
                        <div class="verification-success">
                            <h2>Thank You!</h2>
                            <p>${response.data.message}</p>
                            <p>Redirecting to confirmation page...</p>
                        </div>
                    `;
                    form.closest('.checkout-column').html(successHtml);
                    
                    // Handle redirect if URL is provided
                    if (response.data.redirect_url) {
                        setTimeout(() => {
                            window.location.href = response.data.redirect_url;
                        }, 2000);
                    }
                } else {
                    messages.addClass('error').html(response.data.message || 'An error occurred. Please try again.');
                }
            },
            error: function() {
                messages.addClass('error').html('An error occurred. Please try again.');
            },
            complete: function() {
                // Re-enable submit button and hide loading overlay
                submitButton.prop('disabled', false);
                $('.verification-loading-overlay').removeClass('active');
            }
        });
    });

    // Clear error state on input
    $('.beautiful-rescues-verification-form input, .beautiful-rescues-verification-form textarea').on('input', function() {
        $(this).removeClass('error');
        $(this).closest('.form-group').find('.form-messages').empty().removeClass('error success');
    });

    // File input validation
    $('#_verification_file').on('change', function() {
        const file = this.files[0];
        if (!file) return;
        
        const maxSize = beautifulRescuesVerification.maxFileSize;
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        
        if (file.size > maxSize) {
            const maxSizeMB = Math.round(maxSize / (1024 * 1024));
            $('#form-messages').addClass('error')
                .html(`File is too large. Maximum size is ${maxSizeMB}MB.`);
            this.value = '';
            return;
        }
        
        if (!allowedTypes.includes(file.type)) {
            $('#form-messages').addClass('error')
                .html('Invalid file type. Please upload an image (JPEG, PNG, GIF) or PDF.');
            this.value = '';
            return;
        }
        
        $('#form-messages').empty().removeClass('error success');
    });

    // Helper function to validate email format
    function isValidEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
}); 