<?php

ini_set('max_execution_time', 0);

// ----------------------
// a-blog cms サイトコピーツール ablogcms-copy
// update 2022/09/22
// ----------------------
// 同じサーバーにインストールされた ２つの a-blog cms のサイトのデータのバックアップとコピーを行います。

// --------------------
// 1) 動作許可IP設定
// --------------------
// 利用者のIPアドレスを設定ください。
// チェックしない場合には以下の行をコメントアウトしてください。（非推奨）

 $ip_check = array (
  '::1',
  '123.123.123.123',
  '111.111.111.111'
 );

// --------------------
// X) 移行元 環境設定
// --------------------

// 設置先の config.server.php を読み込み設定されます。

// --------------------
// 2) 移行先環境設定
// --------------------

$system_url_after = "https://test.example.com";
$system_domain_after = "test.example.com";

$database_host_after = "localhost";
$database_name_after = "database_test";
$database_user_after  = "db_username_test";
$database_password_after  = "db_password_test";
$database_prefix_after = "acms_";

// 移行先パス設定 
$system_dir_after = "/home/example/example.com/public_html/test.example.com";

// --------------------
// 3) バックアップ設定
// --------------------

// バックアップ後のディレクトリやファイルを保存する先を指定します。
// コメントアウトで「移行先パス設定」と共通になります。

# $backup_dir_path = "/home/example/example.com/backup";

// バックアップ初期設定
// only : 対象のみをバックアップ
// all  : 対象の全てをバックアップ (未実装) 
// none : バックアップしない 
$backup_ini = "only"; 

// ZIP圧縮
$zip_ini = "true";

// --------------------
// 4) バックアップ設定
// --------------------

// 移行対象 初期チェックボックス設定
// コメントアウトで未選択になります。

$sql_ini = "true";
$archives_ini = "true";
$media_ini = "true";
$storage_ini = "true";
$config_ini = "true";
# $themes_ini = "true";

// --------------------
// 5) コマンド パスの設定
// --------------------

// XSERVER
$mysqldump = "mysqldump";
$mysql = "/usr/bin/mysql";

// さくらのレンタルサーバー
// $mysqldump = "mysqldump";
// $mysql = "/usr/local/bin/mysql";

// macos MAMP
// $mysqldump = "/Applications/MAMP/Library/bin/mysqldump";
// $mysql ="/Applications/MAMP/Library/bin/mysql";


// ------------------------------

# これ以下は修正する必要はありません。

$system_dir_before = realpath('.');

$config_before = $system_dir_before.'/config.server.php';

if (is_file($config_before)) {

  require_once($config_before);

  $database_host_before = DB_HOST;
  $database_name_before = DB_NAME;
  $acount_name_before = DB_USER;
  $acount_password_before = DB_PASS;
  $database_prefix_before = DB_PREFIX;

  // 移行後のドメイン設定
  $system_domain_before = DOMAIN;
  $install = "ok";

} else {

  $install = "ng";
  $database_name_before = "";
  $system_domain_before = "";
}

$YmdHis = date('Ymd_His');

$backup_dir_name = str_replace(".", "_", $system_domain_after);

if (!isset($backup_dir_path)) {
  $backup_dir_path = $system_dir_after;
}

$backup_dir = $backup_dir_path ."/". $backup_dir_name ."_". $YmdHis;
$backup_dir_zip_name = $backup_dir_name ."_". $YmdHis .".zip";
$msg = array();

$setup = "ok";

// --------------------
// 動作IPアドレスチェック
// --------------------

if (isset($ip_check)) {
  if (is_array($ip_check)) { 
    if(!in_array($_SERVER["REMOTE_ADDR"], $ip_check)) {
  ?>
  <!DOCTYPE html>
  <html lang="ja">
  <head><meta charset="UTF-8"><title>a-copy</title>
  <link rel="stylesheet" href="/themes/system/css/acms-admin.min.css">
  <style>
  body { padding : 10px 30px; background-color : #ddd; font-family: Courier; }
  </style></head>
  <body>
  <h1>IPアドレスによるアクセス制限がかかっています</h1>
  <p><strong>1) 動作許可IP設定</strong> に <strong><?php echo $_SERVER["REMOTE_ADDR"]; ?></strong> を追加してください。</p>
  </body>
  </html>
  <?php
    exit;
    }
  }
}

