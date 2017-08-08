<?php
/**
 LICENSE

 Copyright (C) 2016 Teclib'
 Copyright (C) 2010-2016 by the FusionInventory Development Team.

 This file is part of Flyve MDM Plugin for GLPI.

 Flyve MDM Plugin for GLPi is a subproject of Flyve MDM. Flyve MDM is a mobile
 device management software.

 Flyve MDM Plugin for GLPI is free software: you can redistribute it and/or
 modify it under the terms of the GNU Affero General Public License as published
 by the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.
 Flyve MDM Plugin for GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU Affero General Public License for more details.
 You should have received a copy of the GNU Affero General Public License
 along with Flyve MDM Plugin for GLPI. If not, see http://www.gnu.org/licenses/.
 ------------------------------------------------------------------------------
 @author    Thierry Bugier Pineau
 @copyright Copyright (c) 2016 Flyve MDM plugin team
 @license   AGPLv3+ http://www.gnu.org/licenses/agpl.txt
 @link      https://github.com/flyve-mdm/flyve-mdm-glpi
 @link      http://www.glpi-project.org/
 ------------------------------------------------------------------------------
*/
namespace Glpi\Test;
use atoum;

class CommonDBTestCase extends atoum {

   protected function drop_database($dbuser='', $dbhost='', $dbdefault='', $dbpassword=''){

      $cmd = $this->construct_mysql_options($dbuser, $dbhost, $dbpassword, 'mysql');

      if (is_array($cmd)) {
         return $cmd;
      }

      $cmd = 'echo "DROP DATABASE IF EXISTS \`'.$dbdefault .'\`; CREATE DATABASE \`'.$dbdefault.'\`" | ' . $cmd ." 2>&1";

      $returncode = 0;
      $output = array();
      exec(
         $cmd,
         $output,
         $returncode
      );
      array_unshift($output,"Output of '{$cmd}'");
      return array(
         'returncode'   => $returncode,
         'output'       => $output
      );
   }

   protected function load_mysql_file($dbuser='', $dbhost='', $dbdefault='', $dbpassword='', $file = NULL) {

      if (!file_exists($file)) {
         return array(
            'returncode' => 1,
            'output' => array("ERROR: File '$file' does not exist !")
         );
      }

      $result = $this->construct_mysql_options($dbuser, $dbhost, $dbpassword, 'mysql');

      if (is_array($result)) {
         return $result;
      }

      $cmd = $result . " " . $dbdefault . " < ". $file ." 2>&1";

      $returncode = 0;
      $output = array();
      exec(
            $cmd,
            $output,
            $returncode
            );
      array_unshift($output, "Output of '$cmd'");
      return array(
         'returncode'   => $returncode,
         'output'       => $output
      );
   }

   protected function construct_mysql_options($dbuser='', $dbhost='', $dbpassword='', $cmd_base='mysql') {
      $cmd = array();

      if ( empty($dbuser) || empty($dbhost)) {
         return array(
            'returncode' => 2,
            'output' => array("ERROR: missing mysql parameters (user='{$dbuser}', host='{$dbhost}')")
         );
      }
      $cmd = array($cmd_base);

      if (strpos($dbhost, ':') !== FALSE) {
         $dbhost = explode( ':', $dbhost);
         if ( !empty($dbhost[0]) ) {
            $cmd[] = "--host ".$dbhost[0];
         }
         if ( is_numeric($dbhost[1]) ) {
            $cmd[] = "--port ".$dbhost[1];
         } else {
            // The dbhost's second part is assumed to be a socket file if it is not numeric.
            $cmd[] = "--socket ".$dbhost[1];
         }
      } else {
         $cmd[] = "--host ".$dbhost;
      }

      $cmd[] = "--user ".$dbuser;

      if (!empty($dbpassword)) {
         $cmd[] = "-p'".urldecode($dbpassword)."'";
      }

      return implode(' ', $cmd);
   }

   protected function mysql_dump($dbuser = '', $dbhost = '', $dbpassword = '', $dbdefault = '', $file = NULL) {
      if (is_null($file) or empty($file)) {
         return array(
            'returncode' => 1,
            'output' => array("ERROR: mysql_dump()'s file argument must neither be null nor empty")
         );
      }

      if (empty($dbdefault)) {
         return array(
            'returncode' => 2,
            'output' => array("ERROR: mysql_dump() is missing dbdefault argument.")
         );
      }

      $result = self::construct_mysql_options($dbuser, $dbhost, $dbpassword, 'mysqldump');
      if (is_array($result)) {
         return $result;
      }

      $cmd = $result . ' --opt '. $dbdefault.' > ' . $file;
      $returncode = 0;
      $output = array();
      exec(
            $cmd,
            $output,
            $returncode
            );
      array_unshift($output, "Output of '{$cmd}'");
      return array(
            'returncode'=>$returncode,
            'output' => $output
      );
   }

