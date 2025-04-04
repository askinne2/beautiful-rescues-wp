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
                            
                            // Get all current gallery items
                            const $items = $('.gallery-grid .gallery-item');
                            const columnCount = parseInt($('.gallery-grid').css('column-count')) || 5;
                            
                            // Calculate the target column for this item
                            const targetColumn = ($items.length % columnCount);
                            
                            // Find the last item in the target column
                            let $lastInColumn = null;
                            for (let i = $items.length - 1; i >= 0; i--) {
                                const $item = $($items[i]);
                                const itemColumn = i % columnCount;
                                if (itemColumn === targetColumn) {
                                    $lastInColumn = $item;
                                    break;
                                }
                            }
                            
                            // Insert the new item after the last item in its target column
                            if ($lastInColumn) {
                                $lastInColumn.after(item);
                            } else {
                                $('.gallery-grid').append(item);
                            }
                            
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

            // Determine sizes based on aspect ratio
            const sizes = isPortrait 
                ? '(max-width: 480px) 150px, (max-width: 768px) 200px, 250px'
                : '(max-width: 480px) 200px, (max-width: 768px) 300px, 400px';

            return `
                <div class="gallery-item" data-public-id="${imageId}" data-aspect-ratio="${aspectRatio}">
                    <div class="gallery-item-skeleton" style="padding-top: ${(aspectRatio * 100).toFixed(2)}%"></div>
                    <div class="gallery-item-image">
                        <img src="${responsiveUrls.medium}"
                             srcset="${srcset}"
                             sizes="${sizes}"
                             alt="${displayFilename}"
                             data-url="${imageUrl}"
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
                return $(this).find('img').attr('src') === imageUrl;
            }).index();
            
            modalImages = $('.gallery-item').map(function() {
                const $img = $(this).find('img');
                return {
                    url: $img.data('url'), // Use watermarked URL from data-url
                    caption: $(this).find('.gallery-caption').text()
                };
            }).get();
            
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
                            
                            // Get all current gallery items
                            const $items = $('.gallery-grid .gallery-item');
                            const columnCount = parseInt($('.gallery-grid').css('column-count')) || 5;
                            
                            // Calculate the target column for this item
                            const targetColumn = ($items.length % columnCount);
                            
                            // Find the last item in the target column
                            let $lastInColumn = null;
                            for (let i = $items.length - 1; i >= 0; i--) {
                                const $item = $($items[i]);
                                const itemColumn = i % columnCount;
                                if (itemColumn === targetColumn) {
                                    $lastInColumn = $item;
                                    break;
                                }
                            }
                            
                            // Insert the new item after the last item in its target column
                            if ($lastInColumn) {
                                $lastInColumn.after(item);
                            } else {
                                $('.gallery-grid').append(item);
                            }
                            
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