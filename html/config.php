<?php
  $config_base="/etc/radar/";
  //var_dump($_POST);
  //var_dump($_FILES);
  function chk_chnged($name) {
    return (isset($_POST[$name])&&isset($_POST["Orig".$name])&&($_POST[$name]!=$_POST["Orig".$name]));
  }
  if (! function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle) {
      if (strpos($haystack, $needle) === false)
        return false;
      else
        return true;
    }
  }

  $message="";

  if(count($_POST)>0) {
    $restart_radar = 0;
    $file_changed = 0;
    if(isset($_POST['submit-changes'])&&('Save Changes' == $_POST['submit-changes'])&&
       isset($_POST['update_list'])&&('' != $_POST['update_list'])) {
      $config = yaml_parse_file( "$config_base/radar.conf");	// Read in current config
      if(!isset($_POST['save_ver'])||($_POST['save_ver']!=htmlspecialchars($config['save_ver']))) {
        if($_POST['save_ver']<htmlspecialchars($config['save_ver']))
          $message = "<font color=\"#c00000\"> Save Failed: Miss-matched config file - form from an older version </font>";
        else
          $message = "<font color=\"#c00000\"> Save Failed: Miss-matched config file </font>";
      }
      else {
        if ($_POST['update_list'] != ';DefaultReport') {
          if(chk_chnged('Title'))	{ $config['title'] = $_POST['Title']; };
          if(chk_chnged('Comment'))	{ $config['comment'] = $_POST['Comment']; };
          if(chk_chnged('Device'))	{ $config['device'] = $_POST['Device'];				$restart_radar=1; };
          if(chk_chnged('Debug'))	{ $config['debug'] = intval($_POST['Debug']);			$restart_radar=1; };
          if(chk_chnged('RunGap'))	{ $config['run_gap'] = intval($_POST['RunGap'] * 100);		$restart_radar=1; };
          if(chk_chnged('Direction'))	{ $config['direction'] = intval($_POST['Direction']);		$restart_radar=1; };
          if(chk_chnged('Angle'))	{ $config['angle'] = intval($_POST['Angle']);			$restart_radar=1; };
          if(chk_chnged('MinSpeed'))	{ $config['min_speed'] = intval($_POST['MinSpeed']);		$restart_radar=1; };
          if(chk_chnged('Sensitivity'))	{ $config['sensitivity'] = intval($_POST['Sensitivity']);	$restart_radar=1; };
          if(chk_chnged('Rate'))	{ $config['rate'] = intval($_POST['Rate']);			$restart_radar=1; };
          if(chk_chnged('Units'))	{ $config['units'] = intval($_POST['Units']);			$restart_radar=1; };
          if(chk_chnged('Port'))	{ $config['port'] = intval($_POST['Port']);			$restart_radar=1; };
          $config['save_ver']++;
          if (yaml_emit_file("$config_base/_radar.conf", $config))
            if (rename("$config_base/_radar.conf", "$config_base/radar.conf")) {
              $message = $message . "<font color=\"#00a000\"> Config Saved </font>";
              $file_changed = 1;
            }
            else {
              $errors = error_get_last();
              $message = $message . "<font color=\"#c00000\"> Save Failed: " . $errors['message'] . "</font>";
            }
          else {
            $errors = error_get_last();
            $message = $message . "<font color=\"#c00000\"> Save Failed: " . $errors['message'] . "</font>";
          }
        }
      }
    }
    if(isset($_POST['submit-save1'])&&('Save' == $_POST['submit-save1'])) {
      if(isset($_POST['SaveName'])&&('' != $_POST['SaveName'])) {
        $name=$_POST['SaveName'];
        if (!ctype_print($name)) {
          $message = "<font color=\"#c00000\"> Save Failed: Illegal File Name  ctype_alnum '$name'</font>";
        }
        elseif (!preg_match('/^(?:[a-z0-9_-]|\.(?!\.))+$/iD', $name)) {
          $message = "<font color=\"#c00000\"> Save Failed: Illegal File Name  regex '$name'</font>";
        }
        else {
          if (copy("$config_base/radar.conf", "$config_base/$name.conf")) {
            $message = "<font color=\"#00a000\"> Config Saved </font>";
          }
          else {
            $errors = error_get_last();
            $message = "<font color=\"#c00000\"> Save Failed: " . $errors['message'] . "</font>";
          }
        }
      }
      else {
        $message = "<font color=\"#c00000\"> No save name specified </font>";
      }
    }
    if(isset($_POST['submit-load'])&&('Load' == $_POST['submit-load'])) {
      if(isset($_POST['LoadConfig'])&&('' != $_POST['LoadConfig'])) {
        $name=$_POST['LoadConfig'];
        if (!ctype_print($name) || !preg_match('/^(?:[a-z0-9_-]|\.(?!\.))+$/iD', $name)) {
          $message = "<font color=\"#c00000\"> Load Failed: Illegal File Name </font>";
        }
        else {
          if (copy("$config_base/$name", "$config_base/radar.conf")) {
            $message = "<font color=\"#00a000\"> Config Loaded </font>";
            $file_changed = 1;
            $restart_radar = 1;
          }
          else {
            $errors = error_get_last();
            $message = "<font color=\"#c00000\"> Load Failed: " . $errors['message'] . "</font>";
          }
        }
      }
      else {
        $message = "<font color=\"#c00000\"> No save name specified </font>";
      }
    }
    if(isset($_POST['submit-really'])&&('Really' == $_POST['submit-really'])) {
      if(isset($_POST['LoadConfig'])&&('' != $_POST['LoadConfig'])) {
        $name=$_POST['LoadConfig'];
        if (!ctype_print($name) || !preg_match('/^(?:[a-z0-9_-]|\.(?!\.))+$/iD', $name)) {
          $message = "<font color=\"#c00000\"> Delete Failed: Illegal File Name </font>";
        }
        else {
          if (unlink("$config_base/$name")) {
            $message = "<font color=\"#00a000\"> Config Deleted </font>";
          }
          else {
            $errors = error_get_last();
            $message = "<font color=\"#c00000\"> Delete Failed: " . $errors['message'] . "</font>";
          }
        }
      }
      else {
        $message = "<font color=\"#c00000\"> No save name specified </font>";
      }
    }
    if (is_array($_FILES) && isset($_FILES["Upload_Config"]) && is_array($_FILES["Upload_Config"])
      && (0 == $_FILES["Upload_Config"]["error"])){
      $config = yaml_parse_file($_FILES["Upload_Config"]["tmp_name"]);
      if (false === $try_config) {
        $message = "<font color=\"#c00000\"> Uploaded file not valid </font>";
      }
      elseif (isset($config['timing']['inputs'])) {
          if (rename($_FILES["Upload_Config"]["tmp_name"], "$config_base/radar.conf")) {
            $message = "<font color=\"#00a000\"> Config Loaded </font>";
            $file_changed = 1;
            $restart_radar = 1;
          }
          else {
            $errors = error_get_last();
            $message = "<font color=\"#c00000\"> Load Failed: " . $errors['message'] . "</font>";
          }
      }
    }

    if ($file_changed == 1) {
      if ($restart_radar >= 1) {
        unset($results);
        if (!(false === exec("sudo /usr/bin/systemctl restart radar_socket.service 2>&1", $results, $rc)) && ($rc == 0)) {
          $message = $message."<br><font color=\"#00a000\"> Timing service restarted </font>";
        }
        else {
          $error_text="";
          foreach($results as $num => $line) $error_text=$error_text."$line<br>";
          if(!(strpos($error_text, "sudo: ")===false)) $error_text="sudo not correctly setup";
          $message = $message."<br><font color=\"#c00000\"> Timing restart failed: $rc: $error_text</font>";
        }
      }
    }
  }

  unset($config);
  $config = yaml_parse_file( "$config_base/radar.conf");

  $dir_undef = '<option value="0">Both</option> <option value="1">Approaching</option> <option value="2">Receding</option>';
  $dir[0] = '<option value="0" selected>Both</option> <option value="1">Approaching</option> <option value="2">Receding</option>';
  $dir[1] = '<option value="0">Both</option> <option value="1" selected>Approaching</option> <option value="2">Receding</option>';
  $dir[2] = '<option value="0">Both</option> <option value="1">Approaching</option> <option value="2" selected>Receding</option>';
  $units_undef = '<option value="0">km/h</option> <option value="1">mph</option> <option value="2">m/s</option>';
  $units[0] = '<option value="0" selected>km/h</option> <option value="1">mph</option> <option value="2">m/s</option>';
  $units[1] = '<option value="0">km/h</option> <option value="1" selected>mph</option> <option value="2">m/s</option>';
  $units[2] = '<option value="0">km/h</option> <option value="1">mph</option> <option value="2" selected>m/s</option>';
  $off = '<option value="false" selected> Off </option> <option value="true"> On </option>';
  $on = '<option value="false"> Off </option> <option value="true" selected> On </option>';

  $safe_title="";
  $safe_comment="";
  $safe_dev_path="";
  $safe_debug="";
  $safe_run_gap="";
  $safe_direction="bogus";
  $safe_direction_opt=$dir_undef;
  $safe_units="bogus";
  $safe_units_opt=$units_undef;
  $safe_angle="";
  $safe_min_speed="";
  $safe_sensitivity="";
  $safe_rate="";
  $safe_port="";
  $safe_save_ver="0";
  if (false === $config) {
    $message = "<font color=\"#c00000\"> No Config File </font>";
  }
  else {
    $safe_title=htmlspecialchars($config['title'],ENT_QUOTES);
    $safe_comment=htmlspecialchars($config['comment'],ENT_QUOTES);
    $safe_dev_path=htmlspecialchars($config['device'],ENT_QUOTES);
    if (isset($config['debug']))
      $safe_debug=htmlspecialchars($config['debug']);
    if (isset($config['run_gap']))
      $safe_run_gap=intval($config['run_gap']) / 100;
    if (isset($config['min_speed']))
      $safe_min_speed=htmlspecialchars($config['min_speed']);
    if (isset($config['angle']))
      $safe_angle=htmlspecialchars($config['angle']);
    if (isset($config['direction'])) {
      $safe_direction=intval($config['direction']);
      if (isset($dir[$safe_direction]))
        $safe_direction_opt=$dir[$safe_direction];
    }
    if (isset($config['units'])) {
      $safe_units=intval($config['units']);
      if (isset($units[$safe_units]))
        $safe_units_opt=$units[$safe_units];
    }
    if (isset($config['sensitivity']))
      $safe_sensitivity=htmlspecialchars($config['sensitivity']);
    if (isset($config['rate']))
      $safe_rate=htmlspecialchars($config['rate']);
    if (isset($config['port']))
      $safe_port=htmlspecialchars($config['port']);

    $safe_save_ver=htmlspecialchars($config['save_ver']);
  }

  $possible_configs=scandir("$config_base", SCANDIR_SORT_ASCENDING);
  // var_dump($possible_configs);
  $conf_file_list="<option value=\"\" selected>&nbsp; -- Select config file -- &nbsp; </option>";
  foreach($possible_configs as $conf_num => $conf_file) {
    // echo "$conf_file_list  <br>\n";
    // echo "$conf_num  :  $conf_file     ";
    if (substr($conf_file,0,1) == ".") { continue ; };
    if ($conf_file == "timing.conf") { continue ; };
    $contents = yaml_parse_file( "$config_base/$conf_file");
    if (!(false === $contents) && isset($contents['title'])) {
      $title = $contents['title'];
      $comment = $contents['comment'];
      $conf_file_list = $conf_file_list . "<option value=\"$conf_file\" title=\"$comment\"> $conf_file &nbsp; : &nbsp; $title </option>";
    }
  }
  // var_dump($result_list);
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Configuration</title>
    <link rel="stylesheet" href="style.css">
