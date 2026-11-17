import os
import cv2
import numpy as np
from PIL import Image, ImageDraw, ImageFont, ImageFilter

W, H = 1536, 1024

# 1. Base Canvas - Deep Palayan Midnight Teal to Regal Maroon Gradient
base = Image.new('RGBA', (W, H), (0, 0, 0, 255))
base_draw = ImageDraw.Draw(base)

# Rich background gradient
for y in range(H):
    t = y / float(H)
    r = int(6 * (1 - t) + 88 * t)
    g = int(30 * (1 - t) + 2 * t)
    b = int(36 * (1 - t) + 8 * t)
    base_draw.line([(0, y), (W, y)], fill=(r, g, b, 255))

# Soft ambient lighting glows
glow = Image.new('RGBA', (W, H), (0, 0, 0, 0))
glow_draw = ImageDraw.Draw(glow)
for rad in range(800, 50, -25):
    alpha = int(48 * (1 - rad / 800.0))
    glow_draw.ellipse([480 - rad, 280 - rad, 480 + rad, 280 + rad], fill=(216, 186, 152, alpha))
glow = glow.filter(ImageFilter.GaussianBlur(35))
base = Image.alpha_composite(base, glow)

# Left decorative vertical satin bar (like in reference)
left_bar = Image.new('RGBA', (W, H), (0, 0, 0, 0))
lb_draw = ImageDraw.Draw(left_bar)
lb_width = 120
for x in range(lb_width):
    tx = x / float(lb_width)
    r = int(10 * (1 - tx) + 127 * tx)
    g = int(38 * (1 - tx) + 3 * tx)
    b = int(42 * (1 - tx) + 3 * tx)
    lb_draw.line([(x, 0), (x, H)], fill=(r, g, b, 235))
# Gold and white divider trim
lb_draw.line([(lb_width, 0), (lb_width, H)], fill=(216, 186, 152, 255), width=3)
lb_draw.line([(lb_width - 3, 0), (lb_width - 3, H)], fill=(255, 255, 255, 180), width=1)
base = Image.alpha_composite(base, left_bar)

# 2. Diagonal Ribbons with Services Photos
# Angle: ribbons run from top-right down to bottom-left (tilt ~ -0.32)
slope = 340

ribbon_configs = [
    {
        'top': (105, 345),
        'path': 'images/hero/service_housing_key.jpg',
        'focus_x': 0.54,
        'focus_y': 0.48,
        'title': 'PABAHAY (HOUSING)',
        'sub': 'Turn-Over Ceremony • Akap & NHA'
    },
    {
        'top': (370, 625),
        'path': 'images/hero/service_wheelchair.jpg',
        'focus_x': 0.50,
        'focus_y': 0.38,
        'title': 'PWD CARE & MOBILITY',
        'sub': 'Wheelchair & Assistive Support'
    },
    {
        'top': (650, 910),
        'path': 'images/cswdo-food-assistance.jpg',
        'focus_x': 0.62,
        'focus_y': 0.42,
        'title': 'AICS FOOD RELIEF',
        'sub': 'Ayuda & Family Rice Packs'
    },
    {
        'top': (935, 1205),
        'path': 'images/hero/service_cash_handshake.jpg',
        'focus_x': 0.48,
        'focus_y': 0.40,
        'title': 'CASH FINANCIAL AID',
        'sub': 'Emergency Cash Relief Ayuda'
    },
    {
        'top': (1230, 1540),
        'path': 'images/hero/service_senior_desk.jpg',
        'focus_x': 0.65,
        'focus_y': 0.42,
        'title': 'SENIOR CITIZEN DESK',
        'sub': 'OSCA Social Pension & Care'
    }
]

ribbons_layer = Image.new('RGBA', (W, H), (0, 0, 0, 0))
separators_layer = Image.new('RGBA', (W, H), (0, 0, 0, 0))
sep_draw = ImageDraw.Draw(separators_layer)

