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

        // Function to handle form submission
        function handleFormSubmission(e) {
            e.preventDefault();
            if (donationForm.data('submitting')) return;
            donationForm.data('submitting', true);
            donationForm.addClass('submitting');

            const formData = new FormData(donationForm[0]);
            formData.append('selected_images', JSON.stringify(selectedImages));

            $.ajax({
                url: beautifulRescuesCheckout.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert(beautifulRescuesCheckout.i18n.thankYou);
                        selectedImages = [];
                        localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImages));
                        if (response.redirect) {
                            window.location.href = response.redirect;
                        } else {
                            window.location.href = beautifulRescuesCheckout.homeUrl;
                        }
                    } else {
                        alert(response.data?.message || beautifulRescuesCheckout.i18n.error);
                    }
                },
                error: function() {
                    alert(beautifulRescuesCheckout.i18n.error);
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

        // Bind form submission
        donationForm.on('submit', handleFormSubmission);

        // Initialize selected images preview
        console.log('Initializing selected images preview');
        updateSelectedImagesPreview();
    });
})(jQuery); 