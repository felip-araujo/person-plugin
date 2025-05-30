<?php
/*
Plugin Name: Person Plugin - Editor de Adesivos
Description: Plugin para edição de (Arquivos SVG) edite e gerencie seus arquivos de forma prática.
Version: 2.2.1
Author: Evolution Design
Author URI:  https://evoludesign.com.br/
*/

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/templates/email-handlers.php';

/* -------------------------------------------------------------------------
   1. Rota para Forçar Download do Arquivo (PDF)
------------------------------------------------------------------------- */
function force_download_file()
{
    if (isset($_GET['download_file']) && !empty($_GET['download_file'])) {
        $relative_file = sanitize_text_field($_GET['download_file']);
        $upload_dir = wp_upload_dir();
        $filepath = trailingslashit($upload_dir['basedir']) . $relative_file;
        error_log("Tentando baixar o arquivo: " . $filepath);

        if (file_exists($filepath) && strpos($filepath, $upload_dir['basedir']) === 0) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        } else {
            error_log("Arquivo não encontrado ou caminho inválido: " . $filepath);
            wp_die('Arquivo não encontrado.', 'Erro', array('response' => 404));
        }
    }
}
add_action('template_redirect', 'force_download_file');

/* -------------------------------------------------------------------------
   2. Rota para Forçar Download do SVG
------------------------------------------------------------------------- */
function force_download_svg_file()
{
    if (isset($_GET['download_svg']) && !empty($_GET['download_svg'])) {
        $relative_file = sanitize_text_field($_GET['download_svg']);
        $upload_dir = wp_upload_dir();
        $filepath = trailingslashit($upload_dir['basedir']) . $relative_file;
        error_log("Tentando baixar o SVG: " . $filepath);

        if (file_exists($filepath) && strpos($filepath, $upload_dir['basedir']) === 0) {
            header('Content-Description: File Transfer');
            header('Content-Type: image/svg+xml');
            header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        } else {
            error_log("SVG não encontrado ou caminho inválido: " . $filepath);
            wp_die('SVG não encontrado.', 'Erro', array('response' => 404));
        }
    }
}
add_action('template_redirect', 'force_download_svg_file');

/* -------------------------------------------------------------------------
   3. Uploads e Configurações Gerais
------------------------------------------------------------------------- */
function permitir_svg_upload($mimes)
{
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}
add_filter('upload_mimes', 'permitir_svg_upload');

function customizar_rodape_plugin($footer_text)
{
    $tela_atual = get_current_screen();
    if ($tela_atual->id === 'toplevel_page_plugin-adesivos') {
        return '';
    }
    return $footer_text;
}
add_filter('admin_footer_text', 'customizar_rodape_plugin');

function carregar_bootstrap_no_admin($hook_suffix)
{
    if ($hook_suffix === 'toplevel_page_plugin-adesivos') {
        wp_enqueue_style('bootstrap-css', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
        wp_enqueue_script('bootstrap-js', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js', array('jquery'), null, true);
    }
}
add_action('admin_enqueue_scripts', 'carregar_bootstrap_no_admin');

/* -------------------------------------------------------------------------
   4. Scripts e Estilos do Frontend
------------------------------------------------------------------------- */
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
    wp_enqueue_script('fabric-js', 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js', array(), null, true);
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

function add_module_attribute($tag, $handle, $src)
{
    if ('person-plugin-js' === $handle) {
        $tag = '<script type="module" src="' . esc_url($src) . '"></script>';
    }
    return $tag;
}
add_filter('script_loader_tag', 'add_module_attribute', 10, 3);

function meu_plugin_carregar_fontawesome_kit()
{
    if (is_admin()) {
        wp_enqueue_script('font-awesome-kit', 'https://kit.fontawesome.com/d4755c66d3.js', array(), null, true);
    }
}
add_action('admin_enqueue_scripts', 'meu_plugin_carregar_fontawesome_kit');

/* -------------------------------------------------------------------------
   5. Menu e Templates do Admin
------------------------------------------------------------------------- */
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
    echo '<div class="alert alert-warning" style="display: inline-flex; align-items: center; font-size: 1.2rem; margin-top: 1rem; padding: 10px;">
    <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i>
    <p style="margin: 0;">Crie uma página com a tag <strong>[customizador_adesivo_page]</strong> para exibir o editor de adesivos, copie a tag abaixo.</p>
    </div>';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_sticker'])) {
        plugin_processar_upload();
    }
    $file = plugin_dir_path(__FILE__) . 'templates/admin-form.php';
    if (file_exists($file)) {
        include $file;
    } else {
        echo '<p class="alert alert-danger">Erro: Formulário de configuração não encontrado.</p>';
    }
    echo '</div>';
}

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

