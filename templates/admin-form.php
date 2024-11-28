<?php
// Formulário de envio de adesivos e busca
echo '<div class="row align-items-center mb-3">';
echo '<div class="col-md-6">';
echo '<form method="post" enctype="multipart/form-data" class="d-flex align-items-center">';
wp_nonce_field('upload_sticker_nonce', 'sticker_nonce'); // Gera o nonce corretamente
echo '<div class="input-group">';
echo '<input type="file" name="sticker" id="sticker" class="form-control" accept=".svg" required>';
echo '<button style="margin-left: .2rem;" type="submit" name="submit_sticker" class="btn btn-primary">Enviar</button>';
echo '</div>'; // Fecha input-group
echo '</form>';
echo '</div>';

echo '<div class="col-md-6">';
echo '<form method="get" class="d-flex align-items-center">';
echo '<input type="hidden" name="page" value="plugin-adesivos">'; // Garante que a página seja a correta
echo '<div class="input-group">';
echo '<input type="text" name="search_sticker" class="form-control" placeholder="Busque um adesivo pelo nome..." value="' . (isset($_GET['search_sticker']) ? esc_attr($_GET['search_sticker']) : '') . '">';
echo '<button style="margin-left: .1rem;" type="submit" class="btn btn-secondary">';
echo '<i class="fas fa-search"></i>'; // Ícone de lupa
echo '</button>';
echo '</div>'; // Fecha input-group
echo '</form>';
echo '</div>';
echo '</div>'; // Fecha row

// Associar adesivos a produtos WooCommerce
echo '<h2>Gerenciar Adesivos</h2>';
$args_products = array(
    'post_type'      => 'product',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
);
$products = get_posts($args_products);

// Adesivos por página e paginação
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$posts_per_page = 4;
$args_attachments = array(
    'post_type'      => 'attachment',
    'post_mime_type' => 'image/svg+xml',
    'post_status'    => 'inherit',
    'posts_per_page' => $posts_per_page,
    'paged'          => $current_page,
);

// Adiciona o filtro de busca, se aplicável
if (!empty($_GET['search_sticker'])) {
    $search_term = sanitize_text_field($_GET['search_sticker']);
    $args_attachments['s'] = $search_term; // Adiciona a busca por título
}

$query = new WP_Query($args_attachments);

$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$posts_per_page = 4;

// Argumentos para consulta dos adesivos
$args = array(
    'post_type'      => 'attachment',
    'post_mime_type' => 'image/svg+xml',
    'post_status'    => 'inherit',
    'posts_per_page' => $posts_per_page,
    'paged'          => $current_page,
);

// Adiciona o filtro de busca, se aplicável
if (!empty($_GET['search_sticker'])) {
    $search_term = sanitize_text_field($_GET['search_sticker']);
    $args['s'] = $search_term; // Adiciona a busca por título
}

$query = new WP_Query($args);

// Renderiza a tabela de adesivos
if ($query->have_posts()) {
    echo '<table style="border-radius: .7rem" class="table table-dark">';
    echo '<thead><tr><th>Visualização</th><th>Nome do Adesivo</th><th>Associar Produto</th><th>Gerenciar</th></tr></thead>';
    echo '<tbody>';
    while ($query->have_posts()) {
        $query->the_post();
        $attachment_id = get_the_ID();
        $url_svg = wp_get_attachment_url($attachment_id);
        $nome_arquivo = basename($url_svg);

        echo '<tr>';
        echo '<td style="width: 50px;"><img src="' . esc_url($url_svg) . '" alt="' . esc_attr($nome_arquivo) . '" style="width: 80px; border-radius:.7rem; background-color:#eee; height: auto;"></td>';
        echo '<td>' . esc_html($nome_arquivo) . '</td>';

        // Campo de seleção de produto e botão de salvar
        echo '<td>';
        echo '<form method="post">';
        wp_nonce_field('associate_sticker_nonce', 'associate_nonce');
        echo '<input type="hidden" name="sticker_id" value="' . esc_attr($attachment_id) . '">';
        echo '<div class="input-group">';
        echo '<select name="product_id" class="form-control">';
        $products = get_posts(array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ));
        foreach ($products as $product) {
            echo '<option value="' . esc_attr($product->ID) . '">' . esc_html($product->post_title) . '</option>';
        }
        echo '</select>';
        echo '<button type="submit" name="save_association" class="btn btn-success" style="margin-left: 0.5rem;">Salvar</button>';
        echo '</div>'; // Fecha input-group
        echo '</form>';
        echo '</td>';

        // Botão de exclusão
        echo '<td>';
        echo '<form method="post" style="display:inline;">';
        wp_nonce_field('delete_attachment_nonce', 'delete_attachment_nonce_field');
        echo '<button type="submit" name="delete_attachment" value="' . esc_attr($attachment_id) . '" class="btn btn-danger">Apagar</button>';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';

    // Renderiza a paginação
    $total_pages = $query->max_num_pages;
    if ($total_pages > 1) {
        echo '<nav aria-label="Paginação">';
        echo '<ul class="pagination justify-content-center">';
        if ($current_page > 1) {
            echo '<li class="page-item"><a class="page-link" href="?page=plugin-adesivos&paged=' . ($current_page - 1) . '&search_sticker=' . esc_attr($_GET['search_sticker'] ?? '') . '">Anterior</a></li>';
        }
        for ($i = 1; $i <= $total_pages; $i++) {
            echo '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '"><a class="page-link" href="?page=plugin-adesivos&paged=' . $i . '&search_sticker=' . esc_attr($_GET['search_sticker'] ?? '') . '">' . $i . '</a></li>';
        }
        if ($current_page < $total_pages) {
            echo '<li class="page-item"><a class="page-link" href="?page=plugin-adesivos&paged=' . ($current_page + 1) . '&search_sticker=' . esc_attr($_GET['search_sticker'] ?? '') . '">Próximo</a></li>';
        }
        echo '</ul>';
        echo '</nav>';
    }

    wp_reset_postdata();
} else {
    echo '<p>Nenhum adesivo encontrado.</p>';
}

// Mensagem de sucesso ou erro no admin
if (isset($_GET['editor_habilitado'])) {
    if ($_GET['editor_habilitado'] === 'sucesso') {
        echo '<div class="notice notice-success is-dismissible"><p>O editor foi habilitado com sucesso para este produto!</p></div>';
    } elseif ($_GET['editor_habilitado'] === 'erro') {
        echo '<div class="notice notice-error is-dismissible"><p>Erro ao habilitar o editor. Tente novamente.</p></div>';
    }
}

// Processar habilitação do editor ao salvar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $produto_id = intval($_POST['product_id']);
    $adesivo_associado = intval($_POST['sticker_id']);

    if ($produto_id && $adesivo_associado) {
        update_post_meta($produto_id, '_associated_sticker', $adesivo_associado);
        update_post_meta($produto_id, '_editor_enabled', 1);
        wp_redirect(admin_url('admin.php?page=plugin-adesivos&editor_habilitado=sucesso'));
        exit;
    } else {
        wp_redirect(admin_url('admin.php?page=plugin-adesivos&editor_habilitado=erro'));
        exit;
    }
}


?>
