drop table if exists db_sm.giacenze_correnti;

create table db_sm.giacenze_correnti as
select g.codice, g.negozio, ifnull(g.giacenza, 0) `giacenza`
from db_sm.giacenze as g
         join (select g.`codice`, g.`negozio`, max(g.`data`) `data`
               from db_sm.giacenze as g
               where g.anno = 2022
               group by 1, 2) as d on g.codice = d.codice and g.negozio = d.negozio and g.data = d.data
order by g.codice, lpad(SUBSTR(g.negozio, 3), 2, '0');
alter table db_sm.giacenze_correnti
    add primary key (codice, negozio);

drop table if exists `db_sm`.ordini_righe_arrivi;

create table `db_sm`.`ordini_righe_arrivi`
(
    `codice_fornitore` varchar(10) not null default '',
    `data_ddt`         date        not null default '0000-00-00',
    `numero_ddt`       varchar(20) not null default '',
    `negozio`          varchar(4)  not null default '',
    `codice_articolo`  varchar(7)  not null default '',
    `quantita`         float       not null,
    `costo`            float       not null default '0',
    `keycod`           varchar(36) not null default '',
    primary key (`negozio`, `keycod`)
) engine = innodb
  default charset = latin1;

/*comparazione righe_arrivi/righe_ordini*/
insert into `db_sm`.ordini_righe_arrivi
select a.`codice_fornitore`,
       a.`data_ddt`,
       a.`numero_ddt`,
       a.`negozio`,
       ar.`codice_articolo`,
       sum(ar.`quantita`) `quantita`,
       ar.`costo`,
       ar.`keycod`
from `db_sm`.arrivi as a
         join `db_sm`.righe_arrivi as ar on a.`id` = ar.`id_arrivi`
where a.`bozza` = 0
group by ar.`keycod`, a.`negozio`;

drop table if exists `db_sm`.situazioni;

create table `db_sm`.`situazioni`
(
    `codice_articolo` varchar(7) not null default '',
    `negozio`         varchar(4) not null default '',
    `ordinato`        float      not null default 0,
    `consegnato`      float      not null default 0,
    `in_ordine`       float      not null default 0,
    `acquistati`      float      not null default 0,
    `giacenza`        float      not null default 0,
    `venduto`         float      not null default 0,
    `venduto_7`       float      not null default 0,
    `venduto_15`      float      not null default 0,
    `venduto_30`      float      not null default 0,
    `venduto_90`      float      not null default 0,
    primary key (`codice_articolo`, `negozio`)
) engine = innodb
  default charset = latin1;

/*situazioni (quantita in ordine)*/
insert into `db_sm`.`situazioni`
select ro.`codice_articolo`,
       roq.`sede`,
       sum(roq.`quantita` + roq.`sconto_merce`)                                  `ordinato`,
       sum(ifnull(roa.`quantita`, 0))                                            `consegnato`,
       sum(roq.`quantita` + roq.`sconto_merce`) - sum(ifnull(roa.`quantita`, 0)) `in ordine`,
       0,
       0,
       0,
       0,
       0,
       0,
       0
from `db_sm`.ordini_righe_quantita as roq
         join `db_sm`.ordini_righe as ro on roq.`id_righe` = ro.`id`
         join `db_sm`.ordini as o on ro.`id_ordini` = o.`id`
         left join `db_sm`.ordini_righe_arrivi as roa on roa.`keycod` = ro.`keycod` and roq.`sede` = roa.`negozio`
where o.`data_ordine` >= MAKEDATE(EXTRACT(YEAR FROM CURDATE()), 1)
  and o.`sospeso` = 0
  and o.`annullato` = 0
  and ro.`sospeso` = 0
group by ro.`codice_articolo`, roq.`sede`
having `in ordine` <> 0
order by ro.`codice_articolo`, lpad(substr(roq.`sede`, 3), 2, '0');

/*creazione di tutti i record che abbiano almeno un tipo di movimento diverso da 0*/
insert ignore into db_sm.`situazioni`
select g.`codice`,
       g.`negozio`,
       0,
       0,
       0,
       0,
       0,
       0,
       0,
       0,
       0,
       0
