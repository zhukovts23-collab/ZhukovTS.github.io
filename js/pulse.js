'use strict';
(() => {
 const form=document.querySelector('#pulse-form'); if(!form)return;
 const status=document.querySelector('#pulse-status'), result=document.querySelector('#pulse-result'), compare=document.querySelector('#pulse-compare');
 const fmt=n=>new Intl.NumberFormat('ru-RU').format(n);
 const when=s=>s?new Date(s).toLocaleString('ru-RU'):'нет замера';
 const escape=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 let serial=0;
 function draw(g,index){
  const h=g.history, section=document.createElement('article'); section.className='pulse-card pulse-color-'+index;
  section.innerHTML=`<p class="eyebrow">${index?'ВТОРАЯ ИГРА':'ПЕРВАЯ ИГРА'}</p><h2>${escape(g.name)}</h2><p class="pulse-number">${g.players===null?'Нет данных':fmt(g.players)}</p><p>активных игроков по последнему ответу Steam</p><p class="small">${g.stale?'Сохранённый замер · Steam пока не обновился':'Замер получен'}: ${escape(when(g.sampled_at))}</p><div class="pulse-chart"></div><label class="pulse-rewind">Перемотка наблюдений<input type="range" min="0" max="${Math.max(0,h.length-1)}" value="${Math.max(0,h.length-1)}" step="1" ${h.length<2?'disabled':''}></label><output class="pulse-observation"></output><p class="pulse-change"></p><a href="${escape(g.source_url)}" target="_blank" rel="noopener">Открыть ответ Steam API ↗</a><details><summary>Таблица наблюдений (${h.length})</summary><div class="table-wrap"><table class="table"><thead><tr><th>Время</th><th>Игроки</th></tr></thead><tbody>${h.map(x=>`<tr><td>${escape(when(x.at))}</td><td>${fmt(x.players)}</td></tr>`).join('')}</tbody></table></div></details>`;
  const out=section.querySelector('output'), plot=section.querySelector('.pulse-chart'), slider=section.querySelector('input');
  function render(k){
   if(!h.length){plot.textContent='Steam ещё не вернул данные для этой игры.';out.textContent='История появится после первого успешного замера.';return;}
   const p=h[k];out.textContent=`${when(p.at)} · ${fmt(p.players)} игроков`;
   if(h.length<2){plot.innerHTML='<div class="pulse-empty">Первый реальный замер сохранён.<br>График и перемотка появятся после следующего замера.</div>';return;}
   const values=h.map(x=>x.players), lo=Math.min(...values), hi=Math.max(...values), span=Math.max(1,hi-lo), times=h.map(x=>new Date(x.at).getTime()), duration=Math.max(1,times.at(-1)-times[0]);
   const xy=(x,i)=>[36+(times[i]-times[0])/duration*520,154-(x-lo)/span*112];
   const points=values.map((x,i)=>xy(x,i).join(',')).join(' '), chosen=xy(p.players,k);
   plot.innerHTML=`<svg viewBox="0 0 590 190" role="img" aria-label="Наблюдения ${escape(g.name)}: минимум ${lo}, максимум ${hi}. Точные значения в таблице."><path d="M36 30V155H560" fill="none" stroke="currentColor" opacity=".25"/><polyline points="${points}" fill="none" stroke="currentColor" stroke-width="3"/><circle cx="${chosen[0]}" cy="${chosen[1]}" r="6" fill="currentColor"/><text x="36" y="20">max ${fmt(hi)}</text><text x="36" y="180">min ${fmt(lo)} · ${h.length} замеров</text></svg>`;
  }
  slider.oninput=()=>render(Number(slider.value));render(Math.max(0,h.length-1));
  if(h.length>1){const last=h.at(-1),before=h.at(-2),delta=last.players-before.players;section.querySelector('.pulse-change').textContent=`К предыдущему замеру (${when(before.at)}): ${delta>0?'+':''}${fmt(delta)}${before.players>0?' ('+(delta/before.players*100).toFixed(2)+'%)':''}.`;}
  return section;
 }
 async function load(){
  const id=++serial, btn=form.querySelector('button');btn.disabled=true;status.textContent='Получаем наблюдения Steam…';
  const params=new URLSearchParams(new FormData(form)), controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),25000);
  try{
   const r=await fetch('../api/pulse.php?'+params,{signal:controller.signal});const d=await r.json();if(!r.ok)throw Error(d.error||'Ошибка сервиса');if(id!==serial)return;
   result.replaceChildren(...d.games.map(draw));compare.replaceChildren();
   if(d.games.length===2&&d.games.every(g=>g.players!==null)){
    const [a,b]=d.games,total=a.players+b.players,share=total?100*a.players/total:50;
    const box=document.createElement('div');box.className='pulse-duel';
    box.innerHTML=`<div class="pulse-ring"><strong>${total?Math.round(share)+'%':'—'}</strong><span>доля ${escape(a.name)}</span></div><div><p class="eyebrow">ДУЭЛЬ АУДИТОРИЙ</p><h2>${escape(a.players===b.players?'Числа совпали':(a.players>b.players?a.name:b.name)+' впереди')}</h2><p>Разница: <strong>${fmt(Math.abs(a.players-b.players))}</strong> игроков.</p><p>${total?`${escape(a.name)} — ${share.toFixed(1)}%; ${escape(b.name)} — ${(100-share).toFixed(1)}%.`:'Оба значения равны нулю; доли не определены.'}</p><p class="small">Доли рассчитаны только для выбранной пары. Замеры могли быть получены в разное время; это не доли всего рынка.</p></div>`;
    box.querySelector('.pulse-ring').style.background=`conic-gradient(#64d9ff 0 ${share}%,#f59ac5 ${share}% 100%)`;compare.append(box);
   }
   status.textContent='Показаны последние доступные ответы Steam. Новые запросы к источнику — не чаще раза в 5 минут на игру.';
   const u=new URL(location.href);u.search=params.toString();history.replaceState(null,'',u);
  }catch(e){if(id===serial){status.textContent=e.name==='AbortError'?'Источник не ответил вовремя. Повторите позже.':e.message;result.replaceChildren();compare.replaceChildren();}}
  finally{clearTimeout(timeout);if(id===serial)btn.disabled=false;}
 }
 const supplied=new URL(location.href).searchParams;for(const field of ['left','right']){const v=supplied.get(field);if(v&&[...form.elements[field].options].some(x=>x.value===v))form.elements[field].value=v;}
 form.onsubmit=e=>{e.preventDefault();load();};load();
})();
