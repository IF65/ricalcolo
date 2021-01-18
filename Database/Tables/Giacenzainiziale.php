<?php
    namespace Database\Tables;

	use Database\Database;

	class Giacenzainiziale extends Database {
        
        public $tableName = 'giacenze_iniziali';

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
                            `codice` varchar(7) NOT NULL DEFAULT '',
                            `negozio` varchar(4) NOT NULL DEFAULT '',
                            `giacenza` float NOT NULL DEFAULT '0',
                            `costo_medio` float NOT NULL DEFAULT '0',
                            `anno_attivo` int(11) NOT NULL DEFAULT '2021',
                            PRIMARY KEY (`codice`,`negozio`,`anno_attivo`)
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
							( codice, negozio, giacenza, costo_medio, anno_attivo )
						values
							( :codice, :negozio, :giacenza, :costo_medio, :anno_attivo )
                        on duplicate key update
                            giacenza = :giacenza, costo_medio = :costo_medio";
				$stmt = $this->pdo->prepare($sql);
                $stmt->execute(array(	":codice" => $record['codice'],
               							":negozio" => $record['negozio'],
                						":giacenza" => $record['giacenza'],
                                        ":costo_medio" => $record['costo_medio'],
                                        ":anno_attivo" => $record['anno_attivo']
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

                $sql = "delete from $this->tableName where codice = :codice and negozio = :negozio and anno_attivo = :anno_attivo";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(array(	":codice" => $record['codice'],
               							":negozio" => $record['negozio'],
                						":anno_attivo" => $record['anno_attivo']
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
        
        public function ricerca($record) {
             try {
                $sql = "select codice, upper(negozio) `negozio`, giacenza from $this->tableName where anno_attivo = :anno_attivo";
                if (key_exists('codice', $record)) {
                    $codice = $record['codice'];
                    $sql .= " and codice = '$codice'";
                }
                
				$stmt = $this->pdo->prepare($sql);
                $stmt->execute( [":anno_attivo" => $record['anno_attivo'] ] );
                $result = $stmt->fetchall(\PDO::FETCH_ASSOC);
                
                $movimenti = [];
                foreach ($result as $record) {
                    if (! key_exists($record['codice'], $movimenti)) {
                        $movimenti[$record['codice']] = []; 
                    }
                    if (! key_exists($record['negozio'], $movimenti[$record['codice']])) {
                        $movimenti[$record['codice']][$record['negozio']] = 0; 
                    }
                    
                    $movimenti[$record['codice']][$record['negozio']] += $record['giacenza']*1;
                }
                
				return $movimenti;
            } catch (PDOException $e) {
             	$this->pdo->rollBack();
                return [];
            }
        }

        public function __destruct() {
			parent::__destruct();
        }

    }
?>
