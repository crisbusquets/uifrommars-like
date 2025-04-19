<?php
/**
 * Plugin Name: uiFromMars Like Button
 * Description: Adds a like button to posts and displays most liked articles
 * Version: 1.0.0
 * Author: uiFromMars
 * Text Domain: uifrommars-like-button
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class UIFromMars_Like_Button {
    
    public function __construct() {
        // Initialize plugin
        add_action('init', array($this, 'init'));
        
        // Load text domain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Register shortcode
        add_shortcode('uifrommars_like_button', array($this, 'like_button_shortcode'));
        
        // Add AJAX handlers
        add_action('wp_ajax_uifrommars_like_post', array($this, 'handle_like'));
        add_action('wp_ajax_nopriv_uifrommars_like_post', array($this, 'handle_like'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register block
        add_action('init', array($this, 'register_like_block'));
    }
    
    // Load plugin text domain for translations
    public function load_textdomain() {
        load_plugin_textdomain('uifrommars-like-button', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function init() {
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Register post meta
        register_post_meta('post', '_uifrommars_like_count', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'integer',
            'default' => 0,
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script(
            'uifrommars-like-button-js',
            plugin_dir_url(__FILE__) . 'js/like-button.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_localize_script('uifrommars-like-button-js', 'uifrommarsLike', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('uifrommars_like_nonce')
        ));
        
        wp_enqueue_style(
            'uifrommars-like-button-css',
            plugin_dir_url(__FILE__) . 'css/like-button.css',
            array(),
            '1.0.0'
        );
    }
    
    public function like_button_shortcode($atts) {
        $post_id = get_the_ID();
        $like_count = $this->get_like_count($post_id);
        $cookie_name = 'uifrommars_liked_' . $post_id;
        $is_liked = isset($_COOKIE[$cookie_name]) ? true : false;
        
        // Text translations
        $like_text = __('Like', 'uifrommars-like-button');
        $liked_text = __('Liked', 'uifrommars-like-button');
        
        ob_start();
        ?>
<div class="uifrommars-like-button" data-post-id="<?php echo esc_attr($post_id); ?>"
  data-liked="<?php echo $is_liked ? 'true' : 'false'; ?>">
  <button class="uifrommars-like-btn <?php echo $is_liked ? 'liked' : ''; ?>"
    <?php echo $is_liked ? 'disabled' : ''; ?>>
    <span class="like-icon">❤</span>
    <span class="like-text"><?php echo $is_liked ? $liked_text : $like_text; ?></span>
    <span class="uifrommars-like-count"><?php echo esc_html($like_count); ?></span>
  </button>
</div>
<?php
        return ob_get_clean();
    }
    
    public function handle_like() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'uifrommars_like_nonce')) {
            wp_send_json_error('Invalid nonce');
            die();
        }
        
        // Get post ID
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
            die();
        }
        
        // Get current like count
        $like_count = $this->get_like_count($post_id);
        
        // Increment like count
        $new_count = $like_count + 1;
        update_post_meta($post_id, '_uifrommars_like_count', $new_count);
        
        // Set cookie to prevent multiple likes (expires in 30 days)
        $cookie_name = 'uifrommars_liked_' . $post_id;
        setcookie($cookie_name, '1', time() + (30 * DAY_IN_SECONDS), '/');
        
        wp_send_json_success(array(
            'count' => $new_count
        ));
        die();
    }
    
    public function get_like_count($post_id) {
        return (int) get_post_meta($post_id, '_uifrommars_like_count', true);
    }
    
    // Admin menu functions
    public function add_admin_menu() {
        add_menu_page(
            __('Mars Likes', 'uifrommars-like-button'),
            __('Mars Likes', 'uifrommars-like-button'),
            'manage_options',
            'uifrommars-likes',
            array($this, 'render_admin_page'),
            'dashicons-heart',
            30
        );
    }
    
    public function render_admin_page() {
        // Check if we need to reset likes
        $this->process_reset_actions();
        
        // Display admin notices
        settings_errors('uifrommars_likes');
        
        // Get all posts with like count
        $args = array(
          'post_type'      => 'post',
          'posts_per_page' => -1,
          'meta_key'       => '_uifrommars_like_count',
          'orderby'        => 'meta_value_num',
          'order'          => 'DESC',
          'meta_query'     => array(
              array(
                  'key'     => '_uifrommars_like_count',
                  'value'   => 0,
                  'compare' => '>',
                  'type'    => 'NUMERIC',
              ),
          ),
      );
      $posts = get_posts( $args );
        
        ?>
<div class="wrap">
  <h1><?php echo esc_html__('Post Likes Overview', 'uifrommars-like-button'); ?></h1>

  <!-- Reset all likes button -->
  <div class="reset-all-container" style="margin-bottom: 20px;">
    <form method="post"
      onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to reset ALL likes? This cannot be undone.', 'uifrommars-like-button')); ?>');">
      <?php wp_nonce_field('uifrommars_reset_all_likes', 'uifrommars_reset_all_nonce'); ?>
      <input type="hidden" name="uifrommars_action" value="reset_all_likes">
      <button type="submit"
        class="button button-secondary"><?php echo esc_html__('Reset All Likes', 'uifrommars-like-button'); ?></button>
    </form>
  </div>

  <table class="wp-list-table widefat fixed striped posts">
    <thead>
      <tr>
        <th><?php echo esc_html__('Post Title', 'uifrommars-like-button'); ?></th>
        <th width="150"><?php echo esc_html__('Likes', 'uifrommars-like-button'); ?></th>
        <th width="150"><?php echo esc_html__('Publication Date', 'uifrommars-like-button'); ?></th>
        <th width="100"><?php echo esc_html__('Actions', 'uifrommars-like-button'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($posts)) : ?>
      <tr>
        <td colspan="4"><?php echo esc_html__('No posts with likes found.', 'uifrommars-like-button'); ?></td>
      </tr>
      <?php else : ?>
      <?php foreach ($posts as $post) : ?>
      <tr>
        <td>
          <a href="<?php echo get_permalink($post->ID); ?>" target="_blank">
            <?php echo esc_html($post->post_title); ?>
          </a>
        </td>
        <td>
          <?php echo esc_html($this->get_like_count($post->ID)); ?>
        </td>
        <td>
          <?php echo get_the_date('', $post->ID); ?>
        </td>
        <td>
          <form method="post"
            onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to reset likes for this post?', 'uifrommars-like-button')); ?>');">
            <?php wp_nonce_field('uifrommars_reset_post_likes_' . $post->ID, 'uifrommars_reset_post_nonce_' . $post->ID); ?>
            <input type="hidden" name="uifrommars_action" value="reset_post_likes">
            <input type="hidden" name="post_id" value="<?php echo esc_attr($post->ID); ?>">
            <button type="submit"
              class="button button-small"><?php echo esc_html__('Reset', 'uifrommars-like-button'); ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="notice notice-info" style="margin-top: 20px;">
    <p><strong><?php echo esc_html__('Note:', 'uifrommars-like-button'); ?></strong>
      <?php echo esc_html__('If you\'re logged in and see "Liked" buttons after resetting likes, try using an incognito/private browser window to see the correct state or clear your browser cookies.', 'uifrommars-like-button'); ?>
    </p>
  </div>
</div>
<?php
    }
    /**
 * Reset likes for one post or all posts.
 *
 * @param int $post_id (Optional) If provided, only reset that post; otherwise reset all.
 */
