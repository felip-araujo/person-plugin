<?php
echo '<h2>Adesivos Existentes</h2>';
if (is_dir($plugin_sticker_dir)) {
    $arquivos_svg = glob($plugin_sticker_dir . '*.svg');

    if (!empty($arquivos_svg)) {
        echo '<form method="post" action="">'; // Adiciona o formulário
        echo '<table style="border-radius: .7rem" class="table table-dark">';
        echo '<thead><tr><th>Visualização</th><th>Nome do Adesivo</th><th>Associar</th><th>Gerenciar</th></tr></thead>';
        echo '<tbody>';
        foreach ($arquivos_svg as $arquivo) {
            $url_svg = $url_diretorio . basename($arquivo);
            $nome_arquivo = basename($arquivo); 

            // Lista de produtos
            $produtos = get_posts(array(
                'post_type' => 'product', // Tipo de post personalizado do WooCommerce
                'posts_per_page' => -1
            ));

            echo '<tr>';
            echo '<td style="width: 50px;"><img src="' . esc_url($url_svg) . '" alt="' . esc_attr($nome_arquivo) . '" style="width: 80px; border-radius:.7rem; background-color:#eee; height: auto;"></td>';
            echo '<td>' . esc_html($nome_arquivo) . '</td>';
            
            // Dropdown para selecionar produto
            echo '<td>';
            echo '<select name="produto_associado[' . esc_attr($nome_arquivo) . ']">';
            echo '<option value="">Selecione um Produto</option>';
            foreach ($produtos as $produto) {
                echo '<option value="' . $produto->ID . '">' . esc_html($produto->post_title) . '</option>';
            }
            echo '</select>';
            echo '</td>';
            
            // Botão de exclusão
            echo '<td>';
            echo '<button type="submit" name="excluir_adesivo" value="' . esc_attr($nome_arquivo) . '" class="btn btn-danger">Apagar</button>';
            echo '</td>';

            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '<button type="submit" name="salvar_associacoes" class="btn btn-primary">Salvar Associações</button>';
        echo '</form>';
    } else {
        echo '<p>Nenhum adesivo encontrado.</p>';
    }
} else {
    echo '<p>Pasta de adesivos não encontrada.</p>';
}

// Processar as ações do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Excluir adesivo
    if (isset($_POST['excluir_adesivo'])) {
        $adesivo = sanitize_text_field($_POST['excluir_adesivo']);
        $caminho_adesivo = $plugin_sticker_dir . $adesivo;
        if (file_exists($caminho_adesivo)) {
            unlink($caminho_adesivo); // Exclui o arquivo
            echo '<p>Adesivo "' . esc_html($adesivo) . '" excluído com sucesso.</p>';
        } else {
            echo '<p>Adesivo não encontrado.</p>';
        }
    }

    // Salvar associações
    if (isset($_POST['salvar_associacoes'])) {
        $associacoes = $_POST['produto_associado'];
        foreach ($associacoes as $adesivo => $produto_id) {
            if (!empty($produto_id)) {
                update_post_meta($produto_id, '_svg_file', esc_url($url_diretorio . $adesivo));
            }
        }
        echo '<p>Associações salvas com sucesso.</p>';
    }
}
