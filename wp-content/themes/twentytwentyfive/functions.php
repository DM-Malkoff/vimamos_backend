<?php
/**
 * Twenty Twenty-Five functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_Five
 * @since Twenty Twenty-Five 1.0
 */

// Adds theme support for post formats.
if ( ! function_exists( 'twentytwentyfive_post_format_setup' ) ) :
	/**
	 * Adds theme support for post formats.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_post_format_setup() {
		add_theme_support( 'post-formats', array( 'aside', 'audio', 'chat', 'gallery', 'image', 'link', 'quote', 'status', 'video' ) );
	}
endif;
add_action( 'after_setup_theme', 'twentytwentyfive_post_format_setup' );

// Enqueues editor-style.css in the editors.
if ( ! function_exists( 'twentytwentyfive_editor_style' ) ) :
	/**
	 * Enqueues editor-style.css in the editors.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_editor_style() {
		add_editor_style( get_parent_theme_file_uri( 'assets/css/editor-style.css' ) );
	}
endif;
add_action( 'after_setup_theme', 'twentytwentyfive_editor_style' );

// Enqueues style.css on the front.
if ( ! function_exists( 'twentytwentyfive_enqueue_styles' ) ) :
	/**
	 * Enqueues style.css on the front.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_enqueue_styles() {
		wp_enqueue_style(
			'twentytwentyfive-style',
			get_parent_theme_file_uri( 'style.css' ),
			array(),
			wp_get_theme()->get( 'Version' )
		);
	}
endif;
add_action( 'wp_enqueue_scripts', 'twentytwentyfive_enqueue_styles' );

// Registers custom block styles.
if ( ! function_exists( 'twentytwentyfive_block_styles' ) ) :
	/**
	 * Registers custom block styles.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_block_styles() {
		register_block_style(
			'core/list',
			array(
				'name'         => 'checkmark-list',
				'label'        => __( 'Checkmark', 'twentytwentyfive' ),
				'inline_style' => '
				ul.is-style-checkmark-list {
					list-style-type: "\2713";
				}

				ul.is-style-checkmark-list li {
					padding-inline-start: 1ch;
				}',
			)
		);
	}
endif;
add_action( 'init', 'twentytwentyfive_block_styles' );

// Registers pattern categories.
if ( ! function_exists( 'twentytwentyfive_pattern_categories' ) ) :
	/**
	 * Registers pattern categories.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_pattern_categories() {

		register_block_pattern_category(
			'twentytwentyfive_page',
			array(
				'label'       => __( 'Pages', 'twentytwentyfive' ),
				'description' => __( 'A collection of full page layouts.', 'twentytwentyfive' ),
			)
		);

		register_block_pattern_category(
			'twentytwentyfive_post-format',
			array(
				'label'       => __( 'Post formats', 'twentytwentyfive' ),
				'description' => __( 'A collection of post format patterns.', 'twentytwentyfive' ),
			)
		);
	}
endif;
add_action( 'init', 'twentytwentyfive_pattern_categories' );

// Registers block binding sources.
if ( ! function_exists( 'twentytwentyfive_register_block_bindings' ) ) :
	/**
	 * Registers the post format block binding source.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_register_block_bindings() {
		register_block_bindings_source(
			'twentytwentyfive/format',
			array(
				'label'              => _x( 'Post format name', 'Label for the block binding placeholder in the editor', 'twentytwentyfive' ),
				'get_value_callback' => 'twentytwentyfive_format_binding',
			)
		);
	}
endif;
add_action( 'init', 'twentytwentyfive_register_block_bindings' );

// Registers block binding callback function for the post format name.
if ( ! function_exists( 'twentytwentyfive_format_binding' ) ) :
	/**
	 * Callback function for the post format name block binding source.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return string|void Post format name, or nothing if the format is 'standard'.
	 */
	function twentytwentyfive_format_binding() {
		$post_format_slug = get_post_format();

		if ( $post_format_slug && 'standard' !== $post_format_slug ) {
			return get_post_format_string( $post_format_slug );
		}
	}
endif;

/**  Регистрация REST API endpoint для получения атрибутов товаров категории */
add_action('rest_api_init', function() {
    register_rest_route('custom/v1', '/category-attributes/(?P<id>\d+)', [
        'methods'  => 'GET',
        'callback' => 'get_category_attributes',
    ]);
});

