'use strict';
(()=>{
 const toggle=document.querySelector('.menu-toggle'),menu=document.getElementById('main-menu');
 if(toggle&&menu){
  const set=open=>{menu.hidden=!open;toggle.setAttribute('aria-expanded',String(open));toggle.querySelector('.menu-label').textContent=open?'Закрыть меню':'Открыть меню';};
  toggle.addEventListener('click',()=>set(menu.hidden));
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!menu.hidden){set(false);toggle.focus();}});
  menu.querySelectorAll('a').forEach(a=>{if(new URL(a.href).pathname===location.pathname)a.setAttribute('aria-current','page');});
 }
 const start=(selector,fn)=>{const el=document.querySelector(selector);if(el)Promise.resolve().then(()=>fn(el)).catch(e=>error(el,e));};
 async function authState(){const s=await session();document.querySelectorAll('.editor-auth').forEach(box=>{box.querySelector('.editor-login').hidden=s.admin;box.querySelector('.editor-logout').hidden=!s.admin;box.querySelector('.editor-state').textContent=s.admin?'Вы вошли как редактор. Сохранение доступно.':'Сохранение общих данных доступно после входа редактора.';});}
 document.querySelectorAll('.editor-login').forEach(form=>{bind(form,async d=>{await api('login',{},d);sessionPromise=null;form.reset();await authState();});});
 document.querySelectorAll('.editor-logout').forEach(b=>b.addEventListener('click',async()=>{try{await api('logout',{},{});sessionPromise=null;await authState();}catch(e){error(b.parentElement.querySelector('.editor-state'),e);}}));
 if(document.querySelector('.editor-auth'))authState().catch(e=>document.querySelectorAll('.editor-state').forEach(el=>error(el,e)));
 start('#db-info',async el=>{const d=await api('db_info');el.innerHTML=`<p>СУБД: <strong>${esc(d.engine)}</strong>. Кодировка: ${esc(d.encoding)}. Игр в каталоге: <strong>${d.games}</strong>.</p><div class="table-wrap"><table class="table"><caption>Поля таблицы games</caption><thead><tr><th>Поле</th><th>Назначение</th></tr></thead><tbody>${Object.entries(d.fields).map(([k,v])=>`<tr><td><code>${esc(k)}</code></td><td>${esc(v)}</td></tr>`).join('')}</tbody></table></div><h3>Связанные таблицы</h3><ul>${Object.entries(d.related).map(([k,v])=>`<li><strong>${esc(k)}</strong>: ${esc(v)}</li>`).join('')}</ul>`;});
 start('#db-search-status',async status=>{
  const form=document.querySelector('#db-search'),g=form.elements.genre,p=form.elements.platform,mode=form.elements.mode;let generation=0;
  const options=async()=>{const token=++generation;form.querySelector('button').disabled=true;try{const data=await api('game_options',{genre:g.value});if(token!==generation)return;const chosen=p.value;p.innerHTML='<option value="">Все платформы выбранного жанра</option>'+data.platforms.map(v=>`<option>${esc(v)}</option>`).join('');if(data.platforms.includes(chosen))p.value=chosen;
   if(g.options.length===1){g.innerHTML='<option value="">Все жанры</option>'+data.genres.map(v=>`<option>${esc(v)}</option>`).join('');}
  }finally{if(token===generation)form.querySelector('button').disabled=false;}};
  const render=async d=>{const data=await api('games',{q:d.q||'',genre:d.genre||'',platform:d.mode==='two'?d.platform||'':'',sort:d.sort||'title'});status.textContent=`Найдено игр: ${data.length}. Режим: ${d.mode==='two'?'жанр + платформа':'жанр'}.`;document.querySelector('#db-game-rows').innerHTML=data.map(r=>`<tr><td>${esc(r.title)}</td><td>${esc(r.genre)}</td><td>${esc(r.platforms)}</td><td>${r.year}</td><td>${Number(r.rating).toFixed(1)}</td><td>${esc(r.tag)}</td></tr>`).join('')||'<tr><td colspan="6">По заданным условиям игр нет.</td></tr>';};
  g.addEventListener('change',()=>options().catch(e=>error(status,e)));mode.addEventListener('change',()=>{document.querySelector('#db-platform-label').hidden=mode.value!=='two';if(mode.value==='one')p.value='';});
  form.addEventListener('reset',()=>setTimeout(async()=>{document.querySelector('#db-platform-label').hidden=true;try{await options();await render(Object.fromEntries(new FormData(form)));}catch(e){error(status,e);}},0));
  form.elements.q.value=new URLSearchParams(location.search).get('q')||'';bind(form,render);await options();await render(Object.fromEntries(new FormData(form)));
 });
 start('#game-edit-result',async status=>{
  const select=document.querySelector('#edit-game'),form=document.querySelector('#game-edit-form');let records=[];
  async function load(chosen=''){records=await api('games');select.innerHTML='<option value="">Новая игра</option>'+records.map(r=>`<option value="${esc(r.id)}">${esc(r.title)}</option>`).join('');select.value=chosen;fill();}
  function fill(){const row=records.find(r=>r.id===select.value);for(const key of ['id','version','title','genre','platforms','year','rating','tag'])form.elements[key].value=row?.[key]??'';form.querySelector('button').textContent=row?'Сохранить изменения в БД':'Добавить игру в БД';}
  select.addEventListener('change',fill);form.addEventListener('reset',()=>setTimeout(()=>{select.value='';fill();status.textContent='Новая запись. Заполните все поля.';},0));
  bind(form,async d=>{const result=await api('game_save',{}, {...d,operation:select.value?'update':'create'});await load(result.id);status.textContent='Запись сохранена на сервере. Вы можете открыть поиск или статистику для проверки.';});await load();
 });
 start('#rating-results-status',async status=>{
  const form=document.querySelector('#rating-form'),select=form.elements.game_id;
  const render=async()=>{const rows=await api('ratings'),old=select.value;select.innerHTML='<option value="">Выберите игру</option>'+rows.map(r=>`<option value="${esc(r.id)}">${esc(r.title)}</option>`).join('');select.value=old;document.querySelector('#rating-results').innerHTML=rows.map(r=>`<tr><td>${esc(r.title)}</td><td>${r.votes}</td><td>${r.average===null?'Пока нет оценок':esc(r.average)}</td></tr>`).join('');status.textContent=`Результаты загружены из БД. Игр: ${rows.length}. Голосов всего: ${rows.reduce((sum,r)=>sum+Number(r.votes),0)}.`;};
  bind(form,async d=>{await api('rate',{}, {game_id:d.game_id,score:Number(d.score)});await render();document.querySelector('#rating-save-status').textContent='Оценка записана в MySQL. Результаты ниже обновлены.';});document.querySelector('#rating-refresh').addEventListener('click',()=>render().catch(e=>error(status,e)));await render();
 });
 start('#glossary-form',async form=>{const xml=await fetch(new URL('glossary.xml',ROOT)).then(r=>r.text());const doc=new DOMParser().parseFromString(xml,'application/xml');const cats=[...new Set([...doc.querySelectorAll('category')].map(e=>e.textContent))].sort();form.elements.category.innerHTML='<option value="">Все категории</option>'+cats.map(c=>`<option>${esc(c)}</option>`).join('');bind(form,async d=>{document.querySelector('#xml-view').src=new URL('api/xml.php?'+new URLSearchParams(d),ROOT);});});
 start('#feed-status',async status=>{
  const select=document.querySelector('#feed-source'),items=document.querySelector('#feed-items'),sources=document.querySelector('#rss-sources');let data;
  function render(){const filtered=data.items.filter(n=>!select.value||n.source===select.value);items.innerHTML=filtered.map(n=>`<article class="feed-item" id="${n.id.startsWith('local-')?'news-'+n.id.slice(6):esc(n.id)}"><span class="tag">${esc(n.source)}</span> <time>${esc(n.date)}</time><h2><a href="${esc(n.url)}"${n.source==='GameVerse'?'':' target="_blank" rel="noopener noreferrer"'}>${esc(n.title)}</a></h2>${n.text?`<p>${esc(n.text)}</p>`:''}</article>`).join('')||'<p>По выбранному источнику публикаций нет.</p>';}
  async function refresh(){status.textContent='Загружаем источники…';data=await api('feed');const chosen=select.value;select.innerHTML='<option value="">Все источники</option>'+[...new Set(data.items.map(n=>n.source))].map(n=>`<option>${esc(n)}</option>`).join('');select.value=[...select.options].some(o=>o.value===chosen)?chosen:'';status.textContent=data.sources.length?data.sources.map(s=>s.title+': '+(s.ok?(s.stale?'сохранённая копия, источник недоступен':'загружен'):'источник недоступен')).join('; '):'Внешние источники выключены. Показаны публикации GameVerse.';render();
   const list=await api('rss_sources');sources.innerHTML=list.map(s=>`<article class="list-item"><h3>${esc(s.title)}</h3><p><a href="${esc(s.url)}" target="_blank" rel="noopener noreferrer">${esc(s.url)}</a></p><p>${Number(s.active)?'Включён':'Выключен'}</p><button type="button" class="btn" data-source="${s.id}" data-active="${Number(s.active)?0:1}">${Number(s.active)?'Выключить':'Включить'} источник</button></article>`).join('')||'<p>Источники ещё не добавлены.</p>';
   sources.querySelectorAll('button').forEach(b=>b.addEventListener('click',async()=>{b.disabled=true;try{await api('rss_source_toggle',{}, {id:Number(b.dataset.source),active:Number(b.dataset.active)});await refresh();}catch(e){error(status,e);}finally{b.disabled=false;}}));
   if(location.hash.startsWith('#news-'))document.getElementById(location.hash.slice(1))?.scrollIntoView();
  }
  select.addEventListener('change',render);document.querySelector('#feed-refresh').addEventListener('click',()=>refresh().catch(e=>error(status,e)));
  bind(document.querySelector('#rss-source-form'),async d=>{await api('rss_source_save',{},d);document.querySelector('#rss-source-form').reset();await refresh();});
  bind(document.querySelector('#rss-news-form'),async d=>{await api('news_save',{},d);document.querySelector('#rss-news-form').reset();await refresh();});await refresh();
 });
})();
