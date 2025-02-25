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

// =====================================================================
// FUNÇÃO: Extrair e converter gradiente linear de um SVG (Fabric.js não altera isso)
// =====================================================================
function parseLinearGradient(gradientElem) {
    // Converte valores percentuais ou numéricos para decimal
    function toDecimal(val) {
        if (val.indexOf('%') !== -1) {
            return parseFloat(val) / 100;
        }
        return parseFloat(val);
    }
    var x1 = gradientElem.getAttribute('x1') || '0%';
    var y1 = gradientElem.getAttribute('y1') || '0%';
    var x2 = gradientElem.getAttribute('x2') || '100%';
    var y2 = gradientElem.getAttribute('y2') || '0%';
    x1 = toDecimal(x1);
    y1 = toDecimal(y1);
    x2 = toDecimal(x2);
    y2 = toDecimal(y2);
    var stops = [];
    var stopElems = gradientElem.querySelectorAll('stop');
    stopElems.forEach(function(stop) {
        var offset = stop.getAttribute('offset') || '0%';
        offset = toDecimal(offset);
        var color = stop.getAttribute('stop-color') || '#000000';
        var opacity = stop.getAttribute('stop-opacity');
        if (opacity && opacity !== '1') {
            color = color; // Aqui você pode melhorar a conversão para RGBA se necessário
        }
        stops.push({ offset: offset, color: color });
    });
    stops.sort(function(a, b) { return a.offset - b.offset; });
    var gradientColorStops = [];
    stops.forEach(function(s) {
        gradientColorStops.push(s.offset);
        gradientColorStops.push(s.color);
    });
    return {
        colorStops: gradientColorStops,
        startPoint: { x: x1, y: y1 },
        endPoint: { x: x2, y: y2 }
    };
}

// =====================================================================
// FUNÇÃO: Permitir a edição de gradiente (exemplo simples com prompt)
// =====================================================================
function enableGradientEditing(shape) {
    var currentStops = shape.fillLinearGradientColorStops();
    if (currentStops && currentStops.length >= 2) {
        var newColor = prompt("Editar cor do primeiro stop (atual: " + currentStops[1] + "):", currentStops[1]);
        if (newColor) {
            currentStops[1] = newColor;
            shape.fillLinearGradientColorStops(currentStops);
            layer.draw();
            saveHistory();
        }
    } else {
        alert("Este objeto não possui gradiente configurado.");
    }
}

// =====================================================================
// FUNÇÃO: Carregar o adesivo usando Fabric.js para melhor suporte a gradientes
// =====================================================================
function carregarAdesivo(stickerUrl) {
    // Usa o fabric.js para carregar o SVG
    fabric.loadSVGFromURL(stickerUrl, function(objects, options) {
        var fabricGroup = fabric.util.groupSVGElements(objects, options);
        // Cria um canvas temporário off-screen para renderizar o grupo
        var tempCanvasEl = document.createElement('canvas');
        tempCanvasEl.width = fabricGroup.width;
        tempCanvasEl.height = fabricGroup.height;
        var tempFabricCanvas = new fabric.Canvas(tempCanvasEl, { renderOnAddRemove: false });
        tempFabricCanvas.add(fabricGroup);
        tempFabricCanvas.renderAll();
        // Exporta para dataURL (PNG)
        var dataURL = tempFabricCanvas.toDataURL({ format: 'png', quality: 1 });
        
        // Cria um objeto de imagem do Konva a partir do dataURL
        var konvaImage = new Konva.Image({
            x: 0,
            y: 0,
            draggable: false
        });
        var imgObj = new Image();
        imgObj.onload = function() {
            konvaImage.image(imgObj);
            // Opcional: ajuste a escala para encaixar no canvas
            var scaleX = stage.width() / imgObj.width;
            var scaleY = stage.height() / imgObj.height;
            var scale = Math.min(scaleX, scaleY);
            konvaImage.scale({ x: scale, y: scale });
            layer.add(konvaImage);
            layer.draw();
            saveHistory();
        };
        imgObj.src = dataURL;
        
        // Opcional: para permitir edição via Fabric.js, pode atribuir um duplo clique
        konvaImage.on('dblclick', function() {
            alert("Aqui você pode abrir um editor Fabric para ajustar gradientes.");
            // Neste ponto, você pode implementar uma interface modal que permita editar o grupo fabricGroup
            // e, após edição, atualizar a imagem do Konva.
        });
    });
}

