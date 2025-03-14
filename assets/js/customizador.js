import { setupGradientEditor } from './gradiente.js';

// Variáveis globais
var initialCanvasZoom = 1;
var initialCanvasPosition = { x: 0, y: 0 };
var insertedImage = null;
var historyStates = [];
var historyIndex = -1;
var tempTextObject = null;
var selectedObject = null; // Objeto(s) ativo(s) para edição de cor/gradiente

// Variáveis para armazenar os atributos originais do SVG
var originalSvgWidth = null;
var originalSvgHeight = null;
var exportScaleFactor = 1;

// Fator de escala para exibição no editor (para caber no canvas)
var displayScale = 1;
var stickerGroup = null;   // Objeto Fabric que representa o adesivo

// Cria um seletor de cor inline (caso necessário)
var inlineColorPicker = document.createElement('input');
inlineColorPicker.type = 'text';
inlineColorPicker.style.position = 'fixed';
inlineColorPicker.style.display = 'none';
document.body.appendChild(inlineColorPicker);

// Declaração global do canvas Fabric
var canvas;

// ---------------------------------------------------------------------------
// FUNÇÃO: Converter cor RGB para Hex
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

// ---------------------------------------------------------------------------
// FUNÇÃO: Extrair dimensões originais do SVG a partir do texto
function getOriginalDimensions(svgText) {
    var parser = new DOMParser();
    var doc = parser.parseFromString(svgText, 'image/svg+xml');
    var svgElem = doc.querySelector('svg');
    if (svgElem) {
        var w = svgElem.getAttribute('width');
        var h = svgElem.getAttribute('height');
        console.log(w.h);
        // Remove a unidade "mm" se presente e converte para número
        if (w && h) {
            return { width: parseFloat(w), height: parseFloat(h) };
        }
    }
    return null;
}

// ---------------------------------------------------------------------------
// (Opcional) FUNÇÃO: Converter estilos inline do SVG
function converterEstilosInline(svgText) {
    var parser = new DOMParser();
    var doc = parser.parseFromString(svgText, 'image/svg+xml');
    var styleElements = doc.querySelectorAll('style');
    var cssText = '';
    styleElements.forEach(function (styleEl) {
        cssText += styleEl.textContent;
    });
    var regras = {};
    cssText.replace(/\.([^ \n{]+)\s*\{([^}]+)\}/g, function (match, className, declarations) {
        var props = {};
        declarations.split(';').forEach(function (decl) {
            if (decl.trim()) {
                var parts = decl.split(':');
                if (parts.length === 2) {
                    var prop = parts[0].trim();
                    var value = parts[1].trim();
                    props[prop] = value;
                }
            }
        });
        regras[className] = props;
        return '';
    });
    var elemsComClasse = doc.querySelectorAll('[class]');
    elemsComClasse.forEach(function (elem) {
        var classes = elem.getAttribute('class').split(/\s+/);
        classes.forEach(function (cls) {
            if (regras[cls] && regras[cls].fill) {
                elem.setAttribute('fill', regras[cls].fill);
            }
        });
    });
    // Remove os elementos <style>
    styleElements.forEach(function (el) {
        el.parentNode.removeChild(el);
    });
    return new XMLSerializer().serializeToString(doc);
}

// ---------------------------------------------------------------------------
// FUNÇÃO: Carregar adesivo via Fabric.js mantendo as dimensões originais
function carregarAdesivo(stickerUrl) {
    fetch(stickerUrl)
        .then(function (response) {
            return response.text();
        })
        .then(function (svgText) {
            // Extrai as dimensões originais do SVG
            var parser = new DOMParser();
            var doc = parser.parseFromString(svgText, 'image/svg+xml');
            var svgElem = doc.getElementsByTagName('svg')[0];
            if (svgElem) {
                originalSvgWidth = svgElem.getAttribute('width');   // ex: "636.567mm"
                originalSvgHeight = svgElem.getAttribute('height'); // ex: "760.136mm"
                console.log(originalSvgWidth, originalSvgHeight);
            }
            // Remove <script> se houver, para segurança
            var scripts = doc.getElementsByTagName('script');
            for (var i = scripts.length - 1; i >= 0; i--) {
                scripts[i].parentNode.removeChild(scripts[i]);
            }
            var cleanedSVG = new XMLSerializer().serializeToString(doc);

            // Carrega o SVG no canvas usando Fabric.js
            fabric.loadSVGFromString(cleanedSVG, function (objects, options) {
                var svgGroup = fabric.util.groupSVGElements(objects, options);

                // Calcula fator de escala para exibição:
                var scaleX = canvas.getWidth() / svgGroup.width;
                var scaleY = canvas.getHeight() / svgGroup.height;

                // Usa a menor escala para garantir que o adesivo caiba no canvas
                var displayScale = Math.min(scaleX, scaleY) * 0.9; // 0.9 para margem visual

                svgGroup.set({
                    left: canvas.getWidth() / 2,
                    top: canvas.getHeight() / 2,
                    originX: 'center',
                    originY: 'center',
                    scaleX: displayScale,
                    scaleY: displayScale,
                    selectable: true,
                });

                canvas.clear(); // Limpa objetos anteriores se houver
                canvas.add(svgGroup);
                canvas.renderAll();

                // Continua o resto do fluxo...
                preencherSelecaoDeCores();
                saveHistory();
            });

        })
        .catch(function (error) {
            console.error("Erro ao carregar o adesivo:", error);
        });
}