   /**
    * compare a .sql schema against the database
    * @param string $pluginname
    * @param string Tables of the t ested DB having this string in their name are checked
    * @param string $when
    */
   public function checkInstall($filename = '', $filter = 'glpi_', $when = '') {
      global $DB;

      if ($filename == '') {
         return;
      }

      // See http://joefreeman.co.uk/blog/2009/07/php-script-to-compare-mysql-database-schemas/
      $file_content = file_get_contents($filename);
      $a_lines = explode("\n", $file_content);

      $a_tables_ref = array();
      $current_table = '';
      foreach ($a_lines as $line) {
         if (strstr($line, "CREATE TABLE ") || strstr($line, "CREATE VIEW ")) {
            $matches = array();
            preg_match("/`(.*)`/", $line, $matches);
            $current_table = $matches[1];
         } else {
            if (preg_match("/^`/", trim($line))) {
               $line = preg_replace('/\s+/', ' ',$line);
               $s_line = explode("`", $line);
               $s_type = explode("COMMENT", $s_line[2]);
               $s_type[0] = trim($s_type[0]);
               $s_type[0] = str_replace(" COLLATE utf8_unicode_ci", "", $s_type[0]);
               $s_type[0] = str_replace(" CHARACTER SET utf8", "", $s_type[0]);
               if (strpos(trim($s_type[0]), 'text') === 0
                   || strpos(trim($s_type[0]), 'longtext') === 0) {
                 $s_type[0] = str_replace(" DEFAULT NULL", "", $s_type[0]);
               }
               $a_tables_ref[$current_table][$s_line[1]] = str_replace(",", "", $s_type[0]);
            }
         }
      }

      // * Get tables from MySQL
      $a_tables_db = array();
      $a_tables = array();
      // SHOW TABLES;
      $query = "SHOW TABLES LIKE '$filter%'";
      $result = $DB->query($query);
      while ($data = $DB->fetch_array($result)) {
         $data[0] = str_replace(" COLLATE utf8_unicode_ci", "", $data[0]);
         $data[0] = str_replace("( ", "(", $data[0]);
         $data[0] = str_replace(" )", ")", $data[0]);
         $a_tables[] = $data[0];
      }

      foreach($a_tables as $table) {
         $query = "SHOW CREATE TABLE ".$table;
         $result = $DB->query($query);
         while ($data=$DB->fetch_array($result)) {
            $a_lines = explode("\n", $data['Create Table']);

            foreach ($a_lines as $line) {
               if (strstr($line, "CREATE TABLE ")
                     OR strstr($line, "CREATE VIEW")) {
                        $matches = array();
                        preg_match("/`(.*)`/", $line, $matches);
                        $current_table = $matches[1];
                     } else {
                        if (preg_match("/^`/", trim($line))) {
                           $line = preg_replace('/\s+/', ' ',$line);
                           $s_line = explode("`", $line);
                           $s_type = explode("COMMENT", $s_line[2]);
                           $s_type[0] = trim($s_type[0]);
                           $s_type[0] = str_replace(" COLLATE utf8_unicode_ci", "", $s_type[0]);
                           $s_type[0] = str_replace(" CHARACTER SET utf8", "", $s_type[0]);
                           $a_tables_db[$current_table][$s_line[1]] = str_replace(",", "", $s_type[0]);
                        }
                     }
            }
         }
      }

      $a_tables_ref_tableonly = array();
      foreach ($a_tables_ref as $table=>$data) {
         $a_tables_ref_tableonly[] = $table;
      }
      $a_tables_db_tableonly = array();
      foreach ($a_tables_db as $table=>$data) {
         $a_tables_db_tableonly[] = $table;
      }

      // Compare
      $tables_toremove = array_diff($a_tables_db_tableonly, $a_tables_ref_tableonly);
      $tables_toadd = array_diff($a_tables_ref_tableonly, $a_tables_db_tableonly);

      // See tables missing or to delete
      $this->integer(count($tables_toadd))->isEqualTo(0, "Tables missing $when " . print_r($tables_toadd, TRUE));
      $this->integer(count($tables_toremove))->isEqualTo(0, "Tables to delete $when " . print_r($tables_toremove, TRUE));

      // See if fields are same
      foreach ($a_tables_db as $table=>$data) {
         if (isset($a_tables_ref[$table])) {
            $fields_toremove = array_diff_assoc($data, $a_tables_ref[$table]);
            $fields_toadd = array_diff_assoc($a_tables_ref[$table], $data);
            $diff = "======= DB ============== Ref =======> ".$table."\n";
            $diff .= print_r($data, TRUE);
            $diff .= print_r($a_tables_ref[$table], TRUE);

            // See tables missing or to delete
            $this->integer(count($fields_toadd))->isEqualTo(0, "Fields missing/not good during $when $table " . print_r($fields_toadd, TRUE)." into ".$diff);
            $this->integer(count($fields_toremove))->isEqualTo(0, "Fields to delete during  $when $table " . print_r($fields_toremove, TRUE)." into ".$diff);
         }
      }
   }

}
