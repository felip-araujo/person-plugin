<!-- /var/www/html/wp-content/plugins/person-plugin/templates/editor-template.php -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>


<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center">
        <h2 class="text-left" id="titulo">Personalize seu Adesivo</h2>
        <button id="close-editor" class="btn btn-outline-secondary btn-sm d-flex align-items-center justify-content-center" style="border: none; background: transparent;">
            <i class="fas fa-times"></i>
        </button>
    </div>
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
            <input type="number" id="tamanho-fonte" class="form-range" min="10" max="100" value="25">
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
            <input type="range" id="rotacao-texto" class="form-range" min="-180" max="180" step="0.1" value="0" style="width: 100%;">
            <input type="number" id="rotacao-texto-valor" class="form-control mt-1" min="-180" max="180" step="0.4" value="0">
        </div>

        <div class="col-md-3 mb-3 d-flex align-items-end">
            <button id="adicionar-texto-botao" class="btn btn-primary w-100">Adicionar Texto</button>
        </div>
    </div>

    <div class="text-center mt-4">
        <div class="d-flex justify-content-center gap-2 mb-4">
            <button id="salvar-adesivo-botao" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#salvarAdesivoModal">Salvar Adesivo</button>
            <button id="salvar-modelo-botao" class="btn btn-primary">Salvar Modelo</button>
            <button id="undo-button" class="btn btn-secondary">Desfazer</button>
            <button id="redo-button" class="btn btn-secondary">Refazer</button>
            <button id="zoom-in" class="btn btn-secondary">+</button>
            <button id="zoom-out" class="btn btn-secondary">-</button>
            <button id="reset-zoom" class="btn btn-secondary">Resetar Zoom</button>
        </div>
        <div class="d-flex justify-content-center align-items-center">
            <div id="adesivo-canvas" class="border bg-white" style="width: 100%; max-width: 800px; height: auto; aspect-ratio: 16/9; overflow: hidden; position: relative;"></div>
        </div>
    </div>

    <script src="/assets/css/customizador.css"></script>
    <!-- Modal para coletar Nome e Email -->
    <div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div style="border-radius: .5rem;" class="modal-header">
                    <h5 class="modal-title" id="infoModalLabel">Informações do Usuário</h5>
                    <button style="border: none;" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <form id="userInfoForm">
                        <div class="form-group">
                            <label for="userName">Nome:</label>
                            <input type="text" class="form-control" id="userName" required>
                        </div>
                        <div class="form-group">
                            <label for="userEmail">Email:</label>
                            <input type="email" class="form-control" id="userEmail" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Enviar Adesivo</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="salvarAdesivoModal" tabindex="-1" aria-labelledby="salvarAdesivoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="salvarAdesivoModalLabel">Salvar Adesivo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form id="salvarAdesivoForm">
                    <div class="mb-3">
                        <div id="mensagem" class="alert d-none" role="alert"></div>
                        <label for="nome" class="form-label">Nome Completo:</label>
                        <input type="text" class="form-control" id="nome" placeholder="Digite seu nome" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail:</label>
                        <input type="email" class="form-control" id="email" placeholder="Digite seu e-mail" required>
                    </div>
                    <div class="mb-3">
                        <label for="telefone" class="form-label">Telefone (opcional):</label>
                        <input type="text" class="form-control" id="telefone" placeholder="Digite seu telefone">
                    </div>
                    <div class="mb-3">
                        <label for="material" class="form-label">Material:</label>
                        <select class="form-select" id="material">
                            <option value="Vinil Brilhante">Vinil Brilhante</option>
                            <option value="Vinil Fosco">Vinil Fosco</option>
                            <option value="Transparente">Transparente</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="quantidade" class="form-label">Quantidade:</label>
                        <input type="number" class="form-control" id="quantidade" min="1" value="1">
                    </div>
                    <div class="mb-3">
                        <label for="texto_instrucoes" class="form-label">Texto Adicional ou Instruções:</label>
                        <textarea class="form-control" id="texto_instrucoes" placeholder="Adicione instruções ou informações adicionais"></textarea>
                    </div>

                    <div id="loadingText" style="display: none; color:#333;">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Confirmar e Salvar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('close-editor').addEventListener('click', function() {
        document.querySelector('.container').style.display = 'none';
    });
</script>