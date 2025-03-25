#!/usr/bin/env python3
import sys
import xml.etree.ElementTree as ET

if len(sys.argv) < 3:
    print("Usage: process_svg.py input.svg output.svg")
    sys.exit(1)

input_svg = sys.argv[1]
output_svg = sys.argv[2]

try:
    tree = ET.parse(input_svg)
except Exception as e:
    print("Erro ao ler o SVG:", e)
    sys.exit(1)

root = tree.getroot()

# Se já existir width e height contendo "mm", mantemos; caso contrário, se houver viewBox, usamos seus valores.
width = root.get("width")
height = root.get("height")
if width and height and ("mm" in width and "mm" in height):
    # Já está com a unidade desejada
    pass
else:
    viewBox = root.get("viewBox")
    if viewBox:
        parts = viewBox.split()
        if len(parts) == 4:
            vb_width, vb_height = parts[2], parts[3]
            root.set("width", vb_width + "mm")
            root.set("height", vb_height + "mm")
        else:
            print("viewBox com formato inesperado:", viewBox)
            sys.exit(1)
    else:
        # Se não houver viewBox, tenta limpar qualquer unidade e acrescentar "mm"
        if width:
            numeric_width = ''.join([c for c in width if (c.isdigit() or c=='.')])
            root.set("width", numeric_width + "mm")
        if height:
            numeric_height = ''.join([c for c in height if (c.isdigit() or c=='.')])
            root.set("height", numeric_height + "mm")

# Salva o SVG processado (sem DOCTYPE)
tree.write(output_svg, encoding="utf-8", xml_declaration=True)
