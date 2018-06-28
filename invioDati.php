<?php
	@ini_set('memory_limit','1024M');
	
	include(__DIR__.'/Database/bootstrap.php');

	use Database\Tables\Giacenze;
	use Database\Tables\Vendite;
	
	// creazione ogetti
	//--------------------------------------------------------------------------------
	$giacenze = new Giacenze($sqlDetails);
	$vendite = new Vendite($sqlDetails);
	
    // impostazioni periodo
	//--------------------------------------------------------------------------------
    $timeZone = new DateTimeZone('Europe/Rome');

	$dataCalcolo = (new DateTime())->setTimezone($timeZone)->sub(new DateInterval('P1D'));
	$dataCorrente = (new DateTime())->setTimezone($timeZone);
	
	//$giacenzeAllaData = $giacenze->giacenzeAllaData($dataCalcolo->format('Y-m-d'));
	$vendutoAllaData = $vendite->esportazioneVenditeGre($dataCalcolo->format('Y-m-d'));
	
	$nome_file = '/Users/if65/Desktop/test.txt';
	
	$negozioOld = '';
	$scontrinoOld = 0;
	
	$totali = [];
	foreach ($vendutoAllaData as $vendita) {
		if ($negozioOld != $vendita['negozio'] or $scontrinoOld  != $vendita['numero_upb']) {
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
	
	$righe = [];
	foreach ($vendutoAllaData as $vendita) {
		
		$riga = '';
		
		// sistemo i contatori
		if ($negozioOld != $vendita['negozio'] or $scontrinoOld  != $vendita['numero_upb']) {
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
		$riga .= $dataCorrente->format('dmY H:i').'|'.$dataCalcolo->format('dmY').'|';
		$riga .= $vendita['ora']."|";
		$riga .= $vendita['numero_upb']."|";
		$riga .= $vendita['numero']."|||";
		$riga .= $vendita['negozio']."|SUPERMEDIA|02147260174||".$vendita['carta']."|||||||DET|";
		$riga .= number_format($totale,2,',','')."|";
		$riga .= number_format($totaleNoIva,2,',','')."|";
		$riga .= number_format($totale,2,',','')."|0|0|0|0|0|0|0|0|";
		$riga .= "$contatore||";
		$riga .= $vendita['ean']."|";
		$riga .= $vendita['codice']."|";
		$riga .= $vendita['marca']."|";
		$riga .= $vendita['modello']."|";
		$riga .= $vendita['quantita']."|";
		$riga .= number_format($vendita['prezzo_unitario'],2,',','')."|0|";
		$riga .= number_format($vendita['totale'],2,',','')."|";
		$riga .= number_format($vendita['totale no iva'],2,',','')."|";
		$riga .= $vendita['tipo']."||0\r\n";
		
		$righe[] = $riga;
	}
	
	$n = "28062018 00:00|27062018|08|9999|9999|||SMW1|SUPERMEDIA|02147260174|||||||||DET|0,01|0,01|0,01|0|0|0|0|0|0|0|0|1||2999999999999|6672941|SAMSUNG|1GB EGOKIT|1|0,01|0|0,01|0,01|VEN||0";
	/*$json = json_encode($vendutoAllaData, true);*/
	file_put_contents("/Users/if65/Desktop/dati.txt", $righe);
	