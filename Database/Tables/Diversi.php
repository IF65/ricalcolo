<?php
    namespace Database\Tables;

	use Database\Database;

	class Diversi extends Database {
        
        public $tableNameT = 'diversi';
        public $tableNameR = 'righe_diversi';

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
                $sql = "CREATE TABLE IF NOT EXISTS $this->tableNameT (
                            `link` varchar(36) NOT NULL DEFAULT '',
                            `progressivo` varchar(36) NOT NULL DEFAULT '',
                            `negozio` varchar(4) NOT NULL DEFAULT '',
                            `data` date NOT NULL,
                            `numero_ddt` varchar(5) NOT NULL DEFAULT '',
                            `definitivo` tinyint(1) unsigned NOT NULL DEFAULT '0',
                            PRIMARY KEY (`link`),
                            KEY `progressivo` (`progressivo`),
                            KEY `data` (`data`,`negozio`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=latin1";
                $this->pdo->exec($sql);
                
                $sql = "CREATE TABLE IF NOT EXISTS $this->tableNameR (
                            `progressivo` varchar(36) NOT NULL DEFAULT '',
                            `link_diversi` varchar(36) NOT NULL DEFAULT '',
                            `codice` varchar(7) NOT NULL DEFAULT '',
                            `ean` varchar(7) NOT NULL DEFAULT '',
                            `quantita` float NOT NULL DEFAULT '0',
                            `costo` float NOT NULL DEFAULT '0',
                            PRIMARY KEY (`progressivo`),
                            KEY `link` (`link_diversi`),
                            KEY `codici` (`codice`,`ean`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
                $this->pdo->exec($sql);

				return true;
            } catch (PDOException $e) {
                die($e->getMessage());
            }
        }
        
        public function movimenti($record) {
             try {
                $data = $record['data'];
                $sql = "select d.data, r.`codice`,upper(d.`negozio`) `negozio`,sum(r.`quantita`) `quantita`
                        from  `$this->tableNameT` as d join  `$this->tableNameR` as r on d.`link`=r.`link_diversi`
                        where d.`data`= '$data'";
                if (key_exists('negozio', $record)) {
                    $negozio = $record['negozio'];
                    $sql .= " and a.negozio = '$negozio'"; 
                }
                if (key_exists('codice', $record)) {
                    $codice = $record['codice'];
                    $sql .= " and r.codice = '$codice'";
                }
                $sql .= " group by 1,2,3";
                
				$stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                $result = $stmt->fetchall(\PDO::FETCH_ASSOC);
                
                $movimenti = [];
                foreach ($result as $record) {
                    if (! key_exists($record['codice'], $movimenti)) {
                        $movimenti[$record['codice']] = []; 
                    }
                    if (! key_exists($record['negozio'], $movimenti[$record['codice']])) {
                        $movimenti[$record['codice']][$record['negozio']] = 0; 
                    }
                    
                    $movimenti[$record['codice']][$record['negozio']] += $record['quantita'];
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
