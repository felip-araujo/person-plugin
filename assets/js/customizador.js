
document.addEventListener('DOMContentLoaded', function () {
    var stage = new Konva.Stage({
        container: 'adesivo-canvas', // Certifique-se de que este ID existe no HTML
        width: 1150,
        height: 620,
        draggable: true, // Permite arrastar o stage
    });

    var layer = new Konva.Layer();
    stage.add(layer);

    var stickerGroup = null;
    var tempTextObject = null;

    // Verifica se a URL do adesivo está disponível
    if (typeof pluginData !== 'undefined' && pluginData.stickerUrl) {
        console.log('URL do adesivo:', pluginData.stickerUrl); // Log para verificar a URL
        carregarAdesivo(pluginData.stickerUrl);
    } else {
        console.error('pluginData ou stickerUrl não está definido.');
    }

    // Função para carregar o adesivo
    function carregarAdesivo(stickerUrl) {
        fetch(stickerUrl)
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Erro ao carregar o adesivo: ' + response.status);
                }
                return response.text();
            })
            .then((svgText) => {
                var parser = new DOMParser();
                var svgDoc = parser.parseFromString(svgText, 'image/svg+xml');

                // Limpa os elementos anteriores
                layer.destroyChildren();

                stickerGroup = new Konva.Group({
                    draggable: false, // O grupo em si não é arrastável
                });

                Array.from(svgDoc.querySelectorAll('path, rect, circle, ellipse, polygon, polyline, line')).forEach(
                    (elem, index) => {
                        var pathData = elem.getAttribute('d') || '';
                        var fillColor = elem.getAttribute('fill') || '#000';

                        if (!pathData) {
                            pathData = obterPathDataDeForma(elem);
                        }

                        if (pathData) {
                            var path = new Konva.Path({
                                data: pathData,
                                fill: fillColor,
                                draggable: false,
                                id: `layer-${index}`, // Define um ID único para cada camada
                            });
                            stickerGroup.add(path);
                        }
                    }
                );

                layer.add(stickerGroup);
                ajustarTamanhoDoAdesivo();
                preencherSeletorDeCamadas(); // Preenche o seletor com as camadas
                layer.draw();
            })
            .catch((error) => {
                console.error('Erro ao carregar o adesivo:', error);
            });
    }

    // Converte outros elementos SVG para Path, se necessário
    function obterPathDataDeForma(shapeNode) {
        var tagName = shapeNode.tagName.toLowerCase();
        var pathData = '';
        switch (tagName) {
            case 'rect':
                var x = parseFloat(shapeNode.getAttribute('x')) || 0;
                var y = parseFloat(shapeNode.getAttribute('y')) || 0;
                var width = parseFloat(shapeNode.getAttribute('width')) || 0;
                var height = parseFloat(shapeNode.getAttribute('height')) || 0;
                pathData = `M${x},${y} h${width} v${height} h${-width} Z`;
                break;
            case 'circle':
                var cx = parseFloat(shapeNode.getAttribute('cx')) || 0;
                var cy = parseFloat(shapeNode.getAttribute('cy')) || 0;
                var r = parseFloat(shapeNode.getAttribute('r')) || 0;
                pathData = `M${cx - r},${cy} a${r},${r} 0 1,0 ${2 * r},0 a${r},${r} 0 1,0 ${-2 * r},0`;
                break;
            default:
                console.warn('Elemento SVG não suportado:', tagName);
                break;
        }
        return pathData;
    }

    // Preenche o seletor de camadas dinamicamente
    function preencherSeletorDeCamadas() {
        var layerSelect = document.getElementById('layer-select');
        layerSelect.innerHTML = ''; // Limpa o seletor antes de preenchê-lo
    
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
            // Aplica a cor a todas as camadas
            stickerGroup.getChildren().forEach((layer) => {
                layer.fill(selectedColor);
            });
        } else {
            // Aplica a cor apenas à camada selecionada
            var selectedLayer = stickerGroup.findOne(`#${selectedLayerId}`);
            if (selectedLayer) {
                selectedLayer.fill(selectedColor);
            }
        }
    
        layer.draw(); // Redesenha o canvas
    });

    // Função para ajustar o tamanho do adesivo
    function ajustarTamanhoDoAdesivo() {
        if (!stickerGroup) return;

        var stickerRect = stickerGroup.getClientRect();
        var margin = 10; // Margem de 10px
        var availableWidth = stage.width() - margin * 2;
        var availableHeight = stage.height() - margin * 2;

        var scaleX = availableWidth / stickerRect.width;
        var scaleY = availableHeight / stickerRect.height;
        var scale = Math.min(scaleX, scaleY);

        stickerGroup.scale({ x: scale, y: scale });

        var newStickerRect = stickerGroup.getClientRect();

        stickerGroup.position({
            x: (stage.width() - newStickerRect.width) / 2 - newStickerRect.x,
            y: (stage.height() - newStickerRect.height) / 2 - newStickerRect.y,
        });

        layer.draw();
    }

    // Eventos para manipulação do texto
document.getElementById('texto').addEventListener('input', atualizarTextoNoCanvas);
document.getElementById('tamanho-fonte').addEventListener('input', atualizarTextoNoCanvas);
document.getElementById('fontPicker').addEventListener('change', atualizarTextoNoCanvas);
document.getElementById('cor-texto').addEventListener('input', atualizarTextoNoCanvas);
document.getElementById('rotacao-texto').addEventListener('input', atualizarTextoNoCanvas);

// Objeto temporário para manipulação do texto
var tempTextObject = null;

function atualizarTextoNoCanvas() {
    var textContent = document.getElementById('texto').value;

    if (!tempTextObject) {
        if (textContent.trim() === '') {
            return; // Não faz nada se o texto estiver vazio
        }

        // Cria um novo texto temporário
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
        // Atualiza as propriedades do texto existente
        tempTextObject.text(textContent);
        tempTextObject.fontSize(parseInt(document.getElementById('tamanho-fonte').value));
        tempTextObject.fontFamily(document.getElementById('fontPicker').value);
        tempTextObject.fill(document.getElementById('cor-texto').value);
        tempTextObject.rotation(parseFloat(document.getElementById('rotacao-texto').value));
    }

    layer.draw(); // Redesenha o canvas
}

// Evento para adicionar o texto ao adesivo
document.getElementById('adicionar-texto-botao').addEventListener('click', function () {
    adicionarTextoAoAdesivo();
});

function adicionarTextoAoAdesivo() {
    if (tempTextObject) {
        // Clona o texto temporário para o grupo do adesivo
        var textoFinal = new Konva.Text({
            x: tempTextObject.x(),
            y: tempTextObject.y(),
            text: tempTextObject.text(),
            fontSize: tempTextObject.fontSize(),
            fontFamily: tempTextObject.fontFamily(),
            fill: tempTextObject.fill(),
            rotation: tempTextObject.rotation(),
            draggable: true,
        });

        stickerGroup.add(textoFinal);
        tempTextObject.destroy(); // Remove o texto temporário
        tempTextObject = null;

        layer.draw();

        // Limpa os campos de entrada de texto
        document.getElementById('texto').value = '';
        document.getElementById('tamanho-fonte').value = 16;
        document.getElementById('fontPicker').value = 'Arial';
        document.getElementById('cor-texto').value = '#000000';
        document.getElementById('rotacao-texto').value = 0;
    } else {
        alert('Por favor, insira um texto antes de adicioná-lo.');
    }
}



});
