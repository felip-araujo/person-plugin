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

function permitir_svg_upload($mimes)
{
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}
add_filter('upload_mimes', 'permitir_svg_upload');

add_filter('admin_footer_text', 'customizar_rodape_plugin');

function customizar_rodape_plugin($footer_text)
{
    // Verifica se estamos na tela específica do plugin
    $tela_atual = get_current_screen();
    if ($tela_atual->id === 'toplevel_page_plugin-adesivos') {
        return ''; // Remove a mensagem do rodapé
    }

    return $footer_text; // Retorna o rodapé padrão para outras páginas
}

function carregar_bootstrap_no_admin($hook_suffix)
{
    if ($hook_suffix === 'toplevel_page_plugin-adesivos') {
        wp_enqueue_style('bootstrap-css', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
        wp_enqueue_script('bootstrap-js', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js', array('jquery'), null, true);
    }
}
add_action('admin_enqueue_scripts', 'carregar_bootstrap_no_admin');

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
    wp_enqueue_script('person-plugin-js', plugins_url('assets/js/customizador.js', __FILE__), array('jquery'), time(), true);

    wp_localize_script('person-plugin-js', 'personPlugin', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);
}
add_action('wp_enqueue_scripts', 'person_plugin_enqueue_scripts');


function meu_plugin_carregar_fontawesome_kit()
{
    // Verifica se está no painel de administração
    if (is_admin()) {
        wp_enqueue_script(
            'font-awesome-kit', // Handle único para o script
            'https://kit.fontawesome.com/d4755c66d3.js', // URL do Font Awesome Kit
            array(), // Dependências
            null, // Versão (deixe como null para usar a versão mais recente)
            true // Carregar no footer (true)
        );
    }
}
add_action('admin_enqueue_scripts', 'meu_plugin_carregar_fontawesome_kit');

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
    // Se não houver adesivo selecionado, você pode definir um padrão ou deixar vazio.
    // Exemplo (opcional):
    // if ( empty( $sticker_url ) ) { $sticker_url = 'URL_PADRÃO.svg'; }

    // Enfileira os scripts e estilos necessários para o editor
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

    // Inclui o template do editor (por exemplo, editor-template.php)
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
// 1️⃣ Salvar a imagem personalizada no servidor e retornar a URL correta
function salvar_imagem_personalizada($base64_image)
{
    $upload_dir = wp_upload_dir();
    $filename = 'adesivo-' . time() . '.png';
    $upload_path = $upload_dir['path'] . '/' . $filename;

    // Obtém a subpasta correta (ano/mês)
    $relative_path = str_replace($upload_dir['basedir'], '', $upload_dir['path']);
    $upload_url = $upload_dir['baseurl'] . $relative_path . '/' . $filename;




    // Decodificar Base64
    $image_data = explode(',', $base64_image);
    if (!isset($image_data[1])) {
        error_log('❌ Base64 inválido.');
        return false;
    }
    $decoded_image = base64_decode($image_data[1]);

    if (!$decoded_image) {
        error_log('❌ Erro ao decodificar a imagem.');
        return false;
    }

    // Salvar imagem no diretório de uploads
    if (file_put_contents($upload_path, $decoded_image) === false) {
        error_log('❌ Erro ao salvar a imagem.');
        return false;
    }

    error_log('✅ Imagem salva com sucesso: ' . $upload_url);
    return $upload_url;
}

// 2️⃣ Adicionar o adesivo ao carrinho do WooCommerce corretamente
function adicionar_adesivo_ao_carrinho()
{
    if (!isset($_POST['adesivo_url'])) {
        error_log('❌ Nenhuma imagem foi enviada.');
        wp_send_json_error(['message' => 'Nenhuma imagem foi enviada.']);
    }

    // Salvar a imagem e obter a URL
    $adesivo_url = salvar_imagem_personalizada($_POST['adesivo_url']);
    if (!$adesivo_url) {
        error_log('❌ Erro ao salvar a imagem.');
        wp_send_json_error(['message' => 'Erro ao salvar a imagem.']);
    }

    // Recupera o ID do produto personalizado (definido nas configurações do plugin)
    $produto_id = get_option('manual_product_id'); // ou outro método para obter o ID

    // Recupera o preço customizado definido no painel de administração
    $custom_price = get_option('custom_sticker_price'); // Ex: "29.99"
    if (empty($custom_price)) {
        $custom_price = 0; // ou um valor default
    }

    // Inclui o preço customizado nos dados do item do carrinho
    $cart_item_data = [
        'adesivo_url'         => $adesivo_url,
        'unique_key'          => md5(microtime() . rand()),
        'custom_sticker_price' => $custom_price,
    ];

    $cart_item_key = WC()->cart->add_to_cart($produto_id, 1, 0, [], $cart_item_data);

    if ($cart_item_key) {
        error_log('✅ Produto adicionado ao carrinho com imagem: ' . $adesivo_url);
        wp_send_json_success([
            'message'  => 'Produto adicionado ao carrinho!',
            'cart_url' => wc_get_cart_url()
        ]);
    } else {
        error_log('❌ Erro ao adicionar o produto ao carrinho.');
        error_log($produto_id);
        wp_send_json_error(['message' => 'Erro ao adicionar o produto ao carrinho.']);
    }
}
add_action('wp_ajax_adicionar_adesivo_ao_carrinho', 'adicionar_adesivo_ao_carrinho');
add_action('wp_ajax_nopriv_adicionar_adesivo_ao_carrinho', 'adicionar_adesivo_ao_carrinho');


add_action('admin_post_update_sticker_price', 'save_sticker_price');
function save_sticker_price()
{
    // Verificar se o formulário foi enviado e se o nonce é válido
    if (
        !isset($_POST['update_sticker_price_nonce_field']) ||
        !wp_verify_nonce($_POST['update_sticker_price_nonce_field'], 'update_sticker_price_nonce')
    ) {
        wp_die('Erro de validação. Acesso não autorizado!');
    }

    // Verifica se os campos 'sticker_price' e 'sticker_id' foram enviados
    if (isset($_POST['sticker_price']) && isset($_POST['sticker_id'])) {
        $sticker_id = intval($_POST['sticker_id']);
        $new_price = sanitize_text_field($_POST['sticker_price']);

        // Atualiza o meta do post (adesivo) com a chave '_sticker_price'
        update_post_meta($sticker_id, '_sticker_price', $new_price);

        // Redireciona para a página de administração com uma mensagem de sucesso
        wp_redirect(admin_url('admin.php?page=plugin-adesivos&status=success'));
        exit;
    } else {
        // Redireciona com mensagem de erro se os campos não foram enviados corretamente
        wp_redirect(admin_url('admin.php?page=plugin-adesivos&status=error'));
        exit;
    }
}


function custom_dynamic_price($cart)
{
    // Evita a execução em áreas administrativas fora de AJAX
    if (is_admin() && !defined('DOING_AJAX'))
        return;

    // Percorre os itens do carrinho
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['custom_sticker_price']) && is_numeric($cart_item['custom_sticker_price'])) {
            // Atualiza o preço do item com o valor customizado
            $cart_item['data']->set_price(floatval($cart_item['custom_sticker_price']));
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'custom_dynamic_price', 10, 1);




