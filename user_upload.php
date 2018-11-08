<?php

    define('NAME_POSITION', 0); 
    define('SURNAME_POSITION', 1);
    define('EMAIL_POSITION', 2);

    define('TABLE_NAME', "users");
    define('MAX_NAME', 50);
    define('MAX_SURNAME', 150);
    define('MAX_EMAIL', 50);

    define('CRITICAL_ERROR', "CRITICAL ERROR");
    define('WARNING', "WARNING");

    define('INVALID_EMAIL', array("message" => 'Email has incorrect format', "type"=>CRITICAL_ERROR));
    define('MISSING_EMAIL', array("message" => 'Email is missing', "type"=>CRITICAL_ERROR));
    define('NONUNIQUE_EMAIL', array("message" => 'Email is not unique', "type"=>CRITICAL_ERROR));
    define('VALUE_TOO_LONG', array("message" => 'One of the values is too long', "type"=>CRITICAL_ERROR));
    define('MISSING_NAME', array("message" => 'Name is missing', "type"=>WARNING) );
    define('MISSING_SURNAME', array("message" => 'Surname is missing', "type"=>WARNING));

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

    /**
     * Establish connection with DB.
     *
     * @param string $servername host name.
     * @param string $user user name for the database.
     * @param string $password password to user account.
     */
    function connectToDb($servername, $user, $password){
        $db_connection = null;
        $dsn_options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

        try { 
            $db_connection = new PDO($servername, $user, $password, $dsn_options);
        } catch (PDOException $error) {
            echo 'DB connection error: ' . $error->getMessage();
        }

        return $db_connection;
    }

    /**
     * Check if a table exists in the current database.
     *
     * @param PDO $pdo PDO instance connected to a database.
     * @param string $table Table to search for.
     * @return bool TRUE if table exists, FALSE if no table found.
     */
    function tableExists($pdo, $table) {
        try {
            $result = $pdo->query("SELECT 1 FROM $table LIMIT 1");
        } catch (Exception $e) {
            return FALSE;
        }

        return $result !== FALSE;
    }

    /**
     * Creates the new table in DB.
     *
     * @param PDO $pdo PDO instance connected to a database.
     * @param string $table Table to search for.
     * @return bool TRUE if table exists, FALSE if no table found.
     */
    function dropTable(&$pdo){
        try {
            $statement = $pdo->prepare('DROP TABLE '.TABLE_NAME.';');
            $statement->execute();
        } catch(PDOException $e) {
            echo 'Error while dropping the table: ' .$e->getMessage();
        }
        echo "Table dropped \n";
    }

    /**
     * Creates the new table in DB.
     *
     * @param PDO $pdo PDO instance connected to a database.
     * @param string $table Table to search for.
     * @return bool TRUE if table exists, FALSE if no table found.
     */
    function createTable(&$pdo){
        $create_table = true; 
        if(tableExists($pdo, TABLE_NAME))
        {
            print("Table already exists. Would you like to drop current table and create new one? All the data will be lost (y/n) ");
            $ans = fgets(STDIN);
            if(preg_replace('/\s+/', '',$ans) == "y") 
            {
                dropTable($pdo);
            }
            else 
            {
                $create_table = false;
            }
        }

        if($create_table)
        {
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

    /**
     * Inesrts one row to the users table.
     *
     * @param array $data Row (user) data.
     * @param PDO $pdo PDO instance connected to a database.
     */
    function insertRow($data, &$pdo){
        $statement = $pdo->prepare('INSERT INTO '.TABLE_NAME.'(email, name, surname) VALUES (:email, :name, :surname)');
    
        $statement->execute([
            'email' => $data[EMAIL_POSITION],
            'name' => $data[NAME_POSITION],
            'surname' => $data[SURNAME_POSITION],
        ]);
    }

    /**
     * Parses the csv file provided in the script parameter.
     *
     * @param array $data Row (user) data.
     * @param bool $dry_run If TRUE we only print the data to the screen witout saving it in DB.
     * @param PDO $pdo PDO instance connected to a database.
     */
    function parseFile($file_name, $dry_run, &$pdo){
        $row_no = 0;
        $emails = array();
        $inserted = 0;
        if(($handle = fopen($file_name, "r")) !== FALSE)
        {
            while(($data = fgetcsv($handle, 1000, ",")) !== FALSE)
            {
                if($row_no)
                {
                    $data = correctRow($data);
                    $validation_data = validate_row($data, $emails);

                    if($dry_run) {
                        display($data, $validation_data, $row_no);
                    } 
                    else 
                    {
                        // Check if validation critical errors exist for the given row
                        $critical_errors = array_values(array_filter($validation_data, function($d) {
                            return $d["type"] === CRITICAL_ERROR;
                        }));

                        // Insert if no errors, do not insert otherwise
                        if(!count($critical_errors) > 0) 
                        {
                            insertRow($data, $pdo);
                            $inserted++;
                        } 
                        else 
                        {
                            echo "Row $row_no NOT INSERTED Reason: ";
                            echo  displayValidation($critical_errors);
                            echo "\n";
                        }
                    }
                }
                $row_no++;
            }
        }
        fclose($handle);
        echo "Total number of ".$inserted. " rows inserted. ";
    }

    /**
     * Prints out the data of the user.
     *
     * @param array $data Row (user) data.
     * @param array $validation Validation data about the fields of the user.
     * @param integer $row_no Validation data about the fields of the user.
     */
    function display($data, $validation, $row_no){
        displayRow($data, $row_no);
        displayValidation($validation);
        echo " \n";
    }

    /**
     * Displays one row of parsed file.
     *
     * @param array $data Row (user) data.
     * @param integer $row_no Row number of data to display.
     */
    function displayRow($data, $row_no) {
        $format = '%4u. %-10s %-15s %-25s';
        echo sprintf($format, $row_no, $data[NAME_POSITION], $data[SURNAME_POSITION], $data[EMAIL_POSITION]);
    }

    /**
     * Displays validation massages stored in an array.
     *
     * @param array $validation Array with validation data.
     */
    function displayValidation($validation){
        $format = '%-15s: %-30s';
        for($i=0; $i<count($validation); $i++){
            echo sprintf($format, $validation[$i]["type"], $validation[$i]["message"]);
        }
    }

    /**
     * Corrects the data in given row (removes whitespaces, capitalize names).
     *
     * @param array $data Row (user) data.
     * @return array $data Corrected user data.
     */
    function correctRow($data){
        $data[NAME_POSITION] = ucfirst(strtolower(preg_replace('/\s+/', '', $data[NAME_POSITION])));
        $data[SURNAME_POSITION] = ucfirst(strtolower(preg_replace('/\s+/', '', $data[SURNAME_POSITION])));
        $data[EMAIL_POSITION] = strtolower(preg_replace('/\s+/', '', $data[EMAIL_POSITION]));
        return $data;
    }

    /**
     * Validates if the value in a row have correct format.
     *
     * @param array $data Row (user) data.
     * @param array $emails emails which has been already processed.
     * @return array $validation_result array with errors or warnings caused by data.
     */
    function validate_row($data, &$emails){
        $validation_result = array();

        if(!preg_match('/^([a-zA-Z0-9_\-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.)|(([a-zA-Z0-9\-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/',$data[EMAIL_POSITION] ))
        {
            array_push($validation_result, INVALID_EMAIL);
        }

        if($data[EMAIL_POSITION] == '') array_push($validation_result, MISSING_EMAIL);

        if($data[NAME_POSITION] == '') array_push($validation_result, MISSING_NAME);

        if($data[SURNAME_POSITION] == '') array_push($validation_result, MISSING_SURNAME);

        if(strlen($data[EMAIL_POSITION]) > MAX_EMAIL 
                || strlen($data[SURNAME_POSITION]) > MAX_SURNAME 
                || strlen($data[NAME_POSITION]) > MAX_NAME) 
            array_push($validation_result, VALUE_TOO_LONG);
        
        if(in_array($data[EMAIL_POSITION], $emails)) 
            array_push($validation_result, NONUNIQUE_EMAIL);
        else 
            array_push($emails, $data[EMAIL_POSITION]);

        return $validation_result;
    }

    /**
     * Prints help information for the script.
     *
     */
    function printHelp(){
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

    if(array_key_exists("help", $options))
    {
        printHelp();
    }
    elseif(array_key_exists("h", $options) && array_key_exists("p", $options) && array_key_exists("u", $options))
    {
        $pdo = connectToDb($options["h"], $options["u"], $options["p"]);
        if($pdo) {
            if(array_key_exists("create_table", $options))
            {
                createTable($pdo);
            }
            else
            {
                if(array_key_exists("file", $options)) 
                {
                    if(file_exists($options["file"]))
                        parseFile($options["file"], array_key_exists("dry_run", $options), $pdo);
                    else
                        echo "File specified does not exist";
                } 
                else 
                {
                    echo "File not specified";
                }
            }
        }
    }
    else
    {
        print("You didn't use the script properly. To transfer data to DB remember to specift: user, database, host and source file.\n  Please type --help to see how to use the script");
    }
?>