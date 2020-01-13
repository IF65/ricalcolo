<?php
    namespace Database\Tables;

	use Database\Database;

	class Giacenze extends Database {
        
        public $tableName = 'giacenze';
        public $tableRicalcolo = 'giacenzeRicalcolo';

        public function __construct($sqlDetails) {
        	try {
				parent::__construct($sqlDetails);

                self::creaTabella();

            } catch (PDOException $e) {
                die($e->getMessage());
            }
        }

		public function creaTabella() {
        	try {
                $sql = "CREATE TABLE IF NOT EXISTS $this->tableName (
                        `anno` smallint(6) NOT NULL,
                        `data` date NOT NULL,
                        `codice` varchar(7) NOT NULL DEFAULT '',
                        `negozio` varchar(4) NOT NULL DEFAULT '',
                        `giacenza` float NOT NULL DEFAULT '0',
                        PRIMARY KEY (`data`,`codice`,`negozio`),
                        KEY `anno` (`anno`,`codice`,`negozio`,`giacenza`)
                      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
                $this->pdo->exec($sql);
                
                $sql = "CREATE TABLE IF NOT EXISTS `giacenze_correnti` (
                        `codice` varchar(7) NOT NULL DEFAULT '',
                        `negozio` varchar(4) NOT NULL DEFAULT '',
                        `giacenza` float NOT NULL DEFAULT '0',
                        PRIMARY KEY (`codice`,`negozio`)
                      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
                $this->pdo->exec($sql);

				return true;
            } catch (PDOException $e) {
                die($e->getMessage());
            }
        }
        
        public function eliminaTabella() {
        	try {
                $sql = "DROP TABLE IF EXISTS $this->tableName;";
                $this->pdo->exec($sql);
                
                $sql = "DROP TABLE IF EXISTS `giacenze_correnti`;";
                $this->pdo->exec($sql);

				return true;
            } catch (PDOException $e) {
                die($e->getMessage());
            }
        }
        
        public function salvaRecord($record) {
             try {
                $this->pdo->beginTransaction();

				$sql = "insert into $this->tableName
							( anno, data, codice, negozio, giacenza )
						values
							( :anno, :data, :codice, :negozio, :giacenza )
                        on duplicate key update
                            giacenza = :giacenza";
				$stmt = $this->pdo->prepare($sql);
                $stmt->execute(array(	":anno" => $record['anno'],
                                        ":data" => $record['data'],
               							":codice" => $record['codice'],
                						":negozio" => $record['negozio'],
                                        ":giacenza" => $record['giacenza']
									)
							   );

                $stmt->closeCursor();

                $this->pdo->commit();

				return 0;
            } catch (PDOException $e) {
             	$this->pdo->rollBack();
                return 1;
            }
        }

        public function cancellaRecord($record) {
            try {
                $this->pdo->beginTransaction();

                $sql = "delete from $this->tableName where data = :data and codice = :codice and negozio = :negozio";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(array(	":data" => $record['data'],
               							":codice" => $record['codice'],
                						":negozio" => $record['negozio']
									)
							   );
                $stmt->closeCursor();

                $this->pdo->commit();

                return true;
            } catch (PDOException $e) {
                $this->pdo->rollBack();

                die($e->getMessage());
            }
        }
        
        public function creaTabellaGiacenzePerRicalcolo() {
            try {
                //elimino la tabella di ricalcolo se c'?
				$sql = "drop table if exists $this->tableRicalcolo";
				$stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                
                $sql = "create table $this->tableRicalcolo like $this->tableName";
				$stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                
				return true;
            } catch (PDOException $e) {
                die($e->getMessage());
            }
        }
        
        public function eliminaTabelleTemporaneeeRicalcolo() {
            try {
                $sql = "DROP TABLE IF EXISTS $this->tableName;";
                $this->pdo->exec($sql);
                
                $sql = "RENAME TABLE $this->tableRicalcolo TO $this->tableName;";
                $this->pdo->exec($sql);
                
                return true;
            } catch (PDOException $e) {
                die($e->getMessage());
            }
        }

        public function caricaSituazioni($data, $situazioni) {
             try {
                $tempTableName = "giacenzeTemp";
                
                $anno = $data->format('Y');
                $giorno = $data->format('Y-m-d');
                
                //elimino la tabella temporanea se c'?
				$sql = "drop table if exists $tempTableName";
				$stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                
                //e ora la ricreo
                $sql = "create table $tempTableName like $this->tableName";
				$stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                
                //creo un array con la struttura della tabella per il caricamento
                $records = [];
                foreach ($situazioni as $codice => $dettaglio) {
                    foreach ($dettaglio as $negozio => $quantita) {
                        $records[] = '('.implode(',',[$anno, "'".$giorno."'", "'".$codice."'", "'".$negozio."'", $quantita]).')';
                    }
                }
                
                //carico l'array a blocchi di 1000
                while (count($records)) {
                    $toInsert = array_splice($records, 0, 1000);
                    $sql = "insert into `$tempTableName` (anno, data, codice, negozio, giacenza) values ".implode(',',$toInsert);
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute();
                }
                
                //ora faccio la join con la tabella di ricalcolo per inserire solo i record modificati
                $sql = "insert into $this->tableRicalcolo select g.*
                        from `$tempTableName` as g
                        left join (select * from (select g.anno, g.data, g.codice, g.negozio, g.giacenza from $this->tableRicalcolo as g join (select g.`codice`, g.`negozio`, max(g.`data`) `data` 
                        from $this->tableRicalcolo as g where g.negozio not in ('SMBB','SMMD') group by 1,2) as d on g.codice=d.codice and g.negozio=d.negozio and g.data=d.data
                        order by g.codice, lpad(SUBSTR(g.negozio,3),2,'0')) as g) as t on g.`anno`=t.`anno` and g.`codice`=t.`codice` and g.`giacenza`=t.`giacenza` and g.`negozio`=t.`negozio`
                        where t.`anno` is null";
                        
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(); 
				
            } catch (PDOException $e) {
                //se c'? un errore blocco tutto
             	die($e->getMessage());
            }
        }
        
        public function creaGiacenzeCorrenti() {
            //elimino la tabella temporanea se c'?
            $sql = "drop table if exists giacenze_correnti";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            $sql = "CREATE TABLE `giacenze_correnti` (
                      `codice` varchar(7) NOT NULL DEFAULT '',
                      `negozio` varchar(4) NOT NULL DEFAULT '',
                      `giacenza` float NOT NULL DEFAULT '0',
                      PRIMARY KEY (`codice`,`negozio`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            $sql =" insert into giacenze_correnti
                    select g.codice, g.negozio, g.giacenza from $this->tableName as g join (select g.`codice`, g.`negozio`, max(g.`data`) `data` 
                    from $this->tableName as g where g.anno = 2020 and g.data < CURRENT_DATE() and g.negozio not in ('SMBB','SMMD') group by 1,2) as d on g.codice=d.codice and g.negozio=d.negozio and g.data=d.data
                    order by g.codice, lpad(SUBSTR(g.negozio,3),2,'0');";
            
            $this->pdo->exec($sql);
        }
        
        public function giacenzeAllaDataGreCopre($dataCalcolo) {
            $sql = "select
                        g.negozio,
                        ifnull((select e.`ean` from `db_sm`.ean as e where e.`codice`=g.`codice` order by 1 desc limit 1),'2999999999999') as `ean`, 
                        g.codice, mr.marca  `linea`, m.modello, g.giacenza 
                    from $this->tableName as g join 
                        (	select g.`codice`, g.`negozio`, max(g.`data`) `data` 
                            from $this->tableName as g where g.anno = 2020 and g.data <= '$dataCalcolo' 
                            group by 1,2
                        ) as d on g.codice=d.codice and g.negozio=d.negozio and g.data=d.data join magazzino as m on g.codice=m.codice join marche as mr on m.linea=mr.linea
                    where m.`giacenza_bloccata` = 0 and m.`invio_gre`=1 and mr.`invio_gre` = 1 and m.linea not like 'SUPERMEDIA%' and g.giacenza <> 0
                    order by lpad(SUBSTR(g.negozio,3),2,'0'), g.codice";
                    
            try {
                $stmt = $this->pdo->prepare($sql);
                if ($stmt->execute()) {
                    $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    
                    $situazioni = [];
                    foreach ($results as $situazione) {
                        if (preg_match("/^\d{7}$/", $situazione['codice'])) {
                            $record = [
                                       'giacenza' => $situazione['giacenza']*1,
                                       'ean' => $situazione['ean'],
                                       'linea' => $situazione['linea'],
                                       'modello' => $situazione['modello']
                                       ];
                            $situazioni[$situazione['negozio']][$situazione['codice']] = $record;
                        }
                    }
                    unset($results);
                    
                    return $situazioni;
                }
                return null;
            } catch (PDOException $e) {
                print $e->getMessage();
                return null;
            }
        }
        
        public function giacenzeAllaData($dataCalcolo) {
            $sql = "select g.codice, g.negozio, g.giacenza from $this->tableName as g join (select g.`codice`, g.`negozio`, max(g.`data`) `data` 
                    from $this->tableName as g where g.anno = 2020 and g.data <= '$dataCalcolo' group by 1,2) as d on g.codice=d.codice and g.negozio=d.negozio and g.data=d.data
                    order by lpad(SUBSTR(g.negozio,3),2,'0'), g.codice;";
                    
            try {
                $stmt = $this->pdo->prepare($sql);
                if ($stmt->execute()) {
                    $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    
                    $situazioni = [];
                    foreach ($results as $situazione) {
                        if (preg_match("/^\d{7}$/", $situazione['codice'])) {
                            $situazioni[$situazione['codice']][$situazione['negozio']] = $situazione['giacenza']*1;
                        }
                    }
                    unset($results);
                    
                    return $situazioni;
                }
                return null;
            } catch (PDOException $e) {
                print $e->getMessage();
                return null;
            }
        }
        
        public function eliminaTabellaTemporanea() {
        	try {
                $sql = "DROP TABLE IF EXISTS $this->tableName;";
                $this->pdo->exec($sql);
                
                $sql = "DROP TABLE IF EXISTS `giacenze_correnti`;";
                $this->pdo->exec($sql);

				return true;
            } catch (PDOException $e) {
                die($e->getMessage());
            }
        }
        
        public function __destruct() {
			parent::__destruct();
        }
    }
?>
