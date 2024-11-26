jQuery(document).ready(function ($) {
    $('#upload-button').on('click', function (e) {
        e.preventDefault();

        // Cria o Media Uploader
        var mediaUploader = wp.media({
            title: 'Escolha um Adesivo',
            button: {
                text: 'Usar este arquivo'
            },
            multiple: false
        });

        // Seleciona o arquivo
        mediaUploader.on('select', function () {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#attachment-id').val(attachment.id); // Salva o ID do anexo
            $('#upload-preview').html('<img src="' + attachment.url + '" width="100" />'); // Exibe a prévia
        });

        // Abre o uploader
        mediaUploader.open();
    });
});
