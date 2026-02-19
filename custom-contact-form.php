<?php
/*
Plugin Name: Custom Contact Form 
Description: A professional, AJAX-powered inquiry system with CSV Export. Shortcodes: [contact_form id="1"]
Version: 3.0
Author: SDkid
*/

if (!defined('ABSPATH'))
    exit;

/**
 * 1. DATABASE & EXPORT LOGIC
 */
register_activation_hook(__FILE__, 'dcf_create_db_tables');
function dcf_create_db_tables()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $tables = [$wpdb->prefix . 'contact_form', $wpdb->prefix . 'contact_form_2'];
    foreach ($tables as $table) {
        $sql = "CREATE TABLE $table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name tinytext NOT NULL,
            number varchar(15) NOT NULL,
            email varchar(100) NOT NULL,
            patient_name tinytext NOT NULL,
            location varchar(50) NOT NULL,
            time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql);
    }
}

// CSV Export Handler
add_action('admin_init', 'dcf_export_csv_handler');
function dcf_export_csv_handler()
{
    if (isset($_GET['action']) && $_GET['action'] === 'dcf_export_csv') {
        if (!current_user_can('manage_options'))
            return;

        global $wpdb;
        $form_id = isset($_GET['form']) ? sanitize_text_field($_GET['form']) : '1';
        $table = ($form_id === '2') ? $wpdb->prefix . 'contact_form_2' : $wpdb->prefix . 'contact_form';

        $results = $wpdb->get_results("SELECT * FROM $table ORDER BY time DESC", ARRAY_A);

        $filename = "leads-form-{$form_id}-" . date('Y-m-d') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        fputcsv($output, array('ID', 'Name', 'Phone', 'Email', 'Patient Name', 'Location', 'Date'));

        if ($results) {
            foreach ($results as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit;
    }
}

/**
 * 2. FRONTEND ASSETS (Modernized)
 */
add_action('wp_enqueue_scripts', 'dcf_enqueue_assets');
function dcf_enqueue_assets()
{
    wp_enqueue_style('dcf-fonts', 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

    $custom_css = "
        :root { --dcf-primary: #2563eb; --dcf-bg: #ffffff; --dcf-text: #1e293b; --dcf-border: #e2e8f0; }
        .dcf-form-card { background: var(--dcf-bg); border-radius: 16px; padding: 40px; max-width: 550px; font-family: 'Plus Jakarta Sans', sans-serif; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); border: 1px solid var(--dcf-border); margin: 20px auto; }
        .dcf-form-card h3 { margin: 0 0 8px 0; color: var(--dcf-text); font-size: 1.75rem; font-weight: 700; letter-spacing: -0.02em; }
        .dcf-subtext { color: #64748b; margin-bottom: 24px; font-size: 14px; }
        .dcf-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .dcf-field { position: relative; margin-bottom: 20px; }
        .dcf-field.full { grid-column: span 2; }
        .dcf-field label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #475569; text-transform: uppercase; letter-spacing: 0.025em; }
        .dcf-field input, .dcf-field select { width: 100%; padding: 12px 16px; border: 1.5px solid var(--dcf-border); border-radius: 8px; font-size: 15px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); background: #f8fafc; color: var(--dcf-text); }
        .dcf-field input:focus { border-color: var(--dcf-primary); background: #fff; outline: none; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); }
        .dcf-submit { width: 100%; background: var(--dcf-primary); color: #fff; border: none; padding: 14px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; margin-top: 10px; display: flex; justify-content: center; align-items: center; gap: 8px; }
        .dcf-submit:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2); }
        .dcf-msg { margin-top: 20px; padding: 12px; border-radius: 8px; display: none; font-weight: 500; text-align: center; }
        .dcf-msg.success { display: block; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; animation: slideIn 0.4s ease; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 480px) { .dcf-grid { grid-template-columns: 1fr; } .dcf-field.full { grid-column: auto; } }
    ";
    wp_add_inline_style('dcf-fonts', $custom_css);

    wp_add_inline_script('jquery', "
        jQuery(document).ready(function($) {
            $('.dcf-form').on('submit', function(e) {
                e.preventDefault();
                var form = $(this), btn = form.find('.dcf-submit'), msg = form.find('.dcf-msg');
                btn.prop('disabled', true).html('<span class=\"dcf-spinner\"></span> Processing...');
                $.ajax({
                    url: '" . admin_url('admin-ajax.php') . "',
                    type: 'POST',
                    data: form.serialize(),
                    success: function(res) {
                        if(res.success) {
                            msg.removeClass('error').addClass('success').text(res.data).show();
                            form[0].reset();
                        } else { msg.removeClass('success').addClass('error').text(res.data).show(); }
                    },
                    complete: function() { btn.prop('disabled', false).text('Send Request'); }
                });
            });
        });
    ");
}

/**
 * 3. UI TEMPLATE
 */
add_shortcode('contact_form', 'dcf_shortcode_handler');
function dcf_shortcode_handler($atts)
{
    $a = shortcode_atts(['id' => '1'], $atts);
    ob_start(); ?>
    <div class="dcf-form-card">
        <h3>Contact Our Office</h3>
        <p class="dcf-subtext">Fill out the form below and our team will be in touch shortly.</p>
        <form class="dcf-form">
            <div class="dcf-grid">
                <div class="dcf-field">
                    <label>Your Name</label>
                    <input type="text" name="dcf_name" placeholder="John Doe" required>
                </div>
                <div class="dcf-field">
                    <label>Phone Number</label>
                    <input type="tel" name="dcf_number" placeholder="(555) 000-0000" required>
                </div>
                <div class="dcf-field full">
                    <label>Email Address</label>
                    <input type="email" name="dcf_email" placeholder="john@example.com" required>
                </div>
                <div class="dcf-field full">
                    <label>Patient Name</label>
                    <input type="text" name="dcf_patient_name" placeholder="Full name of patient" required>
                </div>
                <div class="dcf-field full">
                    <label>Preferred Location</label>
                    <select name="dcf_location" required>
                        <option value="" disabled selected>Select an office location</option>
                        <option value="Downtown Medical">Downtown Medical</option>
                        <option value="Westside Clinic">Westside Clinic</option>
                    </select>
                </div>
            </div>
            <input type="hidden" name="action" value="dcf_submit_action">
            <input type="hidden" name="form_id" value="<?php echo esc_attr($a['id']); ?>">
            <?php wp_nonce_field('dcf_secure_nonce', 'dcf_security'); ?>
            <button type="submit" class="dcf-submit">Send Request</button>
            <div class="dcf-msg"></div>
        </form>
    </div>
    <?php return ob_get_clean();
}

/**
 * 4. BACKEND HANDLER
 */
add_action('wp_ajax_dcf_submit_action', 'dcf_handle_submission');
add_action('wp_ajax_nopriv_dcf_submit_action', 'dcf_handle_submission');
function dcf_handle_submission()
{
    check_ajax_referer('dcf_secure_nonce', 'dcf_security');
    global $wpdb;
    $form_id = sanitize_text_field($_POST['form_id']);
    $table_name = ($form_id === '2') ? $wpdb->prefix . 'contact_form_2' : $wpdb->prefix . 'contact_form';

    $data = [
        'name' => sanitize_text_field($_POST['dcf_name']),
        'number' => sanitize_text_field($_POST['dcf_number']),
        'email' => sanitize_email($_POST['dcf_email']),
        'patient_name' => sanitize_text_field($_POST['dcf_patient_name']),
        'location' => sanitize_text_field($_POST['dcf_location']),
        'time' => current_time('mysql')
    ];

    if ($wpdb->insert($table_name, $data)) {
        wp_send_json_success('Request sent! We will contact you soon.');
    }
    wp_send_json_error('Error saving your request.');
}

/**
 * 5. MODERNIZED ADMIN PAGE
 */
add_action('admin_menu', function () {
    add_menu_page('Leads', 'Leads', 'manage_options', 'dcf-leads', 'dcf_render_admin_page', 'dashicons-groups', 25);
});

function dcf_render_admin_page()
{
    global $wpdb;
    $form_id = isset($_GET['form']) ? $_GET['form'] : '1';
    $table = ($form_id === '2') ? $wpdb->prefix . 'contact_form_2' : $wpdb->prefix . 'contact_form';
    $results = $wpdb->get_results("SELECT * FROM $table ORDER BY time DESC");
    $export_url = add_query_arg(['action' => 'dcf_export_csv', 'form' => $form_id], admin_url());
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Patient Leads</h1>
        <a href="<?php echo esc_url($export_url); ?>" class="page-title-action">Export CSV</a>
        <hr class="wp-header-end">

        <div class="nav-tab-wrapper" style="margin-bottom: 20px;">
            <a href="?page=dcf-leads&form=1" class="nav-tab <?php echo $form_id == '1' ? 'nav-tab-active' : ''; ?>">Main
                Form</a>
            <a href="?page=dcf-leads&form=2"
                class="nav-tab <?php echo $form_id == '2' ? 'nav-tab-active' : ''; ?>">Secondary Form</a>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 15%;">Date</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Patient</th>
                    <th>Location</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($results):
                    foreach ($results as $row): ?>
                        <tr>
                            <td><?php echo date('M j, Y - g:i a', strtotime($row->time)); ?></td>
                            <td><strong><?php echo esc_html($row->name); ?></strong></td>
                            <td><?php echo esc_html($row->number); ?></td>
                            <td><a href="mailto:<?php echo esc_attr($row->email); ?>"><?php echo esc_html($row->email); ?></a></td>
                            <td><?php echo esc_html($row->patient_name); ?></td>
                            <td><span class="dcf-pill"><?php echo esc_html($row->location); ?></span></td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="6">No leads recorded.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <style>
        .dcf-pill {
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .page-title-action {
            background: #2271b1 !important;
            color: #fff !important;
            border: none !important;
        }
    </style>
<?php }