<?php
/*
Plugin Name: NeoDB Integration
Description: Integrates NeoDB records into WordPress using a shortcode.
Version: 1.2.4
Author: anotherdayu.com veryjack.com
*/

// Register a settings page
function neodb_add_settings_page() {
    add_options_page(
        'NeoDB Settings',
        'NeoDB Settings',
        'manage_options',
        'neodb-settings',
        'neodb_render_settings_page'
    );
}
add_action('admin_menu', 'neodb_add_settings_page');

// Render the settings page
function neodb_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>NeoDB Settings</h1>
        
        <?php if (isset($_POST['neodb_manual_update'])): ?>
            <?php 
            if (check_admin_referer('neodb_manual_update_action', 'neodb_manual_update_nonce')) {
                neodb_manual_update();
                echo '<div class="notice notice-success"><p>数据已更新。</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Nonce 验证失败，无法更新数据。</p></div>';
            }
            ?>
        <?php endif; ?>

        <?php if (isset($_POST['neodb_clear_cache'])): ?>
            <?php 
            if (check_admin_referer('neodb_clear_cache_action', 'neodb_clear_cache_nonce')) {
                neodb_clear_cache();
                echo '<div class="notice notice-success"><p>图片缓存已清理。</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Nonce 验证失败，无法清理缓存。</p></div>';
            }
            ?>
        <?php endif; ?>

        <div class="neodb-settings-container">
            <form method="post" action="options.php">
                <?php
                settings_fields('neodb_settings');
                do_settings_sections('neodb-settings');
                submit_button('保存设置');
                ?>
            </form>

            <div class="neodb-actions-form" style="margin-top: 20px;">
                <div class="neodb-action-item">
                    <form method="post" style="display: inline-block; margin-right: 20px;">
                        <?php wp_nonce_field('neodb_manual_update_action', 'neodb_manual_update_nonce'); ?>
                        <input type="submit" name="neodb_manual_update" class="button button-secondary" value="手动更新数据">
                        <p class="description">从 NeoDB 获取最新数据并更新到网站。</p>
                    </form>

                    <form method="post" style="display: inline-block;">
                        <?php wp_nonce_field('neodb_clear_cache_action', 'neodb_clear_cache_nonce'); ?>
                        <input type="submit" name="neodb_clear_cache" class="button button-secondary" value="清理图片缓存">
                        <p class="description">清理本地缓存的所有图片文件，下次访问时会重新从 NeoDB 获取。</p>
                    </form>
                </div>
            </div>
        </div>

        <style>
            .neodb-settings-container {
                max-width: 800px;
            }
            .form-table th {
                width: 200px;
            }
            .neodb-actions-form {
                padding: 15px;
                background: #f8f9fa;
                border-radius: 4px;
            }
            .neodb-action-item {
                margin-bottom: 10px;
            }
            .neodb-action-item .description {
                margin: 5px 0 15px 0;
                color: #666;
            }
        </style>
    </div>
    <?php
}

// Register settings
function neodb_register_settings() {
    register_setting('neodb_settings', 'neodb_token');
    register_setting('neodb_settings', 'neodb_text_color');
    register_setting('neodb_settings', 'neodb_hover_color');
    
    // 注册每个分类的启用选项
    $categories = array('movie', 'tv', 'book', 'music', 'game', 'podcast', 'performance');
    foreach ($categories as $category) {
        register_setting('neodb_settings', 'neodb_enable_' . $category);
    }

    add_settings_section(
        'neodb_main_section',
        '基本设置',
        'neodb_section_callback',
        'neodb-settings'
    );

    add_settings_field(
        'neodb_token',
        'API Token',
        'neodb_token_callback',
        'neodb-settings',
        'neodb_main_section'
    );

    add_settings_field(
        'neodb_text_color',
        '文字颜色',
        'neodb_text_color_callback',
        'neodb-settings',
        'neodb_main_section'
    );

    add_settings_field(
        'neodb_hover_color',
        '悬停颜色',
        'neodb_hover_color_callback',
        'neodb-settings',
        'neodb_main_section'
    );

    add_settings_field(
        'neodb_categories',
        '启用的分类',
        'neodb_categories_callback',
        'neodb-settings',
        'neodb_main_section'
    );
}
add_action('admin_init', 'neodb_register_settings');

