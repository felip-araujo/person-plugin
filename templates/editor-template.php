<!-- /var/www/html/wp-content/plugins/person-plugin/templates/editor-template.php -->

<div class="container mt-5">
    <h2 class="text-center">Personalize seu Adesivo</h2>
    <div class="row">
        <div class="col-md-3 mb-3">
            <label for="cor" class="form-label">Cor da Camada:</label>
            <input type="color" id="cor" class="form-control">
        </div>
        <div class="col-md-3 mb-3">
            <label for="layer-select" class="form-label">Escolha a Camada:</label>
            <select id="layer-select" class="form-control">
                <!-- As opções de camada serão adicionadas dinamicamente pelo JavaScript -->
            </select>
        </div>
        <div class="col-md-3 mb-3">
            <label for="cor-texto" class="form-label">Cor do Texto:</label>
            <input type="color" id="cor-texto" class="form-control" value="#000000">
        </div>
        <div class="col-md-3 mb-3">
            <label for="tamanho-fonte" class="form-label">Tamanho da Fonte:</label>
            <input type="range" id="tamanho-fonte" class="form-range" min="10" max="100" value="16">
        </div>
        <div class="col-md-3 mb-3">
            <label for="fontPicker" class="form-label">Fonte:</label>
            <select id="fontPicker" class="form-control">
                <option value="Arial">Arial</option>
                <option value="Times New Roman">Times New Roman</option>
                <option value="Courier New">Courier New</option>
                <option value="Georgia">Georgia</option>
                <option value="Verdana">Verdana</option>
                <!-- Adicione mais fontes se necessário -->
            </select>
        </div>
        <div class="col-md-3 mb-3">
            <label for="texto" class="form-label">Texto do Adesivo:</label>
            <input type="text" id="texto" class="form-control" placeholder="Digite o texto do adesivo">
        </div>
        <div class="col-md-3 mb-3">
            <label for="rotacao-texto" class="form-label">Rotação do Texto:</label>
            <input type="range" id="rotacao-texto" class="form-range" min="0" max="360" value="0">
        </div>
        <div class="col-md-3 mb-3 d-flex align-items-end">
            <button id="adicionar-texto-botao" class="btn btn-primary w-100">Adicionar Texto ao Adesivo</button>
        </div>
        <div class="col-md-12 mb-3">
            <button id="salvar-adesivo-botao" class="btn btn-success w-100">Salvar Adesivo</button>
        </div>
    </div>

    <div class="text-center mt-4">
        <h4 class="mb-3">Pré-visualização:</h4>
        <div id="zoom-controls" class="d-flex justify-content-center gap-2 mb-4">
            <button style="margin-right: .3rem;" id="zoom-in" class="btn btn-secondary btn-sm"> + </button>
            <button style="margin-right: .3rem;" id="zoom-out" class="btn btn-secondary btn-sm"> - </button>
            <button id="reset-zoom" class="btn btn-secondary btn-sm">Resetar Zoom</button>
        </div>
        <div class="d-flex justify-content-center align-items-center">
            <div id="adesivo-canvas" class="border bg-white" style="width: 100%; max-width: 800px; height: auto; aspect-ratio: 16/9; overflow: hidden; position: relative;"></div>
        </div>
    </div>


    <script src="/assets/css/customizador.css"></script>

</div>