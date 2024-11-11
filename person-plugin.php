<?php
/*
Plugin Name: Person Plugin - Customizador de Adesivos
Description: Plugin para customização de adesivos com controle de cores, fontes, etc.
Version: 1.0
Author: Evolution Design
*/

// Função para permitir upload de SVG de forma segura
function permitir_svg_upload($mimes) {
    // Permite SVG no WordPress
    $mimes['svg'] = 'image/svg+xml'; 
    return $mimes;
}
add_filter('upload_mimes', 'permitir_svg_upload');



function plugin_adicionar_menu() {
    add_menu_page(
        'Configurações de Adesivos',           // Título da página
        'Seus Adesivos',                            // Nome do menu
        'manage_options',                      // Capacidade necessária
        'plugin-adesivos',                     // Slug
        'plugin_pagina_de_configuracao',       // Função de callback
        'dashicons-format-image',              // Ícone do menu
        6                                      // Posição no menu
    );
}
add_action('admin_menu', 'plugin_adicionar_menu');


function plugin_pagina_de_configuracao() {
    ?>
    <div class="wrap">
        <h1>Configurações de Adesivos</h1> 
        <p> Adicione a tag " [customizador_adesivo] " na página onde você deseja que o Personalizador apareça. </p>
        <form method="post" enctype="multipart/form-data">
            <?php
            wp_nonce_field('upload_sticker_nonce', 'sticker_nonce'); // Segurança do nonce
            ?>
            <label for="sticker">Selecione um adesivo para upload:</label>
            <input type="file" name="sticker" id="sticker" accept="image/*" />
            <input type="submit" name="submit_sticker" value="Upload Adesivo" class="button button-primary" />
        </form>

        <?php
        // Processar o upload após o envio do formulário
        if (isset($_POST['submit_sticker'])) {
            plugin_processar_upload();
        }
        ?>
    </div>
    <?php
}

function plugin_processar_upload() {
    // Verifica o nonce de segurança
    if (!isset($_POST['sticker_nonce']) || !wp_verify_nonce($_POST['sticker_nonce'], 'upload_sticker_nonce')) {
        echo 'Nonce inválido!';
        return;
    }

    // Verifica se o arquivo foi enviado
    if (isset($_FILES['sticker']) && !empty($_FILES['sticker']['name'])) {
        $file = $_FILES['sticker'];
        
        // Verifica os erros de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo 'Erro ao fazer upload do arquivo. Código de erro: ' . $file['error'];
            return;
        }

        // Verifica o tipo de arquivo (incluindo SVG)
        $file_type = wp_check_filetype($file['name']);
        // Agora aceitamos JPG, PNG, GIF, e SVG
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'svg');

        if (in_array($file_type['ext'], $allowed_types)) {
            // Faz o upload usando a função do WordPress
            $upload = wp_upload_bits($file['name'], null, file_get_contents($file['tmp_name']));
            
            if ($upload['error']) {
                echo 'Erro ao enviar o arquivo: ' . $upload['error'];
            } else {
                // Mover o arquivo para a pasta de adesivos do plugin
                $plugin_sticker_dir = plugin_dir_path(__FILE__) . 'assets/stickers/';
                if (!file_exists($plugin_sticker_dir)) {
                    mkdir($plugin_sticker_dir, 0755, true);
                }

                // Move o arquivo para a pasta do plugin
                $new_file_path = $plugin_sticker_dir . basename($upload['file']);
                rename($upload['file'], $new_file_path);

                echo 'Adesivo carregado com sucesso!';
            }
        } else {
            echo 'Por favor, envie um arquivo de imagem válido (JPG, PNG, GIF, SVG).';
        }
    } else {
        echo 'Nenhum arquivo foi enviado.';
    }
}



function meu_plugin_enqueue_scripts() {
    // Carregar o Fabric.js da CDN
    wp_enqueue_script(
        'fabric-js', // Handle do script
        'https://cdn.jsdelivr.net/npm/fabric@4.6.0/dist/fabric.min.js', // Novo URL do CDN
        array(), // Dependências
        null, // Versão
        true // Colocar o script no final do body (melhor performance)
    );
}

// Hook para adicionar o script na página
add_action('wp_enqueue_scripts', 'meu_plugin_enqueue_scripts');

// Enfileirar CSS e JS
function person_plugin_enqueue_scripts()
{
    // CSS Principal do Plugin
    wp_enqueue_style('person-plugin-css', plugin_dir_url(__FILE__) . 'assets/css/customizador.css');

    // Google Fonts
    wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&display=swap', false);

    // Bootstrap CSS
    wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css', false, null);

    // Fabric.js
    wp_enqueue_script('fabric-js', plugin_dir_url(__FILE__) . 'assets/vendor/fabric.min.js', array(), null, true);

    // JavaScript do Plugin com Dependência do jQuery e Fabric.js
    wp_enqueue_script('person-plugin-js', plugin_dir_url(__FILE__) . 'assets/js/customizador.js', array('jquery', 'fabric-js'), null, true);

    // Bootstrap JS
    wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'person_plugin_enqueue_scripts');

// Shortcode para exibir o customizador
function person_plugin_display_customizer()
{
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/editor-template.php';
    return ob_get_clean();
}
add_shortcode('customizador_adesivo', 'person_plugin_display_customizer');


