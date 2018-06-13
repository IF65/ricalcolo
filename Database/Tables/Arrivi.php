<?php
    namespace Database\Tables;

	use Database\Database;

	class Arrivi extends Database {
        
        public $tableNameT = 'arrivi';
        public $tableNameR = 'righe_arrivi';

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
                            `id_arrivi` varchar(36) NOT NULL DEFAULT '',
                            `keycod` varchar(36) NOT NULL DEFAULT '',
                            `codice_articolo` varchar(7) NOT NULL DEFAULT '',
                            `codice_articolo_fornitore` varchar(15) NOT NULL DEFAULT '',
                            `costo` float NOT NULL DEFAULT '0',
                            `listino` float NOT NULL DEFAULT '0',
                            `quantita` int(11) NOT NULL DEFAULT '0',
                            `quantita_evasa` int(11) NOT NULL DEFAULT '0',
                            `sconto_a` float NOT NULL DEFAULT '0',
                            `sconto_b` float NOT NULL DEFAULT '0',
                            `sconto_c` float NOT NULL DEFAULT '0',
                            `sconto_d` float NOT NULL DEFAULT '0',
                            `sconto_cassa` float NOT NULL DEFAULT '0',
                            `sconto_commerciale` float NOT NULL DEFAULT '0',
                            `sconto_extra` float NOT NULL DEFAULT '0',
                            `sconto_importo` float NOT NULL DEFAULT '0',
                            `sconto_merce` float NOT NULL DEFAULT '0',
                            `spese_trasporto` float NOT NULL DEFAULT '0',
                            PRIMARY KEY (`id`),
                            KEY `arrivi` (`id_arrivi`),
                            KEY `articolo` (`codice_articolo`,`codice_articolo_fornitore`),
                            KEY `keycod` (`keycod`)
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
                $sql = "select a.`data_arrivo` `data`, r.`codice_articolo` `codice`,a.`negozio`,sum(r.`quantita`) `quantita`
                        from `$this->tableNameT` as a join `$this->tableNameR` as r on a.`id`=r.`id_arrivi`
                        where a.`data_arrivo` = '$data'
                        group by 1,2,3";
                if (key_exists('negozio', $record)) {
                    $negozio = $record['negozio'];
                    $sql .= " and a.negozio = '$negozio'"; 
                }
                            
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
                    
                    $movimenti[$record['codice']][$record['negozio']] = $record['quantita'];
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
