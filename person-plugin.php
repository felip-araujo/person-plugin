<?php
/*
Plugin Name: Person Plugin - Customizador de Adesivos
Description: Plugin para customização de adesivos com controle de cores, fontes, etc.
Version: 1.0
Author: Evolution Design
*/

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/templates/email-handlers.php';

// -----------------------------
// 1. Uploads e Configurações Gerais
// -----------------------------

// Permitir upload de SVG
function permitir_svg_upload($mimes)
{
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}
add_filter('upload_mimes', 'permitir_svg_upload');

// Customizar rodapé do admin
function customizar_rodape_plugin($footer_text)
{
    $tela_atual = get_current_screen();
    if ($tela_atual->id === 'toplevel_page_plugin-adesivos') {
        return '';
    }
    return $footer_text;
}
add_filter('admin_footer_text', 'customizar_rodape_plugin');

// Carregar Bootstrap no admin
function carregar_bootstrap_no_admin($hook_suffix)
{
    if ($hook_suffix === 'toplevel_page_plugin-adesivos') {
        wp_enqueue_style('bootstrap-css', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
        wp_enqueue_script('bootstrap-js', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js', array('jquery'), null, true);
    }
}
add_action('admin_enqueue_scripts', 'carregar_bootstrap_no_admin');

// -----------------------------
// 2. Scripts e Estilos do Frontend
// -----------------------------

function person_plugin_enqueue_frontend_scripts()
{
    if (is_page('custom-sticker')) {
        wp_enqueue_style('bootstrap-css', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
        wp_enqueue_style('person-plugin-customizer-css', plugin_dir_url(__FILE__) . 'assets/css/customizador.css');
        wp_enqueue_script('bootstrap-js', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js', array('jquery'), time(), true);
        wp_enqueue_script('konva-js', 'https://cdn.jsdelivr.net/npm/konva@8.4.2/konva.min.js', array(), time(), true);
        wp_enqueue_script('person-plugin-customizer-js', plugin_dir_url(__FILE__) . 'assets/js/customizador.js', array('jquery', 'konva-js'), time(), true);
        wp_enqueue_media();
    }
}
add_action('wp_enqueue_scripts', 'person_plugin_enqueue_frontend_scripts', 20);

function person_plugin_enqueue_scripts()
{
    wp_enqueue_script('person-plugin-js', plugins_url('assets/js/customizador.js', __FILE__), array('jquery'), null, true);
    wp_localize_script('person-plugin-js', 'personPlugin', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);
}
add_action('wp_enqueue_scripts', 'person_plugin_enqueue_scripts');

// Carregar Font Awesome no admin
function meu_plugin_carregar_fontawesome_kit()
{
    if (is_admin()) {
        wp_enqueue_script('font-awesome-kit', 'https://kit.fontawesome.com/d4755c66d3.js', array(), null, true);
    }
}
add_action('admin_enqueue_scripts', 'meu_plugin_carregar_fontawesome_kit');

// -----------------------------
// 3. Menu e Templates do Admin
// -----------------------------

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
    echo '
<div class="alert alert-warning" style="display: inline-flex; align-items: center; font-size: 1.2rem; margin-top: 1rem; padding: 10px;">
    <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i>
    <p style="margin: 0;">
       Crie uma página com a tag <strong> [customizador_adesivo_page] </strong> para exibir o editor de adesivos, copie a tag abaixo.
    </p>
</div>';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_sticker'])) {
        plugin_processar_upload($plugin_sticker_dir);
    }
    $file = plugin_dir_path(__FILE__) . 'templates/admin-form.php';
    if (file_exists($file)) {
        include $file;
    } else {
        echo '<p class="alert alert-danger">Erro: Formulário de configuração não encontrado.</p>';
    }
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

function person_plugin_display_customizer($sticker_url = '')
{
    wp_enqueue_script(
        'person-plugin-customizer-js',
        plugin_dir_url(__FILE__) . 'assets/js/customizador.js',
        array('jquery', 'konva-js'),
        null,
        true
    );
    wp_localize_script(
        'person-plugin-customizer-js',
        'pluginData',
        array(
            'stickerUrl' => $sticker_url,
            'ajaxUrl'    => admin_url('admin-ajax.php'),
        )
    );
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/editor-template.php';
    return ob_get_clean();
}

function person_plugin_customizer_page()
{
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/customizador-page.php';
    return ob_get_clean();
}
add_shortcode('customizador_adesivo_page', 'person_plugin_customizer_page');

function carregar_font_awesome()
{
    wp_enqueue_style(
        'font-awesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
        array(),
        '5.15.4'
    );
}
add_action('admin_enqueue_scripts', 'carregar_font_awesome');
add_action('wp_enqueue_scripts', 'carregar_font_awesome');

register_activation_hook(__FILE__, 'criar_tabela_adesivos');
function criar_tabela_adesivos()
{
    global $wpdb;
    $tabela = $wpdb->prefix . 'adesivos';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $tabela (
        id INT(11) NOT NULL AUTO_INCREMENT,
        nome_cliente VARCHAR(255) NOT NULL,
        email_cliente VARCHAR(255) NOT NULL,
        telefone_cliente VARCHAR(20),
        material VARCHAR(100),
        quantidade INT(11),
        texto_instrucoes TEXT,
        data_pedido DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// -----------------------------
// 4. Salvamento da Imagem e Criação do Produto Temporário
// -----------------------------

// FUNÇÃO: Salvar imagem personalizada (recebe Base64) e retorna a URL
function salvar_imagem_personalizada($base64_image)
{
    $upload_dir = wp_upload_dir();
    $filename = 'adesivo-' . time() . '.png';
    $upload_path = $upload_dir['path'] . '/' . $filename;
    // Remove o prefixo "data:image/png;base64," se existir
    $base64_image = preg_replace('#^data:image/\w+;base64,#i', '', $base64_image);
    $data = base64_decode($base64_image);

    error_log(print_r($upload_dir, true));


    if (!$data) {
        error_log('❌ Erro ao decodificar a imagem.');
        return false;
    }
    if (file_put_contents($upload_path, $data) === false) {
        error_log('❌ Erro ao salvar a imagem.');
        return false;
    }
    error_log('✅ Imagem salva com sucesso: ' . $upload_dir['url'] . '/' . $filename);
    return $upload_dir['url'] . '/' . $filename;
}

// AJAX handler para salvar a imagem, criar o produto temporário e adicioná-lo ao carrinho
// AJAX handler para salvar a imagem, criar o produto temporário e adicioná-lo ao carrinho
add_action('wp_ajax_salvar_adesivo_servidor', 'salvar_adesivo_servidor');
add_action('wp_ajax_nopriv_salvar_adesivo_servidor', 'salvar_adesivo_servidor');

function salvar_adesivo_servidor() {
    // Verifica se os dados necessários foram enviados
    if (!isset($_POST['adesivo_base64']) || !isset($_POST['price'])) {
        wp_send_json_error(array('message' => 'Dados incompletos.'));
        wp_die();
    }

    $price = floatval($_POST['price']);
    error_log("📌 Preço recebido no PHP: " . $price);

    // Salva a imagem usando a função já definida e obtém a URL
    $image_url = salvar_imagem_personalizada($_POST['adesivo_base64']);
    if (!$image_url) {
        wp_send_json_error(array('message' => 'Erro ao salvar a imagem.'));
        wp_die();
    }

    // --- NOVA PARTE: Inserir o anexo para que a imagem fique registrada no WP ---
    $upload_dir = wp_upload_dir();
    $filename   = basename($image_url); // Nome do arquivo salvo
    $file_path  = $upload_dir['path'] . '/' . $filename; // Caminho completo do arquivo

    $attachment = array(
        'post_mime_type' => 'image/png',
        'post_title'     => sanitize_file_name($filename),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );
    $attachment_id = wp_insert_attachment($attachment, $file_path, 0);
    if (!is_wp_error($attachment_id)) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attach_data);
        // Obter a URL definitiva a partir do attachment
        $image_url = wp_get_attachment_url($attachment_id);
    }
    // --- FIM DA NOVA PARTE ---

    // Cria um título único para o produto temporário
    $post_title = 'Adesivo Personalizado - ' . current_time('Y-m-d H:i:s');
    $temp_product = array(
        'post_title'  => $post_title,
        'post_status' => 'publish',
        'post_type'   => 'product',
        'post_author' => get_current_user_id(),
    );
    $product_id = wp_insert_post($temp_product);
    if (!$product_id) {
        wp_send_json_error(array('message' => 'Erro ao criar o produto temporário.'));
        wp_die();
    }

    // Define o tipo de produto (simples) e configura os metadados de preço
    wp_set_object_terms($product_id, 'simple', 'product_type');
    update_post_meta($product_id, '_regular_price', $price);
    update_post_meta($product_id, '_price', $price);
    update_post_meta($product_id, '_virtual', 'yes');

    // Salva a URL do adesivo (imagem) para uso posterior – usando a meta key padronizada "_adesivo_url"
    update_post_meta($product_id, '_adesivo_url', $image_url);

    // Define a imagem destacada do produto com o attachment criado
    set_post_thumbnail($product_id, $attachment_id);

    // Dados adicionais para o item do carrinho
    $cart_item_data = array(
        'temp_product' => true,
        'adesivo_url'  => $image_url,
        'custom_price' => $price,
        'unique_key'   => md5(microtime() . rand())
    );

    // Adiciona o produto criado ao carrinho do WooCommerce
    $cart_item_key = WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);

    if ($cart_item_key) {
        wp_send_json_success(array(
            'message'  => 'Produto temporário criado e adicionado ao carrinho!',
            'cart_url' => wc_get_cart_url()
        ));
    } else {
        wp_send_json_error(array('message' => 'Erro ao adicionar o produto ao carrinho.'));
    }

    wp_die();
}


