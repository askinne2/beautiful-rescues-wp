(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(function() {
        BRDebug.info('Checkout page script initialized');

        const checkout = $('.checkout-page');
        if (!checkout.length) {
            BRDebug.warn('Checkout page element not found');
            return;
        }

        // Initialize variables
        const selectedImagesGrid = $('.selected-images-grid');
        const donationForm = $('#donation-verification-form');
        const checkoutButton = $('.checkout-button');
        const checkoutColumn = donationForm.closest('.checkout-column');
        const messages = $('#form-messages');
        
        BRDebug.info('Checkout page elements found:', {
            checkoutPage: checkout.length > 0,
            selectedImagesGrid: selectedImagesGrid.length > 0,
            donationForm: donationForm.length > 0,
            checkoutButton: checkoutButton.length > 0
        });

        // Get selected images from localStorage and ensure HTTPS URLs
        let selectedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]')
            .map(img => ({
                ...img,
                url: img.url.replace('http://', 'https://')
            }));

        // Update localStorage with HTTPS URLs
        localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImages));

        BRDebug.info('LocalStorage data:', {
            selectedImages: selectedImages,
            localStorage: localStorage.getItem('beautifulRescuesSelectedImages')
        });

        // Function to update selected images preview
        function updateSelectedImagesPreview() {
            const selectedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]');
            
            BRDebug.info('LocalStorage data:', {
                selectedImages
            });
            
            BRDebug.info('Updating selected images preview:', {
                selectedImages,
                previewContainer: $('.selected-images-container').length
            });

            if (!selectedImages.length) {
                // Show simple message in images grid
                selectedImagesGrid.html('<p class="no-images">No images selected</p>');
                
                // Show empty cart notice in form column
                checkoutColumn.html('<div class="empty-cart-notice"><p>Your cart is empty. Please select some images before proceeding to checkout.</p><a href="/" class="button">Return to Gallery</a></div>');
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

            // Show form
            checkoutColumn.html(donationForm);
            donationForm.show();

            // Update cart count
            updateCartCount();
        }

        // Function to update cart count
        function updateCartCount() {
            const selectedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]');
            
            BRDebug.info('Updating cart count:', {
                count: selectedImages.length
            });

            $('.cart-count').text(selectedImages.length);
            $('.cart-button').toggleClass('hidden', selectedImages.length === 0);

            // Trigger custom event for cart
            $(document).trigger('beautifulRescuesSelectionChanged');
        }

        // Add form validation
        donationForm.on('input', 'input[type="email"]', function(e) {
            // Clear any existing validation messages
            $(this).removeClass('error valid');
            messages.empty().removeClass('error success');
        });

        // Function to validate file upload
        function validateFileUpload(file) {
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            
            if (!file) {
                return { valid: false, message: 'Please select a file to upload.' };
            }
            
            if (file.size > maxSize) {
                return { valid: false, message: 'File size must be less than 5MB.' };
            }
            
            if (!allowedTypes.includes(file.type)) {
                return { valid: false, message: 'File must be an image (JPEG, PNG, GIF) or PDF.' };
            }
            
            return { valid: true };
        }

        // Function to show error message
        function showError(message) {
            const messages = $('#form-messages');
            messages.removeClass('success').addClass('error').html(message);
        }

        // Function to show success message
        function showSuccess(message) {
            const messages = $('#form-messages');
            messages.removeClass('error').addClass('success').html(message);
        }

        // Function to handle form submission
        function handleFormSubmission(e) {
            e.preventDefault();
            BRDebug.debug('Starting form submission');
            
            const donationForm = $('#checkout-verification-form');
            const messages = $('#form-messages');
            const submitButton = donationForm.find('button[type="submit"]');
            const checkoutColumn = donationForm.closest('.checkout-column');
            
            // Clear previous messages
            messages.removeClass('error success').empty();
            
            // Validate required fields
            const requiredFields = {
                '_donor_first_name': 'first name',
                '_donor_last_name': 'last name',
                '_donor_email': 'email address',
                '_donor_phone': 'phone number'
            };

            for (const [fieldName, label] of Object.entries(requiredFields)) {
                const field = donationForm.find(`input[name="${fieldName}"]`);
                if (!field.val().trim()) {
                    BRDebug.error(`Missing required field: ${fieldName}`);
                    showError(`Please enter your ${label}.`);
                    field.addClass('error').focus();
                    return;
                }
            }

            // Validate email format
            const emailField = donationForm.find('input[name="_donor_email"]');
            const emailValue = emailField.val().trim();
            if (!isValidEmail(emailValue)) {
                BRDebug.error('Invalid email format');
                showError('Please enter a valid email address.');
                emailField.addClass('error').focus();
                return;
            }

            // Validate verification file
            const fileInput = donationForm.find('input[name="_verification_file"]');
            if (!fileInput.length || !fileInput[0].files.length) {
                BRDebug.error('Missing verification file');
                showError('Please upload a verification file.');
                fileInput.addClass('error').focus();
                return;
            }

            const fileValidation = validateFileUpload(fileInput[0].files[0]);
            if (!fileValidation.valid) {
                BRDebug.error('Invalid file:', fileValidation.message);
                showError(fileValidation.message);
                fileInput.addClass('error').focus();
                return;
            }

            if (donationForm.data('submitting')) {
                BRDebug.error('Form already submitting');
                return;
            }

            // Set submitting state
            donationForm.data('submitting', true);
            donationForm.addClass('submitting');
            submitButton.prop('disabled', true);
            
            // Show loading overlay
            showLoadingOverlay();

            const formData = new FormData(donationForm[0]);
            
            // Add action and nonce
            formData.append('action', 'submit_donation_verification');
            formData.append('verification_nonce', beautifulRescuesCheckout.nonce);
            formData.append('source', 'checkout');
            
            // Add selected images with standardized format
            const formattedImages = selectedImages.map(img => ({
                id: img.id,
                filename: img.filename || '',
                width: img.width || '',
                height: img.height || '',
                url: img.url.replace('http://', 'https://')
            }));
            formData.append('selected_images', JSON.stringify(formattedImages));

            $.ajax({
                url: beautifulRescuesCheckout.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    BRDebug.info('Form submission response:', response);
                    
                    if (response.success) {
                        BRDebug.info('Submission successful, showing success message');
                        // Hide the form and show success message
                        const successHtml = `
                            <div class="checkout-success">
                                <h2>${beautifulRescuesCheckout.i18n.thankYou}</h2>
                                <p>${beautifulRescuesCheckout.i18n.verificationReceived}</p>
                                <p>Debug: Loading overlay should be hidden</p>
                            </div>
                        `;
                        checkoutColumn.html(successHtml);
                        
                        // Handle redirect if URL is provided
                        if (response.data.redirect_url) {
                            BRDebug.info('Redirect URL provided:', response.data.redirect_url);
                            // For debugging, we'll log the redirect but not execute it
                            // setTimeout(() => {
                            //     window.location.href = response.data.redirect_url;
                            // }, 2000);
                        }
                    } else {
                        BRDebug.error('Submission failed:', response.data?.message);
                        showError(response.data?.message || beautifulRescuesCheckout.i18n.error);
                    }
                },
                error: function(xhr, status, error) {
                    BRDebug.error('Form submission error:', error);
                    showError(beautifulRescuesCheckout.i18n.error);
                },
                complete: function() {
                    BRDebug.info('Form submission complete, hiding loading overlay');
                    BRDebug.info('Loading overlay element:', $('.checkout-loading-overlay').length);
                    BRDebug.info('Loading overlay classes:', $('.checkout-loading-overlay').attr('class'));
                    
                    // Hide loading overlay
                    $('.checkout-loading-overlay').removeClass('active');
                    
                    BRDebug.info('Loading overlay classes after removal:', $('.checkout-loading-overlay').attr('class'));
                    
                    donationForm.removeClass('submitting');
                    donationForm.data('submitting', false);
                    submitButton.prop('disabled', false);
                }
            });
        }

        // Handle remove image
        selectedImagesGrid.on('click', '.remove-image', function(e) {
            e.preventDefault();
            const imageId = $(this).data('id');
            
            BRDebug.info('Removing image:', {
                imageId
            });

            selectedImages = selectedImages.filter(img => img.id !== imageId);
            
            // Update localStorage
            localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImages));
            
            // Update UI
            updateSelectedImagesPreview();
            
            // Trigger custom event for cart update
            $(document).trigger('beautifulRescuesSelectionChanged');
            
            BRDebug.info('Image removed, new state:', {
                selectedImages: JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]')
            });
        });

        // Helper function to validate email format
        function isValidEmail(email) {
            const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(String(email).toLowerCase());
        }

        // Bind form submission
        donationForm.on('submit', handleFormSubmission);

        // Initialize selected images preview
        BRDebug.info('Initializing selected images preview');
        updateSelectedImagesPreview();
    });
})(jQuery); 