<?php
  $icon_file=dirname(__FILE__) . "/icons.inc";
  if (file_exists($icon_file))
    readfile($icon_file);
?>
  </head>
<body>
  <div style="float:right">
   <a href="/">Main Menu</a>&nbsp; &nbsp; 
  </div>
 <div align="center" style="padding-bottom:5px;">
  <h2>Configuration</h2>
 </div>
  <form name="frmConfig" id="frmConfig" method="post" action="">
    <input type="hidden" name="update_list" value="" id="update_list">
    <input type="hidden" name="save_ver" value="<?php echo "$safe_save_ver";?>">
  <script type="text/javascript">
    function haveUpdate(){
            update_list="";
            update_count=0;
            orig_val="";
            input_fields=document.getElementsByTagName("input");
            for (let i = 0; i < input_fields.length; i++) {
              if (input_fields[i].name.substr(0,4) == "Orig") {
                new_field=document.getElementById(input_fields[i].name.substr(4));
                if (new_field != null) {
                  if (input_fields[i].value != new_field.value) {
                    update_list=update_list + ";" + input_fields[i].name.substr(4);
                    update_count++;
                  }
                }
              }
            }
            document.getElementById('update_list').value=update_list;
            document.getElementById('submit-changes').disabled=(update_count == 0);
    };
  </script>
  <div class="message"><?php if(isset($message)) { echo $message; } ?> </div>
  <table align=center border="2" cellpadding="4">
