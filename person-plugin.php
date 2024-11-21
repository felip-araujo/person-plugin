<?php
// /var/www/html/wp-content/plugins/person-plugin/person-plugin.php

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
function carregar_bootstrap_no_admin()
{
    wp_enqueue_style('bootstrap-css', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
    wp_enqueue_script('bootstrap-js', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js', array('jquery'), null, true);
}
add_action('admin_enqueue_scripts', 'carregar_bootstrap_no_admin');

// Adicionar menu ao admin
function plugin_adicionar_menu()
{
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

function plugin_pagina_de_configuracao()
{
    $plugin_sticker_dir = plugin_dir_path(__FILE__) . 'assets/stickers/';
    $url_diretorio = plugin_dir_url(__FILE__) . 'assets/stickers/';
?>
    <div class="wrap">
        <h1 class="display-2">Configurações de Adesivos</h1>
        <p>Adicione a tag "[customizador_adesivo]" na página onde você deseja que o Personalizador apareça.</p>

        <!-- Formulário de Upload -->
        <form method="post" enctype="multipart/form-data" class="mb-4">
            <?php wp_nonce_field('upload_sticker_nonce', 'sticker_nonce'); ?>
            <div class="form-group">
                <label for="sticker">Selecione um adesivo para upload:</label>
                <input type="file" name="sticker" id="sticker" accept="image/svg+xml" class="form-control-file" />
            </div>
            <input type="submit" name="submit_sticker" value="Upload Adesivo" class="btn btn-primary" />
        </form>

        <?php
        // Processar o upload após o envio do formulário
        if (isset($_POST['submit_sticker'])) {
            plugin_processar_upload();
        }

        // Listagem de adesivos
        echo '<h2>Adesivos Existentes</h2>';
        if (is_dir($plugin_sticker_dir)) {
            $arquivos_svg = glob($plugin_sticker_dir . '*.svg');

            if (!empty($arquivos_svg)) {
                echo '<table style="border-radius: .7rem" class="table table-dark">';
                echo '<thead><tr><th>Visualização</th><th>Nome do Adesivo</th><th>Associar</th><th>Gerenciar</th></tr></thead>';
                echo '<tbody>';
                foreach ($arquivos_svg as $arquivo) {
                    $url_svg = $url_diretorio . basename($arquivo);
                    $nome_arquivo = basename($arquivo);
                    echo '<tr>';
                    echo '<td style="width: 100px;"><img src="' . esc_url($url_svg) . '" alt="' . esc_attr($nome_arquivo) . '" style="width: 80px; height: auto;"></td>';
                    echo '<td>' . esc_html($nome_arquivo) . '</td>';
                    echo '<td> <select name="" id=""></select> </td>';
                    echo '<td> <button class="btn btn-danger"> Apagar </button> </td>';
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
            } else {
                echo '<p>Nenhum adesivo encontrado.</p>';
            }
        } else {
            echo '<p>Pasta de adesivos não encontrada.</p>';
        }
        ?>
    </div>
<?php
}

function plugin_processar_upload()
{
    if (!isset($_POST['sticker_nonce']) || !wp_verify_nonce($_POST['sticker_nonce'], 'upload_sticker_nonce')) {
        echo 'Nonce inválido!';
        return;
    }

    if (isset($_FILES['sticker']) && !empty($_FILES['sticker']['name'])) {
        $file = $_FILES['sticker'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo 'Erro ao fazer upload do arquivo. Código de erro: ' . $file['error'];
            return;
        }

        $file_type = wp_check_filetype($file['name']);
        $allowed_types = array('svg'); // Apenas permite SVG

        if (in_array($file_type['ext'], $allowed_types)) {
            $upload = wp_upload_bits($file['name'], null, file_get_contents($file['tmp_name']));

            if ($upload['error']) {
                echo 'Erro ao enviar o arquivo: ' . $upload['error'];
            } else {
                $plugin_sticker_dir = plugin_dir_path(__FILE__) . 'assets/stickers/';
                if (!file_exists($plugin_sticker_dir)) {
                    mkdir($plugin_sticker_dir, 0755, true);
                }

                $new_file_path = $plugin_sticker_dir . basename($upload['file']);
                rename($upload['file'], $new_file_path);

                echo 'Adesivo carregado com sucesso!';
            }
        } else {
            echo 'Por favor, envie um arquivo SVG válido.';
        }
    } else {
        echo 'Nenhum arquivo foi enviado.';
    }
}

// Enfileirar CSS e JS
function person_plugin_enqueue_scripts()
{
    // CSS Principal do Plugin
    wp_enqueue_style('person-plugin-css', plugin_dir_url(__FILE__) . 'assets/css/customizador.css');

    // Google Fonts
    wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&display=swap', false);

    // Bootstrap CSS
    wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css', false, null);

    // Registrar e enfileirar o Konva.js
    wp_register_script('konva-js', 'https://cdn.jsdelivr.net/npm/konva@8.4.2/konva.min.js', array(), null, false);
    wp_enqueue_script('konva-js');

    // Registrar e enfileirar o script principal do plugin, garantindo que ele dependa do 'konva-js'
    wp_register_script('person-plugin-js', plugin_dir_url(__FILE__) . 'assets/js/customizador.js', array('jquery', 'konva-js'), null, true);
    wp_enqueue_script('person-plugin-js');

    // Bootstrap JS
    wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js', array('jquery'), null, true);

    // Passa a URL do adesivo para o script
    if (is_product()) {
        global $product;

        if (!$product) {
            $product = wc_get_product(get_the_ID());
        }

        if ($product) {
            // Obtém o nome do produto
            $product_name = $product->get_name();

            // Prepara o nome do arquivo do adesivo, assumindo que os adesivos são nomeados de acordo com o slug do produto
            $sticker_filename = sanitize_title($product_name) . '.svg';

            $sticker_url = plugin_dir_url(__FILE__) . 'assets/stickers/' . $sticker_filename;

            // Verifica se o arquivo do adesivo existe
            $sticker_path = plugin_dir_path(__FILE__) . 'assets/stickers/' . $sticker_filename;

            if (file_exists($sticker_path)) {
                wp_localize_script('person-plugin-js', 'pluginData', array(
                    'stickerUrl' => $sticker_url,
                ));
            }
        }
    }
}
add_action('wp_enqueue_scripts', 'person_plugin_enqueue_scripts');

// Shortcode para exibir o customizador
function person_plugin_display_customizer()
{
    if (!is_product()) {
        return ''; // Retorna vazio se não for uma página de produto
    }

    global $product;

    if (!$product) {
        $product = wc_get_product(get_the_ID());
    }

    if (!$product) {
        return ''; // Retorna vazio se o produto não for encontrado
    }

    // Verifica se o arquivo do adesivo existe
    $product_name = $product->get_name();
    $sticker_filename = sanitize_title($product_name) . '.svg';
    $sticker_path = plugin_dir_path(__FILE__) . 'assets/stickers/' . $sticker_filename;

    if (!file_exists($sticker_path)) {
        return '<p>Adesivo não encontrado para este produto.</p>';
    }

    // Enfileira os scripts necessários (isso já é feito no person_plugin_enqueue_scripts)

    // Renderiza o template
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/editor-template.php';
    return ob_get_clean();
}
add_shortcode('customizador_adesivo', 'person_plugin_display_customizer');
