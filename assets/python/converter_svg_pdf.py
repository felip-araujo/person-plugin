#!/usr/bin/env python3
import sys
import os
import cairosvg

def main():
    if len(sys.argv) < 3:
        print("Usage: converter_svg_pdf.py input.svg output.pdf [width_mm height_mm]")
        sys.exit(1)
    
    input_svg = sys.argv[1]
    output_pdf = sys.argv[2]

    # Se estiver no Windows e o caminho não estiver no formato URL, ajusta-o
    if os.name == 'nt' and not input_svg.lower().startswith("file:///"):
        input_svg = "file:///" + input_svg.replace("\\", "/")
    
    output_width = None
    output_height = None
    if len(sys.argv) >= 5:
        try:
            width_mm = float(sys.argv[3])
            height_mm = float(sys.argv[4])
            # Converte de mm para pixels: 1 mm = 96/25.4 px (assumindo 96 DPI)
            factor = 96 / 25.4
            output_width = width_mm * factor
            output_height = height_mm * factor
        except Exception as e:
            print("Erro ao ler dimensões:", e)
            sys.exit(1)
    
    try:
        if output_width and output_height:
            cairosvg.svg2pdf(
                url=input_svg,
                write_to=output_pdf,
                output_width=output_width,
                output_height=output_height,
                dpi=96
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