/* -------------------------------------------------------------------------
   6. Função para Exibição do Editor (mantendo a exibição original)
------------------------------------------------------------------------- */
function person_plugin_display_customizer($sticker_url = '')
{
    wp_enqueue_script('person-plugin-customizer-js', plugin_dir_url(__FILE__) . 'assets/js/customizador.js', array('jquery', 'konva-js'), null, true);
    wp_localize_script('person-plugin-customizer-js', 'pluginData', array(
        'stickerUrl' => $sticker_url,
        'ajaxUrl'    => admin_url('admin-ajax.php'),
    ));
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/editor-template.php';
    return ob_get_clean();
}

/* -------------------------------------------------------------------------
   7. Shortcode para Exibição do Customizador na Página
------------------------------------------------------------------------- */
function person_plugin_customizer_page()
{
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/customizador-page.php';
    return ob_get_clean();
}
add_shortcode('customizador_adesivo_page', 'person_plugin_customizer_page');

/* -------------------------------------------------------------------------
   8. Funções Auxiliares para Conversão de SVG para PDF via Python/CairoSVG
------------------------------------------------------------------------- */

/**
 * Retorna as dimensões do SVG (em milímetros) a partir do atributo width/height.
 * Se os valores incluírem "mm", eles são convertidos para float.
 */






/**
 * Função auxiliar para extrair as dimensões (em mm) de um arquivo SVG.
 * Ela lê os atributos width e height e remove o sufixo "mm" se presente.
 */