/** Получение атрибутов товаров категории */
function get_category_attributes($request) {
    $category_id = $request['id'];
    
    // Получаем категорию
    $term = get_term($category_id, 'product_cat');
    if (!$term || is_wp_error($term)) {
        return [];
    }

    // Получаем все товары категории
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $category_id
            )
        )
    );
    
    $query = new WP_Query($args);
    $products = $query->posts;

    $attributes = [];
    foreach ($products as $product_post) {
        $product = wc_get_product($product_post->ID);
        if (!$product) continue;

        foreach ($product->get_attributes() as $attr) {
            $attr_id = $attr->is_taxonomy() ? sanitize_title($attr->get_name()) : $attr->get_id();
            
            // Получаем опции атрибута
            $options = [];
            if ($attr->is_taxonomy()) {
                $terms = $attr->get_terms();
                if (!is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $options[] = [
                            'name' => $term->name,
                            'slug' => $term->slug
                        ];
                    }
                }
            } else {
                foreach ($attr->get_options() as $option) {
                    $options[] = [
                        'name' => $option,
                        'slug' => sanitize_title($option)
                    ];
                }
            }

            // Если атрибут уже есть в массиве, объединяем опции
            if (isset($attributes[$attr_id])) {
                $existing_options = $attributes[$attr_id]['options'];
                $merged_options = array_merge($existing_options, $options);
                $unique_options = array_values(array_unique($merged_options, SORT_REGULAR));
                $attributes[$attr_id]['options'] = $unique_options;
            } else {
                $attributes[$attr_id] = [
                    'id'      => $attr->get_id(),
                    'name'    => $attr->get_name(),
                    'title'   => wc_attribute_label($attr->get_name()),
                    'slug'    => $attr->is_taxonomy() ? $attr->get_name() : sanitize_title($attr->get_name()),
                    'options' => array_values(array_unique($options, SORT_REGULAR))
                ];
            }
        }
    }

    // Сортируем атрибуты по названию (title)
    usort($attributes, function($a, $b) {
        return strcmp($a['title'], $b['title']);
    });

    // Сортируем опции каждого атрибута по названию (name)
    foreach ($attributes as &$attribute) {
        usort($attribute['options'], function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
    }

    return $attributes;
}

/**
 * Поиск товаров по нескольким фильтрам и атрибутам одного типа.
 *
 * @param array $filters Массив фильтров (например, категория, цена и т.д.).
 * @param array $attributes Массив атрибутов для фильтрации.
 * @return WP_Query Результаты поиска товаров.
 */
function search_products_by_filters_and_attributes($filters = array(), $attributes = array()) {
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'tax_query'      => array(), // Инициализируем tax_query
        'meta_query'     => array(), // Инициализируем meta_query
    );

    // Добавляем фильтр по категории
    if (!empty($filters) && isset($filters['category_id'])) {
        $args['tax_query'][] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => (int) $filters['category_id'], // Убедимся, что term_id является числом
            'operator' => 'IN', // Используем 'IN' для совместимости
        );
    }

    // Добавляем фильтрацию по цене
    if (!empty($filters) && (isset($filters['min_price']) || isset($filters['max_price']))) {
        $min_price = isset($filters['min_price']) ? (float) $filters['min_price'] : 0;
        $max_price = isset($filters['max_price']) ? (float) $filters['max_price'] : PHP_FLOAT_MAX;

        $args['meta_query'][] = array(
            'key'     => '_price',
            'value'   => array($min_price, $max_price),
            'compare' => 'BETWEEN',
            'type'    => 'NUMERIC',
        );
    }

    // Добавляем фильтрацию по атрибутам (логическое ИЛИ)
    if (!empty($attributes)) {
        foreach ($attributes as $attr_name => $attr_values) {
            $args['tax_query'][] = array(
                'taxonomy' => $attr_name,
                'field'    => 'slug',
                'terms'    => $attr_values,
                'operator' => 'IN',
            );
        }
    }

    // Устанавливаем отношение 'AND' для tax_query, если есть несколько условий
    if (count($args['tax_query']) > 1) {
        $args['tax_query']['relation'] = 'AND';
    }

    // Логирование для отладки (можно удалить после проверки)
    error_log('WP_Query args: ' . print_r($args, true));

    $query = new WP_Query($args);

    // Логирование результатов запроса
    error_log('Found posts: ' . $query->found_posts);

    return $query;
}

// Регистрация REST API endpoint для поиска товаров
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/search-products', [
        'methods'  => 'POST',
        'callback' => 'handle_product_search_request',
        'permission_callback' => '__return_true', // Разрешить доступ всем (можно настроить ограничения)
    ]);
});

/**
 * Обработчик запроса поиска товаров.
 *
 * @param WP_REST_Request $request Объект запроса.
 * @return WP_REST_Response Ответ с результатами поиска.
 */
function handle_product_search_request(WP_REST_Request $request) {
    $filters = $request->get_param('filters') ?: array();
    $attributes = $request->get_param('attributes') ?: array();

    $query = search_products_by_filters_and_attributes($filters, $attributes);

    $products = array();
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $product_id = get_the_ID();
            $product = wc_get_product($product_id);

            $products[] = array(
                'id'    => $product_id,
                'title' => get_the_title(),
                'price' => $product->get_price(),
                'link'  => get_permalink(),
            );
        }
        wp_reset_postdata();
    }

    return new WP_REST_Response($products, 200);
}
