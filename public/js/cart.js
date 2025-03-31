(function($) {
    'use strict';

    // Initialize variables
    const cart = $('.beautiful-rescues-cart');
    const cartButton = $('.cart-button'); // Changed to search globally
    const cartCount = $('.cart-count'); // Changed to search globally
    let selectedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]');

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

    // Function to update cart count
    function updateCartCount() {
        const selectedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]')
            .map(img => ({
                ...img,
                url: img.url.replace('http://', 'https://')
            }));
        const count = selectedImages.length;
        console.log('Updating cart count:', {
            count: count,
            selectedImages: selectedImages,
            localStorage: localStorage.getItem('beautifulRescuesSelectedImages')
        });

        // Update localStorage with HTTPS URLs
        localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImages));

        // Update all cart count elements on the page
        $('.cart-count').text(count);
        $('.cart-button').toggleClass('hidden', count === 0);
    }

    // Function to open donation modal
    function openDonationModal() {
        // Refresh selected images from localStorage and ensure HTTPS URLs
        selectedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]')
            .map(img => ({
                ...img,
                url: img.url.replace('http://', 'https://')
            }));
        console.log('Opening donation modal:', {
            selectedImages: selectedImages,
            count: selectedImages.length,
            cartButtonHidden: cartButton.hasClass('hidden')
        });

        // Update localStorage with HTTPS URLs
        localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImages));

        // Check if there are any selected images
        if (!selectedImages.length) {
            console.log('No items in cart, showing toast notification');
            showToast('You have no items in your cart');
            return;
        }

        // Redirect to checkout page
        console.log('Redirecting to checkout:', beautifulRescuesCart.checkoutUrl);
        window.location.href = beautifulRescuesCart.checkoutUrl;
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
                    alert(beautifulRescuesCart.i18n.thankYou);
                    selectedImages = [];
                    localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImages));
                    updateCartCount();
                    closeModal($form.closest('.donation-modal'));
                } else {
                    alert(response.data?.message || beautifulRescuesCart.i18n.error);
                }
            },
            error: function() {
                alert(beautifulRescuesCart.i18n.error);
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
            console.log('Storage changed in another window:', {
                oldValue: e.originalEvent.oldValue,
                newValue: e.originalEvent.newValue
            });
            updateCartCount();
        }
    });

    // Listen for custom event from gallery
    $(document).on('beautifulRescuesSelectionChanged', function() {
        console.log('Selection changed event received');
        selectedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]');
        console.log('Cart state after selection change:', {
            selectedImages: selectedImages,
            count: selectedImages.length,
            localStorage: localStorage.getItem('beautifulRescuesSelectedImages')
        });
        updateCartCount();
    });

    // Initialize cart count
    updateCartCount();

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
        console.log('Checkout button clicked', {
            selectedImages: selectedImages,
            localStorage: localStorage.getItem('beautifulRescuesSelectedImages'),
            checkoutUrl: beautifulRescuesCart.checkoutUrl
        });
        
        // Check if there are any selected images
        if (!selectedImages.length) {
            console.warn('No images selected, preventing checkout');
            alert(beautifulRescuesCart.i18n.noImagesSelected);
            return;
        }
        
        console.log('Redirecting to checkout page');
        window.location.href = beautifulRescuesCart.checkoutUrl;
    });

})(jQuery); 