/* -------------------------------------------------------------------------
   9. Salvamento do SVG e Criação do Produto Temporário no WooCommerce
------------------------------------------------------------------------- */
function salvar_imagem_personalizada($base64_image)
{
    $upload_dir = wp_upload_dir();
    $filename = 'adesivo-' . time() . '.png';
    $upload_path = $upload_dir['path'] . '/' . $filename;
    $base64_image = preg_replace('#^data:image/\\w+;base64,#i', '', $base64_image);
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

// function ajustar_svg_dimensoes($svg_content)
// {
//     $dom = new DOMDocument();
//     libxml_use_internal_errors(true);
//     $dom->loadXML($svg_content);
//     libxml_clear_errors();

//     $svg = $dom->getElementsByTagName('svg')->item(0);
//     if ($svg) {
//         $width = $svg->getAttribute('width');
//         $height = $svg->getAttribute('height');

//         // Se já estiverem definidos em "mm", não altera nada
//         if (
//             !empty($width) && !empty($height) &&
//             (stripos($width, 'mm') !== false && stripos($height, 'mm') !== false)
//         ) {
//             return $dom->saveXML();
//         }

//         // Se houver viewBox, usamos os valores do viewBox para definir as dimensões com "mm"
//         if ($svg->hasAttribute('viewBox')) {
//             $viewBox = $svg->getAttribute('viewBox');
//             $parts = preg_split('/\s+/', trim($viewBox));
//             if (count($parts) === 4) {
//                 $w = $parts[2];
//                 $h = $parts[3];
//                 $svg->setAttribute('width', $w . 'mm');
//                 $svg->setAttribute('height', $h . 'mm');
//             }
//         }
//         return $dom->saveXML();
//     }
//     return $svg_content;
// }



function salvar_adesivo_servidor()
{
    if (!isset($_POST['adesivo_svg']) || !isset($_POST['price'])) {
        wp_send_json_error(array('message' => 'Dados incompletos.'));
        wp_die();
    }

    $price = floatval($_POST['price']);
    error_log("📌 Preço recebido no PHP: " . $price);

    // Salva o conteúdo SVG em um arquivo sem ajustes
    $upload_dir = wp_upload_dir();
    $filename_svg = 'adesivo-' . time() . '.svg';
    $upload_path_svg = trailingslashit($upload_dir['path']) . $filename_svg;
    // Não aplica ajustar_svg_dimensoes(), usa o SVG enviado
    $svg_content = wp_unslash($_POST['adesivo_svg']);

    if (file_put_contents($upload_path_svg, $svg_content) === false) {
        error_log('❌ Erro ao salvar o SVG.');
        wp_send_json_error(array('message' => 'Erro ao salvar o SVG.'));
        wp_die();
    }
    $svg_url = trailingslashit($upload_dir['url']) . $filename_svg;
    error_log('✅ SVG salvo com sucesso: ' . $svg_url);

    // Criação do produto temporário no WooCommerce
    $product_title = 'Adesivo Personalizado - ' . time();
    $produto_temporario = array(
        'post_title'   => $product_title,
        'post_content' => '',
        'post_status'  => 'publish',
        'post_type'    => 'product'
    );
    $product_id = wp_insert_post($produto_temporario);
    if (!$product_id) {
        error_log('❌ Erro ao criar produto temporário.');
        wp_send_json_error(array('message' => 'Erro ao criar produto.'));
        wp_die();
    }
    wp_set_post_terms($product_id, array('exclude-from-catalog', 'exclude-from-search'), 'product_visibility');

    // Define preço e salva a URL do SVG
    update_post_meta($product_id, '_regular_price', $price);
    update_post_meta($product_id, '_price', $price);
    update_post_meta($product_id, '_adesivo_svg_url', $svg_url);

    // Cria attachment para o SVG e define como imagem destacada
    $attachment = array(
        'post_mime_type' => 'image/svg+xml',
        'post_title'     => sanitize_file_name(basename($upload_path_svg)),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );
    $attachment_id = wp_insert_attachment($attachment, $upload_path_svg, $product_id);
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attachment_id, $upload_path_svg);
    wp_update_attachment_metadata($attachment_id, $attach_data);
    set_post_thumbnail($product_id, $attachment_id);

    // Marca o adesivo como "editado"
    update_post_meta($attachment_id, '_adesivo_editado', 'sim');

    // Adiciona o produto ao carrinho com a URL do SVG
    $cart_item_data = array(
        'adesivo_url' => $svg_url
    );
    $added = WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);
    if (!$added) {
        error_log('❌ Erro ao adicionar o produto ao carrinho.');
        wp_send_json_error(array('message' => 'Erro ao adicionar o produto ao carrinho.'));
        wp_die();
    }

    wp_send_json_success(array(
        'message'  => 'Produto temporário criado e adicionado ao carrinho!',
        'cart_url' => wc_get_cart_url()
    ));
    wp_die();
}
add_action('wp_ajax_salvar_adesivo_servidor', 'salvar_adesivo_servidor');
add_action('wp_ajax_nopriv_salvar_adesivo_servidor', 'salvar_adesivo_servidor');


/* -------------------------------------------------------------------------
   10. Exibição do Adesivo no Carrinho, Checkout e E-mails
------------------------------------------------------------------------- */
function restore_custom_cart_item_data($cart_item, $cart_item_key)
{
    if (isset($cart_item['adesivo_url']) && !empty($cart_item['adesivo_url'])) {
        $cart_item['data']->add_meta_data('adesivo_url', $cart_item['adesivo_url'], true);
    } else {
        $product_id = $cart_item['data']->get_id();
        $meta = get_post_meta($product_id, '_adesivo_svg_url', true);
        if (!empty($meta)) {
            $cart_item['adesivo_url'] = $meta;
            $cart_item['data']->add_meta_data('adesivo_url', $meta, true);
        } else {
            error_log("❌ Nenhum SVG encontrado no carrinho para o item " . $cart_item_key);
        }
    }
    return $cart_item;
}
add_filter('woocommerce_get_cart_item_from_session', 'restore_custom_cart_item_data', 20, 2);

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


// Adiciona o campo de link de personalização na página do produto
add_action('woocommerce_product_options_general_product_data', 'ea_adicionar_campo_link_personalizador');
function ea_adicionar_campo_link_personalizador()
{
    woocommerce_wp_text_input(
        array(
            'id'          => '_link_personalizador',
            'label'       => __('Link para Personalizador', 'text-domain'),
            'placeholder' => 'https://seusite.com/personalizador',
            'description' => __('Digite o link da página do personalizador para este produto.'),
            'desc_tip'    => true,
            'type'        => 'url'
        )
    );
}

