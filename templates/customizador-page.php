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
    .person-customizer header {
        position: absolute;
        z-index: 9999;
    }

    body {
        margin: 0;
        padding: 0;
        height: 100vh;
        display: flex;
        font-family: 'Montserrat', sans-serif;
    }

    .container-fluid {
        height: 100vh;
        display: contents;
        overflow: hidden;
    }

    .editor-container {
        margin-left: 300px;
        width: 20vh;
        height: 100%;
        transition: margin-left 0.3s ease-in-out;
    }

    .editor-container.open {
        margin-left: 0;
    }

    @media (max-width: 991px) {
        .editor-container {
            margin-left: 0;
        }

        .editor-container.open {
            margin-left: 300px;
        }
    }

    .accordion-button {
        font-family: 'Montserrat', sans-serif;
        font-size: 16px;
        font-weight: 600;
        text-transform: uppercase;
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
        font-family: 'Montserrat', sans-serif;
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
        margin-top: 5px;
        text-transform: uppercase;
        text-decoration: none;
        font-weight: 500;
    }

    .side-bar {
        width: 290px;
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        z-index: 1;
        transition: transform 0.3s ease-in-out;
    }

    .side-bar.hidden {
        transform: translateX(-100%);
    }

    @media (max-width: 768px) {
        .hamburger-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1050;
        }
    }

    @media (min-width: 992px) {
        .side-bar {
            transform: translateX(0);
        }

        .side-bar.hidden {
            transform: translateX(0);
        }

        .hamburger-btn {
            display: none;
        }
    }
</style>


<input type="hidden" id="adesivoUrl" name="adesivoUrl" value="<?php echo esc_url($url_do_adesivo); ?>">

<!-- Botão para iniciar o tour interativo -->


<div class="container-fluid">
    <div class="editor-container" id="editor-container">
        <?php echo person_plugin_display_customizer($selected_sticker); ?>
    </div>

    <!-- Sidebar de adesivos -->
    <button class="btn d-md-none hamburger-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i> Adesivos
    </button>

    <div class="p-4 bg-white ml-4 border-right shadow-sm overflow-auto side-bar d-md-block hidden" id="sidebar">
        <p class="alert alert-info text-center">Selecione um Adesivo</p>
        <button id="iniciar-tour" class="btn btn-info">Como editar?</button>
        <p></p>
        <input data-intro="Você também pode encontrar um adesivo digitando o nome na busca." data-step="2" type="text" id="searchSticker" class="form-control mb-3" placeholder="Buscar adesivo...">

        <div data-intro="Aqui você pode selecionar os adesivos disponíveis para customização." data-step="1" id="stickerAccordion" class="accordion">
            <?php
            // Agrupando adesivos por letra inicial do nome
            $args = array(
                'post_type'      => 'attachment',
                'post_mime_type' => 'image/svg+xml',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC'
            );
            $stickers = get_posts($args);

            $groups = [];
            if ($stickers) :
                foreach ($stickers as $sticker) {
                    $sticker_url = wp_get_attachment_url($sticker->ID);
                    $sticker_name = pathinfo($sticker_url, PATHINFO_FILENAME);
                    $letter = strtoupper(substr($sticker_name, 0, 1));

                    if (!isset($groups[$letter])) {
                        $groups[$letter] = [];
                    }

                    $groups[$letter][] = [
                        'url'   => $sticker_url,
                        'name'  => $sticker_name,
                        'price' => get_post_meta($sticker->ID, '_sticker_price', true)
                    ];
                }

                foreach ($groups as $letter => $sticker_group) : ?>

                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading-<?php echo $letter; ?>">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $letter; ?>" aria-expanded="true" aria-controls="collapse-<?php echo $letter; ?>">
                                <?php echo $letter; ?> (<?php echo count($sticker_group); ?> adesivos)
                            </button>
                        </h2>
                        <div id="collapse-<?php echo $letter; ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo $letter; ?>" data-bs-parent="#stickerAccordion">
                            <div class="accordion-body">
                                <div class="sticker-grid">
                                    <?php foreach ($sticker_group as $sticker) : ?>
                                        <a href="<?php echo esc_url(add_query_arg('sticker', urlencode($sticker['url']))); ?>" class="sticker-item text-center m-2" data-price="<?php echo esc_attr($sticker['price']); ?>">

                                            <img id="img-adesivo" src="<?php echo esc_url($sticker['url']); ?>" class="img-fluid rounded border p-2 bg-light" alt="<?php echo esc_attr($sticker['name']); ?>">
                                            <span class="d-block small mt-1 sticker-name"><?php echo esc_html($sticker['name']); ?></span>
                                            <?php if (!empty($sticker['price'])) : ?>
                                                <span class="d-block small sticker-price" id="preco-exibido"><?php echo wc_price($sticker['price']); ?></span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>

                                    <input type="hidden" id="stickerPrice" name="stickerPrice" value="0.1">
                                </div>
                            </div>
                        </div>
                    </div>
            <?php endforeach;
            else :
                echo '<p class="text-center text-muted">Nenhum adesivo encontrado.</p>';
            endif;
            ?>
        </div>
    </div>
