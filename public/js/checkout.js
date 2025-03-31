(function($) {
    'use strict';

    console.log('Checkout page script initialized');

    const checkout = $('.checkout-page');
    if (!checkout.length) {
        console.warn('Checkout page element not found');
        return;
    }

    // Initialize variables
    const selectedImagesGrid = $('.selected-images-grid');
    const donationForm = $('#donation-verification-form');
    
    console.log('Checkout page elements found:', {
        checkoutPage: checkout.length > 0,
        selectedImagesGrid: selectedImagesGrid.length > 0,
        donationForm: donationForm.length > 0
    });

    // Get selected images from localStorage
    const localStorageData = localStorage.getItem('beautifulRescuesSelectedImages');
    console.log('LocalStorage data:', localStorageData);
    
    let selectedImages = [];
    try {
        selectedImages = JSON.parse(localStorageData || '[]');
        console.log('Parsed selected images:', selectedImages);
    } catch (error) {
        console.error('Error parsing localStorage data:', error);
    }

    // Function to update selected images preview
    function updateSelectedImagesPreview() {
        console.log('Updating selected images preview', {
            selectedImagesCount: selectedImages.length,
            selectedImages: selectedImages
        });
        
        selectedImagesGrid.empty();
        
        if (!selectedImages.length) {
            console.log('No images selected, showing empty state');
            selectedImagesGrid.html('<p class="no-images">' + beautifulRescuesCheckout.i18n.noImages + '</p>');
            return;
        }

        selectedImages.forEach(function(image, index) {
            console.log(`Processing image ${index + 1}:`, image);
            
            if (!image.url) {
                console.warn('Missing URL for image:', image);
                return;
            }

            // Ensure URL is HTTPS and add watermark
            const watermarkId = beautifulRescuesCheckout.watermarkUrl.match(/\/v\d+\/([^\/]+)\.(webp|png|jpg|jpeg)$/)?.[1] || 'br-watermark-2025_2x_uux1x2';
            console.log('Watermark ID:', watermarkId);
            
            const secureUrl = image.url
                .replace('http://', 'https://')
                .replace('/upload/', `/upload/c_fill,w_800,h_800,q_auto,f_auto/l_${watermarkId},w_0.2,o_50,fl_relative/fl_tiled.layer_apply/`);
            console.log('Generated secure URL:', secureUrl);
            
            const imageHtml = `
                <div class="selected-image-item" data-public-id="${image.id}">
                    <img src="${secureUrl}" alt="Selected image">
                    <button class="selected-image-remove" data-public-id="${image.id}">&times;</button>
                </div>
            `;
            selectedImagesGrid.append(imageHtml);
        });
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

    // Bind remove image buttons
    selectedImagesGrid.on('click', '.selected-image-remove', function() {
        const publicId = $(this).data('public-id');
        selectedImages = selectedImages.filter(img => img.id !== publicId);
        localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImages));
        $(this).closest('.selected-image-item').remove();
    });

    // Bind form submission
    donationForm.on('submit', handleFormSubmission);

    // Initialize selected images preview
    console.log('Initializing selected images preview');
    updateSelectedImagesPreview();

})(jQuery); 