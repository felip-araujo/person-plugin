// Variáveis globais de posição do adesivo na caixa de preview
var initialStageScale = { x: 1, y: 1 };
var initialStagePosition = { x: 0, y: 0 };
var initialStickerScale = { x: 1, y: 1 };
var initialStickerPosition = { x: 0, y: 0 };

// Variáveis globais para o histórico
var historyStates = [];
var historyIndex = -1;

document.addEventListener('DOMContentLoaded', function () {
    var stage = new Konva.Stage({
        container: 'adesivo-canvas',
        width: document.getElementById('adesivo-canvas').offsetWidth,
        height: document.getElementById('adesivo-canvas').offsetHeight,
        draggable: false, // Canvas fixo inicialmente
    });

    var layer = new Konva.Layer();
    stage.add(layer);
    
    var stickerGroup = null; // Grupo para os elementos do adesivo
    var tempTextObject = null; // Objeto temporário para manipulação de texto
    var scaleBy = 1.05; // Fator de zoom

    // Verifica se a URL do adesivo está disponível
    if (typeof pluginData !== 'undefined' && pluginData.stickerUrl) {
        carregarAdesivo(pluginData.stickerUrl);
    } else {
        console.error('pluginData ou stickerUrl não está definido.');
    }

    function getRandomColor() {
        return '#' + ('000000' + Math.floor(Math.random() * 16777215).toString(16)).slice(-6);
    }
    
    // Função para carregar o adesivo
    function carregarAdesivo(stickerUrl) {
        fetch(stickerUrl)
            .then((response) => {
                if (!response.ok) throw new Error('Erro ao carregar o adesivo: ' + response.status);
                return response.text();
            })
            .then((svgText) => {
                var parser = new DOMParser();
                var svgDoc = parser.parseFromString(svgText, 'image/svg+xml');

                layer.destroyChildren(); // Limpa os elementos anteriores

                stickerGroup = new Konva.Group({ draggable: false });
                Array.from(svgDoc.querySelectorAll('path, rect, circle, ellipse, polygon, polyline, line')).forEach(
                    (elem, index) => {
                        var pathData = elem.getAttribute('d') || '';
                        var fillColor = elem.getAttribute('fill');
                
                        if (!fillColor || fillColor === 'black' || fillColor === '#000' || fillColor === '#000000') {
                            // Atribuir uma cor aleatória
                            fillColor = getRandomColor();
                        }
                
                        if (pathData) {
                            var path = new Konva.Path({
                                data: pathData,
                                fill: fillColor,
                                draggable: false,
                                id: `layer-${index}`, // ID único para cada camada
                            });
                            stickerGroup.add(path);
                        }
                    }
                );

                layer.add(stickerGroup);
                ajustarTamanhoEPosicaoDoAdesivo();
                preencherSeletorDeCamadas();
                layer.draw();
                saveHistory(); // Salva o estado inicial
            })
            .catch((error) => console.error('Erro ao carregar o adesivo:', error));
    }

    // Função para salvar o estado atual
    function saveHistory() {
        // Limitar o tamanho do histórico, se necessário
        if (historyStates.length > 50) {
            historyStates.shift();
            historyIndex--;
        }
        // Remover estados futuros após desfazer
        if (historyIndex < historyStates.length - 1) {
            historyStates = historyStates.slice(0, historyIndex + 1);
        }

        // Salvar o estado atual do layer
        var json = layer.toJSON();
        historyStates.push(json);
        historyIndex++;
        // Atualizar estado dos botões de undo/redo
        updateUndoRedoButtons();
    }

    // Função para desfazer
    function undo() {
        if (historyIndex > 0) {
            historyIndex--;
            var previousState = historyStates[historyIndex];
            layer.destroyChildren();
            var restoredLayer = Konva.Node.create(previousState).getChildren();
            layer.add(...restoredLayer);
            layer.draw();

            // Atualizar referências
            stickerGroup = layer.findOne('Group');
            preencherSeletorDeCamadas();
            updateUndoRedoButtons();
        }
    }

    // Função para refazer
    function redo() {
        if (historyIndex < historyStates.length - 1) {
            historyIndex++;
            var nextState = historyStates[historyIndex];
            layer.destroyChildren();
            var restoredLayer = Konva.Node.create(nextState).getChildren();
            layer.add(...restoredLayer);
            layer.draw();

            // Atualizar referências
            stickerGroup = layer.findOne('Group');
            preencherSeletorDeCamadas();
            updateUndoRedoButtons();
        }
    }

    // Atualizar estado dos botões de undo/redo
    function updateUndoRedoButtons() {
        document.getElementById('undo-button').disabled = historyIndex <= 0;
        document.getElementById('redo-button').disabled = historyIndex >= historyStates.length - 1;
    }

    // Preenche o seletor de camadas dinamicamente
    function preencherSeletorDeCamadas() {
        var layerSelect = document.getElementById('layer-select');
        layerSelect.innerHTML = '';

        if (!stickerGroup) return;

        // Adiciona a opção "Todas as Camadas"
        var allLayersOption = document.createElement('option');
        allLayersOption.value = 'all';
        allLayersOption.textContent = 'Todas as Camadas';
        layerSelect.appendChild(allLayersOption);

        // Adiciona cada camada individualmente
        stickerGroup.getChildren().forEach((child, index) => {
            var option = document.createElement('option');
            option.value = child.id();
            option.textContent = `Camada ${index + 1}`;
            layerSelect.appendChild(option);
        });
    }

    // Evento para alterar a cor das camadas
    document.getElementById('cor').addEventListener('input', function (event) {
        var selectedColor = event.target.value;
        var layerSelect = document.getElementById('layer-select');
        var selectedLayerId = layerSelect.value;

        if (!stickerGroup) return;

        if (selectedLayerId === 'all') {
            stickerGroup.getChildren().forEach((layer) => layer.fill(selectedColor));
        } else {
            var selectedLayer = stickerGroup.findOne(`#${selectedLayerId}`);
            if (selectedLayer) selectedLayer.fill(selectedColor);
        }

        layer.draw();
    });

    // Salvar histórico quando a cor da camada for alterada
    document.getElementById('cor').addEventListener('change', saveHistory);

    document.getElementById('adicionar-texto-botao').addEventListener('click', function () {
        var textContent = document.getElementById('texto').value.trim();
        if (!textContent) return;
    
        // Criar um novo objeto de texto
        var newTextObject = new Konva.Text({
            x: stage.width() / 2,
            y: stage.height() / 2,
            text: textContent,
            fontSize: parseInt(document.getElementById('tamanho-fonte').value),
            fontFamily: document.getElementById('fontPicker').value,
            fill: document.getElementById('cor-texto').value,
            draggable: true,
            rotation: parseFloat(document.getElementById('rotacao-texto').value),
        });

        // Adicionar evento para salvar histórico após mover o texto
        newTextObject.on('dragend', function() {
            saveHistory();
        });

        layer.add(newTextObject);
        layer.draw();

        // Limpar a caixa de texto e o objeto temporário
        document.getElementById('texto').value = '';
        if (tempTextObject) {
            tempTextObject.destroy();
            tempTextObject = null;
        }

        saveHistory(); // Salva o estado após adicionar texto
    });

    // Função para ajustar o tamanho e posicionar o adesivo
    function ajustarTamanhoEPosicaoDoAdesivo() {
        if (!stickerGroup) return;
    
        // Resetar escala e posição do stage
        stage.scale({ x: 1, y: 1 });
        stage.position({ x: 0, y: 0 });
    
        var canvasWidth = stage.width();
        var canvasHeight = stage.height();
    
        var stickerRect = stickerGroup.getClientRect();
        var scaleX = canvasWidth / stickerRect.width;
        var scaleY = canvasHeight / stickerRect.height;
    
        var scale = Math.min(scaleX, scaleY);
        stickerGroup.scale({ x: scale, y: scale });
    
        var newStickerRect = stickerGroup.getClientRect();
        stickerGroup.position({
            x: (canvasWidth - newStickerRect.width) / 2 - newStickerRect.x,
            y: (canvasHeight - newStickerRect.height) / 2 - newStickerRect.y,
        });
    
        layer.draw();
        // Armazenar os valores iniciais
        initialStageScale = { x: stage.scaleX(), y: stage.scaleY() };
        initialStagePosition = { x: stage.x(), y: stage.y() };
        initialStickerScale = { x: stickerGroup.scaleX(), y: stickerGroup.scaleY() };
        initialStickerPosition = { x: stickerGroup.x(), y: stickerGroup.y() };
    }

    // Eventos para manipulação do texto
    document.getElementById('texto').addEventListener('input', atualizarTextoNoCanvas);
    document.getElementById('tamanho-fonte').addEventListener('input', atualizarTextoNoCanvas);
    document.getElementById('fontPicker').addEventListener('change', atualizarTextoNoCanvas);
    document.getElementById('cor-texto').addEventListener('input', atualizarTextoNoCanvas);
    document.getElementById('rotacao-texto').addEventListener('input', function (event) {
        document.getElementById('rotacao-texto-valor').value = event.target.value;
        atualizarTextoNoCanvas();
    });
    document.getElementById('rotacao-texto-valor').addEventListener('input', function (event) {
        document.getElementById('rotacao-texto').value = event.target.value;
        atualizarTextoNoCanvas();
    });

    function atualizarTextoNoCanvas() {
        var textContent = document.getElementById('texto').value;
        if (!textContent.trim()) {
            if (tempTextObject) {
                tempTextObject.destroy();
                tempTextObject = null;
                layer.draw();
            }
            return;
        }
    
        if (!tempTextObject) {
            tempTextObject = new Konva.Text({
                x: stage.width() / 2,
                y: stage.height() / 2,
                text: textContent,
                fontSize: parseInt(document.getElementById('tamanho-fonte').value),
                fontFamily: document.getElementById('fontPicker').value,
                fill: document.getElementById('cor-texto').value,
                draggable: true,
                rotation: parseFloat(document.getElementById('rotacao-texto').value),
            });

            // Adicionar evento para salvar histórico após mover o texto
            tempTextObject.on('dragend', function() {
                saveHistory();
            });

            layer.add(tempTextObject);
        } else {
            tempTextObject.text(textContent);
            tempTextObject.fontSize(parseInt(document.getElementById('tamanho-fonte').value));
            tempTextObject.fontFamily(document.getElementById('fontPicker').value);
            tempTextObject.fill(document.getElementById('cor-texto').value);
            tempTextObject.rotation(parseFloat(document.getElementById('rotacao-texto').value));
        }
    
        layer.draw();
    }

    // Salvar histórico quando os campos de texto perdem o foco
    document.getElementById('texto').addEventListener('blur', saveHistory);
    document.getElementById('tamanho-fonte').addEventListener('blur', saveHistory);
    document.getElementById('fontPicker').addEventListener('blur', saveHistory);
    document.getElementById('cor-texto').addEventListener('blur', saveHistory);
    document.getElementById('rotacao-texto').addEventListener('mouseup', saveHistory);
    document.getElementById('rotacao-texto-valor').addEventListener('blur', saveHistory);

    // Evento para salvar o modelo atual
    document.getElementById('salvar-modelo-botao').addEventListener('click', function () {
        if (tempTextObject) {
            tempTextObject.draggable(false);
            tempTextObject = null;
        }
        saveHistory();
    });

    // Adiciona eventos de zoom
    function aplicarZoom(direction) {
        var oldScale = stage.scaleX();
        var pointer = { x: stage.width() / 2, y: stage.height() / 2 };

        var mousePointTo = {
            x: (pointer.x - stage.x()) / oldScale,
            y: (pointer.y - stage.y()) / oldScale,
        };

        var newScale = direction === 'in' ? oldScale * scaleBy : oldScale / scaleBy;
        newScale = Math.max(0.5, Math.min(newScale, 3));

        stage.scale({ x: newScale, y: newScale });

        var newPos = {
            x: pointer.x - mousePointTo.x * newScale,
            y: pointer.y - mousePointTo.y * newScale,
        };

        stage.position(newPos);
        stage.batchDraw();
    }

    document.getElementById('zoom-in').addEventListener('click', function () {
        aplicarZoom('in');
    });

    document.getElementById('zoom-out').addEventListener('click', function () {
        aplicarZoom('out');
    });

    document.getElementById('reset-zoom').addEventListener('click', function () {
        // Resetar escala e posição do stage
        stage.scale(initialStageScale);
        stage.position(initialStagePosition);
    
        // Resetar escala e posição do stickerGroup
        if (stickerGroup) {
            stickerGroup.scale(initialStickerScale);
            stickerGroup.position(initialStickerPosition);
        }
    
        stage.batchDraw();
    });

    stage.on('wheel', function (e) {
        e.evt.preventDefault();
        var direction = e.evt.deltaY > 0 ? 'out' : 'in';
        aplicarZoom(direction);
    });

    // Eventos dos botões de desfazer e refazer
    document.getElementById('undo-button').addEventListener('click', function () {
        undo();
    });

    document.getElementById('redo-button').addEventListener('click', function () {
        redo();
    });

    // Atualizar estado inicial dos botões
    updateUndoRedoButtons();

    // Evento para salvar o adesivo
    document.getElementById("salvarAdesivoForm").addEventListener("submit", function (e) {
        e.preventDefault();

        // Captura os dados do formulário
        const nome = document.getElementById("nome").value;
        const email = document.getElementById("email").value;
        const telefone = document.getElementById("telefone").value;
        const material = document.getElementById("material").value;
        const quantidade = document.getElementById("quantidade").value;
        const texto_instrucoes = document.getElementById("texto_instrucoes").value;

        // Div de mensagens
        const mensagemDiv = document.getElementById("mensagem");

        // Envia os dados reais do modal via fetch
        fetch(pluginData.ajaxUrl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                action: "salvar_adesivo_cliente",
                nome,
                email,
                telefone,
                material,
                quantidade,
                texto_instrucoes,
            }).toString(),
        })
            .then(async (response) => {
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP status ${response.status}: ${errorText}`);
                }
                return response.json();
            })
            .then((data) => {
                // Sucesso
                mensagemDiv.classList.remove("d-none", "alert-danger");
                mensagemDiv.classList.add("alert-success");
                mensagemDiv.textContent = "Adesivo salvo com sucesso!";

                // Limpa os campos do formulário após o sucesso
                document.getElementById("salvarAdesivoForm").reset();

                // Fechar o modal após 3 segundos
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById("salvarAdesivoModal")).hide();
                    mensagemDiv.classList.add("d-none"); // Oculta a mensagem
                }, 3000);
            })
            .catch((error) => {
                // Erro
                mensagemDiv.classList.remove("d-none", "alert-success");
                mensagemDiv.classList.add("alert-danger");
                mensagemDiv.textContent = "Erro ao salvar adesivo. Tente novamente.";
                console.error("Erro no fetch:", error);
            });
    });
});
