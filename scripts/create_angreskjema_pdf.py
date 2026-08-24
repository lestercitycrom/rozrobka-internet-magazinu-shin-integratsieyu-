from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle


root = Path(__file__).resolve().parents[1]
output = root / "application" / "public" / "dokumenter" / "angreskjema-easytire.pdf"
output.parent.mkdir(parents=True, exist_ok=True)

pdfmetrics.registerFont(TTFont("Arial", r"C:\Windows\Fonts\arial.ttf"))
pdfmetrics.registerFont(TTFont("Arial-Bold", r"C:\Windows\Fonts\arialbd.ttf"))

base = getSampleStyleSheet()
title = ParagraphStyle("title", parent=base["Title"], fontName="Arial-Bold", fontSize=15, leading=18, textColor=colors.HexColor("#111111"), spaceAfter=3 * mm)
body = ParagraphStyle("body", parent=base["BodyText"], fontName="Arial", fontSize=9, leading=12, textColor=colors.HexColor("#222222"), spaceAfter=2 * mm)
small = ParagraphStyle("small", parent=body, fontSize=8, leading=10, textColor=colors.HexColor("#555555"))

doc = SimpleDocTemplate(
    str(output), pagesize=A4, rightMargin=15 * mm, leftMargin=15 * mm,
    topMargin=11 * mm, bottomMargin=9 * mm,
    title="Standard angreskjema – EasyTire", author="EasyTask AS / EasyTire",
)

story = [
    Paragraph("STANDARD ANGRESKJEMA", title),
    Paragraph("Ved kjøp av varer og tjenester som ikke er finansielle tjenester. Fyll ut og returner skjemaet bare dersom du vil gå fra avtalen.", body),
]

merchant = Table(
    [
        [Paragraph("Til", body), Paragraph("EasyTask AS / EasyTire", body)],
        [Paragraph("Adresse", body), Paragraph("Ringvegen 200, 9018 Tromsø, Norge", body)],
        [Paragraph("Kontakt", body), Paragraph("max.easytire@gmail.com · +47 939 58 270", body)],
        [Paragraph("Org.nr.", body), Paragraph("934 860 187 MVA", body)],
    ],
    colWidths=[31 * mm, 145 * mm],
    rowHeights=[8 * mm] * 4,
)
merchant.setStyle(TableStyle([
    ("BOX", (0, 0), (-1, -1), .8, colors.HexColor("#999999")),
    ("INNERGRID", (0, 0), (-1, -1), .4, colors.HexColor("#cccccc")),
    ("BACKGROUND", (0, 0), (0, -1), colors.HexColor("#f2f1ee")),
    ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
    ("LEFTPADDING", (0, 0), (-1, -1), 6),
    ("RIGHTPADDING", (0, 0), (-1, -1), 6),
    ("TOPPADDING", (0, 0), (-1, -1), 3),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
]))
story.extend([
    merchant,
    Spacer(1, 4 * mm),
    Paragraph("Jeg/vi underretter herved om at jeg/vi ønsker å gå fra min/vår avtale om kjøp av følgende varer / levering av følgende tjenester:", body),
    Spacer(1, 7 * mm),
    Paragraph("________________________________________________________________________________________________", body),
    Paragraph("________________________________________________________________________________________________", body),
    Spacer(1, 1 * mm),
])

field_rows = [
    [Paragraph("Bestillingsreferanse", body), Paragraph("____________________________________________", body)],
    [Paragraph("Avtale inngått / vare mottatt (dato)", body), Paragraph("____________________________________________", body)],
    [Paragraph("Forbrukerens navn", body), Paragraph("____________________________________________", body)],
    [Paragraph("Forbrukerens adresse", body), Paragraph("____________________________________________", body)],
    [Paragraph("E-post eller telefon", body), Paragraph("____________________________________________", body)],
]
fields = Table(field_rows, colWidths=[74 * mm, 102 * mm], rowHeights=[9 * mm] * 5)
fields.setStyle(TableStyle([
    ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
    ("LINEBELOW", (1, 0), (1, -1), .5, colors.HexColor("#777777")),
    ("LEFTPADDING", (0, 0), (-1, -1), 0),
    ("RIGHTPADDING", (0, 0), (-1, -1), 3),
]))
story.extend([
    fields,
    Spacer(1, 4 * mm),
    Paragraph("Dato: ______________________________", body),
    Spacer(1, 4 * mm),
    Paragraph("Forbrukerens underskrift (bare dersom skjemaet sendes på papir):", body),
    Spacer(1, 8 * mm),
    Paragraph("________________________________________________________________________________________________", body),
    Spacer(1, 4 * mm),
    Paragraph("Du kan også bruke en annen entydig melding om at du vil gå fra avtalen. Se easytire.no/kjopsvilkar for frister og praktisk retur. EasyTire dekker direkte returkostnader etter de publiserte vilkårene.", small),
])

doc.build(story)
print(output)
