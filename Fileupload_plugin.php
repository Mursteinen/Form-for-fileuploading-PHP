<?php
/*
Plugin Name: Form Submission with SQLite
Description: A simple form submission plugin using SQLite
Version: 2
Author: Kevin M Thorsen
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class FormSubmissionPlugin {

    private $db;

    public function __construct() {
        add_shortcode('custom_form', [$this, 'render_form']);
        add_action('init', [$this, 'handle_form_submission']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        $this->initialize_database();
    }

    // Enqueue styles for the form
    public function enqueue_styles() {
        wp_enqueue_style('form-submission-plugin-styles', plugin_dir_url(__FILE__) . 'assets/form-styles.css');
    }

    // Initialize SQLite database
    public function initialize_database() {
        global $wpdb;

        $db_file = __DIR__ . '/form_submission_db.sqlite';
        
        if (!file_exists($db_file)) {
            $this->db = new PDO('sqlite:' . $db_file);
            $this->db->exec("
                CREATE TABLE submissions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT,
                    email TEXT,
                    phone TEXT,
                    platetykkelse TEXT,
                    comment TEXT,
                    file_path TEXT
                );
            ");
        } else {
            $this->db = new PDO('sqlite:' . $db_file);
        }
    }

    // Render the form
    public function render_form() {
        ob_start();
        ?>
        <form action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" method="post" enctype="multipart/form-data">
            <label for="name">Navn: </label>
            <input type="text" id="name" name="name" required><br><br>

            <label for="email">E-Mail: </label>
            <input type="email" id="email" name="email" required><br><br>

            <label for="phone">Telefon: </label>
            <input type="tel" id="phone" name="phone" required><br><br>

            <label for="platetykkelse">Platetykkelse: </label>
            <input type="text" id="platetykkelse" name="platetykkelse" required><br><br>

            <label for="comment">Kommentar (valgfritt): </label>
            <textarea id="comment" name="comment"></textarea><br><br>

            <label for="file">Fil(er): </label>
            <input type="file" id="file" name="file"><br><br>

            <input type="submit" name="submit_form" value="Send">
        </form>
        <?php
        return ob_get_clean();
    }

    // Handle form submission
    public function handle_form_submission() {
        if (isset($_POST['submit_form'])) {
            $name = sanitize_text_field($_POST['name']);
            $email = sanitize_email($_POST['email']);
            $phone = sanitize_text_field($_POST['phone']);
            $platetykkelse = sanitize_text_field($_POST['platetykkelse']);
            $comment = isset($_POST['comment']) ? sanitize_text_field($_POST['comment']) : '';

            // Handle file upload
            $file_path = '';
            if (!empty($_FILES['file']['name'])) {
                $upload_dir = wp_upload_dir();
                $upload_file = $upload_dir['path'] . '/' . basename($_FILES['file']['name']);
                
                if (move_uploaded_file($_FILES['file']['tmp_name'], $upload_file)) {
                    $file_path = $upload_file;
                }
            }

            // Save to SQLite
            $stmt = $this->db->prepare("
                INSERT INTO submissions (name, email, phone, platetykkelse, comment, file_path)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $email, $phone, $platetykkelse, $comment, $file_path]);

            echo "<p>Thank you for your submission!</p>";
        }
    }

    // Add Admin Menu
    public function add_admin_menu() {
        add_menu_page(
            'Form Submissions', // Page title
            'Submissions',      // Menu title
            'manage_options',   // Capability
            'form_submissions', // Menu slug
            [$this, 'admin_page_content'], // Callback function
            'dashicons-media-document',   // Icon
            6                             // Position
        );
    }

    // Admin Page Content
    public function admin_page_content() {
        ?>
        <div class="wrap">
            <h1>Form Submissions</h1>
            <?php $this->display_submissions(); ?>
        </div>
        <?php
    }

    // Display Submissions
    public function display_submissions() {
        $stmt = $this->db->query("SELECT * FROM submissions");
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($submissions) {
            echo '<table class="widefat fixed" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Platetykkelse</th>
                            <th>Comment</th>
                            <th>File</th>
                        </tr>
                    </thead>
                    <tbody>';
            foreach ($submissions as $submission) {
                echo '<tr>';
                echo '<td>' . esc_html($submission['id']) . '</td>';
                echo '<td>' . esc_html($submission['name']) . '</td>';
                echo '<td>' . esc_html($submission['email']) . '</td>';
                echo '<td>' . esc_html($submission['phone']) . '</td>';
                echo '<td>' . esc_html($submission['platetykkelse']) . '</td>';
                echo '<td>' . esc_html($submission['comment']) . '</td>';
                echo '<td>';
                if (!empty($submission['file_path'])) {
                    echo '<a href="' . esc_url(wp_upload_dir()['url'] . '/' . basename($submission['file_path'])) . '" download>Download File</a>';
                } else {
                    echo 'No File';
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No submissions found.</p>';
        }
    }
}

// Initialize the plugin
new FormSubmissionPlugin();

