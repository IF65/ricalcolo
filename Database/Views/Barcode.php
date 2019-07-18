<?php
namespace Database\Views;

use Database\Database;

class Barcode extends Database {

    public $viewName = 'barcode';

    public function __construct($sqlDetails) {
        try {
            parent::__construct($sqlDetails);

        } catch (PDOException $e) {
            die($e->getMessage());
        }
    }


    public function creaElenco() {
        try {
            $sql = "select f.codice, t.barcode 
                    from (  select distinct codice_articolo as codice, codice_articolo_fornitore as codiceGcc  
                            from db_sm.fornitore_articolo 
                            where codice_fornitore = 'FCOPRE') as f join 
                        copre.tabulatoCopre as t on f.codiceGcc = t.`codice`";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            $barcodeList = $stmt->fetchall(\PDO::FETCH_ASSOC);

            $result = [];
            foreach ($barcodeList as $barcode) {
                 $result[$barcode['codice']] = $barcode['barcode'];
            }

            return $result;
        } catch (PDOException $e) {
            die($e->getMessage());
        }
    }

    public function __destruct() {
        parent::__destruct();
    }

}
?>