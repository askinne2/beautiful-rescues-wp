(function($) {
    'use strict';

    const cart = $('.beautiful-rescues-cart');
    if (!cart.length) return;

    // Initialize variables
    const cartButton = cart.find('.cart-button');
    const cartCount = cart.find('.cart-count');
    let selectedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]');

    // Function to update cart count
    function updateCartCount() {
        const selectedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]');
        const count = selectedImages.length;
        cartCount.text(count);
        cartButton.toggleClass('hidden', count === 0);
    }

    // Function to open donation modal
    function openDonationModal() {
        // Check if there are any selected images
        if (!selectedImages.length) {
            alert(beautifulRescuesCart.i18n.noImagesSelected);
            return;
        }

        // Redirect to checkout page
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
            updateCartCount();
        }
    });

    // Listen for custom event from gallery
    $(document).on('beautifulRescuesSelectionChanged', function() {
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