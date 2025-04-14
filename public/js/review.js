jQuery(document).ready(function($) {
    // State management
    let currentPage = 1;
    let totalPages = 1;
    let selectedDonationId = null;
    let currentStatus = 'all';
    let searchTerm = '';

    // Cache DOM elements
    const donationList = $('.donation-list');
    const detailsPanel = $('.donation-details-panel');
    const statusFilter = $('.status-filter');
    const searchFilter = $('.search-filter');
    const pagination = $('.pagination');

    // Initialize the page
    loadDonations();

    // Event Listeners
    statusFilter.on('change', function() {
        currentStatus = $(this).val();
        currentPage = 1;
        loadDonations();
    });

    // Update search filter handler with defensive checks
    if (searchFilter.length) {
        searchFilter.on('input', debounce(function() {
            try {
                const val = searchFilter.val();
                searchTerm = val ? String(val).trim() : '';
                currentPage = 1;
                loadDonations();
            } catch (error) {
                console.error('Error in search filter:', error);
                searchTerm = '';
                currentPage = 1;
                loadDonations();
            }
        }, 500));
    }

    // Handle donation item click
    donationList.on('click', '.donation-item', function() {
        const donationId = $(this).data('id');
        $('.donation-item').removeClass('selected');
        $(this).addClass('selected');
        loadDonationDetails(donationId);
    });

    // Handle close button click on mobile
    $(document).on('click', '.close-panel-button', function() {
        $('.donation-details-panel').removeClass('active');
        return false;
    });

    // Handle pagination clicks
    pagination.on('click', '.page-button', function() {
        if (!$(this).hasClass('active')) {
            currentPage = parseInt($(this).data('page'));
            loadDonations();
        }
    });

    // // Handle verification actions
    // detailsPanel.on('click', '.verify-button', function() {
    //     if (selectedDonationId) {
    //         updateDonationStatus(selectedDonationId, 'verified');
    //     }
    // });

    // detailsPanel.on('click', '.reject-button', function() {
    //     if (selectedDonationId) {
    //         updateDonationStatus(selectedDonationId, 'rejected');
    //     }
    // });

    // Load donations with filters and pagination
    function loadDonations() {
        $.ajax({
            url: beautifulRescuesReview.ajaxurl,
            type: 'POST',
            data: {
                action: 'load_donations',
                nonce: beautifulRescuesReview.nonce,
                page: currentPage,
                status: currentStatus,
                search: searchTerm
            },
            beforeSend: function() {
                donationList.html('<div class="loading">Loading donations...</div>');
            },
            success: function(response) {
                if (response.success) {
                    renderDonationList(response.data.donations);
                    totalPages = response.data.total_pages;
                    renderPagination();
                } else {
                    // Check if the error is related to permissions
                    if (response.data && response.data.includes('logged in as an administrator')) {
                        // Show a message with a link to the login page
                        donationList.html('<div class="error">You need to be logged in as an administrator to view donations. <a href="' + window.location.href + '">Click here to log in</a>.</div>');
                    } else {
                        donationList.html('<div class="error">Error loading donations: ' + response.data + '</div>');
                    }
                }
            },
            error: function() {
                donationList.html('<div class="error">Failed to load donations. Please try again.</div>');
            }
        });
    }

    // Load donation details
    function loadDonationDetails(donationId) {
        selectedDonationId = donationId;
        
        $.ajax({
            url: beautifulRescuesReview.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_donation_details',
                nonce: beautifulRescuesReview.nonce,
                donation_id: donationId
            },
            beforeSend: function() {
                detailsPanel.removeClass('empty').html('<div class="loading">Loading details...</div>');
                
                // Scroll to the details panel
                if (window.innerWidth <= 768) {
                    // For mobile devices, the panel is positioned as fixed
                    detailsPanel.addClass('active');
                } else {
                    // For desktop and tablets, scroll to the panel
                    $('html, body').animate({
                        scrollTop: detailsPanel.offset().top - 30
                    }, 300);
                }
            },
            success: function(response) {
                if (response.success) {
                    // Ensure response data has HTTPS URLs
                    if (response.data.selected_images) {
                        response.data.selected_images = response.data.selected_images.map(image => ({
                            ...image,
                            watermarked_url: image.watermarked_url ? image.watermarked_url.replace('http://', 'https://') : '',
                            original_url: image.original_url ? image.original_url.replace('http://', 'https://') : ''
                        }));
                    }
                    if (response.data.verification_file && response.data.verification_file.url) {
                        response.data.verification_file.url = response.data.verification_file.url.replace('http://', 'https://');
                    }
                    renderDonationDetails(response.data);
                } else {
                    // Check if the error is related to permissions
                    if (response.data && response.data.includes('logged in as an administrator')) {
                        // Show a message with a link to the login page
                        detailsPanel.html('<div class="error">You need to be logged in as an administrator to view donation details. <a href="' + window.location.href + '">Click here to log in</a>.</div>');
                    } else {
                        detailsPanel.html('<div class="error">Error loading details: ' + response.data + '</div>');
                    }
                }
            },
            error: function() {
                detailsPanel.html('<div class="error">Failed to load donation details. Please try again.</div>');
            }
        });
    }

    // Update donation status
    function updateDonationStatus(donationId, status) {
        $.ajax({
            url: beautifulRescuesReview.ajaxurl,
            type: 'POST',
            data: {
                action: 'update_donation_status',
                nonce: beautifulRescuesReview.nonce,
                donation_id: donationId,
                status: status
            },
            beforeSend: function() {
                detailsPanel.find('.donation-actions').html('<div class="loading">Updating status...</div>');
            },
            success: function(response) {
                if (response.success) {
                    loadDonations();
                    loadDonationDetails(donationId);
                } else {
                    // Check if the error is related to permissions
                    if (response.data && response.data.includes('logged in as an administrator')) {
                        // Show a message with a link to the login page
                        detailsPanel.find('.donation-actions').html(
                            '<div class="error">You need to be logged in as an administrator to update donation status. <a href="' + window.location.href + '">Click here to log in</a>.</div>'
                        );
                    } else {
                        detailsPanel.find('.donation-actions').html(
                            '<div class="error">Error updating status: ' + response.data + '</div>'
                        );
                    }
                }
            },
            error: function() {
                detailsPanel.find('.donation-actions').html(
                    '<div class="error">Failed to update status. Please try again.</div>'
                );
            }
        });
    }

    // Render donation list
    function renderDonationList(donations) {
        if (!donations.length) {
            donationList.html('<div class="no-results">No donations found.</div>');
            return;
        }

        const html = donations.map(donation => `
            <div class="donation-item status-${donation.status}" data-id="${donation.id}">
                <div class="donation-info">
                    <h3>${donation._first_name} ${donation._last_name}</h3>
                    <p class="donor-info">${donation._email}</p>
                    <p class="donor-info">${donation._phone}</p>
                    <div class="donation-date">Submitted: ${donation.date} ${donation.time}</div>
                    <div class="donation-status">Status: <strong>${donation.status}</strong></div>
                </div>
                <div class="quick-actions">
                    <button class="verify-button" data-id="${donation.id}">Verify</button>
                    <button class="reject-button" data-id="${donation.id}">Reject</button>
                </div>
            </div>
        `).join('');

        donationList.html(html);
    }

    // Add event listeners for quick actions
    donationList.on('click', '.quick-actions button', function(e) {
        e.stopPropagation(); // Prevent triggering the donation item click
        const donationId = $(this).data('id');
        const status = $(this).hasClass('verify-button') ? 'verified' : 'rejected';
        updateDonationStatus(donationId, status);
    });

    // Render donation details
    function renderDonationDetails(donation) {
        const closeButton = window.innerWidth <= 768 ? 
            '<button class="close-panel-button">×</button>' : '';
            
        const html = `
            ${closeButton}
            <div class="verification-details">
                <div class="verification-header">
                    <h2>Donation Details</h2>
       
                </div>

                <div class="donor-info">
                    <div class="donor-details">
                        <p><strong>Name:</strong> ${donation._first_name} ${donation._last_name}</p>
                        <p><strong>Email:</strong> ${donation._email}</p>
                        <p><strong>Phone:</strong> ${donation._phone}</p>
                        <p><strong>Message:</strong> ${donation._message || 'No message provided'}</p>
                                     <div class="verification-status status-${donation.status}">
                        ${donation.status.charAt(0).toUpperCase() + donation.status.slice(1)}
                    </div>
                    </div>
                </div>

                <div class="verification-file">
                    <h3>Verification Document</h3>
                    ${renderVerificationFile(donation.verification_file)}
                </div>

                <div class="selected-images-container">
                    <h3>Selected Images</h3>
                    <div class="selected-images">
                        ${renderSelectedImages(donation.selected_images, donation.status)}
                    </div>
                </div>
            </div>
        `;

        detailsPanel.removeClass('empty').html(html);
    }

    // Render verification file preview
    function renderVerificationFile(file) {
        if (!file) return '<p>No verification file uploaded</p>';

        const extension = file.url.split('.').pop().toLowerCase();
        
        if (extension === 'pdf') {
            return `<iframe src="${file.url}" width="100%" height="600"></iframe>`;
        } else if (['jpg', 'jpeg', 'png', 'gif'].includes(extension)) {
            return `<img src="${file.url}" alt="Verification document">`;
        } else {
            return `<p><a href="${file.url}" target="_blank">Download verification file</a></p>`;
        }
    }

    // Render selected images
    function renderSelectedImages(images, status) {
        if (!images || !images.length) {
            return '<p>No images selected</p>';
        }

        let html = '';
        
        images.forEach(function(image) {
            if (image.watermarked_url) {
                // Generate responsive image URLs for watermarked version
                const baseUrl = image.watermarked_url;
                const responsiveUrls = {
                    thumbnail: baseUrl.replace('/upload/', '/upload/w_200,c_scale/'),
                    medium: baseUrl.replace('/upload/', '/upload/w_400,c_scale/'),
                    large: baseUrl.replace('/upload/', '/upload/w_800,c_scale/'),
                    full: baseUrl
                };

                // Create srcset string
                const srcset = [
                    `${responsiveUrls.thumbnail} 200w`,
                    `${responsiveUrls.medium} 400w`,
                    `${responsiveUrls.large} 800w`,
                    `${responsiveUrls.full} 1600w`
                ].join(', ');

                // Only show download link if the donation is verified
                const downloadOverlay = status === 'verified' && image.original_url ? `
                    <div class="image-info">
                        <a href="${image.original_url}" target="_blank" class="download-link">
                            Download Original
                        </a>
                    </div>
                ` : '';

                html += `
                    <div class="selected-image">
                        <img src="${responsiveUrls.medium}"
                             srcset="${srcset}"
                             sizes="(max-width: 480px) 200px, (max-width: 768px) 400px, (max-width: 1200px) 800px, 1600px"
                             alt="Selected image"
                             loading="lazy">
                        ${downloadOverlay}
                    </div>
                `;
            }
        });
        
        return html;
    }

    // Render pagination
    function renderPagination() {
        if (totalPages <= 1) {
            pagination.empty();
            return;
        }

        let html = '';
        
        // Previous button
        if (currentPage > 1) {
            html += `<button class="page-button" data-page="${currentPage - 1}">Previous</button>`;
        }

        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (
                i === 1 || // First page
                i === totalPages || // Last page
                (i >= currentPage - 2 && i <= currentPage + 2) // Pages around current
            ) {
                html += `<button class="page-button${i === currentPage ? ' active' : ''}" data-page="${i}">${i}</button>`;
            } else if (
                (i === currentPage - 3 && currentPage > 4) ||
                (i === currentPage + 3 && currentPage < totalPages - 3)
            ) {
                html += '<span class="page-ellipsis">...</span>';
            }
        }

        // Next button
        if (currentPage < totalPages) {
            html += `<button class="page-button" data-page="${currentPage + 1}">Next</button>`;
        }

        pagination.html(html);
    }

    // Utility function for debouncing
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            const context = this;
            const later = () => {
                clearTimeout(timeout);
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}); 