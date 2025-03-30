(function($) {
    'use strict';

    // Initialize form handling
    function initVerificationForm() {
        const $form = $('#donation-verification-form');
        const $imagePreview = $('#image-preview');
        const $fileInput = $('#selected_images');
        const $messages = $('#form-messages');

        // Handle file selection
        $fileInput.on('change', function() {
            $imagePreview.empty();
            const files = this.files;

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const $img = $('<img>').attr('src', e.target.result);
                        $imagePreview.append($img);
                    };
                    reader.readAsDataURL(file);
                } else if (file.type === 'application/pdf') {
                    const $pdfIcon = $('<div class="pdf-preview">PDF Document</div>');
                    $imagePreview.append($pdfIcon);
                }
            }
        });

        // Handle form submission
        $form.on('submit', function(e) {
            e.preventDefault();
            
            // Clear previous messages
            $messages.empty();
            
            // Validate file size
            const files = $fileInput[0].files;
            for (let i = 0; i < files.length; i++) {
                if (files[i].size > beautifulRescuesVerification.maxFileSize) {
                    showMessage('error', 'File size exceeds limit');
                    return;
                }
            }

            // Create FormData
            const formData = new FormData(this);
            formData.append('action', 'submit_donation_verification');
            formData.append('nonce', beautifulRescuesVerification.nonce);

            // Show loading state
            $form.addClass('loading');

            // Submit form
            $.ajax({
                url: beautifulRescuesVerification.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showMessage('success', response.message);
                        $form[0].reset();
                        $imagePreview.empty();
                    } else {
                        showMessage('error', response.message);
                    }
                },
                error: function() {
                    showMessage('error', 'An error occurred. Please try again.');
                },
                complete: function() {
                    $form.removeClass('loading');
                }
            });
        });

        // Helper function to show messages
        function showMessage(type, message) {
            $messages.html(`<div class="${type}">${message}</div>`);
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        initVerificationForm();
    });

})(jQuery); 