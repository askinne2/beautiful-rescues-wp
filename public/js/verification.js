jQuery(document).ready(function($) {
    // Handle form submission for both forms
    $('#donation-verification-form, #checkout-verification-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitButton = form.find('button[type="submit"]');
        var messagesDiv = $('#form-messages');
        var originalButtonText = submitButton.text();
        
        // Clear previous messages
        messagesDiv.empty().removeClass('error success');
        
        // Basic validation
        const emailInput = form.find('input[type="email"]');
        const emailValue = emailInput.val().trim();
        
        if (!emailValue) {
            messagesDiv.addClass('error').html('Please enter an email address.');
            emailInput.addClass('error').focus();
            return;
        }
        
        if (!isValidEmail(emailValue)) {
            messagesDiv.addClass('error').html('Please enter a valid email address.');
            emailInput.addClass('error').focus();
            return;
        }
        
        // Disable submit button and show loading state
        submitButton.prop('disabled', true).text('Processing...');
        messagesDiv.html('<div class="loading">Processing your submission...</div>');
        
        // Create FormData object
        var formData = new FormData(this);
        
        // Add selected images if they exist
        if (typeof beautifulRescuesCart !== 'undefined' && beautifulRescuesCart.selectedImages) {
            formData.append('selected_images', JSON.stringify(beautifulRescuesCart.selectedImages));
        } else if (localStorage.getItem('beautifulRescuesSelectedImages')) {
            formData.append('selected_images', localStorage.getItem('beautifulRescuesSelectedImages'));
        }
        
        // Log form submission data
        console.log('Form submission data:');
        for (var pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }
        
        // Log AJAX URL and nonce
        console.log('AJAX URL:', beautifulRescuesVerification.ajaxurl);
        console.log('Nonce:', beautifulRescuesVerification.nonce);
        
        // Submit form via AJAX
        $.ajax({
            url: beautifulRescuesVerification.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('Server response:', response);
                
                if (response.success) {
                    messagesDiv.addClass('success').html(response.data.message);
                    form.trigger('reset');
                    
                    // Clear selected images from localStorage if source is checkout
                    if (formData.get('source') === 'checkout') {
                        localStorage.removeItem('beautifulRescuesSelectedImages');
                        // Trigger a custom event to notify other components
                        window.dispatchEvent(new Event('beautifulRescuesSelectionChanged'));
                    }
                    
                    // Redirect if URL is provided
                    if (response.data.redirect_url) {
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 2000);
                    }
                } else {
                    messagesDiv.addClass('error').html(response.data.message || 'An error occurred. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', {xhr: xhr, status: status, error: error});
                messagesDiv.addClass('error').html('An error occurred while processing your request. Please try again.');
            },
            complete: function() {
                // Reset button state
                submitButton.prop('disabled', false).text(originalButtonText);
            }
        });
    });
    
    // File input validation
    $('#_verification_file').on('change', function() {
        const file = this.files[0];
        if (!file) return;
        
        const maxSize = beautifulRescuesVerification.maxFileSize;
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        
        if (file.size > maxSize) {
            const maxSizeMB = Math.round(maxSize / (1024 * 1024));
            $('#form-messages').addClass('error')
                .html(`File is too large. Maximum size is ${maxSizeMB}MB.`);
            this.value = '';
            return;
        }
        
        if (!allowedTypes.includes(file.type)) {
            $('#form-messages').addClass('error')
                .html('Invalid file type. Please upload an image (JPEG, PNG, GIF) or PDF.');
            this.value = '';
            return;
        }
        
        $('#form-messages').empty().removeClass('error success');
    });

    // Helper function to validate email format
    function isValidEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
}); 