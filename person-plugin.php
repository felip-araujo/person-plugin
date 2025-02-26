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

if (class_exists('\TCPDF')) {
    error_log('✅ TCPDF está instalado e disponível.');
} else {
    error_log('❌ TCPDF não foi encontrado.');
}

require __DIR__ . '/templates/email-handlers.php';

// -----------------------------
// 1. Uploads e Configurações Gerais
// -----------------------------
function permitir_svg_upload($mimes) {
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}
add_filter('upload_mimes', 'permitir_svg_upload');

function customizar_rodape_plugin($footer_text) {
    $tela_atual = get_current_screen();
    if ($tela_atual->id === 'toplevel_page_plugin-adesivos') {
        return '';
    }
    return $footer_text;
}
add_filter('admin_footer_text', 'customizar_rodape_plugin');

function carregar_bootstrap_no_admin($hook_suffix) {
    if ($hook_suffix === 'toplevel_page_plugin-adesivos') {
        wp_enqueue_style('bootstrap-css', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
        wp_enqueue_script('bootstrap-js', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js', array('jquery'), null, true);
    }
}
add_action('admin_enqueue_scripts', 'carregar_bootstrap_no_admin');

// -----------------------------
// 2. Scripts e Estilos do Frontend
// -----------------------------
function person_plugin_enqueue_frontend_scripts() {
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

function person_plugin_enqueue_scripts() {
    // Enfileira o Fabric.js (já incluído via CDN ou outro método)
    wp_enqueue_script('fabric-js', 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js', array(), null, true);

    // Enfileira o script principal customizador.js como módulo
    wp_enqueue_script(
        'person-plugin-js',
        plugins_url('assets/js/customizador.js', __FILE__),
        array('jquery', 'fabric-js'),
        null,
        true
    );
    wp_localize_script('person-plugin-js', 'personPlugin', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));
}
add_action('wp_enqueue_scripts', 'person_plugin_enqueue_scripts');

function add_module_attribute($tag, $handle, $src) {
    if ('person-plugin-js' === $handle) {
        $tag = '<script type="module" src="' . esc_url($src) . '"></script>';
    }
    return $tag;
}
add_filter('script_loader_tag', 'add_module_attribute', 10, 3);



function meu_plugin_carregar_fontawesome_kit() {
    if (is_admin()) {
        wp_enqueue_script('font-awesome-kit', 'https://kit.fontawesome.com/d4755c66d3.js', array(), null, true);
    }
}
add_action('admin_enqueue_scripts', 'meu_plugin_carregar_fontawesome_kit');

// -----------------------------
// 3. Menu e Templates do Admin
// -----------------------------
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

function plugin_pagina_de_configuracao() {
    echo '<div class="alert alert-warning" style="display: inline-flex; align-items: center; font-size: 1.2rem; margin-top: 1rem; padding: 10px;">
    <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i>
    <p style="margin: 0;">Crie uma página com a tag <strong>[customizador_adesivo_page]</strong> para exibir o editor de adesivos, copie a tag abaixo.</p>
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

// -----------------------------
// 4. Funções Auxiliares (Conversão de PNG para PDF)
// -----------------------------
function convert_png_to_pdf($png_path) {
    if (!file_exists($png_path)) {
        error_log("❌ O arquivo PNG não existe: " . $png_path);
        return false;
    }
    try {
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();
        $pdf->Image($png_path, 0, 0, 210, 0, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
        $pdf_path = preg_replace('/\.png$/i', '.pdf', $png_path);
        $pdf->Output($pdf_path, 'F');
        if (file_exists($pdf_path)) {
            error_log("✅ PDF gerado: " . $pdf_path);
            return $pdf_path;
        } else {
            error_log("❌ Falha ao gerar o PDF a partir de: " . $png_path);
            return false;
        }
    } catch (Exception $e) {
        error_log("❌ Exceção ao gerar PDF: " . $e->getMessage());
        return false;
    }
}

// -----------------------------
// 5. Templates de Exibição e Shortcodes
// -----------------------------
function person_plugin_display_customizer($sticker_url = '') {
    $url_do_adesivo = $sticker_url;
    wp_enqueue_script('person-plugin-customizer-js', plugin_dir_url(__FILE__) . 'assets/js/customizador.js', array('jquery', 'konva-js'), null, true);
    wp_localize_script('person-plugin-customizer-js', 'pluginData', array(
        'stickerUrl' => $sticker_url,
        'ajaxUrl'    => admin_url('admin-ajax.php'),
    ));
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/editor-template.php';
    return ob_get_clean();
}

function person_plugin_customizer_page() {
    $url_do_adesivo = '';
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/customizador-page.php';
    return ob_get_clean();
}
add_shortcode('customizador_adesivo_page', 'person_plugin_customizer_page');

function carregar_font_awesome() {
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4');
}
add_action('admin_enqueue_scripts', 'carregar_font_awesome');
add_action('wp_enqueue_scripts', 'carregar_font_awesome');

register_activation_hook(__FILE__, 'criar_tabela_adesivos');
function criar_tabela_adesivos() {
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
// 6. Salvamento da Imagem e Criação do Produto Temporário
// -----------------------------
function salvar_imagem_personalizada($base64_image) {
    $upload_dir = wp_upload_dir();
    $filename = 'adesivo-' . time() . '.png';
    $upload_path = $upload_dir['path'] . '/' . $filename;
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

add_action('wp_ajax_salvar_adesivo_servidor', 'salvar_adesivo_servidor');
add_action('wp_ajax_nopriv_salvar_adesivo_servidor', 'salvar_adesivo_servidor');

function salvar_adesivo_servidor() {
    if (!isset($_POST['adesivo_base64']) || !isset($_POST['price'])) {
        wp_send_json_error(array('message' => 'Dados incompletos.'));
        wp_die();
    }
    $price = floatval($_POST['price']);
    error_log("📌 Preço recebido no PHP: " . $price);
    $image_url = salvar_imagem_personalizada($_POST['adesivo_base64']);
    if (!$image_url) {
        wp_send_json_error(array('message' => 'Erro ao salvar a imagem.'));
        wp_die();
    }
    $upload_dir = wp_upload_dir();
    $filename   = basename($image_url);
    $file_path  = $upload_dir['path'] . '/' . $filename;
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
        $image_url = wp_get_attachment_url($attachment_id);
    }
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
    wp_set_object_terms($product_id, 'simple', 'product_type');
    update_post_meta($product_id, '_regular_price', $price);
    update_post_meta($product_id, '_price', $price);
    update_post_meta($product_id, '_virtual', 'yes');
    wp_set_object_terms($product_id, 'exclude-from-catalog', 'product_visibility');
    update_post_meta($product_id, '_adesivo_url', $image_url);
    set_post_thumbnail($product_id, $attachment_id);
    
    // Geração imediata do PDF a partir do PNG
    $png_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
    $pdf_path = convert_png_to_pdf($png_path);
    if ($pdf_path && file_exists($pdf_path)) {
        $pdf_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $pdf_path);
        update_post_meta($product_id, '_adesivo_pdf_url', $pdf_url);
        error_log("✅ PDF gerado e meta atualizada: " . $pdf_url);
    } else {
        error_log("❌ Falha ao gerar PDF para: " . $png_path);
    }
    
    $cart_item_data = array(
        'temp_product' => true,
        'adesivo_url'  => $image_url,
        'custom_price' => $price,
        'unique_key'   => md5(microtime() . rand())
    );
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
// 7. Exibição do Adesivo no Carrinho, Checkout e E-mails
// -----------------------------
function restore_custom_cart_item_data($cart_item, $cart_item_key) {
    if (isset($cart_item['adesivo_url']) && !empty($cart_item['adesivo_url'])) {
        $cart_item['data']->add_meta_data('adesivo_url', $cart_item['adesivo_url'], true);
    } else {
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

function exibir_imagem_personalizada_no_carrinho($item_data, $cart_item) {
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

function substituir_imagem_no_carrinho($product_image, $cart_item, $cart_item_key) {
    if (!empty($cart_item['adesivo_url']) && filter_var($cart_item['adesivo_url'], FILTER_VALIDATE_URL)) {
        return '<img src="' . esc_url($cart_item['adesivo_url']) . '" style="width: 80px; height:auto;">';
    }
    return $product_image;
}
add_filter('woocommerce_cart_item_thumbnail', 'substituir_imagem_no_carrinho', 10, 3);

function salvar_imagem_personalizada_no_pedido($item, $cart_item_key, $values, $order) {
    $adesivo_url = '';
    if (isset($values['adesivo_url']) && !empty($values['adesivo_url'])) {
        $adesivo_url = $values['adesivo_url'];
    } else {
        $product_id = $values['data']->get_id();
        $adesivo_url = get_post_meta($product_id, '_adesivo_url', true);
    }
    if (!empty($adesivo_url)) {
        $item->add_meta_data('_adesivo_url', esc_url($adesivo_url), true);
    }
    // Adiciona o meta do PDF
    $product_id = $values['data']->get_id();
    $pdf_url = get_post_meta($product_id, '_adesivo_pdf_url', true);
    if (!empty($pdf_url)) {
        $item->add_meta_data('_adesivo_pdf_url', esc_url($pdf_url), true);
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'salvar_imagem_personalizada_no_pedido', 10, 4);

// -----------------------------
// 8. Envio do Link do PDF nos E-mails de Pedido
// -----------------------------
function adicionar_link_adesivo_email($order, $sent_to_admin, $plain_text, $email) {
    $output = '';
    foreach ($order->get_items() as $item_id => $item) {
        $pdf_url = $item->get_meta('_adesivo_pdf_url');
        if ($pdf_url) {
            if ($plain_text) {
                $output .= "\n" . __('Download do Adesivo em PDF (alta qualidade):', 'woocommerce') . ' ' . esc_url($pdf_url) . "\n";
            } else {
                $output .= '<p>' . __('Download do Adesivo em PDF (alta qualidade):', 'woocommerce') . ' <a href="' . esc_url($pdf_url) . '" target="_blank">' . __('Clique aqui para baixar', 'woocommerce') . '</a></p>';
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

// -----------------------------
// 9. Limpeza Agendada dos Produtos Temporários
// -----------------------------
function limpar_produtos_personalizados_antigos() {
    global $wpdb;
    $tempo_limite = strtotime('-24 hours');
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

function agendar_limpeza_produtos_personalizados() {
    if (!wp_next_scheduled('evento_limpar_produtos_personalizados')) {
        wp_schedule_event(time(), 'daily', 'evento_limpar_produtos_personalizados');
    }
}
add_action('wp', 'agendar_limpeza_produtos_personalizados');
add_action('evento_limpar_produtos_personalizados', 'limpar_produtos_personalizados_antigos');

function desativar_limpeza_produtos_personalizados() {
    $timestamp = wp_next_scheduled('evento_limpar_produtos_personalizados');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'evento_limpar_produtos_personalizados');
    }
}
register_deactivation_hook(__FILE__, 'desativar_limpeza_produtos_personalizados');

add_filter('woocommerce_order_item_thumbnail', function ($product_image, $item) {
    $adesivo_url = $item->get_meta('_adesivo_url');
    if (!empty($adesivo_url)) {
        return '<img src="' . esc_url($adesivo_url) . '" alt="Adesivo Personalizado" style="max-width: 50px; height: auto;">';
    }
    return $product_image;
}, 10, 2);
