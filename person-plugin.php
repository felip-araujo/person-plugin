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
    if (isset($_POST['submit_sticker'])) {
        plugin_processar_upload();
    }

    // Listagem de adesivos
    include plugin_dir_path(__FILE__) . 'templates/admin-form.php';

    echo '</div>';
}

// Processar o upload de adesivos
function plugin_processar_upload()
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
            $upload_dir = plugin_dir_path(__FILE__) . 'assets/stickers/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_path = $upload_dir . basename($file['name']);
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                echo '<p class="alert alert-success">Adesivo carregado com sucesso!</p>';
            } else {
                echo '<p class="alert alert-danger">Erro ao salvar o arquivo no servidor.</p>';
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
    $sticker_path = plugin_dir_path(__FILE__) . 'assets/stickers/';
    if (!file_exists($sticker_path)) {
        return '<p>Adesivo não encontrado para este produto.</p>';
    }

    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/editor-template.php';
    return ob_get_clean();
}
add_shortcode('customizador_adesivo', 'person_plugin_display_customizer');

// AJAX para salvar adesivo
function salvar_adesivo()
{
    if (!isset($_POST['svg'])) {
        wp_send_json_error('Dados SVG estão faltando');
    }

    $svg_data = wp_unslash($_POST['svg']);
    $svg_data = preg_replace('/<script.*?<\/script>/s', '', $svg_data);

    $filename = 'adesivo_' . time() . '.svg';
    $upload_dir = plugin_dir_path(__FILE__) . 'assets/stickers-prontos/';

    if (!file_exists($upload_dir)) {
        wp_mkdir_p($upload_dir);
    }

    $file_path = $upload_dir . $filename;
    if (file_put_contents($file_path, $svg_data)) {
        wp_send_json_success('Arquivo SVG salvo com sucesso!');
    } else {
        wp_send_json_error('Falha ao salvar o arquivo SVG');
    }
}
add_action('wp_ajax_salvar_adesivo', 'salvar_adesivo');
add_action('wp_ajax_nopriv_salvar_adesivo', 'salvar_adesivo');
