<?php
/**
* This file is part of the FGFS-METAR-Proxy project (https://github.com/hbeni/fgfs-metar-proxy)
* Copyright (c) 2021 Benedikt Hallinger
* 
* This program is free software: you can redistribute it and/or modify  
* it under the terms of the GNU General Public License as published by  
* the Free Software Foundation, version 3.
*
* This program is distributed in the hope that it will be useful, but 
* WITHOUT ANY WARRANTY; without even the implied warranty of 
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU 
* General Public License for more details.
*
* You should have received a copy of the GNU General Public License 
* along with this program. If not, see <http://www.gnu.org/licenses/>.
***********************************************************************
*
* This is a small METAR proxy for the FlightGear flight simulator.
*
* It will proxy reqeusts to another data provider, but local overrides take precedence.
*
* You can experimentally test this locally with PHPs internal webserver:
*   fgfs-metar-proxy$ php -S localhost:8080 -t .
* Then the proxy is serviced at http://localhost:8080/
* Finally start flightgear by adding to your launcher:
*  --prop:/environment/realwx/metar-url=http://localhost:8080/[station]`
*/



/*
* Load central config, if present
*/
if (is_readable(dirname(__FILE__).'/config.ini')) {
    $ini_config = parse_ini_file(dirname(__FILE__).'/config.ini', true);
    $ini_config = sanitize($ini_config);
} else {
    print("ERROR: ".dirname(__FILE__).'/config.ini'." no such file or not readable");
    exit(1);
}

$datadir = dirname(__FILE__).'/data/';


/* Show upload form */
function showForm() {
return <<<UPLOAD_FORM
    <html>
        <header>
            <title>FlightGear METAR submission page</title>
        </header>
        <body>
            <h1>FlightGear METAR submission page</h1>
            <p>
            <form action="?upload" method="POST">
                <label for="metar">METAR string:</label>
                <input type="text" id="metar" name="metar">

                <br><br><input type="submit">
            </form>
            </p>
            <p>
            <b>Examples:</b><br>
              <ul>
                <li><code>2021/06/03 16:20 EDFF 031620Z AUTO 05090KT 010V080 9999 // OVC140/// 24/11 Q1021</code></li>
                <li><code>XXXX 031620Z AUTO 05090KT 010V080 9999 // OVC140/// 24/11 Q1021</code></li>
              </ul>
            </p>
        </body>
    </html>
UPLOAD_FORM;
}

/**
* UPLOAD page in case requested
*/
if (array_key_exists('upload', $_GET)) {
    if ($_POST['metar']) {
        /* process new uploaded data */
        
        // fetch the station from the METAR string
        //   ex1: 2021/06/03 16:20 EDFF 031620Z AUTO 05090KT 010V080 9999 // OVC140/// 24/11 Q1021
        //   ex2: XXXX 031620Z AUTO 05090KT 010V080 9999 // OVC140/// 24/11 Q1021
        if (strlen($_POST['metar']) <= 256 && preg_match('/(?:(\d{4}\/\d{2}\/\d{2}) (\d{2}:\d{2}) )?([a-sA-Z0-9]{4}) (\d{6}Z) .+/', $_POST['metar'], $sm)) {
            $station = sanitize($sm[3]);
            $metar   = sanitize($_POST['metar']);
            $station_file = $datadir.$station.".TXT";
            file_put_contents($station_file, $metar);
            print("stored METAR.<br><br>STATION: <code>$station</code><br>TEXT (".strlen($metar)."): <code>$metar</code>");
            exit(0);
        } else {
            print("unable to parse supplied METAR string. Check syntax.");
            print(showForm());
            exit(1);
        }

    } else {
        print(showForm());
    }
    exit(0);
}



/**
* Service METAR:
*  - in case the station has a local override:
*    - if its valid, serve it
*    - if its outdated, delete it
* - in case not: redirect proxy results
*/

// Clear out any outdated station files
$metarfiles = scandir($datadir);
$ttl = ($ini_config['upload']['ttl'])?$ini_config['upload']['ttl']:14400;
foreach ($metarfiles as $f) {
    $cfile = $datadir.'/'.$f;
    if (preg_match('/.TXT$/', $cfile)) {
        $lastChange = filectime($cfile);
        $stillValid = ($lastChange + $ttl >= time())?true:false;
        if (!$stillValid) unlink($cfile);
    }
}


// Get the requested station from the URL
if (!preg_match('/([a-zA-Z0-9]+)$/', $_SERVER['REQUEST_URI'], $sm)) {
    print("ERROR: could not extract station from URI ".$_SERVER['REQUEST_URI']);
    exit(1);
}
$station = sanitize($sm[1]);

// Check if a present station file is here.
$station_file = $datadir.$station.".TXT";
if (is_readable($station_file)) {
    // Serve its contents
    print(file_get_contents($station_file));
    exit(0);

} else {
    // redirect the request to the configured provider
    if ($ini_config['provider']['url']) {
        $redirectTGT = str_replace("%s", $station, sanitize($ini_config['provider']['url']));
        header('Location: '.$redirectTGT);
        exit(0);
    } else {
        print("ERROR: provider URL not defined in config!");
        exit(1);
    }
}



/************************* LIBS ****************************/

/*
* make the values HTML-secure
*/
function sanitize($v) {
    if (is_array($v)) {
        foreach ($v as $kv => $vv) {
            $v[$kv] = sanitize($vv);
        }
        return $v;
    } else {
        // TODO: ASCII only
        // TODO: Check basic METAR syntax
        return(htmlentities($v));
    }
}

?>
