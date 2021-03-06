<?php
// Copyright (C) 2015 Remy van Elst

// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

function crl_verify($raw_cert_data, $verbose=true) {
    global $random_blurp;
    $cert_data = openssl_x509_parse($raw_cert_data);
    $cert_serial_nm = strtoupper(bcdechex($cert_data['serialNumber']));   
    $crl_uris = [];
    $crl_uri = explode("\nFull Name:\n ", $cert_data['extensions']['crlDistributionPoints']);
    foreach ($crl_uri as $key => $uri) {
        if (!empty($uri) ) {
            $uri = explode("URI:", $uri);
            foreach ($uri as $key => $crluri) {
                if (!empty($crluri) ) {
                    $crl_uris[] = preg_replace('/\s+/', '', $crluri);
                }
            }
        }
    }
    foreach ($crl_uris as $key => $uri) {
        if (!empty($uri)) {
            if (0 === strpos($uri, 'http')) {
                $fp = fopen ("/tmp/" . $random_blurp .  "." . $key . ".crl", 'w+');
                $ch = curl_init(($uri));
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                if(curl_exec($ch) === false)
                {
                    echo '<pre>Curl error: ' . htmlspecialchars(curl_error($ch)) ."</pre>";
                }
                curl_close($ch);
                if(stat("/tmp/" . $random_blurp .  "." . $key . ".crl")['size'] < 10 ) {
                    return false;
                } 
                $crl_text = shell_exec("openssl crl -noout -text -inform der -in /tmp/" . $random_blurp .  "." . $key . ".crl 2>&1");

                $crl_last_update = shell_exec("openssl crl -noout -lastupdate -inform der -in /tmp/" . $random_blurp .  "." . $key . ".crl");

                $crl_next_update = shell_exec("openssl crl -noout -nextupdate -inform der -in /tmp/" . $random_blurp .  "." . $key . ".crl");

                unlink("/tmp/" . $random_blurp .  "." . $key . ".crl");

                if ( strpos($crl_text, "unable to load CRL") === 0 ) {
                    if ( $verbose ) {
                        $result = "<span class='text-danger glyphicon glyphicon-exclamation-sign'></span> - <span class='text-danger'>CRL invalid. (" . $uri . ")</span><br><pre> " . htmlspecialchars($crl_text) . "</pre>";
                        return $result;
                    } else {
                        $result = "<span class='text-danger glyphicon glyphicon-remove'></span>";
                        return $result;
                    }
                }

                $crl_info = explode("Revoked Certificates:", $crl_text)[0];

                $crl_certificates = explode("Revoked Certificates:", $crl_text)[1];

                $crl_certificates = explode("Serial Number:", $crl_certificates); 
                $revcert = array('bla' => "die bla");
                foreach ($crl_certificates as $key => $revoked_certificate) {
                    if (!empty($revoked_certificate)) {
                        $revcert[str_replace(" ", "", explode("\n", $revoked_certificate)[0])] = str_replace("        Revocation Date: ", "", explode("\n", $revoked_certificate)[1]);
                    }
                }
                if( array_key_exists($cert_serial_nm, $revcert) ) {
                    if ( $verbose ) {
                        $result = "<span class='text-danger glyphicon glyphicon-exclamation-sign'></span> - <span class='text-danger'>REVOKED on " . $revcert[$cert_serial_nm] . ". " . $uri . "</span><br><pre>        " . $crl_last_update . "        " . $crl_next_update . "</pre>";
                    } else {
                        $result = "<span class='text-danger glyphicon glyphicon-remove'></span>";
                    }
                } else {
                    if ( $verbose ) {
                        $result = "<span class='text-success glyphicon glyphicon-ok-sign'></span> <span class='text-success'> - " . $uri . "</span><br><pre>        " . $crl_last_update . "        " . $crl_next_update . "</pre>";
                    } else {
                        $result = "<span class='text-success glyphicon glyphicon-ok'></span>";
                    }
                }
                return $result;
            }
        }
    }
}

?>