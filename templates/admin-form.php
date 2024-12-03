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

        // Recuperar o produto associado a este adesivo
        $associated_product_id = null;
        $products_with_this_sticker = get_posts(array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_associated_sticker',
                    'value' => $attachment_id,
                    'compare' => '=',
                ),
            ),
        ));
        if (!empty($products_with_this_sticker)) {
            $associated_product_id = $products_with_this_sticker[0]->ID;
        }

        // Campo de seleção de produto e botão de salvar
        echo '<td>';
        echo '<form method="post">';
        wp_nonce_field('associate_sticker_nonce', 'associate_nonce');
        echo '<input type="hidden" name="sticker_id" value="' . esc_attr($attachment_id) . '">';
        echo '<div class="input-group">';
        echo '<select name="product_id" class="form-control">';
        echo '<option value="">Selecione um produto</option>';
        foreach ($products as $product) {
            $selected = ($product->ID == $associated_product_id) ? 'selected' : '';
            echo '<option value="' . esc_attr($product->ID) . '" ' . $selected . '>' . esc_html($product->post_title) . '</option>';
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_association'])) {
    if (!isset($_POST['associate_nonce']) || !wp_verify_nonce($_POST['associate_nonce'], 'associate_sticker_nonce')) {
        echo '<p class="alert alert-danger">Nonce inválido!</p>';
        return;
    }

    $produto_id = intval($_POST['product_id']);
    $adesivo_associado = intval($_POST['sticker_id']);

    if ($produto_id && $adesivo_associado) {
        // Associar o adesivo ao produto
        update_post_meta($produto_id, '_associated_sticker', $adesivo_associado);
        wp_redirect(admin_url('admin.php?page=plugin-adesivos&editor_habilitado=sucesso'));
        exit;
    } else {
        wp_redirect(admin_url('admin.php?page=plugin-adesivos&editor_habilitado=erro'));
        exit;
    }
}

// Processar exclusão de adesivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_attachment'])) {
    if (!isset($_POST['delete_attachment_nonce_field']) || !wp_verify_nonce($_POST['delete_attachment_nonce_field'], 'delete_attachment_nonce')) {
        echo '<p class="alert alert-danger">Nonce inválido!</p>';
        return;
    }

    $attachment_id_to_delete = intval($_POST['delete_attachment']);
    if ($attachment_id_to_delete) {
        // Excluir o adesivo
        wp_delete_attachment($attachment_id_to_delete, true);
        // Remover associações com produtos
        $products = get_posts(array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => '_associated_sticker',
                    'value'   => $attachment_id_to_delete,
                    'compare' => '=',
                ),
            ),
        ));
        foreach ($products as $product) {
            delete_post_meta($product->ID, '_associated_sticker');
        }
        wp_redirect(admin_url('admin.php?page=plugin-adesivos'));
        exit;
    }
}

// Seção de Configurações
echo '<h2>Configurações</h2>';

$recognize_sticker_setting = get_option('person_plugin_recognize_sticker', 'yes');
$admin_email = get_option('person_plugin_admin_email', '');
$sender_email = get_option('person_plugin_sender_email', '');
$sender_password = get_option('person_plugin_sender_password', '');

echo '<form method="post">';
wp_nonce_field('person_plugin_settings_nonce', 'person_plugin_nonce');
echo '<div class="form-group">';
echo '<label for="recognize_sticker">Ativar reconhecimento de adesivo pelo nome do produto:</label>';
echo '<select name="recognize_sticker" id="recognize_sticker" class="form-control">';
echo '<option value="yes"' . selected($recognize_sticker_setting, 'yes', false) . '>Sim</option>';
echo '<option value="no"' . selected($recognize_sticker_setting, 'no', false) . '>Não</option>';
echo '</select>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="admin_email">Email do Administrador:</label>';
echo '<input type="email" name="admin_email" id="admin_email" class="form-control" value="' . esc_attr($admin_email) . '">';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="sender_email">Email de Remetente:</label>';
echo '<input type="email" name="sender_email" id="sender_email" class="form-control" value="' . esc_attr($sender_email) . '">';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="sender_password">Senha do Email de Remetente:</label>';
echo '<input type="password" name="sender_password" id="sender_password" class="form-control" value="' . esc_attr($sender_password) . '">';
echo '</div>';

echo '<button type="submit" name="save_plugin_settings" class="btn btn-primary">Salvar Configurações</button>';
echo '</form>';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plugin_settings'])) {
    if (!isset($_POST['person_plugin_nonce']) || !wp_verify_nonce($_POST['person_plugin_nonce'], 'person_plugin_settings_nonce')) {
        echo '<p class="alert alert-danger">Nonce inválido!</p>';
    } else {
        $recognize_sticker = sanitize_text_field($_POST['recognize_sticker']);
        update_option('person_plugin_recognize_sticker', $recognize_sticker);

        $admin_email = sanitize_email($_POST['admin_email']);
        update_option('person_plugin_admin_email', $admin_email);

        $sender_email = sanitize_email($_POST['sender_email']);
        update_option('person_plugin_sender_email', $sender_email);

        $sender_password = sanitize_text_field($_POST['sender_password']);
        update_option('person_plugin_sender_password', $sender_password);

        echo '<p class="alert alert-success">Configurações salvas com sucesso!</p>';
    }
}
?>
