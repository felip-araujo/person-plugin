import { setupGradientEditor } from './gradiente.js';

// Variáveis globais
var initialCanvasZoom = 1;
var initialCanvasPosition = { x: 0, y: 0 };
var insertedImage = null;
var historyStates = [];
var historyIndex = -1;
var tempTextObject = null;
var selectedObject = null; // Objeto(s) ativo(s) para edição de cor/gradiente

// Cria um seletor de cor inline para edição direta (se necessário)
var inlineColorPicker = document.createElement('input');
inlineColorPicker.type = 'text';
inlineColorPicker.style.position = 'fixed';
inlineColorPicker.style.display = 'none';
document.body.appendChild(inlineColorPicker);

// Declaração global do canvas Fabric
var canvas;

// ---------------------------------------------------------------------------
// Função auxiliar: Converter cor RGB para Hex
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
// Função auxiliar: Converter estilos inline do SVG (se necessário)
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
    styleElements.forEach(function (el) {
        el.parentNode.removeChild(el);
    });
    return new XMLSerializer().serializeToString(doc);
}

// ---------------------------------------------------------------------------
// FUNÇÃO: Carregar adesivo via Fabric.js (removendo scripts internos)
function carregarAdesivo(stickerUrl) {
    fetch(stickerUrl)
        .then(function (response) {
            return response.text();
        })
        .then(function (svgText) {
            // Remove todas as tags <script> do SVG para evitar execução de código indesejada
            var parser = new DOMParser();
            var doc = parser.parseFromString(svgText, 'image/svg+xml');
            var scripts = doc.querySelectorAll('script');
            scripts.forEach(function (script) {
                script.parentNode.removeChild(script);
            });
            var cleanedSVG = new XMLSerializer().serializeToString(doc);
            // (Opcional) converter estilos inline:
            // cleanedSVG = converterEstilosInline(cleanedSVG);
            fabric.loadSVGFromString(cleanedSVG, function (objects, options) {
                var svgGroup = fabric.util.groupSVGElements(objects, options);
                svgGroup.set({
                    left: 0,
                    top: 0,
                    selectable: true,
                });
                var canvasWidth = canvas.getWidth();
                var canvasHeight = canvas.getHeight();
                var scale = Math.min(canvasWidth / svgGroup.width, canvasHeight / svgGroup.height);
                svgGroup.scale(scale);
                svgGroup.set({
                    left: (canvasWidth - svgGroup.width * scale) / 2,
                    top: (canvasHeight - svgGroup.height * scale) / 2,
                });
                canvas.add(svgGroup);
                canvas.renderAll();

                // Chama a função para preencher as bolinhas de cores assim que o adesivo for carregado
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

    // Função interna para processar um objeto e extrair o fill
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

    // Percorre todos os objetos do canvas
    canvas.getObjects().forEach(function (obj) {
        if (obj.type === 'group' && obj._objects) {
            obj._objects.forEach(function (child) {
                processObject(child);
            });
        } else {
            processObject(obj);
        }
    });

    // Cria as bolinhas de cor
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
// FUNÇÕES: Histórico (undo/redo) usando canvas.toJSON() e loadFromJSON()
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

// Adiciona texto usando fabric.IText (edição inline ao dar duplo clique)


// Variável para armazenar o objeto de texto atual
let currentTextObj;

// Adiciona um listener ao input de texto
document.getElementById('texto').addEventListener('input', function (e) {
    var valorDigitado = e.target.value;

    // Se o objeto ainda não foi criado, cria-o e adiciona ao canvas
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
        // Se o objeto já existe, apenas atualiza seu texto
        currentTextObj.text = valorDigitado;
    }

    canvas.renderAll();
});

function addText() {
    // Se você quiser, finalize o objeto criado, por exemplo,
    // removendo o listener do input ou resetando a variável.
    // Aqui, podemos simplesmente limpar o input e garantir que
    // o objeto de texto atual fique "fixado" no canvas.
    currentTextObj && currentTextObj.set({ editable: false });
    currentTextObj = null;
    // Opcionalmente, limpe o input:
    document.getElementById('texto').value = '';
    canvas.renderAll();
}


// Insere uma imagem a partir de arquivo
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

// Controle de zoom usando canvas.setZoom()
function aplicarZoom(direction) {
    var oldZoom = canvas.getZoom();
    var newZoom = direction === 'in' ? oldZoom * 1.05 : oldZoom / 1.05;
    newZoom = Math.max(0.5, Math.min(newZoom, 3));
    canvas.setZoom(newZoom);
}

// ---------------------------------------------------------------------------
// EVENTOS INICIAIS E CONFIGURAÇÃO DO CANVAS
document.addEventListener('DOMContentLoaded', function () {
    var canvasElement = document.getElementById('adesivo-canvas');
    if (!canvasElement) {
        console.error("Elemento com id 'adesivo-canvas' não encontrado. Certifique-se de usar uma tag <canvas>.");
        return;
    }
    // Inicializa o canvas Fabric
    canvas = new fabric.Canvas('adesivo-canvas', {
        width: canvasElement.offsetWidth,
        height: canvasElement.offsetHeight,
        preserveObjectStacking: true,
    });

    // Se houver URL do adesivo, carrega-o
    if (typeof pluginData !== 'undefined' && pluginData.stickerUrl) {
        carregarAdesivo(pluginData.stickerUrl);
    }

    // Chama o editor de gradiente importado após a inicialização do canvas.
    // Define uma função para obter o objeto selecionado.
    function getSelectedObject() {
        return selectedObject;
    }
    setupGradientEditor(canvas, getSelectedObject);

    // Configura os botões e eventos
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

    $('#salvar-adesivo-botao').on('click', function (e) {
        e.preventDefault();
        if (!canvas) {
            console.error('Canvas não definido!');
            alert('Erro: O editor de adesivos não está carregado corretamente.');
            return;
        }
        var adesivoBase64 = canvas.toDataURL({ format: 'png', quality: 1 });
        console.log('Imagem capturada:', adesivoBase64);
        var price = $('#stickerPrice').val();
        var aceitoTermos = $('#aceito-termos').prop('checked');
        if (!price || isNaN(price) || price <= 0) {
            alert('Erro: Preço inválido!');
            return;
        }
        if (!aceitoTermos && !aceitoTermos) {
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
                price: price,
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
            },
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

    canvas.on('object:modified', function () {
        saveHistory();
        preencherSelecaoDeCores();
    });

    // Verifica elementos opcionais
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
