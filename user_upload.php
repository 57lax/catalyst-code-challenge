<?php

    define('NAME_POSITION', 0); 
    define('SURNAME_POSITION', 1);
    define('EMAIL_POSITION', 2);

    define('TABLE_NAME', "users");
    define('MAX_NAME', 50);
    define('MAX_SURNAME', 150);
    define('MAX_EMAIL', 50);

    $shortopts  = "";
    $shortopts .= "u:";
    $shortopts .= "p:";
    $shortopts .= "h:";

    $longopts  = array(
        "file:",            
        "create_table::",
        "help",
        "dry_run",
    );
    $options = getopt($shortopts, $longopts);

    function connect_to_DB($servername, $user, $password){
        $dsn_options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

        try { 
            $db_connection = new PDO($servername, $user, $password, $dsn_options);
        } catch (PDOException $error) {
            echo 'DB connection error: ' . $error->getMessage();
        }

        return $db_connection;
    }

    function tableExists($pdo, $table) {
        try {
            $result = $pdo->query("SELECT 1 FROM $table LIMIT 1");
        } catch (Exception $e) {
            return FALSE;
        }

        return $result !== FALSE;
    }

    function drop_table(&$pdo){
        try {
            $statement = $pdo->prepare('DROP TABLE '.TABLE_NAME.';');
            $statement->execute();
        } catch(PDOException $e) {
            echo 'Error while dropping the table: ' .$e->getMessage();
        }
        echo "Table dropped \n";
    }

    function create_table(&$pdo){
        $create_table = true; 
        if(tableExists($pdo, TABLE_NAME)){
            print("Table already exists. Would you like to drop current table and create new one? All the data will be lost (y/n) ");
            $ans = fgets(STDIN);
            if(preg_replace('/\s+/', '',$ans) == "y") {
                drop_table($pdo);
            }else {
                $create_table = false;
            }
        }

        if($create_table){
            try {
                $sql ="CREATE TABLE ". TABLE_NAME."(
                    email VARCHAR(".MAX_EMAIL.") NOT NULL PRIMARY KEY,
                    name VARCHAR(".MAX_NAME."), 
                    surname VARCHAR(".MAX_SURNAME."));";
                $pdo->exec($sql);
                echo "Created a new table: ".TABLE_NAME."\n";
            } catch(PDOException $e) {
                echo 'Error while creating table: ' .$e->getMessage();
            }
        }
    }

    function parse_file($file_name, $dry_run, &$pdo){
        $row_no = 0;
        $emails = array();
        if(($handle = fopen($file_name, "r")) !== FALSE){
            while(($data = fgetcsv($handle, 1000, ",")) !== FALSE){
                if($row_no){
                    $data = correct_row($data);

                    if($dry_run) {
                        display($data, $row_no);
                    } else {
                    }
                }
                $row_no++;
            }
        }
        fclose($handle);
    }

    function display($data, $row_no){
        display_row($data, $row_no);
        echo " \n";
    }

    function display_row($data, $row_no) {
        echo $row_no.". ".$data[NAME_POSITION]." " .$data[SURNAME_POSITION]." \t".$data[EMAIL_POSITION]."\t";
    }

    function correct_row($data){
        $data[NAME_POSITION] = ucfirst(strtolower(preg_replace('/\s+/', '', $data[NAME_POSITION])));
        $data[SURNAME_POSITION] = ucfirst(strtolower(preg_replace('/\s+/', '', $data[SURNAME_POSITION])));
        $data[EMAIL_POSITION] = strtolower(preg_replace('/\s+/', '', $data[EMAIL_POSITION]));
        return $data;
    }


    function print_help(){
        echo "Available directives: \n";
        echo "--file <file>\t – the name of the CSV to be parsed \n";
        echo "--create_table\t – builds MySQL users table \n";
        echo "--dry_run\t – used with the --file directive in the instance that we want to run the script but not insert into the DB.
                            All other functions will be executed, but the database won't be altered. \n";
        echo "-u\t\t – MySQL username \n";
        echo "-p\t\t – MySQL password \n";
        echo "-h\t\t – MySQL host \n";
        echo "--help\t\t – output the above list of directives with details \n";
    }

    if(array_key_exists("help", $options)){
        print_help();
    }elseif( array_key_exists("h", $options) && array_key_exists("p", $options) && array_key_exists("u", $options)){
        $pdo = connect_to_DB($options["h"], $options["u"], $options["p"]);
        if(array_key_exists("create_table", $options)){
            create_table($pdo);
        }else{
            if(array_key_exists("file", $options)) {
                parse_file($options["file"], TRUE, $pdo);
            } else {
                echo "No file specified";
            }
        }
    }else {
        print("You didn't use the script properly. To transfer data to DB remember to specift: user, database, host and source file.\n  Please type --help to see how to use the script");
    }


?>