for cfg in ribbon_configs:
    x_start, x_end = cfg['top']
    p_path = cfg['path']
    if not os.path.exists(p_path):
        continue

    img = Image.open(p_path).convert('RGB')
    
    poly = [
        (x_start, 0),
        (x_end, 0),
        (x_end - slope, H),
        (x_start - slope, H)
    ]
    
    mask = Image.new('L', (W, H), 0)
    m_draw = ImageDraw.Draw(mask)
    m_draw.polygon(poly, fill=255)
    
    min_x = max(0, min(p[0] for p in poly))
    max_x = min(W, max(p[0] for p in poly))
    box_w = max_x - min_x + 60
    box_h = H + 60
    
    im_w, im_h = img.size
    scale = max(box_w / float(im_w), box_h / float(im_h))
    new_w = int(im_w * scale)
    new_h = int(im_h * scale)
    img_resized = img.resize((new_w, new_h), Image.Resampling.LANCZOS)
    
    center_x = min_x + (box_w // 2)
    center_y = H // 2
    
    paste_x = int(center_x - (new_w * cfg['focus_x']))
    paste_y = int(center_y - (new_h * cfg['focus_y']))
    
    temp_photo = Image.new('RGBA', (W, H), (0, 0, 0, 0))
    temp_photo.paste(img_resized, (paste_x, paste_y))
    
    ribbons_layer.paste(temp_photo, (0, 0), mask)
    
    # Crisp white & gold separator lines (like the reference flyer)
    p1 = (x_end, -10)
    p2 = (x_end - slope, H + 10)
    
    # Soft shadow under separator
    sep_draw.line([p1, p2], fill=(0, 0, 0, 160), width=18)
    # White main border bar (width 10)
    sep_draw.line([p1, p2], fill=(255, 255, 255, 255), width=10)
    # Inner gold hairline (width 3)
    sep_draw.line([p1, p2], fill=(216, 186, 152, 240), width=3)

sep_shadow = separators_layer.filter(ImageFilter.GaussianBlur(10))
shadow_offset = Image.new('RGBA', (W, H), (0, 0, 0, 0))
shadow_offset.paste(sep_shadow, (6, 6))

base = Image.alpha_composite(base, shadow_offset)
base = Image.alpha_composite(base, ribbons_layer)
base = Image.alpha_composite(base, separators_layer)

# 3. Soft vignette gradient to anchor the composition
vignette = Image.new('RGBA', (W, H), (0, 0, 0, 0))
v_draw = ImageDraw.Draw(vignette)
for y in range(H - 280, H):
    t = (y - (H - 280)) / 280.0
    v_draw.line([(0, y), (W, y)], fill=(8, 16, 20, int(210 * t)))
base = Image.alpha_composite(base, vignette)

# 4. Place Mayor Vianne Cuevas on the RIGHT SIDE
mayor_cutout = Image.open('images/hero/mayor_clean_cutout.png').convert('RGBA')

# Target height for Mayor: 980px
target_h = 980
m_w, m_h = mayor_cutout.size
target_w = int(m_w * (target_h / float(m_h)))
mayor_resized = mayor_cutout.resize((target_w, target_h), Image.Resampling.LANCZOS)

# Position on the right side
mayor_x = W - target_w + 20
mayor_y = H - target_h + 10

# Soft multi-layered realistic drop shadow behind Mayor onto the ribbons
mayor_shadow = Image.new('RGBA', (W, H), (0, 0, 0, 0))
m_alpha = mayor_resized.split()[3]
m_shadow_patch = Image.new('RGBA', (target_w, target_h), (0, 0, 0, 185))
m_shadow_patch.putalpha(m_alpha)
mayor_shadow.paste(m_shadow_patch, (mayor_x - 22, mayor_y + 16))
mayor_shadow = mayor_shadow.filter(ImageFilter.GaussianBlur(22))

base = Image.alpha_composite(base, mayor_shadow)
base.paste(mayor_resized, (mayor_x, mayor_y), mayor_resized)

# 5. Overlays, Badges, and Typography (inspired by the reference flyer)
overlay = Image.new('RGBA', (W, H), (0, 0, 0, 0))
draw_o = ImageDraw.Draw(overlay)

font_dir = os.path.join(os.environ.get('WINDIR', 'C:\\Windows'), 'Fonts')
font_name = ImageFont.truetype(os.path.join(font_dir, 'impact.ttf'), 48)
font_title = ImageFont.truetype(os.path.join(font_dir, 'segoeuib.ttf'), 21)
font_badge_bold = ImageFont.truetype(os.path.join(font_dir, 'segoeuib.ttf'), 13)
font_badge_sub = ImageFont.truetype(os.path.join(font_dir, 'segoeui.ttf'), 11)

# Badge tags on the upper parts of each ribbon
badge_positions = [
    (105, "PABAHAY", "Housing Turn-Over"),
    (370, "PWD CARE", "Wheelchair Support"),
    (650, "AICS RELIEF", "Food & Rice Ayuda"),
    (935, "CASH AID", "Financial Assistance")
]

for bx, b_title, b_sub in badge_positions:
    px = bx + 22
    py = 32
    draw_o.rounded_rectangle([px, py, px + 200, py + 52], radius=12, fill=(10, 45, 52, 230), outline=(216, 186, 152, 240), width=2)
    draw_o.text((px + 14, py + 8), b_title, font=font_badge_bold, fill=(255, 235, 180, 255))
    draw_o.text((px + 14, py + 28), b_sub, font=font_badge_sub, fill=(255, 255, 255, 220))

# Bottom-left satin drape effect (like the purple silk in reference)
satin_poly = [
    (0, H - 190),
    (190, H - 250),
    (250, H),
    (0, H)
]
satin = Image.new('RGBA', (W, H), (0, 0, 0, 0))
s_draw = ImageDraw.Draw(satin)
s_draw.polygon(satin_poly, fill=(127, 3, 3, 210), outline=(216, 186, 152, 200))
satin = satin.filter(ImageFilter.GaussianBlur(8))
base = Image.alpha_composite(base, satin)

# Official Palayan Shield Crest (Inspired by the 14th June Shield in the reference)
shield_x = mayor_x - 175
shield_y = H - 275
shield_w = 200
shield_h = 175

shield_poly = [
    (shield_x, shield_y),
    (shield_x + shield_w, shield_y),
    (shield_x + shield_w, shield_y + 115),
    (shield_x + shield_w // 2, shield_y + shield_h),
    (shield_x, shield_y + 115)
]
draw_o.polygon([(p[0] + 6, p[1] + 6) for p in shield_poly], fill=(0, 0, 0, 140))
draw_o.polygon(shield_poly, fill=(127, 3, 3, 250), outline=(216, 186, 152, 255), width=3)

inner_shield = [
    (shield_x + 6, shield_y + 6),
    (shield_x + shield_w - 6, shield_y + 6),
    (shield_x + shield_w - 6, shield_y + 110),
    (shield_x + shield_w // 2, shield_y + shield_h - 10),
    (shield_x + 6, shield_y + 110)
]
draw_o.polygon(inner_shield, outline=(255, 255, 255, 180), width=1)

draw_o.text((shield_x + 42, shield_y + 26), "LUNGSOD NG", font=ImageFont.truetype(os.path.join(font_dir, 'segoeuib.ttf'), 15), fill=(216, 186, 152, 255))
draw_o.text((shield_x + 36, shield_y + 50), "PALAYAN", font=ImageFont.truetype(os.path.join(font_dir, 'impact.ttf'), 33), fill=(255, 255, 255, 255))
draw_o.text((shield_x + 32, shield_y + 98), "KALINGA HUB", font=ImageFont.truetype(os.path.join(font_dir, 'segoeuib.ttf'), 15), fill=(216, 186, 152, 240))
draw_o.text((shield_x + 58, shield_y + 124), "CSWDO", font=ImageFont.truetype(os.path.join(font_dir, 'segoeuib.ttf'), 12), fill=(255, 255, 255, 200))

# Mayor Name Ribbon Box (Right bottom, like EARNEST ribbon in reference)
banner_w = 600
banner_h = 100
banner_x = W - banner_w - 30
banner_y = H - banner_h - 25

draw_o.rounded_rectangle([banner_x + 6, banner_y + 6, banner_x + banner_w + 6, banner_y + banner_h + 6], radius=16, fill=(0, 0, 0, 140))
draw_o.rounded_rectangle([banner_x, banner_y, banner_x + banner_w, banner_y + banner_h], radius=16, fill=(10, 42, 48, 245), outline=(216, 186, 152, 255), width=2)
draw_o.line([(banner_x + 20, banner_y + 6), (banner_x + banner_w - 20, banner_y + 6)], fill=(216, 186, 152, 255), width=2)

draw_o.text((banner_x + 26, banner_y + 14), "HON. VIANDREI NICOLE \"VIANNE\" J. CUEVAS", font=ImageFont.truetype(os.path.join(font_dir, 'segoeuib.ttf'), 21), fill=(255, 255, 255, 255))
draw_o.text((banner_x + 26, banner_y + 46), "CITY MAYOR • LUNGSOD NG PALAYAN", font=ImageFont.truetype(os.path.join(font_dir, 'impact.ttf'), 23), fill=(216, 186, 152, 255))
draw_o.text((banner_x + 26, banner_y + 74), "One Palayan-Kalinga Hub • Kalinga at Serbisyong Tapat", font=ImageFont.truetype(os.path.join(font_dir, 'segoeui.ttf'), 13), fill=(230, 240, 245, 220))

# Merge all layers
final_image = Image.alpha_composite(base, overlay).convert('RGB')

out_path = 'images/palayan_hero_mayor_cuevas.jpg'
final_image.save(out_path, quality=98)
print('Refined final graphic saved to:', out_path)
