<?php
    namespace Database;

	use \PDO;

    class Database {

        protected $pdo = null;

        public function __construct($sqlDetails) {
            $conStr = sprintf("mysql:host=%s", $sqlDetails['host']);
            try {
                $this->pdo = new PDO($conStr, $sqlDetails['user'], $sqlDetails['password']);

                self::createDatabase($sqlDetails['db']);

            } catch (PDOException $e) {
                die($e->getMessage());
            }
        }

        public function createDatabase($db) {
        	try {
					//creo il database
                    $this->pdo->exec("create database if not exists `$db`;")
                        or die(print_r($this->pdo->errorInfo(), true));

					//.. e lo imposto come db di default
					$this->pdo->exec("use `$db`;");

                    return true;
            } catch (PDOException $e) {
                die("DB ERROR: ". $e->getMessage());
            }
        }

        public function __destruct() {
            $this->pdo = null;
        }
    }
?>
