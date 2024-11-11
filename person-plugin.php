<?php
/*
Plugin Name: Person Plugin - Customizador de Adesivos
Description: Plugin para customização de adesivos com controle de cores, fontes, etc.
Version: 1.0
Author: Evolution Design
*/

function meu_plugin_enqueue_scripts() {
    // Carregar o Fabric.js da CDN
    wp_enqueue_script(
        'fabric-js', // Handle do script
        'https://cdn.jsdelivr.net/npm/fabric@4.6.0/dist/fabric.min.js', // Novo URL do CDN
        array(), // Dependências
        null, // Versão
        true // Colocar o script no final do body (melhor performance)
    );
}

// Hook para adicionar o script na página
add_action('wp_enqueue_scripts', 'meu_plugin_enqueue_scripts');

// Enfileirar CSS e JS
function person_plugin_enqueue_scripts()
{
    // CSS Principal do Plugin
    wp_enqueue_style('person-plugin-css', plugin_dir_url(__FILE__) . 'assets/css/customizador.css');

    // Google Fonts
    wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&display=swap', false);

    // Bootstrap CSS
    wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css', false, null);

    // Fabric.js
    wp_enqueue_script('fabric-js', plugin_dir_url(__FILE__) . 'assets/vendor/fabric.min.js', array(), null, true);

    // JavaScript do Plugin com Dependência do jQuery e Fabric.js
    wp_enqueue_script('person-plugin-js', plugin_dir_url(__FILE__) . 'assets/js/customizador.js', array('jquery', 'fabric-js'), null, true);

    // Bootstrap JS
    wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'person_plugin_enqueue_scripts');

// Shortcode para exibir o customizador
function person_plugin_display_customizer()
{
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/editor-template.php';
    return ob_get_clean();
}
add_shortcode('customizador_adesivo', 'person_plugin_display_customizer');


