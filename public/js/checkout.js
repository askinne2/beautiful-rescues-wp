(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(function() {
        console.log('Checkout page script initialized');

        const checkout = $('.checkout-page');
        if (!checkout.length) {
            console.warn('Checkout page element not found');
            return;
        }

        // Initialize variables
        const selectedImagesGrid = $('.selected-images-grid');
        const donationForm = $('#donation-verification-form');
        const checkoutButton = $('.checkout-button');
        const checkoutColumn = donationForm.closest('.checkout-column');
        const messages = $('#form-messages');
        
        console.log('Checkout page elements found:', {
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

        console.log('LocalStorage data:', {
            selectedImages: selectedImages,
            localStorage: localStorage.getItem('beautifulRescuesSelectedImages')
        });

        // Function to update selected images preview
        function updateSelectedImagesPreview() {
            console.log('Updating selected images preview:', {
                selectedImages: selectedImages
            });

            if (!selectedImages.length) {
                // Show simple message in images grid
                selectedImagesGrid.html('<p class="no-images">No images selected</p>');
                
                // Show empty cart notice in form column
                checkoutColumn.html('<div class="empty-cart-notice"><p>Your cart is empty. Please select some images before proceeding to checkout.</p><a href="/" class="button">Return to Gallery</a></div>');
                return;
            }

            // Show images in grid
            const imagesHtml = selectedImages.map(image => `
                <div class="selected-image-item" data-id="${image.id}">
                    <img src="${image.url.replace('http://', 'https://')}" alt="Selected image">
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
            const count = selectedImages.length;
            console.log('Updating cart count:', {
                count: count,
                selectedImages: selectedImages
            });

            $('.cart-count').text(count);
            $('.cart-button').toggleClass('hidden', count === 0);

            // Trigger custom event for cart
            $(document).trigger('beautifulRescuesSelectionChanged');
        }

        // Add form validation
        donationForm.on('input', 'input[type="email"]', function(e) {
            // Clear any existing validation messages
            $(this).removeClass('error valid');
            messages.empty().removeClass('error success');
        });

        // Function to handle form submission
        function handleFormSubmission(e) {
            e.preventDefault();
            console.group('Form Submission Process');
            console.log('Form submission started');
            debugger; // Breakpoint 1: Initial form submission

            if (donationForm.data('submitting')) {
                console.warn('Form is already submitting');
                console.groupEnd();
                return;
            }
            
            // Clear previous messages
            messages.empty().removeClass('error success');
            
            // Basic validation
            const emailInput = donationForm.find('input[name="_donor_email"]');
            const emailValue = emailInput.val().trim();
            
            console.log('Validating form fields:', {
                emailValue: emailValue,
                formData: donationForm.serializeArray()
            });
            debugger; // Breakpoint 2: Form validation

            // Validate required fields
            const requiredFields = {
                '_donor_first_name': 'First Name',
                '_donor_last_name': 'Last Name',
                '_donor_phone': 'Phone Number'
            };

            for (const [fieldName, label] of Object.entries(requiredFields)) {
                const field = donationForm.find(`input[name="${fieldName}"]`);
                if (!field.val().trim()) {
                    console.error(`Missing required field: ${fieldName}`);
                    messages.addClass('error').html(`Please enter your ${label}.`);
                    field.addClass('error').focus();
                    console.groupEnd();
                    return;
                }
            }

            // Validate verification file
            const fileInput = donationForm.find('input[name="_verification_file"]');
            if (!fileInput.length || !fileInput[0].files.length) {
                console.error('Missing verification file');
                messages.addClass('error').html('Please upload a verification file.');
                fileInput.addClass('error').focus();
                console.groupEnd();
                return;
            }

            donationForm.data('submitting', true);
            donationForm.addClass('submitting');

            const formData = new FormData(donationForm[0]);
            
            // Add action and nonce
            formData.append('action', 'submit_donation_verification');
            formData.append('verification_nonce', beautifulRescuesCheckout.nonce);
            formData.append('source', 'checkout');
            
            // Add selected images with proper formatting
            const formattedImages = selectedImages.map(img => ({
                id: img.public_id || img.id,
                url: img.url.replace('http://', 'https://'),
                width: img.width,
                height: img.height
            }));
            formData.append('selected_images', JSON.stringify(formattedImages));

            console.log('Preparing AJAX submission:', {
                action: formData.get('action'),
                nonce: formData.get('verification_nonce'),
                selectedImages: formattedImages,
                formFields: Object.fromEntries(formData.entries())
            });
            debugger; // Breakpoint 3: Before AJAX submission

            $.ajax({
                url: beautifulRescuesCheckout.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Form submission response:', response);
                    debugger; // Breakpoint 4: After successful submission
                    if (response.success) {
                        messages.addClass('success').html(beautifulRescuesCheckout.i18n.thankYou);
                        selectedImages = [];
                        localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImages));
                        if (response.data?.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            window.location.href = beautifulRescuesCheckout.homeUrl;
                        }
                    } else {
                        messages.addClass('error').html(response.data?.message || beautifulRescuesCheckout.i18n.error);
                    }
                    console.groupEnd();
                },
                error: function(xhr, status, error) {
                    console.error('Form submission error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText,
                        statusText: xhr.statusText
                    });
                    debugger; // Breakpoint 5: After submission error
                    messages.addClass('error').html('An error occurred while processing your request. Please try again.');
                    console.groupEnd();
                },
                complete: function() {
                    donationForm.removeClass('submitting');
                    donationForm.data('submitting', false);
                }
            });
        }

        // Handle remove image
        selectedImagesGrid.on('click', '.remove-image', function(e) {
            e.preventDefault();
            const imageId = $(this).data('id');
            
            console.log('Removing image:', {
                imageId: imageId,
                currentSelections: selectedImages
            });

            selectedImages = selectedImages.filter(img => img.id !== imageId);
            
            // Update localStorage
            localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImages));
            
            // Update UI
            updateSelectedImagesPreview();
            
            // Trigger custom event for cart update
            $(document).trigger('beautifulRescuesSelectionChanged');
            
            console.log('Image removed, new state:', {
                selectedImages: selectedImages,
                localStorage: JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]')
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
        console.log('Initializing selected images preview');
        updateSelectedImagesPreview();
    });
})(jQuery); 