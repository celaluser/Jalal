#!/usr/bin/env python3
"""Panel Araçları (birleşik eklenti) derleyici.

Kaynak üç ayrı eklentidir: kategori-temizle/, servis-pasifle/, servis-tasi/. Bu betik onların içerik betiklerini ve
mini panellerini panel-araclari/ içine kopyalar (kategori.js, pasifle.js, tasi.js; kt.html, sp.html, pt.html).
panel-araclari/ içinde elle yazılan dosyalar: manifest.json, ortak.js, background.js, popup.html, popup.js, README.md.
Kaynaklardan birini değiştirince yeniden çalıştır:  python3 build-panel-araclari.py
"""
import json, pathlib, shutil
ROOT = pathlib.Path(__file__).resolve().parent
OUT = ROOT / 'panel-araclari'
TOOLS = [('kategori-temizle', 'kategori.js', 'kt'), ('servis-pasifle', 'pasifle.js', 'sp'), ('servis-tasi', 'tasi.js', 'pt')]
HEAD = '// OTOMATİK ÜRETİLDİ — düzenleme: kaynak {src}/{name} (build-panel-araclari.py kopyalar).\n'
versions = {}
for src, js, key in TOOLS:
    d = ROOT / src
    (OUT / js).write_text(HEAD.format(src=src, name='content.js') + (d / 'content.js').read_text(encoding='utf-8'), encoding='utf-8')
    html = (d / 'popup.html').read_text(encoding='utf-8')
    assert html.count('<script src="popup.js"></script>') == 1, src
    # birleşik mini panelde her araç bir sekmenin içinde (iframe) açılır: gövde genişliği çerçeveye uysun
    html = html.replace('<script src="popup.js"></script>', '<script src="%s.js"></script>' % key).replace('width:340px;', 'width:auto;', 1)
    (OUT / (key + '.html')).write_text(html, encoding='utf-8')
    (OUT / (key + '.js')).write_text(HEAD.format(src=src, name='popup.js') + (d / 'popup.js').read_text(encoding='utf-8'), encoding='utf-8')
    versions[key] = json.loads((d / 'manifest.json').read_text(encoding='utf-8'))['version']
for f in (ROOT / 'kategori-temizle' / 'icons').iterdir():
    shutil.copy(f, OUT / 'icons' / f.name)
print('panel-araclari güncellendi:', versions)
