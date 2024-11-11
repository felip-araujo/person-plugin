var canvas = new fabric.Canvas('adesivo-canvas');
var loadedSticker; // Variável para armazenar o adesivo carregado
var stickerLayers = []; // Armazena cada camada individual do SVG

// Função para carregar o adesivo SVG e identificar as camadas
document.getElementById('load-sticker').addEventListener('click', function() {
    var selectedSticker = document.getElementById('sticker-select').value;
    var stickerPath = '../wp-content/plugins/person-plugin/assets/stickers/' + selectedSticker;
    
    // Carrega o adesivo SVG com Fabric.js
    fabric.loadSVGFromURL(stickerPath, function(objects, options) {
        if (loadedSticker) {
            canvas.remove(loadedSticker); // Remove o adesivo anterior do canvas
        }
        
        loadedSticker = fabric.util.groupSVGElements(objects, options);
        canvas.clear(); // Limpa o canvas antes de adicionar o novo adesivo
        canvas.add(loadedSticker).renderAll(); // Adiciona o adesivo ao canvas
        
        // Armazena as camadas individualmente
        stickerLayers = loadedSticker.getObjects();
        
        // Popula a lista de seleção de camadas para o usuário
        populateLayerSelector();
    });
});

// Função para preencher a lista de seleção com as camadas do adesivo
function populateLayerSelector() {
    var layerSelect = document.getElementById('layer-select');
    layerSelect.innerHTML = ''; // Limpa o seletor
    
    stickerLayers.forEach((layer, index) => {
        var option = document.createElement('option');
        option.value = index;
        option.text = `Camada ${index + 1}`;
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

    if (selectedLayer && selectedLayer.type === 'path') {
        selectedLayer.set('fill', color); // Define a cor da camada selecionada
        canvas.renderAll(); // Atualiza o canvas após alterar a cor
    }
}
