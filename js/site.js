
const SITE_PAGES = [
  {title:'Главная', url:'index.html', text:'Видеоигры GameVerse каталог новости обзоры сервисы'},
  {title:'Каталог игр', url:'games.html', text:'игры каталог RPG экшен стратегия симулятор хоррор приключения'},
  {title:'Жанры', url:'genres.html', text:'жанры RPG action стратегия симулятор киберспорт'},
  {title:'Платформы', url:'platforms.html', text:'ПК PlayStation Xbox Nintendo мобильные платформы'},
  {title:'Обзоры', url:'reviews.html', text:'обзоры игр рейтинги впечатления прохождение'},
  {title:'Мир киберспорта', url:'esports.html', text:'киберспорт турниры команды дисциплины esports'},
  {title:'Галерея', url:'gallery.html', text:'фото изображения скриншоты игровые миры'},
  {title:'Коллекция ссылок', url:'links.html', text:'ссылки Steam PlayStation Xbox Nintendo игровые ресурсы'},
  {title:'О сайте', url:'about.html', text:'GameVerse редакция о сайте видеоигры медиа'},
  {title:'Сервисы', url:'services.html', text:'поиск гостевая книга форум новости рейтинг статистика опрос'}
];

const games = [
 {name:'The Witcher 3: Wild Hunt',genre:'RPG',platform:'PC / PlayStation / Xbox / Switch',year:2015,tag:'История',rating:4.9},
 {name:'Cyberpunk 2077',genre:'Action RPG',platform:'PC / PlayStation / Xbox',year:2020,tag:'Открытый мир',rating:4.6},
 {name:'Minecraft',genre:'Sandbox',platform:'PC / Консоли / Mobile',year:2011,tag:'Творчество',rating:4.8},
 {name:'Baldur’s Gate 3',genre:'RPG',platform:'PC / PlayStation / Xbox',year:2023,tag:'Тактика',rating:4.9},
 {name:'Elden Ring',genre:'Action RPG',platform:'PC / PlayStation / Xbox',year:2022,tag:'Сложность',rating:4.8},
 {name:'Hades',genre:'Roguelike',platform:'PC / PlayStation / Xbox / Switch',year:2020,tag:'Динамика',rating:4.7},
 {name:'Forza Horizon 5',genre:'Гонки',platform:'PC / Xbox',year:2021,tag:'Автоспорт',rating:4.6},
 {name:'It Takes Two',genre:'Приключение',platform:'PC / PlayStation / Xbox / Switch',year:2021,tag:'Кооператив',rating:4.8},
 {name:'Stardew Valley',genre:'Симулятор',platform:'PC / Консоли / Mobile',year:2016,tag:'Уют',rating:4.7}
];

