<?php
if (!isset($url_do_adesivo)) {
    $url_do_adesivo = '';
}
?>


<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/d4755c66d3.js" crossorigin="anonymous"></script>
<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.js"></script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intro.js/4.2.2/introjs.min.css" integrity="sha512-... (hash)" crossorigin="anonymous" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/intro.js/4.2.2/intro.min.js" integrity="sha512-... (hash)" crossorigin="anonymous"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/konva@8.3.5/konva.min.js"></script>

<style>
    /* Ajustes para tornar o layout responsivo */

    .sp-container {
        position: absolute !important;
        z-index: 10000 !important;
    }

    body {
        font-family: 'Montserrat', sans-serif;
    }

    .container-fluid {
        padding: 0;
    }

    .container {
        max-width: 100%;
        padding: 15px;
    }

    #adesivo-canvas {
        background-color: #fff;
        width: 100%;
        height: 100%;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
    }

    /* Ajustar a altura do sidebar para mobile */
    .col-md-3 {
        max-height: 60vh;
        overflow-y: auto;
    }

    #titulo {
        font-size: 1.4rem;
        font-family: 'Montserrat', sans-serif;
        font-weight: 400;
        text-align: left;
    }

    .nav-tabs .nav-link {
        background-color: #f8f9fa;
        /* Cor de fundo das abas inativas */
        color: #212529;
        /* Cor do texto das abas inativas */
        border: 1px solid #dee2e6;
        /* Borda sutil */
    }

    .nav-tabs .nav-link.active {
        background-color: #ffffff;
        /* Fundo branco para a aba ativa */
        color: #000000;
        /* Texto preto na aba ativa */
        font-weight: bold;
        /* Deixa a aba ativa mais destacada */
        border-bottom: 2px solid #007bff;
        /* Realce com uma borda azul */
    }

    #modal-terms {
        color: #000000;
    }

    #modal-terms h5 {
        font-family: 'Montserrat', sans-serif;
        color: #000000;
    }

    /* Responsividade para mobile */
    @media (max-width: 768px) {

        #alert {
            visibility: hidden;
        }

        body {
            overflow-y: auto;
        }

        .col-md-3 {
            width: 100%;
            max-height: 60vh;
            overflow-y: auto;
            padding: 0;
        }

        .col-md-9 {
            width: 100%;
            margin-top: 20px;
        }

        .d-flex {
            flex-direction: column;
        }

        .btn {
            width: 100%;
            margin-bottom: 10px;
        }

        #titulo {
            font-size: 1.4rem;
            font-family: 'Montserrat', sans-serif;
            text-align: center;
        }

        /* Ajustar para tela pequena */
        .tab-content {
            padding-left: 0;
            padding-right: 0;
        }

        /* Ajustar espaçamento para os botões no mobile */
        .d-flex button {
            margin-bottom: 5px;
        }

        #abas-1 {
            padding: 1.5rem;
        }

        #imagem-botao-container {
            flex-direction: initial;
        }


    }

    @media (max-width: 568px) {
        .btn {
            width: auto;
        }

        button#limpar-tela {
            margin-bottom: 8px;
            background-color: red;
        }

        button#aumentar-png-botao {
            text-align: center;
        }

        #caixa-visualizacao {
            margin-top: 8px;
        }
    }
</style>

<?php
$sticker_id = isset($_GET['sticker_id']) ? intval($_GET['sticker_id']) : 0;
?>



<!-- Campos ocultos para armazenar o preço e URL do adesivo -->
<input type="hidden" id="stickerPrice" name="stickerPrice" value="0">
<input type="hidden" id="adesivoUrl" name="adesivoUrl" value="<?php echo esc_url($url_do_adesivo); ?>">