// Salva o campo personalizado corretamente, sem remover caracteres
add_action('woocommerce_process_product_meta', 'ea_salvar_campo_link_personalizador');
function ea_salvar_campo_link_personalizador($post_id)
{
    if (isset($_POST['_link_personalizador'])) {
        $link_personalizador = $_POST['_link_personalizador'];

        // Garante que o link seja salvo exatamente como foi digitado
        update_post_meta($post_id, '_link_personalizador', esc_url_raw($link_personalizador));
    }
}


// Exibe o botão de personalização na página do produto
add_action('woocommerce_single_product_summary', 'ea_exibir_botao_personalizador', 25);
function ea_exibir_botao_personalizador()
{
    global $product;

    $link_personalizador = get_post_meta($product->get_id(), '_link_personalizador', true);

    if (!empty($link_personalizador)) {
        // Não fazer nenhuma codificação, apenas exibir o link diretamente
        echo '<div class="ea-botao-personalizador" style="margin-top: 15px;">';
        echo '<a href="' . $link_personalizador . '" class="button" target="_blank" rel="noopener noreferrer" style="background-color: #00a2ff; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Editar Agora</a>';
        echo '</div>';
    }
}


// Cria um shortcode para exibir o botão do personalizador
function ea_shortcode_botao_personalizador() {
    if (!is_product()) return '';

    global $product;

    if (!$product) return '';

    $link_personalizador = get_post_meta($product->get_id(), '_link_personalizador', true);

    if (!empty($link_personalizador)) {
        return '<div class="ea-botao-personalizador" style="margin-top: 15px;">
                    <a href="' . esc_url($link_personalizador) . '" class="button" target="_blank" rel="noopener noreferrer" style="background-color: #00a2ff; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                        Personalizar Agora
                    </a>
                </div>';
    }

    return '';
}
add_shortcode('botao_personalizador', 'ea_shortcode_botao_personalizador');



/* -------------------------------------------------------------------------
   11. Transferência do Meta do Carrinho para o Pedido
------------------------------------------------------------------------- */
function add_svg_to_order_item_meta($item, $cart_item_key, $values, $order)
{
    if (!empty($values['adesivo_url'])) {
        $item->update_meta_data('_adesivo_svg_url', $values['adesivo_url']);
        $pdf_url = get_post_meta($item->get_product_id(), '_adesivo_pdf_url', true);
        if ($pdf_url) {
            $item->update_meta_data('_adesivo_pdf_url', $pdf_url);
        }
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'add_svg_to_order_item_meta', 10, 4);

/* -------------------------------------------------------------------------
   12. Exibição do Link do Adesivo nos E-mails de Pedido
------------------------------------------------------------------------- */
function adicionar_link_adesivo_email($order, $sent_to_admin, $plain_text, $email)
{
    // Se não for um e-mail para o administrador, não exibe os links
    if (!$sent_to_admin) {
        return;
    }

    error_log("🚀 Hook 'adicionar_link_adesivo_email' acionado para admin!");
    $output = '';

    foreach ($order->get_items() as $item_id => $item) {
        $svg_url = $item->get_meta('_adesivo_svg_url');
        if ($svg_url) {
            $upload_dir = wp_upload_dir();
            $relative_svg = str_replace($upload_dir['baseurl'] . '/', '', $svg_url);
            $download_link_svg = home_url('?download_svg=' . $relative_svg);
            $pdf_url = $item->get_meta('_adesivo_pdf_url');
            if ($pdf_url) {
                $relative_file = str_replace($upload_dir['baseurl'] . '/', '', $pdf_url);
                $download_link_file = home_url('?download_file=' . $relative_file);
            } else {
                $download_link_file = '';
            }

            if ($plain_text) {
                $output .= "\n" . __('Download do Adesivo SVG (alta qualidade):', 'woocommerce') . ' ' . esc_url($download_link_svg) . "\n";
                if (!empty($download_link_file)) {
                    $output .= "\n" . __('Download do Adesivo PDF:', 'woocommerce') . ' ' . esc_url($download_link_file) . "\n";
                }
            } else {
                $output .= '<p>' . __('Download do Adesivo SVG (alta qualidade):', 'woocommerce') . ' <a href="' . esc_url($download_link_svg) . '" target="_blank">' . __('Clique aqui para baixar', 'woocommerce') . '</a></p>';
                if (!empty($download_link_file)) {
                    $output .= '<p>' . __('Download do Adesivo PDF:', 'woocommerce') . ' <a href="' . esc_url($download_link_file) . '" target="_blank">' . __('Clique aqui para baixar', 'woocommerce') . '</a></p>';
                }
            }
        } else {
            error_log("❌ Nenhum SVG encontrado no carrinho para o item $item_id.");
        }
    }

    if (!empty($output)) {
        error_log("📝 Link de adesivo adicionado ao e-mail para o admin.");
        if ($plain_text) {
            echo "\n" . __('Adesivo Personalizado', 'woocommerce') . "\n" . $output;
        } else {
            echo '<h2>' . __('Adesivo Personalizado', 'woocommerce') . '</h2>' . $output;
        }
    } else {
        error_log("⚠️ Nenhum link foi gerado para o e-mail.");
    }
}
add_action('woocommerce_email_order_meta', 'adicionar_link_adesivo_email', 10, 4);
add_action('woocommerce_email_after_order_table', 'adicionar_link_adesivo_email', 10, 4);


/* -------------------------------------------------------------------------
   13. Anexar PDF nos E-mails do WooCommerce
------------------------------------------------------------------------- */
function add_pdf_attachment_to_woocommerce_email($attachments, $email_id, $order)
{
    if (in_array($email_id, array('customer_processing_order', 'customer_completed_order'))) {
        foreach ($order->get_items() as $item) {
            $pdf_url = $item->get_meta('_adesivo_pdf_url');
            if ($pdf_url) {
                $upload_dir = wp_upload_dir();
                $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $pdf_url);
                if (file_exists($file_path)) {
                    $attachments[] = $file_path;
                } else {
                    error_log("❌ Arquivo PDF não encontrado para anexar ao e-mail: " . $file_path);
                }
            } else {
                error_log("❌ Nenhum PDF encontrado para o item do pedido.");
            }
        }
    }
    return $attachments;
}
add_filter('woocommerce_email_attachments', 'add_pdf_attachment_to_woocommerce_email', 10, 3);

