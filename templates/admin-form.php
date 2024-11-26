<?php
echo '<form method="post" enctype="multipart/form-data">';
wp_nonce_field('upload_sticker_nonce', 'sticker_nonce'); // Gera o nonce corretamente
echo '<div class="form-group">';
echo '<label for="sticker">Selecione um Adesivo (Apenas SVG):</label>';
echo '<input type="file" name="sticker" id="sticker" class="form-control" accept=".svg" required>';
echo '</div>';
echo '<button type="submit" name="submit_sticker" class="btn btn-primary">Enviar Adesivo</button>';
echo '</form>';


echo '<h2>Adesivos Existentes</h2>';

$args = array(
    'post_type'      => 'attachment',
    'post_mime_type' => 'image/svg+xml',
    'post_status'    => 'inherit',
    'posts_per_page' => -1,
);

$attachments = get_posts($args);

if (!empty($attachments)) {
    echo '<table style="border-radius: .7rem" class="table table-dark">';
    echo '<thead><tr><th>Visualização</th><th>Nome do Adesivo</th><th>Gerenciar</th></tr></thead>';
    echo '<tbody>';
    foreach ($attachments as $attachment) {
        $url_svg = wp_get_attachment_url($attachment->ID);
        $nome_arquivo = basename($url_svg);

        echo '<tr>';
        echo '<td style="width: 50px;"><img src="' . esc_url($url_svg) . '" alt="' . esc_attr($nome_arquivo) . '" style="width: 80px; border-radius:.7rem; background-color:#eee; height: auto;"></td>';
        echo '<td>' . esc_html($nome_arquivo) . '</td>';

        // Botão de exclusão
        echo '<td>';
        echo '<form method="post" style="display:inline;">';
        wp_nonce_field('delete_attachment_nonce', 'delete_attachment_nonce_field');
        echo '<button type="submit" name="delete_attachment" value="' . esc_attr($attachment->ID) . '" class="btn btn-danger">Apagar</button>';
        echo '</form>';
        echo '</td>';

        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
} else {
    echo '<p>Nenhum adesivo encontrado.</p>';
}

if (isset($_POST['delete_attachment']) && isset($_POST['delete_attachment_nonce_field'])) {
    if (wp_verify_nonce($_POST['delete_attachment_nonce_field'], 'delete_attachment_nonce')) {
        $attachment_id = intval($_POST['delete_attachment']);
        if (wp_delete_attachment($attachment_id, true)) {
            echo '<p class="alert alert-success">Adesivo excluído com sucesso.</p>';
        } else {
            echo '<p class="alert alert-danger">Erro ao excluir o adesivo.</p>';
        }
    } else {
        echo '<p class="alert alert-danger">Ação não autorizada.</p>';
    }
}
