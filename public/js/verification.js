(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(function() {
        // Initialize debug utility
        if (typeof beautifulRescuesDebug !== 'undefined' && typeof beautifulRescuesDebugSettings !== 'undefined') {
            beautifulRescuesDebug.init(beautifulRescuesDebugSettings);
        }
        
        beautifulRescuesDebug.log('Verification script initialized');

        // Add loading overlay to the page if it doesn't exist
        if (!$('.verification-loading-overlay').length) {
            $('body').append('<div class="verification-loading-overlay"><div class="verification-spinner"></div></div>');
        }

        // Global variable to track form submission state
        let isFormSubmitting = false;

        // Check if we're on the checkout page
        const isCheckoutPage = $('.checkout-page').length > 0;
        beautifulRescuesDebug.log('Page type:', { isCheckoutPage });

        // Get selected images from localStorage and ensure HTTPS URLs
        let selectedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]')
            .filter(img => img && img.id && (img.watermarked_url || img.original_url)) // Filter out invalid entries
            .map(img => ({
                ...img,
                watermarked_url: (img.watermarked_url || '').replace('http://', 'https://'),
                original_url: (img.original_url || '').replace('http://', 'https://')
            }));

        // Update localStorage with filtered and HTTPS URLs
        localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImages));

        beautifulRescuesDebug.log('LocalStorage data:', {
            selectedImages: selectedImages,
            localStorage: localStorage.getItem('beautifulRescuesSelectedImages')
        });

        // Initialize variables for checkout page
        if (isCheckoutPage) {
            // Get the actual DOM elements based on the structure shown in the console
            const checkoutContainer = $('.checkout-container');
            const galleryColumn = $('.checkout-column.gallery-column');
            const checkoutColumn = $('.checkout-column.checkout-verification-form');
            const selectedImagesGrid = $('.selected-images-grid');
            const donationForm = $('#checkout-verification-form');
            const messages = $('#form-messages');
            
            beautifulRescuesDebug.log('Checkout page elements found:', {
                checkoutPage: isCheckoutPage,
                checkoutContainer: checkoutContainer.length > 0,
                galleryColumn: galleryColumn.length > 0,
                checkoutColumn: checkoutColumn.length > 0,
                selectedImagesGrid: selectedImagesGrid.length > 0,
                donationForm: donationForm.length > 0
            });
            
            // Function to handle empty cart state
            function handleEmptyCart() {
                beautifulRescuesDebug.log('Handling empty cart state');
                
                // Hide the gallery column completely when cart is empty
                galleryColumn.hide();
                
                // Show empty cart notice in the checkout column
                checkoutColumn.html(`
                    <div class="empty-cart-notice">
                        <p>Your cart is empty. Please select some images before proceeding to checkout.</p>
                        <a href="${beautifulRescuesCheckout.galleryUrl || beautifulRescuesCheckout.homeUrl}" class="button">Return to Gallery</a>
                    </div>
                `);
                
                // Make sure the checkout column takes full width when gallery column is hidden
                checkoutColumn.css('grid-column', '1 / -1');
            }
            
            // Check if cart is empty on page load
            if (!selectedImages.length) {
                beautifulRescuesDebug.log('Cart is empty on page load');
                handleEmptyCart();
                return; // Don't initialize form handlers if cart is empty
            }

            // Function to update selected images preview
            function updateSelectedImagesPreview() {
                beautifulRescuesDebug.log('Updating selected images preview:', {
                    selectedImages: selectedImages
                });

                if (!selectedImages.length) {
                    handleEmptyCart();
                    return;
                }
                
                // Reset the checkout column to normal width when images are present
                checkoutColumn.css('grid-column', '');
                
                // Show the gallery column when images are present
                galleryColumn.show();
                
                // If selectedImagesGrid doesn't exist, create it
                if (!selectedImagesGrid.length) {
                    galleryColumn.html('<div class="selected-images-grid"></div>');
                }
                
                // Get the selectedImagesGrid again after potentially creating it
                const updatedSelectedImagesGrid = $('.selected-images-grid');
                
                // Show images in grid with standardized data
                const imagesHtml = selectedImages.map(image => `
                    <div class="selected-image-item" data-id="${image.id}">
                        <img src="${image.watermarked_url}" 
                             alt="${image.filename || ''}"
                             data-width="${image.width || ''}"
                             data-height="${image.height || ''}">
                        <button class="remove-image" data-id="${image.id}">&times;</button>
                    </div>
                `).join('');

                updatedSelectedImagesGrid.html(imagesHtml);
            }

            // Function to update cart count
            function updateCartCount() {
                // Get current selections from localStorage
                const storedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]');
                const count = storedImages.length;
                
                beautifulRescuesDebug.log('Updating cart count:', {
                    count: count,
                    storedImages: storedImages
                });

                $('.cart-count').text(count);
                $('.cart-button').toggleClass('hidden', count === 0);
            }

            // Handle remove image
            $(document).on('click', '.remove-image', function(e) {
                e.preventDefault();
                const imageId = $(this).data('id');
                
                beautifulRescuesDebug.log('Removing image:', {
                    imageId: imageId
                });

                // Get current selections from localStorage
                const currentStoredImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]');
                
                // Filter out the removed image
                const updatedImages = currentStoredImages.filter(img => img.id !== imageId);
                
                // Update localStorage
                localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(updatedImages));
                
                // Update local cache
                selectedImages = updatedImages;
                
                // Update UI
                updateSelectedImagesPreview();
                updateCartCount();
                
                // Trigger custom event for cart update
                $(document).trigger('beautifulRescuesSelectionChanged', [{
                    selectedImages: updatedImages
                }]);
                
                beautifulRescuesDebug.log('Image removed, new state:', {
                    selectedImages: updatedImages,
                    localStorage: JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]')
                });
            });

            // Listen for selection changes from gallery
            $(document).on('beautifulRescuesSelectionChanged', function(e, data) {
                beautifulRescuesDebug.log('Selection changed event received:', data);
                
                if (data && data.selectedImages) {
                    // Update local cache
                    selectedImages = data.selectedImages.filter(img => img && img.id)
                        .map(img => ({
                            ...img,
                            watermarked_url: (img.watermarked_url || '').replace('http://', 'https://'),
                            original_url: (img.original_url || '').replace('http://', 'https://')
                        }));
                    
                    beautifulRescuesDebug.log('Updated selected images:', selectedImages);
                    
                    // Update localStorage (if not already done by the sender)
                    const storedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]');
                    if (JSON.stringify(storedImages) !== JSON.stringify(selectedImages)) {
                        localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImages));
                    }
                    
                    // Update UI
                    updateSelectedImagesPreview();
                    updateCartCount();
                }
            });

            // Initialize selected images preview
            updateSelectedImagesPreview();
            updateCartCount();

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
                beautifulRescuesDebug.group('Form Submission');
                
                const donationForm = $('#checkout-verification-form');
                const messages = $('#form-messages');
                const submitButton = donationForm.find('button[type="submit"]');
                const checkoutColumn = donationForm.closest('.checkout-column');
                
                // Check if form is already submitting using form data attribute
                if (donationForm.data('submitting')) {
                    beautifulRescuesDebug.error('Form already submitting');
                    beautifulRescuesDebug.groupEnd();
                    return;
                }
                
                // Set form submitting state
                donationForm.data('submitting', true);
                submitButton.prop('disabled', true);
                
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
                        beautifulRescuesDebug.error(`Missing required field: ${fieldName}`);
                        showError(`Please enter your ${label}.`);
                        field.addClass('error').focus();
                        beautifulRescuesDebug.groupEnd();
                        return;
                    }
                }

                // Validate email format
                const emailField = donationForm.find('input[name="_donor_email"]');
                const emailValue = emailField.val().trim();
                if (!isValidEmail(emailValue)) {
                    beautifulRescuesDebug.error('Invalid email format');
                    showError('Please enter a valid email address.');
                    emailField.addClass('error').focus();
                    beautifulRescuesDebug.groupEnd();
                    return;
                }

                // Validate verification file
                const fileInput = donationForm.find('input[name="_verification_file"]');
                if (!fileInput.length || !fileInput[0].files.length) {
                    beautifulRescuesDebug.error('Missing verification file');
                    showError('Please upload a verification file.');
                    fileInput.addClass('error').focus();
                    beautifulRescuesDebug.groupEnd();
                    return;
                }

                const fileValidation = validateFileUpload(fileInput[0].files[0]);
                if (!fileValidation.valid) {
                    beautifulRescuesDebug.error('Invalid file:', fileValidation.message);
                    showError(fileValidation.message);
                    fileInput.addClass('error').focus();
                    beautifulRescuesDebug.groupEnd();
                    return;
                }

                // Show loading overlay
                beautifulRescuesDebug.log('Showing loading overlay');
                $('.verification-loading-overlay').addClass('active');

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
                    watermarked_url: img.watermarked_url.replace('http://', 'https://'),
                    original_url: img.original_url.replace('http://', 'https://')
                }));
                formData.append('selected_images', JSON.stringify(formattedImages));

                // Use a timeout to prevent multiple submissions
                const submissionTimeout = setTimeout(() => {
                    $.ajax({
                        url: beautifulRescuesCheckout.ajaxurl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            beautifulRescuesDebug.log('Form submission response:', response);
                            
                            if (response.success) {
                                beautifulRescuesDebug.log('Submission successful, showing success message');
                                // Hide the form and show success message
                                const successHtml = `
                                    <div class="verification-success">
                                        <h2>${beautifulRescuesCheckout.i18n.thankYou}</h2>
                                        <p>${beautifulRescuesCheckout.i18n.verificationReceived}</p>
                                    </div>
                                `;
                                checkoutColumn.html(successHtml);
                                
                                // Scroll to the success message
                                $('html, body').animate({
                                    scrollTop: checkoutColumn.offset().top - 100
                                }, 500);
                                
                                // Clear selected images from localStorage after successful submission
                                localStorage.removeItem('beautifulRescuesSelectedImages');
                                beautifulRescuesDebug.log('Cleared selected images from localStorage');
                                
                                // Handle redirect if URL is provided
                                if (response.data.redirect_url) {
                                    beautifulRescuesDebug.log('Redirect URL provided:', response.data.redirect_url);
                                    setTimeout(() => {
                                        window.location.href = response.data.redirect_url;
                                    }, 2000);
                                }
                            } else {
                                beautifulRescuesDebug.error('Submission failed:', response.data?.message);
                                showError(response.data?.message || beautifulRescuesCheckout.i18n.error);
                                
                                // Scroll to the error message
                                $('html, body').animate({
                                    scrollTop: messages.offset().top - 100
                                }, 500);
                            }
                        },
                        error: function(xhr, status, error) {
                            beautifulRescuesDebug.error('Form submission error:', error);
                            showError(beautifulRescuesCheckout.i18n.error);
                            
                            // Scroll to the error message
                            $('html, body').animate({
                                scrollTop: messages.offset().top - 100
                            }, 500);
                        },
                        complete: function() {
                            beautifulRescuesDebug.log('Form submission complete, hiding loading overlay');
                            
                            // Clear the timeout
                            clearTimeout(submissionTimeout);
                            
                            // Hide loading overlay
                            $('.verification-loading-overlay').removeClass('active');
                            
                            // Reset form state
                            donationForm.removeClass('submitting');
                            donationForm.data('submitting', false);
                            submitButton.prop('disabled', false);
                            
                            // Reset global submitting state
                            isFormSubmitting = false;
                            
                            beautifulRescuesDebug.groupEnd();
                        }
                    });
                }, 100); // Small delay to prevent multiple submissions
            }

            // Helper function to validate email format
            function isValidEmail(email) {
                const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                return re.test(String(email).toLowerCase());
            }

            // Bind form submission - only once
            if (!donationForm.data('submission-bound')) {
                donationForm.on('submit', handleFormSubmission);
                donationForm.data('submission-bound', true);
            }
        } else {
            // Handle regular verification form (not on checkout page)
            const verificationForm = $('#verification-form');
            if (!verificationForm.length) {
                beautifulRescuesDebug.warn('Verification form not found');
                return;
            }

            // Handle form submission
            verificationForm.on('submit', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const submitButton = form.find('button[type="submit"]');
                const messages = form.find('#form-messages');
                
                // Check if form is already submitting using global variable
                if (isFormSubmitting) {
                    beautifulRescuesDebug.error('Form already submitting');
                    return;
                }
                
                // Set global submitting state
                isFormSubmitting = true;
                
                // Clear previous messages
                messages.removeClass('error success').empty();
                
                // Show loading overlay
                $('.verification-loading-overlay').addClass('active');
                
                // Get form data
                const formData = new FormData(form[0]);
                
                // Add selected images to form data
                const selectedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]');
                beautifulRescuesDebug.log('Adding selected images to form submission:', selectedImages);
                
                // Add selected images to form data
                formData.append('selected_images', JSON.stringify(selectedImages));
                
                // Ensure beautiful_rescues identifier is set
                formData.append('beautiful_rescues', '1');
                
                // Use a timeout to prevent multiple submissions
                const submissionTimeout = setTimeout(() => {
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
                                
                                // Scroll to the success message
                                $('html, body').animate({
                                    scrollTop: messages.offset().top - 100
                                }, 500);
                                
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
                                
                                // Scroll to the error message
                                $('html, body').animate({
                                    scrollTop: messages.offset().top - 100
                                }, 500);
                            }
                        },
                        error: function() {
                            // Show error message
                            messages.addClass('error').html('An error occurred. Please try again.');
                            
                            // Scroll to the error message
                            $('html, body').animate({
                                scrollTop: messages.offset().top - 100
                            }, 500);
                        },
                        complete: function() {
                            // Clear the timeout
                            clearTimeout(submissionTimeout);
                            
                            // Re-enable submit button
                            submitButton.prop('disabled', false);
                            
                            // Reset form state
                            form.data('submitting', false);
                            
                            // Reset global submitting state
                            isFormSubmitting = false;
                            
                            // Hide loading overlay
                            $('.verification-loading-overlay').removeClass('active');
                        }
                    });
                }, 100); // Small delay to prevent multiple submissions
            });
        }
    });
})(jQuery); 