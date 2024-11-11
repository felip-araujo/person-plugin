<div class="container mt-5">
    <h2 class="text-center">Personalize seu Adesivo</h2>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label for="cor" class="form-label">Cor:</label>
            <input type="color" id="cor" class="form-control">
        </div>
        <div class="col-md-4 mb-3">
            <label for="tamanho-fonte" class="form-label">Tamanho da Fonte:</label>
            <input type="range" id="tamanho-fonte" class="form-range" min="10" max="100" value="16">
        </div>
        <div class="col-md-4 mb-3">
            <label for="fontPicker" class="form-label">Fonte:</label>
            <select id="fontPicker" class="form-control">
                <option value="Arial">Arial</option>
                <option value="Times New Roman">Times New Roman</option>
                <option value="Courier New">Courier New</option>
                <option value="Georgia">Georgia</option>
                <option value="Verdana">Verdana</option>
            </select>
        </div>
        <div class="col-md-12 mb-3">
            <label for="texto" class="form-label">Texto do Adesivo:</label>
            <input type="text" id="texto" class="form-control" placeholder="Digite o texto do adesivo">
        </div>
    </div>

    <label for="sticker-select">Escolha um modelo de adesivo:</label>
    <select id="sticker-select">
        <option value="modelo1.svg">Honda CG</option>
        <option value="Honda.svg">Honda CG 2</option>
        <option value="modelo3.svg">Modelo 3</option>
    </select>
    <button id="load-sticker">Carregar Adesivo</button>

    <div class="col-md-4 mb-3">
    <label for="layer-select" class="form-label">Escolha a Camada:</label>
    <select id="layer-select" class="form-control">
        <!-- As opções de camada serão adicionadas dinamicamente pelo JavaScript --> 
    </select>
</div>

    <div class="text-center mt-4">
        <h4>Pré-visualização:</h4>
        <canvas id="adesivo-canvas" width="1150" height="400" style="border: 1px solid #ccc;"></canvas>
    </div>
</div>