// -----------------------------
// 5. Exibição do Adesivo no Carrinho, Checkout e E-mails
// -----------------------------

// Recuperar e restaurar a URL do adesivo no carrinho
function restore_custom_cart_item_data($cart_item, $cart_item_key)
{
    // Se a URL já estiver definida na adição ao carrinho, a usamos e garantimos que ela esteja nos metadados do produto
    if (isset($cart_item['adesivo_url']) && !empty($cart_item['adesivo_url'])) {
        $cart_item['data']->add_meta_data('adesivo_url', $cart_item['adesivo_url'], true);
    } else {
        // Caso contrário, tenta recuperá-la a partir do meta do produto (salvo com a key '_adesivo_url')
        $product_id = $cart_item['data']->get_id();
        $meta = get_post_meta($product_id, '_adesivo_url', true);
        if (!empty($meta)) {
            $cart_item['adesivo_url'] = $meta;
            $cart_item['data']->add_meta_data('adesivo_url', $meta, true);
        }
    }
    return $cart_item;
}
add_filter('woocommerce_get_cart_item_from_session', 'restore_custom_cart_item_data', 20, 2);

// Exibir a imagem na lista de itens do carrinho
function exibir_imagem_personalizada_no_carrinho($item_data, $cart_item)
{
    if (!empty($cart_item['adesivo_url'])) {
        $item_data[] = array(
            'key'     => __('Imagem Personalizada', 'woocommerce'),
            'value'   => '<img src="' . esc_url($cart_item['adesivo_url']) . '" style="max-width:100px; height:auto;">',
            'display' => '<img src="' . esc_url($cart_item['adesivo_url']) . '" style="max-width:100px; height:auto;">'
        );
    }
    return $item_data;
}
add_filter('woocommerce_get_item_data', 'exibir_imagem_personalizada_no_carrinho', 10, 2);

