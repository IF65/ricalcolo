<?php
	@ini_set('memory_limit','256M');
	
	include(__DIR__.'/Database/bootstrap.php');

	use Database\Tables\Giacenzainiziale;
	use Database\Tables\Giacenze;
	use Database\Tables\Vendite;
	use Database\Tables\Arrivi;
	
	// creazione ogetti
	//--------------------------------------------------------------------------------
	$giacenzeIniziali = new Giacenzainiziale($sqlDetails);
	$giacenze = new Giacenze($sqlDetails);
	$arrivi = new Arrivi($sqlDetails);
	$vendite = new Vendite($sqlDetails);
	
    // impostazioni periodo
	//--------------------------------------------------------------------------------
    $timeZone = new DateTimeZone('Europe/Rome');

	$end = new DateTime();
	$start = new DateTime('January 1, '.$end->format('Y'));
	$interval = new DateInterval('P1D');
	$range = new DatePeriod($start, $interval, $end);
	
	// serpentone
	//--------------------------------------------------------------------------------
	
	// carico le giacenze iniziali
	$situazioni = $giacenzeIniziali->ricerca(['anno_attivo' => $start->format('Y')]);
	foreach ($range as $date) {
		print_r($date->format('Y-m-d')."\n");
		// carico gli arrivi
		$elencoArrivi = $arrivi->movimenti(["data" => $date->format('Y-m-d')]);
		foreach ($elencoArrivi as $codice => $arrivo) {
			if (! key_exists($codice, $situazioni)) {
				$situazioni[$codice] = [];
			}
			foreach ($arrivo as $negozio => $quantita) {
				if (! key_exists($negozio, $situazioni)) {
					$situazioni[$codice][$negozio] = 0;
				}
				
				$situazioni[$codice][$negozio] += $quantita;
				if ($situazioni[$codice][$negozio] == 0) {
					unset($situazioni[$codice][$negozio]);
				}
			}
		}
		unset($elencoArrivi);
		
		// scarico le vendite
		$elencoVendite = $vendite->movimenti(["data" => $date->format('Y-m-d')]);
		foreach ($elencoVendite as $codice => $vendita) {
			if (! key_exists($codice, $situazioni)) {
				$situazioni[$codice] = [];
			}
			foreach ($vendita as $negozio => $quantita) {
				if (! key_exists($negozio, $situazioni)) {
					$situazioni[$codice][$negozio] = 0;
				}
				
				$situazioni[$codice][$negozio] -= $quantita;
				if ($situazioni[$codice][$negozio] == 0) {
					unset($situazioni[$codice][$negozio]);
				}
			}
		}
		unset($elencoVendite);
		
		// qui devo salvare le giacenze della giornata
	}