// Settings callbacks
function neodb_section_callback() {
    echo '<p>配置您的 NeoDB 设置。</p>';
}

function neodb_token_callback() {
    $token = get_option('neodb_token');
    echo '<input type="text" name="neodb_token" value="' . esc_attr($token) . '" class="regular-text">';
    echo '<p class="description">从 NeoDB 获取的 API Token</p>';
}

function neodb_text_color_callback() {
    $color = get_option('neodb_text_color', '#666666');
    echo '<input type="color" name="neodb_text_color" value="' . esc_attr($color) . '">';
}

function neodb_hover_color_callback() {
    $color = get_option('neodb_hover_color', '#007bff');
    echo '<input type="color" name="neodb_hover_color" value="' . esc_attr($color) . '">';
}

function neodb_categories_callback() {
    $categories = array(
        'movie' => '电影',
        'tv' => '剧集',
        'book' => '书籍',
        'music' => '音乐',
        'game' => '游戏',
        'podcast' => '播客',
        'performance' => '表演'
    );

    foreach ($categories as $key => $label) {
        $enabled = get_option('neodb_enable_' . $key, '1');
        echo '<label style="display: block; margin-bottom: 8px;">';
        echo '<input type="checkbox" name="neodb_enable_' . $key . '" value="1" ' . checked('1', $enabled, false) . '>';
        echo ' ' . esc_html($label);
        echo '</label>';
    }
}

// 分类设置验证函数
function neodb_sanitize_categories($input) {
    $valid_categories = array('movie', 'tv', 'book', 'music', 'game', 'podcast', 'performance');
    return is_array($input) ? array_intersect($input, $valid_categories) : array();
}

// 设置区块说明回调函数
function neodb_api_section_callback() {
    echo '<p>设置您的 NeoDB API Token，用于获取数据。</p>';
}

function neodb_appearance_section_callback() {
    echo '<p>自定义显示效果，包括文字颜色和悬停效果。</p>';
}

function neodb_category_section_callback() {
    echo '<p>选择要在页面上显示的分类。</p>';
}

function neodb_update_section_callback() {
    echo '<p>手动更新数据缓存。建议在修改设置后执行更新。</p>';
}

// 字段回调函数
function neodb_token_field_callback() {
    $token = get_option('neodb_token');
    echo '<input type="text" id="neodb_token" name="neodb_token" value="' . esc_attr($token) . '" size="50" class="regular-text" />';
}

function neodb_text_color_field_callback() {
    $color = get_option('neodb_text_color', '#333333');
    echo '<input type="color" id="neodb_text_color" name="neodb_text_color" value="' . esc_attr($color) . '" />';
    echo '<code style="margin-left: 10px;">' . esc_html($color) . '</code>';
}

function neodb_hover_color_field_callback() {
    $color = get_option('neodb_hover_color', '#0066cc');
    echo '<input type="color" id="neodb_hover_color" name="neodb_hover_color" value="' . esc_attr($color) . '" />';
    echo '<code style="margin-left: 10px;">' . esc_html($color) . '</code>';
}

function neodb_categories_field_callback() {
    $categories = array(
        'movie' => '电影',
        'tv' => '剧集',
        'book' => '书籍',
        'music' => '音乐',
        'game' => '游戏',
        'podcast' => '播客',
        'performance' => '表演'
    );
    
    $enabled_categories = get_option('neodb_enabled_categories', array_keys($categories));
    
    echo '<div class="neodb-categories-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px;">';
    foreach ($categories as $key => $label) {
        $checked = in_array($key, $enabled_categories) ? 'checked' : '';
        echo '<label style="display: flex; align-items: center; gap: 5px;">';
        echo '<input type="checkbox" name="neodb_enabled_categories[]" value="' . esc_attr($key) . '" ' . $checked . '>';
        echo esc_html($label);
        echo '</label>';
    }
    echo '</div>';
}

// 清除图片缓存
function neodb_clear_cache() {
    $cache_dir = plugin_dir_path(__FILE__) . 'cache';
    if (is_dir($cache_dir)) {
        $files = glob($cache_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file) && !in_array(basename($file), array('.htaccess'))) {
                unlink($file);
            }
        }
    }
}

