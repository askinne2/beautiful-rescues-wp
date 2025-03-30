(function($) {
    'use strict';

    const reviewContainer = $('.donation-review-container');
    if (!reviewContainer.length) return;

    // Initialize variables
    const donationList = $('.donation-list');
    const statusFilter = $('#status-filter');
    const searchFilter = $('#search-filter');
    const detailsPanel = $('.donation-details-panel');
    const detailsContent = $('.donation-details-content');
    const verifyButton = $('.verify-button');
    const rejectButton = $('.reject-button');
    const donorInfo = $('.donor-info');
    const verificationFile = $('.verification-file');
    const selectedImages = $('.selected-images');

    let currentPage = 1;
    let isLoading = false;
    let currentDonationId = null;

    // Initialize review functionality
    function initReview() {
        loadDonations();
        bindEventHandlers();
    }

    // Bind event handlers
    function bindEventHandlers() {
        // Status filter change
        statusFilter.on('change', function() {
            currentPage = 1;
            loadDonations();
        });

        // Search filter input (debounced)
        let searchTimeout;
        searchFilter.on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentPage = 1;
                loadDonations();
            }, 500);
        });

        // Verify/Reject buttons
        verifyButton.on('click', function() {
            updateDonationStatus('verified');
        });

        rejectButton.on('click', function() {
            updateDonationStatus('rejected');
        });
    }

    // Load donations via AJAX
    function loadDonations() {
        if (isLoading) return;
        isLoading = true;

        $.ajax({
            url: beautifulRescuesReview.ajaxurl,
            type: 'POST',
            data: {
                action: 'load_donations',
                nonce: beautifulRescuesReview.nonce,
                status: statusFilter.val(),
                search: searchFilter.val(),
                page: currentPage,
                per_page: 20
            },
            success: function(response) {
                console.log('Donations loaded:', response);
                if (response.success) {
                    renderDonations(response.data);
                    updatePagination(response.data);
                }
                isLoading = false;
            },
            error: function() {
                isLoading = false;
            }
        });
    }

    // Render donations list
    function renderDonations(data) {
        console.log('Rendering donations:', data.donations);
        donationList.empty();

        data.donations.forEach(function(donation) {
            console.log('Processing donation:', donation);
            const donationHtml = `
                <div class="donation-item status-${donation.status || 'pending'}" data-id="${donation.id}">
                    <div class="donation-info">
                        <h3>${donation.title}</h3>
                        <p class="donor-name">${donation.donor_name || 'Unknown'}</p>
                        <p class="donation-date">${donation.date}</p>
                        <p class="donation-status">Status: ${donation.status || 'pending'}</p>
                    </div>
                    <div class="donation-actions">
                        <button class="review-button">Review</button>
                    </div>
                </div>
            `;
            donationList.append(donationHtml);
        });

        // Bind review button clicks
        $('.review-button').on('click', function() {
            const donationId = $(this).closest('.donation-item').data('id');
            openDonationDetails(donationId);
        });
    }

    // Update pagination
    function updatePagination(data) {
        const totalPages = Math.ceil(data.total / data.per_page);
        const pagination = $('.pagination');
        pagination.empty();

        if (totalPages > 1) {
            // Previous button
            if (currentPage > 1) {
                pagination.append(`<button class="page-button" data-page="${currentPage - 1}">Previous</button>`);
            }

            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                pagination.append(`
                    <button class="page-button ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>
                `);
            }

            // Next button
            if (currentPage < totalPages) {
                pagination.append(`<button class="page-button" data-page="${currentPage + 1}">Next</button>`);
            }

            // Bind pagination clicks
            pagination.find('.page-button').on('click', function() {
                currentPage = parseInt($(this).data('page'));
                loadDonations();
            });
        }
    }

    // Function to open donation details
    function openDonationDetails(donationId) {
        currentDonationId = donationId;
        
        // Update selected state in the list
        $('.donation-item').removeClass('selected');
        $(`.donation-item[data-id="${donationId}"]`).addClass('selected');
        
        $.ajax({
            url: beautifulRescuesReview.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_donation_details',
                nonce: beautifulRescuesReview.nonce,
                donation_id: donationId
            },
            success: function(response) {
                if (response.success) {
                    const donation = response.data;
                    
                    // Update donor info
                    $('.donor-name').text(donation.donor_name);
                    $('.donor-email').text(donation.email);
                    $('.donor-phone').text(donation.phone);
                    $('.donor-message').text(donation.message || 'No message provided');

                    // Update verification file preview
                    if (donation.verification_file_url) {
                        const fileUrl = donation.verification_file_url;
                        const fileExtension = donation.verification_file.split('.').pop().toLowerCase();
                        
                        if (fileExtension === 'pdf') {
                            verificationFile.html(`
                                <div class="pdf-preview">
                                    <iframe src="${fileUrl}" width="100%" height="500px"></iframe>
                                </div>
                            `);
                        } else {
                            verificationFile.html(`
                                <div class="image-preview">
                                    <img src="${fileUrl}" alt="Verification file">
                                </div>
                            `);
                        }
                    } else {
                        verificationFile.html('<p>No verification file provided</p>');
                    }

                    // Update selected images
                    if (donation.selected_images && donation.selected_images.length > 0) {
                        const imagesHtml = donation.selected_images.map(image => `
                            <div class="selected-image">
                                <img src="${image.url}" alt="${image.filename || 'Selected image'}">
                                <div class="image-info">
                                    <p>${image.filename || 'Image'}</p>
                                    <a href="${image.url}" target="_blank" class="download-link">Download</a>
                                </div>
                            </div>
                        `).join('');
                        selectedImages.html(imagesHtml);
                    } else {
                        selectedImages.html('<p>No images selected</p>');
                    }

                    // Show details panel
                    detailsPanel.removeClass('empty');
                    detailsContent.addClass('active');
                }
            }
        });
    }

    // Update donation status
    function updateDonationStatus(status) {
        if (!currentDonationId) return;

        $.ajax({
            url: beautifulRescuesReview.ajaxurl,
            type: 'POST',
            data: {
                action: 'update_donation_status',
                nonce: beautifulRescuesReview.nonce,
                donation_id: currentDonationId,
                status: status
            },
            success: function(response) {
                if (response.success) {
                    loadDonations();
                    detailsPanel.addClass('empty');
                    detailsContent.removeClass('active');
                    currentDonationId = null;
                }
            }
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        initReview();
    });

})(jQuery); 