</div>

<script>
    // Função para alternar a sidebar
    function toggleSidebar() {
        var sidebar = document.getElementById('sidebar');
        var editorContainer = document.getElementById('editor-container');
        sidebar.classList.toggle('hidden');
        editorContainer.classList.toggle('open');
    }

    // Filtro dos stickers via input de busca
    document.getElementById('searchSticker').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let stickers = document.querySelectorAll('.sticker-item');
        let groups = document.querySelectorAll('.accordion-item');

        stickers.forEach(function(sticker) {
            let name = sticker.querySelector('.sticker-name').textContent.toLowerCase();
            sticker.style.display = name.includes(filter) ? 'flex' : 'none';
        });

        groups.forEach(function(group) {
            let groupVisible = Array.from(group.querySelectorAll('.sticker-item'))
                .some(sticker => sticker.style.display === 'flex');
            group.style.display = groupVisible ? 'block' : 'none';
        });
    });

    // Ao pressionar o mouse (antes do clique completo) salva o preço no sessionStorage
    document.querySelectorAll('.sticker-item').forEach(item => {
        item.addEventListener('mousedown', function(e) {
            var price = this.getAttribute('data-price');
            console.log('Mousedown: armazenando preço no sessionStorage:', price);
            sessionStorage.setItem('stickerPrice', price);
        });
    });

    // Evento click para processar outras funções (como carregar o adesivo na página)
    document.querySelectorAll('.sticker-item').forEach(item => {
        item.addEventListener('click', function(e) {
            // Aqui você mantém o comportamento normal do link que carrega o adesivo na página
            console.log('Click: adesivo clicado.');
            // Outras funções já existentes podem ser adicionadas aqui se necessário.
        });
    });

    // Ao carregar a página, atualiza o input hidden com o preço armazenado, se houver
    document.addEventListener('DOMContentLoaded', function() {
        var storedPrice = sessionStorage.getItem('stickerPrice');
        if (storedPrice) {
            console.log('Atualizando input hidden com o preço armazenado:', storedPrice);
            document.getElementById('stickerPrice').value = storedPrice;
            // Se preferir, pode limpar o valor armazenado depois:
            // sessionStorage.removeItem('stickerPrice');
        }
    });


    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById("iniciar-tour").addEventListener("click", function() {
            introJs().start();
        });
    });






    document.addEventListener("DOMContentLoaded", function() {
        const startTourBtn = document.getElementById("startTour");

        if (startTourBtn) {
            startTourBtn.addEventListener("click", function() {
                sessionStorage.setItem("tourStep", "1"); // Começa o tour do passo 1
                iniciarTour();
            });
        }

        function iniciarTour() {
            const tour = new Shepherd.Tour({
                useModalOverlay: true,
                defaultStepOptions: {
                    classes: 'shadow-md bg-purple-dark',
                    scrollTo: true
                }
            });

            tour.addStep({
                title: "Passo 1",
                text: "Selecione um adesivo nesta área.",
                attachTo: {
                    element: ".side-bar",
                    on: "right"
                },
                buttons: [{
                    text: "Próximo",
                    action: function() {
                        sessionStorage.setItem("tourStep", "2"); // Define o próximo passo
                        window.location.href = "editor-template.php"; // Redireciona para a outra página
                    }
                }]
            });

            tour.start();
        }
    });
</script>



<!-- ponto de rollback -->