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
    wp_enqueue_script('person-plugin-js', plugins_url('assets/js/customizador.js', __FILE__), ['jquery'], null, true);

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

// Função para criar um produto temporário para o adesivo e adicioná-lo ao carrinho
function criar_produto_temporario_adesivo()
{
    // Verifica se os dados necessários foram enviados
    if (!isset($_POST['adesivo_url']) || !isset($_POST['price'])) {
        wp_send_json_error(['message' => 'Dados insuficientes.']);
        return;
    }

    // Recebe e sanitiza os dados
    $adesivo_url = esc_url_raw($_POST['adesivo_url']);
    $price       = floatval($_POST['price']);
    error_log("📌 Preço recebido no PHP: " . $price); // 🔍 Log para depuração


    // Cria um título único para o produto temporário
    $post_title = 'Adesivo Personalizado - ' . current_time('Y-m-d H:i:s');

    // Insere o produto (post) no banco
    $temp_product = array(
        'post_title'  => $post_title,
        'post_status' => 'publish',
        'post_type'   => 'product',
        'post_author' => get_current_user_id(),
    );
    $product_id = wp_insert_post($temp_product);
    if (!$product_id) {
        wp_send_json_error(['message' => 'Erro ao criar o produto temporário.']);
        return;
    }

    // Define o tipo de produto (simples)
    wp_set_object_terms($product_id, 'simple', 'product_type');

    // Atualiza os metadados de preço e outras configurações importantes
    update_post_meta($product_id, '_regular_price', $price);
    update_post_meta($product_id, '_price', $price);
    update_post_meta($product_id, '_virtual', 'yes');

    // Salva a URL do adesivo para uso posterior (por exemplo, para exibição no carrinho)
    update_post_meta($product_id, '_adesivo_url', $adesivo_url);

    // Dados adicionais para o item do carrinho
    $cart_item_data = array(
        'temp_product' => true, // flag para identificar que é temporário
        'adesivo_url'  => $adesivo_url,
        'custom_price' => $price,
        'unique_key'   => md5(microtime() . rand())
    );

    // Adiciona o produto criado ao carrinho
    $cart_item_key = WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);

    if ($cart_item_key) {
        wp_send_json_success([
            'message'  => 'Produto temporário criado e adicionado ao carrinho!',
            'cart_url' => wc_get_cart_url()
        ]);
    } else {
        wp_send_json_error(['message' => 'Erro ao adicionar o produto ao carrinho.']);
    }
}
add_action('wp_ajax_criar_produto_temporario_adesivo', 'criar_produto_temporario_adesivo');
add_action('wp_ajax_nopriv_criar_produto_temporario_adesivo', 'criar_produto_temporario_adesivo');


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
            // Deleta o produto permanentemente
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

// Dispara a função quando o evento do cron job rodar
add_action('evento_limpar_produtos_personalizados', 'limpar_produtos_personalizados_antigos');


function desativar_limpeza_produtos_personalizados()
{
    $timestamp = wp_next_scheduled('evento_limpar_produtos_personalizados');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'evento_limpar_produtos_personalizados');
    }
}
register_deactivation_hook(__FILE__, 'desativar_limpeza_produtos_personalizados');


// Adiciona o link do adesivo (imagem de alta qualidade) nos e-mails do pedido
function adicionar_link_adesivo_email($order, $sent_to_admin, $plain_text, $email)
{
    // Inicialmente, verifica se existe algum item com o meta "Imagem Personalizada"
    $existe_adesivo = false;
    foreach ($order->get_items() as $item_id => $item) {
        if ($item->get_meta('Imagem Personalizada')) {
            $existe_adesivo = true;
            break;
        }
    }

    if (!$existe_adesivo) {
        return;
    }

    // Exibe uma seção de cabeçalho para o adesivo personalizado
    echo '<h2>' . __('Adesivo Personalizado', 'woocommerce') . '</h2>';

    // Para cada item do pedido, exibe o link caso exista a URL do adesivo
    foreach ($order->get_items() as $item_id => $item) {
        $adesivo_url = $item->get_meta('Imagem Personalizada');
        if ($adesivo_url) {
            // Exibe o link com um texto amigável
            echo '<p>' . __('Download do Adesivo (alta qualidade):', 'woocommerce') . ' <a href="' . esc_url($adesivo_url) . '" target="_blank">' . __('Clique aqui para baixar', 'woocommerce') . '</a></p>';
            // Caso prefira exibir a própria imagem, descomente a linha abaixo:
            // echo '<p><img src="' . esc_url( $adesivo_url ) . '" alt="' . __('Adesivo Personalizado', 'woocommerce') . '" style="max-width:300px;height:auto;"></p>';
        }
    }
}
add_action('woocommerce_email_after_order_table', 'adicionar_link_adesivo_email', 10, 4);