private function reset_likes( $post_id = 0 ) {
    // MUST match the first argument of wp_nonce_field() in your forms
    $action   = $post_id
        ? "uifrommars_reset_post_likes_{$post_id}"
        : 'uifrommars_reset_all_likes';

    // These are the field names you used in wp_nonce_field()
    $nonce_id = $post_id
        ? "uifrommars_reset_post_nonce_{$post_id}"
        : 'uifrommars_reset_all_nonce';

  if ( empty( $_POST[ $nonce_id ] )
    || ! wp_verify_nonce( $_POST[ $nonce_id ], $action )
  ) {
      add_settings_error(
          'uifrommars_likes',
          'security',
          __( 'Security verification failed.', 'uifrommars-like-button' ),
          'error'
      );
      return;
  }

  if ( $post_id ) {
      update_post_meta( $post_id, '_uifrommars_like_count', 0 );
  } else {
      $all = get_posts([
          'post_type'      => 'post',
          'posts_per_page' => -1,
          'meta_key'       => '_uifrommars_like_count',
          'meta_value_num' => 0,
          'compare'        => '>',
      ]);
      foreach ( $all as $p ) {
          update_post_meta( $p->ID, '_uifrommars_like_count', 0 );
      }
  }

  add_settings_error(
      'uifrommars_likes',
      'success',
      __( 'Likes reset successfully.', 'uifrommars-like-button' ),
      'updated'
  );
}

