<?php
    namespace Database\Tables;

	use Database\Database;

	class Trasferimentiin extends Database {
        
        public $tableNameT = 'trasferimenti_in';
        public $tableNameR = 'righe_trasferimenti_in';

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
                            `id` varchar(36) NOT NULL DEFAULT '',
                            `numero` varchar(25) NOT NULL DEFAULT '',
                            `negozio` varchar(4) NOT NULL DEFAULT '',
                            `data_arrivo` date NOT NULL DEFAULT '0000-00-00',
                            `data_ddt` date NOT NULL DEFAULT '0000-00-00',
                            `numero_ddt` varchar(20) NOT NULL DEFAULT '',
                            `codice_fornitore` varchar(10) NOT NULL DEFAULT '',
                            `bozza` tinyint(1) NOT NULL DEFAULT '0',
                            `materiale_consumo` tinyint(1) NOT NULL DEFAULT '0',
                            PRIMARY KEY (`id`),
                            KEY `arrivo` (`negozio`,`data_arrivo`),
                            KEY `ddt` (`data_ddt`,`numero_ddt`,`codice_fornitore`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
                $this->pdo->exec($sql);
                
                $sql = "CREATE TABLE IF NOT EXISTS $this->tableNameR (
                            `id` varchar(36) NOT NULL DEFAULT '',
                            `link_trasferimento` varchar(36) NOT NULL DEFAULT '',
                            `codice` varchar(7) NOT NULL DEFAULT '',
                            `ean` varchar(13) NOT NULL DEFAULT '',
                            `quantita` float NOT NULL DEFAULT '0',
                            PRIMARY KEY (`id`),
                            KEY `link` (`link_trasferimento`),
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
                $sql = "select t.`data`,r.`codice`,upper(t.`negozio_arrivo`) `negozio`,sum(r.`quantita`) `quantita`
                        from $this->tableNameT as t join $this->tableNameR as r on t.`link`=r.`link_trasferimento`
                        where t.`data` = '$data'";
                if (key_exists('negozio', $record)) {
                    $negozio = $record['negozio'];
                    $sql .= " and t.negozio_arrivo = '$negozio'";
                }
                if (key_exists('codice', $record) && $record['codice'] != '') {
                    $codice = $record['codice'];
                    $sql .= " and r.codice = '$codice'";
                }
                $sql .= " group by 1,2,3;";
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