function esc(s){return String(s).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
function key(k){return 'gameverse_'+k}
function now(){return new Date().toLocaleString('ru-RU')}
function toast(msg,type='ok'){
  let box=document.querySelector('#toast'); if(!box){box=document.createElement('div');box.id='toast';box.style.position='fixed';box.style.right='18px';box.style.bottom='18px';box.style.zIndex='100';document.body.appendChild(box)}
  box.innerHTML=`<div class="notice ${type==='warn'?'warn':''}" style="margin:0;box-shadow:var(--shadow)">${esc(msg)}</div>`; setTimeout(()=>box.innerHTML='',2600);
}

function initActiveNav(){
 const p=location.pathname.split('/').pop()||'index.html';
 document.querySelectorAll('.nav a').forEach(a=>{ if(a.getAttribute('href')===p || (p==='' && a.getAttribute('href')==='index.html')) a.classList.add('active') });
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
function initWidgets(){
 const times=document.querySelectorAll('[data-clock]');
 if(times.length){ const tick=()=>times.forEach(el=>el.textContent=new Date().toLocaleString('ru-RU')); tick(); setInterval(tick,1000); }
 const currency=document.querySelector('[data-currency]');
 if(currency){
   fetch('https://open.er-api.com/v6/latest/EUR').then(r=>r.json()).then(d=>{currency.textContent=`1 EUR ≈ ${Number(d.rates?.RUB||0).toFixed(2)} ₽`}).catch(()=>currency.textContent='Курс EUR/RUB: данные требуют подключения к сети');
 }
 const weatherGrid=document.querySelector('[data-weather-grid]');
 const weatherUpdated=document.querySelector('[data-weather-updated]');
 if(weatherGrid){
   const cities=[
     {name:'Обнинск',country:'Россия',lat:55.096,lon:36.611,population:125000,rate:0.010},
     {name:'Москва',country:'Россия',lat:55.7558,lon:37.6173,population:13200000,rate:0.011},
     {name:'Санкт-Петербург',country:'Россия',lat:59.9343,lon:30.3351,population:5600000,rate:0.0105},
     {name:'Лондон',country:'Великобритания',lat:51.5074,lon:-0.1278,population:9000000,rate:0.010},
     {name:'Нью-Йорк',country:'США',lat:40.7128,lon:-74.006,population:8400000,rate:0.0105},
     {name:'Париж',country:'Франция',lat:48.8566,lon:2.3522,population:2200000,rate:0.010},
     {name:'Токио',country:'Япония',lat:35.6762,lon:139.6503,population:14000000,rate:0.011},
     {name:'Дубай',country:'ОАЭ',lat:25.2048,lon:55.2708,population:3900000,rate:0.012}
   ];
   const codes={
     0:['Ясно','☀️'],1:['Преимущественно ясно','🌤️'],2:['Переменная облачность','⛅'],3:['Пасмурно','☁️'],
     45:['Туман','🌫️'],48:['Изморозь и туман','🌫️'],51:['Слабая морось','🌦️'],53:['Морось','🌦️'],55:['Сильная морось','🌧️'],
     56:['Ледяная морось','🌧️'],57:['Сильная ледяная морось','🌧️'],61:['Небольшой дождь','🌦️'],63:['Дождь','🌧️'],65:['Сильный дождь','🌧️'],
     66:['Ледяной дождь','🌨️'],67:['Сильный ледяной дождь','🌨️'],71:['Небольшой снег','🌨️'],73:['Снег','❄️'],75:['Сильный снег','❄️'],
     77:['Снежные зёрна','❄️'],80:['Небольшой ливень','🌦️'],81:['Ливень','🌧️'],82:['Сильный ливень','⛈️'],
     85:['Снегопад','🌨️'],86:['Сильный снегопад','❄️'],95:['Гроза','⛈️'],96:['Гроза с градом','⛈️'],99:['Сильная гроза с градом','⛈️']
   };
   const latitudes=cities.map(c=>c.lat).join(',');
   const longitudes=cities.map(c=>c.lon).join(',');
   const url=`https://api.open-meteo.com/v1/forecast?latitude=${latitudes}&longitude=${longitudes}&current=temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m,is_day&temperature_unit=celsius&wind_speed_unit=kmh&timezone=auto&forecast_days=1`;
   fetch(url).then(r=>{if(!r.ok)throw new Error('weather');return r.json()}).then(data=>{
     const items=Array.isArray(data)?data:[data];
     weatherGrid.innerHTML=items.map((d,i)=>{
       const c=cities[i]||{}; const cur=d.current||{}; const info=codes[cur.weather_code]||['Погодные условия','🌡️'];
       const temp=Math.round(Number(cur.temperature_2m)); const feels=Math.round(Number(cur.apparent_temperature));
       const wind=Math.round(Number(cur.wind_speed_10m)); const hum=Math.round(Number(cur.relative_humidity_2m));
       const localTime=cur.time ? cur.time.slice(11,16) : '—';
       const hour=cur.time ? Number(cur.time.slice(11,13)) : new Date().getHours();
       const hourlyFactor=[0.32,0.28,0.24,0.20,0.18,0.20,0.26,0.34,0.46,0.58,0.66,0.70,0.74,0.78,0.86,0.98,1.08,1.20,1.30,1.38,1.42,1.30,1.04,0.74][Math.min(Math.max(hour,0),23)];
       const active=Math.max(120,Math.round((c.population||100000)*(c.rate||0.01)*hourlyFactor));
       const activeText=active>=100000?`${(active/1000).toFixed(0)} тыс.`:active>=10000?`${(active/1000).toFixed(1)} тыс.`:active.toLocaleString('ru-RU');
       return `<article class="weather-card"><div class="weather-top"><div><span class="weather-city">${esc(c.name)}</span><span class="small muted">${esc(c.country)}</span></div><span class="weather-icon" aria-hidden="true">${info[1]}</span></div><div class="weather-temp">${Number.isFinite(temp)?temp:'—'}°</div><div class="weather-desc">${esc(info[0])}</div><div class="weather-meta"><span>Ощущается ${Number.isFinite(feels)?feels:'—'}°</span><span>💧 ${Number.isFinite(hum)?hum:'—'}%</span><span>💨 ${Number.isFinite(wind)?wind:'—'} км/ч</span></div><div class="player-activity"><div><div class="small muted"><span class="activity-dot"></span>Играют сейчас</div><strong>≈ ${activeText}</strong></div><div class="small muted" title="Оценка зависит от численности города и местного времени">оценка</div></div><div class="small muted" style="margin-top:8px">Местное время ${esc(localTime)}</div></article>`;
     }).join('');
     if(weatherUpdated){weatherUpdated.textContent=`Обновлено: ${new Date().toLocaleTimeString('ru-RU',{hour:'2-digit',minute:'2-digit'})}`;}
   }).catch(()=>{
     weatherGrid.innerHTML='<div class="notice warn">Не удалось получить погодные данные. Обновите страницу при подключении к Интернету.</div>';
     if(weatherUpdated)weatherUpdated.textContent='Нет соединения';
   });
 }
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

function initGamesTable(){
 const wrap=document.querySelector('#games-table'); if(!wrap)return;
 const q=document.querySelector('#game-filter'); const genre=document.querySelector('#genre-filter');
 const render=()=>{
  const t=(q?.value||'').toLowerCase(); const g=genre?.value||'';
  const rows=games.filter(x=>(!t || `${x.name} ${x.genre} ${x.platform}`.toLowerCase().includes(t)) && (!g||x.genre===g));
  wrap.innerHTML=rows.length?rows.map(x=>`<tr><td><strong>${esc(x.name)}</strong></td><td>${esc(x.genre)}</td><td>${esc(x.platform)}</td><td>${x.year}</td><td>★ ${x.rating}</td><td><span class="tag">${esc(x.tag)}</span></td></tr>`).join(''):`<tr><td colspan="6">Ничего не найдено.</td></tr>`;
 };
 [q,genre].forEach(el=>el&&el.addEventListener('input',render)); render();
}

function initSearch(){
 const out=document.querySelector('#search-results'); if(!out)return;
 const input=document.querySelector('#site-query');
 const base = location.pathname.includes('/services/') ? '../' : '';
 function match(page,q){
   q=q.trim().toLowerCase(); if(!q)return true;
   // OR groups: "rpg OR minecraft"; inside group all words must match.
   return q.split(/\s+or\s+/i).some(group=>group.split(/\s+/).filter(Boolean).every(t=>`${page.title} ${page.text}`.toLowerCase().includes(t)));
 }
 function render(){
   const q=input.value; const res=SITE_PAGES.filter(p=>match(p,q));
   out.innerHTML=res.map(p=>`<div class="list-item"><h3><a href="${base}${p.url}">${esc(p.title)}</a></h3><p>${esc(p.text)}</p></div>`).join('') || '<div class="notice warn">По вашему запросу ничего не найдено.</div>';
 }
 input.addEventListener('input',render); render();
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
 const out=document.querySelector('#news-list'); if(!out)return; seedServiceData();
 const form=document.querySelector('#news-form'); const status=document.querySelector('#news-status');
 const render=()=>{const data=JSON.parse(localStorage.getItem(key('news'))||'[]');out.innerHTML=data.map(n=>`<article class="list-item"><div class="small muted">${esc(n.date)}</div><h3>${esc(n.title)}</h3><p>${esc(n.text)}</p></article>`).join('')};
 form?.addEventListener('submit',e=>{e.preventDefault();if(form.pass.value!=='gameadmin'){status.textContent='Демо-пароль администратора: gameadmin';status.className='notice warn';return}const d=JSON.parse(localStorage.getItem(key('news'))||'[]');d.unshift({title:form.title.value.trim(),text:form.text.value.trim(),date:now().slice(0,10)});localStorage.setItem(key('news'),JSON.stringify(d));form.reset();status.textContent='Новость добавлена.';status.className='notice';render()});render();
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
 initActiveNav(); initStats(); initWidgets(); seedServiceData(); initGamesTable(); initSearch(); initGuestbook(); initForum(); initNews(); initRating(); initStatsPage();
});
