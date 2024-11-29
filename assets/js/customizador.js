//variáveis globais de posicao do adesivo na caixa de preview
var initialStageScale = { x: 1, y: 1 };
var initialStagePosition = { x: 0, y: 0 };
var initialStickerScale = { x: 1, y: 1 };
var initialStickerPosition = { x: 0, y: 0 };

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
            })
            .catch((error) => console.error('Erro ao carregar o adesivo:', error));
    }

    // Converte outros elementos SVG para Path
    function obterPathDataDeForma(shapeNode) {
        var tagName = shapeNode.tagName.toLowerCase();
        switch (tagName) {
            case 'rect':
                var x = parseFloat(shapeNode.getAttribute('x')) || 0;
                var y = parseFloat(shapeNode.getAttribute('y')) || 0;
                var width = parseFloat(shapeNode.getAttribute('width')) || 0;
                var height = parseFloat(shapeNode.getAttribute('height')) || 0;
                return `M${x},${y} h${width} v${height} h${-width} Z`;
            case 'circle':
                var cx = parseFloat(shapeNode.getAttribute('cx')) || 0;
                var cy = parseFloat(shapeNode.getAttribute('cy')) || 0;
                var r = parseFloat(shapeNode.getAttribute('r')) || 0;
                return `M${cx - r},${cy} a${r},${r} 0 1,0 ${2 * r},0 a${r},${r} 0 1,0 ${-2 * r},0`;
            default:
                console.warn('Elemento SVG não suportado:', tagName);
                return '';
        }
    }

    // Preenche o seletor de camadas dinamicamente
    function preencherSeletorDeCamadas() {
        var layerSelect = document.getElementById('layer-select');
        layerSelect.innerHTML = '';

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

        if (selectedLayerId === 'all') {
            stickerGroup.getChildren().forEach((layer) => layer.fill(selectedColor));
        } else {
            var selectedLayer = stickerGroup.findOne(`#${selectedLayerId}`);
            if (selectedLayer) selectedLayer.fill(selectedColor);
        }

        layer.draw();
    });

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
    
        layer.add(newTextObject);
        layer.draw();
    
        // Limpar a caixa de texto e o objeto temporário
        document.getElementById('texto').value = '';
        if (tempTextObject) {
            tempTextObject.destroy();
            tempTextObject = null;
        }
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
        stickerGroup.scale(initialStickerScale);
        stickerGroup.position(initialStickerPosition);
    
        stage.batchDraw();
    });

    stage.on('wheel', function (e) {
        e.evt.preventDefault();
        var direction = e.evt.deltaY > 0 ? 'out' : 'in';
        aplicarZoom(direction);
    });
    
});
