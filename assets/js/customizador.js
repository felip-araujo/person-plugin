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
inlineColorPicker.style.position = 'fixed'; // Usamos fixed para posicionamento relativo à viewport
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

// Inicialização do Spectrum no inlineColorPicker
// $(inlineColorPicker).spectrum({
//     showInput: true,
//     showInitial: true,
//     preferredFormat: "hex",
//     showPalette: true,
//     palette: [],
//     // Não vamos usar o preview interno (você pode ocultá-lo com CSS se necessário)
//     move: function (color) {
//         // Atualiza a cor em tempo real no grupo selecionado
//         if (Array.isArray(selectedGroup) && stickerGroup && layer) {
//             selectedGroup.forEach(child => {
//                 child.fill(color.toHexString());
//             });
//             layer.draw();
//         }
//     },
//     change: function (color) {
//         // Aplica a cor final no grupo selecionado
//         if (Array.isArray(selectedGroup) && stickerGroup && layer) {
//             selectedGroup.forEach(child => {
//                 child.fill(color.toHexString());
//             });
//             layer.draw();
//             saveHistory();
//             selectedGroup = null;
//             $(inlineColorPicker).spectrum("hide");
//             preencherSelecaoDeCores(); // Atualiza as bolinhas, se necessário
//         }
//     },
//     hide: function () {
//         selectedGroup = null;
//     }
// });

