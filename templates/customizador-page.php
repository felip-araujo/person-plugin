<?php
/**
 * Template para a página de customização.
 *
 * Exibe na lateral esquerda (sidebar) os adesivos e, na área principal, o editor.
 */

// Recupera o adesivo selecionado via URL (se houver)
$selected_sticker = '';
if ( isset( $_GET['sticker'] ) && !empty( $_GET['sticker'] ) ) {
    $selected_sticker = urldecode( $_GET['sticker'] );
}
?>
    
    <style>
        .container-fluid {
            height: 90vh; /* Ocupa toda a altura da tela */
            display: flex;
            overflow: hidden;
        }
        .editor-container {
            flex: 1;
            padding: 5px;
            
        }

        .side-bar{
          width: 75vh;
          position: sticky;
        }
        
        .sticker-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
        }
        .sticker-item {
            text-align: center;
            width: 100px;
            margin: 5px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #333;
        }
        .sticker-item img {
            width: 100px;
            height: 100px;
            border-radius: 4px;
            transition: transform 0.2s ease-in-out;
        }
        .sticker-item:hover img {
            transform: scale(1.1);
        }
        .sticker-name {
            font-size: 12px; 
            font-weight: 800px;
            margin-top: 5px; 
            color: red;
        }
    </style>
</head>
<body>

<div class="container-fluid vh-100 position-relative editor-container" >
    <!-- Área principal do editor -->
    <div class="flex-grow-1 p-4 ">
        <?php
            echo person_plugin_display_customizer($selected_sticker);
        ?>
    </div>
    <!-- Sidebar de adesivos -->
  
    <div class="p-4 bg-white ml-4 border-right shadow-sm overflow-auto side-bar">
        <p class="alert alert-info text-center">Selecione um Adesivo</p>
        <div class="d-flex flex-wrap justify-content-center">
            <?php
            $args = array(
                'post_type'      => 'attachment',
                'post_mime_type' => 'image/svg+xml',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC'
            );
            $stickers = get_posts($args);
            if ($stickers) :
                foreach ($stickers as $sticker) :
                    $sticker_url = wp_get_attachment_url($sticker->ID);
                    $link = esc_url(add_query_arg('sticker', urlencode($sticker_url)));
                    $sticker_name = pathinfo($sticker_url, PATHINFO_FILENAME);
            ?>
                    <a href="<?php echo $link; ?>" class="sticker-item text-center m-2">
                        <img src="<?php echo esc_url($sticker_url); ?>" class="img-fluid rounded border p-2 bg-light" alt="<?php echo esc_attr($sticker_name); ?>" style="width: 80px; height: 80px;">
                        <span class="d-block small mt-1 text-muted sticker-name"><?php echo esc_html($sticker_name); ?></span>
                    </a>
            <?php
                endforeach;
            else :
                echo '<p class="text-center text-muted">Nenhum adesivo encontrado.</p>';
            endif;
            ?>
        </div>
    </div>
</div>

</body>
</html>