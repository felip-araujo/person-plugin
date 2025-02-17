<?php

/**
 * Template para a página de customização.
 *
 * Exibe na lateral esquerda (sidebar) os adesivos e, na área principal, o editor.
 */

// Recupera o adesivo selecionado via URL (se houver)
$selected_sticker = '';
if (isset($_GET['sticker']) && !empty($_GET['sticker'])) {
    $selected_sticker = urldecode($_GET['sticker']);
}
?>

<style>
    /*desabilita a seleção no body*/
    body {
        -webkit-touch-callout: none;
        /* iOS Safari */
        -webkit-user-select: none;
        /* Chrome/Safari/Opera */
        -khtml-user-select: none;
        /* Konqueror */
        -moz-user-select: none;
        /* Firefox */
        -ms-user-select: none;
        /* Internet Explorer/Edge */
        user-select: none;
    }

    /*habilita a seleção nos campos editaveis*/
    input,
    textarea {
        -webkit-touch-callout: initial;
        /* iOS Safari */
        -webkit-user-select: text;
        /* Chrome/Safari/Opera */
        -khtml-user-select: text;
        /* Konqueror */
        -moz-user-select: text;
        /* Firefox */
        -ms-user-select: text;
        /* Internet Explorer/Edge */
        user-select: text;
    }

    /*habilita a seleção nos campos com o atributo contenteditable*/
    [contenteditable=true] {
        -webkit-touch-callout: initial;
        /* iOS Safari */
        -webkit-user-select: all;
        /* Chrome/Safari/Opera */
        -khtml-user-select: all;
        /* Konqueror */
        -moz-user-select: all;
        /* Firefox */
        -ms-user-select: all;
        /* Internet Explorer/Edge */
        user-select: all;
    }


    body {
        margin: 0;
        padding: 0;
        height: 100vh;
        display: flex;
        font-family: 'Montserrat', sans-serif;
        /* Flexbox para organizar o layout */
    }

    .container-fluid {
        height: 100vh;
        /* Ocupa toda a altura da tela */
        display: contents;
        overflow: hidden;
    }

    /* O editor agora ocupa o restante do espaço à direita da barra lateral */
    .editor-container {
        margin-left: 300px;
        width: 20vh;
        height: 100%;
        /* Espaço para a side-bar */
        transition: margin-left 0.3s ease-in-out;
        /* Suaviza a transição */
    }

    .editor-container.open {
        margin-left: 0;
        /* Quando a side-bar estiver visível no mobile, o editor ocupa o espaço */
    }

    @media (max-width: 991px) {
        .editor-container {
            margin-left: 0;
            /* No mobile, o editor ocupa toda a largura */
        }

        .editor-container.open {
            margin-left: 300px;
            /* Espaço para a side-bar quando estiver aberta */
        }
    }

    .sticker-grid {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
    }

    .sticker-item {
        text-align: center;
        width: 90px;
        margin: 4px;
        display: flex;
        flex-direction: column;
        align-items: center;
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
        font-size: 15px;
        font-weight: 800px;
        margin-top: 5px;
        text-transform: uppercase;
        text-decoration: none;
        font-weight: 500;

    }

    .side-bar {
        width: 290px;
        /* Largura fixa da barra lateral */
        position: fixed;
        /* Fixa a barra lateral à esquerda */
        top: 0;
        left: 0;
        height: 100vh;
        /* Altura da barra lateral igual à altura da tela */
        z-index: 1;
        /* Garante que a side-bar fique sobre outros elementos */
        transition: transform 0.3s ease-in-out;
    }

    /* Quando a side-bar estiver escondida */
    .side-bar.hidden {
        transform: translateX(-100%);
        /* Move a side-bar para fora da tela */
    }

    @media (max-width: 768px) {
        .hamburger-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1050;
        }
    }

    /* Exibe a side-bar no desktop */
    @media (min-width: 992px) {
        .side-bar {
            transform: translateX(0);
            /* Barra lateral visível no desktop */
        }

        .side-bar.hidden {
            transform: translateX(0);
            /* Impede que a side-bar seja escondida no desktop */
        }

        /* Garante que o botão de hambúrguer não apareça no desktop */
        .hamburger-btn {
            display: none;
        }
    }
