"""Run only against a disposable initialized database. Never against production."""
import os,json,subprocess,tempfile,xml.etree.ElementTree as E
from pathlib import Path
php=os.environ.get('TEST_PHP','php');ini=os.environ.get('TEST_PHP_INI');cmd=[php]+(['-c',ini] if ini else [])
sid='';csrf='';checks=0

def request(action=None,data=None,query=None,controller='index.php',token=True,ip='127.0.0.1'):
 global sid
 q=query or {};q={**q,**({'action':action} if action else {})}
 with tempfile.NamedTemporaryFile() as f:
  env={**os.environ,'TEST_QUERY':json.dumps(q),'TEST_METHOD':'POST' if data is not None else 'GET','TEST_BODY':json.dumps(data) if data is not None else '', 'TEST_CSRF':csrf if token else 'wrong','TEST_SESSION':sid,'TEST_META':f.name,'TEST_CONTROLLER':controller,'TEST_IP':ip}
  p=subprocess.run(cmd+['tests/request.php'],env=env,capture_output=True,text=True)
  assert p.returncode==0,(p.stdout,p.stderr)
  meta=json.loads(Path(f.name).read_text());sid=meta['session'] or sid
  if p.stderr:print(p.stderr.strip())
  if controller=='index.php':
   try:return meta['status'],json.loads(p.stdout)
   except:raise AssertionError((p.stdout,p.stderr))
  return meta['status'],p.stdout

def check(cond,msg):
 global checks
 assert cond,msg
 checks+=1
 print('PASS',msg)
status,s=request('session');csrf=s['csrf'];check(len(csrf)==64,'Session / CSRF')
status,g=request('games');check(status==200 and len(g)==20,'20 games in MySQL')
_,g=request('games',query={'q':'Portal'});check(len(g)==1 and g[0]['title']=='Portal 2','Parameterized search')
_,g=request('games',query={'q':"' OR 1=1 --"});check(len(g)==0,'SQL injection is literal input')
_,a=request('analytics');check(int(a['summary']['total'])==20 and sum(int(x['total']) for x in a['genres'])==20,'Aggregate counts')
check(len(a['top'])==5,'TOP 5')
_,x=request(controller='xml.php',query={'q':'portal'});check('Portal 2' in x and 'Minecraft' not in x,'Server XSLT filtering')
_,x=request(controller='xml.php',query={'q':'"<script>alert(1)</script>'});check('<script>' not in x,'XSLT parameter escaping')
_,g=request('games',query={'sort':'year'});check([int(x['year']) for x in g]==sorted([int(x['year']) for x in g],reverse=True),'Year sorting')
status,_=request('message',{'kind':'guest','author':'QA','text':'test'},token=False);check(status==403,'CSRF rejected')
status,_=request('news_save',{'title':'QA','text':'test'});check(status==401,'Anonymous publication blocked')
status,_=request('message',{'kind':'guest','author':'QA','text':'Integration message'});check(status==201,'Guestbook insert')
_,m=request('messages');mid=next(x['id'] for x in m if x['body']=='Integration message');check(bool(mid),'Guestbook persisted')
status,_=request('message',{'kind':'forum','author':'QA','title':'QA Topic','text':'Topic body'});check(status==201,'Forum topic')
_,m=request('messages',query={'kind':'forum'});pid=next(x['id'] for x in m if x['title']=='QA Topic')
status,_=request('message',{'kind':'reply','parent_id':pid,'author':'QA','text':'Reply body'});check(status==201,'Forum reply')
status,_=request('rate',{'game_id':'portal-2','score':6});check(status==400,'Rating range')
request('rate',{'game_id':'portal-2','score':5});request('rate',{'game_id':'portal-2','score':3});_,rr=request('ratings');p=next(x for x in rr if x['id']=='portal-2');check(int(p['votes'])==1 and float(p['average'])==3,'Vote update, no duplicate')
request('vote',{'choice':'RPG'});request('vote',{'choice':'Шутер'});_,v=request('poll');check(sum(int(x['total']) for x in v)==1,'Poll one vote per session')
request('visit',{'page':'index.html'});_,s=request('stats');check(int(s['summary']['views'])>=1,'Server visit counter')
status,_=request('login',{'username':'admin','password':'wrong'});check(status==401,'Wrong password rejected')
status,_=request('login',{'username':'admin','password':os.environ['ADMIN_PASSWORD']});check(status==200,'Admin login')
request('news_save',{'title':'QA export & <test>','text':'Quotes " and ; delimiters\nSecond line'});_,n=request('news');check(any(x['title']=='QA export & <test>' for x in n),'Admin news stored')
request('moderate',{'id':mid,'visible':0});_,m=request('messages');check(not any(x['id']==mid for x in m),'Message hidden')
request('moderate',{'id':mid,'visible':1});_,m=request('messages');check(any(x['id']==mid for x in m),'Message restored')
request('block_word',{'phrase':'bannedtest'});status,_=request('message',{'kind':'guest','author':'QA','text':'bannedtest'});check(status==400,'Banned expression')
request('block_word',{'phrase':'bannedtest','remove':True});status,_=request('message',{'kind':'guest','author':'QA','text':'bannedtest'});check(status==201,'Expression unblocked')
for fmt in ['rss','xml','wrapped']:
 _,out=request(controller='export.php',query={'format':fmt});root=E.fromstring(out);check(root.tag in ['rss','news','exchange'],f'{fmt} export valid XML')
_,out=request(controller='export.php',query={'format':'csv'});import csv,io
rows=list(csv.reader(io.StringIO(out),delimiter=';'));check(any(r[1]=='QA export & <test>' and '\n' in r[2] for r in rows[1:]),'CSV escaping round trip')
_,out=request(controller='export.php',query={'format':'wrapped'});rows=list(csv.reader(io.StringIO(E.fromstring(out).findtext('data')),delimiter=';'));check(len(rows)>1,'CSV inside XML processed')
status,r=request('rates');check(status==200 and r['rates']['EUR']>0,'Live CBR API')
status,b=request('budget',query={'amount':'10','currency':'EUR','fee':'5'});check(status==200 and b['rub']==round(10*r['rates']['EUR']*1.05,2),'Budget arithmetic with nominal')
status,_=request('budget',query={'amount':'-1'});check(status==400,'Negative budget rejected')
status,w=request('weather');check(status==200 and len(w['items'])>0,'Live Russian weather RSS')
status,f=request('feed');check(status==200 and f['external']['ok'] and len({x['source'] for x in f['items']})==2,'Live RSS merged with DB '+str(f.get('external'))+' sources='+str({x['source'] for x in f['items']}))
_,out=request(controller='export.php',query={'format':'rss','source':'merged'});check(len(E.fromstring(out).findall('channel/item'))>len(n),'Merged RSS export')
request('logout',{});status,_=request('admin_data');check(status==401,'Logout removes admin access')
print(f'{checks} checks passed')
