#!/usr/bin/env python
import sys
import os

# Adiciona o diretório com a biblioteca do Cairo ao PATH.
# Ajuste o caminho conforme onde sua biblioteca do Cairo está instalada.
if os.name == 'nt':
    os.environ['PATH'] = r"C:\msys64\mingw64\bin;" + os.environ.get('PATH', '')

import cairosvg

def main():
    if len(sys.argv) < 3:
        print("Uso: python converter_svg_pdf.py <input.svg> <output.pdf>")
        sys.exit(1)
    
    input_svg = sys.argv[1]
    output_pdf = sys.argv[2]
    
    # Debug: imprimir os caminhos recebidos
    print("Input SVG:", input_svg)
    print("Output PDF:", output_pdf)
    
    # Se estiver no Windows e o caminho não estiver no formato URL, ajuste-o
    if os.name == 'nt' and not input_svg.lower().startswith("file:///"):
        input_svg = "file:///" + input_svg.replace("\\", "/")
        print("Input SVG ajustado:", input_svg)
    
    try:
        cairosvg.svg2pdf(url=input_svg, write_to=output_pdf)
        print("PDF convertido com sucesso.")
        sys.exit(0)
    except Exception as e:
        print("Erro na conversão:", e)
        sys.exit(1)

if __name__ == '__main__':
    main()