// 3️⃣ Recuperar a URL da imagem ao recarregar o carrinho
function recuperar_dados_personalizados_carrinho($cart_item, $cart_item_key)
{
    if (isset($cart_item['adesivo_url'])) {
        $cart_item['data']->add_meta_data('adesivo_url', $cart_item['adesivo_url'], true);
    }
    return $cart_item;
}
add_filter('woocommerce_get_cart_item_from_session', 'recuperar_dados_personalizados_carrinho', 10, 2);

// 4️⃣ Exibir a imagem na lista de itens do carrinho
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


function substituir_imagem_no_carrinho($product_image, $cart_item, $cart_item_key)
{
    if (!empty($cart_item['adesivo_url']) && filter_var($cart_item['adesivo_url'], FILTER_VALIDATE_URL)) {
        error_log('📌 Substituindo imagem no carrinho com: ' . esc_url($cart_item['adesivo_url']));
        return '<img src="' . esc_url($cart_item['adesivo_url']) . '" style="width: 80px; height: auto;">';
    }

    return $product_image;
}
add_filter('woocommerce_cart_item_thumbnail', 'substituir_imagem_no_carrinho', 10, 3);



// 6️⃣ Garantir que a imagem seja salva corretamente no pedido
function salvar_imagem_personalizada_no_pedido($item, $cart_item_key, $values, $order)
{
    if (isset($values['adesivo_url']) && !empty($values['adesivo_url'])) {
        $item->add_meta_data('Imagem Personalizada', esc_url($values['adesivo_url']));
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'salvar_imagem_personalizada_no_pedido', 10, 4);

function restaurar_dados_personalizados_no_carrinho($cart_item, $cart_item_key)
{
    if (isset($cart_item['adesivo_url'])) {
        $cart_item['data']->add_meta_data('adesivo_url', $cart_item['adesivo_url'], true);
    }
    return $cart_item;
}
add_filter('woocommerce_get_cart_item_from_session', 'restaurar_dados_personalizados_no_carrinho', 10, 2);

add_filter('theme_page_templates', 'mp_adicionar_template_no_dropdown');
function mp_adicionar_template_no_dropdown($templates)
{
    $templates['page-adesivos.php'] = 'Editor de Adesivos';
    return $templates;
}

add_filter('template_include', 'mp_carregar_template_personalizado');
function mp_carregar_template_personalizado($template)
{
    if (is_page()) {
        $pagina_id = get_queried_object_id();
        $escolhido = get_page_template_slug($pagina_id);

        // Verifica se o template selecionado é o nosso
        if ($escolhido === 'page-adesivos.php') {
            // Caminho do template dentro do plugin
            $template_custom = plugin_dir_path(__FILE__) . 'templates/page-adesivos.php';
            if (file_exists($template_custom)) {
                return $template_custom;
            }
        }
    }
    return $template;
}
