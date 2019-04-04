<?php
$_['d_ajax_filter_setting'] = array(
    'default' => array(
        'name' => '',
        'status' => 0,
        'show_mobile' => 1,
        'selected_categories' => array(),
        'submission' => 0,
        'button_filter_position' => 0,
        'time' => 1000,
        'selected_filters' => 1,
        'button_reset' => 1,
        'display_quantity' => 1,
        'limit_height' => 1,
        'limit_block' => 0,
        'height' => '150px',
        'count_elemnts' => 5,
        'min_elemnts' => 3,
        'title' => array(
            1 => 'Ajax Filter'
            ),
        'options' => array(),
        'attributes' => array(),
        'filters' => array(),
        'custom_style' => '',
        'theme' => 'default'
        ),
    'attributes' => array(
        'attributes' => array(),
        'default' => array(
            'type' => 'checkbox',
            'status' => '0',
            'collapse' => 1,
            'sort_order_values' => 'default',
            'sort_order' => 0
            ),
        ),
    'options' => array(
        'options' => array(),
        'default' => array(
            'type' => 'checkbox',
            'status' => '0',
            'collapse' => 1,
            'sort_order_values' => 'default',
            'sort_order' => 0
            ),
        ),
    'filters' => array(
        'filters' => array(),
        'default' => array(
            'type' => 'checkbox',
            'status' => '1',
            'collapse' => 1,
            'sort_order_values' => 'default',
            'sort_order' => 0
            ),
        ),
    'general' => array(
        'ajax' => 1,
        'content_path' => '#ajax-filter-container',
        'display_selected_top' => 0,
        'selected_path' => '#ajax-filter-container > .row:eq(1)',
        'display_out_of_stock' => 1,
        'display_sub_category' => 0,
        'multiple_attributes_value' => 0,
        'separator' => ',',
        'fade_out_product' => 1,
        'display_loader' => 1,
        'in_stock_status' => 1,
        'custom_script' => '
        d_ajax_filter.beforeRequest = function(){
            console.log("Before Request");
        }
        d_ajax_filter.beforeRender = function(json){
            console.log("Before Render");
        }
        d_ajax_filter.afterRender = function(json){
            console.log("After Render");
        }'
        ),
    'theme' => array(
        'header' => array(
            'background' => '#f7f7f7',
            'text' => '#000000',
            'title' => array (
                '1' => 'Ajax Filter'
                )
            ),

        'product_quantity' => array(
            'background' => 'rgb(244, 98, 52)',
            'text' => 'rgb(255, 255, 255)'
            ),

        'price_slider' => array(
            'background' => '#f3f4f8',
            'area_active' => '#f6a828',
            'border' => 'rgb(213, 213, 213)',
            'handle_background' => '#f6f6f6',
            'handle_border' => '#cccccc'
            ),

        'group_header' => array(
            'background' => 'rgb(244, 244, 244)',
            'text' => 'rgb(17, 17, 17)'
            ),

        'button' => array(
            'button_filter' => '#19a3df',
            'button_reset' => '#19a3df',
            'border_image' => '#111',
            'border_radius_image' => '0px',
            'button_selected' => '#b600ec'
            )
        )
    );