<?php
    echo "<tr>\n <th class=\"listheader\"> Config Title </th>\n";
    echo "<td colspan=\"3\"><input type=\"hidden\" name=\"OrigTitle\" value=\"$safe_title\" id=\"OrigTitle\">";
    echo "<input type=\"text\" size=\"30\" placeholder=\"Title\" name=\"Title\" id=\"Title\" class=\"txtField\" required value=\"$safe_title\" oninput=\"haveUpdate()\" ></td>\n";
    echo "</tr>\n";

    echo "<tr>\n <th class=\"listheader\"> Comments </th>\n";
    echo "<td colspan=\"3\"><input type=\"hidden\" name=\"OrigComment\" value=\"$safe_comment\" id=\"OrigComment\">";
    echo "<input type=\"text\" size=\"60\" placeholder=\"Comments\" name=\"Comment\" id=\"Comment\" class=\"txtField\" required value=\"$safe_comment\" oninput=\"haveUpdate()\" ></td>\n";
    echo "</tr>\n";

    echo "<tr>\n <th class=\"listheader\"> Radar Serial Device </th>\n";
    echo "<td colspan=\"1\"><input type=\"hidden\" name=\"OrigDevice\" value=\"$safe_dev_path\" id=\"OrigDevice\">";
    echo "<input type=\"text\" size=\"12\" placeholder=\"Radar Serial Device\" name=\"Device\" id=\"Device\" class=\"txtField\" required value=\"$safe_dev_path\" oninput=\"haveUpdate()\" >\n";
    echo "</td>\n";

    echo "<th class=\"listheader\"> Debug </th>\n";
    echo "<td><input type=\"hidden\" name=\"OrigDebug\" value=\"$safe_debug\" id=\"OrigDebug\">";
    echo "<input type=\"number\" size=\"3\" min=\"0\" max=\"5\" placeholder=\"0\" name=\"Debug\" id=\"Debug\" class=\"input_number\" required value=\"$safe_debug\" oninput=\"haveUpdate()\" ></td>\n";
    echo "</tr>\n";


    echo "<tr>\n <th colspan=\"4\" class=\"listheader\"> Radar Module Configuration </th></tr>\n";

    $dir_width="120px";

    echo "<tr>\n <th class=\"listheader\"> Minimum Speed </th>\n";
    echo "<td><input type=\"hidden\" name=\"OrigMinSpeed\" value=\"$safe_min_speed\" id=\"OrigMinSpeed\">";
    echo "<input type=\"number\" size=\"4\" min=\"1\" max=\"100\" placeholder=\"20\" name=\"MinSpeed\" id=\"MinSpeed\" class=\"input_number\" required value=\"$safe_min_speed\" oninput=\"haveUpdate()\" ></td>\n";

    echo "<th class=\"listheader\"> Direction </th>\n";
    echo "<td><input type=\"hidden\" name=\"OrigDirection\" value=\"$safe_direction\" id=\"OrigDirection\">";
    echo "<select name=\"Direction\" id=\"Direction\" style=\"width: $dir_width\" onchange=\"haveUpdate()\">$safe_direction_opt</select></td>";
    echo "</tr>\n";

    echo "<tr>\n <th class=\"listheader\"> Angle </th>\n";
    echo "<td><input type=\"hidden\" name=\"OrigAngle\" value=\"$safe_angle\" id=\"OrigAngle\">";
    echo "<input type=\"number\" size=\"2\" min=\"0\" max=\"80\" placeholder=\"0\" name=\"Angle\" id=\"Angle\" class=\"input_number\" min=0 value=\"$safe_angle\" oninput=\"haveUpdate()\" ></td>\n";

    echo "<th class=\"listheader\"> Rate </th>\n";
    echo "<td><input type=\"hidden\" name=\"OrigRate\" value=\"$safe_rate\" id=\"OrigRate\">";
    echo "<input type=\"number\" size=\"3\" min=\"0\" max=\"10\" placeholder=\"0\" name=\"Rate\" id=\"Rate\" title=\"lower is faster\" class=\"input_number\" required value=\"$safe_rate\" oninput=\"haveUpdate()\" > 22,11,5/s</td>\n";
    echo "</tr>\n";

    echo "<tr>\n <th class=\"listheader\"> Sensitivity </th>\n";
    echo "<td><input type=\"hidden\" name=\"OrigSensitivity\" value=\"$safe_sensitivity\" title=\"lame comment\" id=\"OrigSensitivity\">";
    echo "<input type=\"number\" size=\"4\" min=\"1\" max=\"15\" placeholder=\"5\" name=\"Sensitivity\" id=\"Sensitivity\" title=\"Lower is more sensitive\" class=\"input_number\" min=0 value=\"$safe_sensitivity\" oninput=\"haveUpdate()\" > 1 - 15 </td>\n";

    echo "<th class=\"listheader\"> Units </th>\n";
    echo "<td><input type=\"hidden\" name=\"OrigUnits\" value=\"$safe_units\" id=\"OrigUnits\">";
    echo "<select name=\"Units\" id=\"Units\" style=\"width: $dir_width\" onchange=\"haveUpdate()\">$safe_units_opt</select></td>";
    echo "</tr>\n";

    echo "<tr>\n <th colspan=\"4\" class=\"listheader\"> Web Server </th></tr>\n";

    echo "<tr>\n <th class=\"listheader\"> Run Gap </th>\n";
    echo "<td><input type=\"hidden\" name=\"OrigRunGap\" value=\"$safe_run_gap\" id=\"OrigRunGap\">";
    echo "<input type=\"number\" size=\"4\" min=\"5\" max=\"100\" placeholder=\"20\" name=\"RunGap\" id=\"RunGap\" class=\"input_number\" required value=\"$safe_run_gap\" oninput=\"haveUpdate()\" >sec</td>\n";

    echo "<th class=\"listheader\"> Port </th>\n";
    echo "<td><input type=\"hidden\" name=\"OrigPort\" value=\"$safe_port\" id=\"OrigPort\">";
    echo "<input type=\"number\" size=\"5\" min=\"1\" max=\"65535\" placeholder=\"251\" name=\"Port\" id=\"Port\" class=\"input_number\" required value=\"$safe_port\" oninput=\"haveUpdate()\" ></td>\n";
    echo "</tr>\n";


    echo "<tr><td colspan=\"1\" align=right style=\"border: 0px\"><input id=\"submit-changes\" type=\"submit\" name=\"submit-changes\" value=\"Save Changes\" disabled formenctype=\"multipart/form-data\"></td>";
    echo "</tr>\n";

