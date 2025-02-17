<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/d4755c66d3.js" crossorigin="anonymous"></script>

<style>
    /* Ajustes para tornar o layout responsivo */

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
        font-weight: 400 ;
        text-align: left;
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

        #close-editor {
            margin-left: 35vh;
            background-color: red;

        }
    }



    @media (max-width: 568px) {
        .btn {
            width: auto;
        }

        button#limpar-tela {
            margin-bottom: 8px;
            /* Ajuste conforme necessário */
            background-color: red;
        }

        button#aumentar-png-botao {
            text-align: center;
        }

        #caixa-visualizacao {
            margin-top: 8px;
            /* Ajuste conforme necessário */
        }

    }
</style>

<div class="container-fluid" style="overflow-y: auto;">
    <div class="container mt-5">
        <!-- Titulo -->
        <div>
            <h2 id="titulo">Personalize seu Adesivo</h2>
        </div>
        <!-- Row para Divisao Lado a Lado -->
        <div class="row" id="abas">
            <div class="col-md-3 border-end" style="height: 100vh; overflow-y:auto;">
                <!-- Abas para alternar entre Texto e Camadas -->
                <ul class="nav nav-tabs">
                    <li class="nav-item col-6 p-0 text-center">
                        <a class="nav-link  text-dark active" id="tab-camadas" data-bs-toggle="tab" href="#camadas-tab-content">Camadas</a>
                    </li>
                    <li class="nav-item col-6 p-0 text-center">
                        <a class="nav-link text-light" id="tab-texto" data-bs-toggle="tab" href="#texto-tab-content">Texto</a>
                    </li>
                </ul>

                <!-- Conteúdo das Abas -->
                <div class="tab-content mt-3" id="abas-1">
                    <!-- Aba de Texto -->
                    <div class="tab-pane fade" id="texto-tab-content">
                        <div>
                            <label for="texto" class="form-label">Texto do Adesivo:</label>
                            <input type="text" id="texto" class="form-control" placeholder="Digite o texto do adesivo">

                            <label for="cor-texto" class="form-label mt-2">Cor do Texto:</label>
                            <input type="color" id="cor-texto" class="form-control" value="#000000">

                            <label for="tamanho-fonte" class="form-label mt-2">Tamanho da Fonte:</label>
                            <input type="number" id="tamanho-fonte" class="form-range form-control" min="10" max="100" value="25">

                            <label for="fontPicker" class="form-label mt-2">Fonte:</label>
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

                            <label for="rotacao-texto" class="form-label mt-2">Rotação do Texto:</label>
                            <input type="range" id="rotacao-texto" class="form-range" min="-180" max="180" step="0.1" value="0">
                            <input type="number" id="rotacao-texto-valor" class="form-control mt-1" min="-180" max="180" step="0.4" value="0">

                            <button id="adicionar-texto-botao" class="btn btn-primary w-100 mt-2">Adicionar Texto</button>
                        </div>
                    </div>

                    <!-- Aba de Camadas -->
                    <div class="tab-pane fade show active" id="camadas-tab-content">
                        <form>
                            <div class="mb-3">
                                <label for="layer-select" class="form-label">Escolha a Camada:</label>
                                <select id="layer-select" class="form-control"></select>

                                <label for="cor" class="form-label mt-2">Cor da Camada:</label>
                                <input type="color" id="cor" class="form-control">

                                <!-- Botão para inserir um PNG -->
                                <button id="inserir-imagem-botao" type="button" class="btn btn-primary w-100 mt-3">
                                    Inserir Imagem
                                </button>

                                <!-- Botões de aumentar/diminuir o PNG -->
                                <div class="d-flex gap-2 mt-3" id="imagem-botao-container">
                                    <button id="aumentar-png-botao" type="button" class="btn btn-secondary w-50">
                                        Aumentar
                                    </button>
                                    <button id="diminuir-png-botao" type="button" class="btn btn-secondary w-50">
                                        Diminuir
                                    </button>
                                </div>

                                <!-- Botão para limpar tela (remove TUDO) -->
                                <button id="limpar-tela-botao" type="button" class="btn btn-dark w-100 mt-2">
                                    Limpar Tela
                                </button>
                            </div>
                            <p id="alert" class="alert alert-info alert-dismissible fade show d-flex align-items-center" role="alert" style="font-size: .8rem; text-align: left;">
                                <button type="button" class="btn-close ms-auto  d-flex align-items-center" data-bs-dismiss="alert" aria-label="Close"></button>
                                <span> Ao utilizar nosso serviço de edição de adesivos, você concorda com os nossos <a href="https://palevioletred-parrot-583208.hostingersite.com/"> Termos de Uso. </a> </span>
                            </p>

                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-9 d-flex align-items-center justify-content-center">
                <div id="adesivo-canvas" style="width: 100%; height: 100%;" class="bg-white"></div>
            </div>
            <div class="d-flex justify-content-center gap-1 mb-4 mt-3">
                <button id="undo-button" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i></button>
                <button id="redo-button" class="btn btn-secondary"><i class="fa-solid fa-rotate-right"></i></button>
                <button id="zoom-in" class="btn btn-secondary"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                <button id="zoom-out" class="btn btn-secondary"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                <button id="reset-zoom" class="btn btn-secondary"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></button>
                <button id="salvar-modelo-botao" class="btn btn-primary">Salvar Modelo</button>
                <button type="submit" id="salvar-adesivo-botao" class="btn btn-success">Comprar Agora</button>
            </div>
        </div>
    </div>
</div>



<script>
    document.getElementById('close-editor').addEventListener('click', function() {
        document.querySelector('.container').style.display = 'none';
    });
</script>
<!-- Certifique-se de que o customizador.js está sendo carregado -->
