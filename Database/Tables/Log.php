<?php
    namespace Database\Tables;

	use Database\Database;

	class Log extends Database {
        
        public function __construct($sqlDetails) {
        	try {
				parent::__construct($sqlDetails);

            } catch (PDOException $e) {
                die($e->getMessage());
            }
        }
        
        public function copreOK($sede, $data) {
            try {
                $sql = "select i.eseguito
                        from lavori.incarichi as i join archivi.negozi as n on i.`negozio_codice`=n.`codice`
                        where n.`codice_interno` = '$sede' and i.`lavoro_codice` = 200 and i.`data`='$data'";
                            
				$stmt = $this->pdo->prepare($sql);
                if ($stmt->execute() && $stmt->rowCount()) {
                    if ( ! ($stmt->fetch(\PDO::FETCH_NUM))[0]) {
                        $sql = "update lavori.incarichi as i join archivi.negozi as n on i.`negozio_codice`=n.`codice`
                                set i.eseguito = 1
                                where n.`codice_interno` = '$sede' and i.`lavoro_codice` = 200 and i.`data`='$data'";
                        $stmt = $this->pdo->prepare($sql);
                        if ($stmt->execute()) {
                            return true;
                        }
                    }
                }
                return false;
            } catch (PDOException $e) {
             	return false;
            }
        }
        
        public function greOK($sede, $data) {
            try {
                $sql = "select i.eseguito
                        from lavori.incarichi as i join archivi.negozi as n on i.`negozio_codice`=n.`codice`
                        where n.`codice_interno` = '$sede' and i.`lavoro_codice` = 210 and i.`data`='$data'";
                            
				$stmt = $this->pdo->prepare($sql);
                if ($stmt->execute() && $stmt->rowCount()) {
                    if ( ! ($stmt->fetch(\PDO::FETCH_NUM))[0]) {
                        $sql = "update lavori.incarichi as i join archivi.negozi as n on i.`negozio_codice`=n.`codice`
                                set i.eseguito = 1
                                where n.`codice_interno` = '$sede' and i.`lavoro_codice` = 210 and i.`data`='$data'";
                        $stmt = $this->pdo->prepare($sql);
                        if ($stmt->execute()) {
                            return true;
                        }
                    }
                }
                return false;
            } catch (PDOException $e) {
             	return false;
            }
        }
        
        public function elencoGiornateSediMancanti() {
             try {
                $sql = "select distinct n.`codice_interno`, i.`data`
                        from lavori.incarichi as i join archivi.negozi as n on n.codice=i.`negozio_codice` left join db_sm.logCaricamento as c on c.`sede`=n.`codice_interno` and c.`data`=i.`data`
                        where i.`lavoro_codice` in (200,210) and c.`sede` is  null
                        order by 1,2;";
                            
				$stmt = $this->pdo->prepare($sql);
                if ($stmt->execute()) {
                    $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    
                    return $results;
                }
                return null;
            } catch (PDOException $e) {
                return [];
            }
        }
        
        public function elencoGiornateDaInviareGre() {
             try {
                $sql = "select distinct i.`data`
                        from lavori.incarichi as i join archivi.negozi as n on n.codice=i.`negozio_codice` left join db_sm.logCaricamento as c on c.`sede`=n.`codice_interno` and c.`data`=i.`data`
                        where i.`lavoro_codice` = 210 and i.eseguito = 0 and c.`sede` is not null
                        order by 1";
                            
				$stmt = $this->pdo->prepare($sql);
                if ($stmt->execute()) {
                    $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    
                    return $results;
                }
                return [];
            } catch (PDOException $e) {
                return [];
            }
        }
        
        public function elencoSediDaInviareGre($data) {
             try {
                $sql = "select n.codice_interno, c.vuoto
                        from lavori.incarichi as i join archivi.negozi as n on n.codice=i.`negozio_codice` left join db_sm.logCaricamento as c on c.`sede`=n.`codice_interno` and c.`data`=i.`data`
                        where i.`lavoro_codice` = 210 and i.data = '$data' and c.`sede` is not null
                        order by lpad(substr(n.codice_interno,3),2,'0')";
                            
				$stmt = $this->pdo->prepare($sql);
                if ($stmt->execute()) {
                    $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    
                    $elencoNegozi = [];
                    foreach ($results as $negozio) {
                        $elencoNegozi[$negozio['codice_interno']] = $negozio['vuoto'];
                    }
                    
                    return $elencoNegozi;
                }
                return [];
            } catch (PDOException $e) {
                return [];
            }
        }
        
        public function elencoGiornateDaInviareCopre() {
             try {
                $sql = "select distinct i.`data`
                        from lavori.incarichi as i join archivi.negozi as n on n.codice=i.`negozio_codice` left join db_sm.logCaricamento as c on c.`sede`=n.`codice_interno` and c.`data`=i.`data`
                        where i.`lavoro_codice` = 200 and i.eseguito = 0 and c.`sede` is not null
                        order by 1";
                            
				$stmt = $this->pdo->prepare($sql);
                if ($stmt->execute()) {
                    $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    
                    return $results;
                }
                return null;
            } catch (PDOException $e) {
                return [];
            }
        }

        public function __destruct() {
			parent::__destruct();
        }

    }
?>
