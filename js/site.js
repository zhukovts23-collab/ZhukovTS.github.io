
const SITE_PAGES = [
  {title:'Главная',url:'index.html',text:'GameVerse видеоигры каталог новости обзоры сервисы погода курс валют'},
  {title:'Каталог игр',url:'games.html',text:'видеоигры каталог RPG экшен стратегия симулятор хоррор приключения XML'},
  {title:'Жанры',url:'genres.html',text:'жанры RPG action стратегия симулятор киберспорт'},
  {title:'Платформы',url:'platforms.html',text:'ПК PlayStation Xbox Nintendo мобильные платформы'},
  {title:'Обзоры',url:'reviews.html',text:'обзоры игр рейтинги впечатления прохождение'},
  {title:'Мир киберспорта',url:'esports.html',text:'киберспорт турниры команды дисциплины esports'},
  {title:'Галерея',url:'gallery.html',text:'фото изображения скриншоты игровые миры'},
  {title:'Ссылки',url:'links.html',text:'Steam PlayStation Xbox Nintendo игровые ресурсы'},
  {title:'XML-каталог',url:'xml.html',text:'XML каталог videogames игры XML-файл структура данных games.xml'},
  {title:'База данных',url:'database.html',text:'БД база данных игры каталог новости публикации схема SQL'},
  {title:'RSS-лента',url:'rss.html',text:'RSS лента новости подписка XML RSS feed'},
  {title:'О сайте',url:'about.html',text:'GameVerse редакция о сайте видеоигры медиа'},
  {title:'Сервисы',url:'services.html',text:'поиск гостевая книга форум новости рейтинг статистика'},
  {title:'Поиск по сайту',url:'services/search.html',text:'поиск разделов ключевые слова AND OR'},
  {title:'Новости',url:'services/news.html',text:'новости GameVerse публикации редакционный архив RSS'},
  {title:'Форум',url:'services/forum.html',text:'форум темы ответы сообщество обсуждения'},
  {title:'Гостевая книга',url:'services/guestbook.html',text:'гостевая книга сообщения обратная связь'},
  {title:'Рейтинг игр',url:'services/rating.html',text:'рейтинг оценка игр звёзды'},
  {title:'Статистика посещаемости',url:'services/stats.html',text:'статистика просмотры страницы посещаемость'}
];



