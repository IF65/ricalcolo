<?php
@ini_set('memory_limit', '1024M');

include(__DIR__ . '/Database/bootstrap.php');

use Database\Tables\Giacenze;
use Database\Tables\Vendite;
use Database\Tables\Log;
use Database\Views\Barcode;

// creazione cartelle
//--------------------------------------------------------------------------------
$cartellaDiInvio = '/gre/file_da_inviare';
if (!file_exists($cartellaDiInvio)) {
	mkdir($cartellaDiInvio, 0777, true);
}

// creazione oggetti
//--------------------------------------------------------------------------------
$giacenze = new Giacenze($sqlDetails);
$vendite = new Vendite($sqlDetails);
$barcodeCopre = new Barcode($sqlDetails);
$log = new Log($sqlDetails);

$articoliNascosti = $giacenze->getHiddenArticles();

// impostazioni periodo
//--------------------------------------------------------------------------------
$timeZone = new DateTimeZone('Europe/Rome');

$dataCorrente = (new DateTime())->setTimezone($timeZone);

$dataFinale = (clone $dataCorrente)->setTimezone($timeZone)->sub(new DateInterval('P1D'));
$dataIniziale = (clone $dataFinale)->setTimezone($timeZone)->sub(new DateInterval('P4D'));
$data = clone $dataIniziale;

$elencoBarcodeCopre = $barcodeCopre->creaElenco();
while ($data->format('Ymd') <= $dataFinale->format('Ymd')) {
	$elencoSediDaInviare = $log->elencoSediDaInviare($data->format('Y-m-d'), 210);
	$vendutoAllaData = $vendite->esportazioneVenditeGreCopre($data->format('Y-m-d'));

	$negozioOld = '';
	$scontrinoOld = 0;

	// calcolo del totale di ogni scontrino
	$totali = [];
	foreach ($vendutoAllaData as $vendita) {
		if ($negozioOld != $vendita['negozio'] or $scontrinoOld != $vendita['numero_upb']) {
			if ($negozioOld != '') {
				$totali[$negozioOld][$scontrinoOld]['totale'] = $totale;
				$totali[$negozioOld][$scontrinoOld]['totale no iva'] = $totaleNoIva;
			}
			$negozioOld = $vendita['negozio'];
			$scontrinoOld = $vendita['numero_upb'];
			$contatore = 1;
			$totale = $vendita['totale'];
			$totaleNoIva = $vendita['totale no iva'];
		} else {
			$totale += $vendita['totale'];
			$totaleNoIva += $vendita['totale no iva'];
		}
	}
	$totali[$negozioOld][$scontrinoOld]['totale'] = $totale;
	$totali[$negozioOld][$scontrinoOld]['totale no iva'] = $totaleNoIva;

	$contatore = 1;
	$negozioOld = '';
	$scontrinoOld = 0;

	// scrittura righe
	$righe = [];
	foreach ($vendutoAllaData as $vendita) {
		if (array_key_exists($vendita['negozio'], $elencoSediDaInviare)) {
			$costo = $vendita['costo_ultimo'] * 1;
			if ($costo == 0.0) {
				$costo = $vendita['costo_medio'] * 1;
			}

			$riga = '';
			// sistemo i contatori
			if ($negozioOld != $vendita['negozio'] or $scontrinoOld != $vendita['numero_upb']) {
				if ($negozioOld != '' && $negozioOld != $vendita['negozio']) {

				}

				if ($negozioOld != $vendita['negozio']) {
					if (!$log->incaricoOk($vendita['negozio'], $data->format('Y-m-d'), 210)) {

					}
				}

				$negozioOld = $vendita['negozio'];
				$scontrinoOld = $vendita['numero_upb'];
				$contatore = 1;
			} else {
				$contatore += 1;
			}

			$totale = 0;
			$totaleNoIva = 0;
			if (array_key_exists($vendita['negozio'], $totali)) {
				if (array_key_exists($vendita['numero_upb'], $totali[$vendita['negozio']])) {
					$totale = $totali[$vendita['negozio']][$vendita['numero_upb']]['totale'];
					$totaleNoIva = $totali[$vendita['negozio']][$vendita['numero_upb']]['totale no iva'];
				}
			}

			// inserisco il barcode principale di copre
			$mainBarcode = $vendita['ean'];
			if (key_exists($vendita['codice'], $elencoBarcodeCopre)) {
				$mainBarcode = $elencoBarcodeCopre[$vendita['codice']];
			}
			//$riga .= $dataCorrente->format('dmY H:i').'|'.$dataCalcolo->format('dmY').'|';
			$riga .= $dataCorrente->format('dmY H:i') . '|' . $data->format('dmY') . '|';
			$riga .= $vendita['ora'] . "|";
			$riga .= $vendita['numero_upb'] . "|";
			$riga .= $vendita['numero'] . "|||";
			$riga .= $vendita['negozio'] . "|SUPERMEDIA|02147260174||" . $vendita['carta'] . "|||||||DET|";
			$riga .= number_format($totale, 2, ',', '') . "|";
			$riga .= number_format($totaleNoIva, 2, ',', '') . "|";
			$riga .= number_format($totale, 2, ',', '') . "|0|0|0|0|0|0|0|0|";
			$riga .= "$contatore||";
			if (key_exists($vendita['codice'], $articoliNascosti)) {
				$riga .= '' . "|";
				$riga .= '9999999' . "|";
				$riga .= 'SM' . "|";
				$riga .= '' . "|";
			} else {
				$riga .= $mainBarcode . "|";
				$riga .= $vendita['codice'] . "|";
				$riga .= $vendita['marca'] . "|";
				$riga .= $vendita['modello'] . "|";
			}
			$riga .= $vendita['quantita'] . "|";
			$riga .= number_format($vendita['prezzo_unitario'], 2, ',', '') . "|0|";
			$riga .= number_format($vendita['totale'], 2, ',', '') . "|";
			$riga .= number_format($vendita['totale no iva'], 2, ',', '') . "|";
			$riga .= $vendita['tipo'] . "||0|" . preg_replace('/\./', ',', sprintf('%.2f', $costo)) . "\r\n";

			$righe[] = $riga;
		}
	}

	$nome_file = 'SO_02147260174_SM_' . $data->format('Ymd') . '.txt';
	file_put_contents("$cartellaDiInvio/$nome_file", $righe);

	$data->add(new DateInterval('P1D'));
}

