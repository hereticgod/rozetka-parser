<?php

class Model {
    private $dbh;
    private $configFile = 'config.ini';
    private $tableName = 'items';

    public function __construct(){
        $this->dbh = $this->get_dbh();
        if (PHP_SAPI === 'cli'){
            $this->try_create_tables();
        }
    }

    public function add_item($keyword, $data){
        $stm = $this->dbh->prepare("INSERT INTO {$this->tableName} (keyword, data) VALUES (?, ?);");
        $stm->execute(array($keyword, $data));
    }

    /**
     * For your convenient you can just type 'yes'
     * for creating needed table.
     */
    private function try_create_tables(){
        $sqlItemsTable = "CREATE TABLE {$this->tableName}
        (
        id INTEGER(10) UNSIGNED AUTO_INCREMENT,
        keyword VARCHAR(100),
        data TEXT,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8";
        $conf = parse_ini_file($this->configFile);
        $stm = $this->dbh->prepare("SHOW TABLES FROM {$conf['dbname']} LIKE '{$this->tableName}';");
        $stm->execute();
        if ( ! $stm->rowCount()){
            print "Table for items doesn't exist! Do you want to create it?  Type 'yes' to continue: ";
            $handle = fopen ("php://stdin","r");
            $line = fgets($handle);
            fclose($handle);
            if(trim($line) == 'yes'){
                $this->dbh->query($sqlItemsTable);
            }
        }
        return true;
    }

    /**
     * Get PDO instance. 
     * 
     * @return PDO
     */
    private function get_dbh(){
        try {
            $dbConfig = parse_ini_file($this->configFile);
            $dbh = new PDO("{$dbConfig['driver']}:".
                    "host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8",
                    $dbConfig['user'], $dbConfig['passwd'],
                    array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
            $dbh->exec("SET CHARACTER SET utf8");
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            print "Error: " . $e->getMessage() . "<br/>";
            die;
        }
        return $dbh;
    }
}