function esc(s){return String(s).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
function key(k){return 'gameverse_'+k}
function now(){return new Date().toLocaleString('ru-RU')}
function toast(msg,type='ok'){
  let box=document.querySelector('#toast'); if(!box){box=document.createElement('div');box.id='toast';box.style.position='fixed';box.style.right='18px';box.style.bottom='18px';box.style.zIndex='100';document.body.appendChild(box)}
  box.innerHTML=`<div class="notice ${type==='warn'?'warn':''}" style="margin:0;box-shadow:var(--shadow)">${esc(msg)}</div>`; setTimeout(()=>box.innerHTML='',2600);
}

function initGlobalNavigation(){
 const nav=document.querySelector('.nav');
 const header=document.querySelector('.site-header');
 if(!nav||!header)return;
 const inServicePath=location.pathname.includes('/services/');
 const base=inServicePath?'../':'';
 const links=[
  ['Главная','index.html'],['Каталог','games.html'],['Жанры','genres.html'],['Платформы','platforms.html'],
  ['Обзоры','reviews.html'],['Киберспорт','esports.html'],['Галерея','gallery.html'],['Ссылки','links.html'],
  ['XML','xml.html'],['БД','database.html'],['RSS','rss.html'],['Сервисы','services.html'],['О сайте','about.html']
 ];
 nav.innerHTML=links.map(([label,url])=>`<a href="${base}${url}">${label}</a>`).join('');
 let row=header.querySelector('.header-search-row');
 if(!row){
  row=document.createElement('div');row.className='container header-search-row';
  row.innerHTML='<form class="global-search" role="search"><label class="sr-only" for="global-site-query">Поиск по GameVerse</label><input class="input" type="search" id="global-site-query" placeholder="Поиск по сайту: игра, жанр, платформа" aria-label="Поиск по сайту"><button class="btn primary" type="submit">Найти</button></form>';
  header.querySelector('.header-row')?.insertAdjacentElement('afterend',row);
 }
 const form=row.querySelector('form');
 const input=row.querySelector('input');
 form?.addEventListener('submit',event=>{
  event.preventDefault();
  const query=input.value.trim();
  const target=new URL(base+'services/search.html',location.href);
  if(query)target.searchParams.set('q',query);
  location.href=target.href;
 });
}
function initActiveNav(){
 const currentPath=location.pathname.replace(/\/$/,'/index.html');
 document.querySelectorAll('.nav a').forEach(a=>{
  const targetPath=new URL(a.href,location.href).pathname.replace(/\/$/,'/index.html');
  if(targetPath===currentPath)a.classList.add('active');
 });
}
function initStats(){
 const page=location.pathname.split('/').pop()||'index.html';
 const data=JSON.parse(localStorage.getItem(key('stats'))||'{}');
 const entry=data[page]||{views:0,last:''}; entry.views++; entry.last=now(); data[page]=entry;
 let total=Object.values(data).reduce((s,x)=>s+x.views,0); let hosts=Number(localStorage.getItem(key('hosts'))||0);
 const visitorKey=key('visitor');
 if(!sessionStorage.getItem(visitorKey)){ sessionStorage.setItem(visitorKey,'1'); hosts++; localStorage.setItem(key('hosts'),String(hosts)); }
 localStorage.setItem(key('stats'),JSON.stringify(data));
 document.querySelectorAll('[data-counter]').forEach(el=>el.textContent=total);
 document.querySelectorAll('[data-hosts]').forEach(el=>el.textContent=hosts);
}
function fetchWithTimeout(url,timeoutMs=9000){
 const controller=new AbortController();
 const timer=setTimeout(()=>controller.abort(),timeoutMs);
 return fetch(url,{signal:controller.signal}).finally(()=>clearTimeout(timer));
}
function cityLocalTime(timeZone){
 try{return new Intl.DateTimeFormat('ru-RU',{hour:'2-digit',minute:'2-digit',timeZone}).format(new Date());}
 catch{return new Date().toLocaleTimeString('ru-RU',{hour:'2-digit',minute:'2-digit'});}
}
function estimateCityPlayers(city,hour){
 const hourlyFactor=[0.32,0.28,0.24,0.20,0.18,0.20,0.26,0.34,0.46,0.58,0.66,0.70,0.74,0.78,0.86,0.98,1.08,1.20,1.30,1.38,1.42,1.30,1.04,0.74][Math.min(Math.max(hour,0),23)];
 const active=Math.max(120,Math.round((city.population||100000)*(city.rate||0.01)*hourlyFactor));
 return active>=100000?`${Math.round(active/1000)} тыс.`:active>=10000?`${(active/1000).toFixed(1)} тыс.`:active.toLocaleString('ru-RU');
}
function initWidgets(){
 const times=document.querySelectorAll('[data-clock]');
 if(times.length){const tick=()=>times.forEach(el=>el.textContent=new Date().toLocaleString('ru-RU',{dateStyle:'short',timeStyle:'medium'}));tick();setInterval(tick,1000);}
 const currency=document.querySelector('[data-currency]');
 const currencyUpdated=document.querySelector('[data-currency-updated]');
 if(currency){
  fetchWithTimeout('https://open.er-api.com/v6/latest/EUR',9000)
   .then(r=>{if(!r.ok)throw new Error('currency');return r.json();})
   .then(data=>{
    const rate=Number(data?.rates?.RUB);
    if(data?.result==='error'||!Number.isFinite(rate)||rate<=0)throw new Error('rate');
    currency.textContent=`1 EUR ≈ ${rate.toLocaleString('ru-RU',{minimumFractionDigits:2,maximumFractionDigits:2})} ₽`;
    if(currencyUpdated)currencyUpdated.textContent='Источник: ExchangeRate-API · обновлено '+new Date().toLocaleTimeString('ru-RU',{hour:'2-digit',minute:'2-digit'});
   })
   .catch(()=>{currency.textContent='Временно недоступен';if(currencyUpdated)currencyUpdated.textContent='Проверьте подключение к Интернету';});
 }
 const grid=document.querySelector('[data-weather-grid]');
 const updated=document.querySelector('[data-weather-updated]');
 if(!grid)return;
 const cities=[
  {name:'Обнинск',country:'Россия',lat:55.096,lon:36.611,zone:'Europe/Moscow',population:125000,rate:.010},
  {name:'Москва',country:'Россия',lat:55.7558,lon:37.6173,zone:'Europe/Moscow',population:13200000,rate:.011},
  {name:'Санкт-Петербург',country:'Россия',lat:59.9343,lon:30.3351,zone:'Europe/Moscow',population:5600000,rate:.0105},
  {name:'Лондон',country:'Великобритания',lat:51.5074,lon:-.1278,zone:'Europe/London',population:9000000,rate:.010},
  {name:'Нью-Йорк',country:'США',lat:40.7128,lon:-74.006,zone:'America/New_York',population:8400000,rate:.0105},
  {name:'Париж',country:'Франция',lat:48.8566,lon:2.3522,zone:'Europe/Paris',population:2200000,rate:.010},
  {name:'Токио',country:'Япония',lat:35.6762,lon:139.6503,zone:'Asia/Tokyo',population:14000000,rate:.011},
  {name:'Дубай',country:'ОАЭ',lat:25.2048,lon:55.2708,zone:'Asia/Dubai',population:3900000,rate:.012}
 ];
 const codes={
  0:['Ясно','☀️'],1:['Преимущественно ясно','🌤️'],2:['Переменная облачность','⛅'],3:['Пасмурно','☁️'],
  45:['Туман','🌫️'],48:['Изморозь и туман','🌫️'],51:['Слабая морось','🌦️'],53:['Морось','🌦️'],55:['Сильная морось','🌧️'],
  56:['Ледяная морось','🌧️'],57:['Сильная ледяная морось','🌧️'],61:['Небольшой дождь','🌦️'],63:['Дождь','🌧️'],65:['Сильный дождь','🌧️'],
  66:['Ледяной дождь','🌨️'],67:['Сильный ледяной дождь','🌨️'],71:['Небольшой снег','🌨️'],73:['Снег','❄️'],75:['Сильный снег','❄️'],
  77:['Снежные зёрна','❄️'],80:['Небольшой ливень','🌦️'],81:['Ливень','🌧️'],82:['Сильный ливень','⛈️'],85:['Снегопад','🌨️'],
  86:['Сильный снегопад','❄️'],95:['Гроза','⛈️'],96:['Гроза с градом','⛈️'],99:['Сильная гроза с градом','⛈️']
 };
 const card=(city,current)=>{
  const h=current?.time?Number(current.time.slice(11,13)):Number(new Intl.DateTimeFormat('en-GB',{hour:'2-digit',hourCycle:'h23',timeZone:city.zone}).format(new Date()));
  const info=current?(codes[current.weather_code]||['Погодные условия','🌡️']):['Погода временно недоступна','⌛'];
  const numeric=(value,suffix='')=>Number.isFinite(Number(value))&&value!==null?Math.round(Number(value))+suffix:'—';
  const temperature=current?numeric(current.temperature_2m,'°'):'—';
  const feels=current?numeric(current.apparent_temperature,'°'):'—';
  const humidity=current?numeric(current.relative_humidity_2m,'%'):'—';
  const wind=current?numeric(current.wind_speed_10m,' км/ч'):'—';
  return `<article class="weather-card"><div class="weather-top"><div><span class="weather-city">${esc(city.name)}</span><span class="small muted">${esc(city.country)}</span></div><span class="weather-icon" aria-hidden="true">${info[1]}</span></div><div class="weather-temp">${temperature}</div><div class="weather-desc">${esc(info[0])}</div><div class="weather-meta"><span>Ощущается ${feels}</span><span>💧 ${humidity}</span><span>💨 ${wind}</span></div><div class="player-activity"><div><div class="small muted"><span class="activity-dot"></span>Играют сейчас</div><strong>≈ ${estimateCityPlayers(city,Number.isFinite(h)?h:12)}</strong></div><div class="small muted" title="Модельная оценка, не официальная статистика">оценка</div></div><div class="small muted" style="margin-top:8px">Местное время ${esc(current?.time?current.time.slice(11,16):cityLocalTime(city.zone))}</div></article>`;
 };
 // Separate requests prevent a bad response for one coordinate from removing weather for every city.
 Promise.all(cities.map(async city=>{
  const query=new URLSearchParams({
   latitude:String(city.lat),longitude:String(city.lon),
   current:'temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m,is_day',
   temperature_unit:'celsius',wind_speed_unit:'kmh',timezone:city.zone,forecast_days:'1'
  });
  try{
   const response=await fetchWithTimeout('https://api.open-meteo.com/v1/forecast?'+query.toString(),9000);
   if(!response.ok)throw new Error('weather');
   const data=await response.json();
   return {city,current:data.current||null};
  }catch{return {city,current:null};}
 })).then(results=>{
  grid.innerHTML=results.map(result=>card(result.city,result.current)).join('');
  const failures=results.filter(result=>!result.current).length;
  if(updated)updated.textContent=failures?(`Обновлено частично: ${results.length-failures} из ${results.length} городов`):('Обновлено: '+new Date().toLocaleTimeString('ru-RU',{hour:'2-digit',minute:'2-digit'}));
 });
}
function seedServiceData(){
 if(!localStorage.getItem(key('guestbook'))){
   localStorage.setItem(key('guestbook'),JSON.stringify([
    {name:'GameFan',text:'Отличный каталог — особенно понравился раздел с жанрами!',date:'01.10.2026, 00:12'},
    {name:'Player_42',text:'Добавьте больше материалов про кооперативные игры.',date:'01.10.2026, 00:25'}
   ]));
 }
 if(!localStorage.getItem(key('forum'))){
   localStorage.setItem(key('forum'),JSON.stringify([
    {id:1,title:'Во что играете сейчас?',author:'Moderator',date:'01.10.2026, 00:30',replies:[{author:'Player_42',text:'Сейчас прохожу Baldur’s Gate 3.',date:'01.10.2026, 00:41'}]},
    {id:2,title:'Любимые игры на двоих',author:'GameFan',date:'01.10.2026, 00:55',replies:[]}
   ]));
 }
 if(!localStorage.getItem(key('news'))){
   localStorage.setItem(key('news'),JSON.stringify([
    {title:'Открыт новый раздел «Мир киберспорта»',text:'В каталоге появился отдельный раздел о дисциплинах, командах и турнирной сцене.',date:'01.10.2026'},
    {title:'Обновлена коллекция ссылок',text:'Добавлены быстрые переходы к официальным игровым площадкам и справочным ресурсам.',date:'30.09.2026'}
   ]));
 }
}

async function loadGameCatalog(){
 const response=await fetchWithTimeout('games.xml',9000);
 if(!response.ok)throw new Error('Не удалось загрузить games.xml');
 const xmlText=await response.text();
 const doc=new DOMParser().parseFromString(xmlText,'application/xml');
 if(doc.querySelector('parsererror'))throw new Error('Ошибка в games.xml');
 const entries=[...doc.querySelectorAll('game')].map(node=>({
  name:node.querySelector('title')?.textContent.trim()||'',
  genre:node.querySelector('genre')?.textContent.trim()||'',
  platform:node.querySelector('platforms')?.textContent.trim()||'',
  year:Number(node.querySelector('year')?.textContent.trim())||'—',
  rating:Number(node.querySelector('rating')?.textContent.trim())||0,
  tag:node.querySelector('tag')?.textContent.trim()||''
 })).filter(game=>game.name);
 if(!entries.length)throw new Error('В XML-файле не найдено игр');
 return entries;
}
function initGamesTable(){
 const wrap=document.querySelector('#games-table');if(!wrap)return;
 const query=document.querySelector('#game-filter');const genre=document.querySelector('#genre-filter');
 let entries=[];
 const render=()=>{
  const q=(query?.value||'').trim().toLowerCase();const g=genre?.value||'';
  const matches=entries.filter(game=>(!q||`${game.name} ${game.genre} ${game.platform} ${game.year} ${game.tag}`.toLowerCase().includes(q))&&(!g||game.genre===g));
  wrap.innerHTML=matches.length?matches.map(game=>`<tr><td><strong>${esc(game.name)}</strong></td><td>${esc(game.genre)}</td><td>${esc(game.platform)}</td><td>${game.year}</td><td>★ ${Number(game.rating).toFixed(1)}</td><td><span class="tag">${esc(game.tag)}</span></td></tr>`).join(''):`<tr><td colspan="6">${entries.length?'Ничего не найдено.':'Загрузка XML-каталога…'}</td></tr>`;
 };
 [query,genre].forEach(element=>element?.addEventListener('input',render));
 genre?.addEventListener('change',render);
 loadGameCatalog().then(games=>{entries=games;render();}).catch(()=>{wrap.innerHTML='<tr><td colspan="6">Не удалось загрузить games.xml. Проверьте, что файл опубликован в корне сайта.</td></tr>';});
}
function initXmlCatalog(){
 const table=document.querySelector('#xml-catalog-table');if(!table)return;
 const status=document.querySelector('#xml-catalog-status');
 loadGameCatalog().then(games=>{
  table.innerHTML=games.map(g=>`<tr><td>${esc(g.name)}</td><td>${esc(g.genre)}</td><td>${esc(g.platform)}</td><td>${g.year}</td><td>★ ${Number(g.rating).toFixed(1)}</td><td>${esc(g.tag)}</td></tr>`).join('');
  if(status)status.textContent=`Каталог загружен из games.xml · записей: ${games.length}`;
 }).catch(()=>{if(status){status.textContent='Не удалось загрузить XML-каталог.';status.className='notice warn';}table.innerHTML='<tr><td colspan="6">XML-файл недоступен.</td></tr>';});
}
function initRssPage(){
 const out=document.querySelector('#rss-items');if(!out)return;
 const status=document.querySelector('#rss-status');
 fetchWithTimeout('rss.xml',9000).then(response=>{if(!response.ok)throw new Error('rss');return response.text();}).then(text=>{
  const doc=new DOMParser().parseFromString(text,'application/xml');
  if(doc.querySelector('parsererror'))throw new Error('rss xml');
  const items=[...doc.querySelectorAll('channel > item')];
  out.innerHTML=items.map(item=>{
   const title=item.querySelector('title')?.textContent.trim()||'Публикация GameVerse';
   const description=item.querySelector('description')?.textContent.trim()||'';
   const link=item.querySelector('link')?.textContent.trim()||'services/news.html';
   const date=item.querySelector('pubDate')?.textContent.trim()||'';
   return `<article class="feed-item"><div class="small muted">${esc(date)}</div><h3><a href="${esc(link)}" target="_blank" rel="noopener noreferrer">${esc(title)}</a></h3><p>${esc(description)}</p></article>`;
  }).join('')||'<div class="notice">В ленте пока нет публикаций.</div>';
  if(status){status.textContent=`RSS-лента загружена · публикаций: ${items.length}`;status.className='notice';}
 }).catch(()=>{if(status){status.textContent='Не удалось загрузить rss.xml.';status.className='notice warn';}});
}

function initSearch(){
 const out=document.querySelector('#search-results');if(!out)return;
 const input=document.querySelector('#site-query');if(!input)return;
 input.value=new URLSearchParams(location.search).get('q')||'';
 const base=location.pathname.includes('/services/')?'../':'';
 const form=input.closest('form');
 function matches(page,query){
  query=query.trim().toLowerCase();if(!query)return true;
  return query.split(/\s+or\s+/i).some(group=>group.split(/\s+/).filter(Boolean).every(term=>`${page.title} ${page.text}`.toLowerCase().includes(term)));
 }
 function render(){
  const found=SITE_PAGES.filter(page=>matches(page,input.value));
  out.innerHTML=found.map(page=>`<div class="list-item"><h3><a href="${base}${page.url}">${esc(page.title)}</a></h3><p>${esc(page.text)}</p></div>`).join('')||'<div class="notice warn">По вашему запросу ничего не найдено.</div>';
 }
 input.addEventListener('input',render);
 form?.addEventListener('submit',event=>{event.preventDefault();const url=new URL(location.href);if(input.value.trim())url.searchParams.set('q',input.value.trim());else url.searchParams.delete('q');history.replaceState(null,'',url);render();});
 render();
}

function initGuestbook(){
 const out=document.querySelector('#guest-list'); if(!out)return; seedServiceData();
 const form=document.querySelector('#guest-form'); const admin=document.querySelector('#admin-mode'); const pass=document.querySelector('#admin-pass');
 function render(){ const data=JSON.parse(localStorage.getItem(key('guestbook'))||'[]'); out.innerHTML=data.map((m,i)=>`<div class="list-item"><div class="small muted">${esc(m.date)}</div><h3>${esc(m.name)}</h3><p>${esc(m.text)}</p>${admin?.checked?`<button class="btn" data-del="${i}">Удалить</button>`:''}</div>`).join('')||'<div class="notice">Пока нет сообщений.</div>'; out.querySelectorAll('[data-del]').forEach(b=>b.onclick=()=>{let d=JSON.parse(localStorage.getItem(key('guestbook')));d.splice(Number(b.dataset.del),1);localStorage.setItem(key('guestbook'),JSON.stringify(d));render();}); }
 form.addEventListener('submit',e=>{e.preventDefault(); const name=form.name.value.trim(), text=form.text.value.trim(); if(!name||!text)return toast('Заполните имя и сообщение','warn'); const bad=['спам','хулиган']; if(bad.some(w=>text.toLowerCase().includes(w)))return toast('Сообщение содержит запрещённое выражение','warn'); const d=JSON.parse(localStorage.getItem(key('guestbook'))||'[]');d.unshift({name,text,date:now()});localStorage.setItem(key('guestbook'),JSON.stringify(d));form.reset();render();toast('Сообщение добавлено');});
 admin?.addEventListener('change',()=>{if(admin.checked){if(pass.value!=='gameadmin'){admin.checked=false;toast('Демо-пароль модератора: gameadmin','warn');return}}render()}); render();
}

function initForum(){
 const list=document.querySelector('#topic-list'); if(!list)return; seedServiceData();
 let data=JSON.parse(localStorage.getItem(key('forum'))||'[]'); const form=document.querySelector('#topic-form');
 const render=()=>{data=JSON.parse(localStorage.getItem(key('forum'))||'[]');list.innerHTML=data.map(t=>`<div class="list-item"><div class="small muted">${esc(t.date)} · ${esc(t.author)}</div><h3>${esc(t.title)}</h3><details><summary>Ответы (${t.replies.length})</summary><div class="list" style="margin-top:10px">${t.replies.map(r=>`<div class="list-item"><strong>${esc(r.author)}</strong><div class="small muted">${esc(r.date)}</div><p>${esc(r.text)}</p></div>`).join('')||'<p>Ответов пока нет.</p>'}</div><form data-reply="${t.id}" class="form-grid" style="margin-top:12px"><div class="field"><label>Имя</label><input class="input" name="author" required></div><div class="field"><label>Ответ</label><input class="input" name="text" required></div><div class="field full"><button class="btn primary">Ответить</button></div></form></details></div>`).join(''); list.querySelectorAll('form[data-reply]').forEach(f=>f.onsubmit=e=>{e.preventDefault();const id=Number(f.dataset.reply);const t=data.find(x=>x.id===id);t.replies.push({author:f.author.value.trim(),text:f.text.value.trim(),date:now()});localStorage.setItem(key('forum'),JSON.stringify(data));render();toast('Ответ опубликован');});};
 form.addEventListener('submit',e=>{e.preventDefault();const title=form.title.value.trim(), author=form.author.value.trim();if(!title||!author)return;const id=Date.now();data.unshift({id,title,author,date:now(),replies:[]});localStorage.setItem(key('forum'),JSON.stringify(data));form.reset();render();toast('Тема создана')}); render();
}

function initNews(){
 const out=document.querySelector('#news-list');if(!out)return;seedServiceData();
 const form=document.querySelector('#news-form');const status=document.querySelector('#news-status');
 const render=()=>{
  const data=JSON.parse(localStorage.getItem(key('news'))||'[]');
  out.innerHTML=data.map(item=>`<article class="list-item"><div class="small muted">${esc(item.date)}</div><h3>${esc(item.title)}</h3><p>${esc(item.text)}</p></article>`).join('')||'<div class="notice">Публикаций пока нет.</div>';
 };
 form?.addEventListener('submit',event=>{
  event.preventDefault();
  if(form.pass.value!=='gameadmin'){status.textContent='Доступ к публикации не подтверждён.';status.className='notice warn';return;}
  const title=form.title.value.trim(),text=form.text.value.trim();
  if(!title||!text){status.textContent='Заполните заголовок и текст новости.';status.className='notice warn';return;}
  const data=JSON.parse(localStorage.getItem(key('news'))||'[]');
  data.unshift({title,text,date:now().slice(0,10)});
  localStorage.setItem(key('news'),JSON.stringify(data));form.reset();
  status.textContent='Новость сохранена в локальном хранилище этого браузера.';status.className='notice';render();
 });
 document.querySelector('#export-news-rss')?.addEventListener('click',()=>{
  const data=JSON.parse(localStorage.getItem(key('news'))||'[]');
  const xmlEscape=value=>String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&apos;');
  const base=new URL('../',location.href).href;
  const items=data.map(item=>`<item><title>${xmlEscape(item.title)}</title><description>${xmlEscape(item.text)}</description><link>${xmlEscape(base+'services/news.html')}</link><guid isPermaLink="false">${xmlEscape(item.title+'-'+item.date)}</guid><pubDate>${new Date().toUTCString()}</pubDate></item>`).join('\n');
  const feed=`<?xml version="1.0" encoding="UTF-8"?>\n<rss version="2.0"><channel><title>GameVerse — новости</title><link>${xmlEscape(base)}</link><description>Новости игрового медиа GameVerse</description>${items}</channel></rss>`;
  const blob=new Blob([feed],{type:'application/rss+xml;charset=utf-8'});const objectUrl=URL.createObjectURL(blob);
  const anchor=document.createElement('a');anchor.href=objectUrl;anchor.download='gameverse-news.xml';document.body.appendChild(anchor);anchor.click();anchor.remove();
  setTimeout(()=>URL.revokeObjectURL(objectUrl),1000);toast('RSS-файл сформирован для последующей публикации.');
 });
 render();
}
function initHomeNewsPreview(){
 const out=document.querySelector('#home-news-list');if(!out)return;
 const news=JSON.parse(localStorage.getItem(key('news'))||'[]').slice(0,3);
 out.innerHTML=news.map(item=>`<article class="home-news-item"><a href="services/news.html">${esc(item.title)}</a><p>${esc(item.text)}</p><div class="small muted">${esc(item.date)}</div></article>`).join('')||'<div class="notice">Новости скоро появятся.</div>';
}

function initRating(){
 const out=document.querySelector('#rating-result'); if(!out)return;
 const gamesList=['The Witcher 3: Wild Hunt','Cyberpunk 2077','Minecraft','Baldur’s Gate 3','Elden Ring','Hades'];
 const box=document.querySelector('#rating-box');
 const render=()=>{const data=JSON.parse(localStorage.getItem(key('ratings'))||'{}'); const vals=Object.values(data); const avg=vals.length?vals.reduce((a,b)=>a+b,0)/vals.length:0; out.textContent=vals.length?`Средняя оценка сайта: ${avg.toFixed(1)} / 5 · голосов: ${vals.length}`:'Пока нет голосов'; box.innerHTML=gamesList.map(g=>{const id=g.replace(/[^a-z0-9]/gi,'_');const v=data[id];return `<div class="list-item" style="display:flex;align-items:center;justify-content:space-between;gap:12px"><div><strong>${esc(g)}</strong><div class="small muted">${v?`Ваша/последняя оценка: ${v}/5`:'оцените игру'}</div></div><div class="rating-stars" data-game="${esc(id)}">${[1,2,3,4,5].map(n=>`<button type="button" data-rate="${n}" title="${n}">★</button>`).join('')}</div></div>`}).join(''); box.querySelectorAll('[data-game]').forEach(el=>el.querySelectorAll('button').forEach(b=>b.onclick=()=>{const d=JSON.parse(localStorage.getItem(key('ratings'))||'{}');d[el.dataset.game]=Number(b.dataset.rate);localStorage.setItem(key('ratings'),JSON.stringify(d));render();toast('Оценка сохранена')}));};render();
}

function initStatsPage(){
 const out=document.querySelector('#stats-table'); if(!out)return;
 const data=JSON.parse(localStorage.getItem(key('stats'))||'{}');
 const rows=Object.entries(data).sort((a,b)=>b[1].views-a[1].views); const total=rows.reduce((s,[,v])=>s+v.views,0);document.querySelector('[data-total-views]')?.replaceChildren(document.createTextNode(String(total)));
 out.innerHTML=rows.map(([page,v])=>`<tr><td>${esc(page)}</td><td>${v.views}</td><td>${esc(v.last)}</td></tr>`).join('')||'<tr><td colspan="3">Статистика появится после посещения страниц.</td></tr>';
}

document.addEventListener('DOMContentLoaded',()=>{
 initGlobalNavigation(); initActiveNav(); initStats(); initWidgets(); seedServiceData(); initGamesTable(); initXmlCatalog(); initRssPage(); initSearch(); initGuestbook(); initForum(); initNews(); initHomeNewsPreview(); initRating(); initStatsPage();
});
