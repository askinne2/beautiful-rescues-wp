jQuery(document).ready(function($) {
    const $form = $('#donation-verification-form');
    const $messages = $('#form-messages');
    const $fileInput = $('#_selected_images');
    const $preview = $('#image-preview');

    // Handle form submission
    $form.on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const selectedImages = [];

        // Add selected images to form data
        if ($fileInput.length) {
            const files = $fileInput[0].files;
            for (let i = 0; i < files.length; i++) {
                selectedImages.push({
                    name: files[i].name,
                    type: files[i].type,
                    size: files[i].size
                });
            }
            formData.append('_selected_images', JSON.stringify(selectedImages));
        }

        $.ajax({
            url: beautifulRescuesVerification.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $form.find('button[type="submit"]').prop('disabled', true);
                $messages.html('<div class="loading">Submitting verification...</div>');
            },
            success: function(response) {
                if (response.success) {
                    $messages.html('<div class="success">' + response.data.message + '</div>');
                    $form[0].reset();
                    $preview.empty();
                    if (response.data.redirect) {
                        window.location.href = response.data.redirect;
                    }
                } else {
                    $messages.html('<div class="error">' + response.data + '</div>');
                }
            },
            error: function() {
                $messages.html('<div class="error">An error occurred. Please try again.</div>');
            },
            complete: function() {
                $form.find('button[type="submit"]').prop('disabled', false);
            }
        });
    });

    // Handle file input change for preview
    $fileInput.on('change', function() {
        $preview.empty(); // Clear existing previews
        const files = this.files;

        if (files.length === 0) {
            // If no files are selected (cleared), just return after emptying the preview
            return;
        }

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const reader = new FileReader();

            reader.onload = function(e) {
                const $img = $('<img>').attr('src', e.target.result);
                const $wrapper = $('<div>').addClass('preview-item').append($img);
                $preview.append($wrapper);
            };

            reader.readAsDataURL(file);
        }
    });
}); 