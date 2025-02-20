document.addEventListener('DOMContentLoaded', function () {
    // Verifica se o botão de iniciar tutorial foi clicado
    document.querySelector('#start-tutorial').addEventListener('click', function () {
        introJs()
            .setOptions({
                nextLabel: 'Próximo',
                prevLabel: 'Anterior',
                skipLabel: 'Pular',
                doneLabel: 'Concluir',
                tooltipClass: 'customTooltip', // Para estilizar
                highlightClass: 'customHighlight' // Para destacar
            })
            .start();
    });
});