// Manual update function
function neodb_manual_update() {
    $categories = array('book', 'movie', 'tv', 'podcast', 'music', 'game', 'performance');
    $type = 'complete';
    foreach ($categories as $category) {
        $data = fetch_neodb_data($category, $type);
        if ($data) {
            set_transient('neodb_' . $category . '_' . $type, $data, HOUR_IN_SECONDS);
        }
    }
    
    // 清除所有分类的缓存数据
    foreach ($categories as $category) {
        delete_transient('neodb_' . $category . '_complete');
    }
}

// Fetch NeoDB data
function fetch_neodb_data($category, $type, $page = 1) {
    $token = get_option('neodb_token');
    if (!$token) {
        return null;
    }

    $url = sprintf('https://neodb.social/api/me/shelf/%s?category=%s&page=%d', $type, $category, $page);

    $response = wp_remote_get($url, array(
        'headers' => array(
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token
        )
    ));

    if (is_wp_error($response)) {
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $data;
}

// 确保缓存目录存在
function neodb_ensure_cache_dir() {
    $cache_dir = plugin_dir_path(__FILE__) . 'cache';
    if (!file_exists($cache_dir)) {
        wp_mkdir_p($cache_dir);
    }
    
    // 创建 .htaccess 文件来允许访问图片
    $htaccess_file = $cache_dir . '/.htaccess';
    if (!file_exists($htaccess_file)) {
        $htaccess_content = "Options -Indexes\n";
        $htaccess_content .= "<IfModule mod_mime.c>\n";
        $htaccess_content .= "AddType image/jpeg .jpg .jpeg\n";
        $htaccess_content .= "AddType image/png .png\n";
        $htaccess_content .= "AddType image/webp .webp\n";
        $htaccess_content .= "</IfModule>\n";
        file_put_contents($htaccess_file, $htaccess_content);
    }
    
    return $cache_dir;
}

// 获取或缓存图片
function neodb_get_cached_image($image_url) {
    if (empty($image_url)) {
        return '';
    }

    // 生成缓存文件名
    $cache_filename = md5($image_url);
    $image_extension = pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION);
    if (empty($image_extension)) {
        $image_extension = 'jpg';
    }
    $cache_file = neodb_ensure_cache_dir() . '/' . $cache_filename . '.' . $image_extension;
    
    // 如果缓存存在且未过期（30天），直接返回缓存URL
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < 30 * 24 * 60 * 60)) {
        return plugins_url('cache/' . $cache_filename . '.' . $image_extension, __FILE__);
    }
    
    // 下载图片
    $response = wp_remote_get($image_url, array(
        'timeout' => 15,
        'sslverify' => false
    ));
    
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return $image_url; // 如果下载失败，返回原始URL
    }
    
    $image_data = wp_remote_retrieve_body($response);
    if (file_put_contents($cache_file, $image_data)) {
        return plugins_url('cache/' . $cache_filename . '.' . $image_extension, __FILE__);
    }
    
    return $image_url;
}

// Render items function
function neodb_render_items($items) {
    foreach ($items as $value): 
        $item = $value['item'];
        $rating = !empty($item['rating']) ? floatval($item['rating']) : 0;
        $stars = min(5, round($rating / 2));
        $stars_html = str_repeat('⭐', max(1, $stars));
        ?>
        <div class="neodb-item">
            <div class="neodb-cover">
                <a href="<?php echo esc_url($item['id']); ?>" target="_blank" rel="noreferrer">
                    <?php 
                    $image_url = neodb_get_cached_image($item['cover_image_url']);
                    if ($image_url) {
                        echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($item['display_title']) . '" loading="lazy">';
                    }
                    ?>
                </a>
            </div>
            <div class="neodb-info">
                <div class="neodb-rating">
                    <div class="rating-stars"><?php echo $stars_html; ?></div>
                    <div class="rating-score"><?php echo number_format($rating, 1); ?></div>
                </div>
                <h3 class="neodb-title">
                    <a href="<?php echo esc_url($item['id']); ?>" target="_blank" rel="noreferrer">
                        <?php echo esc_html($item['display_title']); ?>
                    </a>
                </h3>
            </div>
        </div>
    <?php
    endforeach;
}

