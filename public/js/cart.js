(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(function() {
        // Initialize debug utility
        if (typeof beautifulRescuesDebug !== 'undefined' && typeof beautifulRescuesDebugSettings !== 'undefined') {
            beautifulRescuesDebug.init(beautifulRescuesDebugSettings);
        }
        
        beautifulRescuesDebug.log('Cart script initialized');

        const cart = $('.beautiful-rescues-cart');
        if (!cart.length) {
            beautifulRescuesDebug.warn('Cart element not found');
            return;
        }

        // Initialize variables
        const cartButton = $('.cart-button');
        const cartCount = $('.cart-count');
        const selectedImagesGrid = $('.selected-images-grid');
        const checkoutColumn = $('.checkout-column');
        const donationForm = $('.donation-form');
        let selectedImages = [];
        let isAnimating = false;

        // Create toast container if not exists
        if (!$('.toast-container').length) {
            $('body').append('<div class="toast-container"></div>');
        }

        // Function to show toast notification
        function showToast(message, duration = 3000) {
            const toast = $(`
                <div class="toast">
                    <span class="toast-message">${message}</span>
                    <button class="toast-close">&times;</button>
                </div>
            `);
            
            $('.toast-container').append(toast);

            // Handle manual close
            toast.find('.toast-close').on('click', () => {
                toast.addClass('hiding');
                setTimeout(() => toast.remove(), 300);
            });

            // Auto dismiss
            setTimeout(() => {
                toast.addClass('hiding');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }

        // Function to handle cart visibility with animation
        function updateCartVisibility(show) {
            if (isAnimating) return;
            isAnimating = true;

            // Check if we're on the checkout page
            if ($('body').hasClass('page-template-checkout')) {
                cart.hide();
                isAnimating = false;
                return;
            }

            if (show) {
                cart.removeClass('hidden animate-out').addClass('visible animate-in');
            } else {
                cart.removeClass('visible animate-in').addClass('hidden animate-out');
            }

            // Reset animation state after animation completes
            setTimeout(() => {
                isAnimating = false;
            }, 500);
        }

        // Get selected images from localStorage and ensure HTTPS URLs
        selectedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]')
            .filter(img => img && img.id && img.watermarked_url && img.original_url) // Filter out invalid entries
            .map(img => ({
                ...img,
                watermarked_url: img.watermarked_url.replace('http://', 'https://'),
                original_url: img.original_url.replace('http://', 'https://')
            }));

        // Update localStorage with filtered and HTTPS URLs
        localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImages));

        beautifulRescuesDebug.log('LocalStorage data:', {
            selectedImages: selectedImages,
            localStorage: localStorage.getItem('beautifulRescuesSelectedImages')
        });

        // Function to update selected images preview
        function updateSelectedImagesPreview() {
            beautifulRescuesDebug.log('Updating selected images preview:', {
                selectedImages: selectedImages
            });

            // Only update preview if we're on the checkout page
            if (selectedImagesGrid.length) {
                if (!selectedImages.length) {
                    selectedImagesGrid.html('<p class="no-images">No images selected</p>');
                    if (checkoutColumn.length) {
                        checkoutColumn.html('<div class="empty-cart-notice"><p>Your cart is empty. Please select some images before proceeding to checkout.</p><a href="/" class="button">Return to Gallery</a></div>');
                    }
                    return;
                }

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

                selectedImagesGrid.html(imagesHtml);
                if (checkoutColumn.length && donationForm.length) {
                    checkoutColumn.html(donationForm);
                    donationForm.show();
                }
            }
            
            updateCartCount();
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

            if (cartCount.length) {
                cartCount.text(count);
                // Update cart visibility based on count
                updateCartVisibility(count > 0);
            }
        }

        // Initialize cart count on page load
        updateCartCount();
        updateSelectedImagesPreview();

        // Function to open donation modal
        function openDonationModal() {
            try {
                // Check if there are any selected images
                if (!selectedImages.length) {
                    beautifulRescuesDebug.log('No items in cart, showing toast notification');
                    showToast('You have no items in your cart');
                    return;
                }

                // Redirect to checkout page
                beautifulRescuesDebug.log('Redirecting to checkout:', beautifulRescuesCart.checkoutUrl);
                window.location.href = beautifulRescuesCart.checkoutUrl;
            } catch (error) {
                beautifulRescuesDebug.error('Error opening donation modal:', error);
                showToast('An error occurred while processing your cart');
            }
        }

        // Function to close modal
        function closeModal($modal) {
            $modal.fadeOut();
            if (!$('.gallery-modal:visible, .donation-modal:visible').length) {
                $('body').removeClass('modal-open');
                $('body').css('padding-right', '');
            }
        }

        // Function to handle form submission
        function handleFormSubmission($form) {
            if ($form.data('submitting')) return;
            $form.data('submitting', true);
            $form.addClass('submitting');

            const formData = new FormData($form[0]);
            formData.append('action', 'submit_donation_verification');
            formData.append('nonce', beautifulRescuesCart.nonce);
            formData.append('selected_images', JSON.stringify(selectedImages));

            $.ajax({
                url: beautifulRescuesCart.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showToast(beautifulRescuesCart.i18n.thankYou);
                        selectedImages = [];
                        localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImages));
                        updateCartCount();
                        closeModal($form.closest('.donation-modal'));
                    } else {
                        showToast(response.data?.message || beautifulRescuesCart.i18n.error);
                    }
                },
                error: function() {
                    showToast(beautifulRescuesCart.i18n.error);
                },
                complete: function() {
                    $form.removeClass('submitting');
                    $form.data('submitting', false);
                }
            });
        }

        // Bind cart button click
        cartButton.on('click', openDonationModal);

        // Listen for storage changes from other tabs/windows
        $(window).on('storage', function(e) {
            if (e.originalEvent.key === 'beautifulRescuesSelectedImages') {
                beautifulRescuesDebug.log('Storage changed in another window:', {
                    oldValue: e.originalEvent.oldValue,
                    newValue: e.originalEvent.newValue
                });
                
                // Check if cart was cleared (empty array)
                const newData = JSON.parse(e.originalEvent.newValue || '[]');
                if (newData.length === 0) {
                    // If cart was cleared, make sure gallery item states are reset
                    $('.gallery-item').removeClass('selected');
                    if ($('.selected-count').length) {
                        $('.selected-count').text('0');
                    }
                }
                
                updateCartCount();
            }
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

        // Close modal on escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                const visibleModal = $('.gallery-modal:visible, .donation-modal:visible');
                if (visibleModal.length) {
                    closeModal(visibleModal);
                }
            }
        });

        // Handle checkout button click
        $('.donation-checkout-button').on('click', function(e) {
            e.preventDefault();
            
            if (selectedImages.length === 0) {
                beautifulRescuesDebug.log('No items in cart, showing toast notification');
                showToast(beautifulRescuesCart.i18n.noImagesSelected);
                return;
            }
            
            beautifulRescuesDebug.log('Checkout button clicked', {
                selectedImages: selectedImages,
                checkoutUrl: beautifulRescuesCart.checkoutUrl
            });
            
            beautifulRescuesDebug.log('Redirecting to checkout:', beautifulRescuesCart.checkoutUrl);
            window.location.href = beautifulRescuesCart.checkoutUrl;
        });

        // Add clear cart functionality that will ripple across other JS files
        function clearCart() {
            beautifulRescuesDebug.log('Clearing cart');
            
            // Clear localStorage
            localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify([]));
            
            // Clear local variable
            selectedImages = [];
            
            // Update UI
            updateSelectedImagesPreview();
            updateCartCount();
            
            // Reset gallery item selection states - this will update the UI in gallery.js
            $('.gallery-item').removeClass('selected');
            
            // Trigger custom event for other files to sync with
            $(document).trigger('beautifulRescuesSelectionChanged', [{ 
                selectedImages: [] 
            }]);
            
            // If a selected count element exists (from gallery.js), update it
            if ($('.selected-count').length) {
                $('.selected-count').text('0');
            }
            
            // Show toast notification
            showToast('Cart cleared');
            
            beautifulRescuesDebug.log('Cart cleared, gallery items reset');
        }

        // Add clear cart button to the DOM next to cart button
        $('<button class="clear-cart-button" aria-label="Clear shopping cart"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg><span class="clear-text">Clear</span></button>').insertAfter('.cart-button');

        // Initially hide the button
        $('.clear-cart-button').hide();

        // Show/hide clear button based on cart count
        function updateClearButtonVisibility() {
            const storedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]');
            $('.clear-cart-button').toggle(storedImages.length > 0);
        }

        // Update the updateCartCount function to also update clear button visibility
        const originalUpdateCartCount = updateCartCount;
        updateCartCount = function() {
            originalUpdateCartCount.apply(this, arguments);
            updateClearButtonVisibility();
        };

        // Bind click event to the clear cart button
        $(document).on('click', '.clear-cart-button', function(e) {
            e.preventDefault();
            clearCart();
        });

        // Initialize clear button visibility
        updateClearButtonVisibility();
    });
})(jQuery); 