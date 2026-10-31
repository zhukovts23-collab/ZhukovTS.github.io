from pathlib import Path
import re,json,html,xml.etree.ElementTree as E
from urllib.parse import urlencode
r=Path(__file__).resolve().parents[1];index=[]
for f in sorted([*r.glob('*.html'),*r.glob('services/*.html')]):
 if f.name=='admin.html':continue
 s=f.read_text();main=re.search(r'<main[^>]*>(.*?)</main>',s,re.S).group(1)
 index.append({'title':html.unescape(re.search(r'<title>(.*?)</title>',s).group(1)).replace(' — GameVerse',''),'url':str(f.relative_to(r)),'text':html.unescape(re.sub('<[^>]+>',' ',main))})
for g in E.parse(r/'games.xml').getroot():index.append({'title':g.findtext('title'),'url':'games.html?'+urlencode({'q':g.findtext('title')}),'text':' '.join(n.text for n in g)})
(r/'search-index.json').write_text(json.dumps(index,ensure_ascii=False),encoding='utf-8')