// --------------------
// データベース接続・データ取得
// --------------------

if (isset($database_prefix_before)) {
  $sql = "SELECT sequence_system_version FROM ".$database_prefix_before."sequence";
  try { 
    $dbh = new PDO('mysql:host='.$database_host_before.';dbname='.$database_name_before.'', $acount_name_before, $acount_password_before);
    $stmt = $dbh->query($sql);
    foreach ($stmt as $row) {
      $before_version = $row['sequence_system_version'];
    }
  } catch (PDOException $e) {
    $before_db_error = "<strong>↑ 移行元環境設定のデータベース情報が間違っています。</strong>";
    $setup = "ng";
  }

  $sql = "SELECT config_value FROM ".$database_prefix_before."config WHERE config_key = 'theme'";
  try { 
    $dbh = new PDO('mysql:host='.$database_host_before.';dbname='.$database_name_before.'', $acount_name_before, $acount_password_before);
    $stmt = $dbh->query($sql);
    foreach ($stmt as $row) {
      $theme_before_array[] = $row['config_value'];
    }
    $theme_before = array_unique($theme_before_array);
  } catch (PDOException $e) {

  }
  $dbh = null;

  // 親テーマ 追加
  foreach ($theme_before as $theme) {
    $theme_check = explode("@", $theme);
    $parent_key = array_key_last($theme_check);
    $theme_before[] = $theme_check[$parent_key];
  }
  $theme_before = array_unique($theme_before);

  foreach ($theme_before as $theme) {
    $check_array = explode("@", $theme);
    foreach ($check_array as $data) {
      array_shift($check_array);
      $theme_name = implode("@",$check_array);
      if (!empty($theme_name)) {
        $theme_before[] = $theme_name;
      } 
    }
  }
  $theme_before = array_unique($theme_before);

}

