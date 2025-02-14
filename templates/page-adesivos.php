<?php
/*
 * Template Name: Editor de Adesivos
 * Template Post Type: page
 */


?>

<style>
    .editor-container {
        max-width: 100%;
        margin: 0 auto;
        padding: 20px;
        box-sizing: border-box;
    }
</style>

<div class="editor-container">
    <?php
    // Chama o editor de adesivos do seu plugin
    echo do_shortcode('[customizador_adesivo_page]');
    ?>
</div>