/* -------------------------------------------------------------------------
   14. Limpeza Agendada dos Produtos Temporários
------------------------------------------------------------------------- */
function limpar_produtos_personalizados_antigos()
{
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

/* -------------------------------------------------------------------------
   15. Outros (Exibição da imagem no carrinho e Font Awesome)
------------------------------------------------------------------------- */
add_filter('woocommerce_order_item_thumbnail', function ($product_image, $item) {
    $adesivo_url = $item->get_meta('_adesivo_svg_url');
    if (!empty($adesivo_url)) {
        return '<img src="' . esc_url($adesivo_url) . '" alt="Adesivo Personalizado" style="max-width: 50px; height: auto;">';
    }
    return $product_image;
}, 10, 2);

function carregar_font_awesome()
{
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4');
}
add_action('admin_enqueue_scripts', 'carregar_font_awesome');
add_action('wp_enqueue_scripts', 'carregar_font_awesome');

/* -------------------------------------------------------------------------
   16. Criação da Tabela (caso necessário)
------------------------------------------------------------------------- */
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

/* -------------------------------------------------------------------------
   17. Excluir Adesivos Editados da Lista de Anexos na Biblioteca de Mídia
------------------------------------------------------------------------- */
// Aplicado na query AJAX (usada pelo modal de mídia)
function exclude_edited_attachments($query)
{
    if (isset($query['post_mime_type']) && $query['post_mime_type'] === 'image/svg+xml') {
        $meta_query = isset($query['meta_query']) ? $query['meta_query'] : array();
        $meta_query[] = array(
            'key'     => '_adesivo_editado',
            'compare' => 'NOT EXISTS'
        );
        $query['meta_query'] = $meta_query;
    }
    return $query;
}
add_filter('ajax_query_attachments_args', 'exclude_edited_attachments');

// Aplicado também em queries do admin (caso use pre_get_posts)
function exclude_edited_attachments_pre_get_posts($query)
{
    if (is_admin() && $query->is_main_query() && $query->get('post_type') === 'attachment') {
        $meta_query = $query->get('meta_query');
        if (!is_array($meta_query)) {
            $meta_query = array();
        }
        $meta_query[] = array(
            'key'     => '_adesivo_editado',
            'compare' => 'NOT EXISTS'
        );
        $query->set('meta_query', $meta_query);
    }
}
add_action('pre_get_posts', 'exclude_edited_attachments_pre_get_posts');
