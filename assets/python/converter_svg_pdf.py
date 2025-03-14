#!/usr/bin/env python3
import sys
import os
import cairosvg

def main():
    if len(sys.argv) < 3:
        print("Uso: python converter_svg_pdf.py <input.svg> <output.pdf> [<width_mm> <height_mm>]")
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
    
    output_width = None
    output_height = None
    
    # Se foram passados parâmetros para dimensões, use-os
    if len(sys.argv) >= 5:
        try:
            width_mm = float(sys.argv[3])
            height_mm = float(sys.argv[4])
            print("Dimensões recebidas (mm):", width_mm, height_mm)
            # Converte de mm para pixels: px = mm * (96/25.4)
            factor = 96 / 25.4
            output_width = width_mm * factor
            output_height = height_mm * factor
            print("Dimensões convertidas para px:", output_width, output_height)
        except Exception as e:
            print("Erro ao ler as dimensões:", e)
            sys.exit(1)
    
    try:
        if output_width and output_height:
            cairosvg.svg2pdf(
                url=input_svg,
                write_to=output_pdf,
                output_width=output_width,
                output_height=output_height,
                dpi=96,
                scale=1.0
            )
        else:
            cairosvg.svg2pdf(url=input_svg, write_to=output_pdf)
        print("PDF convertido com sucesso.")
        sys.exit(0)
    except Exception as e:
        print("Erro na conversão:", e)
        sys.exit(1)

if __name__ == '__main__':
    main()
