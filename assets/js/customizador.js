// customizador.js

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

// Variáveis para edição direta individual e em grupo
var selectedPath = null;
var selectedGroup = null;

// Criação do seletor de cor inline para edição direta
var inlineColorPicker = document.createElement('input');
inlineColorPicker.type = 'text';
inlineColorPicker.style.position = 'fixed';
inlineColorPicker.style.display = 'none';
document.body.appendChild(inlineColorPicker);

// Declaração global para que os callbacks tenham acesso
var stage, layer, stickerGroup = null;

// Função auxiliar para converter cor RGB para Hex
function rgbToHex(rgb) {
    if (rgb.startsWith('#')) return rgb;
    var result = /^rgba?\((\d+),\s*(\d+),\s*(\d+)/i.exec(rgb);
    if (result) {
        var r = parseInt(result[1]).toString(16).padStart(2, '0');
        var g = parseInt(result[2]).toString(16).padStart(2, '0');
        var b = parseInt(result[3]).toString(16).padStart(2, '0');
        return '#' + r + g + b;
    }
    return '#000000';
}

// ---------- Funções Auxiliares Globalmente Definidas ----------

// Converte os estilos inline do SVG para que os estilos sejam aplicados diretamente nos elementos
function converterEstilosInline(svgText) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(svgText, 'image/svg+xml');
    const styleElements = doc.querySelectorAll('style');
    let cssText = '';
    styleElements.forEach(function (styleEl) {
        cssText += styleEl.textContent;
    });
    const regras = {};
    cssText.replace(/\.([^ \n{]+)\s*\{([^}]+)\}/g, function (match, className, declarations) {
        const props = {};
        declarations.split(';').forEach(function (decl) {
            if (decl.trim()) {
                const parts = decl.split(':');
                if (parts.length === 2) {
                    const prop = parts[0].trim();
                    const value = parts[1].trim();
                    props[prop] = value;
                }
            }
        });
        regras[className] = props;
        return '';
    });
    const elemsComClasse = doc.querySelectorAll('[class]');
    elemsComClasse.forEach(function (elem) {
        const classes = elem.getAttribute('class').split(/\s+/);
        classes.forEach(function (cls) {
            if (regras[cls] && regras[cls].fill) {
                elem.setAttribute('fill', regras[cls].fill);
            }
        });
    });
    styleElements.forEach(function (el) { el.parentNode.removeChild(el); });
    return new XMLSerializer().serializeToString(doc);
}

// Retorna o preenchimento efetivo (cor sólida) de um elemento SVG
function getEffectiveFill(elem) {
    let fill = elem.getAttribute('fill');
    if (fill && fill.trim() !== '' && fill.toLowerCase() !== 'none') {
        return fill;
    }
    const styleAttr = elem.getAttribute('style');
    if (styleAttr) {
        const match = styleAttr.match(/fill\s*:\s*([^;]+)/i);
        if (match && match[1] && match[1].trim() !== '' && match[1].trim().toLowerCase() !== 'none') {
            return match[1].trim();
        }
    }
    if (document.body.contains(elem)) {
        const computed = window.getComputedStyle(elem);
        if (computed && computed.fill && computed.fill !== 'none') {
            return computed.fill;
        }
    }
    return '#000000';
}

// ---------- Fim das Funções Auxiliares ----------