if (isset($database_prefix_after)) {
  $sql = "SELECT sequence_system_version FROM ".$database_prefix_after."sequence";
  try { 
    $dbh = new PDO('mysql:host='.$database_host_after.';dbname='.$database_name_after.'', $database_user_after, $database_password_after);
    $stmt = $dbh->query($sql);
    foreach ($stmt as $row) {
      $after_version = $row['sequence_system_version'];
    }
  } catch (PDOException $e) {
    $after_db_error = "<strong>↑ 移行後のデータベース情報が間違っています。</strong>";
    $setup = "ng";
  }

  $sql = "SELECT config_value FROM ".$database_prefix_after."config WHERE config_key = 'theme'";
  try { 
    $stmt = $dbh->query($sql);
    foreach ($stmt as $row) {
      $theme_after_array[] = $row['config_value'];
    }
    $theme_after = array_unique($theme_after_array);
  } catch (PDOException $e) {

  }
  $dbh = null;
}

  $input_pass = filter_input(INPUT_POST, "dbpass");
  $archives_copy = filter_input(INPUT_POST, "archives");
  $media_copy = filter_input(INPUT_POST, "media");
  $config_copy = filter_input(INPUT_POST, "config");
  $sql_copy = filter_input(INPUT_POST, "sql");
  $storage_copy = filter_input(INPUT_POST, "storage");
  $themes_copy = filter_input(INPUT_POST, "themes");
  $backup = filter_input(INPUT_POST, "backup");
  $zip = filter_input(INPUT_POST, "zip");

  if (!isset($input_pass)) {

    if (isset($archives_ini)) { $archives_copy = $archives_ini; }
    if (isset($media_ini)) { $media_copy = $media_ini; }
    if (isset($config_ini)) { $config_copy = $config_ini; }
    if (isset($sql_ini)) { $sql_copy = $sql_ini; }
    if (isset($storage_ini)) { $storage_copy = $storage_ini; }
    if (isset($themes_ini)) { $themes_copy = $themes_ini; }
    if (isset($backup_ini)) { $backup = $backup_ini; }
    if (isset($zip_ini)) { $zip = $zip_ini; }

  } elseif ($input_pass != $database_password_after) {

    array_push($msg,"データベースのパスワードが間違っています。");

  } else {

      // ------------------------
      // バックアップファイル コピー
      // ------------------------

      mkdir ($backup_dir);

      // $command = 'cp -r '. $system_dir_after . '/archives '. $backup_dir . "/archives";

      if ($archives_copy == "true" ) {
        $command ='mv '. $system_dir_after . '/archives ' . $backup_dir . "/archives";
        exec($command);
        $command ='mv '. $system_dir_after . '/archives_rev ' . $backup_dir . "/archives_rev";
        exec($command);
      }
      
      if ($media_copy == "true" ) {
        $command ='mv '. $system_dir_after . '/media ' . $backup_dir . "/media";
        exec($command);
      }

      if ($storage_copy == "true" ) {
        $command ='mv '. $system_dir_after . '/storage ' . $backup_dir . "/storage";
        exec($command);
      }
      
      if ($config_copy == "true") {
        mkdir ($backup_dir."/private");
        $command ='mv '. $system_dir_after . '/private/config.system.yaml ' . $backup_dir . "/private/config.system.yaml";
        exec($command);
      }

      if ($themes_copy == "true") {

        mkdir ($backup_dir."/themes");

        foreach ($theme_before as $theme) {
          if (is_dir($system_dir_after.'/themes/'.$theme)) {
            $command ='mv '. $system_dir_after . '/themes/'.$theme .' '. $backup_dir . '/themes/'.$theme;
            exec($command);
          }
        }
      }

    if ($sql_copy == "true") {

      // ------------------------
      // データベース エクスポート 移行元
      // ------------------------

      $sql_fileName = $YmdHis . '_databasedump.sql';

      $sql_filePath = $backup_dir .'/'. $sql_fileName;

      $command_dump = $mysqldump. ' --single-transaction --default-character-set=binary ' . $database_name_before . ' --host=' . $database_host_before . ' --user=' . $acount_name_before . ' --password=' . $acount_password_before . ' > ' . $sql_filePath;

      exec($command_dump);

      // ------------------------
      // データベース エクスポート 移行後 (バックアップ)
      // ------------------------

      $sql_fileName_after = $database_name_after .'_'. $YmdHis . '.sql';

      $sql_filePath_after = $backup_dir .'/' . $sql_fileName_after;

      $command_dump = $mysqldump. ' --single-transaction --default-character-set=binary ' . $database_name_after . ' --host=' . $database_host_after . ' --user=' . $database_user_after . ' --password=' . $database_password_after . ' > ' . $sql_filePath_after;
    
      exec($command_dump);

      // ------------------------
      // データベース インポート
      // ------------------------

      $command_restore = "$mysql -u $database_user_after -p$database_password_after -h $database_host_after $database_name_after < $sql_filePath";

      exec($command_restore);

      // SQL 削除

      unlink($sql_filePath);

      // ---------------------------
      // データベース ドメイン名書き換え
      // ---------------------------

      try {
        $dbh = new PDO('mysql:host='.$database_host_after.';dbname='.$database_name_after.'', $database_user_after, $database_password_after);
        $sql = sprintf("UPDATE %sblog SET blog_domain = '%s'",$database_prefix_after,$system_domain_after);
        $dbh->query($sql);
        $dbh = null;

      } catch (PDOException $e) {
        print "エラー!: " . $e->getMessage();
        die();
      }
    }

    // ------------------------
    // ファイルをコピー
    // ------------------------

    if ($archives_copy == "true" ) {
      $command ='cp -r '. $system_dir_before . '/archives ' . $system_dir_after . "/archives";
      exec($command);
      $command ='cp -r '. $system_dir_before . '/archives_rev ' . $system_dir_after . "/archives_rev";
      exec($command);
    }

    if ($media_copy == "true") {
      $command ='cp -r '. $system_dir_before . '/media ' . $system_dir_after . "/media";
      exec($command);
    }

    if ($storage_copy == "true") {
      $command ='cp -r '. $system_dir_before . '/storage ' . $system_dir_after . "/storage";
      exec($command);
    }

    if ($config_copy == "true") {
      $command ='cp -r '. $system_dir_before . '/private/config.system.yaml ' . $system_dir_after . "/private/config.system.yaml";
      exec($command);
    }

    if ($themes_copy == "true") {

      foreach ($theme_before as $theme) {
        $command ='cp -r '. $system_dir_before . '/themes/'.$theme .' '. $system_dir_after . '/themes/'.$theme;
        exec($command);
      }
    }

    if ($backup == "none") {

      dir_shori("delete", $backup_dir);

    } elseif ($zip == "true") {

      $command_zip =  "cd ". $backup_dir .";".
      "zip -r -q ". $backup_dir_path."/".$backup_dir_zip_name ." .";

      exec($command_zip);
      dir_shori("delete", $backup_dir);

      array_push($msg, $backup_dir_path." に、圧縮したバックアップファイルを作成しました。");
    } else {
      array_push($msg, $backup_dir_path." に、バックアップディレクトリーを作成しました。");
    }
    
    // キャッシュの削除
    $cache_dir = $system_dir_after."/cache";
    $htaccessData = "RemoveHandler php php4 php5 php51 php52 php53 php54 rb py pl cgi phps phtml shtml html inc\n";
    if (is_dir($cache_dir)) {
      dir_shori("delete", $cache_dir);
      mkdir($cache_dir);
      file_put_contents($cache_dir."/.htaccess", $htaccessData, FILE_APPEND | LOCK_EX);
    }

    array_push($msg,"<strong>コピー作業が終了しました。</strong>");
  }

