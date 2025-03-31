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
            $messages.empty();

            // Create FormData object
            const formData = new FormData(this);
            
            // Add selected images if they exist
            const selectedImages = localStorage.getItem('selectedImages');
            if (selectedImages) {
                formData.append('selected_images', selectedImages);
            }

            // Show loading state
            const submitButton = $form.find('button[type="submit"]');
            const originalText = submitButton.text();
            submitButton.prop('disabled', true).text('Processing...');

            // Submit form via AJAX
            $.ajax({
                url: beautifulRescuesVerification.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $messages.html('<div class="success-message">' + response.message + '</div>');
                        $form[0].reset();
                        $imagePreview.empty();
                        if (response.redirect) {
                            setTimeout(function() {
                                window.location.href = response.redirect;
                            }, 2000);
                        }
                    } else {
                        $messages.html('<div class="error-message">' + response.message + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $messages.html('<div class="error-message">An error occurred. Please try again.</div>');
                    console.error('Form submission error:', error);
                },
                complete: function() {
                    submitButton.prop('disabled', false).text(originalText);
                }
            });
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        initVerificationForm();
    });

})(jQuery); 