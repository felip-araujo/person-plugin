<?php
// Converte uma URL de uploads para o caminho físico
function url_to_path( $url ) {
    $upload_dir = wp_upload_dir();
    return str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
}

// Filtra os anexos de e-mail do WooCommerce
add_filter('woocommerce_email_attachments', 'attach_sticker_emails', 10, 3);
function attach_sticker_emails( $attachments, $email_id, $order ) {
    // Obtém o ID do produto configurado (produto personalizado)
    $produto_id = get_option('manual_product_id');
    
    // Loop pelos itens do pedido
    foreach ( $order->get_items() as $item_id => $item ) {
        $product = $item->get_product();
        if ( $product && $product->get_id() == $produto_id ) {
            // Tenta recuperar a URL da imagem personalizada salva no pedido.
            $sticker_url = $item->get_meta('Imagem Personalizada');
            if ( ! $sticker_url ) {
                continue;
            }
            // Converte a URL para o caminho físico do arquivo
            $sticker_path = url_to_path( $sticker_url );
            
            // Verifica se o arquivo existe
            if ( file_exists( $sticker_path ) ) {
                $attachments[] = $sticker_path;
            }
        }
    }
    return $attachments;
}
