<?php
    namespace Database\Tables;

	use Database\Database;

	class Giacenze extends Database {
        
        public $tableName = 'giacenze_test';

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

				return true;
            } catch (PDOException $e) {
                die($e->getMessage());
            }
        }
        
        public function eliminaTabella() {
        	try {
                $sql = "DROP TABLE IF EXISTS $this->tableName;";
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

        public function caricaSituazioni($data, $situazioni) {
             try {
                $tempTableName = "giacenzeTemp";
                
                $anno = $data->format('Y');
                $giorno = $data->format('Y-m-d');
                
                $this->pdo->beginTransaction();
                
                //elimino la tabella temporanea se c'
				$sql = "drop table if exists $tempTableName";
				$stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                
                $sql = "create table $tempTableName like $this->tableName";
				$stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                
                $records = [];
               
                foreach ($situazioni as $codice => $dettaglio) {
                    foreach ($dettaglio as $negozio => $quantita) {
                        $records[] = '('.implode(',',[$anno, "'".$giorno."'", "'".$codice."'", "'".$negozio."'", $quantita]).')';
                    }
                }

                while (count($records)) {
                    $toInsert = array_splice($records, 0, 1000);
                    $sql = "insert into `$tempTableName` (anno, data, codice, negozio, giacenza) values ".implode(',',$toInsert);
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute();
                }
                
                $sql = "insert into giacenze_test select g.*
                        from giacenzeTemp  as g left join giacenze_test
                        as t on g.`anno`=t.`anno` and g.`codice`=t.`codice` and g.`giacenza`=t.`giacenza` and g.`negozio`=t.`negozio`
                        where t.`anno` is null";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                $this->pdo->commit();   
				
                return 0;
            } catch (PDOException $e) {
             	$this->pdo->rollBack();
                return 1;
            }
        }
        public function __destruct() {
			parent::__destruct();
        }
    }
?>
