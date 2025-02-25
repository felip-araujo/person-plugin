// gradiente.js

(function() {
    /**
     * Converte um valor de string (com ou sem porcentagem) para decimal.
     * @param {string} val - Valor a ser convertido.
     * @returns {number} Valor decimal.
     */
    function toDecimal(val) {
      return (val.indexOf('%') !== -1) ? parseFloat(val) / 100 : parseFloat(val);
    }
  
    /**
     * Extrai e converte um elemento <linearGradient> do SVG.
     * Retorna um objeto com as propriedades necessárias para definir um gradiente no Konva.
     * @param {Element} gradientElem - O elemento <linearGradient> do SVG.
     * @returns {Object} Objeto com colorStops, startPoint e endPoint.
     */
    function parseLinearGradient(gradientElem) {
      var x1 = toDecimal(gradientElem.getAttribute('x1') || '0%');
      var y1 = toDecimal(gradientElem.getAttribute('y1') || '0%');
      var x2 = toDecimal(gradientElem.getAttribute('x2') || '100%');
      var y2 = toDecimal(gradientElem.getAttribute('y2') || '0%');
      
      var stops = [];
      var stopElems = gradientElem.querySelectorAll('stop');
      stopElems.forEach(function(stop) {
        var offset = toDecimal(stop.getAttribute('offset') || '0%');
        var color = stop.getAttribute('stop-color') || '#000000';
        var opacity = stop.getAttribute('stop-opacity');
        if (opacity && opacity !== '1') {
          // Aqui você pode aprimorar para gerar uma cor RGBA se necessário
          color = color;
        }
        stops.push({ offset: offset, color: color });
      });
      stops.sort(function(a, b) { return a.offset - b.offset; });
      var gradientColorStops = [];
      stops.forEach(function(s) {
        gradientColorStops.push(s.offset);
        gradientColorStops.push(s.color);
      });
      console.log("Gradient parsed:", gradientColorStops);
      return {
        colorStops: gradientColorStops,
        startPoint: { x: x1, y: y1 },
        endPoint: { x: x2, y: y2 }
      };
    }
  
    /**
     * Abre um editor de cor (input type="color") para editar o primeiro stop do gradiente.
     * @param {Konva.Shape} shape - O objeto Konva que possui um gradiente.
     * @param {Konva.Layer} layer - A layer onde o objeto está.
     * @param {Function} saveHistory - Função a ser chamada após a edição.
     */
    function openGradientColorEditor(shape, layer, saveHistory) {
      var currentStops = shape.fillLinearGradientColorStops();
      if (!currentStops || currentStops.length < 2) return;
      
      var colorEditor = document.createElement('input');
      colorEditor.type = 'color';
      colorEditor.value = currentStops[1]; // Edita o primeiro stop
      var pos = shape.getAbsolutePosition();
      colorEditor.style.position = 'absolute';
      colorEditor.style.left = (pos.x + 20) + 'px';
      colorEditor.style.top = (pos.y + 20) + 'px';
      colorEditor.style.zIndex = 10000;
      
      document.body.appendChild(colorEditor);
      colorEditor.focus();
      
      colorEditor.oninput = function() {
        var newColor = colorEditor.value;
        currentStops[1] = newColor;
        shape.fillLinearGradientColorStops(currentStops);
        layer.draw();
      };
      
      colorEditor.onblur = function() {
        document.body.removeChild(colorEditor);
        if (typeof saveHistory === 'function') {
          saveHistory();
        }
      };
    }
  
    // Expor as funções no objeto global GradienteEditor
    window.GradienteEditor = {
      parseLinearGradient: parseLinearGradient,
      openGradientColorEditor: openGradientColorEditor
    };
  })();
  