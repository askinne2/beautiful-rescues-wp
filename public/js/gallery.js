(function($) {
    'use strict';

    const gallery = $('.beautiful-rescues-gallery');
    if (!gallery.length) return;

    // Initialize variables
    const modal = $('.gallery-modal');
    const donationModal = $('.donation-modal');
    const modalImage = $('.modal-image');
    const modalClose = $('.modal-close');
    const modalPrev = $('.modal-prev');
    const modalNext = $('.modal-next');
    const loadMoreBtn = $('.load-more-button');
    const verifyDonationBtn = $('.verify-donation-button');
    const sortSelect = $('#gallery-sort');
    const galleryGrid = $('.gallery-grid');
    const selectedImagesGrid = $('.selected-images-grid');
    const donationForm = $('#donation-verification-form');

    let currentPage = parseInt(gallery.data('page'));
    let currentSort = gallery.data('sort');
    let currentCategory = gallery.data('category');
    let isLoading = false;
    let hasMoreImages = true;
    let selectedImages = [];

    // Initialize gallery functionality
    function initGallery() {
        // Add lazy loading
        gallery.find('img').each(function() {
            $(this).attr('loading', 'lazy');
        });

        // Bind all event handlers
        bindEventHandlers();
    }

    // Bind all event handlers
    function bindEventHandlers() {
        // Handle select button clicks
        gallery.off('click', '.select-button').on('click', '.select-button', function(e) {
            e.stopPropagation();
            const $item = $(this).closest('.gallery-item');
            const publicId = $item.data('public-id');
            const imageUrl = $item.find('img').data('url');
            
            if ($item.hasClass('selected')) {
                $item.removeClass('selected');
                selectedImages = selectedImages.filter(img => img.id !== publicId);
            } else {
                $item.addClass('selected');
                selectedImages.push({ id: publicId, url: imageUrl });
            }
            
            updateSelectedImagesPreview();
            verifyDonationBtn.toggle(selectedImages.length > 0);
        });

        // Handle zoom button clicks
        gallery.off('click', '.zoom-button').on('click', '.zoom-button', function(e) {
            e.stopPropagation();
            const $item = $(this).closest('.gallery-item');
            const publicId = $item.data('public-id');
            const imageUrl = $item.find('img').data('url');
            openModal(publicId, imageUrl);
        });

        // Handle remove image from selection
        selectedImagesGrid.off('click', '.selected-image-remove').on('click', '.selected-image-remove', function() {
            const publicId = $(this).data('public-id');
            $(`.gallery-item[data-public-id="${publicId}"]`).removeClass('selected');
            selectedImages = selectedImages.filter(img => img.id !== publicId);
            updateSelectedImagesPreview();
            verifyDonationBtn.toggle(selectedImages.length > 0);
        });

        // Handle verify donation button
        verifyDonationBtn.off('click').on('click', function() {
            openDonationModal();
        });

        // Handle sort change
        sortSelect.off('change').on('change', function() {
            currentSort = $(this).val();
            currentPage = 1;
            hasMoreImages = true;
            galleryGrid.empty();
            loadMoreBtn.show();
            loadImages();
        });

        // Handle load more
        loadMoreBtn.off('click').on('click', function() {
            if (!isLoading && hasMoreImages) {
                currentPage++;
                loadImages();
            }
        });

        // Handle modal navigation
        modalPrev.off('click').on('click', function() {
            navigateModal('prev');
        });

        modalNext.off('click').on('click', function() {
            navigateModal('next');
        });

        // Handle modal close
        modalClose.off('click').on('click', function() {
            const $modal = $(this).closest('.gallery-modal, .donation-modal');
            closeModal($modal);
        });

        // Handle form submission
        donationForm.off('submit').on('submit', function(e) {
            e.preventDefault();
            handleFormSubmission();
        });
    }

    // Update selected images preview
    function updateSelectedImagesPreview() {
        selectedImagesGrid.empty();
        selectedImages.forEach(function(image) {
            if (!image.url) {
                console.warn('Missing URL for image:', image);
                return;
            }
            // Ensure URL is HTTPS
            const secureUrl = image.url.replace('http://', 'https://');
            const imageHtml = `
                <div class="selected-image-item" data-public-id="${image.id}">
                    <img src="${secureUrl}" alt="Selected image">
                    <button class="selected-image-remove" data-public-id="${image.id}">&times;</button>
                </div>
            `;
            selectedImagesGrid.append(imageHtml);
        });
    }

    // Modal functions
    function openModal(publicId, imageUrl) {
        // Ensure URL is HTTPS
        const secureUrl = imageUrl.replace('http://', 'https://');
        modalImage.attr('src', secureUrl);
        modalImage.attr('data-public-id', publicId);
        modal.fadeIn();
        $('body').addClass('modal-open');
    }

    function openDonationModal() {
        donationModal.fadeIn();
        $('body').addClass('modal-open');
    }

    function closeModal($modal) {
        $modal.fadeOut();
        if (!$('.gallery-modal:visible, .donation-modal:visible').length) {
            $('body').removeClass('modal-open');
        }
    }

    function navigateModal(direction) {
        const currentItem = $('.gallery-item[data-public-id="' + modalImage.attr('data-public-id') + '"]');
        let nextItem;

        if (direction === 'prev') {
            nextItem = currentItem.prev('.gallery-item');
            if (!nextItem.length) {
                nextItem = $('.gallery-item').last();
            }
        } else {
            nextItem = currentItem.next('.gallery-item');
            if (!nextItem.length) {
                nextItem = $('.gallery-item').first();
            }
        }

        const nextImageUrl = nextItem.find('img').data('url');
        const nextPublicId = nextItem.data('public-id');

        // Ensure URL is HTTPS
        const secureUrl = nextImageUrl.replace('http://', 'https://');
        modalImage.attr('src', secureUrl);
        modalImage.attr('data-public-id', nextPublicId);
    }

    // Load images via AJAX
    function loadImages() {
        if (isLoading) return;
        isLoading = true;

        $.ajax({
            url: beautifulRescuesGallery.ajaxurl,
            type: 'POST',
            data: {
                action: 'load_gallery_images',
                nonce: beautifulRescuesGallery.nonce,
                category: currentCategory,
                sort: currentSort,
                page: currentPage,
                per_page: gallery.data('per-page')
            },
            success: function(response) {
                if (response.success) {
                    appendImages(response.data.images);
                    hasMoreImages = response.data.has_more;
                    if (!hasMoreImages) {
                        loadMoreBtn.hide();
                    }
                    // Rebind event handlers after loading new images
                    bindEventHandlers();
                }
                isLoading = false;
            },
            error: function() {
                isLoading = false;
            }
        });
    }

    // Append new images to the gallery
    function appendImages(images) {
        images.forEach(function(image) {
            // Ensure URL is HTTPS
            const imageUrl = image.url ? image.url.replace('http://', 'https://') : '';
            const imageHtml = `
                <div class="gallery-item" data-public-id="${image.public_id}">
                    <div class="gallery-item-image">
                        <img src="${imageUrl}" alt="${image.filename}" data-url="${imageUrl}">
                        <div class="gallery-item-overlay">
                            <div class="gallery-item-actions">
                                <button class="gallery-item-button select-button">
                                    ${selectedImages.some(img => img.id === image.public_id) ? 'Selected' : 'Select'}
                                </button>
                                <button class="gallery-item-button zoom-button">Zoom</button>
                            </div>
                        </div>
                    </div>
                    ${image.caption ? `<div class="gallery-caption">${image.caption}</div>` : ''}
                </div>
            `;
            galleryGrid.append(imageHtml);
        });
    }

    // Handle form submission
    function handleFormSubmission() {
        // Prevent multiple submissions
        if (donationForm.data('submitting')) {
            return;
        }
        donationForm.data('submitting', true);

        // Add submitting class to form
        donationForm.addClass('submitting');

        // Log form data for debugging
        console.log('Form submission started', {
            selectedImages: selectedImages,
            formData: new FormData(donationForm[0])
        });

        // Validate required fields
        const requiredFields = ['firstName', 'lastName', 'email', 'phone', 'donationVerification'];
        let missingFields = [];
        
        requiredFields.forEach(field => {
            const input = donationForm.find(`[name="${field}"]`);
            if (!input.val()) {
                missingFields.push(field);
                input.addClass('error');
            } else {
                input.removeClass('error');
            }
        });

        if (missingFields.length > 0) {
            console.error('Missing required fields:', missingFields);
            alert('Please fill in all required fields');
            donationForm.removeClass('submitting');
            donationForm.data('submitting', false);
            return;
        }

        // Validate email format
        const emailInput = donationForm.find('[name="email"]');
        if (!emailInput.val().match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
            console.error('Invalid email format:', emailInput.val());
            alert('Please enter a valid email address');
            emailInput.addClass('error');
            donationForm.removeClass('submitting');
            donationForm.data('submitting', false);
            return;
        }

        // Validate phone format
        const phoneInput = donationForm.find('[name="phone"]');
        if (!phoneInput.val().match(/^\+?[1-9]\d{1,14}$/)) {
            console.error('Invalid phone format:', phoneInput.val());
            alert('Please enter a valid phone number');
            phoneInput.addClass('error');
            donationForm.removeClass('submitting');
            donationForm.data('submitting', false);
            return;
        }

        // Validate file size
        const fileInput = donationForm.find('[name="donationVerification"]');
        const file = fileInput[0].files[0];
        if (file && file.size > beautifulRescuesGallery.maxFileSize) {
            console.error('File too large:', file.size, 'Max size:', beautifulRescuesGallery.maxFileSize);
            alert(`File size must be less than ${beautifulRescuesGallery.maxFileSize / (1024 * 1024)}MB`);
            fileInput.addClass('error');
            donationForm.removeClass('submitting');
            donationForm.data('submitting', false);
            return;
        }

        // Validate file type
        if (file && !file.type.match('image.*|application/pdf')) {
            console.error('Invalid file type:', file.type);
            alert('Please upload an image or PDF file');
            fileInput.addClass('error');
            donationForm.removeClass('submitting');
            donationForm.data('submitting', false);
            return;
        }

        // Create FormData object
        const formData = new FormData();
        
        // Add form fields
        donationForm.serializeArray().forEach(item => {
            formData.append(item.name, item.value);
        });

        // Add file if exists
        if (file) {
            formData.append('donationVerification', file);
        }

        // Add action and nonce
        formData.append('action', 'submit_donation_verification');
        formData.append('nonce', beautifulRescuesGallery.nonce);

        // Add selected images
        formData.append('selected_images', JSON.stringify(selectedImages.map(img => ({
            id: img.id,
            url: img.url.replace('http://', 'https://')
        }))));

        // Log the final form data
        console.log('Submitting form data:', {
            action: 'submit_donation_verification',
            nonce: beautifulRescuesGallery.nonce,
            selectedImages: selectedImages,
            formFields: Object.fromEntries(formData.entries())
        });

        $.ajax({
            url: beautifulRescuesGallery.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('Form submission response:', response);
                if (response.success) {
                    alert('Thank you for your donation! We will review your verification and get back to you soon.');
                    if (response.redirect) {
                        window.location.href = response.redirect;
                    } else {
                        donationForm[0].reset();
                        closeModal(donationModal);
                        selectedImages = [];
                        $('.gallery-item').removeClass('selected');
                        verifyDonationBtn.hide();
                        updateSelectedImagesPreview();
                    }
                } else {
                    console.error('Form submission failed:', response);
                    alert(response.data?.message || 'There was an error submitting your donation. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Form submission error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText,
                    statusText: xhr.statusText
                });
                alert('There was an error submitting your donation. Please try again.');
            },
            complete: function() {
                donationForm.removeClass('submitting');
                donationForm.data('submitting', false);
            }
        });
    }

    // Add input validation on blur
    function bindFormValidation() {
        donationForm.find('input, textarea').on('blur', function() {
            const $input = $(this);
            const field = $input.attr('name');
            
            if ($input.prop('required')) {
                if (!$input.val()) {
                    $input.addClass('error');
                } else {
                    $input.removeClass('error');
                }
            }

            // Email validation
            if (field === 'email' && $input.val()) {
                if (!$input.val().match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                    $input.addClass('error');
                } else {
                    $input.removeClass('error');
                }
            }

            // Phone validation
            if (field === 'phone' && $input.val()) {
                if (!$input.val().match(/^\+?[1-9]\d{1,14}$/)) {
                    $input.addClass('error');
                } else {
                    $input.removeClass('error');
                }
            }
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        initGallery();
        bindFormValidation();
    });

    // Close modal on escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal($('.gallery-modal, .donation-modal:visible'));
        }
    });

})(jQuery); 