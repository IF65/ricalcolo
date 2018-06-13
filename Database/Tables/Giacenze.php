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
                        `data` date NOT NULL,
                        `codice` varchar(7) NOT NULL DEFAULT '',
                        `negozio` varchar(4) NOT NULL DEFAULT '',
                        `giacenza` float NOT NULL DEFAULT '0',
                        PRIMARY KEY (`data`,`codice`,`negozio`)
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
							( data, codice, negozio, giacenza )
						values
							( :data, :codice, :negozio, :giacenza )
                        on duplicate key update
                            giacenza = :giacenza";
				$stmt = $this->pdo->prepare($sql);
                $stmt->execute(array(	":data" => $record['data'],
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

        public function __destruct() {
			parent::__destruct();
        }

    }
?>
