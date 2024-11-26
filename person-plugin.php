<?php
/*
Plugin Name: Person Plugin - Customizador de Adesivos
Description: Plugin para customização de adesivos com controle de cores, fontes, etc.
Version: 1.0
Author: Evolution Design
*/

// Permitir upload de SVG
function permitir_svg_upload($mimes)
{
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}
add_filter('upload_mimes', 'permitir_svg_upload');

// Carregar Bootstrap no admin
function carregar_bootstrap_no_admin($hook_suffix)
{
    if ($hook_suffix === 'toplevel_page_plugin-adesivos') {
        wp_enqueue_style('bootstrap-css', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
        wp_enqueue_script('bootstrap-js', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js', array('jquery'), null, true);
    }
}
add_action('admin_enqueue_scripts', 'carregar_bootstrap_no_admin');

// Adicionar menu ao admin
function plugin_adicionar_menu()
{
    add_menu_page(
        'Configurações de Adesivos', // Título da página
        'Seus Adesivos',            // Título do menu
        'manage_options',           // Capacidade necessária
        'plugin-adesivos',          // Slug do menu
        'plugin_pagina_de_configuracao', // Callback para renderizar a página
        'dashicons-format-image',   // Ícone do menu
        6                           // Posição no menu
    );
}
add_action('admin_menu', 'plugin_adicionar_menu');

// Função que renderiza a página do plugin
function plugin_pagina_de_configuracao()
{
    $plugin_sticker_dir = plugin_dir_path(__FILE__) . 'assets/stickers/';
    $url_diretorio = plugin_dir_url(__FILE__) . 'assets/stickers/';

    echo '<div class="container">';
    echo '<h1>Configurações de Adesivos</h1>';
    echo '<p class="alert alert-warning">Adicione a tag <strong>"[customizador_adesivo]"</strong> na página onde você deseja que o Personalizador apareça.</p>';

    // Processar o upload após o envio do formulário
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_sticker'])) {
        plugin_processar_upload($plugin_sticker_dir);
    }

    // Listagem de adesivos
    include plugin_dir_path(__FILE__) . 'templates/admin-form.php';

    echo '</div>';
}

function plugin_processar_upload($plugin_sticker_dir)
{
    if (!isset($_POST['sticker_nonce']) || !wp_verify_nonce($_POST['sticker_nonce'], 'upload_sticker_nonce')) {
        echo '<p class="alert alert-danger">Nonce inválido!</p>';
        return;
    }

    if (isset($_FILES['sticker']) && !empty($_FILES['sticker']['name'])) {
        $file = $_FILES['sticker'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo '<p class="alert alert-danger">Erro ao fazer upload do arquivo. Código de erro: ' . $file['error'] . '</p>';
            return;
        }

        $file_type = wp_check_filetype($file['name']);
        $allowed_types = array('svg'); // Apenas permite SVG

        if (in_array($file_type['ext'], $allowed_types)) {
            // Faz o upload para o diretório padrão do WordPress
            $upload = wp_handle_upload($file, array('test_form' => false));

            if (isset($upload['error']) && $upload['error']) {
                echo '<p class="alert alert-danger">Erro ao enviar o arquivo: ' . $upload['error'] . '</p>';
            } else {
                // Registrar o arquivo na biblioteca de mídia
                $attachment = array(
                    'post_mime_type' => $upload['type'],
                    'post_title'     => sanitize_file_name($file['name']),
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                );

                $attachment_id = wp_insert_attachment($attachment, $upload['file']);
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));

                echo '<p class="alert alert-success">Adesivo carregado e registrado na biblioteca de mídia!</p>';
            }
        } else {
            echo '<p class="alert alert-danger">Por favor, envie um arquivo SVG válido.</p>';
        }
    } else {
        echo '<p class="alert alert-danger">Nenhum arquivo foi enviado.</p>';
    }
}


// Enfileirar scripts e estilos
function person_plugin_enqueue_scripts($hook_suffix)
{
    if ($hook_suffix !== 'toplevel_page_plugin-adesivos') {
        return;
    }

    wp_enqueue_style(
        'person-plugin-css',
        plugin_dir_url(__FILE__) . 'assets/css/customizador.css'
    );

    wp_enqueue_script(
        'person-plugin-upload-script',
        plugin_dir_url(__FILE__) . 'assets/js/upload-script.js',
        array('jquery'),
        null,
        true
    );

    wp_enqueue_media();
}
add_action('admin_enqueue_scripts', 'person_plugin_enqueue_scripts');

// Shortcode para exibir o customizador
function person_plugin_display_customizer()
{
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/editor-template.php';
    return ob_get_clean();
}
add_shortcode('customizador_adesivo', 'person_plugin_display_customizer');


// Função para carregar adesivo com base no produto atual
function person_plugin_load_sticker() {
    if (!function_exists('is_product') || !is_product()) {
        return '<p>Este não é um produto.</p>';
    }

    // Tenta carregar o produto global
    global $product;

    // Se o objeto $product não estiver disponível, tenta carregar pelo ID
    if (!$product || !is_a($product, 'WC_Product')) {
        $product_id = get_the_ID();
        $product = wc_get_product($product_id);
    }

    // Se ainda não encontrar o produto, retorna erro
    if (!$product || !is_a($product, 'WC_Product')) {
        return '<p>Produto não encontrado.</p>';
    }

    // Obtém o nome do produto e cria o nome do adesivo correspondente
    $product_name = $product->get_name();
    $sticker_filename = sanitize_title($product_name) . '.svg';

    // Consulta na biblioteca de mídia
    $args = array(
        'post_type'      => 'attachment',
        'post_mime_type' => 'image/svg+xml',
        'post_status'    => 'inherit',
        'meta_query'     => array(
            array(
                'key'     => '_wp_attachment_metadata',
                'value'   => $sticker_filename,
                'compare' => 'LIKE'
            )
        ),
    );

    $attachments = get_posts($args);

    // Retorna o adesivo encontrado ou mensagem de erro
    if (!empty($attachments)) {
        $sticker_url = wp_get_attachment_url($attachments[0]->ID);

        return '<div class="custom-sticker">' .
            '<img src="' . esc_url($sticker_url) . '" alt="Adesivo do Produto" />' .
            '</div>';
    } else {
        return '<p>Adesivo não encontrado para este produto.</p>';
    }
}
add_shortcode('customizador_adesivo', 'person_plugin_load_sticker');