<div class="container-fluid" style="overflow-y: auto;">
    <div class="container mt-5">
        <!-- Título com tour -->
        <div>
            <p></p>
        </div>
        <!-- Row para divisão lado a lado -->
        <div class="row" id="abas">
            <div class="col-md-3 border-end" style="height: 100vh; overflow-y:auto;">
                <!-- Abas para alternar entre Texto e Camadas -->
                <ul class="nav nav-tabs">
                    <li class="nav-item col-6 p-0 text-center">
                        <a data-intro="Após selecionar o adesivo, as camadas de cores serão exibidas. Basta clicar em uma cor e personalizá-la como desejar." data-step="4" class="nav-link text-dark active" id="tab-camadas" data-bs-toggle="tab" href="#camadas-tab-content">Camadas</a>
                    </li>
                    <li class="nav-item col-6 p-0 text-center">
                        <a data-intro="Aqui você pode inserir um texto no seu adesivo, clique na aba para ver as opções de texto." data-step="8" class="nav-link text-dark" id="tab-texto" data-bs-toggle="tab" href="#texto-tab-content">Texto</a>
                    </li>
                </ul>

                <!-- Conteúdo das Abas -->
                <div class="tab-content mt-3" id="abas-1">
                    <!-- Aba de Texto -->
                    <div class="tab-pane fade" id="texto-tab-content">
                        <div>
                            <label for="texto" class="form-label">Texto do Adesivo:</label>
                            <input data-intro="Digite o texto do adesivo." data-step="9" type="text" id="texto" class="form-control" placeholder="Digite o texto do adesivo">
                            <label data-intro="Altere a cor do adesivo." data-step="10" for="cor-texto" class="form-label mt-2">Cor do Texto:</label>
                            <input type="color" id="cor-texto" class="form-control" value="#000000">
                            <!-- <label data-intro="Ajuste o tamanho da fonte." data-step="11" for="tamanho-fonte" class="form-label mt-2">Tamanho da Fonte:</label> -->
                            <input type="hidden" id="tamanho-fonte">
                            <!-- <input style="visibility:hidden;" type="number" id="tamanho-fonte" class="form-range form-control" min="10" max="100" value="25"> -->
                            <label data-intro="Selecione a fonte desejada." data-step="12" for="fontPicker" class="form-label mt-2">Fonte:</label>
                            <select id="fontPicker" class="form-control">
                                <option value="Arial">Arial</option>
                                <option value="Times New Roman">Times New Roman</option>
                                <option value="Courier New">Courier New</option>
                                <option value="Georgia">Georgia</option>
                                <option value="Verdana">Verdana</option>
                                <option value="Roboto">Roboto</option>
                                <option value="Smooch Sans">Smooch Sans</option>
                                <option value="Poppins">Poppins</option>
                                <option value="Montserrat">Montserrat</option>
                                <option value="Ubuntu">Ubuntu</option>
                                <option value="Gabriola">Gabriola</option>
                                <option value="Lato">Lato</option>
                                <option value="Oswald">Oswald</option>
                                <option value="Smooch">Smooch</option>
                            </select>
                            <!-- <label data-intro="Ajuste a rotação do texto" data-step="13" for="rotacao-texto" class="form-label mt-2">Rotação do Texto:</label> -->
                            <input type="hidden" id="rotacao-texto">
                            <!-- <input style="visibility:hidden;" type="range" id="rotacao-texto" class="form-range" min="-180" max="180" step="0.1" value="0">
                            <input type="number" id="rotacao-texto-valor" class="form-control mt-1" min="-180" max="180" step="0.4" value="0"> -->
                            <button data-intro="Adcione o texto definitivo, após isso você consegue adicionar um novo texto." data-step="14" id="adicionar-texto-botao" class="btn btn-primary w-100 mt-2">Adicionar Texto</button>
                        </div>
                    </div>

                    <!-- Aba de Camadas -->
                    <div class="tab-pane fade show active" id="camadas-tab-content">
                        <form>
                            <div class="mb-3">
                                <div id="layer-colors-container" style="display: flex; flex-wrap: wrap;">



                                    <!-- <a href="" id="cor"></a> -->
                                </div>
                                <!-- <label for="gradient-color">Editar Gradiente:</label>
                                <input type="color" id="gradient-color" value="#ff0000"> -->
                                <!-- Botão para inserir um PNG -->
                                <button data-intro="Para enviar ou adicionar sua própria imagem, clique neste botão e selecione um arquivo do seu dispositivo." data-step="5" id="inserir-imagem-botao" type="button" class="btn btn-primary w-100 mt-3">Enviar Imagem</button>
                                <!-- Botões de aumentar/diminuir o PNG -->
                                <div class="d-flex gap-2 mt-3" id="imagem-botao-container" data-intro="Aqui você pode aumentar ou diminuir o tamanho da imagem." data-step="6">
                                    <button id="aumentar-png-botao" type="button" class="btn btn-secondary w-50">Aumentar</button>
                                    <button id="diminuir-png-botao" type="button" class="btn btn-secondary w-50">Diminuir</button>
                                </div>
                                <!-- Botão para limpar tela -->
                                <button id="limpar-tela-botao" type="button" class="btn btn-dark w-100 mt-2">Limpar Tela</button>
                            </div>
                            <p id="alert" class="alert alert-info alert-dismissible fade show d-flex align-items-center" role="alert" style="font-size: .8rem; text-align: left;">
                                <button type="button" class="btn-close ms-auto d-flex align-items-center" data-bs-dismiss="alert" aria-label="Close"></button>
                                <span>Ao utilizar nosso serviço de edição de adesivos, você concorda com os nossos <a href="https://decalquesautomotivos.com.br/termos-de-uso/">Termos de Uso.</a></span>
                                <input type="checkbox" name="aceito-termos" id="aceito-termos">
                            </p>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-9 d-flex align-items-center justify-content-center">
                <canvas id="adesivo-canvas"></canvas>
                <div id="layer-colors-container"></div>
                <!-- <div id="layer-colors-container"></div> -->
                <!-- <canvas> data-intro="Agora que você selecionou seu adesivo, é aqui que você pode personalizá-lo do jeito que quiser!" data-step="3" id="adesivo-canvas" style="width: 100%; height: 100%;" class="bg-white"> </canvas> -->
            </div>
            <div class="d-flex justify-content-center gap-1 mb-4 mt-3" data-intro="Aqui você pode realizar ações como voltar ou avançar edição, zomm e finalizar a compra " data-step="7">
                <button id="undo-button" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i></button>
                <button id="redo-button" class="btn btn-secondary"><i class="fa-solid fa-rotate-right"></i></button>
                <button id="zoom-in" class="btn btn-secondary"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                <button id="zoom-out" class="btn btn-secondary"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                <button id="reset-zoom" class="btn btn-secondary"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></button>
                <button type="submit" id="salvar-adesivo-botao" class="btn btn-success">Comprar Agora</button>
            </div>
        </div>
    </div>
