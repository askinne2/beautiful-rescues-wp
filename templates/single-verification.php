<?php
/**
 * Single Verification Template
 * 
 * @package Beautiful_Rescues
 */

get_header();

// Enqueue review scripts if user is admin
if (current_user_can('manage_options')) {
    wp_enqueue_style(
        'beautiful-rescues-review',
        BR_PLUGIN_URL . 'public/css/review.css',
        array(),
        BR_VERSION
    );

    wp_enqueue_script(
        'beautiful-rescues-review',
        BR_PLUGIN_URL . 'public/js/review.js',
        array('jquery'),
        BR_VERSION,
        true
    );

    // Get the upload directory URL
    $upload_dir = wp_upload_dir();
    $verification_url = $upload_dir['baseurl'] . '/donation-verifications/';

    wp_localize_script('beautiful-rescues-review', 'beautifulRescuesReview', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('beautiful_rescues_review_nonce'),
        'verificationUrl' => $verification_url
    ));
}

// Verify access token unless user is an administrator
$verification_id = get_the_ID();
$stored_token = get_post_meta($verification_id, '_access_token', true);
$provided_token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

// Allow administrators to bypass token check
if (!current_user_can('manage_options') && (!$stored_token || $stored_token !== $provided_token)) {
    wp_die(__('Invalid or expired verification link. Please check your email for the correct link.', 'beautiful-rescues'));
}

