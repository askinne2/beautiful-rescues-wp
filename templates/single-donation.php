<?php
/**
 * Single Donation Template
 * 
 * @package Beautiful_Rescues
 */

// Security check - only allow admins to view this page
if (!current_user_can('edit_posts')) {
    wp_die(__('You do not have permission to view this page.', 'beautiful-rescues'));
}

get_header();

while (have_posts()) :
    the_post();
    $donation_id = get_the_ID();

    // Get donation details
    $donor_first_name = get_post_meta($donation_id, '_donor_first_name', true);
    $donor_last_name = get_post_meta($donation_id, '_donor_last_name', true);
    $donor_email = get_post_meta($donation_id, '_donor_email', true);
    $donor_phone = get_post_meta($donation_id, '_donor_phone', true);
    $donor_message = get_post_meta($donation_id, '_donor_message', true);
    $verification_file = get_post_meta($donation_id, '_verification_file', true);
    $verification_file_url = get_post_meta($donation_id, '_verification_file_url', true);
    $selected_images = get_post_meta($donation_id, '_selected_images', true);
    $submission_date = get_post_meta($donation_id, '_submission_date', true);
    
    // Get current status
    $status_terms = wp_get_object_terms($donation_id, 'donation_status');
    $current_status = !empty($status_terms) ? $status_terms[0]->name : 'pending';
    ?>

    <div class="wrap donation-details">
        <h1><?php the_title(); ?></h1>

        <div class="donation-meta">
            <div class="donation-status">
                <h3><?php _e('Donation Status', 'beautiful-rescues'); ?></h3>
                <form id="update-donation-status" method="post">
                    <?php wp_nonce_field('update_donation_status', 'donation_status_nonce'); ?>
                    <input type="hidden" name="donation_id" value="<?php echo esc_attr($donation_id); ?>">
                    <select name="donation_status" id="donation-status">
                        <option value="pending" <?php selected($current_status, 'pending'); ?>><?php _e('Pending', 'beautiful-rescues'); ?></option>
                        <option value="verified" <?php selected($current_status, 'verified'); ?>><?php _e('Verified', 'beautiful-rescues'); ?></option>
                        <option value="rejected" <?php selected($current_status, 'rejected'); ?>><?php _e('Rejected', 'beautiful-rescues'); ?></option>
                    </select>
                    <button type="submit" class="button button-primary"><?php _e('Update Status', 'beautiful-rescues'); ?></button>
                </form>
            </div>

            <div class="donor-details">
                <h3><?php _e('Donor Information', 'beautiful-rescues'); ?></h3>
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

            <?php if ($verification_file_url) : ?>
            <div class="verification-file">
                <h3><?php _e('Donation Verification', 'beautiful-rescues'); ?></h3>
                <?php
                $file_extension = strtolower(pathinfo($verification_file, PATHINFO_EXTENSION));
                $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif']);
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
                <h3><?php _e('Selected Images', 'beautiful-rescues'); ?></h3>
                <div class="images-grid">
                    <?php 
                    foreach ($selected_images as $image) : 
                        if (isset($image['id'])) :
                            // Assuming we're using Cloudinary integration
                            if (class_exists('BR_Cloudinary_Integration')) {
                                $cloudinary = new BR_Cloudinary_Integration();
                                $image_url = $cloudinary->generate_image_url($image['id'], array(
                                    'width' => 800,
                                    'height' => 800,
                                    'crop' => 'fill',
                                    'quality' => 'auto',
                                    'format' => 'auto'
                                ));
                            ?>
                            <div class="image-item">
                                <img src="<?php echo esc_url($image_url); ?>" alt="<?php _e('Selected image', 'beautiful-rescues'); ?>">
                                <a href="<?php echo esc_url($image_url); ?>" target="_blank" class="view-full">
                                    <?php _e('View Full Size', 'beautiful-rescues'); ?>
                                </a>
                            </div>
                            <?php
                            }
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .donation-details {
            max-width: 1200px;
            margin: 2em auto;
            padding: 0 20px;
        }
        .donation-meta {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .donation-status {
            margin-bottom: 2em;
            padding-bottom: 1em;
            border-bottom: 1px solid #eee;
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
    </style>

    <script>
    jQuery(document).ready(function($) {
        $('#update-donation-status').on('submit', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var status = $('#donation-status').val();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_donation_status',
                    nonce: $('#donation_status_nonce').val(),
                    donation_id: form.find('input[name="donation_id"]').val(),
                    status: status
                },
                success: function(response) {
                    if (response.success) {
                        alert('Status updated successfully');
                    } else {
                        alert('Failed to update status');
                    }
                },
                error: function() {
                    alert('An error occurred while updating the status');
                }
            });
        });
    });
    </script>

<?php
endwhile;

get_footer(); 