</div>


<!-- Modal de Termos de Uso -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel" style=" font-family: 'Montserrat', sans-serif;">Termos de Uso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="modal-terms">
                    <h5>Aceitação dos Termos</h5>
                    <p>
                        Ao utilizar o editor e demais funcionalidades deste site, você declara ter lido, compreendido e aceitado integralmente estes Termos de Uso. Se você não concordar com qualquer parte destes termos, solicitamos que não utilize os serviços aqui oferecidos.
                    </p>

                    <h5>Responsabilidade pelo Conteúdo e Edições</h5>
                    <p>
                        O editor disponibilizado neste site é uma ferramenta para a criação e edição de conteúdos. Todas as criações, edições e alterações realizadas são de inteira responsabilidade do usuário. Você é o único responsável pelo conteúdo gerado, incluindo o uso de imagens, logos, marcas ou qualquer material que possa estar protegido por direitos autorais.
                    </p>

                    <h5>Uso de Marcas e Direitos Autorais</h5>
                    <p>
                        Caso você utilize marcas, logotipos ou qualquer conteúdo protegido por direitos autorais em suas criações, deverá garantir que possui as autorizações necessárias para tanto. A gráfica e a equipe técnica deste site não se responsabilizam por eventuais violações de direitos autorais ou de propriedade intelectual decorrentes do uso indevido desses elementos.
                    </p>

                    <h5>Isenção de Responsabilidade</h5>
                    <p>
                        Este site e seus responsáveis não assumem qualquer responsabilidade por danos, prejuízos ou implicações legais que possam advir do uso do editor ou do conteúdo criado por meio dele. É de sua inteira responsabilidade assegurar que todas as edições e criações estejam em conformidade com as leis vigentes e com os direitos de terceiros.
                    </p>

                    <h5>Alterações nos Termos de Uso</h5>
                    <p>
                        Reservamo-nos o direito de, a qualquer momento, modificar estes Termos de Uso. As alterações entrarão em vigor imediatamente após sua publicação no site. Recomendamos que você verifique periodicamente esta página para estar sempre ciente das condições vigentes.
                    </p>

                    <h5>Legislação Aplicável e Foro</h5>
                    <p>
                        Estes Termos serão regidos e interpretados de acordo com a legislação vigente no país. Eventuais disputas ou controvérsias decorrentes do uso do site serão dirimidas no foro da comarca em que a empresa estiver sediada, salvo disposição em contrário por lei.
                    </p>
                </div>

                <div class="modal-footer">
                    <label for="acceptTermsBtn" class="mb-0" style="color: #000000;">
                        <input type="checkbox" name="acceptTermsBtn" id="acceptTermsBtn">
                        Concordo com os termos de uso
                    </label>
                </div>
            </div>
        </div>
    </div>




    <script type="module" src="customizador.js?ver=1740622541"></script>




    <script>
        // Tour com Intro.js
        document.addEventListener("DOMContentLoaded", function() {
            if (sessionStorage.getItem("tourStep") === "2") {
                iniciarTourEditor();
            }

            function iniciarTourEditor() {
                const tour = new Shepherd.Tour({
                    useModalOverlay: true,
                    defaultStepOptions: {
                        classes: 'shadow-md bg-purple-dark',
                        scrollTo: true
                    }
                });

                tour.addStep({
                    title: "Passo 2",
                    text: "Agora edite o adesivo nesta área.",
                    attachTo: {
                        element: "#editor-container",
                        on: "top"
                    },
                    buttons: [{
                        text: "Finalizar",
                        action: function() {
                            sessionStorage.removeItem("tourStep"); // Remove o status do tour
                            tour.complete();
                        }
                    }]
                });
                tour.start();
            }
        });
    </script>