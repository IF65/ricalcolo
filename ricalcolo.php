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
	
	//Logger::configure(__DIR__.'/Database/config.xml');
	Logger::configure($logConfig);
	$logger = Logger::getLogger("ricalcolo");
  
	$logger->info("Inizio procedura ricalcolo.");
	sleep(3);
	
	use Database\Tables\Giacenzainiziale;
	use Database\Tables\Giacenze;
	use Database\Tables\Vendite;
	use Database\Tables\Arrivi;
	use Database\Tables\Trasferimentiin;
	use Database\Tables\Trasferimentiout;
	use Database\Tables\Diversi;

    // impostazioni debug
    //--------------------------------------------------------------------------------
    $codiceArticoloAnalizzato = '0848293';

	// creazione ogetti
	//--------------------------------------------------------------------------------
	$giacenzeIniziali = new Giacenzainiziale($sqlDetails);
	$giacenze = new Giacenze($sqlDetails);
	$arrivi = new Arrivi($sqlDetails);
	$vendite = new Vendite($sqlDetails);
	$trasferimentiIn = new Trasferimentiin($sqlDetails);
	$trasferimentiOut = new Trasferimentiout($sqlDetails);
	$diversi = new Diversi($sqlDetails);
	$logger->debug("Oggetti creati.");
	sleep(3);
	
    // impostazioni periodo
	//--------------------------------------------------------------------------------
    $timeZone = new DateTimeZone('Europe/Rome');

	$end = (new DateTime())->sub(new DateInterval('P1D'));
	$start = new DateTime('January 1, '.$end->format('Y'));
	$interval = new DateInterval('P1D');
	$range = new DatePeriod($start, $interval, $end);

	// serpentone
	//--------------------------------------------------------------------------------
	$logger->info("Inizio serpentone.");
	sleep(3);
	if ($giacenze->creaTabellaGiacenzePerRicalcolo()) {
		// carico le giacenze iniziali
		$situazioni = $giacenzeIniziali->ricerca(['anno_attivo' => $start->format('Y')]);
		foreach ($range as $date) {
			$logger->info($date->format('Y-m-d'));

			// carico gli arrivi
			$elencoArrivi = $arrivi->movimenti(["data" => $date->format('Y-m-d')]);
			foreach ($elencoArrivi as $codice => $arrivo) {
			    if (! key_exists($codice, $situazioni)) {
					$situazioni[$codice] = [];
				}
				foreach ($arrivo as $negozio => $quantita) {
                    if (!key_exists( $negozio, $situazioni[$codice] )) {
                        $situazioni[$codice][$negozio] = 0;
                    }

                    $situazioni[$codice][$negozio] += $quantita;

                    if ($codiceArticoloAnalizzato == $codice) {
                        $logger->debug( $date->format( 'Y-m-d' ) . ', ' . $codice . '/' . $negozio . 'arrivi: ' . $quantita . ' => sit: ' . $situazioni[$codice][$negozio] );
                    }
                }
			}
			unset($elencoArrivi);
			
			// carico i trasferimenti in ingresso
			$elencoTrasferimentiIn = $trasferimentiIn->movimenti(["data" => $date->format('Y-m-d')]);
			foreach ($elencoTrasferimentiIn as $codice => $trasferimento) {
                if (! key_exists($codice, $situazioni)) {
					$situazioni[$codice] = [];
				}
				foreach ($trasferimento as $negozio => $quantita) {
                    if (!key_exists( $negozio, $situazioni[$codice] )) {
                        $situazioni[$codice][$negozio] = 0;
                    }

                    $situazioni[$codice][$negozio] += $quantita;

                    if ($codiceArticoloAnalizzato == $codice) {
                        $logger->debug( $date->format( 'Y-m-d' ) . ', ' . $codice . '/' . $negozio . 'trasf.in: ' . $quantita . ' => sit: ' . $situazioni[$codice][$negozio] );
                    }
                }
			}

			unset($elencoTrasferimentiIn);
			
			// carico/scarico i diversi
			$elencoDiversi = $diversi->movimenti(["data" => $date->format('Y-m-d')]);
			foreach ($elencoDiversi as $codice => $diverso) {
                if (! key_exists($codice, $situazioni)) {
					$situazioni[$codice] = [];
				}
				foreach ($diverso as $negozio => $quantita) {
                    if (!key_exists( $negozio, $situazioni[$codice] )) {
                        $situazioni[$codice][$negozio] = 0;
                    }
                    $situazioni[$codice][$negozio] -= $quantita;

                    if ($codiceArticoloAnalizzato == $codice) {
                        $logger->debug( $date->format( 'Y-m-d' ) . ', ' . $codice . '/' . $negozio . 'diversi: ' . $quantita . ' => sit: ' . $situazioni[$codice][$negozio] );
                    }
                }
			}
			$logger->debug($date->format('Y-m-d').', diversi: '.count($elencoDiversi));
			unset($elencoDiversi);
			
			// scarico i trasferimenti in uscita
			$elencoTrasferimentiOut = $trasferimentiOut->movimenti(["data" => $date->format('Y-m-d')]);
			foreach ($elencoTrasferimentiOut as $codice => $trasferimento) {
                if (! key_exists($codice, $situazioni)) {
					$situazioni[$codice] = [];
				}
				foreach ($trasferimento as $negozio => $quantita) {
                    if (!key_exists( $negozio, $situazioni[$codice] )) {
                        $situazioni[$codice][$negozio] = 0;
                    }

                    $situazioni[$codice][$negozio] -= $quantita;
                    if ($codiceArticoloAnalizzato == $codice) {
                        $logger->debug( $date->format( 'Y-m-d' ) . ', ' . $codice . '/' . $negozio . 'trasf.out: ' . $quantita . ' => sit: ' . $situazioni[$codice][$negozio] );
                    }
                }
			}
			$logger->debug($date->format('Y-m-d').', trasf.out: '.count($elencoTrasferimentiOut));
			unset($elencoTrasferimentiOut);
			
			// scarico le vendite
			$elencoVendite = $vendite->movimenti(["data" => $date->format('Y-m-d')]);
			foreach ($elencoVendite as $codice => $vendita) {
                if (! key_exists($codice, $situazioni)) {
					$situazioni[$codice] = [];
				}
				foreach ($vendita as $negozio => $quantita) {
                    if (!key_exists( $negozio, $situazioni[$codice] )) {
                        $situazioni[$codice][$negozio] = 0;
                    }

                    $situazioni[$codice][$negozio] -= $quantita;

                    if ($codiceArticoloAnalizzato == $codice) {
                        $logger->debug( $date->format( 'Y-m-d' ) . ', ' . $codice . '/' . $negozio . 'vendite: ' . $quantita . ' => sit: ' . $situazioni[$codice][$negozio] );
                    }
                }
			}
			$logger->debug($date->format('Y-m-d').', vendite: '.count($elencoVendite));
			unset($elencoVendite);
			
			$giacenze->caricaSituazioni($date, $situazioni);
			$logger->debug($date->format('Y-m-d').', situazioni caricate.');
		}
		$giacenze->creaGiacenzeCorrenti();
		$logger->info('giacenze correnti create');
	}
	$giacenze->eliminaTabelleTemporaneeeRicalcolo();
	$logger->debug('tabelle eliminate/rinominate');
	
	$logger->info("Fine procedura ricalcolo.");
	//$json = json_encode($situazioni, true);
	//file_put_contents("/Users/if65/Desktop/dati.json", $json);
	
