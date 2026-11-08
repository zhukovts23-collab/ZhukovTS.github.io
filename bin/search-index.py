from pathlib import Path
import re,json,html,xml.etree.ElementTree as E
from urllib.parse import urlencode
r=Path(__file__).resolve().parents[1];index=[]
for f in sorted([*r.glob('*.html'),*r.glob('services/*.html')]):
 if f.name in ('admin.html','games.html') or f==r/'services/news.html':continue
 s=f.read_text();main=re.search(r'<main[^>]*>(.*?)</main>',s,re.S).group(1)
 index.append({'title':html.unescape(re.search(r'<title>(.*?)</title>',s).group(1)).replace(' — GameVerse',''),'url':str(f.relative_to(r)),'text':html.unescape(re.sub('<[^>]+>',' ',main))})
(r/'search-index.json').write_text(json.dumps(index,ensure_ascii=False),encoding='utf-8')
