<?php
/*
Plugin Name: Person Plugin - Customizador de Adesivos
Description: Plugin para customização de adesivos com controle de cores, fontes, etc.
Version: 1.0
Author: Evolution Design
*/

// Permitir upload de SVG
function permitir_svg_upload($mimes) {
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}
add_filter('upload_mimes', 'permitir_svg_upload');

// Carregar estilos e scripts no admin
function carregar_bootstrap_no_admin($hook_suffix) {
    if ($hook_suffix === 'toplevel_page_plugin-adesivos') {
        wp_enqueue_style('bootstrap-css', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
        wp_enqueue_script('bootstrap-js', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js', array('jquery'), null, true);
    }
}
add_action('admin_enqueue_scripts', 'carregar_bootstrap_no_admin'); 

// Adicionar menu ao admin
function plugin_adicionar_menu() {
    add_menu_page(
        'Configurações de Adesivos',
        'Seus Adesivos',
        'manage_options',
        'plugin-adesivos',
        'plugin_pagina_de_configuracao',
        'dashicons-format-image',
        6
    );
}
add_action('admin_menu', 'plugin_adicionar_menu');

// Renderizar a página do plugin no admin
function plugin_pagina_de_configuracao() {
    // $plugin_sticker_dir = plugin_dir_path(__FILE__) . 'assets/stickers/';
    echo '<div class="container">';
    echo '<h1></h1>';
    echo '<p style="font-size: 1.2rem; margin-top: 2rem;" class="alert alert-primary">Adicione a tag <strong>[customizador_adesivo]</strong> na página onde você deseja exibir o editor de adesivos</p>';
 
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_sticker'])) {
        plugin_processar_upload($plugin_sticker_dir);
    }

    include plugin_dir_path(__FILE__) . 'templates/admin-form.php';
    echo '</div>';
}

// Processar upload de adesivos
function plugin_processar_upload($plugin_sticker_dir) {
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
        $allowed_types = array('svg');

        if (in_array($file_type['ext'], $allowed_types)) {
            $upload = wp_handle_upload($file, array('test_form' => false));

            if (isset($upload['error']) && $upload['error']) {
                echo '<p class="alert alert-danger">Erro ao enviar o arquivo: ' . $upload['error'] . '</p>';
            } else {
                $attachment = array(
                    'post_mime_type' => $upload['type'],
                    'post_title'     => pathinfo($file['name'], PATHINFO_FILENAME),
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

// Enfileirar scripts e estilos no frontend
function person_plugin_enqueue_frontend_scripts() {
    if (!is_product()) {
        return; // Garante que os scripts sejam carregados apenas em páginas de produtos
    }

    // Estilos do Bootstrap
    wp_enqueue_style(
        'bootstrap-css',
        'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css'
    );

    // Estilos do Customizador
    wp_enqueue_style(
        'person-plugin-customizer-css',
        plugin_dir_url(__FILE__) . 'assets/css/customizador.css'
    );

    // Script do Bootstrap
    wp_enqueue_script(
        'bootstrap-js',
        'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js',
        array('jquery'),
        null,
        true
    );

    // Script do Konva.js
    wp_enqueue_script(
        'konva-js',
        'https://cdn.jsdelivr.net/npm/konva@8.4.2/konva.min.js',
        array(),
        null,
        true
    );

    // Script do Customizador
    wp_enqueue_script(
        'person-plugin-customizer-js',
        plugin_dir_url(__FILE__) . 'assets/js/customizador.js',
        array('jquery', 'konva-js'),
        null,
        true
    );

    wp_enqueue_media();
}
add_action('wp_enqueue_scripts', 'person_plugin_enqueue_frontend_scripts');

// Shortcode para exibir o customizador
function person_plugin_display_customizer() {
    if (!is_product()) {
        return ''; // Retorna vazio se não for uma página de produto
    }

    global $product;
    if (!$product) {
        $product = wc_get_product(get_the_ID());
    }

    if (!$product) {
        return '<p>Produto não encontrado.</p>';
    }

    // Verificar se o reconhecimento de adesivo pelo nome está ativado
    $recognize_sticker_setting = get_option('person_plugin_recognize_sticker', 'yes');
    $sticker_url = '';

    if ($recognize_sticker_setting === 'yes') {
        // Tentar associar automaticamente pelo nome do produto
        $product_name = $product->get_name();
        $sanitized_name = sanitize_title($product_name);

        // Busca o adesivo na biblioteca de mídia
        $args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image/svg+xml',
            'post_status'    => 'inherit',
            'meta_query'     => array(
                array(
                    'key'     => '_wp_attached_file',
                    'value'   => $sanitized_name . '.svg',
                    'compare' => 'LIKE',
                ),
            ),
        );

        $attachments = get_posts($args);

        if (!empty($attachments)) {
            $sticker_url = wp_get_attachment_url($attachments[0]->ID);
        }
    }

    // Se não encontrou ou se o reconhecimento automático está desativado, tentar a associação manual
    if (empty($sticker_url)) {
        $associated_sticker_id = get_post_meta($product->get_id(), '_associated_sticker', true);
        if ($associated_sticker_id) {
            $sticker_url = wp_get_attachment_url($associated_sticker_id);
        } else {
            // Se não houver adesivo associado, exibir mensagem ou lógica alternativa
            return '<p>Adesivo não encontrado para este produto.</p>';
        }
    }

    // Passar os dados para o JavaScript
    wp_enqueue_script('person-plugin-customizer-js', plugin_dir_url(__FILE__) . 'assets/js/customizador.js', array('jquery'), null, true);
    wp_localize_script(
        'person-plugin-customizer-js',
        'pluginData',
        array(
            'stickerUrl' => $sticker_url,
            'ajaxUrl'    => admin_url('admin-ajax.php'),
        )
    );

    // Renderizar o template do editor
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/editor-template.php';
    return ob_get_clean();
}




add_shortcode('customizador_adesivo', 'person_plugin_display_customizer');

function carregar_font_awesome() {
    wp_enqueue_style(
        'font-awesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
        array(),
        '5.15.4'
    );
}
add_action('admin_enqueue_scripts', 'carregar_font_awesome'); // Para páginas do admin
add_action('wp_enqueue_scripts', 'carregar_font_awesome'); // Para páginas do frontend

// Handler AJAX para salvar o adesivo e enviar email
function salvar_adesivo() {
    if (!isset($_POST['dataURL']) || !isset($_POST['userName']) || !isset($_POST['userEmail'])) {
        wp_send_json_error('Dados incompletos recebidos');
        wp_die();
    }

    $dataURL = $_POST['dataURL'];
    $userName = sanitize_text_field($_POST['userName']);
    $userEmail = sanitize_email($_POST['userEmail']);

    if (!is_email($userEmail)) {
        wp_send_json_error('Email inválido');
        wp_die();
    }

    // Processar o dataURL e salvar a imagem
    $upload_dir = wp_upload_dir();
    $img = str_replace('data:image/png;base64,', '', $dataURL);
    $img = str_replace(' ', '+', $img);
    $decoded = base64_decode($img);
    $filename = 'adesivo_' . time() . '.png';
    $file_path = $upload_dir['path'] . '/' . $filename;

    file_put_contents($file_path, $decoded);

    // Enviar o email
    $to = 'contato@mstechbio.com.br'; // Substitua pelo email desejado
    $subject = 'Novo adesivo salvo por ' . $userName;
    $message = 'Um novo adesivo foi salvo por ' . $userName . ' (' . $userEmail . ').';
    $headers = array('Content-Type: text/html; charset=UTF-8');

    // Definir o remetente
    $headers[] = 'From: ' . get_bloginfo('name') . ' <no-reply@' . $_SERVER['HTTP_HOST'] . '>';

    // Anexar a imagem
    $attachments = array($file_path);

    // Enviar o email e verificar se foi enviado com sucesso
    $sent = wp_mail($to, $subject, $message, $headers, $attachments);

    if ($sent) {
        wp_send_json_success('Adesivo salvo e email enviado com sucesso.');
    } else {
        wp_send_json_error('Erro ao enviar o email. Por favor, tente novamente mais tarde.');
    }

    wp_die();
}
add_action('wp_ajax_salvar_adesivo', 'salvar_adesivo');
add_action('wp_ajax_nopriv_salvar_adesivo', 'salvar_adesivo');

