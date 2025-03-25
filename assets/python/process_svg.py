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

# Verifica se os atributos já estão com "mm"
width_attr = root.get("width")
height_attr = root.get("height")

if width_attr and height_attr and "mm" in width_attr and "mm" in height_attr:
    # Já possuem "mm", não alteramos
    pass
else:
    # Se não houver ou estiver sem unidade, e se houver viewBox, usa seus valores
    viewBox = root.get("viewBox")
    if viewBox:
        parts = viewBox.split()
        if len(parts) == 4:
            vb_width, vb_height = parts[2], parts[3]
            root.set("width", vb_width + "mm")
            root.set("height", vb_height + "mm")
        else:
            print("viewBox com formato inesperado:", viewBox)
    else:
        if width_attr:
            root.set("width", width_attr.replace("mm", "").strip() + "mm")
        if height_attr:
            root.set("height", height_attr.replace("mm", "").strip() + "mm")

tree.write(output_svg, encoding="utf-8", xml_declaration=True)
