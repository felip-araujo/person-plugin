<?php
/*
Plugin Name: Person Plugin - Customizador de Adesivos
Description: Plugin para customização de adesivos com controle de cores, fontes e preço dinâmico.
Version: 1.1
Author: Evolution Design
*/

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/templates/email-handlers.php';

/* ==============================
   Configurações de Debug
============================== */
// Certifique-se de que WP_DEBUG e WP_DEBUG_LOG estejam ativados no wp-config.php
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}

/**
 * Função auxiliar para logar mensagens no debug.log.
 */
function person_plugin_log($message)
{
    if (WP_DEBUG && WP_DEBUG_LOG) {
        error_log('[Person Plugin] ' . $message);
    }
}

/* ==============================
   Upload de Arquivos e Outras Configurações
============================== */

// Permitir upload de SVG
function person_plugin_permitir_svg_upload($mimes)
{
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}
add_filter('upload_mimes', 'person_plugin_permitir_svg_upload');

// Customizar o rodapé do admin na página do plugin
function person_plugin_customizar_rodape_plugin($footer_text)
{
    $tela_atual = get_current_screen();
    if (isset($tela_atual->id) && $tela_atual->id === 'toplevel_page_plugin-adesivos') {
        return '';
    }
    return $footer_text;
}
add_filter('admin_footer_text', 'person_plugin_customizar_rodape_plugin');

// Carregar Bootstrap no admin para a página do plugin
function person_plugin_carregar_bootstrap_no_admin($hook_suffix)
{
    if ($hook_suffix === 'toplevel_page_plugin-adesivos') {
        wp_enqueue_style('bootstrap-css', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
        wp_enqueue_script('bootstrap-js', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js', array('jquery'), null, true);
    }
}
add_action('admin_enqueue_scripts', 'person_plugin_carregar_bootstrap_no_admin');

/* ==============================
   Enfileiramento de Scripts e Estilos
============================== */

// Enfileirar scripts e estilos para o front-end (página "custom-sticker")
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

// Enfileirar script global do plugin e localizar variáveis
function person_plugin_enqueue_scripts()
{
    wp_enqueue_script('person-plugin-js', plugins_url('assets/js/customizador.js', __FILE__), array('jquery'), time(), true);
    wp_localize_script('person-plugin-js', 'personPlugin', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);
}
add_action('wp_enqueue_scripts', 'person_plugin_enqueue_scripts');

// Carregar Font Awesome Kit no admin
function person_plugin_carregar_fontawesome_kit()
{
    if (is_admin()) {
        wp_enqueue_script('font-awesome-kit', 'https://kit.fontawesome.com/d4755c66d3.js', array(), null, true);
    }
}
add_action('admin_enqueue_scripts', 'person_plugin_carregar_fontawesome_kit');


function enqueue_introjs_scripts()
{
    wp_enqueue_style('introjs-css', 'https://unpkg.com/intro.js/minified/introjs.min.css');
    wp_enqueue_script('introjs-js', 'https://unpkg.com/intro.js/minified/intro.min.js', array('jquery'), null, true);
    wp_enqueue_script('custom-introjs', plugins_url('/js/custom-intro.js', __FILE__), array('introjs-js'), null, true);
}
add_action('wp_enqueue_scripts', 'enqueue_introjs_scripts');


/* ==============================
   Menu e Páginas de Configuração no Admin
============================== */

function person_plugin_adicionar_menu()
{
    add_menu_page(
        'Configurações de Adesivos',
        'Seus Adesivos',
        'manage_options',
        'plugin-adesivos',
        'person_plugin_pagina_de_configuracao',
        'dashicons-format-image',
        6
    );
}
add_action('admin_menu', 'person_plugin_adicionar_menu');

function person_plugin_pagina_de_configuracao()
{
    echo '<div class="wrap">
        <h1>Configurações de Adesivos</h1>
        <div class="alert alert-warning" style="margin-top: 1rem; padding: 10px;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i>
            <p style="margin: 0;">
               Crie uma página com o shortcode <strong>[customizador_adesivo_page]</strong> para exibir o editor de adesivos.
            </p>
        </div>';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_sticker'])) {
        // Processamento de upload (se aplicável)
    }
    $file = plugin_dir_path(__FILE__) . 'templates/admin-form.php';
    if (file_exists($file)) {
        include $file;
    } else {
        echo '<p class="alert alert-danger">Erro: Formulário de configuração não encontrado.</p>';
    }
    echo '</div>';
}

/* ==============================
   Funcionalidades do Customizador e Carrinho
============================== */

/**
 * Salva a imagem personalizada.
 */
function person_plugin_salvar_imagem_personalizada($base64_image)
{
    $upload_dir = wp_upload_dir();
    $filename = 'adesivo-' . time() . '.png';
    $upload_path = $upload_dir['path'] . '/' . $filename;
    $relative_path = str_replace($upload_dir['basedir'], '', $upload_dir['path']);
    $upload_url = $upload_dir['baseurl'] . $relative_path . '/' . $filename;
    $image_data = explode(',', $base64_image);
    if (!isset($image_data[1])) {
        person_plugin_log('Base64 inválido.');
        return false;
    }
    $decoded_image = base64_decode($image_data[1]);
    if (!$decoded_image) {
        person_plugin_log('Erro ao decodificar a imagem.');
        return false;
    }
    if (file_put_contents($upload_path, $decoded_image) === false) {
        person_plugin_log('Erro ao salvar a imagem.');
        return false;
    }
    person_plugin_log('Imagem salva com sucesso: ' . $upload_url);
    return $upload_url;
}

