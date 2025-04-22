<?php

// Processar renomeação do adesivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_sticker'])) {
    if (
        !isset($_POST['rename_sticker_nonce'])
        || !wp_verify_nonce($_POST['rename_sticker_nonce'], 'rename_sticker_nonce_action')
    ) {
        echo '<p class="alert alert-danger">Nonce inválido!</p>';
    } else {
        $attachment_id = intval($_POST['attachment_id']);
        $new_title     = sanitize_text_field($_POST['sticker_name']);
        // Atualiza title e slug (post_name)
        wp_update_post(array(
            'ID'         => $attachment_id,
            'post_title' => $new_title,
            'post_name'  => sanitize_title($new_title),
        ));
        echo '<div class="notice notice-success"><p>Nome do adesivo atualizado para “' . esc_html($new_title) . '” com sucesso!</p></div>';
    }
}


// Processa o salvamento do preço do adesivo para cada item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sticker_price'])) {
    if (!isset($_POST['sticker_price_nonce']) || !wp_verify_nonce($_POST['sticker_price_nonce'], 'save_sticker_price_nonce')) {
        echo '<p class="alert alert-danger">Nonce inválido!</p>';
    } else {
        $sticker_id = intval($_POST['sticker_id']);
        $sticker_price = floatval($_POST['sticker_price']);
        update_post_meta($sticker_id, '_sticker_price', $sticker_price);
        echo '<div class="notice notice-success"><p>Preço do adesivo atualizado com sucesso!</p></div>';
        // echo get_post_meta($sticker_id, '_sticker_price', true);
    }
}


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
echo '
<table style="width: 100%; border-collapse: collapse; margin-bottom: .3rem">
    <tr style="background-color: #eee; border-bottom: 2px solid #dee2e6;">
        <!-- Item 1 -->
        <td style="padding: 10px; text-align: center;">
            <a href="#form-table" style="text-decoration: none; color: #343a40; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-file" style="margin-right: 8px;"></i>
                <span>Gerenciar Adesivos</span>
            </a>
        </td>
        <!-- Item 2 -->
        <td style="padding: 10px; text-align: center;">
           
            </a>
        </td>
        <!-- Item 3 -->
        <td style="padding: 10px; text-align: center;">
            <a href="#" onclick="copiarTag(event)" style="text-decoration: none; color: #343a40; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-copy" style="margin-right: 8px;"></i>
                <span>Copiar Tag</span>
            </a>
        </td>
    </tr>
</table>
<!-- Input invisível para copiar a tag -->
<input type="text" id="tagInput" value="[customizador_adesivo_page]" style="position: absolute; left: -9999px;">
';

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
    'meta_query'     => array(
        array(
            'key'     => '_adesivo_editado',
            'compare' => 'NOT EXISTS'
        )
    )
);

// Adiciona o filtro de busca, se aplicável
if (!empty($_GET['search_sticker'])) {
    $search_term = sanitize_text_field($_GET['search_sticker']);
    $args_attachments['s'] = $search_term; // Adiciona a busca por título
}

$query = new WP_Query($args_attachments);

// Inicializa o array para armazenar os preços dos adesivos
$sticker_prices = array();

// Renderiza a tabela de adesivos
if ($query->have_posts()) {
    echo '<table id="form-table" style="" class="table table-dark">';
    echo '<thead><tr><th>Visualização</th><th>Nome do Adesivo</th><th>Preço</th><th>Gerenciar</th></tr></thead>';
    echo '<tbody>';
    while ($query->have_posts()) {
        $query->the_post();
        $attachment_id = get_the_ID();
        $url_svg = wp_get_attachment_url($attachment_id);
        $nome_arquivo = basename($url_svg);

        // Armazena o preço do adesivo (se existir) para envio ao frontend
        $sticker_prices[$attachment_id] = get_post_meta($attachment_id, '_sticker_price', true);

        echo '<tr>';
        echo '<td style="width: 50px;"><img src="' . esc_url($url_svg) . '" alt="' . esc_attr($nome_arquivo) . '" style="width: 80px; border-radius:.7rem; background-color:#eee; height: auto;"></td>';

        echo '<td>';
        // Campo de renomeação
        echo '<form method="post" style="display:inline-flex; align-items:center;">';
        wp_nonce_field('rename_sticker_nonce_action', 'rename_sticker_nonce');
        echo '<input type="hidden" name="attachment_id" value="' . esc_attr($attachment_id) . '">';
        // Usa o título atual do attachment
        $current_name = get_the_title($attachment_id);
        echo '<input type="text" name="sticker_name" value="' . esc_attr($current_name) . '" style="width:150px; margin-right:5px;">';
        echo '<button type="submit" name="rename_sticker" class="btn btn-sm btn-primary">Renomear</button>';
        echo '</form>';
        echo '</td>';



        // Campo para salvar o preço do adesivo
        echo '<td>';
        echo '<form method="post" style="display:inline;">';
        wp_nonce_field('save_sticker_price_nonce', 'sticker_price_nonce');
        echo '<input type="hidden" name="sticker_id" value="' . esc_attr($attachment_id) . '">';
        $current_price = get_post_meta($attachment_id, '_sticker_price', true);
        echo '<input type="number" step="0.01" name="sticker_price" value="' . esc_attr($current_price) . '" style="width:80px;">';
        echo '<button type="submit" name="save_sticker_price" class="btn btn-success btn-sm" style="margin-left:5px;">Salvar Preço</button>';
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
    echo '<a style="color:rgba(0, 0, 0, 0.52); text-decoration:none;" href="https://evoludesign.com.br/"><p>Desenvolvido por <span style="font-weight:800;">Evo Design</span> </p></a>';

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




// Envio dos preços dos adesivos para o frontend
echo '<script>';
echo 'var stickerPrices = ' . json_encode($sticker_prices) . ';';
echo '</script>';

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



// Scripts
echo "
<script>
    jQuery(document).ready(function($) {
        $('a[href^=\"#\"]').on('click', function(e) {
            e.preventDefault();
            var target = this.hash;
            var $target = $(target);
            $('html, body').animate({
                scrollTop: $target.offset().top - 50 // Ajusta a posição para não cobrir o cabeçalho
            }, 800); // 800ms de animação
        });
    });
</script>
";

echo '
<script>
function copiarTag(event) {
    event.preventDefault(); // Prevenir comportamento padrão do link
    const tagInput = document.getElementById("tagInput");

    // Seleciona o valor do input escondido
    tagInput.select();
    tagInput.setSelectionRange(0, 99999); // Para compatibilidade com dispositivos móveis

    // Copia o texto para a área de transferência
    document.execCommand("copy");

    // Exibe uma mensagem de confirmação (opcional)
    alert("Tag copiada para a área de transferência!");
}
</script>';