// =====================================================================
// EVENTO DOMContentLoaded: Inicializa o editor
// =====================================================================
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
        // Aqui, ao carregar o adesivo, usamos Fabric.js para suportar gradientes
        carregarAdesivo(pluginData.stickerUrl);
    }

    // =====================================================================
    // FUNÇÃO: Converter estilos inline no SVG (usada para fallback)
    // =====================================================================
    function converterEstilosInline(svgText) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(svgText, 'image/svg+xml');
        const styleElements = doc.querySelectorAll('style');
        let cssText = '';
        styleElements.forEach(function(styleEl) {
            cssText += styleEl.textContent;
        });
        const regras = {};
        cssText.replace(/\.([^ \n{]+)\s*\{([^}]+)\}/g, function(match, className, declarations) {
            const props = {};
            declarations.split(';').forEach(function(decl) {
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
        elemsComClasse.forEach(function(elem) {
            const classes = elem.getAttribute('class').split(/\s+/);
            classes.forEach(function(cls) {
                if (regras[cls] && regras[cls].fill) {
                    elem.setAttribute('fill', regras[cls].fill);
                }
            });
        });
        styleElements.forEach(function(el) { el.parentNode.removeChild(el); });
        return new XMLSerializer().serializeToString(doc);
    }

    // =====================================================================
    // FUNÇÃO: Obter o preenchimento efetivo de um elemento (cor sólida)
    // =====================================================================
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

    // =====================================================================
    // FUNÇÃO: Ajustar tamanho e posição do adesivo (para Konva)
    // =====================================================================
    function ajustarTamanhoEPosicaoDoAdesivo() {
        // Como agora a imagem é carregada via Fabric.js e inserida como Konva.Image,
        // você pode ajustar sua escala e posição conforme necessário.
        // Exemplo simples: centraliza a imagem no stage.
        var children = layer.getChildren();
        if (children.length > 0) {
            var konvaImage = children[0];
            konvaImage.position({
                x: (stage.width() - konvaImage.width() * konvaImage.scaleX()) / 2,
                y: (stage.height() - konvaImage.height() * konvaImage.scaleY()) / 2
            });
            stage.batchDraw();
        }
    }

    // =====================================================================
    // FUNÇÃO: Preencher seleção de cores na aba lateral (para objetos sem gradiente)
    // =====================================================================
    function preencherSelecaoDeCores() {
        var container = document.getElementById('layer-colors-container');
        if (!container) return;
        container.innerHTML = '';
        // Como agora os elementos podem ser apenas uma imagem do Konva, você pode desabilitar essa funcionalidade
        // ou adaptá-la para permitir a seleção de cores de elementos editáveis.
    }

    // =====================================================================
    // FUNÇÕES: Histórico (undo/redo)
    // =====================================================================
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
            updateUndoRedoButtons();
        }
    }

    function updateUndoRedoButtons() {
        document.getElementById('undo-button').disabled = (historyIndex <= 0);
        document.getElementById('redo-button').disabled = (historyIndex >= historyStates.length - 1);
    }

    // =====================================================================
    // FUNÇÕES DE TEXTO, ZOOM, INSERÇÃO DE IMAGEM, ETC.
    // (Estas funções permanecem conforme seu código original)
    // =====================================================================

    // Exemplo: botão salvar adesivo (AJAX)
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
});