/**
 * Callback AJAX para adicionar o adesivo personalizado ao carrinho.
 * Espera os seguintes parâmetros via POST:
 * - produto_id: ID do produto "adesivo personalizado"
 * - preco: valor dinâmico a ser aplicado
 * - adesivo_url: a imagem em base64 gerada pelo customizador
 */
function person_plugin_adicionar_adesivo_ao_carrinho_callback()
{
    $produto_id = isset($_POST['produto_id']) ? intval($_POST['produto_id']) : 0;
    $preco = isset($_POST['preco']) ? floatval($_POST['preco']) : 0;
    $adesivo_url = isset($_POST['adesivo_url']) ? sanitize_text_field($_POST['adesivo_url']) : '';

    if (!$produto_id || !$preco || empty($adesivo_url)) {
        person_plugin_log("Dados inválidos: produto_id: $produto_id, preco: $preco, adesivo_url: $adesivo_url");
        wp_send_json_error(['message' => 'Dados inválidos fornecidos.']);
        return;
    }

    $adesivo_salvo = person_plugin_salvar_imagem_personalizada($adesivo_url);
    if (!$adesivo_salvo) {
        person_plugin_log("Erro ao salvar imagem personalizada.");
        wp_send_json_error(['message' => 'Erro ao salvar a imagem personalizada.']);
        return;
    }

    $cart_item_data = [
        'custom_price' => $preco,
        'adesivo_url'  => $adesivo_salvo,
    ];

    person_plugin_log("Adicionando item com custom_price: $preco");
    $cart_item_key = WC()->cart->add_to_cart($produto_id, 1, 0, [], $cart_item_data);
    if ($cart_item_key) {
        person_plugin_log("Item adicionado ao carrinho: $cart_item_key");
        wp_send_json_success(['cart_url' => wc_get_cart_url()]);
    } else {
        person_plugin_log("Erro ao adicionar o item ao carrinho");
        wp_send_json_error(['message' => 'Erro ao adicionar o produto ao carrinho.']);
    }
}
add_action('wp_ajax_adicionar_adesivo_ao_carrinho', 'person_plugin_adicionar_adesivo_ao_carrinho_callback');
add_action('wp_ajax_nopriv_adicionar_adesivo_ao_carrinho', 'person_plugin_adicionar_adesivo_ao_carrinho_callback');

/* Removido o filtro que verificava 'custom_sticker_price', pois o AJAX envia o preço no campo "preco". */

/**
 * Atualiza os preços dos itens do carrinho com base no valor 'custom_price'.
 * Usamos prioridade 99 para garantir que essa alteração ocorra após outros cálculos.
 */
add_action('woocommerce_before_calculate_totals', 'person_plugin_custom_price_override', 99);
function person_plugin_custom_price_override($cart)
{
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['custom_price']) && is_numeric($cart_item['custom_price'])) {
            $novo_preco = floatval($cart_item['custom_price']);
            $cart_item['data']->set_price($novo_preco);
            person_plugin_log("Preço dinâmico aplicado: $novo_preco para o item $cart_item_key");
        }
    }
}

/**
 * Exibe os dados personalizados (imagem e preço) na página do carrinho.
 */
add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
    if (isset($cart_item['adesivo_url'])) {
        $item_data[] = [
            'key'   => __('Imagem Personalizada', 'woocommerce'),
            'value' => '<img src="' . esc_url($cart_item['adesivo_url']) . '" style="max-width:100px; height:auto;">',
        ];
    }
    if (isset($cart_item['custom_price'])) {
        $item_data[] = [
            'key'   => __('Preço Personalizado', 'woocommerce'),
            'value' => wc_price($cart_item['custom_price']),
        ];
    }
    return $item_data;
}, 10, 2);

/**
 * Salva os dados personalizados no pedido.
 */
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
    if (isset($values['adesivo_url'])) {
        $item->add_meta_data('Imagem Personalizada', esc_url($values['adesivo_url']));
    }
    if (isset($values['custom_price'])) {
        $item->add_meta_data('Preço Personalizado', wc_price($values['custom_price']));
    }
}, 10, 4);

/* ==============================
   Shortcodes e Templates
============================== */

// Shortcode para exibir o editor de adesivos na página
function person_plugin_customizer_page()
{
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/customizador-page.php';
    return ob_get_clean();
}
add_shortcode('customizador_adesivo_page', 'person_plugin_customizer_page');

// Função para carregar o template do editor
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

/* ==============================
   Ativação do Plugin
============================== */

register_activation_hook(__FILE__, 'person_plugin_criar_tabela_adesivos');
function person_plugin_criar_tabela_adesivos()
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


/**
 * Força a exibição do preço customizado no carrinho, se estiver definido.
 */
add_filter('woocommerce_cart_item_price', 'person_plugin_display_custom_price', 10, 3);
function person_plugin_display_custom_price($price, $cart_item, $cart_item_key)
{
    if (isset($cart_item['custom_price']) && is_numeric($cart_item['custom_price'])) {
        // Retorna o preço formatado usando wc_price()
        $price = wc_price($cart_item['custom_price']);
    }
    return $price;
}

/**
 * Força a exibição do subtotal do item no carrinho com o preço customizado.
 */
add_filter('woocommerce_cart_item_subtotal', 'person_plugin_display_custom_subtotal', 10, 3);
function person_plugin_display_custom_subtotal($subtotal, $cart_item, $cart_item_key)
{
    if (isset($cart_item['custom_price']) && is_numeric($cart_item['custom_price'])) {
        $quantity = $cart_item['quantity'];
        $subtotal = wc_price($cart_item['custom_price'] * $quantity);
    }
    return $subtotal;
}
