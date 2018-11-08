<?php

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

    if(array_key_exists("help", $options)){
        echo "Prints help";
    }elseif( array_key_exists("h", $options) && array_key_exists("p", $options) && array_key_exists("u", $options)){
        echo "Makes connection to DB";
        if(array_key_exists("create_table", $options)){
            echo "Creates table";
        }else{
            if(array_key_exists("file", $options)) {
                echo "Parse file";
            } else {
                echo "No file specified";
            }
        }
    }else {
        print("You didn't use the script properly. To transfer data to DB remember to specift: user, database, host and source file.\n  Please type --help to see how to use the script");
    }


?>