while (have_posts()) :
    the_post();
    $verification_id = get_the_ID();

    // Get verification details
    $donor_first_name = get_post_meta($verification_id, '_first_name', true);
    $donor_last_name = get_post_meta($verification_id, '_last_name', true);
    $donor_email = get_post_meta($verification_id, '_email', true);
    $donor_phone = get_post_meta($verification_id, '_phone', true);
    $donor_message = get_post_meta($verification_id, '_message', true);
    $verification_file = get_post_meta($verification_id, '_verification_file', true);
    $verification_file_path = get_post_meta($verification_id, '_verification_file_path', true);
    $selected_images = get_post_meta($verification_id, '_selected_images', true);
    $submission_date = get_post_meta($verification_id, '_submission_date', true);
    
    // Get current status
    $current_status = get_post_meta($verification_id, '_status', true) ?: 'pending';
    
    // Get status display text and class
    $status_display = array(
        'pending' => array(
            'text' => __('Pending Review', 'beautiful-rescues'),
            'class' => 'status-pending'
        ),
        'verified' => array(
            'text' => __('Verified', 'beautiful-rescues'),
            'class' => 'status-verified'
        ),
        'rejected' => array(
            'text' => __('Rejected', 'beautiful-rescues'),
            'class' => 'status-rejected'
        )
    );
    $status_info = $status_display[$current_status] ?? $status_display['pending'];
    ?>

    <div class="wrap verification-details">
        <div class="verification-header">
            <h1><?php _e('Donation Verification', 'beautiful-rescues'); ?></h1>
            <div class="verification-status <?php echo esc_attr($status_info['class']); ?>">
                <?php echo esc_html($status_info['text']); ?>
            </div>
        </div>

        <?php if (current_user_can('manage_options')) : ?>
        <div class="admin-actions">
            <h3><?php _e('Update Verification Status', 'beautiful-rescues'); ?></h3>
            <form id="status-update-form" class="status-update-form">
                <select name="status" id="verification-status">
                    <option value="pending" <?php selected($current_status, 'pending'); ?>><?php _e('Pending Review', 'beautiful-rescues'); ?></option>
                    <option value="verified" <?php selected($current_status, 'verified'); ?>><?php _e('Verified', 'beautiful-rescues'); ?></option>
                    <option value="rejected" <?php selected($current_status, 'rejected'); ?>><?php _e('Rejected', 'beautiful-rescues'); ?></option>
                </select>
                <button type="submit" class="button button-primary"><?php _e('Update Status', 'beautiful-rescues'); ?></button>
                <div class="status-update-message"></div>
            </form>
        </div>
        <?php endif; ?>

        <div class="verification-meta">
            <div class="donor-details">
                <h3><?php _e('Your Information', 'beautiful-rescues'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Name', 'beautiful-rescues'); ?></th>
                        <td><?php echo esc_html($donor_first_name . ' ' . $donor_last_name); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Email', 'beautiful-rescues'); ?></th>
                        <td><?php echo esc_html($donor_email); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Phone', 'beautiful-rescues'); ?></th>
                        <td><?php echo esc_html($donor_phone); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Submission Date', 'beautiful-rescues'); ?></th>
                        <td><?php echo esc_html($submission_date); ?></td>
                    </tr>
                    <?php if ($donor_message) : ?>
                    <tr>
                        <th><?php _e('Message', 'beautiful-rescues'); ?></th>
                        <td><?php echo wp_kses_post($donor_message); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <?php if ($verification_file) : ?>
            <div class="verification-file">
                <h3><?php _e('Your Verification Document', 'beautiful-rescues'); ?></h3>
                <?php
                $file_extension = strtolower(pathinfo($verification_file, PATHINFO_EXTENSION));
                $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif']);
                $verification_file_url = wp_get_upload_dir()['baseurl'] . '/verifications/' . $verification_file;
                ?>
                
                <div class="verification-preview">
                    <?php if ($is_image) : ?>
                        <img src="<?php echo esc_url($verification_file_url); ?>" alt="<?php _e('Verification Image', 'beautiful-rescues'); ?>">
                    <?php else : ?>
                        <div class="pdf-preview">
                            <span class="dashicons dashicons-pdf"></span>
                            <a href="<?php echo esc_url($verification_file_url); ?>" target="_blank">
                                <?php _e('View PDF Document', 'beautiful-rescues'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($selected_images)) : ?>
            <div class="selected-images">
                <h3><?php _e('Your Selected Images', 'beautiful-rescues'); ?></h3>
                <div class="images-grid">
                    <?php 
                    foreach ($selected_images as $image) : 
                        if (isset($image['url'])) :
                            // Use the image URL directly since it's already from Cloudinary
                            $image_url = $image['url'];
                    ?>
                            <div class="image-item">
                                <img src="<?php echo esc_url($image_url); ?>" alt="<?php _e('Selected image', 'beautiful-rescues'); ?>">
                                <a href="<?php echo esc_url($image_url); ?>" target="_blank" class="view-full">
                                    <?php _e('View Full Size', 'beautiful-rescues'); ?>
                                </a>
                            </div>
                    <?php
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .verification-details {
            max-width: 1200px;
            margin: 2em auto;
            padding: 0 20px;
        }
        .verification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2em;
        }
        .verification-status {
            padding: 0.5em 1em;
            border-radius: 4px;
            font-weight: bold;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-verified {
            background-color: #d4edda;
            color: #155724;
        }
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        .verification-meta {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .donor-details table {
            width: 100%;
            margin-bottom: 2em;
        }
        .donor-details th {
            width: 150px;
            text-align: left;
            padding: 10px;
        }
        .verification-preview {
            margin: 1em 0;
            max-width: 800px;
        }
        .verification-preview img {
            max-width: 100%;
            height: auto;
        }
        .pdf-preview {
            padding: 20px;
            background: #f5f5f5;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 1em;
        }
        .image-item {
            position: relative;
        }
        .image-item img {
            width: 100%;
            height: auto;
            border-radius: 5px;
        }
        .view-full {
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.7);
            color: #fff;
            padding: 5px 10px;
            border-radius: 3px;
            text-decoration: none;
            font-size: 0.9em;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .image-item:hover .view-full {
            opacity: 1;
        }
        .admin-actions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 2em;
            border: 1px solid #dee2e6;
        }
        .status-update-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .status-update-message {
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
            display: none;
        }
        .status-update-message.success {
            background-color: #d4edda;
            color: #155724;
            display: block;
        }
        .status-update-message.error {
            background-color: #f8d7da;
            color: #721c24;
            display: block;
        }
    </style>

    <?php if (current_user_can('manage_options')) : ?>
    <script>
    jQuery(document).ready(function($) {
        $('#status-update-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $message = $form.find('.status-update-message');
            const status = $('#verification-status').val();
            
            $.ajax({
                url: beautifulRescuesReview.ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_donation_status',
                    nonce: beautifulRescuesReview.nonce,
                    donation_id: <?php echo $verification_id; ?>,
                    status: status
                },
                beforeSend: function() {
                    $form.find('button').prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        // Update the status display
                        const statusText = $('#verification-status option:selected').text();
                        const statusClass = 'status-' + status;
                        
                        $('.verification-status')
                            .removeClass('status-pending status-verified status-rejected')
                            .addClass(statusClass)
                            .text(statusText);
                        
                        $message
                            .removeClass('error')
                            .addClass('success')
                            .text('Status updated successfully');
                    } else {
                        $message
                            .removeClass('success')
                            .addClass('error')
                            .text('Failed to update status');
                    }
                },
                error: function() {
                    $message
                        .removeClass('success')
                        .addClass('error')
                        .text('An error occurred while updating the status');
                },
                complete: function() {
                    $form.find('button').prop('disabled', false);
                }
            });
        });
    });
    </script>
    <?php endif; ?>

<?php
endwhile;

get_footer(); 