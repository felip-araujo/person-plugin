// gradiente.js
export function setupGradientEditor(canvas, selectedObjectGetter) {
  const gradientInput = document.getElementById('gradient-color');
  if (!gradientInput) {
    console.log("Elemento com id 'gradient-color' não encontrado.");
    return;
  }
  
  gradientInput.addEventListener('input', function (e) {
    const newColor = e.target.value;
    const selectedObject = selectedObjectGetter();
    console.log("Gradient input acionado, novo valor:", newColor, "Objeto selecionado:", selectedObject);
    
    if (selectedObject && selectedObject.length > 0) {
      selectedObject.forEach(function (obj, index) {
        console.log(`Objeto ${index} fill antes:`, obj.fill);
        if (typeof obj.fill === 'object' && obj.fill.type === 'linear' && obj.fill.colorStops) {
          obj.fill.colorStops.forEach(function (stop, stopIndex) {
            console.log(`Stop ${stopIndex} anterior:`, stop.color);
            stop.color = newColor;
            console.log(`Stop ${stopIndex} atualizado:`, stop.color);
          });
          obj.set('fill', obj.fill);
        } else {
          console.log("Objeto", index, "não possui um fill de tipo 'linear'.");
        }
      });
      canvas.renderAll();
    }
  });
}
