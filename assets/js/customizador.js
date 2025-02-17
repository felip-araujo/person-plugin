// Variáveis globais de posição do adesivo na caixa de preview 
var initialStageScale = { x: 1, y: 1 };
var initialStagePosition = { x: 0, y: 0 };
var initialStickerScale = { x: 1, y: 1 };
var initialStickerPosition = { x: 0, y: 0 };

// Variável para armazenar a imagem PNG adicionada
var insertedImage = null;

// Variáveis globais para o histórico
var historyStates = [];
var historyIndex = -1;

// Objeto de texto temporário
var tempTextObject = null;

document.addEventListener('DOMContentLoaded', function () {
    // Verifica se o elemento 'adesivo-canvas' existe
    var canvasElement = document.getElementById('adesivo-canvas');
    if (!canvasElement) { 
        // Se não existir, encerra a execução deste script para evitar erros
        return;
    }

    var stage = new Konva.Stage({
        container: 'adesivo-canvas',
        width: canvasElement.offsetWidth,
        height: canvasElement.offsetHeight,
        draggable: false, // Canvas fixo inicialmente
    });

    var layer = new Konva.Layer();
    stage.add(layer);

    var stickerGroup = null; // Grupo para os elementos do adesivo (SVG)
    var scaleBy = 1.05; // Fator de zoom

    // Se pluginData.stickerUrl existir, carregar o adesivo
    if (typeof pluginData !== 'undefined' && pluginData.stickerUrl) {
        carregarAdesivo(pluginData.stickerUrl);
        //else {
        //    console.error('pluginData.stickerUrl não definido');
    }
    // Continuar execução mesmo que pluginData.stickerUrl não esteja definido

    // -------------------
    //  FUNÇÕES PRINCIPAIS
    // -------------------

    function getRandomColor() {
        return '#' + ('000000' + Math.floor(Math.random() * 16777215).toString(16)).slice(-6);
    }

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

                        if (!fillColor || fillColor === 'black' || fillColor === '#000') {
                            fillColor = getRandomColor();
                        }

                        if (pathData) {
                            var path = new Konva.Path({
                                data: pathData,
                                fill: fillColor,
                                draggable: false,
                                id: `layer-${index}`,
                            });
                            stickerGroup.add(path);
                        }
                    }
                );

                layer.add(stickerGroup);
                ajustarTamanhoEPosicaoDoAdesivo();
                preencherSeletorDeCamadas();
                layer.draw();
                saveHistory();
            })
            .catch((error) => console.error('Erro ao carregar o adesivo:', error));
    }

    function ajustarTamanhoEPosicaoDoAdesivo() {
        if (!stickerGroup) return;

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
        initialStageScale = { x: stage.scaleX(), y: stage.scaleY() };
        initialStagePosition = { x: stage.x(), y: stage.y() };
        initialStickerScale = { x: stickerGroup.scaleX(), y: stickerGroup.scaleY() };
        initialStickerPosition = { x: stickerGroup.x(), y: stickerGroup.y() };
    }

    function preencherSeletorDeCamadas() {
        var layerSelect = document.getElementById('layer-select');
        layerSelect.innerHTML = '';

        if (!stickerGroup) return;

        var allLayersOption = document.createElement('option');
        allLayersOption.value = 'all';
        allLayersOption.textContent = 'Todas as Camadas';
        layerSelect.appendChild(allLayersOption);

        stickerGroup.getChildren().forEach((child, index) => {
            var option = document.createElement('option');
            option.value = child.id();
            option.textContent = `Camada ${index + 1}`;
            layerSelect.appendChild(option);
        });
    }

    // Salvar histórico
    function saveHistory() {
        if (historyStates.length > 50) {
            historyStates.shift();
            historyIndex--;
        }
        if (historyIndex < historyStates.length - 1) {
            historyStates = historyStates.slice(0, historyIndex + 1);
        }
        var json = layer.toJSON();
        historyStates.push(json);
        historyIndex++;
        updateUndoRedoButtons();
    }

    function undo() {
        if (historyIndex > 0) {
            historyIndex--;
            var previousState = historyStates[historyIndex];
            layer.destroyChildren();
            var restoredLayer = Konva.Node.create(previousState).getChildren();
            layer.add(...restoredLayer);
            layer.draw();

            stickerGroup = layer.findOne('Group');
            preencherSeletorDeCamadas();
            updateUndoRedoButtons();
        }
    }

    function redo() {
        if (historyIndex < historyStates.length - 1) {
            historyIndex++;
            var nextState = historyStates[historyIndex];
            layer.destroyChildren();
            var restoredLayer = Konva.Node.create(nextState).getChildren();
            layer.add(...restoredLayer);
            layer.draw();

            stickerGroup = layer.findOne('Group');
            preencherSeletorDeCamadas();
            updateUndoRedoButtons();
        }
    }

    function updateUndoRedoButtons() {
        document.getElementById('undo-button').disabled = (historyIndex <= 0);
        document.getElementById('redo-button').disabled = (historyIndex >= historyStates.length - 1);
    }

    // -----------------
    //  EVENTOS PRINCIPAIS
    // -----------------

    // 1. Alterar cor das camadas
    document.getElementById('cor').addEventListener('input', function (event) {
        var selectedColor = event.target.value;
        var layerSelect = document.getElementById('layer-select');
        var selectedLayerId = layerSelect.value;

        if (!stickerGroup) return;

        if (selectedLayerId === 'all') {
            stickerGroup.getChildren().forEach((child) => child.fill(selectedColor));
        } else {
            var selectedLayer = stickerGroup.findOne(`#${selectedLayerId}`);
            if (selectedLayer) selectedLayer.fill(selectedColor);
        }
        layer.draw();
    });
    document.getElementById('cor').addEventListener('change', saveHistory);

    // 2. Adicionar texto
    document.getElementById('adicionar-texto-botao').addEventListener('click', function () {
        var textContent = document.getElementById('texto').value.trim();
        if (!textContent) return;

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

        newTextObject.on('dragend', saveHistory);
        layer.add(newTextObject);
        layer.draw();

        document.getElementById('texto').value = '';
        if (tempTextObject) {
            tempTextObject.destroy();
            tempTextObject = null;
        }
        saveHistory();
    });

    // 3. Atualização prévia do texto no canvas (se quiser “preview”)
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
            tempTextObject.on('dragend', saveHistory);
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

    // Salvar histórico quando campos de texto perdem o foco
    document.getElementById('texto').addEventListener('blur', saveHistory);
    document.getElementById('tamanho-fonte').addEventListener('blur', saveHistory);
    document.getElementById('fontPicker').addEventListener('blur', saveHistory);
    document.getElementById('cor-texto').addEventListener('blur', saveHistory);
    document.getElementById('rotacao-texto').addEventListener('mouseup', saveHistory);
    document.getElementById('rotacao-texto-valor').addEventListener('blur', saveHistory);

    // Botão Salvar Modelo
    document.getElementById('salvar-modelo-botao').addEventListener('click', function () {
        if (tempTextObject) {
            tempTextObject.draggable(false);
            tempTextObject = null;
        }
        saveHistory();
    });

    // Controles de Zoom
    function aplicarZoom(direction) {
        var oldScale = stage.scaleX();
        var pointer = { x: stage.width() / 2, y: stage.height() / 2 };

        var mousePointTo = {
            x: (pointer.x - stage.x()) / oldScale,
            y: (pointer.y - stage.y()) / oldScale,
        };

        var newScale = (direction === 'in') ? (oldScale * scaleBy) : (oldScale / scaleBy);
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
        stage.scale(initialStageScale);
        stage.position(initialStagePosition);
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

    // Undo/Redo
    document.getElementById('undo-button').addEventListener('click', undo);
    document.getElementById('redo-button').addEventListener('click', redo);
    updateUndoRedoButtons();

    // Inserir Imagem
    document.getElementById('inserir-imagem-botao').addEventListener('click', function () {
        inserirImagem();
    });
    function inserirImagem() {
        var fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.onchange = function (e) {
            var file = e.target.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function (evt) {
                var imageObj = new Image();
                imageObj.onload = function () {
                    insertedImage = new Konva.Image({
                        image: imageObj,
                        x: stage.width() / 2 - 50,
                        y: stage.height() / 2 - 50,
                        width: imageObj.width / 3,
                        height: imageObj.height / 3,
                        draggable: true
                    });
                    insertedImage.on('dragend', saveHistory);
                    layer.add(insertedImage);
                    layer.draw();
                    saveHistory();
                };
                imageObj.src = evt.target.result;
            };
            reader.readAsDataURL(file);
        };
        fileInput.click();
    }

    // Aumentar/Diminuir imagem
    document.getElementById('aumentar-png-botao').addEventListener('click', function () {
        if (insertedImage) {
            insertedImage.scaleX(insertedImage.scaleX() * 1.1);
            insertedImage.scaleY(insertedImage.scaleY() * 1.1);
            layer.draw();
            saveHistory();
        }
    });
    document.getElementById('diminuir-png-botao').addEventListener('click', function () {
        if (insertedImage) {
            insertedImage.scaleX(insertedImage.scaleX() * 0.9);
            insertedImage.scaleY(insertedImage.scaleY() * 0.9);
            layer.draw();
            saveHistory();
        }
    });

    // Limpar Tela (remove TUDO)
    document.getElementById('limpar-tela-botao').addEventListener('click', function () {
        layer.destroyChildren();  // Apaga todo o conteúdo
        stickerGroup = null;
        insertedImage = null;
        tempTextObject = null;
        layer.draw();
        saveHistory();
    });

    // Formulário Salvar Adesivo 01
    const loadingOverlay = document.createElement('div');
    loadingOverlay.style.position = 'fixed';
    loadingOverlay.style.top = 0;
    loadingOverlay.style.left = 0;
    loadingOverlay.style.width = '100vw';
    loadingOverlay.style.height = '100vh';
    loadingOverlay.style.background = 'rgba(0,0,0,0.5)';
    loadingOverlay.style.display = 'flex';
    loadingOverlay.style.justifyContent = 'center';
    loadingOverlay.style.alignItems = 'center';
    loadingOverlay.style.zIndex = '9999';
    loadingOverlay.style.visibility = 'hidden';

    const loadingText = document.createElement('div');
    loadingText.textContent = 'Enviando Email';
    loadingText.style.color = '#fff';
    loadingText.style.fontSize = '3rem';
    loadingText.style.fontFamily = 'Arial, sans-serif';
    loadingText.style.textAlign = 'center';

    loadingOverlay.appendChild(loadingText);
    document.body.appendChild(loadingOverlay);

    document.getElementById("salvar-adesivo-botao").addEventListener("click", function (e) {
        e.preventDefault();

        // Captura a imagem do canvas como base64
        const adesivoData = stage.toDataURL({ mimeType: "image/png" });

        // Envia a imagem para o servidor via AJAX
        fetch(personPlugin.ajax_url, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                action: "adicionar_adesivo_ao_carrinho",
                adesivo_url: adesivoData
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log("Resposta do servidor:", data);

            if (data.success) {
                // Redireciona para o carrinho
                window.location.href = data.data.cart_url;
            } else {
                alert("Erro: " + data.data.message);
            }
        })
        .catch(error => console.error("Erro no AJAX:", error));
    });

});

jQuery(document).ready(function($) {
    setTimeout(function() {
        $('td.product-thumbnail img').each(function() {
            var imgSrc = $(this).attr('src');
            if (imgSrc.includes('placeholder')) {
                $(this).attr('src', $(this).closest('tr').find('.product-name img').attr('src'));
            }
        });
    }, 500);
});
