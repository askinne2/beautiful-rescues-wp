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
        let isProcessingSelectionChange = false;

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

        beautifulRescuesDebug.log('cart.js - LocalStorage data:', {
            selectedImages: selectedImages,
            localStorage: localStorage.getItem('beautifulRescuesSelectedImages')
        });

        // Function to update selected images preview
        function updateSelectedImagesPreview() {
            beautifulRescuesDebug.log('cart.js - Updating selected images preview:', {
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
            
            beautifulRescuesDebug.log('cart.js - Updating cart count:', {
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
            // Prevent concurrent processing
            if (isProcessingSelectionChange) {
                beautifulRescuesDebug.log('cart.js - Selection change already in progress, queuing');
                setTimeout(function() {
                    $(document).trigger('beautifulRescuesSelectionChanged', data);
                }, 200);
                return;
            }
            
            isProcessingSelectionChange = true;
            beautifulRescuesDebug.log('cart.js - Selection changed event received:', data);
            
            if (data && data.selectedImages) {
                // Create a deep copy of the selected images to avoid reference issues
                const newSelections = JSON.parse(JSON.stringify(data.selectedImages));
                
                // Update local cache with the copy
                selectedImages = newSelections.filter(img => img && img.id)
                    .map(img => ({
                        ...img,
                        watermarked_url: (img.watermarked_url || '').replace('http://', 'https://'),
                        original_url: (img.original_url || '').replace('http://', 'https://')
                    }));
                
                beautifulRescuesDebug.log('cart.js - Updated selected images:', selectedImages);
                
                // Store the updated selection in localStorage
                localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImages));
                
                // Update UI
                updateSelectedImagesPreview();
                updateCartCount();
                
                // Make sure gallery items reflect the selection state
                setTimeout(function() {
                    const selectedIds = new Set(selectedImages.map(img => img.id));
                    $('.gallery-item').each(function() {
                        const $item = $(this);
                        const publicId = $item.attr('data-public-id') || $item.data('public-id');
                        
                        if (selectedIds.has(publicId)) {
                            $item.addClass('selected');
                        } else {
                            $item.removeClass('selected');
                        }
                    });
                    
                    // Release the lock
                    isProcessingSelectionChange = false;
                    beautifulRescuesDebug.log('cart.js - Selection change processing complete');
                }, 100);
            } else {
                isProcessingSelectionChange = false;
            }
        });

        // Listen for new gallery items
        $(document).on('beautifulRescuesGalleryUpdated', function(e, data) {
            beautifulRescuesDebug.log('Gallery updated with new items:', data);
            
            // Get the current selections from localStorage
            const storedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]');
            const storedIds = new Set(storedImages.map(img => img.id));
            
            // Ensure all gallery items have proper data attributes
            setTimeout(function() {
                $('.gallery-item').each(function() {
                    const $item = $(this);
                    const $img = $item.find('img');
                    const publicId = $item.attr('data-public-id') || $item.data('public-id');
                    
                    // Make sure both attr and data are set
                    if (!$item.attr('data-public-id')) {
                        $item.attr('data-public-id', publicId || $img.attr('data-public-id') || $img.data('public-id'));
                    }
                    
                    if (!$item.attr('data-watermarked-url')) {
                        $item.attr('data-watermarked-url', $item.data('watermarked-url') || $img.attr('data-watermarked-url') || $img.data('watermarked-url'));
                    }
                    
                    if (!$item.attr('data-original-url')) {
                        $item.attr('data-original-url', $item.data('original-url') || $img.attr('data-original-url') || $img.data('original-url'));
                    }
                    
                    // Update selection states based on localStorage
                    if (storedIds.has(publicId)) {
                        $item.addClass('selected');
                    } else {
                        $item.removeClass('selected');
                    }
                });
                
                beautifulRescuesDebug.log('Gallery items updated with data attributes');
            }, 100);
            
            // Important: Do NOT update selectedImages or localStorage here
            // Only update the UI to reflect the current state
            updateSelectedImagesPreview();
            updateCartCount();
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