/**
* Dispatch reset based on user action.
*/
private function process_reset_actions() {
  if ( empty( $_POST['uifrommars_action'] ) ) {
      return;
  }
  $action = sanitize_text_field( $_POST['uifrommars_action'] );

  if ( 'reset_all_likes' === $action ) {
      $this->reset_likes();
  } elseif ( 'reset_post_likes' === $action && ! empty( $_POST['post_id'] ) ) {
      $this->reset_likes( intval( $_POST['post_id'] ) );
  }
}

    // Gutenberg block functions
    public function register_like_block() {
        // Skip block registration if Gutenberg is not available
        if (!function_exists('register_block_type')) {
            return;
        }
        
        // Register script for the block editor
        wp_register_script(
            'uifrommars-top-liked-block',
            plugin_dir_url(__FILE__) . 'js/top-liked-block.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
            '1.0.0'
        );
        
        // Register the block
        register_block_type('uifrommars/top-liked-posts', array(
            'editor_script' => 'uifrommars-top-liked-block',
            'render_callback' => array($this, 'render_top_liked_block')
        ));
    }
    
    public function render_top_liked_block($attributes) {
        // Default to showing 5 posts if not specified
        $count = isset($attributes['count']) ? intval($attributes['count']) : 5;
        
        // Get top liked posts
        $args = array(
            'post_type' => 'post',
            'posts_per_page' => $count,
            'meta_key' => '_uifrommars_like_count',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_uifrommars_like_count',
                    'value' => 0,
                    'compare' => '>'
                )
            )
        );
        
        $posts = get_posts($args);
        
        ob_start();
        ?>
<div class="uifrommars-top-liked-posts">
  <h3 class="uifrommars-top-liked-heading">
    <?php echo isset($attributes['title']) ? esc_html($attributes['title']) : esc_html__('Top Liked Posts', 'uifrommars-like-button'); ?>
  </h3>
  <?php if (empty($posts)) : ?>
  <p><?php echo esc_html__('No liked posts found.', 'uifrommars-like-button'); ?></p>
  <?php else : ?>
  <ul class="uifrommars-top-liked-list">
    <?php foreach ($posts as $post) : ?>
    <li class="uifrommars-top-liked-item">
      <a href="<?php echo get_permalink($post->ID); ?>">
        <?php echo esc_html($post->post_title); ?>
      </a>
      <span class="uifrommars-top-liked-count"><?php echo esc_html($this->get_like_count($post->ID)); ?>
        <?php echo esc_html__('likes', 'uifrommars-like-button'); ?></span>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
<?php
        return ob_get_clean();
    }
}

// Initialize the plugin
$uifrommars_like_button = new UIFromMars_Like_Button();