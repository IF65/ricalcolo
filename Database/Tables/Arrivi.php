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
                            `link` varchar(36) NOT NULL DEFAULT '',
                            `negozio_partenza` varchar(4) NOT NULL DEFAULT '',
                            `negozio_arrivo` varchar(4) NOT NULL DEFAULT '',
                            `numero_ddt` varchar(13) NOT NULL DEFAULT '',
                            `data` date NOT NULL,
                            `fase` tinyint(1) unsigned NOT NULL DEFAULT '0',
                            `causale` varchar(28) NOT NULL DEFAULT '',
                            `solo_giacenze` tinyint(1) unsigned NOT NULL DEFAULT '0',
                            PRIMARY KEY (`link`),
                            KEY `negozio` (`negozio_partenza`,`negozio_arrivo`),
                            KEY `ddt` (`numero_ddt`,`data`)
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
                $sql = "select a.`data_arrivo` `data`, r.`codice_articolo` `codice`,upper(a.`negozio`) `negozio`,sum(r.`quantita`) `quantita`
                        from `$this->tableNameT` as a join `$this->tableNameR` as r on a.`id`=r.`id_arrivi`
                        where a.`data_arrivo` = '$data'";
                if (key_exists('negozio', $record)) {
                    $negozio = $record['negozio'];
                    $sql .= " and a.negozio = '$negozio'"; 
                }
                if (key_exists('codice', $record)) {
                    $codice = $record['codice'];
                    $sql .= " and r.codice_articolo = '$codice'";
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