document.addEventListener('DOMContentLoaded', function () {
    var canvasElement = document.getElementById('adesivo-canvas');
    if (!canvasElement) {
        return;
    }

    // Inicializa o stage e layer do Konva
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

    if (typeof pluginData !== 'undefined' && pluginData.stickerUrl) {
        carregarAdesivo(pluginData.stickerUrl);
    }

    // Função para converter estilos inline no SVG
    function converterEstilosInline(svgText) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(svgText, 'image/svg+xml');
        const styleElements = doc.querySelectorAll('style');
        let cssText = '';
        styleElements.forEach(styleEl => {
            cssText += styleEl.textContent;
        });
        const regras = {};
        cssText.replace(/\.([^ \n{]+)\s*\{([^}]+)\}/g, (match, className, declarations) => {
            const props = {};
            declarations.split(';').forEach(decl => {
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
        elemsComClasse.forEach(elem => {
            const classes = elem.getAttribute('class').split(/\s+/);
            classes.forEach(cls => {
                if (regras[cls] && regras[cls].fill) {
                    elem.setAttribute('fill', regras[cls].fill);
                }
            });
        });
        styleElements.forEach(el => el.parentNode.removeChild(el));
        return new XMLSerializer().serializeToString(doc);
    }

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

    // Função para carregar o adesivo (SVG)
    function carregarAdesivo(stickerUrl) {
        fetch(stickerUrl)
            .then((response) => {
                if (!response.ok) throw new Error('Erro ao carregar o adesivo: ' + response.status);
                return response.text();
            })
            .then((svgText) => {
                const svgComInline = converterEstilosInline(svgText);
                var parser = new DOMParser();
                var svgDoc = parser.parseFromString(svgComInline, 'image/svg+xml');
                layer.destroyChildren();
                stickerGroup = new Konva.Group({ draggable: false });

                // Seleciona todos os elementos relevantes
                Array.from(svgDoc.querySelectorAll('path, rect, circle, ellipse, polygon, polyline, line')).forEach((elem, index) => {
                    var fillColor = getEffectiveFill(elem);
                    var tagName = elem.tagName.toLowerCase();

                    if (tagName === 'path') {
                        var pathData = elem.getAttribute('d');
                        if (pathData) {
                            var path = new Konva.Path({
                                data: pathData,
                                fill: fillColor,
                                draggable: false,
                                id: `layer-${index}`,
                            });
                            stickerGroup.add(path);
                        }
                    } else if (tagName === 'circle') {
                        var cx = parseFloat(elem.getAttribute('cx')) || 0;
                        var cy = parseFloat(elem.getAttribute('cy')) || 0;
                        var r = parseFloat(elem.getAttribute('r')) || 0;
                        var circle = new Konva.Circle({
                            x: cx,
                            y: cy,
                            radius: r,
                            fill: fillColor,
                            draggable: false,
                            id: `layer-${index}`,
                        });
                        stickerGroup.add(circle);
                    } else if (tagName === 'rect') {
                        var x = parseFloat(elem.getAttribute('x')) || 0;
                        var y = parseFloat(elem.getAttribute('y')) || 0;
                        var width = parseFloat(elem.getAttribute('width')) || 0;
                        var height = parseFloat(elem.getAttribute('height')) || 0;
                        var rect = new Konva.Rect({
                            x: x,
                            y: y,
                            width: width,
                            height: height,
                            fill: fillColor,
                            draggable: false,
                            id: `layer-${index}`,
                        });
                        stickerGroup.add(rect);
                    } else if (tagName === 'ellipse') {
                        var cx = parseFloat(elem.getAttribute('cx')) || 0;
                        var cy = parseFloat(elem.getAttribute('cy')) || 0;
                        var rx = parseFloat(elem.getAttribute('rx')) || 0;
                        var ry = parseFloat(elem.getAttribute('ry')) || 0;
                        var ellipse = new Konva.Ellipse({
                            x: cx,
                            y: cy,
                            radiusX: rx,
                            radiusY: ry,
                            fill: fillColor,
                            draggable: false,
                            id: `layer-${index}`,
                        });
                        stickerGroup.add(ellipse);
                    } else if (tagName === 'line') {
                        // Para linhas, você pode buscar os atributos x1, y1, x2, y2 e criar um array de pontos
                        var x1 = parseFloat(elem.getAttribute('x1')) || 0;
                        var y1 = parseFloat(elem.getAttribute('y1')) || 0;
                        var x2 = parseFloat(elem.getAttribute('x2')) || 0;
                        var y2 = parseFloat(elem.getAttribute('y2')) || 0;
                        var line = new Konva.Line({
                            points: [x1, y1, x2, y2],
                            stroke: fillColor,
                            draggable: false,
                            id: `layer-${index}`,
                        });
                        stickerGroup.add(line);
                    }
                    // Para polygon e polyline, você pode converter os pontos para um array numérico
                    else if (tagName === 'polygon' || tagName === 'polyline') {
                        var pointsString = elem.getAttribute('points');
                        if (pointsString) {
                            var points = pointsString.trim().split(/[\s,]+/).map(parseFloat);
                            var shape = new Konva.Line({
                                points: points,
                                fill: fillColor,
                                closed: (tagName === 'polygon'),
                                draggable: false,
                                id: `layer-${index}`,
                            });
                            stickerGroup.add(shape);
                        }
                    }
                });

                layer.add(stickerGroup);
                ajustarTamanhoEPosicaoDoAdesivo();
                preencherSelecaoDeCores();
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

    // Função para preencher a seleção de cores como bolinhas na aba lateral
    function preencherSelecaoDeCores() {
        var container = document.getElementById('layer-colors-container');
        if (!container) return;
        container.innerHTML = ''; // Limpa o container

        var groups = {};
        // Agrupa os elementos por cor (em formato hex)
        stickerGroup.getChildren().forEach(child => {
            var fillColor = child.fill() || '#000000';
            fillColor = rgbToHex(fillColor).toLowerCase();
            if (!groups[fillColor]) groups[fillColor] = [];
            groups[fillColor].push(child);
        });

        // Para cada cor, cria uma bolinha
        Object.keys(groups).forEach(function (fillColor) {
            var count = groups[fillColor].length;
            var colorDiv = document.createElement('div');
            colorDiv.style.cssText = "display:inline-block;width:30px;height:30px;border-radius:50%;background-color:" + fillColor + ";border:2px solid #fff;margin:5px;cursor:pointer;";
            colorDiv.title = 'Mudar cor ' + fillColor + ' (' + count + ' camada' + (count > 1 ? 's' : '') + ')';

            colorDiv.addEventListener('click', function () {
                // Define o grupo selecionado
                selectedGroup = groups[fillColor];

                // Cria um input do tipo color
                var colorInput = document.createElement('input');
                colorInput.type = 'color';
                colorInput.value = fillColor;
                colorInput.style.position = 'fixed';
                colorInput.style.zIndex = 10000;

                // Posiciona o input ao lado da bolinha usando jQuery offset
                var offset = $(colorDiv).offset();
                var width = $(colorDiv).outerWidth();
                $(colorInput).css({
                    left: (offset.left + width + 10) + 'px', // 10px à direita
                    top: offset.top + 'px'
                });

                // Ao mudar a cor, atualiza o grupo em tempo real
                colorInput.addEventListener('input', function (e) {
                    var newColor = e.target.value;
                    selectedGroup.forEach(child => {
                        child.fill(newColor);
                    });
                    layer.draw();
                });

                // Remove o input quando perder o foco
                colorInput.addEventListener('blur', function () {
                    colorInput.remove();
                    // Opcional: Atualiza as bolinhas caso a cor tenha mudado
                    preencherSelecaoDeCores();
                });

                document.body.appendChild(colorInput);
                colorInput.focus();
            });

            container.appendChild(colorDiv);
        });
    }


    // Funções de histórico (undo/redo)
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
            preencherSelecaoDeCores();
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
            preencherSelecaoDeCores();
            updateUndoRedoButtons();
        }
    }

    function updateUndoRedoButtons() {
        document.getElementById('undo-button').disabled = (historyIndex <= 0);
        document.getElementById('redo-button').disabled = (historyIndex >= historyStates.length - 1);
    }



    //funcoes de texto 

    // Variável global para o preview
    // var tempTextObject = null;

    // Função para atualizar o preview com base nos inputs
    function atualizarTextoNoCanvas() {
        var textContent = document.getElementById('texto').value;
        // Se não houver texto, remove o preview (caso exista)
        if (!textContent.trim()) {
            if (tempTextObject) {
                tempTextObject.destroy();
                tempTextObject = null;
                layer.draw();
            }
            return;
        }

        // Parâmetros atuais do texto
        var fontSize = parseInt(document.getElementById('tamanho-fonte').value) || 16;
        var fontFamily = document.getElementById('fontPicker').value || 'Arial';
        var fillColor = document.getElementById('cor-texto').value || '#000';
        var rotation = parseFloat(document.getElementById('rotacao-texto').value) || 0;

        if (!tempTextObject) {
            // Cria o objeto de preview se ainda não existir
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
            // Atualiza o preview existente
            tempTextObject.text(textContent);
            tempTextObject.fontSize(fontSize);
            tempTextObject.fontFamily(fontFamily);
            tempTextObject.fill(fillColor);
            tempTextObject.rotation(rotation);
        }
        layer.draw();
    }

    // Event listeners para atualizar o preview conforme os inputs mudam
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

    // Ao clicar em "Adicionar Texto", cria o objeto definitivo e remove o preview
    document.getElementById('adicionar-texto-botao').addEventListener('click', function () {
        var textContent = document.getElementById('texto').value.trim();
        if (!textContent) return;

        var fontSize = parseInt(document.getElementById('tamanho-fonte').value) || 16;
        var fontFamily = document.getElementById('fontPicker').value || 'Arial';
        var fillColor = document.getElementById('cor-texto').value || '#000';
        var rotation = parseFloat(document.getElementById('rotacao-texto').value) || 0;

        // Cria o objeto definitivo com os parâmetros atuais
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

        // Habilita a edição inline (ao dar duplo clique)
        newTextObject.on('dblclick', function () {
            enableInlineEditing(newTextObject);
        });
        // Exemplo: salva o estado após arrastar
        newTextObject.on('dragend', saveHistory);

        layer.add(newTextObject);
        layer.draw();

        // Remove o preview (se existir) e limpa o input
        if (tempTextObject) {
            tempTextObject.destroy();
            tempTextObject = null;
        }
        document.getElementById('texto').value = '';
        saveHistory();
    });

    // Função para edição inline (permite editar o texto já adicionado)
    function enableInlineEditing(textNode) {
        // Oculta o texto enquanto edita
        textNode.hide();
        layer.draw();

        // Pega a posição e define o estilo do textarea
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

        // Finaliza a edição ao pressionar Enter ou ao perder o foco
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

    // Event listeners para salvar o estado (assegure-se de que saveHistory está definida)
    document.getElementById('texto').addEventListener('blur', saveHistory);
    document.getElementById('tamanho-fonte').addEventListener('blur', saveHistory);
    document.getElementById('fontPicker').addEventListener('blur', saveHistory);
    document.getElementById('cor-texto').addEventListener('blur', saveHistory);
    document.getElementById('rotacao-texto').addEventListener('mouseup', saveHistory);
    document.getElementById('rotacao-texto-valor').addEventListener('blur', saveHistory);


    //funcoes de texto 




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

    // Limpar Tela
    document.getElementById('limpar-tela-botao').addEventListener('click', function () {
        layer.destroyChildren();
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

    $('#salvar-adesivo-botao').on('click', function (e) {
        e.preventDefault();

        var adesivoUrl = $('#adesivoUrl').val();
        var price = $('#stickerPrice').val();

        console.log('Preço enviado:', price);

        if (!price || isNaN(price) || price <= 0) {
            alert('Erro: Preço inválido!');
            return;
        }

        $.ajax({
            url: personPlugin.ajax_url,
            method: 'POST',
            data: {
                action: 'criar_produto_temporario_adesivo',
                adesivo_url: adesivoUrl,
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
            error: function () {
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

