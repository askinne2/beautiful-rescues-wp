(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(function() {
        // Initialize debug utility
        if (typeof beautifulRescuesDebug !== 'undefined' && typeof beautifulRescuesDebugSettings !== 'undefined') {
            beautifulRescuesDebug.init(beautifulRescuesDebugSettings);
        }
        
        beautifulRescuesDebug.log('Verification script initialized');

        const verificationForm = $('#verification-form');
        if (!verificationForm.length) {
            beautifulRescuesDebug.warn('Verification form not found');
            return;
        }

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
        verificationForm.on('submit', function(e) {
            e.preventDefault();
            
            const form = $(this);
            const submitButton = form.find('button[type="submit"]');
            const messages = form.find('#form-messages');
            
            // Clear previous messages
            messages.removeClass('error success').empty();
            
            // Disable submit button
            submitButton.prop('disabled', true);
            
            // Show loading overlay
            $('.verification-loading-overlay').addClass('active');
            
            // Get form data
            const formData = new FormData(form[0]);
            
            // Add selected images to form data
            const selectedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]');
            beautifulRescuesDebug.log('Adding selected images to form submission:', selectedImages);
            
            // Add selected images to form data
            formData.append('selected_images', JSON.stringify(selectedImages));
            
            // Submit form via AJAX
            $.ajax({
                url: beautifulRescuesVerification.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        messages.addClass('success').html(response.data.message || 'Verification submitted successfully.');
                        
                        // Clear form
                        form[0].reset();
                        
                        // Clear selected images from localStorage after successful submission
                        localStorage.removeItem('beautifulRescuesSelectedImages');
                        beautifulRescuesDebug.log('Cleared selected images from localStorage');
                        
                        // Redirect if URL is provided
                        if (response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        }
                    } else {
                        // Show error message
                        messages.addClass('error').html(response.data.message || 'An error occurred. Please try again.');
                    }
                },
                error: function() {
                    // Show error message
                    messages.addClass('error').html('An error occurred. Please try again.');
                },
                complete: function() {
                    // Re-enable submit button
                    submitButton.prop('disabled', false);
                    
                    // Hide loading overlay
                    $('.verification-loading-overlay').removeClass('active');
                }
            });
        });
    });
})(jQuery); 