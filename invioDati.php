<?php
	@ini_set('memory_limit','1024M');
	
	include(__DIR__.'/Database/bootstrap.php');

	use Database\Tables\Giacenze;
	use Database\Tables\Vendite;
	use Database\Tables\Log;
	
	// creazione cartelle
	//--------------------------------------------------------------------------------
	$cartellaDiInvio = '/gre/file_da_inviare';
	if (!file_exists($cartellaDiInvio)) {
		mkdir($cartellaDiInvio, 0777, true);
	}
	
	// creazione ogetti
	//--------------------------------------------------------------------------------
	$giacenze = new Giacenze($sqlDetails);
	$vendite = new Vendite($sqlDetails);
	$log = new Log($sqlDetails);
	
    // impostazioni periodo
	//--------------------------------------------------------------------------------
    $timeZone = new DateTimeZone('Europe/Rome');

	$dataCorrente = (new DateTime())->setTimezone($timeZone);
	
	$elencoDateDaInviare = $log->elencoGiornateDaInviareGre();
	foreach($elencoDateDaInviare as $dataDaInviare) {
		$dataCalcolo = (new DateTime($dataDaInviare['data']))->setTimezone($timeZone);
		$elencoSediDaInviare =  $log->elencoSediDaInviareGre($dataCalcolo->format('Y-m-d'));
		
		// invio stock
		$vendutoAllaData = $vendite->esportazioneVenditeGreCopre($dataCalcolo->format('Y-m-d'));
		
		$negozioOld = '';
		$scontrinoOld = 0;
		
		// calcolo del totale di ogni scontrino
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
		
		// scrittura righe
		$righe = [];
		foreach ($vendutoAllaData as $vendita) {
			if (array_key_exists($vendita['negozio'], $elencoSediDaInviare)) {
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
				//$riga .= $dataCorrente->format('dmY H:i').'|'.$dataCalcolo->format('dmY').'|';
				$riga .= $dataCorrente->format('dmY 00:00').'|'.$dataCalcolo->format('dmY').'|';
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
		}
		
		// ha valore 1 se la giornata � vuota
		foreach ($elencoSediDaInviare as $sede => $vuota) {
			if ($vuota) {
				$riga = '';
				$riga .= $dataCorrente->format('dmY H:i').'|'.$dataCalcolo->format('dmY').'|';
				$riga .= $vendita['ora']."|";
				$riga .= "9999|9999|||SMW1|SUPERMEDIA|02147260174|||||||||DET|0,01|0,01|0,01|0|0|0|0|0|0|0|0|1||2999999999999|6672941|SAMSUNG|1GB EGOKIT|1|0,01|0|0,01|0,01|VEN||0\r\n";
				$righe[] = $riga;
			}
		}
		
		$nome_file = 'SO_02147260174_SM_'.$dataCalcolo->format('Ymd').'.txt';
		file_put_contents("$cartellaDiInvio/$nome_file", $righe);
		
		
		$giacenzeAllaData = $giacenze->giacenzeAllaDataGreCopre($dataCalcolo->format('Y-m-d'));
		
		$righe = [];
		foreach($giacenzeAllaData as $codiceNegozio => $recordNegozio) {
			if (array_key_exists($codiceNegozio, $elencoSediDaInviare)) {
				foreach($recordNegozio as $codiceArticolo => $recordArticolo) {
					$riga = '';
					//$riga .= $dataCorrente->format('dmY H:i').'|'.$dataCalcolo->format('dmY').'|';
					$riga .= $dataCorrente->format('dmY 00:00').'|'.$dataCalcolo->format('dmY').'|';
					$riga .= 'SUPERMEDIA|02147260174|';
					$riga .= $codiceNegozio."||";
					$riga .= $recordArticolo['ean']."|";
					$riga .= $codiceArticolo."|";
					$riga .= $recordArticolo['linea']."|";
					$riga .= $recordArticolo['modello']."|";
					$riga .= $recordArticolo['giacenza']."|".$recordArticolo['giacenza']."|0||0,00\r\n";
					
					$righe[] = $riga;
				}
				$log->greOk($codiceNegozio, $dataCalcolo->format('Y-m-d'));
			}
		}
		
		$nome_file = 'ST_02147260174_SM_'.$dataCalcolo->format('Ymd').'.txt';
		file_put_contents("$cartellaDiInvio/$nome_file", $righe);
		
		// creazione semaforo verde
		touch("$cartellaDiInvio/CO_02147260174_SM.txt");
		
	}
	