// Enqueue scripts and styles
function neodb_enqueue_scripts() {
    wp_enqueue_style('neodb-styles', plugins_url('css/neodb.css', __FILE__));
    wp_enqueue_script('neodb-script', plugins_url('js/neodb.js', __FILE__), array('jquery'), '1.0', true);
    wp_localize_script('neodb-script', 'neodb_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('neodb_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'neodb_enqueue_scripts');

// Ajax handler for category switching
function neodb_ajax_get_category() {
    check_ajax_referer('neodb_nonce', 'nonce');
    $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : 'movie';
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'complete';
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    
    $data = fetch_neodb_data($category, $type, $page);
    
    ob_start();
    if (!empty($data['data'])) {
        neodb_render_items($data['data']);
    }
    $html = ob_get_clean();
    
    // 检查是否还有更多数据
    $has_more = !empty($data['data']) && count($data['data']) >= 10; // 假设每页10条数据
    $has_data = !empty($data['data']) && count($data['data']) > 0;
    
    wp_send_json_success(array(
        'html' => $html,
        'has_more' => $has_more,
        'has_data' => $has_data
    ));
}
add_action('wp_ajax_neodb_get_category', 'neodb_ajax_get_category');
add_action('wp_ajax_nopriv_neodb_get_category', 'neodb_ajax_get_category');

// Main shortcode function
function neodb_page_shortcode() {
    // 获取启用的分类
    $enabled_categories = array();
    $all_categories = array(
        'movie' => '电影',
        'tv' => '剧集',
        'book' => '书籍',
        'music' => '音乐',
        'game' => '游戏',
        'podcast' => '播客',
        'performance' => '表演'
    );

    foreach ($all_categories as $key => $value) {
        if (get_option('neodb_enable_' . $key, '1') === '1') {
            $enabled_categories[$key] = $value;
        }
    }

    if (empty($enabled_categories)) {
        return '<p>请在设置中启用至少一个分类。</p>';
    }

    // 获取颜色设置
    $text_color = get_option('neodb_text_color', '#666666');
    $hover_color = get_option('neodb_hover_color', '#007bff');

    // 获取初始数据
    $first_category = array_key_first($enabled_categories);
    $initial_data = fetch_neodb_data($first_category, 'complete', 1);
    $has_more = !empty($initial_data['data']) && count($initial_data['data']) >= 10;

    // 添加动态颜色样式
    echo '<style>
        .neodb-nav-item { color: ' . esc_attr($text_color) . '; }
        .neodb-nav-item:hover, .neodb-nav-item.active { color: ' . esc_attr($hover_color) . '; }
        .neodb-nav-item.active::after { background-color: ' . esc_attr($hover_color) . '; }
        .neodb-title a { color: ' . esc_attr($text_color) . '; }
        .neodb-title a:hover { color: ' . esc_attr($hover_color) . '; }
        .neodb-type-item { color: ' . esc_attr($text_color) . '; }
        .neodb-type-item.active { background-color: ' . esc_attr($hover_color) . '; border-color: ' . esc_attr($hover_color) . '; }
    </style>';

    // 输出HTML结构
    $output = '<div class="neodb-container">';
    
    // 分类导航
    $output .= '<div class="neodb-nav">';
    $first = true;
    foreach ($enabled_categories as $key => $value) {
        $active_class = $first ? ' active' : '';
        $output .= '<button class="neodb-nav-item' . $active_class . '" data-category="' . esc_attr($key) . '">' . esc_html($value) . '</button>';
        $first = false;
    }
    $output .= '</div>';

    // 类型切换
    $output .= '<div class="neodb-type-nav">';
    $output .= '<button class="neodb-type-item active" data-type="complete">看过</button>';
    $output .= '<button class="neodb-type-item" data-type="progress">在看</button>';
    $output .= '<button class="neodb-type-item" data-type="wishlist">想看</button>';
    $output .= '</div>';

    // 内容网格
    $output .= '<div class="neodb-grid" data-page="1" data-type="complete">';
    if (!empty($initial_data['data'])) {
        ob_start();
        neodb_render_items($initial_data['data']);
        $output .= ob_get_clean();
    }
    $output .= '</div>';

    // 加载更多按钮
    $output .= '<div class="neodb-load-more" style="text-align: center; margin-top: 20px;">';
    $output .= '<button class="load-more-button button"' . ($has_more ? '' : ' style="display: none;"') . '>加载更多</button>';
    $output .= '<p class="no-more-items"' . ($has_more ? ' style="display: none;"' : '') . '>没有更多内容了</p>';
    $output .= '</div>';
    
    $output .= '</div>';
    
    return $output;
}
add_shortcode('neodb_page', 'neodb_page_shortcode');
