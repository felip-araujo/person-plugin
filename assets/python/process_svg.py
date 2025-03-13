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

# Se existir viewBox, usamos os valores para definir width/height sem unidades.
viewBox = root.get("viewBox")
if viewBox:
    parts = viewBox.split()
    if len(parts) == 4:
        vb_width, vb_height = parts[2], parts[3]
        root.set("width", vb_width)    # remove a unidade
        root.set("height", vb_height)
    else:
        print("viewBox com formato inesperado:", viewBox)
else:
    # Se não houver viewBox, tente remover "mm" dos atributos width/height
    width = root.get("width")
    height = root.get("height")
    if width:
        root.set("width", width.replace("mm", ""))
    if height:
        root.set("height", height.replace("mm", ""))

tree.write(output_svg, encoding="utf-8", xml_declaration=True)
