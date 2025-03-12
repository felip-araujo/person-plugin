#!/usr/bin/env python3
"""
process_svg.py
---------------
Este script lê um arquivo SVG, remove tags <script> (e <style> se desejado),
garante que os atributos de dimensão estejam presentes (usando o viewBox se necessário)
e salva um novo SVG "limpo" que preserva as dimensões originais.
Esse SVG poderá então ser convertido para PDF pelo CairoSVG sem perder as medidas.
"""

import sys
from lxml import etree

def process_svg(svg_input_path, svg_output_path):
    # Cria um parser XML removendo espaços em branco
    parser = etree.XMLParser(remove_blank_text=True)
    try:
        tree = etree.parse(svg_input_path, parser)
    except Exception as e:
        print("Erro ao parsear o SVG:", e)
        sys.exit(1)
    
    root = tree.getroot()

    # Define o namespace padrão (caso o SVG não o defina explicitamente)
    nsmap = root.nsmap.copy()
    if None in nsmap:
        nsmap['svg'] = nsmap.pop(None)
    else:
        nsmap['svg'] = 'http://www.w3.org/2000/svg'
    
    # 1. Remover todas as tags <script>
    for script in root.xpath('//svg:script', namespaces=nsmap):
        parent = script.getparent()
        if parent is not None:
            parent.remove(script)
    
    # 2. (Opcional) Remover ou processar as tags <style>
    # Se preferir manter os estilos inline (para evitar que o browser ou a conversão altere cores)
    # você pode, por exemplo, usar a biblioteca cssutils para ler o conteúdo do <style>
    # e aplicar como atributos inline aos elementos com classes correspondentes.
    # Aqui, como exemplo simples, optamos por remover as tags <style>:
    for style in root.xpath('//svg:style', namespaces=nsmap):
        parent = style.getparent()
        if parent is not None:
            parent.remove(style)
    
    # 3. Garantir que os atributos de dimensão estejam definidos corretamente.
    # Se width e height não estiverem definidos ou não contiverem "mm", tenta extrair do viewBox.
    width = root.get('width')
    height = root.get('height')
    if (width is None or height is None or ('mm' not in width and 'mm' not in height)) and root.get('viewBox'):
        try:
            parts = root.get('viewBox').split()
            if len(parts) == 4:
                vb_width = parts[2]
                vb_height = parts[3]
                # Define as dimensões em milímetros (ajuste conforme sua necessidade)
                root.set('width', vb_width + 'mm')
                root.set('height', vb_height + 'mm')
        except Exception as e:
            print("Erro ao processar o viewBox:", e)
    
    # 4. Salvar o novo SVG "limpo"
    tree.write(svg_output_path, pretty_print=True, xml_declaration=True, encoding='UTF-8')
    print("SVG processado salvo em:", svg_output_path)

if __name__ == '__main__':
    if len(sys.argv) != 3:
        print("Uso: python process_svg.py input.svg output.svg")
        sys.exit(1)
    process_svg(sys.argv[1], sys.argv[2])