from db_sm.`giacenze_correnti` as g
where g.`negozio` <> 'SMMD';

insert ignore into db_sm.`situazioni`
select distinct r.`codice`,
                r.`negozio`,
                0,
                0,
                0,
                0,
                0,
                0,
                0,
                0,
                0,
                0
from db_sm.`righe_vendita` as r
where r.`data` >= MAKEDATE(EXTRACT(YEAR FROM CURDATE()), 1);

insert ignore into db_sm.`situazioni`
select distinct r.`codice_articolo`,
                a.`negozio`,
                0,
                0,
                0,
                0,
                0,
                0,
                0,
                0,
                0,
                0
from db_sm.`arrivi` as a
         left join db_sm.`righe_arrivi` as r on a.`id` = r.`id_arrivi`
where a.`data_arrivo` >= MAKEDATE(EXTRACT(YEAR FROM CURDATE()), 1);

/*aggiorno le situazioni con le giacenze*/
update db_sm.`situazioni` as s join db_sm.`giacenze_correnti` as g on s.`codice_articolo` = g.`codice` and s.`negozio` = g.`negozio`
set s.`giacenza`=g.`giacenza`
where g.`negozio` <> 'SMMD';

/* calcolo degli acquistati*/
update db_sm.`situazioni` as s join (select r.`codice_articolo`         `codice`,
                                            a.`negozio`                 `negozio`,
                                            round(sum(r.`quantita`), 0) `acquistati`
                                     from db_sm.`arrivi` as a
                                              join db_sm.`righe_arrivi` as r on a.`id` = r.`id_arrivi`
                                     where a.`data_arrivo` >= MAKEDATE(EXTRACT(YEAR FROM CURDATE()), 1)
                                     group by 1, 2) as q on s.`codice_articolo` = q.`codice` and s.`negozio` = q.`negozio`
set s.`acquistati`=q.`acquistati`;

/*calcolo il venduto annuo, 90, 30, 15, 7*/
drop table if exists `db_sm`.temp_venduto;

create table `db_sm`.temp_venduto
select r.`codice`,
       r.`negozio`,
       round(sum(case when r.`data` >= DATE_SUB(current_date(), INTERVAL 1 WEEK) then r.`quantita` else 0 end),
             0) venduto_7,
       round(sum(case when r.`data` >= DATE_SUB(current_date(), INTERVAL 2 WEEK) then r.`quantita` else 0 end),
             0) venduto_15,
       round(sum(case when r.`data` >= DATE_SUB(current_date(), INTERVAL 1 MONTH) then r.`quantita` else 0 end),
             0) venduto_30,
       round(sum(case when r.`data` >= DATE_SUB(current_date(), INTERVAL 3 MONTH) then r.`quantita` else 0 end),
             0) venduto_90,
       round(sum(case when r.`data` >= makedate(extract(year from curdate()), 1) then r.`quantita` else 0 end),
             0) venduto_anno
from db_sm.`righe_vendita` as r
where r.`data` >= (select case
                              when DATE_SUB(current_date(), INTERVAL 3 MONTH) <=
                                   makedate(extract(year from curdate()), 1)
                                  then DATE_SUB(current_date(), INTERVAL 3 MONTH)
                              else makedate(extract(year from curdate()), 1) end)
group by 1, 2
having venduto_anno <> 0
    or venduto_90 <> 0
    or venduto_30 <> 0
    or venduto_15 <> 0
    or venduto_7 <> 0;
ALTER TABLE `db_sm`.temp_venduto
    ADD PRIMARY KEY (`codice`, `negozio`);

/*aggiorno le situazioni con il venduto*/
update db_sm.`situazioni` as s join db_sm.`temp_venduto` as v on s.`codice_articolo` = v.`codice` and s.`negozio` = v.`negozio`
set s.`venduto`=v.`venduto_anno`,
    s.`venduto_7`=v.`venduto_7`,
    s.`venduto_15`=v.`venduto_15`,
    s.`venduto_30`=v.`venduto_30`,
    s.`venduto_90`=v.`venduto_90`;

drop table if exists `db_sm`.temp_venduto;