// ---------------------------------------------------------------------------
// FUNÇÃO: Preencher seleção de cores (bolinhas) com base nos fills dos objetos
function preencherSelecaoDeCores() {
    var container = document.getElementById('layer-colors-container');
    if (!container) return;
    container.innerHTML = '';
    var groups = {};
    function processObject(obj) {
        if (obj.fill) {
            var fillVal = obj.fill;
            if (typeof fillVal === 'object' && fillVal.type === 'linear') {
                if (fillVal.colorStops && fillVal.colorStops.length > 0) {
                    fillVal = fillVal.colorStops[0].color;
                }
            }
            fillVal = rgbToHex(fillVal).toLowerCase();
            if (!groups[fillVal]) groups[fillVal] = [];
            groups[fillVal].push(obj);
        }
    }
    canvas.getObjects().forEach(function (obj) {
        if (obj.type === 'group' && obj._objects) {
            obj._objects.forEach(function (child) {
                processObject(child);
            });
        } else {
            processObject(obj);
        }
    });
    Object.keys(groups).forEach(function (color) {
        var count = groups[color].length;
        var colorDiv = document.createElement('div');
        colorDiv.style.cssText =
            "display:inline-block;width:30px;height:30px;border-radius:50%;background-color:" +
            color +
            ";border:2px solid #fff;margin:5px;cursor:pointer;";
        colorDiv.title = 'Mudar cor ' + color + ' (' + count + ' objeto' + (count > 1 ? 's' : '') + ')';
        colorDiv.addEventListener('click', function () {
            selectedObject = groups[color];
            var colorInput = document.createElement('input');
            colorInput.type = 'color';
            colorInput.value = color;
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
                selectedObject.forEach(function (obj) {
                    if (typeof obj.fill === 'object' && obj.fill.type === 'linear') {
                        obj.fill.colorStops.forEach(function (stop) {
                            stop.color = newColor;
                        });
                        obj.set('fill', obj.fill);
                    } else {
                        obj.set('fill', newColor);
                    }
                });
                canvas.renderAll();
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

// ---------------------------------------------------------------------------
// FUNÇÕES: Histórico (undo/redo)
function saveHistory() {
    if (historyStates.length > 50) {
        historyStates.shift();
        historyIndex--;
    }
    if (historyIndex < historyStates.length - 1) {
        historyStates = historyStates.slice(0, historyIndex + 1);
    }
    var state = canvas.toJSON();
    historyStates.push(state);
    historyIndex++;
    updateUndoRedoButtons();
}
function undo() {
    if (historyIndex > 0) {
        historyIndex--;
        canvas.loadFromJSON(historyStates[historyIndex], function () {
            canvas.renderAll();
            preencherSelecaoDeCores();
            updateUndoRedoButtons();
        });
    }
}
function redo() {
    if (historyIndex < historyStates.length - 1) {
        historyIndex++;
        canvas.loadFromJSON(historyStates[historyIndex], function () {
            canvas.renderAll();
            preencherSelecaoDeCores();
            updateUndoRedoButtons();
        });
    }
}
function updateUndoRedoButtons() {
    var undoBtn = document.getElementById('undo-button');
    var redoBtn = document.getElementById('redo-button');
    if (undoBtn) undoBtn.disabled = historyIndex <= 0;
    if (redoBtn) redoBtn.disabled = historyIndex >= historyStates.length - 1;
}

// ---------------------------------------------------------------------------
// FUNÇÕES DE TEXTO, INSERÇÃO DE IMAGEM, ZOOM, ETC.
let currentTextObj;
document.getElementById('texto').addEventListener('input', function (e) {
    var valorDigitado = e.target.value;
    if (!currentTextObj) {
        var fontSize = parseInt(document.getElementById('tamanho-fonte').value) || 16;
        var fontFamily = document.getElementById('fontPicker').value || 'Arial';
        var fillColor = document.getElementById('cor-texto').value || '#000';
        var rotation = parseFloat(document.getElementById('rotacao-texto').value) || 0;
        currentTextObj = new fabric.IText(valorDigitado, {
            left: canvas.getWidth() / 2,
            top: canvas.getHeight() / 2,
            fontSize: fontSize,
            fontFamily: fontFamily,
            fill: fillColor,
            angle: rotation,
            editable: true,
        });
        canvas.add(currentTextObj);
        canvas.setActiveObject(currentTextObj);
    } else {
        currentTextObj.text = valorDigitado;
    }
    canvas.renderAll();
});
document.getElementById('cor-texto').addEventListener('input', function (e) {
    if (currentTextObj) {
        currentTextObj.set('fill', e.target.value);
        canvas.renderAll();
    }
});
document.getElementById('fontPicker').addEventListener('change', function (e) {
    if (currentTextObj) {
        currentTextObj.set('fontFamily', e.target.value);
        canvas.renderAll();
    }
});
function addText() {
    if (currentTextObj) {
        currentTextObj.set({ editable: false });
    }
    currentTextObj = null;
    document.getElementById('texto').value = '';
    canvas.renderAll();
}
function inserirImagem() {
    var fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = 'image/*';
    fileInput.onchange = function (e) {
        var file = e.target.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (evt) {
            fabric.Image.fromURL(evt.target.result, function (img) {
                img.set({
                    left: canvas.getWidth() / 2 - img.width / 6,
                    top: canvas.getHeight() / 2 - img.height / 6,
                    scaleX: 1 / 3,
                    scaleY: 1 / 3,
                    selectable: true,
                });
                canvas.add(img);
                canvas.setActiveObject(img);
                canvas.renderAll();
                saveHistory();
            });
        };
        reader.readAsDataURL(file);
    };
    fileInput.click();
}
function aplicarZoom(direction) {
    var oldZoom = canvas.getZoom();
    var newZoom = direction === 'in' ? oldZoom * 1.05 : oldZoom / 1.05;
    newZoom = Math.max(0.5, Math.min(newZoom, 3));
    canvas.setZoom(newZoom);
}

// ---------------------------------------------------------------------------
// Inicialização do Canvas e eventos
document.addEventListener('DOMContentLoaded', function () {
    var canvasElement = document.getElementById('adesivo-canvas');
    if (!canvasElement) {
        console.error("Elemento com id 'adesivo-canvas' não encontrado. Certifique-se de usar uma tag <canvas>.");
        return;
    }
    canvas = new fabric.Canvas('adesivo-canvas', {
        width: canvasElement.offsetWidth,
        height: canvasElement.offsetHeight,
        preserveObjectStacking: true,
    });
    if (typeof pluginData !== 'undefined' && pluginData.stickerUrl) {
        carregarAdesivo(pluginData.stickerUrl);
    }
    function getSelectedObject() {
        return selectedObject;
    }
    setupGradientEditor(canvas, getSelectedObject);
    document.getElementById('adicionar-texto-botao').addEventListener('click', addText);
    document.getElementById('zoom-in').addEventListener('click', function () {
        aplicarZoom('in');
    });
    document.getElementById('zoom-out').addEventListener('click', function () {
        aplicarZoom('out');
    });
    document.getElementById('reset-zoom').addEventListener('click', function () {
        canvas.setZoom(1);
        canvas.absolutePan({ x: 0, y: 0 });
    });
    canvas.on('mouse:wheel', function (opt) {
        var delta = opt.e.deltaY;
        var zoom = canvas.getZoom();
        zoom *= 0.999 ** delta;
        zoom = Math.max(0.5, Math.min(zoom, 3));
        canvas.setZoom(zoom);
        opt.e.preventDefault();
        opt.e.stopPropagation();
    });
    document.getElementById('undo-button').addEventListener('click', undo);
    document.getElementById('redo-button').addEventListener('click', redo);
    document.getElementById('inserir-imagem-botao').addEventListener('click', inserirImagem);
    document.getElementById('aumentar-png-botao').addEventListener('click', function () {
        var activeObj = canvas.getActiveObject();
        if (activeObj && activeObj.type === 'image') {
            activeObj.scale(activeObj.scaleX * 1.1);
            canvas.renderAll();
            saveHistory();
        }
    });
    document.getElementById('diminuir-png-botao').addEventListener('click', function () {
        var activeObj = canvas.getActiveObject();
        if (activeObj && activeObj.type === 'image') {
            activeObj.scale(activeObj.scaleX * 0.9);
            canvas.renderAll();
            saveHistory();
        }
    });
    document.getElementById('limpar-tela-botao').addEventListener('click', function () {
        canvas.clear();
        saveHistory();
    });
    // Função para salvar o adesivo
    function salvarAdesivo() {
        if (!canvas) {
            console.error('Canvas não definido!');
            alert('Erro: O editor de adesivos não está carregado corretamente.');
            return;
        }
    
        // Gera o SVG do canvas (conteúdo vetorial)
        var adesivoSVG = canvas.toSVG();
        console.log('SVG gerado pelo Fabric.js:', adesivoSVG);
    
        // Se tivermos as dimensões originais, vamos ajustá-las
        if (originalSvgWidth && originalSvgHeight) {
            // Extraímos os valores numéricos dos atributos (supondo que estão em mm)
            var width_mm = parseFloat(originalSvgWidth);
            var height_mm = parseFloat(originalSvgHeight);
    
            // Converte de mm para pixels (1 mm = 96/25.4 ≈ 3.78)
            var factor = 96 / 25.4;
            var width_px = (width_mm * factor).toFixed(2);   // ex.: ~2406.90
            var height_px = (height_mm * factor).toFixed(2);  // ex.: ~2873.30
    
            console.log("Dimensões originais (mm):", originalSvgWidth, originalSvgHeight);
            console.log("Convertidas para px:", width_px, height_px);
    
            // Usando DOMParser para manipular o SVG
            var parser = new DOMParser();
            var doc = parser.parseFromString(adesivoSVG, "image/svg+xml");
            var svgElem = doc.querySelector("svg");
            if (svgElem) {
                // Define os atributos width e height com os valores originais (em mm)
                svgElem.setAttribute("width", originalSvgWidth);
                svgElem.setAttribute("height", originalSvgHeight);
                // Define o viewBox com os valores convertidos para px
                svgElem.setAttribute("viewBox", "0 0 " + width_px + " " + height_px);
    
                // Procura entre os filhos imediatos de <svg> algum <g> com atributo transform cujo fator de escala seja menor que 1
                var groups = svgElem.querySelectorAll(":scope > g[transform]");
                for (var i = 0; i < groups.length; i++) {
                    var transform = groups[i].getAttribute("transform");
                    var match = transform.match(/matrix\(([^)]+)\)/);
                    if (match) {
                        // Separa os valores da matriz (assumindo que são separados por espaços)
                        var parts = match[1].split(/[\s,]+/);
                        if (parts.length >= 4) {
                            var scaleX = parseFloat(parts[0]);
                            // Se o fator de escala for menor que 1, removemos esse grupo
                            if (scaleX < 1) {
                                console.log("Removendo o grupo com transform:", transform);
                                // Move os filhos desse grupo para o nível do SVG
                                while (groups[i].firstChild) {
                                    svgElem.insertBefore(groups[i].firstChild, groups[i]);
                                }
                                // Remove o grupo vazio
                                svgElem.removeChild(groups[i]);
                                // Se deseja remover apenas o primeiro grupo com esse comportamento, interrompa aqui:
                                break;
                            }
                        }
                    }
                }
    
                // Serializa novamente o SVG modificado
                adesivoSVG = new XMLSerializer().serializeToString(doc);
            } else {
                console.warn("Elemento <svg> não encontrado após o parse.");
            }
            console.log("SVG final ajustado:", adesivoSVG);
        } else {
            console.warn("Dimensões originais não definidas; usando as do Fabric.js.");
        }
    
        var price = $('#stickerPrice').val();
        if (!price || isNaN(price) || price <= 0) {
            alert('Erro: Preço inválido!');
            return;
        }
    
        // Envia também as dimensões para o servidor (elas serão utilizadas no script Python/CairoSVG)
        $.ajax({
            url: personPlugin.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'salvar_adesivo_servidor',
                adesivo_svg: adesivoSVG,
                price: price,
                width: originalSvgWidth,
                height: originalSvgHeight
            },
            success: function (response) {
                console.log('Resposta do servidor:', response);
                if (response.success) {
                    // window.location.href = response.data.cart_url;
                } else {
                    alert(response.data.message);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('Erro AJAX:', textStatus, errorThrown, jqXHR.responseText);
                alert('Erro na requisição.');
            },
        });
    }
    
    



    $('#salvar-adesivo-botao').on('click', function (e) {

        e.preventDefault();
        var aceitoTermos = $('#aceito-termos').prop('checked');
        if (!aceitoTermos) {
            $('#termsModal').modal('show');
        } else {
            salvarAdesivo();
        }
    });
    $('#acceptTermsBtn').on('change', function () {
        if ($(this).is(':checked')) {
            $('#aceito-termos').prop('checked', true);
            $('#termsModal').modal('hide');
            salvarAdesivo();
        }
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
    canvas.on('object:modified', function () {
        saveHistory();
        preencherSelecaoDeCores();
    });
    var iniciarTourBtn = document.getElementById('iniciar-tour');
    if (iniciarTourBtn) {
        iniciarTourBtn.addEventListener("click", function () {
            introJs().start();
        });
    }
    var closeEditorBtn = document.getElementById('close-editor');
    if (closeEditorBtn) {
        closeEditorBtn.addEventListener('click', function () {
            document.querySelector('.container').style.display = 'none';
        });
    }
});
