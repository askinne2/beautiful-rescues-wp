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
        let currentCategory = gallery.data('category') || '';
        let currentSort = gallery.data('sort') || 'random';
        let currentPerPage = parseInt(gallery.data('per-page')) || 20;

        beautifulRescuesDebug.log('Initializing gallery', {
            category: currentCategory,
            sort: currentSort,
            perPage: currentPerPage
        });

        // Create variables to track click timestamps and processing state
        let lastClickTime = 0;
        const debounceTime = 500; // milliseconds
        let isProcessingSelection = false;

        // Track if we've handled a touch event to prevent duplicate handling
        let touchHandled = false;

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
            const currentItems = $('.gallery-grid .gallery-item').length;
            hasMoreImages = currentItems < totalImages;
            beautifulRescuesDebug.log('hasMoreImages:', {
                currentCount: currentItems,
                hasMore: hasMoreImages,
                totalImages: totalImages
            });

            // Show/hide load more button based on hasMoreImages
            if (hasMoreImages) {
                loadMoreButton.show();
            } else {
                loadMoreButton.hide();
            }

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
                
                beautifulRescuesDebug.log('Sort changed:', {
                    sort: sort,
                    category: category,
                    perPage: perPage
                });
                
                // Update gallery data attribute
                $gallery.data('sort', sort);
                
                // Reset page and clear existing images
                currentPage = 1;
                $gallery.find('.gallery-grid').empty();
                
                // Show loading overlay
                $('.gallery-loading-overlay').addClass('active');
                
                // Load images with new sort
                loadImages(1, false);
            });

            // Handle load more
            $(document).on('click', '.load-more-button', function() {
                if (!isLoading && hasMoreImages) {
                    currentPage++;
                    loadImages(currentPage, true);
                }
            });

            // Handle touch events first (they fire before click events on mobile)
            $(document).on('touchstart', '.select-button', function(e) {
                // Mark that we've handled a touch to prevent the click handler from also firing
                touchHandled = true;
                
                // Clear touchHandled flag after a delay (reset for next interaction)
                setTimeout(function() {
                    touchHandled = false;
                }, debounceTime + 100);
                
                // Process the selection (same logic as click handler)
                handleSelectionEvent(e, this);
            });

            // Handle mouse click events
            $(document).on('click', '.select-button', function(e) {
                // Skip if this was already handled by the touch event
                if (touchHandled) {
                    return false;
                }
                
                // Process the selection
                handleSelectionEvent(e, this);
            });

            // Shared function to handle selection events (from either touch or click)
            function handleSelectionEvent(e, buttonElement) {
                // Stop the event immediately
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                // Skip if we're already processing a selection
                if (isProcessingSelection) {
                    beautifulRescuesDebug.log('gallery.js - Selection already in progress, ignoring');
                    return false;
                }
                
                // Debounce protection - prevent double clicks
                const now = new Date().getTime();
                if (now - lastClickTime < debounceTime) {
                    beautifulRescuesDebug.log('gallery.js - Debounced event, ignoring');
                    return false;
                }
                lastClickTime = now;
                
                // Set processing flag
                isProcessingSelection = true;
                
                const item = $(buttonElement).closest('.gallery-item');
                const imageId = item.attr('data-public-id') || item.data('public-id');
                const img = item.find('img');
                
                if (!imageId) {
                    beautifulRescuesDebug.warn('gallery.js - Missing image ID on selection click', item);
                    isProcessingSelection = false;
                    return false;
                }
                
                beautifulRescuesDebug.log('gallery.js - Image selection clicked:', {
                    imageId,
                    imgData: {
                        src: img.attr('src'),
                        alt: img.attr('alt'),
                        width: img.data('width'),
                        height: img.data('height'),
                        url: img.data('url')
                    }
                });
                
                // Get current selections from localStorage
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
                
                // Create a copy of the current selected images array to avoid reference issues
                const selectedImagesArray = Array.from(currentStoredIds).map(id => {
                    // First check if we already have this image in localStorage
                    const existingImage = currentStoredImages.find(img => img.id === id);
                    if (existingImage) {
                        return existingImage;
                    }
                    
                    // If not, get it from the DOM
                    const imgElement = $('.gallery-item[data-public-id="' + id + '"] img');
                    
                    // Get the src as fallback for any missing URLs
                    const imgSrc = imgElement.attr('src') || '';
                    const imgUrl = imgElement.data('url') || imgSrc;
                    
                    const imageData = {
                        id: id,
                        filename: imgElement.attr('alt') || '',
                        width: imgElement.data('width') || '',
                        height: imgElement.data('height') || '',
                        watermarked_url: imgElement.data('watermarked-url') || imgSrc,
                        original_url: imgElement.data('original-url') || imgUrl
                    };
                    
                    // Validate ID only - URLs will fall back to src
                    if (!imageData.id) {
                        beautifulRescuesDebug.warn('gallery.js - Invalid image data - missing ID:', imageData);
                        return null;
                    }
                    
                    // Log warning but don't reject if URLs are using fallbacks
                    if (!imgElement.data('watermarked-url') || !imgElement.data('original-url')) {
                        beautifulRescuesDebug.warn('gallery.js - Using fallback URLs for image:', {
                            id: imageData.id,
                            filename: imageData.filename,
                            fallbackSrc: imgSrc
                        });
                    }
                    
                    return imageData;
                }).filter(Boolean); // Remove any null entries
                
                beautifulRescuesDebug.log('gallery.js - Storing selected images:', selectedImagesArray);
                
                // Store a clone of the data to prevent reference issues
                localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify(selectedImagesArray));
                
                // Update the selected count display
                $('.selected-count').text(selectedImages.size);
                
                // Trigger custom event for cart
                $(document).trigger('beautifulRescuesSelectionChanged', [{
                    selectedImages: selectedImagesArray.slice() // Create a copy to prevent reference issues
                }]);
                
                // Reset processing flag after a short delay
                setTimeout(function() {
                    isProcessingSelection = false;
                    beautifulRescuesDebug.log('gallery.js - Selection processing complete');
                }, 100);
                
                return false;
            }

            // Handle clear selection
            $('.clear-selection-button').on('click', function() {
                selectedImages.clear();
                $('.gallery-item').removeClass('selected');
                $('.selected-count').text('0');
                
                // Clear localStorage
                localStorage.setItem('beautifulRescuesSelectedImages', JSON.stringify([]));
                
                // Trigger custom event for cart
                $(document).trigger('beautifulRescuesSelectionChanged', [{ 
                    selectedImages: [] 
                }]);
            });

            // Handle zoom button
            $('.gallery-grid').on('click', '.zoom-button', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const item = $(this).closest('.gallery-item');
                const img = item.find('img');
                // Use data-watermarked-url if available, fall back to src
                const imageUrl = img.data('watermarked-url') || img.attr('src');
                // Clean URL to avoid parameters causing issues
                const cleanImageUrl = imageUrl.includes('?') ? 
                    imageUrl.split('?')[0] : 
                    imageUrl;
                const caption = item.find('.gallery-caption').text();
                
                beautifulRescuesDebug.log('Opening modal with image:', {
                    imageUrl: cleanImageUrl,
                    fromAttribute: img.data('watermarked-url') ? 'data-watermarked-url' : 'src'
                });
                
                openModal(cleanImageUrl, caption);
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
                    if (response.success && response.data) {
                        beautifulRescuesDebug.log('Server response:', response.data);
                        
                        // Update total images count from response
                        const totalImages = response.data.total_images || 0;
                        gallery.data('total-images', totalImages);
                        
                        // Process new images
                        const newImages = response.data.images || [];
                        beautifulRescuesDebug.log('Processing images:', newImages);
                        
                        // Get current selections before adding new images
                        beautifulRescuesDebug.log('Selection state before adding images:', {
                            selectedImages: Array.from(selectedImages)
                        });
                        
                        // Track existing items to prevent duplicates
                        const existingItems = new Set();
                        $('.gallery-grid .gallery-item').each(function() {
                            existingItems.add($(this).data('public-id'));
                        });
                        
                        beautifulRescuesDebug.log('Existing items:', Array.from(existingItems));
                        
                        // Add new images to the grid
                        newImages.forEach(function(image) {
                            if (existingItems.has(image.public_id)) {
                                beautifulRescuesDebug.log('Skipping duplicate image:', image.public_id);
                                return;
                            }
                            
                            const item = $(createGalleryItem(image));
                            if (append) {
                                galleryGrid.append(item);
                            } else {
                                galleryGrid.prepend(item);
                            }
                            
                            // Check if this image was previously selected
                            if (selectedImages.has(image.public_id)) {
                                item.addClass('selected');
                            }
                        });
                        
                        // Update hasMoreImages state
                        const currentItems = $('.gallery-grid .gallery-item').length;
                        hasMoreImages = currentItems < totalImages;
                        
                        beautifulRescuesDebug.log('Gallery state after adding images:', {
                            currentItems: currentItems,
                            totalImages: totalImages,
                            hasMoreImages: hasMoreImages
                        });
                        
                        // Show/hide load more button
                        if (hasMoreImages) {
                            loadMoreButton.show();
                        } else {
                            loadMoreButton.hide();
                        }
                        
                        beautifulRescuesDebug.log('Gallery updated with new items:', {
                            added: newImages.length,
                            total: currentItems,
                            hasMore: hasMoreImages
                        });
                        
                        // Update cart count and preview
                        if (typeof beautifulRescuesCart !== 'undefined') {
                            beautifulRescuesCart.updateCartCount();
                            beautifulRescuesCart.updateSelectedImagesPreview();
                        }
                    }
                },
                complete: function() {
                    isLoading = false;
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
            const watermarkedUrl = image.watermarked_url || imageUrl;
            const originalUrl = image.original_url || imageUrl;

            // Extract filename from URL if not provided
            let displayFilename = imageFilename;
            if (!displayFilename && image.asset_folder) {
                beautifulRescuesDebug.log('Processing asset_folder for filename', {
                    asset_folder: image.asset_folder,
                    imageData: image
                });
                
                // Split by forward slashes and get the last part
                const parts = image.asset_folder.split('/');
                displayFilename = parts[parts.length - 1];
                beautifulRescuesDebug.log('Final filename', {
                    filename: displayFilename,
                    parts: parts
                });
            }

            // Calculate aspect ratio
            const aspectRatio = imageHeight && imageWidth ? imageHeight / imageWidth : 1;
            const isPortrait = aspectRatio > 1;
            const isLandscape = aspectRatio < 1;

            // Generate responsive image URLs with aspect ratio consideration
            const responsiveUrls = {
                thumbnail: imageUrl.replace('/upload/', `/upload/w_${isPortrait ? '200' : '300'},c_scale/`),
                medium: imageUrl.replace('/upload/', `/upload/w_${isPortrait ? '400' : '600'},c_scale/`),
                large: imageUrl.replace('/upload/', `/upload/w_${isPortrait ? '800' : '1200'},c_scale/`),
                full: imageUrl
            };

            // Create srcset string with proper width descriptors
            const srcset = [
                `${responsiveUrls.thumbnail} 200w`,
                `${responsiveUrls.medium} 400w`,
                `${responsiveUrls.large} 800w`,
                `${responsiveUrls.full} 1600w`
            ].join(', ');

            // Standardize Cloudinary URL to avoid issues with parameters
            const cleanImageUrl = imageUrl.includes('?') ? 
                imageUrl.split('?')[0] : 
                imageUrl;

            // Determine sizes based on aspect ratio
            const sizes = isPortrait 
                ? '(max-width: 480px) 150px, (max-width: 768px) 200px, 250px'
                : '(max-width: 480px) 200px, (max-width: 768px) 300px, 400px';

            return `
                <div class="gallery-item" data-public-id="${imageId}" data-aspect-ratio="${aspectRatio}" data-width="${imageWidth}" data-height="${imageHeight}" data-watermarked-url="${watermarkedUrl}" data-original-url="${originalUrl}">
                    <div class="gallery-item-skeleton" style="padding-top: ${(aspectRatio * 100).toFixed(2)}%"></div>
                    <div class="gallery-item-image">
                        <img src="${responsiveUrls.medium}"
                             srcset="${srcset}"
                             sizes="${sizes}"
                             alt="${displayFilename}"
                             data-url="${cleanImageUrl}"
                             data-watermarked-url="${watermarkedUrl}"
                             data-original-url="${originalUrl}"
                             data-width="${imageWidth}"
                             data-height="${imageHeight}"
                             loading="lazy"
                             onload="this.classList.add('loaded'); this.parentElement.classList.add('loaded'); this.parentElement.parentElement.querySelector('.gallery-item-skeleton').style.display='none'">
                        <div class="gallery-item-filename">${displayFilename}</div>
                        <div class="gallery-item-actions">
                            <button class="gallery-item-button select-button" aria-label="Select image">
                                <svg class="checkmark-icon" viewBox="0 0 24 24" width="24" height="24">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
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
                const img = $(this).find('img');
                return img.data('watermarked-url') === imageUrl || img.attr('src') === imageUrl;
            }).index();
            
            modalImages = $('.gallery-item').map(function() {
                const $img = $(this).find('img');
                return {
                    url: $img.data('watermarked-url') || $img.attr('src'), // Use watermarked URL from data attribute
                    caption: $(this).find('.gallery-caption').text()
                };
            }).get();
            
            beautifulRescuesDebug.log('Modal images prepared:', {
                count: modalImages.length,
                currentIndex: currentModalIndex
            });
            
            // Show loading state
            const $modalImage = $('.modal-image');
            $modalImage.addClass('loading');
            
            // Create new image to preload
            const img = new Image();
            img.onload = function() {
                $modalImage.attr('src', this.src).removeClass('loading');
            };
            img.src = modalImages[currentModalIndex].url;
            
            $('.modal-caption').text(caption);
            $('.gallery-modal').fadeIn(300);
            $('body').addClass('modal-open');
            
            // Preload adjacent images
            preloadAdjacentImages();
            
            updateModalNavigation();
        }

        function preloadAdjacentImages() {
            const preloadCount = 2; // Number of images to preload in each direction
            const totalImages = modalImages.length;
            
            for (let i = 1; i <= preloadCount; i++) {
                // Preload next images
                const nextIndex = (currentModalIndex + i) % totalImages;
                const nextImage = new Image();
                nextImage.src = modalImages[nextIndex].url;
                
                // Preload previous images
                const prevIndex = (currentModalIndex - i + totalImages) % totalImages;
                const prevImage = new Image();
                prevImage.src = modalImages[prevIndex].url;
            }
        }

        function closeModal() {
            $('.gallery-modal').fadeOut(300);
            $('body').removeClass('modal-open');
        }

        function navigateModal(direction) {
            currentModalIndex = (currentModalIndex + direction + modalImages.length) % modalImages.length;
            const image = modalImages[currentModalIndex];
            
            // Show loading state
            const $modalImage = $('.modal-image');
            $modalImage.addClass('loading');
            
            // Create new image to preload
            const img = new Image();
            img.onload = function() {
                $modalImage.attr('src', this.src).removeClass('loading');
            };
            img.src = image.url;
            
            $('.modal-caption').text(image.caption);
            
            // Preload adjacent images
            preloadAdjacentImages();
            
            updateModalNavigation();
        }

        // Add loading state handling
        $('.modal-image').on('load', function() {
            $(this).removeClass('loading');
        });

        function updateModalNavigation() {
            $('.modal-nav-button[data-direction="-1"]').toggle(currentModalIndex > 0);
            $('.modal-nav-button[data-direction="1"]').toggle(currentModalIndex < modalImages.length - 1);
        }

        // Add modal HTML structure
        $('body').append(`
            <div class="gallery-modal">
                <div class="modal-content">
                    <button class="modal-close" aria-label="Close modal">
                        <svg viewBox="0 0 24 24" width="24" height="24">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                    </button>
                    <div class="modal-image-container">
                        <img class="modal-image" src="" alt="">
                        <div class="modal-caption"></div>
                    </div>
                    <div class="modal-navigation">
                        <button class="modal-nav-button" data-direction="-1" aria-label="Previous image">
                            <svg viewBox="0 0 24 24" width="24" height="24">
                                <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                            </svg>
                        </button>
                        <button class="modal-nav-button" data-direction="1" aria-label="Next image">
                            <svg viewBox="0 0 24 24" width="24" height="24">
                                <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        `);

        // Function to load more images
        function loadMoreImages($gallery, category, sort, page, perPage) {
            if (isLoading) return;
            isLoading = true;
            
            beautifulRescuesDebug.log('gallery.js - Loading more images:', {
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
                    if (response.success && response.data) {
                        beautifulRescuesDebug.log('Server response:', response.data);
                        
                        // Update total images count from response
                        const totalImages = response.data.total_images || 0;
                        gallery.data('total-images', totalImages);
                        
                        // Process new images
                        const newImages = response.data.images || [];
                        beautifulRescuesDebug.log('Processing images:', newImages);
                        
                        // Get current selections before adding new images
                        beautifulRescuesDebug.log('Selection state before adding images:', {
                            selectedImages: Array.from(selectedImages)
                        });
                        
                        // Track existing items to prevent duplicates
                        const existingItems = new Set();
                        $('.gallery-grid .gallery-item').each(function() {
                            existingItems.add($(this).data('public-id'));
                        });
                        
                        beautifulRescuesDebug.log('Existing items:', Array.from(existingItems));
                        
                        // Add new images to the grid
                        newImages.forEach(function(image) {
                            if (existingItems.has(image.public_id)) {
                                beautifulRescuesDebug.log('Skipping duplicate image:', image.public_id);
                                return;
                            }
                            
                            const item = $(createGalleryItem(image));
                            if (append) {
                                galleryGrid.append(item);
                            } else {
                                galleryGrid.prepend(item);
                            }
                            
                            // Check if this image was previously selected
                            if (selectedImages.has(image.public_id)) {
                                item.addClass('selected');
                            }
                        });
                        
                        // Update hasMoreImages state
                        const currentItems = $('.gallery-grid .gallery-item').length;
                        hasMoreImages = currentItems < totalImages;
                        
                        beautifulRescuesDebug.log('Gallery state after adding images:', {
                            currentItems: currentItems,
                            totalImages: totalImages,
                            hasMoreImages: hasMoreImages
                        });
                        
                        // Show/hide load more button
                        if (hasMoreImages) {
                            loadMoreButton.show();
                        } else {
                            loadMoreButton.hide();
                        }
                        
                        beautifulRescuesDebug.log('Gallery updated with new items:', {
                            added: newImages.length,
                            total: currentItems,
                            hasMore: hasMoreImages
                        });
                        
                        // Update cart count and preview
                        if (typeof beautifulRescuesCart !== 'undefined') {
                            beautifulRescuesCart.updateCartCount();
                            beautifulRescuesCart.updateSelectedImagesPreview();
                        }
                    }
                },
                complete: function() {
                    isLoading = false;
                    $('.gallery-loading-overlay').removeClass('active');
                }
            });
        }

        // Initialize the gallery by loading the first page of images
        loadImages(1, false);
    });
})(jQuery); 