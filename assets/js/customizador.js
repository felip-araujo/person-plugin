// /var/www/html/wp-content/plugins/person-plugin/assets/js/customizador.js

document.addEventListener('DOMContentLoaded', function() {
    if (typeof pluginData !== 'undefined' && pluginData.stickerUrl) {
        loadSticker();
    } else {
        console.error('pluginData ou stickerUrl não está definido.');
    }
});

// Inicialização do stage e layer com o Konva.js
var stage = new Konva.Stage({
    container: 'adesivo-canvas',
    width: 1150,
    height: 400
});

var layer = new Konva.Layer();
stage.add(layer);

var stickerLayers = []; // Armazena as camadas individuais do SVG
var textObject; // Variável para armazenar o objeto de texto
var stickerGroup; // Variável para o grupo do adesivo

// Função para carregar o adesivo SVG e identificar as camadas
function loadSticker() {
    var stickerUrl = pluginData.stickerUrl;

    // Verifica se o URL do adesivo está definido
    if (!stickerUrl) {
        console.error('URL do adesivo não está definido.');
        return;
    }

    // Log para depuração
    console.log('Carregando adesivo do URL:', stickerUrl);

    // Carrega o adesivo SVG usando fetch
    fetch(stickerUrl)
        .then(function(response) {
            console.log('Resposta do fetch:', response);
            return response.text();
        })
        .then(function(svgText) {
            console.log('SVG carregado:', svgText);
            var parser = new DOMParser();
            var svgDoc = parser.parseFromString(svgText, "image/svg+xml");

            // Limpa o layer e o array de camadas
            layer.destroyChildren();
            stickerLayers = [];

            // Cria um grupo para o adesivo
            stickerGroup = new Konva.Group({
                draggable: true // Torna o grupo do adesivo arrastável
            });

            // Seleciona todos os elementos SVG relevantes
            var svgElements = svgDoc.querySelectorAll('path, rect, circle, ellipse, polygon, polyline, line');

            for (var i = 0; i < svgElements.length; i++) {
                var elem = svgElements[i];
                var pathData = elem.getAttribute('d');
                var fillColor = elem.getAttribute('fill') || elem.style.fill || '#000';
                var id = elem.getAttribute('id') || 'Camada ' + (i + 1);

                // Verifica se pathData está definido (alguns elementos como <rect> podem não ter 'd')
                if (!pathData) {
                    // Converte outros elementos para Path
                    pathData = getPathDataFromShape(elem);
                }

                if (pathData) {
                    var path = new Konva.Path({
                        data: pathData,
                        fill: fillColor,
                        id: id,
                        draggable: false // Individualmente, as camadas não são arrastáveis
                    });

                    stickerLayers.push(path);
                    stickerGroup.add(path); // Adiciona o path ao grupo do adesivo
                }
            }

            layer.add(stickerGroup); // Adiciona o grupo ao layer
            layer.draw();

            // Popula a lista de seleção de camadas para o usuário
            populateLayerSelector();
        })
        .catch(function(error) {
            console.error('Erro ao carregar o SVG:', error);
        });
}

// Função para converter formas básicas em pathData
function getPathDataFromShape(shapeNode) {
    var tagName = shapeNode.tagName.toLowerCase();
    var pathData = '';
    switch (tagName) {
        case 'rect':
            var x = parseFloat(shapeNode.getAttribute('x')) || 0;
            var y = parseFloat(shapeNode.getAttribute('y')) || 0;
            var width = parseFloat(shapeNode.getAttribute('width'));
            var height = parseFloat(shapeNode.getAttribute('height'));
            pathData = `M${x},${y} h${width} v${height} h${-width} Z`;
            break;
        case 'circle':
            var cx = parseFloat(shapeNode.getAttribute('cx'));
            var cy = parseFloat(shapeNode.getAttribute('cy'));
            var r = parseFloat(shapeNode.getAttribute('r'));
            pathData = `M${cx - r},${cy} a${r},${r} 0 1,0 ${2 * r},0 a${r},${r} 0 1,0 ${-2 * r},0`;
            break;
        // Adicione casos para outras formas se necessário
        default:
            console.warn('Elemento SVG não suportado:', tagName);
            break;
    }
    return pathData;
}

// Função para preencher a lista de seleção com as camadas do adesivo
function populateLayerSelector() {
    var layerSelect = document.getElementById('layer-select');
    layerSelect.innerHTML = ''; // Limpa o seletor

    stickerLayers.forEach((layerItem, index) => {
        var option = document.createElement('option');
        option.value = index;
        // Usa IDs ou nomes das camadas se disponíveis
        var layerName = layerItem.id() || `Camada ${index + 1}`;
        option.text = layerName;
        layerSelect.appendChild(option);
    });
}

// Atualizar a cor da camada selecionada
document.getElementById('cor').addEventListener('input', function(event) {
    var color = event.target.value;
    updateSelectedLayerColor(color);
});

// Função para atualizar a cor da camada selecionada
function updateSelectedLayerColor(color) {
    var layerIndex = document.getElementById('layer-select').value;
    var selectedLayer = stickerLayers[layerIndex];

    if (selectedLayer) {
        selectedLayer.fill(color); // Define a cor da camada selecionada
        layer.draw(); // Atualiza o layer após alterar a cor
    }
}

// Adicionar texto ao canvas
document.getElementById('texto').addEventListener('input', function(event) {
    var textContent = event.target.value;
    updateCanvasText(textContent);
});

// Atualizar tamanho da fonte
document.getElementById('tamanho-fonte').addEventListener('input', function(event) {
    var fontSize = event.target.value;
    updateTextFontSize(fontSize);
});

// Atualizar família da fonte
document.getElementById('fontPicker').addEventListener('change', function(event) {
    var fontFamily = event.target.value;
    updateTextFontFamily(fontFamily);
});

// Atualizar cor do texto
document.getElementById('cor-texto').addEventListener('input', function(event) {
    var fontColor = event.target.value;
    updateTextFontColor(fontColor);
});

function updateCanvasText(textContent) {
    if (textObject) {
        textObject.text(textContent);
    } else {
        if (textContent.trim() !== '') {
            textObject = new Konva.Text({
                x: 100,
                y: 100,
                text: textContent,
                fontSize: parseInt(document.getElementById('tamanho-fonte').value),
                fontFamily: document.getElementById('fontPicker').value,
                fill: document.getElementById('cor-texto').value,
                draggable: true
            });
            layer.add(textObject);
        }
    }
    layer.draw();
}

function updateTextFontSize(fontSize) {
    if (textObject) {
        textObject.fontSize(parseInt(fontSize));
        layer.draw();
    }
}

function updateTextFontFamily(fontFamily) {
    if (textObject) {
        textObject.fontFamily(fontFamily);
        layer.draw();
    }
}

function updateTextFontColor(fontColor) {
    if (textObject) {
        textObject.fill(fontColor);
        layer.draw();
    }
}
