<?php
declare(strict_types=1);
function catalog_options(string $genre=''): array {
 $genres=query('SELECT DISTINCT genre FROM games ORDER BY genre')->fetchAll(PDO::FETCH_COLUMN);
 $platforms=[];
 foreach(query('SELECT DISTINCT platforms FROM games WHERE (?=\'\' OR genre=?)',[$genre,$genre])->fetchAll(PDO::FETCH_COLUMN) as $list)
  foreach(explode(' / ',$list) as $p) if(trim($p)!=='') $platforms[trim($p)]=true;
 $values=array_keys($platforms);sort($values,SORT_STRING);
 return ['genres'=>$genres,'platforms'=>$values];
}
function catalog_handle(string $action): void {
 if($action==='game_options')json_out(catalog_options(mb_substr((string)($_GET['genre']??''),0,60)));
 if($action==='db_info')json_out(['engine'=>'MySQL / InnoDB','encoding'=>'UTF-8 (utf8mb4)','games'=>(int)query('SELECT COUNT(*) FROM games')->fetchColumn(),'fields'=>['id'=>'Уникальный идентификатор','title'=>'Название','genre'=>'Жанр','platforms'=>'Платформы, разделённые знаком /','year'=>'Год выпуска','rating'=>'Редакционная оценка, 1–5','tag'=>'Краткая характеристика'],'related'=>['ratings'=>'Оценки посетителей связаны с games по game_id','news'=>'Редакционные новости для RSS','rss_feeds'=>'Сохранённые внешние RSS-источники']]);
 if($action!=='game_save')return;
 admin();rate_limit('catalog_edit',30,300);$d=input();
 $row=[];foreach(['title'=>160,'genre'=>60,'platforms'=>160,'tag'=>80] as $k=>$n)$row[$k]=field($d,$k,$n);
 $year=filter_var($d['year']??null,FILTER_VALIDATE_INT);$rating=filter_var($d['rating']??null,FILTER_VALIDATE_FLOAT);
 if($year===false||$year<1950||$year>2100||$rating===false||$rating<1||$rating>5||abs($rating*10-round($rating*10))>0.00001)fail('Год: 1950–2100; оценка: 1–5 с одним десятичным знаком.');
 $row['year']=$year;$row['rating']=$rating;$operation=$d['operation']??'';
 if(!in_array($operation,['create','update'],true))fail('Выберите создание или редактирование.');
 $id=$operation==='create'?'game-'.bin2hex(random_bytes(8)):field($d,'id',80);
 db()->beginTransaction();
 try {
  if($operation==='update'){
   $old=query('SELECT * FROM games WHERE id=? FOR UPDATE',[$id])->fetch();if(!$old){db()->rollBack();fail('Запись не найдена.',404);}
   if(!hash_equals(hash('sha256',json_encode($old)),(string)($d['version']??''))){db()->rollBack();fail('Запись изменилась. Выберите её заново перед сохранением.',409);}
   query('UPDATE games SET title=?,genre=?,platforms=?,year=?,rating=?,tag=? WHERE id=?',[$row['title'],$row['genre'],$row['platforms'],$year,$rating,$row['tag'],$id]);
  }else query('INSERT INTO games(id,title,genre,platforms,year,rating,tag) VALUES (?,?,?,?,?,?,?)',[$id,$row['title'],$row['genre'],$row['platforms'],$year,$rating,$row['tag']]);
  db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
 json_out(['ok'=>true,'id'=>$id],$operation==='create'?201:200);
}
