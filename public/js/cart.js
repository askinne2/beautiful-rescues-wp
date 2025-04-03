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
            .filter(img => img && img.id && img.url) // Filter out invalid entries
            .map(img => ({
                ...img,
                url: img.url.replace('http://', 'https://')
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
                        <img src="${image.url}" 
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
            const count = selectedImages.length;
            
            beautifulRescuesDebug.log('Updating cart count:', {
                count: count,
                selectedImages: selectedImages
            });

            if (cartCount.length) {
                cartCount.text(count);
            }
            updateCartVisibility(count > 0);
        }

        // Initialize cart visibility and count immediately
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
                updateCartCount();
            }
        });

        // Listen for selection changes from gallery
        $(document).on('beautifulRescuesSelectionChanged', function(e, data) {
            beautifulRescuesDebug.log('Selection changed event received:', data);
            
            if (data && data.selectedImages) {
                selectedImages = data.selectedImages.filter(img => img && img.id && img.url);
                localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImages));
                updateSelectedImagesPreview();
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
    });
})(jQuery); 