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
                BRDebug.error('Error in search filter:', error);
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
                    donationList.html('<div class="error">Error loading donations: ' + response.data + '</div>');
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
            },
            success: function(response) {
                if (response.success) {
                    // Ensure response data has HTTPS URLs
                    if (response.data.selected_images) {
                        response.data.selected_images = response.data.selected_images.map(image => ({
                            ...image,
                            url: image.url.replace('http://', 'https://')
                        }));
                    }
                    if (response.data.verification_file && response.data.verification_file.url) {
                        response.data.verification_file.url = response.data.verification_file.url.replace('http://', 'https://');
                    }
                    renderDonationDetails(response.data);
                } else {
                    detailsPanel.html('<div class="error">Error loading details: ' + response.data + '</div>');
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
                    detailsPanel.find('.donation-actions').html(
                        '<div class="error">Error updating status: ' + response.data + '</div>' 
                        // +
                        // '<div class="donation-actions">' +
                        // '<button class="verify-button">Verify</button>' +
                        // '<button class="reject-button">Reject</button>' +
                        // '</div>'
                    );
                }
            },
            error: function() {
                detailsPanel.find('.donation-actions').html(
                    '<div class="error">Failed to update status. Please try again.</div>' 
                    // +
                    // '<div class="donation-actions">' +
                    // '<button class="verify-button">Verify</button>' +
                    // '<button class="reject-button">Reject</button>' +
                    // '</div>'
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
        const html = `
            <div class="donor-info">
                <h3>Donor Information</h3>
                <div class="donor-details">
                    <div class="donation-details">
                        <h3>${donation._first_name} ${donation._last_name}</h3>
                        <h3>${donation._email}</h3>
                        <h3>${donation._phone}</h3>
                        <p>${donation._message || 'No message provided'}</p>
                    </div>
                </div>
            </div>

            <div class="verification-file">
                <h3>Verification Document</h3>
                ${renderVerificationFile(donation.verification_file)}
            </div>

            <div class="selected-images">
                <h3>Selected Images</h3>
                <div class="images-grid">
                    ${renderSelectedImages(donation.selected_images)}
                </div>
            </div>

            <!--div class="donation-actions">
                <button class="verify-button">Verify</button>
                <button class="reject-button">Reject</button>
            </div-->
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
    function renderSelectedImages(images) {
        if (!images || !images.length) {
            return '<p>No images selected</p>';
        }

        return images.map(image => {
            // Parse the title from the URL
            const urlParts = image.url.split('/');
            const title = urlParts[urlParts.length - 2] || 'Beautiful Rescue Kitty';
            
            return `
                <div class="selected-image">
                    <img src="${image.url}" alt="${title}">
                    <div class="image-info">
                        <p>${title}</p>
                    </div>
                </div>
            `;
        }).join('');
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