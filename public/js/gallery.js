jQuery(document).ready(function($) {
    console.log('Gallery script initialized');
    
    // Initialize variables
    let currentPage = 1;
    let isLoading = false;
    let hasMoreImages = true;
    let selectedImages = new Set();
    let currentCategory = '';
    let currentSort = 'random';
    let currentPerPage = 20;
    
    // Initialize the gallery
    function initGallery() {
        console.log('Initializing gallery');
        
        // Add loading overlay to the page
        if (!$('.gallery-loading-overlay').length) {
            $('body').append('<div class="gallery-loading-overlay"><div class="gallery-spinner"></div></div>');
        }
        
        // Get initial data from data attributes
        const gallery = $('.beautiful-rescues-gallery');
        currentCategory = gallery.data('category') || '';
        currentSort = gallery.data('sort') || 'random';
        currentPerPage = parseInt(gallery.data('per-page')) || 20;
        const totalImages = parseInt(gallery.data('total-images')) || 0;

        // Set initial hasMoreImages state
        hasMoreImages = $('.gallery-grid .gallery-item').length < totalImages;
        console.log('Initial state:', {
            currentItems: $('.gallery-grid .gallery-item').length,
            totalImages: totalImages,
            hasMoreImages: hasMoreImages
        });

        // Load existing selections from localStorage
        const storedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]');
        storedImages.forEach(img => {
            if (img && img.id) {
                selectedImages.add(img.id);
            }
        });
        
        // Update UI to reflect stored selections
        $('.gallery-item').each(function() {
            const imageId = $(this).data('public-id');
            if (selectedImages.has(imageId)) {
                $(this).addClass('selected');
                $(this).find('.select-button').text(beautifulRescuesGallery.i18n.selected);
            }
        });
        $('.selected-count').text(selectedImages.size);
        
        // Handle sort changes
        $('.gallery-sort-select').on('change', function() {
            const $gallery = $(this).closest('.beautiful-rescues-gallery');
            const sort = $(this).val();
            const category = $gallery.data('category');
            const perPage = parseInt($gallery.data('per-page'));
            
            // Update gallery data attribute
            $gallery.data('sort', sort);
            
            // Reset page and clear existing images
            $gallery.data('page', 1);
            $gallery.find('.gallery-grid').empty();
            
            // Show loading overlay
            $('.gallery-loading-overlay').addClass('active');
            
            // Load images with new sort
            loadMoreImages($gallery, category, sort, 1, perPage);
        });

        // Handle load more
        $(document).on('click', '.load-more-button', function() {
            if (!isLoading && hasMoreImages) {
                currentPage++;
                loadImages(false);
            }
        });

        // Handle image selection
        $('.gallery-grid').on('click', '.select-button', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const item = $(this).closest('.gallery-item');
            const imageId = item.data('public-id');
            const img = item.find('img');
            
            console.log('Image selection clicked:', {
                imageId,
                imgData: {
                    src: img.attr('src'),
                    alt: img.attr('alt'),
                    width: img.data('width'),
                    height: img.data('height'),
                    url: img.data('url')
                }
            });
            
            if (selectedImages.has(imageId)) {
                selectedImages.delete(imageId);
                item.removeClass('selected');
                $(this).text(beautifulRescuesGallery.i18n.select);
            } else {
                selectedImages.add(imageId);
                item.addClass('selected');
                $(this).text(beautifulRescuesGallery.i18n.selected);
            }
            
            $('.selected-count').text(selectedImages.size);
            
            // Update localStorage with standardized image data
            const selectedImagesArray = Array.from(selectedImages).map(id => {
                const imgElement = $('.gallery-item[data-public-id="' + id + '"] img');
                const imageData = {
                    id: id,
                    filename: imgElement.attr('alt') || '',
                    width: imgElement.data('width') || '',
                    height: imgElement.data('height') || '',
                    url: imgElement.attr('src') || imgElement.data('url') || ''
                };
                
                console.log('Processing image data:', {
                    id,
                    imageData,
                    element: imgElement[0]
                });
                
                // Validate required fields
                if (!imageData.id || !imageData.url) {
                    console.warn('Invalid image data:', imageData);
                    return null;
                }
                
                return imageData;
            }).filter(Boolean); // Remove any null entries
            
            console.log('Storing selected images:', selectedImagesArray);
            localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImagesArray));
            
            // Trigger custom event for cart
            $(document).trigger('beautifulRescuesSelectionChanged', [{
                selectedImages: selectedImagesArray
            }]);
        });

        // Handle clear selection
        $('.clear-selection-button').on('click', function() {
            selectedImages.clear();
            $('.gallery-item').removeClass('selected');
            $('.select-button').text(beautifulRescuesGallery.i18n.select);
            $('.selected-count').text('0');
            
            // Clear localStorage
            localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify([]));
            
            // Trigger custom event for cart
            $(document).trigger('beautifulRescuesSelectionChanged', [{ selectedImages: [] }]);
        });

        // Handle zoom button
        $('.gallery-grid').on('click', '.zoom-button', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const item = $(this).closest('.gallery-item');
            const imageUrl = item.find('img').attr('src');
            const caption = item.find('.gallery-caption').text();
            
            openModal(imageUrl, caption);
        });

        // Add modal event handlers
        const $modal = $('.gallery-modal');
        const $modalClose = $modal.find('.modal-close');
        const $modalNavButtons = $modal.find('.modal-nav-button');

        // Close modal when clicking close button or outside the modal
        $modalClose.on('click', function() {
            $modal.fadeOut(300);
            $('body').removeClass('modal-open');
        });

        $modal.on('click', function(e) {
            if (e.target === this) {
                $modal.fadeOut(300);
                $('body').removeClass('modal-open');
            }
        });

        // Handle navigation
        $modalNavButtons.on('click', function() {
            const direction = parseInt($(this).data('direction'));
            navigateModal(direction);
        });

        // Handle keyboard navigation
        $(document).on('keydown', function(e) {
            if (!$modal.is(':visible')) return;
            
            if (e.key === 'ArrowLeft') {
                navigateModal(-1);
            } else if (e.key === 'ArrowRight') {
                navigateModal(1);
            } else if (e.key === 'Escape') {
                $modal.fadeOut(300);
                $('body').removeClass('modal-open');
            }
        });
    }

    // Load images from server
    function loadImages(reset = false) {
        if (isLoading) return;
        
        isLoading = true;
        const $loadMoreBtn = $('.load-more-button');
        $loadMoreBtn.prop('disabled', true);
        $('.gallery-loading-overlay').addClass('active');
        
        if (reset) {
            $('.gallery-grid').empty();
        }
        
        $.ajax({
            url: beautifulRescuesGallery.ajaxurl,
            type: 'POST',
            data: {
                action: 'load_gallery_images',
                category: currentCategory,
                sort: currentSort,
                page: currentPage,
                per_page: currentPerPage,
                nonce: beautifulRescuesGallery.nonce
            },
            success: function(response) {
                console.log('Server response:', response);
                
                if (response.success && response.data.images.length > 0) {
                    const images = response.data.images;
                    const totalImages = response.data.total_images || 0;
                    
                    // Add new images to grid
                    images.forEach(function(image) {
                        const item = createGalleryItem(image);
                        $('.gallery-grid').append(item);
                        
                        // Restore selection state for new images
                        if (selectedImages.has(image.id)) {
                            $(item).addClass('selected');
                            $(item).find('.select-button').text(beautifulRescuesGallery.i18n.selected);
                        }
                    });
                    
                    // Update hasMoreImages flag based on total images count
                    hasMoreImages = $('.gallery-grid .gallery-item').length < totalImages;
                    console.log('hasMoreImages:', hasMoreImages);
                    
                    // Show/hide load more button
                    if (hasMoreImages) {
                        if (!$('.load-more-button').length) {
                            const $newBtn = $('<button class="load-more-button">' + beautifulRescuesGallery.i18n.loadMore + '</button>');
                            $('.gallery-grid').after($newBtn);
                        }
                        $('.load-more-button').show();
                    } else {
                        $('.load-more-button').hide();
                    }
                } else {
                    hasMoreImages = false;
                    $('.load-more-button').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading images:', error);
                showToast('Failed to load images. Please try again.');
            },
            complete: function() {
                isLoading = false;
                $('.load-more-button').prop('disabled', false);
                $('.gallery-loading-overlay').removeClass('active');
            }
        });
    }

    // Create gallery item HTML
    function createGalleryItem(image) {
        const imageId = image.public_id || image.id;
        const imageUrl = image.url;
        const imageFilename = image.filename || '';
        const imageWidth = image.width || '';
        const imageHeight = image.height || '';

        return `
            <div class="gallery-item" data-public-id="${imageId}">
                <div class="gallery-item-image">
                    <img src="${imageUrl}" 
                         alt="${imageFilename}" 
                         loading="lazy" 
                         data-width="${imageWidth}" 
                         data-height="${imageHeight}"
                         data-url="${imageUrl}">
                </div>
                <div class="gallery-item-overlay">
                    <div class="gallery-item-actions">
                        <button class="gallery-item-button select-button">${beautifulRescuesGallery.i18n.select}</button>
                        <button class="gallery-item-button zoom-button">${beautifulRescuesGallery.i18n.zoom}</button>
                    </div>
                </div>
            </div>
        `;
    }

    // Modal functions
    let currentModalIndex = 0;
    let modalImages = [];

    function openModal(imageUrl, caption) {
        currentModalIndex = $('.gallery-item').filter(function() {
            return $(this).find('img').attr('src') === imageUrl;
        }).index();
        
        modalImages = $('.gallery-item').map(function() {
            return {
                url: $(this).find('img').attr('src'),
                caption: $(this).find('.gallery-caption').text()
            };
        }).get();
        
        $('.modal-image').attr('src', imageUrl);
        $('.modal-caption').text(caption);
        $('.gallery-modal').fadeIn(300);
        $('body').addClass('modal-open');
        
        updateModalNavigation();
    }

    function closeModal() {
        $('.gallery-modal').fadeOut(300);
        $('body').removeClass('modal-open');
    }

    function navigateModal(direction) {
        currentModalIndex = (currentModalIndex + direction + modalImages.length) % modalImages.length;
        const image = modalImages[currentModalIndex];
        
        $('.modal-image').attr('src', image.url);
        $('.modal-caption').text(image.caption);
        
        updateModalNavigation();
    }

    function updateModalNavigation() {
        $('.modal-nav-button[data-direction="-1"]').toggle(currentModalIndex > 0);
        $('.modal-nav-button[data-direction="1"]').toggle(currentModalIndex < modalImages.length - 1);
    }

    // Update loadMoreImages function to handle sorting
    function loadMoreImages($gallery, category, sort, page, perPage) {
        $.ajax({
            url: beautifulRescuesGallery.ajaxurl,
            type: 'POST',
            data: {
                action: 'load_gallery_images',
                category: category,
                sort: sort,
                page: page,
                per_page: perPage,
                nonce: beautifulRescuesGallery.nonce
            },
            success: function(response) {
                if (response.success) {
                    const images = response.data.images;
                    const hasMore = response.data.has_more;
                    const $grid = $gallery.find('.gallery-grid');
                    
                    // Append new images
                    images.forEach(function(image) {
                        $grid.append(createGalleryItem(image));
                    });
                    
                    // Update page number
                    $gallery.data('page', page + 1);
                    
                    // Show/hide load more button
                    const $loadMoreButton = $gallery.find('.load-more-button');
                    if (hasMore) {
                        $loadMoreButton.show();
                    } else {
                        $loadMoreButton.hide();
                    }
                }
            },
            error: function() {
                $gallery.find('.gallery-grid').append('<div class="gallery-error">Error loading images. Please try again.</div>');
            },
            complete: function() {
                // Hide loading overlay
                $('.gallery-loading-overlay').removeClass('active');
            }
        });
    }

    // Initialize the gallery
    initGallery();
}); 