</style>
</head>

<body>
    <div class="container-fluid" back>
        <div class="editor-container" id="editor-container">
            <?php
            echo person_plugin_display_customizer($selected_sticker);
            ?>
        </div>

        <!-- Sidebar de adesivos -->
        <button class="btn d-md-none hamburger-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i> Adesivos
        </button>

        <div class="p-4 bg-white ml-4 border-right shadow-sm overflow-auto side-bar d-md-block hidden" id="sidebar">
            <p class="alert alert-info text-center">Selecione um Adesivo</p>
            <input type="text" id="searchSticker" class="form-control mb-3" placeholder="Buscar adesivo...">
            <div class="d-flex flex-wrap justify-content-center">
                <?php
                // Recupera o ID do produto configurado na área administrativa
                $produto_id = get_option('manual_product_id');
                $product = wc_get_product($produto_id);

                // Se o produto for recuperado, obtemos os dados
                if ($product) {
                    $product_name  = $product->get_name();
                    $product_price = wc_price($product->get_price());
                } else {
                    // Caso o produto não esteja configurado ou não exista
                    $product_name  = '';
                    $product_price = '';
                }

                // Recupera todos os adesivos (imagens SVG)
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
                        $sticker_url  = wp_get_attachment_url($sticker->ID);
                        $link         = esc_url(add_query_arg('sticker', urlencode($sticker_url)));
                        $sticker_name = pathinfo($sticker_url, PATHINFO_FILENAME);
                ?>
                        <a href="<?php echo $link; ?>" class="sticker-item text-center m-2">
                            <img src="<?php echo esc_url($sticker_url); ?>" class="img-fluid rounded border p-2 bg-light" alt="<?php echo esc_attr($sticker_name); ?>" style="width: 80px; height: 80px;">
                            <span class="d-block small mt-1 sticker-name"><?php echo esc_html($sticker_name); ?></span>
                            <?php if (!empty($product_price)) : ?>
                                <span class="d-block small sticker-price"><?php echo $product_price; ?></span>
                            <?php endif; ?>
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
    </div>
</body>

</html>

<script>
    function toggleSidebar() {
        var sidebar = document.getElementById('sidebar');
        var editorContainer = document.getElementById('editor-container');
        sidebar.classList.toggle('hidden');
        editorContainer.classList.toggle('open');
    }

    // // funcao para bloquear botao direito no navegador 
    // if (document.addEventListener) {
    //     document.addEventListener("contextmenu", function(e) {
    //         e.preventDefault();
    //         return false;
    //     });
    // } else { //Versões antigas do IE 
    //     document.attachEvent("oncontextmenu", function(e) {
    //         e = e || window.event;
    //         e.returnValue = false;
    //         return false;
    //     });
    // }

    // funcao para bloquear ctrl+u e ctrl+s no naveegador
    if (document.addEventListener) {
        document.addEventListener("keydown", bloquearSource);
    } else { //Versões antigas do IE 
        document.attachEvent("onkeydown", bloquearSource);
    }

    function bloquearSource(e) {
        e = e || window.event;

        var code = e.which || e.keyCode;

        if (
            e.ctrlKey &&
            (code == 83 || code == 85) //83 = S, 85 = U 
        ) {
            if (e.preventDefault) {
                e.preventDefault();
            } else {
                e.returnValue = false;
            }

            return false;
        }
    }

    document.getElementById('searchSticker').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let stickers = document.querySelectorAll('.sticker-item');

        stickers.forEach(function(sticker) {
            let name = sticker.querySelector('.sticker-name').textContent.toLowerCase();
            if (name.includes(filter)) {
                sticker.style.display = 'flex';
            } else {
                sticker.style.display = 'none';
            }
        });
    });
</script>