?>
<!DOCTYPE html>
    <html lang="ja">
    <head>
    <meta charset="UTF-8">
    <title>a-copy</title>
    <link rel="stylesheet" href="/themes/system/css/acms-admin.min.css">
    <style>
      body {
        padding : 10px 30px;
        background-color : #ddd;
        font-family: Courier;
      }
      .error {
        color: #aa0000;
        font-weight: bold;
      }
    </style>
    </head>
    <body>
    <h1>a-copy</h1>

    <p>同じサーバーにインストールされた ２つの a-blog cms のサイトのデータのバックアップとコピーを行います。</p>

    <?php 
    if (!isset($ip_check)) { 
      echo "<p><strong>IPアドレスの制限がかかっていません。</strong></p>
      <p>"; 
      echo $_SERVER["REMOTE_ADDR"]."（現在のアクセスIPアドレス）</p>";
    } 
    ?>

    <h2>移行元</h2>
    <ul>
        <li>Domain: <?php echo $system_domain_before; ?></li>
        <li>Path: <?php echo $system_dir_before; ?></li>
        <li>DB: <?php echo $database_name_before; ?>
        <?php 
        if (isset($before_version)) { 
          echo "<li>a-blog cms: ". $before_version."</li>";
        } else { 
          echo "<li>".$before_db_error."</li>"; 
        }  
        if (isset($theme_before)) {
          echo "<li>Themes: ". implode(",", $theme_before)."</li>";
        }
        ?>
    </ul>
    <h2>移行先</h2>
    <ul>
    <?php 

    if ($install == "ng") {

      echo "<li><strong>\$system_dir_after=\"$system_dir_after\" のパスの設定が間違っているか、正しく a-blog cms がインストールされておりません。</strong></li>";

    } else {
    ?>
        <li>Domain: <?php echo $system_domain_after; ?></li>
        <li>Path: <?php echo $system_dir_after; ?></li>
        <li>DB: <?php echo $database_name_after;
        if (!isset($after_version)) { echo $after_db_error; } ?></li> 
        <li>a-blog cms: <?php echo $after_version; ?></li>
        <li>Themes: <?php echo implode(",", $theme_after); ?></li>
    </ul> 

    <?php

  if ($setup == "ok") {
?>
    <h2>対象</h2>

    <form action="#submit" method="POST" class="acms-admin-form">

    <div style="margin-left: 25px;">
      <div><input type="checkbox" name="sql" id="sql_checkbox" value="true" <?php if ($sql_copy == "true") { echo "checked=\"checked\""; } ?>> <label for="sql_checkbox"> database (mysql)</label></div>
      <div><input type="checkbox" name="archives" id="archives_checkbox" value="true" <?php if ($archives_copy == "true") { echo "checked=\"checked\""; } ?>> <label for="archives_checkbox"> archives / archives_rev</label></div>
      <div><input type="checkbox" name="media" id="media_checkbox" value="true" <?php if ($media_copy == "true") { echo "checked=\"checked\""; } ?>> <label for="media_checkbox"> media</label></div>
      <div><input type="checkbox" name="storage" id="storage_checkbox" value="true" <?php if ($storage_copy == "true") { echo "checked=\"checked\""; } ?>> <label for="storage_checkbox"> storage</label></div>
      <div><input type="checkbox" name="config" id="config_checkbox" value="true" <?php if ($config_copy == "true") { echo "checked=\"checked\""; } ?>> <label for="config_checkbox"> private/config.system.yaml</label></div>
      <div><input type="checkbox" name="themes" id="themes_checkbox" value="true" <?php if ($themes_copy == "true") { echo "checked=\"checked\""; } ?>> <label for="themes_checkbox"> themes</label></div>
  </div>
  <h2>バックアップ</h2>
  <select name="backup">
    <option value="only" <?php if ($backup == "only") { echo "selected=\"selected\""; } ?>>対象のみをバックアップ</option>
    <!-- <option value="all" <?php if ($backup == "all") { echo "selected=\"selected\""; } ?>>対象以外もバックアップ</option> -->
    <option value="none" <?php if ($backup == "none") { echo "selected=\"selected\""; } ?>>バックアップしない</option>
  </select>

  <input type="checkbox" name="zip" id="zip_checkbox" value="true" <?php if ($zip == "true") { echo "checked=\"checked\""; } ?>> <label for="zip_checkbox"> ZIP圧縮</label>
    
  <h2 id="submit">コピー作業</h2>
  <p>認証のために、移行先のデータベースのパスワードを入力してください。</p>
  <p>
      <input type="password" name="dbpass" id="dbpass" value="<?php echo $input_pass; ?>" class="acms-admin-form-width-mini">
      <input type="submit" class="acms-admin-btn" value="実行" onclick="javascript:return confirm('データが上書きされ <?php echo $system_domain_after; ?> のデータが削除されます。\n本当に、よろしかったでしょうか？')">
   </form>
<?php
    foreach ( $msg as $text ) {
      echo "<p>".$text."</p>";
    }
  }
}
?>
  </body></html>
<?php

// --------------------------------------------------
// ディレクトリを操作 function ( move / copy / delete )
// --------------------------------------------------

function dir_shori ($shori, $nowDir , $newDir="") {

  if ($shori != "delete") {
    if (!is_dir($newDir)) {
      mkdir($newDir);
    }
  }

  if (is_dir($nowDir)) {
    if ($handle = opendir($nowDir)) {
      while (($file = readdir($handle)) !== false) {
        if ($file != "." && $file != "..") {
          if ($shori == "copy") {
            if (is_dir($nowDir."/".$file)) {
              dir_shori("copy", $nowDir."/".$file, $newDir."/".$file);
            } else {
              copy($nowDir."/".$file, $newDir."/".$file);
            }
          } elseif ($shori == "move") {
            rename($nowDir."/".$file, $newDir."/".$file);
          } elseif ($shori == "delete") {
            if (filetype($nowDir."/".$file) == "dir") {
              dir_shori("delete", $nowDir."/".$file, "");
            } else {
              unlink($nowDir."/".$file);
            }
          }
        }
      }
      closedir($handle);
    }
  }

  if ($shori == "move" || $shori == "delete") {
    rmdir($nowDir);
  }

  return true;
}
