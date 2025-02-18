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
    }
    // Continuar execução mesmo que pluginData.stickerUrl não esteja definido

    // -------------------
    //  FUNÇÕES AUXILIARES
    // -------------------

    // Função getRandomColor() foi mantida caso seja necessária em outro momento
    function getRandomColor() {
        return '#' + ('000000' + Math.floor(Math.random() * 16777215).toString(16)).slice(-6);
    }

    /**
     * Converte as regras CSS definidas no <style> do SVG em atributos inline.
     * Atualmente, aplica somente a propriedade "fill".
     */
    function converterEstilosInline(svgText) {
        // Parse o SVG
        const parser = new DOMParser();
        const doc = parser.parseFromString(svgText, 'image/svg+xml');

        // Extrai todo o conteúdo dos <style>
        const styleElements = doc.querySelectorAll('style');
        let cssText = '';
        styleElements.forEach(styleEl => {
            cssText += styleEl.textContent;
        });

        // Cria um objeto com as regras: nome_da_classe => { propriedade: valor, ... }
        const regras = {};
        // Expressão regular simples para capturar regras do tipo: .nome { ... }
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

        // Para cada elemento que possui atributo class, aplica os estilos inline (neste caso, o fill)
        const elemsComClasse = doc.querySelectorAll('[class]');
        elemsComClasse.forEach(elem => {
            const classes = elem.getAttribute('class').split(/\s+/);
            classes.forEach(cls => {
                if (regras[cls] && regras[cls].fill) {
                    // Se já existir um atributo inline fill, pode decidir sobrescrever ou não
                    elem.setAttribute('fill', regras[cls].fill);
                }
            });
        });

        // Opcional: remover o elemento <style> se não for mais necessário
        styleElements.forEach(el => el.parentNode.removeChild(el));

        return new XMLSerializer().serializeToString(doc);
    }

    /**
     * Tenta obter o valor efetivo do fill de um elemento SVG.
     * Primeiro verifica o atributo inline, depois o style e, por fim,
     * o estilo computado (caso o SVG esteja anexado ao DOM).
     */
    function getEffectiveFill(elem) {
        // Tenta o atributo 'fill' inline
        let fill = elem.getAttribute('fill');
        if (fill && fill.trim() !== '' && fill.toLowerCase() !== 'none') {
            return fill;
        }

        // Tenta extrair do atributo 'style'
        const styleAttr = elem.getAttribute('style');
        if (styleAttr) {
            const match = styleAttr.match(/fill\s*:\s*([^;]+)/i);
            if (match && match[1] && match[1].trim() !== '' && match[1].trim().toLowerCase() !== 'none') {
                return match[1].trim();
            }
        }

        // Tenta obter o estilo computado (caso o SVG esteja no DOM)
        if (document.body.contains(elem)) {
            const computed = window.getComputedStyle(elem);
            if (computed && computed.fill && computed.fill !== 'none') {
                return computed.fill;
            }
        }

        // Se nada for encontrado, retorna um padrão (preto)
        return '#000000';
    }

    // -------------------
    //  FUNÇÃO CARREGAR ADESIVO (SVG)
    // -------------------
    function carregarAdesivo(stickerUrl) {
        fetch(stickerUrl)
            .then((response) => {
                if (!response.ok) throw new Error('Erro ao carregar o adesivo: ' + response.status);
                return response.text();
            })
            .then((svgText) => {
                // Converte os estilos CSS do SVG em atributos inline
                const svgComInline = converterEstilosInline(svgText);

                var parser = new DOMParser();
                var svgDoc = parser.parseFromString(svgComInline, 'image/svg+xml');

                layer.destroyChildren(); // Limpa os elementos anteriores

                stickerGroup = new Konva.Group({ draggable: false });
                Array.from(svgDoc.querySelectorAll('path, rect, circle, ellipse, polygon, polyline, line')).forEach(
                    (elem, index) => {
                        var pathData = elem.getAttribute('d') || '';
                        // Usa a função para obter a cor efetiva (deve estar inline agora)
                        var fillColor = getEffectiveFill(elem);

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

    // -------------------
    //  FUNÇÃO AJUSTAR TAMANHO E POSIÇÃO DO ADESIVO
    // -------------------
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

    // -------------------
    //  FUNÇÃO PREENCHE SELETOR DE CAMADAS
    // -------------------
    function preencherSeletorDeCamadas() {
        // Dicionário para nomes amigáveis das cores
        const colorNames = {
            '#000': 'Preto',
            '#000000': 'Preto',
            '#fff': 'Branco',
            '#ffffff': 'Branco',
            '#ff0000': 'Vermelho',
            '#00ff00': 'Verde',
            '#0000ff': 'Azul',
            '#ffff00': 'Amarelo',
            '#00ffff': 'Ciano',
            '#ff00ff': 'Magenta',
            '#eee': 'Cinza claro',
            '#eeeeee': 'Cinza claro'
        };

        var layerSelect = document.getElementById('layer-select');
        layerSelect.innerHTML = '';

        // Opção para editar todas as camadas
        var allLayersOption = document.createElement('option');
        allLayersOption.value = 'all';
        allLayersOption.textContent = 'Todas as Camadas';
        layerSelect.appendChild(allLayersOption);

        if (!stickerGroup) return;

        // Agrupa as camadas pelo valor do fill
        var groups = {};
        stickerGroup.getChildren().forEach((child) => {
            var fillColor = child.fill();
            if (!fillColor) {
                fillColor = '#000000';
            }
            fillColor = fillColor.toLowerCase();
            if (!groups[fillColor]) {
                groups[fillColor] = [];
            }
            groups[fillColor].push(child);
        });

        // Cria uma opção para cada grupo de cor
        for (var fillColor in groups) {
            var count = groups[fillColor].length;
            var option = document.createElement('option');
            option.value = fillColor;
            var friendlyName = colorNames[fillColor] || fillColor;
            option.textContent = `Cor ${friendlyName} (${count} camada${count > 1 ? 's' : ''})`;
            layerSelect.appendChild(option);
        }
    }

    // -------------------
    //  FUNÇÕES DE HISTÓRICO (UNDO/REDO)
    // -------------------
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

    // -------------------
    //  FUNÇÃO DE EDIÇÃO DE TEXTO
    // -------------------
    function enableTextEditing(textNode) {
        textNode.on('dblclick', function () {
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
            area.style.border = 'none';
            area.style.padding = '0px';
            area.style.margin = '0px';
            area.style.overflow = 'hidden';
            area.style.background = 'none';
            area.style.outline = 'none';
            area.style.resize = 'none';
            area.style.transform = 'rotate(' + textNode.rotation() + 'deg)';
            area.style.lineHeight = textNode.lineHeight();
            area.style.minWidth = '50px';

            area.focus();

            area.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    textNode.text(area.value);
                    textNode.show();
                    layer.draw();
                    document.body.removeChild(area);
                    saveHistory();
                }
            });

            area.addEventListener('blur', function () {
                textNode.text(area.value);
                textNode.show();
                layer.draw();
                document.body.removeChild(area);
                saveHistory();
            });
        });
    }

    // -------------------
    //  EVENTOS PRINCIPAIS
    // -------------------

    // Alterar cor das camadas
    document.getElementById('cor').addEventListener('input', function (event) {
        var newColor = event.target.value;
        var layerSelect = document.getElementById('layer-select');
        var selectedGroup = layerSelect.value;

        if (!stickerGroup) return;

        if (selectedGroup === 'all') {
            stickerGroup.getChildren().forEach(child => child.fill(newColor));
        } else {
            stickerGroup.getChildren().forEach(child => {
                if (child.fill() === selectedGroup) {
                    child.fill(newColor);
                }
            });
        }
        layer.draw();
    });

    document.getElementById('cor').addEventListener('change', function () {
        saveHistory();
        preencherSeletorDeCamadas();
    });

    // Adicionar texto
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

        enableTextEditing(newTextObject);

        document.getElementById('texto').value = '';
        if (tempTextObject) {
            tempTextObject.destroy();
            tempTextObject = null;
        }
        saveHistory();
    });

    // Atualização prévia do texto (preview)
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

    var tempTextObject = null;
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
                draggable: true,
                rotation: rotation
            });
            tempTextObject.on('dragend', saveHistory);
            layer.add(tempTextObject);
        } else {
            tempTextObject.text(textContent);
            tempTextObject.fontSize(fontSize);
            tempTextObject.fontFamily(fontFamily);
            tempTextObject.fill(fillColor);
            tempTextObject.rotation(rotation);
        }
        tempTextObject.moveToTop();
        layer.draw();
    }

    document.getElementById('texto').addEventListener('input', atualizarTextoNoCanvas);
    document.getElementById('tamanho-fonte').addEventListener('input', atualizarTextoNoCanvas);
    document.getElementById('fontPicker').addEventListener('change', atualizarTextoNoCanvas);
    document.getElementById('cor-texto').addEventListener('input', atualizarTextoNoCanvas);
    document.getElementById('rotacao-texto').addEventListener('input', atualizarTextoNoCanvas);

    document.getElementById('rotacao-texto').addEventListener('input', function () {
        document.getElementById('rotacao-texto-valor').value = this.value;
    });
    document.getElementById('rotacao-texto-valor').addEventListener('input', function () {
        document.getElementById('rotacao-texto').value = this.value;
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

    document.getElementById("salvar-adesivo-botao").addEventListener("click", function (e) {
        e.preventDefault();

        let checkbox = document.getElementById("aceito-termos");

        if (!checkbox.checked) {
            let confirmacao = confirm("Você precisa aceitar os termos antes de continuar. Deseja aceitar agora?");
            if (confirmacao) {
                checkbox.checked = true;
            } else {
                return;
            }
        }

        const adesivoData = stage.toDataURL({ mimeType: "image/png" });

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
                    window.location.href = data.data.cart_url;
                } else {
                    alert("Erro: " + data.data.message);
                }
            })
            .catch(error => console.error("Erro no AJAX:", error));
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
