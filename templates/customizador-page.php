<?php
/**
 * Template para a página de customização.
 *
 * Exibe na lateral esquerda (sidebar) os adesivos e, na área principal, o editor.
 */

// Recupera o adesivo selecionado via URL (se houver)
$selected_sticker = '';
if ( isset( $_GET['sticker'] ) && !empty( $_GET['sticker'] ) ) {
    $selected_sticker = urldecode( $_GET['sticker'] );
}
?>
<div class="plugin-container">
  <!-- Sidebar com os adesivos na lateral esquerda -->
  <div class="sticker-sidebar">
    <h2>Escolha um Adesivo</h2>
    <div class="sticker-grid">
      <?php
      // Consulta os adesivos (arquivos SVG) na biblioteca de mídia
      $args = array(
          'post_type'      => 'attachment',
          'post_mime_type' => 'image/svg+xml',
          'posts_per_page' => -1,
          'orderby'        => 'title',
          'order'          => 'ASC'
      );
      $stickers = get_posts( $args );
      if ( $stickers ) :
          foreach ( $stickers as $sticker ) :
              $sticker_url = wp_get_attachment_url( $sticker->ID );
              // Cria o link que recarrega a página com o adesivo selecionado via query string
              $link = esc_url( add_query_arg( 'sticker', urlencode( $sticker_url ) ) );
              // Obtém o nome do adesivo sem extensão
              $sticker_name = pathinfo( $sticker_url, PATHINFO_FILENAME );
              ?>
              <a href="<?php echo $link; ?>" class="sticker-item">
                  <img src="<?php echo esc_url( $sticker_url ); ?>" alt="<?php echo esc_attr( $sticker_name ); ?>">
                  <span class="sticker-name"><?php echo esc_html( $sticker_name ); ?></span>
              </a>
          <?php
          endforeach;
      else :
          echo '<p>Nenhum adesivo encontrado.</p>';
      endif;
      ?>
    </div>
  </div>

  <!-- Área principal do editor -->
  <div class="editor-container">
    <?php
      /**
       * Chama a função que exibe o editor.
       * A função person_plugin_display_customizer() deve aceitar a URL do adesivo selecionado.
       */
      echo person_plugin_display_customizer( $selected_sticker );
    ?>
  </div>
</div>
