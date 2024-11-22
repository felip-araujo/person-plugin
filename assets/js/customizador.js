// /var/www/html/wp-content/plugins/person-plugin/assets/js/customizador.js

document.addEventListener('DOMContentLoaded', function () {
    var stage = new Konva.Stage({
        container: 'adesivo-canvas',
        width: 1150,
        height: 620,
        draggable: true, // Permite arrastar o stage para visualizar áreas fora da tela durante o zoom
    });

    var layer = new Konva.Layer();
    stage.add(layer);

    var stickerGroup = null;
    var tempTextObject = null;

    var scaleBy = 1.05; // Fator de zoom

    if (typeof pluginData !== 'undefined' && pluginData.stickerUrl) {
        carregarAdesivo();
    } else {
        console.error('pluginData ou stickerUrl não está definido.');
    }

    function carregarAdesivo() {
        var stickerUrl = pluginData.stickerUrl;

        fetch(stickerUrl)
            .then((response) => response.text())
            .then((svgText) => {
                var parser = new DOMParser();
                var svgDoc = parser.parseFromString(svgText, 'image/svg+xml');

                layer.destroyChildren();

                stickerGroup = new Konva.Group({
                    draggable: false, // O grupo em si não é arrastável; o stage é
                });

                Array.from(svgDoc.querySelectorAll('path, rect, circle, ellipse, polygon, polyline, line')).forEach(
                    (elem) => {
                        var pathData = elem.getAttribute('d') || '';
                        var fillColor = elem.getAttribute('fill') || '#000';

                        if (!pathData) {
                            // Converte outros elementos para Path
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

                preencherSeletorDeCamadas();
            })
            .catch((error) => console.error('Erro ao carregar o adesivo:', error));
    }

    function obterPathDataDeForma(shapeNode) {
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

    function ajustarTamanhoDoAdesivo() {
        if (!stickerGroup) return;

        // Obtém o tamanho do adesivo
        var stickerRect = stickerGroup.getClientRect({ relativeTo: stickerGroup });

        // Calcula a escala necessária para caber no canvas com margem
        var margin = 10; // margem de 10 pixels
        var availableWidth = stage.width() - margin * 2;
        var availableHeight = stage.height() - margin * 2;

        var scaleX = availableWidth / stickerRect.width;
        var scaleY = availableHeight / stickerRect.height;
        var scale = Math.min(scaleX, scaleY);

        // Aplica a escala ao grupo do adesivo
        stickerGroup.scale({ x: scale, y: scale });

        // Recalcula o tamanho após a escala
        var newStickerRect = stickerGroup.getClientRect({ relativeTo: stickerGroup });

        // Centraliza o adesivo no canvas
        stickerGroup.position({
            x: (stage.width() - newStickerRect.width) / 2 - newStickerRect.x,
            y: (stage.height() - newStickerRect.height) / 2 - newStickerRect.y,
        });

        layer.draw();
    }

    function preencherSeletorDeCamadas() {
        var layerSelect = document.getElementById('layer-select');
        layerSelect.innerHTML = '';

        stickerGroup.getChildren().forEach((child, index) => {
            var option = document.createElement('option');
            option.value = index;
            var layerName = child.id() || `Camada ${index + 1}`;
            option.text = layerName;
            layerSelect.appendChild(option);
        });
    }

    document.getElementById('cor').addEventListener('input', function (event) {
        var color = event.target.value;
        atualizarCorDaCamadaSelecionada(color);
    });

    document.getElementById('layer-select').addEventListener('change', function (event) {
        var layerIndex = event.target.value;
        var selectedLayer = stickerGroup.getChildren()[layerIndex];

        if (selectedLayer) {
            var currentColor = selectedLayer.fill();
            document.getElementById('cor').value = currentColor;
        }
    });

    function atualizarCorDaCamadaSelecionada(color) {
        var layerIndex = document.getElementById('layer-select').value;
        var selectedLayer = stickerGroup.getChildren()[layerIndex];

        if (selectedLayer) {
            selectedLayer.fill(color);
            layer.draw();
        }
    }

    // Eventos para manipulação do texto
    document.getElementById('texto').addEventListener('input', atualizarTextoNoCanvas);
    document.getElementById('tamanho-fonte').addEventListener('input', atualizarTextoNoCanvas);
    document.getElementById('fontPicker').addEventListener('change', atualizarTextoNoCanvas);
    document.getElementById('cor-texto').addEventListener('input', atualizarTextoNoCanvas);
    document.getElementById('rotacao-texto').addEventListener('input', atualizarTextoNoCanvas);

    function atualizarTextoNoCanvas() {
        var textContent = document.getElementById('texto').value;

        if (!tempTextObject) {
            if (textContent.trim() === '') {
                return;
            }
            tempTextObject = new Konva.Text({
                x: stage.width() / 2,
                y: stage.height() / 2,
                text: textContent,
                fontSize: parseInt(document.getElementById('tamanho-fonte').value),
                fontFamily: document.getElementById('fontPicker').value,
                fill: document.getElementById('cor-texto').value,
                draggable: true,
                rotation: parseFloat(document.getElementById('rotacao-texto').value)
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

    document.getElementById('adicionar-texto-botao').addEventListener('click', function () {
        adicionarTextoAoAdesivo();
    });

    function adicionarTextoAoAdesivo() {
        if (tempTextObject) {
            // Obter a posição absoluta do texto temporário
            var absPos = tempTextObject.getAbsolutePosition();
    
            // Transformar a posição absoluta para o sistema de coordenadas do stickerGroup
            var relativePos = stickerGroup.getAbsoluteTransform().copy().invert().point(absPos);
    
            // Obter o scale total aplicado ao texto
            var totalScale = tempTextObject.getAbsoluteScale();
    
            // Calcular a escala relativa ao stickerGroup
            var stickerScale = stickerGroup.getAbsoluteScale();
    
            // Ajustar o tamanho da fonte
            var adjustedFontSize = tempTextObject.fontSize() * (totalScale.x / stickerScale.x);
    
            // Calcular a rotação relativa ao stickerGroup
            var adjustedRotation = tempTextObject.getAbsoluteRotation() - stickerGroup.getAbsoluteRotation();
    
            // Clonar o texto com as propriedades corretas
            var textoFinal = new Konva.Text({
                x: relativePos.x,
                y: relativePos.y,
                text: tempTextObject.text(),
                fontSize: adjustedFontSize,
                fontFamily: tempTextObject.fontFamily(),
                fill: tempTextObject.fill(),
                rotation: adjustedRotation,
                draggable: false
            });
    
            // Adicionar o texto ao grupo do adesivo
            stickerGroup.add(textoFinal);
    
            // Remover o texto temporário
            tempTextObject.destroy();
            tempTextObject = null;
    
            layer.draw();
    
            // Limpar os campos de entrada de texto
            document.getElementById('texto').value = '';
            document.getElementById('tamanho-fonte').value = 16;
            document.getElementById('fontPicker').value = 'Arial';
            document.getElementById('cor-texto').value = '#000000';
            document.getElementById('rotacao-texto').value = 0;
        }
    }
    

    document.getElementById('salvar-adesivo-botao').addEventListener('click', function () {
        salvarAdesivo();
    });

    function salvarAdesivo() {
        if (!stickerGroup) {
            alert('Nenhum adesivo para salvar.');
            return;
        }

        // Exporta o stickerGroup como SVG
        var svg = stickerGroup.toSVG();

        // Envia os dados SVG para o servidor via AJAX
        var xhr = new XMLHttpRequest();
        xhr.open('POST', pluginData.ajaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        xhr.onload = function () {
            if (xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    alert('Adesivo salvo com sucesso!');
                } else {
                    alert('Erro ao salvar o adesivo: ' + response.data);
                }
            } else {
                alert('Erro ao salvar o adesivo.');
            }
        };

        var params = 'action=salvar_adesivo&svg=' + encodeURIComponent(svg);
        xhr.send(params);
    }

    // Evento de scroll para zoom in e zoom out
    stage.on('wheel', (e) => {
        e.evt.preventDefault();

        var oldScale = stage.scaleX();

        var pointer = stage.getPointerPosition();

        var mousePointTo = {
            x: (pointer.x - stage.x()) / oldScale,
            y: (pointer.y - stage.y()) / oldScale,
        };

        var direction = e.evt.deltaY > 0 ? 1 : -1;

        var newScale = direction > 0 ? oldScale * scaleBy : oldScale / scaleBy;
        newScale = Math.max(0.5, Math.min(newScale, 10)); // Limita o zoom entre 0.5x e 10x

        stage.scale({ x: newScale, y: newScale });

        var newPos = {
            x: pointer.x - mousePointTo.x * newScale,
            y: pointer.y - mousePointTo.y * newScale,
        };

        stage.position(newPos);
        stage.batchDraw();
    });
});
