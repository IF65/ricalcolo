<?php
    namespace Database\Tables;

	use Database\Database;

	class Vendite extends Database {
        
        public $tableName = 'righe_vendita';

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
                            `progressivo` varchar(36) NOT NULL DEFAULT '',
                            `data` date NOT NULL,
                            `ora` varchar(8) NOT NULL DEFAULT '',
                            `negozio` varchar(4) NOT NULL DEFAULT '',
                            `codice` varchar(7) NOT NULL DEFAULT '',
                            `ean` varchar(13) NOT NULL DEFAULT '',
                            `reparto` varchar(20) NOT NULL DEFAULT '',
                            `famiglia` varchar(20) NOT NULL DEFAULT '',
                            `sottofamiglia` varchar(20) NOT NULL DEFAULT '',
                            `riga_non_fiscale` tinyint(1) NOT NULL,
                            `riparazione` tinyint(1) NOT NULL,
                            `numero_riparazione` varchar(13) NOT NULL DEFAULT '',
                            `prezzo_unitario` float NOT NULL DEFAULT '0',
                            `quantita` float NOT NULL DEFAULT '0',
                            `importo_totale` float NOT NULL DEFAULT '0',
                            `margine` float NOT NULL DEFAULT '0',
                            `aliquota_iva` float NOT NULL DEFAULT '0',
                            `tipo_iva` int(11) NOT NULL DEFAULT '0',
                            `descrizione` varchar(255) NOT NULL DEFAULT '',
                            `linea` varchar(40) NOT NULL DEFAULT '',
                            `matricola_rc` varchar(10) NOT NULL DEFAULT '',
                            `codice_operatore` varchar(4) NOT NULL DEFAULT '',
                            `codice_venditore` varchar(4) NOT NULL DEFAULT '',
                            `id_scontrino` varchar(32) NOT NULL DEFAULT '',
                            PRIMARY KEY (`progressivo`),
                            KEY `codice` (`codice`),
                            KEY `ean` (`ean`),
                            KEY `id_scontrino` (`id_scontrino`),
                            KEY `progressivo` (`progressivo`,`margine`),
                            KEY `data` (`data`,`negozio`,`codice`),
                            KEY `linea` (`linea`),
                            KEY `codice_venditore` (`codice_venditore`),
                            KEY `data_2` (`data`,`negozio`),
                            KEY `codice_2` (`codice`,`negozio`)
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
							(   progressivo, data, ora, negozio, codice, ean, reparto, famiglia, sottofamiglia, riga_non_fiscale,
                                riparazione, numero_riparazione, prezzo_unitario, quantita, importo_totale, margine, aliquota_iva,
                                tipo_iva, descrizione, linea, matricola_rc, codice_operatore, codice_venditore, id_scontrino
                            )
						values
							(   :progressivo, :data, :ora, :negozio, :codice, :ean, :reparto, :famiglia, :sottofamiglia,
                                :riga_non_fiscale, :riparazione, :numero_riparazione, :prezzo_unitario, :quantita, :importo_totale,
                                :margine, :aliquota_iva, :tipo_iva, :descrizione, :linea, :matricola_rc, :codice_operatore,
                                :codice_venditore, :id_scontrino
                            )
                        on duplicate key update
                            progressivo = :progressivo, data = :data, ora = :ora, negozio = :negozio, codice = :codice, ean = :ean,
                            reparto = :reparto, famiglia = :famiglia, sottofamiglia = :sottofamiglia, riga_non_fiscale = :riga_non_fiscale,
                            riparazione = :riparazione, numero_riparazione = :numero_riparazione, prezzo_unitario = :prezzo_unitario,
                            quantita = :quantita, importo_totale = :importo_totale, margine = :margine, aliquota_iva = :aliquota_iva,
                            tipo_iva = :tipo_iva, descrizione = :descrizione, linea = :linea, matricola_rc = :matricola_rc,
                            codice_operatore = :codice_operatore, codice_venditore = :codice_venditore, id_scontrino = :id_scontrino";
                            
				$stmt = $this->pdo->prepare($sql);
                $stmt->execute(array(	":progressivo" => $record['progressivo'],
                                        ":data" => $record['data'],
                                        ":ora" => $record['ora'],
                                        ":negozio" => $record['negozio'],
                                        ":codice" => $record['codice'],
                                        ":ean" => $record['ean'],
                                        ":reparto" => $record['reparto'],
                                        ":famiglia" => $record['famiglia'],
                                        ":sottofamiglia" => $record['sottofamiglia'],
                                        ":riga_non_fiscale" => $record['riga_non_fiscale'],
                                        ":riparazione" => $record['riparazione'],
                                        ":numero_riparazione" => $record['numero_riparazione'],
                                        ":prezzo_unitario" => $record['prezzo_unitario'],
                                        ":quantita" => $record['quantita'],
                                        ":importo_totale" => $record['importo_totale'],
                                        ":margine" => $record['margine'],
                                        ":aliquota_iva" => $record['aliquota_iva'],
                                        ":tipo_iva" => $record['tipo_iva'],
                                        ":descrizione" => $record['descrizione'],
                                        ":linea" => $record['linea'],
                                        ":matricola_rc" => $record['matricola_rc'],
                                        ":codice_operatore" => $record['codice_operatore'],
                                        ":codice_venditore" => $record['codice_venditore'],
                                        ":id_scontrino" => $record['id_scontrino']
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
        
        public function ricerca($record) {
             try {
                $sql = "select * from $this->tableName where data = :data and negozio = :negozio";
                            
				$stmt = $this->pdo->prepare($sql);
                $stmt->execute( [":data" => $record['data'], ":negozio" => $record['negozio']] );
                $result = $stmt->fetchall(\PDO::FETCH_ASSOC);
                
				return $result;
            } catch (PDOException $e) {
             	$this->pdo->rollBack();
                return [];
            }
        }
        
        public function esportazioneVenditeGreCopre($dataEsportazione) {
            $sql = "select 	r.`data`, 
							substr(r.`ora`,1,2) `ora`,
							s.`numero_upb`,
							s.`numero`,
							r.`negozio`,
							ifnull((select e.`ean` from `ean` as e where e.`codice`=r.`codice` order by 1 desc limit 1),'2999999999999') as `ean`,
							ma.`codice`,
							mr.`marca`, 
							ma.`modello`,
                            round(ifnull(ma.`costo_ultimo`,0),2) as `costo_ultimo`,
                            round(ifnull(ma.`costo_medio`,0), 2) as `costo_medio`,
							round(r.`quantita`,0) `quantita`,
							round(case when r.`quantita` <> 0 then r.`importo_totale`/r.`quantita` else 0 end ,2) `prezzo_unitario`,
							round(r.`importo_totale`,2) `totale`,
							round(r.`importo_totale`*100/(100+r.`aliquota_iva`),2) `totale no iva`,
							case when r.`quantita`>=0 then 'VEN' else 'RES' end `tipo`,
							case when substr(s.`carta`,1,3)='043' then s.`carta` else '' end `carta`
							from 	`marche` as mr join `magazzino` as ma on mr.`linea`=ma.`linea` join
									`righe_vendita` as r on r.`codice`=ma.`codice` join
									`scontrini` as s on r.`id_scontrino`=s.`id_scontrino` 
							where mr.`invio_gre`=1 and ma.`invio_gre`=1 and r.`data`= '$dataEsportazione' and r.`riga_non_fiscale`=0 and  r.`riparazione`=0 and r.`importo_totale`<>0 and
								r.`codice` not in ('0560440','0560459','0560468','0619218','0560477','0560486','0560495','0575504','0575513')
							order by  r.`data`,lpad(SUBSTR(r.negozio,3),2,'0'),s.`numero_upb`, ma.`codice`;";
                            
            try {
                $stmt = $this->pdo->prepare($sql);
                if ($stmt->execute()) {
                    $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    
                    return $results;
                }
                return null;
            } catch (PDOException $e) {
                print $e->getMessage();
                return null;
            }
        }
        
        public function movimenti($record) {
             try {
                $data = $record['data'];
                $sql = "select data, upper(negozio) `negozio`, codice, quantita from $this->tableName where data = '$data' and riga_non_fiscale=0 and  riparazione=0 and importo_totale<>0";
                if (key_exists('negozio', $record)) {
                    $negozio = $record['negozio'];
                    $sql .= " and negozio = '$negozio'"; 
                }
                if (key_exists('codice', $record)) {
                    $codice = $record['codice'];
                    $sql .= " and codice = '$codice'";
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
                    
                    $movimenti[$record['codice']][$record['negozio']] += $record['quantita'];
                }
                
				return $movimenti;
            } catch (PDOException $e) {
             	$this->pdo->rollBack();
                return [];
            }
        }

        public function cancellaRecord($record) {
            try {
                $this->pdo->beginTransaction();

                $sql = "delete from $this->tableName where progressivo = :progressivo";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(array( ":progressivo" => $record['progressivo'] ));
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
