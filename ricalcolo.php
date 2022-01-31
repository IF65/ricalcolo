<?php
@ini_set('memory_limit', '16384M');

include(__DIR__ . '/Database/bootstrap.php');

use Database\Tables\Giacenzainiziale;
use Database\Tables\Giacenze;
use Database\Tables\Vendite;
use Database\Tables\Arrivi;
use Database\Tables\Trasferimentiin;
use Database\Tables\Trasferimentiout;
use Database\Tables\Diversi;

// impostazioni debug
//--------------------------------------------------------------------------------
$codiceArticoloAnalizzato = '';

// creazione ogetti
//--------------------------------------------------------------------------------
$giacenzeIniziali = new Giacenzainiziale($sqlDetails);
$giacenze = new Giacenze($sqlDetails);
$arrivi = new Arrivi($sqlDetails);
$vendite = new Vendite($sqlDetails);
$trasferimentiIn = new Trasferimentiin($sqlDetails);
$trasferimentiOut = new Trasferimentiout($sqlDetails);
$diversi = new Diversi($sqlDetails);
sleep(3);

// impostazioni periodo
//--------------------------------------------------------------------------------
$timeZone = new DateTimeZone('Europe/Rome');

$currentTime = (new DateTime('now', $timeZone))->format('His');

$end = new DateTime('now', $timeZone);
if ($currentTime < '223000') {
	$end->sub(new DateInterval('P1D'));
}
$start = new DateTime('January 1, ' . $end->format('Y'));
$interval = new DateInterval('P1D');
$range = new DatePeriod($start, $interval, $end);

/*$end = DateTime::createFromFormat('Y-m-d', '2021-01-05');
$start = new DateTime('January 1, '.$end->format('Y'));
$interval = new DateInterval('P1D');
$range = new DatePeriod($start, $interval, $end);*/

// serpentone
//--------------------------------------------------------------------------------
sleep(3);
if ($giacenze->creaTabellaGiacenzePerRicalcolo()) {
	// carico le giacenze iniziali
	$situazioni = $giacenzeIniziali->ricerca(['anno_attivo' => $start->format('Y')]);
	foreach ($range as $date) {
		echo $date->format('Y-m-d') . "\n";

		// carico gli arrivi
		$elencoArrivi = $arrivi->movimenti(["data" => $date->format('Y-m-d')]); //, 'codice' => $codiceArticoloAnalizzato
		foreach ($elencoArrivi as $codice => $arrivo) {
			if (!key_exists($codice, $situazioni)) {
				$situazioni[$codice] = [];
			}
			foreach ($arrivo as $negozio => $quantita) {
				if (!key_exists($negozio, $situazioni[$codice])) {
					$situazioni[$codice][$negozio] = 0;
				}

				$situazioni[$codice][$negozio] += $quantita;
			}
		}
		unset($elencoArrivi);

		// carico i trasferimenti in ingresso
		$elencoTrasferimentiIn = $trasferimentiIn->movimenti(["data" => $date->format('Y-m-d')]);
		foreach ($elencoTrasferimentiIn as $codice => $trasferimento) {
			if (!key_exists($codice, $situazioni)) {
				$situazioni[$codice] = [];
			}
			foreach ($trasferimento as $negozio => $quantita) {
				if (!key_exists($negozio, $situazioni[$codice])) {
					$situazioni[$codice][$negozio] = 0;
				}

				$situazioni[$codice][$negozio] += $quantita;
			}
		}
		unset($elencoTrasferimentiIn);

		// carico/scarico i diversi
		$elencoDiversi = $diversi->movimenti(["data" => $date->format('Y-m-d')]);
		foreach ($elencoDiversi as $codice => $diverso) {
			if (!key_exists($codice, $situazioni)) {
				$situazioni[$codice] = [];
			}
			foreach ($diverso as $negozio => $quantita) {
				if (!key_exists($negozio, $situazioni[$codice])) {
					$situazioni[$codice][$negozio] = 0;
				}
				$situazioni[$codice][$negozio] -= $quantita;
			}
		}
		unset($elencoDiversi);

		// scarico i trasferimenti in uscita
		$elencoTrasferimentiOut = $trasferimentiOut->movimenti(["data" => $date->format('Y-m-d')]);
		foreach ($elencoTrasferimentiOut as $codice => $trasferimento) {
			if (!key_exists($codice, $situazioni)) {
				$situazioni[$codice] = [];
			}
			foreach ($trasferimento as $negozio => $quantita) {
				if (!key_exists($negozio, $situazioni[$codice])) {
					$situazioni[$codice][$negozio] = 0;
				}

				$situazioni[$codice][$negozio] -= $quantita;
			}
		}
		unset($elencoTrasferimentiOut);

		// scarico le vendite
		$elencoVendite = $vendite->movimenti(["data" => $date->format('Y-m-d')]);
		foreach ($elencoVendite as $codice => $vendita) {
			if (!key_exists($codice, $situazioni)) {
				$situazioni[$codice] = [];
			}
			foreach ($vendita as $negozio => $quantita) {
				if (!key_exists($negozio, $situazioni[$codice])) {
					$situazioni[$codice][$negozio] = 0;
				}

				$situazioni[$codice][$negozio] -= $quantita;
			}
		}
		unset($elencoVendite);

		$giacenze->caricaSituazioni($date, $situazioni);
	}
	$giacenze->creaGiacenzeCorrenti();
}
$giacenze->eliminaTabelleTemporaneeRicalcolo();

//$json = json_encode($situazioni, true);
//file_put_contents("/Users/if65/Desktop/dati.json", $json);
	
