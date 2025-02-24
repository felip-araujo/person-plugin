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
            let text = styleEl.textContent;
            // Remove delimitadores CDATA, se houver
            text = text.replace(/<!\[CDATA\[/g, "").replace(/\]\]>/g, "");
            cssText += text;
        });
        cssText = cssText.trim();
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
        console.log("Regras CSS extraídas:", regras);

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
        const result = new XMLSerializer().serializeToString(doc);
        console.log("SVG após converter estilos:", result);
        return result;
    }

    const svgInline = converterEstilosInline(svgText);
    console.log("SVG convertido:", svgInline);


    // Função para extrair gradientes do SVG e armazená-los
    function extrairGradientes(svgDoc) {
        const gradients = {};
        svgDoc.querySelectorAll("linearGradient").forEach(grad => {
            const id = grad.getAttribute("id");
            const gradientUnits = grad.getAttribute("gradientUnits") || "objectBoundingBox";
            const x1 = parseFloat(grad.getAttribute("x1")) || 0;
            const y1 = parseFloat(grad.getAttribute("y1")) || 0;
            const x2 = parseFloat(grad.getAttribute("x2")) || 1;
            const y2 = parseFloat(grad.getAttribute("y2")) || 1;

            const stops = [];
            grad.querySelectorAll("stop").forEach(stop => {
                const offset = parseFloat(stop.getAttribute("offset")) || 0;
                const color = stop.style.getPropertyValue("stop-color") || stop.getAttribute("stop-color");
                if (color) {
                    stops.push(offset, color);
                }
            });

            console.log(`Gradiente extraído: ${id}`, { x1, y1, x2, y2, stops });

            gradients[id] = { x1, y1, x2, y2, stops, gradientUnits };
        });

        return gradients;
    }



    const gradientsMap = extrairGradientes(svgDoc);
    console.log("Gradientes extraídos:", gradientsMap);


    // Retorna o fill efetivo (sólido ou referência a gradiente)
    function getEffectiveFill(elem, gradientsMap) {
        const fill = elem.getAttribute('fill');
        console.log(`Elemento: ${elem.tagName}, Fill encontrado: ${fill}`);

        if (fill && fill.startsWith("url(")) {
            const gradientId = fill.match(/url\(#(.*?)\)/)?.[1];
            console.log(`Gradiente referenciado: ${gradientId}`);

            if (gradientId && gradientsMap[gradientId]) {
                console.log(`Gradiente aplicado:`, gradientsMap[gradientId]);
                return { isGradient: true, ...gradientsMap[gradientId] };
            } else {
                console.log("Gradiente não encontrado no mapa!");
            }
        }

        return fill;
    }


    // Função para carregar o adesivo (SVG) e converter gradientes para propriedades do Konva
    function carregarAdesivo(stickerUrl) {
        fetch(stickerUrl)
            .then(response => {
                if (!response.ok) throw new Error('Erro ao carregar o adesivo: ' + response.status);
                return response.text();
            })
            .then(svgText => {
                const svgInline = converterEstilosInline(svgText);
                const parser = new DOMParser();
                const svgDoc = parser.parseFromString(svgInline, 'image/svg+xml');
                const gradientsMap = extrairGradientes(svgDoc);

                layer.destroyChildren();
                stickerGroup = new Konva.Group({ draggable: false });

                Array.from(svgDoc.querySelectorAll('path, rect, circle, ellipse, polygon, polyline, line')).forEach((elem, index) => {
                    const fillValue = getEffectiveFill(elem, gradientsMap);
                    const tagName = elem.tagName.toLowerCase();

                    if (tagName === 'circle') {
                        // Pegando atributos do círculo
                        const cx = parseFloat(elem.getAttribute('cx'));
                        const cy = parseFloat(elem.getAttribute('cy'));
                        const r = parseFloat(elem.getAttribute('r'));

                        let circle = new Konva.Circle({
                            x: cx,
                            y: cy,
                            radius: r,
                            fill: null, // Define como null para garantir que o gradiente seja aplicado corretamente
                            draggable: false,
                            id: `layer-${index}`,
                        });

                        stickerGroup.add(circle);

                        if (fillValue && fillValue.isGradient) {
                            console.log("Aplicando gradiente no círculo", { fillValue });

                            const bbox = { x: cx - r, y: cy - r, width: 2 * r, height: 2 * r };
                            let startPoint, endPoint;

                            if (fillValue.gradientUnits === 'objectBoundingBox') {
                                startPoint = {
                                    x: bbox.x + fillValue.x1 * bbox.width,
                                    y: bbox.y + fillValue.y1 * bbox.height,
                                };
                                endPoint = {
                                    x: bbox.x + fillValue.x2 * bbox.width,
                                    y: bbox.y + fillValue.y2 * bbox.height,
                                };
                            } else {
                                startPoint = { x: fillValue.x1, y: fillValue.y1 };
                                endPoint = { x: fillValue.x2, y: fillValue.y2 };
                            }

                            // Normaliza os pontos para ficarem dentro do círculo
                            startPoint.x -= cx;
                            startPoint.y -= cy;
                            endPoint.x -= cx;
                            endPoint.y -= cy;

                            console.log("Circle - StartPoint:", startPoint, "EndPoint:", endPoint, "Stops:", fillValue.stops);

                            circle.fillLinearGradientStartPoint(startPoint);
                            circle.fillLinearGradientEndPoint(endPoint);
                            circle.fillLinearGradientColorStops(fillValue.stops);

                            console.log("Gradiente aplicado com sucesso!");
                        } else {
                            circle.fill(fillValue);
                            console.log("Nenhum gradiente encontrado para este círculo.");
                        }
                    }
                });

                // Adicionando ao layer
                layer.add(stickerGroup);
                ajustarTamanhoEPosicaoDoAdesivo();
                preencherSelecaoDeCores();
                layer.draw();


            })
            .catch(error => console.error('Erro ao carregar o adesivo:', error));
    }

    console.log('fill value é:'.fillValue)
    console.log('start é:'.startPoint)
    console.log('end é:'.endPoint)
    console.log(gradientsMap)

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

    function preencherSelecaoDeCores() {
        var container = document.getElementById('layer-colors-container');
        if (!container) return;
        container.innerHTML = '';
        var groups = {};
        stickerGroup.getChildren().forEach(child => {
            var fillColor = child.fill() || '#000000';
            fillColor = rgbToHex(fillColor).toLowerCase();
            if (!groups[fillColor]) groups[fillColor] = [];
            groups[fillColor].push(child);
        });
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
                    selectedGroup.forEach(child => {
                        child.fill(newColor);
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

    // ---------------------------
    // Nova implementação para edição de degradê utilizando Fabric.js
    // ---------------------------

    // Ao invés de usar prompt, ao chamar editarGradiente abriremos uma modal com uma pré-visualização fabric
    function editarGradiente(obj) {
        if (!obj.gradientData) {
            obj.gradientData = {
                startPoint: { x: 0, y: 0 },
                endPoint: { x: obj.width() || 100, y: 0 },
                colorStops: [0, "#ff0000", 1, "#0000ff"]
            };
        }
        openGradientEditor(obj);
    }

    // Cria/abre o editor de degradê usando Fabric.js
    function openGradientEditor(targetObj) {
        var modal = document.getElementById('gradient-editor-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'gradient-editor-modal';
            modal.style.position = 'fixed';
            modal.style.top = '50%';
            modal.style.left = '50%';
            modal.style.transform = 'translate(-50%, -50%)';
            modal.style.background = '#fff';
            modal.style.padding = '20px';
            modal.style.boxShadow = '0 0 10px rgba(0,0,0,0.5)';
            modal.style.zIndex = 10000;
            modal.style.width = '400px';
            modal.style.display = 'none';

            // Cria o canvas do Fabric para pré-visualização do degradê
            var fabricCanvasEl = document.createElement('canvas');
            fabricCanvasEl.id = 'fabric-gradient-canvas';
            fabricCanvasEl.width = 360;
            fabricCanvasEl.height = 50;
            fabricCanvasEl.style.border = '1px solid #ccc';
            modal.appendChild(fabricCanvasEl);

            // Inputs para os stops do degradê
            var label1 = document.createElement('label');
            label1.textContent = 'Stop 1 Offset: ';
            var stop1Offset = document.createElement('input');
            stop1Offset.type = 'number';
            stop1Offset.step = '0.01';
            stop1Offset.min = '0';
            stop1Offset.max = '1';
            stop1Offset.id = 'gradient-stop1-offset';
            label1.appendChild(stop1Offset);
            modal.appendChild(label1);

            var label1Color = document.createElement('label');
            label1Color.textContent = ' Cor: ';
            var stop1Color = document.createElement('input');
            stop1Color.type = 'color';
            stop1Color.id = 'gradient-stop1-color';
            label1Color.appendChild(stop1Color);
            modal.appendChild(label1Color);

            modal.appendChild(document.createElement('br'));

            var label2 = document.createElement('label');
            label2.textContent = 'Stop 2 Offset: ';
            var stop2Offset = document.createElement('input');
            stop2Offset.type = 'number';
            stop2Offset.step = '0.01';
            stop2Offset.min = '0';
            stop2Offset.max = '1';
            stop2Offset.id = 'gradient-stop2-offset';
            label2.appendChild(stop2Offset);
            modal.appendChild(label2);

            var label2Color = document.createElement('label');
            label2Color.textContent = ' Cor: ';
            var stop2Color = document.createElement('input');
            stop2Color.type = 'color';
            stop2Color.id = 'gradient-stop2-color';
            label2Color.appendChild(stop2Color);
            modal.appendChild(label2Color);

            modal.appendChild(document.createElement('br'));

            // Botões Salvar e Cancelar
            var saveButton = document.createElement('button');
            saveButton.textContent = 'Salvar';
            saveButton.id = 'gradient-save-button';
            modal.appendChild(saveButton);

            var cancelButton = document.createElement('button');
            cancelButton.textContent = 'Cancelar';
            cancelButton.id = 'gradient-cancel-button';
            cancelButton.style.marginLeft = '10px';
            modal.appendChild(cancelButton);

            document.body.appendChild(modal);

            // Inicializa o Fabric canvas
            var fabricCanvas = new fabric.Canvas('fabric-gradient-canvas', {
                selection: false,
                backgroundColor: '#fff'
            });
            var rect = new fabric.Rect({
                left: 0,
                top: 0,
                width: fabricCanvasEl.width,
                height: fabricCanvasEl.height,
                selectable: false
            });
            fabricCanvas.add(rect);
            fabricCanvas.renderAll();

            modal.fabricCanvas = fabricCanvas;
            modal.rect = rect;

            // Atualiza a pré-visualização sempre que os inputs mudarem
            function updatePreview() {
                var offset1 = parseFloat(stop1Offset.value) || 0;
                var offset2 = parseFloat(stop2Offset.value) || 1;
                var color1 = stop1Color.value || '#ff0000';
                var color2 = stop2Color.value || '#0000ff';

                rect.set('fill', new fabric.Gradient({
                    type: 'linear',
                    gradientUnits: 'percentage',
                    coords: { x1: 0, y1: 0, x2: 1, y2: 0 },
                    colorStops: [
                        { offset: offset1, color: color1 },
                        { offset: offset2, color: color2 }
                    ]
                }));
                fabricCanvas.renderAll();
            }
            stop1Offset.addEventListener('input', updatePreview);
            stop1Color.addEventListener('input', updatePreview);
            stop2Offset.addEventListener('input', updatePreview);
            stop2Color.addEventListener('input', updatePreview);

            // Evento do botão Salvar
            saveButton.addEventListener('click', function () {
                var offset1 = parseFloat(stop1Offset.value) || 0;
                var offset2 = parseFloat(stop2Offset.value) || 1;
                var color1 = stop1Color.value || '#ff0000';
                var color2 = stop2Color.value || '#0000ff';
                var newStops = [offset1, color1, offset2, color2];

                targetObj.gradientData.colorStops = newStops;
                targetObj.fillLinearGradientColorStops(newStops);
                targetObj.fillLinearGradientStartPoint(targetObj.gradientData.startPoint);
                targetObj.fillLinearGradientEndPoint(targetObj.gradientData.endPoint);
                layer.draw();
                saveHistory();
                modal.style.display = 'none';
            });

            // Evento do botão Cancelar
            cancelButton.addEventListener('click', function () {
                modal.style.display = 'none';
            });
        }

        // Preenche os inputs com os dados atuais do degradê
        var stop1Offset = document.getElementById('gradient-stop1-offset');
        var stop1Color = document.getElementById('gradient-stop1-color');
        var stop2Offset = document.getElementById('gradient-stop2-offset');
        var stop2Color = document.getElementById('gradient-stop2-color');

        var stops = targetObj.gradientData.colorStops;
        stop1Offset.value = stops[0];
        stop1Color.value = stops[1];
        stop2Offset.value = stops[2];
        stop2Color.value = stops[3];

        var fabricCanvas = modal.fabricCanvas;
        var rect = modal.rect;
        rect.set('fill', new fabric.Gradient({
            type: 'linear',
            gradientUnits: 'percentage',
            coords: { x1: 0, y1: 0, x2: 1, y2: 0 },
            colorStops: [
                { offset: parseFloat(stops[0]), color: stops[1] },
                { offset: parseFloat(stops[2]), color: stops[3] }
            ]
        }));
        fabricCanvas.renderAll();

        modal.style.display = 'block';
    }
    // ---------------------------
    // Fim da integração Fabric para edição de degradê
    // ---------------------------

    // Funções de texto

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

    document.getElementById('limpar-tela-botao').addEventListener('click', function () {
        layer.destroyChildren();
        stickerGroup = null;
        insertedImage = null;
        tempTextObject = null;
        layer.draw();
        saveHistory();
    });

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

        if (!aceitoTermos) {
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
});