$data = (clone $dataCorrente)->setTimezone($timeZone)->sub(new DateInterval('P1D'));

$giacenzeSM = $giacenze->giacenzeSM();

$righe = [];
foreach ($giacenzeSM as $codiceNegozio => $recordNegozio) {
	if ($codiceNegozio != 'SMMD' && $codiceNegozio != 'SMW1' && $codiceNegozio != '') {
		foreach ($recordNegozio as $codiceArticolo => $recordArticolo) {

			$giacenza = $recordArticolo['giacenza'];
			if (key_exists($codiceArticolo, $articoliNascosti)) {
				$giacenza = 0;
			}
			if ($giacenza > 0) {
				// inserisco il barcode principale di copre
				$mainBarcode = $recordArticolo['ean'];
				if (key_exists($codiceArticolo, $elencoBarcodeCopre)) {
					$mainBarcode = $elencoBarcodeCopre[$codiceArticolo];
				}

				$riga = '';
				$riga .= $dataCorrente->format('dmY H:i') . '|' . $data->format('dmY') . '|';
				$riga .= 'SUPERMEDIA|02147260174|';
				$riga .= strtoupper($codiceNegozio) . "||";
				$riga .= $mainBarcode . "|";
				$riga .= $codiceArticolo . "|";
				$riga .= $recordArticolo['linea'] . "|";
				$riga .= $recordArticolo['modello'] . "|";
				$riga .= "$giacenza|$giacenza|0||0,00\r\n";

				$righe[] = $riga;
			}
		}
	}
}
$nome_file = 'ST_02147260174_SM_' . $data->format('Ymd') . '.txt';
file_put_contents("$cartellaDiInvio/$nome_file", $righe);

// creazione semaforo verde
touch("$cartellaDiInvio/CO_02147260174_SM.txt");


	