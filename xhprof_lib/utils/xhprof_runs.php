<?php
//
//  Copyright (c) 2009 Facebook
//
//  Licensed under the Apache License, Version 2.0 (the "License");
//  you may not use this file except in compliance with the License.
//  You may obtain a copy of the License at
//
//      http://www.apache.org/licenses/LICENSE-2.0
//
//  Unless required by applicable law or agreed to in writing, software
//  distributed under the License is distributed on an "AS IS" BASIS,
//  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//  See the License for the specific language governing permissions and
//  limitations under the License.
//

//
// This file defines the interface iXHProfRuns and also provides a default
// implementation of the interface (class XHProfRuns).
//

/**
 * iXHProfRuns interface for getting/saving a XHProf run.
 *
 * Clients can either use the default implementation,
 * namely XHProfRuns_Default, of this interface or define
 * their own implementation.
 *
 * @author Kannan
 */
interface iXHProfRuns {

  /**
   * Returns XHProf data given a run id ($run) of a given
   * type ($type).
   *
   * Also, a brief description of the run is returned via the
   * $run_desc out parameter.
   */
  public function get_run($run_id, $type, &$run_desc);

  /**
   * Save XHProf data for a profiler run of specified type
   * ($type).
   *
   * The caller may optionally pass in run_id (which they
   * promise to be unique). If a run_id is not passed in,
   * the implementation of this method must generated a
   * unique run id for this saved XHProf run.
   *
   * Returns the run id for the saved XHProf run.
   *
   */
  public function save_run($xhprof_data, $type, $run_id = null);
}


/**
 * XHProfRuns_Default is the default implementation of the
 * iXHProfRuns interface for saving/fetching XHProf runs.
 *
 * It stores/retrieves runs to/from a filesystem directory
 * specified by the "xhprof.output_dir" ini parameter.
 *
 * @author Kannan
 */
class XHProfRuns_Default implements iXHProfRuns {

  private $dir = '';
  private $suffix = 'xhprof';

  private function gen_run_id($type) {
    return uniqid();
  }

  private function file_name($run_id, $type) {

    $file = "$run_id.$type." . $this->suffix;

    if (!empty($this->dir)) {
      $file = $this->dir . "/" . $file;
    }
    return $file;
  }

  public function __construct($dir = null) {

    // if user hasn't passed a directory location,
    // we use the xhprof.output_dir ini setting
    // if specified, else we default to the directory
    // in which the error_log file resides.

    if (empty($dir)) {
      $dir = ini_get("xhprof.output_dir");
      if (empty($dir)) {

        $dir = sys_get_temp_dir();

        xhprof_error("Warning: Must specify directory location for XHProf runs. ".
                     "Trying {$dir} as default. You can either pass the " .
                     "directory location as an argument to the constructor ".
                     "for XHProfRuns_Default() or set xhprof.output_dir ".
                     "ini param.");
      }
    }
    $this->dir = $dir;
  }

  public function get_run($run_id, $type, &$run_desc) {
    $file_name = $this->file_name($run_id, $type);

    if (!file_exists($file_name)) {
      xhprof_error("Could not find file $file_name");
      $run_desc = "Invalid Run Id = $run_id";
      return null;
    }

    $contents = file_get_contents($file_name);
    $run_desc = "XHProf Run (Namespace=$type)";
    return unserialize($contents);
  }

  public function save_run($xhprof_data, $type, $run_id = null) {

    // Use PHP serialize function to store the XHProf's
    // raw profiler data.
    $xhprof_data = serialize($xhprof_data);

    if ($run_id === null) {
      $run_id = $this->gen_run_id($type);
    }

    $file_name = $this->file_name($run_id, $type);
    $file = fopen($file_name, 'w');

    if ($file) {
      fwrite($file, $xhprof_data);
      fclose($file);
    } else {
      xhprof_error("Could not open $file_name\n");
    }

    // echo "Saved run in {$file_name}.\nRun id = {$run_id}.\n";
    return $run_id;
  }

  public function get_run_files() {
    $files = glob("{$this->dir}/*.{$this->suffix}");
    usort($files, function($a,$b) {
        return filemtime($b) - filemtime($a);
    });
    return $files;
  }

  public function get_run_list() {
    $files = $this->get_run_files();
    $list = array();
    foreach ($files as $file) {
      list($run,$source) = explode('.', basename($file));
      $list[] = array( 'run' => $run , 'source' => $source , 'file' => $file );
    }
    return $list;
  }


  public function get_run_files_by_source() {
    $files = $this->get_run_files();
    $sources = array();
    foreach( $files as $file ) {
      list($run,$source) = explode('.', basename($file));
      $sources[$source][] = array( 'run' => $run , 'file' => $file, 'source' => $source );
    }
    return $sources;
  }

  public function list_sources() {
    $sources = $this->get_run_files_by_source();
    echo '<h3>Sources</h3>';
    echo '<ul>';
    foreach ($sources as $source => $runs) {
      echo '<li><a href="?source='. $source .'">'.$source.'</a></li>';
    }
    echo '</ul>';
  }

  public function list_runs() {
    if ( ! is_dir($this->dir) ) {
      return;
    }
 
    if ( ! isset($_GET['source']) ) {
      $this->list_sources();
      return;
    }
 
    echo '<form method="GET">';

    echo 'Source: <input type="text" name="source" value="'. $_GET['source'] .'"/>';

    echo "<hr/>Existing runs:\n";

    echo '<input type="submit" name="compare" value="Compare"/>';

    echo "<table>\n";
    echo '<tr>';
    echo '<th>Run1</th>';
    echo '<th>Run2</th>';
    echo '<th>Source File</th>';
    echo '<th>Time</th>';
    echo '</tr>';

    $files = $this->get_run_files();
    foreach ($files as $file) {
      list($run,$source) = explode('.', basename($file));

      if ( $source !== $_GET['source'] ) {
        continue;
      }
      $htmlized_run = htmlentities($run);

      echo '<tr>';
      echo '<td align="center"><input type="radio" name="run1" value="' . $htmlized_run . '"/></td>';
      echo '<td align="center"><input type="radio" name="run2" value="' . $htmlized_run . '"/></td>';
      echo '<td><a href="' . htmlentities($_SERVER['SCRIPT_NAME'])
          . '?run=' . $htmlized_run . '&source='
          . htmlentities($source) . '">'
          . htmlentities(basename($file)) . "</a>"
          . "</td>\n";
      echo '<td><small><time>' . date("Y-m-d H:i:s", filemtime($file))  . '</time></small></td>';
      echo '</tr>';
    }
    echo "</table>\n";
    echo '</form>';
  }
}