document.addEventListener('DOMContentLoaded', function () {
    var canvasElement = document.getElementById('adesivo-canvas');
    if (!canvasElement) {
        console.error("Elemento com id 'adesivo-canvas' não encontrado.");
        return;
    }

    // Inicializa o stage e a layer do Konva
    stage = new Konva.Stage({
        container: 'adesivo-canvas',
        width: canvasElement.offsetWidth,
        height: canvasElement.offsetHeight,
        draggable: false,
    });
    layer = new Konva.Layer();
    stage.add(layer);
    stickerGroup = null;
    var scaleBy = 1.05;

    // Se houver URL do adesivo no objeto pluginData, carrega-o
    if (typeof pluginData !== 'undefined' && pluginData.stickerUrl) {
        carregarAdesivo(pluginData.stickerUrl);
    }

    // ============================================================================
    // FUNÇÃO: Carregar o adesivo usando fabric.js para renderizar gradientes corretamente
    // ============================================================================
    function carregarAdesivo(stickerUrl) {
        console.log("Carregando adesivo de:", stickerUrl);
        // Utiliza fabric.js para carregar o SVG com suporte a gradientes
        fabric.loadSVGFromURL(stickerUrl, function (objects, options) {
            var fabricGroup = fabric.util.groupSVGElements(objects, options);
            // Cria um canvas off-screen para renderizar o grupo fabric
            var tempCanvasEl = document.createElement('canvas');
            tempCanvasEl.width = fabricGroup.width;
            tempCanvasEl.height = fabricGroup.height;
            var tempFabricCanvas = new fabric.Canvas(tempCanvasEl, { renderOnAddRemove: false });
            tempFabricCanvas.add(fabricGroup);
            tempFabricCanvas.renderAll();
            // Converte o canvas para dataURL (PNG)
            var dataURL = tempFabricCanvas.toDataURL({ format: 'png', quality: 1 });

            // Cria um objeto Konva.Image a partir do dataURL
            var konvaImage = new Konva.Image({
                x: 0,
                y: 0,
                draggable: false
            });
            var imgObj = new Image();
            imgObj.onload = function () {
                konvaImage.image(imgObj);
                // Ajusta a escala para encaixar no canvas do Konva
                var scaleX = stage.width() / imgObj.width;
                var scaleY = stage.height() / imgObj.height;
                var scale = Math.min(scaleX, scaleY);
                konvaImage.scale({ x: scale, y: scale });
                layer.add(konvaImage);
                layer.draw();
                saveHistory();
            };
            imgObj.src = dataURL;
            // Para permitir futura edição (por exemplo, abrindo uma interface para editar gradientes), você pode configurar eventos:
            konvaImage.on('dblclick', function () {
                alert("Aqui você pode abrir um editor Fabric para ajustar gradientes.");
            });
        });
    }

    // ============================================================================
    // FUNÇÃO: Ajustar tamanho e posição do adesivo (centralizar imagem)
    // ============================================================================
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

    // ============================================================================
    // FUNÇÃO: Preencher seleção de cores (bolinhas) para objetos sólidos
    // ============================================================================
    function preencherSelecaoDeCores() {
        var container = document.getElementById('layer-colors-container');
        if (!container) return;
        container.innerHTML = '';
        var groups = {};
        // Agrupa os objetos do grupo de adesivos (nesse exemplo, se o adesivo for uma imagem, não haverá cores vetoriais)
        if (stickerGroup) {
            stickerGroup.getChildren().forEach(function (child) {
                var fillColor = child.fill() || '#000000';
                fillColor = rgbToHex(fillColor).toLowerCase();
                if (!groups[fillColor]) groups[fillColor] = [];
                groups[fillColor].push(child);
            });
        }
        Object.keys(groups).forEach(function (fillColor) {
            var count = groups[fillColor].length;
            var colorDiv = document.createElement('div');
            colorDiv.style.cssText = "display:inline-block;width:30px;height:30px;border-radius:50%;background-color:" + fillColor + ";border:2px solid #fff;margin:5px;cursor:pointer;";
            colorDiv.title = 'Mudar cor ' + fillColor + ' (' + count + ' camada' + (count > 1 ? 's' : '') + ')';
            colorDiv.addEventListener('click', function () {
                selectedGroup = groups[fillColor];
                var colorInput = document.createElement('input');
                colorInput.type = 'color';
                colorInput.value = fillColor;
                colorInput.style.position = 'fixed';
                colorInput.style.zIndex = 10000;
                var offset = $(colorDiv).offset();
                var width = $(colorDiv).outerWidth();
                $(colorInput).css({
                    left: (offset.left + width + 10) + 'px',
                    top: offset.top + 'px'
                });
                colorInput.addEventListener('input', function (e) {
                    var newColor = e.target.value;
                    selectedGroup.forEach(function (child) {
                        if (typeof child.fill === 'function' && child.fill() && !child.fillLinearGradientColorStops()) {
                            child.fill(newColor);
                        }
                    });
                    layer.draw();
                });
                colorInput.addEventListener('blur', function () {
                    colorInput.remove();
                    preencherSelecaoDeCores();
                });
                document.body.appendChild(colorInput);
                colorInput.focus();
            });
            container.appendChild(colorDiv);
        });
    }

    // ============================================================================
    // FUNÇÕES: Histórico (undo/redo)
    // ============================================================================
    function saveHistory() {
        if (!layer || typeof layer.toJSON !== 'function') return;
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
        if (!layer || typeof layer.toJSON !== 'function') return;
        if (historyIndex > 0) {
            historyIndex--;
            var previousState = historyStates[historyIndex];
            layer.destroyChildren();
            var restoredLayer = Konva.Node.create(previousState).getChildren();
            layer.add(...restoredLayer);
            layer.draw();
            stickerGroup = layer.findOne('Group');
            preencherSelecaoDeCores();
            updateUndoRedoButtons();
        }
    }

    function redo() {
        if (!layer || typeof layer.toJSON !== 'function') return;
        if (historyIndex < historyStates.length - 1) {
            historyIndex++;
            var nextState = historyStates[historyIndex];
            layer.destroyChildren();
            var restoredLayer = Konva.Node.create(nextState).getChildren();
            layer.add(...restoredLayer);
            layer.draw();
            stickerGroup = layer.findOne('Group');
            preencherSelecaoDeCores();
            updateUndoRedoButtons();
        }
    }

    function updateUndoRedoButtons() {
        document.getElementById('undo-button').disabled = (historyIndex <= 0);
        document.getElementById('redo-button').disabled = (historyIndex >= historyStates.length - 1);
    }

    // ============================================================================
    // FUNÇÕES DE TEXTO, ZOOM, INSERÇÃO DE IMAGEM, ETC.
    // ============================================================================

    function atualizarTextoNoCanvas() {
        if (!stage || typeof stage.width !== 'function') return;
        var textContent = document.getElementById('texto').value;
        if (!textContent.trim()) {
            if (tempTextObject) {
                tempTextObject.destroy();
                tempTextObject = null;
                layer.draw();
            }
            return;
        }
        var fontSize = parseInt(document.getElementById('tamanho-fonte').value) || 16;
        var fontFamily = document.getElementById('fontPicker').value || 'Arial';
        var fillColor = document.getElementById('cor-texto').value || '#000';
        var rotation = parseFloat(document.getElementById('rotacao-texto').value) || 0;
        if (!tempTextObject) {
            tempTextObject = new Konva.Text({
                x: stage.width() / 2,
                y: stage.height() / 2,
                text: textContent,
                fontSize: fontSize,
                fontFamily: fontFamily,
                fill: fillColor,
                rotation: rotation,
                draggable: true
            });
            layer.add(tempTextObject);
        } else {
            tempTextObject.text(textContent);
            tempTextObject.fontSize(fontSize);
            tempTextObject.fontFamily(fontFamily);
            tempTextObject.fill(fillColor);
            tempTextObject.rotation(rotation);
        }
        layer.draw();
    }

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

    document.getElementById('adicionar-texto-botao').addEventListener('click', function () {
        var textContent = document.getElementById('texto').value.trim();
        if (!textContent) return;
        var fontSize = parseInt(document.getElementById('tamanho-fonte').value) || 16;
        var fontFamily = document.getElementById('fontPicker').value || 'Arial';
        var fillColor = document.getElementById('cor-texto').value || '#000';
        var rotation = parseFloat(document.getElementById('rotacao-texto').value) || 0;
        var newTextObject = new Konva.Text({
            x: stage.width() / 2,
            y: stage.height() / 2,
            text: textContent,
            fontSize: fontSize,
            fontFamily: fontFamily,
            fill: fillColor,
            draggable: true,
            rotation: rotation
        });
        newTextObject.on('dblclick', function () {
            enableInlineEditing(newTextObject);
        });
        newTextObject.on('dragend', saveHistory);
        layer.add(newTextObject);
        layer.draw();
        if (tempTextObject) {
            tempTextObject.destroy();
            tempTextObject = null;
        }
        document.getElementById('texto').value = '';
        saveHistory();
    });

    function enableInlineEditing(textNode) {
        textNode.hide();
        layer.draw();
        var textPosition = textNode.getAbsolutePosition();
        var stageBox = stage.container().getBoundingClientRect();
        var area = document.createElement('textarea');
        document.body.appendChild(area);
        area.value = textNode.text();
        area.style.position = 'absolute';
        area.style.top = stageBox.top + textPosition.y + 'px';
        area.style.left = stageBox.left + textPosition.x + 'px';
        area.style.fontSize = textNode.fontSize() + 'px';
        area.style.fontFamily = textNode.fontFamily();
        area.style.color = textNode.fill();
        area.style.background = 'none';
        area.style.outline = 'none';
        area.style.resize = 'none';
        area.focus();
        area.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                finishEdit();
            }
        });
        area.addEventListener('blur', finishEdit);
        function finishEdit() {
            textNode.text(area.value);
            textNode.show();
            layer.draw();
            document.body.removeChild(area);
        }
    }

    document.getElementById('texto').addEventListener('blur', saveHistory);
    document.getElementById('tamanho-fonte').addEventListener('blur', saveHistory);
    document.getElementById('fontPicker').addEventListener('blur', saveHistory);
    document.getElementById('cor-texto').addEventListener('blur', saveHistory);
    document.getElementById('rotacao-texto').addEventListener('mouseup', saveHistory);
    document.getElementById('rotacao-texto-valor').addEventListener('blur', saveHistory);

    document.getElementById('salvar-modelo-botao').addEventListener('click', function () {
        if (tempTextObject) {
            tempTextObject.draggable(false);
            tempTextObject = null;
        }
        saveHistory();
    });

    // ============================================================================
    // Controles de Zoom
    // ============================================================================
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

    // ============================================================================
    // Undo/Redo
    // ============================================================================
    document.getElementById('undo-button').addEventListener('click', undo);
    document.getElementById('redo-button').addEventListener('click', redo);
    updateUndoRedoButtons();

    function undo() {
        if (!layer || typeof layer.toJSON !== 'function') return;
        if (historyIndex > 0) {
            historyIndex--;
            var previousState = historyStates[historyIndex];
            layer.destroyChildren();
            var restoredLayer = Konva.Node.create(previousState).getChildren();
            layer.add(...restoredLayer);
            layer.draw();
            stickerGroup = layer.findOne('Group');
            preencherSelecaoDeCores();
            updateUndoRedoButtons();
        }
    }

    function redo() {
        if (!layer || typeof layer.toJSON !== 'function') return;
        if (historyIndex < historyStates.length - 1) {
            historyIndex++;
            var nextState = historyStates[historyIndex];
            layer.destroyChildren();
            var restoredLayer = Konva.Node.create(nextState).getChildren();
            layer.add(...restoredLayer);
            layer.draw();
            stickerGroup = layer.findOne('Group');
            preencherSelecaoDeCores();
            updateUndoRedoButtons();
        }
    }

    function updateUndoRedoButtons() {
        document.getElementById('undo-button').disabled = (historyIndex <= 0);
        document.getElementById('redo-button').disabled = (historyIndex >= historyStates.length - 1);
    }

    // ============================================================================
    // Inserir Imagem
    // ============================================================================
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

    // ============================================================================
    // Aumentar/Diminuir Imagem
    // ============================================================================
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

    // ============================================================================
    // Limpar Tela
    // ============================================================================
    document.getElementById('limpar-tela-botao').addEventListener('click', function () {
        layer.destroyChildren();
        stickerGroup = null;
        insertedImage = null;
        tempTextObject = null;
        layer.draw();
        saveHistory();
    });

    // ============================================================================
    // Salvar Adesivo (envia via AJAX)
    // ============================================================================
    $('#salvar-adesivo-botao').on('click', function (e) {
        e.preventDefault();
        if (!stage) {
            console.error('Stage não definido!');
            alert('Erro: O editor de adesivos não está carregado corretamente.');
            return;
        }
        var adesivoBase64 = stage.toDataURL({ pixelRatio: 3 });
        console.log('Imagem capturada:', adesivoBase64);
        var price = $('#stickerPrice').val();
        var aceitoTermos = $('#aceito-termos').prop('checked');
        if (!price || isNaN(price) || price <= 0) {
            alert('Erro: Preço inválido!');
            return;
        }
        if (!aceitoTeroms && !aceitoTermos) {
            if (confirm('Você precisa aceitar os termos para continuar. Deseja aceitar agora?')) {
                $('#aceito-termos').prop('checked', true);
            } else {
                return;
            }
        }
        $.ajax({
            url: personPlugin.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'salvar_adesivo_servidor',
                adesivo_base64: adesivoBase64,
                price: price
            },
            success: function (response) {
                console.log('Resposta do servidor:', response);
                if (response.success) {
                    window.location.href = response.data.cart_url;
                } else {
                    alert(response.data.message);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('Erro AJAX:', textStatus, errorThrown, jqXHR.responseText);
                alert('Erro na requisição.');
            }
        });
    });

    jQuery(document).ready(function ($) {
        setTimeout(function () {
            $('td.product-thumbnail img').each(function () {
                var imgSrc = $(this).attr('src');
                if (imgSrc.includes('placeholder')) {
                    $(this).attr('src', $(this).closest('tr').find('.product-name img').attr('src'));
                }
            });
        }, 500);
    });
});