?>
  </table>
  <br>
  <div align="center">
   
   <select name="LoadConfig" style="width: 240px" onchange="document.getElementById('submit-load').disabled=(this.value == '');document.getElementById('del').disabled=(this.value == '')"><?php echo $conf_file_list;?></select>
   <input id="submit-load" type="submit" name="submit-load" value="Load" disabled formnovalidate formenctype="multipart/form-data"> &nbsp; 
   <input id="del" type="button" name="del" value="Del" disabled onclick="document.getElementById('submit-really').disabled=false">
   <input id="submit-really" type="submit" name="submit-really" value="Really" disabled formnovalidate formenctype="multipart/form-data"> <br>
   <input type="text" size="30" placeholder="Save Name" name="SaveName" class="txtField" value="" oninput="document.getElementById('submit-save1').disabled=(this.value == '')">
   <input id="submit-save1" type="submit" name="submit-save1" value="Save" disabled formenctype="multipart/form-data"> <br> <br>
   <input type="file" name="Upload_Config" oninput="document.getElementById('submit-upload').disabled=false">
   <input id="submit-upload" type="submit" name="submit" value="Upload" disabled formnovalidate formenctype="multipart/form-data">
   <a href="config_save.php"> Download Config </a>
  </div>
  </form>
 </body>
</html>
