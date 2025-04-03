(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(function() {
        // Initialize debug utility
        if (typeof beautifulRescuesDebug !== 'undefined' && typeof beautifulRescuesDebugSettings !== 'undefined') {
            beautifulRescuesDebug.init(beautifulRescuesDebugSettings);
        }
        
        beautifulRescuesDebug.log('Gallery script initialized');

        const gallery = $('.beautiful-rescues-gallery');
        if (!gallery.length) {
            beautifulRescuesDebug.warn('Gallery element not found');
            return;
        }

        // Initialize variables
        const galleryGrid = $('.gallery-grid');
        const loadMoreButton = $('.load-more-button');
        const selectedImages = new Set();
        let currentPage = 1;
        let hasMoreImages = true;
        let isLoading = false;
        let currentCategory = '';
        let currentSort = 'random';
        let currentPerPage = 20;

        beautifulRescuesDebug.log('Initializing gallery');

        // Function to load images
        function loadImages(page = 1, append = false) {
            if (isLoading) return;
            isLoading = true;

            beautifulRescuesDebug.log('Initial state:', {
                currentPage: page,
                append: append,
                selectedImages: Array.from(selectedImages)
            });

            // Get initial data from data attributes
            currentCategory = gallery.data('category') || '';
            currentSort = gallery.data('sort') || 'random';
            currentPerPage = parseInt(gallery.data('per-page')) || 20;
            const totalImages = parseInt(gallery.data('total-images')) || 0;

            // Set initial hasMoreImages state
            hasMoreImages = $('.gallery-grid .gallery-item').length < totalImages;
            beautifulRescuesDebug.log('Initial state:', {
                currentItems: $('.gallery-grid .gallery-item').length,
                totalImages: totalImages,
                hasMoreImages: hasMoreImages
            });

            // Only load selections from localStorage on initial load, not when appending
            if (page === 1 && !append) {
                // Load existing selections from localStorage
                const storedImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]');
                selectedImages.clear(); // Clear the set first
                storedImages.forEach(img => {
                    if (img && img.id) {
                        selectedImages.add(img.id);
                    }
                });
                
                beautifulRescuesDebug.log('Loaded selections from localStorage:', {
                    storedImages: storedImages,
                    selectedImages: Array.from(selectedImages)
                });
            }
            
            // Update UI to reflect stored selections
            $('.gallery-item').each(function() {
                const imageId = $(this).data('public-id');
                if (selectedImages.has(imageId)) {
                    $(this).addClass('selected');
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
                    loadImages(currentPage, true);
                }
            });

            // Handle image selection
            $('.gallery-grid').on('click', '.select-button', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const item = $(this).closest('.gallery-item');
                const imageId = item.data('public-id');
                const img = item.find('img');
                
                beautifulRescuesDebug.log('Image selection clicked:', {
                    imageId,
                    imgData: {
                        src: img.attr('src'),
                        alt: img.attr('alt'),
                        width: img.data('width'),
                        height: img.data('height'),
                        url: img.data('url')
                    }
                });
                
                // Get current selections from localStorage to ensure we have the complete set
                const currentStoredImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]');
                const currentStoredIds = new Set(currentStoredImages.map(img => img.id).filter(Boolean));
                
                if (selectedImages.has(imageId)) {
                    selectedImages.delete(imageId);
                    currentStoredIds.delete(imageId);
                    item.removeClass('selected');
                } else {
                    selectedImages.add(imageId);
                    currentStoredIds.add(imageId);
                    item.addClass('selected');
                }
                
                $('.selected-count').text(selectedImages.size);
                
                // Update localStorage with standardized image data
                const selectedImagesArray = Array.from(currentStoredIds).map(id => {
                    // First check if we already have this image in localStorage
                    const existingImage = currentStoredImages.find(img => img.id === id);
                    if (existingImage) {
                        return existingImage;
                    }
                    
                    // If not, get it from the DOM
                    const imgElement = $('.gallery-item[data-public-id="' + id + '"] img');
                    const imageData = {
                        id: id,
                        filename: imgElement.attr('alt') || '',
                        width: imgElement.data('width') || '',
                        height: imgElement.data('height') || '',
                        url: imgElement.attr('src') || imgElement.data('url') || ''
                    };
                    
                    beautifulRescuesDebug.log('Processing image data:', {
                        id,
                        imageData,
                        element: imgElement[0]
                    });
                    
                    // Validate required fields
                    if (!imageData.id || !imageData.url) {
                        beautifulRescuesDebug.warn('Invalid image data:', imageData);
                        return null;
                    }
                    
                    return imageData;
                }).filter(Boolean); // Remove any null entries
                
                beautifulRescuesDebug.log('Storing selected images:', selectedImagesArray);
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

            // Load images from server
            $.ajax({
                url: beautifulRescuesGallery.ajaxurl,
                type: 'POST',
                data: {
                    action: 'load_gallery_images',
                    category: currentCategory,
                    sort: currentSort,
                    page: page,
                    per_page: currentPerPage,
                    nonce: beautifulRescuesGallery.nonce
                },
                success: function(response) {
                    beautifulRescuesDebug.log('Server response:', response);
                    
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
                            }
                        });
                        
                        // Update hasMoreImages flag based on total images count
                        hasMoreImages = $('.gallery-grid .gallery-item').length < totalImages;
                        beautifulRescuesDebug.log('hasMoreImages:', hasMoreImages);
                        
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
                    beautifulRescuesDebug.error('Error loading images:', error);
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

            // Generate responsive image URLs
            const responsiveUrls = {
                thumbnail: imageUrl.replace('/upload/', '/upload/w_200,c_scale/'),
                medium: imageUrl.replace('/upload/', '/upload/w_400,c_scale/'),
                large: imageUrl.replace('/upload/', '/upload/w_800,c_scale/'),
                full: imageUrl
            };

            // Create srcset string
            const srcset = Object.entries(responsiveUrls)
                .map(([size, url]) => `${url} ${size === 'thumbnail' ? '200w' : size === 'medium' ? '400w' : size === 'large' ? '800w' : '1600w'}`)
                .join(', ');

            return `
                <div class="gallery-item" data-public-id="${imageId}">
                    <div class="gallery-item-image">
                        <img src="${responsiveUrls.medium}"
                             srcset="${srcset}"
                             sizes="(max-width: 480px) 200px, (max-width: 768px) 400px, (max-width: 1200px) 800px, 1600px"
                             alt="${imageFilename}"
                             data-url="${imageUrl}"
                             data-width="${imageWidth}"
                             data-height="${imageHeight}"
                             loading="lazy">
                        <div class="gallery-item-actions">
                            <button class="gallery-item-button select-button" aria-label="Select image">
                                <svg class="radio-icon" viewBox="0 0 24 24" width="24" height="24">
                                    <circle class="radio-circle" cx="12" cy="12" r="10"/>
                                    <circle class="radio-dot" cx="12" cy="12" r="4"/>
                                </svg>
                            </button>
                            <button class="gallery-item-button zoom-button" aria-label="Zoom image">
                                <svg class="zoom-icon" viewBox="0 0 24 24" width="24" height="24">
                                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                                </svg>
                            </button>
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

        // Function to load more images
        function loadMoreImages($gallery, category, sort, page, perPage) {
            if (isLoading) return;
            isLoading = true;
            
            beautifulRescuesDebug.log('Loading more images:', {
                category,
                sort,
                page,
                perPage
            });
            
            // Show loading overlay
            $('.gallery-loading-overlay').addClass('active');
            
            // Load images from server
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
                    beautifulRescuesDebug.log('Server response:', response);
                    
                    if (response.success && response.data.images.length > 0) {
                        const images = response.data.images;
                        const totalImages = response.data.total_images || 0;
                        
                        // Get current selections from localStorage to ensure we have the complete set
                        const currentStoredImages = JSON.parse(localStorage.getItem('beautifulRescuesSelectedImages') || '[]');
                        const currentStoredIds = new Set(currentStoredImages.map(img => img.id).filter(Boolean));
                        
                        // Update selectedImages set with current stored IDs
                        selectedImages.clear();
                        currentStoredIds.forEach(id => selectedImages.add(id));
                        
                        // Add new images to grid
                        images.forEach(function(image) {
                            const item = createGalleryItem(image);
                            $('.gallery-grid').append(item);
                            
                            // Restore selection state for new images
                            if (selectedImages.has(image.id)) {
                                $(item).addClass('selected');
                            }
                        });
                        
                        // Update hasMoreImages flag based on total images count
                        hasMoreImages = $('.gallery-grid .gallery-item').length < totalImages;
                        beautifulRescuesDebug.log('hasMoreImages:', hasMoreImages);
                        
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
                    beautifulRescuesDebug.error('Error loading images:', error);
                    showToast('Failed to load images. Please try again.');
                },
                complete: function() {
                    isLoading = false;
                    $('.load-more-button').prop('disabled', false);
                    $('.gallery-loading-overlay').removeClass('active');
                }
            });
        }

        // Initialize the gallery by loading the first page of images
        loadImages(1, false);
    });
})(jQuery); 