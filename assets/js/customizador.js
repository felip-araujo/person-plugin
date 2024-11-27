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
    var scaleBy = 1.05; // Fator de zoom

    // Verifica se a URL do adesivo está disponível
    if (typeof pluginData !== 'undefined' && pluginData.stickerUrl) {
        console.log('Dados do plugin:', pluginData); // Log para verificar os dados do plugin
        carregarAdesivo(pluginData.stickerUrl);
    } else {
        console.error('pluginData ou stickerUrl não está definido.');
    }

    // Função para carregar o adesivo
    function carregarAdesivo(stickerUrl) {
        console.log('Tentando carregar o adesivo na URL:', stickerUrl);

        fetch(stickerUrl)
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`Erro ao carregar o adesivo: HTTP ${response.status}`);
                }
                console.log('Adesivo carregado com sucesso, processando SVG...');
                return response.text();
            })
            .then((svgText) => {
                var parser = new DOMParser();
                var svgDoc = parser.parseFromString(svgText, 'image/svg+xml');

                // Verifica se houve erro no parsing do SVG
                if (svgDoc.querySelector('parsererror')) {
                    throw new Error('Erro ao analisar o SVG. Verifique a estrutura do arquivo.');
                }

                console.log('SVG analisado com sucesso:', svgDoc);

                // Limpa os elementos anteriores
                layer.destroyChildren();

                stickerGroup = new Konva.Group({
                    draggable: false, // O grupo em si não é arrastável
                });

                // Processa os elementos do SVG e os converte para Konva Paths
                Array.from(svgDoc.querySelectorAll('path, rect, circle, ellipse, polygon, polyline, line')).forEach(
                    (elem) => {
                        console.log('Processando elemento SVG:', elem);
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
                            });
                            stickerGroup.add(path);
                        }
                    }
                );

                layer.add(stickerGroup);
                ajustarTamanhoDoAdesivo();
                layer.draw();

                console.log('Adesivo renderizado com sucesso no canvas!');
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

    // Ajusta o tamanho do adesivo para caber no canvas
    function ajustarTamanhoDoAdesivo() {
        if (!stickerGroup) {
            console.warn('Nenhum adesivo encontrado para ajustar o tamanho.');
            return;
        }

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

    // Confirma se o container do canvas existe
    var canvasContainer = document.getElementById('adesivo-canvas');
    if (!canvasContainer) {
        console.error('O elemento #adesivo-canvas não foi encontrado no DOM.');
    } else {
        console.log('Elemento #adesivo-canvas encontrado:', canvasContainer);
    }
});
