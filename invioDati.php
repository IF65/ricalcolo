<?php
	@ini_set('memory_limit','1024M');
	
	include(__DIR__.'/Database/bootstrap.php');
	include(__DIR__.'/vendor/apache/log4php/src/main/php/Logger.php');
	
	$logConfig = [
					'appenders' => [
										'default' => [
														'class' => 'LoggerAppenderPDO',
														'params' => [
																		'dsn' => 'mysql:host=10.11.14.78;dbname=log',
																		'user' => 'root',
																		'password' => 'mela',
																		'table' => 'logPhpScript',
																	],
													],
									],
					'rootLogger' => [
										'level' => 'debug',
										'appenders' => [
															'default'
														],
									],
				];
	
	Logger::configure($logConfig);
	$logger = Logger::getLogger("invioDati");
  
	$logger->info("Inizio procedura di invio dati a Gre/Copre.");

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
	
	// creazione ogetti
	//--------------------------------------------------------------------------------
	$giacenze = new Giacenze($sqlDetails);
	$vendite = new Vendite($sqlDetails);
	$barcodeCopre = new Barcode($sqlDetails);
	$log = new Log($sqlDetails);
	$logger->info("Oggetti creati.");
	
    // impostazioni periodo
	//--------------------------------------------------------------------------------
    $timeZone = new DateTimeZone('Europe/Rome');

	$dataCorrente = (new DateTime())->setTimezone($timeZone);

	$elencoBarcodeCopre = $barcodeCopre->creaElenco();
	
	$elencoDateDaInviare = $log->elencoGiornateDaInviare(210); //200 = INVIO VENDITE COPRE, 210 = INVIO VENDITE GRE
    $elencoDateDaInviare = [['data' => '2019-05-24'],['data' => '2019-05-25'],['data' => '2019-05-26'],['data' => '2019-05-27']];  //<-----------------------------------------------------------------------
	$logger->info("(210) INVIO VENDITE GRE, date da inviare: ".count($elencoDateDaInviare));
	foreach($elencoDateDaInviare as $dataDaInviare) {
		$dataCalcolo = (new DateTime($dataDaInviare['data']))->setTimezone($timeZone);
		$elencoSediDaInviare =  $log->elencoSediDaInviare($dataCalcolo->format('Y-m-d'), 210);
		$vendutoAllaData = $vendite->esportazioneVenditeGreCopre($dataCalcolo->format('Y-m-d'));
		$logger->info('(210) '.$dataCalcolo->format('Y-m-d').', sedi: '.count($elencoSediDaInviare));
		
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
					if ($negozioOld != '' && $negozioOld != $vendita['negozio']) {
						
					}
					
					if ($negozioOld != $vendita['negozio']) {
						$logger->debug('(210) '.$dataCalcolo->format('Y-m-d').', vendite negozio inviate: '.$negozioOld);
						if (! $log->incaricoOk($vendita['negozio'], $dataCalcolo->format('Y-m-d'), 210)) {
							$logger->warn('(210) '.$dataCalcolo->format('Y-m-d').', flag inviate vendite negozio non impostato: '.$negozioOld);
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
                if (key_exists( $vendita['codice'], $elencoBarcodeCopre)) {
                    $mainBarcode = $elencoBarcodeCopre[$vendita['codice']];
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
				$riga .= $mainBarcode."|";
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
				$riga .= "9999|9999|||".$sede."|SUPERMEDIA|02147260174|||||||||DET|0,01|0,01|0,01|0|0|0|0|0|0|0|0|1||2999999999999|6672941|SAMSUNG|1GB EGOKIT|1|0,01|0|0,01|0,01|VEN||0\r\n";
				$righe[] = $riga;
				
				$log->incaricoOk($sede, $dataCalcolo->format('Y-m-d'), 210);
				$logger->debug('(210) '.$dataCalcolo->format('Y-m-d').', negozio inviato: '.$sede.', vendite: 0');
			}
		}
		
		$nome_file = 'SO_02147260174_SM_'.$dataCalcolo->format('Ymd').'.txt';
		file_put_contents("$cartellaDiInvio/$nome_file", $righe);
		$logger->debug('(210) '.$dataCalcolo->format('Y-m-d').', file inviato');
	}
	
	$elencoDateDaInviare = $log->elencoGiornateDaInviare(230); //220 = INVIO GIACENZE COPRE, 230 = INVIO GIACENZE GRE
	$elencoDateDaInviare = [['data' => '2019-05-24'],['data' => '2019-05-25'],['data' => '2019-05-26'],['data' => '2019-05-27']]; //<-----------------------------------------------------------------------
	$logger->info("(230) INVIO GIACENZE GRE, date da inviare: ".count($elencoDateDaInviare));
	foreach($elencoDateDaInviare as $dataDaInviare) {
		$dataCalcolo = (new DateTime($dataDaInviare['data']))->setTimezone($timeZone);
		$giacenzeAllaData = $giacenze->giacenzeAllaDataGreCopre($dataCalcolo->format('Y-m-d'));
		$elencoSediDaInviare =  $log->elencoSediDaInviare($dataCalcolo->format('Y-m-d'), 230); 
		$logger->info('(230) '.$dataCalcolo->format('Y-m-d').', sedi: '.count($elencoSediDaInviare) );
		
		$righe = [];
		foreach($giacenzeAllaData as $codiceNegozio => $recordNegozio) {
			
			
			if (array_key_exists($codiceNegozio, $elencoSediDaInviare)) {
				$logger->debug('(230) '.$dataCalcolo->format('Y-m-d').', invio stock negozio: '.$codiceNegozio.', articoli: '.count($recordNegozio));
				foreach($recordNegozio as $codiceArticolo => $recordArticolo) {

                    // inserisco il barcode principale di copre
                    $mainBarcode = $recordArticolo['ean'];
                    if (key_exists( $codiceArticolo, $elencoBarcodeCopre)) {
                        $mainBarcode = $elencoBarcodeCopre[$codiceArticolo];
                    }

					$riga = '';
					//$riga .= $dataCorrente->format('dmY H:i').'|'.$dataCalcolo->format('dmY').'|';
					$riga .= $dataCorrente->format('dmY 00:00').'|'.$dataCalcolo->format('dmY').'|';
					$riga .= 'SUPERMEDIA|02147260174|';
					$riga .= $codiceNegozio."||";
					$riga .= $mainBarcode."|";
					$riga .= $codiceArticolo."|";
					$riga .= $recordArticolo['linea']."|";
					$riga .= $recordArticolo['modello']."|";
					$riga .= $recordArticolo['giacenza']."|".$recordArticolo['giacenza']."|0||0,00\r\n";
					
					$righe[] = $riga;
				}
				if ( ! $log->incaricoOk($codiceNegozio, $dataCalcolo->format('Y-m-d'), 230) ) {
					$logger->warn('(230) '.$dataCalcolo->format('Y-m-d').', flag inviato stock negozio non impostato: '.$codiceNegozio);
				}
			}
		}
		
		$nome_file = 'ST_02147260174_SM_'.$dataCalcolo->format('Ymd').'.txt';
		file_put_contents("$cartellaDiInvio/$nome_file", $righe);
		$logger->debug('(230) '.$dataCalcolo->format('Y-m-d').', file inviato');
		
		// creazione semaforo verde
		touch("$cartellaDiInvio/CO_02147260174_SM.txt");
		
	}
	