// Substituir a thumbnail do produto no carrinho pela imagem do adesivo
function substituir_imagem_no_carrinho($product_image, $cart_item, $cart_item_key)
{
    if (!empty($cart_item['adesivo_url']) && filter_var($cart_item['adesivo_url'], FILTER_VALIDATE_URL)) {
        return '<img src="' . esc_url($cart_item['adesivo_url']) . '" style="width: 80px; height:auto;">';
    }
    return $product_image;
}
add_filter('woocommerce_cart_item_thumbnail', 'substituir_imagem_no_carrinho', 10, 3);

// Salvar a URL do adesivo no pedido utilizando a chave "_adesivo_url"
function salvar_imagem_personalizada_no_pedido($item, $cart_item_key, $values, $order)
{
    if (isset($values['adesivo_url']) && !empty($values['adesivo_url'])) {
        // Salva com a meta key padronizada "_adesivo_url"
        $item->add_meta_data('_adesivo_url', esc_url($values['adesivo_url']), true);
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'salvar_imagem_personalizada_no_pedido', 10, 4);

// Adicionar o link do adesivo nos e-mails do pedido (usando a meta "_adesivo_url")
function adicionar_link_adesivo_email($order, $sent_to_admin, $plain_text, $email)
{
    $output = '';
    foreach ($order->get_items() as $item_id => $item) {
        // Recupera a URL usando a meta padronizada
        $adesivo_url = $item->get_meta('_adesivo_url');
        if ($adesivo_url) {
            if ($plain_text) {
                $output .= "\n" . __('Download do Adesivo (alta qualidade):', 'woocommerce') . ' ' . esc_url($adesivo_url) . "\n";
            } else {
                $output .= '<p>' . __('Download do Adesivo (alta qualidade):', 'woocommerce') . ' <a href="' . esc_url($adesivo_url) . '" target="_blank">' . __('Clique aqui para baixar', 'woocommerce') . '</a></p>';
            }
        }
    }
    if (!empty($output)) {
        if ($plain_text) {
            echo "\n" . __('Adesivo Personalizado', 'woocommerce') . "\n";
        } else {
            echo '<h2>' . __('Adesivo Personalizado', 'woocommerce') . '</h2>';
        }
        echo $output;
    }
}
add_action('woocommerce_email_after_order_table', 'adicionar_link_adesivo_email', 10, 4);

// Remova qualquer duplicata do hook "woocommerce_checkout_create_order_line_item" que adicione o meta "_adesivo_url" (se houver) para evitar conflitos.

// -----------------------------
// 6. Limpeza Agendada dos Produtos Temporários
// -----------------------------

function limpar_produtos_personalizados_antigos()
{
    global $wpdb;
    // Define o tempo limite (24 horas atrás)
    $tempo_limite = strtotime('-24 hours');
    // Busca produtos personalizados criados antes desse tempo
    $query = $wpdb->prepare("
        SELECT ID FROM {$wpdb->posts} 
        WHERE post_type = 'product' 
        AND post_title LIKE 'Adesivo Personalizado - %%'
        AND post_date < %s
    ", date('Y-m-d H:i:s', $tempo_limite));
    $produtos_para_excluir = $wpdb->get_col($query);
    if (!empty($produtos_para_excluir)) {
        foreach ($produtos_para_excluir as $product_id) {
            wp_delete_post($product_id, true);
        }
    }
}

function agendar_limpeza_produtos_personalizados()
{
    if (!wp_next_scheduled('evento_limpar_produtos_personalizados')) {
        wp_schedule_event(time(), 'daily', 'evento_limpar_produtos_personalizados');
    }
}
add_action('wp', 'agendar_limpeza_produtos_personalizados');
add_action('evento_limpar_produtos_personalizados', 'limpar_produtos_personalizados_antigos');

function desativar_limpeza_produtos_personalizados()
{
    $timestamp = wp_next_scheduled('evento_limpar_produtos_personalizados');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'evento_limpar_produtos_personalizados');
    }
}
register_deactivation_hook(__FILE__, 'desativar_limpeza_produtos_personalizados');

// // Substituir a thumbnail do produto no carrinho pelo adesivo personalizado
// add_filter('woocommerce_cart_item_thumbnail', function ($product_image, $cart_item, $cart_item_key) {
//     if (isset($cart_item['adesivo_url'])) {
//         return '<img src="' . esc_url($cart_item['adesivo_url']) . '" alt="Adesivo Personalizado" style="max-width: 50px; height: auto;">';
//     }
//     return $product_image;
// }, 10, 3);

// // Substituir a imagem do produto no e-mail do WooCommerce
// add_filter('woocommerce_order_item_thumbnail', function ($product_image, $item) {
//     $adesivo_url = wc_get_order_item_meta($item->get_id(), '_adesivo_url', true);
//     if (!empty($adesivo_url)) {
//         return '<img src="' . esc_url($adesivo_url) . '" alt="Adesivo Personalizado" style="max-width: 50px; height: auto;">';
//     }
//     return $product_image;
// }, 10, 2);

// // Salvar a URL da imagem no pedido WooCommerce
// add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
//     if (isset($values['adesivo_url'])) {
//         $item->add_meta_data('_adesivo_url', $values['adesivo_url'], true);
//     }
// }, 10, 4);
